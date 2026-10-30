@extends('layouts.app')
@use('App\Support\Studio')

@section('title', $venue->name.' — დაფა')

@section('content')
<header class="page-head">
    <h1><span class="venue-badge" style="background: {{ $venue->color }}"></span>{{ $venue->name }}</h1>
    <span class="spacer"></span>
    <a class="btn" href="{{ route('calendar', $venue) }}">კალენდარი</a>
    <button class="btn primary" type="button" onclick="document.getElementById('new-task').showModal()">+ ახალი დავალება</button>
</header>

<nav class="tabs" aria-label="პლატფორმა">
    <a class="tab {{ $platform ? '' : 'is-active' }}" href="{{ route('board', [$venue, 'mine' => $mine ?: null, 'q' => $q ?: null]) }}">
        ყველა <span class="count">{{ $totalCount }}</span>
    </a>
    @foreach (Studio::PLATFORMS as $key => [$label, $color])
        <a class="tab {{ $platform === $key ? 'is-active' : '' }}" href="{{ route('board', [$venue, 'platform' => $key, 'mine' => $mine ?: null, 'q' => $q ?: null]) }}">
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
    </form>
    <a class="btn {{ $mine ? 'is-on' : '' }}" href="{{ route('board', [$venue, 'platform' => $platform, 'mine' => $mine ? null : 1, 'q' => $q ?: null]) }}">ჩემი დავალებები</a>
</div>

@if ($totalCount > 0)
    <div class="summary" role="img" aria-label="სტატუსების განაწილება">
        @foreach ($statusSummary as $key => $n)
            @if ($n > 0)<span style="width: {{ $n / $totalCount * 100 }}%; background: {{ Studio::STATUSES[$key][1] }}"></span>@endif
        @endforeach
    </div>
@endif

@if ($hints->isNotEmpty())
    <section class="hints" aria-label="ჭკვიანი შეხსენებები">
        @foreach ($hints as $hint)
            <a class="hint {{ $hint['tone'] }}" href="{{ $hint['url'] ?? '#' }}">
                <span class="ic" aria-hidden="true">!</span><span>{{ $hint['text'] }}</span>
            </a>
        @endforeach
    </section>
@endif

@foreach ($groups as $group)
    <details class="group" open style="--group: {{ $group['color'] }}">
        <summary style="color: {{ $group['key'] === 'review' ? '#8A6D00' : $group['color'] }}">
            <svg class="chev" width="14" height="14" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="3" aria-hidden="true"><path d="M9 6l6 6-6 6"/></svg>
            {{ $group['label'] }} <small>{{ $group['tasks']->count() }} დავალება</small>
        </summary>
        <div class="table-wrap">
            <table class="board">
                <thead>
                <tr>
                    <th scope="col" style="width: 34%">დავალება</th>
                    <th scope="col">პლატფორმა</th>
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
                                @if ($task->format)<span>{{ $task->format }}</span>@endif
                                @if ($task->assets_count)<span>ვიზუალი {{ $task->assets_count }}</span>@endif
                                @if ($task->comments_count)<span>კომენტარი {{ $task->comments_count }}</span>@endif
                            </span>
                        </td>
                        <td><div class="cell-fill" style="{{ Studio::platformStyle($task->platform) }}">{{ Studio::platformLabel($task->platform) }}</div></td>
                        <td>
                            <details class="menu">
                                <summary class="cell-fill" aria-label="შემსრულებლის შეცვლა">
                                    @if ($task->assignee)
                                        <span class="avatar" style="background: {{ $task->assignee->avatar_color }}" title="{{ $task->assignee->name }}">{{ $task->assignee->initial() }}</span>
                                        <span style="margin-left: 6px; font-weight: 500">{{ $task->assignee->name }}</span>
                                    @else
                                        <span class="avatar empty">+</span>
                                    @endif
                                </summary>
                                <form class="menu-pop" method="post" action="{{ route('tasks.quick', $task) }}">
                                    @csrf @method('patch')
                                    @foreach ($users as $u)
                                        <button class="who" name="assignee_id" value="{{ $u->id }}" type="submit">
                                            <span class="avatar" style="background: {{ $u->avatar_color }}">{{ $u->initial() }}</span>{{ $u->name }}
                                        </button>
                                    @endforeach
                                    <button class="who" name="assignee_id" value="" type="submit">— მოხსნა</button>
                                </form>
                            </details>
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

<dialog id="new-task" aria-labelledby="new-task-title">
    <form method="post" action="{{ route('tasks.store', $venue) }}">
        @csrf
        <h2 id="new-task-title">ახალი დავალება — {{ $venue->name }}</h2>
        <label class="field">სათაური
            <input name="title" required maxlength="200" value="{{ old('title') }}">
        </label>
        <div class="form-grid">
            <label class="field">პლატფორმა
                <select name="platform" required>
                    @foreach (Studio::PLATFORMS as $key => [$label])
                        <option value="{{ $key }}" @selected(($platform ?? old('platform')) === $key)>{{ $label }}</option>
                    @endforeach
                </select>
            </label>
            <label class="field">ფორმატი
                <input name="format" maxlength="60" placeholder="Reels, პოსტერი, კარუსელი…" value="{{ old('format') }}">
            </label>
            <label class="field">შემსრულებელი
                <select name="assignee_id">
                    <option value="">— არავინ</option>
                    @foreach ($users as $u)
                        <option value="{{ $u->id }}">{{ $u->name }} · {{ $u->roleLabel() }}</option>
                    @endforeach
                </select>
            </label>
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
        @if ($venue->concept)
            <p style="margin: 0; color: var(--muted); font-size: 13px; white-space: pre-line"><b>კონცეფცია:</b> {{ $venue->concept }}</p>
        @endif
        <div style="display: flex; gap: 8px; justify-content: flex-end">
            <button class="btn" type="button" onclick="this.closest('dialog').close()">გაუქმება</button>
            <button class="btn primary" type="submit">დამატება</button>
        </div>
    </form>
</dialog>
@endsection
