/**
 * LinkDigest Dashboard — format timestamps and handle link deletion.
 *
 * @since 1.0.0
 */
/* global linkdigestDash */

document.addEventListener('DOMContentLoaded', function() {
    if (typeof postboxes !== 'undefined') {
        postboxes.add_postbox_toggles(pagenow);
    }

    document.querySelectorAll('.linkdigest-date-time').forEach(function(element) {
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
    if (e.target.closest('.linkdigest-delete-cancel')) {
        var li = e.target.closest('li');
        li.querySelector('.linkdigest-delete-confirm-row').remove();
        li.querySelector('.linkdigest-delete-btn').style.display = '';
        return;
    }

    if (e.target.closest('.linkdigest-delete-confirm-yes')) {
        var btn = e.target.closest('.linkdigest-delete-confirm-yes');
        var li = btn.closest('li');
        btn.disabled = true;
        btn.textContent = '...';
        try {
            var res = await fetch(linkdigestDash.restUrl + li.dataset.linkId, {
                method: 'DELETE',
                credentials: 'same-origin',
                headers: { 'X-WP-Nonce': linkdigestDash.nonce }
            });
            if (res.ok || res.status === 204) {
                li.remove();
                ['linkdigest-stat-total', 'linkdigest-stat-unpublished'].forEach(function(id) {
                    var el = document.getElementById(id);
                    if (el) { el.textContent = Math.max(0, parseInt(el.textContent.replace(/,/g, ''), 10) - 1).toLocaleString(); }
                });
            } else {
                li.querySelector('.linkdigest-delete-confirm-row').remove();
                li.querySelector('.linkdigest-delete-btn').style.display = '';
            }
        } catch (err) {
            li.querySelector('.linkdigest-delete-confirm-row').remove();
            li.querySelector('.linkdigest-delete-btn').style.display = '';
        }
        return;
    }

    var btn = e.target.closest('.linkdigest-delete-btn');
    if (!btn) return;
    var li = btn.closest('li');
    if (li.querySelector('.linkdigest-delete-confirm-row')) return;
    btn.style.display = 'none';
    var row = document.createElement('div');
    row.className = 'linkdigest-delete-confirm-row';
    var lbl = document.createElement('span');
    lbl.className = 'linkdigest-delete-confirm-label';
    lbl.textContent = linkdigestDash.labels.delete;
    var yes = document.createElement('button');
    yes.className = 'linkdigest-delete-confirm-yes';
    yes.textContent = linkdigestDash.labels.yes;
    var no = document.createElement('button');
    no.className = 'linkdigest-delete-cancel';
    no.textContent = linkdigestDash.labels.cancel;
    row.append(lbl, yes, no);
    btn.parentElement.appendChild(row);
});
