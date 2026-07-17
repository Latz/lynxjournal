# Slack-Benachrichtigungen — Benutzerhandbuch

LynxJournal kann Slack bei jedem geplanten Roundup-Lauf benachrichtigen,
über ein Slack Bot Token. Zwei unabhängige Ziele werden mit demselben
Token unterstützt: eine **Kanalnachricht** und eine **persönliche
Nachricht** (DM an eine bestimmte Person). Dies funktioniert zusätzlich
zu den bestehenden E-Mail- und Discord-Benachrichtigungen.

## Wo man es findet

Gehe im WordPress-Adminmenü zu **LynxJournal → Zeitplan**. Im Abschnitt
**Benachrichtigungen** öffne den Tab **Slack**, wo du Folgendes findest:

- **Slack Bot Token** — immer sichtbar.
- **Nach jedem Lauf in einen Slack-Kanal posten** — Checkbox, mit einem
  Feld **Slack-Kanal-ID** darunter.
- **Mir nach jedem Lauf eine Slack-DM senden** — Checkbox, mit einem Feld
  **Slack-Benutzer-ID** darunter.

Die Felder für Kanal-ID und Benutzer-ID sind ebenfalls immer sichtbar;
sie werden nur benötigt, wenn die zugehörige Checkbox aktiviert ist.

Beide können gleichzeitig und unabhängig voneinander aktiviert werden —
nur Kanal, nur DM, beides oder keins.

## Warum ein Bot-Token statt eines Webhooks

Discord-Benachrichtigungen verwenden eine Webhook-URL, die immer an einen
festen Ort postet. Slack-Benachrichtigungen müssen zwei verschiedene,
unabhängig konfigurierbare Ziele erreichen (einen Kanal *und* eine
bestimmte Person). Daher verwendet LynxJournal stattdessen ein **Slack
Bot Token** mit Slacks `chat.postMessage`-API — ein Token kann in jeden
Kanal oder jede DM posten, auf die der Bot Zugriff hat.

## Einrichten eines Slack Bot Tokens

Du benötigst eine Slack-App mit einem Bot-Token, bevor du eine der beiden
Optionen aktivieren kannst:

