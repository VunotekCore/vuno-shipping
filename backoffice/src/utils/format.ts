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
    out_for_delivery: 'admin-badge-warning',
    delivered: 'admin-badge-success',
    returned: 'admin-badge-danger',
    cancelled: 'admin-badge-danger',
    disputed: 'admin-badge-danger',
  }
  return map[status] || 'admin-badge-info'
}
