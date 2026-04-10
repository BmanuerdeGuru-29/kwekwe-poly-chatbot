<?php

declare(strict_types=1);

require_once __DIR__ . '/../../app/bootstrap.php';

kwekwe_handle_preflight();
kwekwe_require_admin();

$limit = max(1, min(50, (int) ($_GET['limit'] ?? 25)));
kwekwe_json_response(kwekwe_admin_knowledge_gap_inbox($limit));
