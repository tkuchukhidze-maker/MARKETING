@extends('layouts.app')
@use('App\Support\Studio')

@section('title', $venue->name.' — დაფა')

@section('content')
<header class="page-head">
    <h1><span class="venue-badge" style="background: {{ $venue->color }}"></span>{{ $venue->name }}</h1>
    <span class="spacer"></span>
    @php($keep = ['platform' => $platform, 'mine' => $mine ?: null, 'q' => $q ?: null])
    <nav class="seg" aria-label="ხედი">
        <a class="{{ $view === 'board' ? 'is-active' : '' }}" href="{{ route('board', [$venue, ...$keep]) }}" @if ($view === 'board') aria-current="page" @endif>დაფა</a>
        <a class="{{ $view === 'list' ? 'is-active' : '' }}" href="{{ route('board', [$venue, 'view' => 'list', ...$keep]) }}" @if ($view === 'list') aria-current="page" @endif>სია</a>
    </nav>
    <a class="btn" href="{{ route('calendar', $venue) }}">კალენდარი</a>
    <button class="btn primary" type="button" onclick="document.getElementById('new-task').showModal()">+ ახალი დავალება</button>
</header>

@php($boardUrl = route('board', [$venue, 'platform' => $platform, 'mine' => $mine ?: null, 'q' => $q ?: null]))
<section class="pulse" aria-label="მოკლე სურათი">
    <a class="pulse-tile" href="{{ $boardUrl }}#col-review" style="--tone: #E0A800">
        <span>დასადასტურებელი</span>
        <b>{{ $pulse['review'] }}</b>
        <small>{{ auth()->user()->canApprove() ? ($pulse['review'] ? 'ელოდება შენს გადაწყვეტილებას' : 'არაფერი ელოდება') : 'მენეჯერთან განხილვაზე' }}</small>
    </a>
    <a class="pulse-tile {{ $pulse['post_late'] ? 'is-alert' : '' }}" href="{{ $boardUrl }}#col-approved" style="--tone: #00854D">
        <span>განსათავსებელი</span>
        <b>{{ $pulse['to_post'] }}</b>
        <small>დღეს {{ $pulse['post_today'] }}@if ($pulse['post_late']) · <em>დაგვიანდა {{ $pulse['post_late'] }}</em>@endif</small>
    </a>
    <a class="pulse-tile" href="{{ $boardUrl }}#col-in_progress" style="--tone: #1F76C2">
        <span>პროცესშია</span>
        <b>{{ $pulse['working'] }}</b>
        <small>@if ($pulse['revision'])შესწორებაზე {{ $pulse['revision'] }}@else გასაკეთებელი + მუშავდება @endif</small>
    </a>
    <a class="pulse-tile" href="{{ $boardUrl }}#col-published" style="--tone: #6E3FD1">
        <span>განთავსდა ამ კვირაში</span>
        <b>{{ $pulse['live_week'] }}</b>
        <small>პოსტი / სთორი ყველა ქსელში</small>
    </a>
    <a class="pulse-tile {{ $pulse['overdue'] ? 'is-alert' : '' }}" href="{{ route('board', [$venue, 'view' => 'list']) }}" style="--tone: #D83A52">
        <span>ვადაგადაცილებული</span>
        <b>{{ $pulse['overdue'] }}</b>
        <small>{{ $pulse['overdue'] ? 'ნახე სიაში' : 'ყველაფერი დროზეა' }}</small>
    </a>
</section>

<nav class="tabs" aria-label="პლატფორმა">
    <a class="tab {{ $platform ? '' : 'is-active' }}" href="{{ route('board', [$venue, 'view' => $view === 'list' ? 'list' : null, 'mine' => $mine ?: null, 'q' => $q ?: null]) }}">
        ყველა <span class="count">{{ $totalCount }}</span>
    </a>
    @foreach (Studio::PLATFORMS as $key => [$label, $color])
        <a class="tab {{ $platform === $key ? 'is-active' : '' }}" href="{{ route('board', [$venue, 'view' => $view === 'list' ? 'list' : null, 'platform' => $key, 'mine' => $mine ?: null, 'q' => $q ?: null]) }}">
            <span class="pf-dot" style="background: {{ $color }}"></span>{{ $label }} <span class="count">{{ $platformCounts[$key] }}</span>
        </a>
    @endforeach
