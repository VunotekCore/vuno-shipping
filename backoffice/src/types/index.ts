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
