<?php

namespace ORCA\OrcaSearch;

use Exception;

trait REDCapUtils {

    private $_dataDictionary = [];
    private $_metadata = [];
    private $timers = [];

    public function getAutoId() {
        if (version_compare(REDCAP_VERSION, "9.8.0", ">=")) {
            return \DataEntry::getAutoId();
        } else {
            require_once APP_PATH_DOCROOT . 'ProjectGeneral/form_renderer_functions.php';
            return getAutoId();
        }
    }

    public function getDataDictionary($format = 'array') {
        if (!array_key_exists($format, $this->_dataDictionary)) {
            $this->_dataDictionary[$format] = \REDCap::getDataDictionary($format);
        }
        $dictionaryToReturn = $this->_dataDictionary[$format];
        return $dictionaryToReturn;
    }

    public function getFieldValidationTypeFor($field_name) {
        $result = $this->getDataDictionary()[$field_name]['text_validation_type_or_show_slider_number'];
        if (empty($result)) {
            return null;
        }
        return $result;
    }

    public function getDictionaryLabelFor($key) {
        $label = $this->getDataDictionary()[$key]['field_label'];
        if (empty($label)) {
            return $key;
        }
        return $label;
    }

    public function getDictionaryValuesFor($key) {
        // TODO consider using $this->getChoiceLabels()
        return $this->flatten_type_values($this->getDataDictionary()[$key]['select_choices_or_calculations']);
    }

    /**
     * Returns a formatted date string if the provided date is valid, otherwise returns FALSE
     * @param mixed $date
     * @param string $format
     * @return false|string
     */
    public function getFormattedDateString($date, $format) {
        if (empty($format)) {
            return $date;
        } else if ($date instanceof \DateTime) {
            return date_format($date, $format);
        } else {
            if (!empty($date)) {
                $timestamp = strtotime($date);
                if ($timestamp !== false) {
                    return date($format, $timestamp);
                }
            }
        }
        return false;
    }

    public function comma_delim_to_key_value_array($value) {
        $arr = explode(', ', trim($value));
        $sliced = array_slice($arr, 1, count($arr) - 1, true);
        return array($arr[0] => implode(', ', $sliced));
    }

    public function array_flatten($array) {
        $return = array();
        foreach ($array as $key => $value) {
            if (is_array($value)) {
                $return = $return + $this->array_flatten($value);
            } else {
                $return[$key] = $value;
            }
        }
        return $return;
    }

    public function flatten_type_values($value) {
        $split = explode('|', $value);
        $mapped = array_map(function ($value) {
            return $this->comma_delim_to_key_value_array($value);
        }, $split);
        return $this->array_flatten($mapped);
    }

