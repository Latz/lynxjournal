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
        // phpcs:ignore WordPress.Security.NonceVerification.Missing -- nonce is verified in checkBatchPublishPermissions() below
        if ( ! isset( $_POST['lynxjournal_batch_publish'] ) ) {
            return null;
        }
        if ( ! $this->checkBatchPublishPermissions() ) {
            return null;
        }
        // phpcs:ignore WordPress.Security.NonceVerification.Missing -- nonce is verified in checkBatchPublishPermissions() above
        $as_draft = isset( $_POST['publish_as_draft'] ) && sanitize_text_field( wp_unslash( $_POST['publish_as_draft'] ) ) === '1';
        return $this->batchPublishLinks( $this->getUnpublishedLinkIds(), $as_draft );
    }

    /**
     * Verify the nonce and capability required to run a batch publish request.
     *
     * @since 1.0.0
     * @return bool
     */
    private function checkBatchPublishPermissions(): bool {
        $nonce = isset( $_POST['lynxjournal_batch_nonce'] ) ? sanitize_text_field( wp_unslash( $_POST['lynxjournal_batch_nonce'] ) ) : '';
        if ( ! wp_verify_nonce( $nonce, 'lynxjournal_batch_publish' ) ) {
            return false;
        }
        return current_user_can( 'publish_posts' );
    }

    /**
     * Handle roundup creation form submission.
     *
     * @since 1.0.0
     * @return array|null Roundup result or null if no request was made.
     */
    public function handleRoundupRequest(): ?array {
        // phpcs:ignore WordPress.Security.NonceVerification.Missing -- nonce is verified in checkRoundupPermissions() below
        if ( ! isset( $_POST['lynxjournal_create_roundup'] ) ) {
            return null;
        }
        if ( ! $this->checkRoundupPermissions() ) {
            return null;
        }
        // phpcs:ignore WordPress.Security.NonceVerification.Missing -- nonce is verified in checkRoundupPermissions() above
        $roundup_title = isset( $_POST['roundup_title'] ) ? sanitize_text_field( wp_unslash( $_POST['roundup_title'] ) ) : '';
        // phpcs:ignore WordPress.Security.NonceVerification.Missing -- nonce is verified in checkRoundupPermissions() above
        $as_draft      = isset( $_POST['roundup_as_draft'] ) && sanitize_text_field( wp_unslash( $_POST['roundup_as_draft'] ) ) === '1';
        return $this->createRoundupPost( $this->getUnpublishedLinkIds(), $roundup_title, $as_draft );
    }

    /**
     * Verify the nonce and capability required to run a roundup creation request.
     *
     * @since 1.0.0
     * @return bool
     */
    private function checkRoundupPermissions(): bool {
        $nonce = isset( $_POST['lynxjournal_roundup_nonce'] ) ? sanitize_text_field( wp_unslash( $_POST['lynxjournal_roundup_nonce'] ) ) : '';
        if ( ! wp_verify_nonce( $nonce, 'lynxjournal_create_roundup' ) ) {
            return false;
        }
        return current_user_can( 'publish_posts' );
    }

    /**
     * Handle quick add link form submission.
     *
     * @since 1.0.0
     * @return bool True if link was added successfully.
     */
    public function handleQuickAddRequest(): bool {
        $input = $this->validateQuickAddInput();
        if ( $input === null ) {
            return false;
        }
        return $this->insertQuickAddLink( $input );
    }

    /**
     * Validate and sanitize a quick-add-link form submission.
     *
     * @since 1.0.0
     * @return array{title: string, url: string, category: int}|null Validated input, or null if absent/invalid.
     */
    private function validateQuickAddInput(): ?array {
        // phpcs:ignore WordPress.Security.NonceVerification.Missing -- nonce is verified in checkQuickAddPermissions() below
        if ( ! isset( $_POST['lynxjournal_quick_add'] ) ) {
            return null;
        }
        // phpcs:disable WordPress.Security.NonceVerification.Missing -- nonce is verified in checkQuickAddPermissions() below
        $title    = isset( $_POST['quick_title'] )    ? sanitize_text_field( wp_unslash( $_POST['quick_title'] ) )    : '';
        $url      = isset( $_POST['quick_url'] )      ? esc_url_raw( wp_unslash( $_POST['quick_url'] ) )              : '';
        $category = isset( $_POST['quick_category'] ) ? (int) $_POST['quick_category']                                : 0;
        // phpcs:enable WordPress.Security.NonceVerification.Missing
        if ( ! $this->checkQuickAddPermissions( $title, $url, $category ) ) {
            return null;
        }
        return compact( 'title', 'url', 'category' );
    }

    /**
     * Verify the nonce, required fields, and capability for a quick-add-link submission.
     *
     * @since 1.0.0
     * @param string $title    Sanitized title from the submission.
     * @param string $url      Sanitized URL from the submission.
     * @param int    $category Selected category term ID.
     * @return bool
     */
    private function checkQuickAddPermissions( string $title, string $url, int $category ): bool {
        $nonce = isset( $_POST['lynxjournal_quick_nonce'] ) ? sanitize_text_field( wp_unslash( $_POST['lynxjournal_quick_nonce'] ) ) : '';
        if ( ! wp_verify_nonce( $nonce, 'lynxjournal_quick_add_link' ) || empty( $title ) || empty( $url ) || $category <= 0 ) {
            return false;
        }
        return current_user_can( 'edit_posts' );
    }

    /**
     * Insert a link post from validated quick-add input.
     *
     * @since 1.0.0
     * @param array{title: string, url: string, category: int} $input Validated quick-add input.
     * @return bool True if the link was inserted successfully.
     */
    private function insertQuickAddLink( array $input ): bool {
        $post_id = wp_insert_post( array(
            'post_title'  => $input['title'],
            'post_type'   => 'lynx-journal',
            'post_status' => 'lynxjournal_pending',
        ) );
        if ( $post_id ) {
            if ( ! empty( $input['url'] ) ) {
                update_post_meta( $post_id, '_lynxjournal_url', $input['url'] );
            }
            if ( $input['category'] > 0 ) {
                wp_set_post_terms( $post_id, array( $input['category'] ), 'lynxjournal_category' );
            }
        }
        return (bool) $post_id;
    }
}
