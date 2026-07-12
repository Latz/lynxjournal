<?php

declare(strict_types=1);

if (!defined("ABSPATH")) {
    exit;
}

/**
 * Tests for query helpers in LynxJournal_Queries.
 */

beforeEach(function (): void {
    $this->plugin = Mockery::mock(LynxJournal::class)->makePartial();
});

afterEach(function (): void {
    unset($GLOBALS['wpdb']);
});

describe('LynxJournal::getCategoryLinkCounts()', function (): void {

    it('returns an empty array when there are no rows', function (): void {
        $GLOBALS['wpdb'] = Mockery::mock('wpdb');
        $GLOBALS['wpdb']->term_taxonomy      = 'wp_term_taxonomy';
        $GLOBALS['wpdb']->term_relationships = 'wp_term_relationships';
        $GLOBALS['wpdb']->posts              = 'wp_posts';
        $GLOBALS['wpdb']->shouldReceive('get_results')->once()->andReturn(null);

        expect($this->plugin->getCategoryLinkCounts())->toBe([]);
    });

    it('returns counts keyed by term_id', function (): void {
        $GLOBALS['wpdb'] = Mockery::mock('wpdb');
        $GLOBALS['wpdb']->term_taxonomy      = 'wp_term_taxonomy';
        $GLOBALS['wpdb']->term_relationships = 'wp_term_relationships';
        $GLOBALS['wpdb']->posts              = 'wp_posts';
        $GLOBALS['wpdb']->shouldReceive('get_results')->once()->andReturn([
            ['term_id' => 5, 'cnt' => 3],
            ['term_id' => 7, 'cnt' => 0],
        ]);

        expect($this->plugin->getCategoryLinkCounts())->toBe([5 => 3, 7 => 0]);
    });
});

describe('LynxJournal::getTagLinkCounts()', function (): void {

    it('returns an empty array when there are no rows', function (): void {
        $GLOBALS['wpdb'] = Mockery::mock('wpdb');
        $GLOBALS['wpdb']->term_taxonomy      = 'wp_term_taxonomy';
        $GLOBALS['wpdb']->term_relationships = 'wp_term_relationships';
        $GLOBALS['wpdb']->posts              = 'wp_posts';
        $GLOBALS['wpdb']->shouldReceive('get_results')->once()->andReturn(null);

        expect($this->plugin->getTagLinkCounts())->toBe([]);
    });

    it('returns counts keyed by term_id', function (): void {
        $GLOBALS['wpdb'] = Mockery::mock('wpdb');
        $GLOBALS['wpdb']->term_taxonomy      = 'wp_term_taxonomy';
        $GLOBALS['wpdb']->term_relationships = 'wp_term_relationships';
        $GLOBALS['wpdb']->posts              = 'wp_posts';
        $GLOBALS['wpdb']->shouldReceive('get_results')->once()->andReturn([
            ['term_id' => 5, 'cnt' => 3],
            ['term_id' => 7, 'cnt' => 0],
        ]);

        expect($this->plugin->getTagLinkCounts())->toBe([5 => 3, 7 => 0]);
    });
});
