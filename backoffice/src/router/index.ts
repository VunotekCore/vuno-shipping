import { createRouter, createWebHistory } from 'vue-router'
import { useAuthStore } from '../stores/auth'

import LoginView from '../pages/LoginView.vue'
import DashboardView from '../pages/DashboardView.vue'

const router = createRouter({
  history: createWebHistory('/'),
  routes: [
    {
      path: '/login',
      name: 'login',
      component: LoginView,
      meta: { public: true },
    },
    {
      path: '/',
      component: () => import('../pages/AdminLayout.vue'),
      children: [
        {
          path: '',
          name: 'dashboard',
          component: DashboardView,
        },
        {
          path: 'users',
          name: 'users',
          component: () => import('../pages/UsersView.vue'),
          meta: { permission: 'user', action: 'read' },
        },
        {
          path: 'roles',
          name: 'roles',
          component: () => import('../pages/RolesView.vue'),
          meta: { permission: 'role', action: 'read' },
        },
        {
          path: 'permissions',
          name: 'permissions',
          component: () => import('../pages/PermissionsView.vue'),
          meta: { permission: 'permission', action: 'read' },
        },
        {
          path: 'company',
          name: 'company',
          component: () => import('../pages/CompanyView.vue'),
          meta: { permission: 'company', action: 'read' },
        },
        {
          path: 'areas',
          name: 'areas',
          component: () => import('../pages/AreasView.vue'),
          meta: { permission: 'area', action: 'read' },
        },
        {
          path: 'puestos',
          name: 'puestos',
          component: () => import('../pages/PuestosView.vue'),
          meta: { permission: 'puesto', action: 'read' },
        },
        {
          path: 'empleados',
          name: 'empleados',
          component: () => import('../pages/EmpleadosView.vue'),
          meta: { permission: 'empleado', action: 'read' },
        },
        {
          path: 'document-sequences',
          name: 'document-sequences',
          component: () => import('../pages/DocumentSequenceView.vue'),
          meta: { permission: 'document_sequence', action: 'read' },
        },
        {
          path: 'packages',
          name: 'packages',
          component: () => import('../pages/PackagesView.vue'),
          meta: { permission: 'package', action: 'read' },
        },
        {
          path: 'packages/:id',
          name: 'package-detail',
          component: () => import('../pages/PackageDetailView.vue'),
          meta: { permission: 'package', action: 'read' },
        },
        {
          path: 'clients',
          name: 'clients',
          component: () => import('../pages/ClientsView.vue'),
          meta: { permission: 'client', action: 'read' },
        },
        {
          path: 'vehicles',
          name: 'vehicles',
          component: () => import('../pages/VehiclesView.vue'),
          meta: { permission: 'vehicle', action: 'read' },
        },
        {
          path: 'zones',
          name: 'zones',
          component: () => import('../pages/ZonesView.vue'),
          meta: { permission: 'zone', action: 'read' },
        },
        {
          path: 'pricing',
          name: 'pricing',
          component: () => import('../pages/PricingView.vue'),
          meta: { permission: 'pricing_rule', action: 'read' },
        },
      ],
    },
  ],
})

router.beforeEach(async (to) => {
  const auth = useAuthStore()

  if (to.meta.public) {
    if (auth.isAuthenticated) {
      return { name: 'dashboard' }
    }
    return true
  }

  if (!auth.isAuthenticated) {
    const verified = await auth.verify()
    if (!verified) {
      return { name: 'login' }
    }
  }

  if (to.meta.permission && to.meta.action) {
    if (!auth.hasPermission(to.meta.permission as string, to.meta.action as string)) {
      return { name: 'dashboard' }
    }
  }

  return true
})

export default router
