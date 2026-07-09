import { useState, useEffect, useCallback } from '@wordpress/element';
import apiFetch from '@wordpress/api-fetch';
import { Button, Notice } from '@wordpress/components';
import { __ } from '@wordpress/i18n';

const API_KEY_PATH = '/lynxjournal/v1/api-key';

/**
 * Copy a value to the clipboard using the Clipboard API.
 *
 * @param {HTMLInputElement} input The input element holding the value to copy.
 * @return {Promise<boolean>} Resolves true on success, false on failure.
 */
function copyInputValue(input) {
  if (!navigator.clipboard || !navigator.clipboard.writeText) {
    return Promise.resolve(false);
  }
  return navigator.clipboard.writeText(input.value).then(() => true).catch(() => false);
}

/**
 * Readonly text field with a copy-to-clipboard button.
 *
 * @param {object} props
 * @param {string} props.id Input element id.
 * @param {string} props.value Field value.
 * @param {string} [props.type] Input type, defaults to "text".
 * @param {React.ReactNode} [props.trailing] Extra content rendered after the copy button.
 * @return {JSX.Element}
 */
function CopyableField({ id, value, type = 'text', trailing = null }) {
  const [copied, setCopied] = useState(false);

  /** @listens click Copies the field value and briefly shows a confirmation icon. */
  function handleCopy() {
    const input = document.getElementById(id);
    if (!input) return;
    copyInputValue(input).then((success) => {
      if (!success) return;
      setCopied(true);
      setTimeout(() => setCopied(false), 2000);
    });
  }

  return (
    <div className="lynxjournal-row">
      <input
        type={type}
        id={id}
        value={value}
        readOnly
        onClick={(e) => e.currentTarget.select()}
        className="large-text code"
      />
      <button type="button" className="button lynxjournal-copy-btn" onClick={handleCopy}>
        <span
          className={`dashicons ${copied ? 'dashicons-yes' : 'dashicons-clipboard'} lynxjournal-btn-icon`}
          style={copied ? { color: '#00a32a' } : undefined}
        ></span>
      </button>
      {trailing}
    </div>
  );
}

