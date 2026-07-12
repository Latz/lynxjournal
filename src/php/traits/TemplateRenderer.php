<?php

declare(strict_types=1);

if ( ! defined( 'ABSPATH' ) ) {
    exit;
}

use League\CommonMark\CommonMarkConverter;

/**
 * Server-side port of the admin Post Template preview pipeline
 * (src/js/template-preview.js, src/js/template-utils.js,
 * assets/js/template-page.js), so real roundup publishing can honor the
 * saved lynxjournal_post_template option instead of only the JS-only
 * live preview.
 *
 * One intentional omission vs. the JS pipeline: preserveBlankLines() is not
 * ported. It exists purely to make blank lines visible in the live in-editor
 * preview (wrapped in a marker paragraph, rendered as-is in the preview
 * iframe) and has no equivalent in real publishing, so skipping it is
 * simpler and behavior-equivalent. Tradeoff: multiple consecutive blank
 * lines in a template collapse to normal single-paragraph Markdown spacing
 * in real output (see template.md's "Notes on real publishing").
 */
trait LynxJournal_TemplateRenderer {

    private const LINK_TOKENS = ['[link]', '[link_description]', '[link_domain]', '[link_date]'];
    private const INDENT_RE = '/^( {2,})(?:(-|\d+\.) )?(.+)/';
    private const TOP_LEVEL_LIST_RE = '/^(-|\d+\.)\s/';

    private ?CommonMarkConverter $templateMarkdownConverter = null;

    /**
     * Lazily-constructed, shared CommonMark converter, configured to allow
     * raw HTML passthrough (matches marked.js's default behavior, and is
     * required for the <div style="padding-left:..."> wrappers built by
     * convertIndentedLinesPhp() and any <u>...</u> toolbar markup to survive
     * the full-document parse instead of being escaped).
     *
     * @return CommonMarkConverter
     */
    private function getTemplateMarkdownConverter(): CommonMarkConverter {
        if ($this->templateMarkdownConverter === null) {
            $this->templateMarkdownConverter = new CommonMarkConverter([
                'html_input' => 'allow',
                // Intentionally left at CommonMark's safe default (false):
                // marked.js doesn't sanitize link schemes, but this output
                // becomes real published post content, not a preview.
                'allow_unsafe_links' => false,
            ]);
        }
        return $this->templateMarkdownConverter;
    }

    /**
     * Builds the real-data equivalent of assets/js/template-preview-data.json's
     * scalar/category token maps, from the actual links being published.
     *
     * @since 1.0.0
     * @param array  $links_by_category   Same shape as groupLinksByCategory()'s first return value.
     * @param array  $uncategorized_links Flat array of link post IDs.
     * @param string $post_title          Already-resolved real post title.
     * @param int    $author_id           Resolved author user ID (0 = unresolved).
     * @return array{0: array<string,string>, 1: list<array<string,mixed>>, 2: list<array<string,string>>}
     *         [$scalarData, $categoryVariants, $allLinkTokenMaps]
     */
    private function buildTemplateTokenData(
        array $links_by_category,
        array $uncategorized_links,
        string $post_title,
        int $author_id
    ): array {
        $category_variants = [];
        $all_link_token_maps = [];

        foreach ($links_by_category as $group) {
            $link_maps = array_map([$this, 'buildLinkTokenMap'], $group['links']);
            $category_variants[] = [
                '[category_name]'       => esc_html($group['term']->name),
                '[category_link_count]' => (string) count($group['links']),
                'links'                 => $link_maps,
            ];
            array_push($all_link_token_maps, ...$link_maps);
        }

        // Uncategorized links get a synthetic "Other" category variant so a
        // template using [category_start] doesn't silently drop them, matching
        // the fixed builder's existing "Other" heading convention.
        if (!empty($uncategorized_links)) {
            $other_link_maps = array_map([$this, 'buildLinkTokenMap'], $uncategorized_links);
            $category_variants[] = [
                '[category_name]'       => __('Other', 'lynx-journal'),
                '[category_link_count]' => (string) count($uncategorized_links),
                'links'                 => $other_link_maps,
            ];
            array_push($all_link_token_maps, ...$other_link_maps);
        }

        $tags = $this->collectAllLinkTags($links_by_category, $uncategorized_links);

        $scalar_data = [
            '[title]'          => $post_title,
            '[date]'           => wp_date((string) get_option('date_format')),
            '[author]'         => $author_id > 0 ? esc_html($this->getAuthorDisplayName($author_id)) : '',
            '[site_name]'      => esc_html((string) get_bloginfo('name')),
            '[roundup_count]'  => (string) get_option('lynxjournal_roundup_count', 0),
            '[link_count]'     => (string) count($all_link_token_maps),
            '[category_list]'  => esc_html(implode(', ', array_map(static fn($g) => $g['term']->name, $links_by_category))),
            '[tags]'           => implode(', ', $tags),
        ];

        // Flat single-link/category fallback tokens, for templates that use
        // these tokens with no [category_start]/[link_start] wrapper at all.
        if (!empty($category_variants)) {
            $first_cat = $category_variants[0];
            $scalar_data['[category_name]']       = $first_cat['[category_name]'];
            $scalar_data['[category_link_count]'] = $first_cat['[category_link_count]'];
        }
        if (!empty($all_link_token_maps)) {
            $scalar_data = array_merge($scalar_data, $all_link_token_maps[0]);
        }

        return [$scalar_data, $category_variants, $all_link_token_maps];
    }

