<?php

declare(strict_types=1);

spl_autoload_register(function ($class) {
    require __DIR__ . "/src/{$class}.php";
});

use Helpers\Logger;

require_once __DIR__ . '/helpers/Logger.php';

Logger::logRequest([
    'method' => $_SERVER['REQUEST_METHOD'],
    'uri' => $_SERVER['REQUEST_URI'],
    'query' => $_GET,
]);

$endpoint = $_GET['endpoint'] ?? null;

function snakeToPascal(string $string): string
{
    return str_replace(' ', '', ucwords(str_replace('_', ' ', $string)));
}

$controllerClass = $endpoint ? snakeToPascal($endpoint) . 'Controller' : null;

if ($endpoint && class_exists($controllerClass)) {
    $controller = new $controllerClass();
    $controller->processRequest($_SERVER['REQUEST_METHOD']);
} else {
    header("Content-Type: application/json");
    http_response_code(404);
    echo json_encode(["error" => "Resource '$endpoint' not found"]);
    exit;
}

exit;
