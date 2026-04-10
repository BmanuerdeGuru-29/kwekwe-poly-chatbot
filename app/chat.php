<?php

declare(strict_types=1);

require_once __DIR__ . '/knowledge.php';

if (!function_exists('kwekwe_detect_intent')) {
    function kwekwe_detect_intent(string $message): string
    {
        $text = kwekwe_normalize_text($message);

        return match (true) {
            // Recognise mission and vision queries separately so they are not mistaken for admissions.
            preg_match('/\b(mission|vision)\b/u', $text) === 1 => 'general',
            preg_match('/^(hi|hie|hiya|hello|hey|greetings?|good (morning|afternoon|evening|day)|howdy|sup|what\'?s up|how are you|how are u|thank you|thanks|bye|goodbye|see you)\b/ui', $text) === 1 => 'greeting',
            preg_match('/fee|payment|cost|tuition|bank|ecocash|onemoney|paynow/u', $text) === 1 => 'fees',
            preg_match('/apply|application|admission|intake|requirement|register/u', $text) === 1 => 'admissions',
            preg_match('/engineering|automotive|mechanical|electrical|civil/u', $text) === 1 => 'engineering',
            preg_match('/commerce|business|management|accountancy|banking|finance/u', $text) === 1 => 'commerce',
            preg_match('/applied science|information technology|biological|physical science|metallurgy/u', $text) === 1 => 'applied_sciences',
            preg_match('/btech|b tech|degree|industrial and manufacturing|electrical power/u', $text) === 1 => 'btech',
            preg_match('/adult|continuing education|ace|cosmetology|tourism|textile/u', $text) === 1 => 'ace',
            preg_match('/portal|elearning|e learning|ict|password|login/u', $text) === 1 => 'portal',
            preg_match('/hostel|accommodation|welfare|student affairs/u', $text) === 1 => 'accommodation',
            preg_match('/hexco|exam|result|examination|certificate/u', $text) === 1 => 'exams',
            preg_match('/contact|phone|email|whatsapp|office/u', $text) === 1 => 'contact',
            default => 'general',
        };
    }
}

if (!function_exists('kwekwe_default_actions')) {
    function kwekwe_default_actions(): array
    {
        $links = kwekwe_config()['links'];

        return [
            ['label' => 'Apply Online', 'type' => 'link', 'url' => $links['apply']],
            ['label' => 'Portal Help', 'type' => 'prompt', 'prompt' => 'How do I access the student portal and get ICT help?'],
            ['label' => 'Fees Guide', 'type' => 'prompt', 'prompt' => 'Show me the fee payment options in USD and ZiG.'],
        ];
    }
}

if (!function_exists('kwekwe_actions_for_intent')) {
    function kwekwe_actions_for_intent(string $intent): array
    {
        $links = kwekwe_config()['links'];

        return match ($intent) {
            'fees' => [
                ['label' => 'Apply Online', 'type' => 'link', 'url' => $links['apply']],
                ['label' => 'Admissions', 'type' => 'prompt', 'prompt' => 'What documents do I need to apply?'],
            ],
            'portal' => [
                ['label' => 'Open Portal', 'type' => 'link', 'url' => $links['portal']],
                ['label' => 'ICT Help', 'type' => 'prompt', 'prompt' => 'How do I get ICT help for the student portal?'],
            ],
            'accommodation' => [
                ['label' => 'Hostel Page', 'type' => 'link', 'url' => $links['hostel']],
                ['label' => 'Student Affairs', 'type' => 'prompt', 'prompt' => 'How do I contact student affairs about accommodation?'],
            ],
            default => kwekwe_default_actions(),
        };
    }
}

if (!function_exists('kwekwe_handoff_for_intent')) {
    function kwekwe_handoff_for_intent(string $intent): ?array
    {
        $contacts = kwekwe_config()['contacts'];

        return match ($intent) {
            'portal' => [
                'office' => 'ICT Support',
                'message' => "Use the student portal and official ICT support channels for account-specific issues.\n- Portal: " . kwekwe_config()['links']['portal'] . "\n- Phone: {$contacts['phone']}\n- Email: {$contacts['email']}",
            ],
            'accommodation' => [
                'office' => 'Student Affairs',
                'message' => "Accommodation is limited, so confirm the latest availability and charges with Student Affairs before paying.\n- Hostel page: " . kwekwe_config()['links']['hostel'] . "\n- Phone: {$contacts['phone']}\n- WhatsApp: {$contacts['whatsapp']}",
            ],
            'admissions', 'fees' => [
                'office' => 'Admissions and Accounts',
                'message' => "Use the official application and accounts channels for the latest deadlines, balances, and payment confirmations.\n- Apply: " . kwekwe_config()['links']['apply'] . "\n- Phone: {$contacts['phone']}\n- Email: {$contacts['email']}",
            ],
            default => null,
        };
    }
}

if (!function_exists('kwekwe_handoff_directory')) {
    function kwekwe_handoff_directory(): array
    {
        $config = kwekwe_config();
        $contacts = $config['contacts'];
        $links = $config['links'];

        return [
            'admissions_accounts' => [
                'office' => 'Admissions and Accounts',
                'scope' => 'applications, intake dates, balances, fee confirmations, and payment verification',
                'message' => 'Use the official admissions and accounts channels for verified institution-specific details.',
                'recommended_action' => 'Open the admissions portal or contact the office below for the latest verified information.',
                'channels' => [
                    ['label' => 'Apply portal', 'value' => $links['apply'], 'url' => $links['apply']],
                    ['label' => 'Phone', 'value' => $contacts['phone']],
                    ['label' => 'Email', 'value' => $contacts['email']],
                ],
            ],
            'ict_support' => [
                'office' => 'ICT Support',
                'scope' => 'portal access, login issues, passwords, and account-specific technical support',
                'message' => 'Portal and login issues should be confirmed through the official ICT support route.',
                'recommended_action' => 'Try the student portal first, then contact ICT if your account issue continues.',
                'channels' => [
                    ['label' => 'Student portal', 'value' => $links['portal'], 'url' => $links['portal']],
                    ['label' => 'Phone', 'value' => $contacts['phone']],
                    ['label' => 'Email', 'value' => $contacts['email']],
                ],
            ],
            'student_affairs' => [
                'office' => 'Student Affairs',
                'scope' => 'hostels, accommodation availability, welfare, and student support follow-up',
                'message' => 'Accommodation and welfare details can change, so they should be confirmed through Student Affairs.',
                'recommended_action' => 'Confirm availability, charges, or accommodation arrangements with Student Affairs before paying.',
                'channels' => [
                    ['label' => 'Hostel page', 'value' => $links['hostel'], 'url' => $links['hostel']],
                    ['label' => 'Phone', 'value' => $contacts['phone']],
                    ['label' => 'WhatsApp', 'value' => $contacts['whatsapp']],
                ],
            ],
            'examinations_office' => [
                'office' => 'Examinations Office',
                'scope' => 'results, examination schedules, certificate collection, and office-hour confirmations',
                'message' => 'Exam schedules, results, and collection details should be confirmed through the official examinations route.',
                'recommended_action' => 'Contact the examinations office for the latest verified schedule or collection details.',
                'channels' => [
                    ['label' => 'Website', 'value' => $links['website'], 'url' => $links['website']],
                    ['label' => 'Phone', 'value' => $contacts['phone']],
                    ['label' => 'Email', 'value' => $contacts['email']],
                ],
            ],
            'main_office' => [
                'office' => 'Main Office',
                'scope' => 'general public enquiries, contact verification, and institution-wide follow-up',
                'message' => 'Use the official main office channels for a verified response on this topic.',
                'recommended_action' => 'Contact the main office using the official channels below.',
                'channels' => [
                    ['label' => 'Website', 'value' => $links['website'], 'url' => $links['website']],
                    ['label' => 'Phone', 'value' => $contacts['phone']],
                    ['label' => 'WhatsApp', 'value' => $contacts['whatsapp']],
                    ['label' => 'Email', 'value' => $contacts['email']],
                ],
            ],
        ];
    }
}

