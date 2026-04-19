<?php
declare(strict_types=1);

header('Content-Type: application/json; charset=utf-8');
header('Cache-Control: no-store, no-cache, must-revalidate, max-age=0');

if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    http_response_code(405);
    echo json_encode([
        'ok' => false,
        'error' => 'Method not allowed.',
    ], JSON_UNESCAPED_UNICODE);
    exit;
}

$rawInput = file_get_contents('php://input');
if ($rawInput === false) {
    http_response_code(400);
    echo json_encode([
        'ok' => false,
        'error' => 'Empty request body.',
    ], JSON_UNESCAPED_UNICODE);
    exit;
}

$payload = json_decode($rawInput, true);
if (!is_array($payload)) {
    http_response_code(400);
    echo json_encode([
        'ok' => false,
        'error' => 'Invalid JSON.',
    ], JSON_UNESCAPED_UNICODE);
    exit;
}

$userId = isset($payload['userId']) ? (string)$payload['userId'] : '';
$username = isset($payload['username']) ? trim((string)$payload['username']) : '';
$firstName = isset($payload['firstName']) ? trim((string)$payload['firstName']) : '';
$lastName = isset($payload['lastName']) ? trim((string)$payload['lastName']) : '';
$score = isset($payload['score']) ? (int)$payload['score'] : -1;

if ($userId === '' || $score < 0) {
    http_response_code(400);
    echo json_encode([
        'ok' => false,
        'error' => 'userId and score are required.',
    ], JSON_UNESCAPED_UNICODE);
    exit;
}

$name = trim($firstName . ' ' . $lastName);
if ($username !== '') {
    $displayName = '@' . ltrim($username, '@');
} elseif ($name !== '') {
    $displayName = $name;
} else {
    $displayName = 'Игрок';
}

$rootDir = dirname(__DIR__);
$dataDir = $rootDir . '/data';
$dataFile = $dataDir . '/leaderboard.json';

if (!is_dir($dataDir)) {
    if (!mkdir($dataDir, 0775, true) && !is_dir($dataDir)) {
        http_response_code(500);
        echo json_encode([
            'ok' => false,
            'error' => 'Failed to create data directory.',
        ], JSON_UNESCAPED_UNICODE);
        exit;
    }
}

if (!file_exists($dataFile)) {
    if (file_put_contents($dataFile, '[]') === false) {
        http_response_code(500);
        echo json_encode([
            'ok' => false,
            'error' => 'Failed to create leaderboard file.',
        ], JSON_UNESCAPED_UNICODE);
        exit;
    }
}

$fp = fopen($dataFile, 'c+');
if ($fp === false) {
    http_response_code(500);
    echo json_encode([
        'ok' => false,
        'error' => 'Failed to open leaderboard file.',
    ], JSON_UNESCAPED_UNICODE);
    exit;
}

try {
    if (!flock($fp, LOCK_EX)) {
        throw new RuntimeException('Failed to lock leaderboard file.');
    }

    rewind($fp);
    $contents = stream_get_contents($fp);
    if ($contents === false || trim($contents) === '') {
        $contents = '[]';
    }

    $data = json_decode($contents, true);
    if (!is_array($data)) {
        $data = [];
    }

    $updated = false;
    foreach ($data as &$entry) {
        if ((string)($entry['userId'] ?? '') === $userId) {
            $entry['username'] = $username;
            $entry['name'] = $displayName;
            $entry['score'] = max((int)($entry['score'] ?? 0), $score);
            $updated = true;
            break;
        }
    }
    unset($entry);

    if (!$updated) {
        $data[] = [
            'userId' => $userId,
            'username' => $username,
            'name' => $displayName,
            'score' => $score,
        ];
    }

    usort($data, static function (array $a, array $b): int {
        return (int)($b['score'] ?? 0) <=> (int)($a['score'] ?? 0);
    });

    $data = array_slice($data, 0, 10);

    $json = json_encode(
        $data,
        JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES | JSON_PRETTY_PRINT
    );

    if ($json === false) {
        throw new RuntimeException('Failed to encode leaderboard JSON.');
    }

    ftruncate($fp, 0);
    rewind($fp);

    if (fwrite($fp, $json) === false) {
        throw new RuntimeException('Failed to write leaderboard file.');
    }

    fflush($fp);
    flock($fp, LOCK_UN);

    echo json_encode([
        'ok' => true,
        'items' => $data,
    ], JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES);
} catch (Throwable $e) {
    flock($fp, LOCK_UN);
    http_response_code(500);
    echo json_encode([
        'ok' => false,
        'error' => $e->getMessage(),
    ], JSON_UNESCAPED_UNICODE);
} finally {
    fclose($fp);
}
