---
name: CargoMundo Enterprise Logistics
colors:
  surface: '#f9f9ff'
  surface-dim: '#d0daef'
  surface-bright: '#f9f9ff'
  surface-container-lowest: '#ffffff'
  surface-container-low: '#eff3ff'
  surface-container: '#e6eeff'
  surface-container-high: '#dee9fd'
  surface-container-highest: '#d9e3f7'
  on-surface: '#121c2a'
  on-surface-variant: '#434750'
  inverse-surface: '#273140'
  inverse-on-surface: '#ebf1ff'
  outline: '#737781'
  outline-variant: '#c3c6d1'
  surface-tint: '#355e9c'
  primary: '#00346b'
  on-primary: '#ffffff'
  primary-container: '#1e4b88'
  on-primary-container: '#97bdff'
  inverse-primary: '#a9c7ff'
  secondary: '#006689'
  on-secondary: '#ffffff'
  secondary-container: '#51c7fe'
  on-secondary-container: '#00516e'
  tertiary: '#651a00'
  on-tertiary: '#ffffff'
  tertiary-container: '#8c2700'
  on-tertiary-container: '#ffa488'
  error: '#ba1a1a'
  on-error: '#ffffff'
  error-container: '#ffdad6'
  on-error-container: '#93000a'
  primary-fixed: '#d6e3ff'
  primary-fixed-dim: '#a9c7ff'
  on-primary-fixed: '#001b3d'
  on-primary-fixed-variant: '#174683'
  secondary-fixed: '#c3e8ff'
  secondary-fixed-dim: '#78d1ff'
  on-secondary-fixed: '#001e2c'
  on-secondary-fixed-variant: '#004c68'
  tertiary-fixed: '#ffdbd0'
  tertiary-fixed-dim: '#ffb59e'
  on-tertiary-fixed: '#3a0b00'
  on-tertiary-fixed-variant: '#842500'
  background: '#f9f9ff'
  on-background: '#121c2a'
  surface-variant: '#d9e3f7'
  navy-dark: '#0F294D'
  cyan-muted: '#E0F2FE'
  orange-light: '#FFEDD5'
  surface-bg: '#F8FAFC'
  surface-card: '#FFFFFF'
  border-subtle: '#E2E8F0'
  status-in-transit: '#0284C7'
  status-delivered: '#16A34A'
  status-pending: '#F59E0B'
  status-customs: '#DC2626'
  status-customs-bg: '#FEE2E2'
typography:
  headline-xl:
    fontFamily: Plus Jakarta Sans
    fontSize: 2rem
    fontWeight: '700'
    lineHeight: 2.5rem
    letterSpacing: -0.025em
  headline-xl-mobile:
    fontFamily: Plus Jakarta Sans
    fontSize: 1.5rem
    fontWeight: '700'
    lineHeight: 2rem
    letterSpacing: -0.02em
  headline-lg:
    fontFamily: Plus Jakarta Sans
    fontSize: 1.5rem
    fontWeight: '600'
    lineHeight: 2rem
    letterSpacing: -0.02em
  headline-md:
    fontFamily: Plus Jakarta Sans
    fontSize: 1.25rem
    fontWeight: '600'
    lineHeight: 1.75rem
    letterSpacing: -0.015em
  headline-sm:
    fontFamily: Plus Jakarta Sans
    fontSize: 1.125rem
    fontWeight: '600'
    lineHeight: 1.5rem
  body-lg:
    fontFamily: Plus Jakarta Sans
    fontSize: 1rem
    fontWeight: '400'
    lineHeight: 1.5rem
  body-md:
    fontFamily: Plus Jakarta Sans
    fontSize: 0.875rem
    fontWeight: '400'
    lineHeight: 1.25rem
  body-sm:
    fontFamily: Plus Jakarta Sans
    fontSize: 0.75rem
    fontWeight: '400'
    lineHeight: 1rem
  label-lg:
    fontFamily: Plus Jakarta Sans
    fontSize: 0.875rem
    fontWeight: '600'
    lineHeight: 1.25rem
    letterSpacing: 0.01em
  label-md:
    fontFamily: Plus Jakarta Sans
    fontSize: 0.75rem
    fontWeight: '600'
    lineHeight: 1rem
    letterSpacing: 0.02em
  label-sm:
    fontFamily: Plus Jakarta Sans
    fontSize: 0.6875rem
    fontWeight: '700'
    lineHeight: 0.875rem
    letterSpacing: 0.04em
  mono-data:
    fontFamily: Space Grotesk
    fontSize: 0.8125rem
    fontWeight: '500'
    lineHeight: 1.125rem
    letterSpacing: -0.01em
rounded:
  sm: 0.125rem
  DEFAULT: 0.25rem
  md: 0.375rem
  lg: 0.5rem
  xl: 0.75rem
  full: 9999px
