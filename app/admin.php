<?php

declare(strict_types=1);

require_once __DIR__ . '/chat.php';

if (!function_exists('kwekwe_start_session')) {
    function kwekwe_start_session(): void
    {
        if (session_status() === PHP_SESSION_ACTIVE) {
            return;
        }

        session_name('kwekwe_admin');
        session_start([
            'cookie_httponly' => true,
            'cookie_samesite' => 'Lax',
            'use_strict_mode' => true,
        ]);
    }
}

if (!function_exists('kwekwe_is_admin')) {
    function kwekwe_is_admin(): bool
    {
        kwekwe_start_session();
        return !empty($_SESSION['kwekwe_admin_authenticated']);
    }
}

if (!function_exists('kwekwe_require_admin')) {
    function kwekwe_require_admin(): void
    {
        if (!kwekwe_is_admin()) {
            kwekwe_json_response([
                'authenticated' => false,
                'login_required' => true,
                'detail' => 'Admin sign-in required.',
            ], 401);
        }
    }
}

if (!function_exists('kwekwe_admin_login')) {
    function kwekwe_admin_login(string $key): array
    {
        kwekwe_start_session();
        if (!hash_equals(kwekwe_config()['admin_key'], trim($key))) {
            return [
                'authenticated' => false,
                'detail' => 'Invalid admin key.',
            ];
        }

        $_SESSION['kwekwe_admin_authenticated'] = true;
        $_SESSION['kwekwe_admin_issued_at'] = time();

        return kwekwe_admin_session_state();
    }
}

if (!function_exists('kwekwe_admin_logout')) {
    function kwekwe_admin_logout(): array
    {
        kwekwe_start_session();
        $_SESSION = [];
        session_destroy();

        return [
            'authenticated' => false,
            'detail' => 'Admin session cleared.',
        ];
    }
}

if (!function_exists('kwekwe_admin_session_state')) {
    function kwekwe_admin_session_state(): array
    {
        kwekwe_start_session();
        $authenticated = kwekwe_is_admin();

        return [
            'authenticated' => $authenticated,
            'method' => $authenticated ? 'php-session' : null,
            'issued_at' => isset($_SESSION['kwekwe_admin_issued_at'])
                ? gmdate('c', (int) $_SESSION['kwekwe_admin_issued_at'])
                : null,
            'login_required' => !$authenticated,
        ];
    }
}

