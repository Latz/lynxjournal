<?php

declare(strict_types=1);

trait LinkDigest_MetaBoxes {

    /**
     * Register the URL meta box for the linkdigest post type.
     *
     * @since 1.0.0
     * @return void
     */
    public function addMetaBoxes(): void {
        add_meta_box(
            'linkdigest_url',
            __('Link URL', 'linkdigest'),
            [$this, 'urlMetaBoxCallback'],
            'linkdigest',
            'normal',
            'high'
        );
    }

    /**
     * Render the URL meta box callback.
     *
     * @since 1.0.0
     * @param \WP_Post $post The current post object.
     * @return void
     */
    public function urlMetaBoxCallback(\WP_Post $post): void {
        wp_nonce_field('linkdigest_save_url', 'linkdigest_url_nonce');
        $url = get_post_meta($post->ID, '_linkdigest_url', true);
        ?>
        <p>
            <label for="linkdigest_url_meta"><?php esc_html_e('URL:', 'linkdigest'); ?></label><br>
            <input type="url" id="linkdigest_url_meta" name="linkdigest_url" value="<?php echo esc_attr($url); ?>" size="50" placeholder="https://example.com" class="large-text">
        </p>
        <?php
    }

    /**
     * Save the URL meta value for a linkdigest post.
     *
     * @since 1.0.0
     * @param int $post_id The post ID.
     * @return void
     */
    public function saveUrl(int $post_id): void {
        // Check nonce
        if (!isset($_POST['linkdigest_url_nonce']) || !wp_verify_nonce(sanitize_text_field(wp_unslash($_POST['linkdigest_url_nonce'])), 'linkdigest_save_url')) {
            return;
        }

        // Check autosave
        if (defined('DOING_AUTOSAVE') && DOING_AUTOSAVE) {
            return;
        }

        // Check permissions
        if (!current_user_can('edit_post', $post_id)) {
            return;
        }

        // Save URL
        if (isset($_POST['linkdigest_url'])) {
            $url = esc_url_raw(wp_unslash($_POST['linkdigest_url']));
            update_post_meta($post_id, '_linkdigest_url', $url);
        }
    }
}
