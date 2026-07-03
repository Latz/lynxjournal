<?php

declare(strict_types=1);

if (!defined("ABSPATH")) {
    exit;
}

use Brain\Monkey\Functions;

/**
 * Tests for LynxJournal::buildRoundupContent()
 *
 * Private method, invoked via reflection since there's no public entry
 * point that exposes its return value directly.
 *
 * The function signature:
 *   LynxJournal::buildRoundupContent(array $links_by_category, array $uncategorized_links): string
 */

beforeEach(function (): void {
    Functions\when('esc_html')->returnArg();
    Functions\when('esc_html__')->returnArg();
    Functions\when('esc_url')->returnArg();
    Functions\when('esc_url_raw')->returnArg();
    Functions\when('wp_kses_post')->returnArg();
    Functions\when('wp_json_encode')->alias(fn($value, $flags = 0) => json_encode($value, $flags));
    // Default: no template configured, matching the real get_option() default.
    Functions\when('get_option')->justReturn('');
    $this->plugin = new LynxJournal();
});

/**
 * @param array $linksByCategory
 * @param array $uncategorizedLinks
 * @return string
 */
function invokeBuildRoundupContent($plugin, array $linksByCategory, array $uncategorizedLinks): string
{
    $ref    = new ReflectionClass($plugin);
    $method = $ref->getMethod('buildRoundupContent');
    return $method->invoke($plugin, $linksByCategory, $uncategorizedLinks);
}

