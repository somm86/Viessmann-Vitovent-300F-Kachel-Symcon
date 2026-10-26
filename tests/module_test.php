<?php
// Real official Symcon stubs; synthetic values here are TEST fixtures, never production modes.
$stubs = getenv('SYMCON_STUBS') ?: '/tmp/SymconStubs';
require $stubs . '/autoload.php';
set_error_handler(function ($severity, $message, $file, $line) {
    if ($severity === E_DEPRECATED) { return true; } // Third-party stubs use deprecated Reflection APIs on PHP 8.4.
    throw new ErrorException($message, 0, $severity, $file, $line);
});
IPS\Kernel::reset();
IPS\ModuleLoader::loadSingleModule(dirname(__DIR__) . '/Vitovent300FTile', '{B24E29F4-981D-4B92-842B-785D28962BD5}');
$id = IPS_CreateInstance('{4305595E-0E5F-4124-AD59-8E11257A1E97}');
$module = IPS\InstanceManager::getInstanceInterface($id);
$checks = 0;
function check($condition, $message) {
    global $checks;
    if (!$condition) { throw new Exception('FAIL: ' . $message); }
    $checks++;
}
function rejects($call, $message) {
    try { $call(); } catch (RuntimeException | InvalidArgumentException $e) { check(true, $message); return; }
    throw new Exception('FAIL: accepted ' . $message);
}
function snapshot($module) {
    preg_match('/const INITIAL = (.*);/', $module->GetVisualizationTile(), $m);
    return json_decode($m[1], true, 512, JSON_THROW_ON_ERROR);
}
function privateValue($module, $name) {
    $r = new ReflectionProperty(IPSModule::class, $name);
    return $r->getValue($module);
}
check(IPS_GetInstance($id)['InstanceVisualizationType'] === 1, 'HTML visualization type');
check(IPS_GetProperty($id, 'ModeID') === 28301, 'original defaults');
check(IPS_GetProperty($id, 'PowerID') === 48602 && IPS_GetProperty($id, 'EnergyID') === 50335, 'electricity IDs from user screenshot');
check(count(snapshot($module)['missing']) === 10, 'empty installation is honest');
check(IPS_GetInstance($id)['InstanceStatus'] === 104, 'incomplete status');
$form = json_decode(IPS_GetConfigurationForm($id), true, 512, JSON_THROW_ON_ERROR);
check(count(array_filter($form['elements'], function ($e) { return $e['type'] === 'SelectVariable'; })) === 10, 'ten selectors');
$ids = [];
foreach (['OutsideID','SupplyID','ExtractID','ExhaustID','FlowID','FilterID','ModeID','PowerID','EnergyID','SetpointID'] as $property) {
    $v = IPS_CreateVariable($property === 'ModeID' ? 1 : 2);
    $ids[$property] = $v;
    IPS_SetProperty($id, $property, $v);
    SetValue($v, $property === 'ModeID' ? 7 : 18.5);
}
$mode = $ids['ModeID'];
IPS_CreateVariableProfile('Fixture.Mode', 1);
IPS_SetVariableProfileAssociation('Fixture.Mode', 7, 'Test A', '', -1);
IPS_SetVariableProfileAssociation('Fixture.Mode', 23, 'Test B', '', -1);
IPS_SetVariableCustomProfile($mode, 'Fixture.Mode');
IPS_CreateVariableProfile('Fixture.Filter', 2);
IPS_SetVariableProfileText('Fixture.Filter', '', ' h');
IPS_SetVariableCustomProfile($ids['FilterID'], 'Fixture.Filter');
IPS_ApplyChanges($id);
check(count(snapshot($module)['missing']) === 0, 'all sources read');
check(IPS_GetInstance($id)['InstanceStatus'] === 102, 'active status');
check(count(IPS_GetReferenceList($id)) === 10, 'source references registered');
$messages = privateValue($module, 'messageRegistrations');
check(in_array(IPS_KERNELSTARTED, $messages[0], true), 'restart subscribed');
check(in_array(VM_UPDATE, $messages[$mode], true), 'updates subscribed');
check(in_array(VM_DELETE, $messages[$mode], true), 'deletion subscribed');
check(!snapshot($module)['mode']['writable'], 'profile without action read only');
check(strpos(snapshot($module)['values']['filter']['formatted'], ' h') !== false, 'filter source units, no guessed days');
// Verify a standard profile and an instance action through the official dispatch route.
IPS\ModuleLoader::loadSingleModule(__DIR__ . '/fixtures/ActionTarget', '{B24E29F4-981D-4B92-842B-785D28962BD5}');
$target = IPS_CreateInstance('{88B1BB10-E81D-4E5E-9369-4A6F3AAB614F}');
IPS_SetParent($mode, $target);
IPS_SetIdent($mode, 'OperatingMode');
IPS\VariableManager::setVariableAction($mode, $target);
IPS_CreateVariableProfile('Fixture.DefaultMode', 1);
IPS_SetVariableProfileAssociation('Fixture.DefaultMode', 73, 'Test default profile', '', -1);
IPS\VariableManager::setVariableProfile($mode, 'Fixture.DefaultMode');
check(snapshot($module)['mode']['choices'][0]['value'] === 7, 'custom profile overrides default');
$module->RequestAction('ChangeMode', 23);
check($GLOBALS['instanceRequest'] === ['ident' => 'OperatingMode', 'value' => 23], 'instance action receives ident and typed value');
check(GetValue($mode) === 7, 'module does not fake source acknowledgement');
IPS_SetVariableCustomProfile($mode, '');
check(snapshot($module)['mode']['choices'][0]['value'] === 73, 'default profile used when custom empty');
rejects(function () use ($module) { $module->RequestAction('ChangeMode', 23); }, 'old custom choice not in effective default profile');
IPS_SetVariableCustomProfile($mode, 'Fixture.Mode');
$action = IPS_CreateScript(0);
IPS_SetScriptContent($action, '<?php $GLOBALS["captured"] = $_IPS; SetValue($_IPS["VARIABLE"], $_IPS["VALUE"]);');
IPS_SetVariableCustomAction($mode, $action);
$module->Refresh();
check(snapshot($module)['mode']['writable'], 'profile and real action writable');
$module->RequestAction('ChangeMode', 23);
check($GLOBALS['captured']['VARIABLE'] === $mode, 'correct target');
check($GLOBALS['captured']['VALUE'] === 23, 'integer type preserved');
check(GetValue($mode) === 23, 'action script executed');
foreach (['garbage', '7', null, true, [], INF, NAN, 23.000001, 999] as $invalid) {
    rejects(function () use ($module, $invalid) { $module->RequestAction('ChangeMode', $invalid); }, 'invalid request');
}
rejects(function () use ($module) { $module->RequestAction('Other', 7); }, 'unknown ident');
IPS\VariableManager::setVariableAction($mode, $id);
IPS_SetVariableCustomAction($mode, 1);
check(!snapshot($module)['mode']['writable'], 'custom action 1 overrides default');
rejects(function () use ($module) { $module->RequestAction('ChangeMode', 7); }, 'disabled custom action');
IPS_SetVariableCustomAction($mode, $action);
IPS_SetVariableProfileAssociation('Fixture.Mode', 23, '', '', -1); // API deletes association with empty label.
rejects(function () use ($module) { $module->RequestAction('ChangeMode', 23); }, 'stale removed association');
$module->Refresh();
$updates = count(privateValue($module, 'messages'));
$module->Refresh();
check(count(privateValue($module, 'messages')) === $updates, 'unchanged polling suppressed');
SetValue($ids['OutsideID'], -4.2);
$module->MessageSink(time(), $ids['OutsideID'], VM_UPDATE, [-4.2, true]);
check(count(privateValue($module, 'messages')) > $updates, 'value event publishes');
$published = privateValue($module, 'messages');
$last = end($published);
check(is_string($last['Data'][0]), 'SDK payload is JSON string');
check(json_decode($last['Data'][0], true)['values']['outside']['value'] === -4.2, 'live payload has new value');
$old = $ids['OutsideID'];
$new = IPS_CreateVariable(2);
SetValue($new, 3.0);
IPS_SetProperty($id, 'OutsideID', $new);
IPS_ApplyChanges($id);
check(!in_array($old, IPS_GetReferenceList($id), true), 'old reference removed');
check(!isset(privateValue($module, 'messageRegistrations')[$old]), 'old events removed');
IPS_ApplyChanges($id);
check(count(IPS_GetReferenceList($id)) === 10, 'apply repeatable');
IPS_DeleteVariable($new);
$module->MessageSink(time(), $new, VM_DELETE, []);
check(in_array('Außenluft', snapshot($module)['missing'], true), 'deleted variable unavailable');
check(IPS_GetInstance($id)['InstanceStatus'] === 104, 'deletion status');
$module->MessageSink(time(), 0, IPS_KERNELSTARTED, []);
check(in_array('Außenluft', snapshot($module)['missing'], true), 'restart handles missing variable');
// Float profiles keep the exact profile value, with no epsilon matching.
$floatMode = IPS_CreateVariable(2);
IPS_CreateVariableProfile('Fixture.FloatMode', 2);
IPS_SetVariableProfileAssociation('Fixture.FloatMode', 2.5, 'Test float', '', -1);
IPS_SetVariableCustomProfile($floatMode, 'Fixture.FloatMode');
IPS_SetVariableCustomAction($floatMode, $action);
IPS_SetProperty($id, 'ModeID', $floatMode);
IPS_ApplyChanges($id);
$module->RequestAction('ChangeMode', 2.5);
check($GLOBALS['captured']['VALUE'] === 2.5, 'float type preserved');
rejects(function () use ($module) { $module->RequestAction('ChangeMode', 2.500001); }, 'nearby float value');
// Script terminators in a real profile label must not escape the initial JSON script.
IPS_SetVariableProfileAssociation('Fixture.FloatMode', 2.5, '</script><img src=x onerror=alert(1)>', '', -1);
$html = $module->GetVisualizationTile();
check(strpos($html, '</script><img') === false, 'script injection escaped');
// Regression for modern Symcon enumeration presentations without legacy associations.
$enumMode = IPS_CreateVariable(1);
IPS_SetVariableCustomAction($enumMode, $action);
IPS_SetVariableCustomPresentation($enumMode, ['PRESENTATION' => VARIABLE_PRESENTATION_ENUMERATION,
    'OPTIONS' => json_encode([['Value' => 17, 'Caption' => 'Fixture enumeration A'], ['Value' => 41, 'Caption' => 'Fixture enumeration B']])]);
