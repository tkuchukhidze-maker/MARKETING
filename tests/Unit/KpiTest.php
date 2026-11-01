<?php

namespace Tests\Unit;

use App\Reporting\CsvMetrics;
use App\Reporting\Kpi;
use PHPUnit\Framework\TestCase;

class KpiTest extends TestCase
{
    private function bucket(array $t, float $costs = 0, float $margin = 0.65, float $share = 0.30): array
    {
        return compact('t', 'costs', 'margin', 'share');
    }

    public function test_romi_roi_mer_and_unit_costs(): void
    {
        $now = [$this->bucket([
            'pos.revenue' => 100000, 'pos.new_customers' => 500, 'pos.checks' => 1000, 'pos.reservations' => 200,
            'google_business.gbp_bookings' => 50,
            'meta_ads.spend' => 2000, 'google_ads.spend' => 1000, 'google_ads.conversion_value' => 9000,
            'meta_ads.impressions' => 500000, 'meta_ads.clicks' => 5000,
        ], costs: 2000)];
        $prev = [$this->bucket(['pos.revenue' => 90000])];

        $v = Kpi::compute($now, $prev)['values'];

        $this->assertEqualsWithDelta(5000, $v['marketing_spend'], 1e-6);
        $this->assertEqualsWithDelta(30000, $v['attributed_revenue'], 1e-6);
        $this->assertEqualsWithDelta(290, $v['romi'], 1e-6);   // (30 000 × .65 − 5 000) ÷ 5 000
        $this->assertEqualsWithDelta(30, $v['roi'], 1e-6);     // (10 000 × .65 − 5 000) ÷ 5 000
        $this->assertEqualsWithDelta(20, $v['mer'], 1e-6);
        $this->assertEqualsWithDelta(3, $v['roas'], 1e-6);
        $this->assertEqualsWithDelta(10, $v['cac'], 1e-6);
        $this->assertEqualsWithDelta(20, $v['cost_per_reservation'], 1e-6);
        $this->assertEqualsWithDelta(100, $v['avg_check'], 1e-6);
        $this->assertEqualsWithDelta(6, $v['cpm'], 1e-6);
        $this->assertEqualsWithDelta(1, $v['ctr'], 1e-6);
    }

    public function test_tracked_marketing_revenue_beats_the_share_and_margins_stay_per_brand(): void
    {
        $r = Kpi::compute([
            $this->bucket(['pos.revenue' => 1000, 'pos.marketing_revenue' => 400], costs: 100, margin: 0.5),
            $this->bucket(['pos.revenue' => 2000], costs: 100, margin: 0.7, share: 0.1),
        ]);

        $this->assertSame('mixed', $r['attribution']);
        $this->assertEqualsWithDelta(600, $r['values']['attributed_revenue'], 1e-6);
        $this->assertEqualsWithDelta(((400 * .5 + 200 * .7) - 200) / 200 * 100, $r['values']['romi'], 1e-6);
        $this->assertNull($r['values']['roi']);
    }

    public function test_no_sales_means_no_revenue_kpis_instead_of_zero(): void
    {
        $v = Kpi::compute([$this->bucket(['meta_ads.spend' => 10])])['values'];

        $this->assertNull($v['romi']);
        $this->assertNull($v['revenue']);
        $this->assertSame(10.0, $v['marketing_spend']);
    }

    public function test_delta_and_tone(): void
    {
        $this->assertEqualsWithDelta(10, Kpi::delta('romi', 50, 40), 1e-6);      // percentage points
        $this->assertEqualsWithDelta(10, Kpi::delta('revenue', 110, 100), 1e-6); // percent
        $this->assertSame('good', Kpi::tone('cpc', -10));
        $this->assertSame('bad', Kpi::tone('revenue', -10));
        $this->assertSame('290%', Kpi::format('romi', 290.0));
        $this->assertSame('5 000 ₾', Kpi::format('marketing_spend', 5000.0));
    }

    public function test_csv_reader_handles_georgian_headers_and_local_numbers(): void
    {
        $p = CsvMetrics::parse("\xEF\xBB\xBFთარიღი;revenue;checks;foo\n31.10.2026;1 234,50;12;x\n2026-11-01;\"2,500\";;\nbad;1;1;\n");

        $this->assertCount(3, $p['rows']);
        $this->assertSame('2026-10-31', $p['rows'][0]['date']);
        $this->assertSame(1234.5, $p['rows'][0]['value']);
        $this->assertSame(2500.0, $p['rows'][2]['value']);
        $this->assertCount(2, $p['errors']);
        $this->assertSame(1234.56, CsvMetrics::number('1,234.56'));
        $this->assertSame(1234.5, CsvMetrics::number('1.234,5'));
    }
}
