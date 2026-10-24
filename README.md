# WaldorfshopTranslations 0.5.0 (unveröffentlicht)

Der Korrekturstand 0.5.0 ist lokal vorbereitet. Plenty-Testbuild und gesonderte Live-Freigabe stehen aus.

Bei der geschützten Wiederaufnahme kann `retainEmptySource` ausdrücklich vorhandene Fremdtexte bei leerem Deutsch beibehalten, zum Beispiel `{"en":["technicalData"],"fr":["technicalData"]}`. Dieselbe Auswahl muss in Vorschau und Wiederaufnahme übergeben werden. Die Vorschau speichert nichts; erst eine gültige, aktuelle Rücklese-Freigabe übernimmt den Vergleichsstand. Die Freigabe bindet sämtliche deutschen Quellfelder, den unveränderten Zieltext und die Verarbeitungspolitik. Änderungen erfordern eine neue Prüfung. Ohne diese Auswahl bleibt die bisherige Sperre bestehen; es werden weder technische Daten gelöscht noch leere deutsche Felder automatisch ergänzt.


Konfigurierbares Plenty-Plugin für deutsche Artikeltexte → ausgewählte Zielsprachen mit DeepL. Version 0.4.0 ergänzt geprüfte Wiederaufnahme, nachvollziehbare Fehler und eine getrennte Verbrauchsanzeige. Die Plenty-Prüfung erfolgt ausschließlich in einem unverknüpften Testset. Eine Live-Bereitstellung benötigt eine eigene Freigabe.

## Wiederaufnahme ab Version 0.4.0

Gesperrte Aufträge werden nicht pauschal freigegeben. Die OAuth-geschützte Steuerung erlaubt ausschließlich dem konfigurierten API-Benutzer folgende Schritte:

1. `GET /rest/waldorfshop-translations/recovery-preview?itemId=123` prüft einen offenen Auftrag rein lesend. Die Antwort enthält wiederverwendbare Felder, fehlende Felder, einen konservativen maximalen neuen Zeichenumfang, offene Probleme und einen `review_token`. Die Prüfung schreibt weder Aufträge noch Artikel oder Tags.
2. Falls eine konkrete gespeicherte Antwort falsch ist, kann `POST /rest/waldorfshop-translations/recovery-preview` zusätzlich `corrections` als Objekt Sprache → Feld → korrigierter Text erhalten. Zulässig sind nur bereits benötigte Textfelder dieses Auftrags. Auch Korrekturen müssen Zahlen-, HTML- und Längenprüfungen bestehen. URL-Felder bleiben ausgeschlossen. In diesem Schritt wird nichts gespeichert.
3. Nach fachlicher Prüfung erhält `POST /rest/waldorfshop-translations/recovery-resume` dieselbe `itemId`, denselben `reviewToken` und dieselben optionalen `corrections`. Der Speicherschalter muss aktiv sein. Unter der globalen Sperre werden Quelle, Zieltexte, Auftrag, aktuelle Tags, Glossar und Einstellungen erneut verglichen. Eine inzwischen geänderte Vorschau wird abgewiesen. Bestehende Antworten bleiben erhalten und werden erneut validiert. Nur tatsächlich fehlende Felder dürfen neue Anfragen erzeugen.

Fachlich falsche Zahlen werden nicht still durchgewunken. Eine bestätigte Feldkorrektur wird mit dem Auftrag dokumentiert und benötigt für dieses Feld keine neue Übersetzungsanfrage. Leere deutsche Felder mit bestehenden Zieltexten bleiben weiterhin ein Prüffall; sie werden nicht automatisch gelöscht.

HTML-Tags müssen in derselben Reihenfolge und mit denselben Attributwerten erhalten bleiben. Gleichwertige Anführungszeichen, Attributreihenfolgen und die Schreibweisen `<br>`/`<br/>` werden akzeptiert. Neue Attribute, geänderte Links, geänderte Styles und zusätzliche oder entfernte Elemente bleiben gesperrt. Zahlenprüfungen bleiben aktiv.

Neue Aufträge speichern Fehlercode, Phase, Sprache und Feld. Bekannte Pluginfehler liefern eine verständliche Meldung. Unbekannte SDK- oder HTTP-Ausnahmen geben keine Rohmeldungen mit möglichen Zugangsdaten aus. Die nächtlichen Arbeitspakete schreiben zusätzlich einen begrenzten Status ins Plenty-Protokoll.

