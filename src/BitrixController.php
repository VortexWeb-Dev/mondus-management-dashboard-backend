<?php

require_once __DIR__ . "/../crest/crest.php";

class BitrixController
{
    private $config;

    public function __construct()
    {
        $this->config = require __DIR__ . '/../config/config.php';
    }

    public function getDeals(array $filter = [], array $select = [], int $limit = null, array $order = []): ?array
    {
        $allDeals = [];
        $start = 0;

        $select = empty($select) ? ['ID', 'TITLE'] : $select;
        $order = empty($order) ? ['DATE_CREATE' => 'DESC'] : $order;

        do {
            $params = [
                'filter' => $filter,
                'select' => $select,
                'start'  => $start,
                'order'  => $order,
            ];

            $response = CRest::call('crm.deal.list', $params);

            if (!isset($response['result'])) {
                error_log('Bitrix CRM error: ' . json_encode($response));
                break;
            }

            $allDeals = array_merge($allDeals, $response['result']);

            if ($limit && count($allDeals) >= $limit) {
                break;
            }

            $start = $response['next'] ?? null;
        } while ($start !== null);

        return array_slice($allDeals, 0, $limit) ?: null;
    }

    public function getUser(int $id): ?array
    {
        $result = CRest::call('user.get', ['ID' => $id]);
        return $result['result'][0] ?? null;
    }

    public function searchUsers(array $filter = []): array
    {
        $result = CRest::call('user.get', ['filter' => $filter]);
        return $result['result'] ?? [];
    }

    public function getAllUsers(array $filter = ['ACTIVE' => 'Y'], array $select = ['ID']): array
    {
        $allUsers = [];
        $start = 0;

        do {
            $response = CRest::call('user.get', [
                'order' => ['ID' => 'ASC'],
                'filter' => $filter,
                'select' => $select,
                'start' => $start
            ]);

            $users = $response['result'] ?? [];
            $allUsers = array_merge($allUsers, $users);

            $start = isset($response['next']) ? $response['next'] : null;
        } while ($start !== null);

        return $allUsers;
    }

    public function getAllUserAds(array $filter = [], array $select = []): array
    {
        $allItems = [];
        $start = 0;

        do {
            $response = CRest::call('crm.item.list', [
                'entityTypeId' => $this->config['LISTINGS_ENTITY_TYPE_ID'],
                'filter' => $filter,
                'select' => $select,
                'start' => $start
            ]);

            $items = $response['result']['items'] ?? [];
            $allItems = array_merge($allItems, $items);

            $start = isset($response['next']) ? $response['next'] : null;
        } while ($start !== null);

        return $allItems;
    }
    public function getAllDepartments(array $filter = [], array $select = []): array
    {
        $allDepartments = [];
        $start = 0;

        do {
            $response = CRest::call('department.get', [
                'select' => $select,
                'start' => $start,
                'filter' => $filter
            ]);

            $departments = $response['result'] ?? [];

            $allDepartments = array_merge($allDepartments, $departments);

            $start = $response['next'] ?? null;
        } while ($start !== null);

        $filteredDepartments = array_filter($allDepartments, function ($dept) use ($filter) {
            return in_array($dept['ID'], $filter);
        });

        return $filteredDepartments;
    }
}
