<?php
/** @var \ORCA\OrcaSearch\OrcaSearch $module */

$module->addTime();
$module->initializeJavascriptModuleObject();
$b = new \Browser();
$cmdKey = ( $b->getPlatform() == "Apple" ? "&#8984;" : "Ctrl" );
?>
    <div id="ORCA_SEARCH_CONFIG"></div>
    <script>
        const OrcaSearch = function() {
            return {
                jsmo: <?=$module->getJavascriptModuleObjectName()?>,
                cmdKey: '<?=$cmdKey?>'
            }
        };
    </script>
    <script type="module" src="<?=$module->getUrl('dist/config.js')?>"></script>
    <link rel="stylesheet" href="<?=$module->getUrl('dist/assets/config.css')?>">
<?php
$module->outputModuleVersionJS();