<?php

declare(strict_types=1);

if (!defined("ABSPATH")) {
    exit;
}

use Brain\Monkey\Functions;

/**
 * Tests for LynxJournal_TemplateRenderer — the PHP port of the admin
 * Post Template preview pipeline (buildTemplateText(), convertIndentedLines(),
 * wrapAsGutenbergBlocks() in src/js/template-preview.js, src/js/template-utils.js,
 * assets/js/template-page.js), used to make real roundup publishing honor the
 * saved lynxjournal_post_template option.
 *
 * Mirrors tests/js/template-preview.test.js and tests/js/template-utils.test.js
 * — see those files for the JS-side equivalents of each case below.
 */

beforeEach(function (): void {
    Functions\when('esc_html')->returnArg();
    Functions\when('esc_html__')->returnArg();
    Functions\when('esc_url')->returnArg();
    Functions\when('esc_url_raw')->returnArg();
    Functions\when('wp_kses_post')->returnArg();
    Functions\when('wp_json_encode')->alias(fn($value, $flags = 0) => json_encode($value, $flags));
    Functions\when('get_the_date')->justReturn('2026-06-27');
    Functions\when('wp_parse_url')->alias(fn($url, $component = -1) => parse_url($url, $component));
    Functions\when('get_userdata')->justReturn(false);
    Functions\when('get_bloginfo')->justReturn('Test Site');
    Functions\when('wp_date')->justReturn('June 29, 2026');
    Functions\when('get_option')->justReturn('');
    $this->plugin = new LynxJournal();
});

/**
 * @return array
 */
function invokeBuildTemplateTokenData($plugin, array $linksByCategory, array $uncategorizedLinks, string $postTitle = '', int $authorId = 0): array
{
    $ref    = new ReflectionClass($plugin);
    $method = $ref->getMethod('buildTemplateTokenData');
    return $method->invoke($plugin, $linksByCategory, $uncategorizedLinks, $postTitle, $authorId);
}

function invokeBuildTemplateTextPhp($plugin, string $rawText, array $categoryVariants, array $scalarData, array $allLinkTokenMaps): string
{
    $ref    = new ReflectionClass($plugin);
    $method = $ref->getMethod('buildTemplateTextPhp');
    return $method->invoke($plugin, $rawText, $categoryVariants, $scalarData, $allLinkTokenMaps);
}

function invokeExpandLinkBlocksPhp($plugin, string $text, array $links): string
{
    $ref    = new ReflectionClass($plugin);
    $method = $ref->getMethod('expandLinkBlocksPhp');
    return $method->invoke($plugin, $text, $links);
}

function invokeExpandLinkLinesPhp($plugin, string $text, array $links): string
{
    $ref    = new ReflectionClass($plugin);
    $method = $ref->getMethod('expandLinkLinesPhp');
    return $method->invoke($plugin, $text, $links);
}

function invokeConvertIndentedLinesPhp($plugin, string $text): string
{
    $ref    = new ReflectionClass($plugin);
    $method = $ref->getMethod('convertIndentedLinesPhp');
    return $method->invoke($plugin, $text);
}

function invokeWrapAsGutenbergBlocksPhp($plugin, string $html): string
{
    $ref    = new ReflectionClass($plugin);
    $method = $ref->getMethod('wrapAsGutenbergBlocksPhp');
    return $method->invoke($plugin, $html);
}

function invokeBuildRoundupContentFromTemplate($plugin, string $template, array $linksByCategory, array $uncategorizedLinks, string $postTitle = '', int $authorId = 0): string
{
    $ref    = new ReflectionClass($plugin);
    $method = $ref->getMethod('buildRoundupContentFromTemplate');
    return $method->invoke($plugin, $template, $linksByCategory, $uncategorizedLinks, $postTitle, $authorId);
}

