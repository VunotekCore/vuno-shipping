export function formatCurrency(val: number | null | undefined): string {
  if (val === null || val === undefined) return '—'
  return '$' + Number(val).toFixed(2)
}

export type PaidCurrency = 'USD' | 'CORD'

const CENTS = 100

/**
 * Convierte USD a cordobas con el mismo redondeo que el backend
 * (`round($total * $rate, 2)` en InvoiceController::pay). Si el preview del
 * modal de cobro difiere un centavo del cobro real, el operador confirma un
 * monto que no es el que se guarda.
 */
export function usdToCord(usd: number | null | undefined, rate: number | null | undefined): number {
  const amount = Number(usd ?? 0)
  const exchange = Number(rate ?? 0)
  return Math.round(amount * exchange * CENTS) / CENTS
}

export function formatUSD(val: number | null | undefined): string {
  if (val === null || val === undefined) return '—'
  return '$' + Number(val).toLocaleString('es-NI', { minimumFractionDigits: 2, maximumFractionDigits: 2 })
}

export function formatCord(val: number | null | undefined): string {
  if (val === null || val === undefined) return '—'
  return 'C$' + Number(val).toLocaleString('es-NI', { minimumFractionDigits: 2, maximumFractionDigits: 2 })
}

export function formatCurrencyOf(val: number | null | undefined, currency: PaidCurrency | null | undefined): string {
  return currency === 'CORD' ? formatCord(val) : formatUSD(val)
}

/**
 * Tasa de cambio con 4 decimales. `exchange_rate` es DECIMAL(10,4): con 2
 * decimales un comprobante puede mostrar una tasa que no fue la aplicada.
 */
export function formatRate(rate: number | null | undefined): string {
  if (rate === null || rate === undefined) return '—'
  return Number(rate).toLocaleString('es-NI', { minimumFractionDigits: 4, maximumFractionDigits: 4 })
}

export function paidCurrencyLabel(currency: PaidCurrency | null | undefined): string {
  return currency === 'CORD' ? 'Córdobas (C$)' : 'USD ($)'
}

/** Peso en libras. El flete se cotiza por peso, asi que se muestra con 2 decimales. */
export function formatWeight(val: number | null | undefined): string {
  if (val === null || val === undefined) return '—'
  return `${Number(val).toLocaleString('es-NI', { minimumFractionDigits: 2, maximumFractionDigits: 2 })} lb`
}

/**
 * Dimensiones en pulgadas. Devuelve `—` si falta alguna de las tres medidas,
 * para no mostrar `10 × — × 5` como si fuera un dato completo.
 */
export function formatDimensions(
  length: number | null | undefined,
  width: number | null | undefined,
  height: number | null | undefined,
): string {
  if (length == null || width == null || height == null) return '—'
  return `${Number(length)} × ${Number(width)} × ${Number(height)} in`
}

export function readingTypeLabel(type: string | null | undefined): string {
  if (!type) return '—'
  const map: Record<string, string> = {
    manual: 'Manual',
    inicio_ruta: 'Inicio de ruta',
    fin_ruta: 'Fin de ruta',
    entrega_llaves: 'Entrega de llaves',
    devolucion_llaves: 'Devolución de llaves',
    inspeccion: 'Inspección',
    mantenimiento: 'Mantenimiento',
    reparacion: 'Reparación',
    otro: 'Otro',
  }
  return map[type] || type
}

export function statusColor(status: string | null): string {
  if (!status) return 'admin-badge-info'
  const map: Record<string, string> = {
    pending: 'admin-badge-warning',
    received: 'admin-badge-info',
    in_transit: 'admin-badge-info',
    in_nicaragua: 'admin-badge-info',
    out_for_delivery: 'admin-badge-warning',
    delivered: 'admin-badge-success',
    returned: 'admin-badge-danger',
    cancelled: 'admin-badge-danger',
    disputed: 'admin-badge-danger',
  }
  return map[status] || 'admin-badge-info'
}

export function statusLabel(status: string | null | undefined): string {
  if (!status) return '—'
  const map: Record<string, string> = {
    pending: 'Pendiente',
    received: 'Recibido en Miami',
    in_transit: 'En tránsito a Nicaragua',
    in_nicaragua: 'Recibido en Nicaragua',
    out_for_delivery: 'En ruta de entrega',
    delivered: 'Entregado',
    returned: 'Devuelto',
    cancelled: 'Cancelado',
    disputed: 'En disputa',
    skipped: 'Omitida',
    failed: 'Fallo de entrega',
    draft: 'Borrador',
    active: 'Activo',
    completed: 'Completado',
    maintenance: 'En mantenimiento',
    inactive: 'Inactivo',
  }
  return map[status] || status
}
