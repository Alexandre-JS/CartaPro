@props(['paginator', 'preserveQuery' => true])
@php
    if ($preserveQuery) $paginator->withQueryString();
    $current = $paginator->currentPage();
    $last = $paginator->lastPage();
    $start = max(1, $current - 2);
    $end = min($last, $current + 2);
@endphp
    <div {{ $attributes->class('pv-table-pagination') }} aria-label="Paginação">
        <p>A mostrar <strong>{{ $paginator->firstItem() ?? 0 }}–{{ $paginator->lastItem() ?? 0 }}</strong> de <strong>{{ number_format($paginator->total(), 0, ',', ' ') }}</strong></p>
        @if($paginator->hasPages())<nav aria-label="Paginação da tabela">
            @if($paginator->onFirstPage())<span class="is-disabled" aria-hidden="true"><i class="bi bi-chevron-left"></i></span>@else<a href="{{ $paginator->previousPageUrl() }}" rel="prev" aria-label="Página anterior"><i class="bi bi-chevron-left" aria-hidden="true"></i></a>@endif
            @if($start > 1)<a href="{{ $paginator->url(1) }}">1</a>@if($start > 2)<span class="pv-pagination-gap" aria-hidden="true">…</span>@endif @endif
            @for($page = $start; $page <= $end; $page++)
                @if($page === $current)<span class="is-current" aria-current="page">{{ $page }}</span>@else<a href="{{ $paginator->url($page) }}">{{ $page }}</a>@endif
            @endfor
            @if($end < $last)@if($end < $last - 1)<span class="pv-pagination-gap" aria-hidden="true">…</span>@endif<a href="{{ $paginator->url($last) }}">{{ $last }}</a>@endif
            @if($paginator->hasMorePages())<a href="{{ $paginator->nextPageUrl() }}" rel="next" aria-label="Página seguinte"><i class="bi bi-chevron-right" aria-hidden="true"></i></a>@else<span class="is-disabled" aria-hidden="true"><i class="bi bi-chevron-right"></i></span>@endif
        </nav>@endif
    </div>
