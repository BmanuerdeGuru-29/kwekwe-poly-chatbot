<?php

declare(strict_types=1);

require_once __DIR__ . '/../app/bootstrap.php';

kwekwe_handle_preflight();

$method = $_SERVER['REQUEST_METHOD'] ?? 'GET';
if (!in_array($method, ['GET', 'POST'], true)) {
    kwekwe_method_not_allowed('GET, POST, OPTIONS');
}

$input = $method === 'POST' ? kwekwe_json_input() : $_GET;
$query = trim((string) ($input['query'] ?? ''));
$limit = max(1, min(10, (int) ($input['limit'] ?? 6)));

if ($query === '') {
    kwekwe_json_response(['detail' => 'Query cannot be empty.'], 400);
}

$intent = kwekwe_detect_intent($query);
$profile = kwekwe_question_profile($query, $intent);
$results = array_map(static function (array $result): array {
    return [
        'title' => $result['title'] ?? '',
        'heading' => $result['heading'] ?? '',
        'excerpt' => $result['excerpt'] ?? '',
        'score' => $result['score'] ?? 0,
        'section_type' => $result['section_type'] ?? '',
        'metadata' => $result['metadata'] ?? [],
    ];
}, kwekwe_search_knowledge($query, $limit, $intent, $profile));

kwekwe_json_response([
    'query' => $query,
    'intent' => $intent,
    'count' => count($results),
    'results' => $results,
]);
