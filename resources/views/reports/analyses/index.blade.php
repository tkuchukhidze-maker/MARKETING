@extends('layouts.app')

@section('title', 'ანალიზები — CMO რეპორტინგი')

@push('styles')
<link rel="stylesheet" href="{{ asset('css/reports.css') }}?v=2">
@endpush

@section('content')
<header class="page-head">
    <h1>ანალიზები</h1>
    <span class="spacer"></span>
    <a class="btn primary" href="{{ route('reports.analyses.create') }}">+ ახალი ანალიზი</a>
</header>

<nav class="rp-chips" aria-label="ბრენდი" style="margin-bottom: 18px">
    <a class="rp-chip {{ $brand ? '' : 'is-on' }}" href="{{ route('reports.analyses.index') }}">ყველა</a>
    @foreach ($venues as $v)
        <a class="rp-chip {{ $brand?->is($v) ? 'is-on' : '' }}" href="{{ route('reports.analyses.index', ['brand' => $v->slug]) }}">
            <span class="venue-dot" style="background: {{ $v->color }}"></span>{{ $v->name }}
        </a>
    @endforeach
</nav>

@if ($reports->isEmpty())
    <div class="rp-callout info">
        <span aria-hidden="true">i</span>
        <p>ჯერ არცერთი ანალიზი არ არის. ატვირთე Meta Ads-ის, Inbox-ის ან სხვა რეპორტი (.md ან .txt) — ცხრილები და ჩეკლისტი ავტომატურად აეწყობა.
            <a href="{{ route('reports.analyses.create') }}">ატვირთვა →</a></p>
    </div>
@else
    <div class="rp-analyses">
        @foreach ($reports as $a)
            @include('reports.analyses.card', ['a' => $a])
        @endforeach
    </div>
@endif
@endsection
