<?php
/** @var \ORCA\OrcaSearch\OrcaSearch $module */
require_once APP_PATH_DOCROOT . 'ProjectGeneral/header.php';

$module->initializeSmarty();
$module->addTime();

$config = [
    "result_limit" => intval($module->getProjectSetting("search_limit")),
    "has_repeating_forms" => $Proj->hasRepeatingForms(),
    "instance_search" => $module->getProjectSetting("instance_search"),
    "show_instance_badge" => $module->getProjectSetting("show_instance_badge"),
    "auto_numbering" => $Proj->project["auto_inc_set"] === "1",
    "new_record_label" => $Proj->table_pk_label,
    "new_record_text" => $lang['data_entry_46'],
    "redcap_images_path" => APP_PATH_IMAGES,
    "module_version" => $module->VERSION,
    "new_record_url" => APP_PATH_WEBROOT . "DataEntry/record_home.php?" . http_build_query([
        "pid" => $module->getPid(),
        "auto" => "1"
    ]),
    "include_dag" => false,
    "user_dag" => null,
    "groups" => [],
    "search_fields" => [],
    "display_fields" => [],
    "messages" => [],
    "errors" => []
];

$metadata = [
    "fields" => [],
    "forms" => [],
    "form_statuses" => [
        0 => "Incomplete",
        1 => "Unverified",
        2 => "Complete"
    ],
    "unstructured_field_types" => [
        "text",
        "textarea"
    ],
    "custom_dictionary_values" => [
        "yesno" => [
            "1" => "Yes",
            "0" => "No"
        ],
        "truefalse" => [
            "1" => "True",
            "0" => "False"
        ]
    ]
];

$debug = [];
$records = [];
$results = [];

$recordIds = null;
$recordCount = null;
$searchConfig = null;

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
if ($config["has_repeating_forms"]) {
    foreach ($Proj->getRepeatingFormsEvents() as $event_id => $event_forms) {
        foreach ($event_forms as $form_name => $value) {
            $metadata["forms"][$form_name]["repeating"] = true;
        }
    }
}

if (!empty(\REDCap::getUserRights(USERID)[USERID]["group_id"])) {
    $config["user_dag"] = \REDCAP::getGroupNames(true, \REDCap::getUserRights(USERID)[USERID]["group_id"]);
}

foreach ($module->getSubSettings("search_fields") as $search_field) {
    if (empty($search_field["search_field_name"])) continue;
    $field_name = $search_field["search_field_name"];

    if ($Proj->isFormStatus($field_name)) {
        $config["search_fields"][$field_name] = [
            "wildcard" => false,
            "value" => $Proj->forms[$Proj->metadata[$field_name]["form_name"]]["menu"] . " Status",
            "dictionary_values" => $metadata["form_statuses"]
        ];
    } else {
        $config["search_fields"][$field_name] = [
            "value" => $module->truncate($module->getDictionaryLabelFor($field_name))
        ];
        // override wildcard config in certain cases; otherwise, take what the user specified
        switch ($Proj->metadata[$field_name]["element_type"]) {
            case "select":
            case "radio":
            case "sql":
                $config["search_fields"][$field_name]["wildcard"] = false;
                break;
            case "checkbox":
                $config["search_fields"][$field_name]["wildcard"] = false;
                break;
            default:
                $config["search_fields"][$field_name]["wildcard"] = $search_field["search_field_name_wildcard"];
                break;
        }
        // set structured values for display in search options
        switch ($Proj->metadata[$field_name]["element_type"]) {
            case "select":
            case "radio":
            case "checkbox":
                $config["search_fields"][$field_name]["dictionary_values"] =
                    $module->truncate($module->getDictionaryValuesFor($field_name));
                break;
            case "yesno":
            case "truefalse":
                $config["search_fields"][$field_name]["dictionary_values"] =
                    $metadata["custom_dictionary_values"][$Proj->metadata[$field_name]["element_type"]];
                break;
            case "sql":
                // add 'dd' to custom_dictionary_values if not already there
                if (!isset($metadata["custom_dictionary_values"][$field_name])) {
                    $sql_enum = parseEnum(getSqlFieldEnum($Proj->metadata[$field_name]['element_enum']));
                    $metadata["custom_dictionary_values"][$field_name] = $sql_enum;
                }
                // set dictionary values for this sql field
                $config["search_fields"][$field_name]["dictionary_values"] =
                    $module->truncate($metadata["custom_dictionary_values"][$field_name]);
                break;
            default: break;
        }
    }
}

