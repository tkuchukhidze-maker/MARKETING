@extends('layouts.app')
@use('App\Support\Studio')

@section('title', 'გუნდი და დატვირთვა')

@section('content')
<header class="page-head">
    <h1>გუნდი და დატვირთვა</h1>
</header>

<div class="venue-cards">
    @foreach ($venueStats as $s)
        <a class="venue-card" href="{{ route('board', $s['venue']) }}" style="background: {{ $s['venue']->color }}">
            <div style="display: flex; align-items: center; gap: 10px; flex-wrap: wrap">
                <h2 style="flex: 1">{{ $s['venue']->name }}</h2>
                <span class="pill" style="background: #fff; color: var(--ink)">{{ $s['open'] }} ღია · {{ $s['review'] }} დასადასტურებელი</span>
            </div>
            <div class="nums">
                @foreach (Studio::PLATFORMS as $key => [$label, $bg, $fg])
                    <div>
                        <span class="pill" style="background: {{ $bg }}; color: {{ $fg }}; align-self: flex-start">{{ $label }}</span>
                        <b>{{ $s['week'][$key] }}</b>
                        <small style="color: var(--muted)">ამ კვირაში</small>
                    </div>
                @endforeach
            </div>
        </a>
    @endforeach
</div>

<div class="cols">
    <div class="main">
        <section class="panel">
            <h2>ვინ რაზე მუშაობს</h2>
            <div style="overflow-x: auto">
                <table class="plain" style="min-width: 640px">
                    <thead>
                    <tr>
                        <th scope="col">წევრი</th>
                        @foreach ($venues as $v)<th scope="col">{{ $v->name }}</th>@endforeach
                        <th scope="col">მუშავდება</th>
                        <th scope="col">მზადაა</th>
                        <th scope="col">ვადაგადაცილ.</th>
                        <th scope="col" style="width: 22%">დატვირთვა</th>
                    </tr>
                    </thead>
                    <tbody>
                    @foreach ($team as $row)
                        @php($u = $row['user'])
                        <tr style="{{ $u->is_active ? '' : 'opacity: .55' }}">
                            <td>
                                <span style="display: flex; align-items: center; gap: 10px">
                                    <span class="avatar" style="background: {{ $u->avatar_color }}">{{ $u->initial() }}</span>
                                    <span><b>{{ $u->name }}</b><br><small style="color: var(--muted)">{{ $u->roleLabel() }}</small></span>
                                </span>
                            </td>
                            @foreach ($venues as $v)<td>{{ $row['byVenue'][$v->id] }}</td>@endforeach
                            <td>{{ $row['working'] }}</td>
                            <td>{{ $row['ready'] }}</td>
                            <td class="{{ $row['overdue'] ? 'due-late' : '' }}">{{ $row['overdue'] }}</td>
                            <td>
                                @php($pct = min(100, $row['total'] / max(1, $threshold) * 100))
                                <div class="bar" role="img" aria-label="{{ $row['total'] }} დაუსრულებელი"><span style="width: {{ $pct }}%; background: {{ $row['total'] >= $threshold ? '#D83A52' : '#00854D' }}"></span></div>
                                <small style="color: var(--muted)">{{ $row['total'] }} დაუსრულებელი</small>
                            </td>
                        </tr>
                    @endforeach
                    </tbody>
                </table>
            </div>
        </section>

        @if (auth()->user()->isAdmin())
            <section class="panel">
                <h2>გუნდის წევრის დამატება</h2>
                <form method="post" action="{{ route('team.users.store') }}" style="display: flex; flex-direction: column; gap: 12px">
                    @csrf
                    <div class="form-grid">
                        <label class="field">სახელი<input name="name" required maxlength="100" value="{{ old('name') }}"></label>
                        <label class="field">ელფოსტა<input type="email" name="email" required value="{{ old('email') }}"></label>
                        <label class="field">როლი
                            <select name="role" required>
                                @foreach (Studio::ROLES as $key => $label)<option value="{{ $key }}">{{ $label }}</option>@endforeach
                            </select>
                        </label>
                        <label class="field">დროებითი პაროლი<input type="text" name="password" required minlength="8" autocomplete="new-password"></label>
                    </div>
                    <div><button class="btn primary" type="submit">დამატება</button></div>
                </form>
            </section>

            <section class="panel">
                <h2>როლები და წვდომა</h2>
                <table class="plain">
                    <tbody>
                    @foreach ($team as $row)
                        @php($u = $row['user'])
                        <tr>
                            <td><b>{{ $u->name }}</b><br><small style="color: var(--muted)">{{ $u->email }}</small></td>
                            <td>
                                <form method="post" action="{{ route('team.users.update', $u) }}" style="display: flex; gap: 8px; flex-wrap: wrap; align-items: center">
                                    @csrf @method('patch')
                                    <label class="sr-only" for="role-{{ $u->id }}">როლი</label>
                                    <select id="role-{{ $u->id }}" name="role" style="min-height: 34px; border: 1px solid var(--line-strong); border-radius: 6px">
                                        @foreach (Studio::ROLES as $key => $label)<option value="{{ $key }}" @selected($u->role === $key)>{{ $label }}</option>@endforeach
                                    </select>
                                    <label class="sr-only" for="active-{{ $u->id }}">სტატუსი</label>
                                    <select id="active-{{ $u->id }}" name="is_active" style="min-height: 34px; border: 1px solid var(--line-strong); border-radius: 6px">
                                        <option value="1" @selected($u->is_active)>აქტიური</option>
                                        <option value="0" @selected(! $u->is_active)>გათიშული</option>
                                    </select>
                                    <button class="btn sm" type="submit">შენახვა</button>
                                </form>
                            </td>
                        </tr>
                    @endforeach
                    </tbody>
                </table>
            </section>
        @endif
    </div>

    <div class="aside">
        <section class="panel">
            <h2>ჭკვიანი შეხსენებები</h2>
            @forelse ($hints as $hint)
                <a class="hint {{ $hint['tone'] }}" href="{{ $hint['url'] ?? '#' }}" style="margin-bottom: 8px">
                    <span class="ic" aria-hidden="true">!</span><span>{{ $hint['text'] }}</span>
                </a>
            @empty
                <p style="margin: 0; color: var(--muted)">ყველაფერი რიგზეა — შეხსენება არ არის.</p>
            @endforelse
        </section>
    </div>
</div>
@endsection
