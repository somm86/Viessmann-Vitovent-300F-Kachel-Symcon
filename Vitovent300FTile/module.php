<?php

class Vitovent300FTile extends IPSModule
{
    private const ENUMERATION = '{52D9E126-D7D2-2CBB-5E62-4CF7BA7C5D82}';
    private const LEGACY = '{4153A8D4-5C33-C65F-C1F3-7B61AAF99B1C}';

    private const SOURCES = [
        'outside' => ['OutsideID', 31044, 'Außenluft'],
        'supply' => ['SupplyID', 42234, 'Zuluft'],
        'extract' => ['ExtractID', 58476, 'Abluft'],
        'exhaust' => ['ExhaustID', 22909, 'Fortluft'],
        'flow' => ['FlowID', 34190, 'Volumenstrom'],
        'filter' => ['FilterID', 38673, 'Filterrestzeit'],
        'mode' => ['ModeID', 28301, 'Betriebsart'],
        'power' => ['PowerID', 48602, 'Momentanleistung'],
        'energy' => ['EnergyID', 50335, 'Gesamtverbrauch'],
        'setpoint' => ['SetpointID', 41384, 'Lüftung Raumtemperatur Soll']
    ];

    public function Create()
    {
        parent::Create();
        foreach (self::SOURCES as $source) {
            $this->RegisterPropertyInteger($source[0], $source[1]);
        }
        $this->RegisterAttributeBoolean('SourcesUpdated20261026', true);
        $this->RegisterTimer('Refresh', 0, 'VVF_Refresh($_IPS["TARGET"]);');
        $this->SetVisualizationType(1);
    }

    public function Migrate($JSONData)
    {
        parent::Migrate($JSONData);
        $state = json_decode($JSONData);
        if (!is_object($state) || !isset($state->configuration) || !is_object($state->configuration)) return '';
        if (!isset($state->attributes) || !is_object($state->attributes)) $state->attributes = new stdClass();
        if (($state->attributes->SourcesUpdated20261026 ?? false) === true) return '';
        $oldDefaults = ['OutsideID' => 46477, 'SupplyID' => 29484, 'ExtractID' => 15325,
            'ExhaustID' => 23757, 'FlowID' => 36483, 'FilterID' => 51925, 'ModeID' => 54849];
        foreach (self::SOURCES as $source) {
            $property = $source[0];
            if (isset($oldDefaults[$property]) && ($state->configuration->$property ?? null) === $oldDefaults[$property]) {
                $state->configuration->$property = $source[1];
            }
        }
        $state->attributes->SourcesUpdated20261026 = true;
        return json_encode($state, JSON_THROW_ON_ERROR);
    }

    public function ApplyChanges()
    {
        parent::ApplyChanges();
        $this->SetBuffer('ActionError', '');
        $this->SetBuffer('SetpointError', '');
        $this->SetBuffer('MonthlyEnergyCache', '');
        $this->RegisterMessage(0, IPS_KERNELSTARTED);
        // Only remove subscriptions owned by this module; retain the kernel subscription.
        foreach ($this->GetMessageList() as $sender => $messages) {
            foreach ($messages as $message) {
                if (in_array($message, $this->sourceMessages(), true)) {
                    $this->UnregisterMessage((int) $sender, $message);
                }
            }
        }
        foreach ($this->GetReferenceList() as $id) {
            $this->UnregisterReference($id);
        }
        foreach (self::SOURCES as $source) {
            $id = $this->ReadPropertyInteger($source[0]);
            if ($id > 0 && IPS_VariableExists($id)) {
                $this->RegisterReference($id);
                foreach ($this->sourceMessages() as $message) {
                    $this->RegisterMessage($id, $message);
                }
            }
        }
        $this->SetTimerInterval('Refresh', 30000);
        if (IPS_GetKernelRunlevel() === KR_READY) {
            $this->Refresh();
        }
    }

    public function MessageSink($timeStamp, $senderID, $messageID, $data)
    {
        parent::MessageSink($timeStamp, $senderID, $messageID, $data);
        if ($messageID === IPS_KERNELSTARTED ||
            (in_array($messageID, $this->sourceMessages(), true) && in_array((int) $senderID, $this->sourceIDs(), true))) {
            $this->Refresh();
        }
    }

