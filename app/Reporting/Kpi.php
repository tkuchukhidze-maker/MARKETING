<?php

namespace App\Reporting;

/**
 * Every CMO KPI and the formula behind it. Pure PHP: give it the summed
 * numbers per brand and it returns the KPIs, so the maths is testable alone.
 *
 * A "bucket" is one brand for one period:
 *   [
 *     't'      => ['meta_ads.spend' => 1200.0, 'pos.revenue' => 54000.0, ...],  // provider.metric => sum
 *     'costs'  => 800.0,   // manual marketing costs (agency, influencers, print…)
 *     'margin' => 0.65,    // gross margin of revenue
 *     'share'  => 0.30,    // share of revenue credited to marketing when it isn't tracked
 *   ]
 */
final class Kpi
{
    /**
     * key => [label, group, unit, better (up|down|null), formula]
     * unit: money | count | pct | ratio | rank
     */
    public const DEFS = [
        // ---- finance: what marketing costs and what it brings back
        'marketing_spend' => ['მარკეტინგის ხარჯი', 'finance', 'money', null, 'რეკლამის ხარჯი (Meta + Google + TikTok) + სხვა მარკეტინგული ხარჯები'],
        'ad_spend' => ['რეკლამის ხარჯი (პლატფორმები)', 'finance', 'money', null, 'Meta Ads + Google Ads + TikTok Ads, ლარში გადაყვანილი'],
        'other_costs' => ['სხვა მარკეტინგული ხარჯი', 'finance', 'money', null, 'სააგენტო, ინფლუენსერები, გადაღება, ბეჭდვა… (ხელით შეყვანილი)'],
        'revenue' => ['შემოსავალი', 'finance', 'money', 'up', 'POS-ის შემოსავალი პერიოდში'],
        'attributed_revenue' => ['მარკეტინგის შემოსავალი', 'finance', 'money', 'up', 'POS-ში მონიშნული მარკეტინგული შემოსავალი; თუ არ არის — შემოსავალი × ატრიბუციის წილი'],
        'romi' => ['ROMI', 'finance', 'pct', 'up', '(მარკეტინგის შემოსავალი × მარჟა − მარკეტინგის ხარჯი) ÷ მარკეტინგის ხარჯი'],
        'roi' => ['ROI (ზრდაზე)', 'finance', 'pct', 'up', '((შემოსავალი − წინა პერიოდის შემოსავალი) × მარჟა − მარკეტინგის ხარჯი) ÷ მარკეტინგის ხარჯი'],
        'mer' => ['MER', 'finance', 'ratio', 'up', 'მთლიანი შემოსავალი ÷ მარკეტინგის ხარჯი (რამდენი ლარი შემოვიდა 1 ლარზე)'],
        'roas' => ['ROAS (პლატფორმის)', 'finance', 'ratio', 'up', 'პლატფორმის მიერ დათვლილი კონვერსიის ღირებულება ÷ რეკლამის ხარჯი'],
        'spend_share' => ['ხარჯი შემოსავლის %', 'finance', 'pct', 'down', 'მარკეტინგის ხარჯი ÷ შემოსავალი'],
        'cac' => ['CAC — ახალი სტუმრის ფასი', 'finance', 'money', 'down', 'მარკეტინგის ხარჯი ÷ ახალი სტუმრები'],
        'cost_per_reservation' => ['ერთი ჯავშნის ფასი', 'finance', 'money', 'down', 'მარკეტინგის ხარჯი ÷ ჯავშნები'],

        // ---- sales (POS)
        'checks' => ['ჩეკები / შეკვეთები', 'sales', 'count', 'up', 'POS'],
        'guests' => ['სტუმრები', 'sales', 'count', 'up', 'POS'],
        'reservations' => ['ჯავშნები', 'sales', 'count', 'up', 'POS / ჯავშნის სისტემა + Google-ის ჯავშნები'],
        'new_customers' => ['ახალი სტუმრები', 'sales', 'count', 'up', 'POS / ლოიალობის სისტემა'],
        'avg_check' => ['საშუალო ჩეკი', 'sales', 'money', 'up', 'შემოსავალი ÷ ჩეკები'],
        'revenue_per_guest' => ['შემოსავალი სტუმარზე', 'sales', 'money', 'up', 'შემოსავალი ÷ სტუმრები'],

        // ---- paid ads
        'paid_impressions' => ['ნახვები (რეკლამა)', 'paid', 'count', 'up', 'ყველა სარეკლამო პლატფორმის impressions'],
        'paid_reach' => ['მიწვდომა (რეკლამა)', 'paid', 'count', 'up', 'კამპანიების reach-ის ჯამი (ადამიანი შეიძლება რამდენჯერმე ჩაითვალოს)'],
        'paid_clicks' => ['კლიკები (რეკლამა)', 'paid', 'count', 'up', 'ყველა სარეკლამო პლატფორმის კლიკი'],
        'paid_conversions' => ['კონვერსიები (რეკლამა)', 'paid', 'count', 'up', 'მესიჯი, ლიდი, ზარი, ჯავშანი — პლატფორმის მიხედვით'],
        'cpm' => ['CPM', 'paid', 'money', 'down', 'რეკლამის ხარჯი ÷ ნახვები × 1000'],
        'cpc' => ['CPC', 'paid', 'money', 'down', 'რეკლამის ხარჯი ÷ კლიკები'],
        'ctr' => ['CTR', 'paid', 'pct', 'up', 'კლიკები ÷ ნახვები'],
        'cpa' => ['CPA — კონვერსიის ფასი', 'paid', 'money', 'down', 'რეკლამის ხარჯი ÷ კონვერსიები'],
        'paid_cr' => ['კონვერსიის მაჩვენებელი', 'paid', 'pct', 'up', 'კონვერსიები ÷ კლიკები'],

        // ---- organic social
        'organic_reach' => ['ორგანული მიწვდომა', 'organic', 'count', 'up', 'Facebook + Instagram reach (TikTok-ს reach არ აქვს)'],
        'organic_impressions' => ['ორგანული ნახვები', 'organic', 'count', 'up', 'Facebook + Instagram views + TikTok ვიდეოს ნახვები'],
        'organic_engagement' => ['ჩართულობა', 'organic', 'count', 'up', 'რეაქციები, კომენტარები, გაზიარებები, შენახვები'],
        'engagement_rate' => ['ჩართულობის მაჩვენებელი', 'organic', 'pct', 'up', 'ჩართულობა ÷ ორგანული ნახვები'],
        'followers' => ['გამომწერები სულ', 'organic', 'count', 'up', 'პერიოდის ბოლო დღის მდგომარეობა, ყველა ანგარიში'],
        'new_followers' => ['ახალი გამომწერები', 'organic', 'count', 'up', 'პერიოდში მოსული გამომწერები'],
        'profile_views' => ['პროფილის ნახვა', 'organic', 'count', 'up', 'Instagram + TikTok'],

        // ---- website
        'sessions' => ['საიტის სესიები', 'web', 'count', 'up', 'GA4'],
        'users' => ['საიტის მომხმარებლები', 'web', 'count', 'up', 'GA4 (დღეების ჯამი)'],
        'new_users' => ['ახალი მომხმარებლები', 'web', 'count', 'up', 'GA4'],
        'key_events' => ['მიზნობრივი მოქმედებები', 'web', 'count', 'up', 'GA4 key events (ჯავშანი, ზარი, მენიუ…)'],
        'web_cr' => ['საიტის კონვერსია', 'web', 'pct', 'up', 'მიზნობრივი მოქმედებები ÷ სესიები'],
        'web_engagement' => ['ჩართული სესიების %', 'web', 'pct', 'up', 'ჩართული სესიები ÷ სესიები'],

        // ---- Google maps / Business Profile
        'gbp_views' => ['ნახვები Google-ზე', 'local', 'count', 'up', 'ძიება + რუკა, მობილური + კომპიუტერი'],
        'calls' => ['ზარები Google-დან', 'local', 'count', 'up', 'Business Profile'],
        'directions' => ['მარშრუტის მოთხოვნა', 'local', 'count', 'up', 'Business Profile'],
        'local_actions' => ['Google-ის ქმედებები', 'local', 'count', 'up', 'ზარი + მარშრუტი + საიტზე გადასვლა + ჯავშანი'],
        'local_action_rate' => ['ქმედების მაჩვენებელი', 'local', 'pct', 'up', 'ქმედებები ÷ ნახვები'],

        // ---- organic search
        'search_clicks' => ['ძიების კლიკები', 'search', 'count', 'up', 'Search Console'],
        'search_impressions' => ['ძიების ნახვები', 'search', 'count', 'up', 'Search Console'],
        'search_ctr' => ['ძიების CTR', 'search', 'pct', 'up', 'კლიკები ÷ ნახვები'],
        'search_position' => ['საშუალო პოზიცია', 'search', 'rank', 'down', 'Σ(პოზიცია × ნახვა) ÷ ნახვები'],
    ];