if (!function_exists('kwekwe_handoff_key_for_query')) {
    function kwekwe_handoff_key_for_query(string $query, string $intent, array $profile): ?string
    {
        $text = kwekwe_normalize_text($query);
        $focus = trim((string) ($profile['focus'] ?? 'general')) ?: 'general';

        return match (true) {
            $intent === 'portal' || $focus === 'portal' || preg_match('/portal|login|password|account/u', $text) === 1 => 'ict_support',
            $intent === 'accommodation' || preg_match('/hostel|accommodation|student affairs|welfare/u', $text) === 1 => 'student_affairs',
            $intent === 'fees' || $intent === 'admissions' || $focus === 'payments' || preg_match('/fees?|payment|apply|application|intake|deadline|balance/u', $text) === 1 => 'admissions_accounts',
            $intent === 'exams' || preg_match('/exam|hexco|results?|certificate|collection/u', $text) === 1 => 'examinations_office',
            $focus === 'contact' || $focus === 'location' => 'main_office',
            default => null,
        };
    }
}

if (!function_exists('kwekwe_question_needs_verified_follow_up')) {
    function kwekwe_question_needs_verified_follow_up(string $query, string $intent, array $profile): bool
    {
        $text = kwekwe_normalize_text($query);
        $focus = trim((string) ($profile['focus'] ?? 'general')) ?: 'general';

        if (in_array($intent, ['fees', 'admissions', 'portal', 'accommodation', 'exams'], true)) {
            return true;
        }

        if (in_array($focus, ['payments', 'portal', 'time'], true)) {
            return true;
        }

        return preg_match(
            '/\b(current|latest|today|now|this year|deadline|closing|opening|open|close|availability|available|balance|confirm|verify|status|results?)\b/u',
            $text
        ) === 1;
    }
}

if (!function_exists('kwekwe_section_focus_score')) {
    function kwekwe_section_focus_score(array $results, array $profile, string $intent): float
    {
        if ($results === []) {
            return 0.0;
        }

        $preferred = kwekwe_preferred_section_types($profile, $intent);
        if ($preferred === []) {
            return 0.0;
        }

        $matches = 0;
        $considered = 0;
        foreach (array_slice($results, 0, 4) as $result) {
            $considered++;
            if (in_array((string) ($result['section_type'] ?? kwekwe_chunk_section_type($result)), $preferred, true)) {
                $matches++;
            }
        }

        return $considered > 0 ? round($matches / $considered, 3) : 0.0;
    }
}

if (!function_exists('kwekwe_category_consistency_score')) {
    function kwekwe_category_consistency_score(array $results): float
    {
        if ($results === []) {
            return 0.0;
        }

        $counts = [];
        $considered = 0;
        foreach (array_slice($results, 0, 4) as $result) {
            $category = trim((string) ($result['category'] ?? 'general')) ?: 'general';
            $counts[$category] = ($counts[$category] ?? 0) + 1;
            $considered++;
        }

        if ($considered === 0) {
            return 0.0;
        }

        return round(max($counts) / $considered, 3);
    }
}

if (!function_exists('kwekwe_best_answer_signal')) {
    function kwekwe_best_answer_signal(string $query, string $intent, array $profile, array $results): float
    {
        $best = 0.0;
        foreach (array_slice(kwekwe_answer_candidates($results), 0, 30) as $candidate) {
            $best = max($best, kwekwe_answer_candidate_score($query, $intent, $profile, $candidate));
        }

        return round(min($best / 22.0, 1.0), 3);
    }
}

if (!function_exists('kwekwe_evidence_report')) {
    function kwekwe_evidence_report(string $query, string $intent, array $results, ?array $profile = null): array
    {
        $profile ??= kwekwe_question_profile($query, $intent);
        if ($results === []) {
            return [
                'score' => 0,
                'label' => 'low',
                'top_score' => 0.0,
                'top_support' => 0.0,
                'coverage' => 0.0,
                'section_focus' => 0.0,
                'category_consistency' => 0.0,
                'answer_signal' => 0.0,
                'verification_required' => kwekwe_question_needs_verified_follow_up($query, $intent, $profile),
                'profile' => $profile,
            ];
        }

        $topScore = (float) ($results[0]['score'] ?? 0.0);
        $support = kwekwe_local_result_support($query, $results[0]);
        $combined = kwekwe_local_results_support($query, $results, 4);
        $sectionFocus = kwekwe_section_focus_score($results, $profile, $intent);
        $categoryConsistency = kwekwe_category_consistency_score($results);
        $answerSignal = kwekwe_best_answer_signal($query, $intent, $profile, $results);
        $verificationRequired = kwekwe_question_needs_verified_follow_up($query, $intent, $profile);
        $missingSpecificTokens = kwekwe_missing_specific_tokens($query, $results, $profile, $intent, 4);

        $score = (
            min($topScore / 12.0, 1.0) * 0.18
            + min((float) ($support['ratio'] ?? 0.0), 1.0) * 0.2
            + min((float) ($combined['coverage'] ?? 0.0), 1.0) * 0.24
            + $sectionFocus * 0.16
            + $categoryConsistency * 0.1
            + $answerSignal * 0.12
        ) * 100;

        if ($verificationRequired && $score > 55) {
            $score -= 4;
        }
        if ($missingSpecificTokens !== []) {
            $score -= min(28, count($missingSpecificTokens) * 14);
        }

        $score = (int) round(max(0, min(100, $score)));

        return [
            'score' => $score,
            'label' => $score >= 78 ? 'high' : ($score >= 56 ? 'medium' : 'low'),
            'top_score' => round($topScore, 3),
            'top_support' => round((float) ($support['ratio'] ?? 0.0), 3),
            'coverage' => round((float) ($combined['coverage'] ?? 0.0), 3),
            'section_focus' => $sectionFocus,
            'category_consistency' => $categoryConsistency,
            'answer_signal' => $answerSignal,
            'missing_specific_tokens' => $missingSpecificTokens,
            'verification_required' => $verificationRequired,
            'profile' => $profile,
        ];
    }
}

