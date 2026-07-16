# Discord-Benachrichtigungen — Benutzerhandbuch

LynxJournal kann bei jedem geplanten Roundup-Lauf eine Nachricht an einen
Discord-Kanal senden, über einen Discord-Webhook. Dies funktioniert
unabhängig von (und zusätzlich zu) der bestehenden E-Mail-Benachrichtigung.

## Wo man es findet

Gehe im WordPress-Adminmenü zu **LynxJournal → Zeitplan**. Im Abschnitt
**Benachrichtigungen**, unterhalb der bestehenden Option "Mich nach jedem
Lauf per E-Mail benachrichtigen", findest du:

- **Nach jedem Lauf eine Discord-Benachrichtigung senden** — Checkbox zum
  Aktivieren.
- **Discord-Webhook-URL** — erscheint, sobald die obige Checkbox aktiviert
  ist.

## Erstellen eines Discord-Webhooks

Du benötigst eine Webhook-URL aus dem Discord-Kanal, an den
Benachrichtigungen gesendet werden sollen:

1. Öffne in Discord **Servereinstellungen → Integrationen → Webhooks**.
2. Klicke auf **Neuer Webhook**, wähle den Kanal aus, an den er posten
   soll, und benenne/bebildere ihn optional um.
3. Klicke auf **Webhook-URL kopieren**.

Die URL sieht so aus:
`https://discord.com/api/webhooks/123456789012345678/AbCdEf...`

## Aktivieren

1. Aktiviere **Nach jedem Lauf eine Discord-Benachrichtigung senden**.
2. Füge die kopierte Webhook-URL in das Feld **Discord-Webhook-URL** ein.
3. Klicke auf **Zeitplan speichern**.

Wenn die Checkbox aktiviert bleibt, das URL-Feld aber leer ist (oder etwas
eingefügt wird, das keine echte Discord-Webhook-URL ist), schlägt das
Speichern mit einem Validierungsfehler fehl und nichts wird gespeichert —
korrigiere die URL und speichere erneut.

### Akzeptiertes URL-Format

Die URL muss:
- mit `https://` beginnen
- auf `discord.com`, `discordapp.com`, `ptb.discord.com` oder
  `canary.discord.com` verweisen
- einen Pfad in der Form `/api/webhooks/{id}/{token}` haben (ein
  optionales API-Versionssegment wie `/api/v10/webhooks/...` wird
  ebenfalls akzeptiert)

Alles andere wird beim Speichern abgelehnt.

## Wann es ausgelöst wird

Die Discord-Nachricht wird zum selben Zeitpunkt ausgelöst wie die
E-Mail-Benachrichtigung: direkt nachdem ein geplanter Lauf **tatsächlich
einen Roundup-Beitrag erstellt oder zu erstellen versucht**. Dies
geschieht nur, wenn die Auslösebedingung des Zeitplans erfüllt ist (z. B.
Tagesmodus mit mindestens einem ausstehenden Link, oder
Anzahl-/Alter-Modus, sobald genügend Links angesammelt wurden). Wenn ein
Lauf stattfindet, aber noch nichts zu veröffentlichen ist (keine
ausstehenden Links, oder ein Anzahl-/Alter-Auslöser noch nicht erreicht),
wird **überhaupt keine Benachrichtigung gesendet** — es gibt schlicht
nichts zu berichten. Es handelt sich um einen separaten, unabhängigen
Schalter — du kannst gleichzeitig nur E-Mail, nur Discord, beides oder
keins aktiviert haben.

## Wie die Nachricht aussieht

LynxJournal postet ein reichhaltiges Embed, keine reine Textnachricht:

- **Wenn ein Roundup veröffentlicht wurde**: Der Embed-Titel ist der Titel
  des Beitrags (verlinkt zum veröffentlichten Beitrag), mit einer
  Beschreibung, wie viele Links enthalten waren, und Feldern, die die
  Anzahl der Links und den ausgeführten Zeitplanmodus anzeigen. Es ist in
  Discords "Blurple" eingefärbt.
- **Wenn ein Lauf den Punkt der Beitragserstellung erreicht hat, diese
  Erstellung aber fehlgeschlagen ist**: Ein neutral graues Embed erklärt,
  dass der Zeitplan in diesem Modus gelaufen ist, aber kein Beitrag
  veröffentlicht wurde. Dies ist selten und unterscheidet sich von "noch
  keine ausstehenden Links", wobei überhaupt nichts gesendet wird (siehe
  oben).

## Wenn etwas schiefgeht

Das Senden an Discord erfolgt nach dem Prinzip "fire and forget": Wenn die
Webhook-URL nicht mehr gültig ist, Discord nicht erreichbar ist oder die
Anfrage anderweitig fehlschlägt, gibt es keine Wiederholung — aber im
WordPress-Adminbereich erscheint ein schließbarer Fehlerhinweis, der den
Discord-Kanal und den Grund nennt, sodass der Fehlschlag nicht unbemerkt
bleibt. Wichtig: Dies beeinträchtigt niemals die
Roundup-Veröffentlichung selbst; eine fehlgeschlagene
Discord-Benachrichtigung blockiert oder unterbricht niemals einen
geplanten Lauf. Wenn keine Nachrichten mehr ankommen, kopiere eine neue
Webhook-URL aus Discord (Webhooks können auf Discords Seite
gelöscht/neu generiert werden) und speichere sie hier erneut.
