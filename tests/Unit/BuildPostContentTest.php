<?php

declare(strict_types=1);

if (!defined("ABSPATH")) {
    exit;
}

use Brain\Monkey\Functions;

/**
 * Tests for LynxJournal::buildPostContent()
 *
 * The function signature:
 *   LynxJournal::buildPostContent(string $title, int $link_id, string $url, string $description): string
 */

beforeEach(function (): void {
    // Escaping stubs: return arg unchanged so assertions read literally
    Functions\when('esc_html')->returnArg();
    Functions\when('esc_url')->returnArg();
    Functions\when('wp_kses_post')->returnArg();
    // apply_filters returns the value (second arg) unchanged
    Functions\when('apply_filters')->returnArg(2);
    $this->plugin = Mockery::mock(LynxJournal::class)->makePartial();
});

describe('LynxJournal::buildPostContent()', function (): void {

    it('wraps title in a Gutenberg heading block', function (): void {
        $result = $this->plugin->buildPostContent('My Title', 1, '', '');

        expect($result)->toContain('<!-- wp:heading -->')
            ->toContain('<h2 class="wp-block-heading">My Title</h2>')
            ->toContain('<!-- /wp:heading -->');
    });

    it('does not include a URL link when url is empty', function (): void {
        $result = $this->plugin->buildPostContent('Title', 1, '', '');

        expect($result)->not->toContain('<a href');
        expect($result)->not->toContain('Read more');
    });

    it('appends a read-more link when url is provided', function (): void {
        $result = $this->plugin->buildPostContent('Title', 1, LYNXJOURNAL_URL_EXAMPLE, '');

        expect($result)
            ->toContain('<a href="https://example.com" target="_blank" rel="noopener">')
            ->toContain('Read more');
    });

    it('does not include description markup when description is empty', function (): void {
        $result = $this->plugin->buildPostContent('Title', 1, '', '');

        // The only content should be the heading block
        expect(trim($result))->toBe("<!-- wp:heading -->\n<h2 class=\"wp-block-heading\">Title</h2>\n<!-- /wp:heading -->");
    });

    it('appends description when provided', function (): void {
        $result = $this->plugin->buildPostContent('Title', 1, '', 'Some description text.');

        expect($result)->toContain('Some description text.');
    });

    it('includes both description and read-more link when both are provided', function (): void {
        $result = $this->plugin->buildPostContent('Title', 1, LYNXJOURNAL_URL_EXAMPLE, 'Desc.');

        expect($result)
            ->toContain('Desc.')
            ->toContain(LYNXJOURNAL_URL_EXAMPLE);
    });

    it('passes content through apply_filters with the correct hook name', function (): void {
        $capturedHook = null;
        // Override the beforeEach when() with a capturing alias.
        Functions\when('apply_filters')->alias(
            function (string $hook, mixed $value) use (&$capturedHook): mixed {
                $capturedHook = $hook;
                return $value;
            }
        );

        $this->plugin->buildPostContent('Title', 42, 'https://x.com', 'Desc');

        expect($capturedHook)->toBe('lynxjournal_blog_post_content');
    });

    it('escapes the title via esc_html', function (): void {
        $capturedTitle = null;
        Functions\when('esc_html')->alias(
            function (string $t) use (&$capturedTitle): string {
                $capturedTitle = $t;
                return htmlspecialchars($t, ENT_QUOTES);
            }
        );

        $this->plugin->buildPostContent('<script>alert(1)</script>', 1, '', '');

        expect($capturedTitle)->toBe('<script>alert(1)</script>');
    });
});
