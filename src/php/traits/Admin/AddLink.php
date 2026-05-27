<?php

declare(strict_types=1);

trait LinkDigest_Admin_AddLink {

    public function addLinkPage(): void {
        [$message, $error] = $this->processAddLinkSubmission();

        // Repopulate form fields from POST on submission; requires nonce to be present.
        $nonce_ok        = isset( $_POST['linkdigest_add_nonce'] ) && wp_verify_nonce( sanitize_text_field( wp_unslash( $_POST['linkdigest_add_nonce'] ) ), 'linkdigest_add_link' );
        $current_title   = $nonce_ok && isset( $_POST['linkdigest_title'] )      ? sanitize_text_field( wp_unslash( $_POST['linkdigest_title'] ) )      : '';
        $current_url     = $nonce_ok && isset( $_POST['linkdigest_url'] )        ? esc_url_raw( wp_unslash( $_POST['linkdigest_url'] ) )                : '';
        $current_content = $nonce_ok && isset( $_POST['linkdigest_content'] )    ? wp_kses_post( wp_unslash( $_POST['linkdigest_content'] ) )           : '';
        $current_tags    = $nonce_ok && isset( $_POST['linkdigest_tags'] )       ? sanitize_text_field( wp_unslash( $_POST['linkdigest_tags'] ) )       : '';
        $current_cats    = $nonce_ok && isset( $_POST['linkdigest_categories'] ) ? array_map( 'intval', $_POST['linkdigest_categories'] )               : array();

        // Get all categories (cached for 1 hour, invalidated on term changes)
        $all_categories = $this->getCachedCategories();
        ?>
        <div class="wrap">
            <h1><?php esc_html_e('Add New Link', 'linkdigest'); ?></h1>

            <?php if ($message) : ?>
                <div class="notice notice-success is-dismissible">
                    <p><?php echo esc_html($message); ?></p>
                </div>
            <?php endif; ?>

            <?php if ($error) : ?>
                <div class="notice notice-error is-dismissible">
                    <p><?php echo esc_html($error); ?></p>
                </div>
            <?php endif; ?>

            <form method="post" action="">
                <?php wp_nonce_field('linkdigest_add_link', 'linkdigest_add_nonce'); ?>

                <table class="form-table">
                    <tr>
                        <th scope="row">
                            <label for="linkdigest_title"><?php esc_html_e('Title', 'linkdigest'); ?> <span class="required">*</span></label>
                        </th>
                        <td>
                            <input type="text" name="linkdigest_title" id="linkdigest_title" class="regular-text" value="<?php echo esc_attr($current_title); ?>" required>
                        </td>
                    </tr>

                    <tr>
                        <th scope="row">
                            <label for="linkdigest_url"><?php esc_html_e('URL', 'linkdigest'); ?></label>
                        </th>
                        <td>
                            <input type="url" name="linkdigest_url" id="linkdigest_url" class="regular-text" value="<?php echo esc_attr($current_url); ?>" placeholder="https://example.com">
                        </td>
                    </tr>

                    <tr>
                        <th scope="row">
                            <label for="linkdigest_content"><?php esc_html_e('Text/Description', 'linkdigest'); ?></label>
                        </th>
                        <td>
                            <?php
                            wp_editor($current_content, 'linkdigest_content', array(
                                'textarea_name' => 'linkdigest_content',
                                'textarea_rows' => 10,
                                'media_buttons' => false,
                            ));
                            ?>
                        </td>
                    </tr>

                    <tr>
                        <th scope="row">
                            <span><?php esc_html_e('Categories', 'linkdigest'); ?></span>
                        </th>
                        <td>
                            <fieldset>
                                <legend class="screen-reader-text"><?php esc_html_e('Categories', 'linkdigest'); ?></legend>
                                <?php if (!empty($all_categories)) : ?>
                                    <div class="linkdigest-cat-scroll-list">
                                        <?php foreach ($all_categories as $category) : ?>
                                            <label class="linkdigest-cat-scroll-label">
                                                <input type="checkbox" name="linkdigest_categories[]" value="<?php echo esc_attr($category->term_id); ?>" <?php echo in_array((int) $category->term_id, $current_cats, true) ? 'checked' : ''; ?>>
                                                <?php echo esc_html($category->name); ?>
                                            </label>
                                        <?php endforeach; ?>
                                    </div>
                                <?php else : ?>
                                    <p><?php esc_html_e('No categories available. Create categories first in LinkDigest > Categories.', 'linkdigest'); ?></p>
                                <?php endif; ?>
                            </fieldset>
                        </td>
                    </tr>

                    <tr>
                        <th scope="row">
                            <label for="linkdigest_tags"><?php esc_html_e('Tags', 'linkdigest'); ?></label>
                        </th>
                        <td>
                            <input type="text" name="linkdigest_tags" id="linkdigest_tags" class="regular-text" value="<?php echo esc_attr($current_tags); ?>" placeholder="<?php esc_attr_e('Separate tags with commas', 'linkdigest'); ?>">
                            <p class="description"><?php esc_html_e('Separate multiple tags with commas (e.g., tag1, tag2, tag3)', 'linkdigest'); ?></p>
                        </td>
                    </tr>
                </table>

                <p class="submit">
                    <input type="submit" name="linkdigest_add_submit" id="submit" class="button button-primary" value="<?php esc_attr_e('Add Link', 'linkdigest'); ?>">
                    <a href="<?php echo esc_url(admin_url(self::ADMIN_LINKS_PAGE)); ?>" class="button"><?php esc_html_e('Cancel', 'linkdigest'); ?></a>
                </p>
            </form>
        </div>
        <?php
    }

