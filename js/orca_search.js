$(function() {
    if (!OrcaSearch) return;

    $addEditLink = $("#west a:contains(" + OrcaSearch.addEditLinkText + ")");
    switch (OrcaSearch.moduleLinkType) {
        case "add_edit_replace":
            $addEditLink.attr("href", OrcaSearch.orcaSearchURL);
            $("<div class='orca-tooltip'>\n" +
                "\t<div class='orca-tooltip-content'>This link has been modified to send you to the Orca Search Module</div>\n" +
                "\t<span class=\"glyphicon glyphicon-info-sign\" style=\"display: inline;color: #800000;\"></span>\n" +
                "</div>").insertAfter($addEditLink);
            break;
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