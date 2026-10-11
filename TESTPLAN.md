# Plan de Pruebas — Módulos Fase 3.6 / 4

> **Rama:** `feature/cobros-facturacion` (base `develop` = `84de6ad`, +16 commits)
> **Entorno de prueba:** local (`localhost`) — nunca producción.
> **Objetivo:** validar los 8 módulos antes de integrar a `develop`.
> **Criterio de salida:** suites API A–H en verde **y** checklist UI aprobado. Recién entonces merge/PR a `develop`.

---

## 1. Alcance

| Módulo | Descripción | Backend | UI |
|--------|-------------|:-------:|:--:|
| **A** | Facturación y cobros (factura automática, IVA, pago USD/C$, métodos, anulación, impresión) | ✅ | ✅ |
| **B** | Fixes de UI y Admisión (modales con mapa, firma, 410 en edición de paquetes, etiquetas, RUC, destinatario) | ✅ | ✅ |
| **C** | Vehículos: estado + kilometraje (odómetro, correcciones, historial, km de jornada) | ✅ | ✅ |
| **D** | Cotizaciones / vendedores / comisiones (CRUD, transiciones, conversión, snapshot de comisión) | ✅ | ✅ |
| **E** | Recolección (pickup vehicle) en cotizaciones y su copia al paquete | ✅ | ✅ |
| **F** | Transversal: RBAC, CSRF, rate-limit, endpoints deprecados | ✅ | — |
| **G** | Embarques: transferencia internacional Miami → Nicaragua (draft → in_transit → arrived) | ✅ | — |
| **H** | Despacho local: admisión local → candidatos por zona → paradas → entrega → completar | ✅ | — |

---

## 2. Prerequisitos

```bash
# 1. Base de datos (usar credenciales de api/.env)
mysql -h127.0.0.1 -udail -p vuno_db_shipping < api/migrations/018_vehicle_status_km.sql  # idempotente

# 2. Backend
php -S localhost:8000 -t api        # http://localhost:8000

# 3. Backoffice
cd backoffice && npm install && npm run dev   # http://localhost:5173/backoffice/
```

### Usuarios de prueba (reset a `Test1234!`, bcrypt cost 12)

| Email | Rol | Uso en pruebas |
|-------|-----|----------------|
| `admin@vunotek.com` | admin | Todos los permisos (`vehicle.*` incl. `change_status`, `edit_km`, `km_correction`, `km_supervisor`) |
| `vendedor@vunotek.com` | vendedor | Cotizaciones (sin `invoice.*`/`quote.delete`) |
| `operador@vunotek.com` | operador | RBAC negativo (403 en vehículos/facturas/cotizaciones) |
| `jdoe@vunotek.com` | conductor | Solo `vehicle.edit_km`; usado para validar segregación de km de ruta (409) |

### Datos base

- Vehículos `M-10001` (id 2) y `M-10002` (id 3).
- Cliente con RUC (id 6).
- `pricing_rule` #1 = *Aéreo por Peso* (`delivery_type='aereo'`, $7/kg, mínimo $5, divisor volumétrico 166) → cotiza/factura con cualquier zona.
- Despacho `route_run` sobre ruta id 4, conductor id 5, vehículo id 2.

---

## 3. Suites API automatizadas

Script: `scripts/test-api.sh` (helpers `req`, `login`, suites A/C/D/E/F/G/H). Limpia el store de rate-limit al arrancar y, al terminar, corre **self-cleanup** (`scripts/cleanup-test-data.sql`) con las credenciales de `api/.env`.

```bash
scripts/test-api.sh            # todas (A,C,D,E,F,G,H)
scripts/test-api.sh A          # solo facturación
scripts/test-api.sh G H        # varias suites
SKIP_CLEANUP=1 scripts/test-api.sh   # conservar los datos de prueba
```

> **Correo en dev:** `MAIL_ENABLED=false` (en `api/.env`) hace que `MailService`
> omita el envío real y solo registre un WARNING en el log
> (`sys_get_temp_dir()/shipping-api-log`). Las suites crean paquetes y disparan
> notificaciones, así que este flag evita correos reales a los clientes.

### Resultado de la última corrida

| Suite | Assertions | Resultado |
|-------|:----------:|:---------:|
| Auth | 8 | ✅ |
| A — Facturación | 32 | ✅ |
| C — Vehículos | 17 | ✅ |
| D — Cotizaciones | 22 | ✅ |
| E — Recolección | 8 | ✅ |
| F — Transversal | 6 | ✅ |
| G — Embarques | 20 | ✅ |
| H — Despacho local | 15 | ✅ |
| **Total** | **128** | **✅ 128 PASS / 0 FAIL** |