    public function GetConfigurationForm()
    {
        $elements = [['type' => 'Label', 'caption' => 'Vorhandene Lüftungsvariablen auswählen. Die Betriebsart verwendet nur vorhandene Profil-Assoziationen oder Aufzählungsoptionen. Für die Solltemperatur gelten die deklarierten Grenzen und Schritte der Quelle. Geschaltet wird jeweils über die vorhandene Variablenaktion.']];
        foreach (self::SOURCES as $source) {
            $elements[] = ['type' => 'SelectVariable', 'name' => $source[0], 'caption' => $source[2]];
        }
        $elements[] = ['type' => 'Label', 'caption' => 'Temperaturen müssen in °C vorliegen. Einheiten für Volumenstrom und Filterrestzeit stammen aus den Quellprofilen. Ohne Profil wird keine Einheit angenommen.'];
        return $this->encode(['elements' => $elements, 'actions' => [['type' => 'Button', 'caption' => 'Betriebsart-Diagnose anzeigen', 'onClick' => 'echo VVF_GetDiagnostics($id);'], ['type' => 'Button', 'caption' => 'Solltemperatur-Diagnose anzeigen', 'onClick' => 'echo VVF_GetSetpointDiagnostics($id);']], 'status' => [
            ['code' => 102, 'icon' => 'active', 'caption' => 'Alle zehn Quellvariablen verfügbar'],
            ['code' => 104, 'icon' => 'inactive', 'caption' => 'Quellvariable fehlt oder hat einen ungeeigneten Datentyp']
        ]]);
    }

    public function Refresh()
    {
        if (IPS_GetKernelRunlevel() !== KR_READY) {
            return;
        }
        $snapshot = $this->snapshot();
        $this->SetStatus(count($snapshot['missing']) === 0 ? 102 : 104);
        $json = $this->encode($snapshot);
        // Polling also catches edits inside profiles without publishing unchanged data.
        if ($json !== $this->GetBuffer('LastSnapshot')) {
            $this->SetBuffer('LastSnapshot', $json);
            $this->UpdateVisualizationValue($json);
        }
    }

    public function GetVisualizationTile()
    {
        return str_replace('/*__INITIAL_DATA__*/null', $this->encode($this->snapshot()), file_get_contents(__DIR__ . '/tile.html'));
    }

    public function RequestAction($ident, $value)
    {
        if ($ident === 'SetRoomTemperature') {
            $this->setRoomTemperature($value);
            return;
        }
        if ($ident === 'LoadHistory') {
            $this->loadHistory($value);
            return;
        }
        $this->SetBuffer('ActionError', '');
        try {
            if ($ident !== 'ChangeMode') {
                throw new InvalidArgumentException('Unbekannte Aktion.');
            }
            if (IPS_GetKernelRunlevel() !== KR_READY) {
                throw new RuntimeException('IP-Symcon ist noch nicht bereit.');
            }
            // Re-read the effective presentation/profile and action on EVERY request.
            $mode = $this->modeConfiguration();
            if (!$mode['writable']) {
                throw new RuntimeException($mode['reason']);
            }
            $candidate = $value;
            if ($mode['type'] === 1 && is_float($value)) {
                $candidate = is_finite($value) && floor($value) === $value && abs($value) <= 9007199254740991 ? (int) $value : null;
            } elseif ($mode['type'] === 2 && (is_float($value) || is_int($value))) {
                $candidate = (float) $value;
            }
            foreach ($mode['choices'] as $choice) {
                if ($candidate === $choice['value']) {
                    // Never SetValue: use the source's effective action and original value type.
                    RequestAction($this->ReadPropertyInteger('ModeID'), $choice['value']);
                    $this->Refresh();
                    return;
                }
            }
            throw new InvalidArgumentException('Dieser Wert ist in der aktuellen Betriebsartauswahl nicht freigegeben.');
        } catch (Throwable $e) {
            // requestAction() is documented as void: PHP errors need a separate SDK push.
            $this->SetBuffer('ActionError', $e->getMessage());
            $this->SendDebug('Betriebsartaktion fehlgeschlagen', $e->getMessage(), 0);
            $this->Refresh();
            throw $e;
        }
    }

    public function GetDiagnostics(): string
    {
        return $this->encode($this->modeConfiguration());
    }

    public function GetSetpointDiagnostics(): string
    {
        return $this->encode($this->setpointConfiguration());
    }