export default function App() {
  const settings = window.lynxjournalSettings || {};
  const restUrl = settings.restUrl || '';

  const [apiKey, setApiKey] = useState(null);
  const [loading, setLoading] = useState(true);
  const [keyVisible, setKeyVisible] = useState(false);
  const [generating, setGenerating] = useState(false);
  const [notice, setNotice] = useState(null);
  const [testing, setTesting] = useState(false);
  const [testStatus, setTestStatus] = useState(null);

  useEffect(() => {
    apiFetch({ path: API_KEY_PATH })
      .then((data) => setApiKey(data.key))
      .catch(() => setApiKey(null))
      .finally(() => setLoading(false));
  }, []);

  /** @listens click Regenerates the API key, confirming first when one already exists. */
  const handleGenerate = useCallback(() => {
    if (apiKey && !window.confirm(__(
      'This will permanently invalidate your current API key. You will need to update the Chrome extension with the new key. Continue?',
      'lynx-journal'
    ))) {
      return;
    }
    setGenerating(true);
    setNotice(null);
    apiFetch({ path: API_KEY_PATH, method: 'POST' })
      .then((data) => {
        setApiKey(data.key);
        setKeyVisible(false);
        setTestStatus(null);
        setNotice({ status: 'success', message: __('New API key generated successfully!', 'lynx-journal') });
      })
      .catch(() => {
        setNotice({ status: 'error', message: __('Failed to generate API key.', 'lynx-journal') });
      })
      .finally(() => setGenerating(false));
  }, [apiKey]);

  /** @listens click Tests connectivity to the REST API using the current API key. */
  const handleTestConnection = useCallback(() => {
    if (!restUrl || !apiKey) {
      setTestStatus({ ok: false, message: __('Missing endpoint or API key.', 'lynx-journal') });
      return;
    }
    setTesting(true);
    setTestStatus({ ok: null, message: __('Testing…', 'lynx-journal') });
    fetch(`${restUrl.replace(/\/$/, '')}/categories`, {
      headers: { 'X-LynxJournal-API-Key': apiKey },
    })
      .then((res) => {
        if (res.ok) {
          setTestStatus({ ok: true, message: `✓ ${__('Connected successfully.', 'lynx-journal')}` });
        } else {
          setTestStatus({ ok: false, message: `✗ ${__('Connection failed', 'lynx-journal')} (HTTP ${res.status})` });
        }
      })
      .catch(() => {
        setTestStatus({ ok: false, message: `✗ ${__('Could not reach endpoint.', 'lynx-journal')}` });
      })
      .finally(() => setTesting(false));
  }, [restUrl, apiKey]);

  if (loading) {
    return null;
  }

  return (
    <>
      {notice && (
        <Notice status={notice.status} onRemove={() => setNotice(null)} isDismissible>
          {notice.message}
        </Notice>
      )}

      <div className="card lynxjournal-settings-card">
        <h2>{__('Chrome Extension Access Data', 'lynx-journal')}</h2>
        <p>{__('Use these credentials to connect the LynxJournal Chrome extension to your WordPress site.', 'lynx-journal')}</p>

        <div className="lynxjournal-settings-field">
          <label className="lynxjournal-settings-label" htmlFor="lynxjournal-api-endpoint">
            {__('API Endpoint', 'lynx-journal')}
            {' '}
            <span className="lynxjournal-settings-note">({__('read-only', 'lynx-journal')})</span>
          </label>
          <CopyableField id="lynxjournal-api-endpoint" value={restUrl} />
          <p className="description">
            {__('Use this URL in the Chrome extension settings.', 'lynx-journal')}
            {' '}
            <a href={restUrl} target="_blank" rel="noreferrer" className="lynxjournal-rest-link">
              {__('View REST API', 'lynx-journal')} ↗
            </a>
          </p>
        </div>

        {apiKey && (
          <div className="lynxjournal-settings-field">
            <label htmlFor="lynxjournal-api-key" className="lynxjournal-settings-label">
              {__('API Key:', 'lynx-journal')}
            </label>
            <CopyableField
              id="lynxjournal-api-key"
              value={apiKey}
              type={keyVisible ? 'text' : 'password'}
              trailing={(
                <button
                  type="button"
                  className="button lynxjournal-toggle-key"
                  title={__('Show / hide API key', 'lynx-journal')}
                  onClick={() => setKeyVisible((v) => !v)}
                >
                  <span className={`dashicons ${keyVisible ? 'dashicons-hidden' : 'dashicons-visibility'} lynxjournal-btn-icon`}></span>
                </button>
              )}
            />
            <p className="description lynxjournal-settings-desc">
              {__('Keep this key secure. Use the copy button to transfer it without revealing it.', 'lynx-journal')}
            </p>
            <div className="lynxjournal-settings-test-row">
              <Button variant="secondary" onClick={handleTestConnection} isBusy={testing} disabled={testing}>
                {__('Test Connection', 'lynx-journal')}
              </Button>
              {testStatus && (
                <span style={{ color: testStatus.ok === false ? '#d63638' : testStatus.ok === true ? '#00a32a' : '#666' }}>
                  {testStatus.message}
                </span>
              )}
            </div>
          </div>
        )}

        {apiKey && (
          <div className="notice notice-warning inline">
            <p>{__('Warning: Generating a new key will permanently invalidate the current one. You will need to update the Chrome extension with the new key.', 'lynx-journal')}</p>
          </div>
        )}
        <Button variant="primary" onClick={handleGenerate} isBusy={generating} disabled={generating}>
          {apiKey ? __('Generate New API Key', 'lynx-journal') : __('Generate API Key', 'lynx-journal')}
        </Button>
      </div>

      <div className="card lynxjournal-setup-card">
        <h2>{__('Chrome Extension Setup', 'lynx-journal')}</h2>
        <ol>
          <li>{__('Download and install the LynxJournal Chrome extension', 'lynx-journal')}</li>
          <li>{__('Click the extension icon and go to Settings', 'lynx-journal')}</li>
          <li>{__('Paste your API Endpoint and API Key from above', 'lynx-journal')}</li>
          <li>{__('Click Save', 'lynx-journal')}</li>
          <li>{__('Now you can save links directly from any webpage!', 'lynx-journal')}</li>
        </ol>
      </div>
    </>
  );
}