IPS_SetProperty($id, 'ModeID', $enumMode);
IPS_ApplyChanges($id);
check(snapshot($module)['mode']['source'] === 'Aufzählung', 'modern enumeration detected');
check(snapshot($module)['mode']['writable'], 'no legacy associations required for enumeration');
check(array_column(snapshot($module)['mode']['choices'], 'value') === [17, 41], 'only actual enumeration values used');
$module->RequestAction('ChangeMode', 41);
check($GLOBALS['captured']['VALUE'] === 41, 'enumeration uses exact integer value');
rejects(function () use ($module) { $module->RequestAction('ChangeMode', 1); }, 'invented enumeration value');
check(snapshot($module)['actionError'] !== '', 'PHP action errors reach SDK snapshot');
$module->RequestAction('ChangeMode', 17);
check(snapshot($module)['actionError'] === '', 'successful new request clears server error');
IPS_SetVariableCustomPresentation($enumMode, ['PRESENTATION' => VARIABLE_PRESENTATION_ENUMERATION,
    'OPTIONS' => json_encode([['Value' => 41, 'Caption' => 'Fixture enumeration B']])]);
rejects(function () use ($module) { $module->RequestAction('ChangeMode', 17); }, 'removed enumeration value');
check(count(snapshot($module)['mode']['choices']) === 1, 'presentation changes reflected');
IPS_SetVariableCustomPresentation($enumMode, ['PRESENTATION' => VARIABLE_PRESENTATION_ENUMERATION, 'OPTIONS' => '[]']);
check(!snapshot($module)['mode']['writable'], 'empty enumeration is read only');
check(strpos(snapshot($module)['mode']['reason'], (string) $enumMode) !== false, 'diagnostic identifies missing source options');
$diagnostic = json_decode($module->GetDiagnostics(), true, 512, JSON_THROW_ON_ERROR);
check($diagnostic['actionID'] === $action && $diagnostic['variableID'] === $enumMode, 'diagnostic has actual action and variable');
// Verify a modern presentation overrides stale legacy associations instead of merging them.
IPS_SetVariableCustomProfile($enumMode, 'Fixture.Mode');
IPS_SetVariableCustomPresentation($enumMode, ['PRESENTATION' => VARIABLE_PRESENTATION_ENUMERATION,
    'OPTIONS' => json_encode([['Value' => 41, 'Caption' => 'Fixture enumeration B']])]);
