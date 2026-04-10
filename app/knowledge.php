<?php

declare(strict_types=1);

require_once __DIR__ . '/config.php';

if (!function_exists('kwekwe_detect_source_category')) {
    function kwekwe_detect_source_category(string $filename): string
    {
        $filename = strtolower($filename);
        return match (true) {
            str_contains($filename, 'fee') || str_contains($filename, 'admission') => 'fees',
            str_contains($filename, 'engineering') => 'engineering',
            str_contains($filename, 'commerce') => 'commerce',
            str_contains($filename, 'applied') => 'applied_sciences',
            str_contains($filename, 'btech') => 'btech',
            str_contains($filename, 'ace') => 'ace',
            str_contains($filename, 'hexco') || str_contains($filename, 'exam') => 'exams',
            default => 'general',
        };
    }
}

if (!function_exists('kwekwe_source_url_for_category')) {
    function kwekwe_source_url_for_category(string $category): ?string
    {
        $links = kwekwe_config()['links'];

        return match ($category) {
            'fees' => $links['apply'],
            default => $links['website'],
        };
    }
}

if (!function_exists('kwekwe_list_source_files')) {
    function kwekwe_list_source_files(): array
    {
        $paths = [];
        foreach (kwekwe_config()['knowledge_sources'] as $directory) {
            if (!is_dir($directory)) {
                continue;
            }

            $iterator = new RecursiveIteratorIterator(
                new RecursiveDirectoryIterator($directory, FilesystemIterator::SKIP_DOTS)
            );

            foreach ($iterator as $item) {
                if (!$item instanceof SplFileInfo || !$item->isFile()) {
                    continue;
                }

                $extension = strtolower($item->getExtension());
                if (!in_array($extension, ['md', 'txt'], true)) {
                    continue;
                }

                $paths[] = $item->getPathname();
            }
        }

        sort($paths);
        return $paths;
    }
}

if (!function_exists('kwekwe_parse_markdown_sections')) {
    function kwekwe_parse_markdown_sections(string $content): array
    {
        $lines = preg_split("/\R/u", $content) ?: [];
        $sections = [];
        $currentHeading = 'Overview';
        $buffer = [];

        foreach ($lines as $line) {
            $trimmed = trim($line);
            if (preg_match('/^#{1,3}\s+(.+)$/u', $trimmed, $matches)) {
                if (trim(implode("\n", $buffer)) !== '') {
                    $sections[] = [
                        'heading' => $currentHeading,
                        'content' => trim(implode("\n", $buffer)),
                    ];
                }
                $currentHeading = trim($matches[1]);
                $buffer = [];
                continue;
            }

            $buffer[] = $line;
        }

        if (trim(implode("\n", $buffer)) !== '') {
            $sections[] = [
                'heading' => $currentHeading,
                'content' => trim(implode("\n", $buffer)),
            ];
        }

        return array_values(array_filter($sections, static function (array $section): bool {
            $content = trim($section['content']);
            $length = function_exists('mb_strlen') ? mb_strlen($content, 'UTF-8') : strlen($content);
            return $length >= 40;
        }));
    }
}

if (!function_exists('kwekwe_detect_section_type')) {
    function kwekwe_detect_section_type(string $title, string $heading, string $content): string
    {
        $text = kwekwe_normalize_text($title . ' ' . $heading . ' ' . $content);

        return match (true) {
            preg_match('/address|location|railway avenue|p o box|campus/u', $text) === 1 => 'location',
            preg_match('/principal|head of division|division head|leadership|director|dean/u', $text) === 1 => 'leadership',
            preg_match('/office hours|opening hours|working hours|monday|tuesday|wednesday|thursday|friday|saturday|sunday/u', $text) === 1 => 'hours',
            preg_match('/payment channels|bank transfer|ecocash|onemoney|paynow|fees|account/u', $text) === 1 => 'payments',
            preg_match('/admission|apply|application|registration process|successful applicants|portal access|username|password/u', $text) === 1 => 'process',
            preg_match('/requirements|required documents|documents|minimum academic|eligibility|passes/u', $text) === 1 => 'requirements',
            preg_match('/programmes|programs|courses|departments and heads|qualifications offered/u', $text) === 1 => 'programmes',
            preg_match('/contact information|phone|email|whatsapp|website|mobile|reach us|official online channels/u', $text) === 1 => 'contact',
            default => 'overview',
        };
    }
}

