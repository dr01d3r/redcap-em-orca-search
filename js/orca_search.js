$(function() {
    if (!OrcaSearch) return;

    $addEditLink = $("#west a:contains(" + OrcaSearch.addEditLinkText + ")");
    $link = $("#external_modules_panel a[href*='orca_search']");
    switch (OrcaSearch.moduleLinkType) {
        case "add_edit_replace":
            //$link.closest(".hang").detach();
            $addEditLink.attr("href", $link.attr("href"));
            $("<div class='orca-tooltip'>\n" +
                "\t<div class='orca-tooltip-content'>This link has been modified to send you to the Orca Search Module</div>\n" +
                "\t<span class=\"glyphicon glyphicon-info-sign\" style=\"display: inline;color: #800000;\"></span>\n" +
                "</div>").insertAfter($addEditLink);
            break;
        /*
        // For future use
        case "add_edit_sibling":
            $linkContainer = $link.closest(".hang");
            $linkContainer.detach();
            $linkContainer.insertAfter($addEditLink.closest(".hang"));
            break;
         */
    }
    $('.orca-tooltip').popover({
        html: true,
        trigger: 'hover',
        placement: 'auto right',
        container: 'body',
        content: function() {
            $(this).css('cursor','pointer');
            return $(this).find('.orca-tooltip-content').html();
        }
    });
});