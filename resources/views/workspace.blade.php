@extends('layouts.app')
@use('App\Support\Studio')

@section('title', 'ჩემი სივრცე')

@section('content')
<header class="page-head">
    <h1>გამარჯობა, {{ auth()->user()->name }}</h1>
    <span class="spacer"></span>
    <nav class="tabs" aria-label="რესტორანი" style="margin: 0; border: 0">
        <a class="tab {{ $venue ? '' : 'is-active' }}" href="{{ route('workspace') }}">ორივე სახლი</a>
        @foreach ($venues as $v)
            <a class="tab {{ $venue?->is($v) ? 'is-active' : '' }}" href="{{ route('workspace', ['venue' => $v->slug]) }}">
                <span class="pf-dot" style="background: {{ $v->color }}"></span>{{ $v->name }}
            </a>
        @endforeach
    </nav>
</header>

<ol class="steps" aria-label="როგორ მუშაობს">
    <li><b>1</b> გაეცანი და დააჭირე „დავიწყე“</li>
    <li><b>2</b> ატვირთე ფაილი ან ჩასვი ბმული</li>
    <li><b>3</b> „მზადაა“ — წავა დასადასტურებლად</li>
</ol>

{{-- 1. what I'm working on now: everything needed is on the card --}}
<section class="ws-section" aria-labelledby="h-working">
    <h2 id="h-working"><span class="ws-dot" style="--tone: #1F76C2"></span>ახლა ვმუშაობ <span class="count">{{ $working->count() }}</span></h2>

    @forelse ($working as $a)
        @php($t = $a->task)
        @php($feedback = $t->status === 'revision' ? $t->comments->first(fn ($c) => $c->user_id !== auth()->id()) : null)
        <article class="job {{ $t->status === 'revision' ? 'is-revision' : '' }}">
            <div class="job-main">
                <div class="job-top">
                    <span class="chip" style="background: {{ $t->venue->color }}; color: #fff">{{ $t->venue->name }}</span>
                    <span class="job-due {{ $t->isOverdue() ? 'due-late' : '' }}">ვადა: <b>{{ $t->due_on?->format('d.m') ?? '—' }}</b></span>
                </div>
                <h3><a href="{{ route('tasks.show', $t) }}">{{ $t->title }}</a></h3>

                @if ($t->status === 'revision')
                    <div class="job-feedback" role="note">
                        <b>შესწორება:</b>
                        {{ $feedback?->body ?? 'შესწორება მოითხოვეს — ნახე დავალების გვერდი.' }}
                        @if ($feedback)<small>— {{ $feedback->user?->name }}</small>@endif
                    </div>
                @endif

                @if ($a->note)<p class="job-part"><b>შენი ნაწილი:</b> {{ $a->note }}</p>@endif

                <div class="job-formats">
                    @foreach ($t->channels as $ch)
                        <div class="fmt" style="--pf: {{ Studio::PLATFORMS[$ch->platform][1] }}">
                            <b>{{ Studio::platformLabel($ch->platform) }}</b>
                            <span>{{ $ch->format ?: 'ფორმატი არ არის მითითებული' }}</span>
                            @if ($size = Studio::sizeFor($ch->platform, $ch->format))<small>{{ $size }} px</small>@endif
                        </div>
                    @endforeach
                </div>

                @if ($t->brief)
                    <details class="job-brief" open>
                        <summary>ბრიფი</summary>
                        <p>{{ $t->brief }}</p>
                    </details>
                @endif

                @if ($t->reference_links)
                    <div class="job-refs">
                        <span>რეფერენსები:</span>
                        @foreach ($t->reference_links as $i => $ref)
                            <a class="btn sm" href="{{ $ref }}" target="_blank" rel="noopener noreferrer">{{ $i + 1 }}. {{ \Illuminate\Support\Str::limit(parse_url($ref, PHP_URL_HOST), 22) }}</a>
                        @endforeach
                    </div>
                @endif
            </div>

            <form class="job-handin" method="post" action="{{ route('tasks.upload', $t) }}" enctype="multipart/form-data">
                @csrf
                <input type="hidden" name="mark_ready" value="1">
                <b>ჩააბარე ნამუშევარი</b>
                <label class="drop-zone">
                    <input type="file" name="file" accept="image/*,video/mp4,video/quicktime,application/pdf">
                    <span>აირჩიე ფაილი<br><small>JPG, PNG, MP4, PDF · 100MB-მდე</small></span>
                </label>
                <label class="field">ან ჩასვი ბმული
                    <input type="url" name="url" placeholder="Drive / Figma / Canva / Dropbox" maxlength="2048">
                </label>
                @if ($t->channels->count() > 1)
                    <label class="field">რომელი ქსელისთვის
                        <select name="task_channel_id">
                            <option value="">ყველასთვის</option>
                            @foreach ($t->channels as $ch)
                                <option value="{{ $ch->id }}">{{ $ch->label() }}</option>
                            @endforeach
                        </select>
                    </label>
                @endif
                <button class="btn ok" type="submit" style="min-height: 46px">ატვირთვა და მზადაა</button>
                @if ($t->assets_count)
                    <small style="color: var(--muted)">უკვე ატვირთული: {{ $t->assets_count }} ვერსია</small>
                @endif
            </form>
            <form method="post" action="{{ route('tasks.ready', $t) }}" class="job-ready-only">
                @csrf
                <button class="btn sm ghost" type="submit">ატვირთვის გარეშე — უბრალოდ მზადაა</button>
            </form>
        </article>
    @empty
        <p class="ws-empty">ახლა არაფერზე მუშაობ. აირჩიე ქვემოთ ახალი დავალება და დააჭირე „დავიწყე“.</p>
    @endforelse
</section>

{{-- 2. new for me --}}
<section class="ws-section" aria-labelledby="h-new">
    <h2 id="h-new"><span class="ws-dot" style="--tone: #676879"></span>ახალი დავალებები <span class="count">{{ $new->count() }}</span></h2>
    <div class="new-grid">
        @forelse ($new as $a)
            @php($t = $a->task)
            <article class="ws-card">
                <div class="job-top">
                    <span class="chip" style="background: {{ $t->venue->color }}; color: #fff">{{ $t->venue->name }}</span>
                    <span class="job-due {{ $t->isOverdue() ? 'due-late' : '' }}">ვადა: <b>{{ $t->due_on?->format('d.m') ?? '—' }}</b></span>
                </div>
                <a class="ws-title" href="{{ route('tasks.show', $t) }}">{{ $t->title }}</a>
                <div class="chips" style="justify-content: flex-start; padding: 0">
                    @foreach ($t->channels as $ch)
                        <span class="chip" style="{{ Studio::platformStyle($ch->platform) }}">{{ Studio::platformLabel($ch->platform) }}{{ $ch->format ? ' · '.$ch->format : '' }}</span>
                    @endforeach
                </div>
                @if ($a->note)<p class="ws-note"><b>შენი ნაწილი:</b> {{ $a->note }}</p>@endif
                @if ($t->brief)<p class="ws-brief">{{ \Illuminate\Support\Str::limit($t->brief, 140) }}</p>@endif
                <div class="ws-actions">
                    <a class="btn sm" href="{{ route('tasks.show', $t) }}">სრულად ნახვა</a>
                    <form method="post" action="{{ route('tasks.start', $t) }}">@csrf
                        <button class="btn primary" type="submit">დავიწყე</button>
                    </form>
                </div>
            </article>
        @empty
            <p class="ws-empty">ახალი დავალება არ გაქვს.</p>
        @endforelse
    </div>
</section>

{{-- 3. handed in, waiting --}}
@if ($sent->isNotEmpty())
    <section class="ws-section" aria-labelledby="h-sent">
        <h2 id="h-sent"><span class="ws-dot" style="--tone: #00854D"></span>გაგზავნილი — ელოდება დასტურს <span class="count">{{ $sent->count() }}</span></h2>
        <ul class="sent-list">
            @foreach ($sent as $a)
                <li>
                    <span class="chip" style="background: {{ $a->task->venue->color }}; color: #fff">{{ $a->task->venue->name }}</span>
                    <a href="{{ route('tasks.show', $a->task) }}"><b>{{ $a->task->title }}</b></a>
                    <span style="color: var(--muted)">{{ $a->task->status === 'review' ? 'ელოდება დასტურს' : 'ელოდება დანარჩენებს ('.$a->task->readyCount().'/'.$a->task->assignments->count().')' }}</span>
                    <form method="post" action="{{ route('tasks.start', $a->task) }}">@csrf
                        <button class="btn sm ghost" type="submit">დავაბრუნო მუშაობაში</button>
                    </form>
                </li>
            @endforeach
        </ul>
    </section>
@endif

@if ($done->isNotEmpty())
    <section class="ws-section" aria-labelledby="h-done">
        <h2 id="h-done"><span class="ws-dot" style="--tone: #6E3FD1"></span>ბოლოს დადასტურებული</h2>
        <ul class="sent-list">
            @foreach ($done as $a)
                <li>
                    <a href="{{ route('tasks.show', $a->task) }}"><b>{{ $a->task->title }}</b></a>
                    <span style="color: var(--muted)">{{ $a->task->venue->name }} · {{ Studio::statusLabel($a->task->status) }}</span>
                </li>
            @endforeach
        </ul>
    </section>
@endif
@endsection
