<?php
// Run in IP-Symcon's script editor. READ ONLY: no RequestAction and no SetValue.
$ids = ['Außenluft' => 31044, 'Zuluft' => 42234, 'Abluft' => 58476, 'Fortluft' => 22909,
    'Volumenstrom' => 34190, 'Filterrestzeit' => 38673, 'Betriebsart' => 28301, 'Momentanleistung' => 48602, 'Gesamtverbrauch' => 50335, 'Lüftung Raumtemperatur Soll' => 41384];
$report = [];
foreach ($ids as $name => $id) {
    if (!IPS_VariableExists($id)) {
        $report[$name] = ['id' => $id, 'exists' => false];
        continue;
    }
    $variable = IPS_GetVariable($id);
    $profile = ($variable['VariableCustomProfile'] ?? '') !== '' ? $variable['VariableCustomProfile'] : ($variable['VariableProfile'] ?? '');
    $action = $variable['VariableCustomAction'] > 0 ? $variable['VariableCustomAction'] : $variable['VariableAction'];
    $report[$name] = ['id' => $id, 'exists' => true, 'type' => $variable['VariableType'],
        'value' => GetValue($id), 'formatted' => GetValueFormatted($id), 'profileName' => $profile,
        'presentation' => function_exists('IPS_GetVariablePresentation') ? IPS_GetVariablePresentation($id) : [],
        'profile' => $profile !== '' && IPS_VariableProfileExists($profile) ? IPS_GetVariableProfile($profile) : null,
        'defaultAction' => $variable['VariableAction'], 'customAction' => $variable['VariableCustomAction'],
        'effectiveAction' => $action, 'actionExists' => $action >= 10000 && (IPS_InstanceExists($action) || IPS_ScriptExists($action))];
}
echo json_encode($report, JSON_PRETTY_PRINT | JSON_UNESCAPED_UNICODE | JSON_INVALID_UTF8_SUBSTITUTE | JSON_THROW_ON_ERROR);
