@extends('layouts.app')

@section('title', $report->title.' — ანალიზები')

@push('styles')
<link rel="stylesheet" href="{{ asset('css/reports.css') }}?v=2">
@endpush

@php($total = $report->actions->count())
@php($done = $report->actions->whereNotNull('done_at')->count())

@section('content')
<header class="page-head">
    <a class="btn sm" href="{{ route('reports.analyses.index') }}">← ანალიზები</a>
    <span class="spacer"></span>
    @if ($report->venue)
        <span class="rp-chip"><span class="venue-dot" style="background: {{ $report->venue->color }}"></span>{{ $report->venue->name }}</span>
    @endif
</header>

<div class="cols">
    <article class="main panel rp-md">
        {!! $report->html() !!}
    </article>

    <aside class="aside">
        @if ($total)
            <section class="panel rp-checklist">
                <h2>სამოქმედო ჩეკლისტი</h2>
                <div class="rp-analysis-progress" style="margin-bottom: 12px">
                    <span class="rp-bar" style="flex: 1"><span style="width: {{ round($done / $total * 100) }}%; background: {{ $done === $total ? '#00854D' : '#1F76C2' }}"></span></span>
                    <small>{{ $done }}/{{ $total }}</small>
                </div>
                @foreach ($groups as $group => $items)
                    @if ($group !== '')<h3>{{ $group }}</h3>@endif
                    <ul>
                        @foreach ($items as $item)
                            <li class="{{ $item->done_at ? 'is-done' : '' }}">
                                <form method="post" action="{{ route('reports.analyses.toggle', [$report, $item]) }}">
                                    @csrf
                                    <button type="submit" class="rp-check" aria-pressed="{{ $item->done_at ? 'true' : 'false' }}" aria-label="{{ $item->done_at ? 'მოხსნა' : 'შესრულდა' }}: {{ $item->text }}">
                                        @if ($item->done_at)<svg width="12" height="12" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="3.5" aria-hidden="true"><path d="M4 12l5 5L20 6"/></svg>@endif
                                    </button>
                                </form>
                                <span>
                                    {{ $item->text }}
                                    @if ($item->done_at)
                                        <small>{{ $item->doneBy?->name ?? '' }} · {{ $item->done_at->timezone('Asia/Tbilisi')->format('d.m H:i') }}</small>
                                    @endif
                                </span>
                            </li>
                        @endforeach
                    </ul>
                @endforeach
            </section>
        @endif

        <section class="panel">
            <h2>დეტალები</h2>
            <form method="post" action="{{ route('reports.analyses.update', $report) }}" style="display: flex; flex-direction: column; gap: 10px">
                @csrf @method('put')
                <label class="field">სათაური <input name="title" required maxlength="255" value="{{ $report->title }}"></label>
                <label class="field">ბრენდი
                    <select name="venue_id">
                        <option value="">ყველა ბრენდი</option>
                        @foreach ($venues as $v)<option value="{{ $v->id }}" @selected($report->venue_id === $v->id)>{{ $v->name }}</option>@endforeach
                    </select>
                </label>
                <div class="form-grid" style="grid-template-columns: 1fr 1fr">
                    <label class="field">დან <input type="date" name="period_from" value="{{ $report->period_from?->toDateString() }}"></label>
                    <label class="field">მდე <input type="date" name="period_to" value="{{ $report->period_to?->toDateString() }}"></label>
                </div>
                <button class="btn sm primary" type="submit">შენახვა</button>
            </form>
            <p class="rp-muted" style="font-size: 12px; margin: 12px 0 0">
                ატვირთა {{ $report->user?->name ?? '—' }}, {{ $report->created_at->timezone('Asia/Tbilisi')->format('d.m.Y H:i') }}
            </p>
            <form method="post" action="{{ route('reports.analyses.destroy', $report) }}" style="margin-top: 10px" onsubmit="return confirm('წაიშალოს ანალიზი და მისი ჩეკლისტი?')">
                @csrf @method('delete')
                <button class="btn sm danger" type="submit">წაშლა</button>
            </form>
        </section>
    </aside>
</div>
@endsection