if (!function_exists('kwekwe_response_decision')) {
    function kwekwe_response_decision(string $query, string $intent, array $results, ?array $profile = null, ?array $evidence = null): array
    {
        $profile ??= kwekwe_question_profile($query, $intent);
        $evidence ??= kwekwe_evidence_report($query, $intent, $results, $profile);
        $handoffKey = kwekwe_handoff_key_for_query($query, $intent, $profile);
        $handoffDirectory = kwekwe_handoff_directory();
        $handoffTarget = $handoffKey !== null ? ($handoffDirectory[$handoffKey] ?? null) : null;
        if ($handoffTarget === null && ($evidence['verification_required'] ?? false)) {
            $handoffKey = 'main_office';
            $handoffTarget = $handoffDirectory[$handoffKey] ?? null;
        }
        $canUseOpenAI = function_exists('kwekwe_resolve_openai_config') && kwekwe_resolve_openai_config()['configured'];
        $shouldUseOpenAI = kwekwe_should_use_openai_fallback($intent, $query, $results, $profile, $evidence);
        $handoffMode = 'none';
        if ($handoffTarget !== null) {
            if (($evidence['verification_required'] ?? false) && ($evidence['score'] ?? 0) < 72) {
                $handoffMode = 'required';
            } elseif (($evidence['verification_required'] ?? false) || ($evidence['score'] ?? 0) < 48) {
                $handoffMode = 'recommended';
            }
        }

        $preferOpenAIBeforeVerifiedHandoff = $canUseOpenAI
            && $handoffTarget !== null
            && $handoffMode === 'required';

        $source = 'local-answer';
        if ($shouldUseOpenAI || $preferOpenAIBeforeVerifiedHandoff) {
            $source = $canUseOpenAI ? 'openai-fallback' : 'contacts-fallback';
        }

        $reasonCodes = [];
        if (($evidence['verification_required'] ?? false)) {
            $reasonCodes[] = 'needs-official-verification';
        }
        if (($evidence['label'] ?? 'low') === 'low') {
            $reasonCodes[] = 'low-local-evidence';
        } elseif (($evidence['label'] ?? 'low') === 'medium') {
            $reasonCodes[] = 'medium-local-evidence';
        }
        if ($preferOpenAIBeforeVerifiedHandoff && !$shouldUseOpenAI) {
            $reasonCodes[] = 'chatgpt-before-verified-handoff';
        }
        if ($source === 'openai-fallback') {
            $reasonCodes[] = 'openai-fallback-selected';
        } elseif ($source === 'contacts-fallback') {
            $reasonCodes[] = 'official-contacts-fallback';
        } else {
            $reasonCodes[] = 'local-answer-selected';
        }

        $strategy = match (true) {
            $source === 'local-answer' && $handoffMode === 'required' => 'local-answer-with-required-handoff',
            $source === 'local-answer' && $handoffMode === 'recommended' => 'local-answer-with-recommended-handoff',
            $source === 'openai-fallback' && $handoffMode !== 'none' => 'openai-fallback-with-handoff',
            $source === 'contacts-fallback' && $handoffMode !== 'none' => 'verified-handoff-only',
            default => $source,
        };

        return [
            'source' => $source,
            'strategy' => $strategy,
            'handoff_mode' => $handoffMode,
            'handoff_key' => $handoffKey,
            'handoff_target' => $handoffTarget,
            'reason_codes' => $reasonCodes,
            'evidence' => $evidence,
            'can_use_openai' => $canUseOpenAI,
        ];
    }
}

if (!function_exists('kwekwe_build_verified_handoff')) {
    function kwekwe_build_verified_handoff(string $query, string $intent, array $decision): ?array
    {
        $target = $decision['handoff_target'] ?? null;
        $mode = trim((string) ($decision['handoff_mode'] ?? 'none'));
        $evidence = $decision['evidence'] ?? [];

        if (!is_array($target) || $mode === 'none') {
            return null;
        }

        $status = $mode === 'required'
            ? 'Official verification required'
            : 'Verified follow-up recommended';

        $reason = match (true) {
            ($evidence['verification_required'] ?? false) && ($evidence['label'] ?? 'low') === 'low'
                => 'This question depends on details that can change, and the local evidence is not strong enough to treat the answer as verified.',
            ($evidence['verification_required'] ?? false)
                => 'This topic can change, so the official office should confirm the latest details even though the local knowledge was helpful.',
            ($decision['source'] ?? '') === 'openai-fallback'
                => 'The local knowledge did not provide strong enough verified evidence, so a specialist office is the safest follow-up route.',
            default
                => 'A specialist office can verify the latest institution-specific details for this question.',
        };

        return [
            'office' => $target['office'] ?? 'Official office',
            'status' => $status,
            'reason' => $reason,
            'scope' => $target['scope'] ?? '',
            'message' => $target['message'] ?? 'Use the official office below for follow-up support.',
            'recommended_action' => $target['recommended_action'] ?? '',
            'channels' => $target['channels'] ?? [],
            'mode' => $mode,
            'evidence_label' => $evidence['label'] ?? 'low',
            'evidence_score' => $evidence['score'] ?? 0,
        ];
    }
}

if (!function_exists('kwekwe_extract_points')) {
    // Extract key points from search results. The default limit has been increased
    // from 5 to 8 to provide more comprehensive lists of programmes or services.
    function kwekwe_extract_points(array $results, int $limit = 8): array
    {
        $points = [];

        foreach ($results as $result) {
            $lines = preg_split("/\R/u", (string) ($result['content'] ?? '')) ?: [];
            foreach ($lines as $line) {
                $clean = trim($line);
                $clean = preg_replace('/^(?:[-*]\s+|\d+[\.\)]\s+|#+\s+)/u', '', $clean) ?? $clean;
                $length = function_exists('mb_strlen') ? mb_strlen($clean, 'UTF-8') : strlen($clean);
                if ($clean === '' || $length < 18) {
                    continue;
                }

                $key = kwekwe_normalize_text($clean);
                if (isset($points[$key])) {
                    continue;
                }

                $points[$key] = rtrim($clean, '. ') . '.';
                if (count($points) >= $limit) {
                    break 2;
                }
            }
        }

        return array_values($points);
    }
}

if (!function_exists('kwekwe_question_profile')) {
    function kwekwe_question_profile(string $query, string $intent = 'general'): array
    {
        $text = kwekwe_normalize_text($query);
        $focus = 'general';
        $style = 'fact';
        $maxPoints = 3;

        if (preg_match('/\b(contact|phone|email|whatsapp|mobile|call|reach)\b/u', $text) === 1) {
            $focus = 'contact';
            $style = 'contact';
            $maxPoints = 4;
        } elseif (preg_match('/\b(where|address|location|located)\b/u', $text) === 1) {
            $focus = 'location';
            $maxPoints = 1;
        } elseif (preg_match('/\b(who|principal|head|hod|director|dean)\b/u', $text) === 1) {
            $focus = 'person';
            $maxPoints = 1;
        } elseif (preg_match('/\b(time|hours?|open|close|closing|opening|deadline|date|day)\b/u', $text) === 1) {
            $focus = 'time';
            $maxPoints = 1;
        } elseif (preg_match('/\b(requirements?|documents?|document|passes?|qualifications?|eligibility)\b/u', $text) === 1) {
            $focus = 'requirements';
            $style = 'checklist';
            $maxPoints = 4;
        } elseif (preg_match('/\b(payment|payments|pay|bank|ecocash|onemoney|paynow|account|fees?)\b/u', $text) === 1) {
            $focus = 'payments';
            $style = 'checklist';
            $maxPoints = 4;
        } elseif (
            preg_match('/\bhow to\b/u', $text) === 1
            || preg_match('/\bhow (do|can)\b/u', $text) === 1
            || in_array($intent, ['admissions', 'portal'], true)
        ) {
            $focus = $intent === 'portal' ? 'portal' : 'process';
            $style = 'process';
            $maxPoints = 4;
        }

        if (preg_match('/\b(list|courses?|programmes?|programs?|departments?|options?|available|offered)\b/u', $text) === 1) {
            $focus = 'list';
            $style = 'list';
            $maxPoints = $intent === 'general' ? 5 : 6;
        } elseif ($focus === 'general' && preg_match('/\b(tell me about|about|overview|information)\b/u', $text) === 1) {
            $style = 'overview';
            $maxPoints = 4;
        }

        return [
            'focus' => $focus,
            'style' => $style,
            'max_points' => $maxPoints,
        ];
    }
}

