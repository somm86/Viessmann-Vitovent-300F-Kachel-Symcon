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
check(IPS_GetProperty($id, 'ModeID') === 54849, 'original defaults');
check(count(snapshot($module)['missing']) === 7, 'empty installation is honest');
check(IPS_GetInstance($id)['InstanceStatus'] === 104, 'incomplete status');
$form = json_decode(IPS_GetConfigurationForm($id), true, 512, JSON_THROW_ON_ERROR);
check(count(array_filter($form['elements'], function ($e) { return $e['type'] === 'SelectVariable'; })) === 7, 'seven selectors');
$ids = [];
foreach (['OutsideID','SupplyID','ExtractID','ExhaustID','FlowID','FilterID','ModeID'] as $property) {
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
check(count(IPS_GetReferenceList($id)) === 7, 'source references registered');
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
check(count(IPS_GetReferenceList($id)) === 7, 'apply repeatable');
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
// Produce browser input with synthetic fixture values and actual module-generated HTML.
IPS_SetVariableProfileAssociation('Fixture.FloatMode', 2.5, 'Testmodus A', '', -1);
IPS_SetVariableProfileAssociation('Fixture.FloatMode', 9.5, 'Testmodus B', '', -1);
IPS_SetProperty($id, 'OutsideID', $old);
IPS_ApplyChanges($id);
if (!is_dir(__DIR__ . '/output')) { mkdir(__DIR__ . '/output', 0777, true); }
file_put_contents(__DIR__ . '/output/tile.html', $module->GetVisualizationTile());
file_put_contents(__DIR__ . '/output/snapshot.json', json_encode(snapshot($module), JSON_THROW_ON_ERROR));
echo "PASS: $checks module assertions (official SymconStubs)\n";
