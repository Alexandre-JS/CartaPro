@props(['paginator', 'preserveQuery' => true])
@if($paginator->hasPages())
    <div {{ $attributes->class('pagination') }} aria-label="Paginação">
        {{ ($preserveQuery ? $paginator->withQueryString() : $paginator)->onEachSide(1)->links() }}
    </div>
@endif