`GET /rest/waldorfshop-translations/usage` zeigt den tatsächlichen DeepL-Verbrauch im Abrechnungszeitraum und die konservative lokale Reservierung im Kalendermonat getrennt. Vor einer neuen Übersetzungsanfrage wird der DeepL-Verbrauch gegen das konfigurierte Zeichenlimit und ein eventuell niedrigeres Anbieterlimit geprüft. Andere gleichzeitig nutzende Anwendungen können den Kontoverbrauch verändern; der Anbieter-Kostenstopp bleibt unabhängig davon.

Neue Antworten enthalten nach Möglichkeit die von DeepL mit `show_billed_characters` gemeldeten Zeichen. Anfragezustand, Artikel, Sprache, Feld und reservierte Zeichen werden gespeichert. Alte historische Reservierungen werden nicht rückwirkend umgeschrieben. Bei einer möglicherweise bezahlten Anfrage ohne sicher gespeicherte Antwort wird eine erneute Anfrage zur Prüfung angehalten. Bereits vorhandene Cache-Schlüssel bleiben unverändert, damit bezahlte Antworten weiterhin nutzbar sind.

Die Klassen unter `Diagnostics` simulieren Artikel- und Tagänderungen ausschließlich im Speicher. `InspectRecoveryPreview` darf nur im `runOnBuild` eines ausdrücklich unverknüpften Testzweigs stehen. Die reguläre Version führt beim Build ausschließlich Schema- und reine Laufzeitprüfungen aus, keine Übersetzung oder Auftragsfreigabe.

## Einrichtung in Plenty

Alle Einstellungen liegen unter **Plugins → Plugin-Set → WaldorfshopTranslations → Konfiguration**. Beschriftungen sind auf Deutsch und Englisch verfügbar. Neue Installationen enthalten keine shopbezogenen Zugänge, Tag-IDs oder Artikel-IDs. Beim Wechsel vom alten Konfigurationsformat müssen bestehende Zugänge und IDs einmal in der neuen Oberfläche gespeichert werden; vor jedem Update die Automatik ausschalten.

1. **API-Zugänge:** eigene HTTPS-Plenty-Backend-Adresse, API-Benutzer und Passwort, dessen Benutzer-ID für die Steuerung, DeepL API Free oder Pro und den passenden API-Schlüssel eintragen. Passwörter werden als Passwortfelder dargestellt. Nur Benutzer mit Zugriff auf die Plugin-Konfiguration dürfen Zugänge bearbeiten; Maskierung ersetzt keine Rechteverwaltung.
2. **Zielsprachen:** gewünschte Sprachen anhaken. Quelle bleibt Deutsch. Mindestens eine Zielsprache auswählen. Englisch (GB/US), Portugiesisch (PT/BR) und Chinesisch (vereinfacht/traditionell) haben je eine Variantenwahl; Plenty speichert jeweils nur ein Sprachfeld.
3. **Tags:** zwei verschiedene vorhandene Tag-IDs eintragen, die für Varianten verfügbar sind: Auslöser und Prüftag. Das Plugin legt Tags nicht selbst an. Der API-Benutzer benötigt Zugriff auf beide Tags und die betroffenen Artikel.
4. **Glossar & Kontext:** optional ein eigenes mehrsprachiges DeepL-Glossar angeben und den Glossarmodus aktivieren. Es muss für jede gewählte Zielsprache ein deutsches Wörterbuch enthalten. Fehlende Wörterbücher führen zum Abbruch, nicht zum stillen Übersetzen ohne Glossar. Für Waldorfshop das bereits freigegebene Waldorf-Glossar einstellen. Kontext kann shopbezogen angepasst werden.
5. **Betrieb:** Monatslimits (Standard: 100 Artikel und 250.000 Quellzeichen über alle Zielsprachen), optionalen Pilotartikel und zunächst Vorschau einstellen. Mit `automaticEnabled=0` und `writesAllowed=0` bleibt die Automatik aus.
6. Vor Freigabe die geschützte Konfigurationsprüfung ausführen und einen gezielten Pilot testen. Für den automatischen Betrieb beide Schalter aktivieren und das Plugin im Set des Standardmandanten bereitstellen. Ohne diese separate Freigabe erfolgt keine Live-Aktivierung.