if (!function_exists('kwekwe_human_join')) {
    function kwekwe_human_join(array $values): string
    {
        $values = array_values(array_filter(array_map(static fn ($value): string => trim((string) $value), $values)));
        $count = count($values);
        if ($count === 0) {
            return '';
        }
        if ($count === 1) {
            return $values[0];
        }
        if ($count === 2) {
            return $values[0] . ' and ' . $values[1];
        }

        $last = array_pop($values);
        return implode(', ', $values) . ', and ' . $last;
    }
}

if (!function_exists('kwekwe_specific_query_tokens')) {
    function kwekwe_specific_query_tokens(string $query): array
    {
        $generic = [
            'apply', 'application', 'admission', 'admissions', 'program', 'programs', 'programme', 'programmes',
            'course', 'courses', 'fees', 'payment', 'payments', 'office', 'hours', 'contact', 'portal', 'student',
            'principal', 'address', 'location', 'result', 'results', 'exam', 'exams', 'time', 'open', 'close', 'closing',
            'opening', 'deadline', 'date', 'dates', 'requirements', 'documents', 'phone', 'email', 'whatsapp',
            'about', 'hostel', 'hostels', 'accommodation', 'welfare',
        ];

        $specific = [];
        foreach (kwekwe_tokenize($query) as $token) {
            if (strlen($token) < 5 || in_array($token, $generic, true)) {
                continue;
            }
            $specific[] = $token;
        }

        return array_values(array_unique($specific));
    }
}

if (!function_exists('kwekwe_category_label')) {
    function kwekwe_category_label(string $category): ?string
    {
        return match ($category) {
            'engineering' => 'Engineering',
            'commerce' => 'Commerce',
            'applied_sciences' => 'Applied Sciences',
            'btech' => 'B-Tech',
            'ace' => 'Adult and Continuing Education',
            'fees' => 'Admissions and fees',
            'portal' => 'Portal and ICT',
            'accommodation' => 'Accommodation',
            'exams' => 'HEXCO and examinations',
            default => null,
        };
    }
}

if (!function_exists('kwekwe_candidate_segments')) {
    function kwekwe_candidate_segments(string $line, bool $isList): array
    {
        $line = preg_replace('/\s*【[^】]+】/u', '', $line) ?? $line;
        $line = preg_replace('/\s+/u', ' ', $line) ?? $line;
        $line = trim($line);
        if ($line === '' || str_starts_with($line, '<!--') || str_contains($line, '-->')) {
            return [];
        }

        if ($isList) {
            return [$line];
        }

        $parts = preg_split('/(?<=[.!?])\s+(?=(?:\*\*|[0-9]|\p{Lu}))/u', $line) ?: [$line];
        return array_values(array_filter(array_map('trim', $parts), static fn (string $part): bool => $part !== ''));
    }
}

if (!function_exists('kwekwe_prepare_answer_text')) {
    function kwekwe_prepare_answer_text(string $segment, bool $isList): ?string
    {
        $text = trim($segment);
        $text = preg_replace('/^#{1,6}\s+/u', '', $text) ?? $text;
        $text = preg_replace('/^(?:[-*]\s+|\d+[\.\)]\s+)/u', '', $text) ?? $text;
        $text = preg_replace('/\s*【[^】]+】/u', '', $text) ?? $text;
        $text = preg_replace('/\s+/u', ' ', $text) ?? $text;
        $text = trim($text);

        if ($text === '' || preg_match('/^<!--/u', $text) === 1) {
            return null;
        }
        if (preg_match('/^\*\*[^*]+:\*\*$/u', $text) === 1 || preg_match('/^https?:\/\/\S+$/ui', $text) === 1) {
            return null;
        }
        if (preg_match('/:\s*$/u', $text) === 1) {
            return null;
        }

        $normalized = kwekwe_normalize_text($text);
        if (
            $normalized === ''
            || str_starts_with($normalized, 'the assistant should')
            || str_starts_with($normalized, 'when asked')
            || str_starts_with($normalized, 'this document')
        ) {
            return null;
        }

        $length = function_exists('mb_strlen') ? mb_strlen($text, 'UTF-8') : strlen($text);
        if ($length < 12) {
            return null;
        }
        if (!$isList && $length < 20 && preg_match('/\d|@|http/u', $text) !== 1) {
            return null;
        }

        if (!$isList && preg_match('/[.!?]$/u', $text) !== 1) {
            $text = rtrim($text, '. ') . '.';
        }

        return $text;
    }
}

if (!function_exists('kwekwe_answer_candidates')) {
    function kwekwe_answer_candidates(array $results): array
    {
        $candidates = [];

        foreach ($results as $resultIndex => $result) {
            $heading = trim((string) ($result['heading'] ?? ''));
            $lines = preg_split("/\R/u", (string) ($result['content'] ?? '')) ?: [];

            foreach ($lines as $lineIndex => $line) {
                $trimmed = trim($line);
                if ($trimmed === '') {
                    continue;
                }

                if (preg_match('/^#{1,6}\s+(.+)$/u', $trimmed, $matches) === 1) {
                    $heading = trim($matches[1]);
                    continue;
                }

                $isList = preg_match('/^\s*(?:[-*]|\d+[\.\)])\s+/u', $line) === 1;
                foreach (kwekwe_candidate_segments($trimmed, $isList) as $segment) {
                    $text = kwekwe_prepare_answer_text($segment, $isList);
                    if ($text === null) {
                        continue;
                    }

                    $candidates[] = [
                        'text' => $text,
                        'normalized' => kwekwe_normalize_text($text),
                        'is_list' => $isList,
                        'title' => trim((string) ($result['title'] ?? '')),
                        'heading' => $heading,
                        'category' => trim((string) ($result['category'] ?? '')),
                        'result_score' => (float) ($result['score'] ?? 0.0),
                        'result_index' => $resultIndex,
                        'line_index' => $lineIndex,
                    ];
                }
            }
        }

        return $candidates;
    }
}