check(array_column(snapshot($module)['mode']['choices'], 'value') === [41], 'effective presentation overrides stale legacy profile');
rejects(function () use ($module) { $module->RequestAction('ChangeMode', 7); }, 'obsolete legacy option under enumeration');
$boolMode = IPS_CreateVariable(0);
IPS_SetVariableCustomAction($boolMode, $action);
IPS_SetVariableCustomPresentation($boolMode, ['PRESENTATION' => VARIABLE_PRESENTATION_ENUMERATION,
    'OPTIONS' => json_encode([['Value' => false, 'Caption' => 'Fixture false'], ['Value' => true, 'Caption' => 'Fixture true']])]);
IPS_SetProperty($id, 'ModeID', $boolMode);
IPS_ApplyChanges($id);
$module->RequestAction('ChangeMode', true);
check($GLOBALS['captured']['VALUE'] === true, 'boolean enumeration preserves type');
check(snapshot($module)['values']['mode']['value'] === true, 'boolean mode displayed');
rejects(function () use ($module) { $module->RequestAction('ChangeMode', 1); }, 'numeric value for boolean enumeration');
$stringMode = IPS_CreateVariable(3);
IPS_SetParent($stringMode, $target);
IPS_SetIdent($stringMode, 'StringMode');
IPS\VariableManager::setVariableAction($stringMode, $target);
IPS_SetVariableCustomPresentation($stringMode, ['PRESENTATION' => VARIABLE_PRESENTATION_ENUMERATION,
    'OPTIONS' => json_encode([['Value' => '', 'Caption' => 'Fixture empty string'], ['Value' => 'fixture-code', 'Caption' => 'Fixture string']])]);
