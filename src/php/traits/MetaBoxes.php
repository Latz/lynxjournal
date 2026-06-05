<?php

declare(strict_types=1);

trait LynxJournal_MetaBoxes {

    /**
     * Register the URL meta box for the lynxjournal post type.
     *
     * @since 1.0.0
     * @return void
     */
    public function addMetaBoxes(): void {
        add_meta_box(
            'lynxjournal_url',
            __('Link URL', 'lynxjournal'),
            [$this, 'urlMetaBoxCallback'],
            'lynxjournal',
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
        wp_nonce_field('lynxjournal_save_url', 'lynxjournal_url_nonce');
        $url = get_post_meta($post->ID, '_lynxjournal_url', true);
        ?>
        <p>
            <label for="lynxjournal_url_meta"><?php esc_html_e('URL:', 'lynxjournal'); ?></label><br>
            <input type="url" id="lynxjournal_url_meta" name="lynxjournal_url" value="<?php echo esc_attr($url); ?>" size="50" placeholder="https://example.com" class="large-text">
        </p>
        <?php
    }

    /**
     * Save the URL meta value for a lynxjournal post.
     *
     * @since 1.0.0
     * @param int $post_id The post ID.
     * @return void
     */
    public function saveUrl(int $post_id): void {
        // Check nonce
        if (!isset($_POST['lynxjournal_url_nonce']) || !wp_verify_nonce(sanitize_text_field(wp_unslash($_POST['lynxjournal_url_nonce'])), 'lynxjournal_save_url')) {
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
        if (isset($_POST['lynxjournal_url'])) {
            $url = esc_url_raw(wp_unslash($_POST['lynxjournal_url']));
            update_post_meta($post_id, '_lynxjournal_url', $url);
        }
    }
}
