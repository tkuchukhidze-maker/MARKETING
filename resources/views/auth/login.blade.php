<!doctype html>
<html lang="ka">
<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <meta name="robots" content="noindex, nofollow">
    <title>შესვლა — სახლში Studio</title>
    <link rel="stylesheet" href="https://fonts.googleapis.com/css2?family=Figtree:wght@400;600;800&family=Noto+Sans+Georgian:wght@400;600;800&display=swap">
    <link rel="stylesheet" href="{{ asset('css/studio.css') }}?v=2">
</head>
<body>
<div class="login">
    <form class="login-card" method="post" action="{{ route('login') }}">
        @csrf
        <div class="brand" style="padding: 0">
            <span class="brand-mark"><span>ს</span></span>
            <b>სახლში Studio</b>
        </div>
        <p style="margin: 0; color: var(--muted)">ბათუმის და ქუთაისის სახლში — სოც. მედია გუნდის სივრცე</p>

        @if ($errors->any())
            <div class="errors" role="alert">{{ $errors->first() }}</div>
        @endif

        <label class="field">ელფოსტა
            <input type="email" name="email" value="{{ old('email') }}" required autofocus autocomplete="username">
        </label>
        <label class="field">პაროლი
            <input type="password" name="password" required autocomplete="current-password">
        </label>
        <label style="display: flex; gap: 8px; align-items: center">
            <input type="checkbox" name="remember" value="1"> დამიმახსოვრე
        </label>
        <button class="btn primary" type="submit" style="min-height: 44px">შესვლა</button>
    </form>
</div>
</body>
</html>
