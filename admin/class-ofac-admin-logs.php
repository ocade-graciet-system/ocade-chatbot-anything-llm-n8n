<?php
/**
 * Admin Logs Page
 *
 * @package Ocade_Fusion_AnythingLLM_Chatbot
 */

if ( ! defined( 'ABSPATH' ) ) {
    exit;
}

/**
 * Class OFAC_Admin_Logs
 */
class OFAC_Admin_Logs {

    /**
     * Items per page
     *
     * @var int
     */
    private $per_page = 20;

    /**
     * Constructor
     */
    public function __construct() {
        $this->init_hooks();
    }

    /**
     * Initialize hooks
     */
    private function init_hooks() {
        add_action( 'wp_ajax_ofac_get_conversation_messages', array( $this, 'ajax_get_messages' ) );
        add_action( 'wp_ajax_ofac_delete_conversation', array( $this, 'ajax_delete_conversation' ) );
        add_action( 'wp_ajax_ofac_bulk_delete_conversations', array( $this, 'ajax_bulk_delete' ) );
    }

    /**
     * Render logs page
     */
    public function render() {
        if ( ! current_user_can( 'manage_ofac_logs' ) ) {
            return;
        }

        $logs = OFAC_Logs::get_instance();
        
        // Get filters
        $current_page = isset( $_GET['paged'] ) ? absint( $_GET['paged'] ) : 1;
        $date_from = isset( $_GET['date_from'] ) ? sanitize_text_field( $_GET['date_from'] ) : '';
        $date_to = isset( $_GET['date_to'] ) ? sanitize_text_field( $_GET['date_to'] ) : '';
        $search = isset( $_GET['s'] ) ? sanitize_text_field( $_GET['s'] ) : '';
        $user_id = isset( $_GET['user_id'] ) ? absint( $_GET['user_id'] ) : 0;

        // Build filters
        $filters = array();
        if ( $date_from ) {
            $filters['date_from'] = $date_from;
        }
        if ( $date_to ) {
            $filters['date_to'] = $date_to;
        }
        if ( $search ) {
            $filters['search'] = $search;
        }
        if ( $user_id ) {
            $filters['user_id'] = $user_id;
        }

        // Get conversations
        $conversations = $logs->get_conversations(
            $current_page,
            $this->per_page,
            $filters,
            'started_at',
            'DESC'
        );

        $total_items = $conversations['total'];
        $total_pages = ceil( $total_items / $this->per_page );
        ?>
        <div class="wrap ofac-admin-wrap">
            <h1><?php esc_html_e( 'Logs des conversations', 'anythingllm-chatbot' ); ?></h1>

            <div class="ofac-logs-filters">
                <form method="get" action="">
                    <input type="hidden" name="page" value="ofac-logs">
                    
                    <div class="ofac-filter-row">
                        <label for="date_from"><?php esc_html_e( 'Du', 'anythingllm-chatbot' ); ?></label>
                        <input type="date" id="date_from" name="date_from" value="<?php echo esc_attr( $date_from ); ?>">

                        <label for="date_to"><?php esc_html_e( 'Au', 'anythingllm-chatbot' ); ?></label>
                        <input type="date" id="date_to" name="date_to" value="<?php echo esc_attr( $date_to ); ?>">

                        <label for="user_id"><?php esc_html_e( 'Utilisateur', 'anythingllm-chatbot' ); ?></label>
                        <?php
                        wp_dropdown_users( array(
                            'name'             => 'user_id',
                            'id'               => 'user_id',
                            'selected'         => $user_id,
                            'show_option_all'  => __( 'Tous', 'anythingllm-chatbot' ),
                            'option_none_value' => 0,
                        ) );
                        ?>

                        <input type="search" name="s" placeholder="<?php esc_attr_e( 'Rechercher...', 'anythingllm-chatbot' ); ?>" 
                               value="<?php echo esc_attr( $search ); ?>">

                        <button type="submit" class="button"><?php esc_html_e( 'Filtrer', 'anythingllm-chatbot' ); ?></button>
                        <a href="?page=ofac-logs" class="button"><?php esc_html_e( 'Réinitialiser', 'anythingllm-chatbot' ); ?></a>
                    </div>
                </form>
            </div>

            <form method="post" id="ofac-logs-form">
                <?php wp_nonce_field( 'ofac_bulk_action', 'ofac_bulk_nonce' ); ?>
                
                <div class="tablenav top">
                    <div class="alignleft actions bulkactions">
                        <select name="bulk_action" id="bulk-action-selector-top">
                            <option value=""><?php esc_html_e( 'Actions groupées', 'anythingllm-chatbot' ); ?></option>
                            <option value="delete"><?php esc_html_e( 'Supprimer', 'anythingllm-chatbot' ); ?></option>
                        </select>
                        <button type="button" class="button action" id="ofac-bulk-action-btn">
                            <?php esc_html_e( 'Appliquer', 'anythingllm-chatbot' ); ?>
                        </button>
                    </div>

                    <div class="tablenav-pages">
                        <span class="displaying-num">
                            <?php printf( 
                                esc_html( _n( '%s élément', '%s éléments', $total_items, 'anythingllm-chatbot' ) ), 
                                number_format_i18n( $total_items ) 
                            ); ?>
                        </span>
                        <?php if ( $total_pages > 1 ) : ?>
                            <?php echo $this->pagination_links( $current_page, $total_pages ); ?>
                        <?php endif; ?>
                    </div>
                </div>

                <table class="wp-list-table widefat fixed striped ofac-logs-table">
                    <thead>
                        <tr>
                            <td class="manage-column column-cb check-column">
                                <input type="checkbox" id="cb-select-all">
                            </td>
                            <th scope="col"><?php esc_html_e( 'ID', 'anythingllm-chatbot' ); ?></th>
                            <th scope="col"><?php esc_html_e( 'Session', 'anythingllm-chatbot' ); ?></th>
                            <th scope="col"><?php esc_html_e( 'Utilisateur', 'anythingllm-chatbot' ); ?></th>
                            <th scope="col"><?php esc_html_e( 'Messages', 'anythingllm-chatbot' ); ?></th>
                            <th scope="col"><?php esc_html_e( 'Début', 'anythingllm-chatbot' ); ?></th>
                            <th scope="col"><?php esc_html_e( 'Fin', 'anythingllm-chatbot' ); ?></th>
                            <th scope="col"><?php esc_html_e( 'Avis', 'anythingllm-chatbot' ); ?></th>
                            <th scope="col"><?php esc_html_e( 'Actions', 'anythingllm-chatbot' ); ?></th>
                        </tr>
                    </thead>
                    <tbody>
                        <?php if ( empty( $conversations['items'] ) ) : ?>
                            <tr>
                                <td colspan="9"><?php esc_html_e( 'Aucune conversation trouvée.', 'anythingllm-chatbot' ); ?></td>
                            </tr>
                        <?php else : ?>
                            <?php
                            // Pre-fetch all feedback for displayed conversations
                            global $wpdb;
                            $conv_ids = wp_list_pluck( $conversations['items'], 'id' );
                            $feedbacks = array();
                            if ( ! empty( $conv_ids ) ) {
                                $placeholders = implode( ',', array_fill( 0, count( $conv_ids ), '%d' ) );
                                $fb_results = $wpdb->get_results(
                                    $wpdb->prepare(
                                        "SELECT conversation_id, rating, note FROM {$wpdb->prefix}ofac_conversation_feedback WHERE conversation_id IN ($placeholders)",
                                        ...$conv_ids
                                    )
                                );
                                foreach ( $fb_results as $fb ) {
                                    $feedbacks[ $fb->conversation_id ] = $fb;
                                }
                            }
                            ?>
                            <?php foreach ( $conversations['items'] as $conv ) : ?>
                                <tr data-id="<?php echo esc_attr( $conv->id ); ?>">
                                    <th scope="row" class="check-column">
                                        <input type="checkbox" name="conversation_ids[]" value="<?php echo esc_attr( $conv->id ); ?>">
                                    </th>
                                    <td><?php echo esc_html( $conv->id ); ?></td>
                                    <td>
                                        <code title="<?php echo esc_attr( $conv->session_id ); ?>">
                                            <?php echo esc_html( substr( $conv->session_id, 0, 8 ) . '...' ); ?>
                                        </code>
                                    </td>
                                    <td>
                                        <?php if ( $conv->user_id ) : ?>
                                            <?php $user = get_userdata( $conv->user_id ); ?>
                                            <?php if ( $user ) : ?>
                                                <strong><?php echo esc_html( $user->display_name ); ?></strong>
                                                <small>ID: <?php echo esc_html( $conv->user_id ); ?></small>
                                                <small><a href="mailto:<?php echo esc_attr( $user->user_email ); ?>"><?php echo esc_html( $user->user_email ); ?></a></small>
                                            <?php else : ?>
                                                <span class="ofac-anonymous"><?php esc_html_e( 'Utilisateur supprimé', 'anythingllm-chatbot' ); ?> (ID: <?php echo esc_html( $conv->user_id ); ?>)</span>
                                            <?php endif; ?>
                                        <?php else : ?>
                                            <span class="ofac-anonymous"><?php esc_html_e( 'Anonyme', 'anythingllm-chatbot' ); ?></span>
                                        <?php endif; ?>
                                    </td>
                                    <td><?php echo esc_html( $conv->message_count ); ?></td>
                                    <td><?php echo esc_html( mysql2date( 'd/m/Y H:i', $conv->started_at ) ); ?></td>
                                    <td>
                                        <?php if ( $conv->ended_at ) : ?>
                                            <?php echo esc_html( mysql2date( 'd/m/Y H:i', $conv->ended_at ) ); ?>
                                        <?php else : ?>
                                            <span class="ofac-active"><?php esc_html_e( 'Active', 'anythingllm-chatbot' ); ?></span>
                                        <?php endif; ?>
                                    </td>
                                    <td class="ofac-feedback-cell">
                                        <?php
                                        $fb = isset( $feedbacks[ $conv->id ] ) ? $feedbacks[ $conv->id ] : null;
                                        if ( $fb ) :
                                            $icon  = $fb->rating > 0 ? 'dashicons-thumbs-up' : 'dashicons-thumbs-down';
                                            $color = $fb->rating > 0 ? '#22c55e' : '#ef4444';
                                        ?>
                                            <span class="dashicons <?php echo esc_attr( $icon ); ?>"
                                                  style="color:<?php echo esc_attr( $color ); ?>;font-size:18px;width:18px;height:18px;vertical-align:middle;"></span>
                                            <?php if ( ! empty( $fb->note ) ) : ?>
                                            <button type="button" class="button button-small ofac-read-note" data-note="<?php echo esc_attr( $fb->note ); ?>" style="margin-left:4px;vertical-align:middle;">
                                                <?php esc_html_e( 'Lire', 'anythingllm-chatbot' ); ?>
                                            </button>
                                            <?php endif; ?>
                                        <?php else : ?>
                                            <span style="color:#94a3b8;">&mdash;</span>
                                        <?php endif; ?>
                                    </td>
                                    <td>
                                        <button type="button" class="button button-small ofac-view-messages" data-id="<?php echo esc_attr( $conv->id ); ?>">
                                            <?php esc_html_e( 'Voir', 'anythingllm-chatbot' ); ?>
                                        </button>
                                        <button type="button" class="button button-small ofac-delete-conversation" data-id="<?php echo esc_attr( $conv->id ); ?>">
                                            <?php esc_html_e( 'Supprimer', 'anythingllm-chatbot' ); ?>
                                        </button>
                                    </td>
                                </tr>
                            <?php endforeach; ?>
                        <?php endif; ?>
                    </tbody>
                </table>

                <div class="tablenav bottom">
                    <div class="tablenav-pages">
                        <?php if ( $total_pages > 1 ) : ?>
                            <?php echo $this->pagination_links( $current_page, $total_pages ); ?>
                        <?php endif; ?>
                    </div>
                </div>
            </form>
        </div>

        <!-- Modal for feedback note -->
        <div id="ofac-note-modal" class="ofac-modal" style="display:none;">
            <div class="ofac-modal-content" style="max-width:480px;">
                <div class="ofac-modal-header">
                    <h2><?php esc_html_e( 'Commentaire du visiteur', 'anythingllm-chatbot' ); ?></h2>
                    <button type="button" class="ofac-modal-close">&times;</button>
                </div>
                <div class="ofac-modal-body">
                    <p id="ofac-note-text" style="white-space:pre-wrap;margin:0;"></p>
                </div>
            </div>
        </div>

        <!-- Modal for viewing messages -->
        <div id="ofac-messages-modal" class="ofac-modal" style="display:none;">
            <div class="ofac-modal-content">
                <div class="ofac-modal-header">
                    <h2><?php esc_html_e( 'Messages de la conversation', 'anythingllm-chatbot' ); ?></h2>
                    <button type="button" class="ofac-modal-close">&times;</button>
                </div>
                <div class="ofac-modal-body">
                    <div id="ofac-messages-list"></div>
                </div>
            </div>
        </div>
        <?php
    }

