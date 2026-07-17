import { useState } from '@wordpress/element';
import { Button } from '@wordpress/components';
import { __, sprintf } from '@wordpress/i18n';

const SITE_TIMEZONE = globalThis.lynxjournalSchedule?.timezone || undefined;

function fmtTs(ts) {
  return new Date(ts * 1000).toLocaleString(undefined, {
    dateStyle: 'medium',
    timeStyle: 'short',
    timeZone: SITE_TIMEZONE,
  });
}

const DIAG_HISTORY_STATE_KEY = 'lynxjournalScheduleDiagHistoryOpen';

/**
 * Reads the persisted history-panel open/closed state, tolerating
 * environments where localStorage access throws (sandboxed iframe, blocked
 * storage, some private-browsing modes) instead of crashing on mount.
 *
 * @return {boolean}
 */
function readHistoryOpenState() {
  try {
    return localStorage.getItem(DIAG_HISTORY_STATE_KEY) === 'true';
  } catch {
    return false;
  }
}

const REASON_LABELS = {
  condition_not_met: __('Condition not met', 'lynx-journal'),
  locked:            __('Run was locked', 'lynx-journal'),
};

function formatReason(reason) {
  return REASON_LABELS[reason] ?? reason;
}

const STATUS_LABELS = {
  success: __('Success', 'lynx-journal'),
  skipped: __('Skipped', 'lynx-journal'),
  error:   __('Error', 'lynx-journal'),
};

/**
 * Colored status badge for a single scheduler run.
 *
 * @param {Object} props
 * @param {string} props.status Raw run status (e.g. 'success', 'skipped', 'error').
 * @returns {JSX.Element}
 */
function RunBadge({ status }) {
  const cls = status === 'success'
    ? 'lynxjournal-diag-badge lynxjournal-diag-badge--success'
    : 'lynxjournal-diag-badge lynxjournal-diag-badge--neutral';
  return <span className={cls}>{STATUS_LABELS[status] ?? status}</span>;
}

function PostLink({ postId, linkCount }) {
  if (!linkCount) return null;
  /* translators: %d: number of links in the published roundup */
  const label = sprintf(__('%d links', 'lynx-journal'), linkCount);
  return (
    <span className="lynxjournal-diag-meta">
      {' · '}
      {postId
        ? <a href={`/wp-admin/post.php?post=${postId}&action=edit`} target="_blank" rel="noreferrer">{label}</a>
        : label}
    </span>
  );
}

/**
 * Renders the "Next run" row: a scheduled timestamp for time-based modes,
 * or a "links until post" countdown for count-triggered mode.
 *
 * @param {object} data - Diagnostics payload from the schedule/diagnostics endpoint.
 * @param {string} mode - Current schedule mode.
 * @returns {JSX.Element|null}
 */
function NextRunRow({ data, mode }) {
  if (mode === 'count') {
    if (data.links_until_post === undefined) return null;
    return (
      <div className="lynxjournal-diag-row">
        <dt>{__('Next run', 'lynx-journal')}</dt>
        <dd>
          {data.links_until_post > 0
            /* translators: %d: number of links still needed before the next post */
            ? sprintf(__('%d links until post', 'lynx-journal'), data.links_until_post)
            : <em>{__('Ready to post', 'lynx-journal')}</em>}
        </dd>
      </div>
    );
  }

  return (
    <div className="lynxjournal-diag-row">
      <dt>{__('Next run', 'lynx-journal')}</dt>
      <dd>
        {data.next_scheduled
          ? fmtTs(data.next_scheduled)
          : (
            <>
              <em>{__('Not scheduled', 'lynx-journal')}</em>
              {data.wp_cron_disabled && (
                <span className="lynxjournal-diag-reason">
                  {' — '}{__('WP-Cron disabled', 'lynx-journal')}
                </span>
              )}
            </>
          )}
      </dd>
    </div>
  );
}