if (!function_exists('kwekwe_answer_candidate_score')) {
    function kwekwe_answer_candidate_score(string $query, string $intent, array $profile, array $candidate): float
    {
        $queryTokens = kwekwe_tokenize($query);
        $matchedTokens = 0;
        $score = ((float) ($candidate['result_score'] ?? 0.0)) * 1.35;

        $line = (string) ($candidate['normalized'] ?? '');
        $heading = kwekwe_normalize_text((string) ($candidate['heading'] ?? ''));
        $title = kwekwe_normalize_text((string) ($candidate['title'] ?? ''));

        if (($line !== '') && str_contains($line, kwekwe_normalize_text($query))) {
            $score += 8.0;
        }

        foreach ($queryTokens as $token) {
            if (kwekwe_text_matches_token($line, $token)) {
                $score += 3.0;
                $matchedTokens++;
                continue;
            }
            if (kwekwe_text_matches_token($heading, $token)) {
                $score += 1.4;
                $matchedTokens++;
                continue;
            }
            if (kwekwe_text_matches_token($title, $token)) {
                $score += 1.0;
                $matchedTokens++;
            }
        }

        if ($queryTokens !== []) {
            $score += ($matchedTokens / max(1, count($queryTokens))) * 7.0;
        }

        if ($intent !== 'general' && (($candidate['category'] ?? '') === $intent)) {
            $score += 2.5;
        }

        $focus = $profile['focus'] ?? 'general';
        $text = (string) ($candidate['text'] ?? '');
        $length = function_exists('mb_strlen') ? mb_strlen($text, 'UTF-8') : strlen($text);

        if (($profile['style'] ?? 'fact') === 'list' && !empty($candidate['is_list'])) {
            $score += 2.0;
        }
        if (($profile['style'] ?? 'fact') === 'checklist' && !empty($candidate['is_list'])) {
            $score += 1.6;
        }
        if (($profile['style'] ?? 'fact') === 'process' && preg_match('/\b(apply|application|portal|registration|documents?|offer letter|acceptance|requirements?)\b/ui', $text) === 1) {
            $score += 2.5;
        }

        $score += match ($focus) {
            'person' => preg_match('/\b(principal|head|director|dean|mr|mrs|ms|dr)\b/ui', $text) === 1 ? 4.5 : 0.0,
            'time' => (preg_match('/\b(am|pm|monday|tuesday|wednesday|thursday|friday|saturday|sunday|semester|week|deadline|registration|late|early)\b/ui', $text) === 1 ? 3.0 : 0.0)
                + (preg_match('/\d{1,2}(:\d{2})?/u', $text) === 1 ? 3.0 : 0.0),
            'contact' => preg_match('/\b(phone|email|whatsapp|mobile|portal|website|contact)\b/ui', $text) === 1 ? 4.0 : 0.0,
            'location' => preg_match('/\b(address|railway avenue|p o box|campus|located|location)\b/ui', $text) === 1 ? 4.0 : 0.0,
            'requirements' => preg_match('/\b(requirements?|documents?|transcripts?|birth certificate|national id|photos?|passes?|grade|eligibility)\b/ui', $text) === 1 ? 4.0 : 0.0,
            'payments' => preg_match('/\b(bank|account|ecocash|onemoney|paynow|card|usd|zig|fees?)\b/ui', $text) === 1 ? 4.0 : 0.0,
            'list' => preg_match('/\b(programmes?|programs?|courses?|engineering|commerce|science|technology|accountancy|finance|certificate|diploma)\b/ui', $text) === 1 ? 2.5 : 0.0,
            'process', 'portal' => preg_match('/\b(apply|application|portal|registration|documents?|requirements?)\b/ui', $text) === 1 ? 3.0 : 0.0,
            default => 0.0,
        };

        if (preg_match('/\b(public pages|official pages|the institution positions|this document)\b/ui', $text) === 1) {
            $score -= 2.0;
        }
        if ($length > 220) {
            $score -= 2.0;
        } elseif ($length > 160) {
            $score -= 1.0;
        }

        return round($score, 3);
    }
}

if (!function_exists('kwekwe_select_answer_points')) {
    function kwekwe_select_answer_points(string $query, string $intent, array $results): array
    {
        $profile = kwekwe_question_profile($query, $intent);
        $candidates = kwekwe_answer_candidates($results);

        foreach ($candidates as &$candidate) {
            $candidate['answer_score'] = kwekwe_answer_candidate_score($query, $intent, $profile, $candidate);
        }
        unset($candidate);

        usort($candidates, static function (array $left, array $right): int {
            return ($right['answer_score'] <=> $left['answer_score'])
                ?: (($right['result_score'] ?? 0.0) <=> ($left['result_score'] ?? 0.0))
                ?: (strlen((string) ($left['text'] ?? '')) <=> strlen((string) ($right['text'] ?? '')));
        });

        $points = [];
        $seen = [];
        $usedHeadings = [];

        foreach ($candidates as $candidate) {
            $normalized = (string) ($candidate['normalized'] ?? '');
            if ($normalized === '' || isset($seen[$normalized])) {
                continue;
            }

            $headingKey = kwekwe_normalize_text((string) ($candidate['heading'] ?? ''));
            if (($profile['style'] ?? 'fact') === 'list' && count($points) >= 3 && $headingKey !== '' && isset($usedHeadings[$headingKey])) {
                continue;
            }

            $points[] = (string) ($candidate['text'] ?? '');
            $seen[$normalized] = true;
            if ($headingKey !== '') {
                $usedHeadings[$headingKey] = true;
            }

            if (count($points) >= (int) ($profile['max_points'] ?? 3)) {
                break;
            }
        }

        return [
            'profile' => $profile,
            'points' => $points,
        ];
    }
}

if (!function_exists('kwekwe_ranked_candidates')) {
    function kwekwe_ranked_candidates(array $results): array
    {
        $candidates = kwekwe_answer_candidates($results);
        usort($candidates, static function (array $left, array $right): int {
            return (($right['result_score'] ?? 0.0) <=> ($left['result_score'] ?? 0.0))
                ?: (($left['result_index'] ?? 0) <=> ($right['result_index'] ?? 0))
                ?: (($left['line_index'] ?? 0) <=> ($right['line_index'] ?? 0));
        });

        return $candidates;
    }
}

if (!function_exists('kwekwe_matching_candidate_texts')) {
    function kwekwe_matching_candidate_texts(array $results, array $patterns, int $limit = 4, ?callable $filter = null): array
    {
        $matches = [];
        $seen = [];

        foreach (kwekwe_ranked_candidates($results) as $candidate) {
            if ($filter !== null && !$filter($candidate)) {
                continue;
            }

            $text = (string) ($candidate['text'] ?? '');
            foreach ($patterns as $pattern) {
                if (preg_match($pattern, $text) !== 1) {
                    continue;
                }

                $normalized = kwekwe_normalize_text($text);
                if ($normalized === '' || isset($seen[$normalized])) {
                    break;
                }

                $seen[$normalized] = true;
                $matches[] = $text;
                break;
            }

            if (count($matches) >= max(1, $limit)) {
                break;
            }
        }

        return $matches;
    }
}

if (!function_exists('kwekwe_compose_contact_answer')) {
    function kwekwe_compose_contact_answer(): string
    {
        $config = kwekwe_config();
        $contacts = $config['contacts'];
        $links = $config['links'];

        return "The main public contact channels are:\n"
            . "- Phone: {$contacts['phone']}\n"
            . "- Mobile: {$contacts['mobile']}\n"
            . "- WhatsApp: {$contacts['whatsapp']}\n"
            . "- Email: {$contacts['email']}\n"
            . "- Website: {$links['website']}\n"
            . "- Admissions portal: {$links['apply']}";
    }
}

if (!function_exists('kwekwe_compose_location_answer')) {
    function kwekwe_compose_location_answer(array $results): ?string
    {
        $matches = kwekwe_matching_candidate_texts(
            $results,
            ['/railway avenue|p\.?\s*o\.?\s*box|physical address|campus is in|address/ui'],
            3
        );

        foreach ($matches as $match) {
            if (preg_match('/railway avenue|p\.?\s*o\.?\s*box|physical address/u', $match) === 1) {
                return $match;
            }
        }

        return $matches[0] ?? null;
    }
}

if (!function_exists('kwekwe_compose_person_answer')) {
    function kwekwe_compose_person_answer(array $results): ?string
    {
        foreach (kwekwe_ranked_candidates($results) as $candidate) {
            $text = (string) ($candidate['text'] ?? '');
            if (preg_match('/the principal,\s*\*\*?mr\s+([^*]+)\*\*/ui', $text, $matches) === 1) {
                return 'The current principal is Mr ' . trim($matches[1]) . '.';
            }
            if (preg_match('/eddie\s*\(e\.\)\s*musara/ui', $text) === 1) {
                return 'The current principal is Mr Eddie (E.) Musara.';
            }
        }

        return null;
    }
}

