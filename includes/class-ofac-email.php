<?php
/**
 * Email handling class
 *
 * @package OcadeFusion_AnythingLLM_Chatbot
 * @since 1.1.0
 */

if ( ! defined( 'ABSPATH' ) ) {
    exit;
}

/**
 * Class OFAC_Email
 * Handles callback requests and email sending
 */
class OFAC_Email {

    /**
     * Single instance
     *
     * @var OFAC_Email|null
     */
    private static $instance = null;

    /**
     * Get single instance
     *
     * @return OFAC_Email
     */
    public static function get_instance() {
        if ( null === self::$instance ) {
            self::$instance = new self();
        }
        return self::$instance;
    }

    /**
     * Constructor
     */
    private function __construct() {
        $this->init_hooks();
    }

    /**
     * Initialize hooks
     */
    private function init_hooks() {
        add_action( 'wp_ajax_ofac_request_callback', array( $this, 'handle_callback_request' ) );
        add_action( 'wp_ajax_nopriv_ofac_request_callback', array( $this, 'handle_callback_request' ) );
        add_action( 'wp_ajax_ofac_send_reply_email', array( $this, 'handle_send_reply' ) );
        add_action( 'wp_ajax_ofac_add_thread_comment', array( $this, 'handle_add_comment' ) );
        add_action( 'wp_ajax_ofac_test_email', array( $this, 'handle_test_email' ) );
    }

    /**
     * Handle callback request from frontend
     */
    public function handle_callback_request() {
        if ( ! check_ajax_referer( 'ofac_chat_nonce', 'nonce', false ) ) {
            wp_send_json_error( array( 'message' => __( 'Nonce invalide', 'anythingllm-chatbot' ) ), 403 );
        }

        // Rate limiting
        $rate_limiter = new OFAC_Rate_Limiter();
        if ( ! $rate_limiter->check() ) {
            wp_send_json_error( array( 'message' => __( 'Trop de requêtes, veuillez patienter', 'anythingllm-chatbot' ) ), 429 );
        }

        $session_id = isset( $_POST['session_id'] ) ? sanitize_text_field( wp_unslash( $_POST['session_id'] ) ) : '';
        $phone      = isset( $_POST['phone'] ) ? sanitize_text_field( wp_unslash( $_POST['phone'] ) ) : '';
        $message    = isset( $_POST['message'] ) ? sanitize_textarea_field( wp_unslash( $_POST['message'] ) ) : '';
        $email      = '';

        // Si l'utilisateur est connecte, recuperer son email
        if ( is_user_logged_in() ) {
            $email = wp_get_current_user()->user_email;
        }

        if ( empty( $phone ) ) {
            wp_send_json_error( array( 'message' => __( 'Numéro de téléphone requis', 'anythingllm-chatbot' ) ) );
        }

        if ( empty( $message ) ) {
            wp_send_json_error( array( 'message' => __( 'Message requis', 'anythingllm-chatbot' ) ) );
        }

        if ( empty( $session_id ) ) {
            wp_send_json_error( array( 'message' => __( 'Session invalide', 'anythingllm-chatbot' ) ), 400 );
        }

        // Save callback request to database
        $request_id = $this->save_callback_request( $session_id, $email, $phone, $message );

        if ( ! $request_id ) {
            wp_send_json_error( array( 'message' => __( 'Erreur lors de l\'enregistrement', 'anythingllm-chatbot' ) ), 500 );
        }

        // Send notification email to support (non-blocking: failure is logged but doesn't affect user response)
        $email_sent = $this->send_callback_notification( $request_id, $session_id, $email, $phone, $message );

        if ( ! $email_sent ) {
            error_log( '[OFAC] Callback request #' . $request_id . ' saved but notification email failed to send.' );
        }

        wp_send_json_success( array( 'message' => __( 'Demande envoyée', 'anythingllm-chatbot' ) ) );
    }