describe('LynxJournal_TemplateRenderer::buildTemplateTokenData()', function (): void {

    it('uses the passed-in post title verbatim for [title]', function (): void {
        [$scalar] = invokeBuildTemplateTokenData($this->plugin, [], [], 'My Roundup', 0);
        expect($scalar['[title]'])->toBe('My Roundup');
    });

    it('resolves [author] from get_userdata, falling back to empty string when unresolved', function (): void {
        [$scalar] = invokeBuildTemplateTokenData($this->plugin, [], [], '', 0);
        expect($scalar['[author]'])->toBe('');

        Functions\when('get_userdata')->justReturn((object) ['display_name' => 'Latz']);
        [$scalar2] = invokeBuildTemplateTokenData($this->plugin, [], [], '', 5);
        expect($scalar2['[author]'])->toBe('Latz');
    });

    it('uses the current lynxjournal_roundup_count option value, not +1', function (): void {
        Functions\when('get_option')->alias(fn($key, $default = false) => $key === 'lynxjournal_roundup_count' ? 42 : '');
        [$scalar] = invokeBuildTemplateTokenData($this->plugin, [], [], '', 0);
        expect($scalar['[roundup_count]'])->toBe('42');
    });

    it('reads [site_name] from get_bloginfo', function (): void {
        [$scalar] = invokeBuildTemplateTokenData($this->plugin, [], [], '', 0);
        expect($scalar['[site_name]'])->toBe('Test Site');
    });

    it('excludes the synthetic "Other" category from [category_list]', function (): void {
        Functions\when('get_post')->alias(fn($id) => lynxjournal_make_post($id, "Link $id"));
        Functions\when('get_post_meta')->justReturn('');
        $term = (object) ['name' => 'Travel'];

        [$scalar] = invokeBuildTemplateTokenData(
            $this->plugin,
            ['travel' => ['term' => $term, 'links' => [1]]],
            [2],
            '',
            0
        );

        expect($scalar['[category_list]'])->toBe('Travel');
    });

    it('dedupes [tags] across all links in the roundup, comma-separated', function (): void {
        Functions\when('get_post')->alias(fn($id) => lynxjournal_make_post($id, "Link $id"));
        Functions\when('get_post_meta')->justReturn('');
        Functions\when('get_the_terms')->alias(function ($id, $taxonomy) {
            if ($taxonomy !== 'lynxjournal_tag') {
                return false;
            }
            return match ($id) {
                1 => [(object) ['name' => 'guide'], (object) ['name' => 'tips']],
                2 => [(object) ['name' => 'tips']],
                default => false,
            };
        });
        $term = (object) ['name' => 'Travel'];

        [$scalar] = invokeBuildTemplateTokenData(
            $this->plugin,
            ['travel' => ['term' => $term, 'links' => [1, 2]]],
            [],
            '',
            0
        );

        expect($scalar['[tags]'])->toBe('guide, tips');
    });

    it('strips a leading www. from [link_domain] and handles a missing URL', function (): void {
        Functions\when('get_post')->alias(fn($id) => lynxjournal_make_post($id, "Link $id"));
        Functions\when('get_post_meta')->alias(fn($id) => $id === 1 ? 'https://www.example.com/a' : '');
        $term = (object) ['name' => 'Travel'];

        [, $categoryVariants] = invokeBuildTemplateTokenData(
            $this->plugin,
            ['travel' => ['term' => $term, 'links' => [1, 2]]],
            [],
            '',
            0
        );

        expect($categoryVariants[0]['links'][0]['[link_domain]'])->toBe('example.com');
        expect($categoryVariants[0]['links'][1]['[link_domain]'])->toBe('');
    });

    it('uses the link CPT\'s own post date for [link_date], not the roundup\'s', function (): void {
        Functions\when('get_post')->alias(fn($id) => lynxjournal_make_post($id, "Link $id"));
        Functions\when('get_post_meta')->justReturn('');
        Functions\when('get_the_date')->justReturn('2026-06-20');
        $term = (object) ['name' => 'Travel'];

        [, $categoryVariants] = invokeBuildTemplateTokenData(
            $this->plugin,
            ['travel' => ['term' => $term, 'links' => [1]]],
            [],
            '',
            0
        );

        expect($categoryVariants[0]['links'][0]['[link_date]'])->toBe('2026-06-20');
    });

    it('appends a synthetic "Other" category variant when uncategorized links exist', function (): void {
        Functions\when('get_post')->alias(fn($id) => lynxjournal_make_post($id, "Link $id"));
        Functions\when('get_post_meta')->justReturn('');

        [, $categoryVariants] = invokeBuildTemplateTokenData($this->plugin, [], [1, 2], '', 0);

        expect($categoryVariants)->toHaveCount(1);
        expect($categoryVariants[0]['[category_name]'])->toBe('Other');
        expect($categoryVariants[0]['links'])->toHaveCount(2);
    });

    it('omits the "Other" category variant when there are no uncategorized links', function (): void {
        [, $categoryVariants] = invokeBuildTemplateTokenData($this->plugin, [], [], '', 0);
        expect($categoryVariants)->toBe([]);
    });
});