if (!function_exists('kwekwe_compose_application_answer')) {
    function kwekwe_compose_application_answer(array $results): string
    {
        $links = kwekwe_config()['links'];
        $documents = kwekwe_matching_candidate_texts(
            $results,
            [
                '/academic transcripts/ui',
                '/birth certificate/ui',
                '/national id/ui',
                '/passport/ui',
                '/application fee/ui',
            ],
            4,
            static function (array $candidate): bool {
                return in_array((string) ($candidate['category'] ?? ''), ['fees', 'general'], true)
                    && preg_match('/certificate processing|collection fee|result slip/ui', (string) ($candidate['text'] ?? '')) !== 1;
            }
        );

        $lines = [
            "Apply through the official admissions portal: {$links['apply']}.",
            '',
            '1. Check the current intake status and opening dates on the portal.',
            '2. Create your application and upload the required supporting documents.',
        ];

        $lines[] = $documents !== []
            ? '3. Prepare the main documents usually requested: certified academic transcripts, birth certificate, national ID, passport-size photos, and any application fee proof required by the current notice.'
            : '3. Prepare your certified academic documents and identity documents before submitting.';

        $lines[] = '4. Submit the application through the official process and keep your proof of submission or payment.';

        return implode("\n", $lines);
    }
}

if (!function_exists('kwekwe_compose_payment_answer')) {
    function kwekwe_compose_payment_answer(array $results): ?string
    {
        $matches = kwekwe_matching_candidate_texts(
            $results,
            [
                '/online payment|paynow|bank cards/ui',
                '/zb\s*bank|cbz\s*bank|account/ui',
                '/ecocash|onemoney|mobile money/ui',
            ],
            4
        );

        if ($matches === []) {
            return null;
        }

        $lines = ['Official payment channels include:', ''];
        foreach ($matches as $match) {
            $lines[] = '- ' . $match;
        }

        return implode("\n", $lines);
    }
}

if (!function_exists('kwekwe_compose_accommodation_answer')) {
    function kwekwe_compose_accommodation_answer(array $results): ?string
    {
        $matches = kwekwe_matching_candidate_texts(
            $results,
            [
                '/accommodation is limited|on-campus accommodation is limited|hostel information indicates/ui',
                '/hostel capacity|beds/ui',
                '/priority is often given|greater need|apprentices/ui',
                '/full tuition payment|hostel allocation|availability|charges/ui',
            ],
            4
        );

        if ($matches === []) {
            return null;
        }

        $lines = ['Here is the main accommodation guidance I found:', ''];
        foreach ($matches as $match) {
            $lines[] = '- ' . $match;
        }

        $lines[] = '';
        $lines[] = 'Use the official hostel page or Student Affairs to confirm current availability and charges.';

        return implode("\n", $lines);
    }
}

if (!function_exists('kwekwe_compose_hours_answer')) {
    function kwekwe_compose_hours_answer(string $query, array $results): ?string
    {
        $text = kwekwe_normalize_text($query);
        if (
            preg_match('/office hours|opening hours|working hours/u', $text) !== 1
            || preg_match('/library|hostel|accommodation/u', $text) === 1
        ) {
            return null;
        }

        $matches = kwekwe_matching_candidate_texts(
            $results,
            [
                '/monday|friday/ui',
                '/saturday/ui',
                '/sunday/ui',
            ],
            3
        );

        if ($matches === []) {
            return null;
        }

        $lines = ['Published office hours are:', ''];
        foreach ($matches as $match) {
            $lines[] = '- ' . $match;
        }

        return implode("\n", $lines);
    }
}

if (!function_exists('kwekwe_compose_programme_answer')) {
    function kwekwe_compose_programme_answer(string $intent, array $results): ?string
    {
        if ($intent === 'general') {
            return "Kwekwe Polytechnic publicly lists programmes in Engineering, Commerce, Applied Sciences, B-Tech, and Adult and Continuing Education.\n\nAsk me for a specific division if you want the exact programmes under it.";
        }

        $examples = kwekwe_matching_candidate_texts(
            $results,
            [
                '/motor vehicle mechanics|electrical power|instrumentation and control|building technology|quantity surveying|fabrication|machineshop|refrigeration|computer systems|precision machining/ui',
                '/office management|human resources management|sales and marketing management|accountancy|banking and finance/ui',
                '/information technology|metallurgical assaying|industrial metallurgy|food|laboratory/ui',
                '/industrial and manufacturing engineering|electrical power engineering/ui',
                '/cosmetology|tourism and hospitality|clothing and textile design|applied art and design/ui',
            ],
            6
        );

        if ($examples === []) {
            return null;
        }

        $lines = [];
        if (($label = kwekwe_category_label($intent)) !== null) {
            $lines[] = "These are some of the programmes I found under {$label}:";
        } else {
            $lines[] = 'These are some of the programmes I found:';
        }

        $lines[] = '';
        foreach ($examples as $example) {
            $lines[] = '- ' . $example;
        }

        return implode("\n", $lines);
    }
}

if (!function_exists('kwekwe_compose_answer')) {
    function kwekwe_compose_answer(string $query, string $intent, array $results): string
    {
        $contacts = kwekwe_config()['contacts'];
        $links = kwekwe_config()['links'];

        if ($results === []) {
            if ($intent === 'greeting') {
                return "Hello! 👋 I'm your friendly AI assistant from Kwekwe Polytechnic. I'm here to help with any questions you might have about our programs, admissions, fees, or student life. What can I help you with today?";
            }
            return "- Website: {$links['website']}\n"
                . "- Apply portal: {$links['apply']}\n"
                . "- Student portal: {$links['portal']}\n"
                . "- Phone: {$contacts['phone']}\n"
                . "- WhatsApp: {$contacts['whatsapp']}\n"
                . "- Email: {$contacts['email']}";
        }

        $selection = kwekwe_select_answer_points($query, $intent, $results);
        $profile = $selection['profile'];
        $points = $selection['points'];

        $directAnswer = match ($profile['focus'] ?? 'general') {
            'contact' => kwekwe_compose_contact_answer(),
            'location' => kwekwe_compose_location_answer($results),
            'person' => kwekwe_compose_person_answer($results),
            'payments' => kwekwe_compose_payment_answer($results),
            default => null,
        };

        if (($profile['focus'] ?? 'general') === 'process' && $intent === 'admissions') {
            $directAnswer = kwekwe_compose_application_answer($results);
        }
        if ($intent === 'accommodation') {
            $directAnswer = kwekwe_compose_accommodation_answer($results) ?? $directAnswer;
        }
        if (($profile['focus'] ?? 'general') === 'time') {
            $directAnswer = kwekwe_compose_hours_answer($query, $results) ?? $directAnswer;
        }
        if (($profile['style'] ?? 'fact') === 'list') {
            $directAnswer = kwekwe_compose_programme_answer($intent, $results) ?? $directAnswer;
        }

        if (is_string($directAnswer) && trim($directAnswer) !== '') {
            return trim($directAnswer);
        }

        if ($points === []) {
            $fallbackPoints = array_slice(kwekwe_extract_points($results, 3), 0, (int) ($profile['max_points'] ?? 3));
            if ($fallbackPoints !== []) {
                $points = $fallbackPoints;
            }
        }

        if ($points === []) {
            return "- Website: {$links['website']}\n"
                . "- Apply portal: {$links['apply']}\n"
                . "- Student portal: {$links['portal']}\n"
                . "- Phone: {$contacts['phone']}\n"
                . "- WhatsApp: {$contacts['whatsapp']}\n"
                . "- Email: {$contacts['email']}";
        }

        if (($profile['style'] ?? 'fact') === 'list') {
            $areas = [];
            foreach ($results as $result) {
                $label = kwekwe_category_label((string) ($result['category'] ?? ''));
                if ($label !== null) {
                    $areas[$label] = true;
                }
            }

            $lines = [];
            if ($intent !== 'general' && ($label = kwekwe_category_label($intent)) !== null) {
                $lines[] = "These are some of the programmes I found under {$label}:";
            } elseif (count($areas) >= 2) {
                $lines[] = 'Kwekwe Polytechnic publicly lists programmes across ' . kwekwe_human_join(array_keys($areas)) . '.';
            } else {
                $lines[] = 'These are some of the most relevant programmes I found:';
            }

            $lines[] = '';
            foreach ($points as $point) {
                $lines[] = '- ' . $point;
            }

            if ($intent === 'general' && count($areas) >= 2) {
                $lines[] = '';
                $lines[] = 'If you want, ask for a specific division such as Engineering, Commerce, Applied Sciences, B-Tech, or A.C.E.';
            }

            return implode("\n", $lines);
        }

        $lead = array_shift($points);
        if ($lead === null || $lead === '') {
            return '';
        }

        if ($points === []) {
            return $lead;
        }

        $lines = [$lead, ''];
        if (($profile['style'] ?? 'fact') === 'process') {
            $step = 1;
            foreach ($points as $point) {
                $lines[] = $step++ . '. ' . $point;
            }
            return implode("\n", $lines);
        }

        foreach ($points as $point) {
            $lines[] = '- ' . $point;
        }

        return implode("\n", $lines);
    }
}