spacing:
  gutter: 1rem
  gutter-desktop: 1.5rem
  margin: 1rem
  margin-desktop: 1.5rem
  space-xxs: 0.125rem
  space-xs: 0.25rem
  space-sm: 0.5rem
  space-md: 0.75rem
  space-lg: 1rem
  space-xl: 1.5rem
  space-2xl: 2rem
  space-3xl: 3rem
---

## Brand & Style

### Personality & Purpose
The design system delivers an enterprise-grade, high-density, and mission-critical backoffice environment tailored for logistics operators, supply chain managers, customs brokers, and customer support agents. The brand persona blends maritime and airfreight reliability with real-time digital velocity. It conveys utmost structural discipline, operational transparency, precision, and robust control over high-volume data streams.

### Visual Style: Corporate Modern / Functional Density
The aesthetic is built on a clean corporate-modern baseline with tactical high-contrast data visualization. It prioritizes scannability, ergonomics for prolonged desk shifts, and structured layout partitioning. 
- **Structure:** Clean tabular structures, sharp data grouping, and high information density without visual clutter.
- **Micro-accents:** Vibrant cyan and energetic logistics orange serve strictly as functional directional markers, alerts, tracking milestones, and status signals.
- **Tactile feedback:** Controlled subtle borders, crisp contrast divisions, and gentle background tiering replace excessive gradients or disruptive decorative styling.

## Colors

### Palette Architecture
The palette is derived directly from brand assets and operational clarity needs:
- **Primary Navy (`#1E4B88` / Deep `#0F294D`):** Anchors primary brand touchpoints, main structural navigation (sidebar headers, primary action buttons, active tab indicators), and prominent enterprise headings.
- **Secondary Cyan (`#1EA7DC` / `#0284C7`):** Represents kinetic movement, tracking progress lines, real-time connectivity indicators, and active selection highlights.
- **Tertiary Orange (`#EB5A28`):** The operational accent. Reserved for expedited shipments, direct attention calls, primary warnings, dispatch triggers, and key numerical metrics requiring immediate action.
- **Neutral Charcoal (`#374151`):** Serves as high-readability body copy, secondary icons, and header controls.

### Functional Logistics Status Tokens
Status tokens maintain strict background/text luminance pairs to ensure WCAG 2.1 AA legibility across dense data tables:
- **En Tránsito:** `#0284C7` text on `#E0F2FE` background.
- **Entregado:** `#16A34A` text on `#DCFCE7` background.
- **Pendiente / En Almacén:** `#D97706` text on `#FEF3C7` background.
- **Aduana / Retenido:** `#DC2626` text on `#FEE2E2` background.

## Typography

### Structural Selection
**Plus Jakarta Sans** acts as the universal brand typeface, providing geometric rhythm, wide counters, and exceptional clarity across both dense alphanumeric shipping data and spacious page headers. 

### Tabular Numbers & Codes
Tracking numbers (AWB, BL, Guías), warehouse container tags, and tariff calculations use the `mono-data` token configured with `Space Grotesk` or OpenType tabular figures (`tnum`). This guarantees uniform vertical alignment in dense operational tables.

## Layout & Spacing

### Shell & Layout Structure
- **Persistent Top App Header (64px fixed height):** Houses branch switcher (Selector de Sucursal), operational global search, environment indicators, alerts badge, and active user profile.
- **Collapsible Master Sidebar:**
  - Expanded: `260px` fixed width with icon, label, chevron, and badge counter.
  - Collapsed: `68px` mini-rail displaying centered tooltip-enabled icons.
- **Main Workspace Canvas:** Fluid flex/grid area filling `calc(100vw - sidebar_width)`. Background is tinted neutral `#F8FAFC` to isolate white card widgets.

### Responsive Breakpoints & Reflow
- **Desktop (≥ 1280px):** 12-column layout. Multi-pane operational split views (e.g., shipments table at 8 cols + selected tracking timeline drawer at 4 cols).
- **Tablet (768px – 1279px):** Sidebar collapses to 68px rail automatically. Tables enforce horizontal scrolling with sticky frozen columns for AWB and actions.
- **Mobile (< 768px):** Sidebar switches to off-canvas slide-over drawer triggered by header hamburger menu. Data tables convert into structured vertical card stacks.

## Elevation & Depth

### Atmospheric Depth & Layering
Rather than excessive, blurred drop shadows that clutter dense data presentation, visual hierarchy relies on **crisp structural borders (`#E2E8F0`)**, **tonal surface separation**, and **controlled directional ambient shadows**:

- **Level 0 (Flat Canvas):** `#F8FAFC` base application canvas.
- **Level 1 (Cards & Data Panels):** `#FFFFFF` fill with `1px solid #E2E8F0` border and `box-shadow: 0 1px 3px 0 rgba(15, 41, 77, 0.05)`.
- **Level 2 (Dropdowns, Branch Selectors & Flyouts):** `#FFFFFF` fill with `1px solid #CBD5E1` border and `box-shadow: 0 4px 12px -1px rgba(15, 41, 77, 0.12)`.
- **Level 3 (Modals, Slide-over Drawers & Alerts):** `#FFFFFF` fill with `box-shadow: 0 20px 25px -5px rgba(15, 41, 77, 0.2), 0 8px 10px -6px rgba(15, 41, 77, 0.1)`. Modals display over a backdrop scrim tinted with `#0F294D` at 45% opacity with 2px backdrop blur.
- **Level 4 (Interactive Tooltips):** `#1F2937` solid fill with high-contrast `#FFFFFF` text, zero blur, and a crisp arrow pip.