if (!function_exists('kwekwe_admin_analytics_summary')) {
    function kwekwe_admin_analytics_summary(int $limit = 20): array
    {
        $events = kwekwe_read_jsonl(kwekwe_config()['storage']['analytics']);
        $intentCounts = [];
        $languages = [];
        $strategyCounts = [];
        $sourceCounts = [];
        $handoffCounts = [];
        $evidenceCounts = [];
        $answerModeCounts = [];
        $queryTypeCounts = [];
        $evidenceTotal = 0.0;
        $evidenceSamples = 0;
        $decisionEventCount = 0;
        $localAnswers = 0;
        $escalatedQueries = 0;
        $openAiFallbacks = 0;
        $verifiedHandoffOnly = 0;
        $verificationRequired = 0;
        $lowEvidence = 0;

        $increment = static function (array &$counts, string $key): void {
            $label = trim($key) !== '' ? trim($key) : 'unknown';
            $counts[$label] = ($counts[$label] ?? 0) + 1;
        };

        $normalizeDecisionEvent = static function (array $event): array {
            $missingSpecificTokens = array_values(array_filter(
                array_map(static fn ($value): string => trim((string) $value), (array) ($event['missing_specific_tokens'] ?? [])),
                static fn (string $value): bool => $value !== ''
            ));

            return [
                'timestamp' => $event['timestamp'] ?? null,
                'session_id' => $event['session_id'] ?? null,
                'query' => trim((string) ($event['query'] ?? '')),
                'intent' => trim((string) ($event['intent'] ?? 'general')) ?: 'general',
                'language' => trim((string) ($event['language'] ?? 'en')) ?: 'en',
                'query_type' => trim((string) ($event['query_type'] ?? 'unknown')) ?: 'unknown',
                'answer_mode' => trim((string) ($event['answer_mode'] ?? 'unknown')) ?: 'unknown',
                'decision_strategy' => trim((string) ($event['decision_strategy'] ?? 'unknown')) ?: 'unknown',
                'decision_source' => trim((string) ($event['decision_source'] ?? 'unknown')) ?: 'unknown',
                'handoff_mode' => trim((string) ($event['handoff_mode'] ?? 'none')) ?: 'none',
                'handoff_office' => trim((string) ($event['handoff_office'] ?? '')) ?: null,
                'evidence_score' => is_numeric($event['evidence_score'] ?? null) ? (int) round((float) $event['evidence_score']) : null,
                'evidence_label' => trim((string) ($event['evidence_label'] ?? 'unknown')) ?: 'unknown',
                'local_coverage' => is_numeric($event['local_coverage'] ?? null) ? (float) $event['local_coverage'] : null,
                'section_focus' => is_numeric($event['section_focus'] ?? null) ? (float) $event['section_focus'] : null,
                'category_consistency' => is_numeric($event['category_consistency'] ?? null) ? (float) $event['category_consistency'] : null,
                'missing_specific_tokens' => $missingSpecificTokens,
                'verification_required' => (bool) ($event['verification_required'] ?? false),
                'result_count' => isset($event['result_count']) ? (int) $event['result_count'] : null,
                'top_score' => is_numeric($event['top_score'] ?? null) ? round((float) $event['top_score'], 2) : null,
                'openai_used' => (bool) ($event['openai_used'] ?? false),
                'openai_model' => trim((string) ($event['openai_model'] ?? '')) ?: null,
            ];
        };

        foreach ($events as $event) {
            $intent = $event['intent'] ?? 'general';
            $language = $event['language'] ?? 'en';
            $intentCounts[$intent] = ($intentCounts[$intent] ?? 0) + 1;
            $languages[$language] = ($languages[$language] ?? 0) + 1;

            $hasDecisionMetrics = array_key_exists('decision_strategy', $event)
                || array_key_exists('decision_source', $event)
                || array_key_exists('answer_mode', $event)
                || array_key_exists('handoff_mode', $event)
                || array_key_exists('evidence_score', $event);

            if (!$hasDecisionMetrics) {
                continue;
            }

            $decisionEventCount++;

            $decisionStrategy = trim((string) ($event['decision_strategy'] ?? ''));
            $decisionSource = trim((string) ($event['decision_source'] ?? ''));
            $handoffMode = trim((string) ($event['handoff_mode'] ?? 'none')) ?: 'none';
            $evidenceLabel = trim((string) ($event['evidence_label'] ?? ''));
            $answerMode = trim((string) ($event['answer_mode'] ?? ''));
            $queryType = trim((string) ($event['query_type'] ?? ''));

            if ($decisionStrategy !== '') {
                $increment($strategyCounts, $decisionStrategy);
            }
            if ($decisionSource !== '') {
                $increment($sourceCounts, $decisionSource);
            }
            $increment($handoffCounts, $handoffMode);
            if ($evidenceLabel !== '') {
                $increment($evidenceCounts, $evidenceLabel);
            }
            if ($answerMode !== '') {
                $increment($answerModeCounts, $answerMode);
            }
            if ($queryType !== '') {
                $increment($queryTypeCounts, $queryType);
            }

            if (is_numeric($event['evidence_score'] ?? null)) {
                $evidenceScore = (float) $event['evidence_score'];
                $evidenceTotal += $evidenceScore;
                $evidenceSamples++;
                if ($evidenceScore < 60) {
                    $lowEvidence++;
                }
            }

            if (!empty($event['verification_required'])) {
                $verificationRequired++;
            }

            if ($decisionSource === 'local-answer' || $answerMode === 'local-knowledge') {
                $localAnswers++;
            }

            if ($handoffMode !== 'none') {
                $escalatedQueries++;
            }

            if (
                !empty($event['openai_used'])
                || $queryType === 'openai-fallback'
                || $decisionSource === 'openai-fallback'
            ) {
                $openAiFallbacks++;
            }

            if ($answerMode === 'verified-handoff' || $queryType === 'verified-handoff') {
                $verifiedHandoffOnly++;
            }
        }

        arsort($intentCounts);
        arsort($languages);
        arsort($strategyCounts);
        arsort($sourceCounts);
        arsort($handoffCounts);
        arsort($evidenceCounts);
        arsort($answerModeCounts);
        arsort($queryTypeCounts);

        $recentEvents = array_values(array_filter(
            array_reverse($events),
            static fn (array $event): bool => array_key_exists('decision_strategy', $event)
                || array_key_exists('decision_source', $event)
                || array_key_exists('answer_mode', $event)
                || array_key_exists('handoff_mode', $event)
                || array_key_exists('evidence_score', $event)
        ));
        $recentEvents = array_slice($recentEvents, 0, max(1, $limit));
        $recentDecisions = array_map($normalizeDecisionEvent, $recentEvents);

        return [
            'status' => 'ok',
            'total_queries' => count($events),
            'intent_breakdown' => $intentCounts,
            'language_breakdown' => $languages,
            'recent_queries' => array_slice($recentEvents, 0, min(10, count($recentEvents))),
            'decision_summary' => [
                'decision_event_count' => $decisionEventCount,
                'average_evidence_score' => $evidenceSamples > 0 ? round($evidenceTotal / $evidenceSamples, 1) : null,
                'local_answer_count' => $localAnswers,
                'escalated_query_count' => $escalatedQueries,
                'openai_fallback_count' => $openAiFallbacks,
                'verified_handoff_only_count' => $verifiedHandoffOnly,
                'verification_required_count' => $verificationRequired,
                'low_evidence_count' => $lowEvidence,
            ],
            'decision_breakdowns' => [
                'strategy' => $strategyCounts,
                'source' => $sourceCounts,
                'handoff_mode' => $handoffCounts,
                'evidence_label' => $evidenceCounts,
                'answer_mode' => $answerModeCounts,
                'query_type' => $queryTypeCounts,
            ],
            'recent_decisions' => $recentDecisions,
        ];
    }
}

