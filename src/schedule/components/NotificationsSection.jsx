import { useState } from '@wordpress/element';
import { Button, Notice, CheckboxControl, TextControl, TabPanel } from '@wordpress/components';
import { __ } from '@wordpress/i18n';
import { NOTIFICATION_CHANNELS, isChannelEnabled, isChannelComplete, isChannelIncomplete, isTargetComplete } from '../lib/notificationChannels';
import { openHelpTab } from '../lib/helpPanel';

/**
 * The Help-dropdown tab id for a channel entry's key. Every channel matches
 * its own key except Email, which has no dedicated tab and falls back to Overview.
 *
 * @param {string} channelKey A NOTIFICATION_CHANNELS entry's `key`.
 * @returns {string} The corresponding `lynxjournal-help-*` tab id suffix.
 */
function helpTabIdFor(channelKey) {
  return channelKey === 'email' ? 'overview' : channelKey;
}

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
 * Render one channel field (text/url/email/revealable) bound to `notify[field.name]`.
 *
 * @param {Object}   field    A NOTIFICATION_CHANNELS field definition.
 * @param {Object}   notify   Current `notify` form state.
 * @param {Function} setForm  Schedule form setter.
 * @returns {JSX.Element}
 */
function renderField(field, notify, setForm) {
  const value = notify?.[field.name] ?? '';
  const onChange = newValue => setForm(f => ({ ...f, notify: { ...f.notify, [field.name]: newValue } }));

  if (field.type === 'revealable') {
    return <RevealableTextControl key={field.name} label={field.label} value={value} placeholder={field.placeholder} onChange={onChange} />;
  }
  return (
    <TextControl
      key={field.name}
      label={field.label}
      type={field.type}
      value={value}
      placeholder={field.placeholder}
      onChange={onChange}
      __nextHasNoMarginBottom
    />
  );
}

/**
 * Render one target's (or a non-grouped channel's own) enable checkbox,
 * fields, and test/save actions.
 *
 * @param {Object}   props
 * @param {Object}   props.entry            The parent NOTIFICATION_CHANNELS entry (for sharedFields).
 * @param {Object}   props.target           The target itself (or `entry`, for non-grouped channels).
 * @param {Object}   props.notify           Current `notify` form state.
 * @param {Function} props.setForm          Schedule form setter.
 * @param {Object}   [props.testState]      This target's { testing, notice } state, if any.
 * @param {Object}   [props.channelSaveState] This target's { saving, notice } state, if any.
 * @param {Function} props.handleTest       Sends a test notification for a channel key.
 * @param {Function} props.handleSaveChannel Persists a channel key's fields.
 * @returns {JSX.Element}
 */
function TargetFields({ entry, target, notify, setForm, testState, channelSaveState, handleTest, handleSaveChannel }) {
  return (
    <>
      <CheckboxControl
        label={target.checkboxLabel}
        checked={!!notify?.[target.enabledField]}
        onChange={checked => setForm(f => ({ ...f, notify: { ...f.notify, [target.enabledField]: checked } }))}
      />
      {target.fields.map(field => renderField(field, notify, setForm))}
      <ChannelActions
        canTest={isTargetComplete(entry, target, notify)}
        testState={testState[target.key]}
        onTest={() => handleTest(target.key)}
        saveState={channelSaveState[target.key]}
        onSave={() => handleSaveChannel(target.key)}
      />
    </>
  );
}

/**
 * The Notifications section of the Schedule admin page: one tab per
 * NOTIFICATION_CHANNELS entry (Email/Discord/Slack/Telegram/Mastodon/
 * Bluesky), each rendered generically from its field config.
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
}) {
  const notify = form.notify;

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
          tabs={NOTIFICATION_CHANNELS.map(entry => ({
            name: entry.key,
            title: (
              <TabTitleWithBadge
                label={entry.tabLabel}
                enabled={isChannelEnabled(entry, notify)}
                incomplete={isChannelIncomplete(entry, notify)}
              />
            ),
          }))}
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
        {NOTIFICATION_CHANNELS.map(entry => (
          <div key={entry.key} className="lynxjournal-notify-tab-panel" inert={activeNotifyTab !== entry.key ? '' : undefined}>
            <Button
              variant="link"
              icon="editor-help"
              className="lynxjournal-notify-help-link"
              onClick={() => openHelpTab(helpTabIdFor(entry.key))}
            >
              {__('Help', 'lynx-journal')}
            </Button>
            {entry.targets ? (
              <>
                {entry.sharedFields.map(field => renderField(field, notify, setForm))}
                {entry.targets.map(target => (
                  <fieldset key={target.key} className="lynxjournal-notify-target-group">
                    <legend className="lynxjournal-notify-target-heading">{target.groupLabel}</legend>
                    <TargetFields
                      entry={entry}
                      target={target}
                      notify={notify}
                      setForm={setForm}
                      testState={testState}
                      channelSaveState={channelSaveState}
                      handleTest={handleTest}
                      handleSaveChannel={handleSaveChannel}
                    />
                  </fieldset>
                ))}
              </>
            ) : (
              <TargetFields
                entry={entry}
                target={entry}
                notify={notify}
                setForm={setForm}
                testState={testState}
                channelSaveState={channelSaveState}
                handleTest={handleTest}
                handleSaveChannel={handleSaveChannel}
              />
            )}
          </div>
        ))}
      </div>
    </>
  );
}
