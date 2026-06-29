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
                '[link_title]'       => __('Titel eines einzelnen Links', 'lynx-journal'),
                '[link_url]'         => __('Externe URL', 'lynx-journal'),
                '[link_description]' => __('Beschreibungstext des Links', 'lynx-journal'),
                '[link_domain]'      => __('Domain der URL (z. B. "example.com")', 'lynx-journal'),
                '[link_date]'        => __('Datum, wann der Link gespeichert wurde', 'lynx-journal'),
            ],
            __('Kategorien', 'lynx-journal') => [
                '[category_name]' => __('Primäre Kategorie', 'lynx-journal'),
                '[category_list]' => __('Alle Kategorien, kommagetrennt', 'lynx-journal'),
            ],
            __('Tags', 'lynx-journal') => [
                '[tags]' => __('Tags des Links, kommagetrennt', 'lynx-journal'),
            ],
            __('Statistik', 'lynx-journal') => [
                '[unpublished]'     => __('Anzahl unveröffentlichter Links', 'lynx-journal'),
                '[oldest_link_date]' => __('Datum des ältesten unveröff. Links', 'lynx-journal'),
            ],
        ];
        ?>
        <div class="wrap">
            <h1><?php esc_html_e('Post Template', 'lynx-journal'); ?></h1>

            <div class="card" style="max-width:none;">
                <form method="post" action="">
                    <?php wp_nonce_field('lynxjournal_template', 'lynxjournal_template_nonce'); ?>

                    <div class="lynxjournal-settings-field">
                        <label class="lynxjournal-settings-label" for="lynxjournal-post-template">
                            <?php esc_html_e('Template', 'lynx-journal'); ?>
                        </label>
                        <textarea
                            id="lynxjournal-post-template"
                            name="lynxjournal_post_template"
                            class="large-text code"
                            rows="10"
                        ><?php echo esc_textarea($template); ?></textarea>
                    </div>

                    <div id="lynxjournal-template-preview-wrap">
                        <p class="lynxjournal-settings-label"><?php esc_html_e('Vorschau', 'lynx-journal'); ?></p>
                        <div id="lynxjournal-template-preview"></div>
                    </div>

                    <details id="lynxjournal-token-accordion">
                        <summary><?php esc_html_e('💡 Verfügbare Platzhalter anzeigen (Klicken zum Einfügen)', 'lynx-journal'); ?></summary>
                        <div class="lynxjournal-token-grid">
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
                    </details>

                    <p class="submit" style="margin-top:16px;">
                        <button type="submit" class="button button-primary">
                            <?php esc_html_e('Save Template', 'lynx-journal'); ?>
                        </button>
                    </p>
                </form>
            </div>
        </div>
        <?php
    }
}
