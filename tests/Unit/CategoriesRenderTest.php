<?php

declare(strict_types=1);

if (!defined("ABSPATH")) {
    exit;
}

use Brain\Monkey\Functions;

/**
 * Tests for the Categories admin-page entry point and rendering methods:
 * categoriesPage(), buildAddCategoryNotice()/buildDeleteCategoryNotice(),
 * getCategoryLinkCounts(), renderCategoriesTable(), renderCategoryForm().
 */

beforeEach(function (): void {
    Functions\when('__')->returnArg();
    Functions\when('sanitize_text_field')->returnArg();
    Functions\when('sanitize_textarea_field')->returnArg();
    Functions\when('wp_unslash')->returnArg();
    Functions\when('esc_html')->returnArg();
    Functions\when('esc_html__')->returnArg();
    Functions\when('esc_html_e')->alias(function (string $t): void {
        echo htmlspecialchars($t);
    });
    Functions\when('esc_attr')->alias(fn($t): string => htmlspecialchars((string) $t, ENT_QUOTES));
    Functions\when('wp_nonce_field')->justReturn('');
    $this->plugin = Mockery::mock(LynxJournal::class)->makePartial();
    $_POST   = [];
    $_SERVER = [];
});

afterEach(function (): void {
    $_POST   = [];
    $_SERVER = [];
});

describe('LynxJournal::buildAddCategoryNotice()', function (): void {

    beforeEach(function (): void {
        $this->method = new \ReflectionMethod(LynxJournal::class, 'buildAddCategoryNotice');
    });

    it('returns an error notice when handleAddCategory fails', function (): void {
        // handleAddCategory() is private, so we drive it via $_POST rather than mocking it.
        $_POST = ['lynxjournal_cat_nonce' => 'valid'];
        Functions\when('wp_verify_nonce')->justReturn(1);
        Functions\when('current_user_can')->justReturn(true);

        $result = $this->method->invoke($this->plugin);

        expect($result)->toBe(['type' => 'error', 'msg' => 'Category name is required.']);
    });

    it('returns a success notice when handleAddCategory succeeds', function (): void {
        $_POST = ['lynxjournal_cat_nonce' => 'valid', 'cat_name' => 'Tech'];
        Functions\when('wp_verify_nonce')->justReturn(1);
        Functions\when('current_user_can')->justReturn(true);
        Functions\when('wp_insert_term')->justReturn(['term_id' => 5, 'term_taxonomy_id' => 5]);
        Functions\when('delete_transient')->justReturn(true);

        $result = $this->method->invoke($this->plugin);

        expect($result)->toBe(['type' => 'success', 'msg' => 'Category added.']);
    });
});

describe('LynxJournal::buildDeleteCategoryNotice()', function (): void {

    beforeEach(function (): void {
        $this->method = new \ReflectionMethod(LynxJournal::class, 'buildDeleteCategoryNotice');
    });

    it('returns a success notice when the category was deleted', function (): void {
        // handleDeleteCategory() is private, so we drive it via $_POST rather than mocking it.
        $_POST = ['lynxjournal_cat_nonce' => 'valid', 'cat_term_id' => '5'];
        Functions\when('wp_verify_nonce')->justReturn(1);
        Functions\when('current_user_can')->justReturn(true);
        Functions\when('wp_delete_term')->justReturn(true);
        Functions\when('delete_transient')->justReturn(true);

        $result = $this->method->invoke($this->plugin);

        expect($result)->toBe(['type' => 'success', 'msg' => 'Category deleted.']);
    });

    it('returns an error notice when deletion failed', function (): void {
        $_POST = ['lynxjournal_cat_nonce' => 'valid', 'cat_term_id' => '5'];
        Functions\when('wp_verify_nonce')->justReturn(1);
        Functions\when('current_user_can')->justReturn(true);
        Functions\when('wp_delete_term')->justReturn(new WP_Error('invalid_term', 'Invalid term.'));

        $result = $this->method->invoke($this->plugin);

        expect($result)->toBe(['type' => 'error', 'msg' => 'Could not delete category.']);
    });
});

describe('LynxJournal::getCategoryLinkCounts()', function (): void {

    beforeEach(function (): void {
        $this->method = new \ReflectionMethod(LynxJournal::class, 'getCategoryLinkCounts');
    });

    it('returns an empty array when there are no rows', function (): void {
        $GLOBALS['wpdb'] = Mockery::mock('wpdb');
        $GLOBALS['wpdb']->term_taxonomy     = 'wp_term_taxonomy';
        $GLOBALS['wpdb']->term_relationships = 'wp_term_relationships';
        $GLOBALS['wpdb']->posts             = 'wp_posts';
        $GLOBALS['wpdb']->shouldReceive('get_results')->once()->andReturn(null);

        expect($this->method->invoke($this->plugin))->toBe([]);
    });

    it('returns counts keyed by term_id', function (): void {
        $GLOBALS['wpdb'] = Mockery::mock('wpdb');
        $GLOBALS['wpdb']->term_taxonomy     = 'wp_term_taxonomy';
        $GLOBALS['wpdb']->term_relationships = 'wp_term_relationships';
        $GLOBALS['wpdb']->posts             = 'wp_posts';
        $GLOBALS['wpdb']->shouldReceive('get_results')->once()->andReturn([
            ['term_id' => 5, 'cnt' => 3],
            ['term_id' => 7, 'cnt' => 0],
        ]);

        expect($this->method->invoke($this->plugin))->toBe([5 => 3, 7 => 0]);
    });
});

