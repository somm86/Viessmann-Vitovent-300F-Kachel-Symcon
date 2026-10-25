<?php
// Import the actual extracted installation ZIP using the official library loader.
require (getenv('SYMCON_STUBS') ?: '/tmp/SymconStubs') . '/autoload.php';
set_error_handler(function ($severity, $message, $file, $line) {
    if ($severity === E_DEPRECATED) { return true; }
    throw new ErrorException($message, 0, $severity, $file, $line);
});
IPS\Kernel::reset();
IPS\ModuleLoader::loadLibrary($argv[1] . '/library.json');
if (IPS_GetModuleList() !== ['{4305595E-0E5F-4124-AD59-8E11257A1E97}']) {
    throw new Exception('Wrong module membership');
}
$id = IPS_CreateInstance('{4305595E-0E5F-4124-AD59-8E11257A1E97}');
$instance = IPS\InstanceManager::getInstanceInterface($id);
$form = json_decode($instance->GetConfigurationForm(), true, 512, JSON_THROW_ON_ERROR);
if (count($form['elements']) !== 9 || IPS_GetProperty($id, 'ModeID') !== 54849 ||
    IPS_GetInstance($id)['InstanceVisualizationType'] !== 1 ||
    IPS_GetInstance($id)['InstanceStatus'] !== 104 ||
    strpos($instance->GetVisualizationTile(), '/*__INITIAL_DATA__*/null') !== false) {
    throw new Exception('Packaged module did not initialize correctly');
}
echo "PASS: extracted ZIP imports, creates instance and renders initial tile (official stubs)\n";
