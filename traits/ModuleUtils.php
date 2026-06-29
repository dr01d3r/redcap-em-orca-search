<?php
/** @var \Project $Proj */
/** @var \ORCA\OrcaSearch\OrcaSearch $this */

namespace ORCA\OrcaSearch;

use Exception;

trait ModuleUtils {

    public function handleInitializeSearchDashboard($project_id) {
        return $this->getConfigForSearch($project_id);
    }

    /**
     * @param $project_id
     * @param $payload
     * @return mixed
     * @throws Exception
     */
    public function handleSearch($project_id, $payload) {
        // first make sure we're in project context
        $Proj = new \Project($project_id);
        // grab the module config for this project
        $config = $this->getConfigForSearch($Proj->project_id);
        // prevent empty search, if necessary
        if ($payload["value"] == "" && $config["empty_search_disabled"] === true) {
            throw new Exception("Empty search has been disabled.  Please provide a search value and try again.");
        }

        $metadata = $this->getMyMetadata($project_id);
        // initialize some local vars
        $record_ids = [];
        $record_count = null;

        // preload the search parameters into the response
        $response = $payload;

        // prep some search parameters
        $search_mode = $config["search_fields"][$payload["field"]]["wildcard"] ? "wildcard" : "equals";
        $instance_mode = $config["instance_search"];

        // do the search
        $search_results = $this->search($Proj->project_id, $payload["field"], $payload["value"], $search_mode, $instance_mode);

        // this will trigger a full data pull in the next step, so just grab the total record count in the project
        if ($search_results === false) {
            $record_count = \Records::getRecordCount($project_id);
        } else {
            $record_count = count($search_results);
            $record_ids = array_keys($search_results);
        }

        $data_raw = [];
        if ($record_count === 0) {
            $response["messages"][] = "Search yielded no results.";
        } else if ($record_count != null && !empty($config["search_limit"]) && $record_count > $config["search_limit"]) {
            $response["errors"][] = "Too many results found ($record_count).  Please be more specific (limit: {$config["search_limit"]}).";
        } else if ($record_count > 0) {
            $data_raw = \REDCap::getData([
                "project_id" => $project_id,
                "records" => $record_ids,
                "fields" => array_keys($config["display_fields"]),
                "groups" => $config["user_dag"],
                // explicitly set this false, because we'll get DAG info manually
                "exportDataAccessGroups" => false
            ]);
            if ($config["include_dag_if_exists"]) {
                // sifting through the getData results for DAG is too tedious.  manually grabbing is cleaner and probably a bit more efficient
                // Get all DAGs in the project
                $allDags = $Proj->getUniqueGroupNames();
                // Get all DAG values for the records
                $dags = [];
                $data_table = \Records::getDataTable($project_id);
                $dag_sql_result = $this->query("SELECT DISTINCT record, value FROM {$data_table} WHERE project_id = ? AND field_name = '__GROUPID__'", [ $project_id ]);
                while ($r = db_fetch_assoc($dag_sql_result)) {
                    $dags[$r["record"]] = $allDags[$r["value"]];
                }
            }
        }
        $data = [];
        foreach ($data_raw as $record_id => $record) {
            $row = [
                "__URL__" => APP_PATH_WEBROOT . "DataEntry/record_home.php?" . http_build_query([
                        "pid" => $Proj->project_id,
                        "id" => $record_id
                    ])
            ];
            foreach ($config["display_fields"] as $field_name => $field_info) {
                // fill in DAG if this is 'redcap_data_access_group'
                if ($field_name === "redcap_data_access_group" && $config["include_dag_if_exists"]) {
                    $row[$field_name] = [
                        "value" => $config["groups"][$dags[$record_id]],
                        "sort" => $config["groups"][$dags[$record_id]]
                    ];
                    continue;
                }
                if ($Proj->hasRepeatingForms() || $Proj->longitudinal) {
                    $values = [];
                    $form_name = $metadata["fields"][$field_name]["form"];
                    foreach ($metadata["forms"][$form_name]["events"] as $event_id => $ev) {
                        $scope = null;
                        if ($ev["unique_event_name"] !== null) {
                            $scope = sprintf("[%s]", $ev["unique_event_name"]);
                        }
                        if ($ev["repeating"] === true) {
                            foreach ($record["repeat_instances"][$event_id][$form_name] as $repeat_instance => $instance_info) {
                                $val = $this->getFieldValue($Proj->project_id, $field_name, $instance_info[$field_name]);
                                if (!($val["value"] == '' || (is_array($val["value"]) && empty($val["value"])))) {
                                    $val["scope"] = sprintf("[%s][%d]", ($ev["unique_event_name"] ?? $form_name), $repeat_instance);
                                }
                                $values[] = $val;
                            }
                        } else {
                            $val = $this->getFieldValue($Proj->project_id, $field_name, $record[$event_id][$field_name]);
                            if (!($val["value"] == '' || (is_array($val["value"]) && empty($val["value"])))) {
                                $val["scope"] = $scope;
                            }
                            $values[] = $val;
                        }
                    }
                    $vf = end($values);
                    $row[$field_name] = $vf;
                    $row[$field_name]['sort'] = $vf['sort'] ?? $vf['value'] ?? "";
                } else {
                    $row[$field_name] = $this->getFieldValue($Proj->project_id, $field_name, $record[$Proj->firstEventId][$field_name]);
                    $row[$field_name]['sort'] = $row[$field_name]['sort'] ?? $row[$field_name]['value'] ?? "";
                }
            }
            $data[] = $row;
        }
        $response["data"] = $data;

        return $response;
    }