//used to keep track of the zero-based index of each column (because the table displays columns from $config["display_fields"] in the order they appear in here)
$fieldIndex = 0;
$fieldSortingInfo = [];
foreach ($module->getSubSettings("display_fields") as $display_field) {
    if (empty($display_field["display_field_name"])) continue;
    $field_name = $display_field["display_field_name"];

    if ($Proj->isFormStatus($field_name)) {
        $config["display_fields"][$field_name] = [
            "is_form_status" => true,
            "label" => $module->truncate($Proj->forms[$Proj->metadata[$field_name]["form_name"]]["menu"] . " Status")
        ];
    } else {
        $config["display_fields"][$field_name] = [
            "label" => $module->truncate($module->getDictionaryLabelFor($field_name))
        ];
        switch ($Proj->metadata[$field_name]["element_type"]) {
            case "sql":
                // add 'dd' to custom_dictionary_values if not already there
                if (!isset($metadata["custom_dictionary_values"][$field_name])) {
                    $sql_enum = parseEnum(getSqlFieldEnum($Proj->metadata[$field_name]['element_enum']));
                    $metadata["custom_dictionary_values"][$field_name] = $sql_enum;
                }
                break;
            default: break;
        }
    }

    //skip sorting if any of the fields for sorting are empty, to ensure everything is filled out
    $sortOnField = $display_field['display_field_sort_on_field'] === true;
    $emptySortFields = empty($display_field['display_field_sort_direction']) || empty($display_field['display_field_sort_priority']);
    //report incorrect configuration of sorting
    if($sortOnField && $emptySortFields) {
        $config["errors"][] = "Incomplete sort configuration for \"{$field_name}\". Either deselect it for sorting or fill in missing values.";
    }

    //if no fields are empty (the priority has to be 1 or greater) AND sort direction isn't set to "NONE", then we can include this field in sorting
    if($sortOnField && !$emptySortFields && (!$display_field['display_field_sort_direction'] !== "NONE")) {
        //this field should be added to the sorting list
        $fieldSortingInfo[] = ["field_index" => $fieldIndex, "direction" => $display_field['display_field_sort_direction'], "priority" => $display_field['display_field_sort_priority']];
    }

    //increment this after all logic dealing with the field
    $fieldIndex++;
}

//sort the array, by reference, according to priority (lower priorities first, as people order things starting at 1)
usort($fieldSortingInfo, function($a, $b) {
    if($a['priority'] == $b['priority']) {
        return 0;
    }
    return $a['priority'] < $b['priority'] ? -1 : 1;
});
//convert array for DataTables format: [columnIndex, asc/desc]
$fieldSortingInfo = array_map(function($fieldInfo){
    return [$fieldInfo['field_index'], $fieldInfo['direction']];
}, $fieldSortingInfo);

if ($module->getProjectSetting("include_dag_if_exists") === true && count($Proj->getGroups()) > 0) {
    $config["include_dag"] = true;
    $config["display_fields"]["redcap_data_access_group"] = [
        "label" => "Data Access Group"
    ];
    $config["groups"] = array_combine($Proj->getUniqueGroupNames(), $Proj->getGroups());
}

if ($config["auto_numbering"]) {
    $config["new_record_auto_id"] = $module->getAutoId();
}

