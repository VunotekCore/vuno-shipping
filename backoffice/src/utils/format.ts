export function formatCurrency(val: number | null | undefined): string {
  if (val === null || val === undefined) return '—'
  return '$' + Number(val).toFixed(2)
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
