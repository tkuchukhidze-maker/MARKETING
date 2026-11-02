@extends('layouts.app')

@section('title', 'ახალი ანალიზი — CMO რეპორტინგი')

@push('styles')
<link rel="stylesheet" href="{{ asset('css/reports.css') }}?v=2">
@endpush

@section('content')
<header class="page-head">
    <h1>ახალი ანალიზი</h1>
    <span class="spacer"></span>
    <a class="btn sm" href="{{ route('reports.analyses.index') }}">← ანალიზები</a>
</header>

<div class="cols">
    <form class="main panel" method="post" action="{{ route('reports.analyses.store') }}" enctype="multipart/form-data" style="display: flex; flex-direction: column; gap: 14px">
        @csrf
        <div class="form-grid">
            <label class="field">სათაური
                <input name="title" required maxlength="255" value="{{ old('title') }}" placeholder="მაგ. Meta Ads — ოქტომბრის ანალიზი">
            </label>
            <label class="field">ბრენდი
                <select name="venue_id">
                    <option value="">ყველა ბრენდი</option>
                    @foreach ($venues as $v)<option value="{{ $v->id }}" @selected(old('venue_id', request('brand') ? $venues->firstWhere('slug', request('brand'))?->id : null) == $v->id)>{{ $v->name }}</option>@endforeach
                </select>
            </label>
            <label class="field">პერიოდი — დან <input type="date" name="period_from" value="{{ old('period_from') }}"></label>
            <label class="field">მდე <input type="date" name="period_to" value="{{ old('period_to') }}"></label>
        </div>

        <label class="field">ფაილი (.md ან .txt)
            <input type="file" name="file" accept=".md,.markdown,.txt,text/markdown,text/plain">
        </label>
        <label class="field">ან ჩასვი ტექსტი
            <textarea name="body" rows="14" placeholder="# სათაური&#10;&#10;ტექსტი…&#10;&#10;| სვეტი | სვეტი |&#10;|---|---|&#10;&#10;### დღეს&#10;- [ ] დავალება">{{ old('body') }}</textarea>
        </label>
        <div><button class="btn primary" type="submit">შენახვა</button></div>
    </form>

    <aside class="aside">
        <section class="panel">
            <h2>ფორმატი</h2>
            <dl class="kv" style="font-size: 13px; grid-template-columns: 1fr">
                <dt>სათაურები</dt><dd><code>## თავი</code>, <code>### ქვეთავი</code></dd>
                <dt>ცხრილი</dt><dd><code>| ხარჯი | საუბარი |</code><br><code>|---|---|</code></dd>
                <dt>ჩეკლისტი</dt><dd><code>- [ ] გასაკეთებელი</code> — ცალკე ბლოკად გამოვა, მოსანიშნი ღილაკებით. ჯგუფი = ზემოთ მდგომი სათაური, მაგ. <code>### 2 კვირაში</code></dd>
                <dt>მუქი</dt><dd><code>**ტექსტი**</code></dd>
            </dl>
        </section>
    </aside>
</div>
@endsection
