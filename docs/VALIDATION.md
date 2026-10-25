# Prüfbericht · 25. Oktober 2026

## Ergebnis und Reichweite

Das Repository und das lokale Modul-ZIP sind vorbereitet und automatisiert geprüft. Eine tatsächliche Installation in IP-Symcon, ein Test der echten Variablen und ein Schaltvorgang an einer Vitovent 300-F waren in der Cloud **nicht verfügbar**. „Installierbar“ bezeichnet hier die geprüfte Bibliotheks-/Modulstruktur und Verpackung; es wird keine bereits erfolgte Abnahme auf dem Zielsystem behauptet.

## Ausgeführte Prüfungen

| Prüfung | Ergebnis | Umfang |
|---|---|---|
| PHP-Syntax | bestanden | PHP 8.4.24, Modul, Diagnose und PHP-Testdateien |
| Manifeste | bestanden | JSON, erforderliche Felder/Typen, URLs, GUID-Format und Identität zum Ausgangsarchiv, Klassenname, Präfix und Modul-Typ |
| Offizielle SymconStubs | **48 Assertions bestanden** | Lifecycle, Standard-IDs, sieben Konfigurationsfelder, Teil-/Vollstatus, Referenzen und Nachrichten, wiederholtes Übernehmen, Quellwechsel, Löschung, Kernelstart-Nachricht |
| Werte und Aktionen | in den 48 Assertions enthalten | JSON-String-Push, geänderte Werte, unverändertes Polling, Filtereinheit aus Profil, Standard-/Customprofil-Priorität, Instanz- und Skriptaktion, Integer-/Floattyp, keine künstliche Quellbestätigung, CustomAction=1, entfernter Profilwert, ungültige Werte und Skriptabschluss im Profiltext |
| Browser | **45 Assertions bestanden** | Chromium 154.0.8037.57, simulierte SDK-Funktion, initiale Werte, JSON-Push, spät verfügbarer SDK-Bridge, nur Profiloptionen, stabiler Auswahl-DOM bei Sensorupdates, Übergabe des numerischen Werts, Quellenbestätigung, Ablehnung und 15-Sekunden-Timeout, fehlende Daten, unzulässiges JSON und sichere Profiltexte |
| Responsive Darstellung | in den 45 Assertions enthalten | 1180, 700, 360 und 320 px; alle Temperaturen und Steuerung sichtbar, kein horizontaler Überlauf; Desktop-/Mobil-Screenshots visuell geprüft |
| Import des entpackten ZIPs | bestanden | Offizieller LibraryLoader lädt die gesamte Bibliothek, erstellt eine Instanz und rendert die initiale Kachel; fehlende echte Quellen korrekt als Status 104 |
| Installations-ZIP | bestanden | CRC, erwartete Bibliothekswurzel, bytegleiche Nutzdateien und SHA-256; nochmaliger Build auf Bytegleichheit geprüft |

Prüfstand der offiziellen Stubs: `31f2e0478eb3763236fcf44ea4144271fdeddfd5`. PHP-Deprecation-Hinweise aus deren Reflection-Nutzung unter PHP 8.4 werden im Test unterdrückt; Warnungen/Fehler werden zu Exceptions. Testdaten/Profilwerte sind ausschließlich Fixtures und werden nicht in das Laufzeitmodul übernommen. Das Diagnosewerkzeug liest reale Profile erst bei Ausführung auf dem Zielserver.

## Nicht verifiziert / notwendige Zielsystem-Abnahme

- IP-Symcon-Dienst, Bibliotheksimport, Instanzerstellung und HTML-SDK-Transport in der tatsächlichen Kachelvisualisierung.
- Betrieb auf der angegebenen Mindestversion 7.1 und deren eingebetteter PHP-/Browser-Version. Lokale Syntax-/Verhaltenstests liefen auf PHP 8.4; GitHub-CI ist für PHP 8.2 vorbereitet, aber hier noch nicht ausgeführt.
- Existenz, Typ, Einheiten, Skalierung, Zeitstempel und laufende Aktualisierung der sieben benannten Originalvariablen.
- Tatsächliche Profil-Assoziationen und Aktionswerte von 54849. **Es wurde kein realer Schaltwert ermittelt, geraten oder fest eingebaut.** Ohne geeignetes klassisches Profil bleibt die Auswahl gesperrt; neuere profilfreie Darstellungen werden nicht interpretiert.
- Fachliche Zuordnung von Profilwerten zu Gerätefunktionen und reale Gerätewirkung der Aktionsskripte/Quellinstanzen. Die Kachel vertraut ausschließlich auf die vorhandene Integration.
- Fehler-/Promise-Verhalten des echten SDK-Bridge und Quellbestätigung bei asynchronen Geräteaktionen.
- Dienstneustart auf dem Zielserver und unabhängige Wiederherstellung in einer neuen Cloud-Task.
- Direkte vollständige Online-Dokumentationsprüfung (Symcon-Webseite: Proxy-403). Offizielle Git-Quellen waren lesbar; Details stehen in [API.md](API.md).
- GitHub-Push, Module-Store-Veröffentlichung und tatsächliche Ausführung der vorbereiteten GitHub Actions.

Die [Installationsanleitung](INSTALLATION.md#verifikation-der-echten-anlage) enthält die konkreten Abnahmeschritte. Die fehlende Zielsystemprüfung wird nicht durch die simulierten Browser- und API-Tests ersetzt.
