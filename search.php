<?php
/** @var \ORCA\AddEditRecords\AddEditRecords $module */
require_once APP_PATH_DOCROOT . 'ProjectGeneral/header.php';
require_once APP_PATH_DOCROOT . 'ProjectGeneral/form_renderer_functions.php';

$config = [
    "result_limit" => 1000,
    "table_id" => "A" . uniqid(),
    "new_record_text" => $lang['data_entry_46'],
    "include_dag" => false,
    "groups" => [],
    "search_fields" => [],
    "display_fields" => []
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

$debug = [];
$records = [];
$results = [];

if (!empty($fieldValues)) {
    $startSeconds = microtime(true);
    $recordIds = $module->getProjectRecordIds($fieldValues, "ALL", "ALL");
    $stopSecondsRecordId = microtime(true);
    $recordCount = count($recordIds);
    if ($recordCount > $config["result_limit"]) {
        $message = "Too many results found ($recordCount).  Please be more specific (limit {$config["result_limit"]}).";
    } else if ($recordCount > 0) {
        $records = \REDCap::getData($module->getPid(), 'array', $recordIds, array_keys($config["display_fields"]), null, null, false, $config["include_dag"]);
    }
    $stopSecondsGetData = microtime(true);
}

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
        $field_value = null;
        // TODO need to handle eventId properly
        if (false) { // TODO properly check for and handle repeating instruments/events
             $field_value = $record[$Proj->firstEventId][$Proj->metadata[$field_name]["form_name"]][$field_name];
        } else {
            $field_value = $record[$Proj->firstEventId][$field_name];
        }

        // special handling for dag as well as structured data fields
        if ($field_name === "redcap_data_access_group") {
            $field_value = $config["groups"][$field_value];
        } else if ($Proj->metadata[$field_name]["element_type"] !== "text") {
            $field_value = $module->getDictionaryValuesFor($field_name)[$field_value];
        }

        if ($field_name === $_POST["search-field"]) {
            $match_index = strpos(strtolower($field_value), strtolower($_POST["search-value"]));
            $match_value = substr($field_value, $match_index, strlen($_POST["search-value"]));
            if ($match_index >= 0) {
                $field_value = str_replace($match_value, "<span class='add-edit-search-content'>{$match_value}</span>", $field_value);
            }
        }
        $record_info[$field_name] = $field_value;
    }

    $results[$record_id] = $record_info;
}

$stopSecondsFullLoop = microtime(true);

if (true) { // TODO this will be replaced with an 'isDebugging' check
//    $debug["search_info"] = $_POST;
    $debug["Time To Get RecordIDs"] = ($stopSecondsRecordId - $startSeconds) . " seconds";
    $debug["Time To Get Data"] = ($stopSecondsGetData - $startSeconds) . " seconds";
    $debug["Time To Finish Processing"] = ($stopSecondsFullLoop - $startSeconds) . " seconds";
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
$module->setTemplateVariable("message", $message);

$module->displayTemplate('add_edit_records.tpl');

require_once APP_PATH_DOCROOT . 'ProjectGeneral/footer.php';
