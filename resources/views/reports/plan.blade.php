@extends('layouts.app')
@use('App\Reporting\Catalog')
@use('App\Reporting\Kpi')

@section('title', 'ხარჯები და გეგმა — CMO რეპორტინგი')

@push('styles')
<link rel="stylesheet" href="{{ asset('css/reports.css') }}?v=1">
@endpush

@section('content')
<header class="page-head">
    <h1>ხარჯები და გეგმა</h1>
    <span class="spacer"></span>
    <form method="get" action="{{ route('reports.plan') }}" style="display: flex; gap: 6px; align-items: center">
        <label class="sr-only" for="month">თვე</label>
        <input id="month" type="month" name="month" value="{{ $month->format('Y-m') }}" class="btn sm" onchange="this.form.submit()">
    </form>
    <a class="btn sm" href="{{ route('reports.index') }}">← დაშბორდი</a>
</header>

<div class="cols">
    <div class="main">
        <section class="panel">
            <h2>სხვა მარკეტინგული ხარჯები — {{ $month->format('m.Y') }}</h2>
            <p class="panel-sub">ყველაფერი, რაც რეკლამის პლატფორმებს არ ახსოვთ: სააგენტო, ინფლუენსერი, გადაღება, ბეჭდვა, ივენთი. ROMI-ში ითვლება.</p>

            <form method="post" action="{{ route('reports.costs.store') }}" class="form-grid" style="margin-bottom: 16px; align-items: end">
                @csrf
                <label class="field">ბრენდი
                    <select name="venue_id" required>@foreach ($venues as $v)<option value="{{ $v->id }}">{{ $v->name }}</option>@endforeach</select>
                </label>
                <label class="field">თარიღი <input type="date" name="spent_on" required value="{{ old('spent_on', min(today(), $month->endOfMonth())->toDateString()) }}"></label>
                <label class="field">კატეგორია
                    <select name="category" required>@foreach (Catalog::COST_CATEGORIES as $key => $label)<option value="{{ $key }}">{{ $label }}</option>@endforeach</select>
                </label>
                <label class="field">თანხა ({{ $currency }}) <input type="number" name="amount" step="0.01" min="0.01" required></label>
                <label class="field">შენიშვნა <input name="note" maxlength="255" placeholder="მაგ. ინფლუენსერი @..."></label>
                <button class="btn primary" type="submit">დამატება</button>
            </form>

            @if ($costs->isEmpty())
                <p class="rp-muted">ამ თვეში ხარჯი არ არის შეყვანილი.</p>
            @else
                <div style="overflow-x: auto">
                    <table class="plain" style="min-width: 560px">
                        <thead><tr><th scope="col">თარიღი</th><th scope="col">ბრენდი</th><th scope="col">კატეგორია</th><th scope="col">შენიშვნა</th><th scope="col" class="rp-num">თანხა</th><th scope="col"><span class="sr-only">წაშლა</span></th></tr></thead>
                        <tbody>
                        @foreach ($costs as $c)
                            <tr>
                                <td>{{ $c->spent_on->format('d.m') }}</td>
                                <td><span class="rp-src"><i style="background: {{ $c->venue->color }}"></i>{{ $c->venue->name }}</span></td>
                                <td>{{ $c->categoryLabel() }}</td>
                                <td class="rp-muted">{{ $c->note }}@if ($c->user) <small>· {{ $c->user->name }}</small>@endif</td>
                                <td class="rp-num">{{ Kpi::number($c->amount, 2) }} {{ $currency }}</td>
                                <td>
                                    <form method="post" action="{{ route('reports.costs.destroy', $c) }}" onsubmit="return confirm('წაიშალოს ხარჯი?')">
                                        @csrf @method('delete')
                                        <button class="btn ghost sm" type="submit" aria-label="წაშლა">✕</button>
                                    </form>
                                </td>
                            </tr>
                        @endforeach
                        </tbody>
                        <tfoot><tr><td colspan="4"><b>სულ</b></td><td class="rp-num"><b>{{ Kpi::number($costs->sum('amount'), 2) }} {{ $currency }}</b></td><td></td></tr></tfoot>
                    </table>
                </div>
            @endif
        </section>

        <section class="panel" id="targets">
            <h2>თვის გეგმა — {{ $month->format('m.Y') }}</h2>
            <p class="panel-sub">ცარიელი ველი = გეგმა არ არის. დაშბორდზე ჩანს ფაქტი, პროგნოზი თვის ბოლოსთვის და შესრულების %.</p>
            <form method="post" action="{{ route('reports.targets.save') }}">
                @csrf
                <input type="hidden" name="month" value="{{ $month->format('Y-m') }}">
                <div style="overflow-x: auto">
                    <table class="plain rp-targets" style="min-width: 520px">
                        <thead><tr><th scope="col">KPI</th>@foreach ($venues as $v)<th scope="col" class="rp-num">{{ $v->name }}</th>@endforeach</tr></thead>
                        <tbody>
                        @foreach (Catalog::TARGETABLE as $metric => $label)
                            <tr>
                                <td>{{ $label }}</td>
                                @foreach ($venues as $v)
                                    <td class="rp-num"><input type="number" step="any" min="0" name="targets[{{ $v->id }}][{{ $metric }}]" value="{{ $targets[$v->id.'.'.$metric] ?? '' }}" aria-label="{{ $label }} — {{ $v->name }}"></td>
                                @endforeach
                            </tr>
                        @endforeach
                        </tbody>
                    </table>
                </div>
                <button class="btn primary" type="submit" style="margin-top: 12px">გეგმის შენახვა</button>
            </form>
        </section>
    </div>

    <aside class="aside" id="brands">
        @foreach ($venues as $v)
            <section class="panel">
                <h2 style="display: flex; align-items: center; gap: 8px"><span class="venue-dot" style="background: {{ $v->color }}"></span>{{ $v->name }}</h2>
                <form method="post" action="{{ route('reports.brands.save', $v) }}" style="display: flex; flex-direction: column; gap: 10px">
                    @csrf @method('put')
                    <label class="field">მთლიანი მარჟა, %
                        <input type="number" name="gross_margin" step="0.1" min="1" max="100" required value="{{ round($v->gross_margin * 100, 1) }}">
                        <small class="rp-muted" style="font-weight: 400">შემოსავალს მინუს პროდუქტის თვითღირებულება. ROMI და ROI ამით ითვლება.</small>
                    </label>
                    <label class="field">მარკეტინგის ატრიბუციის წილი, %
                        <input type="number" name="attribution_share" step="0.1" min="0" max="100" required value="{{ round($v->attribution_share * 100, 1) }}">
                        <small class="rp-muted" style="font-weight: 400">შემოსავლის რა ნაწილი მოიტანა მარკეტინგმა, როცა POS-ი <code style="display: inline">marketing_revenue</code>-ს არ აგზავნის.</small>
                    </label>
                    <button class="btn sm primary" type="submit">შენახვა</button>
                </form>
            </section>
        @endforeach
    </aside>
</div>
@endsection
