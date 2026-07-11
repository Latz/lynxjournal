<?php

declare(strict_types=1);

trait LynxJournal_Admin_Categories {

    /**
     * Render the Link Categories admin page container.
     *
     * @since 1.0.0
     * @return void
     */
    public function categoriesPage(): void {
        ?>
        <div class="wrap">
            <h1><?php esc_html_e( 'Link Categories', 'lynx-journal' ); ?></h1>
            <div id="lynxjournal-categories-root"></div>
        </div>
        <?php
    }
}
