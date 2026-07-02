<?php

declare(strict_types=1);

if (!defined("ABSPATH")) {
    exit;
}

use Brain\Monkey\Functions;

/**
 * Tests for LynxJournal::renderAddLinkCategoryCheckboxes() (private, via reflection).
 */

beforeEach(function (): void {
    Functions\when('esc_html')->returnArg();
    Functions\when('esc_html_e')->alias(function (string $t): void {
        echo htmlspecialchars($t);
    });
    Functions\when('esc_attr')->returnArg();
    $this->plugin = Mockery::mock(LynxJournal::class)->makePartial();
    $this->method  = new \ReflectionMethod(LynxJournal::class, 'renderAddLinkCategoryCheckboxes');
});

describe('LynxJournal::renderAddLinkCategoryCheckboxes()', function (): void {

    it('shows a fallback message when there are no categories', function (): void {
        ob_start();
        $this->method->invoke($this->plugin, [], []);
        $html = ob_get_clean();

        expect($html)->toContain('No categories available');
        expect($html)->not->toContain('lynxjournal-cat-scroll-list');
    });

    it('renders a checkbox per category', function (): void {
        $cat1 = (object) ['term_id' => '1', 'name' => 'Tech'];
        $cat2 = (object) ['term_id' => '2', 'name' => 'News'];

        ob_start();
        $this->method->invoke($this->plugin, [$cat1, $cat2], []);
        $html = ob_get_clean();

        expect($html)->toContain('lynxjournal-cat-scroll-list');
        expect($html)->toContain('Tech');
        expect($html)->toContain('News');
        expect(substr_count($html, 'type="checkbox"'))->toBe(2);
    });

    it('marks the checkbox checked for a currently-selected category', function (): void {
        $cat = (object) ['term_id' => '3', 'name' => 'Tech'];

        ob_start();
        $this->method->invoke($this->plugin, [$cat], [3]);
        $html = ob_get_clean();

        expect($html)->toContain('checked');
    });

    it('does not mark the checkbox checked when the category is not selected', function (): void {
        $cat = (object) ['term_id' => '3', 'name' => 'Tech'];

        ob_start();
        $this->method->invoke($this->plugin, [$cat], [99]);
        $html = ob_get_clean();

        expect($html)->not->toContain('checked');
    });
});
