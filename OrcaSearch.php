<?php
// Set the namespace defined in your config file
namespace ORCA\OrcaSearch;

// The next 2 lines should always be included and be the same in every module
use ExternalModules\AbstractExternalModule;
use ExternalModules\ExternalModules;

require_once 'traits/ConfigUtils.php';
require_once 'traits/HookUtils.php';
require_once 'traits/ModuleUtils.php';
require_once 'traits/REDCapUtils.php';
require_once 'traits/RequestHandlers.php';

/**
 * Class OrcaSearch
 * @package ORCA\OrcaSearch
 */
class OrcaSearch extends AbstractExternalModule {
    use \ORCA\OrcaSearch\ConfigUtils;
    use \ORCA\OrcaSearch\HookUtils;
    use \ORCA\OrcaSearch\ModuleUtils;
    use \ORCA\OrcaSearch\REDCapUtils;
    use \ORCA\OrcaSearch\RequestHandlers;
}