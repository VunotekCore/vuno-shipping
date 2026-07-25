# 🚚 Shipping Agency — Envíos Miami a Nicaragua

> **Negocio:** Agency de receiving y envío de paquetes desde Miami hasta Nicaragua
> **Modelo:** Usuarios compran en Amazon/eBay/Shein → envían a almacén Miami → Mg consolida y envía a Nicaragua → entrega a domicilio
> **Estado:** Phase 1 + Phase 2 (CRUD) completadas
> **Stack:** Astro 6 · Vue 3 + Pinia · PHP 8.1 SOA · Tailwind 4 · MD3 Design Tokens

---

## 1. Resumen Ejecutivo

Shipping Agency es una plataforma web para un negocio de logística que permite a nicaragüenses comprar en tiendas online de Estados Unidos y recibir sus paquetes en Nicaragua. El cliente compra en Amazon, Shein, eBay, etc., usa la dirección de almacén de Mg en Miami, y Mg consolida y envía todo a Nicaragua.

### Estado Actual

| Componente | Estado |
|-----------|--------|
| Frontend público (Astro) | ✅ 8 páginas funcionales |
| Backoffice (Vue SPA) | ✅ Phase 2 — CRUD completo |
| Backend (PHP SOA) | ✅ Phase 2 — Auth + RBAC CRUD |

---

## 2. Páginas del Sitio

| Página | Ruta | Componentes | Estado |
|--------|------|-------------|--------|
| Home | `/` | HeroSection, ProcessSteps, FeaturesBento, TrackingStepper, CTASection | ✅ |
| Servicios | `/servicios` | ServiceHero, ServiceCards, PricingTable, ShippingCalculator, CTASection | ✅ |
| Contacto | `/contacto` | ContactFormData | ✅ |
| Rastreo | `/rastreo` | TrackingInput | ✅ |
| Nosotros | `/nosotros` | ScrollReveal, values grid | ✅ |
| FAQ | `/faq` | FAQItem, CTASection | ✅ |
| Privacidad | `/privacidad` | Estático | ✅ |
| Términos | `/terminos` | Estático | ✅ |

---

## 3. Arquitectura — Atomic Design

```
src/components/
├── atoms/          # Elementos UI básicos (reutilizables en backoffice)
│   ├── Button.astro
│   ├── Input.astro
│   ├── Textarea.astro
│   ├── Icon.astro
│   └── ScrollReveal.astro
├── molecules/      # Combinaciones de atoms
│   ├── FormField.astro
│   ├── ContactFormData.astro
│   ├── TrackingInput.astro
│   ├── ServiceCard.astro
│   ├── PricingCard.astro
│   ├── LocationCard.astro
│   ├── SocialLinks.astro
│   ├── StepCard.astro
│   ├── FeatureCard.astro
│   ├── CopyrightBar.astro
│   ├── FAQItem.astro
│   └── NavLinks.astro
├── organisms/      # Secciones completas de página
│   ├── Header.astro
│   ├── Footer.astro
│   ├── HeroSection.astro
│   ├── ProcessSteps.astro
│   ├── FeaturesBento.astro
│   ├── TrackingStepper.astro
│   ├── CTASection.astro
│   ├── ServiceHero.astro
│   ├── ServiceCards.astro
│   ├── ShippingCalculator.astro
│   ├── PricingTable.astro
│   └── WhatsAppButton.astro
└── (no crear componentes sin consumidor)

src/lib/             # Datos compartidos y utilidades
├── icons.ts         # Registro centralizado de paths SVG
└── nav.ts           # Links de navegación + interfaz NavLink
```

### Reglas de Componentes

