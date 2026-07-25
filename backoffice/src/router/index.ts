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
        },
        {
          path: 'roles',
          name: 'roles',
          component: () => import('../pages/RolesView.vue'),
        },
        {
          path: 'permissions',
          name: 'permissions',
          component: () => import('../pages/PermissionsView.vue'),
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

  return true
})

export default router
