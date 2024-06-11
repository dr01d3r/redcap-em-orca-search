<?php
namespace ORCA\OrcaSearch;

use Smarty\Smarty;

trait SmartyUtils
{
    private static $smarty;

    public function initializeSmarty($module_docroot) {
        self::$smarty = new Smarty();
        self::$smarty->setTemplateDir($module_docroot . 'templates');
        self::$smarty->setCompileDir($module_docroot . 'templates_c');
        self::$smarty->setConfigDir($module_docroot . 'configs');
        self::$smarty->setCacheDir($module_docroot . 'cache');
    }

    public function setTemplateVariable($key, $value) {
        self::$smarty->assign($key, $value);
    }

    public function displayTemplate($template) {
        self::$smarty->display($template);
    }

    public function fetchTemplate($template) {
        return self::$smarty->fetch($template);
    }
}