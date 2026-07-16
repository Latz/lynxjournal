# Bluesky-Benachrichtigungen — Benutzerhandbuch

LynxJournal kann dir bei jedem geplanten Roundup-Lauf eine private
Bluesky-Direktnachricht senden. Dies funktioniert unabhängig von (und
zusätzlich zu) den bestehenden E-Mail-, Discord-, Slack-, Telegram- und
Mastodon-Benachrichtigungen.

## Wo man es findet

Gehe im WordPress-Adminmenü zu **LynxJournal → Zeitplan**. Im Abschnitt
**Benachrichtigungen**, neben den bestehenden Kanälen, findest du:

- **Nach jedem Lauf eine Bluesky-Direktnachricht senden** — Checkbox zum
  Aktivieren.
- **Bluesky-Handle** — das Konto, *von dem* die Nachricht gesendet wird.
- **Bluesky-App-Passwort** — ein eigenes App-Passwort für dieses Konto.
- **Empfänger-Handle** — an wen die Nachricht adressiert ist (meist du
  selbst).

## Erstellen eines App-Passworts

1. Öffne die Bluesky-App und gehe zu **Einstellungen → App-Passwörter**.
2. Klicke auf **App-Passwort hinzufügen**.
3. Gib ihm einen eindeutigen Namen, z. B. "LynxJournal Digest Alerts".
4. Bluesky generiert ein Passwort im Format `xxxx-xxxx-xxxx-xxxx` —
   kopiere es. Nachdem du diesen Bildschirm verlassen hast, kannst du es
   nicht erneut einsehen.

## Aktivieren

1. Aktiviere **Nach jedem Lauf eine Bluesky-Direktnachricht senden**.
2. Gib das Handle des sendenden Kontos (z. B. `du.bsky.social`) in
   **Bluesky-Handle** ein.
3. Füge das App-Passwort in **Bluesky-App-Passwort** ein.
4. Gib das Handle ein, an das gesendet werden soll (z. B.
   `freund.bsky.social`) in **Empfänger-Handle**.
5. Klicke auf **Zeitplan speichern**.

Wenn die Checkbox aktiviert bleibt, aber ein Feld leer ist (oder Werte
eingefügt werden, die ungültig aussehen), schlägt das Speichern mit
einem Validierungsfehler fehl und nichts wird gespeichert — korrigiere
die Werte und speichere erneut.

### Akzeptierte Formate

- **Bluesky-Handle** / **Empfänger-Handle**: ein reines Handle, z. B.
  `du.bsky.social` (kein `@`-Präfix).
- **App-Passwort**: genau vier Vierergruppen von Zeichen, getrennt durch
  Bindestriche, z. B. `aaaa-bbbb-cccc-dddd`.

Alles andere wird beim Speichern abgelehnt.

## Wann es ausgelöst wird

Die Bluesky-Nachricht wird zum selben Zeitpunkt ausgelöst wie die
anderen Benachrichtigungen: direkt nachdem ein geplanter Lauf
**tatsächlich einen Roundup-Beitrag erstellt oder zu erstellen
versucht**. Dies geschieht nur, wenn die Auslösebedingung des Zeitplans
erfüllt ist (z. B. Tagesmodus mit mindestens einem ausstehenden Link,
oder Anzahl-/Alter-Modus, sobald genügend Links angesammelt wurden).
Wenn ein Lauf stattfindet, aber noch nichts zu veröffentlichen ist
(keine ausstehenden Links, oder ein Anzahl-/Alter-Auslöser noch nicht
erreicht), wird **überhaupt keine Benachrichtigung gesendet** — es gibt
schlicht nichts zu berichten. Es handelt sich um einen separaten,
unabhängigen Schalter — kombiniere E-Mail, Discord, Slack, Telegram,
Mastodon und Bluesky ganz nach Belieben.

## Wie die Nachricht aussieht

Bluesky's Chat-API sendet eine echte private Direktnachricht — anders
als bei Mastodon erscheint sie niemals in der öffentlichen Timeline des
sendenden Kontos. LynxJournal schreibt dein konfiguriertes
Empfänger-Handle direkt an:

- **Wenn ein Roundup veröffentlicht wurde**: der Titel des Beitrags, eine
  Zeile mit der Anzahl der enthaltenen Links und die URL des Beitrags.
- **Wenn ein Lauf den Punkt der Beitragserstellung erreicht hat, diese
  Erstellung aber fehlgeschlagen ist**: eine einfache Nachricht erklärt,
  dass der Zeitplan in diesem Modus gelaufen ist, aber kein Beitrag
  veröffentlicht wurde. Dies ist selten und unterscheidet sich von "noch
  keine ausstehenden Links", wobei überhaupt nichts gesendet wird (siehe
  oben).

## Wenn etwas schiefgeht

Das Senden an Bluesky erfolgt nach dem Prinzip "fire and forget": Wenn
das App-Passwort nicht mehr gültig ist, das Empfänger-Handle nicht
aufgelöst werden kann oder die Anfrage bei irgendeinem Schritt des
Handshakes anderweitig fehlschlägt, gibt es keine Wiederholung — aber im
WordPress-Adminbereich erscheint ein schließbarer Fehlerhinweis, der den
Bluesky-Kanal und den Grund nennt, sodass der Fehlschlag nicht unbemerkt
bleibt. Wichtig: Dies beeinträchtigt niemals die
Roundup-Veröffentlichung selbst; eine fehlgeschlagene
Bluesky-Benachrichtigung blockiert oder unterbricht niemals einen
geplanten Lauf. Wenn keine Nachrichten mehr ankommen:
- prüfe, ob das App-Passwort nicht widerrufen wurde (siehe
  **Einstellungen → App-Passwörter** in der Bluesky-App),
- prüfe, ob das sendende Handle korrekt geschrieben ist,
- prüfe, ob das Empfänger-Handle exakt richtig geschrieben ist — ein
  Tippfehler dort bedeutet, dass das Handle keinem Konto zugeordnet
  werden kann und die Nachricht nie gesendet wird.
