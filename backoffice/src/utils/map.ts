import type { LatLngBoundsExpression, Map, MapOptions, LatLngBoundsLiteral } from 'leaflet'

// Limite geografico a Nicaragua (con margen). Evita que el usuario se salga del
// pais con pan/zoom: con maxBoundsViscosity = 1 el borde es un freno duro.
export const NICARAGUA_BOUNDS: LatLngBoundsExpression = [[10.6, -88.0], [15.1, -82.5]]

export const NICARAGUA_MAP_OPTIONS: Pick<MapOptions, 'maxBounds' | 'maxBoundsViscosity' | 'minZoom'> = {
  maxBounds: NICARAGUA_BOUNDS,
  maxBoundsViscosity: 1.0,
  minZoom: 6,
}

/**
 * Estabiliza el tamaño de un mapa Leaflet. Leaflet mide el contenedor en el
 * momento de `L.map(...)`: si se inicializa durante un layout interpolado
 * (transición del sidebar) o cuando el contenedor aún no tiene dimensiones,
 * el mapa queda "pegado" a un ancho/alto obsoleto y se ve en blanco o fuera
 * del cuadro. Esto re-mide de forma forzada (inmediato + rAF + 300ms) y se
 * suscribe a redimensiones futuras del contenedor con ResizeObserver.
 *
 * Devuelve un cleanup para liberar el observer y el timeout.
 */
export function stabilizeMapSize(map: Map, container: HTMLElement): () => void {
  const invalidate = () => map.invalidateSize()

  invalidate()
  requestAnimationFrame(invalidate)
  const timer = window.setTimeout(invalidate, 300)

  const resizeObserver = new ResizeObserver(invalidate)
  resizeObserver.observe(container)

  return () => {
    window.clearTimeout(timer)
    resizeObserver.disconnect()
  }
}

/**
 * Encuadra el mapa a las bounds dadas (ruta + stops + zonas). Guarda contra
 * bounds vacíos o coordenadas inválidas para no lanzar la excepción de Leaflet.
 */
export function fitMapToBounds(map: Map, bounds: LatLngBoundsLiteral, padding = 50): void {
  if (Array.isArray(bounds) && bounds.length === 0) return
  try {
    map.fitBounds(bounds, { padding: [padding, padding], maxZoom: 14 })
  } catch {
    /* bounds inválidas: no encuadrar */
  }
}