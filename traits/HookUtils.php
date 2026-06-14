<?php
/** @var \ORCA\OrcaSearch\OrcaSearch $this */

namespace ORCA\OrcaSearch;

use Exception;

trait HookUtils {
    public function redcap_module_link_check_display($project_id, $link): bool
    {
        try {
            // limit restricted links to only Project Design rights
            if ($link["restricted"] === true) {
                // respect impersonation when checking link visibility
                $impersonatingUser = \UserRights::getUsernameImpersonating();
                $userid = empty($impersonatingUser) ? USERID : $impersonatingUser;

                return (defined("USERID") && \REDCap::getUserRights()[$userid]['design'] === "1")
                    // ensure SUPER_USER doesn't lose access if put into a role that doesn't have design rights
                    || (empty($impersonatingUser) && defined("SUPER_USER") && SUPER_USER)
                    ;
            }
        } catch (Exception $ex) {}
        return true;
    }

    public function redcap_every_page_top($project_id) {
        if (empty($project_id)) return;
        global $lang;
        if ($this->getProjectSetting("replace_add_edit", $project_id) === true) {
            ?>
            <script type='text/javascript'>
                $(function() {
                    let $addEditLink = $("#west a:contains(<?=$lang['bottom_62']?>)");
                    if ($addEditLink.length === 0) {
                        $addEditLink = $("#west a:contains(<?=$lang['bottom_72']?>)");
                    }
                    $addEditLink.attr("href", "<?=$this->getUrl('search.php')?>");
                    $("<span class='orca-tooltip' data-toggle='popover' data-content='This link has been modified to send you to the Orca Search Module'>\n" +
                        "\t<i class='fas fa-info-circle' style='display: inline;color: #800000;'></i>\n" +
                        "</span>").insertAfter($addEditLink);
                    $('.orca-tooltip').popover({
                        container: 'body',
                        html: true,
                        trigger: 'hover'
                    });
                });
            </script>
            <?php
        }
    }
}