describe('LynxJournal_TemplateRenderer::buildTemplateTextPhp()', function (): void {

    it('expands a [category_start]...[category_end] block once per category variant', function (): void {
        $variants = [
            ['[category_name]' => 'Travel', '[category_link_count]' => '1', 'links' => []],
            ['[category_name]' => 'Food', '[category_link_count]' => '1', 'links' => []],
        ];
        $result = invokeBuildTemplateTextPhp(
            $this->plugin,
            "[category_start]## [category_name]\n[category_end]",
            $variants,
            [],
            []
        );
        expect($result)->toBe("## Travel\n## Food\n");
    });

    it('produces empty expansion for an empty categoryVariants list', function (): void {
        $result = invokeBuildTemplateTextPhp($this->plugin, '[category_start]## [category_name][category_end]', [], [], []);
        expect($result)->toBe('');
    });

    it('substitutes scalar tokens outside any category block', function (): void {
        $result = invokeBuildTemplateTextPhp($this->plugin, '[title] by [author]', [], ['[title]' => 'Roundup', '[author]' => 'Latz'], []);
        expect($result)->toBe('Roundup by Latz');
    });

    it('expands top-level [link_start] blocks using ALL links, not just one category (deliberate JS divergence)', function (): void {
        $allLinks = [['[link]' => 'A'], ['[link]' => 'B'], ['[link]' => 'C']];
        $result = invokeBuildTemplateTextPhp($this->plugin, '[link_start][link] [link_end]', [], [], $allLinks);
        expect($result)->toBe('A B C ');
    });
});

describe('LynxJournal_TemplateRenderer::expandLinkBlocksPhp() / expandLinkLinesPhp()', function (): void {

    it('repeats a [link_start]...[link_end] block once per link', function (): void {
        $links = [['[link]' => 'A'], ['[link]' => 'B']];
        $result = invokeExpandLinkBlocksPhp($this->plugin, "[link_start]- [link]\n[link_end]", $links);
        expect($result)->toBe("- A\n- B\n");
    });

    it('produces empty expansion when there are no links', function (): void {
        $result = invokeExpandLinkBlocksPhp($this->plugin, '[link_start]- [link][link_end]', []);
        expect($result)->toBe('');
    });

    it('repeats a contiguous group of link-token lines once per link', function (): void {
        $links = [['[link]' => 'A', '[link_description]' => 'DescA'], ['[link]' => 'B', '[link_description]' => 'DescB']];
        $result = invokeExpandLinkLinesPhp($this->plugin, "- [link]\n  [link_description]", $links);
        expect($result)->toBe("- A\n  DescA\n- B\n  DescB");
    });
});

