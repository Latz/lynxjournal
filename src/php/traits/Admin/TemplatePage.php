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
            __('Beitrag', 'lynx-journal') => [
                '[titel]'     => __('Titel des Roundup-Beitrags', 'lynx-journal'),
                '[datum]'     => __('Veröffentlichungsdatum', 'lynx-journal'),
                '[autor]'     => __('Name des Beitragsautors', 'lynx-journal'),
                '[site_name]' => __('Name des Blogs', 'lynx-journal'),
            ],
            __('Links', 'lynx-journal') => [
                '[link_anzahl]'       => __('Anzahl Links im Roundup', 'lynx-journal'),
                '[link_titel]'        => __('Titel eines einzelnen Links', 'lynx-journal'),
                '[link_url]'          => __('Externe URL', 'lynx-journal'),
                '[link_beschreibung]' => __('Beschreibungstext des Links', 'lynx-journal'),
                '[link_domain]'       => __('Domain der URL (z. B. "example.com")', 'lynx-journal'),
                '[link_datum]'        => __('Datum, wann der Link gespeichert wurde', 'lynx-journal'),
            ],
            __('Kategorien', 'lynx-journal') => [
                '[kategorie]'       => __('Primäre Kategorie', 'lynx-journal'),
                '[kategorien_liste]' => __('Alle Kategorien, kommagetrennt', 'lynx-journal'),
            ],
            __('Tags', 'lynx-journal') => [
                '[tags]' => __('Tags des Links, kommagetrennt', 'lynx-journal'),
            ],
            __('Statistik', 'lynx-journal') => [
                '[unveröffentlicht]'    => __('Anzahl unveröffentlichter Links', 'lynx-journal'),
                '[ältester_link_datum]' => __('Datum des ältesten unveröff. Links', 'lynx-journal'),
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
