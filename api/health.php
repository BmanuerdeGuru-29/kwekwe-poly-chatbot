<?php

declare(strict_types=1);

require_once __DIR__ . '/../app/bootstrap.php';

kwekwe_handle_preflight();

$index = kwekwe_load_knowledge_index();
kwekwe_json_response([
    'status' => 'healthy',
    'timestamp' => kwekwe_current_timestamp(),
    'runtime' => 'php-flat-files',
    'document_count' => $index['document_count'] ?? 0,
    'chunk_count' => $index['chunk_count'] ?? 0,
]);