    public function getMyMetadata($project_id) {
        if (!isset($this->_metadata[$project_id])) {
            $Proj = new \Project($project_id);
            $metadata = [
                "forms" => $Proj->forms,
                "form_statuses" => [
                    0 => "Incomplete",
                    1 => "Unverified",
                    2 => "Complete"
                ],
                "date_field_formats" => [
                    "date_dmy" => "d/m/Y",
                    "date_mdy" => "m/d/Y",
                    "date_ymd" => "Y-m-d",
                    "datetime_dmy" => "d/m/Y H:i",
                    "datetime_mdy" => "m/d/Y H:i",
                    "datetime_ymd" => "Y-m-d H:i"
                ],
                "unstructured_field_types" => [
                    "text",
                    "textarea"
                ],
                "unsupported_field_types" => [
                    "file",
                    "slider",
                    "descriptive"
                ],
                "custom_values" => [
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

            /*
             * Build the Form/Field Metadata
             * This is necessary for knowing where to find record
             * values (i.e. repeating/non-repeating forms)
             */
            foreach ($Proj->forms as $form_name => $form_data) {
                foreach ($form_data["fields"]  as $field_name => $field_label) {
                    $field_type = $Proj->metadata[$field_name]["element_type"];
                    // ignore unsupported field types
                    if (in_array($field_type, $metadata["unsupported_field_types"])) continue;

                    $metadata["fields"][$field_name] = [
                        "form" => $form_name,
                        "type" => $field_type,
                        "validation" => $Proj->metadata[$field_name]["element_validation_type"]
                    ];
                    if ($Proj->isFormStatus($field_name)) {
                        // special value handling for form statuses
                        $metadata["fields"][$field_name]["label"] = $this->truncate($form_data["menu"] . " Status");
                        $metadata["fields"][$field_name]["values"] = $metadata["form_statuses"];
                    } else {
                        $metadata["fields"][$field_name]["label"] = $this->truncate($field_label);
                        switch ($field_type) {
                            case "select":
                            case "radio":
                            case "checkbox":
                            $metadata["fields"][$field_name]["values"] = $this->getDictionaryValuesFor($field_name);
                                break;
                            case "yesno":
                            case "truefalse":
                            $metadata["fields"][$field_name]["values"] = $metadata["custom_values"][$Proj->metadata[$field_name]["element_type"]];
                                break;
                            case "sql":
                                // this is deferred, in case a project has a lot of sql fields
                                break;
                            default: break;
                        }
                    }
                }
            }
            $event_map = \REDCap::getEventNames(true);
            foreach ($Proj->eventsForms as $event_id => $event_forms) {
                foreach ($event_forms as $form_index => $form_name) {
                    $metadata["forms"][$form_name]["events"][$event_id] = [
                        "unique_event_name" => $event_map[$event_id],
                        "repeating" => false
                    ];
                }
            }
            if ($Proj->hasRepeatingForms()) {
                foreach ($Proj->getRepeatingFormsEvents() as $event_id => $event_forms) {
                    foreach ($event_forms as $form_name => $value) {
                        $metadata["forms"][$form_name]["events"][$event_id]["repeating"] = true;
                    }
                }
            }
            $this->_metadata[$project_id] = $metadata;
        }
        return $this->_metadata[$project_id];
    }

    public function getSqlValuesFor($project_id, $field_name) {
        $Proj = new \Project($project_id);
        if ($Proj->metadata[$field_name]["element_type"] !== "sql") return [];
        if (!isset($this->getMyMetadata($project_id)["fields"][$field_name]["values"])) {
            $this->_metadata[$project_id]["fields"][$field_name]["values"] =
                parseEnum(getSqlFieldEnum($Proj->metadata[$field_name]['element_enum']));
        }
        return $this->_metadata[$project_id]["fields"][$field_name]["values"];
    }

    public function getFieldValue($project_id, $field_name, $raw_value) {
        $Proj = new \Project($project_id);
        $metadata = $this->getMyMetadata($project_id);

        $field_result = [
            "value" => $raw_value
        ];

        if (!isset($metadata["fields"][$field_name])) {
            return $field_result;
        }

        // initialize some helper variables/arrays
        $field_type = $Proj->metadata[$field_name]["element_type"];

        // set the raw value of the field
        $field_value = $raw_value;

        if ($field_name === $Proj->table_pk) {
            $parts = explode("-", $field_value);
            if (count($parts) > 1) {
                $field_result["sort"] = implode(".", [$parts[0], str_pad($parts[1], 10, "0", STR_PAD_LEFT)]);
            } else {
                $field_result["sort"] = $field_value;
            }
        }

        if ($Proj->isFormStatus($field_name)) {
            // special value handling for form statuses
            $field_value = $metadata["form_statuses"][$field_value];
        } else {
            switch ($field_type) {
                case "select":
                case "radio":
                    $field_value = $metadata["fields"][$field_name]["values"][$field_value];
                    break;
                case "checkbox":
                    $temp_field_array = [];
                    $field_value_dd = $metadata["fields"][$field_name]["values"];
                    foreach ($field_value as $field_value_key => $field_value_value) {
                        if ($field_value_value === "1") {
                            $temp_field_array[$field_value_key] = $field_value_dd[$field_value_key];
                        }
                    }
                    $field_value = $temp_field_array;
                    break;
                case "yesno":
                case "truefalse":
                    $field_value = $metadata["custom_values"][$field_type][$field_value];
                    break;
                case "sql":
                    $sql_values = $this->getSqlValuesFor($project_id, $field_name);
                    if (isset($sql_values[$field_value])) {
                        $field_value = $sql_values[$field_value];
                    } else if ($field_value !== null && $field_value != '') {
                        // we don't want to show the raw value if a match is not found
                        // TODO should we change this?
                        $field_value = "";
                    }
                    break;
                default: break;
            }
        }

        $element_validation_type = $Proj->metadata[$field_name]["element_validation_type"];
        // update field value if this is a known date format
        if (array_key_exists($element_validation_type, $metadata["date_field_formats"]) && !empty($field_value)) {
            // just use the raw yyyy-mm-dd format for sorting
            $field_result["sort"] = $field_value;
            $field_value = date_format(date_create($field_value), $metadata["date_field_formats"][$element_validation_type]);
        }
        $field_result["value"] = $field_value;

        return $field_result;
    }

    /**
     * Search the project for a value in a specific field.
     * @since 2.4.0
     * @param $project_id
     * @param $search_field string Field to search
     * @param $search_value string Value to search for
     * @param $search_mode string Use "wildcard" for wildcard matching.  Use "equals" for exact match.
     * @param $instance_mode string Use "LATEST" to check fields against just the latest instance, or "ALL" to check against all instances
     * @return array|false Returns false if no search value was provided.
     * @throws Exception
     */
    public function search($project_id, $search_field, $search_value, $search_mode = "equals", $instance_mode = "LATEST") {
        $valid_search_modes = [ "wildcard", "equals" ];
        $valid_instance_modes = [ "LATEST", "ALL" ];

        // exit if parameters are missing
        if ($search_field === null || $search_field === '') {
            throw new Exception("Search field required!");
        }
        if (!in_array($search_mode, $valid_search_modes)) {
            throw new Exception("Search mode value of '{$search_mode}' is invalid. Allowable values: " . implode(", ", $valid_search_modes));
        }
        if (!in_array($instance_mode, $valid_instance_modes)) {
            throw new Exception("Instance match value of '{$instance_mode}' is invalid. Allowable values: " . implode(", ", $valid_instance_modes));
        }
        // return FALSE if search value is empty
        if ($search_value === null || $search_value === '') {
            return false;
        }

        // initialize the project to validate context
        $proj = new \Project($project_id);

        // get the data_table context
        $data_table = method_exists('\REDCap', 'getDataTable') ? \REDCap::getDataTable($project_id) : "redcap_data";

        // execute the query
        $sql_result = $this->query("SELECT record, event_id, COALESCE(instance, 1) 'instance', field_name, value
FROM {$data_table}
WHERE project_id = ?
AND field_name = ?
ORDER BY CAST(record as UNSIGNED), event_id DESC, COALESCE(instance, 1) DESC
", [
            $project_id,
            $search_field
        ]);

        // load results to memory
        $records = [];
        while($row = db_fetch_assoc($sql_result)) {
            if (!isset($records[$row["record"]][$row["field_name"]])) {
                $records[$row["record"]][$row["field_name"]] = [];
            }
            $records[$row["record"]][$row["field_name"]][] = [
                "event_id" => $row["event_id"],
                "instance" => $row["instance"],
                "value" => $row["value"]
            ];
        }
        $sql_result->free_result();

        // main record loop
        $records_filtered = [];
        foreach ($records as $record_id => $record) {

            // default the values to search to be all instances found
            $arrayToSearch = $record[$search_field];
            // if it was specified to only search the most recent instance
            if ($instance_mode === "LATEST") {
                // set the search array to only be the first result (sorted DESC in sql)
                $arrayToSearch = [ reset($arrayToSearch) ];
            }

            // look for results of the search value in the record data
            foreach ($arrayToSearch as $search) {
                // ignore empty/missing values
                if ($search["value"] === '' || $search["value"] === null) continue;

                if ($search_mode === "wildcard") {
                    if (stripos($search["value"], $search_value) !== false) {
                        $records_filtered[$record_id] = $search;
                        break;
                    }
                } else {
                    if (strcasecmp($search["value"], $search_value) === 0) {
                        $records_filtered[$record_id] = $search;
                        break;
                    }
                }
            }
        }
        return $records_filtered;
    }

    public function getDateFormatFromREDCapValidationType($field_name) {
        $php_date_format = false;

        $validationType = $this->getFieldValidationTypeFor($field_name);
        switch ($validationType)
        {
            case 'time':
                $php_date_format = "H:i";
                break;
            case 'date':
            case 'date_ymd':
                $php_date_format = "Y-m-d";
                break;
            case 'date_mdy':
                $php_date_format = "m-d-Y";
                break;
            case 'date_dmy':
                $php_date_format = "d-m-Y";
                break;
            case 'datetime':
            case 'datetime_ymd':
                $php_date_format = "Y-m-d H:i";
                break;
            case 'datetime_mdy':
                $php_date_format = "m-d-Y H:i";
                break;
            case 'datetime_dmy':
                $php_date_format = "d-m-Y H:i";
                break;
            case 'datetime_seconds':
            case 'datetime_seconds_ymd':
                $php_date_format = "Y-m-d H:i:s";
                break;
            case 'datetime_seconds_mdy':
                $php_date_format = "m-d-Y H:i:s";
                break;
            case 'datetime_seconds_dmy':
                $php_date_format = "d-m-Y H:i:s";
                break;
            default:
                break;
        }
        return $php_date_format;
    }

    /**
     * Truncate text to a specified limit.  The ellipsis '...' length is factored into the limit.
     * @param $value mixed can be string or array. If array, all values will be truncated if needed
     * @param $limit int the total maximum length for the text
     * @return mixed the value after truncation
     */
    public function truncate($value, $limit = 60) {
        if (is_array($value)) {
            foreach ($value as $k => $v) {
                $value[$k] = $this->truncate($v);
            }
        } else {
            // account for rich text editor or multi-line labels
            $v = preg_split("/\r\n|\n|<br>|<br \/>/", strip_tags($value, '<br>'));
            $value = $v[0];
            if (strlen($value) > ($limit - 3)) {
                $value = substr($value, 0, ($limit - 3)) . "...";
            }
        }
        return $value;
    }

    public function preout($content) {
        if (is_array($content) || is_object($content)) {
            echo "<pre>" . print_r($content, true) . "</pre>";
        } else {
            echo "<pre>$content</pre>";
        }
    }

    public function addTime($key = null) {
        if ($key == null) {
            $key = "STEP " . count($this->timers);
        }
        $this->timers[] = [
            "label" => $key,
            "value" => microtime(true)
        ];
    }

    public function outputTimerInfo($showAll = false) {
        $initTime = null;
        $preTime = null;
        $curTime = null;
        foreach ($this->timers as $index => $timeInfo) {
            $curTime = $timeInfo;
            if ($preTime == null) {
                $initTime = $timeInfo;
            } else {
                $calcTime = round($curTime["value"] - $preTime["value"], 4);
                if ($showAll) {
                    echo "<p><i>{$timeInfo["label"]}: {$calcTime}</i></p>";
                }
            }
            $preTime = $curTime;
        }
        $calcTime = round($curTime["value"] - $initTime["value"], 4);
        echo "<p><i>Total Processing Time: {$calcTime} seconds</i></p>";
    }

    /**
     * Outputs the module directory folder name into the page footer, for easy reference.
     * @return void
     */
    public function outputModuleVersionJS() {
        $module_info = $this->getModuleName() . " (" . $this->VERSION . ")";
        echo "<script>$(function() { $('div#south table tr:first td:last, #footer').prepend('<span>$module_info</span>&nbsp;|&nbsp;'); });</script>";
    }

    /**
     * HTML Output for development project record limit being reached (adapted from core to remove max width)
     * @return mixed
     */
    public function outputMaxRecordCountErrorMsg($project_id)
    {
        $Proj = new \Project($project_id);
        $html = \RCView::div(['class'=>'alert alert-warning text-dangerrc fs14 p-2 mb-0'],
            '<i class="fa-solid fa-triangle-exclamation me-1"></i>'.
            \RCView::tt_i("system_config_947", [$Proj->getMaxRecordCount(), \RCView::tt('messaging_07', 'a', ['href'=>'mailto:'.$GLOBALS['project_contact_email']])], false)
        );
        return $html;
    }

    /**
     * Local copy from core - as of v17.1.4 - for backwards compatibility
     * Determine if current user has the specified data viewing rights
     * This is a direct mapping to the "Data Viewing Rights" table.
     * @param string|int $value
     * @param string $right One of: 'no-access', 'read-only', 'view-edit', 'editresp', 'delete'
     * @return bool
     */
    public function hasDataViewingRights($value, $right) {
        // When invalid value, return true for "no-access", else false
        if (!is_numeric($value) || $value === "") return $right == 'no-access';
        $value = intval($value);
        $grant = false;
        if ($value < 128) {
            // Legacy:
            // 0 = no-access, 1 = view-edit, 2 = read-only, 3 = editresp
            switch ($right) {
                case 'no-access':
                    $grant = ($value == 0);
                    break;
                case 'read-only':
                    $grant = ($value == 2);
                    break;
                case 'view-edit':
                    $grant = ($value == 1) || ($value == 3);
                    break;
                case 'editresp':
                    $grant = ($value == 3);
                    break;
                case 'delete':
                    $grant = false;
                    break;
            }
        }
        else {
            // New bitmask:
            // 128 = Marker for new bitmask
            // 1 = read-only, 2 = view-edit, 8 = editresp, 16 = delete
            switch($right) {
                case 'no-access':
                    $grant = ($value == 128);
                    break;
                case 'read-only':
                    $grant = ($value & 1) == 1;
                    break;
                case 'view-edit':
                    $grant = ($value & 2) == 2;
                    break;
                case 'editresp':
                    $grant = ($value & 2) == 2 && ($value & 8) == 8;
                    break;
                case 'delete':
                    $grant = ($value & 2) == 2 && ($value & 16) == 16;
                    break;
            }
        }
        return $grant;
    }
}
