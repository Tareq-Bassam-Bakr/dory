{{-- A bar chart drawn on the server (no script). $series: label => value;
     $labels: label => text shown above the bar (optional); $title: for
     screen readers. A table with the same numbers follows for them. --}}
@php
    $values = array_values($series);
    $keys = array_keys($series);
    $count = max(1, count($values));
    $width = 640;
    $height = 200;
    $top = 18;
    $bottom = 26;
    $plot = $height - $top - $bottom;
    $max = max(1, max($values ?: [0]));
    $slot = $width / $count;
    $barWidth = min(56, $slot * 0.62);
@endphp
<svg class="chart" viewBox="0 0 {{ $width }} {{ $height }}" role="img" aria-label="{{ $title }}">
    @foreach ([0, 0.5, 1] as $step)
        @php $y = $top + $plot - $plot * $step; @endphp
        <line class="grid-line" x1="0" x2="{{ $width }}" y1="{{ $y }}" y2="{{ $y }}"/>
    @endforeach
    @foreach ($values as $i => $value)
        @php
            $barHeight = $value > 0 ? max(2, $plot * $value / $max) : 0;
            $x = $slot * $i + ($slot - $barWidth) / 2;
            $y = $top + $plot - $barHeight;
            $key = $keys[$i];
        @endphp
        <rect class="{{ $barClass ?? 'bar' }}" x="{{ round($x, 2) }}" y="{{ round($y, 2) }}" width="{{ round($barWidth, 2) }}" height="{{ round($barHeight, 2) }}" rx="4">
            <title>{{ ($axis[$key] ?? $key).': '.($labels[$key] ?? $value) }}</title>
        </rect>
        @if ($value > 0)
            <text class="value-label" x="{{ round($x + $barWidth / 2, 2) }}" y="{{ round($y - 5, 2) }}" text-anchor="middle">{{ $labels[$key] ?? $value }}</text>
        @endif
        <text class="axis" x="{{ round($x + $barWidth / 2, 2) }}" y="{{ $height - 8 }}" text-anchor="middle">{{ $axis[$key] ?? $key }}</text>
    @endforeach
</svg>
<table class="visually-hidden">
    <caption>{{ $title }}</caption>
    @foreach ($series as $key => $value)
        <tr><th scope="row">{{ $axis[$key] ?? $key }}</th><td>{{ $labels[$key] ?? $value }}</td></tr>
    @endforeach
</table>