if (!function_exists('kwekwe_chat_sources')) {
    function kwekwe_chat_sources(array $results): array
    {
        $sources = [];
        foreach (array_slice($results, 0, 3) as $result) {
            $sources[] = [
                'title' => $result['title'] ?? 'Knowledge source',
                'excerpt' => $result['excerpt'] ?? '',
                'metadata' => $result['metadata'] ?? [],
            ];
        }

        return $sources;
    }
}

if (!function_exists('kwekwe_local_result_support')) {
    function kwekwe_local_result_support(string $query, array $result): array
    {
        $queryTokens = kwekwe_tokenize($query);
        if ($queryTokens === []) {
            return [
                'ratio' => 0.0,
                'exact_phrase' => false,
            ];
        }

        $haystack = kwekwe_normalize_text(
            ($result['title'] ?? '') . ' ' . ($result['heading'] ?? '') . ' ' . ($result['content'] ?? '')
        );
        $matched = 0;
        foreach ($queryTokens as $token) {
            if (kwekwe_text_matches_token($haystack, $token)) {
                $matched++;
            }
        }

        $normalizedQuery = kwekwe_normalize_text($query);

        return [
            'ratio' => $matched / max(1, count($queryTokens)),
            'exact_phrase' => $normalizedQuery !== '' && str_contains($haystack, $normalizedQuery),
        ];
    }
}

if (!function_exists('kwekwe_local_results_support')) {
    function kwekwe_local_results_support(string $query, array $results, int $limit = 3): array
    {
        $queryTokens = kwekwe_tokenize($query);
        if ($queryTokens === [] || $results === []) {
            return [
                'coverage' => 0.0,
                'matched_tokens' => 0,
                'result_count' => 0,
            ];
        }

        $matched = [];
        foreach (array_slice($results, 0, max(1, $limit)) as $result) {
            $haystack = kwekwe_normalize_text(
                ($result['title'] ?? '') . ' ' . ($result['heading'] ?? '') . ' ' . ($result['content'] ?? '')
            );

            foreach ($queryTokens as $token) {
                if (kwekwe_text_matches_token($haystack, $token)) {
                    $matched[$token] = true;
                }
            }
        }

        return [
            'coverage' => count($matched) / max(1, count($queryTokens)),
            'matched_tokens' => count($matched),
            'result_count' => min(count($results), max(1, $limit)),
        ];
    }
}

if (!function_exists('kwekwe_missing_specific_tokens')) {
    function kwekwe_missing_specific_tokens(string $query, array $results, ?array $profile = null, ?string $intent = null, int $limit = 4): array
    {
        $specificTokens = kwekwe_specific_query_tokens($query);
        if ($specificTokens === [] || $results === []) {
            return [];
        }

        $matched = [];
        $preferredTypes = kwekwe_preferred_section_types($profile, $intent);
        foreach (array_slice($results, 0, max(1, $limit)) as $result) {
            $sectionType = (string) ($result['section_type'] ?? kwekwe_chunk_section_type($result));
            if ($preferredTypes !== [] && !in_array($sectionType, $preferredTypes, true)) {
                continue;
            }

            $haystack = kwekwe_normalize_text(
                ($result['title'] ?? '') . ' ' . ($result['heading'] ?? '') . ' ' . ($result['content'] ?? '')
            );
            foreach ($specificTokens as $token) {
                if (kwekwe_text_matches_token($haystack, $token)) {
                    $matched[$token] = true;
                }
            }
        }

        return array_values(array_filter($specificTokens, static fn (string $token): bool => !isset($matched[$token])));
    }
}

if (!function_exists('kwekwe_should_use_openai_fallback')) {
    function kwekwe_should_use_openai_fallback(
        string $intent,
        string $query,
        array $results,
        ?array $profile = null,
        ?array $evidence = null
    ): bool
    {
        $profile ??= kwekwe_question_profile($query, $intent);
        $evidence ??= kwekwe_evidence_report($query, $intent, $results, $profile);

        if ($intent === 'greeting') {
            return false;
        }

        if ($results === []) {
            return true;
        }

        $topResult = $results[0];
        $topScore = (float) ($topResult['score'] ?? 0.0);
        $support = kwekwe_local_result_support($query, $topResult);
        $combinedSupport = kwekwe_local_results_support($query, $results, 4);
        $ratioThreshold = ($profile['style'] ?? 'fact') === 'list'
            ? 0.55
            : (in_array($profile['focus'] ?? 'general', ['time', 'location', 'person'], true) ? 0.75 : 0.65);

        if (($evidence['score'] ?? 0) >= 78) {
            return false;
        }
        if (($evidence['score'] ?? 0) >= 62 && ($evidence['verification_required'] ?? false)) {
            return false;
        }
        if (($evidence['score'] ?? 0) >= 56) {
            return false;
        }
        if (($evidence['score'] ?? 0) <= 36) {
            return true;
        }
        if (($evidence['missing_specific_tokens'] ?? []) !== [] && ($profile['style'] ?? 'fact') !== 'list') {
            return true;
        }

        if ($support['exact_phrase']) {
            return false;
        }

        if ($support['ratio'] >= $ratioThreshold) {
            return false;
        }

        if ($combinedSupport['coverage'] >= 0.75) {
            return false;
        }

        if (($profile['style'] ?? 'fact') === 'list' && $combinedSupport['coverage'] >= 0.45 && count($results) >= 3) {
            return false;
        }

        if ($intent !== 'general' && $topScore >= 6.0) {
            return false;
        }

        if ($topScore >= 4.0 && $combinedSupport['coverage'] >= 0.7 && count($results) >= 2) {
            return false;
        }

        return $topScore < 8.0 || ($evidence['label'] ?? 'low') === 'low';
    }
}

if (!function_exists('kwekwe_format_openai_fallback_answer')) {
    function kwekwe_format_openai_fallback_answer(string $answer): string
    {
        return trim($answer);
    }
}

if (!function_exists('kwekwe_record_chat_event')) {
    function kwekwe_record_chat_event(array $event): void
    {
        kwekwe_append_jsonl(kwekwe_config()['storage']['analytics'], $event);
    }
}