if (!function_exists('kwekwe_admin_recent_feedback')) {
    function kwekwe_admin_recent_feedback(int $limit = 25): array
    {
        $records = kwekwe_read_jsonl(kwekwe_config()['storage']['feedback']);
        return array_slice(array_reverse($records), 0, $limit);
    }
}

if (!function_exists('kwekwe_admin_gap_reason')) {
    function kwekwe_admin_gap_reason(array $item): string
    {
        if (($item['not_helpful_count'] ?? 0) > 0 && ($item['fallback_count'] ?? 0) > 0) {
            return 'Users asked this repeatedly and the bot still fell back or got negative feedback.';
        }

        if (($item['not_helpful_count'] ?? 0) > 0) {
            return 'This answer has been marked as needing work by users.';
        }

        if (($item['openai_fallback_count'] ?? 0) > 0) {
            return 'The bot currently depends on ChatGPT fallback for this topic.';
        }

        if (($item['fallback_count'] ?? 0) > 0) {
            return 'There is no strong local answer for this topic yet.';
        }

        return 'The chatbot found only weak local matches and needs better local knowledge.';
    }
}

if (!function_exists('kwekwe_admin_gap_action')) {
    function kwekwe_admin_gap_action(array $item): string
    {
        $intent = trim((string) ($item['intent'] ?? 'general')) ?: 'general';

        return match ($intent) {
            'admissions' => 'Add a short admissions knowledge note with steps, required documents, deadlines, and the official apply link.',
            'fees' => 'Add or refresh a fees note with verified payment methods, account details, and who to contact for confirmation.',
            'portal' => 'Add a portal help note covering login steps, password recovery, and ICT support contacts.',
            'accommodation' => 'Add an accommodation note covering hostels, availability limits, payment cautions, and Student Affairs contacts.',
            'exams' => 'Add an examinations note with HEXCO guidance, result follow-up steps, and official exam contacts.',
            default => 'Create or expand a local knowledge note that answers this question directly and includes the official follow-up route.',
        };
    }
}

