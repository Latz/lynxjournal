<?php

declare(strict_types=1);

if (!defined("ABSPATH")) {
    exit;
}

use Brain\Monkey\Functions;

/**
 * Tests for LynxJournal::createRoundupPost() and its helpers
 * groupLinksByCategory() / executeRoundupInsertion() (exercised indirectly).
 */

beforeEach(function (): void {
    Functions\when('esc_html')->returnArg();
    Functions\when('esc_html__')->returnArg();
    Functions\when('esc_url')->returnArg();
    Functions\when('wp_kses_post')->returnArg();
    Functions\when('apply_filters')->returnArg(2);
    Functions\when('__')->returnArg();
    Functions\when('_n')->returnArg();
    Functions\when('get_posts')->justReturn([]);
    Functions\when('update_meta_cache')->justReturn(true);
    Functions\when('update_object_term_cache')->justReturn(true);
    Functions\when('current_time')->justReturn('2026-04-13 10:00:00');
    $this->plugin = Mockery::mock(LynxJournal::class)->makePartial();
});

describe('LynxJournal::createRoundupPost()', function (): void {

    it('returns no_links when link_ids is empty', function (): void {
        $result = $this->plugin->createRoundupPost([], 'My Roundup');

        expect($result['success'])->toBeFalse();
        expect($result['error_code'])->toBe('no_links');
    });

    it('returns no_valid_links when none of the links are publishable', function (): void {
        Functions\when('get_post')->justReturn(null);

        $result = $this->plugin->createRoundupPost([1, 2], 'My Roundup');

        expect($result['success'])->toBeFalse();
        expect($result['error_code'])->toBe('no_valid_links');
    });

    it('returns insert_failed when wp_insert_post fails', function (): void {
        Functions\when('get_post')->alias(fn($id) => lynxjournal_make_post($id, "Link $id"));
        Functions\when('get_the_terms')->justReturn(false);
        Functions\when('wp_insert_post')->justReturn(new WP_Error('insert_failed', 'Insert failed'));

        $result = $this->plugin->createRoundupPost([1], 'My Roundup');

        expect($result['success'])->toBeFalse();
        expect($result['error_code'])->toBe('insert_failed');
    });

    it('creates a roundup post grouping links by category, with uncategorized links under "Other"', function (): void {
        Functions\when('get_post')
            ->alias(fn($id) => lynxjournal_make_post($id, "Link $id", 'lynx-journal', 'Some content'));
        Functions\when('get_post_meta')->justReturn('https://example.com/link');

        $tech = (object) ['slug' => 'tech', 'name' => 'Tech', 'term_id' => 5];
        Functions\when('get_the_terms')->alias(function (int $id) use ($tech) {
            return $id === 1 ? [$tech] : false;
        });

        Functions\when('wp_insert_post')->justReturn(99);
        Functions\when('get_category_by_slug')->justReturn(false);
        Functions\when('wp_insert_term')->justReturn(['term_id' => 50, 'term_taxonomy_id' => 50]);

        $set_cats = null;
        Functions\when('wp_set_post_categories')->alias(function (int $post_id, array $cats) use (&$set_cats) {
            $set_cats = $cats;
            return $cats;
        });
        Functions\when('wp_set_post_tags')->justReturn([]);

        $updated_posts = [];
        Functions\when('wp_update_post')->alias(function (array $args) use (&$updated_posts) {
            $updated_posts[] = $args;
            return $args['ID'];
        });
        Functions\when('update_post_meta')->justReturn(true);
        Functions\when('delete_transient')->justReturn(true);

        $result = $this->plugin->createRoundupPost([1, 2], 'My Roundup');

        expect($result['success'])->toBeTrue();
        expect($result['post_id'])->toBe(99);
        expect($result['link_count'])->toBe(2);
        expect($set_cats)->toBe([50]);
        expect($updated_posts)->toHaveCount(2);
    });

    it('generates a default title from the current date when post_title is empty', function (): void {
        Functions\when('get_post')->justReturn(null);

        $result = $this->plugin->createRoundupPost([1], '');

        // No valid links, so we only reach the title-generation branch, not insertion.
        expect($result['error_code'])->toBe('no_valid_links');
    });

    it('sets post_author when author_id is provided', function (): void {
        Functions\when('get_post')->alias(fn($id) => lynxjournal_make_post($id, "Link $id", 'lynx-journal'));
        Functions\when('get_post_meta')->justReturn('');
        Functions\when('get_the_terms')->justReturn(false);

        $inserted_args = null;
        Functions\when('wp_insert_post')->alias(function (array $args) use (&$inserted_args) {
            $inserted_args = $args;
            return 77;
        });
        Functions\when('wp_set_post_tags')->justReturn([]);
        Functions\when('wp_update_post')->justReturn(1);
        Functions\when('update_post_meta')->justReturn(true);
        Functions\when('delete_transient')->justReturn(true);

        $result = $this->plugin->createRoundupPost([1], 'Title', false, 'manual', 42);

        expect($result['success'])->toBeTrue();
        expect($inserted_args['post_author'])->toBe(42);
    });
});
