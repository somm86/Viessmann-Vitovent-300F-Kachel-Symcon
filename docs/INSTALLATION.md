# Installation und Abnahme auf IP-Symcon

## Voraussetzungen

- IP-Symcon 7.1 oder neuer mit Kachelvisualisierung und HTML-SDK. Die Mindestversion ist aus dem Ausgangsmodul übernommen; reale Versionsabnahme siehe Prüfbericht.
- Bestehende Geräteintegration, die die neun Quellvariablen liefert. Das Kachelmodul benötigt keine Verbindung zu einer übergeordneten Instanz.
- Numerische Temperaturwerte in °C. Numerischer Volumenstrom und Filterrestzeit mit geeigneten Profilen/Einheiten. Betriebsartvariable mit echten Profil-Assoziationen oder einer modernen Darstellung „Aufzählung“ mit hinterlegten Optionen (Auslesen ab Symcon 8.1) und funktionsfähiger Variablenaktion, falls Steuerung gewünscht ist.

## Über Module Control

Das GitHub-Repository muss die Dateien tatsächlich enthalten; ein leeres Repository ist nicht installierbar. `library.json` liegt unmittelbar an der Repository-Wurzel, `module.json` und `module.php` im Unterordner `Vitovent300FTile`.

In Module Control die Repository-URL hinzufügen, veröffentlichten Branch auswählen und danach die Instanz „Vitovent 300-F Kachel“ erstellen. Es handelt sich um ein Git-Modul; eine Aufnahme in den Symcon Module Store wird nicht behauptet.

## Lokal mit dem ZIP

1. ZIP und SHA-256-Datei zusammen herunterladen. Falls möglich die Prüfsumme prüfen (`sha256sum -c Vitovent300F-Kachel-1.8.zip.sha256`).
2. Auf dem Symcon-Server das Datenverzeichnis ermitteln. Es lässt sich im Skripteditor mit `echo IPS_GetKernelDir();` anzeigen. Typisch unter Linux `/var/lib/symcon/`; bei Containern zählt das Verzeichnis **im Container**. Unter Windows den tatsächlichen Datenpfad verwenden.
3. Den entpackten Ordner nach `<Datenverzeichnis>/modules/Vitovent300F_Kachel/` kopieren. Beispiel: `/var/lib/symcon/modules/Vitovent300F_Kachel/library.json`. Keine zusätzliche gleichnamige Ordnerebene einfügen. Lesezugriff für den Symcon-Dienst sicherstellen.
4. Den IP-Symcon-Dienst über die vorhandene Dienst-/Containerverwaltung neu starten. Vorher eigene Konfiguration sichern. Keine zweite Kopie derselben Bibliothek installieren.
5. Instanz erstellen, IDs prüfen, übernehmen und der Kachelvisualisierung hinzufügen.

Die Dateien nicht unter `webfront/` ablegen. Das ZIP wird nicht als HTML-Datei importiert. Die Kachel wird über die Modulinstanz und `GetVisualizationTile()` bereitgestellt.

## Update vom Ausgangsmodul

Die IDs bleiben erhalten:

- Bibliothek `{B24E29F4-981D-4B92-842B-785D28962BD5}`
- Modul `{4305595E-0E5F-4124-AD59-8E11257A1E97}`
- Klasse `Vitovent300FTile`, Präfix `VVF`

Bei lokaler Installation nur die bestehende Bibliothekskopie aktualisieren, nicht parallel eine weitere anlegen. Bestehende Instanz-Eigenschaften bleiben erhalten. Danach Dienst neu starten, Instanzkonfiguration übernehmen und Kachel neu öffnen. Bei Module Control den dort vorgesehenen Update-Ablauf nutzen.

## Verifikation der echten Anlage

1. `docs/inspect-symcon.php` in den Symcon-Skripteditor kopieren und ausführen. Es liest nur die neun vorgegebenen IDs. Bei angepassten IDs diese auch im Diagnoseskript anpassen. Prüfen: Typ, aktueller Wert, formatierter Wert, Profil mit Assoziationen, Standard-/benutzerdefinierte Aktion, wirksame `presentation` und `actionExists`. Ab Version 1.2 zusätzlich die Kachel-Diagnose bzw. den Diagnoseknopf in der Instanz nutzen.
2. Alle vier Temperaturen mit den Quellvariablen vergleichen; insbesondere Abluft und Fortluft nicht verwechseln. Filtereinheit und Volumenstromeinheit aus dem jeweiligen Profil prüfen. Eine fehlende Quellvariable muss „—“ und „Daten unvollständig“ ergeben.
3. Einen von der Geräteintegration ohnehin gelieferten Messwertwechsel beobachten: Die Kachel muss ohne Neuladen reagieren. Profiländerungen werden spätestens beim 30-Sekunden-Abgleich berücksichtigt. Der Zeitstempel unten ist das jüngste Quellupdate und kein Nachweis, dass das Gerät aktuell erreichbar ist.
4. Bei Variable 54849 prüfen, ob jede angezeigte Auswahl exakt einer tatsächlichen Profil-Assoziation oder einem tatsächlichen `OPTIONS.Value` der wirksamen Aufzählung entspricht. Es werden keine beispielhaften Modi als echte Auswahl installiert. Ohne tatsächliche Auswahlwerte oder ohne gültige Aktion bleibt das Feld gesperrt. Eine `VariableCustomAction` von `1` bleibt gesperrt.
5. Wenn das Schalten der Lüftung vor Ort passend ist: den aktuellen Wert merken, einen bereits in der Quellvariablen funktionierenden Profilwert wählen und Quellwert sowie tatsächlichen Gerätezustand kontrollieren. Anschließend den vorherigen Modus wiederherstellen. Der Test verändert den realen Anlagenbetrieb; er wurde in der Cloud nicht ausgeführt.
6. Symcon neu starten und die Kachel erneut öffnen. Zuordnungen, Livewerte und Auswahl müssen erhalten bleiben.