- **Archivos ≤500 líneas** — Si excede, dividir en subcomponentes
- **HTML semántico** — `<main>`, `<section>`, `<article>`, `<aside>`, `<header>`, `<footer>`, `<nav>`
- **Sin dependencias externas** — Solo Tailwind CSS (sin shadcn, sin React, sin Vue en front)
- **Iconos centralizados** — Siempre usar `<Icon name="..." />` desde `src/components/atoms/Icon.astro`. No definir `iconPaths` localmente ni duplicar SVG inline. Para agregar un icono nuevo, añadir su path al mapa en `src/lib/icons.ts`
- **Usar atoms existentes** — No escribir `<input>`, `<select>`, `<button>` inline cuando existen atoms (`Input.astro`, `Button.astro`). Si el atom no cubre el caso de uso, extenderlo o crear uno nuevo en atoms/
- **Interfaces compartidas** — No duplicar interfaces. Importar de `src/lib/` (ej: `NavLink` de `nav.ts`)
- **Sin código muerto** — No crear componentes, funciones o variables sin consumidor. Antes de crear, verificar que no existe uno equivalente
- **Imports limpios** — Solo importar lo que se usa. Ejecutar `pnpm astro check` para detectar imports huérfanos

---

## 4. Diseño y Branding

### Paleta MD3 (global.css)

| Token | Valor | Uso |
|-------|-------|-----|
| `--color-surface` | `#faf9f8` | Fondo general |
| `--color-primary` | `#1a1a1a` | Texto, headlines |
| `--color-surface-container-low` | `#f5f0eb` | Secciones alternas |
| `--color-outline-variant` | `#e0d8d0` | Bordes |
| `--color-secondary-container` | `#dce8f0` | Badges, icons bg |

### Breakpoints

| Name | Width | Usage |
|------|-------|-------|
| xs | 480px | Mobile pequeño |
| sm | 640px | Mobile estándar |
| md | 768px | Tablet |
| lg | 1024px | Desktop |
| xl | 1280px | Desktop grande |

### Responsive Rules

- Container: `max-w-container-max mx-auto`
- Mobile margins: `px-margin-mobile md:px-sp-xl`
- Grids: `grid-cols-1 sm:grid-cols-2 lg:grid-cols-3`
- Hide on mobile: `hidden md:block`
- Hide on desktop: `md:hidden`

---

## 5. Estructura del Proyecto