if (!function_exists('kwekwe_chunk_section_type')) {
    function kwekwe_chunk_section_type(array $chunk): string
    {
        return kwekwe_detect_section_type(
            (string) ($chunk['title'] ?? ''),
            (string) ($chunk['heading'] ?? ''),
            (string) ($chunk['content'] ?? '')
        );
    }
}

if (!function_exists('kwekwe_preferred_section_types')) {
    function kwekwe_preferred_section_types(?array $profile, ?string $intent = null): array
    {
        $focus = trim((string) ($profile['focus'] ?? 'general')) ?: 'general';
        $style = trim((string) ($profile['style'] ?? 'fact')) ?: 'fact';

        $types = match ($focus) {
            'contact' => ['contact'],
            'location' => ['location', 'contact'],
            'person' => ['leadership'],
            'time' => ['hours', 'contact'],
            'requirements' => ['requirements', 'process'],
            'payments' => ['payments', 'contact'],
            'process', 'portal' => ['process', 'requirements', 'contact'],
            'list' => ['programmes'],
            default => ['overview'],
        };

        if ($style === 'list' && !in_array('programmes', $types, true)) {
            $types[] = 'programmes';
        }

        if ($intent !== null && in_array($intent, ['fees', 'admissions', 'portal', 'accommodation', 'exams'], true) && !in_array('contact', $types, true)) {
            $types[] = 'contact';
        }

        return $types;
    }
}

if (!function_exists('kwekwe_is_irrelevant_section_type')) {
    function kwekwe_is_irrelevant_section_type(string $sectionType, ?array $profile): bool
    {
        $focus = trim((string) ($profile['focus'] ?? 'general')) ?: 'general';

        return match ($focus) {
            'contact' => in_array($sectionType, ['programmes', 'requirements'], true),
            'location' => in_array($sectionType, ['programmes', 'payments'], true),
            'person' => in_array($sectionType, ['payments', 'requirements', 'programmes'], true),
            'time' => in_array($sectionType, ['programmes', 'leadership'], true),
            'requirements' => in_array($sectionType, ['leadership', 'hours'], true),
            'payments' => in_array($sectionType, ['leadership', 'programmes'], true),
            'process', 'portal' => in_array($sectionType, ['leadership'], true),
            'list' => in_array($sectionType, ['hours', 'contact', 'payments'], true),
            default => false,
        };
    }
}

if (!function_exists('kwekwe_build_knowledge_index')) {
    function kwekwe_build_knowledge_index(): array
    {
        $documents = [];
        $chunks = [];

        foreach (kwekwe_list_source_files() as $path) {
            $content = trim((string) file_get_contents($path));
            if ($content === '') {
                continue;
            }

            $relativePath = str_replace(kwekwe_project_root() . DIRECTORY_SEPARATOR, '', $path);
            $filename = basename($path);
            preg_match('/^#\s+(.+)$/m', $content, $titleMatch);
            $title = $titleMatch[1] ?? ucwords(str_replace(['_', '-'], ' ', pathinfo($filename, PATHINFO_FILENAME)));
            $category = kwekwe_detect_source_category($filename);
            $documentId = kwekwe_slugify(pathinfo($filename, PATHINFO_FILENAME));

            $documents[] = [
                'id' => $documentId,
                'title' => $title,
                'category' => $category,
                'source_path' => $relativePath,
                'source_url' => kwekwe_source_url_for_category($category),
            ];

            $sectionIndex = 0;
            foreach (kwekwe_parse_markdown_sections($content) as $section) {
                $sectionIndex++;
                $chunks[] = [
                    'id' => $documentId . '-' . $sectionIndex,
                    'document_id' => $documentId,
                    'title' => $title,
                    'category' => $category,
                    'heading' => $section['heading'],
                    'content' => $section['content'],
                    'excerpt' => kwekwe_excerpt($section['content'], 220),
                    'section_type' => kwekwe_detect_section_type($title, $section['heading'], $section['content']),
                    'tokens' => kwekwe_tokenize($title . ' ' . $section['heading'] . ' ' . $section['content']),
                    'metadata' => [
                        'filename' => $filename,
                        'source_path' => $relativePath,
                        'source_url' => kwekwe_source_url_for_category($category),
                    ],
                ];
            }
        }

        return [
            'generated_at' => kwekwe_current_timestamp(),
            'document_count' => count($documents),
            'chunk_count' => count($chunks),
            'documents' => $documents,
            'chunks' => $chunks,
        ];
    }
}