<details>
<summary><strong>Cobertura por suite</strong></summary>

**A — Facturación / Cobros**
- Factura automática `FAC-XXXX` al admitir; total = base + IVA; RUC del cliente.
- Listado, detalle, cobro en USD y en C$ (usa `exchange_rate`).
- Recobrar/ anular factura pagada → 409; anular pendiente OK.
- Impresión HTML (contiene el número).
- CRUD de métodos de pago; borrar método con cobros → 409.
- `billing_config` get/update; RBAC (vendedor/operador → 403); POST sin CSRF → 403.

**C — Vehículos**
- Crear despacho draft → `active` (vehículo pasa a `en_ruta`) → `completed` (vuelve a `disponible`).
- `km-register`: `inicio_ruta` por supervisor OK; **misma lectura por el conductor asignado → 409** (segregación).
- Bajar km sin `km_correction` → 422; con permiso + notas OK; sin justificación → 422.
- `km-log` devuelve el historial ordenado.
- `vehicle/update` con `status` requiere `change_status`; RBAC negativo (operador → 403).

**D — Cotizaciones**
- Crear (vendedor), numeración `COT-XXXX`, total, estado `draft`.
- Listado aislado por agente; operador → 403.
- `draft → sent → converted`; conversión genera paquete(s) y **snapshot de comisión** (`percent`, monto calculado).
- Reconvertir → 409; editar convertida → 409; borrar sin permiso → 403; borrar convertida → 409.
- Stats por vendedor (incluye comisiones); expiración automática (`expireOverdue`).

**E — Recolección (pickup)**
- Cotización con `pickup_vehicle_id` guarda el id y devuelve la placa.
- Vehículo inexistente → 422.
- Al convertir, el paquete hereda `pickup_required`.

**F — Transversal**
- Endpoint protegido sin sesión → 401.
- RBAC cruzado (vendedor→invoice 403, conductor→quote 403).
- POST sin CSRF → 403; `package/update` → 410 Gone; rate-limit de login → 429.

**G — Embarques**
- Paquete `online` admite en `received`; `eligible-packages` solo lista recibidos.
- Crear embarque (draft) → agregar paquetes (`package_count`) → detalle con paquetes.
- Despachar embarque vacío → 422; `dispatch` pasa paquetes `received→in_transit`; `arrive` → `in_nicaragua`.
- `add-packages` a embarque en tránsito → 409; vendedor `shipment/create` → 403.

**H — Despacho local**
- Paquete `origin=local` admite en `in_nicaragua`.
- Crear despacho draft → candidatos por zona incluyen el paquete local → `generate-stops` lo pasa a `out_for_delivery`.
- `generate-stops` en run no-draft → 409.
- Entregar la parada → paquete `delivered` → completar el despacho (libera el vehículo).

</details>

---

## 4. Checklist de pruebas UI (manual)

> Levantar el backoffice (`http://localhost:5173/backoffice/`) y loguearse con cada rol según corresponda.

### A — Facturación y cobros
- [ ] **Admisión** (`/admision`): admitir paquete → se genera `FAC-XXXX` y el botón "Ver factura" aparece.
- [ ] **Facturas** (`/facturas`): summary cards (por cobrar / cobrado hoy), filtro por estado, tabla en desktop / cards en mobile.
- [ ] Cobrar factura en **USD** y en **C$** (el total se convierte por `exchange_rate`); verificar método + referencia.
- [ ] Anular factura pendiente; intentar anular una pagada (debe impedirse).
- [ ] **Imprimir** factura (HTML) — verificar datos de empresa, IVA opcional y moneda de cobro.
- [ ] **Métodos de pago**: crear/editar; intentar borrar uno con cobros (bloqueado).
- [ ] **Empresa → tab Facturación**: activar/desactivar IVA, RUC cliente, tipo de cambio; verificar que el prefijo se edita solo desde Secuencias.

### B — Fixes UI / Admisión
- [ ] Modales con **mapa** no se cierran al hacer click fuera.
- [ ] Firma del conductor no tiene desfase del puntero (PNG acotado).
- [ ] Edición de paquete deshabilitada (410) — la UI no expone "editar paquete obsoleto".
- [ ] Etiquetas "Compras EEUU" / "Ingreso Local" correctas en Admisión.
- [ ] RUC input con validación/regx; switch "rellenar datos del cliente" en sección destinatario.