    /**
     * Resolves a user's display name for the [author] token, tolerating a
     * deleted/nonexistent user ID (get_userdata() returns false).
     *
     * @param int $author_id
     * @return string
     */
    private function getAuthorDisplayName(int $author_id): string {
        $user = get_userdata($author_id);
        return $user ? (string) $user->display_name : '';
    }

    /**
     * Builds the [link]/[link_description]/[link_domain]/[link_date] token
     * map for one link post. Title and URL are escaped before being embedded
     * in the Markdown "[title](url)" syntax, and the description is passed
     * through wp_kses_post(), because the CommonMark converter that later
     * parses this template output is configured with html_input => 'allow'
     * (see getTemplateMarkdownConverter()) and would otherwise pass raw HTML
     * in a link's title/description straight through into published content.
     *
     * @param int $link_id
     * @return array<string,string>
     */
    private function buildLinkTokenMap(int $link_id): array {
        $link = get_post($link_id);
        $url  = $link ? get_post_meta($link_id, '_lynxjournal_url', true) : '';
        $title = $link ? esc_html($link->post_title) : '';

        $domain = '';
        if (!empty($url)) {
            $host = (string) wp_parse_url($url, PHP_URL_HOST);
            $domain = preg_replace('/^www\./', '', $host);
        }

        return [
            '[link]'             => !empty($url) ? '[' . $title . '](' . esc_url($url) . ')' : $title,
            '[link_description]' => $link ? wp_kses_post(trim($link->post_content)) : '',
            '[link_domain]'      => esc_html($domain),
            '[link_date]'        => $link ? get_the_date('Y-m-d', $link_id) : '',
        ];
    }

    /**
     * Union of tags across every link in the roundup, deduplicated, in
     * first-seen order. [tags] is a flat roundup-wide scalar (confirmed
     * absent from LINK_TOKENS), not a per-link loop token.
     *
     * @param array $links_by_category
     * @param array $uncategorized_links
     * @return list<string>
     */
    private function collectAllLinkTags(array $links_by_category, array $uncategorized_links): array {
        $tag_names = [];
        $all_ids = [];
        foreach ($links_by_category as $group) {
            array_push($all_ids, ...$group['links']);
        }
        array_push($all_ids, ...$uncategorized_links);

        foreach ($all_ids as $link_id) {
            $tags = get_the_terms($link_id, 'lynxjournal_tag');
            if ($tags && !is_wp_error($tags)) {
                foreach ($tags as $tag) {
                    $tag_names[] = $tag->name;
                }
            }
        }
        return array_values(array_unique($tag_names));
    }

