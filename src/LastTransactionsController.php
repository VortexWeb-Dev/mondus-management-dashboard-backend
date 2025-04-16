<?php

error_reporting(E_ALL);
ini_set('display_errors', 1);

require_once __DIR__ . "/../services/CacheService.php";
require_once __DIR__ . "/../services/ResponseService.php";
require_once __DIR__ . "/BitrixController.php";

class LastTransactionsController extends BitrixController
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

        $cacheKey = "last_transactions_" . date('Y-m-d');
        $cached = $this->cache->get($cacheKey);

        if ($cached !== false && $this->config['cache']['enabled']) {
            $this->response->sendSuccess(200, $cached);
            return;
        }

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
            '!=OPPORTUNITY' => null
        ], [
            'ID',
            'ASSIGNED_BY_ID',
            'CLOSEDATE',
            'OPPORTUNITY',
            'UF_CRM_67FF84E2C8AB6',
            'UF_CRM_67FF84E2CD927',
        ]);

        $dealByEmployee = [];
        foreach ($deals as $deal) {
            $employeeId = $deal['ASSIGNED_BY_ID'];
            if (!isset($dealByEmployee[$employeeId])) {
                $dealByEmployee[$employeeId] = [];
            }
            $dealByEmployee[$employeeId][] = $deal;
        }

        $data = [];

        foreach ($salesEmployees as $emp) {
            $id = $emp['ID'];
            $name = trim(($emp['NAME'] ?? '') . ' ' . ($emp['LAST_NAME'] ?? ''));
            $joiningDate = $emp['UF_EMPLOYMENT_DATE'] ?? null;

            if (empty($dealByEmployee[$id])) {
                continue;
            }

            usort($dealByEmployee[$id], fn($a, $b) => strtotime($b['CLOSEDATE']) <=> strtotime($a['CLOSEDATE']));
            $lastDeal = $dealByEmployee[$id][0];

            $lastDealDate = $lastDeal['CLOSEDATE'];
            $monthsWithoutClosing = $this->monthsBetweenDates($lastDealDate, date('Y-m-d'));

            $data[] = [
                'agent' => $name,
                'joiningDate' => $joiningDate,
                'lastDealDate' => $lastDealDate,
                'project' => $this->mapProjectName($lastDeal['UF_CRM_67FF84E2C8AB6']) ?? 'Unknown',
                'amount' => (float) $lastDeal['OPPORTUNITY'],
                'grossComms' => (float) $lastDeal['OPPORTUNITY'] * (float) $lastDeal['UF_CRM_67FF84E2CD927'] / 100,
                'monthsWithoutClosing' => $monthsWithoutClosing
            ];
        }

        $this->cache->set($cacheKey, $data);
        $this->response->sendSuccess(200, $data);
    }

    private function monthsBetweenDates(string $startDate, string $endDate): int
    {
        $start = new DateTime($startDate);
        $end = new DateTime($endDate);

        $diff = $start->diff($end);

        return ($diff->y * 12) + $diff->m;
    }

    private function mapProjectName($id): string
    {
        $map = [
            552 => "17 Icon Bay",
            615 => "52|42 Tower 1",
            616 => "52|42 Tower 2",
            607 => "Address Harbour Point Tower 1",
            608 => "Address Harbour Point Tower 2",
            602 => "Arabian Gate",
            569 => "Arabian Ranches III - Bliss",
            4315 => "Creek Beach Breeze - Tower 1",
            4316 => "Creek Beach Breeze - Tower 2",
            4317 => "Creek Beach Breeze - Tower 3",
            4331 => "Springs 1",
            4332 => "Springs 2",
            4334 => "Liv Residence",
            4408 => "Arabian Ranches III - Caya",
            4409 => "Arabian Ranches III - Elie Saab",
            4335 => "Manchester Tower",
            4410 => "Arabian Ranches III - Elie Saab II",
            4435 => "Azizi Riviera 1",
            4436 => "Azizi Riviera 2",
            4437 => "Azizi Riviera 3",
            4438 => "Azizi Riviera 4",
            4439 => "Azizi Riviera 5",
            4440 => "Azizi Riviera 6",
            4441 => "Azizi Riviera 7",
            4442 => "Azizi Riviera 8",
            4443 => "Azizi Riviera 9",
            570 => "Arabian Ranches III - Joy",
            4444 => "Azizi Riviera 10",
            4445 => "Azizi Riviera 11",
            4446 => "Azizi Riviera 12",
            4447 => "Azizi Riviera 13",
            4448 => "Azizi Riviera 14",
            4449 => "Azizi Riviera 15",
            4450 => "Azizi Riviera 16",
            4451 => "Azizi Riviera 17",
            4452 => "Azizi Riviera 18",
            4453 => "Azizi Riviera 19",
            571 => "Arabian Ranches III - June",
            4454 => "Azizi Riviera 20",
            4455 => "Azizi Riviera 21",
            4456 => "Azizi Riviera 22",
            4457 => "Azizi Riviera 23",
            4458 => "Azizi Riviera 32",
            4459 => "Azizi Riviera 33",
            4460 => "Azizi Riviera 34",
            4461 => "Azizi Riviera 35",
            4462 => "Azizi Riviera 37",
            4463 => "Azizi Riviera 38",
            572 => "Arabian Ranches III - Ruba",
            4465 => "Amna Tower",
            4466 => "Harbour Gate Tower 1",
            4467 => "Harbour Gate Tower 2",
            4468 => "Upper Crest",
            4482 => "The Alef Residences",
            4483 => "Jumeirah Gate Tower 1",
            4484 => "Jumeirah Gate Tower 2",
            4485 => "THE ROYAL ATLANTIS RESORT & RESIDENCES",
            4486 => "THE 8",
            4487 => "THE PALM TOWER",
            573 => "Arabian Ranches III - Spring",
            4488 => "Emerald Palace Kempinski Hotel",
            4489 => "BALQIS RESIDENCE 1",
            4490 => "BALQIS RESIDENCE 2",
            4491 => "BALQIS RESIDENCE 3",
            4492 => "Bluewaters Residences 1",
            4493 => "Bluewaters Residences 2",
            4494 => "Bluewaters Residences 3",
            4495 => "Bluewaters Residences 4",
            4496 => "Bluewaters Residences 5",
            4497 => "Bluewaters Residences 6",
            574 => "Arabian Ranches III - Sun",
            4498 => "Bluewaters Residences 7",
            4499 => "Bluewaters Residences 8",
            4500 => "Bluewaters Residences 9",
            4501 => "Bluewaters Residences 10",
            4502 => "CARIBBEAN",
            4503 => "PACIFIC",
            4504 => "SOUTHERN",
            4505 => "ADRIATIC",
            4506 => "AEGEAN",
            4507 => "ATLANTIC",
            567 => "Belgravia III A",
            4508 => "BALTIC",
            4509 => "Cherrywoods",
            4511 => "Al Habtoor Tower",
            4515 => "Forte",
            4516 => "Burj Khalifa",
            4517 => "Opera Grand",
            4518 => "St. Regis",
            4519 => "29 Burj Boulevard",
            4520 => "Downtown Views",
            568 => "Belgravia III B",
            4521 => "The Address Dubai Mall",
            4522 => "Imperial Avenue",
            4523 => "Standpoint Tower",
            4524 => "Address Fountain Views (we worked on tower 3 ONLY)",
            4525 => "IL Primo",
            4526 => "Boulevard Central Tower",
            4527 => "Grande",
            4528 => "Burj Al Nujoom",
            4529 => "RP Heights",
            4530 => "48 Burj Royale",
            580 => "Bloom Heights A",
            4531 => "Mon Reve",
            4532 => "The Residences",
            4533 => "Movenpick Hotel Apartments",
            4534 => "The Lofts West",
            4535 => "Damac Maison the Distinction",
            4536 => "The Signature",
            4537 => "Visa Residence",
            4538 => "Burj Royale",
            4539 => "DT1",
            4540 => "Mada Residences",
            581 => "Bloom Heights B",
            4541 => "Claren Tower",
            4542 => "Elite Downtown",
            4543 => "Boulevard Crescent",
            605 => "Blvd Heights T1",
            606 => "Blvd Heights T2",
            553 => "Burj Crown",
            609 => "Creek Gate Tower 1",
            610 => "Creek Gate Tower 2",
            611 => "Creek Horizon Tower 1",
            612 => "Creek Horizon Tower 2",
            613 => "Creek Rise Tower 1",
            614 => "Creek Rise Tower 2",
            557 => "Continental Tower",
            617 => "Damac Heights",
            556 => "Platinum Residence",
            554 => "Reva Residences",
            551 => "The Grand",
            559 => "Tiara Residence - Emerald North 1",
            626 => "Tiara Residence - Aquamarine",
            627 => "Tiara Residence - Diamond",
            628 => "Tiara Residence - Ruby",
            629 => "Tiara Residence - Sapphire",
            630 => "Tiara Residence - Tanzanite",
            601 => "Others",
            639 => "Azizi Riviera",
            642 => "Merano Tower",
            643 => "The Scala Tower",
            644 => "Paramount Tower Hotel & Residences",
            645 => "Maple",
            646 => "Maple 2",
            647 => "Maple 3",
            648 => "Club Villas",
            651 => "Elan",
            652 => "Aura",
            653 => "Harmony",
            654 => "Alaya",
            656 => "Joya Blanca",
            658 => "Boulevard Point",
            4306 => "Pinnacle Tower",
            4307 => "West Bay Tower",
            4308 => "Marquise Square Tower",
            4309 => "Binghatti Gate",
            4310 => "Binghatti Jasmine",
        ];

        return $map[$id] ?? '';
    }
}