if (!function_exists('kwekwe_build_chat_response')) {
    function kwekwe_build_chat_response(array $request): array
    {
        $message = trim((string) ($request['message'] ?? ''));
        if ($message === '') {
            return ['error' => 'Message cannot be empty'];
        }

        $message = function_exists('mb_substr') ? mb_substr($message, 0, 1200, 'UTF-8') : substr($message, 0, 1200);
        $sessionId = trim((string) ($request['session_id'] ?? '')) ?: kwekwe_random_session_id();
        $language = trim((string) ($request['language'] ?? 'en')) ?: 'en';
        $intent = kwekwe_detect_intent($message);
        $profile = kwekwe_question_profile($message, $intent);
        $results = kwekwe_search_knowledge($message, 6, $intent, $profile);
        $topScore = (float) ($results[0]['score'] ?? 0.0);
        $evidence = kwekwe_evidence_report($message, $intent, $results, $profile);
        $decision = kwekwe_response_decision($message, $intent, $results, $profile, $evidence);
        $queryType = $results === [] ? 'fallback' : 'local-search';
        $openai = null;
        $responseSource = (string) ($decision['source'] ?? 'local-answer');

        if ($responseSource === 'openai-fallback') {
            $openai = kwekwe_fetch_openai_fallback($message, $language, $intent, [
                'results' => $results,
                'evidence' => $evidence,
                'decision' => $decision,
            ]);
            if ($openai === null) {
                $responseSource = 'contacts-fallback';
                $decision['source'] = 'contacts-fallback';
                $decision['strategy'] = (($decision['handoff_mode'] ?? 'none') !== 'none') ? 'verified-handoff-only' : 'contacts-fallback';
                $decision['reason_codes'][] = 'openai-fallback-unavailable';
                $decision['reason_codes'][] = 'official-contacts-fallback';
            }
        }

        $decision['reason_codes'] = array_values(array_unique(array_filter(
            array_map(static fn ($code): string => trim((string) $code), (array) ($decision['reason_codes'] ?? [])),
            static fn (string $code): bool => $code !== ''
        )));

        if ($openai !== null) {
            $decision['strategy'] = 'openai-fallback';
            $decision['handoff_mode'] = 'none';
            $decision['reason_codes'][] = 'chatgpt-fallback-delivered';
            $decision['reason_codes'] = array_values(array_unique(array_filter(
                array_map(static fn ($code): string => trim((string) $code), (array) ($decision['reason_codes'] ?? [])),
                static fn (string $code): bool => $code !== ''
            )));

            $answer = kwekwe_format_openai_fallback_answer($openai['response']);
            $queryType = 'openai-fallback';
        } elseif ($responseSource === 'contacts-fallback') {
            $handoff = kwekwe_build_verified_handoff($message, $intent, $decision);
            $answer = kwekwe_compose_answer($message, $intent, []);
            $queryType = $handoff !== null ? 'verified-handoff' : 'fallback';
        } else {
            $handoff = kwekwe_build_verified_handoff($message, $intent, $decision);
            $answer = kwekwe_compose_answer($message, $intent, $results);
        }

        if ($openai !== null) {
            $handoff = null;
        }

        $payload = [
            'response' => $answer,
            'session_id' => $sessionId,
            'sources' => $openai !== null ? [] : kwekwe_chat_sources($results),
            'confidence' => [
                'score' => $evidence['score'],
                'label' => $openai !== null ? 'fallback' : ($evidence['label'] ?? 'low'),
            ],
            'handoff' => $handoff,
            'suggested_actions' => kwekwe_actions_for_intent($intent),
            'language' => $language,
            'intent' => $intent,
            'timestamp' => kwekwe_current_timestamp(),
            'query_type' => $queryType,
            'answer_mode' => $openai !== null
                ? 'chatgpt-fallback'
                : (($queryType === 'verified-handoff' || $responseSource === 'contacts-fallback')
                    ? ($queryType === 'verified-handoff' ? 'verified-handoff' : 'contacts-fallback')
                    : 'local-knowledge'),
            'handoff_recommended' => $handoff !== null,
            'needs_knowledge_review' => $responseSource !== 'local-answer',
            'decision' => [
                'strategy' => $decision['strategy'],
                'source' => $decision['source'],
                'handoff_mode' => $decision['handoff_mode'],
                'reason_codes' => $decision['reason_codes'],
            ],
            'evidence' => [
                'score' => $evidence['score'],
                'label' => $evidence['label'],
                'coverage' => $evidence['coverage'],
                'section_focus' => $evidence['section_focus'],
                'category_consistency' => $evidence['category_consistency'],
                'missing_specific_tokens' => $evidence['missing_specific_tokens'],
                'verification_required' => $evidence['verification_required'],
            ],
            'openai' => [
                'used' => $openai !== null,
                'model' => $openai['model'] ?? null,
            ],
        ];

        kwekwe_record_chat_event([
            'timestamp' => $payload['timestamp'],
            'session_id' => $sessionId,
            'intent' => $intent,
            'language' => $language,
            'query' => $message,
            'result_count' => count($results),
            'top_score' => $topScore,
            'query_type' => $queryType,
            'answer_mode' => $payload['answer_mode'],
            'decision_strategy' => $decision['strategy'],
            'decision_source' => $decision['source'],
            'handoff_mode' => $decision['handoff_mode'],
            'handoff_office' => $handoff['office'] ?? null,
            'evidence_score' => $evidence['score'],
            'evidence_label' => $evidence['label'],
            'local_coverage' => $evidence['coverage'],
            'section_focus' => $evidence['section_focus'],
            'category_consistency' => $evidence['category_consistency'],
            'missing_specific_tokens' => $evidence['missing_specific_tokens'],
            'verification_required' => $evidence['verification_required'],
            'openai_used' => $openai !== null,
            'openai_model' => $openai['model'] ?? null,
        ]);

        return $payload;
    }
}

if (!function_exists('kwekwe_record_feedback')) {
    function kwekwe_record_feedback(array $payload): array
    {
        $record = [
            'timestamp' => kwekwe_current_timestamp(),
            'session_id' => trim((string) ($payload['session_id'] ?? '')),
            'message_content' => trim((string) ($payload['message_content'] ?? '')),
            'helpful' => (bool) ($payload['helpful'] ?? false),
            'comment' => trim((string) ($payload['comment'] ?? '')),
            'intent' => trim((string) ($payload['intent'] ?? 'general')) ?: 'general',
            'user_query' => trim((string) ($payload['user_query'] ?? '')),
            'query_type' => trim((string) ($payload['query_type'] ?? '')),
            'answer_mode' => trim((string) ($payload['answer_mode'] ?? '')),
            'confidence_label' => trim((string) ($payload['confidence_label'] ?? '')),
            'confidence_score' => isset($payload['confidence_score']) ? (float) $payload['confidence_score'] : null,
            'decision_strategy' => trim((string) ($payload['decision_strategy'] ?? '')),
            'decision_source' => trim((string) ($payload['decision_source'] ?? '')),
            'handoff_mode' => trim((string) ($payload['handoff_mode'] ?? '')),
            'evidence_label' => trim((string) ($payload['evidence_label'] ?? '')),
            'evidence_score' => isset($payload['evidence_score']) ? (int) $payload['evidence_score'] : null,
            'openai_used' => (bool) ($payload['openai_used'] ?? false),
        ];

        kwekwe_append_jsonl(kwekwe_config()['storage']['feedback'], $record);
        return $record;
    }
}
