<?php

declare(strict_types=1);

if (!defined("ABSPATH")) {
    exit;
}

use Brain\Monkey\Functions;

beforeEach(function (): void {
    Functions\when('get_post_meta')->justReturn('');
    Functions\when('get_the_terms')->justReturn(false);
    Functions\when('mysql2date')->returnArg(2);
    Functions\when('get_permalink')->justReturn('https://example.com/post/1');
    Functions\when('get_edit_post_link')->justReturn('https://example.com/wp-admin/edit?p=1');
    Functions\when('admin_url')->justReturn('https://example.com/wp-admin/admin.php?page=lynxjournal');
    Functions\when('esc_attr_e')->alias(function (string $t): void {
        echo htmlspecialchars($t);
    });
    Functions\when('esc_attr')->alias(fn($t): string => htmlspecialchars((string) $t, ENT_QUOTES));
    $this->plugin = Mockery::mock(LynxJournal::class)->makePartial();
});

describe('LynxJournal::renderRecentlyPublishedBox()', function (): void { // NOSONAR

    it('shows empty message when no links provided', function (): void {
        ob_start();
        $this->plugin->renderRecentlyPublishedBox([]);
        $html = ob_get_clean();

        expect($html)->toContain('No published links yet.');
        expect($html)->not->toContain('lynxjournal-recent-links');
    });

    it('shows the link list when links are provided', function (): void {
        $link = lynxjournal_make_post(1, 'Test Link');

        ob_start();
        $this->plugin->renderRecentlyPublishedBox([$link]);
        $html = ob_get_clean();

        expect($html)->toContain('lynxjournal-recent-links');
        expect($html)->toContain('Test Link');
    });

    it('shows no badge for a published link', function (): void {
        $link = lynxjournal_make_post(1, 'Published Link');
        Functions\when('get_post_meta')->alias(
            fn($id, $key, $single) => $key === '_lynxjournal_publish_status' ? 'published' : ''
        );

        ob_start();
        $this->plugin->renderRecentlyPublishedBox([$link]);
        $html = ob_get_clean();

        expect($html)->not->toContain('lynxjournal-status-published');
        expect($html)->not->toContain('lynxjournal-status-draft');
    });

    it('shows draft badge for a draft link', function (): void {
        $link = lynxjournal_make_post(1, 'Draft Link');
        Functions\when('get_post_meta')->alias(
            fn($id, $key, $single) => $key === '_lynxjournal_publish_status' ? 'draft' : ''
        );

        ob_start();
        $this->plugin->renderRecentlyPublishedBox([$link]);
        $html = ob_get_clean();

        expect($html)->toContain('lynxjournal-status-draft');
        expect($html)->not->toContain('lynxjournal-status-published');
    });

    it('shows no badge when status is neither published nor draft', function (): void {
        $link = lynxjournal_make_post(1, 'Pending Link');
        Functions\when('get_post_meta')->alias(
            fn($id, $key, $single) => $key === '_lynxjournal_publish_status' ? 'unpublished' : ''
        );

        ob_start();
        $this->plugin->renderRecentlyPublishedBox([$link]);
        $html = ob_get_clean();

        expect($html)->not->toContain('lynxjournal-status-published');
        expect($html)->not->toContain('lynxjournal-status-draft');
    });

    it('shows View Post link for a published link with published_post_id', function (): void {
        $link = lynxjournal_make_post(1, 'Published Link');
        Functions\when('get_post_meta')->alias(function ($id, $key, $single) {
            return match ($key) {
                '_lynxjournal_publish_status'    => 'published',
                '_lynxjournal_published_post_id' => 42,
                default                       => '',
            };
        });

        ob_start();
        $this->plugin->renderRecentlyPublishedBox([$link]);
        $html = ob_get_clean();

        expect($html)->toContain('View Post');
        expect($html)->toContain('lynxjournal-link-url');
    });

    it('shows View Draft link for a draft link with published_post_id', function (): void {
        $link = lynxjournal_make_post(1, 'Draft Link');
        Functions\when('get_post_meta')->alias(function ($id, $key, $single) {
            return match ($key) {
                '_lynxjournal_publish_status'    => 'draft',
                '_lynxjournal_published_post_id' => 42,
                default                       => '',
            };
        });

        ob_start();
        $this->plugin->renderRecentlyPublishedBox([$link]);
        $html = ob_get_clean();

        expect($html)->toContain('View Draft');
    });

    it('shows the category name when the link has a category', function (): void {
        $link = lynxjournal_make_post(1, 'Categorised Link');
        $term = new stdClass();
        $term->name = 'Technology';
        Functions\when('get_the_terms')->justReturn([$term]);

        ob_start();
        $this->plugin->renderRecentlyPublishedBox([$link]);
        $html = ob_get_clean();

        expect($html)->toContain('Technology');
    });

    it('shows the published date when the link has one', function (): void {
        $link = lynxjournal_make_post(1, 'Dated Link');
        Functions\when('get_post_meta')->alias(
            fn($id, $key, $single) => $key === '_lynxjournal_published_date' ? '2026-04-01 00:00:00' : ''
        );

        ob_start();
        $this->plugin->renderRecentlyPublishedBox([$link]);
        $html = ob_get_clean();

        expect($html)->toContain('2026-04-01 00:00:00');
    });

    it('renders multiple links', function (): void {
        $link1 = lynxjournal_make_post(1, 'First Link');
        $link2 = lynxjournal_make_post(2, 'Second Link');

        ob_start();
        $this->plugin->renderRecentlyPublishedBox([$link1, $link2]);
        $html = ob_get_clean();

        expect($html)->toContain('First Link');
        expect($html)->toContain('Second Link');
    });
});

