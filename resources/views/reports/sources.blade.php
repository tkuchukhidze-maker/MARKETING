@extends('layouts.app')
@use('App\Reporting\Catalog')

@section('title', 'პლატფორმები — CMO რეპორტინგი')

@push('styles')
<link rel="stylesheet" href="{{ asset('css/reports.css') }}?v=2">
@endpush

@section('content')
<header class="page-head">
    <h1>პლატფორმები</h1>
    <span class="spacer"></span>
    <a class="btn sm" href="{{ route('reports.index') }}">← დაშბორდი</a>
</header>

<p class="rp-muted" style="margin-top: -6px">ყველა ანგარიში ერთ ბაზაში იწერება. ყოველ დილით {{ config('reporting.sync_at') }}-ზე ბოლო {{ config('reporting.sync_days') }} დღე თავიდან იტვირთება. ტოკენები ბაზაში დაშიფრულად ინახება და აქ აღარ ჩანს.</p>

{{-- ---- Google: one sign-in, then pick what to add ---- --}}
<section class="panel" style="margin-bottom: 18px" id="google">
    <div style="display: flex; align-items: center; gap: 10px; flex-wrap: wrap">
        <h2 style="margin: 0; flex: 1">Google — Analytics, Search Console, Business Profile</h2>
        @if ($google)
            <span class="rp-status ok">დაკავშირებულია{{ $google->email ? ': '.$google->email : '' }}</span>
            <form method="post" action="{{ route('reports.google.disconnect') }}" onsubmit="return confirm('გაითიშოს Google? დამატებული წყაროები აღარ განახლდება.')">
                @csrf @method('delete')
                <button class="btn sm ghost" type="submit">გათიშვა</button>
            </form>
        @elseif ($googleReady)
            <a class="btn primary" href="{{ route('reports.google.connect') }}">Google-ის დაკავშირება</a>
        @endif
    </div>

    @unless ($googleReady)
        <div class="rp-callout" style="margin: 14px 0 0">
            <span aria-hidden="true">!</span>
            <p><b>Google-ის აპი ჯერ არ არის მითითებული.</b> Laravel Cloud → Environment-ში დაამატე <code>GOOGLE_CLIENT_ID</code> და <code>GOOGLE_CLIENT_SECRET</code> და გაუშვი Deploy.
                Google Cloud-ის OAuth კლიენტში Redirect URI: <code style="display: inline">{{ $callbackUrl }}</code></p>
        </div>
    @endunless

    @if ($google)
        @foreach ($discovered['errors'] as $api => $msg)
            <div class="rp-source" style="margin-top: 12px"><div class="err">{{ ['auth' => 'Google', 'ga4' => 'Analytics', 'search_console' => 'Search Console', 'google_business' => 'Business Profile'][$api] ?? $api }}: {{ $msg }}</div></div>
        @endforeach

        @php
            $groups = ['ga4' => 'Google Analytics 4 — საიტები', 'search_console' => 'Search Console — ორგანული ძიება', 'google_business' => 'Business Profile — Google რუკა'];
            $guess = function (string $text) use ($venues) {
                $t = mb_strtolower($text);
                foreach ($venues as $v) {
                    foreach ([$v->slug, mb_strtolower($v->name)] as $needle) {
                        if ($needle !== '' && str_contains($t, $needle)) return $v->id;
                    }
                    if ($v->slug === 'batumi' && preg_match('/batum|ბათუმ/u', $t)) return $v->id;
                    if ($v->slug === 'kutaisi' && preg_match('/kutais|ქუთაის/u', $t)) return $v->id;
                    if ($v->slug === 'magnolia' && preg_match('/magnolia|მაგნოლ/u', $t)) return $v->id;
                }
                return null;
            };
        @endphp
        @foreach ($groups as $provider => $title)
            <h3 style="font-size: 14px; margin: 18px 0 8px">{{ $title }}</h3>
            @if (empty($discovered[$provider]))
                <p class="rp-muted" style="margin: 0; font-size: 13px">ამ ანგარიშზე არაფერი მოიძებნა.</p>
            @else
                <div style="overflow-x: auto">
                    <table class="plain" style="min-width: 520px">
                        <tbody>
                        @foreach ($discovered[$provider] as $item)
                            @php($done = in_array($provider.'|'.$item['id'], $linked, true))
                            <tr>
                                <td><b>{{ $item['name'] }}</b><br><small class="rp-muted">{{ $item['group'] }}{{ $item['group'] ? ' · ' : '' }}{{ $item['id'] }}</small></td>
                                <td style="width: 52%">
                                    @if ($done)
                                        <span class="rp-status ok">დამატებულია</span>
                                    @else
                                        <form method="post" action="{{ route('reports.google.add') }}" style="display: flex; gap: 6px; justify-content: flex-end; flex-wrap: wrap">
                                            @csrf
                                            <input type="hidden" name="provider" value="{{ $provider }}">
                                            <input type="hidden" name="account_id" value="{{ $item['id'] }}">
                                            <input type="hidden" name="label" value="{{ \App\Reporting\Catalog::PROVIDERS[$provider]['label'] }} — {{ \Illuminate\Support\Str::limit($item['name'], 60) }}">
                                            @php($g = $guess($item['name'].' '.$item['group']))
                                            <select name="venue_id" class="btn sm" style="padding: 0 6px" aria-label="ბრენდი">
                                                @foreach ($venues as $v)<option value="{{ $v->id }}" @selected($g === $v->id)>{{ $v->name }}</option>@endforeach
                                            </select>
                                            <button class="btn sm primary" type="submit">დამატება</button>
                                        </form>
                                    @endif
                                </td>
                            </tr>
                        @endforeach
                        </tbody>
                    </table>
                </div>
            @endif
        @endforeach
        <p class="rp-muted" style="font-size: 12px; margin: 14px 0 0">Google Ads-ისთვის საჭიროა Developer token (Google Ads → API Center){{ $adsReady ? ' — მითითებულია.' : ' — ჯერ არ არის.' }}</p>
    @endif
