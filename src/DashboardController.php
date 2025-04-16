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

        $year = $_GET['year'] ?? date('Y');

        $cacheKey = "dashboard_" . date('Y-m-d');
        $cached = $this->cache->get($cacheKey);

        if ($cached !== false && $this->config['cache']['enabled']) {
            $this->response->sendSuccess(200, $cached);
            return;
        }

        $deals = $this->getDeals(
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
        );

        $data = [
            // 'developer_stats' => $this->getDeveloperStats(array_filter($deals ?? [], fn($deal) => $deal['UF_CRM_67FF84E2B934F']), $year),
            // 'deal_type_distribution' => $this->getDealTypeDistribution(array_filter($deals ?? [], fn($deal) => $deal['UF_CRM_67FF84E2C3A4A']), $year),
            // 'developer_property_price_distribution' => $this->getDeveloperPropertyPriceDistribution(array_filter($deals ?? [], fn($deal) => $deal['UF_CRM_67FF84E2B934F']), $year),
            'developer_stats' => $this->getDeveloperStats($deals, $year),
            'deal_type_distribution' => $this->getDealTypeDistribution($deals, $year),
            'developer_property_price_distribution' => $this->getDeveloperPropertyPriceDistribution($deals, $year),
        ];

        $this->cache->set($cacheKey, $data);
        $this->response->sendSuccess(200, $data);
    }

    private function getDeveloperStats(array $deals, int $year): array
    {
        $stats = [];

        foreach ($deals as $deal) {
            $createDate = new DateTime($deal['DATE_CREATE']);
            $month = (int)$createDate->format('n');
            $developer = $deal['UF_CRM_67FF84E2B934F'] ?? 'Unknown';

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
                $stats[$month][$developer]['property_price'] += (float)$deal['OPPORTUNITY'];
                $stats[$month][$developer]['total_commission'] += (float)$deal['UF_CRM_67FF84E2B45F2'];
                $stats[$month][$developer]['agent_commission'] += (float)$deal['UF_CRM_67FF84E2BE481'];
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

    private function getDealTypeDistribution(array $deals, int $year): array
    {
        $distribution = [
            'Off-Plan' => 0,
            'Secondary' => 0,
            'Rental' => 0,
            'Unknown' => 0,
        ];

        foreach ($deals as $deal) {
            $type = (int) $deal['UF_CRM_67FF84E2C3A4A'] ?? null;

            switch ($type) {
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
        }

        return $distribution;
    }

    private function getDeveloperPropertyPriceDistribution(array $deals, int $year): array
    {
        $totals = [];
        $overallPropertyPrice = 0;

        foreach ($deals as $deal) {
            $developer = $deal['UF_CRM_67FF84E2B934F'] ?? 'Unknown';
            $price = (float)$deal['OPPORTUNITY'];

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
