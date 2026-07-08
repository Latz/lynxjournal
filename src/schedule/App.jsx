import { useState, useEffect, useMemo, useCallback, useRef } from '@wordpress/element';
import apiFetch from '@wordpress/api-fetch';
import { Button, Notice, CheckboxControl, TextControl, SelectControl, TabPanel } from '@wordpress/components';
import { __ } from '@wordpress/i18n';
import { buildRRule } from './lib/rrule';
import { SCHEDULE_MODES } from './lib/modes';
import ScheduleTypePicker from './components/ScheduleTypePicker';
import RecurrenceConfig from './components/RecurrenceConfig';
import TriggerCondition from './components/TriggerCondition';
import TimePicker from './components/TimePicker';
import NextSchedules from './components/NextSchedules';
import DiagnosticsPanel from './components/DiagnosticsPanel';

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
    telegramEnabled: false, telegramBotToken: '', telegramChatId: '',
    mastodonEnabled: false, mastodonInstanceUrl: '', mastodonAccessToken: '', mastodonRecipient: '',
  },
  post_status: 'publish',
};

/**
 * A titled section box. Optionally collapsible, toggled by clicking the heading.
 *
 * @param {Object}   props
 * @param {string}   props.title             Section heading text.
 * @param {*}        props.children          Section body content.
 * @param {boolean}  [props.collapsible]     Whether the section can be collapsed.
 * @param {boolean}  [props.defaultCollapsed] Initial collapsed state, if collapsible.
 * @param {Function} [props.onToggle]        Called with the new collapsed state after each toggle.
 * @returns {JSX.Element}
 */
