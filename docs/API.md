# API-Verwendung und Quellen

## Verwendete Symcon-Verträge

| Bereich | Verwendung |
|---|---|
| Bibliothek/Modul | `library.json`, `module.json`: unveränderte GUIDs, Typ 3, Klasse `Vitovent300FTile`, Präfix `VVF`, keine Parent-/Child-Anforderungen |
| Lifecycle | `IPSModule::Create`, `ApplyChanges`, `MessageSink`; Eigenschaften, Referenzen und eigene Quellmeldungen registrieren/bereinigen |
| Start | `IPS_KERNELSTARTED`, Runlevel `KR_READY`, Timer über `VVF_Refresh($_IPS["TARGET"])` |
| Aktualisierung | `VM_UPDATE`, `VM_DELETE`, `VM_CHANGEPROFILENAME`, `VM_CHANGEPROFILEACTION`; 30-Sekunden-Abgleich für Änderungen innerhalb von Profilen/Darstellungen |
| Lesen | `IPS_VariableExists`, `IPS_GetVariable`, `GetValue`, `GetValueFormatted`, `IPS_VariableProfileExists`, `IPS_GetVariableProfile` |
| Moderne Auswahl | Falls verfügbar: `IPS_GetVariablePresentation()` (ab 8.1), wirksame Darstellung „Aufzählung“, JSON-kodierte `OPTIONS`, ausschließlich deren `Value` und `Caption`. Bool/String/Integer/Float entsprechend dem tatsächlichen Variablentyp |
| Klassische Auswahl | Wirksame Legacy-Darstellung bzw. auf älteren Versionen Custom-/Standardprofil; ausschließlich vorhandene Assoziationen. Moderne Darstellung hat Vorrang vor veralteten Profilfeldern |
| HTML-SDK | `SetVisualizationType(1)`, `GetVisualizationTile()`, `UpdateVisualizationValue(JSON-Text)`, globales JavaScript `handleMessage(message)` |
| Schalten | `requestAction('ChangeMode', Originalwert)` → Instanz-`RequestAction` → erneute Prüfung der wirksamen Auswahl und Aktion → globales `RequestAction(QuellID, typisierterWert)` |
| Diagnose | `VVF_GetDiagnostics(InstanzID)` sowie „Diagnose anzeigen“ innerhalb der Kachel; nur lesende Metadaten/Optionen, keine Zugangsdaten |

Die SDK-Bridge stellt die Symcon-Kachel bereit. Es gibt keine zusätzliche SDK-Datei, kein CDN, keinen eigenen `postMessage`-Handler und keine selbst implementierte HTTP-Schalt-API. Eine spät verfügbare Bridge wird erkannt.

**`requestAction()` liefert laut offizieller Dokumentation `void`.** Ein PHP-Fehler lässt sich daher nicht zuverlässig durch `await`/Promise-Catch erkennen. Das Modul speichert den Fehler kurzzeitig im Instanzpuffer und veröffentlicht ihn über `UpdateVisualizationValue()` im Feld `actionError`. Die Kachel beendet dann den Wartezustand und zeigt den tatsächlichen Fehler als Text. Unterstützende Bridge-Versionen mit einem zusätzlichen Promise können ebenfalls einen lokalen Fehler melden. Eine abgesendete Anforderung bestätigt nie den Gerätebetrieb; entscheidend ist das Quellwertupdate. Nach 15 Sekunden ohne passenden Wert erscheint ein Hinweis.

Initiale Daten sind mit `JSON_HEX_*` gegen Skriptabschluss im Profiltext geschützt. Beschriftungen und Diagnosen werden ausschließlich über `textContent` eingesetzt. Dynamische Buttons erhalten Originalwerte über JavaScript-Closures, ohne String-/Zahlkonvertierung. Es gibt keine feste Zuordnung „Grundbetrieb = x“ etc.; anhand echter Beschriftungen wird nur ein dekoratives Icon gewählt.

Balken sind nur sichtbar, wenn das Quellprofil oder die wirksame Darstellung echte Min-/Max-Grenzen bereitstellt. Sie zeigen die Position innerhalb dieses Bereichs, keine unabhängig berechnete Filterlebensdauer. Die Wellen und Luftwege sind schematische Darstellung und keine historischen Messwerte.

## Direkt geprüfte offizielle Dokumentation