```
/
├── public/
│   ├── favicon.svg
│   └── robots.txt
├── src/
│   ├── components/
│   │   ├── atoms/
│   │   ├── molecules/
│   │   └── organisms/
│   ├── layouts/
│   │   └── BaseLayout.astro        # Layout público (SEO, fonts, meta tags)
│   ├── lib/
│   │   ├── icons.ts                # Registro centralizado de paths SVG
│   │   └── nav.ts                  # Links de navegación compartidos
│   ├── pages/
│   │   ├── index.astro             # Home
│   │   ├── servicios.astro         # Servicios
│   │   ├── contacto.astro          # Contacto
│   │   ├── rastreo.astro           # Rastreo de paquetes
│   │   ├── nosotros.astro          # Sobre nosotros
│   │   ├── faq.astro               # Preguntas frecuentes
│   │   ├── privacidad.astro        # Política de privacidad
│   │   └── terminos.astro          # Términos de servicio
│   ├── scripts/
│   │   └── hero-slider.js          # Slider del hero
│   ├── styles/
│   │   └── global.css              # Tailwind @theme + MD3 tokens + breakpoints
│   └── env.d.ts
├── api/                             # Backend PHP SOA
│   ├── bootstrap.php                # Entry point: config, JWT, CORS, helpers
│   ├── schema.sql                   # Tablas RBAC
│   ├── seed.sql                     # Datos iniciales
│   ├── composer.json                # PHP deps (vlucas/phpdotenv)
│   ├── .htaccess                    # Seguridad: bloquea acceso directo
│   ├── Config/
│   │   └── Database.php            # PDO singleton
│   ├── Controllers/
│   │   ├── AuthController.php       # Login/verify/logout
│   │   ├── PermissionController.php # CRUD permisos
│   │   ├── RoleController.php       # CRUD roles + permisos
│   │   └── UserController.php       # CRUD usuarios
│   ├── Models/
│   │   ├── PermissionModel.php
│   │   ├── RoleModel.php
│   │   └── UserModel.php
│   ├── Services/
│   │   └── AuthService.php          # bcrypt hash/verify
│   ├── Traits/
│   │   ├── ApiResponse.php
│   │   └── JwtAuth.php
│   ├── admin/                       # Auth endpoints
│   │   ├── login.php
│   │   ├── verify.php
│   │   └── logout.php
│   ├── permission/                  # Permission CRUD
│   │   ├── list.php
│   │   ├── create.php
│   │   ├── update.php
│   │   └── delete.php
│   ├── role/                        # Role CRUD
│   │   ├── list.php
│   │   ├── create.php
│   │   ├── update.php
│   │   ├── delete.php
│   │   └── permissions.php
│   └── user/                        # User CRUD
│       ├── list.php
│       ├── create.php
│       ├── update.php
│       └── delete.php
├── backoffice/                      # Vue SPA (admin)
│   ├── .htaccess                    # Vue Router history mode fallback
│   ├── index.html
│   ├── package.json                 # Vue 3, Pinia, Axios, Tailwind 4
│   ├── vite.config.ts               # Dev proxy /api → localhost:8000
│   ├── tsconfig.json
│   └── src/
│       ├── main.ts
│       ├── App.vue
│       ├── router/index.ts          # Vue Router + auth guard
│       ├── stores/auth.ts           # Pinia auth (cookie-based)
│       ├── services/
│       │   ├── api.ts               # Axios instance
│       │   ├── authService.ts       # Login/verify/logout
│       │   ├── permissionService.ts
│       │   ├── roleService.ts
│       │   └── userService.ts
│       ├── composables/
│       │   └── useToast.ts
│       ├── styles/
│       │   ├── main.css
│       │   └── admin.css            # Dark theme (~380 lines)
│       ├── pages/
│       │   ├── LoginView.vue        # Split-panel login
│       │   ├── AdminLayout.vue      # Sidebar + header + RouterView
│       │   ├── DashboardView.vue
│       │   ├── UsersView.vue
│       │   ├── RolesView.vue
│       │   └── PermissionsView.vue
│       └── components/
│           ├── layout/
│           │   ├── AdminSidebar.vue
│           │   └── AdminHeader.vue
│           └── ui/
│               ├── AppModal.vue
│               ├── AppTable.vue
│               ├── AppPagination.vue
│               ├── AppSwitch.vue
│               ├── AppConfirmDialog.vue
│               ├── AppToast.vue
│               └── AppSkeleton.vue
├── html-designs/                   # Diseños HTML de referencia
├── .env.example
├── astro.config.mjs                # output: "static"
├── tsconfig.json
├── package.json
└── AGENTS.md                       # Este archivo
```

---

## 6. Convenciones de Código

### Astro Components

- Frontmatter siempre con al menos una variable (Astro 6 rompe con `---\n---` vacío)
- No usar ternarios anidados dentro de atributos SVG `d=` — mover paths a variables del frontmatter
- Imports con path relativo (`../components/...`)

### Estilos

- **Tailwind 4** con `@theme` en `global.css` — tokens MD3 custom
- **Mobile-first** — estilos base para mobile, `md:` y `lg:` para superiores
- **Sin librerías externas** — solo Tailwind utilities
- **Border radius:** `rounded-xl` o `rounded-2xl` (no `rounded-lg`)

### HTML Semántico

```astro
<BaseLayout title="..." description="...">
  <Header activePage="..." />
  <main class="...">
    <section aria-label="...">...</section>
  </main>
  <Footer />
</BaseLayout>
```

### SEO

- `<title>` y `<meta description>` via props de `BaseLayout`
- OG tags automáticos en `BaseLayout` (og:title, og:description, og:image)
- Twitter Card tags incluidos
- `<link rel="canonical">` automático
- `robots.txt` en `/public/robots.txt`

---

## 7. Comandos

