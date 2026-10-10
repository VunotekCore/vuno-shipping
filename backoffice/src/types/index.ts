export interface Permissions {
  [module: string]: string[]
}

export interface User {
  id: number
  email: string
  name: string
  role_id: number
  role_name: string
  role_slug: string
  permissions: Record<string, string[]>
  commission_type?: CommissionType | null
  commission_value?: number | null
  must_change_password?: boolean
  created_at: string
}

export interface Role {
  id: number
  name: string
  slug: string
  permission_count: number
  user_count: number
  created_at: string
}

export interface Permission {
  id: number
  module: string
  action: string
  label: string
  created_at: string
}

export interface UserPermission extends Pick<Permission, 'id' | 'module' | 'action' | 'label'> {
  granted: number
}

export interface Pagination {
  page: number
  limit: number
  total: number
  pages: number
}

export interface Company {
  id: number
  name: string
  logo: string | null
  ruc: string | null
  address: string | null
  phone: string | null
  email: string | null
  website: string | null
  created_at: string
}

export interface DocumentSequence {
  id: number
  document_code: string
  description: string | null
  prefix: string | null
  current_number: number
  created_at: string
}

export interface Area {
  id: number
  name: string
  description: string | null
  puesto_count: number
  created_at: string
}

export interface Puesto {
  id: number
  name: string
  description: string | null
  area_id: number
  area_name: string
  empleado_count: number
  created_at: string
}

export interface Empleado {
  id: number
  name: string
  email: string | null
  phone: string | null
  cedula: string | null
  fecha_ingreso: string | null
  puesto_id: number
  puesto_name: string
  user_id: number | null
  user_email: string | null
  user_name: string | null
  is_active: boolean
  created_at: string
}

export interface Client {
  id: number
  name: string
  email: string | null
  phone: string | null
  address: string | null
  department: string | null
  notes: string | null
  ruc?: string | null
  is_active: boolean
  agent_id?: number | null
  agent_name?: string | null
  package_count?: number
  created_at: string
  updated_at: string
}

export interface Zone {
  id: number
  name: string
  description: string | null
  lat: number | null
  lng: number | null
  surcharge: number
  is_active: boolean
  created_at: string
}

export interface Subzone {
  id: number
  zone_id: number
  name: string
}

export interface Vehicle {
  id: number
  plate: string
  brand: string | null
  model: string | null
  year: number | null
  color: string | null
  fuel_type: string | null
  capacity_kg: number | null
  capacity_m3: number | null
  current_driver_id: number | null
  current_driver_name: string | null
  status: string
  status_id?: number | null
  status_name?: string | null
  current_km: number
  km_last_updated_at: string | null
  km_last_updated_by: number | null
  km_last_updated_by_name?: string | null
  notes: string | null
  created_at: string
  updated_at: string
}

export interface ShippingService {
  id: number
  name: string
  description: string | null
}

export interface DeliveryOption {
  id: number
  name: string
  description: string | null
  is_default?: boolean
}

export interface DeliveryType {
  id: number
  name: string
  description: string | null
  category: string
}

export interface PricingRule {
  id: number
  name: string
  type_id: number | null
  type_name: string | null
  delivery_type_id: number | null
  delivery_type_name: string | null
  shipping_service_id: number | null
  shipping_service_name: string | null
  base_price: number
  min_weight: number | null
  max_weight: number | null
  price_per_kg: number | null
  price_per_volume: number | null
  price_per_km: number | null
  price_per_zone: number | null
  percentage_of_value: number
  min_price: number | null
  volumetric_divisor: number
  is_active: boolean
  zones?: Zone[]
  created_at: string
  updated_at: string
}

export interface Package {
  id: number
  tracking_number: string
  tracking_token?: string
  client_id: number | null
  client_name: string | null
  sender_name: string
  sender_phone: string | null
  recipient_name: string
  recipient_phone: string
  recipient_address: string
  recipient_city: string | null
  recipient_department: string | null
  zone_id: number | null
  zone_name: string | null
  description: string | null
  weight: number | null
  length: number | null
  width: number | null
  height: number | null
  volumetric_weight: number | null
  chargeable_weight: number | null
  declared_value: number | null
  flete_cost: number | null
  delivery_cost: number | null
  shipping_cost: number | null
  pricing_rule_id: number | null
  pricing_rule_name: string | null
  delivery_type_id: number | null
  delivery_type_name: string | null
  shipping_service_id: number | null
  shipping_service_name: string | null
  delivery_option: string | null
  local_delivery_type_id: number | null
  local_delivery_type_name: string | null
  delivery_option_id: number | null
  delivery_option_name: string | null
  status_id: number | null
  status_name: string | null
  assigned_to: number | null
  assigned_to_name: string | null
  notes: string | null
  origin: 'online' | 'local'
  pickup_required?: number
  pickup_vehicle_id?: number | null
  pickup_vehicle_plate?: string | null
  received_at: string | null
  delivered_at: string | null
  invoice_id?: number | null
  invoice_number?: string | null
  invoice_status?: 'pending' | 'paid' | 'cancelled' | null
  created_by: number | null
  created_at: string
  updated_at: string
}

