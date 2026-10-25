# Änderungen

## 1.8 · Build 9 · 2026-10-25

- Momentanleistung (PowerID 48602) und Gesamtverbrauch (EnergyID 50335) als zusätzliche Live-Karten mit Symbol und anklickbarem Verlauf ergänzt.
- Beide Quellen in Formular, Referenzen, Aktualisierung, Diagnose und Quellstatus aufgenommen.
- Verlauf mit Zeitwahl und Navigation; Leistungs-Mittelwerte, Energie-Zählerdifferenzen als Verbrauchsbalken oder Gesamtzählerstand als Kurve gemäß tatsächlicher Archivierungsart.
- Originaleinheiten aus den Quellen, bis zu vier Nachkommastellen für kleine Energiemengen und breitere Achsenbeschriftung.
- Standard-/Zähleraggregation mit offiziellen ArchiveControl-Stubs und die neue Oberfläche im Browser geprüft.

## 1.7 · Build 8 · 2026-10-25

- Unteres Zusatzfeld „Aktuelle Betriebsart“ samt Informationssymbol entfernt. Die aktuelle Betriebsart bleibt im Kopfbereich und an der aktiven Betriebsart-Kachel sichtbar.
- Filterwartungsbereich inklusive Kalendersymbol entfernt; Filterrestlaufzeit und Quellbereichsbalken bleiben erhalten.
- Nicht mehr benötigte SVG-Symbole, CSS-Regeln und JavaScript-Aktualisierung entfernt.

## 1.6 · Build 7 · 2026-10-25

- Vier Temperaturkarten anklickbar und per Tastatur bedienbar; öffnen ein eigenes Diagramm im Dialog.
- 24 Stunden, 7 Tage, 30 Tage und 365 Tage mit vorigem/nächstem Zeitfenster und „Jetzt“.
- Vorhandene Archive-Control-Daten über die offiziellen APIs lesen, stündliche bzw. tägliche Mittelwerte. Keine Änderung von Archivkonfiguration oder Messwerten.
- Fehlendes Archiv, deaktivierte Protokollierung, falsche Zähleraggregation, leere Bereiche, Timeout und verspätete Antworten behandeln.
- Diagramm außerhalb des skalierten Dashboards, auch in kleinen Kacheln passend; getrennte Live-/Verlaufsnachrichten und Schutz vor beliebigen Variablenabfragen.

## 1.5 · Build 6 · 2026-10-25

- Seitliche Leerflächen der Höhenanpassung beseitigt: interne Breite vor der Skalierung erweitern und Inhalte neu umbrechen, sodass die sichtbare Kachelbreite vollständig genutzt wird.
- Starre maximale Dashboardbreite entfernt; optimale Skalierung nach tatsächlicher Inhaltsgröße ermitteln.
- Luftwege bis hinter das Zentralgerät führen, damit sie auch in sehr breiten/flachen Formaten verbunden bleiben.
- Vollständige Breitenbelegung zusätzlich zu Höhenpassung und Bedienung automatisch prüfen, einschließlich 1412×1168 und 2200×1100 px.

## 1.4 · Build 5 · 2026-10-25

- Gesamtes Dashboard proportional in verfügbare Breite und Höhe einpassen; zuvor wurden nur Breite und Symbole angepasst.
- Kachel- und Inhaltsgrößen per ResizeObserver beobachten, bei Resize, Datenupdate und Schriftladen neu skalieren.
- Diagnoseausgabe intern begrenzen; auch nach deren Öffnen bleibt die Kachel vollständig sichtbar.
- Regressionstests für flache Kacheln bis 320 × 200 px und Bedienung skalierter Betriebsart-Schaltflächen ergänzt.

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
