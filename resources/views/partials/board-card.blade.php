{{-- One card on the pipeline board. $col = the column it sits in. --}}
@use('App\Support\Studio')
@php
    $me = auth()->user();
    $when = null;
    $tone = '';
    if (in_array($col, ['approved', 'published'], true)) {
        if ($col === 'published') {
            $last = $task->channels->sortByDesc('published_at')->first();
            $when = $last?->published_at ? 'განთავსდა '.$last->published_at->format('d.m H:i') : null;
        } elseif (! $task->publish_at) {
            $when = 'დრო არ არის დაგეგმილი';
            $tone = 'is-soft';
        } elseif ($task->publish_at->isPast()) {
            $when = 'დაგვიანდა · '.$task->publish_at->format('d.m H:i');
            $tone = 'is-late';
        } elseif ($task->publish_at->isToday()) {
            $when = 'დღეს '.$task->publish_at->format('H:i');
            $tone = 'is-today';
        } elseif ($task->publish_at->isTomorrow()) {
            $when = 'ხვალ '.$task->publish_at->format('H:i');
        } else {
            $when = $task->publish_at->format('d.m H:i');
        }
    } elseif ($task->due_on) {
        $when = 'ვადა '.$task->due_on->format('d.m');
        $tone = $task->isOverdue() ? 'is-late' : ($task->due_on->isToday() ? 'is-today' : '');
    }
@endphp
<article class="kcard {{ $task->status === 'revision' ? 'is-revision' : '' }} {{ $tone === 'is-late' ? 'is-late' : '' }}">
    <div class="chips kchips">
        @foreach ($task->channels as $ch)
            @if ($ch->isPublished() && $ch->post_url)
                <a class="chip" href="{{ $ch->post_url }}" target="_blank" rel="noopener" style="{{ Studio::platformStyle($ch->platform) }}" title="გახსენი პოსტი">✓ {{ Studio::platformLabel($ch->platform) }} ↗</a>
            @else
                <span class="chip {{ $col === 'approved' && ! $ch->isPublished() ? 'is-pending' : '' }}" style="{{ Studio::platformStyle($ch->platform) }}" title="{{ $ch->label() }}{{ $ch->isPublished() ? ' — განთავსდა' : '' }}">{{ $ch->isPublished() ? '✓ ' : '' }}{{ Studio::platformLabel($ch->platform) }}{{ $ch->format ? ' · '.$ch->format : '' }}</span>
            @endif
        @endforeach
    </div>

    <a class="ktitle" href="{{ route('tasks.show', $task) }}">{{ $task->title }}</a>

    @if ($task->status === 'revision')
        <p class="kflag">↩ დაბრუნდა შესწორებაზე</p>
    @endif

    <div class="kmeta">
        @include('partials.people', ['task' => $task])
        @if ($when)<span class="kwhen {{ $tone }}">{{ $when }}</span>@endif
    </div>

    @if ($task->assets_count || $task->comments_count || $task->reference_links)
        <div class="kcounts">
            @if ($task->assets_count)<span>ვიზუალი {{ $task->assets_count }}</span>@endif
            @if ($task->comments_count)<span>კომენტარი {{ $task->comments_count }}</span>@endif
            @if ($task->reference_links)<span>რეფერენსი {{ count($task->reference_links) }}</span>@endif
        </div>
    @endif

    {{-- the manager decides right on the card --}}
    @if ($col === 'review' && $me->canApprove())
        <div class="kactions">
            <form method="post" action="{{ route('tasks.approve', $task) }}">
                @csrf
                <button class="btn ok sm" type="submit">✓ დადასტურება</button>
            </form>
            <details class="kfold">
                <summary class="btn sm">შესწორება</summary>
                <form method="post" action="{{ route('tasks.revision', $task) }}">
                    @csrf
                    <label class="sr-only" for="rev-{{ $task->id }}">რა უნდა შეიცვალოს</label>
                    <textarea id="rev-{{ $task->id }}" name="body" maxlength="3000" placeholder="რა უნდა შეიცვალოს?"></textarea>
                    <button class="btn danger sm" type="submit">დაბრუნება შესწორებაზე</button>
                </form>
            </details>
        </div>
    @endif

    {{-- the social media manager confirms each platform once it is live --}}
    @if ($col === 'approved' && $me->canPublish())
        <div class="kpublish">
            @foreach ($task->channels->whereNull('published_at') as $ch)
                <details class="kfold">
                    <summary class="btn sm kpost" style="--pf: {{ Studio::PLATFORMS[$ch->platform][1] }}">განთავსდა: {{ Studio::platformLabel($ch->platform) }}</summary>
                    <form method="post" action="{{ route('tasks.publish', [$task, $ch]) }}">
                        @csrf
                        <label class="sr-only" for="url-{{ $ch->id }}">პოსტის ბმული</label>
                        <input id="url-{{ $ch->id }}" type="url" name="post_url" placeholder="პოსტის ბმული (არასავალდებულო)">
                        <button class="btn ok sm" type="submit">✓ დადასტურება</button>
                    </form>
                </details>
            @endforeach
        </div>
    @endif

    @if ($col === 'published')
        @php($by = $task->channels->sortByDesc('published_at')->first()?->publisher)
        @if ($by)<p class="kby">დადო: {{ $by->name }}</p>@endif
    @endif
</article>
