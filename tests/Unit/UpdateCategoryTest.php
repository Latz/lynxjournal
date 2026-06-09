<?php

declare(strict_types=1);

if (!defined('ABSPATH')) {
    exit;
}

use Brain\Monkey\Functions;

beforeEach(function (): void {
    Functions\when('__')->returnArg();
    Functions\when('rest_ensure_response')->returnArg();
    $this->plugin = Mockery::mock(LynxJournal::class)->makePartial();
});

describe('LynxJournal::updateCategory()', function (): void {

    it('returns WP_Error passthrough when wp_update_term fails', function (): void {
        Functions\when('wp_update_term')->justReturn(new WP_Error('term_exists', 'Term already exists'));

        $request        = lynxjournal_make_request(['id' => 5, 'name' => 'Tech']);

        $result = $this->plugin->updateCategory($request);

        expect($result)->toBeInstanceOf(WP_Error::class);
        expect($result->get_error_code())->toBe('term_exists');
    });

    it('returns updated term data and invalidates cache on success', function (): void {
        Functions\when('wp_update_term')->justReturn(['term_id' => 5, 'term_taxonomy_id' => 5]);

        $term              = new stdClass();
        $term->term_id     = 5;
        $term->name        = 'Technology';
        $term->slug        = 'technology';
        $term->description = 'Tech links';
        Functions\when('get_term')->justReturn($term);

        $deletedTransients = [];
        Functions\when('delete_transient')->alias(function (string $key) use (&$deletedTransients): bool {
            $deletedTransients[] = $key;
            return true;
        });

        $request = lynxjournal_make_request(['id' => 5, 'name' => 'Technology']);

        $result = $this->plugin->updateCategory($request);

        expect($result['id'])->toBe(5);
        expect($result['name'])->toBe('Technology');
        expect($result['slug'])->toBe('technology');
        expect($deletedTransients)->toContain('lynxjournal_api_categories_list');
        expect($deletedTransients)->toContain('lynxjournal_categories_terms');
    });

    it('passes description and slug only when present in request', function (): void {
        $capturedArgs = null;
        Functions\when('wp_update_term')->alias(function (int $id, string $taxonomy, array $args) use (&$capturedArgs): array {
            $capturedArgs = $args;
            return ['term_id' => $id, 'term_taxonomy_id' => $id];
        });

        $term              = new stdClass();
        $term->term_id     = 3;
        $term->name        = 'Design';
        $term->slug        = 'design';
        $term->description = '';
        Functions\when('get_term')->justReturn($term);
        Functions\when('delete_transient')->justReturn(true);

        // Request with only name — description and slug absent
        $request = lynxjournal_make_request(['id' => 3, 'name' => 'Design']);

        $this->plugin->updateCategory($request);

        expect($capturedArgs)->toHaveKey('name');
        expect($capturedArgs)->not->toHaveKey('description');
        expect($capturedArgs)->not->toHaveKey('slug');
    });

    it('passes description and slug when explicitly provided', function (): void {
        $capturedArgs = null;
        Functions\when('wp_update_term')->alias(function (int $id, string $taxonomy, array $args) use (&$capturedArgs): array {
            $capturedArgs = $args;
            return ['term_id' => $id, 'term_taxonomy_id' => $id];
        });

        $term              = new stdClass();
        $term->term_id     = 3;
        $term->name        = 'Design';
        $term->slug        = 'design-new';
        $term->description = 'UI/UX links';
        Functions\when('get_term')->justReturn($term);
        Functions\when('delete_transient')->justReturn(true);

        $request = lynxjournal_make_request(['id' => 3, 'name' => 'Design', 'description' => 'UI/UX links', 'slug' => 'design-new']);

        $this->plugin->updateCategory($request);

        expect($capturedArgs['description'])->toBe('UI/UX links');
        expect($capturedArgs['slug'])->toBe('design-new');
    });
});