Profilassoziationen geben die erlaubten Werte dieser Integration an, nicht automatisch eine semantische Gerätebestätigung. Ohne erfolgreichen Vor-Ort-Test ist die Gerätewirkung unbestätigt.

## Fehlerbilder

- **Instanz fehlt in der Auswahl:** Ordnerstruktur, Leserechte, `library.json`, Moduldateien und Dienstneustart prüfen. Doppelte Bibliotheks-GUIDs ausschließen.
- **Status 104 / unvollständige Daten:** mindestens eine ID fehlt oder ist keine numerische Variable. Instanz-Eigenschaften prüfen. Dies blockiert die Anzeige vorhandener Werte nicht.
- **Kein Betriebsartmenü:** wirksames Profil mit Assoziationen bzw. die Darstellung „Aufzählung“ mit `OPTIONS` und die wirksame Aktion prüfen. Die neue Darstellung wird ab Version 1.2 direkt ausgelesen. Andere Darstellungen ohne ausdrücklich hinterlegte Auswahlwerte werden nicht in Schaltwerte umgedeutet. Die Diagnose zeigt die verwendete Quelle und Werte.
- **Kein Quellwert nach Schalten:** Aktionsskript/Quellinstanz und deren Log prüfen. Das Kachelmodul schreibt den Quellwert absichtlich nicht selbst. Ein gleichbleibender oder nur lokal gesetzter Quellwert bestätigt keine Gerätewirkung.
- **Statische HTML-Datei lässt sich nicht schalten:** erwartet; der SDK-Bridge steht in der echten Kachelvisualisierung bereit. Kein WebHook, zusätzlicher HTTP-Port oder Zugangsschlüssel nötig.

## Temperaturdiagramme

Nach dem Update auf 1.6 die Instanz übernehmen und die Kachel neu öffnen. Jede der vier Temperaturkarten öffnet per Klick ihren Verlauf. Oben den Zeitraum auswählen; die Pfeile blättern um genau diesen Zeitraum. „Jetzt“ öffnet das aktuelle rollende Zeitfenster. Die Fenster sind feste Dauern in Sekunden (24 Stunden / 7 / 30 / 365 Tage), keine kalendarischen Monats-/Jahresgrenzen.

Im Archive Control müssen die vier Temperaturvariablen zur Protokollierung aktiviert sein und die Aggregation „Standard“ verwenden. Die Kachel aktiviert das Archiv absichtlich nicht selbst. Bereits gelöschte oder nie aufgezeichnete Daten werden nicht ersetzt. Bei frisch aktivierter Protokollierung stehen historische Zeiträume erst im Lauf der Zeit zur Verfügung.

Zur Abnahme jede Temperatur anklicken, die Zuordnung zum Archiv prüfen, Zeitraum wechseln, vor- und zurückblättern und „Jetzt“ wählen. Die 24-Stunden-, 7-Tage- und 30-Tage-Ansicht mit den stündlichen Archiv-Mittelwerten vergleichen; für 365 Tage die täglichen Mittelwerte prüfen. Auch einen leeren Zeitraum kontrollieren: Es darf keine künstliche Kurve erscheinen. Die reale Archivdatenbank war bei der Entwicklung nicht verfügbar; lokale Tests nutzen die offiziellen ArchiveControl-Stubs.

## Momentanleistung und Gesamtverbrauch ab 1.8

In Module Control aktualisieren, die Instanz übernehmen und die Kachel neu öffnen. Die neuen Eigenschaften sind `PowerID` (48602) und `EnergyID` (50335). Mit den tatsächlichen Stromvariablen der Lüftung vergleichen. Die dargestellten Einheiten werden aus der Quelle übernommen; Leistung und Energie werden nicht ineinander umgerechnet.

Für beide Graphen muss die jeweilige Variable archiviert werden. Die Leistung benötigt Standardaggregation. Beim Gesamtverbrauch akzeptiert die Kachel Standard- oder Zähleraggregation und beschriftet die Graphen entsprechend: Standard zeigt Gesamtzählerstände, Zähler zeigt den Verbrauch pro Archivintervall als Balken. Es werden keine Zählerstände addiert oder Leistungswerte als Energie dargestellt. Die Archivkonfiguration wird nicht automatisch geändert.

Zur Abnahme jede neue Karte öffnen, Zeitraum und Navigation prüfen, Einheiten mit dem Quellprofil vergleichen und die Graphen mit den Archivdaten der jeweiligen Aggregationsart abgleichen. Diese reale Datenprüfung kann nur auf der Zielanlage erfolgen.
