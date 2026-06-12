<?php
/** @var \ORCA\OrcaSearch\OrcaSearch $this */

namespace ORCA\OrcaSearch;

use Exception;

trait ConfigUtils {

    public function handleInitializeConfigDashboard($project_id) {
        return $this->getConfigForConfig($project_id);
    }

    public function getConfigForConfig($project_id) {
        $Proj = new \Project($project_id);
        $response = [
            "config" => [
                "replace_add_edit" => $this->getProjectSetting("replace_add_edit")  ?? false,
                "include_dag_if_exists" => $this->getProjectSetting("include_dag_if_exists") ?? false,
                "empty_search_disabled" => $this->getProjectSetting("empty_search_disabled") ?? false,
                "search_limit" => intval($this->getProjectSetting("search_limit")),
                "instance_search" => $this->getProjectSetting("instance_search") ?? "LATEST",
                "record_home_display" => $this->getProjectSetting("record_home_display") ?? "last",
                "display_context_enabled" => $this->getProjectSetting("display_context_enabled") ?? false,
                "has_repeating_forms" => $Proj->hasRepeatingForms(),
            ],
            "search_fields" => [],
            "display_fields" => []
        ];

        foreach ($this->getSubSettings("search_fields") as $search_field) {
            if (empty($search_field["search_field_name"])) continue;
            $field_name = $search_field["search_field_name"];
            $field = [
                "name" => $field_name,
                "label" => $search_field["search_field_label"]
            ];
            if ($Proj->isFormStatus($field_name)) {
                $field["wildcard"] = false;
            } else {
                // override wildcard config in certain cases; otherwise, take what the user specified
                $field["wildcard"] = match ($Proj->metadata[$field_name]["element_type"]) {
                    "select", "radio", "sql", "checkbox" => false,
                    default => $search_field["search_field_name_wildcard"],
                };
            }
            $response["search_fields"][] = $field;
        }

        foreach ($this->getSubSettings("display_fields") as $display_field) {
            if (empty($display_field["display_field_name"])) continue;
            $field_name = $display_field["display_field_name"];
            $response["display_fields"][] = [
                "name" => $field_name,
                "sort" => $display_field["display_field_sort_on_field"],
                "header" => $display_field["display_field_header"],
                "direction" => $display_field["display_field_sort_direction"],
                "priority" => $display_field["display_field_sort_priority"],
            ];
        }

        // get metadata last
        $response["metadata"] = $this->getMyMetadata($project_id);

        return $response;
    }

    function handleSaveModuleConfig($project_id, $payload): array
    {
        $response = [
            "errors" => []
        ];
        try {
            $x = [
                "replace_add_edit" => $payload["replace_add_edit"],
                "search_limit" => $payload["search_limit"],
                "include_dag_if_exists" => $payload["include_dag_if_exists"],
                "empty_search_disabled" => $payload["empty_search_disabled"],
                "instance_search" => $payload["instance_search"],
                "record_home_display" => $payload["record_home_display"],
                "display_context_enabled" => $payload["display_context_enabled"],
            ];
            // search_fields sub settings
            if (!empty($payload["search_fields"])) {
                $x["search_fields"] = array_fill(0, count($payload["search_fields"]), "true");
                $x["search_field_name"] = [];
                $x["search_field_name_wildcard"] = [];
                foreach ($payload["search_fields"] as $v) {
                    $x["search_field_name"][] = $v["name"];
                    $x["search_field_label"][] = trim($v["label"]) == '' ? null : trim($v["label"]);
                    $x["search_field_name_wildcard"][] = $v["wildcard"];
                }
            }
            // display_fields sub settings
            if (!empty($payload["display_fields"])) {
                $x["display_fields"] = array_fill(0, count($payload["display_fields"]), "true");
                $x["display_field_name"] = [];
                $x["display_field_header"] = [];
                $x["display_field_sort_on_field"] = [];
                $x["display_field_sort_direction"] = [];
                $x["display_field_sort_priority"] = [];
                foreach ($payload["display_fields"] as $v) {
                    $x["display_field_name"][] = $v["name"];
                    $x["display_field_header"][] = trim($v["header"]) == '' ? null : trim($v["header"]);
                    $x["display_field_sort_on_field"][] = $v["sort"];
                    $x["display_field_sort_direction"][] = $v["direction"];
                    $x["display_field_sort_priority"][] = $v["priority"];
                }
            }
            // save the entire payload
            $this->setProjectSettings($x, $project_id);
        } catch (Exception $ex) {
            $response["errors"][] = $ex->getMessage();
        }
        // return the response
        return $response;
    }
}