    public function getConfigForSearch($project_id) {
        global $lang;
        $Proj = new \Project($project_id);
        $metadata = $this->getMyMetadata($project_id);
        // check for dev status and record count limit
        $dev_max_record_limit_reached = false;
        // backward compatibility check
        if (method_exists($Proj, 'reachedMaxRecordCount')) {
            $dev_max_record_limit_reached = $Proj->reachedMaxRecordCount(1);
        }
        if ($dev_max_record_limit_reached) {
            $dev_max_record_limit_reached_html = $this->outputMaxRecordCountErrorMsg($project_id);
        }
        // initialize search dashboard config
        $config = [
            "replace_add_edit" => $this->getProjectSetting("replace_add_edit")  ?? false,
            "include_dag_if_exists" => $this->getProjectSetting("include_dag_if_exists") ?? false,
            "empty_search_disabled" => $this->getProjectSetting("empty_search_disabled") ?? false,
            "search_limit" => intval($this->getProjectSetting("search_limit")),
            "instance_search" => $this->getProjectSetting("instance_search") ?? "LATEST",
            "record_home_display" => $this->getProjectSetting("record_home_display") ?? "last",
            "display_context_enabled" => $this->getProjectSetting("display_context_enabled") ?? false,
            "dev_max_record_limit_reached" => $dev_max_record_limit_reached,
            "dev_max_record_limit_reached_html" => $dev_max_record_limit_reached_html ?? null,
            "has_repeating_forms" => $Proj->hasRepeatingForms(),
            "auto_numbering" => $Proj->project["auto_inc_set"] === "1",
            "new_record_label" => $Proj->table_pk_label,
            "new_record_text" => $lang['data_entry_46'],
            "new_record_url" => APP_PATH_WEBROOT . "DataEntry/record_home.php?" . http_build_query([
                    "pid" => $Proj->project_id,
                    "auto" => "1"
                ]),
            "table_pk" => $Proj->table_pk,
            "user_dag" => null,
            "groups" => [],
            "search_fields" => [],
            "display_fields" => []
        ];

        // get the current user, so we can obtain their user rights
        $impersonatingUser = \UserRights::getUsernameImpersonating();
        $userid = empty($impersonatingUser) ? USERID : $impersonatingUser;

        // let's get the users rights for this project, including DAG
        $user_rights = \REDCap::getUserRights($userid)[$userid];
        if (!empty($user_rights["group_id"])) {
            $config["user_dag"] = \REDCAP::getGroupNames(true, $user_rights["group_id"]);
        }
        // check for 'read-only' or 'view-edit' rights on each form
        // these will be used later to determine search/display field visibility
        foreach ($Proj->forms as $f => $fi) {
            $config["rights"][$f] =
                ($this->hasDataViewingRights($user_rights["forms"][$f], 'view-edit') ?? false) ||
                ($this->hasDataViewingRights($user_rights["forms"][$f], 'read-only') ?? false) ||
                (empty($impersonatingUser) && SUPER_USER)
            ;
        }

        // build metadata for search_fields
        foreach ($this->getSubSettings("search_fields") as $search_field) {
            if (empty($search_field["search_field_name"])) continue;
            $field_name = $search_field["search_field_name"];
            $form_name = $metadata["fields"][$field_name]["form"];
            // skip if user doesn't have access to this form
            if (!$config["rights"][$form_name]) continue;
            $field_type = $Proj->metadata[$field_name]["element_type"];
            $field_validation = $Proj->metadata[$field_name]["element_validation_type"];
            // initialize the field config with normal metadata (i.e. label/values)
            $config["search_fields"][$field_name] = $metadata["fields"][$field_name] ?? [];
            if ($search_field["search_field_label"] !== null && $search_field["search_field_label"] != '') {
                $config["search_fields"][$field_name]["label"] = $this->truncate($search_field["search_field_label"]);
            }
            // include other config info
            if ($Proj->isFormStatus($field_name)) {
                $config["search_fields"][$field_name]["wildcard"] = false;
            } else {
                // override wildcard config in certain cases; otherwise, take what the user specified
                $config["search_fields"][$field_name]["wildcard"] = match ($field_type) {
                    "select", "radio", "sql", "checkbox" => false,
                    default => $search_field["search_field_name_wildcard"],
                };
                // lazy-loaded sql types
                if ($Proj->metadata[$field_name]["element_type"] === "sql") {
                    $config["search_fields"][$field_name]["values"] = $this->getSqlValuesFor($project_id, $field_name);
                }
            }
        }

        // build metadata for display_fields
        $display_sort = [];
        foreach ($this->getSubSettings("display_fields") as $display_field) {
            if (empty($display_field["display_field_name"])) continue;
            $field_name = $display_field["display_field_name"];
            $field_header = $display_field["display_field_header"] ?? $metadata["fields"][$field_name]["label"];
            $form_name = $metadata["fields"][$field_name]["form"];
            // skip if user doesn't have access to this form
            if (!$config["rights"][$form_name]  && $field_name !== $Proj->table_pk) continue;

            $config["display_fields"][$field_name] = [
                "type" => $Proj->metadata[$field_name]["element_type"],
                "validation" => $Proj->metadata[$field_name]["element_validation_type"],
                "label" => $this->truncate($field_header)
            ];
            // handle sorting config
            if ($display_field["display_field_sort_on_field"]) {

                $display_sort[($display_field["display_field_sort_priority"] ?? 0)."_".$field_name] = [
                    "field" => $field_name,
                    "order" => match ($display_field["display_field_sort_direction"]) {
                        "asc" => 1, "desc" => -1, default => 0
                    }
                ];
            }
        }
        if (!empty($display_sort)) {
            ksort($display_sort);
            $config["display_fields_sort"] = array_values($display_sort);
        }

        // data access groups
        if ($config["include_dag_if_exists"] === true && count($Proj->getGroups()) > 0) {
            $config["display_fields"]["redcap_data_access_group"] = [
                "label" => "Data Access Group"
            ];
            $config["groups"] = array_combine($Proj->getUniqueGroupNames(), $Proj->getGroups());
        }

        // tentative new record_id
        if ($config["auto_numbering"]) {
            $config["new_record_auto_id"] = $this->getAutoId();
        }

        return $config;
    }

    public function kv2o($arr, $truncate = false) {
        $arr = $arr ?? [];
        $mapped = array_map(fn($k, $v) => [ "key" => $k, "value" => $v ], array_keys($arr), array_values($arr));
        if ($truncate === true) {
            return $this->truncate($mapped);
        }
        return $mapped;
    }
}