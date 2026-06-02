/**
 * Sidebar diagnostics panel showing last run, next run, WP-Cron status, and run history.
 */

import { useState } from '@wordpress/element';
import { Button } from '@wordpress/components';
import { __, sprintf } from '@wordpress/i18n';

/**
 * Formats a Unix timestamp as a locale-aware medium date + short time string.
 *
 * @param {number} ts - Unix timestamp (seconds).
 * @returns {string}
 */
function fmtTs(ts) {
  return new Date(ts * 1000).toLocaleString(undefined, {
    dateStyle: 'medium',
    timeStyle: 'short',
  });
}

const REASON_LABELS = {
  condition_not_met: __('Condition not met', 'linkdigest'),
  locked:            __('Run was locked', 'linkdigest'),
};

/**
 * Returns a human-readable label for a run skip reason, falling back to the raw value.
 *
 * @param {string} reason - Raw reason string from the run record.
 * @returns {string}
 */
function formatReason(reason) {
  return REASON_LABELS[reason] ?? reason;
}

/**
 * Coloured status badge for a run record.
 *
 * @param {'success'|string} status - Run status value.
 * @returns {JSX.Element}
 */
function RunBadge({ status }) {
  const cls = status === 'success'
    ? 'linkdigest-diag-badge linkdigest-diag-badge--success'
    : 'linkdigest-diag-badge linkdigest-diag-badge--neutral';
  return <span className={cls}>{status}</span>;
}

/**
 * Inline link to the published digest post, with a link-count label.
 * Renders nothing when linkCount is falsy.
 *
 * @param {number|null} postId    - WordPress post ID, or null if not yet saved.
 * @param {number}      linkCount - Number of links in the digest.
 * @returns {JSX.Element|null}
 */
function PostLink({ postId, linkCount }) {
  if (!linkCount) return null;
  /* translators: %d: number of links in the published digest */
  const label = sprintf(__('%d links', 'linkdigest'), linkCount);
  return (
    <span className="linkdigest-diag-meta">
      {' · '}
      {postId
        ? <a href={`/wp-admin/post.php?post=${postId}&action=edit`} target="_blank" rel="noreferrer">{label}</a>
        : label}
    </span>
  );
}

/**
 * Sidebar panel showing schedule diagnostics: next/last run, WP-Cron status, and run history.
 *
 * @param {object|null} data      - Diagnostics data from the REST API.
 * @param {boolean}     loading   - True while the data is being fetched.
 * @param {Function}    onRefresh - Callback to re-fetch diagnostics data.
 * @param {string}      mode      - Active schedule mode (affects which rows are shown).
 * @returns {JSX.Element}
 */
export default function DiagnosticsPanel({ data, loading, onRefresh, mode }) {
  const [showHistory, setShowHistory] = useState(false);

  const lastRun = data?.last_run;
  const history = data?.run_history ?? [];

  return (
    <div className="postbox linkdigest-diagnostics">
      <div className="linkdigest-next-heading">
        {__('Diagnostics', 'linkdigest')}
      </div>
      <div className="inside linkdigest-next-schedules-inside">
        {loading && <p className="description">{__('Loading…', 'linkdigest')}</p>}

        {!loading && data && (
          <>
            <dl className="linkdigest-diag-list">
              {mode !== 'count' && (
                <div className="linkdigest-diag-row">
                  <dt>{__('Next run', 'linkdigest')}</dt>
                  <dd>
                    {data.next_scheduled
                      ? fmtTs(data.next_scheduled)
                      : (
                        <>
                          <em>{__('Not scheduled', 'linkdigest')}</em>
                          {data.wp_cron_disabled && (
                            <span className="linkdigest-diag-reason">
                              {' — '}{__('WP-Cron disabled', 'linkdigest')}
                            </span>
                          )}
                        </>
                      )}
                  </dd>
                </div>
              )}

              {mode === 'count' && data.links_until_post !== undefined && (
                <div className="linkdigest-diag-row">
                  <dt>{__('Next run', 'linkdigest')}</dt>
                  <dd>
                    {data.links_until_post > 0
                      /* translators: %d: number of links still needed before the next post */
                      ? sprintf(__('%d links until post', 'linkdigest'), data.links_until_post)
                      : <em>{__('Ready to post', 'linkdigest')}</em>}
                  </dd>
                </div>
              )}

              <div className="linkdigest-diag-row">
                <dt>{__('Last run', 'linkdigest')}</dt>
                <dd>
                  {lastRun ? (
                    <>
                      <RunBadge status={lastRun.status} />
                      {' '}
                      {fmtTs(lastRun.ts)}
                      <PostLink postId={lastRun.post_id} linkCount={lastRun.link_count} />
                      {lastRun.reason && (
                        <span className="linkdigest-run-reason">{formatReason(lastRun.reason)}</span>
                      )}
                    </>
                  ) : (
                    <em>{__('No runs yet', 'linkdigest')}</em>
                  )}
                </dd>
              </div>

              <div className="linkdigest-diag-row">
                <dt>{__('WP-Cron', 'linkdigest')}</dt>
                <dd>
                  {data.wp_cron_disabled
                    ? <span className="linkdigest-diag-badge linkdigest-diag-badge--warn">{__('Disabled', 'linkdigest')}</span>
                    : <span className="linkdigest-diag-badge linkdigest-diag-badge--success">{__('Active', 'linkdigest')}</span>}
                </dd>
              </div>
            </dl>

            {history.length > 0 && (
              <>
                <button
                  className="linkdigest-history-toggle"
                  onClick={() => setShowHistory(h => !h)}
                >
                  {showHistory
                    ? __('Hide history', 'linkdigest')
                    /* translators: %d: number of stored run records */
                    : sprintf(__('History (%d)', 'linkdigest'), history.length)}
                </button>
                {showHistory && (
                  <ol className="linkdigest-history-list">
                    {history.map((run, i) => (
                      <li key={i} className="linkdigest-history-row">
                        <div className="linkdigest-history-row-main">
                          <RunBadge status={run.status} />
                          <PostLink postId={run.post_id} linkCount={run.link_count} />
                        </div>
                        <div className="linkdigest-history-date">{fmtTs(run.ts)}</div>
                        {run.reason && (
                          <div className="linkdigest-run-reason">{formatReason(run.reason)}</div>
                        )}
                      </li>
                    ))}
                  </ol>
                )}
              </>
            )}
          </>
        )}

        {!loading && (
          <Button
            variant="link"
            size="compact"
            onClick={onRefresh}
            className="linkdigest-diag-refresh"
          >
            {__('Refresh', 'linkdigest')}
          </Button>
        )}
      </div>
    </div>
  );
}
