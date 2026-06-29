<?php

declare(strict_types=1);

trait LynxJournal_Admin_TemplatePage {

    /**
     * Render the post template settings page.
     *
     * @since 1.0.0
     * @return void
     */
    public function templatePage(): void {
        $nonce = isset($_POST['lynxjournal_template_nonce'])
            ? sanitize_text_field(wp_unslash($_POST['lynxjournal_template_nonce']))
            : '';

        if (
            isset($_POST['lynxjournal_post_template']) &&
            wp_verify_nonce($nonce, 'lynxjournal_template') &&
            current_user_can('edit_posts')
        ) {
            $template = sanitize_textarea_field(wp_unslash($_POST['lynxjournal_post_template']));
            update_option('lynxjournal_post_template', $template);
            echo '<div class="notice notice-success is-dismissible"><p>' . esc_html__('Template saved.', 'lynx-journal') . '</p></div>';
        }

        $template = get_option('lynxjournal_post_template', '');

        $token_groups = [
            __('Struktur', 'lynx-journal') => [
                '[category_start]' => __('Beginn eines Kategorieblocks', 'lynx-journal'),
                '[category_end]'   => __('Ende eines Kategorieblocks', 'lynx-journal'),
                '[link_start]'     => __('Beginn eines Linkeintrags', 'lynx-journal'),
                '[link_end]'       => __('Ende eines Linkeintrags', 'lynx-journal'),
            ],
            __('Beitrag', 'lynx-journal') => [
                '[title]'     => __('Titel des Roundup-Beitrags', 'lynx-journal'),
                '[date]'      => __('Veröffentlichungsdatum', 'lynx-journal'),
                '[author]'    => __('Name des Beitragsautors', 'lynx-journal'),
                '[site_name]' => __('Name des Blogs', 'lynx-journal'),
            ],
            __('Links', 'lynx-journal') => [
                '[link_count]'       => __('Anzahl Links im Roundup', 'lynx-journal'),
                '[link]'             => __('Link als Markdown-Hyperlink (Titel + URL)', 'lynx-journal'),
                '[link_description]' => __('Beschreibungstext des Links', 'lynx-journal'),
                '[link_domain]'      => __('Domain der URL (z. B. "example.com")', 'lynx-journal'),
                '[link_date]'        => __('Datum, wann der Link gespeichert wurde', 'lynx-journal'),
            ],
            __('Kategorien', 'lynx-journal') => [
                '[category_name]'       => __('Primäre Kategorie', 'lynx-journal'),
                '[category_link_count]' => __('Anzahl Links in der Kategorie', 'lynx-journal'),
                '[category_list]'       => __('Alle Kategorien, kommagetrennt', 'lynx-journal'),
            ],
            __('Tags', 'lynx-journal') => [
                '[tags]' => __('Tags des Links, kommagetrennt', 'lynx-journal'),
            ],
        ];
        ?>
        <div class="wrap">
            <h1><?php esc_html_e('Post Template', 'lynx-journal'); ?></h1>

            <div class="card lynxjournal-template-card">
                <form method="post" action="">
                    <?php wp_nonce_field('lynxjournal_template', 'lynxjournal_template_nonce'); ?>

                    <div class="lynxjournal-settings-field">
                        <label class="lynxjournal-settings-label" for="lynxjournal-post-template">
                            <?php esc_html_e('Template', 'lynx-journal'); ?>
                        </label>
                        <div class="lynxjournal-format-toolbar">
                            <button type="button" class="button lynxjournal-format-btn" data-action="undo" title="Undo"><svg viewBox="0 0 1024 1024" xmlns="http://www.w3.org/2000/svg" aria-hidden="true" focusable="false"><path transform="matrix(1,0,0,-1,0,1024)" d="M761.862-64c113.726 206.032 132.888 520.306-313.862 509.824v-253.824l-384 384 384 384v-248.372c534.962 13.942 594.57-472.214 313.862-775.628z"/></svg></button>
                            <button type="button" class="button lynxjournal-format-btn" data-action="redo" title="Redo"><svg viewBox="0 0 1024 1024" xmlns="http://www.w3.org/2000/svg" aria-hidden="true" focusable="false"><path transform="matrix(1,0,0,-1,0,1024)" d="M576 711.628v248.372l384-384-384-384v253.824c-446.75 10.482-427.588-303.792-313.86-509.824-280.712 303.414-221.1 789.57 313.86 775.628z"/></svg></button>
                            <button type="button" class="button lynxjournal-format-btn" data-action="bold" title="Bold"><svg viewBox="0 0 1024 1024" xmlns="http://www.w3.org/2000/svg" aria-hidden="true" focusable="false"><path transform="matrix(1,0,0,-1,0,1024)" d="M707.88 475.348c37.498 44.542 60.12 102.008 60.12 164.652 0 141.16-114.842 256-256 256h-320v-896h384c141.158 0 256 114.842 256 256 0 92.956-49.798 174.496-124.12 219.348zM384 768h101.5c55.968 0 101.5-57.42 101.5-128s-45.532-128-101.5-128h-101.5v256zM543 128h-159v256h159c58.45 0 106-57.42 106-128s-47.55-128-106-128z"/></svg></button>
                            <button type="button" class="button lynxjournal-format-btn" data-action="italic" title="Italic"><svg viewBox="0 0 1024 1024" xmlns="http://www.w3.org/2000/svg" aria-hidden="true" focusable="false"><path transform="matrix(1,0,0,-1,0,1024)" d="M896 896v-64h-128l-320-768h128v-64h-448v64h128l320 768h-128v64z"/></svg></button>
                            <button type="button" class="button lynxjournal-format-btn" data-action="underline" title="Underline"><svg viewBox="0 0 1024 1024" xmlns="http://www.w3.org/2000/svg" aria-hidden="true" focusable="false"><path transform="matrix(1,0,0,-1,0,1024)" d="M704 896h128v-416c0-159.058-143.268-288-320-288-176.73 0-320 128.942-320 288v416h128v-416c0-40.166 18.238-78.704 51.354-108.506 36.896-33.204 86.846-51.494 140.646-51.494s103.75 18.29 140.646 51.494c33.116 29.802 51.354 68.34 51.354 108.506v416zM192 128h640v-128h-640z"/></svg></button>
                            <select id="lynxjournal-heading-select" class="lynxjournal-heading-select">
                                <option value=""><?php esc_html_e('Heading', 'lynx-journal'); ?></option>
                                <option value="h1">H1</option>
                                <option value="h2">H2</option>
                                <option value="h3">H3</option>
                                <option value="h4">H4</option>
                                <option value="h5">H5</option>
                                <option value="h6">H6</option>
                            </select>
                            <button type="button" class="button lynxjournal-format-btn" data-action="list" title="Bullet list"><svg viewBox="0 0 1024 1024" xmlns="http://www.w3.org/2000/svg" aria-hidden="true" focusable="false"><path transform="matrix(1,0,0,-1,0,1024)" d="M384 896h640v-128h-640v128zM384 512h640v-128h-640v128zM384 128h640v-128h-640v128zM0 832c0 70.692 57.308 128 128 128s128-57.308 128-128c0-70.692-57.308-128-128-128s-128 57.308-128 128zM0 448c0 70.692 57.308 128 128 128s128-57.308 128-128c0-70.692-57.308-128-128-128s-128 57.308-128 128zM0 64c0 70.692 57.308 128 128 128s128-57.308 128-128c0-70.692-57.308-128-128-128s-128 57.308-128 128z"/></svg></button>
                            <button type="button" class="button lynxjournal-format-btn" data-action="ol" title="Numbered list"><svg viewBox="0 0 1024 1024" xmlns="http://www.w3.org/2000/svg" aria-hidden="true" focusable="false"><path transform="matrix(1,0,0,-1,0,1024)" d="M384 128h640v-128h-640zM384 512h640v-128h-640zM384 896h640v-128h-640zM192 960v-256h-64v192h-64v64zM128 434v-50h128v-64h-192v146l128 60v50h-128v64h192v-146zM256 256v-320h-192v64h128v64h-128v64h128v64h-128v64z"/></svg></button>
                            <button type="button" class="button lynxjournal-format-btn" data-action="indent" title="Indent"><svg viewBox="0 0 1024 1024" xmlns="http://www.w3.org/2000/svg" aria-hidden="true" focusable="false"><path transform="matrix(1,0,0,-1,0,1024)" d="M0 896h1024v-128h-1024zM384 704h640v-128h-640zM384 512h640v-128h-640zM384 320h640v-128h-640zM0 128h1024v-128h-1024zM0 256v384l256-192z"/></svg></button>
                            <button type="button" class="button lynxjournal-format-btn" data-action="outdent" title="Outdent"><svg viewBox="0 0 1024 1024" xmlns="http://www.w3.org/2000/svg" aria-hidden="true" focusable="false"><path transform="matrix(1,0,0,-1,0,1024)" d="M0 896h1024v-128h-1024zM384 704h640v-128h-640zM384 512h640v-128h-640zM384 320h640v-128h-640zM0 128h1024v-128h-1024zM256 640v-384l-256 192z"/></svg></button>
                            <button type="button" class="button lynxjournal-format-btn" data-action="hr" title="Horizontal rule"><svg viewBox="0 0 1024 1024" xmlns="http://www.w3.org/2000/svg" aria-hidden="true" focusable="false"><path transform="matrix(1,0,0,-1,0,1024)" d="M0 512h1024v-128h-1024z"/></svg></button>
                        </div>
                        <textarea
                            id="lynxjournal-post-template"
                            name="lynxjournal_post_template"
                            class="large-text code"
                            rows="10"
                        ><?php echo esc_textarea($template); ?></textarea>
                    </div>

                    <div id="lynxjournal-template-preview-wrap">
                        <div class="lynxjournal-preview-header">
                            <span class="lynxjournal-preview-label"><?php esc_html_e('Vorschau', 'lynx-journal'); ?></span>
                            <span id="lynxjournal-preview-status">Live</span>
                        </div>
                        <div id="lynxjournal-preview-validation"></div>
                        <div id="lynxjournal-template-preview"></div>
                    </div>

                    <div id="lynxjournal-token-accordion">
                        <button
                            type="button"
                            class="lynxjournal-accordion-toggle"
                            aria-expanded="false"
                            aria-controls="lynxjournal-token-panel"
                        >
                            <span><?php esc_html_e('Verfügbare Platzhalter', 'lynx-journal'); ?></span>
                        </button>
                        <div id="lynxjournal-token-panel" class="lynxjournal-token-grid" hidden>
                            <?php foreach ($token_groups as $group_label => $tokens) : ?>
                                <div class="lynxjournal-token-group">
                                    <h4><?php echo esc_html($group_label); ?></h4>
                                    <?php foreach ($tokens as $token => $description) : ?>
                                        <button
                                            type="button"
                                            class="button lynxjournal-insert-token"
                                            data-token="<?php echo esc_attr($token); ?>"
                                        >
                                            <span class="token-code"><?php echo esc_html($token); ?></span>
                                            <span class="token-desc"><?php echo esc_html($description); ?></span>
                                        </button>
                                    <?php endforeach; ?>
                                </div>
                            <?php endforeach; ?>
                        </div>
                    </div>

                    <p class="submit">
                        <button type="submit" class="button button-primary button-large">
                            <?php esc_html_e('Save Template', 'lynx-journal'); ?>
                        </button>
                    </p>
                </form>
            </div>
        </div>
        <?php
    }
}