    /**
     * Save callback request to database
     *
     * @param string $session_id Session ID
     * @param string $email Email
     * @param string $phone Phone
     * @param string $message Message
     * @return int|false Insert ID or false
     */
    private function save_callback_request( $session_id, $email, $phone, $message ) {
        global $wpdb;
        $table = $wpdb->prefix . 'ofac_callback_requests';

        // Get conversation ID
        $conversation_id = $wpdb->get_var(
            $wpdb->prepare(
                "SELECT id FROM {$wpdb->prefix}ofac_conversations WHERE session_id = %s ORDER BY id DESC LIMIT 1",
                $session_id
            )
        );

        $wpdb->insert(
            $table,
            array(
                'conversation_id' => $conversation_id ? $conversation_id : 0,
                'email'           => $email,
                'phone'           => $phone,
                'message'         => $message,
                'status'          => 'pending',
                'created_at'      => current_time( 'mysql' ),
            ),
            array( '%d', '%s', '%s', '%s', '%s', '%s' )
        );

        return $wpdb->insert_id;
    }

    /**
     * Send callback notification email to support
     *
     * @param int    $request_id Request ID
     * @param string $session_id Session ID
     * @param string $email Client email
     * @param string $phone Client phone
     * @param string $message Client message
     * @return bool
     */
    private function send_callback_notification( $request_id, $session_id, $email, $phone, $message ) {
        $settings = OFAC_Settings::get_instance();
        $support_email = $settings->get( 'ofac_support_email', '' );

        if ( empty( $support_email ) ) {
            $support_email = get_option( 'admin_email' );
        }

        // Get conversation ID for admin link
        global $wpdb;
        $conversation_id = $wpdb->get_var(
            $wpdb->prepare(
                "SELECT id FROM {$wpdb->prefix}ofac_conversations WHERE session_id = %s ORDER BY id DESC LIMIT 1",
                $session_id
            )
        );

        $admin_link = '';
        if ( $conversation_id ) {
            $admin_link = admin_url( 'admin.php?page=ofac-logs&open_conversation=' . $conversation_id );
        }

        // Build email
        $site_name = get_bloginfo( 'name' );
        $subject = sprintf(
            /* translators: 1: Site name, 2: Client phone */
            __( '[%1$s] Demande de rappel - %2$s', 'anythingllm-chatbot' ),
            $site_name,
            $phone
        );

        $body = $this->build_callback_email_html( array(
            'email'      => $email,
            'phone'      => $phone,
            'message'    => $message,
            'admin_link' => $admin_link,
            'site_name'  => $site_name,
        ) );

        // Use WordPress filters for Content-Type (more compatible with SMTP plugins)
        $set_html_content_type = function() { return 'text/html'; };
        add_filter( 'wp_mail_content_type', $set_html_content_type );

        $headers = array();
        if ( ! empty( $email ) ) {
            $headers[] = sprintf( 'Reply-To: %s', $email );
        }

        // Log pour debug
        error_log( sprintf( '[OFAC] Sending callback notification to: %s, subject: %s', $support_email, $subject ) );

        // Capture wp_mail errors
        $error_message = '';
        $capture_error = function( $wp_error ) use ( &$error_message ) {
            $error_message = $wp_error->get_error_message();
        };
        add_action( 'wp_mail_failed', $capture_error );

        $result = wp_mail( $support_email, $subject, $body, $headers );

        // Cleanup filters
        remove_filter( 'wp_mail_content_type', $set_html_content_type );
        remove_action( 'wp_mail_failed', $capture_error );

        if ( ! $result ) {
            if ( $error_message ) {
                error_log( '[OFAC] wp_mail_failed: ' . $error_message );
            } else {
                error_log( '[OFAC] wp_mail returned false (no error details)' );
            }
        } else {
            error_log( '[OFAC] Callback notification sent successfully to ' . $support_email );
        }

        return $result;
    }

    /**
     * Get conversation text from session
     *
     * @param string $session_id Session ID
     * @return string
     */
    public function get_conversation_text( $session_id ) {
        global $wpdb;

        $conversation = $wpdb->get_row(
            $wpdb->prepare(
                "SELECT id FROM {$wpdb->prefix}ofac_conversations WHERE session_id = %s ORDER BY id DESC LIMIT 1",
                $session_id
            )
        );

        if ( ! $conversation ) {
            return __( 'Aucune conversation trouvée.', 'anythingllm-chatbot' );
        }

        $messages = $wpdb->get_results(
            $wpdb->prepare(
                "SELECT role, content, created_at FROM {$wpdb->prefix}ofac_messages WHERE conversation_id = %d ORDER BY created_at ASC",
                $conversation->id
            )
        );

        if ( empty( $messages ) ) {
            return __( 'Aucun message dans cette conversation.', 'anythingllm-chatbot' );
        }

        $text = '';
        foreach ( $messages as $msg ) {
            $role = $msg->role === 'user' ? __( 'Client', 'anythingllm-chatbot' ) : __( 'Assistant', 'anythingllm-chatbot' );
            $text .= sprintf( "[%s] %s :\n%s\n\n", $msg->created_at, $role, $msg->content );
        }

        return $text;
    }