    public const GROUPS = [
        'finance' => 'ფინანსები და უკუგება',
        'sales' => 'გაყიდვები',
        'paid' => 'ფასიანი რეკლამა',
        'organic' => 'ორგანული სოც. მედია',
        'web' => 'ვებსაიტი',
        'local' => 'Google რუკა',
        'search' => 'ორგანული ძიება',
    ];

    /**
     * @param  list<array{t: array<string, float>, costs: float, margin: float, share: float}>  $buckets
     * @param  list<array{t: array<string, float>, costs: float, margin: float, share: float}>|null  $previous  same brands, previous period, same order
     * @return array{values: array<string, ?float>, attribution: string}
     */
    public static function compute(array $buckets, ?array $previous = null): array
    {
        $t = [];
        $costs = 0.0;
        $attributed = 0.0;
        $attributedProfit = 0.0;
        $incrementalProfit = null;
        $methods = [];

        foreach ($buckets as $i => $b) {
            foreach ($b['t'] as $k => $v) {
                $t[$k] = ($t[$k] ?? 0) + $v;
            }
            $costs += $b['costs'];

            $revenue = $b['t']['pos.revenue'] ?? 0.0;
            $tracked = $b['t']['pos.marketing_revenue'] ?? 0.0;
            if ($revenue <= 0 && $tracked <= 0) {
                continue;
            }
            $attr = $tracked > 0 ? $tracked : $revenue * $b['share'];
            $methods[$tracked > 0 ? 'tracked' : 'share'] = true;
            $attributed += $attr;
            $attributedProfit += $attr * $b['margin'];

            if ($previous !== null && isset($previous[$i])) {
                $prevRevenue = $previous[$i]['t']['pos.revenue'] ?? 0.0;
                if ($prevRevenue > 0) {
                    $incrementalProfit = ($incrementalProfit ?? 0) + ($revenue - $prevRevenue) * $b['margin'];
                }
            }
        }

        $sum = fn (string $channel, string $metric) => array_sum(array_map(
            fn ($p) => $t[$p.'.'.$metric] ?? 0.0,
            Catalog::providersIn($channel),
        ));
        $div = fn (?float $a, ?float $b, float $mul = 1.0) => ($a === null || ! $b) ? null : $a / $b * $mul;

        $adSpend = $sum('paid', 'spend');
        $spend = $adSpend + $costs;
        $revenue = $t['pos.revenue'] ?? 0.0;
        $hasSales = $revenue > 0;
        $newCustomers = $t['pos.new_customers'] ?? 0.0;
        $reservations = ($t['pos.reservations'] ?? 0.0) + ($t['google_business.gbp_bookings'] ?? 0.0);
        $checks = $t['pos.checks'] ?? 0.0;
        $guests = $t['pos.guests'] ?? 0.0;

        $paidImpr = $sum('paid', 'impressions');
        $paidClicks = $sum('paid', 'clicks');
        $paidConv = $sum('paid', 'conversions');
        $convValue = $sum('paid', 'conversion_value');

        $orgImpr = $sum('organic', 'impressions');
        $orgEng = $sum('organic', 'engagement');

        $sessions = $t['ga4.sessions'] ?? 0.0;
        $gbpViews = $t['google_business.gbp_views'] ?? 0.0;
        $localActions = ($t['google_business.calls'] ?? 0) + ($t['google_business.directions'] ?? 0)
            + ($t['google_business.gbp_website_clicks'] ?? 0) + ($t['google_business.gbp_bookings'] ?? 0);
        $searchImpr = $t['search_console.search_impressions'] ?? 0.0;

        $values = [
            'marketing_spend' => $spend,
            'ad_spend' => $adSpend,
            'other_costs' => $costs,
            'revenue' => $hasSales ? $revenue : null,
            'attributed_revenue' => $hasSales ? $attributed : null,
            'romi' => $hasSales ? $div($attributedProfit - $spend, $spend, 100) : null,
            'roi' => $incrementalProfit === null ? null : $div($incrementalProfit - $spend, $spend, 100),
            'mer' => $hasSales ? $div($revenue, $spend) : null,
            'roas' => $div($convValue ?: null, $adSpend),
            'spend_share' => $div($spend, $revenue, 100),
            'cac' => $div($spend ?: null, $newCustomers),
            'cost_per_reservation' => $div($spend ?: null, $reservations),

            'checks' => $checks ?: null,
            'guests' => $guests ?: null,
            'reservations' => $reservations ?: null,
            'new_customers' => $newCustomers ?: null,
            'avg_check' => $div($hasSales ? $revenue : null, $checks),
            'revenue_per_guest' => $div($hasSales ? $revenue : null, $guests),

            'paid_impressions' => $paidImpr,
            'paid_reach' => $sum('paid', 'reach'),
            'paid_clicks' => $paidClicks,
            'paid_conversions' => $paidConv,
            'cpm' => $div($adSpend, $paidImpr, 1000),
            'cpc' => $div($adSpend, $paidClicks),
            'ctr' => $div($paidClicks, $paidImpr, 100),
            'cpa' => $div($adSpend, $paidConv),
            'paid_cr' => $div($paidConv, $paidClicks, 100),

            'organic_reach' => $sum('organic', 'reach'),
            'organic_impressions' => $orgImpr,
            'organic_engagement' => $orgEng,
            'engagement_rate' => $div($orgEng, $orgImpr, 100),
            'followers' => $sum('organic', 'followers') ?: null,
            'new_followers' => $sum('organic', 'new_followers'),
            'profile_views' => $sum('organic', 'profile_views'),

            'sessions' => $sessions,
            'users' => $t['ga4.users'] ?? 0.0,
            'new_users' => $t['ga4.new_users'] ?? 0.0,
            'key_events' => $t['ga4.key_events'] ?? 0.0,
            'web_cr' => $div($t['ga4.key_events'] ?? 0.0, $sessions, 100),
            'web_engagement' => $div($t['ga4.engaged_sessions'] ?? 0.0, $sessions, 100),

            'gbp_views' => $gbpViews,
            'calls' => $t['google_business.calls'] ?? 0.0,
            'directions' => $t['google_business.directions'] ?? 0.0,
            'local_actions' => $localActions,
            'local_action_rate' => $div($localActions, $gbpViews, 100),

            'search_clicks' => $t['search_console.search_clicks'] ?? 0.0,
            'search_impressions' => $searchImpr,
            'search_ctr' => $div($t['search_console.search_clicks'] ?? 0.0, $searchImpr, 100),
            'search_position' => $div($t['search_console.search_position_weighted'] ?? 0.0, $searchImpr),
        ];

        $attribution = match (true) {
            isset($methods['tracked'], $methods['share']) => 'mixed',
            isset($methods['tracked']) => 'tracked',
            isset($methods['share']) => 'share',
            default => 'none',
        };

        return ['values' => $values, 'attribution' => $attribution];
    }