Unterstützte Plenty-Zielsprachen: EN, FR, IT, ES, NL, BG, CN, CZ, DA, NN, PL, PT, RO, RU, SE, SK, TR, VN. Plenty-Sondercodes werden auf DeepL abgebildet, z. B. CZ→CS, SE→SV, VN→VI, NN→NB. Vor jedem Lauf wird die aktuelle DeepL-Sprachliste inklusive HTML- und Glossar-Unterstützung abgefragt. Nicht verfügbare gewählte Sprachen blockieren den Lauf vor kostenpflichtigen Anfragen. Die Auswahl bietet die dokumentierten Plenty-Sprachen; neue Plenty-Sprachen erfordern ein Plugin-Update.

## Ablauf und Schutz vorhandener Texte

Der konfigurierte Auslöser-Tag wird direkt auf Varianten geprüft und nach Artikel zusammengefasst. Übersetzt werden alle neun Artikeltextfelder: **Name 1, Name 2, Name 3, HTML-Titel für Webshop, Vorschautext, Artikeltext, technische Daten, Meta-Beschreibung und Meta-Keywords**. **URL-Pfade werden nicht an DeepL übergeben und nicht geschrieben.** Name 1–3 und HTML-Titel bleiben Klartext mit maximal 240 Zeichen; Vorschautext, Artikeltext und technische Daten erhalten ihre HTML-Struktur und Attribute. Leere deutsche Felder erzeugen keine Übersetzungsanfragen und löschen keine vorhandenen Zieltexte; bestehende Zieltexte bei leerer Quelle erfordern eine Prüfung. Unbekannte Vergleichsstände werden ohne zusätzliche Freigabe aktualisiert. Bei bekannten Vorlagen werden nur geänderte deutsche und fehlende Zielfelder übersetzt. Manuelle Zieländerungen bei unverändertem Deutsch bleiben erhalten; gleichzeitige Änderungen gehen zur Prüfung. Abgewählte Sprachen werden weder übersetzt noch geschrieben oder aus dem bisherigen Vergleichsstand entfernt.

Beim Wechsel von Version 0.2.1 bleiben bekannte Vergleichsstände der bisherigen vier Felder erhalten. Die fünf ergänzten Felder werden beim erneuten Markieren des Artikels nachgeholt. Ein bereits teilweise geschriebener alter Job wird nach der Felderweiterung blockiert und muss vor Fortsetzung geprüft werden; er wird nicht still ersetzt. Bereits abgeschlossene Artikel werden ohne erneuten Auslöser-Tag nicht automatisch wieder aufgenommen. Der zusätzliche Feldumfang verbraucht entsprechend mehr vom vorhandenen Zeichenbudget.

Erst nach Rücklesen aller ausgewählten Sprachen werden Vergleichsstände gespeichert, der konfigurierte Prüftag auf allen ursprünglich markierten Varianten gesetzt und danach die Auslöser entfernt. Nicht ausgewählte Sprachen sind keine Voraussetzung für den Tag-Wechsel. Es gibt keine Transaktion über mehrere Sprachen und keinen serverseitigen Compare-and-Swap. Parallelbearbeitung während der Verarbeitung vermeiden.

Ab Version 0.2.3 werden äußere ASCII-Leerzeichen in Klartextübersetzungen vor dem Speichern entfernt und beim Rücklesen vereinheitlicht. Innere Leerzeichen, Textinhalt und sämtliche HTML-Felder bleiben exakt geprüft. Der Vergleichsstand enthält die tatsächlich zurückgelesenen Werte. Bereits gespeicherte Teilaufträge behalten ihre bezahlten Übersetzungen; blockierte Aufträge benötigen weiterhin eine gezielte manuelle Wiederaufnahme und werden durch ein Update nicht automatisch freigegeben.

Globale Datenbanksperre gegen parallele Läufe, dauerhafte Jobs für Teilfehler, Antwortcache und Monatsbudget bleiben erhalten. Sprache, Variantenwahl, Tag-IDs, Glossarinhalt und Kontext sind Teil der Verarbeitungspolitik. Änderungen dieser Einstellungen nach einer Teilübernahme blockieren die automatische Fortsetzung. Ein Vorschaujob ohne Teilübernahme darf neu geplant werden. Budgets bereits vorhandener Installationen bleiben erhalten; neue Shops übernehmen keine Waldorfshop-Pilotverbräuche.

## Nachtlauf ab Version 0.3.0