    /**
     * Get conversation text from conversation ID
     *
     * @param int $conversation_id Conversation ID
     * @return string
     */
    public function get_conversation_text_by_id( $conversation_id ) {
        global $wpdb;

        $messages = $wpdb->get_results(
            $wpdb->prepare(
                "SELECT role, content, created_at FROM {$wpdb->prefix}ofac_messages WHERE conversation_id = %d ORDER BY created_at ASC",
                $conversation_id
            )
        );

        if ( empty( $messages ) ) {
            return __( 'Aucun message dans cette conversation.', 'anythingllm-chatbot' );
        }

        $text = '';
        foreach ( $messages as $msg ) {
            $role = $msg->role === 'user' ? __( 'Client', 'anythingllm-chatbot' ) : __( 'Assistant', 'anythingllm-chatbot' );
            $text .= sprintf( "[%s] %s :\n%s\n\n", $msg->created_at, $role, $msg->content );
        }

        return $text;
    }

    /**
     * Generate draft response using AnythingLLM RAG
     *
     * @param string $conversation_text Conversation text
     * @param string $user_message      The user's callback message (their main request)
     * @return string
     */
    public function generate_draft_response( $conversation_text, $user_message = '' ) {
        $settings = OFAC_Settings::get_instance();
        $prompt_template = $settings->get( 'ofac_rag_draft_prompt', '' );

        if ( empty( $prompt_template ) ) {
            $prompt_template = "Tu es un assistant de support client. Un utilisateur a demandé à être recontacté par notre équipe.\n\nVoici sa demande principale :\n{message}\n\nVoici l'historique de la conversation avec notre assistant IA pour contexte :\n{conversation}\n\nRédige un email de réponse professionnel et courtois que l'opérateur du support pourra envoyer directement au client.\n\nRègles de rédaction :\n- Texte brut uniquement, AUCUN formatage Markdown (pas de **, pas de #, pas de -, pas de ```).\n- Commence directement par \"Objet :\" suivi du sujet de l'email, puis saute une ligne.\n- Puis le corps de l'email : salutation, contenu, formule de politesse.\n- Remercie le client pour sa demande.\n- Réponds précisément à la demande exprimée.\n- Propose une suite concrète (rendez-vous, appel, informations complémentaires).\n- Reste concis, professionnel et prêt à être envoyé tel quel.";
        }

        $prompt = str_replace(
            array( '{message}', '{conversation}' ),
            array( $user_message, $conversation_text ),
            $prompt_template
        );

        $api = new OFAC_API();
        if ( ! $api->is_configured() ) {
            return $this->get_default_draft();
        }

        $response = $api->chat( $prompt, '', 'query' );

        if ( is_wp_error( $response ) ) {
            return $this->get_default_draft();
        }

        $draft_text = '';
        if ( isset( $response['textResponse'] ) ) {
            $draft_text = $response['textResponse'];
        } elseif ( isset( $response['text'] ) ) {
            $draft_text = $response['text'];
        }

        if ( ! empty( $draft_text ) ) {
            return $this->strip_markdown( $draft_text );
        }

        return $this->get_default_draft();
    }