describe('LynxJournal::getUnpublishedLinkIds()', function (): void { // NOSONAR

    it('queries pending links oldest-first, returning only IDs', function (): void {
        $captured_args = null;
        Functions\when('get_posts')->alias(function (array $args) use (&$captured_args) {
            $captured_args = $args;
            return [3, 1, 2];
        });

        $result = $this->plugin->getUnpublishedLinkIds();

        expect($result)->toBe([3, 1, 2]);
        expect($captured_args['post_status'])->toBe('lynxjournal_pending');
        expect($captured_args['fields'])->toBe('ids');
        expect($captured_args['order'])->toBe('ASC');
    });
});

describe('LynxJournal::dashboardWidgetContent()', function (): void { // NOSONAR

    it('renders stat totals and no recent-unpublished section when empty', function (): void {
        $this->plugin->shouldReceive('getPublishStatistics')->once()->andReturn([
            'total_links' => 5, 'published_links' => 2, 'unpublished_links' => 3,
        ]);
        Functions\when('number_format_i18n')->alias(fn($n) => (string) $n);
        Functions\when('get_posts')->justReturn([]);

        ob_start();
        $this->plugin->dashboardWidgetContent();
        $html = ob_get_clean();

        expect($html)->toContain('lynxjournal-widget-stats');
        expect($html)->not->toContain('lynxjournal-widget-recent');
    });

    it('renders the recent-unpublished list with a URL', function (): void {
        $link = lynxjournal_make_post(1, 'Pending Link');
        $this->plugin->shouldReceive('getPublishStatistics')->once()->andReturn([
            'total_links' => 1, 'published_links' => 0, 'unpublished_links' => 1,
        ]);
        Functions\when('number_format_i18n')->alias(fn($n) => (string) $n);
        Functions\when('get_posts')->justReturn([$link]);
        Functions\when('get_post_meta')->justReturn('https://example.com/x');

        ob_start();
        $this->plugin->dashboardWidgetContent();
        $html = ob_get_clean();

        expect($html)->toContain('lynxjournal-widget-recent');
        expect($html)->toContain('Pending Link');
        expect($html)->toContain('https://example.com/x');
    });
});