    /**
     * Generate pagination links
     *
     * @param int $current_page Current page.
     * @param int $total_pages  Total pages.
     * @return string
     */
    private function pagination_links( $current_page, $total_pages ) {
        $base_url = add_query_arg( array(
            'page' => 'ofac-logs',
        ), admin_url( 'admin.php' ) );

        // Preserve filters
        foreach ( array( 'date_from', 'date_to', 's', 'user_id' ) as $param ) {
            if ( isset( $_GET[ $param ] ) && $_GET[ $param ] !== '' ) {
                $base_url = add_query_arg( $param, sanitize_text_field( $_GET[ $param ] ), $base_url );
            }
        }

        $output = '<span class="pagination-links">';

        // First page
        if ( $current_page > 1 ) {
            $output .= sprintf(
                '<a class="first-page button" href="%s"><span aria-hidden="true">&laquo;</span></a> ',
                esc_url( add_query_arg( 'paged', 1, $base_url ) )
            );
            $output .= sprintf(
                '<a class="prev-page button" href="%s"><span aria-hidden="true">&lsaquo;</span></a> ',
                esc_url( add_query_arg( 'paged', $current_page - 1, $base_url ) )
            );
        } else {
            $output .= '<span class="tablenav-pages-navspan button disabled">&laquo;</span> ';
            $output .= '<span class="tablenav-pages-navspan button disabled">&lsaquo;</span> ';
        }

        $output .= sprintf(
            '<span class="paging-input">%s / %s</span> ',
            $current_page,
            $total_pages
        );

        // Last page
        if ( $current_page < $total_pages ) {
            $output .= sprintf(
                '<a class="next-page button" href="%s"><span aria-hidden="true">&rsaquo;</span></a> ',
                esc_url( add_query_arg( 'paged', $current_page + 1, $base_url ) )
            );
            $output .= sprintf(
                '<a class="last-page button" href="%s"><span aria-hidden="true">&raquo;</span></a>',
                esc_url( add_query_arg( 'paged', $total_pages, $base_url ) )
            );
        } else {
            $output .= '<span class="tablenav-pages-navspan button disabled">&rsaquo;</span> ';
            $output .= '<span class="tablenav-pages-navspan button disabled">&raquo;</span>';
        }

        $output .= '</span>';

        return $output;
    }

