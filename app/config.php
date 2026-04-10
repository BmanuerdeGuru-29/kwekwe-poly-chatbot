<?php

declare(strict_types=1);

require_once __DIR__ . '/helpers.php';

if (!function_exists('kwekwe_config')) {
    function kwekwe_config(): array
    {
        static $config = null;

        if ($config !== null) {
            return $config;
        }

        kwekwe_load_env_file(kwekwe_path('.env'));
        date_default_timezone_set(getenv('APP_TIMEZONE') ?: 'Africa/Johannesburg');

        $config = [
            'app_name' => 'Kwekwe Polytechnic AI Assistant',
            'app_url' => rtrim(getenv('APP_URL') ?: '', '/'),
            'admin_key' => getenv('ADMIN_KEY') ?: 'change-this-admin-key',
            'knowledge_index' => kwekwe_path('data', 'knowledge', 'index.json'),
            'knowledge_sources' => [
                kwekwe_path('data', 'sample_docs'),
                kwekwe_path('storage', 'uploads'),
            ],
            'storage' => [
                'analytics' => kwekwe_path('storage', 'analytics', 'chat_events.jsonl'),
                'feedback' => kwekwe_path('storage', 'feedback', 'feedback.jsonl'),
                'openai_settings' => kwekwe_path('storage', 'settings', 'openai.json'),
            ],
            'contacts' => [
                'phone' => '+263 8612 122991',
                'mobile' => '0786 658 480',
                'whatsapp' => '0711 806 837',
                'email' => 'infor@kwekwepoly.ac.zw',
            ],
            'links' => [
                'website' => 'https://www.kwekwepoly.ac.zw/',
                'apply' => 'https://apply.kwekwepoly.ac.zw/',
                'portal' => 'https://elearning.kwekwepoly.ac.zw/',
                'hostel' => 'https://www.kwekwepoly.ac.zw/hostel.php',
            ],
            'openai' => [
                'model' => trim((string) (getenv('OPENAI_MODEL') ?: '')) ?: 'gpt-5.4-mini',
                'base_url' => rtrim((string) (getenv('OPENAI_BASE_URL') ?: 'https://api.openai.com/v1'), '/'),
            ],
        ];

        return $config;
    }
}
