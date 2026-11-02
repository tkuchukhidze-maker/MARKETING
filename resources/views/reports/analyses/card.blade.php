@php($pct = $a->actions_count ? round($a->done_count / $a->actions_count * 100) : null)
<a class="rp-analysis" href="{{ route('reports.analyses.show', $a) }}">
    <span class="rp-analysis-top">
        @if ($a->venue)
            <span class="rp-src"><i style="background: {{ $a->venue->color }}"></i>{{ $a->venue->name }}</span>
        @else
            <span class="rp-src"><i style="background: #323338"></i>ყველა ბრენდი</span>
        @endif
        <small class="rp-muted">{{ $a->created_at->timezone('Asia/Tbilisi')->format('d.m.Y') }}</small>
    </span>
    <b>{{ $a->title }}</b>
    @if ($a->summary)<span class="rp-analysis-sum">{{ \Illuminate\Support\Str::limit($a->summary, 170) }}</span>@endif
    @if ($pct !== null)
        <span class="rp-analysis-progress">
            <span class="rp-bar" style="flex: 1"><span style="width: {{ $pct }}%; background: {{ $pct == 100 ? '#00854D' : '#1F76C2' }}"></span></span>
            <small>{{ $a->done_count }}/{{ $a->actions_count }} შესრულდა</small>
        </span>
    @endif
</a>
