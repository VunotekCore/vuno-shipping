export interface NavLink {
  href: string;
  label: string;
  id: string;
}

export const navLinks: NavLink[] = [
  { href: '/', label: 'Inicio', id: 'home' },
  { href: '/servicios', label: 'Servicios', id: 'servicios' },
  { href: '/rastreo', label: 'Rastreo', id: 'rastreo' },
  { href: '/nosotros', label: 'Nosotros', id: 'nosotros' },
  { href: '/faq', label: 'FAQ', id: 'faq' },
  { href: '/contacto', label: 'Contacto', id: 'contacto' },
];