</nav>

<div class="toolbar">
    <form class="search" method="get" action="{{ route('board', $venue) }}" role="search">
        <label for="q" class="sr-only">ძიება</label>
        <svg width="16" height="16" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" aria-hidden="true"><circle cx="11" cy="11" r="7"/><path d="M20 20l-3.5-3.5"/></svg>
        <input id="q" type="search" name="q" value="{{ $q }}" placeholder="დავალების ძიება">
        @if ($platform)<input type="hidden" name="platform" value="{{ $platform }}">@endif
        @if ($mine)<input type="hidden" name="mine" value="1">@endif
        @if ($view === 'list')<input type="hidden" name="view" value="list">@endif
    </form>
    <a class="btn {{ $mine ? 'is-on' : '' }}" href="{{ route('board', [$venue, 'view' => $view === 'list' ? 'list' : null, 'platform' => $platform, 'mine' => $mine ? null : 1, 'q' => $q ?: null]) }}">ჩემი დავალებები</a>
</div>

@if ($hints->isNotEmpty())
    <section class="hints" aria-label="ჭკვიანი შეხსენებები">
        @foreach ($hints as $hint)
            <a class="hint {{ $hint['tone'] }}" href="{{ $hint['url'] ?? '#' }}">
                <span class="ic" aria-hidden="true">!</span><span>{{ $hint['text'] }}</span>
            </a>
        @endforeach
    </section>
@endif

@if ($view === 'board')
    <div class="kanban-wrap">
        <div class="kanban">
            @foreach ($columns as $col)
                <section class="kcol" id="col-{{ $col['key'] }}" style="--tone: {{ $col['color'] }}" aria-labelledby="col-{{ $col['key'] }}-h">
                    <header>
                        <span class="ws-dot" aria-hidden="true"></span>
                        <h2 id="col-{{ $col['key'] }}-h">{{ $col['label'] }}</h2>
                        <span class="count">{{ $col['tasks']->count() }}</span>
                    </header>
                    <p class="kcol-hint">{{ $col['hint'] }}</p>

                    @if ($col['key'] === 'todo')
                        <button class="kadd" type="button" onclick="document.getElementById('new-task').showModal()">+ ახალი დავალება</button>
                    @endif

                    @forelse ($col['tasks'] as $task)
                        @include('partials.board-card', ['task' => $task, 'col' => $col['key']])
                    @empty
                        <p class="ws-empty">{{ $col['empty'] }}</p>
                    @endforelse

                    @if (($col['older'] ?? 0) > 0)
                        <a class="kmore" href="{{ route('board', [$venue, 'view' => 'list', 'platform' => $platform]) }}#grp-published">+ {{ $col['older'] }} ძველი — სიაში</a>
                    @endif
                </section>
            @endforeach
        </div>
    </div>
