import CarritoDrawer, { IconoWhatsApp } from '@/Components/tienda/CarritoDrawer';
import Footer from '@/Components/tienda/Footer';
import Header from '@/Components/tienda/Header';
import { CarritoProvider } from '@/Contexts/CarritoContext';
import { SucursalProvider, useSucursal } from '@/Contexts/SucursalContext';
import { cn } from '@/lib/utils';
import { Head, usePage } from '@inertiajs/react';
import { LocateFixed, MapPin, X } from 'lucide-react';
import { useEffect, useRef, useState } from 'react';

function BotonWhatsApp() {
    const { sucursales, cercana, pedirUbicacion, buscando } = useSucursal();
    const [abierto, setAbierto] = useState(false);
    const ref = useRef(null);

    useEffect(() => {
        if (!abierto) return;
        const cerrar = (e) => ref.current && !ref.current.contains(e.target) && setAbierto(false);
        document.addEventListener('pointerdown', cerrar);
        return () => document.removeEventListener('pointerdown', cerrar);
    }, [abierto]);

    return (
        <div ref={ref} className="pointer-events-none fixed bottom-5 right-5 z-30 flex flex-col items-end gap-3">
            {/* El contenedor no recibe toques: el menú cerrado (invisible) tapaba la paginación y otros botones en celular */}
            <div className={cn('w-64 origin-bottom-right rounded-2xl bg-white p-2  ring-1 ring-black/5 transition-all duration-300',
                abierto ? 'pointer-events-auto scale-100 opacity-100' : 'scale-95 opacity-0')}>
                <p className="px-3 pb-1 pt-2 text-sm font-bold">Escríbenos a la tienda</p>
                {/* La más cercana al cliente va primero */}
                {[...sucursales].sort((a, b) => (b.nombre === cercana?.nombre) - (a.nombre === cercana?.nombre)).map((s) => (
                    <a key={s.nombre}
                        href={`https://wa.me/${s.telefono}?text=${encodeURIComponent(`¡Hola Tenisline ${s.nombre}! Quiero información sobre unos tenis.`)}`}
                        target="_blank" rel="noopener noreferrer"
                        className={cn('flex items-center justify-between rounded-xl px-3 py-2.5 text-sm font-medium transition-colors hover:bg-neutral-100',
                            cercana?.nombre === s.nombre && 'bg-[#25D366]/10')}>
                        <span>
                            {s.nombre}
                            {cercana?.nombre === s.nombre && (
                                <span className="flex items-center gap-1 text-xs font-semibold text-[#128C7E]">
                                    <MapPin className="h-3 w-3" /> Más cercana · {Math.round(cercana.km)} km
                                </span>
                            )}
                        </span>
                        <IconoWhatsApp className="h-4 w-4 text-[#25D366]" />
                    </a>
                ))}
                {!cercana && (
                    <button onClick={pedirUbicacion} disabled={buscando}
                        className="mt-1 flex w-full items-center gap-2 rounded-xl px-3 py-2 text-xs font-semibold text-neutral-500 transition-colors hover:bg-neutral-100 hover:text-ink disabled:opacity-60">
                        <LocateFixed className="h-4 w-4" /> {buscando ? 'Buscando tu ubicación…' : 'Usar mi ubicación para ver la más cercana'}
                    </button>
                )}
            </div>
            <button onClick={() => setAbierto((v) => !v)} aria-label="Escribir por WhatsApp"
                className="pointer-events-auto flex h-14 w-14 items-center justify-center rounded-full bg-[#25D366] text-white transition-transform duration-500 hover:scale-110 active:scale-95">
                {abierto ? <X className="h-6 w-6" /> : <IconoWhatsApp className="h-7 w-7" />}
            </button>
        </div>
    );
}

// Layout del sitio público de Tenisline (header, carrito, footer y botón de WhatsApp).
export default function Layout({ children }) {
    const seo = usePage().props.seo;

    return (
        <SucursalProvider>
        <CarritoProvider>
            {seo?.title && <Head title={seo.title} />}
            <div className="flex min-h-screen flex-col bg-white">
                <Header />
                <main className="flex-1">{children}</main>
                <Footer />
            </div>
            <CarritoDrawer />
            <BotonWhatsApp />
        </CarritoProvider>
        </SucursalProvider>
    );
}
