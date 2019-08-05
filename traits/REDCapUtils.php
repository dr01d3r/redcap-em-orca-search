<?php

namespace ORCA\OrcaSearch;

use Exception;

trait REDCapUtils {

    private $_dataDictionary = [];
    private $timers = [];

    // TODO REDCap date validation type mapping

    private static $_REDCapConn;

    protected static function _getREDCapConn() {
        if (empty(self::$_REDCapConn)) {
            global $conn;
            self::$_REDCapConn = $conn;
        }
        return self::$_REDCapConn;
    }


    /**
     * Pulled from AbstractExternalModule
     * For broad REDCap version compatibility
     * @return string|null
     */
    public function getPID() {
        $pid = @$_GET['pid'];

        // Require only digits to prevent sql injection.
        if (ctype_digit($pid)) {
            return $pid;
        } else {
            return null;
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
     * NOTE: Passing in at least one fieldValue or orderBy will significantly improve performance
     *
     * NOTE: Avoiding the use of the % wildcard will significantly improve performance
     *
     * NOTE: Boolean true for a field value will require only that the field exists for the record
     *
     * "record_ids" will contain the record_ids for the given parameters
     *
     * @param  array $searchValues - An array of field_name to values; if they match (see $instanceToMatch), the record is returned
     * @param  string $instanceToMatch - "LATEST" to check fields against just the latest instance, or "ALL" to check against all instances
     *
     * @return array - An array of record ids, or [{associated information elements}, "records_ids" => [1,2,3]] if requested
     *
     * @throws Exception
     */
    public function getProjectRecordIds($searchValues = null, $instanceToMatch = "LATEST") {
        $validInstanceToMatchTypes = [ "LATEST", "ALL" ];

        if (!in_array($instanceToMatch, $validInstanceToMatchTypes)) {
            throw new Exception(sprintf("PARAMETER_OF_TYPE_INVALID", "\$instanceToMatch", $instanceToMatch));
        }

        $project_id = $this->getPID();
        $fieldSearchTypes = [];

        foreach ($searchValues as $field => $value) {
            $fieldSearchTypes[$field] = "equals";
            if (strpos($value, "%") !== false) {
                $fieldSearchTypes[$field] = "strpos";
                $searchValues[$field] = $this->_getREDCapConn()->real_escape_string(rtrim($value, "%"));
            }
        }

        if (!empty($searchValues)) {
            $sqlFieldNameInclusion = "AND field_name IN ( '" . implode("', '", array_keys($searchValues)) . "' )";
        }

        $primarySql = "
SELECT record, event_id, field_name, value, instance
FROM redcap_data
WHERE project_id = $project_id
$sqlFieldNameInclusion
ORDER BY record, event_id DESC, instance DESC
";

        $primaryResult = $this->_getREDCapConn()->query($primarySql);
        $allRecords = [];
        while ($row = $primaryResult->fetch_assoc()) {
            $instance = 1;
            if (!is_null($row["instance"])) {
                $instance = $row["instance"];
            }
            if (!array_key_exists($row["field_name"], $allRecords[$row["record"]])) {
                $allRecords[$row["record"]][$row["field_name"]] = [];
            }
            $allRecords[$row["record"]][$row["field_name"]][] = [
                "event_id" => $row["event_id"],
                "instance" => $instance,
                "value" => $row["value"]
            ];
        }
        $primaryResult->free_result();

        $filteredRecords = [];
        foreach ($allRecords as $recordId => $record) {
            // initialize the search results to false
            $matchResults = array_fill_keys(array_keys($searchValues), false);

            foreach ($searchValues as $searchField => $searchValue) {
                // consider it a match if the search value is 'empty'
                if ($searchValue === '' || $searchValue === null) {
                    $matchResults[$searchField]["match"] = true;
                    // TODO move out of the loop when multiple search field support is added
                    $filteredRecords[$recordId] = true;
                    continue;
                }

                // default the values to search to be all instances found
                $arrayToSearch = $record[$searchField];
                // if it was specified to only search the most recent instance
                if ($instanceToMatch === "LATEST") {
                    // set the search array to only be the first result (sorted descending in sql)
                    $arrayToSearch = [ reset($arrayToSearch) ];
                }

                // look for results of the search value in the record data
                foreach ($arrayToSearch as $search) {
                    // ignore empty/missing values
                    if ($search["value"] === '' || $search["value"] === null) continue;

                    // wildcard and exact match
                    if ($fieldSearchTypes[$searchField] === "strpos") {
                        if (stripos($search["value"], $searchValue) !== false) {
//                            $this->preout("WILDCARD MATCH $instanceToMatch -> record: $recordId | event: {$search["event_id"]} | instance: {$search["instance"]} | field: $searchField | value: {$search["value"]}");
                            $matchResults[$searchField]["match"] = true;
                            // TODO move out of the loop when multiple search field support is added
                            $filteredRecords[$recordId] = true;
                            break;
                        }
                    } else {
                        if (strcasecmp($search["value"], $searchValue) === 0) {
//                            $this->preout("EXACT MATCH $instanceToMatch -> record: $recordId | event: {$search["event_id"]} | instance: {$search["instance"]} | field: $searchField | value: {$search["value"]}");
                            $matchResults[$searchField]["match"] = true;
                            // TODO move out of the loop when multiple search field support is added
                            $filteredRecords[$recordId] = true;
                            break;
                        }
                    }
                }
            }
        }
        return array_keys($filteredRecords);
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
}