    /**
     * Remove Markdown formatting from text to produce clean plain text for email
     *
     * @param string $text Text potentially containing Markdown
     * @return string Clean plain text
     */
    public function strip_markdown( $text ) {
        // Bold: **text** or __text__
        $text = preg_replace( '/\*\*(.+?)\*\*/', '$1', $text );
        $text = preg_replace( '/__(.+?)__/', '$1', $text );
        // Italic: *text* or _text_
        $text = preg_replace( '/\*(.+?)\*/', '$1', $text );
        $text = preg_replace( '/(?<!\w)_(.+?)_(?!\w)/', '$1', $text );
        // Strikethrough: ~~text~~
        $text = preg_replace( '/~~(.+?)~~/', '$1', $text );
        // Inline code: `text`
        $text = preg_replace( '/`([^`]+)`/', '$1', $text );
        // Code blocks: ```...```
        $text = preg_replace( '/```[\s\S]*?```/', '', $text );
        // Headers: # text
        $text = preg_replace( '/^#{1,6}\s+/m', '', $text );
        // Markdown links: [text](url)
        $text = preg_replace( '/\[([^\]]+)\]\([^)]+\)/', '$1', $text );
        // Horizontal rules: ---, ***, ___
        $text = preg_replace( '/^[\s]*[-*_]{3,}[\s]*$/m', '', $text );
        // Markdown list markers at start of line: - item or * item
        $text = preg_replace( '/^[\s]*[-*+]\s+/m', '  ', $text );
        // Numbered list markers: 1. item
        $text = preg_replace( '/^[\s]*\d+\.\s+/m', '  ', $text );
        // Collapse 3+ consecutive blank lines into 2
        $text = preg_replace( '/\n{3,}/', "\n\n", $text );

        return trim( $text );
    }

    /**
     * Get default draft when RAG is unavailable
     *
     * @return string
     */
    private function get_default_draft() {
        return __( "Bonjour,\n\nMerci de nous avoir contactés. Nous avons bien reçu votre demande et nous reviendrons vers vous dans les plus brefs délais.\n\nCordialement,\nL'équipe support", 'anythingllm-chatbot' );
    }

    /**
     * Build callback notification email HTML
     *
     * @param array $data Email data
     * @return string
     */
    private function build_callback_email_html( $data ) {
        $html = '<!DOCTYPE html><html><head><meta charset="UTF-8"></head><body style="font-family: -apple-system, BlinkMacSystemFont, \'Segoe UI\', Roboto, sans-serif; max-width: 700px; margin: 0 auto; padding: 20px; color: #1e293b;">';

        // Header
        $html .= '<div style="background: #2563eb; color: white; padding: 20px; border-radius: 8px 8px 0 0;">';
        $html .= '<h2 style="margin: 0; font-size: 18px;">' . esc_html__( 'Nouvelle demande de rappel', 'anythingllm-chatbot' ) . '</h2>';
        $html .= '<p style="margin: 5px 0 0; opacity: 0.9; font-size: 14px;">' . esc_html( $data['site_name'] ) . '</p>';
        $html .= '</div>';

        // Contact info
        $html .= '<div style="background: #f8fafc; padding: 20px; border: 1px solid #e2e8f0;">';
        $html .= '<h3 style="margin: 0 0 10px; font-size: 16px; color: #334155;">' . esc_html__( 'Coordonnees du client', 'anythingllm-chatbot' ) . '</h3>';
        $html .= '<p style="margin: 5px 0;"><strong>' . esc_html__( 'Telephone', 'anythingllm-chatbot' ) . ' :</strong> <a href="tel:' . esc_attr( preg_replace( '/[^0-9+]/', '', $data['phone'] ) ) . '">' . esc_html( $data['phone'] ) . '</a></p>';

        if ( ! empty( $data['email'] ) ) {
            $html .= '<p style="margin: 5px 0;"><strong>' . esc_html__( 'Email', 'anythingllm-chatbot' ) . ' :</strong> <a href="mailto:' . esc_attr( $data['email'] ) . '">' . esc_html( $data['email'] ) . '</a></p>';
        }

        if ( ! empty( $data['message'] ) ) {
            $html .= '<p style="margin: 10px 0 0;"><strong>' . esc_html__( 'Message', 'anythingllm-chatbot' ) . ' :</strong></p>';
            $html .= '<p style="margin: 5px 0; padding: 10px; background: white; border-radius: 4px; border: 1px solid #e2e8f0;">' . nl2br( esc_html( $data['message'] ) ) . '</p>';
        }
        $html .= '</div>';

        // Action button
        if ( ! empty( $data['admin_link'] ) ) {
            $html .= '<div style="padding: 20px; border: 1px solid #e2e8f0; border-top: none; text-align: center;">';
            $html .= '<a href="' . esc_url( $data['admin_link'] ) . '" style="display: inline-block; padding: 12px 28px; background: #2563eb; color: white; text-decoration: none; border-radius: 6px; font-weight: 600;">' . esc_html__( 'Voir la conversation et repondre', 'anythingllm-chatbot' ) . '</a>';
            $html .= '</div>';
        }

        // Footer
        $html .= '<div style="padding: 15px 20px; border: 1px solid #e2e8f0; border-top: none; border-radius: 0 0 8px 8px; background: #f1f5f9; text-align: center; font-size: 12px; color: #64748b;">';
        $html .= esc_html__( 'Email envoye automatiquement par le plugin Ocade Fusion AnythingLLM Chatbot', 'anythingllm-chatbot' );
        $html .= '</div>';

        $html .= '</body></html>';

        return $html;
    }

