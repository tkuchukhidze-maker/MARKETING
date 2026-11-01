<?php

namespace App\Http\Controllers\Reporting;

use App\Http\Controllers\Controller;
use App\Models\Venue;
use App\Reporting\Kpi;
use App\Reporting\ReportBuilder;
use Illuminate\Http\Request;
use Illuminate\Support\Collection;
use Illuminate\View\View;
use Symfony\Component\HttpFoundation\StreamedResponse;

/** The CMO dashboard: every brand, every platform, one screen. */
class ReportController extends Controller
{
    public function index(Request $request, ReportBuilder $builder): View
    {
        [$venues, $brand] = $this->venues($request);
        [$from, $to, $pf, $pt, $preset] = ReportBuilder::period($request->query('period'), $request->query('from'), $request->query('to'));

        return view('reports.dashboard', [
            'allVenues' => Venue::orderBy('sort')->get(),
            'brand' => $brand,
            'preset' => $preset,
            'from' => $from, 'to' => $to, 'prevFrom' => $pf, 'prevTo' => $pt,
            'report' => $builder->build($venues, $from, $to, $pf, $pt),
            'currency' => config('reporting.currency'),
            'query' => array_filter($request->only(['brand', 'period', 'from', 'to'])),
        ]);
    }

    /** Every KPI per brand as CSV (opens in Excel / Google Sheets). */
    public function export(Request $request, ReportBuilder $builder): StreamedResponse
    {
        [$venues] = $this->venues($request);
        [$from, $to, $pf, $pt] = ReportBuilder::period($request->query('period'), $request->query('from'), $request->query('to'));
        $report = $builder->build($venues, $from, $to, $pf, $pt);
        $name = 'cmo-report-'.$from->toDateString().'_'.$to->toDateString().'.csv';

        return response()->streamDownload(function () use ($report, $venues) {
            $out = fopen('php://output', 'w');
            fwrite($out, "\xEF\xBB\xBF");
            fputcsv($out, array_merge(['ჯგუფი', 'KPI', 'სულ', 'წინა პერიოდი', 'ცვლილება %'], $venues->pluck('name')->all(), ['ფორმულა']), ',', '"', '');
            foreach ($report['kpis'] as $key => $k) {
                fputcsv($out, array_merge(
                    [Kpi::GROUPS[$k['group']], $k['label'], $this->num($k['value']), $this->num($k['prev']), $this->num($k['delta'])],
                    $report['brands']->map(fn ($b) => $this->num($b['kpi'][$key] ?? null))->all(),
                    [$k['formula']],
                ), ',', '"', '');
            }
            fclose($out);
        }, $name, ['Content-Type' => 'text/csv; charset=UTF-8']);
    }

    /** @return array{0: Collection<int, Venue>, 1: ?Venue} */
    private function venues(Request $request): array
    {
        $all = Venue::orderBy('sort')->get();
        $brand = $all->firstWhere('slug', $request->query('brand'));

        return [$brand ? collect([$brand]) : $all, $brand];
    }

    private function num(?float $v): string
    {
        return $v === null ? '' : (string) round($v, 2);
    }
}
