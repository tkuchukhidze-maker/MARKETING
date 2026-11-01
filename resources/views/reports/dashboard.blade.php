@extends('layouts.app')
@use('App\Reporting\Kpi')
@use('App\Reporting\Catalog')
@use('App\Reporting\ReportBuilder')

@section('title', 'CMO რეპორტინგი')

@push('styles')
<link rel="stylesheet" href="{{ asset('css/reports.css') }}?v=1">
@endpush

@php
    $k = $report['kpis'];
    $fmt = fn ($key, $v = 'value') => Kpi::format($key, $k[$key][$v], $currency);
    $dlt = function ($key) use ($k) {
        $d = $k[$key]['delta'];
        if ($d === null) return '';
        $unit = $k[$key]['unit'] === 'pct' ? ' პ.პ.' : '%';
        return '<span class="delta '.$k[$key]['tone'].'">'.($d > 0 ? '▲ ' : ($d < 0 ? '▼ ' : '')).Kpi::number(abs($d), abs($d) < 10 ? 1 : 0).$unit.'</span>';
    };
    $money = fn (?float $v) => $v === null ? '—' : Kpi::number($v, abs($v) < 100 ? 2 : 0).' '.$currency;
    $hasSources = $report['sources']->isNotEmpty();
    $hasSales = $k['revenue']['value'] !== null;
    $paid = collect($report['channels'])->where('channel', 'paid');
    $organic = collect($report['channels'])->where('channel', 'organic');
    $venueNames = $allVenues->pluck('name', 'id');
@endphp

@section('content')
<header class="page-head">
    <h1>CMO რეპორტინგი</h1>
    <span class="spacer"></span>
    <a class="btn sm" href="{{ route('reports.export', $query) }}">
        <svg width="14" height="14" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" aria-hidden="true"><path d="M12 3v12m0 0l-4-4m4 4l4-4M4 21h16"/></svg>
        CSV / Excel
    </a>
    <a class="btn sm" href="{{ route('reports.sources') }}">პლატფორმები</a>
</header>

<div class="rp-filters">
    <nav class="rp-chips" aria-label="ბრენდი">
        <a class="rp-chip {{ $brand ? '' : 'is-on' }}" href="{{ route('reports.index', array_diff_key($query, ['brand' => 1])) }}">ყველა ბრენდი</a>
        @foreach ($allVenues as $v)
            <a class="rp-chip {{ $brand?->is($v) ? 'is-on' : '' }}" href="{{ route('reports.index', ['brand' => $v->slug] + $query) }}">
                <span class="venue-dot" style="background: {{ $v->color }}"></span>{{ $v->name }}
            </a>
        @endforeach
    </nav>

    <form class="rp-period" method="get" action="{{ route('reports.index') }}">
        @if ($brand)<input type="hidden" name="brand" value="{{ $brand->slug }}">@endif
        <label class="sr-only" for="period">პერიოდი</label>
        <select id="period" name="period" onchange="this.form.querySelectorAll('[data-custom]').forEach(e => e.hidden = this.value !== 'custom'); if (this.value !== 'custom') this.form.submit()">
            @foreach (ReportBuilder::PRESETS as $key => $label)
                <option value="{{ $key }}" @selected($preset === $key)>{{ $label }}</option>
            @endforeach
        </select>
        <input data-custom type="date" name="from" value="{{ $from->toDateString() }}" aria-label="დან" @if ($preset !== 'custom') hidden @endif>
        <input data-custom type="date" name="to" value="{{ $to->toDateString() }}" aria-label="მდე" @if ($preset !== 'custom') hidden @endif>
        <button data-custom class="btn sm primary" type="submit" @if ($preset !== 'custom') hidden @endif>ნახვა</button>
    </form>
</div>
<p class="rp-range">
    <b>{{ $from->format('d.m.Y') }} — {{ $to->format('d.m.Y') }}</b>
    · შედარება: {{ $prevFrom->format('d.m.Y') }} — {{ $prevTo->format('d.m.Y') }}
    @if ($report['lastDataDay']) · ბოლო მონაცემი: {{ \Illuminate\Support\Carbon::parse($report['lastDataDay'])->format('d.m.Y') }}@endif
</p>

@unless ($hasSources)
    <div class="rp-callout">
        <span aria-hidden="true">⚡</span>
        <p><b>ჯერ არცერთი პლატფორმა არ არის დაკავშირებული.</b> დაამატე Meta, Google, TikTok და POS — ან ატვირთე CSV — და რეპორტი ავტომატურად აეწყობა.
            <a href="{{ route('reports.sources') }}">პლატფორმების დაკავშირება →</a></p>
    </div>