    /**
     * Handle generate draft AJAX (admin)
     */
    public function handle_generate_draft() {
        if ( ! check_ajax_referer( 'ofac_admin_nonce', 'nonce', false ) ) {
            wp_send_json_error( array( 'message' => __( 'Nonce invalide', 'anythingllm-chatbot' ) ), 403 );
        }

        if ( ! current_user_can( 'manage_ofac_logs' ) ) {
            wp_send_json_error( array( 'message' => __( 'Permissions insuffisantes', 'anythingllm-chatbot' ) ), 403 );
        }

        $conversation_id = isset( $_POST['conversation_id'] ) ? absint( $_POST['conversation_id'] ) : 0;
        if ( ! $conversation_id ) {
            wp_send_json_error( array( 'message' => __( 'Conversation invalide', 'anythingllm-chatbot' ) ), 400 );
        }

        $conversation_text = $this->get_conversation_text_by_id( $conversation_id );

        // Retrieve the user's callback message for this conversation
        global $wpdb;
        $user_message = $wpdb->get_var(
            $wpdb->prepare(
                "SELECT message FROM {$wpdb->prefix}ofac_callback_requests WHERE conversation_id = %d ORDER BY id DESC LIMIT 1",
                $conversation_id
            )
        );

        $draft = $this->generate_draft_response( $conversation_text, $user_message ? $user_message : '' );

        wp_send_json_success( array( 'draft' => $draft ) );
    }

    /**
     * Handle add internal comment/note AJAX (admin)
     */
    public function handle_add_comment() {
        if ( ! check_ajax_referer( 'ofac_admin_nonce', 'nonce', false ) ) {
            wp_send_json_error( array( 'message' => __( 'Nonce invalide', 'anythingllm-chatbot' ) ) );
        }

        if ( ! current_user_can( 'manage_ofac_logs' ) ) {
            wp_send_json_error( array( 'message' => __( 'Permissions insuffisantes', 'anythingllm-chatbot' ) ) );
        }

        $conversation_id = isset( $_POST['conversation_id'] ) ? absint( $_POST['conversation_id'] ) : 0;
        $body            = isset( $_POST['body'] ) ? sanitize_textarea_field( wp_unslash( $_POST['body'] ) ) : '';
        $request_id      = isset( $_POST['request_id'] ) ? absint( $_POST['request_id'] ) : 0;

        if ( ! $conversation_id || empty( $body ) ) {
            wp_send_json_error( array( 'message' => __( 'Conversation et message requis', 'anythingllm-chatbot' ) ) );
        }

        global $wpdb;

        // Ensure new columns exist (dbDelta may not have run yet)
        $this->ensure_thread_columns();

        $result = $wpdb->insert(
            $wpdb->prefix . 'ofac_ticket_replies',
            array(
                'request_id'      => $request_id,
                'conversation_id' => $conversation_id,
                'user_id'         => get_current_user_id(),
                'type'            => 'comment',
                'subject'         => '',
                'body'            => $body,
                'email_sent'      => 0,
                'created_at'      => current_time( 'mysql' ),
            ),
            array( '%d', '%d', '%d', '%s', '%s', '%s', '%d', '%s' )
        );

        if ( false === $result ) {
            error_log( '[OFAC] handle_add_comment INSERT failed: ' . $wpdb->last_error );
            wp_send_json_error( array( 'message' => __( 'Erreur base de donnees', 'anythingllm-chatbot' ) . ': ' . $wpdb->last_error ) );
        }

        wp_send_json_success( array( 'message' => __( 'Note ajoutee', 'anythingllm-chatbot' ) ) );
    }

