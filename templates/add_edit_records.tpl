{* Smarty *}
<link rel="stylesheet" href="https://cdn.datatables.net/1.10.12/css/dataTables.bootstrap.min.css">
<style>
    #{$config["table_id"]} > thead > tr > th {
        text-overflow: ellipsis;
    }

    #{$config["table_id"]} > tbody > tr,
    #{$config["table_id"]} > tbody > tr > td {
        position: relative;
        vertical-align: middle;
    }

    #{$config["table_id"]} {
        display: none;
    }

    #{$config["table_id"]}_ph {
        font-size: 20px;
        font-style: italic;
        padding: 35px;
    }

    .dataTables_wrapper > div.row:first-child {
        padding: 10px 10px 0px 10px !important;
    }

    .dataTables_wrapper > div.row:last-child {
        padding: 0px 10px 10px 10px !important;
    }

    #{$config["table_id"]} .jqbuttonmed {
        white-space: nowrap;
    }

    .add-edit-search-content {
        font-weight: bold;
        color: blue;
    }

    .alert {
        border: 1px solid transparent !important;
        /*margin-bottom: 10px;*/
    }
    .alert-danger {
        border-color: #ebccd1 !important;
    }
    .alert-warning {
        border-color: #faebcc !important;
    }
    .alert-info {
        border-color: #bce8f1 !important;
    }
    .alert-success {
        border-color: #d6e9c6 !important;
    }
</style>
<script src="https://cdn.datatables.net/1.10.12/js/jquery.dataTables.min.js"></script>
<script src="https://cdn.datatables.net/1.10.12/js/dataTables.bootstrap.min.js"></script>

{foreach from=$config["messages"] item=message}
    <div class="alert alert-info alert-dismissible" role="alert">
        <button type="button" class="close" data-dismiss="alert" aria-label="Close"><span aria-hidden="true">&times;</span></button>
        <b>Info:</b> {$message}
    </div>
{/foreach}

{foreach from=$config["errors"] item=error}
    <div class="alert alert-danger alert-dismissible" role="alert">
        <button type="button" class="close" data-dismiss="alert" aria-label="Close"><span aria-hidden="true">&times;</span></button>
        <b>Error:</b> {$error}
    </div>
{/foreach}