    /**
     * PHP port of buildTemplateText() (src/js/template-preview.js).
     *
     * @since 1.0.0
     * @param string $raw_text
     * @param list<array<string,mixed>> $category_variants
     * @param array<string,string> $scalar_data
     * @param list<array<string,string>> $all_link_token_maps
     * @return string
     */
    private function buildTemplateTextPhp(
        string $raw_text,
        array $category_variants,
        array $scalar_data,
        array $all_link_token_maps
    ): string {
        $text = preg_replace_callback(
            '/\[category_start\]([\s\S]*?)\[category_end\]/',
            function ($m) use ($category_variants) {
                $expansions = [];
                foreach ($category_variants as $cat) {
                    $cat_text = $this->replaceTokensPhp($m[1], [
                        '[category_name]'       => $cat['[category_name]'],
                        '[category_link_count]' => $cat['[category_link_count]'],
                    ]);
                    $cat_text = $this->expandLinkBlocksPhp($cat_text, $cat['links']);
                    $cat_text = $this->expandLinkLinesPhp($cat_text, $cat['links']);
                    $expansions[] = $cat_text;
                }
                return implode('', $expansions);
            },
            $raw_text
        );

        // Top-level (non-category-nested) [link_start] blocks: unlike the JS
        // preview (limited to categoryVariants[0]'s links, a preview-fixture
        // quirk), real data uses ALL links so a template not using
        // [category_start] still shows every link in the roundup.
        $text = $this->expandLinkBlocksPhp($text, $all_link_token_maps);

        $text = $this->replaceTokensPhp($text, $scalar_data);

        return $text;
    }

    /**
     * @param string $text
     * @param array<string,string> $data
     * @return string
     */
    private function replaceTokensPhp(string $text, array $data): string {
        foreach ($data as $token => $value) {
            $text = str_replace($token, (string) $value, $text);
        }
        return $text;
    }

    /**
     * @param string $text
     * @param list<array<string,string>> $links
     * @return string
     */
    private function expandLinkBlocksPhp(string $text, array $links): string {
        return preg_replace_callback(
            '/\[link_start\]([\s\S]*?)\[link_end\]/',
            fn($m) => implode('', array_map(fn($l) => $this->replaceTokensPhp($m[1], $l), $links)),
            $text
        );
    }

    /**
     * @param string $text
     * @param list<array<string,string>> $links
     * @return string
     */
    private function expandLinkLinesPhp(string $text, array $links): string {
        $lines  = explode("\n", $text);
        $result = [];
        $i = 0;
        $n = count($lines);

        while ($i < $n) {
            if (!$this->lineHasLinkToken($lines[$i])) {
                $result[] = $lines[$i];
                $i++;
                continue;
            }
            [$group, $i] = $this->collectLinkTokenRun($lines, $i, $n);
            foreach ($links as $link) {
                foreach ($group as $group_line) {
                    $result[] = $this->replaceTokensPhp($group_line, $link);
                }
            }
        }

        return implode("\n", $result);
    }

    /**
     * @param string $line
     * @return bool
     */
    private function lineHasLinkToken(string $line): bool {
        foreach (self::LINK_TOKENS as $token) {
            if (str_contains($line, $token)) {
                return true;
            }
        }
        return false;
    }

    /**
     * Collects the contiguous run of lines starting at $start that contain
     * a link token, stopping at the first line that doesn't.
     *
     * @param list<string> $lines
     * @param int $start
     * @param int $n
     * @return array{0: list<string>, 1: int} The collected group and the index just past it.
     */
    private function collectLinkTokenRun(array $lines, int $start, int $n): array {
        $group = [];
        $i = $start;
        while ($i < $n && $this->lineHasLinkToken($lines[$i])) {
            $group[] = $lines[$i];
            $i++;
        }
        return [$group, $i];
    }

    /**
     * Flushes a pending indented-list buffer (built up by
     * convertIndentedLinesPhp()) into $result as a real <ul>/<ol>, if one
     * is pending. No-op if $list_buffer is already null.
     *
     * @param array{type: string, level: int, items: list<string>, start: int}|null $list_buffer
     * @param-out null $list_buffer
     * @param list<string> $result
     * @return void
     */
    private function flushIndentedListBuffer(?array &$list_buffer, array &$result): void {
        if ($list_buffer === null) {
            return;
        }
        ['type' => $type, 'level' => $level, 'items' => $items, 'start' => $start] = $list_buffer;
        $start_attr = ($type === 'ol' && $start !== 1) ? " start=\"{$start}\"" : '';
        $result[] = "<{$type} style=\"padding-left:" . ($level * 1.5) . "em\"{$start_attr}>"
            . implode('', array_map(static fn($item) => "<li>{$item}</li>", $items))
            . "</{$type}>";
        $list_buffer = null;
    }