    /**
     * Ensure thread columns exist in ofac_ticket_replies table
     */
    private function ensure_thread_columns() {
        global $wpdb;
        $table = $wpdb->prefix . 'ofac_ticket_replies';

        // Check if 'type' column exists
        $col = $wpdb->get_var( "SHOW COLUMNS FROM `{$table}` LIKE 'type'" );
        if ( ! $col ) {
            $wpdb->query( "ALTER TABLE `{$table}` ADD COLUMN `type` varchar(20) DEFAULT 'email' AFTER `user_id`" );
        }

        // Check if 'conversation_id' column exists
        $col = $wpdb->get_var( "SHOW COLUMNS FROM `{$table}` LIKE 'conversation_id'" );
        if ( ! $col ) {
            $wpdb->query( "ALTER TABLE `{$table}` ADD COLUMN `conversation_id` bigint(20) unsigned NOT NULL DEFAULT 0 AFTER `request_id`" );
            $wpdb->query( "ALTER TABLE `{$table}` ADD KEY `conversation_id` (`conversation_id`)" );
        }
    }

    /**
     * Handle send reply email AJAX (admin)
     */
    public function handle_send_reply() {
        if ( ! check_ajax_referer( 'ofac_admin_nonce', 'nonce', false ) ) {
            wp_send_json_error( array( 'message' => __( 'Nonce invalide', 'anythingllm-chatbot' ) ), 403 );
        }

        if ( ! current_user_can( 'manage_ofac_logs' ) ) {
            wp_send_json_error( array( 'message' => __( 'Permissions insuffisantes', 'anythingllm-chatbot' ) ), 403 );
        }

        $to              = isset( $_POST['to'] ) ? sanitize_email( wp_unslash( $_POST['to'] ) ) : '';
        $subject         = isset( $_POST['subject'] ) ? sanitize_text_field( wp_unslash( $_POST['subject'] ) ) : '';
        $body            = isset( $_POST['body'] ) ? wp_kses_post( wp_unslash( $_POST['body'] ) ) : '';
        $request_id      = isset( $_POST['request_id'] ) ? absint( $_POST['request_id'] ) : 0;
        $conversation_id = isset( $_POST['conversation_id'] ) ? absint( $_POST['conversation_id'] ) : 0;

        if ( empty( $to ) || empty( $subject ) || empty( $body ) ) {
            wp_send_json_error( array( 'message' => __( 'Tous les champs sont requis', 'anythingllm-chatbot' ) ), 400 );
        }

        // Strip any remaining Markdown formatting from body and subject
        $body    = $this->strip_markdown( $body );
        $subject = $this->strip_markdown( $subject );

        // Wrap body in basic HTML
        $html_body = '<!DOCTYPE html><html><head><meta charset="UTF-8"></head><body style="font-family: -apple-system, BlinkMacSystemFont, \'Segoe UI\', Roboto, sans-serif; max-width: 600px; margin: 0 auto; padding: 20px; color: #1e293b; line-height: 1.6;">';
        $html_body .= nl2br( $body );
        $html_body .= '</body></html>';

        // Use WordPress filters for Content-Type (more compatible with SMTP plugins)
        $set_html_content_type = function() { return 'text/html'; };
        add_filter( 'wp_mail_content_type', $set_html_content_type );

        // Build headers with Reply-To so client replies go to the right address
        $headers = array();
        $settings  = OFAC_Settings::get_instance();
        $reply_to  = $settings->get( 'ofac_reply_to_email', '' );
        if ( empty( $reply_to ) ) {
            $reply_to = $settings->get( 'ofac_support_email', '' );
        }
        if ( ! empty( $reply_to ) ) {
            $site_name = get_bloginfo( 'name' );
            $headers[] = sprintf( 'Reply-To: %s <%s>', $site_name, $reply_to );
            $headers[] = sprintf( 'From: %s <%s>', $site_name, $reply_to );
        }

        error_log( sprintf( '[OFAC] Sending reply email to: %s, subject: %s', $to, $subject ) );

        // Capture wp_mail errors
        $error_message = '';
        $capture_error = function( $wp_error ) use ( &$error_message ) {
            $error_message = $wp_error->get_error_message();
        };
        add_action( 'wp_mail_failed', $capture_error );

        $result = wp_mail( $to, $subject, $html_body, $headers );

        // Cleanup filters
        remove_filter( 'wp_mail_content_type', $set_html_content_type );
        remove_action( 'wp_mail_failed', $capture_error );

        if ( ! $result ) {
            if ( $error_message ) {
                error_log( '[OFAC] wp_mail reply error: ' . $error_message );
            } else {
                error_log( '[OFAC] wp_mail reply returned false (no error details)' );
            }
        }

        if ( $result ) {
            global $wpdb;

            // Store reply in ticket_replies table
            $wpdb->insert(
                $wpdb->prefix . 'ofac_ticket_replies',
                array(
                    'request_id'      => $request_id ? $request_id : 0,
                    'conversation_id' => $conversation_id,
                    'user_id'         => get_current_user_id(),
                    'type'            => 'email',
                    'subject'         => $subject,
                    'body'            => $body,
                    'email_sent'      => 1,
                    'created_at'      => current_time( 'mysql' ),
                ),
                array( '%d', '%d', '%d', '%s', '%s', '%s', '%d', '%s' )
            );

            // Update callback request status
            if ( $request_id ) {
                $wpdb->update(
                    $wpdb->prefix . 'ofac_callback_requests',
                    array(
                        'status'     => 'replied',
                        'replied_at' => current_time( 'mysql' ),
                    ),
                    array( 'id' => $request_id ),
                    array( '%s', '%s' ),
                    array( '%d' )
                );
            }

            wp_send_json_success( array( 'message' => __( 'Email envoyé', 'anythingllm-chatbot' ) ) );
        } else {
            wp_send_json_error( array( 'message' => __( 'Erreur lors de l\'envoi de l\'email', 'anythingllm-chatbot' ) ), 500 );
        }
    }

