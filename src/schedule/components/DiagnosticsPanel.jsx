import { useState } from '@wordpress/element';
import { Button } from '@wordpress/components';
import { __, sprintf } from '@wordpress/i18n';

function fmtTs(ts) {
  return new Date(ts * 1000).toLocaleString(undefined, {
    dateStyle: 'medium',
    timeStyle: 'short',
  });
}

const REASON_LABELS = {
  condition_not_met: __('Condition not met', 'lynxjournal'),
  locked:            __('Run was locked', 'lynxjournal'),
};

function formatReason(reason) {
  return REASON_LABELS[reason] ?? reason;
}

function RunBadge({ status }) {
  const cls = status === 'success'
    ? 'lynxjournal-diag-badge lynxjournal-diag-badge--success'
    : 'lynxjournal-diag-badge lynxjournal-diag-badge--neutral';
  return <span className={cls}>{status}</span>;
}

function PostLink({ postId, linkCount }) {
  if (!linkCount) return null;
  /* translators: %d: number of links in the published roundup */
  const label = sprintf(__('%d links', 'lynxjournal'), linkCount);
  return (
    <span className="lynxjournal-diag-meta">
      {' · '}
      {postId
        ? <a href={`/wp-admin/post.php?post=${postId}&action=edit`} target="_blank" rel="noreferrer">{label}</a>
        : label}
    </span>
  );
}

export default function DiagnosticsPanel({ data, loading, onRefresh, mode }) {
  const [showHistory, setShowHistory] = useState(false);

  const lastRun = data?.last_run;
  const history = data?.run_history ?? [];

  return (
    <div className="postbox lynxjournal-diagnostics">
      <div className="lynxjournal-next-heading">
        {__('Diagnostics', 'lynxjournal')}
      </div>
      <div className="inside lynxjournal-next-schedules-inside">
        {loading && <p className="description">{__('Loading…', 'lynxjournal')}</p>}

        {!loading && data && (
          <>
            <dl className="lynxjournal-diag-list">
              {mode !== 'count' && (
                <div className="lynxjournal-diag-row">
                  <dt>{__('Next run', 'lynxjournal')}</dt>
                  <dd>
                    {data.next_scheduled
                      ? fmtTs(data.next_scheduled)
                      : (
                        <>
                          <em>{__('Not scheduled', 'lynxjournal')}</em>
                          {data.wp_cron_disabled && (
                            <span className="lynxjournal-diag-reason">
                              {' — '}{__('WP-Cron disabled', 'lynxjournal')}
                            </span>
                          )}
                        </>
                      )}
                  </dd>
                </div>
              )}

              {mode === 'count' && data.links_until_post !== undefined && (
                <div className="lynxjournal-diag-row">
                  <dt>{__('Next run', 'lynxjournal')}</dt>
                  <dd>
                    {data.links_until_post > 0
                      /* translators: %d: number of links still needed before the next post */
                      ? sprintf(__('%d links until post', 'lynxjournal'), data.links_until_post)
                      : <em>{__('Ready to post', 'lynxjournal')}</em>}
                  </dd>
                </div>
              )}

              <div className="lynxjournal-diag-row">
                <dt>{__('Last run', 'lynxjournal')}</dt>
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
                    <em>{__('No runs yet', 'lynxjournal')}</em>
                  )}
                </dd>
              </div>

              <div className="lynxjournal-diag-row">
                <dt>{__('WP-Cron', 'lynxjournal')}</dt>
                <dd>
                  {data.wp_cron_disabled
                    ? <span className="lynxjournal-diag-badge lynxjournal-diag-badge--warn">{__('Disabled', 'lynxjournal')}</span>
                    : <span className="lynxjournal-diag-badge lynxjournal-diag-badge--success">{__('Active', 'lynxjournal')}</span>}
                </dd>
              </div>
            </dl>

            {history.length > 0 && (
              <>
                <button
                  className="lynxjournal-history-toggle"
                  onClick={() => setShowHistory(h => !h)}
                >
                  {showHistory
                    ? __('Hide history', 'lynxjournal')
                    /* translators: %d: number of stored run records */
                    : sprintf(__('History (%d)', 'lynxjournal'), history.length)}
                </button>
                {showHistory && (
                  <ol className="lynxjournal-history-list">
                    {history.map((run, i) => (
                      <li key={i} className="lynxjournal-history-row">
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
            className="lynxjournal-diag-refresh"
          >
            {__('Refresh', 'lynxjournal')}
          </Button>
        )}
      </div>
    </div>
  );
}