if (!function_exists('kwekwe_admin_knowledge_gap_inbox')) {
    function kwekwe_admin_knowledge_gap_inbox(int $limit = 25): array
    {
        $events = kwekwe_read_jsonl(kwekwe_config()['storage']['analytics']);
        $feedbackRecords = kwekwe_read_jsonl(kwekwe_config()['storage']['feedback']);
        $items = [];

        $seed = static function (string $query, string $intent = 'general') use (&$items): string {
            $key = kwekwe_normalize_text($query);
            if ($key === '') {
                return '';
            }

            if (!isset($items[$key])) {
                $items[$key] = [
                    'query' => $query,
                    'intent' => $intent,
                    'occurrence_count' => 0,
                    'fallback_count' => 0,
                    'openai_fallback_count' => 0,
                    'weak_match_count' => 0,
                    'not_helpful_count' => 0,
                    'latest_query_type' => null,
                    'latest_top_score' => null,
                    'last_seen' => null,
                    'languages' => [],
                    'query_types' => [],
                    'sessions' => [],
                ];
            }

            return $key;
        };

        foreach ($events as $event) {
            $query = trim((string) ($event['query'] ?? ''));
            $intent = trim((string) ($event['intent'] ?? 'general')) ?: 'general';
            if ($query === '') {
                continue;
            }

            $detectedIntent = kwekwe_detect_intent($query);
            if ($detectedIntent === 'greeting') {
                continue;
            }
            if ($intent === 'general' && $detectedIntent !== 'general') {
                $intent = $detectedIntent;
            }

            $queryType = trim((string) ($event['query_type'] ?? ''));
            $resultCount = (int) ($event['result_count'] ?? 0);
            $topScore = (float) ($event['top_score'] ?? 0.0);
            $isFallback = in_array($queryType, ['fallback', 'openai-fallback', 'verified-handoff'], true) || $resultCount === 0;
            $isOpenAiFallback = $queryType === 'openai-fallback' || !empty($event['openai_used']);
            $isWeakMatch = !$isFallback && $topScore > 0 && $topScore < 6.0;

            if (!$isFallback && !$isWeakMatch) {
                continue;
            }

            $key = $seed($query, $intent);
            if ($key === '') {
                continue;
            }

            $items[$key]['query'] = $query;
            $items[$key]['intent'] = $intent;
            $items[$key]['occurrence_count']++;
            $items[$key]['latest_query_type'] = $queryType !== '' ? $queryType : ($isFallback ? 'fallback' : 'local-search');
            $items[$key]['latest_top_score'] = $topScore;
            $items[$key]['languages'][trim((string) ($event['language'] ?? 'en')) ?: 'en'] = true;
            $items[$key]['query_types'][$items[$key]['latest_query_type']] = true;
            $sessionId = trim((string) ($event['session_id'] ?? ''));
            if ($sessionId !== '') {
                $items[$key]['sessions'][$sessionId] = true;
            }

            if ($isFallback) {
                $items[$key]['fallback_count']++;
            }
            if ($isOpenAiFallback) {
                $items[$key]['openai_fallback_count']++;
            }
            if ($isWeakMatch) {
                $items[$key]['weak_match_count']++;
            }

            $timestamp = trim((string) ($event['timestamp'] ?? ''));
            if ($timestamp !== '' && ($items[$key]['last_seen'] === null || strtotime($timestamp) > strtotime((string) $items[$key]['last_seen']))) {
                $items[$key]['last_seen'] = $timestamp;
            }
        }

        foreach ($feedbackRecords as $record) {
            if ((bool) ($record['helpful'] ?? false)) {
                continue;
            }

            $query = trim((string) ($record['user_query'] ?? ''));
            $intent = trim((string) ($record['intent'] ?? 'general')) ?: 'general';
            if ($query === '') {
                continue;
            }

            $detectedIntent = kwekwe_detect_intent($query);
            if ($detectedIntent === 'greeting') {
                continue;
            }
            if ($intent === 'general' && $detectedIntent !== 'general') {
                $intent = $detectedIntent;
            }

            $key = $seed($query, $intent);
            if ($key === '') {
                continue;
            }

            $items[$key]['query'] = $query;
            $items[$key]['intent'] = $intent;
            $items[$key]['not_helpful_count']++;

            $queryType = trim((string) ($record['query_type'] ?? ''));
            if ($queryType !== '') {
                $items[$key]['latest_query_type'] = $queryType;
                $items[$key]['query_types'][$queryType] = true;
            }

            $language = trim((string) ($record['language'] ?? ''));
            if ($language !== '') {
                $items[$key]['languages'][$language] = true;
            }

            $sessionId = trim((string) ($record['session_id'] ?? ''));
            if ($sessionId !== '') {
                $items[$key]['sessions'][$sessionId] = true;
            }

            $timestamp = trim((string) ($record['timestamp'] ?? ''));
            if ($timestamp !== '' && ($items[$key]['last_seen'] === null || strtotime($timestamp) > strtotime((string) $items[$key]['last_seen']))) {
                $items[$key]['last_seen'] = $timestamp;
            }
        }

        foreach ($items as &$item) {
            $item['language_count'] = count($item['languages']);
            $item['session_count'] = count($item['sessions']);
            $item['languages'] = array_values(array_keys($item['languages']));
            $item['query_types'] = array_values(array_keys($item['query_types']));
            unset($item['sessions']);

            $item['severity_score'] = ($item['not_helpful_count'] * 5)
                + ($item['openai_fallback_count'] * 3)
                + ($item['fallback_count'] * 2)
                + $item['weak_match_count']
                + $item['occurrence_count'];

            $item['priority'] = match (true) {
                $item['severity_score'] >= 10 => 'high',
                $item['severity_score'] >= 5 => 'medium',
                default => 'low',
            };
            $item['reason'] = kwekwe_admin_gap_reason($item);
            $item['suggested_action'] = kwekwe_admin_gap_action($item);
        }
        unset($item);

        uasort($items, static function (array $left, array $right): int {
            return ($right['severity_score'] <=> $left['severity_score'])
                ?: (($right['occurrence_count'] ?? 0) <=> ($left['occurrence_count'] ?? 0))
                ?: strcmp((string) ($right['last_seen'] ?? ''), (string) ($left['last_seen'] ?? ''));
        });

        $records = array_values(array_slice($items, 0, max(1, $limit)));

        return [
            'status' => 'ok',
            'summary' => [
                'total_items' => count($items),
                'high_priority' => count(array_filter($items, static fn (array $item): bool => ($item['priority'] ?? 'low') === 'high')),
                'chatgpt_reliant' => count(array_filter($items, static fn (array $item): bool => ($item['openai_fallback_count'] ?? 0) > 0)),
                'negative_feedback' => count(array_filter($items, static fn (array $item): bool => ($item['not_helpful_count'] ?? 0) > 0)),
            ],
            'records' => $records,
        ];
    }
}