if (isset($_POST["search-field"]) && isset($_POST["search-value"])) {
    $search_field = $_POST["search-field"];
    $search_value = $_POST["search-value"];

    try {
        if ($module->getProjectSetting("empty_search_disabled") === true && $search_value == "") {
            throw new Exception("Empty search has been disabled.  Please provide a value and try again.");
        }

        $searchConfig[$search_field] = [
            "value" => $search_value
        ];

        if ($config["search_fields"][$search_field]["wildcard"] === true) {
            $searchConfig[$search_field]["mode"] = "strpos";
        } else {
            $searchConfig[$search_field]["mode"] = "equals";
        }

        if (empty($config["instance_search"])) {
            $config["instance_search"] = "LATEST";
            if ($config["has_repeating_forms"]) {
                // TODO this is set to only look at the first entry in the array, since the module doesn't yet support multiple search fields
                $search_field_key = key($searchConfig);
                if ($metadata["forms"][$metadata["fields"][$search_field_key]["form"]]["repeating"] === true) {
                    $config["warnings"][] = "<b>" . $config["search_fields"][$search_field_key]["value"] . "</b> is on a repeating instrument, and the config setting <b>Which instances to search through</b> has not been set.  Using a default value of <b>Latest</b>.";
                }
            }
        }

        $recordIds = $module->getProjectRecordIds($searchConfig, $config["instance_search"]);
        // getProjectRecordIds() returns false if no search values are specified
        // this will trigger a full data pull in the next step, so just grab the total record count in the project
        if ($recordIds === false) {
            $recordCount = \Records::getRecordCount($module->getPID());
        } else {
            $recordCount = count($recordIds);
        }
    } catch (Exception $ex) {
        $config["errors"][] = $ex->getMessage();
    }
}

if ($recordCount === 0) {
    $config["messages"][] = "Search yielded no results.";
} else if ($recordCount != null && !empty($config["result_limit"]) && $recordCount > $config["result_limit"]) {
    $config["errors"][] = "Too many results found ($recordCount).  Please be more specific (limit {$config["result_limit"]}).";
} else if ($recordCount > 0) {
    $records = \REDCap::getData($module->getPid(), 'array', $recordIds, array_keys($config["display_fields"]), null, $config["user_dag"], false, $config["include_dag"]);
}

