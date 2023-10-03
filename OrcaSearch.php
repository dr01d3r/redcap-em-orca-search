<?php
// Set the namespace defined in your config file
namespace ORCA\OrcaSearch;

// The next 2 lines should always be included and be the same in every module
use ExternalModules\AbstractExternalModule;
use ExternalModules\ExternalModules;

require_once 'vendor/autoload.php';
require_once 'traits/REDCapUtils.php';

/**
 * Class OrcaSearch
 * @package ORCA\OrcaSearch
 */
class OrcaSearch extends AbstractExternalModule {
    use \ORCA\OrcaSearch\REDCapUtils;

    private static $smarty;

    public function redcap_module_link_check_display($project_id, $link) {
        return true;
    }

    public function redcap_every_page_top($project_id) {
        if (empty($project_id)) return;
        global $lang;
        if ($this->getProjectSetting("replace_add_edit", $project_id) === true) {
            ?>
            <script type='text/javascript'>
                if(typeof OrcaSearch === 'undefined') {
                    var OrcaSearch = {
                        addEditLinkText: [ "<?=$lang['bottom_62']?>", "<?=$lang['bottom_72']?>" ],
                        orcaSearchURL: "<?=$this->getUrl('search.php')?>",
                        moduleLinkType: "add_edit_replace"
                    };
                }
            </script>
            <?php
        }
        echo "<script src='" . $this->getUrl('js/orca_search.js') . "'></script>";
    }

    public function initializeSmarty() {
        $module_path = $this->getModulePath();
        self::$smarty = new \Smarty();
        self::$smarty->setTemplateDir($module_path . 'templates');
        self::$smarty->setCompileDir($module_path . 'templates_c');
        self::$smarty->setConfigDir($module_path . 'configs');
        self::$smarty->setCacheDir($module_path . 'cache');
    }

    public function setTemplateVariable($key, $value) {
        self::$smarty->assign($key, $value);
    }

    public function displayTemplate($template) {
        self::$smarty->display($template);
    }
}