    /**
     * PHP port of convertIndentedLines() (src/js/template-preview.js).
     *
     * @since 1.0.0
     * @param string $text
     * @return string
     */
    private function convertIndentedLinesPhp(string $text): string {
        $lines  = explode("\n", $text);
        $result = [];
        $i = 0;
        $n = count($lines);

        while ($i < $n) {
            if (!preg_match(self::INDENT_RE, $lines[$i])) {
                $result[] = $lines[$i];
                $i++;
                continue;
            }

            $prev_raw_line = $i > 0 ? $lines[$i - 1] : '';
            [$group, $i] = $this->collectIndentedRun($lines, $i, $n);

            if (preg_match(self::TOP_LEVEL_LIST_RE, $prev_raw_line)) {
                foreach ($group as $g) {
                    $result[] = $g[0];
                }
                continue;
            }

            foreach ($this->renderIndentedGroup($group) as $line) {
                $result[] = $line;
            }
        }

        return implode("\n", $result);
    }

    /**
     * Collects the contiguous run of lines starting at $start that match
     * INDENT_RE, stopping at the first non-matching (or missing) line.
     *
     * @param list<string> $lines
     * @param int $start
     * @param int $n
     * @return array{0: list<array<int,string>>, 1: int} The collected matches and the index just past them.
     */
    private function collectIndentedRun(array $lines, int $start, int $n): array {
        $matches = [];
        $i = $start;
        while ($i < $n && preg_match(self::INDENT_RE, $lines[$i], $m)) {
            $matches[] = $m;
            $i++;
        }
        return [$matches, $i];
    }

    /**
     * Renders one contiguous group of indented-line matches into <div>
     * wrappers and grouped <ul>/<ol> list blocks, per the rules described
     * on convertIndentedLinesPhp() / convertIndentedLines() (src/js/template-preview.js).
     *
     * @param list<array<int,string>> $group
     * @return list<string>
     */
    private function renderIndentedGroup(array $group): array {
        $rendered = explode("\n", $this->renderInlineMarkdownPhp(
            implode("\n", array_map(static fn($g) => $g[3], $group))
        ));

        $result      = [];
        $list_buffer = null; // ['type' => 'ul'|'ol', 'level' => int, 'items' => [], 'start' => int]

        foreach ($group as $idx => $g) {
            $level   = intdiv(strlen($g[1]), 2);
            $marker  = $g[2];
            $content = $rendered[$idx] ?? '';

            if ($marker === '') {
                $this->flushIndentedListBuffer($list_buffer, $result);
                $result[] = "<div style=\"padding-left:" . ($level * 1.5) . "em\">{$content}</div>";
                continue;
            }

            $type = $marker === '-' ? 'ul' : 'ol';
            $num  = $type === 'ol' ? (int) $marker : 1;

            if ($list_buffer !== null && $list_buffer['type'] === $type && $list_buffer['level'] === $level) {
                $list_buffer['items'][] = $content;
            } else {
                $this->flushIndentedListBuffer($list_buffer, $result);
                $list_buffer = ['type' => $type, 'level' => $level, 'items' => [$content], 'start' => $num];
            }
        }

        $this->flushIndentedListBuffer($list_buffer, $result);
        return $result;
    }

    /**
     * Inline-only Markdown render (equivalent of marked.js's parseInline()).
     * CommonMark has no first-class inline-only mode, so this runs a normal
     * full-document convert then strips a single wrapping <p>...</p> if the
     * whole result is exactly one paragraph — the standard PHP-CommonMark
     * "inline-only" workaround.
     *
     * @param string $text
     * @return string
     */
    private function renderInlineMarkdownPhp(string $text): string {
        $rendered = $this->getTemplateMarkdownConverter()->convert($text)->getContent();
        if (preg_match('/^<p>([\s\S]*)<\/p>\n?$/', $rendered, $m)) {
            return $m[1];
        }
        return $rendered;
    }

    /**
     * @param string $text
     * @return string
     */
    private function renderMarkdownPhp(string $text): string {
        return trim($text) !== '' ? $this->getTemplateMarkdownConverter()->convert($text)->getContent() : '';
    }

