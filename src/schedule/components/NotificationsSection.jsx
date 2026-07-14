import { useState } from '@wordpress/element';
import { Button, Notice, CheckboxControl, TextControl, TabPanel } from '@wordpress/components';
import { __ } from '@wordpress/i18n';

/**
 * A masked TextControl with a toggle button to reveal/hide its contents,
 * for secrets (bot tokens, channel/user IDs) that shouldn't be shown by default.
 *
 * @param {Object}   props
 * @param {string}   props.label       Field label.
 * @param {string}   props.value       Current field value.
 * @param {string}   [props.placeholder] Placeholder text.
 * @param {Function} props.onChange    Called with the new value on change.
 * @returns {JSX.Element}
 */
function RevealableTextControl({ label, value, placeholder, onChange }) {
  const [revealed, setRevealed] = useState(false);
  return (
    <div className="lynxjournal-field-with-toggle">
      <TextControl
        label={label}
        type={revealed ? 'text' : 'password'}
        value={value}
        placeholder={placeholder}
        onChange={onChange}
        __nextHasNoMarginBottom
      />
      <Button
        className="lynxjournal-field-toggle-btn"
        icon={revealed ? 'hidden' : 'visibility'}
        label={revealed ? __('Hide', 'lynx-journal') : __('Show', 'lynx-journal')}
        onClick={() => setRevealed(r => !r)}
      />
    </div>
  );
}

/**
 * Tab title for a notification channel, colored to reflect its on/off/incomplete state.
 *
 * @param {Object}  props
 * @param {string}  props.label       Channel display name.
 * @param {boolean} props.enabled     Whether the channel is currently on.
 * @param {boolean} [props.incomplete] Whether the channel is on but missing required fields.
 * @returns {JSX.Element}
 */
