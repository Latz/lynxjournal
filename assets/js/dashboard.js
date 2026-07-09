/**
 * LynxJournal Dashboard — format timestamps and handle link deletion.
 *
 * @since 1.0.0
 */
/* global lynxjournalDash, postboxes, pagenow, jQuery */

var POSTBOX_STATE_KEY = 'lynxjournalDashboardPostboxState';

/** @returns {Object<string, boolean>} Persisted collapsed state, keyed by postbox id. */
function loadPostboxState() {
    try {
        return JSON.parse(localStorage.getItem(POSTBOX_STATE_KEY) || '{}');
    } catch {
        return {};
    }
}

/** @param {Object<string, boolean>} state */
function savePostboxState(state) {
    try {
        localStorage.setItem(POSTBOX_STATE_KEY, JSON.stringify(state));
    } catch {
        // Ignore quota/availability errors — persistence is a non-critical enhancement.
    }
}

document.addEventListener('DOMContentLoaded', function() {
    if (typeof postboxes !== 'undefined') {
        postboxes.add_postbox_toggles(pagenow);
    }

    var postboxState = loadPostboxState();
    document.querySelectorAll('.postbox[id]').forEach(function(box) {
        if (postboxState[box.id] !== true) { return; }
        box.classList.add('closed');
        var handle = box.querySelector('.handlediv');
        if (handle) { handle.setAttribute('aria-expanded', 'false'); }
    });

    if (typeof jQuery !== 'undefined') {
        /** @listens postbox-toggled Persists a postbox's collapsed state after WP core toggles it. */
        jQuery(document).on('postbox-toggled', function(event, postbox) {
            var box = postbox && postbox.jquery ? postbox[0] : postbox;
            if (!box || !box.id) { return; }
            postboxState[box.id] = box.classList.contains('closed');
            savePostboxState(postboxState);
        });
    }

    document.querySelectorAll('.lynxjournal-date-time').forEach(function(element) {
        var timestamp = Number.parseInt(element.dataset.timestamp);
        if (!timestamp) return;
        var date = new Date(timestamp * 1000);
        element.textContent = date.toLocaleString(navigator.language, {
            year: 'numeric', month: 'short', day: 'numeric',
            hour: 'numeric', minute: '2-digit', hour12: true
        });
    });
});

document.addEventListener('click', async function(e) {
    if (e.target.closest('.lynxjournal-delete-cancel')) {
        var li = e.target.closest('li');
        li.querySelector('.lynxjournal-delete-confirm-row').remove();
        li.querySelector('.lynxjournal-delete-btn').style.display = '';
        return;
    }

    if (e.target.closest('.lynxjournal-delete-confirm-yes')) {
        var btn = e.target.closest('.lynxjournal-delete-confirm-yes');
        var li = btn.closest('li');
        btn.disabled = true;
        btn.textContent = '...';
        try {
            var res = await fetch(lynxjournalDash.restUrl + li.dataset.linkId, {
                method: 'DELETE',
                credentials: 'same-origin',
                headers: { 'X-WP-Nonce': lynxjournalDash.nonce }
            });
            if (res.ok || res.status === 204) {
                li.remove();
                ['lynxjournal-stat-total', 'lynxjournal-stat-unpublished'].forEach(function(id) {
                    var el = document.getElementById(id);
                    if (el) { el.textContent = Math.max(0, parseInt(el.textContent.replace(/,/g, ''), 10) - 1).toLocaleString(); }
                });
            } else {
                li.querySelector('.lynxjournal-delete-confirm-row').remove();
                li.querySelector('.lynxjournal-delete-btn').style.display = '';
            }
        } catch (err) {
            li.querySelector('.lynxjournal-delete-confirm-row').remove();
            li.querySelector('.lynxjournal-delete-btn').style.display = '';
        }
        return;
    }

    var btn = e.target.closest('.lynxjournal-delete-btn');
    if (!btn) return;
    var li = btn.closest('li');
    if (li.querySelector('.lynxjournal-delete-confirm-row')) return;
    btn.style.display = 'none';
    var row = document.createElement('div');
    row.className = 'lynxjournal-delete-confirm-row';
    var lbl = document.createElement('span');
    lbl.className = 'lynxjournal-delete-confirm-label';
    lbl.textContent = lynxjournalDash.labels.delete;
    var yes = document.createElement('button');
    yes.className = 'lynxjournal-delete-confirm-yes';
    yes.textContent = lynxjournalDash.labels.yes;
    var no = document.createElement('button');
    no.className = 'lynxjournal-delete-cancel';
    no.textContent = lynxjournalDash.labels.cancel;
    row.append(lbl, yes, no);
    btn.parentElement.appendChild(row);
});
