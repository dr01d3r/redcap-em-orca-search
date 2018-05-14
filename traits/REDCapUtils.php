<?php

namespace ORCA\OrcaSearch;

trait REDCapUtils {

    private $_pid = 0;
    private $_dataDictionary = [];

    private static $_REDCapConn;

    protected static function _getREDCapConn() {
        if(empty(self::$_REDCapConn)){
            global $conn;
            self::$_REDCapConn = $conn;
        }
        return self::$_REDCapConn;
    }

    public function getPid() {
        if(empty($this->_pid) && array_key_exists("pid", $_GET)){
            $this->_pid = (int)$_GET["pid"];
        }
        return $this->_pid;
    }

    public function getDataDictionary($format = 'array') {
        if(!array_key_exists($format, $this->_dataDictionary)){
            $this->_dataDictionary[$format] = \REDCap::getDataDictionary($format);
        }
        $dictionaryToReturn = $this->_dataDictionary[$format];
        return $dictionaryToReturn;
    }

    public function getDictionaryLabelFor($key) {
        $label = $this->getDataDictionary("array")[$key]['field_label'];
        if (empty($label)) {
            return $key;
        }
        return $label;
    }

    public function getDictionaryValuesFor($key) {
        // TODO consider using $this->getChoiceLabels()
        return $this->flatten_type_values($this->getDataDictionary()[$key]['select_choices_or_calculations']);
    }

    public function comma_delim_to_key_value_array($value) {
        $arr = explode(', ', trim($value));
        $sliced = array_slice($arr, 1, count($arr)-1, true);
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
        $mapped = array_map(function ($value) { return $this->comma_delim_to_key_value_array($value); }, $split);
        $result = $this->array_flatten($mapped);
        return $result;
    }

	/**
	 * NOTE: Passing in at least one fieldValue or orderBy will significantly improve performance
	 *
	 * NOTE: Avoiding the use of the % wildcard will significantly improve performance
	 *
	 * NOTE: An empty (falsey) value for a field ($fieldValues) will always match
	 * NOTE: Boolean true for a field value will require only that the field exists for the record
	 *
	 * "record_ids" will contain the record_ids for the given parameters
	 * "total_records" will contain how many records matched the given criteria, regardless of how many are returned
	 * "all_records" will contain the number of records in the current project
	 *
	 * @param  array  $fieldValues   - An array of field_name to values; if they match (see $fieldValuesMatchType), the record is returned
	 * @param  string $fieldValuesMatchType - How the fieldValues need to match a given record to make it a valid record to return
	 * @param  string $instanceToMatch - "LATEST" to check fields against just the latest instance, or "ALL" to check against all instances
	 *
	 * @return array - An array of record ids, or [{associated information elements}, "records_ids" => [1,2,3]] if requested
	 *
	 * @throws \Exception
	 */
	public function getProjectRecordIds($fieldValues = null, $fieldValuesMatchType = "ALL", $instanceToMatch = "LATEST") {
		$validFieldValueMatchTypes = ["ALL", "ANY"];
		$validInstanceToMatchTypes = ["LATEST", "ALL"];
		if(!in_array($fieldValuesMatchType, $validFieldValueMatchTypes)){
			throw new \Exception(sprintf("PARAMETER_OF_TYPE_INVALID", "\$fieldValuesMatchType", $fieldValuesMatchType));
		}
		if(!in_array($instanceToMatch, $validInstanceToMatchTypes)){
			throw new \Exception(sprintf("PARAMETER_OF_TYPE_INVALID", "\$instanceToMatchTypes", $instanceToMatch));
		}

		$fieldValuesToStrPosOrEquals = [];

		foreach($fieldValues as $field => $value){
			$fieldValuesToStrPosOrEquals[$field] = "equals";
			if(strpos($value, "%") !== false){
				$fieldValuesToStrPosOrEquals[$field] = "strpos";
				$fieldValues[$field] = $this->_getREDCapConn()->real_escape_string(rtrim($value, "%"));
			}
		}

		$allFieldNamesNeeded = [];
		if(!empty($fieldValues)){
			$allFieldNamesNeeded = array_merge($allFieldNamesNeeded, array_keys($fieldValues));
		}

		$allFieldNamesInString = " AND field_name IN('" . implode("', '", $allFieldNamesNeeded) . "')";

		$primarySql = "SELECT record, field_name, value, instance FROM redcap_data WHERE project_id = " . $this->getPid() . $allFieldNamesInString;
		$primaryResult = $this->_getREDCapConn()->query($primarySql);
		$allRecords = [];
		while($row = $primaryResult->fetch_assoc()){
			$instance = 1;
			if(!is_null($row["instance"])){
				$instance = $row["instance"];
			}
			$allRecords[$row["record"]]["instances"][$instance][$row["field_name"]] = $row["value"];
		}
		$primaryResult->free_result();

		$filteredRecords = [];
		foreach($allRecords as $recordId => $record){
			if($instanceToMatch === "LATEST"){
				krsort($record["instances"]);
				$latestData = array_shift($record["instances"]);
				// Get the latest value for each field
				while($instance = array_shift($record["instances"])){
					$latestData += $instance;
				}
				if($this->_checkRecordInclusionForGetProjectRecordIds($latestData, $fieldValues, $fieldValuesMatchType, $fieldValuesToStrPosOrEquals)){
					$filteredRecords[$recordId] = $latestData;
				}
			}elseif($instanceToMatch === "ALL"){
				foreach($record["instances"] as $instanceId => $instanceInfo){
					if($this->_checkRecordInclusionForGetProjectRecordIds($instanceInfo, $fieldValues, $fieldValuesMatchType, $fieldValuesToStrPosOrEquals)){
						$filteredRecords[$recordId] = $instanceInfo;
					}
				}
			}
		}
		return array_keys($filteredRecords);
	}

    private function _checkRecordInclusionForGetProjectRecordIds($recordData, $fieldValues, $fieldValuesMatchType, $fieldValuesToStrPosOrEquals) {
        $allFieldsMatched = true;
        $anyFieldsMatched = false;
        if(is_null($fieldValues)){
            $anyFieldsMatched = true;
        }
        foreach($fieldValues as $field => $value){
            if($fieldValuesToStrPosOrEquals[$field] === "strpos"){
                if($fieldValuesMatchType === "ALL"){
                    if(!empty($value) && !($value === true && array_key_exists($field, $recordData)) && stripos($recordData[$field], $value) === false){
                        $allFieldsMatched = false;
                    }
                }else{
                    if((empty($value) || ($value === true && array_key_exists($field, $recordData)) || stripos($recordData[$field], $value) === 0)){
                        $anyFieldsMatched = true;
                    }
                }
            }else{
                if($fieldValuesMatchType === "ALL"){
                    if(!empty($value) && !($value === true && array_key_exists($field, $recordData)) && $recordData[$field] !== $value){
                        $allFieldsMatched = false;
                        break;
                    }
                }else if(empty($value) || ($value === true && array_key_exists($field, $recordData)) || strpos($recordData[$field], $value) === 0){
                    $anyFieldsMatched = true;
                }
            }
        }

        $foundRecord = false;
        if(($fieldValuesMatchType === "ALL" && $allFieldsMatched === true) || ($fieldValuesMatchType === "ANY" && $anyFieldsMatched === true)){
            $foundRecord = true;
        }
        return $foundRecord;
    }

    public function preout($content) {
	    if (is_array($content) || is_object($content)) {
	        echo "<pre>" . print_r($content, true) . "</pre>";
        } else {
            echo "<pre>$content</pre>";
        }
    }
}