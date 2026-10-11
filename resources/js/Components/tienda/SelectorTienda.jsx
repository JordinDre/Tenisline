import { Button } from '@/Components/ui/button';
import {
    DropdownMenu,
    DropdownMenuContent,
    DropdownMenuLabel,
    DropdownMenuRadioGroup,
    DropdownMenuRadioItem,
    DropdownMenuSeparator,
    DropdownMenuTrigger,
} from '@/Components/ui/dropdown-menu';
import { useSucursal } from '@/Contexts/SucursalContext';
import { cn } from '@/lib/utils';
import { MapPin } from 'lucide-react';

const pastilla = (activa) =>
    cn('inline-flex h-9 items-center rounded-full border px-4 text-sm font-medium transition-all duration-300 active:scale-95',
        activa ? 'border-ink bg-ink text-white ' : 'border-neutral-200 bg-white hover:border-neutral-400');

/** Pastillas "Todas / Zacapa / Chiquimula / Esquipulas" del filtro global de tienda. */
export function PastillasTienda({ className }) {
    const { sucursales, seleccionada, cercana, elegir } = useSucursal();

    return (
        <div className={cn('flex flex-wrap gap-2', className)}>
            <button onClick={() => elegir(null)} className={pastilla(!seleccionada)}>Todas</button>
            {sucursales.map((s) => (
                <button key={s.nombre} onClick={() => elegir(s.nombre)} className={pastilla(seleccionada?.nombre === s.nombre)}>
                    {s.nombre}
                    {cercana?.nombre === s.nombre && <MapPin className="ml-1 h-3.5 w-3.5" aria-label="Más cercana" />}
                </button>
            ))}
        </div>
    );
}

/** Botón del header para elegir la tienda en todo el sitio. */
export default function SelectorTienda() {
    const { sucursales, seleccionada, cercana, elegir } = useSucursal();

    return (
        <DropdownMenu>
            <DropdownMenuTrigger asChild>
                <Button variant="ghost" className={cn('h-10 gap-1.5 rounded-full px-2.5 text-sm font-semibold', seleccionada && 'bg-brand-light text-brand-dark')}
                    aria-label="Elegir tienda">
                    <MapPin className="h-5 w-5" strokeWidth={1.9} />
                    <span className={cn(seleccionada ? 'max-w-[5.5rem] truncate sm:max-w-none' : 'hidden xl:inline')}>
                        {seleccionada ? seleccionada.nombre : 'Todas las tiendas'}
                    </span>
                </Button>
            </DropdownMenuTrigger>
            <DropdownMenuContent align="end" className="w-60 rounded-xl p-1.5">
                <DropdownMenuLabel className="text-xs font-bold uppercase tracking-[0.14em] text-neutral-500">Ver productos de</DropdownMenuLabel>
                <DropdownMenuSeparator />
                <DropdownMenuRadioGroup value={seleccionada?.nombre ?? 'todas'} onValueChange={(v) => elegir(v === 'todas' ? null : v)}>
                    <DropdownMenuRadioItem value="todas" className="rounded-lg py-2">Todas las tiendas</DropdownMenuRadioItem>
                    {sucursales.map((s) => (
                        <DropdownMenuRadioItem key={s.nombre} value={s.nombre} className="rounded-lg py-2">
                            <span className="flex-1">{s.nombre}</span>
                            {cercana?.nombre === s.nombre && <span className="text-xs font-semibold text-brand">Más cercana</span>}
                        </DropdownMenuRadioItem>
                    ))}
                </DropdownMenuRadioGroup>
            </DropdownMenuContent>
        </DropdownMenu>
    );
}
