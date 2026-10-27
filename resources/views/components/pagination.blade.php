@if ($paginator->hasPages())
    <nav role="navigation" aria-label="Navigasi halaman" class="flex flex-wrap items-center justify-center gap-2">
        @php
            $btn = 'flex size-10 items-center justify-center rounded-lg bg-white text-sm shadow-card focus-visible:outline-2 focus-visible:outline-blue-500';
        @endphp

        @if ($paginator->onFirstPage())
            <span class="{{ $btn }} cursor-default text-gray-300" aria-disabled="true"><x-icon name="chevron-left" size="size-4" /></span>
        @else
            <a href="{{ $paginator->previousPageUrl() }}" rel="prev" aria-label="Halaman sebelumnya" class="{{ $btn }} text-gray-800 hover:bg-blue-50"><x-icon name="chevron-left" size="size-4" /></a>
        @endif

        @foreach ($elements as $element)
            @if (is_string($element))
                <span class="{{ $btn }} cursor-default text-gray-500" aria-hidden="true"><x-icon name="dots" size="size-4" /></span>
            @endif

            @if (is_array($element))
                @foreach ($element as $page => $url)
                    @if ($page == $paginator->currentPage())
                        <span class="{{ $btn }} border border-blue-500 font-medium text-blue-700" aria-current="page">{{ $page }}</span>
                    @else
                        <a href="{{ $url }}" aria-label="Ke halaman {{ $page }}" class="{{ $btn }} text-gray-800 hover:bg-blue-50">{{ $page }}</a>
                    @endif
                @endforeach
            @endif
        @endforeach

        @if ($paginator->hasMorePages())
            <a href="{{ $paginator->nextPageUrl() }}" rel="next" aria-label="Halaman berikutnya" class="{{ $btn }} text-gray-800 hover:bg-blue-50"><x-icon name="chevron-right" size="size-4" /></a>
        @else
            <span class="{{ $btn }} cursor-default text-gray-300" aria-disabled="true"><x-icon name="chevron-right" size="size-4" /></span>
        @endif
    </nav>
@endif