describe('LynxJournal::buildRoundupContent()', function (): void {

    it('numbers ItemList positions sequentially across categories and uncategorized links', function (): void {
        Functions\when('get_post')->alias(fn($id) => lynxjournal_make_post($id, "Link $id"));
        Functions\when('get_post_meta')->alias(fn($id) => "https://example.com/{$id}");

        $term = (object) ['name' => 'Travel'];
        $result = invokeBuildRoundupContent(
            $this->plugin,
            ['travel' => ['term' => $term, 'links' => [1, 2]]],
            [3]
        );

        preg_match('/<script type="application\/ld\+json">(.+)<\/script>/s', $result, $matches);
        $schema = json_decode($matches[1], true);

        expect($schema['@type'])->toBe('ItemList');
        expect(array_column($schema['itemListElement'], 'position'))->toBe([1, 2, 3]);
    });

    it('includes a ListItem without a url key for a link with no URL', function (): void {
        Functions\when('get_post')->alias(fn($id) => lynxjournal_make_post($id, "Link $id"));
        Functions\when('get_post_meta')->justReturn('');

        $term = (object) ['name' => 'Travel'];
        $result = invokeBuildRoundupContent(
            $this->plugin,
            ['travel' => ['term' => $term, 'links' => [1]]],
            []
        );

        preg_match('/<script type="application\/ld\+json">(.+)<\/script>/s', $result, $matches);
        $schema = json_decode($matches[1], true);

        expect($schema['itemListElement'][0])->toBe(['@type' => 'ListItem', 'position' => 1, 'name' => 'Link 1']);
        expect($result)->toContain('<li><strong>Link 1</strong></li>');
    });

    it('skips a link ID that fails to resolve via get_post without consuming a position', function (): void {
        Functions\when('get_post')->alias(fn($id) => $id === 2 ? null : lynxjournal_make_post($id, "Link $id"));
        Functions\when('get_post_meta')->alias(fn($id) => "https://example.com/{$id}");

        $term = (object) ['name' => 'Travel'];
        $result = invokeBuildRoundupContent(
            $this->plugin,
            ['travel' => ['term' => $term, 'links' => [1, 2, 3]]],
            []
        );

        preg_match('/<script type="application\/ld\+json">(.+)<\/script>/s', $result, $matches);
        $schema = json_decode($matches[1], true);

        expect(array_column($schema['itemListElement'], 'name'))->toBe(['Link 1', 'Link 3']);
        expect(array_column($schema['itemListElement'], 'position'))->toBe([1, 2]);
        expect($result)->not->toContain('Link 2');
    });

    it('produces valid JSON that survives a literal </script> sequence in a title', function (): void {
        Functions\when('get_post')->justReturn(lynxjournal_make_post(1, 'Evil </script><script>alert(1)</script>'));
        Functions\when('get_post_meta')->justReturn('');

        $term = (object) ['name' => 'Travel'];
        $result = invokeBuildRoundupContent(
            $this->plugin,
            ['travel' => ['term' => $term, 'links' => [1]]],
            []
        );

        preg_match('/<script type="application\/ld\+json">(.+?)<\/script>\s*<!-- \/wp:html -->/s', $result, $matches);
        expect($matches)->not->toBeEmpty();
        $schema = json_decode($matches[1], true);
        expect($schema)->not->toBeNull();
        expect($schema['itemListElement'][0]['name'])->toContain('</script>');
    });

    it('falls back to a level-3 Gutenberg heading block when no template is configured', function (): void {
        Functions\when('get_post')->alias(fn($id) => lynxjournal_make_post($id, "Link $id"));
        Functions\when('get_post_meta')->justReturn('');

        $term = (object) ['name' => 'Travel'];
        $result = invokeBuildRoundupContent($this->plugin, ['travel' => ['term' => $term, 'links' => [1]]], []);

        expect($result)->toContain('<!-- wp:heading {"level":3} -->')
            ->toContain('<h3 class="wp-block-heading">Travel</h3>')
            ->toContain('<!-- /wp:heading -->');
    });

    it('joins consecutive list-items with a single newline, no blank line between them', function (): void {
        Functions\when('get_post')->alias(fn($id) => lynxjournal_make_post($id, "Link $id"));
        Functions\when('get_post_meta')->justReturn('');

        $term = (object) ['name' => 'Travel'];
        $result = invokeBuildRoundupContent($this->plugin, ['travel' => ['term' => $term, 'links' => [1, 2]]], []);

        expect($result)->toContain("<!-- /wp:list-item -->\n<!-- wp:list-item -->");
    });

    it('uses the heading level found on the [category_name] line of the Post Template', function (): void {
        Functions\when('get_post')->alias(fn($id) => lynxjournal_make_post($id, "Link $id"));
        Functions\when('get_post_meta')->justReturn('');
        Functions\when('get_option')->justReturn("[category_start]\n### [category_name]\n[link_start][link][link_end]\n[category_end]");

        $term = (object) ['name' => 'Travel'];
        $result = invokeBuildRoundupContent($this->plugin, ['travel' => ['term' => $term, 'links' => [1]]], []);

        expect($result)->toContain('<!-- wp:heading {"level":3} -->')
            ->toContain('<h3 class="wp-block-heading">Travel</h3>');
    });

    it('omits the level attribute for a level-2 heading (default), matching real WP serialization', function (): void {
        Functions\when('get_post')->alias(fn($id) => lynxjournal_make_post($id, "Link $id"));
        Functions\when('get_post_meta')->justReturn('');
        Functions\when('get_option')->justReturn("[category_start]\n## [category_name]\n[category_end]");

        $term = (object) ['name' => 'Travel'];
        $result = invokeBuildRoundupContent($this->plugin, ['travel' => ['term' => $term, 'links' => [1]]], []);

        expect($result)->toContain("<!-- wp:heading -->\n<h2 class=\"wp-block-heading\">Travel</h2>");
        expect($result)->not->toContain('{"level":2}');
    });

    it('reads a level-1 heading marker', function (): void {
        Functions\when('get_post')->alias(fn($id) => lynxjournal_make_post($id, "Link $id"));
        Functions\when('get_post_meta')->justReturn('');
        Functions\when('get_option')->justReturn("[category_start]\n# [category_name]\n[category_end]");

        $term = (object) ['name' => 'Travel'];
        $result = invokeBuildRoundupContent($this->plugin, ['travel' => ['term' => $term, 'links' => [1]]], []);

        expect($result)->toContain('<!-- wp:heading {"level":1} -->')
            ->toContain('<h1 class="wp-block-heading">Travel</h1>');
    });

    it('falls back to level 3 when the [category_name] line has no heading marker', function (): void {
        Functions\when('get_post')->alias(fn($id) => lynxjournal_make_post($id, "Link $id"));
        Functions\when('get_post_meta')->justReturn('');
        Functions\when('get_option')->justReturn("[category_start]\n[category_name]\n[category_end]");

        $term = (object) ['name' => 'Travel'];
        $result = invokeBuildRoundupContent($this->plugin, ['travel' => ['term' => $term, 'links' => [1]]], []);

        expect($result)->toContain('<h3 class="wp-block-heading">Travel</h3>');
    });

    it('applies the same detected heading level to the "Other" heading', function (): void {
        Functions\when('get_post')->alias(fn($id) => lynxjournal_make_post($id, "Link $id"));
        Functions\when('get_post_meta')->justReturn('');
        Functions\when('esc_html__')->returnArg();
        Functions\when('get_option')->justReturn("[category_start]\n## [category_name]\n[category_end]");

        $result = invokeBuildRoundupContent($this->plugin, [], [1]);

        expect($result)->toContain("<!-- wp:heading -->\n<h2 class=\"wp-block-heading\">Other</h2>");
    });
});
