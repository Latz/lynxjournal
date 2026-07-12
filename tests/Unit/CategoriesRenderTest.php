<?php

declare(strict_types=1);

if (!defined("ABSPATH")) {
    exit;
}

use Brain\Monkey\Functions;

/**
 * Tests for the combined Categories & Tags admin-page entry point. The page
 * body is made of two React apps (src/categories/ and src/tags/); the PHP
 * side only renders the two containers.
 */

beforeEach(function (): void {
    Functions\when('esc_html_e')->alias(function (string $t): void {
        echo htmlspecialchars($t);
    });
    $this->plugin = Mockery::mock(LynxJournal::class)->makePartial();
});

describe('LynxJournal::categoriesPage()', function (): void {

    it('renders the categories and tags root containers', function (): void {
        ob_start();
        $this->plugin->categoriesPage();
        $html = ob_get_clean();

        expect($html)->toContain('lynxjournal-categories-root');
        expect($html)->toContain('lynxjournal-tags-root');
        expect($html)->toContain('Link Categories &amp; Tags');
    });
});
