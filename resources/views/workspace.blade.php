@extends('layouts.app')
@use('App\Support\Studio')

@section('title', 'ჩემი სივრცე')

@section('content')
<header class="page-head">
    <h1>ჩემი სივრცე</h1>
    <span class="spacer"></span>
    <nav class="tabs" aria-label="რესტორანი" style="margin: 0; border: 0">
        <a class="tab {{ $venue ? '' : 'is-active' }}" href="{{ route('workspace') }}">ყველა</a>
        @foreach ($venues as $v)
            <a class="tab {{ $venue?->is($v) ? 'is-active' : '' }}" href="{{ route('workspace', ['venue' => $v->slug]) }}">
                <span class="pf-dot" style="background: {{ $v->color }}"></span>{{ $v->name }}
            </a>
        @endforeach
    </nav>
</header>
<p style="margin: -6px 0 18px; color: var(--muted)">აქ მხოლოდ შენი ნაწილებია. გაეცანი → „დავიწყე მუშაობა“ → „მზადაა“. საერთო დაფაზე დავალება „დასადასტურებელში“ მაშინ გადადის, როცა ყველა შემსრულებელი მონიშნავს „მზადაა“-ს.</p>

<div style="overflow-x: auto">
    <div class="ws">
        @foreach ($columns as $col)
            <section class="ws-col" style="--tone: {{ $col['color'] }}" aria-label="{{ $col['label'] }}">
                <header>
                    <span class="ws-dot"></span>
                    <h2>{{ $col['label'] }}</h2>
                    <span class="count">{{ $col['items']->count() }}</span>
                </header>

                @forelse ($col['items'] as $a)
                    @php($t = $a->task)
                    <article class="ws-card {{ $t->status === 'revision' ? 'is-revision' : '' }}">
                        <div class="chips" style="justify-content: flex-start; padding: 0">
                            <span class="chip" style="background: {{ $t->venue->color }}; color: #fff">{{ $t->venue->name }}</span>
                            @foreach ($t->channels as $ch)
                                <span class="chip" style="{{ Studio::platformStyle($ch->platform) }}">{{ Studio::platformLabel($ch->platform) }}{{ $ch->format ? ' · '.$ch->format : '' }}</span>
                            @endforeach
                        </div>
                        <a class="ws-title" href="{{ route('tasks.show', $t) }}">{{ $t->title }}</a>
                        @if ($a->note)<p class="ws-note"><b>ჩემი ნაწილი:</b> {{ $a->note }}</p>@endif
                        @if ($t->status === 'revision')
                            <p class="ws-alert">შესწორება მოითხოვეს — ნახე კომენტარები</p>
                        @endif
                        <div class="ws-meta">
                            <span class="{{ $t->isOverdue() ? 'due-late' : '' }}">ვადა: {{ $t->due_on?->format('d.m') ?? '—' }}</span>
                            @if ($t->assignments->count() > 1)
                                <span>@include('partials.people', ['task' => $t])</span>
                            @endif
                        </div>
                        <div class="ws-actions">
                            @if ($a->status === 'assigned')
                                <a class="btn sm" href="{{ route('tasks.show', $t) }}">გაცნობა</a>
                                <form method="post" action="{{ route('tasks.start', $t) }}">@csrf
                                    <button class="btn sm primary" type="submit">დავიწყე მუშაობა</button>
                                </form>
                            @elseif ($a->status === 'in_progress')
                                <a class="btn sm" href="{{ route('tasks.show', $t) }}">ატვირთვა</a>
                                <form method="post" action="{{ route('tasks.ready', $t) }}">@csrf
                                    <button class="btn sm ok" type="submit">მზადაა</button>
                                </form>
                            @else
                                <span style="color: var(--muted); font-size: 13px">{{ $t->status === 'review' ? 'ელოდება დადასტურებას' : 'ელოდება დანარჩენებს' }}</span>
                                <form method="post" action="{{ route('tasks.start', $t) }}">@csrf
                                    <button class="btn sm ghost" type="submit">დავაბრუნო</button>
                                </form>
                            @endif
                        </div>
                    </article>
                @empty
                    <p class="ws-empty">{{ $col['key'] === 'assigned' ? 'ახალი დავალება არ გაქვს.' : 'ცარიელია' }}</p>
                @endforelse
            </section>
        @endforeach
    </div>
</div>

@if ($done->isNotEmpty())
    <section class="panel" style="margin-top: 22px">
        <h2>ბოლოს დადასტურებული</h2>
        <ul class="timeline">
            @foreach ($done as $a)
                <li><a href="{{ route('tasks.show', $a->task) }}"><b>{{ $a->task->title }}</b></a> · {{ $a->task->venue->name }} · {{ Studio::statusLabel($a->task->status) }}</li>
            @endforeach
        </ul>
    </section>
@endif
@endsection
