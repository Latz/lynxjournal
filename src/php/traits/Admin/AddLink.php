<?php

declare(strict_types=1);

trait LynxJournal_Admin_AddLink {

    public function addLinkPage(): void {
        [$message, $error] = $this->processAddLinkSubmission();

        // Repopulate form fields from POST on submission; requires nonce to be present.
        $nonce_ok        = isset( $_POST['lynxjournal_add_nonce'] ) && wp_verify_nonce( sanitize_text_field( wp_unslash( $_POST['lynxjournal_add_nonce'] ) ), 'lynxjournal_add_link' );
        $current_title   = $nonce_ok && isset( $_POST['lynxjournal_title'] )      ? sanitize_text_field( wp_unslash( $_POST['lynxjournal_title'] ) )      : '';
        $current_url     = $nonce_ok && isset( $_POST['lynxjournal_url'] )        ? esc_url_raw( wp_unslash( $_POST['lynxjournal_url'] ) )                : '';
        $current_content = $nonce_ok && isset( $_POST['lynxjournal_content'] )    ? wp_kses_post( wp_unslash( $_POST['lynxjournal_content'] ) )           : '';
        $current_tags    = $nonce_ok && isset( $_POST['lynxjournal_tags'] )       ? sanitize_text_field( wp_unslash( $_POST['lynxjournal_tags'] ) )       : '';
        $current_cats    = $nonce_ok && isset( $_POST['lynxjournal_categories'] ) ? array_map( 'intval', $_POST['lynxjournal_categories'] )               : array();

        // Get all categories (cached for 1 hour, invalidated on term changes)
        $all_categories = $this->getCachedCategories();
        ?>
        <div class="wrap">
            <h1><?php esc_html_e('Add New Link', 'lynx-journal'); ?></h1>

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
                <?php wp_nonce_field('lynxjournal_add_link', 'lynxjournal_add_nonce'); ?>

                <table class="form-table">
                    <tr>
                        <th scope="row">
                            <label for="lynxjournal_title"><?php esc_html_e('Title', 'lynx-journal'); ?> <span class="required">*</span></label>
                        </th>
                        <td>
                            <input type="text" name="lynxjournal_title" id="lynxjournal_title" class="regular-text" value="<?php echo esc_attr($current_title); ?>" required>
                        </td>
                    </tr>

                    <tr>
                        <th scope="row">
                            <label for="lynxjournal_url"><?php esc_html_e('URL', 'lynx-journal'); ?></label>
                        </th>
                        <td>
                            <input type="url" name="lynxjournal_url" id="lynxjournal_url" class="regular-text" value="<?php echo esc_attr($current_url); ?>" placeholder="https://example.com">
                        </td>
                    </tr>

                    <tr>
                        <th scope="row">
                            <label for="lynxjournal_content"><?php esc_html_e('Text/Description', 'lynx-journal'); ?></label>
                        </th>
                        <td>
                            <?php
                            wp_editor($current_content, 'lynxjournal_content', array(
                                'textarea_name' => 'lynxjournal_content',
                                'textarea_rows' => 10,
                                'media_buttons' => false,
                            ));
                            ?>
                        </td>
                    </tr>

                    <tr>
                        <th scope="row">
                            <span><?php esc_html_e('Categories', 'lynx-journal'); ?></span>
                        </th>
                        <td>
                            <fieldset>
                                <legend class="screen-reader-text"><?php esc_html_e('Categories', 'lynx-journal'); ?></legend>
                                <?php if (!empty($all_categories)) : ?>
                                    <div class="lynxjournal-cat-scroll-list">
                                        <?php foreach ($all_categories as $category) : ?>
                                            <label class="lynxjournal-cat-scroll-label">
                                                <input type="checkbox" name="lynxjournal_categories[]" value="<?php echo esc_attr($category->term_id); ?>" <?php echo in_array((int) $category->term_id, $current_cats, true) ? 'checked' : ''; ?>>
                                                <?php echo esc_html($category->name); ?>
                                            </label>
                                        <?php endforeach; ?>
                                    </div>
                                <?php else : ?>
                                    <p><?php esc_html_e('No categories available. Create categories first in LynxJournal > Categories.', 'lynx-journal'); ?></p>
                                <?php endif; ?>
                            </fieldset>
                        </td>
                    </tr>

                    <tr>
                        <th scope="row">
                            <label for="lynxjournal_tags"><?php esc_html_e('Tags', 'lynx-journal'); ?></label>
                        </th>
                        <td>
                            <input type="text" name="lynxjournal_tags" id="lynxjournal_tags" class="regular-text" value="<?php echo esc_attr($current_tags); ?>" placeholder="<?php esc_attr_e('Separate tags with commas', 'lynx-journal'); ?>">
                            <p class="description"><?php esc_html_e('Separate multiple tags with commas (e.g., tag1, tag2, tag3)', 'lynx-journal'); ?></p>
                        </td>
                    </tr>
                </table>

                <p class="submit">
                    <input type="submit" name="lynxjournal_add_submit" id="submit" class="button button-primary" value="<?php esc_attr_e('Add Link', 'lynx-journal'); ?>">
                    <a href="<?php echo esc_url(admin_url(self::ADMIN_LINKS_PAGE)); ?>" class="button"><?php esc_html_e('Cancel', 'lynx-journal'); ?></a>
                </p>
            </form>
        </div>
        <?php
    }