@endunless

@if ($report['sources']->where('last_status', 'error')->isNotEmpty())
    <div class="rp-callout">
        <span aria-hidden="true">!</span>
        <p><b>{{ $report['sources']->where('last_status', 'error')->count() }} წყარო ვერ განახლდა:</b>
            {{ $report['sources']->where('last_status', 'error')->map(fn ($s) => $s->venue->name.' · '.$s->label)->join(', ') }}.
            <a href="{{ route('reports.sources') }}">გასწორება →</a></p>
    </div>
@endif

{{-- ---- headline: money in, money out ---- --}}
<section class="rp-tiles" aria-label="მთავარი მაჩვენებლები">
    @foreach (['marketing_spend', 'revenue', 'romi', 'roi', 'mer', 'cac'] as $key)
        <div class="rp-tile hero">
            <span class="lbl">{{ $k[$key]['label'] }} <span class="rp-info" title="{{ $k[$key]['formula'] }}">i</span></span>
            <span class="val">{{ $fmt($key) }}</span>
            <span class="sub">{!! $dlt($key) !!} წინა: {{ $fmt($key, 'prev') }}</span>
        </div>
    @endforeach
</section>
<section class="rp-tiles" aria-label="დამატებითი მაჩვენებლები" style="margin-bottom: 18px">
    @foreach (['roas', 'cost_per_reservation', 'avg_check', 'spend_share', 'new_followers', 'local_actions'] as $key)
        <div class="rp-tile sm">
            <span class="lbl">{{ $k[$key]['label'] }} <span class="rp-info" title="{{ $k[$key]['formula'] }}">i</span></span>
            <span class="val">{{ $fmt($key) }}</span>
            <span class="sub">{!! $dlt($key) !!} წინა: {{ $fmt($key, 'prev') }}</span>
        </div>
    @endforeach
</section>

@if ($hasSales && $report['attribution'] !== 'tracked')
    <div class="rp-callout info">
        <span aria-hidden="true">i</span>
        <p>ROMI ითვლება <b>{{ $report['attribution'] === 'mixed' ? 'ნაწილობრივ' : '' }} ატრიბუციის წილით</b> — შემოსავლის ის ნაწილი, რომელსაც მარკეტინგს მიაწერ (ბრენდის პარამეტრებში).
            ზუსტი ROMI-სთვის POS-იდან გამოაგზავნე <code>marketing_revenue</code> — ჯავშნები/პრომოკოდები, რომლებიც მარკეტინგიდან მოვიდა.
            <a href="{{ route('reports.plan') }}#brands">პარამეტრები →</a></p>
    </div>
@endif

{{-- ---- trend ---- --}}
@php
    $days = $report['daily'];
    $W = 800; $H = 220; $pad = 34;
    $maxRev = max(1, max(array_column($days, 'revenue') ?: [0]));
    $maxSp = max(1, max(array_column($days, 'spend') ?: [0]));
    $n = max(1, count($days));
    $bw = ($W - $pad * 2) / $n;
    $line = collect($days)->map(fn ($d, $i) => round($pad + $bw * $i + $bw / 2, 1).','.round($H - $pad - ($d['spend'] / $maxSp) * ($H - $pad * 2), 1))->join(' ');
    $weekly = count($days) < ((int) $from->diffInDays($to) + 1);
    $hasTrend = array_sum(array_column($days, 'revenue')) + array_sum(array_column($days, 'spend')) > 0;
@endphp
<section class="panel rp-chart" style="margin-bottom: 18px">
    <h2>შემოსავალი და მარკეტინგის ხარჯი</h2>
    @if ($hasTrend)
        <div class="rp-legend">
            <span><i style="background: #00854D"></i>შემოსავალი (მაქს. {{ $money($maxRev) }})</span>
            <span><i style="background: #D83A52"></i>მარკეტინგის ხარჯი (მაქს. {{ $money($maxSp) }})</span>
            @if ($weekly)<span>კვირების მიხედვით</span>@endif
        </div>
        <svg viewBox="0 0 {{ $W }} {{ $H }}" role="img" aria-label="შემოსავალი და ხარჯი დღეების მიხედვით">
            <line x1="{{ $pad }}" y1="{{ $H - $pad }}" x2="{{ $W - $pad }}" y2="{{ $H - $pad }}" stroke="#D0D4E4"/>
            @foreach ($days as $i => $d)
                @php($h = ($d['revenue'] / $maxRev) * ($H - $pad * 2))
                <rect x="{{ round($pad + $bw * $i + $bw * 0.15, 1) }}" y="{{ round($H - $pad - $h, 1) }}" width="{{ round(max(1, $bw * 0.7), 1) }}" height="{{ round($h, 1) }}" rx="2" fill="#00854D" opacity=".75">
                    <title>{{ $d['date']->format('d.m') }} — შემოსავალი {{ $money($d['revenue']) }}, ხარჯი {{ $money($d['spend']) }}</title>
                </rect>
                @if ($n <= 31 || $i % (int) ceil($n / 12) === 0)
                    <text x="{{ round($pad + $bw * $i + $bw / 2, 1) }}" y="{{ $H - 14 }}" font-size="10" text-anchor="middle" fill="#676879">{{ $d['date']->format('d.m') }}</text>
                @endif
            @endforeach
            <polyline points="{{ $line }}" fill="none" stroke="#D83A52" stroke-width="2.5" stroke-linejoin="round" stroke-linecap="round"/>
        </svg>
    @else
        <p class="rp-muted">ამ პერიოდში შემოსავლის და ხარჯის მონაცემი ჯერ არ არის.</p>
    @endif
