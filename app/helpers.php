<?php

declare(strict_types=1);

if (!function_exists('kwekwe_project_root')) {
    function kwekwe_project_root(): string
    {
        return dirname(__DIR__);
    }
}

if (!function_exists('kwekwe_path')) {
    function kwekwe_path(string ...$segments): string
    {
        $path = kwekwe_project_root();
        foreach ($segments as $segment) {
            $path .= DIRECTORY_SEPARATOR . trim($segment, "\\/ \t\n\r\0\x0B");
        }

        return $path;
    }
}

if (!function_exists('kwekwe_ensure_directory')) {
    function kwekwe_ensure_directory(string $path): void
    {
        if (is_dir($path)) {
            return;
        }

        mkdir($path, 0777, true);
    }
}

if (!function_exists('kwekwe_load_env_file')) {
    function kwekwe_load_env_file(string $path): void
    {
        static $loaded = [];

        if (isset($loaded[$path]) || !is_file($path)) {
            return;
        }

        $lines = file($path, FILE_IGNORE_NEW_LINES | FILE_SKIP_EMPTY_LINES) ?: [];
        foreach ($lines as $line) {
            $line = trim($line);
            if ($line === '' || str_starts_with($line, '#') || !str_contains($line, '=')) {
                continue;
            }

            [$key, $value] = explode('=', $line, 2);
            $key = trim($key);
            $value = trim(trim($value), "\"'");

            if ($key !== '' && getenv($key) === false) {
                putenv($key . '=' . $value);
                $_ENV[$key] = $value;
                $_SERVER[$key] = $value;
            }
        }

        $loaded[$path] = true;
    }
}

if (!function_exists('kwekwe_json_input')) {
    function kwekwe_json_input(): array
    {
        $raw = file_get_contents('php://input');
        if ($raw === false || trim($raw) === '') {
            return [];
        }

        $decoded = json_decode($raw, true);
        return is_array($decoded) ? $decoded : [];
    }
}

if (!function_exists('kwekwe_send_cors_headers')) {
    function kwekwe_send_cors_headers(): void
    {
        header('Access-Control-Allow-Origin: *');
        header('Access-Control-Allow-Methods: GET, POST, OPTIONS');
        header('Access-Control-Allow-Headers: Content-Type, Authorization, X-Requested-With');
        header('Access-Control-Max-Age: 86400');
    }
}

if (!function_exists('kwekwe_handle_preflight')) {
    function kwekwe_handle_preflight(): void
    {
        kwekwe_send_cors_headers();

        if (($_SERVER['REQUEST_METHOD'] ?? 'GET') === 'OPTIONS') {
            http_response_code(204);
            exit;
        }
    }
}

if (!function_exists('kwekwe_json_response')) {
    function kwekwe_json_response(array $payload, int $status = 200): never
    {
        kwekwe_send_cors_headers();
        http_response_code($status);
        header('Content-Type: application/json; charset=utf-8');
        echo json_encode($payload, JSON_PRETTY_PRINT | JSON_UNESCAPED_SLASHES | JSON_UNESCAPED_UNICODE);
        exit;
    }
}

if (!function_exists('kwekwe_method_not_allowed')) {
    function kwekwe_method_not_allowed(string $allowed): never
    {
        header('Allow: ' . $allowed);
        kwekwe_json_response(['detail' => 'Method not allowed'], 405);
    }
}

if (!function_exists('kwekwe_read_json_file')) {
    function kwekwe_read_json_file(string $path, array $fallback = []): array
    {
        if (!is_file($path)) {
            return $fallback;
        }

        $decoded = json_decode((string) file_get_contents($path), true);
        return is_array($decoded) ? $decoded : $fallback;
    }
}

if (!function_exists('kwekwe_write_json_file')) {
    function kwekwe_write_json_file(string $path, array $payload): void
    {
        kwekwe_ensure_directory(dirname($path));
        file_put_contents(
            $path,
            json_encode($payload, JSON_PRETTY_PRINT | JSON_UNESCAPED_SLASHES | JSON_UNESCAPED_UNICODE)
        );
    }
}

if (!function_exists('kwekwe_append_jsonl')) {
    function kwekwe_append_jsonl(string $path, array $payload): void
    {
        kwekwe_ensure_directory(dirname($path));
        file_put_contents(
            $path,
            json_encode($payload, JSON_UNESCAPED_SLASHES | JSON_UNESCAPED_UNICODE) . PHP_EOL,
            FILE_APPEND | LOCK_EX
        );
    }
}

if (!function_exists('kwekwe_read_jsonl')) {
    function kwekwe_read_jsonl(string $path, ?int $limit = null): array
    {
        if (!is_file($path)) {
            return [];
        }

        $lines = file($path, FILE_IGNORE_NEW_LINES | FILE_SKIP_EMPTY_LINES) ?: [];
        if ($limit !== null && $limit > 0 && count($lines) > $limit) {
            $lines = array_slice($lines, -$limit);
        }

        $records = [];
        foreach ($lines as $line) {
            $decoded = json_decode($line, true);
            if (is_array($decoded)) {
                $records[] = $decoded;
            }
        }

        return $records;
    }
}