    private function processAddLinkSubmission(): array {
        if (!isset($_POST['lynxjournal_add_submit'])) {
            return ['', ''];
        }

        $nonce = isset($_POST['lynxjournal_add_nonce']) ? sanitize_text_field(wp_unslash($_POST['lynxjournal_add_nonce'])) : '';
        if (!wp_verify_nonce($nonce, 'lynxjournal_add_link')) {
            return ['', __('Security check failed.', 'lynx-journal')];
        }

        if (!current_user_can('edit_posts')) {
            return ['', __('Insufficient permissions.', 'lynx-journal')];
        }

        $input = $this->validateAddLinkInput();
        return ($input['error'] !== '') ? ['', $input['error']] : $this->insertNewLink($input);
    }

    private function validateAddLinkInput(): array {
        $nonce = isset($_POST['lynxjournal_add_nonce']) ? sanitize_text_field(wp_unslash($_POST['lynxjournal_add_nonce'])) : '';
        if (!wp_verify_nonce($nonce, 'lynxjournal_add_link')) {
            return ['title' => '', 'url' => '', 'content' => '', 'categories' => [], 'tags' => '', 'error' => ''];
        }

        $title      = isset($_POST['lynxjournal_title'])   ? sanitize_text_field(wp_unslash($_POST['lynxjournal_title']))   : '';
        $url        = isset($_POST['lynxjournal_url'])     ? esc_url_raw(wp_unslash($_POST['lynxjournal_url']))             : '';
        $content    = isset($_POST['lynxjournal_content']) ? wp_kses_post(wp_unslash($_POST['lynxjournal_content']))        : '';
        $categories = isset($_POST['lynxjournal_categories']) ? array_map('intval', $_POST['lynxjournal_categories']) : array();
        $tags       = isset($_POST['lynxjournal_tags'])    ? sanitize_text_field(wp_unslash($_POST['lynxjournal_tags']))    : '';

        $error = empty($title) ? __('Title is required.', 'lynx-journal') : '';
        return compact('title', 'url', 'content', 'categories', 'tags', 'error');
    }

    private function insertNewLink(array $input): array {
        $post_id = wp_insert_post(array(
            'post_title'   => $input['title'],
            'post_content' => $input['content'],
            'post_type'    => 'lynx-journal',
            'post_status'  => 'lynxjournal_pending',
        ));

        if (!$post_id) {
            return ['', __('Failed to add link.', 'lynx-journal')];
        }

        if (!empty($input['url'])) {
            update_post_meta($post_id, '_lynxjournal_url', $input['url']);
        }
        if (!empty($input['categories'])) {
            wp_set_object_terms($post_id, $input['categories'], 'lynxjournal_category');
        }
        if (!empty($input['tags'])) {
            wp_set_object_terms($post_id, array_map('trim', explode(',', $input['tags'])), 'lynxjournal_tag');
        }

        delete_transient('lynxjournal_publish_stats');
        $_POST = array();
        return [__('Link added successfully!', 'lynx-journal'), ''];
    }
}