describe('LynxJournal::renderCategoriesTable()', function (): void {

    beforeEach(function (): void {
        $this->method = new \ReflectionMethod(LynxJournal::class, 'renderCategoriesTable');
    });

    it('shows the empty-state message when there are no terms', function (): void {
        ob_start();
        $this->method->invoke($this->plugin, [], []);
        $html = ob_get_clean();

        expect($html)->toContain('No categories yet.');
        expect($html)->not->toContain('lynxjournal-cat-table');
    });

    it('renders a row per term with its link count', function (): void {
        $term = (object) ['term_id' => 5, 'name' => 'Tech', 'description' => 'Tech stuff', 'slug' => 'tech'];

        ob_start();
        $this->method->invoke($this->plugin, [$term], [5 => 3]);
        $html = ob_get_clean();

        expect($html)->toContain('lynxjournal-cat-table');
        expect($html)->toContain('Tech');
        expect($html)->toContain('Tech stuff');
        expect($html)->toContain('data-count="3"');
    });

    it('defaults the count to 0 when the term has no entry in counts', function (): void {
        $term = (object) ['term_id' => 9, 'name' => 'News', 'description' => '', 'slug' => 'news'];

        ob_start();
        $this->method->invoke($this->plugin, [$term], []);
        $html = ob_get_clean();

        expect($html)->toContain('data-count="0"');
    });
});

describe('LynxJournal::renderCategoryForm()', function (): void {

    it('renders the add-category form', function (): void {
        $method = new \ReflectionMethod(LynxJournal::class, 'renderCategoryForm');

        ob_start();
        $method->invoke($this->plugin);
        $html = ob_get_clean();

        expect($html)->toContain('cat_name');
        expect($html)->toContain('cat_description');
        expect($html)->toContain('lynxjournal_add_category');
    });
});

describe('LynxJournal::categoriesPage()', function (): void {

    beforeEach(function (): void {
        // getCategoryLinkCounts() is private, so we satisfy it via a $wpdb mock instead.
        $GLOBALS['wpdb'] = Mockery::mock('wpdb');
        $GLOBALS['wpdb']->term_taxonomy      = 'wp_term_taxonomy';
        $GLOBALS['wpdb']->term_relationships = 'wp_term_relationships';
        $GLOBALS['wpdb']->posts              = 'wp_posts';
        $GLOBALS['wpdb']->shouldReceive('get_results')->andReturn(null);
    });

    it('renders without a notice when the request is not a POST', function (): void {
        $_SERVER = ['REQUEST_METHOD' => 'GET'];
        $this->plugin->shouldReceive('getCachedCategories')->once()->andReturn([]);

        ob_start();
        $this->plugin->categoriesPage();
        $html = ob_get_clean();

        expect($html)->not->toContain('notice-success');
        expect($html)->not->toContain('notice-error');
        expect($html)->toContain('No categories yet.');
    });

    it('shows a success notice after a valid add-category POST', function (): void {
        $_SERVER = ['REQUEST_METHOD' => 'POST'];
        $_POST   = ['lynxjournal_add_category' => '1', 'lynxjournal_cat_nonce' => 'valid', 'cat_name' => 'Tech'];
        Functions\when('wp_verify_nonce')->justReturn(1);
        Functions\when('current_user_can')->justReturn(true);
        Functions\when('wp_insert_term')->justReturn(['term_id' => 5, 'term_taxonomy_id' => 5]);
        Functions\when('delete_transient')->justReturn(true);
        $this->plugin->shouldReceive('getCachedCategories')->once()->andReturn([]);

        ob_start();
        $this->plugin->categoriesPage();
        $html = ob_get_clean();

        expect($html)->toContain('notice-success');
        expect($html)->toContain('Category added.');
    });

    it('shows an error notice after a failed delete-category POST', function (): void {
        $_SERVER = ['REQUEST_METHOD' => 'POST'];
        $_POST   = ['lynxjournal_delete_category' => '1', 'lynxjournal_cat_nonce' => 'valid', 'cat_term_id' => '5'];
        Functions\when('wp_verify_nonce')->justReturn(1);
        Functions\when('current_user_can')->justReturn(true);
        Functions\when('wp_delete_term')->justReturn(new WP_Error('invalid_term', 'Invalid term.'));
        $this->plugin->shouldReceive('getCachedCategories')->once()->andReturn([]);

        ob_start();
        $this->plugin->categoriesPage();
        $html = ob_get_clean();

        expect($html)->toContain('notice-error');
        expect($html)->toContain('Could not delete category.');
    });

    it('renders no notice when the POST nonce is invalid', function (): void {
        $_SERVER = ['REQUEST_METHOD' => 'POST'];
        $_POST   = ['lynxjournal_add_category' => '1', 'lynxjournal_cat_nonce' => 'bad'];
        Functions\when('wp_verify_nonce')->justReturn(false);
        $this->plugin->shouldReceive('getCachedCategories')->once()->andReturn([]);

        ob_start();
        $this->plugin->categoriesPage();
        $html = ob_get_clean();

        expect($html)->not->toContain('notice-success');
        expect($html)->not->toContain('notice-error');
    });

    it('renders the categories table when terms exist', function (): void {
        $_SERVER = ['REQUEST_METHOD' => 'GET'];
        $term = (object) ['term_id' => 1, 'name' => 'Tech', 'description' => '', 'slug' => 'tech'];
        $this->plugin->shouldReceive('getCachedCategories')->once()->andReturn([$term]);

        ob_start();
        $this->plugin->categoriesPage();
        $html = ob_get_clean();

        expect($html)->toContain('Tech');
        expect($html)->toContain('lynxjournal-cat-table');
    });
});