describe('LynxJournal_TemplateRenderer::convertIndentedLinesPhp()', function (): void {

    it('leaves non-indented lines untouched', function (): void {
        expect(invokeConvertIndentedLinesPhp($this->plugin, "Hello\nWorld"))->toBe("Hello\nWorld");
    });

    it('wraps a single indented line in a padded div', function (): void {
        $result = invokeConvertIndentedLinesPhp($this->plugin, '  Indented text');
        expect($result)->toBe('<div style="padding-left:1.5em">Indented text</div>');
    });

    it('scales padding by indent level', function (): void {
        $result = invokeConvertIndentedLinesPhp($this->plugin, '    Deeper text');
        expect($result)->toBe('<div style="padding-left:3em">Deeper text</div>');
    });

    it('groups consecutive same-level "- " lines into one real <ul>', function (): void {
        $result = invokeConvertIndentedLinesPhp($this->plugin, "  - One\n  - Two");
        expect($result)->toBe('<ul style="padding-left:1.5em"><li>One</li><li>Two</li></ul>');
    });

    it('adds a start attribute for an ordered list not starting at 1', function (): void {
        $result = invokeConvertIndentedLinesPhp($this->plugin, "  3. Three\n  4. Four");
        expect($result)->toBe('<ol style="padding-left:1.5em" start="3"><li>Three</li><li>Four</li></ol>');
    });

    it('omits the start attribute for an ordered list starting at 1', function (): void {
        $result = invokeConvertIndentedLinesPhp($this->plugin, '  1. One');
        expect($result)->not->toContain('start=');
    });

    it('flushes a list buffer when a plain indented line follows', function (): void {
        $result = invokeConvertIndentedLinesPhp($this->plugin, "  - One\n  Plain");
        expect($result)->toBe('<ul style="padding-left:1.5em"><li>One</li></ul>' . "\n" . '<div style="padding-left:1.5em">Plain</div>');
    });

    it('renders inline markdown within an indented line with no stray <p> wrapper', function (): void {
        $result = invokeConvertIndentedLinesPhp($this->plugin, '  **Bold** text');
        expect($result)->toBe('<div style="padding-left:1.5em"><strong>Bold</strong> text</div>');
    });

    it('leaves an indented list directly continuing an unindented list item untouched (genuine nested list)', function (): void {
        $result = invokeConvertIndentedLinesPhp($this->plugin, "- Parent\n  - Child");
        expect($result)->toBe("- Parent\n  - Child");
    });

    it('handles an empty string', function (): void {
        expect(invokeConvertIndentedLinesPhp($this->plugin, ''))->toBe('');
    });
});

describe('LynxJournal_TemplateRenderer::wrapAsGutenbergBlocksPhp()', function (): void {

    it('wraps <hr> as a wp:separator block', function (): void {
        $result = invokeWrapAsGutenbergBlocksPhp($this->plugin, '<hr>');
        expect($result)->toContain('<!-- wp:separator -->')
            ->toContain('<hr class="wp-block-separator has-alpha-channel-opacity"/>')
            ->toContain('<!-- /wp:separator -->');
    });

    it('wraps h1-h6 as wp:heading blocks, omitting the level attr only for h2', function (): void {
        expect(invokeWrapAsGutenbergBlocksPhp($this->plugin, '<h2>Travel</h2>'))
            ->toContain("<!-- wp:heading -->\n<h2 class=\"wp-block-heading\">Travel</h2>\n<!-- /wp:heading -->");
        expect(invokeWrapAsGutenbergBlocksPhp($this->plugin, '<h3>Travel</h3>'))
            ->toContain('<!-- wp:heading {"level":3} -->');
    });

    it('wraps a <ul> as a wp:list block with wp:list-item children', function (): void {
        $result = invokeWrapAsGutenbergBlocksPhp($this->plugin, '<ul><li>One</li><li>Two</li></ul>');
        expect($result)->toContain('<!-- wp:list -->')
            ->toContain('<!-- wp:list-item -->')
            ->toContain('<!-- /wp:list -->');
    });

    it('wraps an <ol> as an ordered wp:list block', function (): void {
        $result = invokeWrapAsGutenbergBlocksPhp($this->plugin, '<ol><li>One</li></ol>');
        expect($result)->toContain('{"ordered":true}');
    });

    it('recursively wraps a nested list inside an <li> as its own inner wp:list block', function (): void {
        $result = invokeWrapAsGutenbergBlocksPhp($this->plugin, '<ul><li>Parent<ul><li>Child</li></ul></li></ul>');
        expect(substr_count($result, '<!-- wp:list -->'))->toBe(2);
        expect(substr_count($result, '<!-- wp:list-item -->'))->toBe(2);
    });

    it('wraps a <p> as a wp:paragraph block', function (): void {
        $result = invokeWrapAsGutenbergBlocksPhp($this->plugin, '<p>Hello</p>');
        expect($result)->toBe("<!-- wp:paragraph -->\n<p>Hello</p>\n<!-- /wp:paragraph -->");
    });

    it('passes through an unknown tag unwrapped', function (): void {
        $result = invokeWrapAsGutenbergBlocksPhp($this->plugin, '<div style="padding-left:1.5em">X</div>');
        expect($result)->toBe('<div style="padding-left:1.5em">X</div>');
    });
});

