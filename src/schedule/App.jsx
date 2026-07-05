import { useState, useEffect, useMemo, useCallback } from '@wordpress/element';
import apiFetch from '@wordpress/api-fetch';
import { Button, Notice, CheckboxControl, TextControl, SelectControl } from '@wordpress/components';
import { __ } from '@wordpress/i18n';
import { buildRRule } from './lib/rrule';
import { SCHEDULE_MODES } from './lib/modes';
import ScheduleTypePicker from './components/ScheduleTypePicker';
import RecurrenceConfig from './components/RecurrenceConfig';
import TriggerCondition from './components/TriggerCondition';
import TimePicker from './components/TimePicker';
import NextSchedules from './components/NextSchedules';
import DiagnosticsPanel from './components/DiagnosticsPanel';
import AccordionItem from './components/AccordionItem';

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
  },
  post_status: 'publish',
};

function Section({ title, children }) {
  return (
    <div className="lynxjournal-section">
      <h3 className="lynxjournal-section-heading">{title}</h3>
      <div className="lynxjournal-section-body">{children}</div>
    </div>
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
  // Forces the notification accordion items to remount (and re-read their
  // defaultOpen prop) once the real schedule config has loaded, since
  // useState's initializer only runs on a component's first mount.
  const configLoaded = savedForm !== null;

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

        <Section title={__('Notifications', 'lynx-journal')}>
          <div className="lynxjournal-accordion" key={configLoaded ? 'loaded' : 'loading'}>
            <AccordionItem
              title={__('Email', 'lynx-journal')}
              enabled={form.notify?.enabled ?? false}
              defaultOpen={form.notify?.enabled ?? false}
            >
              <CheckboxControl
                label={__('Email me after each run', 'lynx-journal')}
                checked={form.notify?.enabled ?? false}
                onChange={enabled => setForm(f => ({ ...f, notify: { ...f.notify, enabled } }))}
              />
              {form.notify?.enabled && (
                <TextControl
                  label={__('Email address', 'lynx-journal')}
                  type="email"
                  value={form.notify?.email ?? ''}
                  placeholder={__('Leave blank to use admin email', 'lynx-journal')}
                  onChange={email => setForm(f => ({ ...f, notify: { ...f.notify, email } }))}
                  __nextHasNoMarginBottom
                />
              )}
            </AccordionItem>

            <AccordionItem
              title={__('Discord', 'lynx-journal')}
              enabled={form.notify?.discordEnabled ?? false}
              defaultOpen={form.notify?.discordEnabled ?? false}
            >
              <CheckboxControl
                label={__('Send a Discord notification after each run', 'lynx-journal')}
                checked={form.notify?.discordEnabled ?? false}
                onChange={discordEnabled => setForm(f => ({ ...f, notify: { ...f.notify, discordEnabled } }))}
              />
              {form.notify?.discordEnabled && (
                <TextControl
                  label={__('Discord webhook URL', 'lynx-journal')}
                  type="url"
                  value={form.notify?.discordWebhookUrl ?? ''}
                  placeholder={__('https://discord.com/api/webhooks/...', 'lynx-journal')}
                  onChange={discordWebhookUrl => setForm(f => ({ ...f, notify: { ...f.notify, discordWebhookUrl } }))}
                  __nextHasNoMarginBottom
                />
              )}
            </AccordionItem>

            <AccordionItem
              title={__('Slack', 'lynx-journal')}
              enabled={(form.notify?.slackChannelEnabled || form.notify?.slackDmEnabled) ?? false}
              defaultOpen={(form.notify?.slackChannelEnabled || form.notify?.slackDmEnabled) ?? false}
            >
              {(form.notify?.slackChannelEnabled || form.notify?.slackDmEnabled) && (
                <TextControl
                  label={__('Slack Bot Token', 'lynx-journal')}
                  type="password"
                  value={form.notify?.slackBotToken ?? ''}
                  placeholder={__('xoxb-...', 'lynx-journal')}
                  onChange={slackBotToken => setForm(f => ({ ...f, notify: { ...f.notify, slackBotToken } }))}
                  __nextHasNoMarginBottom
                />
              )}

              <CheckboxControl
                label={__('Post to a Slack channel after each run', 'lynx-journal')}
                checked={form.notify?.slackChannelEnabled ?? false}
                onChange={slackChannelEnabled => setForm(f => ({ ...f, notify: { ...f.notify, slackChannelEnabled } }))}
              />
              {form.notify?.slackChannelEnabled && (
                <TextControl
                  label={__('Slack channel ID', 'lynx-journal')}
                  value={form.notify?.slackChannelId ?? ''}
                  placeholder={__('C0123456789', 'lynx-journal')}
                  onChange={slackChannelId => setForm(f => ({ ...f, notify: { ...f.notify, slackChannelId } }))}
                  __nextHasNoMarginBottom
                />
              )}

              <CheckboxControl
                label={__('Send me a Slack DM after each run', 'lynx-journal')}
                checked={form.notify?.slackDmEnabled ?? false}
                onChange={slackDmEnabled => setForm(f => ({ ...f, notify: { ...f.notify, slackDmEnabled } }))}
              />
              {form.notify?.slackDmEnabled && (
                <TextControl
                  label={__('Slack user ID', 'lynx-journal')}
                  value={form.notify?.slackUserId ?? ''}
                  placeholder={__('U0123456789', 'lynx-journal')}
                  onChange={slackUserId => setForm(f => ({ ...f, notify: { ...f.notify, slackUserId } }))}
                  __nextHasNoMarginBottom
                />
              )}
            </AccordionItem>
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
