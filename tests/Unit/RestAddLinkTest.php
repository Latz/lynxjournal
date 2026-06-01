<?php

declare(strict_types=1);

if (!defined("ABSPATH")) {
    exit;
}

use Brain\Monkey\Functions;

/**
 * Tests for LinkDigest::restAddLink()
 */

beforeEach(function (): void {
    Functions\when('__')->returnArg();
    Functions\when('rest_ensure_response')->returnArg();
    Functions\when('sanitize_text_field')->returnArg();
    Functions\when('esc_url_raw')->returnArg();
    $this->plugin = Mockery::mock(LinkDigest::class)->makePartial();
});

describe('LinkDigest::restAddLink()', function (): void {

    it('returns a WP_Error with status 400 when title is empty', function (): void {
        $request = linkdigest_make_request(['title' => '']);

        $result = $this->plugin->restAddLink($request);

        expect($result)->toBeInstanceOf(WP_Error::class);
        expect($result->get_error_code())->toBe('missing_title');
        expect($result->get_error_data()['status'])->toBe(400);
    });

    it('returns a WP_Error with status 500 when wp_insert_post fails', function (): void {
        Functions\when('wp_insert_post')
            ->justReturn(new WP_Error('db_error', 'Database error'));

        $request = linkdigest_make_request(['title' => 'Valid Title']);

        $result = $this->plugin->restAddLink($request);

        expect($result)->toBeInstanceOf(WP_Error::class);
        expect($result->get_error_code())->toBe('insert_failed');
    });

    it('returns success with the new post id', function (): void {
        Functions\when('wp_insert_post')->justReturn(42);
        Functions\when('update_post_meta')->justReturn(true);

        $request = linkdigest_make_request(['title' => 'My Link', 'url' => LINKDIGEST_URL_EXAMPLE]);

        $result = $this->plugin->restAddLink($request);

        expect($result['success'])->toBeTrue();
        expect($result['post_id'])->toBe(42);
    });

    it('saves the URL in post meta when url is provided', function (): void {
        Functions\when('wp_insert_post')->justReturn(42);

        $savedMeta = [];
        Functions\when('update_post_meta')
            ->alias(function (int $_id, string $key, mixed $value) use (&$savedMeta): bool {
                $savedMeta[$key] = $value;
                return true;
            });

        $request = linkdigest_make_request(['title' => 'Link', 'url' => LINKDIGEST_URL_EXAMPLE]);
        $this->plugin->restAddLink($request);

        expect($savedMeta['_linkdigest_url'])->toBe(LINKDIGEST_URL_EXAMPLE);
    });

    it('does not call update_post_meta for url when url is empty', function (): void {
        Functions\when('wp_insert_post')->justReturn(42);

        $metaKeys = [];
        Functions\when('update_post_meta')
            ->alias(function (int $_id, string $key) use (&$metaKeys): bool {
                $metaKeys[] = $key;
                return true;
            });

        $request = linkdigest_make_request(['title' => 'Link', 'url' => '']);
        $this->plugin->restAddLink($request);

        expect($metaKeys)->not->toContain('_linkdigest_url');
    });

    it('assigns categories when provided', function (): void {
        Functions\when('wp_insert_post')->justReturn(42);
        Functions\when('update_post_meta')->justReturn(true);
        Functions\when('get_term_by')
            ->alias(fn($f, $v, $t) => (object) ['term_id' => 99, 'name' => $v, 'slug' => strtolower($v)]);

        $termsCall = null;
        Functions\when('wp_set_object_terms')->alias(
            function (int $postId, array $terms, string $taxonomy) use (&$termsCall): array {
                $termsCall = [$postId, $terms, $taxonomy];
                return [];
            }
        );

        $request = linkdigest_make_request(['title' => 'Link', 'categories' => ['Tech']]);
        $this->plugin->restAddLink($request);

        expect($termsCall)->toBe([42, [99], 'linkdigest_category']);
    });

    it('creates a new category term when it does not exist yet', function (): void {
        Functions\when('wp_insert_post')->justReturn(42);
        Functions\when('update_post_meta')->justReturn(true);
        Functions\when('get_term_by')->justReturn(false);

        $insertedTerm = null;
        Functions\when('wp_insert_term')->alias(
            function (string $term, string $taxonomy) use (&$insertedTerm): array {
                $insertedTerm = [$term, $taxonomy];
                return ['term_id' => 77, 'term_taxonomy_id' => 77];
            }
        );
        Functions\when('wp_set_object_terms')->justReturn([]);

        $request = linkdigest_make_request(['title' => 'Link', 'categories' => ['NewCat']]);
        $this->plugin->restAddLink($request);

        expect($insertedTerm)->toBe(['NewCat', 'linkdigest_category']);
    });

    it('assigns tags when provided as a comma-separated string', function (): void {
        Functions\when('wp_insert_post')->justReturn(42);
        Functions\when('update_post_meta')->justReturn(true);

        $tagsCall = null;
        Functions\when('wp_set_object_terms')->alias(
            function (int $postId, array $terms, string $taxonomy) use (&$tagsCall): array {
                $tagsCall = [$postId, $terms, $taxonomy];
                return [];
            }
        );

        $request = linkdigest_make_request(['title' => 'Link', 'tags' => 'php, wordpress']);
        $this->plugin->restAddLink($request);

        expect($tagsCall[0])->toBe(42);
        expect($tagsCall[1])->toBe(['php', 'wordpress']);
        expect($tagsCall[2])->toBe('linkdigest_tag');
    });
});
