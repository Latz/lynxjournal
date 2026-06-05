jQuery(document).ready(function($) {
    var labels = (window.lynxjournalSettings && window.lynxjournalSettings.labels) || {};

    // Copy to clipboard
    $('.lynxjournal-copy-btn').on('click', function() {
        var targetId = $(this).data('clipboard-target');
        var input = document.getElementById(targetId);
        if (!input) { return; }

        var $btn = $(this);
        var value = input.value;

        function showSuccess() {
            var originalHtml = $btn.html();
            $btn.html('<span class="dashicons dashicons-yes" style="margin-top: 3px; color: #00a32a;"></span>');
            setTimeout(function() { $btn.html(originalHtml); }, 2000);
        }

        if (navigator.clipboard && navigator.clipboard.writeText) {
            navigator.clipboard.writeText(value).then(showSuccess).catch(function() {});
        } else {
            var prevType = input.type;
            var wasDisabled = input.disabled;
            input.type = 'text';
            input.disabled = false;
            input.select();
            input.setSelectionRange(0, 99999);
            try { document.execCommand('copy'); showSuccess(); } catch (err) {}
            input.disabled = wasDisabled;
            input.type = prevType;
        }
    });

    // Show / hide API key toggle
    $('.lynxjournal-toggle-key').on('click', function() {
        var input = document.getElementById('lynxjournal-api-key');
        if (!input) { return; }
        var $icon = $(this).find('.dashicons');
        if (input.type === 'password') {
            input.type = 'text';
            $icon.removeClass('dashicons-visibility').addClass('dashicons-hidden');
        } else {
            input.type = 'password';
            $icon.removeClass('dashicons-hidden').addClass('dashicons-visibility');
        }
    });

    // Confirm before regenerating API key
    $('#lynxjournal-generate-form').on('submit', function(e) {
        if ($(this).data('has-key')) {
            if (!window.confirm(labels.confirmRegenerate || '')) { e.preventDefault(); }
        }
    });

    // Test Connection
    $('#lynxjournal-test-connection').on('click', function() {
        var endpoint = (document.getElementById('lynxjournal-api-endpoint') || {}).value || '';
        var apiKey   = (document.getElementById('lynxjournal-api-key') || {}).value || '';
        var $status  = $('#lynxjournal-connection-status');

        if (!endpoint || !apiKey) {
            $status.css('color', '#d63638').text(labels.missingFields || '');
            return;
        }

        var $btn = $(this);
        $btn.prop('disabled', true);
        $status.css('color', '#666').text(labels.statusTesting || '');

        fetch(endpoint.replace(/\/$/, '') + '/categories', {
            headers: { 'X-LynxJournal-API-Key': apiKey }
        })
        .then(function(res) {
            if (res.ok) {
                $status.css('color', '#00a32a').text('✓ ' + (labels.statusOk || ''));
            } else {
                $status.css('color', '#d63638').text('✗ ' + (labels.statusFail || '') + ' (HTTP ' + res.status + ')');
            }
        })
        .catch(function() {
            $status.css('color', '#d63638').text('✗ ' + (labels.statusUnreachable || ''));
        })
        .finally(function() { $btn.prop('disabled', false); });
    });

    // Rotate setup section arrow on toggle
    var $details = $('details.lynxjournal-setup-details');
    $details.on('toggle', function() {
        var $arrow = $('#lynxjournal-setup-arrow');
        $arrow.css('transform', this.open ? 'rotate(90deg)' : 'rotate(0deg)');
    });
    if ($details[0] && $details[0].open) {
        $('#lynxjournal-setup-arrow').css('transform', 'rotate(90deg)');
    }
});