</section>

<details class="panel" style="margin-bottom: 18px" {{ $venues->sum(fn ($v) => $v->dataSources->count()) === 0 ? 'open' : '' }}>
    <summary style="cursor: pointer; font-weight: 800; font-size: 16px">+ ახალი პლატფორმის დაკავშირება</summary>
    <form method="post" action="{{ route('reports.sources.store') }}" style="margin-top: 14px" data-source-form>
        @csrf
        <div class="form-grid">
            <label class="field">ბრენდი
                <select name="venue_id" required>
                    @foreach ($venues as $v)<option value="{{ $v->id }}" @selected(old('venue_id') == $v->id)>{{ $v->name }}</option>@endforeach
                </select>
            </label>
            <label class="field">პლატფორმა
                <select name="provider" required data-provider>
                    @foreach (Catalog::PROVIDERS as $key => $p)<option value="{{ $key }}" @selected(old('provider') === $key)>{{ $p['label'] }}</option>@endforeach
                </select>
            </label>
            <label class="field">სახელი (შენთვის)
                <input name="label" required maxlength="120" value="{{ old('label') }}" placeholder="მაგ. Instagram — @batumissakhlshi">
            </label>
        </div>
        @foreach (Catalog::PROVIDERS as $key => $p)
            <fieldset data-for="{{ $key }}" style="border: 0; padding: 0; margin: 12px 0 0" @disabled($key !== old('provider', array_key_first(Catalog::PROVIDERS)))>
                <div class="form-grid">
                    <label class="field">{{ $p['account'] }}
                        <input name="account_id" maxlength="255" value="{{ old('provider') === $key ? old('account_id') : '' }}">
                    </label>
                    @foreach ($p['fields'] as $field => $label)
                        <label class="field">{{ $label }}
                            <input type="password" name="credentials[{{ $field }}]" autocomplete="off" maxlength="4000">
                        </label>
                    @endforeach
                    @if (in_array('spend', $p['metrics']) || in_array('revenue', $p['metrics']))
                        <label class="field">ვალუტის კურსი ლარზე
                            <input type="number" name="currency_rate" step="0.000001" min="0.000001" value="{{ old('currency_rate', 1) }}">
                            <small class="rp-muted" style="font-weight: 400">თუ ანგარიში დოლარშია — მაგ. 2.70; ლარში — 1</small>
                        </label>
                    @endif
                </div>
            </fieldset>
        @endforeach
        <p class="rp-muted" style="font-size: 13px">ტოკენი არ გაქვს? შეგიძლია დაამატო ცარიელი და მონაცემები CSV-ით ატვირთო — რეპორტი მაინც აეწყობა.</p>
        <button class="btn primary" type="submit">დაკავშირება</button>
    </form>
