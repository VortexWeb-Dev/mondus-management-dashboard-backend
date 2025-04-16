<?php

error_reporting(E_ALL);
ini_set('display_errors', 1);

require_once __DIR__ . "/../services/CacheService.php";
require_once __DIR__ . "/../services/ResponseService.php";
require_once __DIR__ . "/BitrixController.php";

class OverallDealsController extends BitrixController
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

        $cacheKey = "overall_deals_" . date('Y-m-d');
        $cached = $this->cache->get($cacheKey);

        if ($cached !== false && $this->config['cache']['enabled']) {
            $this->response->sendSuccess(200, $cached);
            return;
        }

        $select = [
            "ID",
            "TITLE",
            "STAGE_ID",
            "DATE_CREATE",
            "OPPORTUNITY",
            "ASSIGNED_BY_ID",
            "UF_CRM_67FF84E299077",
            "UF_CRM_67FF941757D73",
            "UF_CRM_67FF84E2D7CCA",
            "UF_CRM_67FF84E2DCC09",
            "UF_CRM_67FF84E2B934F",
            "UF_CRM_67FF84E2E1D1A",
            "UF_CRM_67FF84E2C8AB6",
            "UF_CRM_67FF84E2C3A4A",
            "UF_TEAM",
            "SOURCE_ID",
            "UF_CRM_67FF84E2BE481",
            "UF_CRM_67FF84E2B45F2",
            "UF_CRM_67FF84E2B45F2",
            "UF_CRM_67FF84E2ECBF3",
        ];

        $salesDeptIds = $this->config['SALES_DEPARTMENT_IDS'];
        $salesEmployees = $this->getAllUsers(['UF_DEPARTMENT' => $salesDeptIds], [
            'ID',
            'NAME',
            'LAST_NAME',
            'WORK_POSITION',
            'UF_DEPARTMENT',
            'UF_EMPLOYMENT_DATE'
        ]);
        $salesEmployeesIds = array_column($salesEmployees, 'ID');

        $deals = $this->getDeals([
            '@ASSIGNED_BY_ID' => $salesEmployeesIds,
            '!=UF_CRM_67FF84E2C8AB6' => null
        ], $select, 10, ['ID' => 'desc']);
        
        // Create a lookup array for employees to easily find by ID
        $employeesById = [];
        foreach ($salesEmployees as $employee) {
            $employeesById[$employee['ID']] = ($employee['NAME'] ?? '') . ' ' . ($employee['LAST_NAME'] ?? '');
        }

        $formatted = [];
        foreach ($deals as $deal) {
            // Get agent name from lookup array
            $agentName = $employeesById[$deal['ASSIGNED_BY_ID']] ?? '';

            // Handle property type which appears to be an array
            $propertyType = '';
            if (!empty($deal['UF_CRM_67FF84E2DCC09']) && is_array($deal['UF_CRM_67FF84E2DCC09'])) {
                $propertyType = $deal['UF_CRM_67FF84E2DCC09'][0];
            } elseif (!empty($deal['UF_CRM_67FF84E2DCC09']) && !is_array($deal['UF_CRM_67FF84E2DCC09'])) {
                $propertyType = $deal['UF_CRM_67FF84E2DCC09'];
            }

            $formatted[] = [
                'date' => date('Y-m-d', strtotime($deal['DATE_CREATE'])),
                'dealType' => $deal['UF_CRM_67FF84E2C3A4A'] ?? '',
                'projectName' => $deal['UF_CRM_67FF84E2C8AB6'] ?? '',
                'unitNo' => $deal['UF_CRM_67FF941757D73'] ?? '',
                'developerName' => $deal['UF_CRM_67FF84E2B934F'] ?? '',
                'propertyType' => $propertyType,
                'noOfBr' => (int)($deal['UF_CRM_67FF84E2E1D1A'] ?? 0),
                'clientName' => $deal['UF_CRM_67FF84E299077'] ?? '',
                'agentName' => $agentName,
                'propertyPrice' => (float)($deal['UF_CRM_67FF84E2D7CCA'] ?? 0),
                'grossCommissionInclVAT' => (float)($deal['UF_CRM_67FF84E2B45F2'] ?? 0),
                'grossCommission' => (float)($deal['UF_CRM_67FF84E2B45F2'] ?? 0),
                'vat' => (float)($deal['UF_CRM_67FF84E2ECBF3'] ?? 0),
                'agentCommission' => (float)($deal['UF_CRM_67FF84E2BE481'] ?? 0),
                'leadSource' => $this->mapSourceId($deal['SOURCE_ID']) ?? '',
            ];
        }

        $this->cache->set($cacheKey, $formatted);
        $this->response->sendSuccess(200, $formatted);
    }

    private function mapSourceId($id = 0)
    {
        $sources = [
            "UC_BL71II" => "SMS Marketing",
            "UC_BUT1P2" => "Emailer",
            "UC_M5MP33" => "Snapchat/Whatsapp",
            "UC_PLY23S" => "Property Finder",
            "UC_LTULS9" => "Bayut",
            "UC_B35FBN" => "Dubizzle",
            "UC_HUNIS3" => "TikTok",
            "UC_3UUGSB" => "Snapchat",
            "UC_YYKE9B" => "Google Ads",
            "UC_ZPFSTP" => "E-Mail",
            "UC_02SXFQ" => "Website",
            "8|WZ_WHATSAPP_CB63BAB86767316147F387473D9D56248" => "WAZZUP: WhatsApp - Open Channel 5",
            "1|WZ_WHATSAPP_CB63BAB86767316147F387473D9D56248" => "WAZZUP: WhatsApp - Gi Properties Channel",
            "3|WZ_WHATSAPP_CB63BAB86767316147F387473D9D56248" => "WAZZUP: WhatsApp - GI Properties FB Chats",
            "CALL" => "Call",
            "WEBFORM" => "CRM form",
            "STORE" => "Personal",
            "UC_L31Q25" => "Property Finder Call",
            "RC_GENERATOR" => "Property finder Emails",
            "CALLBACK" => "Property finder WhatsApp",
            "UC_V2L4X6" => "Bayut Calls",
            "UC_SP0A92" => "Bayut Emails",
            "UC_DPPLAR" => "Bayut WhatsApp",
            "UC_C75HXX" => "Dubizzle Calls",
            "UC_67115U" => "Dubizzle Emails",
            "UC_NUP3WI" => "Dubizzle WhatsApp",
            "UC_2FWM9X" => "Facebook",
            "UC_I3JB89" => "Instagram Direct - Gi Properties Channel",
            "UC_A8MJEL" => "Call back",
            "UC_1X71U2" => "Facebook - Gi Properties Channel",
            "UC_S7U2JP" => "Al Habtoor Tower",
            "UC_CT2ZTA" => "Canal Heights EU FEB 2024",
            "UC_P08WQA" => "Dubai Creek Russia",
            "UC_12QAUE" => "Dubai Creek Harbour - EUR",
            "UC_1TBWA9" => "Dubai Creek Harbour - USA",
            "UC_TJUFIE" => "Dubai Creek Harbour",
            "UC_GCMV2N" => "D1 West at MBR City SEA",
            "UC_8RWO8P" => "D1 West at MBR City US",
            "UC_BR8SQF" => "The Address RAK - RU",
            "UC_MFL3ZH" => "The Acres UAE EN",
            "UC_LX65AA" => "The Address RAK - EUR",
            "UC_SXUEMO" => "The Address RAK - US/Canada",
            "UC_9M2ID6" => "The Valley Emaar AQ",
            "UC_PE86CN" => "Masaar - AR - V6",
            "UC_SQETC6" => "Masaar English - UAE",
            "UC_2IFC98" => "Masaar English",
            "UC_CJVCAK" => "Masaar Arabic AQ",
            "UC_ZX07T8" => "Masaar AE DEC 26",
            "UC_XCF52L" => "Masaar EN DEC 12",
            "UC_EW2DW0" => "Masaar-UAE-Oct-2023",
            "UC_49TGUS" => "Mercedes-Benz Places by Binghatti Updated",
            "UC_BHSM94" => "Mercedes-Benz Places - Meta",
            "UC_4AW5NA" => "Eleganz EU",
            "UC_N1IIP0" => "Eleganz GCC",
            "UC_RY7N93" => "Skyhills Residences GCC",
            "UC_L8CYLX" => "Empire Suites Jan 26 2024",
            "UC_24CGIP" => "Park Lane Dubai Hills - EUR",
            "UC_GW550Q" => "Azizi Venice EU",
            "UC_1HJBDZ" => "Azizi Venice 220124 GCC",
            "UC_9SE51I" => "Marriott Residences EU",
            "UC_EP3L4C" => "Damac 1% DEC 15 FORM",
            "UC_C8UW8Y" => "Weybridge Gardens EU",
            "UC_IH305L" => "Marriot Residences GCC",
            "UC_Z1PFFT" => "Jouri Hills EU 190124",
            "UC_RHDG91" => "JVC Project Campaign UAE 190124",
            "UC_F51L1G" => "JVC Projects EU 190124",
            "UC_G1IREX" => "Verona Dmac Hills 2 UAE 190124",
            "UC_NHTJ91" => "Verona Damac Hills 2 FB Form 191024",
            "UC_8R96G3" => "Expo City Dubai",
            "UC_0UHO49" => "Samana Barari View",
            "UC_3E1KGT" => "Heimat",
            "UC_WHMLYC" => "Sobha Hartland",
            "UC_56CG14" => "Haven By Aldar-Arabic",
            "UC_CP4DXH" => "Address By Emaar",
            "UC_B6CFH6" => "Arada Open House Event",
            "UC_7WWPET" => "FB Masaar English",
            "UC_RMA0LV" => "Sustainable City",
            "UC_CDJY52" => "Damac Park Greens",
            "UC_J3BGKL" => "MB Places EU Arabic",
            "UC_AAOPC6" => "Hayyan - Arabic",
            "UC_IK25GN" => "Hayyan - English",
            "UC_GGVMGE" => "Sobha Reserve",
            "UC_INHDO1" => "Damac Casa",
            "UC_OW5BP1" => "Meraas Central Park",
            "UC_5SEWX5" => "Socio-DHE",
            "UC_L8LIX7" => "Nshama Aria",
            "UC_9BR9LW" => "Porto Playa",
            "UC_88EGID" => "Samana Skyros-Eng",
            "UC_OH55JR" => "Anantara Sharjah",
            "UC_XF3Y91" => "Owners Data",
            "UC_RW3CB8" => "Tiktok LP",
            "UC_SPCPAN" => "Snapchat LP",
            "UC_D0P9AF" => "Facebook LP",
            "UC_3RXYOB" => "Youtube LP",
            "UC_HJKP46" => "Speakol",
            "UC_4B50M9" => "Linked-In",
            "UC_DKCHV2" => "MailChimp",
            "WZ1fcfc891-0009-47fb-b358-809c9c42f0da" => "Whatsapp 971589982713",
            "WZ63d561e3-2b6a-43d8-8df8-01cfe84245e3" => "Whatsapp 971507133886",
            "UC_S8JC1V" => "Import",
            "UC_7I1S2L" => "AI"
        ];

        return $sources[$id] ?? 'Unknown Source';
    }
}