export function TabTitleWithBadge({ label, enabled, incomplete }) {
  const stateClass = !enabled ? 'is-off' : incomplete ? 'is-incomplete' : 'is-on';
  const stateLabel = !enabled
    ? __('Off', 'lynx-journal')
    : incomplete
      ? __('On, incomplete', 'lynx-journal')
      : __('On', 'lynx-journal');
  return (
    <span className={`lynxjournal-notify-tab-title ${stateClass}`} aria-label={`${label}: ${stateLabel}`}>
      {label}
    </span>
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

/**
 * The Notifications section of the Schedule admin page: 6 per-channel tabs
 * (Email/Discord/Slack/Telegram/Mastodon/Bluesky), each with its own fields, test
 * button, and independent save button.
 *
 * @param {Object}   props
 * @param {Object}   props.form                Schedule form state (reads form.notify).
 * @param {Function} props.setForm             Schedule form setter.
 * @param {boolean}  props.configLoaded        Whether the real schedule config has finished loading.
 * @param {string}   props.initialNotifyTab    Channel tab to select once config has loaded.
 * @param {string}   props.activeNotifyTab     Currently active tab name.
 * @param {Function} props.setActiveNotifyTab  Setter for the active tab.
 * @param {Object}   props.testState           Per-channel test-notification state, keyed by channel.
 * @param {Object}   props.channelSaveState    Per-channel save state, keyed by channel.
 * @param {Function} props.handleTest          Sends a test notification for a channel.
 * @param {Function} props.handleSaveChannel   Persists one channel's fields.
 * @param {Object}   props.tabsWrapRef         Ref for the scrollable tab bar wrapper.
 * @param {boolean}  props.tabsOverflow        Whether the tab bar currently overflows its container.
 * @param {Function} props.scrollNotifyTabs    Scrolls the tab bar left/right.
 * @param {boolean}  props.discordComplete     Whether Discord's required fields are filled in.
 * @param {boolean}  props.slackChannelComplete Whether the Slack channel target's required fields are filled in.
 * @param {boolean}  props.slackDmComplete     Whether the Slack DM target's required fields are filled in.
 * @param {boolean}  props.slackEnabled        Whether either Slack target is enabled.
 * @param {boolean}  props.slackIncomplete     Whether an enabled Slack target is missing required fields.
 * @param {boolean}  props.telegramChannelComplete Whether the Telegram channel/group target's required fields are filled in.
 * @param {boolean}  props.telegramDmComplete  Whether the Telegram DM target's required fields are filled in.
 * @param {boolean}  props.telegramEnabled     Whether either Telegram target is enabled.
 * @param {boolean}  props.telegramIncomplete  Whether an enabled Telegram target is missing required fields.
 * @param {boolean}  props.mastodonComplete    Whether Mastodon's required fields are filled in.
 * @param {boolean}  props.blueskyComplete     Whether Bluesky's required fields are filled in.
 * @returns {JSX.Element}
 */
export default function NotificationsSection({
  form,
  setForm,
  configLoaded,
  initialNotifyTab,
  activeNotifyTab,
  setActiveNotifyTab,
  testState,
  channelSaveState,
  handleTest,
  handleSaveChannel,
  tabsWrapRef,
  tabsOverflow,
  scrollNotifyTabs,
  discordComplete,
  slackChannelComplete,
  slackDmComplete,
  slackEnabled,
  slackIncomplete,
  telegramChannelComplete,
  telegramDmComplete,
  telegramEnabled,
  telegramIncomplete,
  mastodonComplete,
  blueskyComplete,
}) {
  return (
    <>
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
            { name: 'telegram', title: <TabTitleWithBadge label={__('Telegram', 'lynx-journal')} enabled={telegramEnabled} incomplete={telegramIncomplete} /> },
            { name: 'mastodon', title: <TabTitleWithBadge label={__('Mastodon', 'lynx-journal')} enabled={form.notify?.mastodonEnabled ?? false} incomplete={!!form.notify?.mastodonEnabled && !mastodonComplete} /> },
            { name: 'bluesky', title: <TabTitleWithBadge label={__('Bluesky', 'lynx-journal')} enabled={form.notify?.bskyEnabled ?? false} incomplete={!!form.notify?.bskyEnabled && !blueskyComplete} /> },
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
          <RevealableTextControl
            label={__('Slack Bot Token', 'lynx-journal')}
            value={form.notify?.slackBotToken ?? ''}
            placeholder={__('xoxb-...', 'lynx-journal')}
            onChange={slackBotToken => setForm(f => ({ ...f, notify: { ...f.notify, slackBotToken } }))}
          />

          <fieldset className="lynxjournal-notify-target-group">
            <legend className="lynxjournal-notify-target-heading">{__('Channel', 'lynx-journal')}</legend>
            <CheckboxControl
              label={__('Post to a Slack channel after each run', 'lynx-journal')}
              checked={form.notify?.slackChannelEnabled ?? false}
              onChange={slackChannelEnabled => setForm(f => ({ ...f, notify: { ...f.notify, slackChannelEnabled } }))}
            />
            <RevealableTextControl
              label={__('Slack channel ID', 'lynx-journal')}
              value={form.notify?.slackChannelId ?? ''}
              placeholder={__('C0123456789', 'lynx-journal')}
              onChange={slackChannelId => setForm(f => ({ ...f, notify: { ...f.notify, slackChannelId } }))}
            />
            <ChannelActions
              canTest={slackChannelComplete}
              testState={testState.slack_channel}
              onTest={() => handleTest('slack_channel')}
              saveState={channelSaveState.slack_channel}
              onSave={() => handleSaveChannel('slack_channel')}
            />
          </fieldset>

          <fieldset className="lynxjournal-notify-target-group">
            <legend className="lynxjournal-notify-target-heading">{__('Personal message', 'lynx-journal')}</legend>
            <CheckboxControl
              label={__('Send me a Slack DM after each run', 'lynx-journal')}
              checked={form.notify?.slackDmEnabled ?? false}
              onChange={slackDmEnabled => setForm(f => ({ ...f, notify: { ...f.notify, slackDmEnabled } }))}
            />
            <RevealableTextControl
              label={__('Slack user ID', 'lynx-journal')}
              value={form.notify?.slackUserId ?? ''}
              placeholder={__('U0123456789', 'lynx-journal')}
              onChange={slackUserId => setForm(f => ({ ...f, notify: { ...f.notify, slackUserId } }))}
            />
            <ChannelActions
              canTest={slackDmComplete}
              testState={testState.slack_dm}
              onTest={() => handleTest('slack_dm')}
              saveState={channelSaveState.slack_dm}
              onSave={() => handleSaveChannel('slack_dm')}
            />
          </fieldset>
        </div>

        <div className="lynxjournal-notify-tab-panel" inert={activeNotifyTab !== 'telegram' ? '' : undefined}>
          <RevealableTextControl
            label={__('Telegram bot token', 'lynx-journal')}
            value={form.notify?.telegramBotToken ?? ''}
            placeholder={__('123456789:AAH...', 'lynx-journal')}
            onChange={telegramBotToken => setForm(f => ({ ...f, notify: { ...f.notify, telegramBotToken } }))}
          />

          <fieldset className="lynxjournal-notify-target-group">
            <legend className="lynxjournal-notify-target-heading">{__('Group or channel', 'lynx-journal')}</legend>
            <CheckboxControl
              label={__('Post to a Telegram group or channel after each run', 'lynx-journal')}
              checked={form.notify?.telegramEnabled ?? false}
              onChange={telegramEnabled => setForm(f => ({ ...f, notify: { ...f.notify, telegramEnabled } }))}
            />
            <RevealableTextControl
              label={__('Telegram group/channel chat ID', 'lynx-journal')}
              value={form.notify?.telegramChatId ?? ''}
              placeholder={__('-1001234567890', 'lynx-journal')}
              onChange={telegramChatId => setForm(f => ({ ...f, notify: { ...f.notify, telegramChatId } }))}
            />
            <ChannelActions
              canTest={telegramChannelComplete}
              testState={testState.telegram}
              onTest={() => handleTest('telegram')}
              saveState={channelSaveState.telegram}
              onSave={() => handleSaveChannel('telegram')}
            />
          </fieldset>

          <fieldset className="lynxjournal-notify-target-group">
            <legend className="lynxjournal-notify-target-heading">{__('Personal message', 'lynx-journal')}</legend>
            <CheckboxControl
              label={__('Send me a Telegram DM after each run', 'lynx-journal')}
              checked={form.notify?.telegramDmEnabled ?? false}
              onChange={telegramDmEnabled => setForm(f => ({ ...f, notify: { ...f.notify, telegramDmEnabled } }))}
            />
            <RevealableTextControl
              label={__('Telegram personal chat ID', 'lynx-journal')}
              value={form.notify?.telegramDmChatId ?? ''}
              placeholder={__('123456789', 'lynx-journal')}
              onChange={telegramDmChatId => setForm(f => ({ ...f, notify: { ...f.notify, telegramDmChatId } }))}
            />
            <ChannelActions
              canTest={telegramDmComplete}
              testState={testState.telegram_dm}
              onTest={() => handleTest('telegram_dm')}
              saveState={channelSaveState.telegram_dm}
              onSave={() => handleSaveChannel('telegram_dm')}
            />
          </fieldset>
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
          <RevealableTextControl
            label={__('Mastodon access token', 'lynx-journal')}
            value={form.notify?.mastodonAccessToken ?? ''}
            placeholder={__('Access token from your Mastodon app', 'lynx-journal')}
            onChange={mastodonAccessToken => setForm(f => ({ ...f, notify: { ...f.notify, mastodonAccessToken } }))}
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

        <div className="lynxjournal-notify-tab-panel" inert={activeNotifyTab !== 'bluesky' ? '' : undefined}>
          <CheckboxControl
            label={__('Send a Bluesky direct message after each run', 'lynx-journal')}
            checked={form.notify?.bskyEnabled ?? false}
            onChange={bskyEnabled => setForm(f => ({ ...f, notify: { ...f.notify, bskyEnabled } }))}
          />
          <TextControl
            label={__('Bluesky handle', 'lynx-journal')}
            value={form.notify?.bskyHandle ?? ''}
            placeholder={__('you.bsky.social', 'lynx-journal')}
            onChange={bskyHandle => setForm(f => ({ ...f, notify: { ...f.notify, bskyHandle } }))}
            __nextHasNoMarginBottom
          />
          <RevealableTextControl
            label={__('Bluesky app password', 'lynx-journal')}
            value={form.notify?.bskyAppPassword ?? ''}
            placeholder={__('xxxx-xxxx-xxxx-xxxx', 'lynx-journal')}
            onChange={bskyAppPassword => setForm(f => ({ ...f, notify: { ...f.notify, bskyAppPassword } }))}
          />
          <TextControl
            label={__('Recipient handle', 'lynx-journal')}
            value={form.notify?.bskyRecipient ?? ''}
            placeholder={__('friend.bsky.social', 'lynx-journal')}
            onChange={bskyRecipient => setForm(f => ({ ...f, notify: { ...f.notify, bskyRecipient } }))}
            __nextHasNoMarginBottom
          />
          <ChannelActions
            canTest={blueskyComplete}
            testState={testState.bluesky}
            onTest={() => handleTest('bluesky')}
            saveState={channelSaveState.bluesky}
            onSave={() => handleSaveChannel('bluesky')}
          />
        </div>
      </div>
    </>
  );
}
