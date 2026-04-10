<?php

declare(strict_types=1);

require_once __DIR__ . '/config.php';

if (!function_exists('kwekwe_openai_preferences_path')) {
    function kwekwe_openai_preferences_path(): string
    {
        return kwekwe_config()['storage']['openai_settings'];
    }
}

if (!function_exists('kwekwe_openai_preferences')) {
    function kwekwe_openai_preferences(bool $refresh = false): array
    {
        static $cache = null;

        if ($refresh || $cache === null) {
            $stored = kwekwe_read_json_file(kwekwe_openai_preferences_path(), []);
            $mode = strtolower(trim((string) ($stored['mode'] ?? 'inherit')));
            if (!in_array($mode, ['inherit', 'custom', 'disabled'], true)) {
                $mode = 'inherit';
            }

            $cache = [
                'mode' => $mode,
                'api_key' => trim((string) ($stored['api_key'] ?? '')),
                'updated_at' => trim((string) ($stored['updated_at'] ?? '')) ?: null,
            ];
        }

        return $cache;
    }
}

if (!function_exists('kwekwe_write_openai_preferences')) {
    function kwekwe_write_openai_preferences(array $preferences): void
    {
        $mode = strtolower(trim((string) ($preferences['mode'] ?? 'inherit')));
        if (!in_array($mode, ['inherit', 'custom', 'disabled'], true)) {
            $mode = 'inherit';
        }

        kwekwe_write_json_file(kwekwe_openai_preferences_path(), [
            'mode' => $mode,
            'api_key' => trim((string) ($preferences['api_key'] ?? '')),
            'updated_at' => kwekwe_current_timestamp(),
        ]);

        kwekwe_openai_preferences(true);
    }
}

if (!function_exists('kwekwe_resolve_openai_config')) {
    function kwekwe_resolve_openai_config(bool $refresh = false): array
    {
        $preferences = kwekwe_openai_preferences($refresh);
        $envKey = trim((string) (getenv('OPENAI_API_KEY') ?: ''));
        $apiKey = '';
        $source = 'none';

        if ($preferences['mode'] === 'disabled') {
            $source = 'disabled';
        } elseif ($preferences['mode'] === 'custom' && $preferences['api_key'] !== '') {
            $apiKey = $preferences['api_key'];
            $source = 'admin-storage';
        } elseif ($envKey !== '') {
            $apiKey = $envKey;
            $source = 'environment';
        }

        return [
            'configured' => $apiKey !== '',
            'api_key' => $apiKey,
            'source' => $source,
            'mode' => $preferences['mode'],
            'updated_at' => $preferences['updated_at'],
            'model' => kwekwe_config()['openai']['model'],
            'base_url' => kwekwe_config()['openai']['base_url'],
        ];
    }
}

if (!function_exists('kwekwe_mask_secret')) {
    function kwekwe_mask_secret(string $secret): string
    {
        $secret = trim($secret);
        if ($secret === '') {
            return '';
        }

        $length = function_exists('mb_strlen') ? mb_strlen($secret, 'UTF-8') : strlen($secret);
        if ($length <= 10) {
            $suffix = function_exists('mb_substr')
                ? mb_substr($secret, -4, null, 'UTF-8')
                : substr($secret, -4);
            return str_repeat('*', max(0, $length - 4)) . $suffix;
        }

        $prefix = function_exists('mb_substr')
            ? mb_substr($secret, 0, 7, 'UTF-8')
            : substr($secret, 0, 7);
        $suffix = function_exists('mb_substr')
            ? mb_substr($secret, -4, null, 'UTF-8')
            : substr($secret, -4);

        return $prefix . str_repeat('*', max(4, $length - 11)) . $suffix;
    }
}

if (!function_exists('kwekwe_openai_status')) {
    function kwekwe_openai_status(bool $refresh = false): array
    {
        $config = kwekwe_resolve_openai_config($refresh);
        $probe = kwekwe_probe_openai_config($config);

        $detail = match ($config['source']) {
            'admin-storage' => 'Using the OpenAI API key saved from the admin panel.',
            'environment' => 'Using the OPENAI_API_KEY value from the environment file.',
            'disabled' => 'OpenAI fallback is disabled from the admin panel.',
            default => 'No OpenAI API key is configured yet.',
        };

        if ($config['configured'] && !($probe['ready'] ?? false)) {
            $detail = 'OpenAI fallback is configured but unavailable: ' . trim((string) ($probe['detail'] ?? 'OpenAI request failed.'));
        } elseif ($config['configured'] && ($probe['ready'] ?? false)) {
            $detail .= ' OpenAI fallback is ready.';
        }

        return [
            'status' => 'ok',
            'configured' => $config['configured'],
            'fallback_enabled' => $config['configured'] && ($probe['ready'] ?? false),
            'source' => $config['source'],
            'management_mode' => $config['mode'],
            'model' => $config['model'],
            'api_base_url' => $config['base_url'],
            'api_key_masked' => $config['configured'] ? kwekwe_mask_secret($config['api_key']) : null,
            'updated_at' => $config['updated_at'],
            'ready' => (bool) ($probe['ready'] ?? false),
            'checked' => (bool) ($probe['checked'] ?? false),
            'status_code' => $probe['status_code'] ?? null,
            'last_error' => ($probe['ready'] ?? false) ? null : ($probe['detail'] ?? null),
            'error_type' => $probe['error_type'] ?? null,
            'error_code' => $probe['error_code'] ?? null,
            'detail' => $detail,
        ];
    }
}

