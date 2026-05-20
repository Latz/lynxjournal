<?php

declare(strict_types=1);

trait LinkDigest_Templates {

    /**
     * Register the hidden linkdigest_template post type used as Gutenberg template canvases.
     *
     * @since 2.1.0
     * @return void
     */
    public function registerTemplateCPT(): void {
        register_post_type('linkdigest_template', array(
            'label'           => __('Link Templates', 'linkdigest'),
            'public'          => false,
            'show_ui'         => true,
            'show_in_menu'    => false,
            'show_in_rest'    => true,
            'supports'        => array('editor'),
            'capability_type' => 'post',
            'map_meta_cap'    => true,
        ));
    }

    /**
     * Register placeholder blocks and their server-side render callbacks.
     *
     * @since 2.1.0
     * @return void
     */
    public function registerPlaceholderBlocks(): void {
        $asset_file = plugin_dir_path(LINKDIGEST_PLUGIN_FILE) . 'build/linkdigest-blocks.asset.php';
        if (!file_exists($asset_file)) {
            return;
        }

        $asset = require $asset_file;

        wp_register_script(
            'linkdigest-blocks',
            plugin_dir_url(LINKDIGEST_PLUGIN_FILE) . 'build/linkdigest-blocks.js',
            $asset['dependencies'],
            $asset['version'],
            true
        );

        $blocks = array(
            'field-title'       => array($this, 'renderBlockFieldTitle'),
            'field-title-link'  => array($this, 'renderBlockFieldTitleLink'),
            'field-url'         => array($this, 'renderBlockFieldUrl'),
            'field-description' => array($this, 'renderBlockFieldDescription'),
            'field-read-more'   => array($this, 'renderBlockFieldReadMore'),
            'field-tags'        => array($this, 'renderBlockFieldTags'),
        );

        foreach ($blocks as $block_name => $callback) {
            register_block_type("linkdigest/{$block_name}", array(
                'api_version'     => 3,
                'editor_script'   => 'linkdigest-blocks',
                'render_callback' => $callback,
            ));
        }

        register_block_type('linkdigest/field-category', array(
            'api_version'     => 3,
            'editor_script'   => 'linkdigest-blocks',
            'attributes'      => array(
                'level' => array(
                    'type'    => 'number',
                    'default' => 2,
                ),
            ),
            'render_callback' => array($this, 'renderBlockFieldCategory'),
        ));
    }

    /**
     * Ensure default template posts exist. Called on admin_init.
     * Bails immediately if options already point to valid posts.
     *
     * @since 2.1.0
     * @return void
     */
    public function ensureTemplatePosts(): void {
        $single_default =
            "<!-- wp:linkdigest/field-title /-->\n\n" .
            "<!-- wp:linkdigest/field-description /-->\n\n" .
            "<!-- wp:linkdigest/field-read-more /-->";

        $digest_default =
            "<!-- wp:linkdigest/field-title-link /-->\n\n" .
            "<!-- wp:linkdigest/field-description /-->";

        $digest_group_default = "<!-- wp:linkdigest/field-category /-->";

        $this->ensureTemplatePost('single_link',   __('LinkDigest: Single Link Template',   'linkdigest'), $single_default);
        $this->ensureTemplatePost('digest_item',  __('LinkDigest: Digest Item Template',  'linkdigest'), $digest_default);
        $this->ensureTemplatePost('digest_group', __('LinkDigest: Digest Group Template', 'linkdigest'), $digest_group_default);
    }

    /**
     * Create a template post if it doesn't already exist.
     *
     * @since 2.1.0
     * @param string $type            Identifier used as part of the option key.
     * @param string $title           Post title.
     * @param string $default_content Default block content.
     * @return void
     */
    private function ensureTemplatePost(string $type, string $title, string $default_content): void {
        $option_key = "linkdigest_template_{$type}";
        $post_id    = (int) get_option($option_key, 0);

        if ($post_id && get_post($post_id)) {
            return;
        }

        $new_id = wp_insert_post(array(
            'post_type'    => 'linkdigest_template',
            'post_title'   => $title,
            'post_content' => $default_content,
            'post_status'  => 'publish',
        ));

        if (!is_wp_error($new_id)) {
            update_option($option_key, $new_id);
        }
    }

    /**
     * Return the post ID of a template, or null if none exists.
     *
     * @since 2.1.0
     * @param string $type Template identifier ('single_link' or 'digest_item').
     * @return int|null
     */
    public function getTemplatePostId(string $type): ?int {
        $post_id = (int) get_option("linkdigest_template_{$type}", 0);
        return ($post_id && get_post($post_id)) ? $post_id : null;
    }

    /**
     * Render a template post by substituting placeholder blocks with real data.
     * Returns empty string if no template is configured or the template is empty.
     *
     * @since 2.1.0
     * @param string $type Template identifier.
     * @param array  $data Associative array of link data passed to render callbacks.
     * @return string Rendered HTML.
     */
    public function renderTemplate(string $type, array $data): string {
        $post_id = $this->getTemplatePostId($type);
        if (!$post_id) {
            return '';
        }

        $post = get_post($post_id);
        if (!$post || empty($post->post_content)) {
            return '';
        }

        $GLOBALS['linkdigest_template_data'] = $data;
        $rendered = do_blocks($post->post_content);
        unset($GLOBALS['linkdigest_template_data']);

        return trim($rendered);
    }