    private function setpointConfiguration(): array
    {
        $id = $this->ReadPropertyInteger('SetpointID');
        $result = ['variableID' => $id, 'writable' => false, 'reason' => 'Solltemperaturvariable fehlt.',
            'type' => null, 'min' => null, 'max' => null, 'step' => null, 'choices' => [], 'actionID' => 0];
        if ($id <= 0 || !IPS_VariableExists($id)) return $result;
        $variable = IPS_GetVariable($id);
        $result['type'] = $variable['VariableType'];
        if (!in_array($result['type'], [1, 2], true)) {
            $result['reason'] = 'Solltemperatur benötigt eine numerische Variable.';
            return $result;
        }
        $presentation = $this->presentation($id);
        $profile = $this->profile($variable, $id);
        $min = $profile['MinValue'] ?? $presentation['MIN'] ?? null;
        $max = $profile['MaxValue'] ?? $presentation['MAX'] ?? null;
        $step = $profile['StepSize'] ?? $presentation['STEP_SIZE'] ?? null;
        if (is_numeric($min) && is_numeric($max) && is_finite((float) $min) && is_finite((float) $max) && $max > $min) {
            $result['min'] = (float) $min;
            $result['max'] = (float) $max;
        }
        if (is_numeric($step) && is_finite((float) $step) && $step > 0) $result['step'] = (float) $step;
        if (($presentation['PRESENTATION'] ?? '') === self::ENUMERATION) {
            $options = json_decode($presentation['OPTIONS'] ?? '[]', true);
            if (is_array($options)) foreach ($options as $option) {
                if (is_array($option) && isset($option['Value'], $option['Caption']) &&
                    (is_int($option['Value']) || is_float($option['Value'])) && is_finite((float) $option['Value'])) {
                    $result['choices'][] = ['value' => $option['Value'], 'label' => (string) $option['Caption']];
                }
            }
            if (!$result['choices']) { $result['reason'] = 'Keine Solltemperatur-Auswahlwerte vorhanden.'; return $result; }
        } elseif ($profile !== null && !empty($profile['Associations'])) {
            foreach ($profile['Associations'] as $association) {
                if ((is_int($association['Value']) || is_float($association['Value'])) && is_finite((float) $association['Value'])) {
                    $result['choices'][] = ['value' => $association['Value'], 'label' => (string) $association['Name']];
                }
            }
        }
        // The user interface uses 1 °C increments, without changing the source profile.
        $result['sourceStep'] = $result['step'];
        $result['step'] = 1;
        $result['relative'] = false;
        $current = GetValue($id);
        $gridOffset = (is_int($current) || is_float($current)) && is_finite((float) $current) ? $current - floor($current) : 0;
        $nativeChoices = $result['choices'];
        $result['choices'] = [];
        $accept = function ($candidate) use ($result) {
            if ($result['min'] !== null && ($candidate < $result['min'] || $candidate > $result['max'])) return false;
            if ($result['sourceStep'] !== null) {
                $steps = ($candidate - ($result['min'] ?? 0)) / $result['sourceStep'];
                if (abs($steps - round($steps)) > 0.000001) return false;
            }
            return true;
        };
        if ($nativeChoices) {
            foreach ($nativeChoices as $choice) {
                $value = $choice['value'];
                if (abs(($value - $gridOffset) - round($value - $gridOffset)) < 0.000001 && $accept($value)) {
                    $result['choices'][] = ['value' => $value, 'label' => (string) $value . ' °C'];
                }
            }
        } elseif ($result['min'] !== null) {
            if ($result['max'] - $result['min'] <= 1000) {
                for ($value = ceil($result['min'] - $gridOffset) + $gridOffset; $value <= $result['max']; $value++) {
                    if ($accept($value)) $result['choices'][] = ['value' => $value, 'label' => (string) $value . ' °C'];
                }
            }
        } else {
            // No invented absolute limits: permit only current -1/current/current +1.
            $current = GetValue($id);
            if (is_int($current) || is_float($current)) {
                $result['relative'] = true;
                foreach ([-1, 0, 1] as $offset) {
                    $value = $current + $offset;
                    if (is_finite((float) $value) && $accept($value)) {
                        $result['choices'][] = ['value' => $value, 'label' => (string) $value . ' °C'];
                    }
                }
            }
        }
        usort($result['choices'], function ($a, $b) { return $a['value'] <=> $b['value']; });
        if (!$result['choices']) {
            $result['reason'] = 'Keine passenden Solltemperaturen in 1-°C-Schritten innerhalb der Quellvorgaben verfügbar.';
            return $result;
        }
        $action = ($variable['VariableCustomAction'] ?? 0) > 0 ? $variable['VariableCustomAction'] : ($variable['VariableAction'] ?? 0);
        $result['actionID'] = $action;
        if ($action < 10000 || (!IPS_InstanceExists($action) && !IPS_ScriptExists($action))) {
            $result['reason'] = 'Solltemperatur hat keine gültige Variablenaktion oder ist deaktiviert.';
            return $result;
        }
        $result['writable'] = true;
        $result['reason'] = '';
        return $result;
    }

