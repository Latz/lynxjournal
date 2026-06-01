<?php

declare(strict_types=1);

if (!defined("ABSPATH")) {
    exit;
}

use Brain\Monkey\Functions;

/**
 * Tests for LinkDigest::restGetCategories() and LinkDigest::invalidateCategoriesCache()
 */

beforeEach(function (): void {
    Functions\when('__')->returnArg();
    Functions\when('rest_ensure_response')->returnArg();
    $this->plugin = Mockery::mock(LinkDigest::class)->makePartial();
});

describe('LinkDigest::restGetCategories()', function (): void {

    it('returns cached data and skips get_terms on a cache hit', function (): void {
        $cached = [['id' => 1, 'name' => 'Tech', 'slug' => 'tech']];

        Functions\when('get_transient')->justReturn($cached);
        // get_terms must NOT be called — stub it to return an unexpected value to catch misuse
        Functions\when('get_terms')->justReturn([new WP_Error('unexpected', 'Should not be called')]);

        $result = $this->plugin->restGetCategories(linkdigest_make_request());

        expect($result)->toBe($cached);
    });

    it('fetches terms and stores them in a transient on a cache miss', function (): void {
        $term        = (object) ['term_id' => 5, 'name' => 'PHP', 'slug' => 'php'];
        Functions\when('get_transient')->justReturn(false);
        Functions\when('get_terms')->justReturn([$term]);

        $transientKey = null;
        $transientVal = null;
        Functions\when('set_transient')->alias(
            function (string $key, mixed $val, int $_ttl) use (&$transientKey, &$transientVal): bool {
                $transientKey = $key;
                $transientVal = $val;
                return true;
            }
        );

        $result = $this->plugin->restGetCategories(linkdigest_make_request());

        expect($transientKey)->toBe('linkdigest_api_categories_list');
        expect($transientVal)->toBeArray();

        expect($result)->toBeArray()->toHaveCount(1);
        expect($result[0]['name'])->toBe('PHP');
        expect($result[0]['slug'])->toBe('php');
    });

    it('returns the correct shape [id, name, slug] for each category', function (): void {
        $terms = [
            (object) ['term_id' => 1, 'name' => 'Tech',    'slug' => 'tech'],
            (object) ['term_id' => 2, 'name' => 'Science', 'slug' => 'science'],
        ];
        Functions\when('get_transient')->justReturn(false);
        Functions\when('get_terms')->justReturn($terms);
        Functions\when('set_transient')->justReturn(true);

        $result = $this->plugin->restGetCategories(linkdigest_make_request());

        expect($result)->toHaveCount(2);
        expect(array_keys($result[0]))->toBe(['id', 'name', 'slug']);
    });

    it('returns an empty array when there are no categories', function (): void {
        Functions\when('get_transient')->justReturn(false);
        Functions\when('get_terms')->justReturn([]);
        Functions\when('set_transient')->justReturn(true);

        $result = $this->plugin->restGetCategories(linkdigest_make_request());

        expect($result)->toBe([]);
    });

    it('returns a WP_Error when get_terms fails', function (): void {
        Functions\when('get_transient')->justReturn(false);
        Functions\when('get_terms')->justReturn(new WP_Error('db_error', 'DB fail'));

        $result = $this->plugin->restGetCategories(linkdigest_make_request());

        expect($result)->toBeInstanceOf(WP_Error::class);
        expect($result->get_error_code())->toBe('fetch_failed');
    });
});

describe('LinkDigest::invalidateCategoriesCache()', function (): void {

    it('deletes the correct transient keys', function (): void {
        $deletedKeys = [];
        Functions\when('delete_transient')->alias(
            function (string $key) use (&$deletedKeys): bool {
                $deletedKeys[] = $key;
                return true;
            }
        );

        $this->plugin->invalidateCategoriesCache();

        expect($deletedKeys)->toContain('linkdigest_api_categories_list');
        expect($deletedKeys)->toContain('linkdigest_categories_terms');
    });
});
