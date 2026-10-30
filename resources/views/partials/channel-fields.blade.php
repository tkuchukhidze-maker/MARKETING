@use('App\Support\Studio')
{{-- $selected: platform => format of the platforms already chosen --}}
@php($selected = old('platforms') !== null
    ? collect(old('platforms'))->mapWithKeys(fn ($p) => [$p => old('formats.'.$p)])->all()
    : $selected)
<fieldset class="channels" data-channels>
    <legend>სად გამოქვეყნდება? <small>აირჩიე ერთი, რამდენიმე ან ყველა — და ფორმატი თითოეულისთვის</small></legend>

    <label class="channel-all">
        <input type="checkbox" data-all @checked(count($selected) === count(Studio::PLATFORMS))>
        <b>ყველა სოციალური ქსელი</b>
    </label>

    @foreach (Studio::PLATFORMS as $key => [$label, $bg, $fg])
        @php($on = array_key_exists($key, $selected))
        <div class="channel-row {{ $on ? 'is-on' : '' }}" style="--pf: {{ $bg }}">
            <label class="channel-pick">
                <input type="checkbox" name="platforms[]" value="{{ $key }}" data-platform @checked($on)>
                <span class="pill" style="background: {{ $bg }}; color: {{ $fg }}">{{ $label }}</span>
            </label>
            <label class="sr-only" for="fmt-{{ $key }}-{{ $uid ?? 'x' }}">{{ $label }} — ფორმატი</label>
            <select id="fmt-{{ $key }}-{{ $uid ?? 'x' }}" name="formats[{{ $key }}]" data-format @disabled(! $on)>
                <option value="">ფორმატი…</option>
                @foreach (Studio::FORMATS[$key] as $f)
                    <option value="{{ $f }}" @selected(($selected[$key] ?? null) === $f)>{{ $f }}</option>
                @endforeach
                @if (($selected[$key] ?? null) && ! in_array($selected[$key], Studio::FORMATS[$key], true))
                    <option value="{{ $selected[$key] }}" selected>{{ $selected[$key] }}</option>
                @endif
            </select>
        </div>
    @endforeach
</fieldset>
