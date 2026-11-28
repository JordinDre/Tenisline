<x-dynamic-component :component="$getFieldWrapperView()" :field="$field">
    {{-- Cámara en vivo: la foto solo puede tomarse en este momento (no hay selector de archivos ni galería). --}}
    <div
        x-data="{
            state: $wire.$entangle('{{ $getStatePath() }}'),
            stream: null,
            error: null,
            async iniciar() {
                this.error = null;
                if (! navigator.mediaDevices || ! navigator.mediaDevices.getUserMedia) {
                    this.error = 'Este navegador no permite usar la cámara. Usa Chrome o Safari actualizado.';
                    return;
                }
                try {
                    this.stream = await navigator.mediaDevices.getUserMedia({
                        video: { facingMode: { ideal: 'environment' }, width: { ideal: 1600 }, height: { ideal: 1600 } },
                        audio: false,
                    });
                    this.$refs.video.srcObject = this.stream;
                    await this.$refs.video.play();
                } catch (e) {
                    this.error = 'No se pudo abrir la cámara. Permite el acceso a la cámara e inténtalo de nuevo.';
                }
            },
            detener() {
                if (this.stream) {
                    this.stream.getTracks().forEach((t) => t.stop());
                    this.stream = null;
                }
            },
            tomar() {
                const video = this.$refs.video;
                if (! video.videoWidth) return;
                const escala = Math.min(1, 1600 / Math.max(video.videoWidth, video.videoHeight));
                const canvas = document.createElement('canvas');
                canvas.width = Math.round(video.videoWidth * escala);
                canvas.height = Math.round(video.videoHeight * escala);
                canvas.getContext('2d').drawImage(video, 0, 0, canvas.width, canvas.height);
                this.state = canvas.toDataURL('image/jpeg', 0.85);
                this.detener();
            },
            repetir() {
                this.state = null;
                this.iniciar();
            },
        }"
        x-init="iniciar()"
        x-on:close-modal.window="detener()"
        x-on:remove="detener()"
        class="space-y-3"
    >
        <template x-if="error">
            <p class="text-sm text-danger-600 dark:text-danger-400" x-text="error"></p>
        </template>

        <div x-show="! state" class="space-y-3">
            <video x-ref="video" playsinline muted autoplay class="w-full max-h-96 rounded-lg bg-black"></video>
            <div class="flex flex-wrap gap-2">
                <x-filament::button type="button" icon="heroicon-o-camera" x-on:click="tomar()" x-bind:disabled="! stream">
                    Tomar foto
                </x-filament::button>
                <x-filament::button type="button" color="gray" x-show="! stream" x-on:click="iniciar()">
                    Abrir cámara
                </x-filament::button>
            </div>
        </div>

        <div x-show="state" x-cloak class="space-y-3">
            <img x-bind:src="state" alt="Foto tomada" class="w-full max-h-96 rounded-lg object-contain">
            <x-filament::button type="button" color="gray" icon="heroicon-o-arrow-path" x-on:click="repetir()">
                Tomar otra
            </x-filament::button>
        </div>
    </div>
</x-dynamic-component>