    /**
     * AJAX: Get conversation messages
     */
    public function ajax_get_messages() {
        check_ajax_referer( 'ofac_admin_nonce', 'nonce' );

        if ( ! current_user_can( 'manage_ofac_logs' ) ) {
            wp_send_json_error( __( 'Permission refusée.', 'anythingllm-chatbot' ) );
        }

        $conversation_id = isset( $_POST['conversation_id'] ) ? absint( $_POST['conversation_id'] ) : 0;

        if ( ! $conversation_id ) {
            wp_send_json_error( __( 'ID de conversation invalide.', 'anythingllm-chatbot' ) );
        }

        $logs = OFAC_Logs::get_instance();
        $messages = $logs->get_messages( $conversation_id );

        // Build user info header
        global $wpdb;
        $conversation = $wpdb->get_row(
            $wpdb->prepare(
                "SELECT * FROM {$wpdb->prefix}ofac_conversations WHERE id = %d",
                $conversation_id
            )
        );

        $html = '';
        if ( $conversation && $conversation->user_id ) {
            $user = get_userdata( $conversation->user_id );
            if ( $user ) {
                $html .= '<div class="ofac-user-info-header">';
                $html .= '<strong>' . esc_html( $user->display_name ) . '</strong>';
                $html .= '<span>ID: ' . esc_html( $conversation->user_id ) . '</span>';
                $html .= '<span>' . esc_html__( 'Email: ', 'anythingllm-chatbot' ) . '<a href="mailto:' . esc_attr( $user->user_email ) . '">' . esc_html( $user->user_email ) . '</a></span>';
                $html .= '</div>';
            }
        } elseif ( $conversation && ! $conversation->user_id ) {
            $html .= '<div class="ofac-user-info-header">';
            $html .= '<strong>' . esc_html__( 'Visiteur anonyme', 'anythingllm-chatbot' ) . '</strong>';
            $html .= '</div>';
        }

        foreach ( $messages as $msg ) {
            $role_class = $msg->role === 'user' ? 'ofac-msg-user' : 'ofac-msg-bot';
            $role_label = $msg->role === 'user'
                ? __( 'Utilisateur', 'anythingllm-chatbot' )
                : __( 'Bot', 'anythingllm-chatbot' );

            // Bot messages: render markdown as formatted HTML
            // User messages: simple text with line breaks
            if ( $msg->role === 'user' ) {
                $content_html = nl2br( esc_html( $msg->content ) );
            } else {
                $content_html = $this->parse_markdown( $msg->content );
            }

            $html .= sprintf(
                '<div class="ofac-message %s">
                    <div class="ofac-message-header">
                        <span class="ofac-message-role">%s</span>
                        <span class="ofac-message-time">%s</span>
                    </div>
                    <div class="ofac-message-content">%s</div>
                </div>',
                esc_attr( $role_class ),
                esc_html( $role_label ),
                esc_html( mysql2date( 'd/m/Y H:i:s', $msg->created_at ) ),
                $content_html
            );
        }

        // Check for callback request linked to this conversation
        $callback_request = $wpdb->get_row(
            $wpdb->prepare(
                "SELECT * FROM {$wpdb->prefix}ofac_callback_requests WHERE conversation_id = %d ORDER BY id DESC LIMIT 1",
                $conversation_id
            )
        );

        // Determine reply email: callback request email > logged-in user email
        $reply_email = '';
        $reply_source = '';
        if ( $callback_request ) {
            $reply_email = $callback_request->email;
            $reply_source = 'callback';
        } elseif ( $conversation && $conversation->user_id ) {
            $user = get_userdata( $conversation->user_id );
            if ( $user ) {
                $reply_email = $user->user_email;
                $reply_source = 'user';
            }
        }

        // Display thread entries (notes + emails) in the conversation flow
        $thread_entries = $wpdb->get_results(
            $wpdb->prepare(
                "SELECT * FROM {$wpdb->prefix}ofac_ticket_replies WHERE conversation_id = %d ORDER BY created_at ASC",
                $conversation_id
            )
        );

        if ( ! empty( $thread_entries ) ) {
            $html .= '<div class="ofac-thread-separator">';
            $html .= '<span>' . esc_html__( 'Suivi client', 'anythingllm-chatbot' ) . '</span>';
            $html .= '</div>';

            foreach ( $thread_entries as $entry ) {
                $entry_user = get_userdata( $entry->user_id );
                $author_name = $entry_user ? $entry_user->display_name : __( 'Utilisateur supprime', 'anythingllm-chatbot' );
                $is_email = ( empty( $entry->type ) || $entry->type === 'email' );

                if ( $is_email ) {
                    $entry_class = 'ofac-msg-email';
                    $entry_label = __( 'Email envoye', 'anythingllm-chatbot' );
                    $entry_icon = 'dashicons-email-alt';
                } else {
                    $entry_class = 'ofac-msg-note';
                    $entry_label = __( 'Note interne', 'anythingllm-chatbot' );
                    $entry_icon = 'dashicons-admin-comments';
                }

                $html .= '<div class="ofac-message ' . esc_attr( $entry_class ) . '">';
                $html .= '<div class="ofac-message-header">';
                $html .= '<span class="ofac-message-role"><span class="dashicons ' . esc_attr( $entry_icon ) . '" style="font-size:14px;width:14px;height:14px;vertical-align:middle;margin-right:4px;"></span>' . esc_html( $entry_label ) . ' &mdash; ' . esc_html( $author_name ) . '</span>';
                $html .= '<span class="ofac-message-time">' . esc_html( mysql2date( 'd/m/Y H:i:s', $entry->created_at ) ) . '</span>';
                $html .= '</div>';

                if ( $is_email && ! empty( $entry->subject ) ) {
                    $html .= '<div class="ofac-message-subject"><strong>' . esc_html__( 'Sujet', 'anythingllm-chatbot' ) . ' :</strong> ' . esc_html( $entry->subject ) . '</div>';
                }

                $html .= '<div class="ofac-message-content">' . wp_kses_post( nl2br( esc_html( $entry->body ) ) ) . '</div>';
                $html .= '</div>';
            }
        }

        // Unified thread action form
        $request_id_attr = $callback_request ? $callback_request->id : 0;

        $html .= '<div class="ofac-thread-actions" data-conversation-id="' . esc_attr( $conversation_id ) . '" data-request-id="' . esc_attr( $request_id_attr ) . '">';
        $html .= '<div class="ofac-thread-form">';

        // Textarea always visible
        $html .= '<div class="ofac-reply-field">';
        $html .= '<textarea class="ofac-reply-body" rows="4" placeholder="' . esc_attr__( 'Ecrivez votre note ou votre email...', 'anythingllm-chatbot' ) . '"></textarea>';
        $html .= '</div>';

        // Email fields (hidden by default, shown when clicking "Envoyer par email")
        if ( $reply_email ) {
            $default_subject = sprintf(
                __( 'Suite a votre echange avec notre assistant - %s', 'anythingllm-chatbot' ),
                get_bloginfo( 'name' )
            );

            $html .= '<div class="ofac-email-fields" style="display:none;">';

            $html .= '<div class="ofac-reply-field">';
            $html .= '<label>' . esc_html__( 'Destinataire', 'anythingllm-chatbot' ) . '</label>';
            $html .= '<input type="email" class="ofac-reply-to" value="' . esc_attr( $reply_email ) . '" readonly>';
            $html .= '</div>';

            $html .= '<div class="ofac-reply-field">';
            $html .= '<label>' . esc_html__( 'Sujet', 'anythingllm-chatbot' ) . '</label>';
            $html .= '<input type="text" class="ofac-reply-subject" value="' . esc_attr( $default_subject ) . '">';
            $html .= '</div>';

            $html .= '</div>'; // .ofac-email-fields
        }

        // Action buttons
        $html .= '<div class="ofac-reply-actions">';
        $html .= '<button type="button" class="button ofac-add-note">';
        $html .= '<span class="dashicons dashicons-admin-comments" style="vertical-align:middle;margin-right:4px;"></span>';
        $html .= esc_html__( 'Ajouter une note', 'anythingllm-chatbot' );
        $html .= '</button>';

        if ( $reply_email ) {
            $html .= '<button type="button" class="button button-primary ofac-send-reply">';
            $html .= '<span class="dashicons dashicons-email" style="vertical-align:middle;margin-right:4px;"></span>';
            $html .= esc_html__( 'Envoyer par email', 'anythingllm-chatbot' );
            $html .= '</button>';
        }

        $html .= '</div>'; // .ofac-reply-actions
        $html .= '</div>'; // .ofac-thread-form
        $html .= '</div>'; // .ofac-thread-actions

        wp_send_json_success( array( 'html' => $html ) );
    }