    private function setRoomTemperature($value)
    {
        $this->SetBuffer('SetpointError', '');
        try {
            if (IPS_GetKernelRunlevel() !== KR_READY) throw new RuntimeException('IP-Symcon ist noch nicht bereit.');
            $config = $this->setpointConfiguration();
            if (!$config['writable']) throw new RuntimeException($config['reason']);
            if ((!is_int($value) && !is_float($value)) || !is_finite((float) $value)) throw new InvalidArgumentException('Solltemperatur muss eine endliche Zahl sein.');
            if ($config['type'] === 1 && (float) (int) $value !== (float) $value) throw new InvalidArgumentException('Diese Solltemperaturvariable benötigt ganze Zahlen.');
            if ($config['choices']) {
                $allowed = false;
                foreach ($config['choices'] as $choice) if ((float) $choice['value'] === (float) $value) $allowed = true;
                if (!$allowed) throw new InvalidArgumentException('Solltemperatur ist nicht in der aktuellen Auswahl enthalten.');
            }
            if ($config['min'] !== null && ($value < $config['min'] || $value > $config['max'])) throw new InvalidArgumentException('Solltemperatur liegt außerhalb der konfigurierten Grenzen.');
            if (!$config['choices'] && $config['step'] !== null) {
                $steps = ($value - ($config['min'] ?? 0)) / $config['step'];
                if (abs($steps - round($steps)) > 0.000001) throw new InvalidArgumentException('Solltemperatur entspricht nicht der konfigurierten Schrittweite.');
            }
            RequestAction($config['variableID'], $config['type'] === 1 ? (int) $value : (float) $value);
            $this->Refresh();
        } catch (Throwable $e) {
            $this->SetBuffer('SetpointError', $e->getMessage());
            $this->SendDebug('Solltemperaturaktion fehlgeschlagen', $e->getMessage(), 0);
            $this->Refresh();
            throw $e;
        }
    }

