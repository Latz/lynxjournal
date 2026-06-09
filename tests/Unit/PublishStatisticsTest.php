<?php

declare(strict_types=1);

if (!defined('ABSPATH')) {
    exit;
}

use Brain\Monkey\Functions;

beforeEach(function (): void {
    Functions\when('__')->returnArg();
    $this->plugin = Mockery::mock(LynxJournal::class)->makePartial();
});

describe('LynxJournal::getPublishStatistics()', function (): void {

    // Note: getPublishStatistics() uses a PHP static variable as an in-process cache.
    // Static variables persist across tests in the same process, so only the first
    // test to call the method can exercise the "cache miss" path reliably.
    // The test below covers the DB path: transient absent → wp_count_posts → totals computed.

    it('computes totals from wp_count_posts, stores transient, and returns the result', function (): void {
        Functions\when('get_transient')->justReturn(false);

        $counts                      = new stdClass();
        $counts->lynxjournal_pub     = 5;
        $counts->lynxjournal_draft   = 2;
        $counts->lynxjournal_pending = 8;
        Functions\when('wp_count_posts')->justReturn($counts);

        $storedKey   = null;
        $storedValue = null;
        Functions\when('set_transient')->alias(function (string $key, mixed $value) use (&$storedKey, &$storedValue): bool {
            $storedKey   = $key;
            $storedValue = $value;
            return true;
        });

        $result = $this->plugin->getPublishStatistics();

        expect($result['published_links'])->toBe(5);
        expect($result['draft_links'])->toBe(2);
        expect($result['unpublished_links'])->toBe(8);
        expect($result['total_links'])->toBe(15);
        expect($storedKey)->toBe('lynxjournal_publish_stats');
        expect($storedValue)->toBe($result);
    });
});