describe('LynxJournal_TemplateRenderer / Batch::buildRoundupContentFromTemplate() integration', function (): void {

    it('renders the example template from template.md end-to-end', function (): void {
        Functions\when('get_post')->alias(fn($id) => lynxjournal_make_post($id, "Link $id"));
        Functions\when('get_post_meta')->alias(fn($id) => "https://example.com/{$id}");
        $term = (object) ['name' => 'Travel'];

        $template = "## [category_start][category_name][category_end]\n\n[link_start]- [link] — [link_description][link_end]";

        $result = invokeBuildRoundupContentFromTemplate(
            $this->plugin,
            $template,
            ['travel' => ['term' => $term, 'links' => [1]]],
            []
        );

        expect($result)->toContain('<!-- wp:heading -->')
            ->toContain('<h2 class="wp-block-heading">Travel</h2>')
            ->toContain('<!-- wp:list -->')
            ->toContain('Link 1');
    });

    it('does not crash on an unbalanced [category_start] with no matching [category_end]', function (): void {
        Functions\when('get_post')->alias(fn($id) => lynxjournal_make_post($id, "Link $id"));
        Functions\when('get_post_meta')->justReturn('');
        $term = (object) ['name' => 'Travel'];

        $result = invokeBuildRoundupContentFromTemplate(
            $this->plugin,
            '[category_start]## [category_name]',
            ['travel' => ['term' => $term, 'links' => [1]]],
            []
        );

        expect($result)->toContain('[category_start]');
    });

    it('falls back to the fixed builder when the template renders to effectively empty content', function (): void {
        // A [category_start]/[category_end] block with zero category variants
        // (no links at all here) expands to '' — buildRoundupContentFromTemplate()
        // itself should report empty so the caller (buildRoundupContent()) can fall back.
        $ref    = new ReflectionClass($this->plugin);
        $method = $ref->getMethod('buildRoundupContentFromTemplate');
        $result = $method->invoke($this->plugin, '[category_start][category_end]', [], [], '', 0);

        expect($result)->toBe('');
    });

    it('does not include a JSON-LD schema.org block when a custom template is used', function (): void {
        Functions\when('get_post')->alias(fn($id) => lynxjournal_make_post($id, "Link $id"));
        Functions\when('get_post_meta')->alias(fn($id) => "https://example.com/{$id}");
        $term = (object) ['name' => 'Travel'];

        $result = invokeBuildRoundupContentFromTemplate(
            $this->plugin,
            "[category_start]## [category_name]\n[link_start]- [link][link_end][category_end]",
            ['travel' => ['term' => $term, 'links' => [1]]],
            []
        );

        expect($result)->not->toContain('application/ld+json');
    });
});

describe('LynxJournal::buildRoundupContent() — new-signature backward compatibility', function (): void {

    it('produces identical fixed-builder output for the new 4-arg call and the legacy 2-arg call, when no template is configured', function (): void {
        Functions\when('get_post')->alias(fn($id) => lynxjournal_make_post($id, "Link $id"));
        Functions\when('get_post_meta')->justReturn('');
        $term = (object) ['name' => 'Travel'];

        $ref    = new ReflectionClass($this->plugin);
        $method = $ref->getMethod('buildRoundupContent');

        $legacy = $method->invoke($this->plugin, ['travel' => ['term' => $term, 'links' => [1]]], []);
        $new    = $method->invoke($this->plugin, ['travel' => ['term' => $term, 'links' => [1]]], [], 'My Title', 5);

        expect($new)->toBe($legacy);
    });
});
