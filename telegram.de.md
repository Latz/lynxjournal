# Telegram-Benachrichtigungen — Benutzerhandbuch

LynxJournal kann bei jedem geplanten Roundup-Lauf eine Telegram-Nachricht
senden, über einen Telegram-Bot. Dies funktioniert unabhängig von (und
zusätzlich zu) den bestehenden E-Mail-, Discord- und
Slack-Benachrichtigungen.

## Wo man es findet

Gehe im WordPress-Adminmenü zu **LynxJournal → Zeitplan**. Im Abschnitt
**Benachrichtigungen**, unterhalb der bestehenden
E-Mail-/Discord-/Slack-Optionen, findest du zwei unabhängige
Telegram-Ziele, die sich ein Bot-Token teilen:

- **Nach jedem Lauf in eine Telegram-Gruppe oder einen Kanal posten** —
  Checkbox, um das Posten in eine Gruppe oder einen Kanal zu aktivieren.
- **Telegram-Bot-Token** — das Token für deinen Bot (wird von beiden
  Zielen unten gemeinsam genutzt).
- **Telegram-Gruppen-/Kanal-Chat-ID** — die Gruppe oder der Kanal, an den
  der Bot posten soll.
- **Mir nach jedem Lauf eine Telegram-DM senden** — Checkbox, um eine
  persönliche Direktnachricht an dich zu aktivieren.
- **Telegram-Benutzer-Chat-ID** — deine persönliche Chat-ID für die DM.

Beide Ziele können gleichzeitig aktiviert werden, jeweils mit eigenem
Test- und Speichern-Button.

## Erstellen eines Telegram-Bots

Du benötigst ein Bot-Token und eine Chat-ID, um Benachrichtigungen zu
empfangen:

1. Schreibe in Telegram eine Nachricht an **@BotFather** und führe
   `/newbot` aus.
2. Folge den Anweisungen, um deinen Bot zu benennen. BotFather antwortet
   mit einem Bot-Token, das wie `123456789:AAH...` aussieht.
3. Ermittle die Chat-ID, an die Nachrichten gesendet werden sollen:
   - **Persönlicher Chat**: Sende deinem neuen Bot eine beliebige
     Nachricht, rufe dann `https://api.telegram.org/bot<TOKEN>/getUpdates`
     im Browser auf und lies den Wert `message.chat.id` aus der
     JSON-Antwort — oder schreibe einen Hilfsbot wie **@userinfobot** an,
     um deine eigene Benutzer-ID direkt zu erhalten.
   - **Gruppe oder Kanal**: Füge den Bot zunächst der Gruppe/dem Kanal
     hinzu (bei Kanälen muss der Bot als Administrator hinzugefügt
     werden), sende eine Nachricht und prüfe dann `getUpdates` auf die
     gleiche Weise. Gruppen-/Kanal-Chat-IDs sind negative Zahlen, z. B.
     `-1001234567890`.

## Aktivieren

**Gruppen-/Kanal-Ziel:**
1. Aktiviere **Nach jedem Lauf in eine Telegram-Gruppe oder einen Kanal
   posten**.
2. Füge das Bot-Token in das Feld **Telegram-Bot-Token** ein.
3. Füge die Chat-ID in das Feld **Telegram-Gruppen-/Kanal-Chat-ID** ein.
4. Klicke auf **Zeitplan speichern** (oder den eigenen
   **Speichern**-Button des Ziels).

**Persönliches DM-Ziel:**
1. Aktiviere **Mir nach jedem Lauf eine Telegram-DM senden**.
2. Füge das Bot-Token in das Feld **Telegram-Bot-Token** ein (dasselbe
   Feld wie oben — ein Token bedient beide Ziele).
3. Füge deine persönliche Chat-ID in das Feld
   **Telegram-Benutzer-Chat-ID** ein.
4. Klicke auf **Zeitplan speichern** (oder den eigenen
   **Speichern**-Button des Ziels).

