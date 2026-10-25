# Prüfbericht · Version 1.5 · 25. Oktober 2026

## Ergebnis und Reichweite

Das Dashboard wurde an die Bildvorlage angepasst. Der gemeldete Fehler „keine passenden Profil-Assoziationen“ ist durch die bisherige Beschränkung auf klassische Profile erklärbar. Version 1.2 liest zusätzlich echte Optionen einer modernen Darstellung „Aufzählung“. Dass Variable 54849 tatsächlich diese Darstellung besitzt, ist ohne Diagnose der Zielanlage noch nicht bestätigt. Es werden keine Schaltwerte erfunden.

## Ausgeführte Prüfungen

| Prüfung | Ergebnis | Umfang |
|---|---|---|
| PHP-Syntax | bestanden | PHP 8.4.24, Modul, Diagnose und PHP-Testdateien |
| Manifeste | bestanden | Felder, JSON, URLs, GUIDs unverändert zum Ausgangsarchiv, Klasse, Präfix und Typ |
| Offizielle SymconStubs | **68 Assertions bestanden** | Lifecycle, Defaults, Formular, Referenzen, Nachrichten, Re-Apply, Quellwechsel/-löschung, Kernelstart, Daten-Push und unverändertes Polling |
| Auswahl und Aktionen | in den 68 Assertions enthalten | Profile und deren Priorität; moderne Aufzählung ohne Legacy-Assoziationen; ausschließlich echte Werte; Vorrang moderner Darstellung vor alten Profilfeldern; entfernte/ungültige Werte; Integer/Float/Bool/String; Instanz-/Skriptaktion; CustomAction=1; Weitergabe von PHP-Fehlern im Snapshot; Diagnose; sichere Texte |
| Browser | **109 Assertions bestanden** | Chromium 154.0.8037.57; dokumentierte void-Bridge simuliert; Betriebsart-Buttons, typgetreuer Request, kein vorzeitiger Aktivzustand, Quellbestätigung, PHP-Fehler-Push, optionale Promise-Ablehnung, Timeout, Diagnose, fehlende Auswahl, keine erfundenen Buttons, Bool/String, Quellbereichsbalken und versteckte Balken ohne Grenzen |
| Responsive Darstellung | in den 109 Assertions enthalten | 1536, 1180, 800, 700, 360 und 320 px; kein horizontaler Überlauf, vier Temperaturen und Steuerung sichtbar; Desktop und Mobil visuell geprüft; tatsächliche Symbolbreiten bei 1536 und 360 px verglichen, Luftsymbol auf kleinen Kacheln weiterhin sichtbar |
| Höhenanpassung | in den 109 Assertions enthalten | 1180×1000, 1180×300, 800×450, 360×360, 320×200 und 360×1400, 1412×1168 und 2200×1100 px; volle sichtbare Breite ohne seitliche Schrumpfränder; ganze Darstellung und Schaltflächen innerhalb der Kachel; reine Höhenänderung verkleinert Symbole; Schalten bei 800×350 px; erneute Anpassung nach Öffnen der Diagnose |
| Import des vollständigen Repositorys | bestanden | Offizieller LibraryLoader lädt Repository-Wurzel einschließlich Downloads unter `docs`, erzeugt genau ein Modul |
| Import des Installations-ZIPs | bestanden | Ganze Bibliothek importiert, Instanz erstellt, initiale Kachel und Formular erzeugt |
| ZIP-Build | bestanden | CRC, bytegleiche Nutzdateien, Bibliothekswurzel und SHA-256; wiederholte Builds reproduzierbar |
| Offizielle Online-Dokumentation | geprüft | HTML-SDK, `requestAction` mit void-Rückgabe, wirksame Variablendarstellung und Aufzählungsoptionen; Links in [API.md](API.md) |

Stubs-Prüfstand: `31f2e0478eb3763236fcf44ea4144271fdeddfd5`. Veraltete Reflection-APIs der Stubs erzeugen unter PHP 8.4 Deprecation-Hinweise; nur diese Kategorie wird im Test unterdrückt. Warnungen/Fehler werden zu Exceptions. Screenshot- und Testauswahlwerte sind ausdrücklich synthetische Fixtures und nicht im Produktionsmodul hinterlegt.

## Nicht verifiziert

- Tatsächliche Profile, Präsentationsoptionen und Aktionen der sieben vorgegebenen Variablen, insbesondere 54849. Der Screenshot ist keine Ermittlung der realen Betriebsarten.
- Tatsächlicher Symcon-Frontend-Transport und Schaltwirkung an der Anlage; dafür stehen Kachel-Diagnose, Formular-Diagnose und [Leseskript](inspect-symcon.php) bereit.
- Installation und Lifecycle auf jeder Symcon-Version ab 7.1. Klassische Profile bleiben unterstützt; moderne Aufzählungen werden nur ausgewertet, wenn die dokumentierte API ab 8.1 vorhanden ist.
- Reale Gerätebestätigung, Aktualisierungsverhalten der Geräteintegration und Neustart auf dem Zielserver.
- Ausführung des GitHub-CI-Workflows und Module-Store-Veröffentlichung. GitHub-Repository und Download-ZIP sind der vorgesehene Installationsweg.

Falls nach dem Update weiterhin keine Auswahl erscheint, in der Kachel „Diagnose anzeigen“ öffnen. Ohne tatsächlich hinterlegte Auswahloptionen muss die Betriebsart gesperrt bleiben; aus Wertebereichen oder Beschriftungen werden keine Schaltwerte abgeleitet. Zur Abnahme siehe [Installation](INSTALLATION.md#verifikation-der-echten-anlage).