/**
 * Renders the "Last run" row, showing the most recent run's status, timestamp,
 * published-post link, and skip reason (if any).
 *
 * @param {object|undefined} lastRun - Most recent run record, or undefined if none yet.
 * @returns {JSX.Element}
 */
function LastRunRow({ lastRun }) {
  return (
    <div className="lynxjournal-diag-row">
      <dt>{__('Last run', 'lynx-journal')}</dt>
      <dd>
        {lastRun ? (
          <>
            <RunBadge status={lastRun.status} />
            {' '}
            {fmtTs(lastRun.ts)}
            <PostLink postId={lastRun.post_id} linkCount={lastRun.link_count} />
            {lastRun.reason && (
              <span className="lynxjournal-run-reason">{formatReason(lastRun.reason)}</span>
            )}
          </>
        ) : (
          <em>{__('No runs yet', 'lynx-journal')}</em>
        )}
      </dd>
    </div>
  );
}

/**
 * Renders the collapsible run-history list.
 *
 * @param {object[]} history     - Past run records, most recent first.
 * @param {boolean}  open        - Whether the <details> element is expanded.
 * @param {Function} onToggle    - Native <details> toggle event handler.
 * @returns {JSX.Element|null}
 */
function HistoryList({ history, open, onToggle }) {
  if (history.length === 0) return null;
  return (
    <details open={open} onToggle={onToggle}>
      <summary className="lynxjournal-history-toggle">
        {/* translators: %d: number of stored run records */}
        {sprintf(__('History (%d)', 'lynx-journal'), history.length)}
      </summary>
      <ol className="lynxjournal-history-list">
        {history.map((run) => (
          <li key={run.ts} className="lynxjournal-history-row">
            <div className="lynxjournal-history-row-main">
              <RunBadge status={run.status} />
              <PostLink postId={run.post_id} linkCount={run.link_count} />
            </div>
            <div className="lynxjournal-history-date">{fmtTs(run.ts)}</div>
            {run.reason && (
              <div className="lynxjournal-run-reason">{formatReason(run.reason)}</div>
            )}
          </li>
        ))}
      </ol>
    </details>
  );
}

export default function DiagnosticsPanel({ data, loading, onRefresh, mode }) {
  const [showHistory, setShowHistory] = useState(readHistoryOpenState);

  /** @listens toggle Persists the native <details> open/closed state. */
  function handleHistoryToggle(event) {
    const open = event.target.open;
    setShowHistory(open);
    try {
      localStorage.setItem(DIAG_HISTORY_STATE_KEY, String(open));
    } catch {
      // Ignore quota/availability errors — persistence is a non-critical enhancement.
    }
  }

  const lastRun = data?.last_run;
  const history = data?.run_history ?? [];

  return (
    <div className="postbox lynxjournal-diagnostics">
      <div className="lynxjournal-next-heading">
        {__('Diagnostics', 'lynx-journal')}
      </div>
      <div className="inside lynxjournal-next-schedules-inside">
        {loading && <p className="description">{__('Loading…', 'lynx-journal')}</p>}

        {!loading && data && (
          <>
            <dl className="lynxjournal-diag-list">
              <NextRunRow data={data} mode={mode} />
              <LastRunRow lastRun={lastRun} />

              <div className="lynxjournal-diag-row">
                <dt>{__('WP-Cron', 'lynx-journal')}</dt>
                <dd>
                  {data.wp_cron_disabled
                    ? <span className="lynxjournal-diag-badge lynxjournal-diag-badge--warn">{__('Disabled', 'lynx-journal')}</span>
                    : <span className="lynxjournal-diag-badge lynxjournal-diag-badge--success">{__('Active', 'lynx-journal')}</span>}
                </dd>
              </div>
            </dl>

            <HistoryList history={history} open={showHistory} onToggle={handleHistoryToggle} />
          </>
        )}

        {!loading && (
          <Button
            variant="link"
            size="compact"
            onClick={onRefresh}
            className="lynxjournal-diag-refresh"
          >
            {__('Refresh', 'lynx-journal')}
          </Button>
        )}
      </div>
    </div>
  );
}
