<?php
/** @var \ORCA\AddEditRecords\AddEditRecords $module */
require_once APP_PATH_DOCROOT . 'ProjectGeneral/header.php';
require_once APP_PATH_DOCROOT . 'ProjectGeneral/form_renderer_functions.php';

$config = [
    "result_limit" => intval($module->getProjectSetting("search_limit")),
    "table_id" => "A" . uniqid(),
    "new_record_text" => $lang['data_entry_46'],
    "include_dag" => false,
    "groups" => [],
    "search_fields" => [],
    "display_fields" => [],
    "messages" => [],
    "errors" => []
];

foreach ($module->getSubSettings("search_fields") as $search_field) {
    $config["search_fields"][$search_field["search_field_name"]] = [
        "wildcard" => $search_field["search_field_name_wildcard"],
        "value" => $module->getDictionaryLabelFor($search_field["search_field_name"])
    ];
}

foreach ($module->getSubSettings("display_fields") as $display_field) {
    $config["display_fields"][$display_field["display_field_name"]] = $module->getDictionaryLabelFor($display_field["display_field_name"]);
}

if ($module->getProjectSetting("include_dag_if_exists") === true && count($Proj->getGroups()) > 0) {
    $config["include_dag"] = true;
    $config["display_fields"]["redcap_data_access_group"] = "Group";
    $config["groups"] = array_combine($Proj->getUniqueGroupNames(), $Proj->getGroups());
}

$fieldValues = null;
if (isset($_POST["search-field"]) && isset($_POST["search-value"])) {
    $search_value = $_POST["search-value"];
    if ($config["search_fields"][$_POST["search-field"]]["wildcard"] === true) {
        $search_value = "$search_value%";
    }
    $fieldValues[$_POST["search-field"]] = $search_value;
}

$metadata = [
    "fields" => [],
    "forms" => []
];
$debug = [];
$records = [];
$results = [];

$recordIds = null;
$recordCount = null;

$startSeconds = microtime(true);

if (!empty($fieldValues)) {
    $recordIds = $module->getProjectRecordIds($fieldValues, "ALL", "ALL");
    $stopSecondsRecordId = microtime(true);
    $recordCount = count($recordIds);
}

if ($recordCount === 0) {
    $config["messages"][] = "Search yielded no results.";
} else if ($recordCount != null && !empty($config["result_limit"]) && $recordCount > $config["result_limit"]) {
    $config["errors"][] = "Too many results found ($recordCount).  Please be more specific (limit {$config["result_limit"]}).";
} else if ($recordCount > 0) {
    $records = \REDCap::getData($module->getPid(), 'array', $recordIds, array_keys($config["display_fields"]), null, null, false, $config["include_dag"]);
}
$stopSecondsGetData = microtime(true);

/*
 * Build the Form/Field Metadata
 * This is necessary for knowing where to find record
 * values (i.e. repeating/non-repeating forms)
 */
foreach ($Proj->forms as $form_name => $form_data) {
    $metadata["forms"][$form_name] = [
        "event_id" => null,
        "repeating" => false
    ];
    foreach ($form_data["fields"]  as $field_name => $field_label) {
        $metadata["fields"][$field_name] = [
            "form" => $form_name
        ];
    }
}
foreach ($Proj->eventsForms as $event_id => $event_forms) {
    foreach ($event_forms as $form_index => $form_name) {
        $metadata["forms"][$form_name]["event_id"] = $event_id;
    }
}
if ($Proj->hasRepeatingForms()) {
    foreach ($Proj->getRepeatingFormsEvents() as $event_id => $event_forms) {
        foreach ($event_forms as $form_name => $value) {
            $metadata["forms"][$form_name]["repeating"] = true;
        }
    }
}

/*
 * Record Processing
 */
foreach ($records as $record_id => $record) { // Record

    $dashboard_url = APP_PATH_WEBROOT . "DataEntry/record_home.php?" . http_build_query([
            "pid" => $module->getPid(),
            "id" => $record_id
        ]);

    $record_info = [
        "record_id" => $record_id,
        "dashboard_url" => $dashboard_url
    ];

    foreach ($config["display_fields"] as $field_name => $field_text) {
        // don't handle DAG directly, it will be set in process of the first non-DAG field
        if ($field_name === "redcap_data_access_group") continue;

        // prep some form info
        $field_form_name = $metadata["fields"][$field_name]["form"];
        $field_form_event_id = $metadata["forms"][$field_form_name]["event_id"];

        // initialize some helper variables/arrays
        $field_value = null;
        $form_values = [];
        $field_value_prefix = "";

        // set the form_values array with the data we want to look at
        if ($metadata["forms"][$field_form_name]["repeating"]) {
            $form_values = end($record["repeat_instances"][$field_form_event_id][$field_form_name]);
            $field_value_prefix = "(" . key($record["repeat_instances"][$field_form_event_id][$field_form_name]) . ") ";
        } else {
            $form_values = $record[$field_form_event_id];
        }

        // special handling for dag as well as structured data fields
        if ($config["include_dag"] === true && !isset($record_info["redcap_data_access_group"])) {
            $record_info["redcap_data_access_group"] = $config["groups"][$form_values["redcap_data_access_group"]];
        }

        // set the raw value of the field
        $field_value = $form_values[$field_name];

        // if it is anything but free text, find the structured non-key value
        if ($Proj->metadata[$field_name]["element_type"] !== "text") {
            $field_value = $module->getDictionaryValuesFor($field_name)[$field_value];
        }

        // highlighting
        if ($field_name === $_POST["search-field"]) {
            $match_index = strpos(strtolower($field_value), strtolower($_POST["search-value"]));
            $match_value = substr($field_value, $match_index, strlen($_POST["search-value"]));
            if ($match_index >= 0) {
                $field_value = str_replace($match_value, "<span class='add-edit-search-content'>{$match_value}</span>", $field_value);
            }
        }

        // prepend the instance prefix to the value (if any) and add it to the record info
        $record_info[$field_name] = $field_value_prefix . $field_value;
    }
    // add record data to the full dataset
    $results[$record_id] = $record_info;
}

/*
 * Push all the results to Smarty templates for rendering
 */

$module->setTemplateVariable("total_server_time", $time_data_processing_complete);

if (false) { // TODO this will be replaced with an 'enable debugging' setting
    if ((isset($debug) && !empty($debug))) {
        $module->setTemplateVariable("debug", print_r($debug, true));
    }
}

$module->setTemplateVariable("config", $config);

if (!empty($_POST)) {
    $module->setTemplateVariable("search_info", $_POST);
}

$module->setTemplateVariable("newRecordUrl",
    APP_PATH_WEBROOT . "DataEntry/record_home.php?" . http_build_query([
        "pid" => $module->getPid(),
        "id" => getAutoId(),
        "auto" => "1"
    ]));

$module->setTemplateVariable("data", $results);

$module->displayTemplate('add_edit_records.tpl');

$stopSecondsFullLoop = microtime(true);
$time_initial_record_result = round($stopSecondsRecordId - $startSeconds, 4);
$time_total_record_result = round($stopSecondsGetData - $startSeconds, 4);
$time_data_processing_complete = round($stopSecondsFullLoop - $startSeconds, 4);

echo "<i>Total Server Time: {$time_data_processing_complete} seconds</i>";

require_once APP_PATH_DOCROOT . 'ProjectGeneral/footer.php';