## Shapes

### Enterprise Precision Geometry
A roundedness level of `1` (Soft: `0.25rem` / `4px` default base, `0.5rem` / `8px` container radius) is enforced:
- **Buttons, Form Controls & Badges:** `4px` (`0.25rem`) corner radius. This conveys architectural solidity and optimizes vertical and horizontal screen real estate.
- **Cards, Modals & Data Tables:** `8px` (`0.5rem`) outer radius.
- **Chips & Status Pills:** `9999px` (Full pill) for immediate recognition as categorical metadata badges.

## Components

### 1. Buttons
- **Primary:** Solid `#1E4B88` background, `#FFFFFF` text. Hover: `#0F294D`. Active: `#0A1C36`. Focus: 2px ring offset with `#1EA7DC`.
- **Accent / Dispatch:** Solid `#EB5A28` background, `#FFFFFF` text. Hover: `#C94519`. Used strictly for mission-critical events (e.g., "Generar Guía", "Despachar Carga").
- **Secondary / Outline:** `#FFFFFF` background, `1px solid #CBD5E1`, text `#374151`. Hover: `#F1F5F9` background, border `#94A3B8`.
- **Ghost:** Transparent background, text `#1E4B88`. Hover: `#F0F7FF`.
- **Destructive:** Solid `#DC2626` or outline with text `#DC2626`.
- **Sizes:** Compact (28px height, dense tables), Regular (36px height, standard forms), Large (44px height, modals & primary CTA).

### 2. Form Inputs, Selects & Datepickers
- **Text Inputs & Selects:** Height 36px, border `1px solid #CBD5E1`, background `#FFFFFF`, text `#1F2937`. Focus: border `#1EA7DC` with subtle 3px glow (`rgba(30, 167, 220, 0.2)`).
- **Disabled State:** Background `#F1F5F9`, text `#94A3B8`, border `#E2E8F0`, cursor `not-allowed`.
- **Date Range Picker:** Double input trigger with calendar flyout showing dispatch vs. estimated delivery ranges highlighted with `#E0F2FE` spanning ribbon and `#1E4B88` endpoints.
- **Toggle Switches:** 20px height × 36px width. Unchecked: `#CBD5E1`. Checked: `#1E4B88` or `#EB5A28`. Smooth 150ms thumb slide.

### 3. Data Tables (High Density)
- **Table Header (`<thead>`):** Background `#F8FAFC`, uppercase `label-sm` text `#64748B`, border-bottom `2px solid #E2E8F0`. Includes column sort chevrons.
- **Table Rows (`<tbody>`):** Row height 44px (compact) or 52px (standard). Alternating hover effect `#F8FAFC`. Selected row highlighted with `#F0F9FF` and left 3px cyan indicator stripe.
- **Sticky Column:** First column (AWB / Guía) and last column (Quick Actions menu) remain pinned during horizontal table scrolling.
- **Pagination Bar:** Border-top `1px solid #E2E8F0`, includes items per page selector (10, 25, 50, 100), total record counter, and accessible numeric jump controls.

### 4. Status Badges & Chips
- **En Tránsito:** Pill format, `#0284C7` text, `#E0F2FE` background, dot indicator pulsing `#0284C7`.
- **Entregado:** Pill format, `#16A34A` text, `#DCFCE7` background, solid green check icon.
- **Pendiente:** Pill format, `#D97706` text, `#FEF3C7` background, clock icon.
- **Aduana:** Pill format, `#DC2626` text, `#FEE2E2` background, alert icon.

### 5. Header & Navigation Components
- **Branch Selector (Sucursal):** Dropdown embedded in header featuring warehouse pin icon, branch name, code (e.g., `MGA - Managua Central`), and quick switch menu.
- **Collapsible Sidebar Tree:** Multi-tier nested list with chevron disclosure. Active items receive `#1E4B88` background, `#FFFFFF` text, and cyan accent stripe. Sub-items nested by `12px` indent.
- **Logistics Activity Feed / Drawer:** Vertical step timeline displaying timestamp, checkpoint location, operational agent, and status change tags connected by a 2px continuous `#CBD5E1` tracking line.

### 6. Modals & Interactive Tooltips
- **Modals:** Max-width bounds (Small: `480px`, Medium: `640px`, Large / Manifest: `960px`). Standard header with title and close icon, scrollable body area, fixed footer with secondary "Cancelar" and primary "Confirmar" actions.
- **Tooltips:** Dark neutral `#1F2937`, text `#FFFFFF`, font size `0.75rem`, padding `4px 8px`, border radius `4px`, appearing with a 100ms fade on hover.