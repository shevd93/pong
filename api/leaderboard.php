<?php
declare(strict_types=1);

header('Content-Type: application/json; charset=utf-8');
header('Cache-Control: no-store, no-cache, must-revalidate, max-age=0');

$rootDir = dirname(__DIR__);
$dataFile = $rootDir . '/data/leaderboard.json';

if (!file_exists($dataFile)) {
    http_response_code(500);
    echo json_encode([
        'ok' => false,
        'error' => 'Leaderboard file not found.',
    ], JSON_UNESCAPED_UNICODE);
    exit;
}

$raw = file_get_contents($dataFile);
if ($raw === false) {
    http_response_code(500);
    echo json_encode([
        'ok' => false,
        'error' => 'Failed to read leaderboard.',
    ], JSON_UNESCAPED_UNICODE);
    exit;
}

$data = json_decode($raw, true);
if (!is_array($data)) {
    $data = [];
}

usort($data, static function (array $a, array $b): int {
    return (int)($b['score'] ?? 0) <=> (int)($a['score'] ?? 0);
});

$data = array_slice($data, 0, 10);

echo json_encode([
    'ok' => true,
    'items' => $data,
], JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES);
