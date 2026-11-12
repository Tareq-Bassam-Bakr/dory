{{-- One bar split by share. $parts: list of [label, value, swatch]. --}}
@php
    $total = max(1, array_sum(array_column($parts, 1)));
    $offset = 0;
@endphp
<svg class="chart" viewBox="0 0 640 28" role="img" aria-label="{{ $title }}">
    <rect class="swatch-neutral" x="0" y="0" width="640" height="28" rx="8"/>
    @foreach ($parts as [$label, $value, $swatch])
        @if ($value > 0)
            @php $w = 640 * $value / $total; @endphp
            <rect class="{{ $swatch }}" x="{{ round($offset, 2) }}" y="0" width="{{ round($w, 2) }}" height="28"><title>{{ $label }}: {{ $value }}</title></rect>
            @php $offset += $w; @endphp
        @endif
    @endforeach
</svg>
<div class="legend">
    @foreach ($parts as [$label, $value, $swatch])
        <span><i class="{{ $swatch }}"></i>{{ $label }}: <strong>{{ $value }}</strong></span>
    @endforeach
</div>
