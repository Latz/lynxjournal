/**
 * Open the native wp-admin contextual Help dropdown, if closed, and switch
 * it to the given LynxJournal help tab.
 *
 * @param {string} tabId Help tab id suffix, e.g. "discord" for "lynxjournal-help-discord".
 * @returns {void}
 */
export function openHelpTab(tabId) {
  const wrap = document.getElementById('contextual-help-wrap');
  const toggle = document.getElementById('contextual-help-link');
  if (wrap?.classList.contains('hidden')) {
    toggle?.click();
  }
  document.querySelector(`#tab-link-lynxjournal-help-${tabId} a`)?.click();
  wrap?.scrollIntoView?.({ behavior: 'smooth', block: 'start' });
}
