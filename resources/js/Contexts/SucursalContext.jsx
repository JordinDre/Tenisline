import { router, usePage } from '@inertiajs/react';
import { createContext, useCallback, useContext, useEffect, useState } from 'react';

// Tienda del cliente en el sitio público:
// - "seleccionada": filtro global (Todas / Zacapa / Chiquimula / Esquipulas), guardado en cookie para que el servidor filtre.
// - "cercana": sucursal más cercana según la ubicación del navegador, para escribir al WhatsApp correcto.
const SucursalContext = createContext(null);
const CLAVE = 'tenisline_ubicacion_v1';
const COOKIE = 'tienda_sucursal';

function leer() {
    try {
        return JSON.parse(window.localStorage.getItem(CLAVE)) || {};
    } catch {
        return {};
    }
}

function guardar(datos) {
    try {
        window.localStorage.setItem(CLAVE, JSON.stringify(datos));
    } catch {
        // Sin almacenamiento disponible: se vuelve a preguntar en la próxima visita
    }
}

/** Cambia el filtro global de tienda (null = todas) y recarga los productos. */
export function elegirTienda(nombre) {
    const valor = nombre ? encodeURIComponent(nombre.toLowerCase()) : '';
    document.cookie = `${COOKIE}=${valor}; path=/; max-age=${nombre ? 60 * 60 * 24 * 365 : 0}; SameSite=Lax`;
    router.reload({ preserveScroll: true });
}

/** Distancia en km entre dos puntos (fórmula de haversine). */
function distancia(a, b) {
    const rad = (g) => (g * Math.PI) / 180;
    const dLat = rad(b.lat - a.lat);
    const dLng = rad(b.lng - a.lng);
    const h = Math.sin(dLat / 2) ** 2 + Math.cos(rad(a.lat)) * Math.cos(rad(b.lat)) * Math.sin(dLng / 2) ** 2;
    return 6371 * 2 * Math.asin(Math.sqrt(h));
}

export function SucursalProvider({ children }) {
    const { tienda } = usePage().props;
    const sucursales = tienda?.sucursales ?? [];
    const seleccionada = sucursales.find((s) => s.nombre.toLowerCase() === tienda?.seleccionada) ?? null;
    const [ubicacion, setUbicacion] = useState(null);
    const [buscando, setBuscando] = useState(false);

    const pedirUbicacion = useCallback(() => {
        if (typeof navigator === 'undefined' || !navigator.geolocation) return;
        setBuscando(true);
        navigator.geolocation.getCurrentPosition(
            ({ coords }) => {
                const datos = { lat: coords.latitude, lng: coords.longitude, preguntado: true };
                guardar(datos);
                setUbicacion(datos);
                setBuscando(false);
            },
            () => {
                guardar({ preguntado: true });
                setBuscando(false);
            },
            { enableHighAccuracy: false, timeout: 15000, maximumAge: 30 * 60 * 1000 },
        );
    }, []);

    // Se pide la ubicación una sola vez (en la primera visita); si ya respondió, se usa lo guardado
    useEffect(() => {
        const guardado = leer();
        if (guardado.lat) setUbicacion(guardado);
        if (guardado.preguntado) return;
        const t = setTimeout(pedirUbicacion, 1500);
        return () => clearTimeout(t);
    }, [pedirUbicacion]);

    const cercana = ubicacion?.lat
        ? sucursales
            .filter((s) => s.lat && s.lng)
            .map((s) => ({ ...s, km: distancia(ubicacion, s) }))
            .sort((a, b) => a.km - b.km)[0] ?? null
        : null;

    // Tienda a la que se escribe por defecto: la elegida, si no la más cercana, si no la primera
    const preferida = seleccionada ?? (cercana && sucursales.find((s) => s.nombre === cercana.nombre)) ?? sucursales[0] ?? null;

    return (
        <SucursalContext.Provider value={{ sucursales, seleccionada, cercana, preferida, elegir: elegirTienda, pedirUbicacion, buscando }}>
            {children}
        </SucursalContext.Provider>
    );
}

export function useSucursal() {
    return useContext(SucursalContext);
}
