<?php

class Vitovent300FTile extends IPSModule
{
    private const SOURCES = [
        'outside' => ['OutsideID', 46477, 'Außenluft'],
        'supply' => ['SupplyID', 29484, 'Zuluft'],
        'extract' => ['ExtractID', 15325, 'Abluft'],
        'exhaust' => ['ExhaustID', 23757, 'Fortluft'],
        'flow' => ['FlowID', 36483, 'Volumenstrom'],
        'filter' => ['FilterID', 51925, 'Filterrestzeit'],
        'mode' => ['ModeID', 54849, 'Betriebsart']
    ];

    public function Create()
    {
        parent::Create();
        foreach (self::SOURCES as $source) {
            $this->RegisterPropertyInteger($source[0], $source[1]);
        }
        $this->RegisterTimer('Refresh', 0, 'VVF_Refresh($_IPS["TARGET"]);');
        $this->SetVisualizationType(1);
    }

    public function ApplyChanges()
    {
        parent::ApplyChanges();
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
        $elements = [['type' => 'Label', 'caption' => 'Vorhandene Lüftungsvariablen auswählen. Keine Verbindung zum Heizgerät nötig. Die Betriebsart verwendet nur echte Profil-Assoziationen und die vorhandene Variablenaktion.']];
        foreach (self::SOURCES as $source) {
            $elements[] = ['type' => 'SelectVariable', 'name' => $source[0], 'caption' => $source[2]];
        }
        $elements[] = ['type' => 'Label', 'caption' => 'Temperaturen müssen in °C vorliegen. Einheiten für Volumenstrom und Filterrestzeit stammen aus den Quellprofilen. Ohne Profil wird keine Einheit angenommen.'];
        return $this->encode(['elements' => $elements, 'actions' => [], 'status' => [
            ['code' => 102, 'icon' => 'active', 'caption' => 'Alle sieben Quellvariablen verfügbar'],
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
        if ($ident !== 'ChangeMode') {
            throw new InvalidArgumentException('Unbekannte Aktion.');
        }
        if (IPS_GetKernelRunlevel() !== KR_READY) {
            throw new RuntimeException('IP-Symcon ist noch nicht bereit.');
        }
        // Re-read the live profile and action for EVERY request, including requests outside the tile.
        $mode = $this->modeConfiguration();
        if (!$mode['writable']) {
            throw new RuntimeException($mode['reason']);
        }
        if ((!is_int($value) && !is_float($value)) || !is_finite((float) $value)) {
            throw new InvalidArgumentException('Betriebsart muss eine endliche Zahl sein.');
        }
        $candidate = $value;
        if ($mode['type'] === 1 && is_float($value)) {
            // Only exactly representable integral JSON numbers may become integer actions.
            $candidate = floor($value) === $value && abs($value) <= 9007199254740991 ? (int) $value : null;
        } elseif ($mode['type'] === 2) {
            $candidate = (float) $value;
        }
        foreach ($mode['choices'] as $choice) {
            if ($candidate === $choice['value']) {
                $typedValue = $mode['type'] === 1 ? (int) $choice['value'] : (float) $choice['value'];
                // Never SetValue: delegate to the source variable's own effective action.
                RequestAction($this->ReadPropertyInteger('ModeID'), $typedValue);
                $this->Refresh();
                return;
            }
        }
        throw new InvalidArgumentException('Dieser Wert ist im aktuellen Betriebsartprofil nicht freigegeben.');
    }

    private function sourceMessages(): array
    {
        return [VM_UPDATE, VM_DELETE, VM_CHANGEPROFILENAME, VM_CHANGEPROFILEACTION];
    }

    private function sourceIDs(): array
    {
        return array_map(function ($source) { return $this->ReadPropertyInteger($source[0]); }, array_values(self::SOURCES));
    }

    private function profile(array $variable): ?array
    {
        $name = $variable['VariableCustomProfile'] !== '' ? $variable['VariableCustomProfile'] : $variable['VariableProfile'];
        return $name !== '' && IPS_VariableProfileExists($name) ? IPS_GetVariableProfile($name) : null;
    }

    private function modeConfiguration(): array
    {
        $result = ['choices' => [], 'writable' => false, 'reason' => 'Betriebsartvariable fehlt.', 'type' => null];
        $id = $this->ReadPropertyInteger('ModeID');
        if ($id <= 0 || !IPS_VariableExists($id)) {
            return $result;
        }
        $variable = IPS_GetVariable($id);
        $result['type'] = $variable['VariableType'];
        if (!in_array($result['type'], [1, 2], true)) {
            $result['reason'] = 'Betriebsart benötigt eine Integer- oder Floatvariable mit Profil-Assoziationen.';
            return $result;
        }
        $profile = $this->profile($variable);
        if ($profile !== null && $profile['ProfileType'] === $result['type']) {
            foreach ($profile['Associations'] as $association) {
                $value = $association['Value'];
                if ((!is_int($value) && !is_float($value)) || !is_finite((float) $value) ||
                    ($result['type'] === 1 && (float) (int) $value !== (float) $value)) {
                    continue;
                }
                $result['choices'][] = [
                    'value' => $result['type'] === 1 ? (int) $value : (float) $value,
                    'label' => (string) $association['Name']
                ];
            }
        }
        // CustomAction=1 explicitly disables an action and MUST NOT fall back to VariableAction.
        $action = $variable['VariableCustomAction'] > 0 ? $variable['VariableCustomAction'] : $variable['VariableAction'];
        if ($action < 10000 || (!IPS_InstanceExists($action) && !IPS_ScriptExists($action))) {
            $result['reason'] = 'Keine gültige Variablenaktion vorhanden oder Aktion deaktiviert.';
        } elseif (count($result['choices']) === 0) {
            $result['reason'] = 'Keine passenden Profil-Assoziationen vorhanden.';
        } else {
            $result['writable'] = true;
            $result['reason'] = '';
        }
        return $result;
    }

    private function snapshot(): array
    {
        $data = ['values' => [], 'missing' => []];
        foreach (self::SOURCES as $key => $source) {
            $id = $this->ReadPropertyInteger($source[0]);
            $entry = ['value' => null, 'formatted' => '—', 'updated' => 0, 'hasUnit' => false];
            try {
                if ($id > 0 && IPS_VariableExists($id)) {
                    $variable = IPS_GetVariable($id);
                    $value = GetValue($id);
                    if (in_array($variable['VariableType'], [1, 2], true) && (is_int($value) || is_float($value)) && is_finite((float) $value)) {
                        $profile = $this->profile($variable);
                        $entry = ['value' => $value, 'formatted' => GetValueFormatted($id),
                            'updated' => (int) $variable['VariableUpdated'],
                            'hasUnit' => $profile !== null && (trim($profile['Prefix']) !== '' || trim($profile['Suffix']) !== '')];
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
