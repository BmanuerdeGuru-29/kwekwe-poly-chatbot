<?php

declare(strict_types=1);

require_once __DIR__ . '/../../app/bootstrap.php';

kwekwe_handle_preflight();
kwekwe_require_admin();

$limit = max(5, min(50, (int) ($_GET['limit'] ?? 20)));
kwekwe_json_response(kwekwe_admin_analytics_summary($limit));