if (!function_exists('kwekwe_save_openai_key')) {
    function kwekwe_save_openai_key(string $apiKey): array
    {
        $apiKey = trim($apiKey);
        if ($apiKey === '') {
            return [
                'status' => 'error',
                'detail' => 'Enter an OpenAI API key before saving.',
            ];
        }

        $probe = kwekwe_probe_openai_config([
            'configured' => true,
            'api_key' => $apiKey,
            'source' => 'admin-storage',
            'mode' => 'custom',
            'updated_at' => kwekwe_current_timestamp(),
            'model' => kwekwe_config()['openai']['model'],
            'base_url' => kwekwe_config()['openai']['base_url'],
        ]);

        if (!($probe['ready'] ?? false)) {
            return [
                'status' => 'error',
                'ready' => false,
                'detail' => 'OpenAI API key could not be verified. ' . trim((string) ($probe['detail'] ?? 'OpenAI request failed.')),
                'last_error' => $probe['detail'] ?? null,
                'error_type' => $probe['error_type'] ?? null,
                'error_code' => $probe['error_code'] ?? null,
                'model' => kwekwe_config()['openai']['model'],
            ];
        }

        kwekwe_write_openai_preferences([
            'mode' => 'custom',
            'api_key' => $apiKey,
        ]);

        $status = kwekwe_openai_status(true);
        $status['detail'] = 'OpenAI API key saved. ChatGPT fallback is now active.';
        return $status;
    }
}

if (!function_exists('kwekwe_reset_openai_key_source')) {
    function kwekwe_reset_openai_key_source(): array
    {
        kwekwe_write_openai_preferences([
            'mode' => 'inherit',
            'api_key' => '',
        ]);

        $status = kwekwe_openai_status(true);
        $status['detail'] = $status['configured']
            ? 'OpenAI fallback reset to the environment configuration.'
            : 'OpenAI fallback reset. No environment key is configured right now.';
        return $status;
    }
}

if (!function_exists('kwekwe_disable_openai_fallback')) {
    function kwekwe_disable_openai_fallback(): array
    {
        kwekwe_write_openai_preferences([
            'mode' => 'disabled',
            'api_key' => '',
        ]);

        $status = kwekwe_openai_status(true);
        $status['detail'] = 'OpenAI fallback disabled from the admin panel.';
        return $status;
    }
}

if (!function_exists('kwekwe_openai_language_name')) {
    function kwekwe_openai_language_name(string $language): string
    {
        return match (strtolower(trim($language))) {
            'sn' => 'Shona',
            'nd' => 'Ndebele',
            default => 'English',
        };
    }
}

if (!function_exists('kwekwe_openai_instructions')) {
    function kwekwe_openai_instructions(string $language): string
    {
        $config = kwekwe_config();
        $contacts = $config['contacts'];
        $links = $config['links'];

        return "You are the ChatGPT fallback for the Kwekwe Polytechnic website assistant. "
            . "The local knowledge base did not contain a strong direct answer, so you are handling a no-match Kwekwe Polytechnic question. "
            . "Respond in " . kwekwe_openai_language_name($language) . ". "
            . "Stay strictly within Kwekwe Polytechnic scope only. "
            . "Only assist with Kwekwe Polytechnic topics such as admissions, programmes, departments, fees, payments, portal access, ICT help, accommodation, hostels, examinations, HEXCO, contact details, campus services, and student guidance. "
            . "If the user asks about anything unrelated to Kwekwe Polytechnic, reply that you can only help with Kwekwe Polytechnic-related questions and invite them to ask about Kwekwe Polytechnic. "
            . "If the user asks for a Kwekwe Polytechnic-specific fact that you cannot verify from the provided context, do not guess. Say you do not have verified information and direct the user to the official channels. "
            . "Be concise, helpful, and practical. "
            . "Do not invent fees, dates, policies, course lists, phone numbers, portal credentials, staff details, office hours, or deadlines. "
            . "Official channels: Website {$links['website']}, Apply {$links['apply']}, Portal {$links['portal']}, "
            . "Phone {$contacts['phone']}, WhatsApp {$contacts['whatsapp']}, Email {$contacts['email']}.";
    }
}