<div class="panel panel-default">
    <!-- Default panel contents -->
    <div class="panel-heading">
        <form method="post" action="">
            <div class="row">
                <div class="form-group col-md-6">
                    <label for="search-field">Select Search Field</label><br/>
                    <select name="search-field" id="search-field" class="form-control">
                        {foreach from=$config["search_fields"] key=field_name item=field_data}
                            {if $field_name == $search_info["search-field"]}
                                <option selected="true" value="{$field_name}">{$field_data["value"]}</option>
                            {else}
                                <option value="{$field_name}">{$field_data["value"]}</option>
                            {/if}
                        {/foreach}
                    </select>
                </div>
                {if $config["auto_numbering"]}
                    <div class="form-group col-md-6">
                        <label>New Record</label><br/>
                        <button id="add-edit-new-record" type="button" class="btn btn-default form-control">{$config["new_record_text"]}</button>
                    </div>
                {else}
                    <div class="col-md-6">
                        <label>New Record</label><br/>
                        <div class="input-group">
                            <input id="add-edit-new-record-id" type="text" class="form-control" placeholder="New {$config["new_record_label"]}" />
                            <span class="input-group-btn">
                                <button id="add-edit-new-record" type="button" class="btn btn-default">{$config["new_record_text"]}</button>
                            </span>
                        </div>
                    </div>
                {/if}
            </div>
            <div class="row">
                <div class="form-group col-md-6">
                    <label for="search-field">Search Text</label><br/>
                    {if !empty($search_info["search-value"])}
                        <input id="search-value" name="search-value" type="text" class="form-control" value="{$search_info["search-value"]}" />
                    {else}
                        <input id="search-value" name="search-value" type="text" class="form-control" />
                    {/if}
                </div>
                <div class="form-group col-md-6">
                    {if $config["result_limit"] > 0}
                        <label>Limit: <i style="font-weight: normal;">{$config["result_limit"]}</i></label>
                    {else}
                        <label>&nbsp;</label><br/>
                    {/if}
                    <button id="add-edit-search" type="button" class="btn btn-info form-control">Search</button>
                </div>
            </div>
        </form>
        {if $config["has_repeating_forms"]}
            {if $config["instance_search"] === "LATEST"}
                <b>Note:</b> <i>Search will only return matches that occur within the <b>latest</b> instance of a form.</i>
            {else}
                <b>Note:</b> <i>Search will return matches that occur in <b>any</b> instance of a form.</i>
            {/if}
        {/if}
    </div>
    <div>
        <div id="{$config["table_id"]}_ph">
            Loading data. Please wait...
        </div>
        <div class="table-responsive">
            <table id="{$config["table_id"]}" class="table table-bordered table-condensed table-hover">
                <thead>
                <tr>
                    {foreach from=$config["display_fields"] key=col_name item=col_value}
                        <th class="header">{$col_value}</th>
                    {/foreach}
                    <th class="header">Record Home</th>
                </tr>
                </thead>
                <tbody>
                {foreach from=$data key=record_id item=record}
                    <tr>
                        {foreach from=$config["display_fields"] key=col_name item=col_value}
                            <td>{$record[$col_name]}</td>
                        {/foreach}
                        <td>
                            <a href="{$record["dashboard_url"]}" class="jqbuttonmed ui-button ui-widget ui-state-default ui-corner-all ui-button-text-only" role="button">
                                <span class="ui-button-text">
                                    <span class="ui-button-text">
                                        <span class="glyphicon glyphicon-edit"></span>&nbsp;Open
                                    </span>
                                </span>
                            </a>
                        </td>
                    </tr>
                {/foreach}
                </tbody>
            </table>
        </div>
    </div>
</div>

{if $debug}
    <pre>{$debug}</pre>
{/if}

<script type="text/javascript">
    $(function () {
        var table = $("#{$config["table_id"]}").DataTable({
            pageLength: 50,
            initComplete: function () {
                $("#{$config["table_id"]}").css('width', '100%').show();
                $("#{$config["table_id"]}_ph").hide();
            }
        });

        $("input[type='search']").on("keydown keypress", function (event) {
            if (event.which === 8) {
                table.draw();
                event.stopPropagation();
            }
        });

        if ($("#search-value").val().length == 0) {
            $("#search-value").focus();
        }

        $("body").on("click", "#add-edit-new-record", function() {
            {if $config["auto_numbering"]}
                window.location.href = '{$config["new_record_url"]}' + '&id=' + {$config["new_record_auto_id"]} + addGoogTrans();
            {else}
                var refocus = false;
                var idval = trim($('#add-edit-new-record-id').val());
                if (idval.length < 1) {
                    return;
                }
                if (idval.length > 100) {
                    refocus = true;
                    alert('The value entered must be 100 characters or less in length');
                }
                if (refocus) {
                    setTimeout(function(){ document.getElementById('add-edit-new-record-id').focus(); },10);
                } else {
                    $('#add-edit-new-record-id').val(idval);
                    idval = $('#add-edit-new-record-id').val();
                    idval = idval.replace(/&quot;/g,''); // HTML char code of double quote
                    var validRecordName = recordNameValid(idval);
                    if (validRecordName !== true) {
                        $('#add-edit-new-record-id').val('');
                        alert(validRecordName);
                        $('#add-edit-new-record-id').focus();
                        return false;
                    }
                    // Redirect, but NOT if the validation pop-up is being displayed (for range check errors)
                    if (!$('.simpleDialog.ui-dialog-content:visible').length) {
                        window.location.href = '{$config["new_record_url"]}' + '&id=' + idval + addGoogTrans();
                    }
                }
            {/if}
        });

        $("body").on("click", "#add-edit-search", function() {
            document.forms[0].submit();
        });



        // disable form resubmit on refresh/back
        if ( window.history.replaceState ) {
            window.history.replaceState( null, null, window.location.href );
        }
    });
</script>