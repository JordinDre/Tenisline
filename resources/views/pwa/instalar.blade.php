{{--
    Sugerencia de instalar la app en el celular (paneles de Filament).
    - Android/Chrome: usa el aviso nativo (beforeinstallprompt, guardado en window.__pwaInstalar por pwa.meta).
    - iPhone/iPad (Safari): no hay aviso nativo; se explica "Compartir → Agregar a inicio".
    - Si el usuario lo cierra, no se vuelve a mostrar en 14 días. No aparece si ya está instalada.
--}}
<div
    x-data="{
        visible: false,
        ios: false,
        clave: 'tenisline-pwa-instalar-oculto',
        init() {
            const instalada = window.matchMedia('(display-mode: standalone)').matches || window.navigator.standalone === true;
            if (instalada || window.innerWidth >= 768) return;
            let oculto = 0;
            try { oculto = parseInt(localStorage.getItem(this.clave) || '0', 10); } catch (e) {}
            if (Date.now() - oculto < 14 * 24 * 60 * 60 * 1000) return;
            this.ios = /iphone|ipad|ipod/i.test(navigator.userAgent) && ! /crios|fxios/i.test(navigator.userAgent);
            if (this.ios || window.__pwaInstalar) { this.visible = true; return; }
            window.addEventListener('pwa-instalable', () => { this.visible = true; }, { once: true });
        },
        async instalar() {
            const aviso = window.__pwaInstalar;
            if (! aviso) return;
            aviso.prompt();
            await aviso.userChoice;
            window.__pwaInstalar = null;
            this.visible = false;
        },
        cerrar() {
            try { localStorage.setItem(this.clave, String(Date.now())); } catch (e) {}
            this.visible = false;
        },
    }"
    x-show="visible"
    x-cloak
    x-transition.opacity
    class="fixed inset-x-3 bottom-3 z-40 flex items-center gap-3 rounded-lg bg-white p-3 text-sm ring-1 ring-gray-950/10 dark:bg-gray-900 dark:ring-white/10 md:hidden"
    role="dialog"
    aria-label="Instalar la app"
>
    <img src="{{ asset('images/icons/icon-96x96.png') }}" alt="" class="h-10 w-10 shrink-0 rounded-lg" width="40" height="40">

    <div class="min-w-0 flex-1">
        <p class="font-semibold text-gray-950 dark:text-white">Instala Tenisline</p>
        <p class="text-xs text-gray-500 dark:text-gray-400" x-show="! ios">Ábrela como una app desde tu pantalla de inicio.</p>
        <p class="text-xs text-gray-500 dark:text-gray-400" x-show="ios">Toca <strong>Compartir</strong> y luego <strong>Agregar a inicio</strong>.</p>
    </div>

    <x-filament::button size="sm" x-show="! ios" x-on:click="instalar()">Instalar</x-filament::button>

    <button type="button" x-on:click="cerrar()" class="shrink-0 rounded-md p-1 text-gray-400 hover:text-gray-600 dark:hover:text-gray-200" aria-label="Cerrar">
        <x-filament::icon icon="heroicon-m-x-mark" class="h-5 w-5" />
    </button>
</div>
