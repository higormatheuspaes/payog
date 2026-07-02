@props(['paginator' => null])

<div class="flex flex-col h-full">

    @isset($header)
        <div class="flex-shrink-0 mb-4">{{ $header }}</div>
    @endisset

    @isset($filters)
        <div class="flex-shrink-0 mb-4" x-data="{ open: false }">
            {{-- Botão toggle — só aparece no mobile --}}
            <button type="button" @click="open = !open"
                class="sm:hidden w-full flex items-center justify-between px-4 py-2.5 bg-white border border-gray-200 rounded-xl text-sm text-gray-700 font-medium shadow-sm">
                <span class="flex items-center gap-2">
                    <svg class="w-4 h-4 text-gray-400" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                        <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2"
                            d="M3 4a1 1 0 011-1h16a1 1 0 011 1v2a1 1 0 01-.293.707L13 13.414V19a1 1 0 01-.553.894l-4 2A1 1 0 017 21v-7.586L3.293 6.707A1 1 0 013 6V4z"/>
                    </svg>
                    Filtros
                </span>
                <svg class="w-4 h-4 text-gray-400 transition-transform duration-200" :class="open ? 'rotate-180' : ''" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                    <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M19 9l-7 7-7-7"/>
                </svg>
            </button>

            {{-- Painel de filtros: sempre visível no sm+, colapsável no mobile --}}
            <div x-show="open" x-transition:enter="transition ease-out duration-150"
                x-transition:enter-start="opacity-0 -translate-y-1" x-transition:enter-end="opacity-100 translate-y-0"
                x-transition:leave="transition ease-in duration-100"
                x-transition:leave-start="opacity-100 translate-y-0" x-transition:leave-end="opacity-0 -translate-y-1"
                class="mt-2 sm:hidden">
                {{ $filters }}
            </div>

            <div class="hidden sm:block">
                {{ $filters }}
            </div>
        </div>
    @endisset

    <div class="bg-white rounded-xl border border-gray-200 overflow-hidden flex flex-col flex-1 min-h-0">
        <div class="flex-1 overflow-auto">
            {{ $slot }}
        </div>

        @if($paginator?->hasPages())
            <div class="px-4 py-3 border-t border-gray-100 flex-shrink-0">
                {{ $paginator->links() }}
            </div>
        @endif
    </div>

    @isset($modal)
        {{ $modal }}
    @endisset

</div>
