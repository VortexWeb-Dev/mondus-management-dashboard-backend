<?php

error_reporting(E_ALL);
ini_set('display_errors', 1);

require_once __DIR__ . "/../services/CacheService.php";
require_once __DIR__ . "/../services/ResponseService.php";
require_once __DIR__ . "/BitrixController.php";

class DashboardController extends BitrixController
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

        $year = $_GET['year'] ?? date('Y');

        $cacheKey = "dashboard_" . date('Y-m-d') . $type . $year;
        $cached = $this->cache->get($cacheKey);

        if ($cached !== false && $this->config['cache']['enabled']) {
            $this->response->sendSuccess(200, $cached);
            return;
        }

        $deals = $type == 'mondus' ? $this->getDeals(
            ['>=DATE_CREATE' => $year . '-01-01', '<=DATE_CREATE' => $year . '-12-31', '!OPPORTUNITY' => null],
            [
                'ID',
                'CLOSED',
                'OPPORTUNITY', // Property Price
                'DATE_CREATE',
                'UF_CRM_67FF84E2C3A4A', // Deal Type
                'UF_CRM_67FF84E2B934F', // Developer Name (enum)
                'UF_CRM_67FF84E2B45F2', // Total Commission AED
                'UF_CRM_67FF84E2BE481', // Agent's Commission AED
            ],
            null,
            ['ID' => 'desc']
        ) : $this->getCFTLeads(
            ['>=createdTime' => $year . '-01-01', '<=createdTime' => $year . '-12-31', '!opportunity' => null],
            [
                'ID',
                'opened',
                'opportunity', // Property Price
                'createdTime',
                'ufCrm3_1744794200', // Deal Type
                'ufCrm3_1744794138', // Developer Name (enum)
                'ufCrm3_1744794099', // Total Commission AED
                'ufCrm3_1744794153', // Agent's Commission AED
            ],
            null,
            ['id' => 'desc']
        );

        $data = [
            // 'developer_stats' => $this->getDeveloperStats(array_filter($deals ?? [], fn($deal) => $deal['UF_CRM_67FF84E2B934F']), $year),
            // 'deal_type_distribution' => $this->getDealTypeDistribution(array_filter($deals ?? [], fn($deal) => $deal['UF_CRM_67FF84E2C3A4A']), $year),
            // 'developer_property_price_distribution' => $this->getDeveloperPropertyPriceDistribution(array_filter($deals ?? [], fn($deal) => $deal['UF_CRM_67FF84E2B934F']), $year),
            'developer_stats' => $this->getDeveloperStats($deals, $year, $type),
            'deal_type_distribution' => $this->getDealTypeDistribution($deals, $year, $type),
            'developer_property_price_distribution' => $this->getDeveloperPropertyPriceDistribution($deals, $year, $type),
        ];

        $this->cache->set($cacheKey, $data);
        $this->response->sendSuccess(200, $data);
    }

    private function getDeveloperStats(array $deals, int $year, string $type): array
    {

        $stats = [];

        foreach ($deals as $deal) {
            $createDate = $type == 'mondus' ? new DateTime($deal['DATE_CREATE']) : new DateTime($deal['createdTime']);
            $month = (int)$createDate->format('n');
            $developer = $type == 'mondus' ? ($deal['UF_CRM_67FF84E2B934F'] ?? 'Unknown') : ($deal['ufCrm3_1744794138'] ?? 'Unknown');

            if (!isset($stats[$month][$developer])) {
                $stats[$month][$developer] = [
                    'month' => $createDate->format('F'),
                    'developer' => $developer,
                    'closed_deals' => 0,
                    'property_price' => 0.0,
                    'total_commission' => 0.0,
                    'agent_commission' => 0.0,
                ];
            }

            if ($deal['CLOSED'] === 'Y') {
                $stats[$month][$developer]['closed_deals']++;
                $stats[$month][$developer]['property_price'] += $type == 'mondus' ? (float)$deal['OPPORTUNITY'] : (float)$deal['opportunity'];
                $stats[$month][$developer]['total_commission'] += $type == 'mondus' ? (float)$deal['UF_CRM_67FF84E2B45F2'] : (float)$deal['ufCrm3_1744794099'];
                $stats[$month][$developer]['agent_commission'] += $type == 'mondus' ? (float)$deal['UF_CRM_67FF84E2BE481'] : (float)$deal['ufCrm3_1744794153'];
            }
        }

        $result = [];
        foreach ($stats as $monthData) {
            foreach ($monthData as $devData) {
                $result[] = $devData;
            }
        }

        return $result;
    }

    private function getDealTypeDistribution(array $deals, int $year, string $type): array
    {
        $distribution = [
            'Off-Plan' => 0,
            'Secondary' => 0,
            'Rental' => 0,
            'Unknown' => 0,
        ];

        foreach ($deals as $deal) {
            if ($type === 'mondus') {
                $dealType = (int) ($deal['UF_CRM_67FF84E2C3A4A'] ?? 0);
                switch ($dealType) {
                    case 4694:
                        $distribution['Off-Plan']++;
                        break;
                    case 4695:
                        $distribution['Secondary']++;
                        break;
                    case 4696:
                        $distribution['Rental']++;
                        break;
                    default:
                        $distribution['Unknown']++;
                        break;
                }
            } else {
                $dealTypeStr = strtolower(trim($deal['ufCrm3_1744794200'] ?? ''));
                switch ($dealTypeStr) {
                    case 'off-plan':
                        $distribution['Off-Plan']++;
                        break;
                    case 'secondary':
                        $distribution['Secondary']++;
                        break;
                    case 'rental':
                        $distribution['Rental']++;
                        break;
                    default:
                        $distribution['Unknown']++;
                        break;
                }
            }
        }

        return $distribution;
    }

    private function getDeveloperPropertyPriceDistribution(array $deals, int $year, string $type): array
    {
        $totals = [];
        $overallPropertyPrice = 0;

        foreach ($deals as $deal) {
            $developer = $type == 'mondus' ? ($deal['UF_CRM_67FF84E2B934F'] ?? 'Unknown') : ($deal['ufCrm3_1744794138'] ?? 'Unknown');
            $price = $type == 'mondus' ? (float)$deal['OPPORTUNITY'] : (float)$deal['opportunity'];

            if (!isset($totals[$developer])) {
                $totals[$developer] = 0;
            }

            $totals[$developer] += $price;
            $overallPropertyPrice += $price;
        }

        $result = [];
        foreach ($totals as $developer => $amount) {
            $percentage = $overallPropertyPrice > 0 ? round(($amount / $overallPropertyPrice) * 100, 2) : 0;
            $result[] = [
                'developer' => $developer,
                'property_price' => $amount,
                'percentage' => $percentage,
            ];
        }

        return $result;
    }
}
