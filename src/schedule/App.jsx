import { useState, useEffect, useMemo, useCallback } from '@wordpress/element';
import apiFetch from '@wordpress/api-fetch';
import { Button, Notice, SelectControl } from '@wordpress/components';
import { __ } from '@wordpress/i18n';
import { buildRRule } from './lib/rrule';
import { SCHEDULE_MODES } from './lib/modes';
import { useNotifications } from './lib/notifications';
import ScheduleTypePicker from './components/ScheduleTypePicker';
import RecurrenceConfig from './components/RecurrenceConfig';
import TriggerCondition from './components/TriggerCondition';
import TimePicker from './components/TimePicker';
import NextSchedules from './components/NextSchedules';
import DiagnosticsPanel from './components/DiagnosticsPanel';
import NotificationsSection, { TabTitleWithBadge } from './components/NotificationsSection';

const DEFAULT_FORM = {
  mode: 'daily',
  recurrence: { interval: 1, weekdays: [], monthDays: [{ type: 'day', value: 1, nth: 1, weekday: 'MO' }], nthWeek: null },
  trigger: { count: 10, tag_id: null, days: 7 },
  times: [],
  notify: {
    enabled: false, email: '',
    discordEnabled: false, discordWebhookUrl: '',
    slackBotToken: '',
    slackChannelEnabled: false, slackChannelId: '',
    slackDmEnabled: false, slackUserId: '',
    telegramBotToken: '',
    telegramEnabled: false, telegramChatId: '',
    telegramDmEnabled: false, telegramDmChatId: '',
    mastodonEnabled: false, mastodonInstanceUrl: '', mastodonAccessToken: '', mastodonRecipient: '',
    bskyEnabled: false, bskyHandle: '', bskyAppPassword: '', bskyRecipient: '',
  },
  post_status: 'publish',
};

/**
 * Reads a collapsed/expanded boolean previously stored for a section, if any.
 *
 * @param {string} storageKey localStorage key to read.
 * @returns {boolean|null} The stored value, or null if unset/unavailable.
 */
function readStoredCollapsed(storageKey) {
  try {
    const stored = window.localStorage.getItem(storageKey);
    return stored === null ? null : stored === '1';
  } catch {
    return null;
  }
}

/**
 * A titled section box. Optionally collapsible, toggled by clicking the heading.
 *
 * @param {Object}   props
 * @param {string}   props.title             Section heading text.
 * @param {*}        props.children          Section body content.
 * @param {boolean}  [props.collapsible]     Whether the section can be collapsed.
 * @param {boolean}  [props.defaultCollapsed] Initial collapsed state, if collapsible and nothing is stored yet.
 * @param {string}   [props.storageKey]      localStorage key to remember the collapsed state across page loads.
 * @param {Function} [props.onToggle]        Called with the new collapsed state after each toggle.
 * @returns {JSX.Element}
 */
function Section({ title, children, collapsible, defaultCollapsed, storageKey, onToggle }) {
  const [collapsed, setCollapsed] = useState(() => {
    if (!collapsible) return false;
    const stored = storageKey ? readStoredCollapsed(storageKey) : null;
    return stored ?? Boolean(defaultCollapsed);
  });

  if (!collapsible) {
    return (
      <div className="lynxjournal-section">
        <h2 className="lynxjournal-section-heading">{title}</h2>
        <div className="lynxjournal-section-body">{children}</div>
      </div>
    );
  }

  return (
    <div className={`lynxjournal-section ${collapsed ? 'is-collapsed' : ''}`}>
      <h2 className="lynxjournal-section-heading">
        <button
          type="button"
          className="lynxjournal-section-toggle"
          aria-expanded={!collapsed}
          onClick={() => {
            const next = !collapsed;
            setCollapsed(next);
            if (storageKey) {
              try {
                window.localStorage.setItem(storageKey, next ? '1' : '0');
              } catch {
                // localStorage unavailable (private browsing, etc.) — collapsed state just won't persist.
              }
            }
            onToggle?.(next);
          }}
        >
          <span className={`lynxjournal-section-chevron ${collapsed ? 'is-collapsed' : ''}`} aria-hidden="true">▾</span>
          {title}
        </button>
      </h2>
      {!collapsed && <div className="lynxjournal-section-body">{children}</div>}
    </div>
  );
}