if (!function_exists('kwekwe_openai_input')) {
    function kwekwe_openai_input(string $message, string $intent = 'general', array $context = []): string
    {
        $config = kwekwe_config();
        $contacts = $config['contacts'];
        $links = $config['links'];
        $lines = [
            "Kwekwe Polytechnic context:",
            "- Institution: Kwekwe Polytechnic",
            "- Official website: {$links['website']}",
            "- Apply portal: {$links['apply']}",
            "- Student portal: {$links['portal']}",
            "- Phone: {$contacts['phone']}",
            "- WhatsApp: {$contacts['whatsapp']}",
            "- Email: {$contacts['email']}",
            "- Allowed support areas: admissions, programmes, fees, departments, portal help, ICT, accommodation, exams, HEXCO, contact details, and student guidance",
            "- Detected intent: {$intent}",
        ];

        if (isset($context['evidence']) && is_array($context['evidence'])) {
            $lines[] = "- Local evidence score: " . (int) ($context['evidence']['score'] ?? 0) . "/100";
            $lines[] = "- Local evidence label: " . trim((string) ($context['evidence']['label'] ?? 'low'));
            $lines[] = "- Local coverage: " . round((float) ($context['evidence']['coverage'] ?? 0.0), 3);
        }

        $snippets = [];
        foreach (array_slice($context['results'] ?? [], 0, 3) as $result) {
            $heading = trim((string) ($result['heading'] ?? ''));
            $excerpt = trim((string) ($result['excerpt'] ?? ''));
            if ($excerpt === '') {
                continue;
            }
            $label = trim((string) ($result['title'] ?? 'Local note'));
            if ($heading !== '') {
                $label .= ' / ' . $heading;
            }
            $snippets[] = "- {$label}: {$excerpt}";
        }

        if ($snippets !== []) {
            $lines[] = '- Top local knowledge snippets:';
            array_push($lines, ...$snippets);
        }

        $lines[] = '';
        $lines[] = 'Student question:';
        $lines[] = $message;

        return implode("\n", $lines);
    }
}

if (!function_exists('kwekwe_extract_openai_text')) {
    function kwekwe_extract_openai_text(array $payload): string
    {
        $direct = trim((string) ($payload['output_text'] ?? ''));
        if ($direct !== '') {
            return $direct;
        }

        $parts = [];
        foreach ($payload['output'] ?? [] as $item) {
            foreach ($item['content'] ?? [] as $content) {
                if (($content['type'] ?? '') !== 'output_text') {
                    continue;
                }

                $text = trim((string) ($content['text'] ?? ''));
                if ($text !== '') {
                    $parts[] = $text;
                }
            }
        }

        return trim(implode("\n\n", $parts));
    }
}

if (!function_exists('kwekwe_http_post_json')) {
    function kwekwe_http_post_json(string $url, array $headers, array $payload, int $timeoutSeconds = 25): array
    {
        $body = json_encode($payload, JSON_UNESCAPED_SLASHES | JSON_UNESCAPED_UNICODE);
        if ($body === false) {
            return [
                'ok' => false,
                'status' => 0,
                'error' => 'Failed to encode request payload.',
                'body' => '',
            ];
        }

        if (function_exists('curl_init')) {
            $curl = curl_init($url);
            curl_setopt_array($curl, [
                CURLOPT_POST => true,
                CURLOPT_RETURNTRANSFER => true,
                CURLOPT_HTTPHEADER => $headers,
                CURLOPT_POSTFIELDS => $body,
                CURLOPT_TIMEOUT => $timeoutSeconds,
            ]);

            $responseBody = curl_exec($curl);
            $status = (int) curl_getinfo($curl, CURLINFO_RESPONSE_CODE);
            $error = curl_error($curl);
            curl_close($curl);

            return [
                'ok' => $error === '' && $status >= 200 && $status < 300,
                'status' => $status,
                'error' => $error !== '' ? $error : null,
                'body' => is_string($responseBody) ? $responseBody : '',
            ];
        }

        $context = stream_context_create([
            'http' => [
                'method' => 'POST',
                'header' => implode("\r\n", $headers),
                'content' => $body,
                'timeout' => $timeoutSeconds,
                'ignore_errors' => true,
            ],
        ]);

        $responseBody = @file_get_contents($url, false, $context);
        $status = 0;
        foreach ($http_response_header ?? [] as $line) {
            if (preg_match('/\s(\d{3})\s/', $line, $matches) === 1) {
                $status = (int) $matches[1];
                break;
            }
        }

        return [
            'ok' => $status >= 200 && $status < 300,
            'status' => $status,
            'error' => $responseBody === false ? 'HTTP request failed.' : null,
            'body' => $responseBody === false ? '' : $responseBody,
        ];
    }
}