    private function loadHistory($value)
    {
        $reply = ['requestId' => '', 'key' => '', 'points' => [], 'error' => ''];
        try {
            if (!is_string($value) || strlen($value) > 512) {
                throw new InvalidArgumentException('Ungültige Verlaufsanfrage.');
            }
            $request = json_decode($value, true, 32, JSON_THROW_ON_ERROR);
            if (!is_array($request) || !is_string($request['requestId'] ?? null) ||
                !preg_match('/^[a-zA-Z0-9_-]{1,64}$/', $request['requestId'])) {
                throw new InvalidArgumentException('Ungültige Anfragekennung.');
            }
            $reply['requestId'] = $request['requestId'];
            $key = $request['key'] ?? '';
            if (!is_string($key) || !in_array($key, ['outside', 'supply', 'extract', 'exhaust', 'power', 'energy'], true)) {
                throw new InvalidArgumentException('Verlauf nur für konfigurierte Temperatur- und Energiequellen verfügbar.');
            }
            $reply['key'] = $key;
            $start = $request['start'] ?? null;
            $end = $request['end'] ?? null;
            if (!is_int($start) || !is_int($end) || $start < 0 || $end <= $start ||
                $end > time() + 300 || $end - $start > 366 * 86400) {
                throw new InvalidArgumentException('Ungültiger Zeitraum (maximal 366 Tage, keine Zukunft).');
            }
            if (IPS_GetKernelRunlevel() !== KR_READY) {
                throw new RuntimeException('IP-Symcon ist noch nicht bereit.');
            }
            $id = $this->ReadPropertyInteger(self::SOURCES[$key][0]);
            if ($id <= 0 || !IPS_VariableExists($id) || !in_array(IPS_GetVariable($id)['VariableType'], [1, 2], true)) {
                throw new RuntimeException('Messwertvariable nicht verfügbar.');
            }
            $reply['variableID'] = $id;
            $archive = 0;
            foreach (IPS_GetInstanceListByModuleID('{43192F0B-135B-4CE7-A0A7-1475603F3060}') as $candidate) {
                if (AC_GetLoggingStatus($candidate, $id)) {
                    $archive = $candidate;
                    break;
                }
            }
            if ($archive === 0) {
                throw new RuntimeException('Für diesen Messwert ist keine Archivprotokollierung aktiv. Bitte die Protokollierung im Archive Control prüfen.');
            }
            $aggregationType = AC_GetAggregationType($archive, $id);
            if ($aggregationType !== 0 && !($key === 'energy' && $aggregationType === 1)) {
                throw new RuntimeException('Temperatur und Momentanleistung müssen im Archiv als Standardwert statt als Zähler protokolliert werden.');
            }
            $variable = IPS_GetVariable($id);
            $profile = $this->profile($variable, $id);
            $presentation = $this->presentation($id);
            $reply['unit'] = in_array($key, ['power', 'energy'], true) ? trim($profile['Suffix'] ?? $presentation['SUFFIX'] ?? '') : '°C';
            $reply['kind'] = $key === 'energy' ? ($aggregationType === 1 ? 'consumption' : 'level') : 'average';
            $level = ($key === 'energy' && $end - $start >= 7 * 86400) || $end - $start > 31 * 86400 ? 1 : 0;
            $reply['aggregation'] = $level === 0 ? 'Stündliche Mittelwerte' : 'Tägliche Mittelwerte';
            if ($reply['kind'] === 'consumption') {
                $reply['aggregation'] = $level === 0 ? 'Verbrauch je Stunde (Zählerdifferenzen)' : 'Verbrauch je Tag (Zählerdifferenzen)';
            } elseif ($reply['kind'] === 'level') {
                $reply['aggregation'] .= ' des Zählerstands';
            }
            $reply['step'] = $level === 0 ? 3600 : 86400;
            $reply['start'] = $start;
            $reply['end'] = $end;
            $rows = AC_GetAggregatedValues($archive, $id, $level, $start, $end, 1000);
            foreach ($rows as $row) {
                $timestamp = $row['TimeStamp'];
                $temperature = $row['Avg'];
                if ($timestamp >= $start && $timestamp <= $end &&
                    (is_int($temperature) || is_float($temperature)) && is_finite((float) $temperature)) {
                    $point = ['time' => (int) $timestamp, 'value' => $temperature];
                    if ($reply['kind'] === 'consumption') {
                        $point['duration'] = (int) ($row['Duration'] ?? $reply['step']);
                    }
                    $reply['points'][] = $point;
                }
            }
            usort($reply['points'], function ($a, $b) { return $a['time'] <=> $b['time']; });
        } catch (Throwable $e) {
            $reply['error'] = $e->getMessage();
        }
        // Independent response: do not overwrite live data or operating-mode errors.
        $this->UpdateVisualizationValue($this->encode(['history' => $reply]));
    }

    private function monthlyConsumption(array $energy): array
    {
        $start = strtotime('first day of this month 00:00:00');
        $now = time();
        $key = $this->ReadPropertyInteger('EnergyID') . ':' . $start . ':' . ($energy['updated'] ?? 0) . ':' . ($energy['value'] ?? '') . ':' . ($energy['unit'] ?? '');
        $cache = json_decode($this->GetBuffer('MonthlyEnergyCache'), true);
        if (is_array($cache) && ($cache['key'] ?? '') === $key && $now - ($cache['time'] ?? 0) < 60) {
            return $cache['data'];
        }
        $result = ['value' => null, 'unit' => $energy['unit'] ?? '', 'reason' => '',
            'start' => $start, 'updated' => $now, 'timezone' => date_default_timezone_get()];
        try {
            $id = $this->ReadPropertyInteger('EnergyID');
            if (($energy['value'] ?? null) === null) {
                throw new RuntimeException('Gesamtverbrauch nicht verfügbar.');
            }
            $archive = 0;
            foreach (IPS_GetInstanceListByModuleID('{43192F0B-135B-4CE7-A0A7-1475603F3060}') as $candidate) {
                if (AC_GetLoggingStatus($candidate, $id)) { $archive = $candidate; break; }
            }
            if (!$archive) {
                throw new RuntimeException('Monatsverbrauch benötigt eine aktive Archivprotokollierung.');
            }
            if (AC_GetAggregationType($archive, $id) !== 1) {
                throw new RuntimeException('Monatsverbrauch benötigt Zähleraggregation im Archive Control; Zählerstands-Mittelwerte sind kein Monatsverbrauch.');
            }
            foreach (AC_GetAggregatedValues($archive, $id, 3, $start, $now, 2) as $row) {
                if ((int) $row['TimeStamp'] === $start && (is_int($row['Avg']) || is_float($row['Avg'])) && is_finite((float) $row['Avg'])) {
                    $result['value'] = $row['Avg'];
                    break;
                }
            }
            if ($result['value'] === null) {
                throw new RuntimeException('Für diesen Monat noch keine archivierten Verbrauchswerte verfügbar.');
            }
        } catch (Throwable $e) { $result['reason'] = $e->getMessage(); }
        $this->SetBuffer('MonthlyEnergyCache', $this->encode(['key' => $key, 'time' => $now, 'data' => $result]));
        return $result;
    }

