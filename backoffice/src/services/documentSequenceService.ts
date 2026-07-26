import api from './api'
import type { DocumentSequence } from '../types'
import { buildPageParams } from './pagination'

export type { DocumentSequence }

export const documentSequenceService = {
  list(params?: { page?: number; limit?: number }) {
    return api.get<{ success: boolean; data: { document_sequences: DocumentSequence[]; pagination: import('./pagination').Pagination } }>(
      `/document_sequence/list.php${buildPageParams(params)}`
    )
  },

  create(data: { document_code: string; description?: string; prefix?: string; current_number?: number }) {
    return api.post('/document_sequence/create.php', data)
  },

  update(data: { id: number; document_code?: string; description?: string | null; prefix?: string | null; current_number?: number }) {
    return api.put('/document_sequence/update.php', data)
  },

  delete(id: number) {
    return api.delete('/document_sequence/delete.php', { data: { id } })
  },
}
