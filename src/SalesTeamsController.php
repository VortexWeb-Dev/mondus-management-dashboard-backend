<?php

error_reporting(E_ALL);
ini_set('display_errors', 1);

require_once __DIR__ . "/../services/CacheService.php";
require_once __DIR__ . "/../services/ResponseService.php";
require_once __DIR__ . "/BitrixController.php";

class SalesTeamsController extends BitrixController
{
    private CacheService $cache;
    private ResponseService $response;
    private array $config;

    public function __construct()
    {
        parent::__construct();
        $this->config = require __DIR__ . '/../config/config.php';
        $this->cache = new CacheService($this->config['cache']['expiry']);
        $this->response = new ResponseService();
    }

    public function processRequest(string $method): void
    {
        if ($method !== 'GET') {
            $this->response->sendError(405, "Method Not Allowed");
            return;
        }

        $cacheKey = "sales_teams_" . date('Y-m-d');
        $cached = $this->cache->get($cacheKey);

        if ($cached !== false && $this->config['cache']['enabled']) {
            $this->response->sendSuccess(200, $cached);
            return;
        }

        $salesDeptIds = $this->config['SALES_DEPARTMENT_IDS'];
        $salesDepartments = $this->getAllDepartments($salesDeptIds, ['ID', 'NAME', 'UF_HEAD']);
        $salesEmployees = $this->getAllUsers(['UF_DEPARTMENT' => $salesDeptIds], ['ID', 'NAME', 'LAST_NAME', 'WORK_POSITION', 'UF_DEPARTMENT']);

        $data = [];

        foreach ($salesDepartments as $department) {
            if (!in_array($department['ID'], $salesDeptIds)) {
                continue;
            }

            $teamName = $department['NAME'];
            $headId = $department['UF_HEAD'] ?? null;
            $members = [];

            foreach ($salesEmployees as $employee) {
                if (!in_array($department['ID'], $employee['UF_DEPARTMENT'])) {
                    continue;
                }

                $fullName = trim(($employee['NAME'] ?? '') . ' ' . ($employee['LAST_NAME'] ?? ''));
                $position = $employee['ID'] == $headId ? null : ($employee['WORK_POSITION'] ?? null);

                $members[] = [
                    'name' => $fullName,
                    'position' => $position,
                ];
            }

            $headName = null;
            foreach ($members as $member) {
                if ($member['position'] === null) {
                    $headName = $member['name'];
                    break;
                }
            }

            $data[] = [
                'teamName' => $teamName,
                'head' => $headName,
                'members' => $members
            ];
        }

        $this->cache->set($cacheKey, $data);
        $this->response->sendSuccess(200, $data);
    }
}
