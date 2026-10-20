# WaldorfshopTranslations 0.2.0

Konfigurierbares Plenty-Plugin für deutsche Artikeltexte → ausgewählte Zielsprachen mit DeepL. Testversion: keine automatische Aktivierung durch Installation oder Update. Ein realer Ein-Artikel-Test der Version 0.1.2 hat alle 20 Textfelder und den vollständigen Tag-Wechsel bestätigt; die neue Konfigurationsversion wird separat im Testset geprüft.

## Einrichtung in Plenty

Alle Einstellungen liegen unter **Plugins → Plugin-Set → WaldorfshopTranslations → Konfiguration**. Beschriftungen sind auf Deutsch und Englisch verfügbar. Neue Installationen enthalten keine shopbezogenen Zugänge, Tag-IDs oder Artikel-IDs.

1. **API-Zugänge:** eigene HTTPS-Plenty-Backend-Adresse, API-Benutzer und Passwort, dessen Benutzer-ID für die Steuerung, DeepL API Free oder Pro und den passenden API-Schlüssel eintragen. Passwörter werden als Passwortfelder dargestellt. Nur Benutzer mit Zugriff auf die Plugin-Konfiguration dürfen Zugänge bearbeiten; Maskierung ersetzt keine Rechteverwaltung.
2. **Zielsprachen:** gewünschte Sprachen anhaken. Quelle bleibt Deutsch. Mindestens eine Zielsprache auswählen. Englisch (GB/US), Portugiesisch (PT/BR) und Chinesisch (vereinfacht/traditionell) haben je eine Variantenwahl; Plenty speichert jeweils nur ein Sprachfeld.
3. **Tags:** zwei verschiedene vorhandene Tag-IDs eintragen, die für Varianten verfügbar sind: Auslöser und Prüftag. Das Plugin legt Tags nicht selbst an. Der API-Benutzer benötigt Zugriff auf beide Tags und die betroffenen Artikel.
4. **Glossar & Kontext:** optional ein eigenes mehrsprachiges DeepL-Glossar angeben und den Glossarmodus aktivieren. Es muss für jede gewählte Zielsprache ein deutsches Wörterbuch enthalten. Fehlende Wörterbücher führen zum Abbruch, nicht zum stillen Übersetzen ohne Glossar. Für Waldorfshop das bereits freigegebene Waldorf-Glossar einstellen. Kontext kann shopbezogen angepasst werden.
5. **Betrieb:** Monatslimits (Standard: 100 Artikel und 250.000 Quellzeichen über alle Zielsprachen), optionalen Pilotartikel und zunächst Vorschau einstellen. Mit `automaticEnabled=0` und `writesAllowed=0` bleibt die Automatik aus.
6. Vor Freigabe die geschützte Konfigurationsprüfung ausführen und einen gezielten Pilot testen. Für den automatischen Betrieb beide Schalter aktivieren und das Plugin im Set des Standardmandanten bereitstellen. Ohne diese separate Freigabe erfolgt keine Live-Aktivierung.

Unterstützte Plenty-Zielsprachen: EN, FR, IT, ES, NL, BG, CN, CZ, DA, NN, PL, PT, RO, RU, SE, SK, TR, VN. Plenty-Sondercodes werden auf DeepL abgebildet, z. B. CZ→CS, SE→SV, VN→VI, NN→NB. Vor jedem Lauf wird die aktuelle DeepL-Sprachliste inklusive HTML- und Glossar-Unterstützung abgefragt. Nicht verfügbare gewählte Sprachen blockieren den Lauf vor kostenpflichtigen Anfragen. Die Auswahl bietet die dokumentierten Plenty-Sprachen; neue Plenty-Sprachen erfordern ein Plugin-Update.

## Ablauf und Schutz vorhandener Texte

Der konfigurierte Auslöser-Tag wird direkt auf Varianten geprüft und nach Artikel zusammengefasst. Felder: Name, Kurzbeschreibung, Beschreibung und Meta-Beschreibung. Unbekannte Vergleichsstände werden ohne zusätzliche Freigabe aktualisiert. Bei bekannten Vorlagen werden nur geänderte deutsche und fehlende Zielfelder übersetzt. Manuelle Zieländerungen bei unverändertem Deutsch bleiben erhalten; gleichzeitige Änderungen gehen zur Prüfung. Abgewählte Sprachen werden weder übersetzt noch geschrieben oder aus dem bisherigen Vergleichsstand entfernt.

Erst nach Rücklesen aller ausgewählten Sprachen werden Vergleichsstände gespeichert, der konfigurierte Prüftag auf allen ursprünglich markierten Varianten gesetzt und danach die Auslöser entfernt. Nicht ausgewählte Sprachen sind keine Voraussetzung für den Tag-Wechsel. Es gibt keine Transaktion über mehrere Sprachen und keinen serverseitigen Compare-and-Swap. Parallelbearbeitung während der Verarbeitung vermeiden.