if (!function_exists('kwekwe_save_knowledge_index')) {
    function kwekwe_save_knowledge_index(array $index): array
    {
        kwekwe_write_json_file(kwekwe_config()['knowledge_index'], $index);
        return $index;
    }
}

if (!function_exists('kwekwe_load_knowledge_index')) {
    function kwekwe_load_knowledge_index(bool $refresh = false): array
    {
        static $cache = null;

        if ($refresh || $cache === null) {
            $indexPath = kwekwe_config()['knowledge_index'];
            $cache = $refresh || !is_file($indexPath)
                ? kwekwe_save_knowledge_index(kwekwe_build_knowledge_index())
                : kwekwe_read_json_file($indexPath, []);

            if (!isset($cache['chunks']) || !is_array($cache['chunks'])) {
                $cache = kwekwe_save_knowledge_index(kwekwe_build_knowledge_index());
            }
        }

        return $cache;
    }
}

if (!function_exists('kwekwe_search_knowledge')) {
    function kwekwe_search_knowledge(string $query, int $limit = 5, ?string $intent = null, ?array $profile = null): array
    {
        $queryTokens = kwekwe_tokenize($query);
        if ($queryTokens === []) {
            return [];
        }

        $normalizedQuery = kwekwe_normalize_text($query);
        $index = kwekwe_load_knowledge_index();
        $results = [];
        $preferredTypes = kwekwe_preferred_section_types($profile, $intent);

        foreach ($index['chunks'] ?? [] as $chunk) {
            $score = 0.0;
            $haystack = kwekwe_normalize_text(
                ($chunk['title'] ?? '') . ' ' . ($chunk['heading'] ?? '') . ' ' . ($chunk['content'] ?? '')
            );
            $sectionType = kwekwe_chunk_section_type($chunk);

            if ($normalizedQuery !== '' && str_contains($haystack, $normalizedQuery)) {
                $score += 8.0;
            }

            foreach ($queryTokens as $token) {
                if (kwekwe_text_matches_token($haystack, $token)) {
                    $score += 2.2;
                }
                if (kwekwe_text_matches_token((string) $chunk['heading'], $token)) {
                    $score += 1.8;
                }
                if (kwekwe_text_matches_token((string) $chunk['title'], $token)) {
                    $score += 1.2;
                }
            }

            if ($intent !== null && $intent !== 'general' && ($chunk['category'] ?? '') === $intent) {
                $score += 3.5;
            }

            if (in_array($sectionType, $preferredTypes, true)) {
                $score += 4.0;
            } elseif ($profile !== null && kwekwe_is_irrelevant_section_type($sectionType, $profile)) {
                $score -= 1.5;
            }

            $heading = kwekwe_normalize_text((string) ($chunk['heading'] ?? ''));
            if (($profile['style'] ?? 'fact') === 'list' && preg_match('/programmes|programs|courses|qualifications/u', $heading) === 1) {
                $score += 2.5;
            }
            if (($profile['focus'] ?? 'general') === 'process' && preg_match('/apply|application|registration|portal/u', $heading) === 1) {
                $score += 2.5;
            }
            if (($profile['focus'] ?? 'general') === 'time' && preg_match('/hours|calendar|schedule/u', $heading) === 1) {
                $score += 2.0;
            }

            if ($score <= 0) {
                continue;
            }

            $result = $chunk;
            $result['score'] = round($score, 3);
            $result['section_type'] = $sectionType;
            $results[] = $result;
        }

        usort($results, static function (array $left, array $right): int {
            return $right['score'] <=> $left['score'];
        });

        $unique = [];
        $deduped = [];
        foreach ($results as $result) {
            $key = kwekwe_normalize_text(
                ($result['title'] ?? '') . '|' . ($result['heading'] ?? '') . '|' . ($result['content'] ?? '')
            );
            if ($key === '' || isset($unique[$key])) {
                continue;
            }

            $unique[$key] = true;
            $deduped[] = $result;
            if (count($deduped) >= max(1, $limit)) {
                break;
            }
        }

        return array_values($deduped);
    }
}