    /**
     * Handle test email AJAX (admin)
     */
    public function handle_test_email() {
        if ( ! check_ajax_referer( 'ofac_admin_nonce', 'nonce', false ) ) {
            wp_send_json_error( array( 'message' => 'Nonce invalide' ), 403 );
        }

        if ( ! current_user_can( 'manage_ofac_settings' ) ) {
            wp_send_json_error( array( 'message' => 'Permissions insuffisantes' ), 403 );
        }

        $settings = OFAC_Settings::get_instance();
        $support_email = $settings->get( 'ofac_support_email', '' );

        if ( empty( $support_email ) ) {
            $support_email = get_option( 'admin_email' );
        }

        if ( empty( $support_email ) ) {
            wp_send_json_error( array( 'message' => 'Aucun email de support configuré' ) );
        }

        $site_name = get_bloginfo( 'name' );
        $subject = sprintf( '[%s] Test email - Plugin AnythingLLM Chatbot', $site_name );

        $body  = '<html><body style="font-family: Arial, sans-serif; padding: 20px;">';
        $body .= '<h2>Test email du plugin AnythingLLM Chatbot</h2>';
        $body .= '<p>Si vous recevez cet email, la configuration email fonctionne correctement.</p>';
        $body .= '<p><strong>Destinataire :</strong> ' . esc_html( $support_email ) . '</p>';
        $body .= '<p><strong>Date :</strong> ' . esc_html( current_time( 'd/m/Y H:i:s' ) ) . '</p>';
        $body .= '<p><strong>Site :</strong> ' . esc_html( $site_name ) . '</p>';
        $body .= '<hr>';
        $body .= '<p style="color: #666; font-size: 12px;">Ce message a été envoyé via wp_mail() depuis le plugin Ocade Fusion AnythingLLM Chatbot.</p>';
        $body .= '</body></html>';

        // Use WordPress filter for Content-Type
        $set_html = function() { return 'text/html'; };
        add_filter( 'wp_mail_content_type', $set_html );

        // Capture errors
        $error_message = '';
        $capture_error = function( $wp_error ) use ( &$error_message ) {
            $error_message = $wp_error->get_error_message();
        };
        add_action( 'wp_mail_failed', $capture_error );

        error_log( '[OFAC] Test email: sending to ' . $support_email );

        $result = wp_mail( $support_email, $subject, $body );

        remove_filter( 'wp_mail_content_type', $set_html );
        remove_action( 'wp_mail_failed', $capture_error );

        if ( $result ) {
            error_log( '[OFAC] Test email sent successfully to ' . $support_email );
            wp_send_json_success( array(
                'message' => sprintf( 'Email de test envoyé à %s', $support_email ),
            ) );
        } else {
            $detail = $error_message ? $error_message : 'wp_mail() a retourné false sans détail';
            error_log( '[OFAC] Test email FAILED: ' . $detail );
            wp_send_json_error( array(
                'message' => sprintf( 'Échec de l\'envoi à %s : %s', $support_email, $detail ),
            ) );
        }
    }
}
