{* Smarty *}
<div class="projhdr">
    <img src="{$config["redcap_images_path"]}blog_pencil.gif" /> Search Dashboard
</div>
{foreach from=$config["messages"] item=message}
    <div class="alert alert-info alert-dismissible" role="alert">
        <button type="button" class="close" data-dismiss="alert" aria-label="Close"><span aria-hidden="true">&times;</span></button>
        <b>Info:</b> {$message}
    </div>
{/foreach}

{foreach from=$config["warnings"] item=warning}
    <div class="alert alert-warning alert-dismissible" role="alert">
        <button type="button" class="close" data-dismiss="alert" aria-label="Close"><span aria-hidden="true">&times;</span></button>
        <b>Warning:</b> {$warning}
    </div>
{/foreach}

{foreach from=$config["errors"] item=error}
    <div class="alert alert-danger alert-dismissible" role="alert">
        <button type="button" class="close" data-dismiss="alert" aria-label="Close"><span aria-hidden="true">&times;</span></button>
        <b>Error:</b> {$error}
    </div>
{/foreach}

<div class="card">
    <!-- Default panel contents -->
    <div class="card-header">
        <form id="orca_search_form" method="post" action="">
            <div class="row">
                <div class="form-group col-lg">
                    <label for="search-field">Select Search Field</label><br/>
                    <select name="search-field" id="search-field" class="form-control form-select">
                        {foreach from=$config["search_fields"] key=field_name item=field_data}
                            {if $field_name === $search_info["search-field"]}
                                <option selected="true" value="{$field_name}">{$field_data["value"]}</option>
                            {else}
                                <option value="{$field_name}">{$field_data["value"]}</option>
                            {/if}
                        {/foreach}
                    </select>
                </div>
                {* This condition is copied below for responsiveness support *}
                {if $config["auto_numbering"]}
                    <div class="form-group col-lg d-none d-lg-block">
                        <label>New Record</label><br/>
                        <button type="button" class="orca-search-new-record btn btn-secondary form-control">{$config["new_record_text"]}</button>
                    </div>
                {else}
                    <div class="col-lg d-none d-lg-block">
                        <label>New Record</label><br/>
                        <div class="input-group">
                            <input type="text" autocomplete="new-password" class="orca-search-new-record-id form-control" placeholder="New {$config["new_record_label"]}" />
                            <span class="input-group-btn">
                                <button type="button" class="orca-search-new-record btn btn-secondary">{$config["new_record_text"]}</button>
                            </span>
                        </div>
                    </div>
                {/if}
            </div>
            <div class="row">
                <div class="form-group col-lg">
                    <label for="search-field">Search Text</label><br/>
                    {if !empty($search_info["search-value"])}
                        <input id="search-value" name="search-value" type="text" class="form-control" value="{$search_info["search-value"]}" />
                    {else}
                        <input id="search-value" name="search-value" type="text" class="form-control" />
                    {/if}
                    {foreach from=$config["search_fields"] key=field_name item=field_data}
                        {if isset($field_data["dictionary_values"])}
                            <select class="form-control form-select orca-search-field-select" id="{$field_name}">
                                <option value="">--</option>
                                {foreach from=$field_data["dictionary_values"] key=dd_key item=dd_value}
                                    {if $field_name === $search_info["search-field"] && $search_info["search-value"] === "$dd_key"}
                                        <option selected="selected" value="{$dd_key}">{$dd_value}</option>
                                    {else}
                                        <option value="{$dd_key}">{$dd_value}</option>
                                    {/if}
                                {/foreach}
                            </select>
                        {/if}
                    {/foreach}
                </div>
                {* This is copied below for responsiveness support *}
                <div class="form-group col-lg d-none d-lg-block">
                    {if $config["result_limit"] > 0}
                        <label>Limit: <i style="font-weight: normal;">{$config["result_limit"]}</i></label>
                    {else}
                        <label>&nbsp;</label><br/>
                    {/if}
                    <button id="orca-search" type="button" class="btn btn-info form-control">Search</button>
                </div>
            </div>
            <div class="row">
                <div class="form-group col-12 d-lg-none">
                    {if $config["result_limit"] > 0}
                        <label>Limit: <i style="font-weight: normal;">{$config["result_limit"]}</i></label>
                    {/if}
                    <button id="orca-search" type="button" class="btn btn-info form-control">Search</button>
                </div>
                {if $config["auto_numbering"]}
                    <div class="form-group col-12 d-lg-none">
                        <label>New Record</label><br/>
                        <button type="button" class="orca-search-new-record btn btn-secondary form-control">{$config["new_record_text"]}</button>
                    </div>
                {else}
                    <div class="col-12 d-lg-none">
                        <label>New Record</label><br/>
                        <div class="input-group">
                            <input type="text" class="orca-search-new-record-id form-control" placeholder="New {$config["new_record_label"]}" />
                            <span class="input-group-btn">
                                <button type="button" class="orca-search-new-record btn btn-secondary">{$config["new_record_text"]}</button>
                            </span>
                        </div>
                    </div>
                {/if}
            </div>
        </form>
        {if $config["has_repeating_forms"]}
            {if $config["instance_search"] === "LATEST"}
                <b>Note:</b> <i>Search will only return matches that occur within the <b>latest</b> instance of a form.</i>
            {else}
                <b>Note:</b> <i>Search will return matches that occur in <b>any</b> instance of a form.</i>
            {/if}
        {/if}
        {if !empty($config["user_dag"])}
            <b>Note:</b> <i>Search results will be limited to the <b>{$config["groups"][$config["user_dag"]]}</b> Data Access Group.</i>
        {/if}
    </div>
    <div class="card-body">
        <div id="orca_search_table_ph">
            Loading data. Please wait...
        </div>
        <table id="orca_search_table" class="table table-bordered table-condensed table-hover">
            <thead>
            <tr>
                {foreach from=$config["display_fields"] key=col_name item=col_value}
                    <th class="header">{$col_value["label"]}</th>
                {/foreach}
                <th class="header">Record Home</th>
            </tr>
            </thead>
            <tbody>
            {foreach from=$data key=record_id item=record}
                <tr>
                    {foreach from=$config["display_fields"] key=col_name item=col_value}
                        <td{if !empty($record[$col_name]["__SORT__"])} data-sort="{$record[$col_name]["__SORT__"]}"{/if}>
                            {if !empty($record[$col_name]["badge"])}
                                <span class="badge badge-danger float-right">{$record[$col_name]["badge"]}</span>
                            {/if}
                            {if is_array($record[$col_name]["value"])}
                                {if count($record[$col_name]["value"]) > 0}
                                    <ul>
                                        {foreach from=$record[$col_name]["value"] key=sub_index item=sub_value}
                                            <li>{$sub_value}</li>
                                        {/foreach}
                                    </ul>
                                {/if}
                            {else}
                                {$record[$col_name]["value"]}
                            {/if}
                        </td>
                    {/foreach}
                    <td>
                        <a href="{$record["dashboard_url"]}" class="jqbuttonmed ui-button ui-widget ui-state-default ui-corner-all ui-button-text-only" role="button">
                            <span class="ui-button-text">
                                <span class="ui-button-text">
                                    <i class="fas fa-edit"></i>&nbsp;Open
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

