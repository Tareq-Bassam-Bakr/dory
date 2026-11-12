@if ($paginator->total() > 0)
    <nav class="pagination" aria-label="{{ __('admin.common.pages') }}">
        <span>{{ __('admin.common.showing', ['from' => $paginator->firstItem(), 'to' => $paginator->lastItem(), 'total' => $paginator->total()]) }}</span>
        @if ($paginator->hasPages())
            <span class="pages">
                @if ($paginator->onFirstPage())
                    <span class="btn btn-ghost btn-small" aria-disabled="true">{{ __('admin.common.previous') }}</span>
                @else
                    <a class="btn btn-secondary btn-small" href="{{ $paginator->previousPageUrl() }}" rel="prev">{{ __('admin.common.previous') }}</a>
                @endif
                @if ($paginator->hasMorePages())
                    <a class="btn btn-secondary btn-small" href="{{ $paginator->nextPageUrl() }}" rel="next">{{ __('admin.common.next') }}</a>
                @else
                    <span class="btn btn-ghost btn-small" aria-disabled="true">{{ __('admin.common.next') }}</span>
                @endif
            </span>
        @endif
    </nav>
@endif
