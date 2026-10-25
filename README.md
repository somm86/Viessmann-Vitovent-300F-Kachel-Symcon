# Viessmann Vitovent 300-F · Kachel für IP-Symcon

Eigenständiges PHP-Modul für die **Kachelvisualisierung ab IP-Symcon 7.1**. Dunkles, responsives Dashboard mit zentralem Wärmetauscher, vier farbigen Luftwegen, vier Temperaturen, Volumenstrom, Filterrestzeit und profilbasierter Betriebsartensteuerung. Ausschließlich Lüftungsdaten, keine Heizungswerte.

![Dashboard mit ausdrücklich synthetischen Testdaten](docs/dashboard.png)

*Screenshot: synthetische Testdaten, auch die vier sichtbaren Betriebsarten sind reine Beispieldaten. Er enthält keine ermittelten Betriebsarten deiner Anlage.*

## Installation

Für eine vorhandene Installation: Repository in **Module Control aktualisieren**, Instanz öffnen und **Übernehmen** drücken, danach die Kachel neu öffnen. Eine neue Instanz ist nicht nötig.

Für die Erstinstallation:

1. In der IP-Symcon-Verwaltungskonsole **Module Control** öffnen, über **Hinzufügen** die URL `https://github.com/somm86/Viessmann-Vitovent-300F-Kachel-Symcon` eintragen und den veröffentlichten Branch auswählen.
2. Instanz **Vitovent 300-F Kachel** anlegen. Die sieben Quellvariablen prüfen, dann **Übernehmen**.
3. Die Instanz in der Kachelvisualisierung hinzufügen bzw. verlinken. Für die Übersicht eine ausreichend große Kachel wählen.

Für eine Installation ohne GitHub: das Release-ZIP entpacken und den darin enthaltenen Bibliotheksordner `Vitovent300F_Kachel` vollständig nach `<Symcon-Datenverzeichnis>/modules/` auf dem **Symcon-Server** kopieren, anschließend den Symcon-Dienst neu starten. Direkt darunter muss `library.json` liegen. Nicht gleichzeitig per Module Control und lokal installieren: beide Pakete haben dieselben GUIDs. Betriebssystemdetails und Prüfschritte: [Installationsanleitung](docs/INSTALLATION.md).

## Vorbelegte Quellvariablen

| Anzeige | ID | Eigenschaft |
|---|---:|---|
| Außenluft | 46477 | `OutsideID` |
| Zuluft | 29484 | `SupplyID` |
| Abluft | 15325 | `ExtractID` |
| Fortluft | 23757 | `ExhaustID` |
| Volumenstrom | 36483 | `FlowID` |
| Filterrestzeit | 51925 | `FilterID` |
| Betriebsart | 54849 | `ModeID` |

Die Variablen müssen bereits von deiner Geräteintegration aktualisiert werden. Das Modul spricht das Gerät nicht selbst an und benötigt keine Viessmann-Zugangsdaten. Es legt keine Messvariablen an und verändert keine Quellprofile. Temperaturwerte müssen in °C vorliegen. Volumenstrom und Filterrestzeit werden mit `GetValueFormatted()` gemäß dem tatsächlichen Quellprofil angezeigt; ohne Einheit im Profil wird keine erfunden. Balken werden nur angezeigt, wenn das echte Quellprofil bzw. die Darstellung einen gültigen Min-/Max-Bereich liefert. Filtertage und Maximalvolumenströme werden nicht angenommen. Die Wellen sind ausdrücklich schematische Dekoration, keine Messhistorie.

## Betriebsart: ausschließlich vorhandene Werte

Die Kachel liest die wirksame Auswahl von Variable 54849:

- Klassische Profile: benutzerdefiniertes Profil vor Standardprofil, ausschließlich vorhandene Assoziationen.
- Moderne Darstellung **Aufzählung**: ausschließlich die über `IPS_GetVariablePresentation()` gelieferten `OPTIONS` mit ihren tatsächlichen `Value`-/`Caption`-Feldern (API verfügbar ab Symcon 8.1). Eine wirksame moderne Darstellung hat Vorrang vor veralteten Profilfeldern.

Die gefundenen Werte erscheinen als anklickbare Betriebsart-Kacheln. Icons werden nach den tatsächlichen Beschriftungen gewählt; Werte werden dadurch nicht verändert. Es gibt **keine fest hinterlegte Liste** von Betriebsarten. Zusätzlich muss die wirksame Variablenaktion auf eine vorhandene Instanz oder ein vorhandenes Skript zeigen. Eine benutzerdefinierte Aktion `1` sperrt die Steuerung ausdrücklich; sie wird nicht durch die Standardaktion ersetzt.