    /** Change vs previous period: % for amounts, percentage points for rates. */
    public static function delta(string $key, ?float $current, ?float $previous): ?float
    {
        if ($current === null || $previous === null) {
            return null;
        }
        if (self::DEFS[$key][2] === 'pct') {
            return $current - $previous;
        }

        return $previous == 0.0 ? null : ($current - $previous) / abs($previous) * 100;
    }

    /** good | bad | flat, from the KPI's direction. */
    public static function tone(string $key, ?float $delta): string
    {
        $better = self::DEFS[$key][3];
        if ($delta === null || $better === null || abs($delta) < 0.5) {
            return 'flat';
        }

        return ($delta > 0) === ($better === 'up') ? 'good' : 'bad';
    }

    public static function format(string $key, ?float $value, string $currency = '₾'): string
    {
        if ($value === null) {
            return '—';
        }

        return match (self::DEFS[$key][2] ?? 'count') {
            'money' => self::number($value, abs($value) < 100 ? 2 : 0).' '.$currency,
            'pct' => self::number($value, abs($value) < 10 ? 1 : 0).'%',
            'ratio' => self::number($value, 2).'×',
            'rank' => self::number($value, 1),
            default => self::compact($value),
        };
    }

    public static function number(float $value, int $decimals = 0): string
    {
        return number_format($value, $decimals, '.', ' ');
    }

    /** 1 234 / 12.3K / 1.2M */
    public static function compact(float $value): string
    {
        $abs = abs($value);

        return match (true) {
            $abs >= 1_000_000 => self::number($value / 1_000_000, 1).'M',
            $abs >= 100_000 => self::number($value / 1000, 0).'K',
            default => self::number($value, $abs < 10 && floor($value) != $value ? 1 : 0),
        };
    }
}
