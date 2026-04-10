<?php

declare(strict_types=1);

require_once __DIR__ . '/../../app/bootstrap.php';

kwekwe_handle_preflight();

if (($_SERVER['REQUEST_METHOD'] ?? 'GET') !== 'POST') {
    kwekwe_method_not_allowed('POST, OPTIONS');
}

kwekwe_json_response(kwekwe_admin_logout());