    private function processAddLinkSubmission(): array {
        if (!isset($_POST['linkdigest_add_submit'])) {
            return ['', ''];
        }

        $nonce = isset($_POST['linkdigest_add_nonce']) ? sanitize_text_field(wp_unslash($_POST['linkdigest_add_nonce'])) : '';
        if (!wp_verify_nonce($nonce, 'linkdigest_add_link')) {
            return ['', __('Security check failed.', 'linkdigest')];
        }

        $input = $this->validateAddLinkInput();
        return ($input['error'] !== '') ? ['', $input['error']] : $this->insertNewLink($input);
    }

    private function validateAddLinkInput(): array {
        $nonce = isset($_POST['linkdigest_add_nonce']) ? sanitize_text_field(wp_unslash($_POST['linkdigest_add_nonce'])) : '';
        if (!wp_verify_nonce($nonce, 'linkdigest_add_link')) {
            return ['title' => '', 'url' => '', 'content' => '', 'categories' => [], 'tags' => '', 'error' => ''];
        }

        $title      = isset($_POST['linkdigest_title'])   ? sanitize_text_field(wp_unslash($_POST['linkdigest_title']))   : '';
        $url        = isset($_POST['linkdigest_url'])     ? esc_url_raw(wp_unslash($_POST['linkdigest_url']))             : '';
        $content    = isset($_POST['linkdigest_content']) ? wp_kses_post(wp_unslash($_POST['linkdigest_content']))        : '';
        $categories = isset($_POST['linkdigest_categories']) ? array_map('intval', $_POST['linkdigest_categories']) : array();
        $tags       = isset($_POST['linkdigest_tags'])    ? sanitize_text_field(wp_unslash($_POST['linkdigest_tags']))    : '';

        $error = empty($title) ? __('Title is required.', 'linkdigest') : '';
        return compact('title', 'url', 'content', 'categories', 'tags', 'error');
    }

    private function insertNewLink(array $input): array {
        $post_id = wp_insert_post(array(
            'post_title'   => $input['title'],
            'post_content' => $input['content'],
            'post_type'    => 'linkdigest',
            'post_status'  => 'linkdigest_pending',
        ));

        if (!$post_id) {
            return ['', __('Failed to add link.', 'linkdigest')];
        }

        if (!empty($input['url'])) {
            update_post_meta($post_id, '_linkdigest_url', $input['url']);
        }
        if (!empty($input['categories'])) {
            wp_set_object_terms($post_id, $input['categories'], 'linkdigest_category');
        }
        if (!empty($input['tags'])) {
            wp_set_object_terms($post_id, array_map('trim', explode(',', $input['tags'])), 'linkdigest_tag');
        }

        delete_transient('linkdigest_publish_stats');
        $_POST = array();
        return [__('Link added successfully!', 'linkdigest'), ''];
    }
}
