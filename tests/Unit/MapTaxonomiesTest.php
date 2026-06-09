<?php

declare(strict_types=1);

if (!defined('ABSPATH')) {
    exit;
}

use Brain\Monkey\Functions;

function lynxjournal_make_term(int $id, string $name, string $slug): object
{
    $t           = new stdClass();
    $t->term_id  = $id;
    $t->name     = $name;
    $t->slug     = $slug;
    return $t;
}

beforeEach(function (): void {
    Functions\when('__')->returnArg();
    $this->plugin = Mockery::mock(LynxJournal::class)->makePartial();
});

describe('LynxJournal::mapTaxonomies()', function (): void {

    it('assigns an existing WP category when the slug matches', function (): void {
        $cat = lynxjournal_make_term(10, 'Tech', 'tech');
        Functions\when('get_the_terms')->alias(function (mixed $id, string $taxonomy) use ($cat): mixed {
            return $taxonomy === 'lynxjournal_category' ? [$cat] : false;
        });

        $existingCat           = new stdClass();
        $existingCat->term_id  = 99;
        Functions\when('get_category_by_slug')->justReturn($existingCat);

        $assignedCategories = null;
        Functions\when('wp_set_post_categories')->alias(function (int $postId, array $catIds) use (&$assignedCategories): array {
            $assignedCategories = $catIds;
            return $catIds;
        });
        Functions\when('wp_set_post_tags')->justReturn([]);

        $this->plugin->mapTaxonomies(1, 2);

        expect($assignedCategories)->toBe([99]);
    });

    it('creates a new WP category when slug does not exist, then assigns it', function (): void {
        $cat = lynxjournal_make_term(10, 'NewCat', 'new-cat');
        Functions\when('get_the_terms')->alias(function (mixed $id, string $taxonomy) use ($cat): mixed {
            return $taxonomy === 'lynxjournal_category' ? [$cat] : false;
        });

        Functions\when('get_category_by_slug')->justReturn(false);
        Functions\when('wp_insert_term')->justReturn(['term_id' => 55, 'term_taxonomy_id' => 55]);

        $assignedCategories = null;
        Functions\when('wp_set_post_categories')->alias(function (int $postId, array $catIds) use (&$assignedCategories): array {
            $assignedCategories = $catIds;
            return $catIds;
        });
        Functions\when('wp_set_post_tags')->justReturn([]);

        $this->plugin->mapTaxonomies(1, 2);

        expect($assignedCategories)->toBe([55]);
    });

    it('assigns post tags from lynxjournal_tag terms', function (): void {
        Functions\when('get_the_terms')->alias(function (mixed $id, string $taxonomy): mixed {
            if ($taxonomy === 'lynxjournal_category') return false;
            return [
                lynxjournal_make_term(1, 'php', 'php'),
                lynxjournal_make_term(2, 'oss',  'oss'),
            ];
        });

        $assignedTags = null;
        Functions\when('wp_set_post_tags')->alias(function (int $postId, mixed $tags) use (&$assignedTags): mixed {
            $assignedTags = $tags;
            return [];
        });

        $this->plugin->mapTaxonomies(1, 2);

        expect($assignedTags)->toBe(['php', 'oss']);
    });

    it('does nothing when get_the_terms returns false', function (): void {
        Functions\when('get_the_terms')->justReturn(false);

        $called = false;
        Functions\when('wp_set_post_categories')->alias(function () use (&$called): array {
            $called = true;
            return [];
        });
        Functions\when('wp_set_post_tags')->alias(function () use (&$called): array {
            $called = true;
            return [];
        });

        $this->plugin->mapTaxonomies(1, 2);

        expect($called)->toBeFalse();
    });
});
