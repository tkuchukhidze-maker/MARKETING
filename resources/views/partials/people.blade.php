{{-- Compact avatars of a task's people with their own progress. --}}
@if ($task->assignments->isEmpty())
    <span class="avatar empty" title="შემსრულებელი არ ჰყავს">+</span>
@else
    <span class="people">
        @foreach ($task->assignments as $a)
            <span class="avatar ring-{{ $a->status }}" style="background: {{ $a->user->avatar_color }}"
                  title="{{ $a->user->name }}{{ $a->note ? ' — '.$a->note : '' }} · {{ $a->label() }}">{{ $a->user->initial() }}</span>
        @endforeach
        @if ($task->assignments->count() > 1)
            <small class="people-count">{{ $task->readyCount() }}/{{ $task->assignments->count() }} მზადაა</small>
        @elseif ($full ?? false)
            <small class="people-count">{{ $task->assignments->first()->user->name }}</small>
        @endif
    </span>
@endif
