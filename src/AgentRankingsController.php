<?php

error_reporting(E_ALL);
ini_set('display_errors', 1);

require_once __DIR__ . "/../services/CacheService.php";
require_once __DIR__ . "/../services/ResponseService.php";
require_once __DIR__ . "/BitrixController.php";

class AgentRankingsController extends BitrixController
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

        $type = $_GET['type'] ?? 'mondus';

        $cacheKey = "agent_rankings_" . date('Y-m-d') . $type;
        $cached = $this->cache->get($cacheKey);

        if ($cached !== false  && $this->config['cache']['enabled']) {
            $this->response->sendSuccess(200, $cached);
            return;
        }

        $salesDeptIds = $type == 'mondus' ? $this->config['SALES_DEPARTMENT_IDS'] : $this->config['CFT_DEPARTMENT_IDS'];
        $salesEmployees = $this->getAllUsers(['UF_DEPARTMENT' => $salesDeptIds], ['ID', 'NAME', 'LAST_NAME', 'WORK_POSITION', 'UF_DEPARTMENT']);

        $employeesById = [];
        foreach ($salesEmployees as $employee) {
            $employeesById[$employee['ID']] = ($employee['NAME'] ?? '') . ' ' . ($employee['LAST_NAME'] ?? '');
        }

        $currentYear = date('Y');
        $deals = $type == 'mondus' ? $this->getDeals([
            '@ASSIGNED_BY_ID' => array_keys($employeesById),
            '!UF_CRM_67FF84E2B45F2' => null,
            'CLOSED' => 'Y',
            '>=DATE_CREATE' => $currentYear . '-01-01',
            '<=DATE_CREATE' => $currentYear . '-12-31',
        ], [
            'ID',
            'ASSIGNED_BY_ID',
            'UF_CRM_67FF84E2B45F2',
            'DATE_CREATE'
        ], null, ['DATE_CREATE' => 'DESC'])
            : $this->getCFTLeads([
                '@assignedById' => array_keys($employeesById),
                '!ufCrm3_1744794099' => null,
                '!opened' => 'Y',
                '>=createdTime' => $currentYear . '-01-01',
                '<=createdTime' => $currentYear . '-12-31',
            ], [
                'ID',
                'assignedById',
                'ufCrm3_1744794099',
                'createdTime'
            ], null, ['createdTime' => 'DESC']);

        if (empty($deals)) {
            $this->response->sendSuccess(200, []);
            return;
        }

        $monthNames = [
            '01' => 'jan',
            '02' => 'feb',
            '03' => 'mar',
            '04' => 'apr',
            '05' => 'may',
            '06' => 'jun',
            '07' => 'jul',
            '08' => 'aug',
            '09' => 'sep',
            '10' => 'oct',
            '11' => 'nov',
            '12' => 'dec'
        ];

        $currentMonth = date('m');

        $monthlyAgentCommissions = [];
        foreach ($monthNames as $monthNum => $monthName) {
            if ($monthNum > $currentMonth) {
                continue;
            }
            $monthlyAgentCommissions[$monthName] = [];
        }

        foreach ($deals as $deal) {
            $agentId = $type == 'mondus' ? $deal['ASSIGNED_BY_ID'] : $deal['assignedById'];
            $commission = $type == 'mondus' ? (float)($deal['UF_CRM_67FF84E2B45F2'] ?? 0) : (float)($deal['ufCrm3_1744794099'] ?? 0);
            $dealDate = $type == 'mondus' ? new DateTime($deal['DATE_CREATE']) : new DateTime($deal['createdTime']);
            $month = $dealDate->format('m');
            $monthName = $monthNames[$month];

            if ($month > $currentMonth) {
                continue;
            }

            if (!isset($monthlyAgentCommissions[$monthName][$agentId])) {
                $monthlyAgentCommissions[$monthName][$agentId] = [
                    'agent' => $employeesById[$agentId] ?? 'Unknown Agent',
                    'gross_commission' => 0
                ];
            }
            $monthlyAgentCommissions[$monthName][$agentId]['gross_commission'] += $commission;
        }

        $data = [];
        foreach ($monthlyAgentCommissions as $month => $agents) {
            uasort($agents, function ($a, $b) {
                return $b['gross_commission'] <=> $a['gross_commission'];
            });

            $rank = 1;
            $data[$month] = [];
            foreach ($agents as $agentId => $agentData) {
                if ($rank > 5) break;
                $data[$month][$rank] = $agentData;
                $rank++;
            }
        }

        $this->cache->set($cacheKey, $data);
        $this->response->sendSuccess(200, $data);
    }
}
