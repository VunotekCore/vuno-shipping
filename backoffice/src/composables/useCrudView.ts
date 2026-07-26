import { ref, type Ref } from 'vue'
import { useToast } from './useToast'

export interface Pagination {
  page: number
  limit: number
  total: number
  pages: number
}

export interface CrudService<_T> {
  list(params?: { page?: number; limit?: number }): Promise<{
    data: { data?: Record<string, unknown>; pagination?: Pagination }
  }>
  create(data: Record<string, unknown>): Promise<unknown>
  update(data: Record<string, unknown>): Promise<unknown>
  delete(id: number): Promise<unknown>
}

export interface CrudOptions<T> {
  service: CrudService<T>
  dataKey: string
  entityName: string
  getDefaultForm: () => Record<string, unknown>
  populateForm: (item: T) => Record<string, unknown>
  validateForm: (form: Record<string, unknown>, isEdit: boolean) => string | null
  getEntityId: (item: T) => number
  prepareCreateData?: (form: Record<string, unknown>) => Record<string, unknown>
  prepareUpdateData?: (form: Record<string, unknown>, item: T) => Record<string, unknown>
}

export function useCrudView<T extends { id: number }>(options: CrudOptions<T>) {
  const toast = useToast()

  const items = ref<T[]>([]) as Ref<T[]>
  const loading = ref(true)
  const showModal = ref(false)
  const showDelete = ref(false)
  const editing = ref<T | null>(null)
  const deleting = ref<T | null>(null)
  const saving = ref(false)
  const form = ref<Record<string, unknown>>({})

  const page = ref(1)
  const limit = ref(50)
  const total = ref(0)
  const pages = ref(0)

  async function load() {
    loading.value = true
    try {
      const { data } = await options.service.list({ page: page.value, limit: limit.value })
      items.value = (data.data?.[options.dataKey] as T[]) ?? []
      const p = data.data?.pagination as Pagination | undefined
      if (p) {
        total.value = p.total
        pages.value = p.pages
      }
    } catch {
      toast.error(`Error al cargar ${options.entityName}s`)
    } finally {
      loading.value = false
    }
  }

  function goToPage(n: number) {
    page.value = n
    load()
  }

  function setLimit(newLimit: number) {
    limit.value = newLimit
    page.value = 1
    load()
  }

  function openCreate() {
    editing.value = null
    form.value = options.getDefaultForm()
    showModal.value = true
  }

  function openEdit(item: T) {
    editing.value = item
    form.value = options.populateForm(item)
    showModal.value = true
  }

  function openDelete(item: T) {
    deleting.value = item
    showDelete.value = true
  }

  async function save() {
    const validationError = options.validateForm(form.value, !!editing.value)
    if (validationError) {
      toast.error(validationError)
      return
    }

    saving.value = true
    try {
      if (editing.value) {
        const data = options.prepareUpdateData
          ? options.prepareUpdateData(form.value, editing.value)
          : { id: options.getEntityId(editing.value), ...form.value }
        await options.service.update(data)
        toast.success(`${options.entityName} actualizado`)
      } else {
        const data = options.prepareCreateData
          ? options.prepareCreateData(form.value)
          : form.value
        await options.service.create(data)
        toast.success(`${options.entityName} creado`)
      }
      showModal.value = false
      await load()
    } catch (e: unknown) {
      const msg = (e as { response?: { data?: { message?: string } } })?.response?.data?.message
      toast.error(msg || `Error al guardar ${options.entityName}`)
    } finally {
      saving.value = false
    }
  }

  async function confirmDelete() {
    if (!deleting.value) return
    try {
      await options.service.delete(options.getEntityId(deleting.value))
      toast.success(`${options.entityName} eliminado`)
      showDelete.value = false
      await load()
    } catch (e: unknown) {
      const msg = (e as { response?: { data?: { message?: string } } })?.response?.data?.message
      toast.error(msg || `Error al eliminar ${options.entityName}`)
    }
  }

  return {
    items,
    loading,
    showModal,
    showDelete,
    editing,
    deleting,
    saving,
    form,
    page,
    limit,
    total,
    pages,
    load,
    goToPage,
    setLimit,
    openCreate,
    openEdit,
    openDelete,
    save,
    confirmDelete,
  }
}
