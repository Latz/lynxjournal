<?php

declare(strict_types=1);

trait LynxJournal_Admin_Categories {

    /**
     * Render the combined Categories & Tags admin page container.
     *
     * @since 1.0.0
     * @return void
     */
    public function categoriesPage(): void {
        ?>
        <div class="wrap">
            <h1><?php esc_html_e( 'Categories & Tags', 'lynx-journal' ); ?></h1>
            <div id="lynxjournal-categories-root"></div>
            <div id="lynxjournal-tags-root"></div>
        </div>
        <?php
    }
}