if (!function_exists('kwekwe_admin_documents')) {
    function kwekwe_admin_documents(): array
    {
        $index = kwekwe_load_knowledge_index();
        return [
            'generated_at' => $index['generated_at'] ?? null,
            'document_count' => $index['document_count'] ?? 0,
            'chunk_count' => $index['chunk_count'] ?? 0,
            'documents' => $index['documents'] ?? [],
        ];
    }
}

if (!function_exists('kwekwe_admin_openai_status')) {
    function kwekwe_admin_openai_status(bool $refresh = false): array
    {
        return kwekwe_openai_status($refresh);
    }
}

if (!function_exists('kwekwe_admin_save_openai_key')) {
    function kwekwe_admin_save_openai_key(string $apiKey): array
    {
        return kwekwe_save_openai_key($apiKey);
    }
}

if (!function_exists('kwekwe_admin_reset_openai_key')) {
    function kwekwe_admin_reset_openai_key(): array
    {
        return kwekwe_reset_openai_key_source();
    }
}

if (!function_exists('kwekwe_admin_disable_openai_fallback')) {
    function kwekwe_admin_disable_openai_fallback(): array
    {
        return kwekwe_disable_openai_fallback();
    }
}

if (!function_exists('kwekwe_admin_rebuild_index')) {
    function kwekwe_admin_rebuild_index(): array
    {
        $index = kwekwe_save_knowledge_index(kwekwe_build_knowledge_index());
        return [
            'status' => 'ok',
            'detail' => 'Knowledge index rebuilt successfully.',
            'generated_at' => $index['generated_at'],
            'document_count' => $index['document_count'],
            'chunk_count' => $index['chunk_count'],
        ];
    }
}

if (!function_exists('kwekwe_admin_upload_documents')) {
    function kwekwe_admin_upload_documents(array $files): array
    {
        $uploaded = [];
        $targetDirectory = kwekwe_path('storage', 'uploads');
        kwekwe_ensure_directory($targetDirectory);

        $fileBag = $files['documents'] ?? $files['documents[]'] ?? null;

        if ($fileBag === null) {
            return [
                'status' => 'error',
                'detail' => 'No files uploaded.',
                'uploaded' => [],
            ];
        }

        $names = (array) ($fileBag['name'] ?? []);
        $tmpNames = (array) ($fileBag['tmp_name'] ?? []);
        $errors = (array) ($fileBag['error'] ?? []);

        foreach ($names as $index => $name) {
            $error = $errors[$index] ?? UPLOAD_ERR_NO_FILE;
            if ($error !== UPLOAD_ERR_OK) {
                continue;
            }

            $extension = strtolower(pathinfo($name, PATHINFO_EXTENSION));
            if (!in_array($extension, ['md', 'txt'], true)) {
                continue;
            }

            $safeName = kwekwe_slugify(pathinfo($name, PATHINFO_FILENAME)) . '.' . $extension;
            $destination = $targetDirectory . DIRECTORY_SEPARATOR . $safeName;
            move_uploaded_file((string) $tmpNames[$index], $destination);

            $uploaded[] = [
                'filename' => $safeName,
                'path' => 'storage/uploads/' . $safeName,
            ];
        }

        $rebuild = kwekwe_admin_rebuild_index();

        return [
            'status' => 'ok',
            'detail' => $uploaded === [] ? 'No valid files were uploaded.' : 'Files uploaded and indexed.',
            'uploaded' => $uploaded,
            'index' => $rebuild,
        ];
    }
}
