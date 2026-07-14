import { useState, useEffect, useMemo, useCallback, useRef } from '@wordpress/element';
import apiFetch from '@wordpress/api-fetch';
import { __ } from '@wordpress/i18n';
import { initialTabFor, anyChannelEnabled } from './notificationChannels';

/**
 * Encapsulates all state and handlers for the Notifications section: the
 * active channel tab, per-channel test/save request state, and tab-bar
 * overflow scrolling. Per-field completeness/enabled state is derived
 * directly from NOTIFICATION_CHANNELS by the component itself.
 *
 * @param {Object}   form         Current schedule form state (read for form.notify).
 * @param {Function} setForm      Schedule form setter.
 * @param {Function} setSavedForm Saved-form setter, kept in sync so the dirty-check
 *                                doesn't flag a channel save that was already persisted.
 * @param {boolean}  configLoaded Whether the real schedule config has finished loading.
 * @returns {Object} Everything NotificationsSection needs to render and respond to input.
 */
export function useNotifications(form, setForm, setSavedForm, configLoaded) {
  // Keyed by channel ('email' | 'discord' | 'slack_channel' | 'slack_dm' | 'telegram' | 'mastodon' | 'bluesky').
  const [testState, setTestState] = useState({});
  // Keyed the same way as testState, but for the per-channel Save button.
  const [channelSaveState, setChannelSaveState] = useState({});

  const initialNotifyTab = useMemo(() => initialTabFor(form.notify),
    // eslint-disable-next-line react-hooks/exhaustive-deps -- only recompute once config finishes loading
    [configLoaded]);

  const [activeNotifyTab, setActiveNotifyTab] = useState(initialNotifyTab);

  useEffect(() => {
    if (configLoaded) setActiveNotifyTab(initialNotifyTab);
    // eslint-disable-next-line react-hooks/exhaustive-deps -- only jump once, when config finishes loading
  }, [configLoaded]);

  /**
   * Send a one-off test notification for a single channel using the
   * currently-entered (possibly unsaved) notify settings.
   *
   * @param {string} channel One of email|discord|slack_channel|slack_dm|telegram|mastodon|bluesky.
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
   * @param {string} channel One of email|discord|slack_channel|slack_dm|telegram|mastodon|bluesky.
   * @returns {Promise<void>}
   */
  async function handleSaveChannel(channel) {
    setChannelSaveState(s => ({ ...s, [channel]: { saving: true, notice: null } }));
    try {
      const res = await apiFetch({ path: '/lynxjournal/v1/schedule/save-notification', method: 'POST', data: { channel, notify: form.notify } });
      setForm(f => ({ ...f, notify: { ...f.notify, ...res.notify } }));
      setSavedForm(f => f && ({ ...f, notify: { ...f.notify, ...res.notify } }));
      setChannelSaveState(s => ({ ...s, [channel]: { saving: false, notice: { status: 'success', message: __('Saved.', 'lynx-journal') } } }));
      return res.notify;
    } catch (err) {
      setChannelSaveState(s => ({ ...s, [channel]: { saving: false, notice: { status: 'error', message: err?.message || __('Failed to save.', 'lynx-journal') } } }));
      return null;
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

  const anyNotifyEnabled = anyChannelEnabled(form.notify);

  return {
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
    handleNotifySectionToggle,
    anyNotifyEnabled,
  };
}
