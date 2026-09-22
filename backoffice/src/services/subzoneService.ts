import api from './api'
import type { Subzone } from '../types'

export type { Subzone }

export const subzoneService = {
  byZone(zoneId: number) {
    return api.get<{ success: boolean; data: { subzones: Subzone[] } }>('/subzona/byzone.php', {
      params: { zone_id: zoneId },
    })
  },
}