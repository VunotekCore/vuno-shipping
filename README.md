# Shipping Agency — Envios Miami a Nicaragua

Plataforma web para un negocio de logistica que permite a nicaragüenses comprar en tiendas online de Estados Unidos y recibir sus paquetes en Nicaragua.

## Stack

- **Frontend:** Astro 6 · Tailwind 4 · Atomic Design
- **Backoffice:** Vue 3 · Pinia · Axios · Tailwind 4
- **Backend:** PHP 8.1 SOA · JWT Auth · RBAC

## Comandos

```bash
# Frontend publico
pnpm dev              # Dev server -> http://localhost:4321
pnpm build            # Build estatico -> dist/
pnpm astro check      # Type check

# Backend PHP
cd api && composer install
php -l api/**/*.php   # Syntax check

# Backoffice Vue SPA
cd backoffice && npm install
cd backoffice && npm run dev    # Dev server -> http://localhost:5173
cd backoffice && npm run build  # Build produccion
```

## Estructura

```
/
├── src/              # Frontend publico (Astro)
│   ├── components/   # Atomic Design (atoms/molecules/organisms)
│   ├── pages/        # 8 paginas publicas
│   └── lib/          # Icons, nav data
├── backoffice/       # Vue SPA (admin panel)
│   └── src/
│       ├── pages/    # Login, Dashboard, Users, Roles, Permissions
│       ├── stores/   # Pinia auth store
│       └── services/ # API services + auth
├── api/              # Backend PHP SOA
│   ├── Controllers/  # Auth, User, Role, Permission
│   ├── Models/       # PDO models
│   ├── Services/     # AuthService (bcrypt)
│   └── admin/        # Login, verify, logout, refresh endpoints
└── AGENTS.md         # Desarrollo reference
```

## Seguridad

- JWT con HTTP-only cookies + refresh tokens
- RBAC completo con permisos dinamicos
- Rate limiting con `flock()` (atomic)
- Token blacklist para revocacion
- Prepared statements (PDO)
- Security headers (CSP, HSTS, X-Frame-Options)
