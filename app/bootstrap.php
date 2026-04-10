<?php

declare(strict_types=1);

require_once __DIR__ . '/helpers.php';
require_once __DIR__ . '/config.php';
require_once __DIR__ . '/knowledge.php';
require_once __DIR__ . '/chat.php';
require_once __DIR__ . '/openai.php';
require_once __DIR__ . '/admin.php';

kwekwe_ensure_directory(kwekwe_path('storage'));
kwekwe_ensure_directory(kwekwe_path('storage', 'analytics'));
kwekwe_ensure_directory(kwekwe_path('storage', 'feedback'));
kwekwe_ensure_directory(kwekwe_path('storage', 'settings'));
kwekwe_ensure_directory(kwekwe_path('storage', 'uploads'));