IPS_SetProperty($id, 'ModeID', $stringMode);
IPS_ApplyChanges($id);
$module->RequestAction('ChangeMode', 'fixture-code');
check($GLOBALS['instanceRequest']['value'] === 'fixture-code', 'string enumeration preserves type');
check(snapshot($module)['values']['mode']['formatted'] === 'Fixture empty string', 'string current label displayed without optimistic change');
rejects(function () use ($module) { $module->RequestAction('ChangeMode', 'other'); }, 'unknown string value');
IPS_SetProperty($id, 'ModeID', $floatMode);
IPS_ApplyChanges($id);
// Produce browser input with synthetic fixture values and actual module-generated HTML.
IPS_SetVariableProfileAssociation('Fixture.FloatMode', 2.5, 'Testmodus A', '', -1);
IPS_SetVariableProfileAssociation('Fixture.FloatMode', 9.5, 'Testmodus B', '', -1);
IPS_SetProperty($id, 'OutsideID', $old);
IPS_ApplyChanges($id);
// History uses actual ArchiveControl APIs, only for the four configured temperatures.
function historyReply($module, $request) {
    $module->RequestAction('LoadHistory', is_array($request) ? json_encode($request) : $request);
    $messages = privateValue($module, 'messages');
    $last = end($messages);
    return json_decode($last['Data'][0], true, 512, JSON_THROW_ON_ERROR)['history'];
}
$base = intdiv(time() - 100000, 3600) * 3600;
$query = ['requestId' => 'fixture_history_1', 'key' => 'outside', 'start' => $base, 'end' => $base + 7200];
$reply = historyReply($module, $query);
check(strpos($reply['error'], 'Archivprotokollierung') !== false, 'missing archive reported honestly');
IPS\ModuleLoader::loadSingleModule($stubs . '/CoreStubs/ArchiveControl', '{B24E29F4-981D-4B92-842B-785D28962BD5}');
$archive = IPS_CreateInstance('{43192F0B-135B-4CE7-A0A7-1475603F3060}');
check(historyReply($module, $query)['error'] !== '', 'logging disabled reported');
AC_SetLoggingStatus($archive, $old, true); // Test-only fixture setup; production module never changes logging.
AC_StubsAddAggregatedValues($archive, $old, 0, [
    ['TimeStamp' => $base, 'Duration' => 3600, 'Avg' => 15.5],
    ['TimeStamp' => $base + 3600, 'Duration' => 3600, 'Avg' => 18.2]
]);
$reply = historyReply($module, $query);
check($reply['error'] === '', 'hourly archive query succeeds');
check($reply['points'] === [['time' => $base, 'value' => 15.5], ['time' => $base + 3600, 'value' => 18.2]], 'actual archive values sorted chronologically');
check($reply['requestId'] === 'fixture_history_1' && $reply['variableID'] === $old, 'response matches request and configured source');
check($reply['step'] === 3600, 'hourly aggregation declared');
check(snapshot($module)['values']['outside']['id'] === $old, 'live snapshot identifies history source');
$forged = $query;
$forged['variableID'] = $mode;
check(historyReply($module, $forged)['variableID'] === $old, 'client cannot select an arbitrary variable ID');
$forged['key'] = 'mode';
check(historyReply($module, $forged)['error'] !== '', 'mode variable cannot be read as temperature history');
foreach ([['key' => 'flow'], ['start' => -1], ['end' => $base], ['end' => time() + 10000],
    ['start' => $base - 367 * 86400], ['start' => (string) $base], ['requestId' => '<script>']] as $bad) {
    check(historyReply($module, array_merge($query, $bad))['error'] !== '', 'invalid history request rejected');
}
check(historyReply($module, 'bad json')['error'] !== '', 'malformed history JSON rejected');
check(historyReply($module, str_repeat('x', 513))['error'] !== '', 'oversized history payload rejected');
AC_SetAggregationType($archive, $old, 1);
check(strpos(historyReply($module, $query)['error'], 'Zähler') !== false, 'counter aggregation not treated as temperature');
AC_SetAggregationType($archive, $old, 0);
$day = intdiv($base - 40 * 86400, 86400) * 86400;
AC_StubsAddAggregatedValues($archive, $old, 1, [['TimeStamp' => $day, 'Duration' => 86400, 'Avg' => 12.7]]);
$long = array_merge($query, ['start' => $base - 60 * 86400]);
$reply = historyReply($module, $long);
check($reply['step'] === 86400 && $reply['points'][0]['value'] === 12.7, 'long ranges use actual daily averages');
$empty = array_merge($query, ['start' => $base - 7200, 'end' => $base - 3600]);
$reply = historyReply($module, $empty);
check($reply['error'] === '' && $reply['points'] === [], 'empty history is not fabricated');
check(AC_GetLoggingStatus($archive, $old), 'history requests preserve logging configuration');
// Electric power is a gauge; total energy may be archived as a real counter.
check(IPS_GetProperty($id, 'PowerID') === $ids['PowerID'], 'power source configured');
check(IPS_GetProperty($id, 'EnergyID') === $ids['EnergyID'], 'energy source configured');
IPS_CreateVariableProfile('Fixture.Power', 2);
IPS_SetVariableProfileText('Fixture.Power', '', ' W');
IPS_SetVariableCustomProfile($ids['PowerID'], 'Fixture.Power');
IPS_CreateVariableProfile('Fixture.Energy', 2);
IPS_SetVariableProfileText('Fixture.Energy', '', ' kWh');
IPS_SetVariableCustomProfile($ids['EnergyID'], 'Fixture.Energy');
SetValue($ids['PowerID'], 37.0);
SetValue($ids['EnergyID'], 952.6);
$module->Refresh();
check((float) snapshot($module)['values']['power']['value'] === 37.0, 'power live updates');
check(snapshot($module)['values']['energy']['value'] === 952.6, 'total energy live updates');
check(snapshot($module)['values']['energy']['formatted'] === '952,60 kWh', 'total consumption uses exactly two decimals');
check(snapshot($module)['values']['power']['unit'] === 'W', 'power unit from source profile');
check(snapshot($module)['values']['energy']['unit'] === 'kWh', 'energy unit from source profile');
AC_SetLoggingStatus($archive, $ids['PowerID'], true);
AC_StubsAddAggregatedValues($archive, $ids['PowerID'], 0, [['TimeStamp' => $base, 'Duration' => 3600, 'Avg' => 37.0]]);
$powerQuery = array_merge($query, ['key' => 'power']);
$reply = historyReply($module, $powerQuery);
check($reply['error'] === '' && (float) $reply['points'][0]['value'] === 37.0, 'power history reads actual mean power');
check($reply['unit'] === 'W' && $reply['kind'] === 'average', 'power history has correct unit and meaning');
AC_SetAggregationType($archive, $ids['PowerID'], 1);
check(historyReply($module, $powerQuery)['error'] !== '', 'counter power is rejected');
AC_SetAggregationType($archive, $ids['PowerID'], 0);
AC_SetLoggingStatus($archive, $ids['EnergyID'], true);
AC_SetAggregationType($archive, $ids['EnergyID'], 1);
AC_StubsAddAggregatedValues($archive, $ids['EnergyID'], 0, [
    ['TimeStamp' => $base, 'Duration' => 3600, 'Avg' => .037],
    ['TimeStamp' => $base + 3600, 'Duration' => 3600, 'Avg' => .041]
]);
$energyQuery = array_merge($query, ['key' => 'energy']);
$reply = historyReply($module, $energyQuery);
check($reply['error'] === '' && $reply['kind'] === 'consumption', 'counter energy supports consumption history');
check(array_column($reply['points'], 'value') === [.037, .041], 'counter deltas preserved without summing or averaging them');
check($reply['points'][0]['duration'] === 3600, 'counter interval duration comes from archive');
check($reply['unit'] === 'kWh' && strpos($reply['aggregation'], 'Zählerdifferenzen') !== false, 'counter history labeled as interval consumption');
AC_StubsAddAggregatedValues($archive, $ids['EnergyID'], 1, [['TimeStamp' => $day, 'Duration' => 86400, 'Avg' => .888]]);
$reply = historyReply($module, array_merge($long, ['key' => 'energy']));
check($reply['step'] === 86400 && $reply['points'][0]['value'] === .888, 'daily counter consumption uses daily archived delta');
$standardEnergy = IPS_CreateVariable(2);
IPS_SetVariableCustomProfile($standardEnergy, 'Fixture.Energy');
AC_SetLoggingStatus($archive, $standardEnergy, true);
AC_StubsAddAggregatedValues($archive, $standardEnergy, 0, [['TimeStamp' => $base, 'Duration' => 3600, 'Avg' => 951.8]]);
IPS_SetProperty($id, 'EnergyID', $standardEnergy);
IPS_ApplyChanges($id);
$reply = historyReply($module, $energyQuery);
check($reply['kind'] === 'level' && $reply['points'][0]['value'] === 951.8, 'standard energy history remains a total-meter level');
check(strpos($reply['aggregation'], 'Zählerstands') !== false, 'total level is not mislabeled as interval consumption');
check(snapshot($module)['monthlyEnergy']['value'] === null && strpos(snapshot($module)['monthlyEnergy']['reason'], 'Zähleraggregation') !== false, 'standard meter averages never become fabricated daily consumption');
IPS_SetProperty($id, 'EnergyID', $ids['EnergyID']);
IPS_ApplyChanges($id);
$sevenDays = array_merge($energyQuery, ['start' => $energyQuery['end'] - 7 * 86400]);
$reply = historyReply($module, $sevenDays);
check($reply['step'] === 86400 && strpos($reply['aggregation'], 'je Tag') !== false, 'energy week view uses daily consumption');
$thirtyDays = array_merge($energyQuery, ['start' => $energyQuery['end'] - 30 * 86400]);
check(historyReply($module, $thirtyDays)['step'] === 86400, 'energy month view also uses daily values');
$yearDays = array_merge($energyQuery, ['start' => $energyQuery['end'] - 365 * 86400]);
check(historyReply($module, $yearDays)['step'] === 86400, 'energy year view retains daily values instead of monthly values');
$temperatureWeek = array_merge($query, ['start' => $query['end'] - 7 * 86400]);
check(historyReply($module, $temperatureWeek)['step'] === 3600, 'temperature week keeps hourly resolution');
check(historyReply($module, $energyQuery)['step'] === 3600, 'short energy range keeps hourly consumption');
$today = strtotime('first day of this month 00:00:00');
AC_StubsAddAggregatedValues($archive, $ids['EnergyID'], 3, [['TimeStamp' => $today, 'Duration' => time() - $today + 1, 'Avg' => .281]]);
IPS_ApplyChanges($id);
$daily = snapshot($module)['monthlyEnergy'];
check($daily['value'] === .281 && $daily['unit'] === 'kWh', 'month consumption reads current monthly counter delta');
check($daily['start'] === $today && $daily['timezone'] === date_default_timezone_get(), 'month boundary follows Symcon server calendar');
check($daily['reason'] === '', 'real current-day data has no missing-data warning');
$unchanged = snapshot($module)['monthlyEnergy'];
check($daily === $unchanged, 'monthly archive query is cached across unrelated updates');
AC_StubsAddAggregatedValues($archive, $ids['EnergyID'], 3, [['TimeStamp' => $today, 'Duration' => time() - $today + 1, 'Avg' => 0.0]]);
SetValue($ids['EnergyID'], 953.0);
$daily = snapshot($module)['monthlyEnergy'];
check((float) $daily['value'] === 0.0 && $daily['reason'] === '', 'real zero consumption is shown and source changes invalidate cache');
// Migrate only the seven old default IDs; preserve custom settings and all other state.
$oldConfig = ['OutsideID' => 46477, 'SupplyID' => 29484, 'ExtractID' => 15325, 'ExhaustID' => 23757,
    'FlowID' => 36483, 'FilterID' => 51925, 'ModeID' => 54849, 'PowerID' => 48602, 'EnergyID' => 50335];