    /**
     * AJAX: Delete conversation
     */
    public function ajax_delete_conversation() {
        check_ajax_referer( 'ofac_admin_nonce', 'nonce' );

        if ( ! current_user_can( 'manage_ofac_logs' ) ) {
            wp_send_json_error( __( 'Permission refusée.', 'anythingllm-chatbot' ) );
        }

        $conversation_id = isset( $_POST['conversation_id'] ) ? absint( $_POST['conversation_id'] ) : 0;

        if ( ! $conversation_id ) {
            wp_send_json_error( __( 'ID de conversation invalide.', 'anythingllm-chatbot' ) );
        }

        $logs = OFAC_Logs::get_instance();
        $result = $logs->delete_conversation( $conversation_id );

        if ( $result ) {
            wp_send_json_success( __( 'Conversation supprimée.', 'anythingllm-chatbot' ) );
        } else {
            wp_send_json_error( __( 'Erreur lors de la suppression.', 'anythingllm-chatbot' ) );
        }
    }

    /**
     * AJAX: Bulk delete conversations
     */
    public function ajax_bulk_delete() {
        check_ajax_referer( 'ofac_admin_nonce', 'nonce' );

        if ( ! current_user_can( 'manage_ofac_logs' ) ) {
            wp_send_json_error( __( 'Permission refusée.', 'anythingllm-chatbot' ) );
        }

        $ids = isset( $_POST['ids'] ) ? array_map( 'absint', (array) $_POST['ids'] ) : array();

        if ( empty( $ids ) ) {
            wp_send_json_error( __( 'Aucune conversation sélectionnée.', 'anythingllm-chatbot' ) );
        }

        $logs = OFAC_Logs::get_instance();
        $deleted = 0;

        foreach ( $ids as $id ) {
            if ( $logs->delete_conversation( $id ) ) {
                $deleted++;
            }
        }

        wp_send_json_success( sprintf(
            __( '%d conversation(s) supprimée(s).', 'anythingllm-chatbot' ),
            $deleted
        ) );
    }