    /**
     * Wraps top-level rendered HTML elements (headings, lists, paragraphs,
     * horizontal rules) in Gutenberg block comments, using DOMDocument in
     * place of browser DOM APIs.
     *
     * @since 1.0.0
     * @param string $rendered_html
     * @return string
     */
    private function wrapAsGutenbergBlocksPhp(string $rendered_html): string {
        $dom = new \DOMDocument();
        libxml_use_internal_errors(true);
        // The XML-encoding-declaration prefix forces loadHTML() to treat the
        // input as UTF-8 instead of mangling multibyte characters.
        $dom->loadHTML(
            '<?xml encoding="utf-8"?><div>' . $rendered_html . '</div>',
            LIBXML_HTML_NOIMPLIED | LIBXML_HTML_NODEFDTD
        );
        libxml_clear_errors();

        $container = $dom->getElementsByTagName('div')->item(0);
        if (!$container) {
            return '';
        }

        $parts = [];
        foreach ($container->childNodes as $node) {
            if (!($node instanceof \DOMElement)) {
                continue;
            }
            $tag = strtolower($node->tagName);

            if ($tag === 'hr') {
                $parts[] = "<!-- wp:separator -->\n<hr class=\"wp-block-separator has-alpha-channel-opacity\"/>\n<!-- /wp:separator -->";
            } elseif (preg_match('/^h[1-6]$/', $tag)) {
                $level = (int) $tag[1];
                $parts[] = $this->buildHeadingBlock($this->domInnerHtml($node), $level);
            } elseif ($tag === 'ul' || $tag === 'ol') {
                $parts[] = $this->wrapListBlockPhp($node);
            } elseif ($tag === 'p') {
                $parts[] = "<!-- wp:paragraph -->\n" . $this->domOuterHtml($node) . "\n<!-- /wp:paragraph -->";
            } else {
                $parts[] = $this->domOuterHtml($node);
            }
        }

        return implode("\n\n", $parts);
    }

    /**
     * Recursively wraps a list node (and any nested sub-lists) in
     * wp:list/wp:list-item block comments.
     *
     * @param \DOMElement $node
     * @return string
     */
    private function wrapListBlockPhp(\DOMElement $node): string {
        $tag = strtolower($node->tagName);
        $existing_class = $node->getAttribute('class');
        $node->setAttribute('class', trim($existing_class . ' wp-block-list'));

        $start = $tag === 'ol' ? $node->getAttribute('start') : '';
        $start_attr_json = ($tag === 'ol' && $start !== '' && (int) $start !== 1) ? ",\"start\":{$start}" : '';
        $attrs = $tag === 'ol' ? " {\"ordered\":true{$start_attr_json}}" : '';
        $start_html = $start !== '' ? " start=\"{$start}\"" : '';

        $xpath = new \DOMXPath($node->ownerDocument);
        $items = [];
        foreach ($node->childNodes as $li) {
            if (!($li instanceof \DOMElement) || strtolower($li->tagName) !== 'li') {
                continue;
            }
            $nested = $xpath->query('./ul | ./ol', $li)->item(0);
            if (!$nested instanceof \DOMElement) {
                $items[] = "<!-- wp:list-item -->\n" . $this->domOuterHtml($li) . "\n<!-- /wp:list-item -->";
                continue;
            }
            $nested_block = $this->wrapListBlockPhp($nested);
            $nested->parentNode->removeChild($nested);
            $items[] = "<!-- wp:list-item -->\n<li>" . $this->domInnerHtml($li) . "\n{$nested_block}</li>\n<!-- /wp:list-item -->";
        }

        return "<!-- wp:list{$attrs} -->\n<{$tag}{$start_html} class=\"wp-block-list\">\n"
            . implode("\n", $items) . "\n</{$tag}>\n<!-- /wp:list -->";
    }

    /**
     * @param \DOMNode $node
     * @return string
     */
    private function domOuterHtml(\DOMNode $node): string {
        return (string) $node->ownerDocument?->saveHTML($node);
    }

    /**
     * @param \DOMNode $node
     * @return string
     */
    private function domInnerHtml(\DOMNode $node): string {
        $html = '';
        foreach ($node->childNodes as $child) {
            $html .= (string) $node->ownerDocument?->saveHTML($child);
        }
        return $html;
    }
}
