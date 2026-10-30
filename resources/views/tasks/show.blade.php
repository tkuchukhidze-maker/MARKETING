@extends('layouts.app')
@use('App\Support\Studio')

@section('title', $task->title)

@section('content')
<p style="margin: 0 0 10px"><a href="{{ route('board', $task->venue) }}">← {{ $task->venue->name }}</a></p>

<header class="page-head">
    <h1><span class="venue-badge" style="background: {{ $task->venue->color }}"></span>{{ $task->title }}</h1>
    @foreach ($task->channels as $ch)
        <span class="pill" style="{{ Studio::platformStyle($ch->platform) }}">{{ $ch->label() }}</span>
    @endforeach
    <span class="pill" style="{{ Studio::statusStyle($task->status) }}">{{ Studio::statusLabel($task->status) }}</span>
</header>

<div class="cols">
    <div class="main">
        <section class="panel">
            <h2>ბრიფი</h2>
            @if ($task->brief)
                <p style="margin: 0 0 12px; white-space: pre-line">{{ $task->brief }}</p>
            @else
                <p style="margin: 0 0 12px; color: var(--muted)">ბრიფი არ არის დაწერილი.</p>
            @endif
            <div class="chips" style="justify-content: flex-start; padding: 0; margin-bottom: 12px">
                @foreach ($task->channels as $ch)
                    <span class="chip" style="{{ Studio::platformStyle($ch->platform) }}">{{ Studio::platformLabel($ch->platform) }} — {{ $ch->format ?: 'ფორმატი არ არის არჩეული' }}</span>
                @endforeach
            </div>
            <h3 style="margin: 0 0 8px; font-size: 14px">რეფერენსები</h3>
            @if ($task->reference_links)
                <ul class="refs">
                    @foreach ($task->reference_links as $ref)
                        <li><a href="{{ $ref }}" target="_blank" rel="noopener noreferrer"><b>{{ parse_url($ref, PHP_URL_HOST) }}</b><span style="color: var(--muted)">{{ \Illuminate\Support\Str::limit($ref, 70) }}</span></a></li>
                    @endforeach
                </ul>
            @else
                <p style="margin: 0; color: var(--muted)">რეფერენსი არ არის დამატებული — „დეტალებში“ ჩასვი.</p>
            @endif
        </section>

        <section class="panel">
            <h2>მზა ნამუშევარი</h2>
            @if ($task->assets->isNotEmpty())
                <div class="assets" style="margin-bottom: 14px">
                    @foreach ($task->assets as $asset)
                        <a class="asset {{ $loop->first ? 'latest' : '' }}" href="{{ $asset->href() }}" target="_blank" rel="noopener noreferrer">
                            @if ($asset->isLink() && ! $asset->isImage())
                                <div class="link-card">
                                    <svg width="22" height="22" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" aria-hidden="true"><path d="M10 14a5 5 0 0 0 7 0l3-3a5 5 0 0 0-7-7l-1 1"/><path d="M14 10a5 5 0 0 0-7 0l-3 3a5 5 0 0 0 7 7l1-1"/></svg>
                                    {{ $asset->host() }}
                                    <small>გახსნა ბმულით</small>
                                </div>
                            @else
                                <div class="thumb">
                                    @if ($asset->isImage())
                                        <img src="{{ $asset->href() }}" alt="ნამუშევარი v{{ $asset->version }}" loading="lazy">
                                    @elseif ($asset->isVideo())
                                        <video src="{{ $asset->href() }}" muted preload="metadata"></video>
                                    @else
                                        <span>{{ strtoupper(pathinfo((string) $asset->original_name, PATHINFO_EXTENSION)) }}</span>
                                    @endif
                                </div>
                            @endif
                            <small>
                                <b>v{{ $asset->version }}</b>
                                @if ($asset->channel)<span class="chip" style="{{ Studio::platformStyle($asset->channel->platform) }}">{{ Studio::platformLabel($asset->channel->platform) }}</span>@else · ყველასთვის @endif
                                <br>{{ $asset->user?->name ?? '—' }} · {{ $asset->created_at->format('d.m H:i') }}
                            </small>
                        </a>
                    @endforeach
                </div>
            @endif

            <div class="handin-tabs" role="tablist" aria-label="ჩაბარების ხერხი">
                <button class="btn sm is-on" type="button" role="tab" aria-selected="true" data-handin="file">ფაილის ატვირთვა</button>
                <button class="btn sm" type="button" role="tab" aria-selected="false" data-handin="link">ბმულით (Drive, Figma, Canva…)</button>
            </div>
            <form class="drop" method="post" action="{{ route('tasks.upload', $task) }}" enctype="multipart/form-data" data-handin-form>
                @csrf
                <label class="field" style="flex: 1 1 260px" data-handin-pane="file">ფაილი (JPG, PNG, WEBP, MP4, MOV, PDF — 100MB-მდე)
                    <input type="file" name="file" accept="image/*,video/mp4,video/quicktime,application/pdf">
                </label>
                <label class="field" style="flex: 1 1 260px" data-handin-pane="link" hidden>მზა პროდუქტის ბმული
                    <input type="url" name="url" placeholder="https://drive.google.com/…" maxlength="2048" value="{{ old('url') }}">
                </label>
                @if ($task->channels->count() > 1)
                    <label class="field" style="flex: 0 1 220px">რომელი ქსელისთვის
                        <select name="task_channel_id">
                            <option value="">ყველასთვის</option>
                            @foreach ($task->channels as $ch)
                                <option value="{{ $ch->id }}">{{ $ch->label() }}</option>
                            @endforeach
                        </select>
                    </label>
                @endif
                @if ($mine)
                    <label style="display: flex; gap: 8px; align-items: center; flex: 1 1 100%">
                        <input type="checkbox" name="mark_ready" value="1" checked>
                        ჩემი ნაწილი მზადაა — მოვნიშნო „მზადაა“
                    </label>
                @endif
                <button class="btn primary" type="submit">ატვირთვა</button>
            </form>
        </section>

        <section class="panel">
            <h2>კომენტარები</h2>
            @forelse ($task->comments as $comment)
                <div class="comment">
                    <span class="avatar" style="background: {{ $comment->user?->avatar_color ?? '#676879' }}">{{ $comment->user?->initial() ?? '?' }}</span>
                    <div class="bubble">
                        <b>{{ $comment->user?->name ?? 'წაშლილი მომხმარებელი' }}</b>
                        <small style="color: var(--muted)"> · {{ $comment->created_at->format('d.m H:i') }}</small>
                        <p>{{ $comment->body }}</p>
                    </div>
                </div>
            @empty
                <p style="color: var(--muted); margin-top: 0">ჯერ კომენტარი არ არის.</p>
            @endforelse
            <form method="post" action="{{ route('tasks.comment', $task) }}" style="display: flex; flex-direction: column; gap: 8px">
                @csrf
                <label class="field">კომენტარი
                    <textarea name="body" required maxlength="3000" style="min-height: 70px"></textarea>
                </label>
                <div><button class="btn" type="submit">დამატება</button></div>
            </form>
        </section>

        <section class="panel">
            <h2>დეტალები</h2>
            <form method="post" action="{{ route('tasks.update', $task) }}" style="display: flex; flex-direction: column; gap: 12px">
                @csrf @method('put')
                <label class="field">სათაური
                    <input name="title" required maxlength="200" value="{{ old('title', $task->title) }}">
                </label>
                @include('partials.channel-fields', [
                    'selected' => $task->channels->pluck('format', 'platform')->all(),
                    'uid' => 'edit',
                ])
                @include('partials.assignee-fields', [
                    'current' => $task->assignments->map(fn ($a) => ['user_id' => $a->user_id, 'note' => $a->note])->all(),
                    'uid' => 'edit',
                ])
                <div class="form-grid">
                    <label class="field">ვადა
                        <input type="date" name="due_on" value="{{ old('due_on', $task->due_on?->format('Y-m-d')) }}">
                    </label>
                    <label class="field">გამოქვეყნება
                        <input type="datetime-local" name="publish_at" value="{{ old('publish_at', $task->publish_at?->format('Y-m-d\TH:i')) }}">
                    </label>
                </div>
                <label class="field">ბრიფი
                    <textarea name="brief" maxlength="5000">{{ old('brief', $task->brief) }}</textarea>
                </label>
                <label class="field">რეფერენსის ბმულები <small style="font-weight: 400; color: var(--muted)">თითო ბმული ახალ ხაზზე</small>
                    <textarea name="reference_links" maxlength="5000" style="min-height: 70px" placeholder="https://…">{{ old('reference_links', implode("\n", $task->reference_links ?? [])) }}</textarea>
                </label>
                <div><button class="btn primary" type="submit">შენახვა</button></div>
            </form>
        </section>
    </div>

    <div class="aside">
        @if ($mine)
            <section class="my-part" style="--tone: {{ \App\Models\TaskAssignment::STATUSES[$mine->status][1] }}">
                <span class="side-label" style="padding: 0">ჩემი ნაწილი</span>
                <b style="font-size: 16px">{{ $mine->note ?: 'ამ დავალებაზე მუშაობ შენ' }}</b>
                <span class="pill" style="{{ $mine->style() }}; align-self: flex-start">{{ $mine->label() }}</span>
                @if ($mine->status === 'assigned')
                    <p style="margin: 0">გაეცანი ბრიფს და რეფერენსებს, მერე დააჭირე — დავალება შენს სივრცეში „მუშავდება“ სვეტში გადავა.</p>
                    <form method="post" action="{{ route('tasks.start', $task) }}">@csrf
                        <button class="btn primary" type="submit" style="width: 100%; min-height: 46px">დავიწყე მუშაობა</button>
                    </form>
                @elseif ($mine->status === 'in_progress')
                    <p style="margin: 0">როცა დაასრულებ, ატვირთე ფაილი ან ბმული (ქვემოთ) ან უბრალოდ მონიშნე „მზადაა“ — დაფაზე მაშინ გამოჩნდება.</p>
                    <form method="post" action="{{ route('tasks.ready', $task) }}">@csrf
                        <button class="btn ok" type="submit" style="width: 100%; min-height: 46px">მზადაა</button>
                    </form>
                @else
                    <p style="margin: 0">მზადაა {{ $mine->ready_at?->format('d.m H:i') }}. @if ($task->status !== 'review' && $task->assignments->count() > 1) ელოდება დანარჩენებს ({{ $task->readyCount() }}/{{ $task->assignments->count() }}). @endif</p>
                    <form method="post" action="{{ route('tasks.start', $task) }}">@csrf
                        <button class="btn sm" type="submit">დავაბრუნო მუშაობაში</button>
                    </form>
                @endif
                <a href="{{ route('workspace') }}">← ჩემი სივრცე</a>
            </section>
        @endif

        <section class="panel">
            <h2>შემსრულებლები</h2>
            @forelse ($task->assignments as $a)
                <div style="display: flex; gap: 10px; align-items: center; margin-bottom: 10px">
                    <span class="avatar lg" style="background: {{ $a->user->avatar_color }}">{{ $a->user->initial() }}</span>
                    <div style="flex: 1; min-width: 0">
                        <b>{{ $a->user->name }}</b><br>
                        <small style="color: var(--muted)">{{ $a->note ?: $a->user->roleLabel() }}</small>
                    </div>
                    <span class="pill" style="{{ $a->style() }}">{{ $a->label() }}</span>
                </div>
            @empty
                <p style="margin: 0; color: var(--muted)">ჯერ არავინ — „დეტალებში“ აირჩიე 3-მდე ადამიანი.</p>
            @endforelse
        </section>
        @php($tone = ['review' => '#1F76C2', 'approved' => '#00854D', 'published' => '#6E3FD1', 'revision' => '#D83A52'][$task->status] ?? '#676879')
        <section class="approve-box" style="background: {{ $tone }}">
            <h2 style="margin: 0; font-size: 16px">დადასტურება</h2>
            @if ($task->status === 'approved' || $task->status === 'published')
                <p style="margin: 0">დაადასტურა {{ $task->approver?->name ?? '—' }} · {{ $task->approved_at?->format('d.m H:i') }}</p>
            @elseif ($task->status === 'review')
                <p style="margin: 0">ნამუშევარი მზადაა და ელოდება დასტურს — ადმინი/CMO ან სოც. მედია მენეჯერი.</p>
            @elseif ($task->status === 'revision')
                <p style="margin: 0">შესწორება მოთხოვნილია. ახალი ვერსიის ატვირთვა დავალებას თავიდან გაგზავნის დასადასტურებლად.</p>
            @else
                <p style="margin: 0">ატვირთე ვიზუალი — დავალება ავტომატურად გადავა „დასადასტურებელში“.</p>
            @endif

            @if (auth()->user()->canApprove() && ! in_array($task->status, ['approved', 'published'], true))
                <form method="post" action="{{ route('tasks.approve', $task) }}">
                    @csrf
                    <button class="btn" type="submit" style="width: 100%; min-height: 44px; font-weight: 800">დადასტურება</button>
                </form>
                <form method="post" action="{{ route('tasks.revision', $task) }}" style="display: flex; flex-direction: column; gap: 8px">
                    @csrf
                    <label class="sr-only" for="rev-body">რა უნდა შესწორდეს</label>
                    <textarea id="rev-body" name="body" maxlength="3000" placeholder="რა უნდა შესწორდეს?" style="min-height: 60px; border-radius: 8px; border: 0; padding: 8px 10px; color: var(--ink)"></textarea>
                    <button class="btn ghost" type="submit" style="color: #fff; border: 1px solid rgba(255,255,255,.7)">შესწორების მოთხოვნა</button>
                </form>
            @endif
        </section>

        @if (in_array($task->status, ['approved', 'published'], true))
            <section class="panel publish-box">
                <h2>გამოქვეყნდა? <small style="font-weight: 500; color: var(--muted)">{{ $task->publishedCount() }}/{{ $task->channels->count() }}</small></h2>
                @if ($task->publish_at)
                    <p style="margin: 0 0 10px; color: var(--muted)">დაგეგმილი: {{ $task->publish_at->format('d.m.Y H:i') }}</p>
                @endif
                @foreach ($task->channels as $ch)
                    <div class="publish-row {{ $ch->isPublished() ? 'is-done' : '' }}" style="--pf: {{ Studio::PLATFORMS[$ch->platform][1] }}">
                        <div class="publish-head">
                            <span class="chip" style="{{ Studio::platformStyle($ch->platform) }}">{{ $ch->label() }}</span>
                            @if ($ch->isPublished())
                                <span class="publish-ok">✓ გამოქვეყნდა</span>
                            @else
                                <span style="color: var(--muted); font-size: 13px">ჯერ არ არის დადასტურებული</span>
                            @endif
                        </div>
                        @if ($ch->isPublished())
                            <small style="color: var(--muted)">{{ $ch->publisher?->name ?? '—' }} · {{ $ch->published_at->format('d.m H:i') }}</small>
                            @if ($ch->post_url)
                                <a href="{{ $ch->post_url }}" target="_blank" rel="noopener noreferrer" style="word-break: break-all">პოსტის ნახვა → {{ parse_url($ch->post_url, PHP_URL_HOST) }}</a>
                            @endif
                            @if (auth()->user()->canPublish())
                                <form method="post" action="{{ route('tasks.unpublish', [$task, $ch]) }}">
                                    @csrf @method('delete')
                                    <button class="btn sm ghost" type="submit">გაუქმება</button>
                                </form>
                            @endif
                        @elseif (auth()->user()->canPublish())
                            <form method="post" action="{{ route('tasks.publish', [$task, $ch]) }}" class="publish-form">
                                @csrf
                                <label class="sr-only" for="post-{{ $ch->id }}">{{ Studio::platformLabel($ch->platform) }} — პოსტის ბმული</label>
                                <input id="post-{{ $ch->id }}" type="url" name="post_url" placeholder="პოსტის ბმული (არასავალდებულო)" maxlength="2048">
                                <button class="btn sm ok" type="submit">✓ გამოქვეყნდა</button>
                            </form>
                        @endif
                    </div>
                @endforeach
                @unless (auth()->user()->canPublish())
                    <p style="margin: 8px 0 0; color: var(--muted); font-size: 13px">გამოქვეყნებას ადასტურებს ადმინი/CMO ან სოც. მედია მენეჯერი.</p>
                @endunless
            </section>
        @endif

        @if (auth()->user()->canApprove())
        <section class="panel">
            <h2>სტატუსის შეცვლა</h2>
            <form method="post" action="{{ route('tasks.quick', $task) }}" style="display: grid; gap: 6px">
                @csrf @method('patch')
                @foreach ($allowed as $s)
                    <button class="btn" type="submit" name="status" value="{{ $s }}" style="{{ Studio::statusStyle($s) }}; border: 0; {{ $s === $task->status ? 'outline: 3px solid #323338; outline-offset: 1px;' : '' }}">{{ Studio::statusLabel($s) }}</button>
                @endforeach
            </form>
        </section>
        @endif

        <section class="panel">
            <h2>ინფორმაცია</h2>
            <dl class="kv">
                <dt>რესტორანი</dt><dd>{{ $task->venue->name }}</dd>
                <dt>შემქმნელი</dt><dd>{{ $task->creator?->name ?? '—' }}</dd>
                <dt>შემსრულებლები</dt><dd>{{ $task->assignments->map(fn ($a) => $a->user->name)->join(', ') ?: '—' }}</dd>
                <dt>ვადა</dt><dd class="{{ $task->isOverdue() ? 'due-late' : '' }}">{{ $task->due_on?->format('d.m.Y') ?? '—' }}</dd>
                <dt>გამოქვეყნება</dt><dd>{{ $task->publish_at?->format('d.m.Y H:i') ?? '—' }}</dd>
            </dl>
        </section>

        @if ($task->venue->concept)
            <section class="panel">
                <h2>კონცეფცია — {{ $task->venue->name }}</h2>
                <p style="margin: 0; white-space: pre-line">{{ $task->venue->concept }}</p>
            </section>
        @endif

        <section class="panel">
            <h2>ისტორია</h2>
            <ul class="timeline">
                @foreach ($task->activities->take(15) as $a)
                    <li><b>{{ $a->user?->name ?? '—' }}</b> {{ $a->describe() }} · {{ $a->created_at->format('d.m H:i') }}</li>
                @endforeach
            </ul>
        </section>

        @if (auth()->user()->canApprove() || $task->creator_id === auth()->id())
            <form method="post" action="{{ route('tasks.destroy', $task) }}" onsubmit="return confirm('წავშალო დავალება და მისი ვიზუალები?')">
                @csrf @method('delete')
                <button class="btn danger" type="submit">დავალების წაშლა</button>
            </form>
        @endif
    </div>
</div>
@push('scripts')
<script>
    (function () {
        var form = document.querySelector('[data-handin-form]');
        if (!form) return;
        var tabs = document.querySelectorAll('[data-handin]');
        function show(mode) {
            tabs.forEach(function (t) {
                var on = t.getAttribute('data-handin') === mode;
                t.classList.toggle('is-on', on);
                t.setAttribute('aria-selected', on ? 'true' : 'false');
            });
            form.querySelectorAll('[data-handin-pane]').forEach(function (p) {
                var on = p.getAttribute('data-handin-pane') === mode;
                p.hidden = !on;
                var input = p.querySelector('input');
                input.required = on;
                if (!on) input.value = '';
            });
        }
        tabs.forEach(function (t) {
            t.addEventListener('click', function () { show(t.getAttribute('data-handin')); });
        });
        show({{ old('url') ? "'link'" : "'file'" }});
    })();
</script>
@endpush
@endsection
