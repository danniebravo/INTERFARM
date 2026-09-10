@if ($paginator->hasPages())
    <nav role="navigation" aria-label="Paginación" class="flex flex-col gap-3 sm:flex-row sm:items-center sm:justify-between">
        <div class="text-sm font-semibold text-gray-500">
            Mostrando
            <span class="font-black text-gray-700">{{ $paginator->firstItem() }}</span>
            a
            <span class="font-black text-gray-700">{{ $paginator->lastItem() }}</span>
            de
            <span class="font-black text-gray-700">{{ $paginator->total() }}</span>
            resultados
        </div>

        <div class="flex flex-wrap items-center gap-2">
            @if ($paginator->onFirstPage())
                <span class="rounded-xl border border-gray-200 bg-gray-100 px-4 py-2 text-sm font-bold text-gray-400">Anterior</span>
            @else
                <a href="{{ $paginator->previousPageUrl() }}" rel="prev" class="rounded-xl border border-gray-200 bg-white px-4 py-2 text-sm font-bold text-gray-700 transition hover:bg-gray-50">Anterior</a>
            @endif

            @foreach ($elements as $element)
                @if (is_string($element))
                    <span class="px-2 py-2 text-sm font-bold text-gray-400">{{ $element }}</span>
                @endif

                @if (is_array($element))
                    @foreach ($element as $page => $url)
                        @if ($page == $paginator->currentPage())
                            <span aria-current="page" class="rounded-xl bg-green-700 px-4 py-2 text-sm font-black text-white">{{ $page }}</span>
                        @else
                            <a href="{{ $url }}" class="rounded-xl border border-gray-200 bg-white px-4 py-2 text-sm font-bold text-gray-700 transition hover:bg-gray-50">{{ $page }}</a>
                        @endif
                    @endforeach
                @endif
            @endforeach

            @if ($paginator->hasMorePages())
                <a href="{{ $paginator->nextPageUrl() }}" rel="next" class="rounded-xl border border-gray-200 bg-white px-4 py-2 text-sm font-bold text-gray-700 transition hover:bg-gray-50">Siguiente</a>
            @else
                <span class="rounded-xl border border-gray-200 bg-gray-100 px-4 py-2 text-sm font-bold text-gray-400">Siguiente</span>
            @endif
        </div>
    </nav>
@endif