{if $debug}
    <pre class="well">{$debug}</pre>
{/if}

<script type="text/javascript">
    $(function () {
        function setSearchControl(clearValue) {
            $(".orca-search-field-select").hide();
            let $searchValue = $("#search-value");
            $searchValue.hide();

            if (clearValue === true) {
                $searchValue.val('');
            }

            let val = $("#search-field").val();
            let $val = $(`#${ val }`);
            if ($val.length > 0) {
                $val.show().change();
            } else {
                $searchValue.show();
            }
        }
        var table = $("#orca_search_table").dataTable({
            pageLength: 50,
            order: {$orca_search_field_sorting},
            initComplete: function () {
                $("#orca_search_table").css('width', '100%').show();
                $("#orca_search_table_ph").hide();
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

        $("body").on("click", "button.orca-search-new-record:visible", function() {
            {if $config["auto_numbering"]}
            window.location.href = '{$config["new_record_url"]}' + '&id=' + '{$config["new_record_auto_id"]}';
            {else}
            let refocus = false;
            let $id = $('input.orca-search-new-record-id:visible');
            let idval = trim($id.val());
            if (idval.length < 1) {
                return;
            }
            if (idval.length > 100) {
                refocus = true;
                alert('The value entered must be 100 characters or less in length');
            }
            if (refocus) {
                setTimeout(function(){ document.getElementById('orca-search-new-record-id').focus(); },10);
            } else {
                $id.val(idval);
                idval = $id.val();
                idval = idval.replace(/&quot;/g,''); // HTML char code of double quote
                let validRecordName = recordNameValid(idval);
                if (validRecordName !== true) {
                    $id.val('');
                    alert(validRecordName);
                    $id.focus();
                    return false;
                }
                window.location.href = '{$config["new_record_url"]}' + '&id=' + idval;
            }
            {/if}
        });

        $("body").on("click", "#orca-search", function() {
            $("form#orca_search_form").submit();
        });
        $("body").on("keypress", "#search-value", function(e) {
            if (e.which == 13) {
                $("form#orca_search_form").submit();
            }
        });

        $("#search-field").change(function() {
            setSearchControl(true);
        });

        $(".orca-search-field-select").change(function() {
            $("#search-value").val($(this).val());
        });

        // disable form resubmit on refresh/back
        if ( window.history.replaceState ) {
            window.history.replaceState( null, null, window.location.href );
        }

        setSearchControl(false);
    });
</script>