```bash
# Frontend público
pnpm dev              # Dev server → http://localhost:4321
pnpm build            # Build estático → dist/
pnpm preview          # Preview del build
pnpm astro check      # Type check (0 errores = saludable)

# Backend PHP
cd api && composer install   # Instalar deps PHP
php -l api/**/*.php          # Syntax check todos los PHP

# Backoffice Vue SPA
cd backoffice && npm install  # Instalar deps Vue
cd backoffice && npm run dev  # Dev server → http://localhost:5173
cd backoffice && npm run build  # Build para producción
```

---

## 8. Roadmap

### ✅ Phase 1 — Frontend Público (Completada)

- [x] Atomic design (atoms, molecules, organisms)
- [x] 8 páginas públicas
- [x] HTML semántico
- [x] Responsive mobile-first
- [x] SEO básico (meta, OG, robots.txt)
- [x] Navegación responsive con hamburger menu

### ✅ Phase 2 — Backoffice con Vue SPA + Backend PHP SOA

- [x] Backend PHP SOA con JWT auth + HTTP-only cookies
- [x] RBAC completo: permisos dinámicos, roles, usuarios
- [x] Security headers, rate limiting, exception handling
- [x] Backoffice Vue SPA con login, dashboard, sidebar
- [x] CRUD de usuarios, roles, permisos con UI completa
- [x] Permissions matrix para roles (toggle por módulo/acción)
- [x] Componentes UI reutilizables: AppModal, AppTable, AppPagination, AppSwitch, AppConfirmDialog, AppToast, AppSkeleton
- [x] Dark theme admin (#0a1022 bg, #69dca4 green, #00A8FF blue)
- [x] `composer install` + `npm install` + build limpio

### 🔜 Phase 3 — Shipping Features

- [ ] Tablas de negocio: clients, shipments, packages, tracking_events
- [ ] Gestión de envíos y tracking en backoffice
- [ ] Gestión de clientes
- [ ] Upload de comprobantes de pago
- [ ] Integración con APIs de tracking
- [ ] Webhooks para notificaciones
- [ ] Pagos con Stripe/PayPal

### 🔜 Phase 4 — Features Avanzados

- [ ] Calculadora de envíos real con API
- [ ] Tracking en tiempo real
- [ ] Notificaciones por email/WhatsApp
- [ ] Multi-idioma (ES/EN)
- [ ] Blog de envíos y logística

---

## 9. Notas para el Desarrollador

### Cómo crear una nueva página

1. Crear `src/pages/nueva-pagina.astro`
2. Importar `BaseLayout`, `Header`, `Footer`
3. Usar componentes existentes de atoms/molecules/organisms
4. Agregar link en `Header.astro` y `Footer.astro`
5. Verificar con `pnpm build`

### Cómo crear un nuevo componente

1. Determinar nivel: atom, molecule, o organism
2. Crear en el directorio correcto (`src/components/...`)
3. Siempre con frontmatter (aunque sea `const x = 1;`)
4. Usar Tailwind utilities — no CSS custom
5. Responsive: base mobile, `md:` para desktop
6. **Checklist pre-commit:**
   - ¿Se reusan atoms existentes (Input, Button, Icon, ScrollReveal)?
   - ¿Los iconos usan `<Icon name="..." />` y no SVG inline?
   - ¿Las interfaces se importan de `src/lib/` en vez de duplicarse?
   - ¿Todos los imports se usan? (verificar con `pnpm astro check`)
   - ¿El componente tiene al menos un consumidor?
   - ¿El build pasa sin errores? (`pnpm build && pnpm astro check`)

### Separación Public vs Backoffice

- **Public:** `src/pages/` — archivos `.astro` estáticos
- **Backoffice (futuro):** `src/pages/backoffice/` — Vue islands
- **Layouts separados:** `BaseLayout.astro` (public) vs `BackofficeLayout.astro` (futuro)
- **CSS separado:** `global.css` (public) vs `backoffice.css` (futuro)

---

> **Creado:** 24/07/2026
> **Última actualización:** Phase 2 completa — CRUD backend + backoffice (25/07/2026)
