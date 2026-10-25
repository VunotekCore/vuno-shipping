import api from './api'
import type { Branch } from '../types'

export type { Branch }

export const branchService = {
  list(activeOnly = true) {
    return api.get<{ success: boolean; data: { branches: Branch[] } }>('/branch/list.php', {
      params: { active: activeOnly ? 1 : undefined },
    })
  },
  get(id: number) {
    return api.get<{ success: boolean; data: { branch: Branch } }>('/branch/get.php', { params: { id } })
  },
  create(data: Partial<Branch>) {
    return api.post<{ success: boolean; data: { branch: Branch } }>('/branch/create.php', data)
  },
  update(data: Partial<Branch> & { id: number }) {
    return api.put<{ success: boolean; data: { branch: Branch } }>('/branch/update.php', data)
  },
  delete(id: number) {
    return api.delete<{ success: boolean; message: string }>('/branch/delete.php', { data: { id } })
  },
}