    /**
     * Parse Markdown text into HTML for bot messages display.
     * Mirrors the chatbot JS parseMarkdown() with same CSS classes.
     *
     * @param string $text Raw markdown text
     * @return string HTML
     */
    private function parse_markdown( $text ) {
        if ( empty( $text ) ) {
            return '';
        }

        $text = esc_html( $text );
        $lines = explode( "\n", $text );
        $result = array();
        $in_list = false;
        $list_type = '';

        foreach ( $lines as $line ) {
            // Headings: # to ######
            if ( preg_match( '/^(#{1,6})\s+(.+)$/', $line, $m ) ) {
                if ( $in_list ) {
                    $result[] = $list_type === 'ul' ? '</ul>' : '</ol>';
                    $in_list = false;
                }
                $level = strlen( $m[1] );
                $result[] = sprintf( '<h%d class="ofac-md-heading">%s</h%d>', $level, $this->inline_format( $m[2] ), $level );
                continue;
            }

            // Horizontal rule: ---, ***, ___
            if ( preg_match( '/^\s*[-*_]{3,}\s*$/', $line ) ) {
                if ( $in_list ) {
                    $result[] = $list_type === 'ul' ? '</ul>' : '</ol>';
                    $in_list = false;
                }
                $result[] = '<hr class="ofac-md-hr">';
                continue;
            }

            // Blockquote: > text
            if ( preg_match( '/^&gt;\s?(.*)$/', $line, $m ) ) {
                if ( $in_list ) {
                    $result[] = $list_type === 'ul' ? '</ul>' : '</ol>';
                    $in_list = false;
                }
                $result[] = '<blockquote class="ofac-md-blockquote">' . $this->inline_format( $m[1] ) . '</blockquote>';
                continue;
            }

            // Unordered list: - item, * item, + item
            if ( preg_match( '/^\s*[-*+]\s+(.+)$/', $line, $m ) ) {
                if ( ! $in_list || $list_type !== 'ul' ) {
                    if ( $in_list ) {
                        $result[] = $list_type === 'ul' ? '</ul>' : '</ol>';
                    }
                    $result[] = '<ul class="ofac-md-list">';
                    $in_list = true;
                    $list_type = 'ul';
                }
                $result[] = '<li>' . $this->inline_format( $m[1] ) . '</li>';
                continue;
            }

            // Ordered list: 1. item
            if ( preg_match( '/^\s*\d+\.\s+(.+)$/', $line, $m ) ) {
                if ( ! $in_list || $list_type !== 'ol' ) {
                    if ( $in_list ) {
                        $result[] = $list_type === 'ul' ? '</ul>' : '</ol>';
                    }
                    $result[] = '<ol class="ofac-md-list">';
                    $in_list = true;
                    $list_type = 'ol';
                }
                $result[] = '<li>' . $this->inline_format( $m[1] ) . '</li>';
                continue;
            }

            // Close list if we're no longer in a list item
            if ( $in_list ) {
                $result[] = $list_type === 'ul' ? '</ul>' : '</ol>';
                $in_list = false;
            }

            // Empty line
            if ( trim( $line ) === '' ) {
                continue;
            }

            // Normal paragraph
            $result[] = '<p class="ofac-md-paragraph">' . $this->inline_format( $line ) . '</p>';
        }

        // Close any open list
        if ( $in_list ) {
            $result[] = $list_type === 'ul' ? '</ul>' : '</ol>';
        }

        return implode( "\n", $result );
    }

    /**
     * Apply inline Markdown formatting (bold, italic, strikethrough, links).
     *
     * @param string $text Escaped text
     * @return string
     */
    private function inline_format( $text ) {
        // Bold: **text**
        $text = preg_replace( '/\*\*([^*]+)\*\*/', '<strong>$1</strong>', $text );
        // Italic: *text*
        $text = preg_replace( '/\*([^*]+)\*/', '<em>$1</em>', $text );
        // Strikethrough: ~~text~~
        $text = preg_replace( '/~~([^~]+)~~/', '<del>$1</del>', $text );
        // Inline code: `text`
        $text = preg_replace( '/`([^`]+)`/', '<code class="ofac-inline-code">$1</code>', $text );
        return $text;
    }
}
