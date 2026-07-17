# Mastodon-Benachrichtigungen — Benutzerhandbuch

LynxJournal kann dir bei jedem geplanten Roundup-Lauf eine private
Mastodon-Direktnachricht senden. Dies funktioniert unabhängig von (und
zusätzlich zu) den bestehenden E-Mail-, Discord-, Slack- und
Telegram-Benachrichtigungen.

## Wo man es findet

Gehe im WordPress-Adminmenü zu **LynxJournal → Zeitplan**. Im Abschnitt
**Benachrichtigungen**, unterhalb der bestehenden
E-Mail-/Discord-/Slack-/Telegram-Optionen, findest du:

- **Nach jedem Lauf eine Mastodon-Direktnachricht senden** — Checkbox zum
  Aktivieren.
- **Mastodon-Instanz-URL** — der Server, auf dem dein Konto liegt.
- **Mastodon-Zugriffstoken** — von einer App, die du auf dieser Instanz
  erstellst.
- **Empfänger-Handle** — an wen die Nachricht adressiert ist (meist du
  selbst).

## Erstellen einer Mastodon-Anwendung

1. Melde dich bei deiner Mastodon-Instanz an und gehe zu
   **Einstellungen → Entwicklung → Neue Anwendung**.
2. Benenne sie z. B. "LynxJournal".
3. Aktiviere unter **Berechtigungen (Scopes)** nur **write:statuses** —
   keine weiteren Berechtigungen werden benötigt, und es ist gute
   Praxis, alles andere zu deaktivieren.
4. Speichere die Anwendung, öffne sie erneut und kopiere das
   **Zugriffstoken (Access Token)**. Notiere dir außerdem die URL deiner
   Instanz (z. B. `https://mastodon.social`).

## Aktivieren

1. Aktiviere **Nach jedem Lauf eine Mastodon-Direktnachricht senden**.
2. Füge deine Instanz-URL in das Feld **Mastodon-Instanz-URL** ein.
3. Füge das Zugriffstoken in das Feld **Mastodon-Zugriffstoken** ein.
4. Gib das Handle ein, an das gesendet werden soll (z. B.
   `@du@mastodon.social`) im Feld **Empfänger-Handle**.
5. Klicke auf **Zeitplan speichern**.

Wenn die Checkbox aktiviert bleibt, aber ein Feld leer ist (oder Werte
eingefügt werden, die ungültig aussehen), schlägt das Speichern mit
einem Validierungsfehler fehl und nichts wird gespeichert — korrigiere
die Werte und speichere erneut.

### Akzeptierte Formate

- **Instanz-URL**: muss mit `https://` beginnen und einen Host enthalten,
  z. B. `https://mastodon.social`. Jede föderierte Mastodon-Instanz
  funktioniert.
- **Empfänger-Handle**: ein vollständiges Fediverse-Handle mit beiden
  Teilen, z. B. `@du@mastodon.social`.

Alles andere wird beim Speichern abgelehnt.

## Wann es ausgelöst wird

Die Mastodon-Nachricht wird zum selben Zeitpunkt ausgelöst wie die
anderen Benachrichtigungen: direkt nachdem ein geplanter Lauf
**tatsächlich einen Roundup-Beitrag erstellt oder zu erstellen
versucht**. Dies geschieht nur, wenn die Auslösebedingung des Zeitplans
erfüllt ist (z. B. Tagesmodus mit mindestens einem ausstehenden Link,
oder Anzahl-/Alter-Modus, sobald genügend Links angesammelt wurden).
Wenn ein Lauf stattfindet, aber noch nichts zu veröffentlichen ist
(keine ausstehenden Links, oder ein Anzahl-/Alter-Auslöser noch nicht
erreicht), wird **überhaupt keine Benachrichtigung gesendet** — es gibt
schlicht nichts zu berichten. Es handelt sich um einen separaten,
unabhängigen Schalter — kombiniere E-Mail, Discord, Slack, Telegram und
Mastodon ganz nach Belieben.

## Wie die Nachricht aussieht

Mastodon hat kein separates DM-System — eine "Direktnachricht" ist
einfach ein normaler Status-Beitrag, dessen Sichtbarkeit auf die darin
erwähnten Personen beschränkt ist. LynxJournal veröffentlicht einen
Status, der an dein konfiguriertes Empfänger-Handle adressiert ist:

- **Wenn ein Roundup veröffentlicht wurde**: der Titel des Beitrags, eine
  Zeile mit der Anzahl der enthaltenen Links und die URL des Beitrags.
- **Wenn ein Lauf den Punkt der Beitragserstellung erreicht hat, diese
  Erstellung aber fehlgeschlagen ist**: eine einfache Nachricht erklärt,
  dass der Zeitplan in diesem Modus gelaufen ist, aber kein Beitrag
  veröffentlicht wurde. Dies ist selten und unterscheidet sich von "noch
  keine ausstehenden Links", wobei überhaupt nichts gesendet wird (siehe
  oben).

Da es sich lediglich um einen sichtbarkeitsbeschränkten Beitrag und
keinen echten privaten Kanal handelt, ist dies **nicht
Ende-zu-Ende-verschlüsselt** — behandle es genauso wie jedes andere
automatisierte Status-Update.

## Wenn etwas schiefgeht

Das Senden an Mastodon erfolgt nach dem Prinzip "fire and forget": Wenn
das Zugriffstoken nicht mehr gültig ist, die Instanz nicht erreichbar
ist oder die Anfrage anderweitig fehlschlägt, gibt es keine Wiederholung —
aber im WordPress-Adminbereich erscheint ein schließbarer Fehlerhinweis,
der den Mastodon-Kanal und den Grund nennt, sodass der Fehlschlag nicht
unbemerkt bleibt. Wichtig: Dies beeinträchtigt niemals die
Roundup-Veröffentlichung selbst; eine fehlgeschlagene
Mastodon-Benachrichtigung blockiert oder unterbricht niemals einen
geplanten Lauf. Wenn keine Nachrichten mehr ankommen:
- prüfe, ob das Zugriffstoken nicht widerrufen wurde (siehe
  **Einstellungen → Entwicklung** auf deiner Instanz),
- prüfe, ob die Instanz-URL korrekt und die Instanz erreichbar ist,
- prüfe, ob das Empfänger-Handle exakt richtig geschrieben ist,
  einschließlich Benutzername und Instanz-Domain.