/**
 * Renders the dismissible "WP-Cron is disabled" warning, when applicable.
 *
 * @param {boolean}  cronDisabled - Whether diagnostics reported WP-Cron as disabled.
 * @param {boolean}  dismissed    - Whether the user has already dismissed this notice.
 * @param {Function} onDismiss    - Called when the notice is dismissed.
 * @returns {JSX.Element|null}
 */
function WpCronNotice({ cronDisabled, dismissed, onDismiss }) {
  if (!cronDisabled || dismissed) return null;
  return (
    <Notice
      status="warning"
      isDismissible
      onRemove={onDismiss}
      className="lynxjournal-wpcron-notice"
    >
      <strong>{__('WP-Cron is disabled.', 'lynx-journal')}</strong>
      {' '}
      {__('Scheduled runs will not fire automatically. Add a real server cron job or remove', 'lynx-journal')}
      {' '}<code>DISABLE_WP_CRON</code>{' '}
      {__('from', 'lynx-journal')}
      {' '}<code>wp-config.php</code>.
    </Notice>
  );
}

export default function App() {
  const [form, setForm]             = useState(DEFAULT_FORM);
  const [savedForm, setSavedForm]   = useState(null);
  const [saving, setSaving]         = useState(false);
  const [notice, setNotice]         = useState(null);
  // Initialised from diag.cron_notice_dismissed once diagnostics load.
  const [cronNoticeDismissed, setCronNoticeDismissed] = useState(false);

  // Diagnostics lifted here so App can show a WP-Cron warning and refresh after save.
  const [diag, setDiag]           = useState(null);
  const [diagLoading, setDiagLoading] = useState(true);

  const refreshDiag = useCallback(() => {
    setDiagLoading(true);
    apiFetch({ path: '/lynxjournal/v1/schedule/diagnostics' })
      .then(d => {
        setDiag(d);
        setCronNoticeDismissed(Boolean(d.cron_notice_dismissed));
        setDiagLoading(false);
      })
      .catch(() => setDiagLoading(false));
  }, []);

  useEffect(() => {
    apiFetch({ path: '/lynxjournal/v1/schedule' })
      .then(data => {
        const loaded = { ...DEFAULT_FORM, ...data };
        setForm(loaded);
        setSavedForm(loaded);
      })
      .catch(() => {});  // skipcq: JS-0057 - intentional no-op test stub
  }, []);

  useEffect(refreshDiag, [refreshDiag]);

  const isDirty = savedForm !== null && JSON.stringify(form) !== JSON.stringify(savedForm);
  // Once the real schedule config has loaded, jump the notification tabs to
  // whichever channel is already enabled (handled inside useNotifications).
  const configLoaded = savedForm !== null;

  const notifications = useNotifications(form, setForm, setSavedForm, configLoaded);

  useEffect(() => {
    if (!isDirty) return;
    const handler = e => { e.preventDefault(); e.returnValue = ''; };
    window.addEventListener('beforeunload', handler);
    return () => window.removeEventListener('beforeunload', handler);  // skipcq: JS-0045 - useEffect cleanup contract: undefined or a cleanup function, per React's API (not an inconsistency bug)
  }, [isDirty]);

  async function handleSave() {
    setSaving(true);
    setNotice(null);
    if (new Set(form.times).size !== form.times.length) {
      setNotice({ status: 'error', message: __('Execution times must be unique.', 'lynx-journal') });
      setSaving(false);
      return;
    }
    try {
      await apiFetch({ path: '/lynxjournal/v1/schedule', method: 'POST', data: form });
      setSavedForm(form);
      setNotice({ status: 'success', message: __('Schedule saved.', 'lynx-journal') });
      refreshDiag();
    } catch {
      setNotice({ status: 'error', message: __('Failed to save schedule.', 'lynx-journal') });
    } finally {
      setSaving(false);
    }
  }

  function handleModeChange(mode) {
    setForm(f => ({
      ...f,
      mode,
      recurrence: SCHEDULE_MODES.has(mode)
        ? { interval: 1, weekdays: [], monthDays: [{ type: 'day', value: 1, nth: 1, weekday: 'MO' }], nthWeek: null }
        : f.recurrence,
    }));
  }

  const isSchedule = SCHEDULE_MODES.has(form.mode);
  const isManual   = form.mode === 'manual';

  const config = useMemo(() => {
    if (isSchedule) {
      return { rrule: buildRRule({ type: form.mode, ...form.recurrence }), times: form.times, trigger: null };
    }
    if (isManual) {
      return { rrule: null, times: [], trigger: { type: 'manual' } };
    }
    return { rrule: null, times: form.times, trigger: { type: form.mode, ...form.trigger } };
  }, [form, isSchedule, isManual]);

  const section02Label = isSchedule ? __('Recurrence', 'lynx-journal') : __('Condition', 'lynx-journal');

  function renderConditionSection() {
    if (isSchedule) return (
      <RecurrenceConfig
        type={form.mode}
        value={form.recurrence}
        onChange={v => setForm(f => ({ ...f, recurrence: v }))}
      />
    );
    if (isManual) return (
      <p className="description">
        {__('No automatic trigger — posts must be triggered manually.', 'lynx-journal')}
      </p>
    );
    return (
      <TriggerCondition
        mode={form.mode}
        value={form.trigger}
        onChange={v => setForm(f => ({ ...f, trigger: v }))}
      />
    );
  }

  return (
    <div className="lynxjournal-schedule-wrap">
      <div className="lynxjournal-schedule-main">
        <WpCronNotice
          cronDisabled={diag?.wp_cron_disabled}
          dismissed={cronNoticeDismissed}
          onDismiss={() => {
            setCronNoticeDismissed(true);
            apiFetch({ path: '/lynxjournal/v1/schedule/dismiss-cron-notice', method: 'POST' });
          }}
        />

        {notice && (
          <Notice status={notice.status} onRemove={() => setNotice(null)} isDismissible>
            {notice.message}
          </Notice>
        )}

        <Section title={__('Mode', 'lynx-journal')}>
          <ScheduleTypePicker value={form.mode} onChange={handleModeChange} />
        </Section>

        <Section title={section02Label}>
          {renderConditionSection()}
        </Section>

        {!isManual && (
          <Section title={__('Execution Times', 'lynx-journal')}>
            <TimePicker
              times={form.times}
              onChange={v => setForm(f => ({ ...f, times: v }))}
            />
          </Section>
        )}

        <Section title={__('Post Status', 'lynx-journal')}>
          <SelectControl
            value={form.post_status ?? 'publish'}
            options={[
              { label: __('Publish', 'lynx-journal'), value: 'publish' },
              { label: __('Draft', 'lynx-journal'), value: 'draft' },
            ]}
            onChange={post_status => setForm(f => ({ ...f, post_status }))}
            __nextHasNoMarginBottom
          />
        </Section>

        <Section
          title={<TabTitleWithBadge label={__('Notifications', 'lynx-journal')} enabled={notifications.anyNotifyEnabled} />}
          collapsible
          defaultCollapsed
          storageKey="lynxjournal_notify_section_collapsed"
          onToggle={notifications.handleNotifySectionToggle}
        >
          <NotificationsSection
            form={form}
            setForm={setForm}
            configLoaded={configLoaded}
            {...notifications}
          />
        </Section>

        <div className="lynxjournal-schedule-actions">
          <Button variant="primary" onClick={handleSave} isBusy={saving} disabled={saving}>
            {__('Save Schedule', 'lynx-journal')}
          </Button>
        </div>
      </div>

      <div className="lynxjournal-schedule-sidebar">
        <NextSchedules config={config} form={form} />
        <DiagnosticsPanel data={diag} loading={diagLoading} onRefresh={refreshDiag} mode={form.mode} />
      </div>
    </div>
  );
}
