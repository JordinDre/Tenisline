<div class="flex h-screen w-full flex-col">
    <div class="mb-4 flex flex-wrap items-center justify-between gap-3">
        <h2 class="text-base font-semibold text-gray-950 dark:text-white">{{ $title }}</h2>

        @if($open)
            {{-- Mismo botón que el resto del sistema (mayúsculas, color primario) --}}
            <x-filament::button
                tag="a"
                :href="$route"
                target="_blank"
                size="sm"
                icon="heroicon-m-arrow-top-right-on-square"
            >
                Abrir en nueva pestaña
            </x-filament::button>
        @endif
    </div>

    <iframe src="{{ $route }}" class="w-full rounded-lg ring-1 ring-gray-950/10 dark:ring-white/10" style="height: calc(100% - 60px);" frameborder="0"></iframe>
</div>
