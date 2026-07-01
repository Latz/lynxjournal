<?php

declare(strict_types=1);

trait LynxJournal_Admin_Dashboard_Actions {

    /**
     * Get all unpublished link IDs in oldest-first order.
     *
     * @since 1.0.0
     * @return array Array of unpublished link post IDs.
     */
    public function getUnpublishedLinkIds(): array {
        return get_posts( array(
            'post_type'      => 'lynx-journal',
            'post_status'    => 'lynxjournal_pending',
            'posts_per_page' => self::UNPUBLISHED_PAGE_SIZE,
            'fields'         => 'ids',
            'orderby'        => 'date',
            'order'          => 'ASC', // oldest first: used by age-mode trigger and batch ordering
        ) );
    }

    /**
     * Handle batch publish form submission.
     *
     * @since 1.0.0
     * @return array|null Batch result or null if no request was made.
     */
    public function handleBatchPublishRequest(): ?array {
        if ( ! isset( $_POST['lynxjournal_batch_publish'] ) ) {
            return null;
        }
        $nonce = isset( $_POST['lynxjournal_batch_nonce'] ) ? sanitize_text_field( wp_unslash( $_POST['lynxjournal_batch_nonce'] ) ) : '';
        if ( ! wp_verify_nonce( $nonce, 'lynxjournal_batch_publish' ) ) {
            return null;
        }
        if ( ! current_user_can( 'publish_posts' ) ) {
            return null;
        }
        $as_draft = isset( $_POST['publish_as_draft'] ) && sanitize_text_field( wp_unslash( $_POST['publish_as_draft'] ) ) === '1';
        return $this->batchPublishLinks( $this->getUnpublishedLinkIds(), $as_draft );
    }

    /**
     * Handle roundup creation form submission.
     *
     * @since 1.0.0
     * @return array|null Roundup result or null if no request was made.
     */
    public function handleRoundupRequest(): ?array {
        if ( ! isset( $_POST['lynxjournal_create_roundup'] ) ) {
            return null;
        }
        $nonce = isset( $_POST['lynxjournal_roundup_nonce'] ) ? sanitize_text_field( wp_unslash( $_POST['lynxjournal_roundup_nonce'] ) ) : '';
        if ( ! wp_verify_nonce( $nonce, 'lynxjournal_create_roundup' ) ) {
            return null;
        }
        if ( ! current_user_can( 'publish_posts' ) ) {
            return null;
        }
        $roundup_title = isset( $_POST['roundup_title'] ) ? sanitize_text_field( wp_unslash( $_POST['roundup_title'] ) ) : '';
        $as_draft      = isset( $_POST['roundup_as_draft'] ) && sanitize_text_field( wp_unslash( $_POST['roundup_as_draft'] ) ) === '1';
        return $this->createRoundupPost( $this->getUnpublishedLinkIds(), $roundup_title, $as_draft );
    }

    /**
     * Handle quick add link form submission.
     *
     * @since 1.0.0
     * @return bool True if link was added successfully.
     */
    public function handleQuickAddRequest(): bool {
        if ( ! isset( $_POST['lynxjournal_quick_add'] ) ) {
            return false;
        }
        $nonce    = isset( $_POST['lynxjournal_quick_nonce'] ) ? sanitize_text_field( wp_unslash( $_POST['lynxjournal_quick_nonce'] ) ) : '';
        $title    = isset( $_POST['quick_title'] )    ? sanitize_text_field( wp_unslash( $_POST['quick_title'] ) )    : '';
        $url      = isset( $_POST['quick_url'] )      ? esc_url_raw( wp_unslash( $_POST['quick_url'] ) )              : '';
        $category = isset( $_POST['quick_category'] ) ? (int) $_POST['quick_category']                                : 0;
        if ( ! wp_verify_nonce( $nonce, 'lynxjournal_quick_add_link' ) || empty( $title ) || empty( $url ) || $category <= 0 ) {
            return false;
        }
        if ( ! current_user_can( 'edit_posts' ) ) {
            return false;
        }
        $post_id = wp_insert_post( array(
            'post_title'  => $title,
            'post_type'   => 'lynx-journal',
            'post_status' => 'lynxjournal_pending',
        ) );
        if ( $post_id ) {
            if ( ! empty( $url ) ) {
                update_post_meta( $post_id, '_lynxjournal_url', $url );
            }
            if ( $category > 0 ) {
                wp_set_post_terms( $post_id, array( $category ), 'lynxjournal_category' );
            }
        }
        return (bool) $post_id;
    }
}