describe('LynxJournal::renderDashboardNotices()', function (): void { // NOSONAR

    it('renders nothing when both results are null', function (): void {
        ob_start();
        $this->plugin->renderDashboardNotices(null, null);
        $html = ob_get_clean();

        expect($html)->toBe('');
    });

    it('renders a success notice for a fully successful batch', function (): void {
        ob_start();
        $this->plugin->renderDashboardNotices(['success' => 3, 'failed' => 0, 'messages' => []], null);
        $html = ob_get_clean();

        expect($html)->toContain('notice-success');
        expect($html)->not->toContain('notice-error');
    });

    it('renders both success and error notices for a partially failed batch', function (): void {
        ob_start();
        $this->plugin->renderDashboardNotices(
            ['success' => 2, 'failed' => 1, 'messages' => ['Link X failed']],
            null
        );
        $html = ob_get_clean();

        expect($html)->toContain('notice-success');
        expect($html)->toContain('notice-error');
        expect($html)->toContain('Link X failed');
    });

    it('renders a success notice with a View Post link for a successful roundup', function (): void {
        ob_start();
        $this->plugin->renderDashboardNotices(null, [
            'success' => true, 'message' => 'Roundup created.', 'post_id' => 9,
        ]);
        $html = ob_get_clean();

        expect($html)->toContain('notice-success');
        expect($html)->toContain('Roundup created.');
        expect($html)->toContain('View Post');
    });

    it('renders an error notice for a failed roundup', function (): void {
        ob_start();
        $this->plugin->renderDashboardNotices(null, ['success' => false, 'message' => 'No links.']);
        $html = ob_get_clean();

        expect($html)->toContain('notice-error');
        expect($html)->toContain('No links.');
    });
});

describe('LynxJournal::unpublishedLinksSubtitle() and helpers', function (): void { // NOSONAR

    beforeEach(function (): void {
        $this->method = new \ReflectionMethod(LynxJournal::class, 'unpublishedLinksSubtitle');
    });

    it('returns an empty subtitle when there is no schedule option', function (): void {
        Functions\when('get_option')->justReturn(null);

        $result = $this->method->invoke($this->plugin);

        expect($result)->toBe(['text' => '', 'icon' => '']);
    });

    it('returns an empty subtitle when the schedule has no mode', function (): void {
        Functions\when('get_option')->justReturn(['trigger' => []]);

        $result = $this->method->invoke($this->plugin);

        expect($result)->toBe(['text' => '', 'icon' => '']);
    });

    it('returns an empty subtitle for a time-based mode with no next run scheduled', function (): void {
        Functions\when('get_option')->justReturn(['mode' => 'daily']);
        Functions\when('wp_next_scheduled')->justReturn(false);

        $result = $this->method->invoke($this->plugin);

        expect($result)->toBe(['text' => '', 'icon' => '']);
    });

    it('returns a formatted "next" subtitle for a time-based mode with a scheduled run', function (): void {
        Functions\when('get_option')->alias(fn($opt, $default = false) => $opt === 'lynxjournal_schedule' ? ['mode' => 'weekly'] : 'F j, Y');
        Functions\when('wp_next_scheduled')->justReturn(1700000000);
        Functions\when('wp_date')->justReturn('April 22, 2026, 3:00 pm');

        $result = $this->method->invoke($this->plugin);

        expect($result['text'])->toContain('April 22, 2026, 3:00 pm');
        expect($result['icon'])->toBe('dashicons-calendar-alt');
    });

    it('returns a count-based subtitle for count mode', function (): void {
        Functions\when('get_option')->justReturn(['mode' => 'count', 'trigger' => ['count' => 10]]);
        $this->plugin->shouldReceive('getPublishStatistics')->once()->andReturn(['unpublished_links' => 4]);
        Functions\when('_n')->returnArg(1);

        $result = $this->method->invoke($this->plugin);

        expect($result['text'])->toContain('6');
        expect($result['text'])->toContain('10');
    });

    it('returns an empty subtitle for an unrecognized mode', function (): void {
        Functions\when('get_option')->justReturn(['mode' => 'manual']);

        $result = $this->method->invoke($this->plugin);

        expect($result)->toBe(['text' => '', 'icon' => '']);
    });
});

