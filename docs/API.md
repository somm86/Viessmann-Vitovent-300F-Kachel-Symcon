# API-Verwendung und Quellen

## Verwendete Symcon-Verträge

| Bereich | Verwendung |
|---|---|
| Bibliothek | `library.json` mit unveränderter Bibliotheks-GUID, Autoren-/URL-/Versions-/Build-/Datumsfeldern und Mindestversion |
| Modul | `module.json`: Typ 3, Klasse `Vitovent300FTile`, Präfix `VVF`, eigene unveränderte GUID, keine Parent-/Child-Anforderungen |
| Lifecycle | `IPSModule::Create`, `ApplyChanges`, `MessageSink`; `RegisterPropertyInteger`, `RegisterMessage`, `RegisterReference`, Bereinigung eigener Quellmeldungen und Referenzen |
| Start | `IPS_KERNELSTARTED`, Runlevel-Prüfung über `IPS_GetKernelRunlevel() === KR_READY`; Timer ruft `VVF_Refresh($_IPS["TARGET"])` auf |
| Aktualisierung | `VM_UPDATE`, `VM_DELETE`, `VM_CHANGEPROFILENAME`, `VM_CHANGEPROFILEACTION`; 30-Sekunden-Timer für Änderungen innerhalb eines Profils und sonstige Konfigurationsänderungen |
| Lesen | `IPS_VariableExists`, `IPS_GetVariable`, `GetValue`, `GetValueFormatted`, `IPS_VariableProfileExists`, `IPS_GetVariableProfile` |
| HTML-SDK | `SetVisualizationType(1)`, `GetVisualizationTile()`, `UpdateVisualizationValue(JSON-Text)`, globale JavaScript-Funktion `handleMessage(message)` mit JSON-Parsing |
| Schalten | JavaScript `requestAction('ChangeMode', numerischerProfilwert)` → Instanz-`RequestAction` → Prüfung des wirksamen Profils und der Aktion → globales `RequestAction(QuellID, typisierterWert)` |

Der SDK-Bridge wird von der Symcon-Kachel bereitgestellt; es wird keine erfundene SDK-Datei von einem CDN eingebunden. Ein spät verfügbarer Bridge wird erkannt. Es gibt keinen eigenen `postMessage`-Handler und keine selbst implementierte HTTP-Schalt-API. Aus einer normalen lokalen HTML-Datei heraus kann nicht geschaltet werden.

Das vollständige Datenobjekt wird initial in die HTML-Datei eingesetzt, später als JSON-Text über den SDK-Kanal übertragen. `JSON_HEX_*` schützt das initiale Skript gegen Profiltexte wie `</script>`. JavaScript setzt Profiltexte nur über `textContent` und erzeugt Optionen über DOM-Methoden.

Eine vollständige Anlage wird als Instanzstatus 102, fehlende/ungeeignete Quellen als 104 gemeldet. Eine unsteuerbare Betriebsartvariable ist bei sonst verfügbaren Werten ein gültiger Nur-Lese-Fall. Der interne Snapshot-Puffer unterdrückt unveränderte Timer-Nachrichten; `GetVisualizationTile()` liest die Quellen unabhängig vom Puffer frisch ein.

## Nachprüfbare Quellen

- [Offizielle Symcon-Dokumentation](https://www.symcon.de/de/service/dokumentation/), Entwicklerbereich → SDK-Tools → HTML-SDK / PHP-SDK, sowie Befehlsreferenz → Variablen und Profile. Die Website war bei dieser Bearbeitung durch den Cloud-Netzwerkproxy gesperrt (HTTP 403); eine vollständige direkte Online-Dokumentationsprüfung ist deshalb **nicht** erfolgt.
- [Offizielle SymconStubs, fester Prüfstand](https://github.com/symcon/SymconStubs/tree/31f2e0478eb3763236fcf44ea4144271fdeddfd5): `ModuleStubs.php` (Visualisierung, Timer, Referenzen, Nachrichten), `GlobalStubs.php` (Aktionspriorität und Dispatch), `MessageStubs.php` (Nachrichtenkonstanten), `Validator.php` (Manifestanforderungen). Die Verhaltenstests benutzen diese offiziellen Stubs, nicht einen eigenen Ersatz für die PHP-API. Manifestfelder wurden lokal separat geprüft; der PHPUnit-Validator wurde nicht ausgeführt.
- [Offizielles Modul symcon/Strompreis: PHP](https://github.com/symcon/Strompreis/blob/75f71d6bebd5543a3d0c1ca7f7878c74c7f2277a/PowerPrice/module.php) und [HTML](https://github.com/symcon/Strompreis/blob/75f71d6bebd5543a3d0c1ca7f7878c74c7f2277a/PowerPrice/module.html): bestätigtes Herstellerbeispiel für Visualisierungstyp, Tile-Methode, JSON-Textübertragung und `handleMessage`.

Die Signatur `requestAction(Ident, Wert)` wird entsprechend dem HTML-SDK-Aufrufmodell des Ausgangsmoduls genutzt. Die Browserprüfung simuliert diese Schnittstelle. Der Browser-Transport, Authentifizierung und Fehlerweitergabe des tatsächlichen Symcon-Frontends sind ohne Symcon-Laufzeit noch nicht verifiziert. Ein aufgelöstes JavaScript-Promise wird deshalb nie als Gerätebestätigung interpretiert; maßgeblich bleibt das Quellwertupdate.
