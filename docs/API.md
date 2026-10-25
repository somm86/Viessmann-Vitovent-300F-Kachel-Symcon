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

`requestAction('LoadHistory', JSON-Text)` sendet nur einen festen Quellschlüssel, Unix-Start/-Endzeit und eine Anfragekennung. Der Server bestimmt die tatsächliche ID aus der Instanzkonfiguration. Andere Quellschlüssel als die vier Temperaturen, ungültige Zeitstempel, Zukunftsbereiche und Fenster über 366 Tage werden abgewiesen. Eine kleine Zeitabweichung des Clients von bis zu fünf Minuten wird toleriert. Payload maximal 512 Bytes; Rückgabe maximal 1000 Datensätze. Innerhalb der zulässigen Fenster umfasst die gewählte Aggregationsstufe höchstens etwa 745 stündliche bzw. 367 tägliche Datensätze.

`IPS_GetInstanceListByModuleID('{43192F0B-135B-4CE7-A0A7-1475603F3060}')` sucht vorhandene Archive. `AC_GetLoggingStatus()` und `AC_GetAggregationType()` prüfen aktive Standardprotokollierung. `AC_GetAggregatedValues()` liest Stufe 0 (stündlich) bis einschließlich 31 Tage, darüber Stufe 1 (täglich). Verwendet werden ausschließlich echte `TimeStamp`-/`Avg`-Werte. Es gibt weder `AC_SetLoggingStatus()` noch `SetValue()` im Produktionspfad.

Die Antwort kommt separat als `{"history": {...}}` über `UpdateVisualizationValue()`; reguläre Live-Snapshots und Aktionsfehler bleiben erhalten. Die Kachel ordnet Antworten über Quellschlüssel, Quell-ID und Anfragekennung zu und ignoriert verspätete Antworten. Bei einem Wechsel der Quelle oder Schließen/sofortigem Wiederöffnen wird keine ältere Anfrage als aktuelle angezeigt. Der Diagrammdialog liegt außerhalb des transformierten Dashboards. SVG-Achsen werden passend zur Dialoggröße erzeugt; Datenlücken über die erwartete Aggregationsdauer werden nicht durch Linien verbunden.

Direkt gelesene offizielle Referenzen:

- [AC_GetAggregatedValues](https://www.symcon.de/de/service/dokumentation/modulreferenz/kern-instanzen/archive-control/ac-getaggregatedvalues/): Stufen, Limit, absteigende Rückgabereihenfolge, `TimeStamp`, `Avg` und Aggregationszeiträume.
- [AC_GetLoggedValues](https://www.symcon.de/de/service/dokumentation/modulreferenz/kern-instanzen/archive-control/ac-getloggedvalues/): Empfehlungen zur Verwendung vorhandener Aggregate statt belastender Rohdatenabfragen. Die Kachel verwendet deshalb Aggregatdaten.
- [HTML-SDK openObject](https://www.symcon.de/de/service/dokumentation/entwicklerbereich/sdk-tools/sdk-php/html-sdk/openobject/): öffnet eine maximierte Objektkachel ab 8.2, garantiert jedoch kein spezifisches Temperaturdiagramm samt gewünschter Navigation. Daher wird ein eigener, nur lesender Diagrammdialog umgesetzt.