export interface ShipmentPackage {
  id: number
  tracking_number: string
  tracking_token?: string
  client_id: number | null
  client_name: string | null
  recipient_name: string
  recipient_address: string | null
  recipient_city: string | null
  recipient_department: string | null
  zone_id: number | null
  zone_name: string | null
  description: string | null
  weight: number | null
  chargeable_weight: number | null
  flete_cost: number | null
  delivery_cost: number | null
  shipping_cost: number | null
  shipping_service_name: string | null
  status_id: number | null
  status_name: string | null
  origin: 'online' | 'local'
  received_at: string | null
}

export interface Shipment {
  id: number
  code: string
  mode: 'air' | 'sea'
  status: 'draft' | 'in_transit' | 'arrived' | 'cancelled'
  departure_date: string | null
  arrival_date: string | null
  notes: string | null
  created_by: number | null
  created_by_name: string | null
  package_count: number
  packages?: ShipmentPackage[]
  created_at: string
  updated_at: string
}

export interface TrackingEvent {
  id: number
  package_id: number
  status: string
  description: string | null
  latitude: number | null
  longitude: number | null
  location_name: string | null
  photo_url: string | null
  recorded_at: string
  created_by: number | null
  created_by_name: string | null
  created_at: string
}

export interface Notification {
  id: number
  package_id: number | null
  type: string
  title: string
  message: string | null
  tracking_number: string | null
  is_read: number
  created_at: string
}

export interface DeliveryRoute {
  id: number
  name: string
  description: string | null
  trajectory: string | null
  color: string
  default_driver_id: number | null
  default_vehicle_id: number | null
  default_driver_name: string | null
  default_vehicle_plate: string | null
  start_latitude: number | null
  start_longitude: number | null
  end_latitude: number | null
  end_longitude: number | null
  zone_count: number
  runs_count: number
  open_run_id: number | null
  open_run_status: string | null
  zones?: Zone[]
  created_at: string
  updated_at: string
}

export interface RouteRun {
  id: number
  route_id: number
  route_name: string
  route_description: string | null
  route_color: string
  trajectory: string | null
  start_latitude: number | null
  start_longitude: number | null
  end_latitude: number | null
  end_longitude: number | null
  run_date: string
  assigned_to: number | null
  driver_name: string | null
  driver_email: string | null
  vehicle_id: number | null
  vehicle_plate: string | null
  vehicle_brand: string | null
  vehicle_model: string | null
  start_time: string | null
  end_time: string | null
  status_id: number | null
  status_name: string | null
  optimized_geometry: string | null
  notes: string | null
  stop_count: number
  pending_stops: number
  delivered_stops: number
  carried_over?: number
  stops?: RouteStop[]
  zones?: Zone[]
  created_at: string
  updated_at: string
}

export interface RouteStop {
  id: number
  route_run_id: number
  package_id: number | null
  stop_order: number
  client_name: string | null
  address: string | null
  latitude: number | null
  longitude: number | null
  estimated_arrival: string | null
  actual_arrival: string | null
  notes: string | null
  carried_over: number
  status_id: number | null
  status_name: string | null
  signature_data: string | null
  photo_url: string | null
  tracking_number?: string
  recipient_phone?: string
  package_description?: string
  from_zone_center?: number
  invoice_id?: number | null
  invoice_number?: string | null
  invoice_status?: 'pending' | 'paid' | 'cancelled' | null
  created_at: string
  updated_at: string
}

export interface DriverLocation {
  user_id: number
  driver_name: string
  driver_email: string
  latitude: number
  longitude: number
  accuracy: number | null
  speed: number | null
  heading: number | null
  battery_level: number | null
  is_active: number
  last_update: string
}

export interface DashboardStats {
  total_packages: number
  active_packages: number
  delivered_today: number
  total_clients: number
  in_transit: number
  pending: number
  delivered: number
  pending_invoices?: number
  paid_today_usd?: number
  paid_today_cord?: number
}

export type InvoiceStatus = 'pending' | 'paid' | 'cancelled'

