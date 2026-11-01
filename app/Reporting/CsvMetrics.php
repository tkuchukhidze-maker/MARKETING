<?php

namespace App\Reporting;

use DateTimeImmutable;

/**
 * Reads a CSV export into unified rows. Pure PHP, no framework.
 *
 * Expected: a header row with a date column (date / თარიღი / day) and one
 * column per metric key from Catalog::METRICS (e.g. revenue, checks, spend…).
 * An optional "campaign" column keeps ad campaigns apart.
 * Comma, semicolon or tab separated; dates as 2026-10-31, 31.10.2026 or 31/10/2026.
 */
final class CsvMetrics
{
    private const DATE_COLUMNS = ['date', 'თარიღი', 'day', 'დღე', 'tarigi'];

    private const CAMPAIGN_COLUMNS = ['campaign', 'კამპანია'];

    /**
     * @return array{rows: list<array{date: string, metric: string, value: float, campaign: string}>, errors: list<string>, metrics: list<string>}
     */
    public static function parse(string $content, ?array $allowed = null): array
    {
        $allowed ??= array_keys(Catalog::METRICS);
        $content = preg_replace('/^\xEF\xBB\xBF/', '', $content);   // Excel BOM
        $lines = preg_split('/\r\n|\r|\n/', trim($content));
        if (! $lines || trim($lines[0]) === '') {
            return ['rows' => [], 'errors' => ['ფაილი ცარიელია.'], 'metrics' => []];
        }

        $delimiter = self::delimiter($lines[0]);
        $header = array_map(fn ($h) => mb_strtolower(trim($h)), str_getcsv($lines[0], $delimiter, '"', ''));

        $dateCol = null;
        $campaignCol = null;
        $metricCols = [];
        $errors = [];
        foreach ($header as $i => $name) {
            if (in_array($name, self::DATE_COLUMNS, true)) {
                $dateCol = $i;
            } elseif (in_array($name, self::CAMPAIGN_COLUMNS, true)) {
                $campaignCol = $i;
            } elseif (in_array($name, $allowed, true)) {
                $metricCols[$i] = $name;
            } elseif ($name !== '') {
                $errors[] = "სვეტი „{$name}“ უცნობია და გამოტოვდა.";
            }
        }

        if ($dateCol === null) {
            return ['rows' => [], 'errors' => ['თარიღის სვეტი (date) ვერ მოიძებნა.'], 'metrics' => []];
        }
        if ($metricCols === []) {
            return ['rows' => [], 'errors' => array_merge($errors, ['არცერთი ცნობილი მეტრიკის სვეტი არ არის.']), 'metrics' => []];
        }

        $rows = [];
        foreach (array_slice($lines, 1) as $n => $line) {
            if (trim($line) === '') {
                continue;
            }
            $cells = str_getcsv($line, $delimiter, '"', '');
            $date = self::date($cells[$dateCol] ?? '');
            if ($date === null) {
                $errors[] = 'სტრიქონი '.($n + 2).': თარიღი ვერ წავიკითხე.';

                continue;
            }
            $campaign = $campaignCol !== null ? trim((string) ($cells[$campaignCol] ?? '')) : '';
            foreach ($metricCols as $i => $metric) {
                $raw = trim((string) ($cells[$i] ?? ''));
                if ($raw === '') {
                    continue;
                }
                $value = self::number($raw);
                if ($value === null) {
                    $errors[] = 'სტრიქონი '.($n + 2).": „{$metric}“ რიცხვი არ არის.";

                    continue;
                }
                $rows[] = ['date' => $date, 'metric' => $metric, 'value' => $value, 'campaign' => $campaign];
            }
        }

        return ['rows' => $rows, 'errors' => array_slice($errors, 0, 20), 'metrics' => array_values($metricCols)];
    }

    private static function delimiter(string $line): string
    {
        $counts = [',' => substr_count($line, ','), ';' => substr_count($line, ';'), "\t" => substr_count($line, "\t")];
        arsort($counts);

        return (string) array_key_first($counts);
    }

    public static function date(string $value): ?string
    {
        $value = trim($value);
        foreach (['Y-m-d', 'd.m.Y', 'd/m/Y', 'Ymd', 'Y/m/d', 'd-m-Y'] as $format) {
            $d = DateTimeImmutable::createFromFormat('!'.$format, $value);
            if ($d && $d->format($format) === $value) {
                return $d->format('Y-m-d');
            }
        }

        return null;
    }

    /** "1 234,50" / "1,234.50" / "₾1234" → 1234.5 */
    public static function number(string $value): ?float
    {
        $v = preg_replace('/[^\d,.\-]/u', '', $value);
        if ($v === '' || $v === '-') {
            return null;
        }
        if (str_contains($v, ',') && str_contains($v, '.')) {
            // the last separator is the decimal one
            $v = strrpos($v, ',') > strrpos($v, '.') ? str_replace(['.', ','], ['', '.'], $v) : str_replace(',', '', $v);
        } elseif (str_contains($v, ',')) {
            // "1,234" / "12,345,678" are thousands; "12,5" is a decimal comma
            $v = preg_match('/^-?\d{1,3}(,\d{3})+$/', $v) ? str_replace(',', '', $v) : str_replace(',', '.', $v);
        }

        return is_numeric($v) ? (float) $v : null;
    }
}
