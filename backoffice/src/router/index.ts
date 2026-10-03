import { createRouter, createWebHistory } from 'vue-router'
import { useAuthStore } from '../stores/auth'

const router = createRouter({
  history: createWebHistory('/backoffice/'),
  routes: [
    {
      path: '/login',
      name: 'login',
      component: () => import('../pages/LoginView.vue'),
      meta: { public: true },
    },
    // === Admin routes ===
    {
      path: '/',
      component: () => import('../pages/AdminLayout.vue'),
      meta: { requiresAdmin: true },
      children: [
        {
          path: '',
          name: 'dashboard',
          component: () => import('../pages/DashboardView.vue'),
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
          path: 'admision',
          name: 'admision',
          component: () => import('../pages/AdmisionView.vue'),
          meta: { permission: 'package', action: 'create' },
        },
        {
          path: 'scan',
          name: 'scan',
          component: () => import('../pages/ScanView.vue'),
          meta: { permission: 'package', action: 'read' },
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
          path: 'embarques',
          name: 'embarques',
          component: () => import('../pages/ShipmentsView.vue'),
          meta: { permission: 'shipment', action: 'read' },
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
        {
          path: 'routes',
          name: 'routes',
          component: () => import('../pages/RoutesView.vue'),
          meta: { permission: 'delivery_route', action: 'read' },
        },
        {
          path: 'runs',
          name: 'runs',
          component: () => import('../pages/RunsView.vue'),
          meta: { permission: 'delivery_route', action: 'read' },
        },
        {
          path: 'runs/:id',
          name: 'run-detail',
          component: () => import('../pages/RunDetailView.vue'),
          meta: { permission: 'delivery_route', action: 'read' },
        },
        {
          path: 'settings/shipping',
          name: 'shipping-settings',
          component: () => import('../pages/ShippingSettingsView.vue'),
          meta: { permission: 'shipping_service', action: 'read' },
        },
        {
          path: 'facturas',
          name: 'invoices',
          component: () => import('../pages/InvoicesView.vue'),
          meta: { permission: 'invoice', action: 'read' },
        },
        {
          path: 'facturas/:id',
          name: 'invoice-detail',
          component: () => import('../pages/InvoiceDetailView.vue'),
          meta: { permission: 'invoice', action: 'read' },
        },
        {
          path: 'facturacion/config',
          name: 'billing-settings',
          component: () => import('../pages/BillingSettingsView.vue'),
          meta: { permission: 'billing_config', action: 'read' },
        },
      ],
    },
    // === Conductor routes ===
    {
      path: '/conductor',
      component: () => import('../pages/conductor/ConductorLayout.vue'),
      meta: { requiresConductor: true },
      children: [
        {
          path: '',
          name: 'conductor-dashboard',
          component: () => import('../pages/conductor/ConductorDashboard.vue'),
        },
        {
          path: 'ruta',
          name: 'conductor-ruta',
          component: () => import('../pages/conductor/ConductorRouteMap.vue'),
        },
        {
          path: 'stop/:id',
          name: 'conductor-stop',
          component: () => import('../pages/conductor/ConductorStopDetail.vue'),
        },
        {
          path: 'deliver/:stopId',
          name: 'conductor-deliver',
          component: () => import('../pages/conductor/ConductorDeliveryForm.vue'),
        },
        {
          path: 'historial',
          name: 'conductor-historial',
          component: () => import('../pages/conductor/ConductorHistory.vue'),
        },
        {
          path: 'perfil',
          name: 'conductor-perfil',
          component: () => import('../pages/conductor/ConductorProfile.vue'),
        },
      ],
    },
  ],
})

router.beforeEach(async (to) => {
  const auth = useAuthStore()

  // Public routes (login)
  if (to.meta.public) {
    if (auth.isAuthenticated) {
      const roleSlug = auth.user?.role_slug
      if (roleSlug === 'conductor') return { name: 'conductor-dashboard' }
      return { name: 'dashboard' }
    }
    return true
  }

  // Auth check
  if (!auth.isAuthenticated) {
    const verified = await auth.verify()
    if (!verified) {
      return { name: 'login' }
    }
  }

  const roleSlug = auth.user?.role_slug

  // Redirect conductor to conductor routes
  if (to.meta.requiresAdmin && roleSlug === 'conductor') {
    return { name: 'conductor-dashboard' }
  }

  // Redirect admin trying to access conductor routes
  if (to.meta.requiresConductor && roleSlug !== 'conductor') {
    return { name: 'dashboard' }
  }

  // Permission check for admin routes
  if (to.meta.permission && to.meta.action) {
    if (!auth.hasPermission(to.meta.permission as string, to.meta.action as string)) {
      return { name: 'dashboard' }
    }
  }

  return true
})

export default router