export interface Invoice {
  id: number
  invoice_number: string
  package_id: number
  client_id: number | null
  client_name: string | null
  client_name_ref: string | null
  client_ruc: string | null
  base_amount: number
  tax_rate: number
  tax_amount: number
  total_amount: number
  currency: 'USD'
  exchange_rate: number | null
  status: InvoiceStatus
  paid_amount: number | null
  paid_currency: 'USD' | 'CORD' | null
  payment_method_id: number | null
  payment_method_name: string | null
  payment_reference: string | null
  paid_at: string | null
  notes: string | null
  created_by: number | null
  tracking_number?: string
  /** Datos del paquete asociado (join de `InvoiceModel::findById`) */
  recipient_name?: string | null
  recipient_phone?: string | null
  recipient_address?: string | null
  recipient_city?: string | null
  recipient_department?: string | null
  description?: string | null
  /** Pesos en libras */
  weight?: number | null
  length?: number | null
  width?: number | null
  height?: number | null
  volumetric_weight?: number | null
  chargeable_weight?: number | null
  declared_value?: number | null
  shipping_cost?: number | null
  flete_cost?: number | null
  delivery_cost?: number | null
  created_at: string
}

export interface PaymentMethod {
  id: number
  name: string
  description: string | null
  is_active: number
}

export interface InvoiceSequenceInfo {
  exists: boolean
  prefix: string
  current_number: number
  next_preview: string
}

export interface BillingConfig {
  id?: number
  tax_enabled: number
  tax_rate: number
  show_client_ruc: number
  exchange_rate_usd: number
}

export interface InvoiceSummary {
  /** Suma de `total_amount`, que siempre esta expresado en USD */
  pending_total: number
  /** Suma de `paid_amount` de las facturas cobradas hoy en USD */
  paid_today_usd: number
  /** Suma de `paid_amount` de las facturas cobradas hoy en CORD */
  paid_today_cord: number
}

export interface VehicleOdometerLog {
  id: number
  vehicle_id: number
  km_reading: number
  previous_km: number
  delta_km: number
  reading_type: string
  route_run_id: number | null
  user_id: number
  user_name: string | null
  notes: string | null
  created_at: string
}

export type CommissionType = 'percent' | 'fixed'
export type QuoteStatus = 'draft' | 'sent' | 'accepted' | 'rejected' | 'expired' | 'converted' | 'cancelled'

/** Resumen de conteo de cotizaciones por estado (KPIs). */
export type QuoteSummary = Record<QuoteStatus, number>

export interface QuoteItem {
  id?: number
  quote_id?: number
  position?: number
  description: string | null
  quantity: number
  /** Pesos en libras (igual que el resto del sistema) */
  weight: number | null
  length: number | null
  width: number | null
  height: number | null
  declared_value: number | null
  volumetric_weight?: number
  chargeable_weight?: number
  flete_cost?: number
  delivery_cost?: number
  total_amount?: number
}

export interface QuotePackage {
  id: number
  tracking_number: string
  status_id: number | null
  shipping_cost: number | null
  created_at: string
  invoice_id: number | null
  invoice_number: string | null
  invoice_status: InvoiceStatus | null
}

export interface Quote {
  id: number
  quote_number: string
  client_id: number | null
  client_name_ref: string | null
  recipient_name: string
  recipient_phone: string | null
  recipient_email: string | null
  recipient_address: string | null
  recipient_city: string | null
  recipient_department: string | null
  recipient_ruc: string | null
  latitude: number | null
  longitude: number | null
  agent_id: number | null
  agent_name: string | null
  agent_commission_type?: CommissionType | null
  agent_commission_value?: number | null
  status: QuoteStatus
  origin: 'online' | 'local'
  zone_id: number | null
  delivery_type_id: number | null
  shipping_service_id: number | null
  delivery_option: string | null
  delivery_option_id: number | null
  pickup_required: number
  pickup_vehicle_id: number | null
  pickup_vehicle_plate?: string | null
  subtotal: number
  delivery_total: number
  base_amount: number
  tax_rate: number
  tax_amount: number
  total_amount: number
  currency: 'USD' | 'CORD'
  commission_type: CommissionType | null
  commission_value: number | null
  commission_amount: number | null
  valid_until: string | null
  converted_at: string | null
  notes: string | null
  created_by: number | null
  /** Solo en listados */
  item_count?: number
  /** Solo en listados */
  package_count?: number
  created_at: string
  updated_at: string
}

/** KPI de comisiones por agente (`quote/stats`). */
export interface QuoteCommissionRow {
  agent_id: number | null
  agent_name: string | null
  converted_count: number
  converted_total: number
  commission_total: number
}

export interface QuoteListResult {
  quotes: Quote[]
  pagination: Pagination
  summary: QuoteSummary
}

export interface QuoteDetailResult {
  quote: Quote
  items: QuoteItem[]
  packages: QuotePackage[]
}

export interface QuoteStatsResult {
  summary: QuoteSummary
  commissions: QuoteCommissionRow[]
}
