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
