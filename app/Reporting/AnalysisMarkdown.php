<?php

namespace App\Reporting;

/**
 * Splits an uploaded analysis (Markdown) into the readable body and its
 * action checklist. Pure PHP so it can be tested on its own.
 *
 * Checklist lines: "- [ ] text" / "[ ] text" / "- [x] text" (done).
 * Each item belongs to the nearest heading above it ("### დღეს") or to a
 * label line ending with ":" ("**2 კვირაში:**"). Headings left with nothing
 * but checklist items are removed from the body — the checklist is shown
 * on its own, with tick boxes.
 */
final class AnalysisMarkdown
{
    private const ITEM = '/^\s*(?:[-*+]\s+)?\[( |x|X)\]\s+(.+)$/u';

    private const HEADING = '/^(#{1,6})\s+(.+?)\s*#*\s*$/u';

    private const LABEL = '/^\s*(?:\*\*|__)?(.{2,120}?):(?:\*\*|__)?\s*$/u';

    /** @return array{body: string, actions: list<array{group: string, text: string, done: bool}>, summary: string} */
    public static function split(string $markdown): array
    {
        $markdown = str_replace(["\r\n", "\r"], "\n", preg_replace('/^\xEF\xBB\xBF/', '', $markdown));
        $lines = explode("\n", $markdown);

        $group = '';
        $actions = [];
        $kept = [];
        foreach ($lines as $line) {
            if (preg_match(self::HEADING, $line, $m)) {
                $group = self::clean($m[2]);
                $kept[] = $line;

                continue;
            }
            if (preg_match(self::ITEM, $line, $m)) {
                $actions[] = ['group' => mb_substr($group, 0, 120), 'text' => mb_substr(self::clean($m[2]), 0, 500), 'done' => strtolower($m[1]) === 'x'];

                continue;
            }
            if (preg_match(self::LABEL, $line, $m) && ! str_contains($line, '|')) {
                // a label line opens a checklist group only if items follow it
                $group = self::clean($m[1]);
            }
            $kept[] = $line;
        }

        $kept = self::dropEmptySections($kept);

        return [
            'body' => trim(preg_replace("/\n{3,}/", "\n\n", implode("\n", $kept))),
            'actions' => $actions,
            'summary' => self::summary($kept),
        ];
    }

    /** Remove headings and "label:" lines whose section became empty. */
    private static function dropEmptySections(array $lines): array
    {
        do {
            $changed = false;
            $count = count($lines);
            for ($i = 0; $i < $count; $i++) {
                $isHeading = preg_match(self::HEADING, $lines[$i], $h);
                $isLabel = ! $isHeading && preg_match(self::LABEL, $lines[$i]) && ! str_contains($lines[$i], '|');
                if (! $isHeading && ! $isLabel) {
                    continue;
                }
                $level = $isHeading ? strlen($h[1]) : 7;
                $j = $i + 1;
                while ($j < $count && trim($lines[$j]) === '') {
                    $j++;
                }
                $nextHeading = $j < $count && preg_match(self::HEADING, $lines[$j], $n);
                $empty = $j >= $count
                    || ($nextHeading && strlen($n[1]) <= $level)
                    || ($isLabel && ($nextHeading || (preg_match(self::LABEL, $lines[$j]) && ! str_contains($lines[$j], '|'))));
                if ($empty) {
                    array_splice($lines, $i, 1);
                    $changed = true;

                    break;
                }
            }
        } while ($changed);

        return $lines;
    }

    /** First real paragraph, for the list view. */
    private static function summary(array $lines): string
    {
        $para = [];
        foreach ($lines as $line) {
            $t = trim($line);
            if ($t === '' || preg_match(self::HEADING, $t) || str_starts_with($t, '|') || str_starts_with($t, '>') && $para === []) {
                if ($para !== []) {
                    break;
                }

                continue;
            }
            if (preg_match('/^([-*+]|\d+\.)\s/', $t) && $para === []) {
                continue;
            }
            $para[] = $t;
        }

        return mb_substr(self::clean(implode(' ', $para)), 0, 400);
    }

    private static function clean(string $text): string
    {
        return trim(preg_replace(['/\*\*|__|`/u', '/\s+/u'], ['', ' '], $text));
    }
}
