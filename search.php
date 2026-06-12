<?php
/** @var \ORCA\OrcaSearch\OrcaSearch $module */

$module->addTime();
$module->initializeJavascriptModuleObject();
?>
    <div id="ORCA_SEARCH"></div>
    <script>
        const OrcaSearch = function() {
            return {
                jsmo: <?=$module->getJavascriptModuleObjectName()?>
            }
        };
    </script>
    <script type="module" src="<?=$module->getUrl('dist/search.js')?>"></script>
    <link rel="stylesheet" href="<?=$module->getUrl('dist/assets/search.css')?>">
<?php
$module->outputModuleVersionJS();