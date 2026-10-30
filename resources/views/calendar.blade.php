@extends('layouts.app')
@use('App\Support\Studio')

@section('title', $venue->name.' — კალენდარი')

@section('content')
@php($weekdays = ['ორშ', 'სამ', 'ოთხ', 'ხუთ', 'პარ', 'შაბ', 'კვი'])
<header class="page-head">
    <h1><span class="venue-badge" style="background: {{ $venue->color }}"></span>{{ $venue->name }} · კალენდარი</h1>
    <span class="spacer"></span>
    <a class="btn" href="{{ route('calendar', [$venue, 'week' => $start->copy()->subWeek()->toDateString()]) }}" aria-label="წინა კვირა">‹</a>
    <a class="btn" href="{{ route('calendar', $venue) }}">ეს კვირა</a>
    <a class="btn" href="{{ route('calendar', [$venue, 'week' => $start->copy()->addWeek()->toDateString()]) }}" aria-label="შემდეგი კვირა">›</a>
    <a class="btn" href="{{ route('board', $venue) }}">დაფა</a>
</header>

<p style="margin: 0 0 14px; color: var(--muted)">{{ $start->format('d.m') }} – {{ $start->copy()->addDays(6)->format('d.m.Y') }} · ქარდზე დაჭერით გაიხსნება დავალება</p>

<div style="overflow-x: auto">
    <div class="cal">
        <div class="hd"></div>
        @foreach ($days as $i => $day)
            <div class="hd {{ $day->isToday() ? 'today' : '' }}">{{ $weekdays[$i] }} <small>{{ $day->format('d.m') }}</small></div>
        @endforeach

        @foreach ($grid as $row)
            <div class="rowhead">
                <span class="pill" style="{{ $row['style'] }}; align-self: flex-start">{{ $row['label'] }}</span>
                <small style="color: var(--muted)">{{ $row['count'] }} პოსტი</small>
            </div>
            @foreach ($row['days'] as $items)
                <div>
                    @foreach ($items as $item)
                        @php($t = $item['task'])
                        <a class="cal-item" href="{{ route('tasks.show', $t) }}" style="--pf: {{ Studio::PLATFORMS[$row['key']][1] }}">
                            <span>{{ $t->publish_at->format('H:i') }} · <span style="font-weight: 700; color: {{ in_array($t->status, ['approved', 'published']) ? '#00854D' : '#676879' }}">{{ Studio::statusLabel($t->status) }}</span></span>
                            <b>{{ $t->title }}</b>
                            @if ($item['format'])<span>{{ $item['format'] }}</span>@endif
                            @if ($t->channels->count() > 1)<span style="color: var(--muted)">+ {{ $t->channels->count() - 1 }} სხვა ქსელი</span>@endif
                        </a>
                    @endforeach
                </div>
            @endforeach
        @endforeach
    </div>
</div>

@if ($unscheduled->isNotEmpty())
    <section class="panel" style="margin-top: 20px">
        <h2>თარიღის გარეშე</h2>
        <ul class="timeline">
            @foreach ($unscheduled as $t)
                <li><a href="{{ route('tasks.show', $t) }}"><b>{{ $t->title }}</b></a> · {{ $t->channels->map(fn ($c) => Studio::platformLabel($c->platform))->join(', ') }} · {{ Studio::statusLabel($t->status) }}</li>
            @endforeach
        </ul>
    </section>
@endif
@endsection
