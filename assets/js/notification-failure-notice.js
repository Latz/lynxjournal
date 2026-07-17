/**
 * LynxJournal — persist dismissal of the notification-failure admin notice.
 *
 * @since 1.0.0
 */
/* global ajaxurl */

document.addEventListener('DOMContentLoaded', () => {
    const notice = document.getElementById('lynxjournal-notification-failure-notice');
    if (!notice) {
        return;
    }

    /**
     * @listens click - Persists dismissal server-side when the notice's
     * built-in dismiss button is clicked; WordPress core already hides the
     * box visually, so this only needs to record the dismissal.
     */
    notice.addEventListener('click', (event) => {
        if (!event.target.closest('.notice-dismiss')) {
            return;
        }

        fetch(ajaxurl, {
            method: 'POST',
            credentials: 'same-origin',
            headers: { 'Content-Type': 'application/x-www-form-urlencoded' },
            body: new URLSearchParams({
                action: 'lynxjournal_dismiss_notification_failures',
                nonce: notice.dataset.nonce,
            }),
        });
    });
});
