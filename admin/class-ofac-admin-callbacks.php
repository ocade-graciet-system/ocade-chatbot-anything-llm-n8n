<?php
/**
 * Admin Callbacks Page - Demandes de rappel
 *
 * @package Ocade_Fusion_AnythingLLM_Chatbot
 */

if ( ! defined( 'ABSPATH' ) ) {
    exit;
}

/**
 * Class OFAC_Admin_Callbacks
 */
class OFAC_Admin_Callbacks {

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
        add_action( 'wp_ajax_ofac_update_callback_status', array( $this, 'ajax_update_status' ) );
        add_action( 'wp_ajax_ofac_delete_callback', array( $this, 'ajax_delete_callback' ) );
        add_action( 'wp_ajax_ofac_bulk_callbacks', array( $this, 'ajax_bulk_action' ) );
        add_action( 'wp_ajax_ofac_get_ticket_replies', array( $this, 'ajax_get_replies' ) );
    }

    /**
     * Render callbacks page
     */
    public function render() {
        if ( ! current_user_can( 'manage_ofac_callbacks' ) ) {
            return;
        }

        global $wpdb;
        $table = $wpdb->prefix . 'ofac_callback_requests';

        // Filtres
        $current_page = isset( $_GET['paged'] ) ? absint( $_GET['paged'] ) : 1;
        $status_filter = isset( $_GET['status'] ) ? sanitize_text_field( $_GET['status'] ) : '';
        $search = isset( $_GET['s'] ) ? sanitize_text_field( $_GET['s'] ) : '';

        // Construction de la requete
        $where = array( '1=1' );
        $params = array();

        if ( $status_filter ) {
            $where[] = 'status = %s';
            $params[] = $status_filter;
        }

        if ( $search ) {
            $where[] = '(email LIKE %s OR phone LIKE %s OR message LIKE %s)';
            $search_like = '%' . $wpdb->esc_like( $search ) . '%';
            $params[] = $search_like;
            $params[] = $search_like;
            $params[] = $search_like;
        }

        $where_clause = implode( ' AND ', $where );

        // Total
        $count_query = "SELECT COUNT(*) FROM $table WHERE $where_clause";
        if ( ! empty( $params ) ) {
            $count_query = $wpdb->prepare( $count_query, $params );
        }
        $total_items = (int) $wpdb->get_var( $count_query );
        $total_pages = ceil( $total_items / $this->per_page );
        $offset = ( $current_page - 1 ) * $this->per_page;

        // Compteurs par statut
        $status_counts = $this->get_status_counts();

        // Resultats
        $query = "SELECT * FROM $table WHERE $where_clause ORDER BY created_at DESC LIMIT %d OFFSET %d";
        $all_params = array_merge( $params, array( $this->per_page, $offset ) );
        $items = $wpdb->get_results( $wpdb->prepare( $query, $all_params ) );

        // Pre-fetch reply counts for each item
        $reply_counts = array();
        if ( ! empty( $items ) ) {
            $item_ids = wp_list_pluck( $items, 'id' );
            $ids_placeholder = implode( ',', array_map( 'absint', $item_ids ) );
            $replies_table = $wpdb->prefix . 'ofac_ticket_replies';
            // phpcs:ignore WordPress.DB.PreparedSQL.InterpolatedNotPrepared
            $counts_raw = $wpdb->get_results( "SELECT request_id, COUNT(*) as cnt FROM $replies_table WHERE request_id IN ($ids_placeholder) GROUP BY request_id" );
            foreach ( $counts_raw as $row ) {
                $reply_counts[ $row->request_id ] = (int) $row->cnt;
            }
        }

        ?>
        <div class="wrap ofac-admin-wrap">
            <h1><?php esc_html_e( 'Demandes de rappel', 'anythingllm-chatbot' ); ?></h1>

            <!-- Compteurs -->
            <ul class="subsubsub">
                <li>
                    <a href="?page=ofac-callbacks" <?php echo ! $status_filter ? 'class="current"' : ''; ?>>
                        <?php esc_html_e( 'Toutes', 'anythingllm-chatbot' ); ?>
                        <span class="count">(<?php echo esc_html( $status_counts['total'] ); ?>)</span>
                    </a> |
                </li>
                <li>
                    <a href="?page=ofac-callbacks&status=pending" <?php echo 'pending' === $status_filter ? 'class="current"' : ''; ?>>
                        <?php esc_html_e( 'En attente', 'anythingllm-chatbot' ); ?>
                        <span class="count">(<?php echo esc_html( $status_counts['pending'] ?? 0 ); ?>)</span>
                    </a> |
                </li>
                <li>
                    <a href="?page=ofac-callbacks&status=replied" <?php echo 'replied' === $status_filter ? 'class="current"' : ''; ?>>
                        <?php esc_html_e( 'Répondu', 'anythingllm-chatbot' ); ?>
                        <span class="count">(<?php echo esc_html( $status_counts['replied'] ?? 0 ); ?>)</span>
                    </a> |
                </li>
                <li>
                    <a href="?page=ofac-callbacks&status=closed" <?php echo 'closed' === $status_filter ? 'class="current"' : ''; ?>>
                        <?php esc_html_e( 'Fermé', 'anythingllm-chatbot' ); ?>
                        <span class="count">(<?php echo esc_html( $status_counts['closed'] ?? 0 ); ?>)</span>
                    </a>
                </li>
            </ul>

            <!-- Recherche -->
            <form method="get" action="">
                <input type="hidden" name="page" value="ofac-callbacks">
                <?php if ( $status_filter ) : ?>
                    <input type="hidden" name="status" value="<?php echo esc_attr( $status_filter ); ?>">
                <?php endif; ?>
                <p class="search-box">
                    <input type="search" name="s" value="<?php echo esc_attr( $search ); ?>"
                           placeholder="<?php esc_attr_e( 'Rechercher par email, téléphone...', 'anythingllm-chatbot' ); ?>">
                    <button type="submit" class="button"><?php esc_html_e( 'Rechercher', 'anythingllm-chatbot' ); ?></button>
                </p>
            </form>

            <form method="post" id="ofac-callbacks-form">
                <?php wp_nonce_field( 'ofac_bulk_action', 'ofac_bulk_nonce' ); ?>

                <div class="tablenav top">
                    <div class="alignleft actions bulkactions">
                        <select name="bulk_action" id="ofac-cb-bulk-action">
                            <option value=""><?php esc_html_e( 'Actions groupées', 'anythingllm-chatbot' ); ?></option>
                            <option value="replied"><?php esc_html_e( 'Marquer comme répondu', 'anythingllm-chatbot' ); ?></option>
                            <option value="closed"><?php esc_html_e( 'Fermer', 'anythingllm-chatbot' ); ?></option>
                            <option value="delete"><?php esc_html_e( 'Supprimer', 'anythingllm-chatbot' ); ?></option>
                        </select>
                        <button type="button" class="button action" id="ofac-cb-bulk-btn">
                            <?php esc_html_e( 'Appliquer', 'anythingllm-chatbot' ); ?>
                        </button>
                    </div>

                    <div class="tablenav-pages">
                        <span class="displaying-num">
                            <?php printf(
                                esc_html( _n( '%s demande', '%s demandes', $total_items, 'anythingllm-chatbot' ) ),
                                number_format_i18n( $total_items )
                            ); ?>
                        </span>
                        <?php if ( $total_pages > 1 ) : ?>
                            <?php echo $this->pagination_links( $current_page, $total_pages, $status_filter, $search ); ?>
                        <?php endif; ?>
                    </div>
                </div>

                <table class="wp-list-table widefat fixed striped ofac-callbacks-table">
                    <thead>
                        <tr>
                            <td class="manage-column column-cb check-column">
                                <input type="checkbox" id="cb-select-all">
                            </td>
                            <th scope="col" class="column-status"><?php esc_html_e( 'Statut', 'anythingllm-chatbot' ); ?></th>
                            <th scope="col" class="column-email"><?php esc_html_e( 'Email', 'anythingllm-chatbot' ); ?></th>
                            <th scope="col" class="column-phone"><?php esc_html_e( 'Téléphone', 'anythingllm-chatbot' ); ?></th>
                            <th scope="col" class="column-message"><?php esc_html_e( 'Message', 'anythingllm-chatbot' ); ?></th>
                            <th scope="col" class="column-conversation"><?php esc_html_e( 'Conversation', 'anythingllm-chatbot' ); ?></th>
                            <th scope="col" class="column-replies"><?php esc_html_e( 'Réponses', 'anythingllm-chatbot' ); ?></th>
                            <th scope="col" class="column-date"><?php esc_html_e( 'Date', 'anythingllm-chatbot' ); ?></th>
                            <th scope="col" class="column-actions"><?php esc_html_e( 'Actions', 'anythingllm-chatbot' ); ?></th>
                        </tr>
                    </thead>
                    <tbody>
                        <?php if ( empty( $items ) ) : ?>
                            <tr>
                                <td colspan="9"><?php esc_html_e( 'Aucune demande de rappel.', 'anythingllm-chatbot' ); ?></td>
                            </tr>
                        <?php else : ?>
                            <?php foreach ( $items as $item ) : ?>
                                <tr data-id="<?php echo esc_attr( $item->id ); ?>">
                                    <th scope="row" class="check-column">
                                        <input type="checkbox" name="callback_ids[]" value="<?php echo esc_attr( $item->id ); ?>">
                                    </th>
                                    <td class="column-status">
                                        <?php echo $this->render_status_badge( $item->status ); ?>
                                    </td>
                                    <td class="column-email">
                                        <?php if ( $item->email ) : ?>
                                            <strong><a href="mailto:<?php echo esc_attr( $item->email ); ?>"><?php echo esc_html( $item->email ); ?></a></strong>
                                        <?php else : ?>
                                            <span class="ofac-muted">—</span>
                                        <?php endif; ?>
                                    </td>
                                    <td class="column-phone">
                                        <?php if ( $item->phone ) : ?>
                                            <a href="tel:<?php echo esc_attr( $item->phone ); ?>"><?php echo esc_html( $item->phone ); ?></a>
                                        <?php else : ?>
                                            <span class="ofac-muted">—</span>
                                        <?php endif; ?>
                                    </td>
                                    <td class="column-message">
                                        <?php if ( $item->message ) : ?>
                                            <span title="<?php echo esc_attr( $item->message ); ?>">
                                                <?php echo esc_html( wp_trim_words( $item->message, 10, '...' ) ); ?>
                                            </span>
                                        <?php else : ?>
                                            <span class="ofac-muted">—</span>
                                        <?php endif; ?>
                                    </td>
                                    <td class="column-conversation">
                                        <?php if ( $item->conversation_id ) : ?>
                                            <a href="<?php echo esc_url( admin_url( 'admin.php?page=ofac-logs&open_conversation=' . $item->conversation_id ) ); ?>"
                                               title="<?php esc_attr_e( 'Voir la conversation et répondre', 'anythingllm-chatbot' ); ?>">
                                                #<?php echo esc_html( $item->conversation_id ); ?>
                                            </a>
                                        <?php else : ?>
                                            <span class="ofac-muted">—</span>
                                        <?php endif; ?>
                                    </td>
                                    <td class="column-replies">
                                        <?php
                                        $count = isset( $reply_counts[ $item->id ] ) ? $reply_counts[ $item->id ] : 0;
                                        if ( $count > 0 ) :
                                        ?>
                                            <button type="button" class="button button-small ofac-view-replies" data-request-id="<?php echo esc_attr( $item->id ); ?>">
                                                <?php echo esc_html( $count ); ?>
                                                <span class="dashicons dashicons-email-alt" style="font-size:14px;width:14px;height:14px;line-height:14px;vertical-align:middle;margin-left:2px;"></span>
                                            </button>
                                        <?php else : ?>
                                            <span class="ofac-muted">0</span>
                                        <?php endif; ?>
                                    </td>
                                    <td class="column-date">
                                        <span title="<?php echo esc_attr( wp_date( 'd/m/Y H:i:s', strtotime( $item->created_at ) ) ); ?>">
                                            <?php echo esc_html( $this->time_ago( $item->created_at ) ); ?>
                                        </span>
                                        <?php if ( $item->replied_at ) : ?>
                                            <br><small><?php esc_html_e( 'Répondu', 'anythingllm-chatbot' ); ?>: <?php echo esc_html( wp_date( 'd/m/Y H:i', strtotime( $item->replied_at ) ) ); ?></small>
                                        <?php endif; ?>
                                    </td>
                                    <td class="column-actions">
                                        <?php if ( $item->conversation_id ) : ?>
                                            <a href="<?php echo esc_url( admin_url( 'admin.php?page=ofac-logs&open_conversation=' . $item->conversation_id ) ); ?>"
                                               class="button button-small button-primary"
                                               title="<?php esc_attr_e( 'Voir la conversation et répondre', 'anythingllm-chatbot' ); ?>">
                                                <span class="dashicons dashicons-email" style="font-size:14px;width:14px;height:14px;line-height:14px;vertical-align:middle;"></span>
                                            </a>
                                        <?php endif; ?>
                                        <?php if ( 'pending' === $item->status ) : ?>
                                            <button type="button" class="button button-small ofac-cb-status-btn"
                                                    data-id="<?php echo esc_attr( $item->id ); ?>"
                                                    data-status="replied"
                                                    title="<?php esc_attr_e( 'Marquer comme répondu', 'anythingllm-chatbot' ); ?>">
                                                &#10003;
                                            </button>
                                        <?php endif; ?>
                                        <?php if ( 'pending' === $item->status || 'replied' === $item->status ) : ?>
                                            <button type="button" class="button button-small ofac-cb-status-btn"
                                                    data-id="<?php echo esc_attr( $item->id ); ?>"
                                                    data-status="closed"
                                                    title="<?php esc_attr_e( 'Fermer', 'anythingllm-chatbot' ); ?>">
                                                &#10005;
                                            </button>
                                        <?php endif; ?>
                                        <button type="button" class="button button-small ofac-cb-delete-btn"
                                                data-id="<?php echo esc_attr( $item->id ); ?>"
                                                title="<?php esc_attr_e( 'Supprimer', 'anythingllm-chatbot' ); ?>">
                                            <span class="dashicons dashicons-trash" style="font-size:14px;width:14px;height:14px;line-height:14px;vertical-align:middle;"></span>
                                        </button>
                                    </td>
                                </tr>
                            <?php endforeach; ?>
                        <?php endif; ?>
                    </tbody>
                </table>
            </form>
        </div>

        <!-- Modal for viewing replies -->
        <div id="ofac-replies-modal" class="ofac-modal" style="display:none;">
            <div class="ofac-modal-content">
                <div class="ofac-modal-header">
                    <h2><?php esc_html_e( 'Réponses envoyées', 'anythingllm-chatbot' ); ?></h2>
                    <button type="button" class="ofac-modal-close">&times;</button>
                </div>
                <div class="ofac-modal-body">
                    <div id="ofac-replies-list"></div>
                </div>
            </div>
        </div>
        <?php
    }

    /**
     * Render status badge
     */
    private function render_status_badge( $status ) {
        $labels = array(
            'pending' => __( 'En attente', 'anythingllm-chatbot' ),
            'replied' => __( 'Répondu', 'anythingllm-chatbot' ),
            'closed'  => __( 'Fermé', 'anythingllm-chatbot' ),
        );

        $label = $labels[ $status ] ?? $status;
        return '<span class="ofac-cb-badge ofac-cb-badge--' . esc_attr( $status ) . '">' . esc_html( $label ) . '</span>';
    }

    /**
     * Get status counts
     */
    private function get_status_counts() {
        global $wpdb;
        $table = $wpdb->prefix . 'ofac_callback_requests';

        $results = $wpdb->get_results( "SELECT status, COUNT(*) as count FROM $table GROUP BY status" );

        $counts = array( 'total' => 0 );
        foreach ( $results as $row ) {
            $counts[ $row->status ] = (int) $row->count;
            $counts['total'] += (int) $row->count;
        }

        return $counts;
    }

    /**
     * Time ago helper
     */
    private function time_ago( $datetime ) {
        $now = current_time( 'timestamp' );
        $time = strtotime( $datetime );
        $diff = $now - $time;

        if ( $diff < 60 ) {
            return __( 'A l\'instant', 'anythingllm-chatbot' );
        } elseif ( $diff < 3600 ) {
            $mins = floor( $diff / 60 );
            return sprintf( _n( 'Il y a %d min', 'Il y a %d min', $mins, 'anythingllm-chatbot' ), $mins );
        } elseif ( $diff < 86400 ) {
            $hours = floor( $diff / 3600 );
            return sprintf( _n( 'Il y a %d h', 'Il y a %d h', $hours, 'anythingllm-chatbot' ), $hours );
        } else {
            return wp_date( 'd/m/Y H:i', $time );
        }
    }

    /**
     * Pagination links
     */
    private function pagination_links( $current, $total, $status = '', $search = '' ) {
        $base_url = admin_url( 'admin.php?page=ofac-callbacks' );
        if ( $status ) {
            $base_url .= '&status=' . urlencode( $status );
        }
        if ( $search ) {
            $base_url .= '&s=' . urlencode( $search );
        }

        $output = '<span class="pagination-links">';

        if ( $current > 1 ) {
            $output .= '<a class="prev-page button" href="' . esc_url( $base_url . '&paged=' . ( $current - 1 ) ) . '">&lsaquo;</a> ';
        }

        $output .= '<span class="paging-input">' . $current . ' / ' . $total . '</span>';

        if ( $current < $total ) {
            $output .= ' <a class="next-page button" href="' . esc_url( $base_url . '&paged=' . ( $current + 1 ) ) . '">&rsaquo;</a>';
        }

        $output .= '</span>';

        return $output;
    }

    /**
     * AJAX: Update callback status
     */
    public function ajax_update_status() {
        check_ajax_referer( 'ofac_admin_nonce', 'nonce' );

        if ( ! current_user_can( 'manage_ofac_callbacks' ) ) {
            wp_send_json_error( array( 'message' => 'Unauthorized' ) );
        }

        $id = isset( $_POST['id'] ) ? absint( $_POST['id'] ) : 0;
        $status = isset( $_POST['status'] ) ? sanitize_text_field( $_POST['status'] ) : '';

        if ( ! $id || ! in_array( $status, array( 'pending', 'replied', 'closed' ), true ) ) {
            wp_send_json_error( array( 'message' => 'Invalid parameters' ) );
        }

        global $wpdb;
        $table = $wpdb->prefix . 'ofac_callback_requests';

        $data = array( 'status' => $status );
        if ( 'replied' === $status ) {
            $data['replied_at'] = current_time( 'mysql' );
        }

        $updated = $wpdb->update( $table, $data, array( 'id' => $id ) );

        if ( false === $updated ) {
            wp_send_json_error( array( 'message' => 'Database error' ) );
        }

        wp_send_json_success( array(
            'message' => __( 'Statut mis à jour', 'anythingllm-chatbot' ),
            'badge'   => $this->render_status_badge( $status ),
        ) );
    }

    /**
     * AJAX: Delete callback
     */
    public function ajax_delete_callback() {
        check_ajax_referer( 'ofac_admin_nonce', 'nonce' );

        if ( ! current_user_can( 'manage_ofac_callbacks' ) ) {
            wp_send_json_error( array( 'message' => 'Unauthorized' ) );
        }

        $id = isset( $_POST['id'] ) ? absint( $_POST['id'] ) : 0;
        if ( ! $id ) {
            wp_send_json_error( array( 'message' => 'Invalid ID' ) );
        }

        global $wpdb;
        $table = $wpdb->prefix . 'ofac_callback_requests';

        $deleted = $wpdb->delete( $table, array( 'id' => $id ) );

        if ( false === $deleted ) {
            wp_send_json_error( array( 'message' => 'Database error' ) );
        }

        wp_send_json_success( array( 'message' => __( 'Demande supprimée', 'anythingllm-chatbot' ) ) );
    }

    /**
     * AJAX: Bulk action
     */
    public function ajax_bulk_action() {
        check_ajax_referer( 'ofac_admin_nonce', 'nonce' );

        if ( ! current_user_can( 'manage_ofac_callbacks' ) ) {
            wp_send_json_error( array( 'message' => 'Unauthorized' ) );
        }

        $action = isset( $_POST['bulk_action'] ) ? sanitize_text_field( $_POST['bulk_action'] ) : '';
        $ids = isset( $_POST['ids'] ) ? array_map( 'absint', (array) $_POST['ids'] ) : array();

        if ( empty( $ids ) || ! $action ) {
            wp_send_json_error( array( 'message' => 'Invalid parameters' ) );
        }

        global $wpdb;
        $table = $wpdb->prefix . 'ofac_callback_requests';
        $count = 0;

        if ( 'delete' === $action ) {
            foreach ( $ids as $id ) {
                if ( $wpdb->delete( $table, array( 'id' => $id ) ) ) {
                    $count++;
                }
            }
        } elseif ( in_array( $action, array( 'replied', 'closed' ), true ) ) {
            $data = array( 'status' => $action );
            if ( 'replied' === $action ) {
                $data['replied_at'] = current_time( 'mysql' );
            }
            foreach ( $ids as $id ) {
                if ( $wpdb->update( $table, $data, array( 'id' => $id ) ) !== false ) {
                    $count++;
                }
            }
        }

        wp_send_json_success( array(
            'message' => sprintf( __( '%d demande(s) traitée(s)', 'anythingllm-chatbot' ), $count ),
        ) );
    }

    /**
     * AJAX: Get ticket replies
     */
    public function ajax_get_replies() {
        check_ajax_referer( 'ofac_admin_nonce', 'nonce' );

        if ( ! current_user_can( 'manage_ofac_callbacks' ) ) {
            wp_send_json_error( array( 'message' => 'Unauthorized' ) );
        }

        $request_id = isset( $_POST['request_id'] ) ? absint( $_POST['request_id'] ) : 0;
        if ( ! $request_id ) {
            wp_send_json_error( array( 'message' => 'Invalid ID' ) );
        }

        global $wpdb;
        $replies = $wpdb->get_results(
            $wpdb->prepare(
                "SELECT * FROM {$wpdb->prefix}ofac_ticket_replies WHERE request_id = %d ORDER BY created_at DESC",
                $request_id
            )
        );

        $html = '';
        if ( empty( $replies ) ) {
            $html .= '<p>' . esc_html__( 'Aucune réponse envoyée pour cette demande.', 'anythingllm-chatbot' ) . '</p>';
        } else {
            foreach ( $replies as $reply ) {
                $reply_user = get_userdata( $reply->user_id );
                $author_name = $reply_user ? $reply_user->display_name : __( 'Utilisateur supprimé', 'anythingllm-chatbot' );

                $html .= '<div class="ofac-reply-item">';
                $html .= '<div class="ofac-reply-item-header">';
                $html .= '<strong>' . esc_html( $author_name ) . '</strong>';
                $html .= '<span class="ofac-reply-item-date">' . esc_html( wp_date( 'd/m/Y H:i', strtotime( $reply->created_at ) ) ) . '</span>';
                if ( $reply->email_sent ) {
                    $html .= '<span class="ofac-badge ofac-badge--success">' . esc_html__( 'Envoyé', 'anythingllm-chatbot' ) . '</span>';
                }
                $html .= '</div>';
                $html .= '<div class="ofac-reply-item-subject">';
                $html .= '<strong>' . esc_html__( 'Sujet', 'anythingllm-chatbot' ) . ' :</strong> ' . esc_html( $reply->subject );
                $html .= '</div>';
                $html .= '<div class="ofac-reply-item-body">' . wp_kses_post( nl2br( $reply->body ) ) . '</div>';
                $html .= '</div>';
            }
        }

        wp_send_json_success( array( 'html' => $html ) );
    }
}
