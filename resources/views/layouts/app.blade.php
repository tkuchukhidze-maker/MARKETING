<!doctype html>
<html lang="ka">
<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <meta name="robots" content="noindex, nofollow">
    <title>@yield('title', 'სახლში Studio')</title>
    <link rel="preconnect" href="https://fonts.googleapis.com">
    <link rel="preconnect" href="https://fonts.gstatic.com" crossorigin>
    <link rel="stylesheet" href="https://fonts.googleapis.com/css2?family=Figtree:wght@400;500;600;700;800&family=Noto+Sans+Georgian:wght@400;500;600;700;800&display=swap">
    <link rel="stylesheet" href="{{ asset('css/studio.css') }}?v=2">
</head>
<body>
@php($navVenues = \App\Models\Venue::orderBy('sort')->get())
<div class="shell">
    <aside class="side">
        <a class="brand" href="{{ route('home') }}">
            <span class="brand-mark"><span>ს</span></span>
            <b>სახლში Studio</b>
        </a>

        @foreach ($navVenues as $v)
            <nav aria-label="{{ $v->name }}">
                <a class="side-link {{ request()->routeIs('board') && request()->route('venue')?->is($v) ? 'is-active' : '' }}" href="{{ route('board', $v) }}">
                    <span class="venue-dot" style="background: {{ $v->color }}"></span>{{ $v->name }}
                </a>
                <a class="side-link sub {{ request()->routeIs('calendar') && request()->route('venue')?->is($v) ? 'is-active' : '' }}" href="{{ route('calendar', $v) }}">კალენდარი</a>
            </nav>
        @endforeach

        <nav aria-label="გუნდი">
            <span class="side-label">გუნდი</span>
            <a class="side-link {{ request()->routeIs('team') ? 'is-active' : '' }}" href="{{ route('team') }}">გუნდი და დატვირთვა</a>
            <a class="side-link {{ request()->routeIs('contacts') ? 'is-active' : '' }}" href="{{ route('contacts') }}">კონტაქტები (CRM)</a>
        </nav>

        <div class="side-user">
            <span class="avatar lg" style="background: {{ auth()->user()->avatar_color }}">{{ auth()->user()->initial() }}</span>
            <div>
                <b>{{ auth()->user()->name }}</b>
                <small>{{ auth()->user()->roleLabel() }}</small>
            </div>
            <form method="post" action="{{ route('logout') }}">
                @csrf
                <button class="btn ghost sm" type="submit">გასვლა</button>
            </form>
        </div>
    </aside>

    <main class="content">
        @if (session('ok'))
            <div class="flash" role="status">{{ session('ok') }}</div>
        @endif
        @if ($errors->any())
            <div class="errors" role="alert">
                @foreach ($errors->all() as $error)<div>{{ $error }}</div>@endforeach
            </div>
        @endif

        @yield('content')
    </main>
</div>
<script>
    // platform picker: "all" toggles every platform, a format is only sent for chosen ones
    document.querySelectorAll('[data-channels]').forEach(function (box) {
        var all = box.querySelector('[data-all]');
        var picks = Array.prototype.slice.call(box.querySelectorAll('[data-platform]'));
        function sync() {
            picks.forEach(function (p) {
                var row = p.closest('.channel-row');
                row.classList.toggle('is-on', p.checked);
                row.querySelector('[data-format]').disabled = !p.checked;
            });
            all.checked = picks.every(function (p) { return p.checked; });
        }
        all.addEventListener('change', function () {
            picks.forEach(function (p) { p.checked = all.checked; });
            sync();
        });
        picks.forEach(function (p) { p.addEventListener('change', sync); });
        sync();
    });

    // close any open <details> menu when clicking elsewhere
    document.addEventListener('click', function (e) {
        document.querySelectorAll('details.menu[open]').forEach(function (d) {
            if (!d.contains(e.target)) d.removeAttribute('open');
        });
    });
</script>
@stack('scripts')
</body>
</html>
