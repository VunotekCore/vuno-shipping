import type { LatLngBoundsExpression, MapOptions } from 'leaflet'

// Limite geografico a Nicaragua (con margen). Evita que el usuario se salga del
// pais con pan/zoom: con maxBoundsViscosity = 1 el borde es un freno duro.
export const NICARAGUA_BOUNDS: LatLngBoundsExpression = [[10.6, -88.0], [15.1, -82.5]]

export const NICARAGUA_MAP_OPTIONS: Pick<MapOptions, 'maxBounds' | 'maxBoundsViscosity' | 'minZoom'> = {
  maxBounds: NICARAGUA_BOUNDS,
  maxBoundsViscosity: 1.0,
  minZoom: 6,
}
