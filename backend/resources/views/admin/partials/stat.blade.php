<div class="card stat {{ $tone ?? '' }}">
    <span class="icon">@include('admin.partials.icon', ['name' => $icon])</span>
    <div>
        <div class="label">
            @if (! empty($href))
                <a href="{{ $href }}">{{ $label }}</a>
            @else
                {{ $label }}
            @endif
        </div>
        <div class="value">{{ $value }}</div>
        @if (! empty($hint))
            <div class="hint">{{ $hint }}</div>
        @endif
    </div>
</div>