if (!function_exists('kwekwe_openai_request')) {
    function kwekwe_openai_request(array $config, array $payload, int $timeoutSeconds = 25): array
    {
        if (trim((string) ($config['api_key'] ?? '')) === '') {
            return [
                'ok' => false,
                'status' => 0,
                'error' => 'OpenAI API key is not configured.',
                'body' => '',
            ];
        }

        return kwekwe_http_post_json(
            rtrim((string) ($config['base_url'] ?? 'https://api.openai.com/v1'), '/') . '/responses',
            [
                'Authorization: Bearer ' . trim((string) ($config['api_key'] ?? '')),
                'Content-Type: application/json',
                'Accept: application/json',
            ],
            $payload,
            $timeoutSeconds
        );
    }
}

if (!function_exists('kwekwe_openai_response_error')) {
    function kwekwe_openai_response_error(array $response): array
    {
        $message = trim((string) ($response['error'] ?? ''));
        $type = null;
        $code = null;

        $payload = json_decode((string) ($response['body'] ?? ''), true);
        if (is_array($payload) && isset($payload['error']) && is_array($payload['error'])) {
            $message = trim((string) ($payload['error']['message'] ?? $message));
            $type = trim((string) ($payload['error']['type'] ?? '')) ?: null;
            $code = trim((string) ($payload['error']['code'] ?? '')) ?: null;
        }

        if ($message === '') {
            $status = (int) ($response['status'] ?? 0);
            $message = $status > 0
                ? 'OpenAI request failed with status ' . $status . '.'
                : 'OpenAI request failed.';
        }

        return [
            'message' => $message,
            'type' => $type,
            'code' => $code,
            'status_code' => isset($response['status']) ? (int) $response['status'] : 0,
        ];
    }
}

if (!function_exists('kwekwe_probe_openai_config')) {
    function kwekwe_probe_openai_config(?array $config = null): array
    {
        $config ??= kwekwe_resolve_openai_config();

        if (!($config['configured'] ?? false)) {
            return [
                'checked' => false,
                'ready' => false,
                'status_code' => 0,
                'detail' => 'No OpenAI API key is configured yet.',
                'error_type' => null,
                'error_code' => null,
            ];
        }

        $response = kwekwe_openai_request($config, [
            'model' => $config['model'],
            'store' => false,
            'instructions' => 'Reply with OK only.',
            'input' => 'OK',
            'max_output_tokens' => 16,
        ], 20);

        if (!$response['ok']) {
            $error = kwekwe_openai_response_error($response);
            return [
                'checked' => true,
                'ready' => false,
                'status_code' => $error['status_code'],
                'detail' => $error['message'],
                'error_type' => $error['type'],
                'error_code' => $error['code'],
            ];
        }

        $payload = json_decode((string) ($response['body'] ?? ''), true);
        if (!is_array($payload)) {
            return [
                'checked' => true,
                'ready' => false,
                'status_code' => (int) ($response['status'] ?? 0),
                'detail' => 'OpenAI returned an unreadable response payload.',
                'error_type' => null,
                'error_code' => null,
            ];
        }

        $text = kwekwe_extract_openai_text($payload);
        if ($text === '') {
            return [
                'checked' => true,
                'ready' => false,
                'status_code' => (int) ($response['status'] ?? 0),
                'detail' => 'OpenAI returned an empty response during verification.',
                'error_type' => null,
                'error_code' => null,
            ];
        }

        return [
            'checked' => true,
            'ready' => true,
            'status_code' => (int) ($response['status'] ?? 0),
            'detail' => 'OpenAI fallback is ready.',
            'error_type' => null,
            'error_code' => null,
        ];
    }
}

if (!function_exists('kwekwe_fetch_openai_fallback')) {
    function kwekwe_fetch_openai_fallback(string $message, string $language = 'en', string $intent = 'general', array $context = []): ?array
    {
        $config = kwekwe_resolve_openai_config();
        if (!$config['configured']) {
            return null;
        }

        $response = kwekwe_openai_request($config, [
            'model' => $config['model'],
            'store' => false,
            'instructions' => kwekwe_openai_instructions($language),
            'input' => kwekwe_openai_input($message, $intent, $context),
        ]);

        if (!$response['ok']) {
            return null;
        }

        $payload = json_decode($response['body'], true);
        if (!is_array($payload)) {
            return null;
        }

        $text = kwekwe_extract_openai_text($payload);
        if ($text === '') {
            return null;
        }

        return [
            'response' => $text,
            'model' => $config['model'],
            'source' => 'openai',
        ];
    }
}