</section>

<div class="rp-grid">
    {{-- ---- paid channels ---- --}}
    <section class="panel">
        <h2>ფასიანი არხები</h2>
        @if ($paid->isEmpty())
            <p class="rp-muted">სარეკლამო ანგარიში ჯერ არ არის დაკავშირებული.</p>
        @else
            <div style="overflow-x: auto">
                <table class="plain" style="min-width: 560px">
                    <thead><tr>
                        <th scope="col">არხი</th><th scope="col" class="rp-num">ხარჯი</th><th scope="col" class="rp-num">წილი</th>
                        <th scope="col" class="rp-num">კლიკი</th><th scope="col" class="rp-num">CPC</th>
                        <th scope="col" class="rp-num">კონვ.</th><th scope="col" class="rp-num">CPA</th><th scope="col" class="rp-num">ROAS</th>
                    </tr></thead>
                    <tbody>
                    @foreach ($paid as $c)
                        @php($m = $c['m'])
                        @php($sp = $m['spend'] ?? 0)
                        <tr>
                            <td><span class="rp-src"><i style="background: {{ $c['color'] }}"></i>{{ $c['label'] }}</span></td>
                            <td class="rp-num">{{ $money($sp) }}</td>
                            <td class="rp-num">{{ $c['spend_share'] === null ? '—' : Kpi::number($c['spend_share']).'%' }}</td>
                            <td class="rp-num">{{ Kpi::compact($m['clicks'] ?? 0) }}</td>
                            <td class="rp-num">{{ ($m['clicks'] ?? 0) > 0 ? $money($sp / $m['clicks']) : '—' }}</td>
                            <td class="rp-num">{{ Kpi::compact($m['conversions'] ?? 0) }}</td>
                            <td class="rp-num">{{ ($m['conversions'] ?? 0) > 0 ? $money($sp / $m['conversions']) : '—' }}</td>
                            <td class="rp-num">{{ $sp > 0 && ($m['conversion_value'] ?? 0) > 0 ? Kpi::number($m['conversion_value'] / $sp, 2).'×' : '—' }}</td>
                        </tr>
                    @endforeach
                    </tbody>
                </table>
            </div>
        @endif
    </section>

    {{-- ---- organic ---- --}}
    <section class="panel">
        <h2>ორგანული სოც. მედია</h2>
        @if ($organic->isEmpty())
            <p class="rp-muted">Facebook / Instagram / TikTok ჯერ არ არის დაკავშირებული.</p>
        @else
            <div style="overflow-x: auto">
                <table class="plain" style="min-width: 480px">
                    <thead><tr>
                        <th scope="col">ანგარიში</th><th scope="col" class="rp-num">მიწვდომა</th><th scope="col" class="rp-num">ნახვები</th>
                        <th scope="col" class="rp-num">ჩართულობა</th><th scope="col" class="rp-num">ER</th><th scope="col" class="rp-num">+ გამომწ.</th>
                    </tr></thead>
                    <tbody>
                    @foreach ($organic as $c)
                        @php($m = $c['m'])
                        @php($er = ($m['impressions'] ?? 0) > 0 ? ($m['engagement'] ?? 0) / $m['impressions'] * 100 : null)
                        <tr>
                            <td><span class="rp-src"><i style="background: {{ $c['color'] }}"></i>{{ $c['label'] }}</span></td>
                            <td class="rp-num">{{ isset($m['reach']) ? Kpi::compact($m['reach']) : '—' }}</td>
                            <td class="rp-num">{{ Kpi::compact($m['impressions'] ?? 0) }}</td>
                            <td class="rp-num">{{ Kpi::compact($m['engagement'] ?? 0) }}</td>
                            <td class="rp-num">{{ $er === null ? '—' : Kpi::number($er, 1).'%' }}</td>
                            <td class="rp-num">{{ Kpi::compact($m['new_followers'] ?? 0) }}</td>
                        </tr>
                    @endforeach
                    </tbody>
                </table>
            </div>
        @endif
    </section>