if (!function_exists('kwekwe_normalize_text')) {
    function kwekwe_normalize_text(string $text): string
    {
        $text = function_exists('mb_strtolower') ? mb_strtolower($text, 'UTF-8') : strtolower($text);
        $text = preg_replace('/[^\p{L}\p{N}\s]+/u', ' ', $text) ?? $text;
        $text = preg_replace('/\s+/u', ' ', $text) ?? $text;

        return trim($text);
    }
}

if (!function_exists('kwekwe_tokenize')) {
    function kwekwe_tokenize(string $text): array
    {
        static $stopwords = [
            'a', 'an', 'and', 'are', 'at', 'be', 'by', 'can', 'do', 'for', 'from', 'how', 'i', 'if',
            'in', 'is', 'it', 'me', 'my', 'of', 'on', 'or', 'please', 'tell', 'that', 'the', 'their',
            'there', 'this', 'to', 'what', 'when', 'where', 'which', 'who', 'why', 'with', 'you', 'your',
            'kwekwe', 'poly', 'polytechnic',
        ];

        $normalized = kwekwe_normalize_text($text);
        if ($normalized === '') {
            return [];
        }

        $parts = preg_split('/\s+/u', $normalized) ?: [];
        $tokens = [];
        foreach ($parts as $part) {
            $length = function_exists('mb_strlen') ? mb_strlen($part, 'UTF-8') : strlen($part);
            if ($length < 3 && !in_array($part, ['it', 'nd', 'nc', 'hnd'], true)) {
                continue;
            }
            if (in_array($part, $stopwords, true)) {
                continue;
            }
            $tokens[$part] = true;
        }

        return array_keys($tokens);
    }
}

if (!function_exists('kwekwe_token_variants')) {
    function kwekwe_token_variants(string $token): array
    {
        $token = kwekwe_normalize_text($token);
        if ($token === '') {
            return [];
        }

        $variants = [$token => true];

        if (preg_match('/ies$/u', $token) === 1) {
            $variants[preg_replace('/ies$/u', 'y', $token) ?? $token] = true;
        }
        if (preg_match('/ing$/u', $token) === 1) {
            $variants[preg_replace('/ing$/u', '', $token) ?? $token] = true;
        }
        if (preg_match('/ed$/u', $token) === 1) {
            $variants[preg_replace('/ed$/u', '', $token) ?? $token] = true;
        }
        if (preg_match('/es$/u', $token) === 1 && strlen($token) > 4) {
            $variants[preg_replace('/es$/u', '', $token) ?? $token] = true;
        }
        if (preg_match('/s$/u', $token) === 1 && strlen($token) > 4) {
            $variants[preg_replace('/s$/u', '', $token) ?? $token] = true;
        }

        if (str_contains($token, 'programme')) {
            $variants[str_replace('programme', 'program', $token)] = true;
            $variants[str_replace('programmes', 'programs', $token)] = true;
        }
        if (str_contains($token, 'program')) {
            $variants[str_replace('program', 'programme', $token)] = true;
            $variants[str_replace('programs', 'programmes', $token)] = true;
        }

        return array_values(array_filter(array_keys($variants), static fn (string $value): bool => $value !== ''));
    }
}

if (!function_exists('kwekwe_text_matches_token')) {
    function kwekwe_text_matches_token(string $text, string $token): bool
    {
        $haystack = kwekwe_normalize_text($text);
        if ($haystack === '') {
            return false;
        }

        foreach (kwekwe_token_variants($token) as $variant) {
            if ($variant !== '' && str_contains($haystack, $variant)) {
                return true;
            }
        }

        return false;
    }
}

if (!function_exists('kwekwe_excerpt')) {
    function kwekwe_excerpt(string $text, int $limit = 240): string
    {
        $text = trim(preg_replace('/\s+/u', ' ', strip_tags($text)) ?? $text);
        $length = function_exists('mb_strlen') ? mb_strlen($text, 'UTF-8') : strlen($text);
        if ($length <= $limit) {
            return $text;
        }

        $slice = function_exists('mb_substr')
            ? mb_substr($text, 0, $limit - 1, 'UTF-8')
            : substr($text, 0, $limit - 1);

        return rtrim($slice) . '...';
    }
}

if (!function_exists('kwekwe_slugify')) {
    function kwekwe_slugify(string $text): string
    {
        $normalized = kwekwe_normalize_text($text);
        $slug = preg_replace('/[^a-z0-9]+/', '-', $normalized) ?? $normalized;
        $slug = trim($slug, '-');

        return $slug !== '' ? $slug : 'item';
    }
}

if (!function_exists('kwekwe_current_timestamp')) {
    function kwekwe_current_timestamp(): string
    {
        return gmdate('c');
    }
}

if (!function_exists('kwekwe_random_session_id')) {
    function kwekwe_random_session_id(): string
    {
        return 'session_' . bin2hex(random_bytes(8));
    }
}