Globale Datenbanksperre gegen parallele Läufe, dauerhafte Jobs für Teilfehler, Antwortcache und Monatsbudget bleiben erhalten. Sprache, Variantenwahl, Tag-IDs, Glossarinhalt und Kontext sind Teil der Verarbeitungspolitik. Änderungen dieser Einstellungen nach einer Teilübernahme blockieren die automatische Fortsetzung. Ein Vorschaujob ohne Teilübernahme darf neu geplant werden. Budgets bereits vorhandener Installationen bleiben erhalten; neue Shops übernehmen keine Waldorfshop-Pilotverbräuche.

Der Cron verarbeitet alle 15 Minuten maximal einen Artikel, wenn beide Betriebsschalter aktiv sind. `blocked` und `review_required` werden automatisch übersprungen. Kostenpflichtige Anfragen und Artikel-Schreibzugriffe werden nicht blind wiederholt. Nach einem harten Prozessabbruch eine verwaiste Sperre erst administrativ lösen, wenn kein Worker mehr läuft.

## Geschützte Schnittstellen

Alle Routen verlangen Plenty-OAuth und zusätzlich die konfigurierte API-Benutzer-ID. Keine öffentlichen Schreib- oder Diagnose-Routen:

- GET `/rest/waldorfshop-translations/status`: Schalter, Sperre, Budget und Pilotstatus, keine Zugangsdaten.
- GET `/rest/waldorfshop-translations/configuration-check`: lesende Prüfung beider Tags, DeepL-Zugang, Sprachen und optionaler Glossarwörterbücher. Keine kostenpflichtige Übersetzung und keine Artikel-/Tagänderung.
- POST `/rest/waldorfshop-translations/pilot`: ausschließlich die konfigurierte Pilotartikel-ID mit Auslöser-Tag; Speichern nur mit `writesAllowed=1`.

Die HTTP-Brücke erlaubt ausschließlich validierte Plenty-Backend-Domains (`p…my.plentysystems.com` oder `….plentymarkets.com`), die festen DeepL-Pro-/Free-Hosts und begrenzte API-Pfade. Keine Weiterleitungen oder Abschaltung der TLS-Prüfung. Individuelle Backend-Domains außerhalb dieser Muster werden derzeit nicht unterstützt.

Erforderliche Plenty-Rechte: Artikel und Varianten lesen; Artikeltexte lesen, erstellen und aktualisieren; Tags lesen; Tag-Verknüpfungen lesen, erstellen und löschen. Kein Administratorzugang erforderlich. Geheimnisse ausschließlich in der Plenty-Konfiguration hinterlegen, niemals in Git, Beispielkonfiguration oder Statusbericht.

## Tests und Veröffentlichung

`php tests/run.php` führt 50 Regressionstests aus. Lokal alternativ `node tools/test-php.mjs` mit den offiziellen WordPress-Playground-Paketen `@php-wasm/universal` und `@php-wasm/node-8-3` (3.1.58). Die Testlaufzeit ist kein Bestandteil des Plenty-Plugins.

Die Marketplace-Veröffentlichung ist ein separater Schritt: Partnerregistrierung, endgültiger Produktname, Kategorie und Produktmetadaten, Supportkontakt, vollständige DE-/EN-Anleitung, Paketprüfung, Reviewzugänge und Plenty-Review. Diese Entwicklungsversion wurde nicht eingereicht und ist keine Marketplace-Zulassung. Die historischen Klassen `Glossary` und `InitialBudget` aus dem privaten Pilot werden seit 0.2.0 nicht mehr zur Laufzeit verwendet und sind vor einer öffentlichen Paketabgabe zu bereinigen.

## English setup

Configure the plugin directly in **Plugins → plugin set → WaldorfshopTranslations → Configuration**. All configuration labels are available in English and German. Enter your own validated Plenty backend URL, API username/password, allowed API user ID, DeepL API Free/Pro plan and API key. Select target languages using checkboxes; the source is German. Configure two different existing variation tags (trigger and review), optional multilingual glossary ID/context, and monthly item/character limits. English, Portuguese and Chinese each offer a variant selector; each has one Plenty text row.

The read-only `configuration-check` endpoint verifies tag availability, credentials, selected languages, HTML support and required glossary coverage before any paid translation. A required glossary must contain every selected DE→target dictionary; missing coverage stops processing. Unselected languages remain unchanged. Only after all selected translations have been saved and read back is the review tag added and the trigger removed. Concurrent source/target edits and changed configuration after partial writes require manual review.

Installation and updates do not enable automatic processing. Keep automatic processing off and saving in preview mode until a pilot has been reviewed. Production processing requires a separate release and both switches enabled in the default client's deployed plugin set. Existing budgets are retained. This is a test release, not a submitted or approved Marketplace product. Marketplace metadata, support contacts, complete product documentation and provider review remain separate publication work.

## Offizielle Grundlagen

- https://developers.plentymarkets.com/en-gb/developers/main/plugin-configuration/how-to-plugin-configuration.html
- https://knowledge.plentyone.com/de-de/manual/main/daten/interne-IDs.html
- https://developers.deepl.com/docs/languages/using-the-languages-api
- https://developers.plentymarkets.com/en-gb/developers/main/plentymarketplace/plugin-review.html