Der vorhandene 15-Minuten-Cron bleibt der Taktgeber. Wenn beide Betriebsschalter aktiv sind, arbeitet er im Zeitfenster **02:00 bis 08:00 Uhr Europe/Berlin**. Sommer- und Winterzeit werden ausdrücklich berücksichtigt. Außerhalb dieses Fensters werden keine automatischen Übersetzungsanfragen gestartet. Der geschützte manuelle Pilot bleibt separat verfügbar.

Beim ersten Nacht-Takt wird einmal pro lokalem Kalendertag ein Stand aller mit dem konfigurierten Auslöser markierten Artikel aufgenommen. Dieser Stand wird nach Artikel-ID sortiert in Seiten von höchstens 100 Artikeln gespeichert. Pro Takt werden bis zu 25 Artikel abgearbeitet, mit einem kooperativen Zeitlimit von fünf Minuten und spätestens bis 08:00 Uhr. Vor weiteren HTTP-Anfragen wird das Zeitlimit geprüft; eine bereits laufende Anfrage kann noch zu Ende laufen. Der nächste Cron setzt am gespeicherten Fortschritt an.

Erfolgreiche Artikel behalten den bisherigen vollständigen Ablauf einschließlich Rückleseprüfung, Vergleichsstand, Prüftag und Entfernen des Auslösers. `blocked` und `review_required` werden nicht automatisch entsperrt. Ein einzelner Validierungsfehler hält die übrigen Artikel nicht auf. Entfernte Auslöser werden vor der Verarbeitung erneut geprüft; danach beginnt für diesen Eintrag keine neue Übersetzung. Nach Beginn der täglichen Aufnahme neu gesetzte Tags warten auf die nächste Nacht.

Zeitunterbrechungen, gemeinsame API-Ausfälle und Monatslimits werden als Pause gespeichert. Bereits bezahlte, gespeicherte Übersetzungsantworten bleiben für die Fortsetzung erhalten. HTTP 429, Anmeldeprobleme und Dienstfehler pausieren das Paket, statt weitere Artikel zu blockieren. Eindeutig abgelehnte einzelne Übersetzungsanfragen bleiben Artikelfehler. Bei einer Budgetpause folgen in derselben Nacht keine weiteren Übersetzungsversuche. Offene Artikel behalten ihren Auslöser und werden in der nächsten Nacht erneut berücksichtigt; die Limits bleiben unverändert wirksam. Eine vollständige Abarbeitung bis morgens hängt deshalb von Laufzeit, Dienstverfügbarkeit und verfügbarem Budget ab.

Ändert sich die Verarbeitungspolitik während eines begonnenen Nachtlaufs, pausiert dieser zur Prüfung. Der Status-Endpunkt zeigt zusätzlich `nightly_run` mit Gesamtzahl, Fortschritt, Ergebnissen und letztem Problem sowie `nightly_issue` für Fehler vor der Warteschlangenaufnahme. `status=complete` beschreibt eine fertig durchlaufene Warteschlange; die Zähler `failed`, `review_required` und `skipped` zeigen verbleibenden Prüfbedarf.

Globale Sperre, Zahlen-/HTML-Schutz, URL-Ausschluss und Geheimnisgrenzen bleiben erhalten. Nach einem harten Prozessabbruch eine verwaiste Sperre erst administrativ lösen, wenn kein Worker mehr läuft. Ein Update aktiviert keine zuvor ausgeschaltete Installation und ändert weder Tags noch Budgets.

## Geschützte Schnittstellen

Alle Routen verlangen Plenty-OAuth und zusätzlich die konfigurierte API-Benutzer-ID. Keine öffentlichen Schreib- oder Diagnose-Routen:

- GET `/rest/waldorfshop-translations/status`: Schalter, Sperre, Budget und Pilotstatus, keine Zugangsdaten.
- GET `/rest/waldorfshop-translations/configuration-check`: lesende Prüfung beider Tags, DeepL-Zugang, Sprachen und optionaler Glossarwörterbücher. Keine kostenpflichtige Übersetzung und keine Artikel-/Tagänderung.
- POST `/rest/waldorfshop-translations/pilot`: ausschließlich die konfigurierte Pilotartikel-ID mit Auslöser-Tag; Speichern nur mit `writesAllowed=1`.

