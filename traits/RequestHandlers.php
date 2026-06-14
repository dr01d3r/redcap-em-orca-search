<?php
/** @var \ORCA\OrcaSearch\OrcaSearch $this */
namespace ORCA\OrcaSearch;

use Exception;

trait RequestHandlers {

    function redcap_module_ajax($action, $payload, $project_id) {
        $response = [];
        try {
            switch ($action) {
                case "initialize-config-dashboard":
                    $response = $this->handleInitializeConfigDashboard($project_id);
                    break;
                case "initialize-search-dashboard":
                    $response = $this->handleInitializeSearchDashboard($project_id);
                    break;
                case "search":
                    $response = $this->handleSearch($project_id, $payload);
                    break;
                case "save-module-config":
                    $response = $this->handleSaveModuleConfig($project_id, $payload);
                    break;
                default:
                    $response["errors"][] = "Unable to process request. Action '$action' is invalid.";
                    break;
            }
        } catch (Exception $ex) {
            $response["errors"][] = $ex->getMessage();
        }
        return $response;
    }

    /**
     * Echoes successful JSON response
     *
     * @param mixed $response
     * @return void
     * @since 3.0.0
     */
    public function sendResponse($response) : void
    {
        header('Content-Type: application/json; charset=UTF-8');
        exit(json_encode($response));
    }

    /**
     * Echoes error JSON response
     *
     * @param mixed $error Optional error details.  Will be JSON encoded.
     * @return void
     * @since 3.0.0
     */
    public function sendError($error = "") : void
    {
        header('Content-Type: application/json; charset=UTF-8');
        http_response_code(400);
        exit(json_encode($this->escape($error)));
    }

    /**
     * Unauthorized error
     *
     * @param mixed $error Optional error details.  Will be JSON encoded.
     * @return void
     * @since 3.0.0
     */
    public function sendUnauthorized($error = "") : void
    {
        header('Content-Type: application/json; charset=UTF-8');
        http_response_code(401);
        exit(json_encode($this->escape($error)));
    }
}