Wenn eine Checkbox aktiviert bleibt, aber ihr Chat-ID-Feld leer ist (oder
Werte eingefügt werden, die nicht wie ein echtes Bot-Token/eine echte
Chat-ID aussehen), schlägt das Speichern mit einem Validierungsfehler
fehl und nichts wird gespeichert — korrigiere die Werte und speichere
erneut.

### Akzeptierte Formate

- **Bot-Token**: eine numerische Bot-ID, ein Doppelpunkt und ein Geheimnis,
  z. B. `123456789:AAAbbbCCCdddEEEfffGGGhhh`.
- **Chat-ID**: eine Ganzzahl — negativ für Gruppen/Kanäle, positiv für
  einen persönlichen Chat. Sowohl das Gruppen-/Kanal- als auch das
  DM-Chat-ID-Feld akzeptieren beide Formen; Telegram hat kein
  strukturelles Merkmal, das sie unterscheidet.

Alles andere wird beim Speichern abgelehnt.

## Wann es ausgelöst wird

Die Telegram-Nachricht wird zum selben Zeitpunkt ausgelöst wie die
anderen Benachrichtigungen: direkt nachdem ein geplanter Lauf
**tatsächlich einen Roundup-Beitrag erstellt oder zu erstellen
versucht**. Dies geschieht nur, wenn die Auslösebedingung des Zeitplans
erfüllt ist (z. B. Tagesmodus mit mindestens einem ausstehenden Link,
oder Anzahl-/Alter-Modus, sobald genügend Links angesammelt wurden).
Wenn ein Lauf stattfindet, aber noch nichts zu veröffentlichen ist
(keine ausstehenden Links, oder ein Anzahl-/Alter-Auslöser noch nicht
erreicht), wird **überhaupt keine Benachrichtigung gesendet** — es gibt
schlicht nichts zu berichten. Es handelt sich um einen separaten,
unabhängigen Schalter — kombiniere E-Mail, Discord, Slack und Telegram
ganz nach Belieben.

## Wie die Nachricht aussieht

- **Wenn ein Roundup veröffentlicht wurde**: ein fett gedruckter
  Beitragstitel, eine Zeile mit der Anzahl der enthaltenen Links und die
  URL des Beitrags in eigener Zeile (Telegram zeigt automatisch eine
  Linkvorschau an).
- **Wenn ein Lauf den Punkt der Beitragserstellung erreicht hat, diese
  Erstellung aber fehlgeschlagen ist**: eine einfache Nachricht erklärt,
  dass der Zeitplan in diesem Modus gelaufen ist, aber kein Beitrag
  veröffentlicht wurde. Dies ist selten und unterscheidet sich von "noch
  keine ausstehenden Links", wobei überhaupt nichts gesendet wird (siehe
  oben).

## Wenn etwas schiefgeht

Das Senden an Telegram erfolgt nach dem Prinzip "fire and forget": Wenn
das Bot-Token nicht mehr gültig ist, der Bot nicht angeschrieben/dem
Ziel-Chat hinzugefügt wurde, Telegram nicht erreichbar ist oder die
Anfrage anderweitig fehlschlägt, gibt es keine Wiederholung — aber im
WordPress-Adminbereich erscheint ein schließbarer Fehlerhinweis, der das
Telegram-Kanal-/DM-Ziel und den Grund nennt, sodass der Fehlschlag nicht
unbemerkt bleibt. Wichtig: Dies beeinträchtigt niemals die
Roundup-Veröffentlichung selbst; eine fehlgeschlagene
Telegram-Benachrichtigung blockiert oder unterbricht niemals einen
geplanten Lauf. Wenn keine Nachrichten mehr ankommen:
- prüfe, ob das Bot-Token noch gültig ist (Tokens können über
  @BotFather widerrufen werden),
- prüfe, ob die Chat-ID korrekt ist,
- stelle bei einem persönlichen Chat sicher, dass du dem Bot mindestens
  eine Nachricht gesendet hast,
- stelle bei einer Gruppe oder einem Kanal sicher, dass der Bot noch
  Mitglied ist (und bei Kanälen weiterhin Administrator).