Die HTTP-Brücke erlaubt ausschließlich validierte Plenty-Backend-Domains (`p…my.plentysystems.com` oder `….plentymarkets.com`), die festen DeepL-Pro-/Free-Hosts und begrenzte API-Pfade. Keine Weiterleitungen oder Abschaltung der TLS-Prüfung. Individuelle Backend-Domains außerhalb dieser Muster werden derzeit nicht unterstützt.

Erforderliche Plenty-Rechte: Artikel und Varianten lesen; Artikeltexte lesen, erstellen und aktualisieren; Tags lesen; Tag-Verknüpfungen lesen, erstellen und löschen. Kein Administratorzugang erforderlich. Geheimnisse ausschließlich in der Plenty-Konfiguration hinterlegen, niemals in Git, Beispielkonfiguration oder Statusbericht.

## Tests und Veröffentlichung

`php tests/run.php` führt 144 Regressionstests aus. Enthalten sind eine simulierte Warteschlange mit 120 Artikeln, Fortsetzung zwischen Paketen, Sommer-/Winterzeit, Tageswechsel, entfernte Tags, einzelne Artikelfehler, API-/Budgetpausen, ein Prozessabbruch nach erfolgreicher Artikelübernahme und die vorhandenen Schutzprüfungen. Runner, Übersetzungs-Engine und HTTP-Grenze werden dabei mit ersetzten externen Schnittstellen tatsächlich ausgeführt. Lokal alternativ `node tools/test-php.mjs` mit den offiziellen WordPress-Playground-Paketen `@php-wasm/universal` und `@php-wasm/node-8-3` (3.1.58). Die Testlaufzeit ist kein Bestandteil des Plenty-Plugins. Die lokalen Tests ersetzen weder den Plenty-Codecheck noch einen Testbuild in einem nachweislich unverknüpften Set.

Die Marketplace-Veröffentlichung ist ein separater Schritt: Partnerregistrierung, endgültiger Produktname, Kategorie und Produktmetadaten, Supportkontakt, vollständige DE-/EN-Anleitung, Paketprüfung, Reviewzugänge und Plenty-Review. Diese Entwicklungsversion wurde nicht eingereicht und ist keine Marketplace-Zulassung. Die historischen Klassen `Glossary` und `InitialBudget` aus dem privaten Pilot werden seit 0.2.0 nicht mehr zur Laufzeit verwendet und sind vor einer öffentlichen Paketabgabe zu bereinigen.

## English setup

Configure the plugin directly in **Plugins → plugin set → WaldorfshopTranslations → Configuration**. All configuration labels are available in English and German. Enter your own validated Plenty backend URL, API username/password, allowed API user ID, DeepL API Free/Pro plan and API key. Select target languages using checkboxes; the source is German. Configure two different existing variation tags (trigger and review), optional multilingual glossary ID/context, and monthly item/character limits. English, Portuguese and Chinese each offer a variant selector; each has one Plenty text row.

The plugin translates nine fields: name 1, name 2, name 3, webshop HTML title, preview text, description, technical data, meta description and meta keywords. URL paths are excluded from translation and writes. Names and HTML titles have a 240-character limit; technical data preserves HTML just like descriptions. Empty source fields never erase existing translations. Existing four-field baselines are retained; re-tag completed articles to fill the five added fields. Partially written older jobs require manual review after this update. The larger field coverage uses more of the configured character budget.

The read-only `configuration-check` endpoint verifies tag availability, credentials, selected languages, HTML support and required glossary coverage before any paid translation. A required glossary must contain every selected DE→target dictionary; missing coverage stops processing. Unselected languages remain unchanged. Only after all selected translations have been saved and read back is the review tag added and the trigger removed. Concurrent source/target edits and changed configuration after partial writes require manual review.

Installation and updates do not enable automatic processing. Keep automatic processing off and saving in preview mode until a pilot has been reviewed. Production processing requires a separate release and both switches enabled in the default client's deployed plugin set. Existing budgets are retained. This is a test release, not a submitted or approved Marketplace product. Marketplace metadata, support contacts, complete product documentation and provider review remain separate publication work.

## Offizielle Grundlagen

- https://developers.plentymarkets.com/en-gb/developers/main/plugin-configuration/how-to-plugin-configuration.html
- https://knowledge.plentyone.com/de-de/manual/main/daten/interne-IDs.html
- https://developers.deepl.com/docs/languages/using-the-languages-api
- https://developers.plentymarkets.com/en-gb/developers/main/plentymarketplace/plugin-review.html
