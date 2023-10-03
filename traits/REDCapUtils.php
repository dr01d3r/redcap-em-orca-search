<?php

namespace ORCA\OrcaSearch;

use Exception;

trait REDCapUtils {

    private $_dataDictionary = [];
    private $timers = [];

    // TODO REDCap date validation type mapping

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
        $result = $this->array_flatten($mapped);
        return $result;
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
}
