<?php

declare(strict_types=1);

require_once __DIR__ . '/../../app/bootstrap.php';

kwekwe_handle_preflight();
kwekwe_require_admin();
kwekwe_json_response([
    'status' => 'ok',
    'records' => kwekwe_admin_recent_feedback(),
]);