### C — Vehículos (kilometraje + estado)
- [ ] **Vehículos** (`/vehiculos`): botón **"Registrar km"** visible solo con permiso `edit_km`/`km_supervisor`.
- [ ] Registrar lectura (manual/mantenimiento/etc.) → actualiza `current_km`.
- [ ] Registrar lectura **menor** al actual: sin permiso de corrección → error visible; con permiso y **sin notas** → error; con notas → OK.
- [ ] Botón **"Historial"** → modal con lecturas (km, variación, tipo, usuario, fecha, notas).
- [ ] **RunDetail** (`/runs/:id`): iniciar despacho (`draft→active`) pide **km de inicio**; completar (`active→completed`) pide **km de fin**; cancelar aborta el cambio de estado.
- [ ] El conductor (PWA) **no** registra km de ruta (solo cambia estado).

### D — Cotizaciones / Comisiones
- [ ] **Cotizaciones** (`/cotizaciones`): vendedor solo ve las suyas; crear/editar/enviar.
- [ ] Convertir a paquete (genera paquete + comisión); reconvertir/editar convertida bloqueado.
- [ ] **Stats**: KPIs de comisiones por vendedor.
- [ ] Cotizaciones vencidas pasan a `expired` automáticamente.

### E — Recolección
- [ ] Cotización con **recolección** activa + selector de vehículo; se muestra la placa.
- [ ] Al convertir, el paquete resultante conserva `pickup_required` (visible en detalle del paquete).

### F — Transversal
- [ ] Sin sesión, cualquier endpoint protegido redirige/401.
- [ ] Rate-limit de login (varios intentos fallidos → 429).
- [ ] CSRF: peticiones mutating sin token → bloqueadas (403).

---

## 5. Hallazgos y correcciones

| # | Hallazgo | Resolución |
|---|----------|------------|
| 1 | `vehicle.change_status` existía en `seed.sql` pero **faltaba en la BD viva** → editar cualquier vehículo (el form siempre envía `status`) devolvía 403. | Sembrado el permiso y asignado a admin. Revisar que `seed.sql` se aplique completo en despliegues futuros. |
| 2 | El conductor asignado podía registrarse a sí mismo el km de inicio/fin de ruta. | Regla de segregación (409) en `VehicleModel::registerOdometerReading`; validada en suite C. |
| 3 | Corrección de km hacia abajo sin justificación. | Requiere `km_correction` + `notes` (422 si falta). |
| 4 | Las suites enviaban **correos reales** por SMTP de producción al admitir paquetes/disparar notificaciones (clientes 2 y 6 → `dflores2t@gmail.com`). | Flag `MAIL_ENABLED` (default `true`); `false` en dev → `MailService` solo loguea WARNING y no envía. |
| 5 | Las corridas dejaban **datos de prueba** (paquetes, facturas, cotizaciones, despachos) que contaminaban el entorno. | `scripts/cleanup-test-data.sql` + self-cleanup automático al final de `test-api.sh`. |

---

## 6. Limpieza de datos de prueba

`scripts/cleanup-test-data.sql` (solo dev, idempotente) borra los artefactos de
las suites y restaura el estado base:

- Paquetes y cotizaciones de los **clientes de prueba (2 y 6)** creados hoy
  (las cascadas eliminan facturas y eventos de tracking).
- Embarques con código `QATEST-*` y despachos con `run_date >= '2027-01-01'`.
- Lecturas de odómetro y notificaciones in-app de hoy; vehículo id 2 vuelve a
  `current_km=45000` y `status='disponible'`.
- Re-sincroniza `document_sequence` (`invoice`, `quote`) con las filas restantes.

```bash
# Manual (credenciales de api/.env):
mysql -h<host> -u<user> -p<pass> <db> < scripts/cleanup-test-data.sql
```

---

## 7. Comandos de verificación

```bash
# Backend — sintaxis PHP
php -l api/Controllers/VehicleController.php
php -l api/Models/VehicleModel.php

# Frontend — tipos + build
cd backoffice && npm run build      # vue-tsc -b && vite build

# API — suites (crea datos, corre self-cleanup)
./scripts/test-api.sh

# Limpieza manual de datos de prueba
mysql -h127.0.0.1 -udail -p vuno_db_shipping < scripts/cleanup-test-data.sql
```

---

## 8. Criterio de integración

1. `./scripts/test-api.sh` → **0 FAIL**.
2. Checklist UI (§4) aprobado por el responsable.
3. Revisión de `git diff develop..HEAD` y commits atómicos.
4. **Con aprobación explícita** → merge/PR a `develop`.