Die Website ist inzwischen erreichbar. Folgende Seiten wurden für Version 1.2 direkt gelesen:

- [HTML-SDK](https://www.symcon.de/de/service/dokumentation/entwicklerbereich/sdk-tools/sdk-php/html-sdk/): Mindestversion 7.1, Visualisierungstyp, initiales HTML, bidirektionale Nachrichten.
- [JavaScript requestAction](https://www.symcon.de/de/service/dokumentation/entwicklerbereich/sdk-tools/sdk-php/html-sdk/requestaction/): `void requestAction(string Ident, mixed Wert)`; ruft PHP-`RequestAction` der Modulinstanz auf.
- [IPS_GetVariablePresentation](https://www.symcon.de/de/service/dokumentation/befehlsreferenz/variablenverwaltung/ips-getvariablepresentation/): ab 8.1, liefert die aktuelle wirksame Darstellung samt Parametern.
- [Aufzählung](https://www.symcon.de/de/service/dokumentation/entwicklerbereich/sdk-tools/sdk-php/darstellungen/aufzaehlung/): Darstellung `{52D9E126-D7D2-2CBB-5E62-4CF7BA7C5D82}`, `OPTIONS` als JSON-Liste mit `Value`/`Caption`.

Weitere geprüfte Referenzen:

- [Offizielle SymconStubs, fester Prüfstand](https://github.com/symcon/SymconStubs/tree/31f2e0478eb3763236fcf44ea4144271fdeddfd5): Verhaltenstests nutzen die offiziellen APIs und Dispatcher. Manifestfelder sind separat geprüft; der PHPUnit-Validator wurde nicht ausgeführt.
- [Offizielles Beispiel symcon/Strompreis: PHP](https://github.com/symcon/Strompreis/blob/75f71d6bebd5543a3d0c1ca7f7878c74c7f2277a/PowerPrice/module.php) und [HTML](https://github.com/symcon/Strompreis/blob/75f71d6bebd5543a3d0c1ca7f7878c74c7f2277a/PowerPrice/module.html): JSON-Nachrichten und Tile-Methoden.

Der tatsächliche Frontend-Transport und die Gerätewirkung müssen weiterhin auf der Symcon-Anlage geprüft werden. Die lokalen Browserprüfungen simulieren die dokumentierte Bridge.

## Temperaturverlauf ab 1.6

`requestAction('LoadHistory', JSON-Text)` sendet nur einen festen Quellschlüssel, Unix-Start/-Endzeit und eine Anfragekennung. Der Server bestimmt die tatsächliche ID aus der Instanzkonfiguration. Andere Quellschlüssel als die vier Temperaturen und die konfigurierten Quellen `power`/`energy`, ungültige Zeitstempel, Zukunftsbereiche und Fenster über 366 Tage werden abgewiesen. Eine kleine Zeitabweichung des Clients von bis zu fünf Minuten wird toleriert. Payload maximal 512 Bytes; Rückgabe maximal 1000 Datensätze. Innerhalb der zulässigen Fenster umfasst die gewählte Aggregationsstufe höchstens etwa 745 stündliche bzw. 367 tägliche Datensätze.

`IPS_GetInstanceListByModuleID('{43192F0B-135B-4CE7-A0A7-1475603F3060}')` sucht vorhandene Archive. `AC_GetLoggingStatus()` und `AC_GetAggregationType()` prüfen aktive Standardprotokollierung. `AC_GetAggregatedValues()` liest für Gesamtverbrauch ab 7 Tagen Stufe 1 (täglich), darunter Stufe 0 (stündlich). Für Temperatur und Leistung gilt weiter: bis einschließlich 31 Tage stündlich, darüber täglich. Verwendet werden ausschließlich echte `TimeStamp`-/`Avg`-Werte. Bei Standardaggregation ist `Avg` der Mittelwert, bei Zähleraggregation die Summe positiver Zählerdifferenzen des Intervalls. Es gibt weder `AC_SetLoggingStatus()` noch `SetValue()` im Produktionspfad.

Die Antwort kommt separat als `{"history": {...}}` über `UpdateVisualizationValue()`; reguläre Live-Snapshots und Aktionsfehler bleiben erhalten. Die Kachel ordnet Antworten über Quellschlüssel, Quell-ID und Anfragekennung zu und ignoriert verspätete Antworten. Bei einem Wechsel der Quelle oder Schließen/sofortigem Wiederöffnen wird keine ältere Anfrage als aktuelle angezeigt. Der Diagrammdialog liegt außerhalb des transformierten Dashboards. SVG-Achsen werden passend zur Dialoggröße erzeugt; Datenlücken über die erwartete Aggregationsdauer werden nicht durch Linien verbunden.

Direkt gelesene offizielle Referenzen:

- [AC_GetAggregatedValues](https://www.symcon.de/de/service/dokumentation/modulreferenz/kern-instanzen/archive-control/ac-getaggregatedvalues/): Stufen, Limit, absteigende Rückgabereihenfolge, `TimeStamp`, `Avg` und Aggregationszeiträume.
- [AC_GetLoggedValues](https://www.symcon.de/de/service/dokumentation/modulreferenz/kern-instanzen/archive-control/ac-getloggedvalues/): Empfehlungen zur Verwendung vorhandener Aggregate statt belastender Rohdatenabfragen. Die Kachel verwendet deshalb Aggregatdaten.
- [HTML-SDK openObject](https://www.symcon.de/de/service/dokumentation/entwicklerbereich/sdk-tools/sdk-php/html-sdk/openobject/): öffnet eine maximierte Objektkachel ab 8.2, garantiert jedoch kein spezifisches Temperaturdiagramm samt gewünschter Navigation. Daher wird ein eigener, nur lesender Diagrammdialog umgesetzt.

## Stromwerte ab 1.8

Zwei zusätzliche Integer-Eigenschaften `PowerID=48602` und `EnergyID=50335` sind in denselben Lifecycle, Referenzen, Meldungen und Live-Snapshot eingebunden. Seit 1.11 gibt es einschließlich Solltemperatur zehn Quellen und sechs erlaubte Verlaufsschlüssel. Livewerte sind read-only; Betriebsart und Lüftungs-Solltemperatur nutzen ihre jeweils eigene Quellvariablenaktion.

Der History-Server liefert zusätzlich `unit` aus Profil-Suffix bzw. Darstellungs-Suffix und `kind`:

- `average`: Temperatur bzw. Leistung, nur Standardaggregation.
- `level`: Gesamtverbrauch mit Standardaggregation; `Avg` bleibt der gemittelte Gesamtzählerstand.
- `consumption`: Gesamtverbrauch mit Zähleraggregation; `Avg` ist laut offizieller Archive-Control-Dokumentation die Verbrauchsmenge des jeweiligen Intervalls. `Duration` bleibt für die Breite der Verbrauchsbalken erhalten, auch bei unterschiedlich langen Archivintervallen.

Die Oberfläche zeichnet `consumption` als Balken, `average` und `level` als Kurven. Verbrauchsachsen und Tooltips sowie der Gesamt- und Monatsverbrauch werden mit genau zwei Nachkommastellen formatiert. Die zugrunde liegenden Werte bleiben ungerundet. Der linke Achsenrand berücksichtigt Zahlen und Einheit. Es gibt keine feste Annahme über W/kW oder Wh/kWh und keine automatische Umrechnung.

## Monatsverbrauch ab 1.10

Der Snapshot enthält `monthlyEnergy` mit Wert, Quelleneinheit, Monatsbeginn, Aktualisierungszeit, Serverzeitzone und gegebenenfalls einem Hinweis. Der Wert wird über `AC_GetAggregatedValues(Archiv, EnergyID, 3, strtotime('first day of this month 00:00:00'), time(), 2)` aus dem laufenden Monatsaggregat gelesen, ausschließlich bei aktiver Zählerarchivierung. Nur ein Datensatz mit exakt dem aktuellen Monatsbeginn wird akzeptiert. Gesamtzählerstands-Mittelwerte werden nie als Verbrauch ausgegeben.

Ein bis zu 60 Sekunden gültiger Cache vermeidet Archivzugriffe bei jedem anderen Sensorupdate. Ein anderer Monatsbeginn, eine andere Energiequelle, neue Quellwerte/-zeitstempel oder eine neue Einheit ändern den Cache-Schlüssel. Übernehmen der Instanzkonfiguration löscht den Cache ebenfalls. Fehlende Werte führen zu `value=null`; ein echter Zahlenwert 0 bleibt erhalten und wird als 0,00 angezeigt. Mit der Solltemperatur ab 1.11 gibt es zehn Quellvariablen.

Der Verbrauchsgraph nutzt weiterhin Stufe 1 für Zeiträume ab 7 Tagen, auch bei 365 Tagen. Die Monatsaggregation dient ausschließlich dem Zusatzwert für den laufenden Monat. Verbrauchswerte werden nur für die Anzeige mit zwei Nachkommastellen gerundet; Originalpräfix und -suffix bleiben erhalten, und Archivdaten werden nicht verändert.

## ID-Migration und Solltemperatur ab 1.11

Die offizielle [Migrate-Dokumentation](https://www.symcon.de/de/service/dokumentation/entwicklerbereich/sdk-tools/sdk-php/module/migrate/) wurde direkt geprüft: Der Kernel übergibt die gespeicherte Persistenz vor dem nachfolgenden `ApplyChanges`, beim Modul-Update oder Start. Das Modul ändert nur die sieben bekannten alten Standard-IDs und ergänzt das persistente Attribut `SourcesUpdated20261026`. Andere Properties/Attribute und vom Standard abweichende IDs bleiben erhalten. Bei bereits gesetztem Marker wird keine weitere Migration durchgeführt.

`SetpointID=41384` ist eine zusätzliche Quelle. `setpoint` im Snapshot beschreibt aktuelle Grenzen, Schrittweite, konkrete Auswahlwerte, Quelltyp und effektive Aktion; `setpointError` ist ein eigenständiger Fehlerkanal. `requestAction('SetRoomTemperature', Zahl)` liest diese Konfiguration erneut, prüft Typ, echte Grenzen, deklarierte Schrittweite bzw. konkrete Auswahl und leitet typgetreu an `RequestAction(SetpointID, Wert)` weiter. CustomAction=1 sperrt auch hier die Steuerung. Es gibt weder erfundene Solltemperaturbereiche noch direkte `SetValue`-Schreibzugriffe.

Die Kachel erhält ab 1.12 ausschließlich ein Dropdown im 1-°C-Raster und einen Übernehmen-Button. Eine noch nicht abgesendete Auswahl bleibt bei anderen Sensorupdates bestehen. Bis zum Quellwertupdate bleibt der bisherige Sollwert angezeigt. Ein eigener 15-Sekunden-Timeout und der separate Serverfehlerkanal lösen den Wartezustand. `VVF_GetSetpointDiagnostics(InstanzID)` liefert nur lesende Konfigurationsdaten. Reale Hardwaregrenzen und Gerätewirkung sind erst auf dem Zielserver verifizierbar.

## Solltemperatur-Dropdown ab 1.13

`setpoint.choices` wird aus dem vom Nutzer vorgegebenen Bereich **15 bis 30 °C in 1-°C-Schritten** erzeugt. Vorhandene Quellgrenzen, native Schrittweite und konkrete Assoziationen werden zusätzlich geprüft. Ausgeschlossene Werte erscheinen nicht in der Auswahl. Ohne native Grenzen gilt der explizit angeforderte Bereich 15–30. `step=1` beschreibt das UI-Raster und `sourceStep` die ursprüngliche Quellschrittweite. Vor jedem Request berechnet der Server die Liste neu und akzeptiert nur aktuell angebotene Werte. Die Quellprofile selbst bleiben unverändert.

## Filterzeit, Uhr und zentrale Sollwertanzeige ab 1.14

Die Oberfläche zeigt die tatsächliche Filterrestzeit in Tagen. Explizit erkannte Zeiteinheiten (Sekunden, Minuten, Stunden, Tage) werden in Tage umgerechnet, unbekannte Einheiten nicht geraten. Die Anzeige nutzt höchstens zwei Nachkommastellen; Rohwert und profilbasierter Bereichsbalken bleiben unverändert. `filterClock` zeigt weiterhin die lokale Browser-Uhrzeit und wird über den bestehenden 1-Sekunden-Timer aktualisiert.

`setpointValue` liegt als SVG-Text im mittigen `setpoint-readout` des zentralen Gerätesymbols. `renderSetpoint` setzt ausschließlich `textContent` und ergänzt den zugänglichen SVG-Beschreibungstext. Die alte separate Wertanzeige in der unteren Bedienkarte ist entfernt; Dropdown und Übernehmen bleiben erhalten. Quellbestätigung, Fehlerbehandlung und native Aktionsweiterleitung sind unverändert.