$newIDs = ['OutsideID' => 31044, 'SupplyID' => 42234, 'ExtractID' => 58476, 'ExhaustID' => 22909,
    'FlowID' => 34190, 'FilterID' => 38673, 'ModeID' => 28301];
$migration = json_decode($module->Migrate(json_encode(['configuration' => $oldConfig, 'attributes' => (object) ['KeepMe' => 'fixture']])), true);
foreach ($newIDs as $property => $newID) check($migration['configuration'][$property] === $newID, 'old default migrates to screenshot ID');
check($migration['attributes']['KeepMe'] === 'fixture', 'migration preserves unrelated attributes');
check($migration['configuration']['PowerID'] === 48602 && $migration['configuration']['EnergyID'] === 50335, 'migration preserves electricity sources');
check($module->Migrate(json_encode($migration)) === '', 'migration runs only once');
$custom = $oldConfig;
$custom['OutsideID'] = 49999;
$preserved = json_decode($module->Migrate(json_encode(['configuration' => $custom, 'attributes' => (object) []])), true);
check($preserved['configuration']['OutsideID'] === 49999, 'custom source is never replaced');
$migratedID = IPS_CreateInstance('{4305595E-0E5F-4124-AD59-8E11257A1E97}');
$migrated = IPS\InstanceManager::getInstanceInterface($migratedID);
$migrated->ApplyPersistence($migration);
IPS_ApplyChanges($migratedID);
check(IPS_GetProperty($migratedID, 'ModeID') === 28301 && IPS_GetProperty($migratedID, 'SetpointID') === 41384, 'persisted migration and new setpoint property initialize together');
// Numeric setpoint dispatch always uses its own source action and current constraints.
$setpoint = $ids['SetpointID'];
check(!json_decode($module->GetSetpointDiagnostics(), true)['writable'], 'setpoint without action read only');
rejects(function () use ($module) { $module->RequestAction('SetRoomTemperature', 24); }, 'missing setpoint action');
IPS_CreateVariableProfile('Fixture.Setpoint', 2);
IPS_SetVariableProfileText('Fixture.Setpoint', '', ' °C');
IPS_SetVariableProfileValues('Fixture.Setpoint', 15, 30, .5);
IPS_SetVariableCustomProfile($setpoint, 'Fixture.Setpoint');
IPS_SetVariableCustomAction($setpoint, $action);
SetValue($setpoint, 24.0);
IPS_ApplyChanges($id);
$config = snapshot($module)['setpoint'];
check($config['writable'] && $config['min'] === 15 && $config['max'] === 30 && $config['step'] === 1 && $config['sourceStep'] === .5, 'one-degree UI step preserves native profile step');
$module->RequestAction('SetRoomTemperature', 23);
check($GLOBALS['captured']['VARIABLE'] === $setpoint && $GLOBALS['captured']['VALUE'] === 23.0, 'setpoint dispatched to correct source as float');
check(snapshot($module)['values']['setpoint']['value'] === 23, 'setpoint source confirmation displayed');
foreach (['24', true, null, [], INF, NAN, 14, 31, 15.5, 28.5, 23.3, 23.5] as $invalid) {
    rejects(function () use ($module, $invalid) { $module->RequestAction('SetRoomTemperature', $invalid); }, 'invalid or out-of-profile setpoint');
}
check(snapshot($module)['setpointError'] !== '', 'setpoint errors published independently');
IPS_SetVariableProfileValues('Fixture.Setpoint', 18, 26, 2);
rejects(function () use ($module) { $module->RequestAction('SetRoomTemperature', 23); }, 'stale setpoint step revalidated');
$module->RequestAction('SetRoomTemperature', 24);
check($GLOBALS['captured']['VALUE'] === 24.0 && snapshot($module)['setpointError'] === '', 'successful setpoint clears error and retains float type');
IPS\VariableManager::setVariableAction($setpoint, $target);
IPS_SetVariableCustomAction($setpoint, 1);
rejects(function () use ($module) { $module->RequestAction('SetRoomTemperature', 24); }, 'disabled custom setpoint action does not fall back');
IPS_SetVariableCustomAction($setpoint, $action);
IPS_SetVariableCustomPresentation($setpoint, ['PRESENTATION' => VARIABLE_PRESENTATION_ENUMERATION,
    'OPTIONS' => json_encode([['Value' => 22.5, 'Caption' => 'Fixture 22.5'], ['Value' => 24, 'Caption' => 'Fixture 24']])]);