    private function sourceMessages(): array
    {
        return [VM_UPDATE, VM_DELETE, VM_CHANGEPROFILENAME, VM_CHANGEPROFILEACTION];
    }

    private function sourceIDs(): array
    {
        return array_map(function ($source) { return $this->ReadPropertyInteger($source[0]); }, array_values(self::SOURCES));
    }

    private function presentation(int $id): array
    {
        return function_exists('IPS_GetVariablePresentation') ? IPS_GetVariablePresentation($id) : [];
    }

    private function profile(array $variable, int $id): ?array
    {
        $presentation = $this->presentation($id);
        if ($presentation !== []) {
            if (($presentation['PRESENTATION'] ?? '') !== self::LEGACY) {
                return null; // A modern presentation takes precedence over obsolete legacy fields.
            }
            $name = $presentation['PROFILE'] ?? '';
        } else {
            $name = ($variable['VariableCustomProfile'] ?? '') !== '' ? $variable['VariableCustomProfile'] : ($variable['VariableProfile'] ?? '');
        }
        return $name !== '' && IPS_VariableProfileExists($name) ? IPS_GetVariableProfile($name) : null;
    }

    private function modeConfiguration(): array
    {
        $id = $this->ReadPropertyInteger('ModeID');
        $result = ['choices' => [], 'writable' => false, 'reason' => 'Betriebsartvariable fehlt.',
            'type' => null, 'variableID' => $id, 'source' => '', 'actionID' => 0];
        if ($id <= 0 || !IPS_VariableExists($id)) {
            return $result;
        }
        $variable = IPS_GetVariable($id);
        $result['type'] = $variable['VariableType'];
        $presentation = $this->presentation($id);
        $rawChoices = [];
        if (($presentation['PRESENTATION'] ?? '') === self::ENUMERATION) {
            $result['source'] = 'Aufzählung';
            $options = json_decode($presentation['OPTIONS'] ?? '[]', true);
            if (is_array($options)) {
                foreach ($options as $option) {
                    if (is_array($option) && array_key_exists('Value', $option) && isset($option['Caption'])) {
                        $rawChoices[] = ['value' => $option['Value'], 'label' => (string) $option['Caption']];
                    }
                }
            }
        } else {
            $profile = $this->profile($variable, $id);
            if ($profile !== null && $profile['ProfileType'] === $result['type']) {
                $result['source'] = 'Profil: ' . $profile['ProfileName'];
                foreach ($profile['Associations'] as $association) {
                    $value = $association['Value'];
                    // Legacy boolean associations use numeric 0/1, not JSON booleans.
                    if ($result['type'] === 0 && in_array($value, [0, 1, 0.0, 1.0], true)) {
                        $value = (bool) $value;
                    }
                    $rawChoices[] = ['value' => $value, 'label' => (string) $association['Name']];
                }
            }
        }
        foreach ($rawChoices as $choice) {
            $value = $choice['value'];
            if ($result['type'] === 0 && is_bool($value)) {
                $result['choices'][] = $choice;
            } elseif ($result['type'] === 3 && is_string($value)) {
                $result['choices'][] = $choice;
            } elseif (in_array($result['type'], [1, 2], true) && (is_int($value) || is_float($value)) && is_finite((float) $value)) {
                if ($result['type'] === 1 && (float) (int) $value !== (float) $value) {
                    continue;
                }
                $choice['value'] = $result['type'] === 1 ? (int) $value : (float) $value;
                $result['choices'][] = $choice;
            }
        }
        $action = ($variable['VariableCustomAction'] ?? 0) > 0 ? $variable['VariableCustomAction'] : ($variable['VariableAction'] ?? 0);
        $result['actionID'] = $action;
        if ($action < 10000 || (!IPS_InstanceExists($action) && !IPS_ScriptExists($action))) {
            $result['reason'] = 'Keine gültige Variablenaktion vorhanden oder Aktion deaktiviert.';
        } elseif (count($result['choices']) === 0) {
            $result['reason'] = 'Variable ' . $id . ': Keine Auswahlwerte im wirksamen Profil oder in der Darstellung „Aufzählung“. Bitte die Auswahl der Quellvariable in IP-Symcon prüfen.';
        } else {
            $result['writable'] = true;
            $result['reason'] = '';
        }
        return $result;
    }