@else
@foreach ($groups as $group)
    <details class="group" id="grp-{{ $group['key'] }}" @if ($group['open']) open @endif style="--group: {{ $group['color'] }}">
        <summary style="color: {{ $group['key'] === 'review' ? '#8A6D00' : $group['color'] }}">
            <svg class="chev" width="14" height="14" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="3" aria-hidden="true"><path d="M9 6l6 6-6 6"/></svg>
            {{ $group['label'] }} <small>{{ $group['tasks']->count() }} დავალება{{ $group['key'] === 'in_progress' ? ' · შემსრულებლების სივრცეში' : '' }}</small>
        </summary>
        <div class="table-wrap">
            <table class="board">
                <thead>
                <tr>
                    <th scope="col" style="width: 34%">დავალება</th>
                    <th scope="col" style="width: 22%">პლატფორმა · ფორმატი</th>
                    <th scope="col">შემსრულებელი</th>
                    <th scope="col" style="width: 150px">სტატუსი</th>
                    <th scope="col">ვადა</th>
                    <th scope="col">გამოქვეყნება</th>
                </tr>
                </thead>
                <tbody>
                @forelse ($group['tasks'] as $task)
                    <tr>
                        <td class="name">
                            <a href="{{ route('tasks.show', $task) }}">{{ $task->title }}</a>
                            <span class="meta">
                                @if ($task->reference_links)<span>რეფერენსი {{ count($task->reference_links) }}</span>@endif
                                @if ($task->assets_count)<span>ვიზუალი {{ $task->assets_count }}</span>@endif
                                @if ($task->comments_count)<span>კომენტარი {{ $task->comments_count }}</span>@endif
                            </span>
                        </td>
                        <td>
                            <div class="chips">
                                @foreach ($task->channels as $ch)
                                    <span class="chip" style="{{ Studio::platformStyle($ch->platform) }}" title="{{ $ch->label() }}{{ $ch->isPublished() ? ' — გამოქვეყნდა' : '' }}">{{ $ch->isPublished() ? '✓ ' : '' }}{{ Studio::platformLabel($ch->platform) }}{{ $ch->format ? ' · '.$ch->format : '' }}</span>
                                @endforeach
                            </div>
                        </td>
                        <td>
                            <a class="cell-fill" href="{{ route('tasks.show', $task) }}" style="text-decoration: none; color: inherit">
                                @include('partials.people', ['task' => $task, 'full' => true])
                            </a>
                        </td>
                        <td>
                            <details class="menu">
                                <summary class="cell-fill" style="{{ Studio::statusStyle($task->status) }}">{{ Studio::statusLabel($task->status) }}</summary>
                                <form class="menu-pop" method="post" action="{{ route('tasks.quick', $task) }}">
                                    @csrf @method('patch')
                                    @foreach ($task->allowedStatusesFor(auth()->user()) as $s)
                                        <button type="submit" name="status" value="{{ $s }}" style="{{ Studio::statusStyle($s) }}">{{ Studio::statusLabel($s) }}</button>
                                    @endforeach
                                </form>
                            </details>
                        </td>
                        <td class="{{ $task->isOverdue() ? 'due-late' : '' }}">{{ $task->due_on?->format('d.m') ?? '—' }}</td>
                        <td>{{ $task->publish_at?->format('d.m H:i') ?? '—' }}</td>
                    </tr>
                @empty
                    <tr class="empty-row"><td colspan="6" style="border-left: 6px solid var(--group)">ცარიელია</td></tr>
                @endforelse
                </tbody>
            </table>
        </div>
    </details>
@endforeach
@endif

<dialog id="new-task" aria-labelledby="new-task-title">
    <form method="post" action="{{ route('tasks.store', $venue) }}">
        @csrf
        <h2 id="new-task-title">ახალი დავალება — {{ $venue->name }}</h2>
        <label class="field">სათაური
            <input name="title" required maxlength="200" value="{{ old('title') }}">
        </label>
        @include('partials.channel-fields', ['selected' => $platform ? [$platform => null] : [], 'uid' => 'new'])
        @include('partials.assignee-fields', ['current' => [], 'uid' => 'new'])
        <div class="form-grid">
            <label class="field">ვადა
                <input type="date" name="due_on" value="{{ old('due_on') }}">
            </label>
            <label class="field">გამოქვეყნება
                <input type="datetime-local" name="publish_at" value="{{ old('publish_at') }}">
            </label>
        </div>
        <label class="field">ბრიფი
            <textarea name="brief" maxlength="5000" placeholder="რა უნდა იყოს ვიზუალზე, ტექსტი, CTA…">{{ old('brief') }}</textarea>
        </label>
        <label class="field">რეფერენსის ბმულები <small style="font-weight: 400; color: var(--muted)">თითო ბმული ახალ ხაზზე — Pinterest, Instagram, Behance, Drive…</small>
            <textarea name="reference_links" maxlength="5000" style="min-height: 70px" placeholder="https://…">{{ old('reference_links') }}</textarea>
        </label>
        @if ($venue->concept)
            <p style="margin: 0; color: var(--muted); font-size: 13px; white-space: pre-line"><b>კონცეფცია:</b> {{ $venue->concept }}</p>
        @endif
        <div style="display: flex; gap: 8px; justify-content: flex-end">
            <button class="btn" type="button" onclick="this.closest('dialog').close()">გაუქმება</button>
            <button class="btn primary" type="submit">დამატება</button>
        </div>
    </form>
</dialog>
@if ($errors->any() && old('title') !== null)
    @push('scripts')
        <script>document.getElementById('new-task').showModal();</script>
    @endpush
@endif
@endsection
