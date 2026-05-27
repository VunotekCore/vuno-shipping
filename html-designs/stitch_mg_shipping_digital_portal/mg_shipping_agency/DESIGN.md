---
name: Mg Shipping Agency
colors:
  surface: '#f9f9ff'
  surface-dim: '#cfdaf2'
  surface-bright: '#f9f9ff'
  surface-container-lowest: '#ffffff'
  surface-container-low: '#f0f3ff'
  surface-container: '#e7eeff'
  surface-container-high: '#dee8ff'
  surface-container-highest: '#d8e3fb'
  on-surface: '#111c2d'
  on-surface-variant: '#43474f'
  inverse-surface: '#263143'
  inverse-on-surface: '#ecf1ff'
  outline: '#737780'
  outline-variant: '#c3c6d1'
  surface-tint: '#3a5f94'
  primary: '#001e40'
  on-primary: '#ffffff'
  primary-container: '#003366'
  on-primary-container: '#799dd6'
  inverse-primary: '#a7c8ff'
  secondary: '#904d00'
  on-secondary: '#ffffff'
  secondary-container: '#fd8b00'
  on-secondary-container: '#603100'
  tertiary: '#381300'
  on-tertiary: '#ffffff'
  tertiary-container: '#592300'
  on-tertiary-container: '#d8885c'
  error: '#ba1a1a'
  on-error: '#ffffff'
  error-container: '#ffdad6'
  on-error-container: '#93000a'
  primary-fixed: '#d5e3ff'
  primary-fixed-dim: '#a7c8ff'
  on-primary-fixed: '#001b3c'
  on-primary-fixed-variant: '#1f477b'
  secondary-fixed: '#ffdcc3'
  secondary-fixed-dim: '#ffb77d'
  on-secondary-fixed: '#2f1500'
  on-secondary-fixed-variant: '#6e3900'
  tertiary-fixed: '#ffdbca'
  tertiary-fixed-dim: '#ffb690'
  on-tertiary-fixed: '#341100'
  on-tertiary-fixed-variant: '#723610'
  background: '#f9f9ff'
  on-background: '#111c2d'
  surface-variant: '#d8e3fb'
typography:
  display-lg:
    fontFamily: Montserrat
    fontSize: 48px
    fontWeight: '700'
    lineHeight: 56px
    letterSpacing: -0.02em
  headline-lg:
    fontFamily: Montserrat
    fontSize: 32px
    fontWeight: '700'
    lineHeight: 40px
  headline-lg-mobile:
    fontFamily: Montserrat
    fontSize: 28px
    fontWeight: '700'
    lineHeight: 36px
  headline-md:
    fontFamily: Montserrat
    fontSize: 24px
    fontWeight: '600'
    lineHeight: 32px
  body-lg:
    fontFamily: Inter
    fontSize: 18px
    fontWeight: '400'
    lineHeight: 28px
  body-md:
    fontFamily: Inter
    fontSize: 16px
    fontWeight: '400'
    lineHeight: 24px
  label-md:
    fontFamily: Inter
    fontSize: 14px
    fontWeight: '600'
    lineHeight: 20px
    letterSpacing: 0.05em
  label-sm:
    fontFamily: Inter
    fontSize: 12px
    fontWeight: '500'
    lineHeight: 16px
rounded:
  sm: 0.25rem
  DEFAULT: 0.5rem
  md: 0.75rem
  lg: 1rem
  xl: 1.5rem
  full: 9999px
spacing:
  base: 8px
  xs: 4px
  sm: 12px
  md: 24px
  lg: 48px
  xl: 80px
  gutter: 24px
  margin-mobile: 16px
  container-max: 1200px
---

## Brand & Style
The design system for the agency is built upon the pillars of **Reliability, Speed, and Modern Logistics**. It targets businesses and individuals moving goods between Miami and Nicaragua, requiring a visual language that balances corporate trustworthiness with the kinetic energy of global transport.

The style is **Modern Minimalism**. We utilize generous whitespace to reduce cognitive load during complex shipping processes, paired with precise, high-quality typography. The aesthetic avoids unnecessary decoration, favoring functional clarity and a systematic approach to information hierarchy. Professional photography should feature high-contrast shots of maritime logistics, clean warehouse environments, and the "last-mile" human connection.

