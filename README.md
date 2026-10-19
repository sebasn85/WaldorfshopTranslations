# WaldorfshopTranslations

Eigenständiges Plenty-Plugin. Entwicklungsversion, nicht live freigegeben. Die lokale Python-Version ist die getestete Referenz, dieses Plugin ist eine separate PHP-Implementierung und muss in Plenty gebaut und praktisch geprüft werden.

## Ablauf

Vorhandenen Varianten-Tag 141 auslesen, direkte Tag-Verknüpfungen mit dem Variantenfilter abgleichen und nach Artikel zusammenfassen. DE nach EN-GB, FR, IT, ES und NL mit dem bei DeepL abgeglichenen Waldorf-Glossar übersetzen. Felder: Name, Kurzbeschreibung, Beschreibung, Meta-Beschreibung. Vorhandene Felder ohne Vergleichsstand werden neu übersetzt. Bei bekannten deutschen Vorlagen werden nur geänderte und fehlende Felder übersetzt. Rein manuelle Zieländerungen bei unverändertem Deutsch bleiben erhalten; gleichzeitige Änderungen gehen zur Prüfung.

Erst nach Rücklesen aller gespeicherten Texte werden Vergleichsstände gespeichert, der Prüftag 354 auf allen ursprünglich markierten Varianten gesetzt und erst danach die jeweiligen Auslöser-Tags entfernt. Teilfehler werden dauerhaft gespeichert und können fortgesetzt werden. Es gibt keine Transaktion über die fünf Sprachen und keinen serverseitigen Compare-and-Swap für Artikeltexte. Parallelbearbeitung während des Piloten vermeiden.

## Pilot und Aktivierung

- Standardmäßig sind `automaticEnabled=0` und `writesAllowed=0`.
- `pilotItemId=10009957` ist ein bereits markierter Artikel; POST `/rest/waldorfshop-translations/pilot` verarbeitet ausschließlich diesen Artikel. `writesAllowed=1` erlaubt den autorisierten Einzel-Speichertest.
- GET `/rest/waldorfshop-translations/status` meldet Schalter, Sperre, Monatsbudget und letztes Ergebnis.
- Beide Routen verlangen OAuth und zusätzlich die konfigurierte Benutzer-ID (`operatorUserId=197`). Es gibt keine öffentlichen Schreib- oder Diagnose-Routen.
- API-Zugänge werden ausschließlich in den passwortgeschützten Konfigurationsfeldern des Plugins eingetragen, niemals in Git. Die HTTP-Brücke spricht ausschließlich die fest vorgegebenen Plenty- und DeepL-Pro-Hosts an.
- Für den späteren Vollbetrieb beide Schalter aktivieren und das Plugin im Set des Standardmandanten bereitstellen. Der Cron verarbeitet alle 15 Minuten maximal einen Artikel. Einträge mit Status `blocked` oder `review_required` werden im automatischen Lauf übersprungen.
- Der manuelle Pilot-Aufruf kann einen blockierten Pilotartikel erneut prüfen. Für andere blockierte Artikel die Pilot-ID gezielt umstellen. Das ist keine Massenfreigabe.

## Persistenz und Limits

Plugin-Datenbank `WaldorfshopTranslations::StateEntry`: Jobs, Quell-/Zielvergleich je Feld, Cache, Budget und globale Sperre. Längere Texte liegen in Array-Feldern, da Plenty-Stringspalten auf 255 Zeichen begrenzt sind.

Die eindeutige Datenbank-Spalte `stateKey` schützt die globale Sperre vor parallelen Läufen. Diese Eigenschaft muss in Plenty praktisch bestätigt werden. Es gibt keine automatische Entsperrung nach Zeitablauf: Nach einem harten Prozessabbruch erst sicherstellen, dass kein Worker mehr läuft, und die verwaiste Sperre administrativ entfernen. Eine solche Aktion wird nicht über eine öffentliche Route angeboten.

100 verschiedene kostenverursachende Artikel je UTC-Kalendermonat, konservativ 250.000 Quellzeichen über alle Zielsprachen. Reservationswerte aus dem lokalen Oktober-Pilot werden mitgenommen. Bei 128 markierten Artikeln wird deshalb nicht automatisch der komplette Bestand in einem Monat übersetzt. Das Zeichenbudget kann früher greifen. Kostenpflichtige POSTs und Schreibzugriffe werden nicht blind wiederholt.

## Tests

`php tests/run.php` führt die unabhängigen PHP-Regressionstests aus. Lokal optional `node tools/test-php.mjs` mit den offiziellen WordPress-Playground-Paketen `@php-wasm/universal` und `@php-wasm/node-8-3` (beide Version 3.1.58 im lokalen Test). Keine WordPress-Installation ist Bestandteil des Plenty-Plugins. Die Testlaufzeit gehört nicht in die Bereitstellung.

Praktischer Plenty-Build, LibraryCall, Migration, OAuth-Routen, Datenbank-Sperre und realer Artikel-Speichertest sind erst nach Installation belegbar. Ein erfolgreicher lokaler Test ersetzt diese Prüfungen nicht.

## Offizielle Grundlagen

- https://developers.plentymarkets.com/en-gb/interface/stable7/Cron.html
- https://developers.plentymarkets.com/en-gb/developers/main/hello-world-sdk.html
- https://developers.plentymarkets.com/en-gb/developers/main/data-storage/database-storage.html
- https://developers.plentymarkets.com/en-gb/developers/main/export-plugins/basic-usage.html
- https://developers.plentymarkets.com/en-gb/developers/main/tutorial/plugin-deploy.html
