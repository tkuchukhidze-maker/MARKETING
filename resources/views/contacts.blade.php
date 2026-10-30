@extends('layouts.app')
@use('App\Support\Studio')

@section('title', 'კონტაქტები')

@section('content')
<header class="page-head">
    <h1>კონტაქტები</h1>
    <span class="spacer"></span>
    <button class="btn primary" type="button" onclick="document.getElementById('new-contact').showModal()">+ კონტაქტი</button>
</header>

<nav class="tabs" aria-label="ტიპი">
    <a class="tab {{ $type ? '' : 'is-active' }}" href="{{ route('contacts') }}">ყველა</a>
    @foreach (Studio::CONTACT_TYPES as $key => $label)
        <a class="tab {{ $type === $key ? 'is-active' : '' }}" href="{{ route('contacts', ['type' => $key]) }}">{{ $label }}</a>
    @endforeach
</nav>

<div class="table-wrap">
    <table class="board" style="min-width: 860px">
        <thead>
        <tr>
            <th scope="col" style="width: 26%">სახელი</th>
            <th scope="col">ტიპი</th>
            <th scope="col">რესტორანი</th>
            <th scope="col">კონტაქტი</th>
            <th scope="col">შემდეგი ნაბიჯი</th>
            <th scope="col"><span class="sr-only">მოქმედება</span></th>
        </tr>
        </thead>
        <tbody>
        @forelse ($contacts as $c)
            <tr>
                <td class="name" style="--group: {{ $c->venue?->color ?? '#676879' }}"><b>{{ $c->name }}</b></td>
                <td>{{ Studio::CONTACT_TYPES[$c->type] ?? $c->type }}</td>
                <td>@if ($c->venue)<div class="cell-fill" style="background: {{ $c->venue->color }}; color: #fff">{{ $c->venue->name }}</div>@else ორივე @endif</td>
                <td style="font-size: 13px">
                    {{ $c->phone }}
                    @if ($c->email)<br><a href="mailto:{{ $c->email }}">{{ $c->email }}</a>@endif
                    @if ($c->instagram)<br>{{ $c->instagram }}@endif
                </td>
                <td style="text-align: left; padding: 0 10px">{{ $c->next_step ?? '—' }}</td>
                <td>
                    <details class="menu">
                        <summary class="btn sm ghost">შეცვლა</summary>
                        <form class="menu-pop" method="post" action="{{ route('contacts.update', $c) }}" style="min-width: 300px; left: auto; right: 0; transform: none; text-align: left">
                            @csrf @method('put')
                            @include('partials.contact-fields', ['c' => $c])
                            <button class="btn primary" type="submit">შენახვა</button>
                        </form>
                    </details>
                    <form method="post" action="{{ route('contacts.destroy', $c) }}" onsubmit="return confirm('წავშალო კონტაქტი?')">
                        @csrf @method('delete')
                        <button class="btn sm ghost" type="submit" style="color: var(--danger)">წაშლა</button>
                    </form>
                </td>
            </tr>
        @empty
            <tr class="empty-row"><td colspan="6">კონტაქტები ჯერ არ არის.</td></tr>
        @endforelse
        </tbody>
    </table>
</div>

<dialog id="new-contact" aria-labelledby="new-contact-title">
    <form method="post" action="{{ route('contacts.store') }}">
        @csrf
        <h2 id="new-contact-title">ახალი კონტაქტი</h2>
        @include('partials.contact-fields', ['c' => null])
        <div style="display: flex; gap: 8px; justify-content: flex-end">
            <button class="btn" type="button" onclick="this.closest('dialog').close()">გაუქმება</button>
            <button class="btn primary" type="submit">დამატება</button>
        </div>
    </form>
</dialog>
@endsection