describe('LynxJournal::renderUnpublishedLinksBox()', function (): void { // NOSONAR

    beforeEach(function (): void {
        Functions\when('get_option')->justReturn(null);
    });

    it('shows the empty message when there are no recent links', function (): void {
        ob_start();
        $this->plugin->renderUnpublishedLinksBox([]);
        $html = ob_get_clean();

        expect($html)->toContain('No unpublished links at the moment.');
    });

    it('renders the list and footer link when links are present', function (): void {
        $link = lynxjournal_make_post(1, 'Unpublished Link');
        Functions\when('get_the_time')->justReturn('12:00 pm');
        Functions\when('get_the_date')->justReturn('Apr 22, 2026');

        ob_start();
        $this->plugin->renderUnpublishedLinksBox([$link]);
        $html = ob_get_clean();

        expect($html)->toContain('Unpublished Link');
        expect($html)->toContain('View All Links');
    });

    it('renders a subtitle when one is available', function (): void {
        Functions\when('get_option')->alias(fn($opt, $default = false) => $opt === 'lynxjournal_schedule' ? ['mode' => 'daily'] : 'F j, Y');
        Functions\when('wp_next_scheduled')->justReturn(1700000000);
        Functions\when('wp_date')->justReturn('April 22, 2026');

        ob_start();
        $this->plugin->renderUnpublishedLinksBox([]);
        $html = ob_get_clean();

        expect($html)->toContain('lynxjournal-box-subtitle');
        expect($html)->toContain('dashicons-calendar-alt');
    });

    it('renders the link URL when the link has one', function (): void {
        $link = lynxjournal_make_post(1, 'Linked Item');
        Functions\when('get_post_meta')->justReturn('https://example.com/article');
        Functions\when('get_the_time')->justReturn('12:00 pm');
        Functions\when('get_the_date')->justReturn('Apr 22, 2026');
        Functions\when('wp_parse_url')->justReturn('example.com');

        ob_start();
        $this->plugin->renderUnpublishedLinksBox([$link]);
        $html = ob_get_clean();

        expect($html)->toContain('lynxjournal-link-url');
        expect($html)->toContain('example.com');
    });

    it('renders the category and formatted date/time for a link', function (): void {
        $link = lynxjournal_make_post(1, 'Full Link');
        $term = new stdClass();
        $term->name = 'News';
        Functions\when('get_the_terms')->justReturn([$term]);
        Functions\when('get_the_time')->justReturn('3:00 pm');
        Functions\when('get_the_date')->justReturn('Apr 22, 2026');

        ob_start();
        $this->plugin->renderUnpublishedLinksBox([$link]);
        $html = ob_get_clean();

        expect($html)->toContain('News');
        expect($html)->toContain('Apr 22, 2026');
        expect($html)->toContain('3:00 pm');
    });
});

describe('LynxJournal::renderPublishBox()', function (): void { // NOSONAR

    it('shows the empty message when there are no unpublished links', function (): void {
        ob_start();
        $this->plugin->renderPublishBox(0);
        $html = ob_get_clean();

        expect($html)->toContain('No pending links to publish.');
    });

    it('renders the roundup form when there are unpublished links', function (): void {
        Functions\when('wp_kses')->returnArg(1);
        Functions\when('_n')->returnArg(1);

        ob_start();
        $this->plugin->renderPublishBox(3);
        $html = ob_get_clean();

        expect($html)->toContain('roundup_title');
        expect($html)->toContain('lynxjournal_create_roundup');
    });
});

describe('LynxJournal::renderQuickAddBox()', function (): void { // NOSONAR

    it('renders without a category select when there are no categories', function (): void {
        $this->plugin->shouldReceive('getCachedCategories')->once()->andReturn([]);

        ob_start();
        $this->plugin->renderQuickAddBox(false);
        $html = ob_get_clean();

        expect($html)->not->toContain('quick_category');
        expect($html)->not->toContain('notice-success');
    });

    it('renders the category select and success notice', function (): void {
        $term = new stdClass();
        $term->term_id = 3;
        $term->name = 'Tech';
        $this->plugin->shouldReceive('getCachedCategories')->once()->andReturn([$term]);

        ob_start();
        $this->plugin->renderQuickAddBox(true);
        $html = ob_get_clean();

        expect($html)->toContain('quick_category');
        expect($html)->toContain('Tech');
        expect($html)->toContain('notice-success');
    });
});

