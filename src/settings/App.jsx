import { useState, useEffect } from "@wordpress/element";
import apiFetch from "@wordpress/api-fetch";
import {
  Button,
  Notice,
  Snackbar,
  CheckboxControl,
  TextControl,
  Card,
  CardHeader,
  CardBody,
} from "@wordpress/components";
import { __ } from "@wordpress/i18n";

const DEFAULT_NOTIFY = {
  enabled: false,
  email: "",
  discord_webhook: "",
  slack_webhook: "",
  telegram_bot_token: "",
  telegram_chat_id: "",
};

function FetchChatIdButton({ token, onFetched, onError }) {
  const [busy, setBusy] = useState(false);

  function handleFetch() {
    setBusy(true);
    apiFetch({ path: "/linkdigest/v1/notify/telegram-chat-id", method: "POST", data: { token } })
      .then((res) => onFetched(res.chat_id))
      .catch((err) => onError(err.message || __("Failed to fetch chat ID.", "linkdigest")))
      .finally(() => setBusy(false));
  }

  return (
    <div className="linkdigest-test-wrap">
      <Button variant="secondary" onClick={handleFetch} isBusy={busy} disabled={!token || busy}>
        {__("Fetch Chat ID", "linkdigest")}
      </Button>
    </div>
  );
}

function TestButton({ type, value, disabled, onResult, data = {} }) {
  const [busy, setBusy] = useState(false);

  function handleTest() {
    setBusy(true);
    apiFetch({ path: "/linkdigest/v1/notify/test", method: "POST", data: { type, value, ...data } })
      .then(() => onResult(__("Test message sent.", "linkdigest")))
      .catch((err) => onResult(err.message || __("Test failed.", "linkdigest")))
      .finally(() => setBusy(false));
  }

  return (
    <div className="linkdigest-test-wrap">
      <Button variant="secondary" onClick={handleTest} isBusy={busy} disabled={disabled || busy}>
        {__("Test", "linkdigest")}
      </Button>
    </div>
  );
}

export default function App() {
  const [notify, setNotify] = useState(DEFAULT_NOTIFY);
  const [saving, setSaving] = useState(false);
  const [notice, setNotice] = useState(null);
  const [snackbar, setSnackbar] = useState(null);

  useEffect(() => {
    apiFetch({ path: "/linkdigest/v1/notify" })
      .then((data) => setNotify({ ...DEFAULT_NOTIFY, ...data }))
      .catch(() => {});
  }, []);

  function handleSave() {
    setSaving(true);
    setNotice(null);
    apiFetch({ path: "/linkdigest/v1/notify", method: "POST", data: notify })
      .then(() => setSnackbar(__("Settings saved.", "linkdigest")))
      .catch((err) =>
        setNotice({ status: "error", message: err.message || __("Save failed.", "linkdigest") }),
      )
      .finally(() => setSaving(false));
  }

  return (
    <div className="linkdigest-settings-wrap">
      {notice && (
        <Notice status={notice.status} isDismissible onRemove={() => setNotice(null)}>
          {notice.message}
        </Notice>
      )}

      <Card>
        <CardHeader>
          <strong>{__("Email", "linkdigest")}</strong>
        </CardHeader>
        <CardBody>
          <CheckboxControl
            label={__("Email me after each run", "linkdigest")}
            checked={notify.enabled}
            onChange={(enabled) => setNotify((n) => ({ ...n, enabled }))}
            __nextHasNoMarginBottom
          />
          <div className="linkdigest-field-mt">
            <div className="linkdigest-field-row">
              <TextControl
                label={__("Email address", "linkdigest")}
                type="email"
                value={notify.email}
                placeholder={__("Leave blank to use admin email", "linkdigest")}
                onChange={(email) => setNotify((n) => ({ ...n, email }))}
                __nextHasNoMarginBottom
              />
              <TestButton
                type="email"
                value={notify.email}
                disabled={false}
                onResult={setSnackbar}
              />
            </div>
          </div>
        </CardBody>
      </Card>

      <div className="linkdigest-card-mt">
        <Card>
          <CardHeader>
            <strong>{__("Webhooks", "linkdigest")}</strong>
          </CardHeader>
          <CardBody>
            <div className="linkdigest-field-row">
              <TextControl
                label={__("Discord Webhook URL", "linkdigest")}
                type="url"
                value={notify.discord_webhook}
                placeholder="https://discord.com/api/webhooks/…"
                onChange={(discord_webhook) => setNotify((n) => ({ ...n, discord_webhook }))}
                __nextHasNoMarginBottom
              />
              <TestButton
                type="discord"
                value={notify.discord_webhook}
                disabled={!notify.discord_webhook}
                onResult={setSnackbar}
              />
            </div>
            <div className="linkdigest-field-mt">
              <div className="linkdigest-field-row">
                <TextControl
                  label={__("Slack Webhook URL", "linkdigest")}
                  type="url"
                  value={notify.slack_webhook}
                  placeholder="https://hooks.slack.com/services/…"
                  onChange={(slack_webhook) => setNotify((n) => ({ ...n, slack_webhook }))}
                  __nextHasNoMarginBottom
                />
                <TestButton
                  type="slack"
                  value={notify.slack_webhook}
                  disabled={!notify.slack_webhook}
                  onResult={setSnackbar}
                />
              </div>
            </div>
          </CardBody>
        </Card>
      </div>

      <div className="linkdigest-card-mt">
        <Card>
          <CardHeader>
            <strong>{__("Telegram", "linkdigest")}</strong>
          </CardHeader>
          <CardBody>
            <div className="linkdigest-field-row">
              <TextControl
                label={__("Bot Token", "linkdigest")}
                type="text"
                value={notify.telegram_bot_token}
                placeholder="1234567890:ABC-DEF…"
                onChange={(telegram_bot_token) => setNotify((n) => ({ ...n, telegram_bot_token }))}
                __nextHasNoMarginBottom
              />
              <FetchChatIdButton
                token={notify.telegram_bot_token}
                onFetched={(chat_id) => setNotify((n) => ({ ...n, telegram_chat_id: chat_id }))}
                onError={setSnackbar}
              />
            </div>
            <p className="linkdigest-hint">
              {__("Before fetching, open Telegram and send any message to your bot.", "linkdigest")}
            </p>
            <div className="linkdigest-field-mt">
              <div className="linkdigest-field-row">
                <TextControl
                  label={__("Chat ID", "linkdigest")}
                  type="text"
                  value={notify.telegram_chat_id}
                  placeholder="-1001234567890"
                  onChange={(telegram_chat_id) => setNotify((n) => ({ ...n, telegram_chat_id }))}
                  __nextHasNoMarginBottom
                />
                <TestButton
                  type="telegram"
                  value={notify.telegram_bot_token}
                  data={{
                    telegram_bot_token: notify.telegram_bot_token,
                    telegram_chat_id: notify.telegram_chat_id,
                  }}
                  disabled={!notify.telegram_bot_token || !notify.telegram_chat_id}
                  onResult={setSnackbar}
                />
              </div>
            </div>
          </CardBody>
        </Card>
      </div>

      <div className="linkdigest-settings-actions">
        <Button variant="primary" onClick={handleSave} isBusy={saving} disabled={saving}>
          {__("Save Settings", "linkdigest")}
        </Button>
      </div>

      {snackbar && (
        <div className="linkdigest-snackbar-region">
          <Snackbar onRemove={() => setSnackbar(null)}>{snackbar}</Snackbar>
        </div>
      )}
    </div>
  );
}
