<?php
// Run in IP-Symcon's script editor. READ ONLY: no RequestAction and no SetValue.
$ids = ['Außenluft' => 46477, 'Zuluft' => 29484, 'Abluft' => 15325, 'Fortluft' => 23757,
    'Volumenstrom' => 36483, 'Filterrestzeit' => 51925, 'Betriebsart' => 54849, 'Momentanleistung' => 48602, 'Gesamtverbrauch' => 50335];
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
