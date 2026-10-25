# Änderungen

## 1.3 · Build 4 · 2026-10-25

- Symbole über Containergrößen und begrenzte fließende Größen an Kachel und Teilbereiche angepasst.
- Luftsymbole bleiben auch bei schmalen Kacheln sichtbar; Thermometer werden bei Platzmangel ausgeblendet.
- Zentrales Gerät, Messwert-, Betriebsart- und Informationssymbole skalieren mit.
- Browserprüfungen vergleichen Symbolgrößen zwischen großer und kleiner Kachel.

## 1.2 · Build 3 · 2026-10-25

- Dashboard an die Bildvorlage angepasst: blaues Gerätegehäuse, leuchtende Luftströme, Außenluft/Abluft links und Zuluft/Fortluft rechts, große Messwerte und dynamische Betriebsarten-Kacheln.
- Betriebsarten zusätzlich aus der offiziellen Darstellung „Aufzählung“ (`IPS_GetVariablePresentation`, `OPTIONS.Value/Caption`) lesen; keine erfundenen Standardmodi. Tatsächliche Werte inklusive Boolean/String typgetreu weitergeben.
- Echte serverseitige Aktionsfehler per HTML-SDK-Push anzeigen, da `requestAction()` keinen Rückgabewert hat.
- Diagnose in Kachel und Instanzformular ergänzt. Balken nur mit tatsächlich hinterlegten Min-/Max-Grenzen anzeigen.
- Prüfungen um Aufzählungen ohne Profil-Assoziationen, Vorrang moderner Darstellung, typgetreue Aktionen und Fehlerweitergabe erweitert.

## Korrektur der Repository-Installation

- Downloadpakete von `dist/` nach `docs/downloads/` verschoben. Symcon deutet zusätzliche Wurzelordner als Module; `docs` ist ein ausgenommener Dokumentationsordner.
- Importprüfung auf das vollständige Repository erweitert, zusätzlich zum Installations-ZIP.

## 1.1 · Build 2 · 2026-10-25

- GUIDs, Klassenname, Präfix und sieben Standard-IDs des Ausgangs-ZIPs beibehalten.
- Vollständige Repository-Metadaten inklusive Modul-URL ergänzt.
- JSON-Text für den HTML-SDK-Nachrichtenkanal, initiale Daten sicher eingebettet.
- Kernelstart, Variablen-/Profil-/Aktionsmeldungen, Referenzbereinigung und 30-Sekunden-Abgleich ergänzt.
- Serverseitige Aktionsvalidierung, echte Aktion erforderlich, deaktivierte CustomAction berücksichtigt, Datentyp erhalten und toleranzbasierte Schaltwertprüfung entfernt.
- Dunkles responsives Wärmetauscher-Dashboard; keine Heizungswerte, angenommenen Filterlaufzeiten oder erfundenen Betriebsarten.
- Automatisierte Modul-/Browserprüfungen, Diagnose, Installationsanleitung und reproduzierbarer ZIP-Build ergänzt.