describe('LynxJournal::renderScheduleStatusBar()', function (): void { // NOSONAR

    beforeEach(function (): void {
        $this->method = new \ReflectionMethod(LynxJournal::class, 'renderScheduleStatusBar');
    });

    it('renders nothing when there is no schedule option', function (): void {
        Functions\when('get_option')->justReturn(null);

        ob_start();
        $this->method->invoke($this->plugin);
        $html = ob_get_clean();

        expect($html)->toBe('');
    });

    it('shows "no schedule" text when no next run is scheduled', function (): void {
        Functions\when('get_option')->justReturn(['mode' => 'daily']);
        Functions\when('wp_next_scheduled')->justReturn(false);

        ob_start();
        $this->method->invoke($this->plugin);
        $html = ob_get_clean();

        expect($html)->toContain('No automatic schedule active.');
    });

    it('shows the next-run text when a run is scheduled', function (): void {
        Functions\when('get_option')->alias(fn($opt, $default = false) => $opt === 'lynxjournal_schedule' ? ['mode' => 'daily'] : 'F j, Y');
        Functions\when('wp_next_scheduled')->justReturn(1700000000);
        Functions\when('wp_date')->justReturn('April 22, 2026, 3:00 pm');

        ob_start();
        $this->method->invoke($this->plugin);
        $html = ob_get_clean();

        expect($html)->toContain('Next run:');
        expect($html)->toContain('April 22, 2026, 3:00 pm');
    });
});

describe('LynxJournal::dashboardPage()', function (): void { // NOSONAR

    beforeEach(function (): void {
        $_POST = [];
        Functions\when('number_format_i18n')->alias(fn($n) => (string) $n);
        Functions\when('_n')->returnArg(1);
        Functions\when('get_option')->justReturn(null);
    });

    afterEach(function (): void {
        $_POST = [];
    });

    it('renders the onboarding block when there are no links and no categories', function (): void {
        $this->plugin->shouldReceive('getPublishStatistics')->once()->andReturn([
            'total_links' => 0, 'published_links' => 0, 'unpublished_links' => 0,
        ]);
        $this->plugin->shouldReceive('getCachedCategories')->andReturn([]);
        Functions\when('get_posts')->justReturn([]);

        ob_start();
        $this->plugin->dashboardPage();
        $html = ob_get_clean();

        expect($html)->toContain('lynxjournal-onboarding');
        expect($html)->toContain('No categories defined yet.');
        expect($html)->not->toContain('lynxjournal-stats-grid');
    });

    it('renders the stats grid when links exist', function (): void {
        $term = new stdClass();
        $term->term_id = 1;
        $term->name = 'Tech';
        $this->plugin->shouldReceive('getPublishStatistics')->once()->andReturn([
            'total_links' => 4, 'published_links' => 1, 'unpublished_links' => 3,
        ]);
        $this->plugin->shouldReceive('getCachedCategories')->andReturn([$term]);
        Functions\when('get_posts')->justReturn([]);

        ob_start();
        $this->plugin->dashboardPage();
        $html = ob_get_clean();

        expect($html)->toContain('lynxjournal-stats-grid');
        expect($html)->not->toContain('lynxjournal-onboarding');
    });

    it('processes a batch-publish submission and shows the result notice', function (): void {
        $_POST = ['lynxjournal_batch_publish' => '1', 'lynxjournal_batch_nonce' => 'valid'];
        Functions\when('wp_verify_nonce')->justReturn(1);
        Functions\when('current_user_can')->justReturn(true);
        $this->plugin->shouldReceive('getUnpublishedLinkIds')->andReturn([1, 2]);
        $this->plugin->shouldReceive('batchPublishLinks')->once()->andReturn(['success' => 2, 'failed' => 0, 'messages' => []]);
        $this->plugin->shouldReceive('getPublishStatistics')->andReturn([
            'total_links' => 2, 'published_links' => 2, 'unpublished_links' => 0,
        ]);
        $this->plugin->shouldReceive('getCachedCategories')->andReturn([]);
        Functions\when('get_posts')->justReturn([]);

        ob_start();
        $this->plugin->dashboardPage();
        $html = ob_get_clean();

        expect($html)->toContain('notice-success');
        expect($html)->toContain('Successfully processed');
    });
});