## Colors
The palette is rooted in industry-standard signals of trust and action:

*   **Deep Logistics Blue (#003366):** Our primary brand color. It represents stability and professional authority. Used for navigation, primary headers, and core brand moments.
*   **Safety Orange (#FF8C00):** Our action color. It draws the eye to conversion points, tracking status updates, and critical alerts. It should be used sparingly to maintain its impact.
*   **Slate Neutrals:** A range of grays from #F8FAFC (Background) to #1E293B (Typography) ensures high legibility and a sophisticated, non-pure-black contrast.
*   **Functional Colors:** Success (Green), Warning (Amber), and Error (Red) should follow the same saturation levels as the primary palette to maintain system harmony.

## Typography
The typographic system uses a dual-font approach to balance personality and utility.

**Montserrat** is used for headlines to provide a geometric, confident, and modern architectural feel. It conveys the "Modern" aspect of the brand personality. Use bold weights for primary titles to anchor the page.

**Inter** is the workhorse for all body copy, forms, and data-heavy tables. Its tall x-height and neutral character ensure maximum readability for tracking numbers and shipping manifestos. 

Maintain strict vertical rhythm by adhering to the defined line heights. Use `label-md` specifically for category headers and button text to provide a clear functional distinction from narrative text.

## Layout & Spacing
The layout follows a **12-column fluid grid** for desktop and a **4-column grid** for mobile. 

We utilize a "Fixed-Fluid" hybrid model: the main content container caps at 1200px to maintain line-length readability on ultra-wide monitors, while the inner gutters remain fluid to ensure a seamless transition across tablet devices.

**Spacing Rhythm:** All spacing must be a multiple of 8px. 
- Use **48px (lg)** to separate major sections.
- Use **24px (md)** for internal component padding (cards, modals).
- Use **12px (sm)** for tight groupings, such as label-to-input relationships.

## Elevation & Depth
In line with a minimalist aesthetic, depth is created through **Ambient Shadows** and **Tonal Layering** rather than heavy gradients.

*   **Surface Level (0dp):** The main background uses the lightest gray (#F8FAFC) to create a soft canvas.
*   **Elevated Level (1dp):** White (#FFFFFF) cards with a subtle 1px border (#E2E8F0) and a soft, diffused shadow (0px 4px 20px rgba(0, 0, 0, 0.05)). This is used for primary content containers.
*   **Interactive Level (2dp):** On hover, cards or buttons should lift slightly, increasing shadow opacity to 0.10 and shifting 2px upwards to provide tactile feedback.

Avoid using shadows on text or inputs; keep these elements flat to maintain a crisp, professional look.

## Shapes
The shape language is **Softly Structured**. We use a consistent 8px (0.5rem) corner radius for most UI elements to humanize the industrial nature of logistics without appearing too playful or "bubbly."

*   **Buttons & Inputs:** 8px radius.
*   **Cards & Modals:** 16px (1rem) radius for a more prominent, contained feel.
*   **Status Tags:** Fully rounded (pill-shaped) to distinguish them from interactive buttons.

## Components
This design system prioritizes clarity in logistics management.

*   **Buttons:** 
    *   *Primary:* Solid Deep Blue with White text. 
    *   *Secondary:* Safety Orange for "Track Package" or "Get Quote."
    *   *Ghost:* Outlined for secondary actions like "Download Receipt."
*   **Input Fields:** Clean, white backgrounds with 1px slate borders. Focus states should use a 2px Deep Blue glow. Labels are always visible above the field in `label-sm`.
*   **Cards:** Used for tracking updates and service offerings. They feature a 1px border and the "Elevated" shadow profile.
*   **WhatsApp Floating Button:** A fixed-position circular button (Green #25D366) in the bottom-right corner. It should have a subtle "pulse" animation to draw attention without being intrusive.
*   **Tracking Stepper:** A custom vertical or horizontal line component using the Secondary color to show the real-time progress of a shipment between Miami and Nicaragua.
*   **Social Icons:** Monochromatic (Deep Blue) in the footer, shifting to their brand colors only on hover.