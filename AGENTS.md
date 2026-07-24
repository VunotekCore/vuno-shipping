# 🚚 Shipping Agency — Envíos Miami a Nicaragua

> **Negocio:** Agency de receiving y envío de paquetes desde Miami hasta Nicaragua
> **Modelo:** Usuarios compran en Amazon/eBay/Shein → envían a almacén Miami → Mg consolida y envía a Nicaragua → entrega a domicilio
> **Estado:** Fase 1 completada — Frontend público funcional
> **Stack:** Astro 6 · TypeScript strict · Tailwind 4 · MD3 Design Tokens

---

## 1. Resumen Ejecutivo

Shipping Agency es una plataforma web para un negocio de logística que permite a nicaragüenses comprar en tiendas online de Estados Unidos y recibir sus paquetes en Nicaragua. El cliente compra en Amazon, Shein, eBay, etc., usa la dirección de almacén de Mg en Miami, y Mg consolida y envía todo a Nicaragua.

### Estado Actual

| Componente | Estado |
|-----------|--------|
| Frontend público (Astro) | ✅ 8 páginas funcionales |
| Backoffice (Vue islands) | 🔜 Phase 2 |
| Backend (PHP) | 🔜 Phase 3 |

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
│   ├── Select.astro
│   ├── Badge.astro
│   ├── Icon.astro
│   ├── Card.astro
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
│   ├── FAQItem.astro
│   └── NavLinks.astro
└── organisms/      # Secciones completas de página
    ├── Header.astro
    ├── Footer.astro
    ├── HeroSection.astro
    ├── ProcessSteps.astro
    ├── FeaturesBento.astro
    ├── TrackingStepper.astro
    ├── CTASection.astro
    ├── ServiceHero.astro
    ├── ServiceCards.astro
    ├── ShippingCalculator.astro
    ├── PricingTable.astro
    └── WhatsAppButton.astro
```

### Reglas de Componentes

- **Archivos ≤500 líneas** — Si excede, dividir en subcomponentes
- **HTML semántico** — `<main>`, `<section>`, `<article>`, `<aside>`, `<header>`, `<footer>`, `<nav>`
- **Sin dependencias externas** — Solo Tailwind CSS (sin shadcn, sin React, sin Vue en front)
- **Iconos inline** — Material Symbols via SVG inline (no font icons)

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
│   ├── pages/
│   │   ├── index.astro             # Home
│   │   ├── servicios.astro         # Servicios
│   │   ├── contacto.astro          # Contacto
│   │   ├── rastreo.astro           # Rastreo de paquetes
│   │   ├── nosotros.astro          # Sobre nosotros
│   │   ├── faq.astro               # Preguntas frecuentes
│   │   ├── privacidad.astro        # Política de privacidad
│   │   └── terminos.astro          # Términos de servicio
│   ├── styles/
│   │   └── global.css              # Tailwind @theme + MD3 tokens + breakpoints
│   └── env.d.ts
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
pnpm dev              # Dev server → http://localhost:4321
pnpm build            # Build estático → dist/
pnpm preview          # Preview del build
pnpm astro check      # Type check (0 errores = saludable)
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

### 🔜 Phase 2 — Backoffice con Vue Islands

- [ ] Dashboard admin con estadísticas
- [ ] Gestión de envíos y tracking
- [ ] Gestión de clientes
- [ ] Formularios con validación Vue
- [ ] Upload de comprobantes de pago

### 🔜 Phase 3 — Backend PHP

- [ ] API REST para datos de envíos
- [ ] Base de datos (MySQL/PostgreSQL)
- [ ] Autenticación JWT
- [ ] Integración con APIs de tracking
- [ ] Webhooks para notificaciones
- [ ] Pagos con Stripe/PayPal

### 🔜 Phase 4 — Features

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

### Separación Public vs Backoffice

- **Public:** `src/pages/` — archivos `.astro` estáticos
- **Backoffice (futuro):** `src/pages/backoffice/` — Vue islands
- **Layouts separados:** `BaseLayout.astro` (public) vs `BackofficeLayout.astro` (futuro)
- **CSS separado:** `global.css` (public) vs `backoffice.css` (futuro)

---

> **Creado:** 24/07/2026
> **Última actualización:** Phase 1 completada