Jede Anforderung wird serverseitig erneut gegen das **aktuelle** Profil bzw. die wirksame Aufzählung geprüft und mit erhaltenem Integer-/Float-/Boolean-/Stringtyp über `RequestAction(QuellvariablenID, Wert)` weitergegeben. Ein frei eingesandter, veralteter oder nur ungefähr gleicher Wert wird abgewiesen. Die Kachel ändert die angezeigte Betriebsart erst nach dem Quellwert-Update. Fehler der serverseitigen Aktion werden über den SDK-Nachrichtenkanal sichtbar gemacht, da `requestAction()` laut offizieller Dokumentation keinen Rückgabewert hat. Nach 15 Sekunden ohne passenden Quellwert erscheint ein Hinweis; eine erfolgreiche Geräteumschaltung wird dann nicht behauptet.

**Die tatsächlichen Profile, Darstellungsoptionen und Aktionswerte deiner Installation waren hier nicht verfügbar.** Bei „keine passenden Profil-Assoziationen“ mit einer modernen Aufzählung ist die zusätzliche Auswertung ab Version 1.2 vorgesehen. Falls weiterhin keine Auswahl erscheint: in der Kachel **Diagnose anzeigen** öffnen oder in der Instanz **Betriebsart-Diagnose anzeigen** verwenden; dort stehen die tatsächlich erkannten Werte, die Auswahlquelle und die Aktions-ID. Zur Prüfung das nur lesende Skript [docs/inspect-symcon.php](docs/inspect-symcon.php) im IP-Symcon-Skripteditor ausführen. Es führt keine Schaltaktion aus. Profil-Assoziationen belegen allein noch nicht, dass das dahinterliegende Gerät jeden Wert akzeptiert: den Test an der Anlage gemäß Installationsanleitung durchführen.

## Entwicklung und Prüfung

Laufzeit: keine Composer-, npm-, CDN- oder WebHook-Abhängigkeiten. PHP, HTML, CSS und JavaScript liegen im Modul. Die Symcon-Kachel stellt den HTML-SDK-Bridge bereit.

```sh
python3 tests/manifests_test.py
# Offizielle SymconStubs separat klonen, außerhalb des Repositorys:
git clone https://github.com/symcon/SymconStubs.git /tmp/SymconStubs
git -C /tmp/SymconStubs checkout 31f2e0478eb3763236fcf44ea4144271fdeddfd5
php -l Vitovent300FTile/module.php
SYMCON_STUBS=/tmp/SymconStubs php tests/module_test.php
# Optionaler Browser-Test (benötigt Python + Playwright + Chromium):
python3 -m pip install playwright==1.62.0
python3 -m playwright install chromium
python3 tests/browser_test.py
# Bei vorhandenem System-Chromium alternativ:
# CHROMIUM_PATH=/usr/bin/chromium python3 tests/browser_test.py
python3 tests/build.py
python3 tests/package_test.py
```

Der Build erzeugt `docs/downloads/Vitovent300F-Kachel-1.2.zip`, das separate Quellpaket `docs/downloads/Vitovent300F-GitHub-1.2.zip` und jeweils eine SHA-256-Datei. Das ZIP enthält einen Bibliotheksordner mit `library.json`, Modul und Dokumentation. Tests, Git-Daten und Testprofile sind nicht im Laufzeitmodul. Der GitHub-Workflow prüft PHP, Manifeste und Browser und stellt das ZIP als Build-Artefakt bereit. Der Workflow wurde lokal vorbereitet, jedoch noch nicht auf GitHub ausgeführt.

[Prüfbericht und Grenzen](docs/VALIDATION.md) · [API-Verwendung und Quellen](docs/API.md) · [Änderungen](CHANGELOG.md)

Ausgangspunkt ist das vom Nutzer bereitgestellte `Vitovent300F_Kachel_Modul.zip`. Bibliotheks- und Modul-GUID sowie Klassenname und Präfix bleiben für Updates erhalten. Keine Verbindung zu oder Freigabe durch Viessmann oder Symcon. Im Ausgangsarchiv lag keine Lizenzdatei; vor einer öffentlichen Weiterverteilung bitte die Rechte am Ausgangscode klären. Es wurde keine Lizenz stellvertretend erteilt.