    /**
     * Return which template type is being edited on the current admin screen,
     * or null when not on a linkdigest_template post edit screen.
     *
     * @since 2.1.0
     * @return string|null 'single_link', 'digest_item', or null.
     */
    public function isLinkDigestTemplate(): ?string {
        global $pagenow, $post;

        if ($pagenow !== 'post.php' || !$post || $post->post_type !== 'linkdigest_template') {
            return null;
        }
        if ($post->ID === $this->getTemplatePostId('single_link')) {
            return 'single_link';
        }
        if ($post->ID === $this->getTemplatePostId('digest_item')) {
            return 'digest_item';
        }
        if ($post->ID === $this->getTemplatePostId('digest_group')) {
            return 'digest_group';
        }
        return null;
    }

    /**
     * Render the template editor page for a given template type.
     * Embeds the Gutenberg editor in an iframe so the user stays within the plugin.
     *
     * @since 2.1.0
     * @param string $type Template identifier ('single_link' or 'digest_item').
     * @return void
     */
    public function renderTemplateEditorFrame(string $type): void {
        $post_id = $this->getTemplatePostId($type);

        if (!$post_id) {
            echo '<div class="wrap"><p>' . esc_html__('Template post not found. Please deactivate and reactivate the plugin.', 'linkdigest') . '</p></div>';
            return;
        }

        $editor_url = admin_url('post.php?post=' . $post_id . '&action=edit');
        ?>
        <iframe
            class="linkdigest-editor-frame"
            src="<?php echo esc_url($editor_url); ?>"
            title="<?php esc_attr_e('Template Editor', 'linkdigest'); ?>"
        ></iframe>
        <?php
    }

    // -------------------------------------------------------------------------
    // Block render callbacks
    // -------------------------------------------------------------------------

    /**
     * @since 2.1.0
     * @param array $attributes Block attributes (unused).
     * @return string
     */
    public function renderBlockFieldTitle(array $attributes): string {
        $data  = $GLOBALS['linkdigest_template_data'] ?? array();
        $title = $data['title'] ?? '';
        return empty($title) ? '' : '<h2>' . esc_html($title) . '</h2>';
    }

    /**
     * @since 2.1.0
     * @param array $attributes Block attributes (unused).
     * @return string
     */
    public function renderBlockFieldTitleLink(array $attributes): string {
        $data  = $GLOBALS['linkdigest_template_data'] ?? array();
        $title = $data['title'] ?? '';
        $url   = $data['url']   ?? '';

        if (empty($title)) {
            return '';
        }
        if (empty($url)) {
            return esc_html($title);
        }
        return '<a href="' . esc_url($url) . '" target="_blank" rel="noopener">' . esc_html($title) . '</a>';
    }

    /**
     * @since 2.1.0
     * @param array $attributes Block attributes (unused).
     * @return string
     */
    public function renderBlockFieldUrl(array $attributes): string {
        $data = $GLOBALS['linkdigest_template_data'] ?? array();
        $url  = $data['url'] ?? '';
        return empty($url) ? '' : esc_url($url);
    }

    /**
     * @since 2.1.0
     * @param array $attributes Block attributes (unused).
     * @return string
     */
    public function renderBlockFieldDescription(array $attributes): string {
        $data        = $GLOBALS['linkdigest_template_data'] ?? array();
        $description = $data['description'] ?? '';
        return empty($description) ? '' : wp_kses_post($description);
    }

    /**
     * @since 2.1.0
     * @param array $attributes Block attributes (unused).
     * @return string
     */
    public function renderBlockFieldReadMore(array $attributes): string {
        $data = $GLOBALS['linkdigest_template_data'] ?? array();
        $url  = $data['url'] ?? '';
        if (empty($url)) {
            return '';
        }
        return '<p><a href="' . esc_url($url) . '">' . esc_html__('Read more', 'linkdigest') . ' &rarr;</a></p>';
    }

    /**
     * @since 2.1.0
     * @param array $attributes Block attributes (unused).
     * @return string
     */
    public function renderBlockFieldTags(array $attributes): string {
        $data = $GLOBALS['linkdigest_template_data'] ?? array();
        $tags = $data['tags'] ?? array();
        return empty($tags) ? '' : '<p>' . esc_html(implode(', ', $tags)) . '</p>';
    }

    /**
     * @since 2.2.0
     * @param array $attributes Block attributes. Expects 'level' (int 1–6, default 2).
     * @return string
     */
    public function renderBlockFieldCategory(array $attributes): string {
        $data     = $GLOBALS['linkdigest_template_data'] ?? array();
        $category = $data['category'] ?? '';
        if (empty($category)) {
            return '';
        }
        $level = isset($attributes['level']) ? (int) $attributes['level'] : 2;
        $level = max(1, min(6, $level));
        $tag   = "h{$level}";
        return "<{$tag}>" . esc_html($category) . "</{$tag}>";
    }
}
