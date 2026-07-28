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
  is_active: boolean
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
  status_id: number | null
  status_name: string | null
  notes: string | null
  created_at: string
  updated_at: string
}

export interface PricingRule {
  id: number
  name: string
  type_id: number | null
  type_name: string | null
  delivery_type_id: number | null
  delivery_type_name: string | null
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
  status_id: number | null
  status_name: string | null
  assigned_to: number | null
  assigned_to_name: string | null
  notes: string | null
  received_at: string | null
  delivered_at: string | null
  created_by: number | null
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

export interface DashboardStats {
  total_packages: number
  active_packages: number
  delivered_today: number
  total_clients: number
  in_transit: number
  pending: number
  delivered: number
}