</div>

<div class="rp-grid">
    {{-- ---- brands side by side ---- --}}
    @if (! $brand && $report['brands']->count() > 1)
        <section class="panel">
            <h2>ბრენდების შედარება</h2>
            <div style="overflow-x: auto">
                <table class="plain" style="min-width: 520px">
                    <thead><tr>
                        <th scope="col">ბრენდი</th><th scope="col" class="rp-num">ხარჯი</th><th scope="col" class="rp-num">შემოსავალი</th>
                        <th scope="col" class="rp-num">ROMI</th><th scope="col" class="rp-num">MER</th><th scope="col" class="rp-num">CAC</th>
                    </tr></thead>
                    <tbody>
                    @foreach ($report['brands'] as $b)
                        <tr>
                            <td><a class="rp-src" href="{{ route('reports.index', ['brand' => $b['venue']->slug] + $query) }}"><i style="background: {{ $b['venue']->color }}"></i>{{ $b['venue']->name }}</a></td>
                            @foreach (['marketing_spend', 'revenue', 'romi', 'mer', 'cac'] as $key)
                                <td class="rp-num">{{ Kpi::format($key, $b['kpi'][$key], $currency) }}</td>
                            @endforeach
                        </tr>
                    @endforeach
                    </tbody>
                </table>
            </div>
        </section>
    @endif

    {{-- ---- plan vs actual ---- --}}
    <section class="panel">
        <h2>გეგმა და ფაქტი</h2>
        @if ($report['targets'] === null)
            <p class="rp-muted">გეგმა ჩანს, როცა პერიოდი ერთ თვეშია (მაგ. „ეს თვე“).</p>
        @elseif ($report['targets']['rows'] === [])
            <p class="rp-muted">{{ $report['targets']['month']->format('m.Y') }}-ისთვის გეგმა არ არის. <a href="{{ route('reports.plan', ['month' => $report['targets']['month']->format('Y-m')]) }}#targets">დაამატე →</a></p>
        @else
            <p class="panel-sub">პროგნოზი — თვის ბოლოსთვის, ამჟამინდელი ტემპით.</p>
            <table class="plain">
                <thead><tr><th scope="col">მიზანი</th><th scope="col" class="rp-num">ფაქტი</th><th scope="col" class="rp-num">გეგმა</th><th scope="col" style="width: 30%">შესრულება</th></tr></thead>
                <tbody>
                @foreach ($report['targets']['rows'] as $t)
                    @php($pct = min(100, max(0, $t['progress'] ?? 0)))
                    <tr>
                        <td>{{ $t['label'] }}</td>
                        <td class="rp-num">{{ Kpi::format($t['metric'], $t['actual'], $currency) }}<br><small>პროგნ. {{ Kpi::format($t['metric'], $t['projected'], $currency) }}</small></td>
                        <td class="rp-num">{{ Kpi::format($t['metric'], $t['target'], $currency) }}</td>
                        <td>
                            <div class="rp-bar"><span style="width: {{ $pct }}%; background: {{ $t['on_track'] === false ? '#D83A52' : '#00854D' }}"></span></div>
                            <small class="rp-muted">{{ $t['progress'] === null ? '—' : Kpi::number($t['progress']).'%' }} · {{ $t['on_track'] === null ? '' : ($t['on_track'] ? 'გეგმაშია' : 'ჩამორჩება') }}</small>
                        </td>
                    </tr>
                @endforeach
                </tbody>
            </table>
        @endif
    </section>

    {{-- ---- where the non-ad money went ---- --}}
    @if ($report['costsByCategory']->isNotEmpty())
        <section class="panel">
            <h2>სხვა მარკეტინგული ხარჯები</h2>
            @php($costMax = max($report['costsByCategory']->max(), 1))
            <table class="plain">
                <tbody>
                @foreach ($report['costsByCategory'] as $cat => $total)
                    <tr>
                        <td>{{ Catalog::COST_CATEGORIES[$cat] ?? $cat }}</td>
                        <td style="width: 40%"><div class="rp-bar"><span style="width: {{ $total / $costMax * 100 }}%; background: #6E3FD1"></span></div></td>
                        <td class="rp-num">{{ $money((float) $total) }}</td>
                    </tr>
                @endforeach
                </tbody>
            </table>
        </section>
    @endif