</details>

@foreach ($venues as $venue)
    <h2 class="rp-venue-h"><span class="venue-badge" style="background: {{ $venue->color }}; height: 22px"></span>{{ $venue->name }}
        <small class="rp-muted" style="font-size: 13px; font-weight: 500">{{ $venue->dataSources->count() }} წყარო</small></h2>

    @if ($venue->dataSources->isEmpty())
        <p class="rp-muted">ჯერ არაფერია დაკავშირებული.</p>
    @else
        <div class="rp-sources">
            @foreach ($venue->dataSources as $s)
                @php($p = Catalog::PROVIDERS[$s->provider])
                <article class="rp-source" style="{{ $s->is_active ? '' : 'opacity: .6' }}">
                    <header>
                        <span class="rp-src"><i style="background: {{ $p['color'] }}"></i></span>
                        <b>{{ $s->label }}<br><small class="rp-muted" style="font-weight: 500">{{ $p['label'] }}{{ $s->account_id ? ' · '.$s->account_id : '' }}</small></b>
                        <span class="rp-status {{ $s->last_status }}">{{ ['ok' => 'OK', 'error' => 'შეცდომა', 'manual' => 'CSV / push', 'never' => 'ახალი'][$s->last_status] ?? $s->last_status }}</span>
                    </header>

                    <small class="rp-muted">ბოლო განახლება: {{ $s->last_synced_at?->timezone('Asia/Tbilisi')->format('d.m.Y H:i') ?? '—' }}
                        @if ((float) $s->currency_rate !== 1.0) · კურსი {{ rtrim(rtrim((string) $s->currency_rate, '0'), '.') }}@endif
                        @unless ($s->is_active) · გამორთულია@endunless</small>

                    @if ($s->last_error)<div class="err">{{ $s->last_error }}</div>@endif

                    @if ($s->isPush())
                        <div>
                            <small class="rp-muted">POS / ჯავშნის სისტემა აგზავნის POST JSON-ს ამ მისამართზე:</small>
                            <code>{{ route('reporting.ingest', $s->ingest_token) }}</code>
                            <small class="rp-muted">{"date":"2026-10-31","revenue":4520.5,"checks":61,"guests":140,"reservations":22,"new_customers":35,"marketing_revenue":900}</small>
                        </div>
                    @endif

                    <div class="actions">
                        @unless ($s->isPush())
                            <form method="post" action="{{ route('reports.sources.sync', $s) }}" style="display: flex; gap: 6px">
                                @csrf
                                <select name="days" aria-label="რამდენი დღე" class="btn sm" style="padding: 0 6px">
                                    <option value="7">7 დღე</option><option value="30" selected>30 დღე</option><option value="90">90 დღე</option><option value="365">1 წელი</option>
                                </select>
                                <button class="btn sm primary" type="submit">სინქრონიზაცია</button>
                            </form>
                        @endunless
                    </div>

                    <details>
                        <summary>CSV-ის ატვირთვა</summary>
                        <form method="post" action="{{ route('reports.sources.import', $s) }}" enctype="multipart/form-data" style="display: flex; flex-direction: column; gap: 8px; margin-top: 8px">
                            @csrf
                            <small class="rp-muted">პირველი სვეტი <b>date</b>, დანარჩენი — მეტრიკები: {{ implode(', ', $p['metrics']) }}@if ($p['channel'] === 'paid'), სურვილისამებრ <b>campaign</b>@endif.</small>
                            <code>date,{{ implode(',', array_slice($p['metrics'], 0, 3)) }}<br>2026-10-01,{{ implode(',', array_fill(0, min(3, count($p['metrics'])), '120')) }}</code>
                            <input type="file" name="file" accept=".csv,text/csv,.txt" required>
                            <button class="btn sm" type="submit">ატვირთვა</button>
                        </form>
                    </details>

                    <details>
                        <summary>რედაქტირება</summary>
                        <form method="post" action="{{ route('reports.sources.update', $s) }}" style="display: flex; flex-direction: column; gap: 10px; margin-top: 8px">
                            @csrf @method('put')
                            <label class="field">სახელი <input name="label" required maxlength="120" value="{{ $s->label }}"></label>
                            <label class="field">{{ $p['account'] }} <input name="account_id" maxlength="255" value="{{ $s->account_id }}"></label>
                            @foreach ($p['fields'] as $field => $label)
                                <label class="field">{{ $label }}
                                    <input type="password" name="credentials[{{ $field }}]" autocomplete="off" maxlength="4000" placeholder="{{ $s->credential($field) ? '•••••• შენახულია — ცარიელი = არ შეიცვლება' : '' }}">
                                </label>
                            @endforeach
                            <label class="field">ვალუტის კურსი ლარზე <input type="number" name="currency_rate" step="0.000001" min="0.000001" value="{{ (float) $s->currency_rate }}"></label>
                            <label style="display: flex; gap: 8px; align-items: center"><input type="hidden" name="is_active" value="0"><input type="checkbox" name="is_active" value="1" @checked($s->is_active)> აქტიურია (ღამის სინქრონიზაცია)</label>
                            <button class="btn sm primary" type="submit">შენახვა</button>
                        </form>
                        <form method="post" action="{{ route('reports.sources.destroy', $s) }}" style="margin-top: 10px" onsubmit="return confirm('წაიშალოს „{{ $s->label }}“ და მისი ყველა მონაცემი?')">
                            @csrf @method('delete')
                            <button class="btn sm danger" type="submit">წაშლა მონაცემებით</button>
                        </form>
                    </details>
                </article>
            @endforeach
        </div>
    @endif