    private function snapshot(): array
    {
        $data = ['values' => [], 'missing' => [], 'actionError' => $this->GetBuffer('ActionError')];
        foreach (self::SOURCES as $key => $source) {
            $id = $this->ReadPropertyInteger($source[0]);
            $entry = ['id' => $id, 'value' => null, 'formatted' => '—', 'updated' => 0, 'hasUnit' => false];
            try {
                if ($id > 0 && IPS_VariableExists($id)) {
                    $variable = IPS_GetVariable($id);
                    $value = GetValue($id);
                    if (($key === 'mode' && (is_bool($value) || is_string($value))) ||
                        (in_array($variable['VariableType'], [1, 2], true) && (is_int($value) || is_float($value)) && is_finite((float) $value))) {
                        $profile = $this->profile($variable, $id);
                        $presentation = $this->presentation($id);
                        $min = $profile !== null ? $profile['MinValue'] : ($presentation['MIN'] ?? null);
                        $max = $profile !== null ? $profile['MaxValue'] : ($presentation['MAX'] ?? null);
                        $formatted = GetValueFormatted($id);
                        if ($key === 'energy') {
                            $formatted = ($profile['Prefix'] ?? $presentation['PREFIX'] ?? '') . number_format((float) $value, 2, ',', '.') . ($profile['Suffix'] ?? $presentation['SUFFIX'] ?? '');
                        }
                        $entry = ['id' => $id, 'value' => $value, 'formatted' => $formatted,
                            'updated' => (int) $variable['VariableUpdated'],
                            'unit' => trim($profile['Suffix'] ?? $presentation['SUFFIX'] ?? ''),
                            'hasUnit' => $profile !== null ? (trim($profile['Prefix']) !== '' || trim($profile['Suffix']) !== '') :
                                (trim($presentation['PREFIX'] ?? '') !== '' || trim($presentation['SUFFIX'] ?? '') !== ''),
                            'range' => is_numeric($min) && is_numeric($max) && $max > $min ? ['min' => $min, 'max' => $max] : null];
                    }
                }
            } catch (Throwable $e) {
                // Deletion/reconfiguration between Exists and Get must not break the whole tile.
                $this->SendDebug('Quelle nicht verfügbar', $source[2] . ': ' . $e->getMessage(), 0);
            }
            $data['values'][$key] = $entry;
            if ($entry['value'] === null) {
                $data['missing'][] = $source[2];
            }
        }
        try { $data['setpoint'] = $this->setpointConfiguration(); }
        catch (Throwable $e) { $data['setpoint'] = ['writable' => false, 'reason' => 'Solltemperaturkonfiguration nicht verfügbar.', 'choices' => []]; }
        $data['setpointError'] = $this->GetBuffer('SetpointError');
        $data['monthlyEnergy'] = $this->monthlyConsumption($data['values']['energy']);
        try {
            $data['mode'] = $this->modeConfiguration();
        } catch (Throwable $e) {
            $data['mode'] = ['choices' => [], 'writable' => false, 'reason' => 'Betriebsartkonfiguration nicht verfügbar.', 'type' => null];
        }
        return $data;
    }

    private function encode(array $data): string
    {
        return json_encode($data, JSON_UNESCAPED_UNICODE | JSON_HEX_TAG | JSON_HEX_AMP | JSON_HEX_APOS | JSON_HEX_QUOT | JSON_INVALID_UTF8_SUBSTITUTE | JSON_THROW_ON_ERROR);
    }
}