$config = json_decode($module->GetSetpointDiagnostics(), true);
check(array_column($config['choices'], 'value') === [24], 'real enumeration setpoint values exposed');
$module->RequestAction('SetRoomTemperature', 24);
rejects(function () use ($module) { $module->RequestAction('SetRoomTemperature', 23); }, 'unknown setpoint association');
IPS_SetVariableCustomProfile($setpoint, 'Fixture.Setpoint');
// Absence of configured limits must not invent a min/max or step.
IPS_SetVariableProfileValues('Fixture.Setpoint', 0, 0, 0);
$config = json_decode($module->GetSetpointDiagnostics(), true);
check($config['min'] === null && $config['max'] === null && $config['sourceStep'] === null && $config['step'] === 1, 'missing source limits remain absent');
$module->RequestAction('SetRoomTemperature', 23);
check($GLOBALS['captured']['VALUE'] === 23.0 && array_column($config['choices'], 'value') === range(15, 30), 'user-defined 15 to 30 range applies without native bounds');
rejects(function () use ($module) { $module->RequestAction('SetRoomTemperature', 31); }, 'cannot leave requested range');
IPS_SetVariableProfileValues('Fixture.Setpoint', 15, 30, .5);
SetValue($setpoint, 24.5);
$config = json_decode($module->GetSetpointDiagnostics(), true);
check(array_column($config['choices'], 'value') === range(15, 30), 'absolute 15 to 30 range remains integer even when current setpoint is fractional');
SetValue($setpoint, 24.0);
IPS_ApplyChanges($id);
if (!is_dir(__DIR__ . '/output')) { mkdir(__DIR__ . '/output', 0777, true); }
file_put_contents(__DIR__ . '/output/tile.html', $module->GetVisualizationTile());
file_put_contents(__DIR__ . '/output/snapshot.json', json_encode(snapshot($module), JSON_THROW_ON_ERROR));
echo "PASS: $checks module assertions (official SymconStubs)\n";