@endforeach

<section class="panel" style="margin-top: 28px">
    <h2>სად ავიღო ტოკენი</h2>
    <dl class="kv" style="font-size: 13px">
        <dt>Facebook / Instagram</dt><dd>Meta Business Suite → Business settings → System users → Generate token (pages_read_engagement, read_insights, instagram_basic, instagram_manage_insights). Page ID — გვერდის About; Instagram ID — Graph API Explorer: <code style="display: inline">me/accounts?fields=instagram_business_account</code></dd>
        <dt>Meta Ads</dt><dd>იგივე System user, უფლება <b>ads_read</b>. Ad account ID — Ads Manager-ის URL-ში act= -ის შემდეგ.</dd>
        <dt>Google (4 სერვისი)</dt><dd>ერთი OAuth refresh token ყველასთვის (scope-ები: analytics.readonly, adwords, business.manage, webmasters.readonly). GA4 Property ID — Admin → Property details. Location ID — Business Profile-ის მისამართის ციფრები.</dd>
        <dt>TikTok</dt><dd>TikTok for Business → Marketing API → My apps → access token. Advertiser ID — Ads Manager-ის ზედა მარცხენა კუთხეში.</dd>
        <dt>POS</dt><dd>დაამატე „POS / გაყიდვები“ და ბარათზე გამოჩენილ მისამართს მიაწოდე შენს POS-ის მომწოდებელს — ან ყოველდღიური ექსპორტი ატვირთე CSV-ით.</dd>
    </dl>
</section>

@push('scripts')
<script>
    // show only the selected platform's fields; disabled fieldsets are not submitted
    document.querySelectorAll('[data-source-form]').forEach(function (form) {
        var select = form.querySelector('[data-provider]');
        function sync() {
            form.querySelectorAll('fieldset[data-for]').forEach(function (fs) {
                var on = fs.getAttribute('data-for') === select.value;
                fs.disabled = !on;
                fs.hidden = !on;
            });
        }
        select.addEventListener('change', sync);
        sync();
    });
</script>
@endpush
@endsection
