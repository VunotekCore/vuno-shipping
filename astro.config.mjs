// @ts-check
import { defineConfig } from 'astro/config';

import tailwindcss from '@tailwindcss/vite';

// https://astro.build/config
export default defineConfig({
  site: 'https://shipping.vunotek.com',
  vite: {
    plugins: [tailwindcss()]
  },
  typescript: {
    check: {
      exclude: ['backoffice/**']
    }
  }
});