if (empty($config["search_fields"])) {
    $config["errors"][] = "Search fields not yet been configured.  Please go to the <b>" . $lang["global_142"] . "</b> area in the project sidebar to configure them.";
}
if (empty($config["display_fields"])) {
    $config["errors"][] = "Display fields not yet been configured.  Please go to the <b>" . $lang["global_142"] . "</b> area in the project sidebar to configure them.";
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
        "record_id" => [
            "value" => $record_id
        ],
        "dashboard_url" => $dashboard_url
    ];

    foreach ($config["display_fields"] as $field_name => $field_info) {
        // don't handle DAG directly, it will be set in process of the first non-DAG field
        if ($field_name === "redcap_data_access_group") continue;

        // prep some form info
        $field_form_name = $metadata["fields"][$field_name]["form"];
        $field_form_event_id = $metadata["forms"][$field_form_name]["event_id"];

        // initialize some helper variables/arrays
        $field_type = $Proj->metadata[$field_name]["element_type"];
        $field_value = null;
        $form_values = [];

        // set the form_values array with the data we want to look at
        if ($metadata["forms"][$field_form_name]["repeating"]) {
            // TODO (ALL vs LATEST) consider finding the latest instance where the search value was found, and display that instead of always the latest
            $form_values = end($record["repeat_instances"][$field_form_event_id][$field_form_name]);
            if ($config["show_instance_badge"] === true) {
                $record_info[$field_name]["badge"] = key($record["repeat_instances"][$field_form_event_id][$field_form_name]);
            }
        } else {
            $form_values = $record[$field_form_event_id];
        }

        // special handling for dag as well as structured data fields
        if ($config["include_dag"] === true && !isset($record_info["redcap_data_access_group"])) {
            $record_info["redcap_data_access_group"]["value"] = $config["groups"][$form_values["redcap_data_access_group"]];
        }

        // set the raw value of the field
        $field_value = $form_values[$field_name];

        if ($field_name === $Proj->table_pk) {
            $parts = explode("-", $field_value);
            if (count($parts) > 1) {
                $record_info[$field_name]["__SORT__"] = implode(".", [$parts[0], str_pad($parts[1], 10, "0", STR_PAD_LEFT)]);
            } else {
                $record_info[$field_name]["__SORT__"] = $field_value;
            }
        }

        if ($field_info["is_form_status"] === true) {
            // special value handling for form statuses
            $field_value = $metadata["form_statuses"][$field_value];
        } else if (!in_array($field_type, $metadata["unstructured_field_types"])) {
            switch ($field_type) {
                case "select":
                case "radio":
                    $field_value = $module->getDictionaryValuesFor($field_name)[$field_value];
                    break;
                case "checkbox":
                    $temp_field_array = [];
                    $field_value_dd = $module->getDictionaryValuesFor($field_name);
                    foreach ($field_value as $field_value_key => $field_value_value) {
                        if ($field_value_value === "1") {
                            $temp_field_array[$field_value_key] = $field_value_dd[$field_value_key];
                        }
                    }
                    $field_value = $temp_field_array;
                    break;
                case "yesno":
                case "truefalse":
                    $field_value = $metadata["custom_dictionary_values"][$Proj->metadata[$field_name]["element_type"]][$field_value];
                    break;
                case "sql":
                    if (isset($metadata["custom_dictionary_values"][$field_name][$field_value])) {
                        $field_value = $metadata["custom_dictionary_values"][$field_name][$field_value];
                    } else if ($field_value !== null && $field_value != '') {
                        // we don't want to show the raw value if a match is not found
                        $field_value = "";
                    }
                    break;
                default: break;
            }
        }

        /*
         * Highlighting
         * - selected search field
         * - is a field type that is unstructured
         * - was selected as a wildcard in the config
         */
        if ($field_name === $_POST["search-field"] && in_array($field_type, $metadata["unstructured_field_types"]) && $config["search_fields"][$field_name]["wildcard"]) {
            $match_index = strpos(strtolower($field_value), strtolower($_POST["search-value"]));
            $match_value = substr($field_value, $match_index, strlen($_POST["search-value"]));
            if ($match_index !== false) {
                $field_value = str_replace($match_value, "<span class='orca-search-content'>{$match_value}</span>", $field_value);
            } else {
                // TODO some way to indicate to the user that the matching content is not on the latest instance of this value
            }
        }

        $record_info[$field_name]["value"] = $field_value;
    }
    // add record data to the full dataset
    $results[$record_id] = $record_info;
}

/*
 * Push all the results to Smarty templates for rendering
 */

if (false) { // TODO this will be replaced with an 'enable debugging' setting
    $debug["config"] = $config;
//    $debug["metadata"] = $metadata;
    if ((isset($debug) && !empty($debug))) {
        $module->setTemplateVariable("debug", print_r($debug, true));
    }
}

$module->setTemplateVariable("config", $config);

if (!empty($_POST)) {
    $module->setTemplateVariable("search_info", $_POST);
}

$module->setTemplateVariable("data", $results);
//A variable used to inject into DataTables to set the default sorting for the table based on configured fields
$module->setTemplateVariable("orca_search_field_sorting", json_encode($fieldSortingInfo));

echo "<link rel='stylesheet' type='text/css' href='" . $module->getUrl('css/orca_search.css') . "' />";

if (version_compare(REDCAP_VERSION, "8.7.0", ">=")) {
    $module->displayTemplate('bs4/orca_search.tpl');
} else {
    $module->displayTemplate('bs3/orca_search.tpl');
}

$module->addTime();
$module->outputTimerInfo();

require_once APP_PATH_DOCROOT . 'ProjectGeneral/footer.php';