function Section({ title, children, collapsible, defaultCollapsed, onToggle }) {
  const [collapsed, setCollapsed] = useState(!!collapsible && !!defaultCollapsed);

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
 * Tab title for a notification channel, with an inline On/Off status badge.
 *
 * @param {Object}  props
 * @param {string}  props.label       Channel display name.
 * @param {boolean} props.enabled     Whether the channel is currently on.
 * @param {boolean} [props.incomplete] Whether the channel is on but missing required fields.
 * @returns {JSX.Element}
 */
function TabTitleWithBadge({ label, enabled, incomplete }) {
  const badgeClass = !enabled ? 'is-off' : incomplete ? 'is-incomplete' : 'is-on';
  return (
    <>
      {label}
      <span className={`lynxjournal-notify-badge ${badgeClass}`}>
        {enabled ? __('On', 'lynx-journal') : __('Off', 'lynx-journal')}
      </span>
    </>
  );
}

/**
 * "Send test notification" + "Save" buttons and their result notices for
 * one notify channel. Save persists just this channel's fields, independent
 * of any other unsaved changes elsewhere on the page.
 *
 * @param {Object}   props
 * @param {boolean}  props.canTest    Whether the channel's required fields are filled in and enabled.
 * @param {Object}   [props.testState] This channel's { testing, notice } state, if any.
 * @param {Function} props.onTest     Called when the Test button is clicked.
 * @param {Object}   [props.saveState] This channel's { saving, notice } state, if any.
 * @param {Function} props.onSave     Called when the Save button is clicked.
 * @returns {JSX.Element}
 */
function ChannelActions({ canTest, testState, onTest, saveState, onSave }) {
  return (
    <div className="lynxjournal-channel-actions">
      <div className="lynxjournal-channel-actions-buttons">
        <Button variant="secondary" onClick={onTest} isBusy={testState?.testing} disabled={testState?.testing || !canTest}>
          {__('Send test notification', 'lynx-journal')}
        </Button>
        <Button variant="primary" onClick={onSave} isBusy={saveState?.saving} disabled={saveState?.saving}>
          {__('Save', 'lynx-journal')}
        </Button>
      </div>
      {testState?.notice && (
        <Notice status={testState.notice.status} isDismissible={false}>
          {testState.notice.message}
        </Notice>
      )}
      {saveState?.notice && (
        <Notice status={saveState.notice.status} isDismissible={false}>
          {saveState.notice.message}
        </Notice>
      )}
    </div>
  );
}

export default function App() {
  const [form, setForm]             = useState(DEFAULT_FORM);
  const [savedForm, setSavedForm]   = useState(null);
  const [saving, setSaving]         = useState(false);
  const [notice, setNotice]         = useState(null);
  // Keyed by channel ('email' | 'discord' | 'slack_channel' | 'slack_dm' | 'telegram' | 'mastodon').
  const [testState, setTestState]   = useState({});
  // Keyed the same way as testState, but for the per-channel Save button.
  const [channelSaveState, setChannelSaveState] = useState({});
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
        setCronNoticeDismissed(!!d.cron_notice_dismissed);
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
      .catch(() => {});
  }, []);

  useEffect(refreshDiag, [refreshDiag]);

  const isDirty = savedForm !== null && JSON.stringify(form) !== JSON.stringify(savedForm);
  // Once the real schedule config has loaded, jump the notification tabs to
  // whichever channel is already enabled (see the configLoaded effect below).
  const configLoaded = savedForm !== null;

  const initialNotifyTab = useMemo(() => {
    if (form.notify?.discordEnabled) return 'discord';
    if (form.notify?.slackChannelEnabled || form.notify?.slackDmEnabled) return 'slack';
    if (form.notify?.telegramEnabled) return 'telegram';
    if (form.notify?.mastodonEnabled) return 'mastodon';
    return 'email';
    // eslint-disable-next-line react-hooks/exhaustive-deps -- only recompute once config finishes loading, like the effect below
  }, [configLoaded]);

  const [activeNotifyTab, setActiveNotifyTab] = useState(initialNotifyTab);

  useEffect(() => {
    if (configLoaded) setActiveNotifyTab(initialNotifyTab);
    // eslint-disable-next-line react-hooks/exhaustive-deps -- only jump once, when config finishes loading
  }, [configLoaded]);

  useEffect(() => {
    if (!isDirty) return;
    const handler = e => { e.preventDefault(); e.returnValue = ''; };
    window.addEventListener('beforeunload', handler);
    return () => window.removeEventListener('beforeunload', handler);
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

  /**
   * Send a one-off test notification for a single channel using the
   * currently-entered (possibly unsaved) notify settings.
   *
   * @param {string} channel One of email|discord|slack_channel|slack_dm|telegram|mastodon.
   * @returns {Promise<void>}
   */
  async function handleTest(channel) {
    setTestState(s => ({ ...s, [channel]: { testing: true, notice: null } }));
    try {
      await apiFetch({ path: '/lynxjournal/v1/schedule/test-notification', method: 'POST', data: { channel, notify: form.notify } });
      setTestState(s => ({ ...s, [channel]: { testing: false, notice: { status: 'success', message: __('Test notification sent.', 'lynx-journal') } } }));
    } catch (err) {
      setTestState(s => ({ ...s, [channel]: { testing: false, notice: { status: 'error', message: err?.message || __('Failed to send test notification.', 'lynx-journal') } } }));
    }
  }

  /**
   * Persist just one notification channel's current field values,
   * independent of any other unsaved changes elsewhere on the page.
   *
   * @param {string} channel One of email|discord|slack_channel|slack_dm|telegram|mastodon.
   * @returns {Promise<void>}
   */
  async function handleSaveChannel(channel) {
    setChannelSaveState(s => ({ ...s, [channel]: { saving: true, notice: null } }));
    try {
      const res = await apiFetch({ path: '/lynxjournal/v1/schedule/save-notification', method: 'POST', data: { channel, notify: form.notify } });
      setForm(f => ({ ...f, notify: { ...f.notify, ...res.notify } }));
      setSavedForm(f => f && ({ ...f, notify: { ...f.notify, ...res.notify } }));
      setChannelSaveState(s => ({ ...s, [channel]: { saving: false, notice: { status: 'success', message: __('Saved.', 'lynx-journal') } } }));
    } catch (err) {
      setChannelSaveState(s => ({ ...s, [channel]: { saving: false, notice: { status: 'error', message: err?.message || __('Failed to save.', 'lynx-journal') } } }));
    }
  }

  const tabsWrapRef = useRef(null);
  const [tabsOverflow, setTabsOverflow] = useState(false);

  /**
   * Scrolls the notification tab bar left/right by a fixed amount.
   *
   * @param {number} direction -1 to scroll left, 1 to scroll right.
   * @returns {void}
   */
  function scrollNotifyTabs(direction) {
    const el = tabsWrapRef.current?.querySelector('.components-tab-panel__tabs');
    el?.scrollBy({ left: direction * 150, behavior: 'smooth' });
  }

  /**
   * Measures whether the notification tab bar currently overflows its
   * container, so the scroll buttons only render when actually needed.
   *
   * @returns {void}
   */
  const measureTabsOverflow = useCallback(() => {
    const el = tabsWrapRef.current?.querySelector('.components-tab-panel__tabs');
    if (el) setTabsOverflow(el.scrollWidth > el.clientWidth + 1);
  }, []);

  useEffect(() => {
    measureTabsOverflow();
    window.addEventListener('resize', measureTabsOverflow);
    return () => window.removeEventListener('resize', measureTabsOverflow);
  }, [configLoaded, measureTabsOverflow]);

  /**
   * Re-measures tab bar overflow after the Notifications section is
   * expanded, since its content (and the tab bar) isn't in the DOM at
   * all while collapsed.
   *
   * @param {boolean} collapsed The section's new collapsed state.
   * @returns {void}
   */
  function handleNotifySectionToggle(collapsed) {
    if (!collapsed) requestAnimationFrame(measureTabsOverflow);
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

  // Whether each notify target has its required fields filled in (used for both
  // the tab badges' "on but incomplete" yellow state and the test-button gating).
  const discordComplete = !!form.notify?.discordEnabled && !!form.notify?.discordWebhookUrl;
  const slackChannelComplete = !!form.notify?.slackChannelEnabled && !!form.notify?.slackBotToken && !!form.notify?.slackChannelId;
  const slackDmComplete = !!form.notify?.slackDmEnabled && !!form.notify?.slackBotToken && !!form.notify?.slackUserId;
  const slackEnabled = !!form.notify?.slackChannelEnabled || !!form.notify?.slackDmEnabled;
  const slackIncomplete = (!!form.notify?.slackChannelEnabled && !slackChannelComplete) || (!!form.notify?.slackDmEnabled && !slackDmComplete);
  const telegramComplete = !!form.notify?.telegramEnabled && !!form.notify?.telegramBotToken && !!form.notify?.telegramChatId;
  const mastodonComplete = !!form.notify?.mastodonEnabled && !!form.notify?.mastodonInstanceUrl && !!form.notify?.mastodonAccessToken && !!form.notify?.mastodonRecipient;
  const anyNotifyEnabled = !!form.notify?.enabled || !!form.notify?.discordEnabled || slackEnabled || !!form.notify?.telegramEnabled || !!form.notify?.mastodonEnabled;

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
        {diag?.wp_cron_disabled && !cronNoticeDismissed && (
          <Notice
            status="warning"
            isDismissible
            onRemove={() => {
              setCronNoticeDismissed(true);
              apiFetch({ path: '/lynxjournal/v1/schedule/dismiss-cron-notice', method: 'POST' });
            }}
            className="lynxjournal-wpcron-notice"
          >
            <strong>{__('WP-Cron is disabled.', 'lynx-journal')}</strong>
            {' '}
            {__('Scheduled runs will not fire automatically. Add a real server cron job or remove', 'lynx-journal')}
            {' '}<code>DISABLE_WP_CRON</code>{' '}
            {__('from', 'lynx-journal')}
            {' '}<code>wp-config.php</code>.
          </Notice>
        )}

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

        <Section title={<TabTitleWithBadge label={__('Notifications', 'lynx-journal')} enabled={anyNotifyEnabled} />} collapsible defaultCollapsed onToggle={handleNotifySectionToggle}>
          <div className="lynxjournal-notify-tabs-row" ref={tabsWrapRef}>
            {tabsOverflow && (
              <Button
                className="lynxjournal-notify-tabs-scroll"
                icon="arrow-left-alt2"
                label={__('Scroll tabs left', 'lynx-journal')}
                onClick={() => scrollNotifyTabs(-1)}
              />
            )}
            <TabPanel
              key={configLoaded ? 'loaded' : 'loading'}
              className="lynxjournal-notify-tabs"
              initialTabName={initialNotifyTab}
              onSelect={setActiveNotifyTab}
              tabs={[
                { name: 'email', title: <TabTitleWithBadge label={__('Email', 'lynx-journal')} enabled={form.notify?.enabled ?? false} incomplete={!!form.notify?.enabled && !form.notify?.email} /> },
                { name: 'discord', title: <TabTitleWithBadge label={__('Discord', 'lynx-journal')} enabled={form.notify?.discordEnabled ?? false} incomplete={!!form.notify?.discordEnabled && !discordComplete} /> },
                { name: 'slack', title: <TabTitleWithBadge label={__('Slack', 'lynx-journal')} enabled={slackEnabled} incomplete={slackIncomplete} /> },
                { name: 'telegram', title: <TabTitleWithBadge label={__('Telegram', 'lynx-journal')} enabled={form.notify?.telegramEnabled ?? false} incomplete={!!form.notify?.telegramEnabled && !telegramComplete} /> },
                { name: 'mastodon', title: <TabTitleWithBadge label={__('Mastodon', 'lynx-journal')} enabled={form.notify?.mastodonEnabled ?? false} incomplete={!!form.notify?.mastodonEnabled && !mastodonComplete} /> },
              ]}
            >
              {() => null}
            </TabPanel>
            {tabsOverflow && (
              <Button
                className="lynxjournal-notify-tabs-scroll"
                icon="arrow-right-alt2"
                label={__('Scroll tabs right', 'lynx-journal')}
                onClick={() => scrollNotifyTabs(1)}
              />
            )}
          </div>

          {/*
            All channel panels are mounted at once, stacked in the same CSS grid
            cell (see .lynxjournal-notify-tab-panel-stack), so the grid row auto-sizes
            to the tallest channel and switching tabs never shifts the layout below.
            Inactive panels are `inert` + visually hidden rather than unmounted, so
            they keep contributing their height to that auto-sizing.
          */}
          <div className="lynxjournal-notify-tab-panel-stack">
            <div className="lynxjournal-notify-tab-panel" inert={activeNotifyTab !== 'email' ? '' : undefined}>
              <CheckboxControl
                label={__('Email me after each run', 'lynx-journal')}
                checked={form.notify?.enabled ?? false}
                onChange={enabled => setForm(f => ({ ...f, notify: { ...f.notify, enabled } }))}
              />
              <TextControl
                label={__('Email address', 'lynx-journal')}
                type="email"
                value={form.notify?.email ?? ''}
                placeholder={__('Leave blank to use admin email', 'lynx-journal')}
                onChange={email => setForm(f => ({ ...f, notify: { ...f.notify, email } }))}
                __nextHasNoMarginBottom
              />
              <ChannelActions
                canTest={!!form.notify?.enabled}
                testState={testState.email}
                onTest={() => handleTest('email')}
                saveState={channelSaveState.email}
                onSave={() => handleSaveChannel('email')}
              />
            </div>

            <div className="lynxjournal-notify-tab-panel" inert={activeNotifyTab !== 'discord' ? '' : undefined}>
              <CheckboxControl
                label={__('Send a Discord notification after each run', 'lynx-journal')}
                checked={form.notify?.discordEnabled ?? false}
                onChange={discordEnabled => setForm(f => ({ ...f, notify: { ...f.notify, discordEnabled } }))}
              />
              <TextControl
                label={__('Discord webhook URL', 'lynx-journal')}
                type="url"
                value={form.notify?.discordWebhookUrl ?? ''}
                placeholder={__('https://discord.com/api/webhooks/...', 'lynx-journal')}
                onChange={discordWebhookUrl => setForm(f => ({ ...f, notify: { ...f.notify, discordWebhookUrl } }))}
                __nextHasNoMarginBottom
              />
              <ChannelActions
                canTest={discordComplete}
                testState={testState.discord}
                onTest={() => handleTest('discord')}
                saveState={channelSaveState.discord}
                onSave={() => handleSaveChannel('discord')}
              />
            </div>

            <div className="lynxjournal-notify-tab-panel" inert={activeNotifyTab !== 'slack' ? '' : undefined}>
              <TextControl
                label={__('Slack Bot Token', 'lynx-journal')}
                type="password"
                value={form.notify?.slackBotToken ?? ''}
                placeholder={__('xoxb-...', 'lynx-journal')}
                onChange={slackBotToken => setForm(f => ({ ...f, notify: { ...f.notify, slackBotToken } }))}
                __nextHasNoMarginBottom
              />

              <CheckboxControl
                label={__('Post to a Slack channel after each run', 'lynx-journal')}
                checked={form.notify?.slackChannelEnabled ?? false}
                onChange={slackChannelEnabled => setForm(f => ({ ...f, notify: { ...f.notify, slackChannelEnabled } }))}
              />
              <TextControl
                label={__('Slack channel ID', 'lynx-journal')}
                value={form.notify?.slackChannelId ?? ''}
                placeholder={__('C0123456789', 'lynx-journal')}
                onChange={slackChannelId => setForm(f => ({ ...f, notify: { ...f.notify, slackChannelId } }))}
                __nextHasNoMarginBottom
              />
              <ChannelActions
                canTest={slackChannelComplete}
                testState={testState.slack_channel}
                onTest={() => handleTest('slack_channel')}
                saveState={channelSaveState.slack_channel}
                onSave={() => handleSaveChannel('slack_channel')}
              />

              <CheckboxControl
                label={__('Send me a Slack DM after each run', 'lynx-journal')}
                checked={form.notify?.slackDmEnabled ?? false}
                onChange={slackDmEnabled => setForm(f => ({ ...f, notify: { ...f.notify, slackDmEnabled } }))}
              />
              <TextControl
                label={__('Slack user ID', 'lynx-journal')}
                value={form.notify?.slackUserId ?? ''}
                placeholder={__('U0123456789', 'lynx-journal')}
                onChange={slackUserId => setForm(f => ({ ...f, notify: { ...f.notify, slackUserId } }))}
                __nextHasNoMarginBottom
              />
              <ChannelActions
                canTest={slackDmComplete}
                testState={testState.slack_dm}
                onTest={() => handleTest('slack_dm')}
                saveState={channelSaveState.slack_dm}
                onSave={() => handleSaveChannel('slack_dm')}
              />
            </div>

            <div className="lynxjournal-notify-tab-panel" inert={activeNotifyTab !== 'telegram' ? '' : undefined}>
              <CheckboxControl
                label={__('Send a Telegram notification after each run', 'lynx-journal')}
                checked={form.notify?.telegramEnabled ?? false}
                onChange={telegramEnabled => setForm(f => ({ ...f, notify: { ...f.notify, telegramEnabled } }))}
              />
              <TextControl
                label={__('Telegram bot token', 'lynx-journal')}
                type="password"
                value={form.notify?.telegramBotToken ?? ''}
                placeholder={__('123456789:AAH...', 'lynx-journal')}
                onChange={telegramBotToken => setForm(f => ({ ...f, notify: { ...f.notify, telegramBotToken } }))}
                __nextHasNoMarginBottom
              />
              <TextControl
                label={__('Telegram chat ID', 'lynx-journal')}
                value={form.notify?.telegramChatId ?? ''}
                placeholder={__('-1001234567890', 'lynx-journal')}
                onChange={telegramChatId => setForm(f => ({ ...f, notify: { ...f.notify, telegramChatId } }))}
                __nextHasNoMarginBottom
              />
              <ChannelActions
                canTest={telegramComplete}
                testState={testState.telegram}
                onTest={() => handleTest('telegram')}
                saveState={channelSaveState.telegram}
                onSave={() => handleSaveChannel('telegram')}
              />
            </div>

            <div className="lynxjournal-notify-tab-panel" inert={activeNotifyTab !== 'mastodon' ? '' : undefined}>
              <CheckboxControl
                label={__('Send a Mastodon direct message after each run', 'lynx-journal')}
                checked={form.notify?.mastodonEnabled ?? false}
                onChange={mastodonEnabled => setForm(f => ({ ...f, notify: { ...f.notify, mastodonEnabled } }))}
              />
              <TextControl
                label={__('Mastodon instance URL', 'lynx-journal')}
                type="url"
                value={form.notify?.mastodonInstanceUrl ?? ''}
                placeholder={__('https://mastodon.social', 'lynx-journal')}
                onChange={mastodonInstanceUrl => setForm(f => ({ ...f, notify: { ...f.notify, mastodonInstanceUrl } }))}
                __nextHasNoMarginBottom
              />
              <TextControl
                label={__('Mastodon access token', 'lynx-journal')}
                type="password"
                value={form.notify?.mastodonAccessToken ?? ''}
                placeholder={__('Access token from your Mastodon app', 'lynx-journal')}
                onChange={mastodonAccessToken => setForm(f => ({ ...f, notify: { ...f.notify, mastodonAccessToken } }))}
                __nextHasNoMarginBottom
              />
              <TextControl
                label={__('Recipient handle', 'lynx-journal')}
                value={form.notify?.mastodonRecipient ?? ''}
                placeholder={__('@you@mastodon.social', 'lynx-journal')}
                onChange={mastodonRecipient => setForm(f => ({ ...f, notify: { ...f.notify, mastodonRecipient } }))}
                __nextHasNoMarginBottom
              />
              <ChannelActions
                canTest={mastodonComplete}
                testState={testState.mastodon}
                onTest={() => handleTest('mastodon')}
                saveState={channelSaveState.mastodon}
                onSave={() => handleSaveChannel('mastodon')}
              />
            </div>
          </div>
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
