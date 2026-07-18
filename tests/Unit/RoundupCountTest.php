<?php

declare(strict_types=1);

if (!defined('ABSPATH')) {
    exit;
}

use Brain\Monkey\Functions;

/**
 * Tests for the lynxjournal_roundup_count option incremented by createRoundupPost().
 */

beforeEach(function (): void {
    // i18n stubs
    Functions\when('__')->returnArg();
    Functions\when('_n')->alias(fn($s, $p, $n) => $n === 1 ? $s : $p);

    // Sanitisation stubs
    Functions\when('esc_html')->returnArg();
    Functions\when('esc_url')->returnArg();
    Functions\when('wp_kses_post')->returnArg();
    Functions\when('get_the_date')->justReturn('2026-06-29');
    Functions\when('get_bloginfo')->justReturn('Test Site');
    Functions\when('wp_date')->justReturn('2026-06-29');

    // WP hooks / taxonomy
    Functions\when('apply_filters')->returnArg(2);
    Functions\when('get_the_terms')->justReturn(false);
    Functions\when('clean_post_cache')->justReturn(null);

    // Cache-priming calls in createRoundupPost() entry
    Functions\when('get_posts')->justReturn([]);
    Functions\when('update_meta_cache')->justReturn(null);
    Functions\when('update_object_term_cache')->justReturn(false);

    // markLinksAsPublished() WP function calls
    Functions\when('current_time')->justReturn('2026-06-29 12:00:00');
    Functions\when('update_post_meta')->justReturn(true);
    Functions\when('delete_transient')->justReturn(true);

    // Minimal $wpdb mock — only needs prepare() + query() for markLinksAsPublished()
    global $wpdb;
    $wpdb        = new class {
        public string $posts = 'wp_posts';
        public function prepare(string $sql, mixed ...$args): string { return $sql; }
        public function query(string $sql): int { return 1; }
    };

    $this->plugin = Mockery::mock(LynxJournal::class)->makePartial();
});

describe('createRoundupPost() — lynxjournal_roundup_count', function (): void {

    it('increments roundup_count from 0 to 1 on first publish', function (): void {
        Functions\when('get_post')->alias(fn($id) => lynxjournal_make_post((int) $id, "Link $id"));
        Functions\when('get_post_meta')->justReturn('https://example.com');
        Functions\when('wp_insert_post')->justReturn(99);
        Functions\when('get_option')->alias(fn($k, $d = false) => $k === 'lynxjournal_roundup_count' ? 0 : $d);

        $updateCalls = [];
        Functions\when('update_option')->alias(function (string $k, mixed $v) use (&$updateCalls): bool {
            $updateCalls[$k] = $v;
            return true;
        });

        $this->plugin->createRoundupPost([1], 'First Roundup', false);

        expect($updateCalls['lynxjournal_roundup_count'] ?? null)->toBe(1);
    });

    it('increments roundup_count from N to N+1 on subsequent publishes', function (): void {
        Functions\when('get_post')->alias(fn($id) => lynxjournal_make_post((int) $id, "Link $id"));
        Functions\when('get_post_meta')->justReturn('https://example.com');
        Functions\when('wp_insert_post')->justReturn(100);
        Functions\when('get_option')->alias(fn($k, $d = false) => $k === 'lynxjournal_roundup_count' ? 7 : $d);

        $updateCalls = [];
        Functions\when('update_option')->alias(function (string $k, mixed $v) use (&$updateCalls): bool {
            $updateCalls[$k] = $v;
            return true;
        });

        $this->plugin->createRoundupPost([1], 'Eighth Roundup', false);

        expect($updateCalls['lynxjournal_roundup_count'] ?? null)->toBe(8);
    });

    it('does not touch roundup_count when publishing as a draft', function (): void {
        Functions\when('get_post')->alias(fn($id) => lynxjournal_make_post((int) $id, "Link $id"));
        Functions\when('get_post_meta')->justReturn('https://example.com');
        Functions\when('wp_insert_post')->justReturn(101);
        Functions\when('get_option')->alias(fn($k, $d = false) => $k === 'lynxjournal_roundup_count' ? 3 : $d);

        $roundupCountUpdated = false;
        Functions\when('update_option')->alias(function (string $k, mixed $v) use (&$roundupCountUpdated): bool {
            if ($k === 'lynxjournal_roundup_count') {
                $roundupCountUpdated = true;
            }
            return true;
        });

        $this->plugin->createRoundupPost([1], 'Draft Roundup', true);

        expect($roundupCountUpdated)->toBeFalse();
    });
});
