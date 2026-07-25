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
  permissions?: Permissions
  created_at?: string
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

export interface Pagination {
  page: number
  limit: number
  total: number
  pages: number
}