</div>

{{-- ---- campaigns ---- --}}
@if ($report['campaigns']->isNotEmpty())
    <section class="panel" style="margin-bottom: 18px">
        <h2>ტოპ კამპანიები ხარჯით</h2>
        <div style="overflow-x: auto">
            <table class="plain" style="min-width: 680px">
                <thead><tr>
                    <th scope="col">კამპანია</th><th scope="col">ბრენდი</th><th scope="col" class="rp-num">ხარჯი</th><th scope="col" class="rp-num">ნახვა</th>
                    <th scope="col" class="rp-num">კლიკი</th><th scope="col" class="rp-num">კონვ.</th><th scope="col" class="rp-num">CPA</th><th scope="col" class="rp-num">ROAS</th>
                </tr></thead>
                <tbody>
                @foreach ($report['campaigns'] as $c)
                    <tr>
                        <td><span class="rp-src"><i style="background: {{ Catalog::PROVIDERS[$c['provider']]['color'] ?? '#676879' }}" title="{{ Catalog::providerLabel($c['provider']) }}"></i>{{ $c['campaign'] }}</span></td>
                        <td class="rp-muted">{{ $venueNames[$c['venue_id']] ?? '' }}</td>
                        <td class="rp-num">{{ $money($c['spend']) }}</td>
                        <td class="rp-num">{{ Kpi::compact($c['impressions']) }}</td>
                        <td class="rp-num">{{ Kpi::compact($c['clicks']) }}</td>
                        <td class="rp-num">{{ Kpi::compact($c['conversions']) }}</td>
                        <td class="rp-num">{{ $c['cpa'] === null ? '—' : $money($c['cpa']) }}</td>
                        <td class="rp-num">{{ $c['roas'] === null ? '—' : Kpi::number($c['roas'], 2).'×' }}</td>
                    </tr>
                @endforeach
                </tbody>
            </table>
        </div>
    </section>
@endif

{{-- ---- every KPI ---- --}}
<h2 style="font-size: 18px; margin: 26px 0 12px">ყველა KPI</h2>
@foreach (Kpi::GROUPS as $group => $groupLabel)
    <details class="group" {{ in_array($group, ['finance', 'sales']) ? 'open' : '' }}>
        <summary>
            <svg class="chev" width="14" height="14" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2.5" aria-hidden="true"><path d="M9 6l6 6-6 6"/></svg>
            {{ $groupLabel }}
        </summary>
        <div class="table-wrap">
            <table class="plain" style="min-width: 640px">
                <thead><tr><th scope="col">KPI</th><th scope="col" class="rp-num">ახლა</th><th scope="col" class="rp-num">წინა</th><th scope="col" class="rp-num">ცვლილება</th><th scope="col">როგორ ითვლება</th></tr></thead>
                <tbody>
                @foreach (collect($k)->where('group', $group) as $key => $row)
                    <tr>
                        <td><b>{{ $row['label'] }}</b></td>
                        <td class="rp-num">{{ $fmt($key) }}</td>
                        <td class="rp-num rp-muted">{{ $fmt($key, 'prev') }}</td>
                        <td class="rp-num">{!! $dlt($key) ?: '—' !!}</td>
                        <td class="rp-muted" style="font-size: 12px">{{ $row['formula'] }}</td>
                    </tr>
                @endforeach
                </tbody>
            </table>
        </div>
    </details>
@endforeach

{{-- ---- freshness ---- --}}
@if ($hasSources)
    <section class="panel" style="margin-top: 18px">
        <h2>მონაცემების წყაროები</h2>
        <div style="overflow-x: auto">
            <table class="plain" style="min-width: 520px">
                <tbody>
                @foreach ($report['sources'] as $s)
                    <tr>
                        <td><span class="rp-src"><i style="background: {{ Catalog::PROVIDERS[$s->provider]['color'] ?? '#676879' }}"></i>{{ $s->label }}</span></td>
                        <td class="rp-muted">{{ $s->venue->name }}</td>
                        <td><span class="rp-status {{ $s->last_status }}">{{ ['ok' => 'განახლდა', 'error' => 'შეცდომა', 'manual' => 'ხელით / CSV', 'never' => 'ჯერ არა'][$s->last_status] ?? $s->last_status }}</span></td>
                        <td class="rp-muted rp-num">{{ $s->last_synced_at?->timezone('Asia/Tbilisi')->format('d.m H:i') ?? '—' }}</td>
                    </tr>
                @endforeach
                </tbody>
            </table>
        </div>
    </section>
@endif
@endsection