1. Gehe zu [api.slack.com/apps](https://api.slack.com/apps) und klicke auf
   **Create New App** → **From scratch**. Benenne sie (z. B.
   "LynxJournal") und wähle dein Workspace aus.
   - Slack fordert dich möglicherweise auf, ein **App-Level Token** zu
     generieren (unter **Basic Information → App-Level Tokens**) mit
     Scopes wie `connections:write`. Das ist für Socket-Mode-/
     WebSocket-Funktionen gedacht und wird hier nicht benötigt —
     überspringen/schließen. LynxJournal ruft nur Slacks Web-API über
     einfaches HTTPS auf.
2. Unter **OAuth & Permissions** scrolle zu **Scopes → Bot Token Scopes**
   und füge hinzu:
   - `chat:write` — erforderlich für Kanal- und DM-Nachrichten.
3. Klicke auf **Install to Workspace** (oben auf der
   OAuth-&-Permissions-Seite) und bestätige.
4. Kopiere das **Bot User OAuth Token** — es beginnt mit `xoxb-`.
5. **Für die Kanalnachricht**: Lade den Bot in den Zielkanal in Slack ein
   (`/invite @DeinAppName` in diesem Kanal) und ermittle die Kanal-ID (in
   Slack: Kanal öffnen → **Kanaldetails anzeigen** → die ID wird unten
   angezeigt, oder in der URL des Kanals).
6. **Für die DM**: Keine Einladung nötig, aber die Slack-Benutzer-ID des
   Empfängers wird benötigt (in Slack: Profil öffnen → **Mehr** →
   **Mitglieds-ID kopieren**).

## Aktivieren

1. Füge das Bot-Token in **Slack Bot Token** ein (es wird wie ein
   Passwortfeld maskiert, da es weitreichenderen Zugriff gewährt als ein
   einzelner Kanal-Webhook und von beiden Optionen unten gemeinsam
   genutzt wird).
2. Um in einen Kanal zu posten: aktiviere **Nach jedem Lauf in einen
   Slack-Kanal posten** und füge die Kanal-ID ein.
3. Um jemandem eine DM zu senden: aktiviere **Mir nach jedem Lauf eine
   Slack-DM senden** und füge dessen Benutzer-ID ein.
4. Klicke auf **Zeitplan speichern**.

Wenn eine Checkbox aktiviert ist, aber das zugehörige Pflichtfeld (Token
oder die Kanal-/Benutzer-ID) fehlt oder ungültig ist, schlägt das
Speichern mit einem Validierungsfehler fehl — nichts wird gespeichert,
bis das Problem behoben ist.

### Akzeptierte ID-Formate

- **Bot-Token**: muss mit `xoxb-` beginnen.
- **Kanal-ID**: beginnt mit `C` (öffentlicher Kanal) oder `G` (privater
  Kanal), z. B. `C0123456789`.
- **Benutzer-ID**: beginnt mit `U`, z. B. `U0123456789`.

Kanal-/DM-Namen (wie `#general` oder `@jemand`) werden **nicht**
akzeptiert — Slack-IDs sind erforderlich, keine Anzeigenamen.

## Wann es ausgelöst wird

Derselbe Auslösepunkt wie bei E-Mail und Discord: direkt nachdem ein
geplanter Lauf **tatsächlich einen Roundup-Beitrag erstellt oder zu
erstellen versucht**. Dies geschieht nur, wenn die Auslösebedingung des
Zeitplans erfüllt ist (z. B. Tagesmodus mit mindestens einem
ausstehenden Link, oder Anzahl-/Alter-Modus, sobald genügend Links
angesammelt wurden). Wenn ein Lauf stattfindet, aber noch nichts zu
veröffentlichen ist, wird **überhaupt keine Slack-Nachricht gesendet** —
es gibt schlicht nichts zu berichten.

## Wie die Nachricht aussieht

LynxJournal sendet eine Slack-Block-Kit-Nachricht (kein reiner Text):

- **Wenn ein Roundup veröffentlicht wurde**: eine Kopfzeile mit dem Titel
  des Beitrags, ein Abschnitt mit einem "Beitrag ansehen"-Link zum
  veröffentlichten Beitrag und eine Zeile mit der Anzahl der Links und
  dem ausgeführten Zeitplanmodus.
- **Wenn ein Lauf den Punkt der Beitragserstellung erreicht hat, diese
  Erstellung aber fehlgeschlagen ist** (selten): eine Kopfzeile, die
  vermerkt, dass der Zeitplan gelaufen ist, mit einer Zeile, die erklärt,
  dass kein Beitrag veröffentlicht wurde. Dies unterscheidet sich von
  "noch keine ausstehenden Links", wobei überhaupt nichts gesendet wird
  (siehe oben).

Wenn sowohl die Kanal- als auch die DM-Option aktiviert sind, erhältst
du zwei separate Nachrichten — eine an den Kanal, eine als DM — für
denselben Lauf.

## Wenn etwas schiefgeht

Das Senden an Slack erfolgt nach dem Prinzip "fire and forget": Wenn das
Bot-Token widerrufen wurde, die Kanal-/Benutzer-ID falsch ist, der Bot
nicht im Kanal ist oder die Anfrage anderweitig fehlschlägt, gibt es keine
Wiederholung — aber im WordPress-Adminbereich erscheint ein schließbarer
Fehlerhinweis, der das Slack-Kanal-/DM-Ziel und den Grund nennt, sodass der
Fehlschlag nicht unbemerkt bleibt. Dies beeinträchtigt niemals die
Roundup-Veröffentlichung selbst; eine fehlgeschlagene
Slack-Benachrichtigung blockiert oder unterbricht niemals einen
geplanten Lauf.

Häufige Ursachen für eine fehlende Nachricht:
- Der Bot wurde nicht in den Zielkanal eingeladen (nur bei
  Kanalnachrichten — DMs benötigen keine Einladung).
- Das Bot-Token wurde in den Slack-App-Einstellungen neu generiert oder
  widerrufen.
- Die Kanal- oder Benutzer-ID wurde falsch eingegeben oder aus dem
  falschen Workspace kopiert.

Wenn keine Nachrichten mehr ankommen, überprüfe, ob der Bot noch im
Kanal ist und ob Token/IDs aktuell sind, und speichere dann die
Zeitplan-Seite erneut.
