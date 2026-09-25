# Guía de Prueba — Despacho y Distribución (Phase 4)

> Prueba física paso a paso para cubrir: Admisión con geolocalización, ruta maestra,
> despacho diario, asignación de paquetes, generación de paradas, optimización OSRM,
> estados de entrega y carry-forward al día siguiente.

---

## 0. Preparación

1. **Backend PHP** corriendo en `:8000`:
   ```bash
   cd api && php -S localhost:8000 -t api
   ```
2. **Backoffice** corriendo en `:5173`:
   ```bash
   cd backoffice && npm run dev
   ```
3. **Login**: `http://localhost:5173/backoffice/login`
   - Admin: `admin@vunotek.com` / `admin123` (sin `$`).
   - Conductor: `jdoe@vunotek.com` (si no se recuerda la clave, resetearla en `/users` con el admin).

**Estado inicial esperado (tras la limpieza de 2026-09-19):**
- Ruta maestra **"Ruta Smoke Sur"** → zonas **Managua (1) → Masaya (5) → Granada (4)**, conductor por defecto `jhon Doe`, vehículo `M-10001`.
- **0 paquetes, 0 despachos, 0 paradas.**
- Cliente de prueba: `cliente test`.

---

## 1. Cómo funciona el modelo (resumen)

- **Paquete**: se crea en `/admisión`. La **zona se fija al geolocalizar** la dirección
  (mini-mapa Leaflet + búsqueda Nominatim). Sin coordenadas → `zone_id NULL` → **nunca
  aparece** como candidato. Estado inicial `pending` (o `received` en bodega).
- **Distribución**: un paquete solo aparece en rutas cuyo conjunto de **zonas contiene la
  zona del paquete**. El filtro de candidatos exige: `zone_id ∈ zonas de la ruta`, estado
  `pending`/`received` y **sin parada previa** en el despacho.
- **Ruta maestra** (`delivery_route`): plantilla geográfica. Zonas **ordenadas de visita**
  (1ª = inicio, última = fin), trayectoria derivada de los centros de zona, conductor y
  vehículo por defecto.
- **Despacho diario** (`route_run`): instancia del día. Se crea con **"Despacho de hoy"**;
  copia los defaults de la ruta y **arrastra (carry-forward, ↻) las paradas no entregadas**
  del run anterior de la misma ruta (`pending`/`skipped`/`failed`), conservando la observación.
  Las paradas `visited` **no** se arrastran.
- **Asignar paquetes + Generar paradas**: manual del despachador. Selecciona candidatos →
  crea las stops `pending` ordenadas 1..n.
- **Optimizar (OSRM)**: reordena **solo paradas `pending` con coordenadas** (mínimo 2). Si
  OSRM no enruta alguna coordenada (ej. islas), se usa el orden original (fallback).
- **Estados del run**: `draft` (planificación/asignación) → `active` (en curso; solo aquí se
  registran entregas/acciones) → `completed` (finalizada; se setea `end_time`) → `cancelled` (cancelada).
El run lo **inicia el conductor asignado** (botón "Iniciar jornada", `draft → active`) o el admin.
   Se **completa explícitamente** (botón "Terminar jornada" del conductor o "Completar" del admin)
   solo si quedan **0 paradas pendientes**; ya no se auto-completa al entregar la última parada.
- **Estados de parada**: `pending → visited` (entregada); `skipped`/`failed` (con observación
  obligatoria, se arrastran al día siguiente). Todas las acciones de parada exigen el run en `active`.

---

## 2. Escenario 1 — Admisión con geolocalización (clave)

1. Ir a `/admisión` → crear o seleccionar el cliente (`cliente test`).
2. Crear un paquete con dirección real en **Managua**.
3. En el mini-mapa, **geolocalizar** la dirección (búsqueda o clic) y **verificar que la
   zona quedó "Managua"** antes de guardar. Guardar (queda `pending`).
4. Repetir creando **2 paquetes en Masaya** y **2 en Granada** → mínimo **5 paquetes**
   distribuidos en las 3 zonas de la ruta (la zona se vuelve a fijar en cada geolocalización).

**Check**: en `/packages` todos deben tener zona y estado `pending`.

---

## 3. Escenario 2 — Ruta maestra y despacho de hoy

1. `/routes` → **Editar** "Ruta Smoke Sur":
   - Las **3 zonas deben aparecer marcadas y ordenadas** (Managua 1º, Masaya 2º, Granada 3º)
     — bug corregido (antes se perdían al editar porque el listado no traía `zones`).
   - La trayectoria se ve en el mapa con los marcadores numerados por zona.
   - Verificar conductor por defecto `jhon Doe` y vehículo `M-10001`. Cancelar.
2. Botón **"Despacho de hoy"** → crea el run y navega a `/runs/:id`.
3. Verificar en el detalle: estado `draft`, conductor y vehículo copiados, **0 paradas**.

**Check**: en `/runs` aparece un despacho con fecha de hoy, estado `draft`, 0 pendientes.

---

## 4. Escenario 3 — Asignar paquetes y generar paradas

1. En el detalle del run, **"Asignar paquetes"** → deben aparecer **los 5 paquetes** creados
   en zonas 1/5/4 (los de otras zonas NO aparecen).
2. Seleccionar todos → **"Generar paradas"**.
3. Verificar la tabla: orden 1..n, estado `pending`, con dirección y tracking.

**Check**: en el mapa aparecen marcadores amarillos (pendientes) en Managua/Masaya/Granada
y los círculos de cobertura por zona en el color de la ruta.

---

## 5. Escenario 4 — Optimizar ruta (OSRM)

1. **"Optimizar ruta (OSRM)"** → debe estar **habilitado** (hay ≥2 paradas `pending` con coordenadas).
2. Verificar: ruta óptima **azul punteada** sobre el mapa; el orden puede cambiar.

**Check**: si una coordenada no es enrutable (isla/sin carretera), la app usa el orden
original y avisa (fallback, no es error).

---

## 6. Escenario 5 — Iniciar jornada, simular entrega y estados

> **Regla de validación:** las entregas y acciones de parada (✔/→/✕, firma, foto) SOLO se
> permiten con el despacho en `active`. Con el run en `draft` el backend responde 409. Por
> eso el paso 1 es iniciar la jornada.

1. **Iniciar la jornada** (`draft → active`):
   - Admin: botón **"→ active"** en el detalle del despacho (`/runs/:id`).
   - Conductor: botón **"Iniciar jornada"** en `/conductor` (solo el conductor asignado).
   - Se setea `start_time`.
2. Marcar 1 parada **✔ Visitar** (`visited`).
3. Marcar 1 parada **→ Saltar** (`skipped`) con **observación** (ej. "Cliente no estaba").
4. Marcar 1 parada **✕ Fallar** (`failed`) con **observación**.
5. Dejar **2 paradas pendientes** (para probar el arrastre).
6. Finalizar **→ completed** solo si quedan **0** paradas pendientes (`pending` impide el
   completado; `skipped`/`failed` NO lo impiden). Mecanismo único: botón **"Terminar jornada"**
   del conductor asignado (en `/conductor`, visible solo con `pending_stops = 0`) o botón
   **"Completar"** del admin en `/runs/:id`. NO hay auto-complete al entregar la última parada.

**Check**: en el mapa, paradas verde (visitada), gris (omitida/fallada), amarillo (pendiente);
en la tabla se ven las observaciones.

---

## 7. Escenario 6 — Día siguiente / carry-forward

1. `/routes` → **"Despacho de hoy"** otra vez (misma ruta).
2. En el nuevo run verificar que se **arrastraron las paradas no entregadas**
   (`2 pendientes + skipped + failed`) con **↻ arrastrado** y **observación conservada**.
   Las paradas `visited` **NO** se arrastran.

**Check**: 4 paradas con `carried_over=1`, estado `pending`, notas presentes.

---

## 8. Escenario 7 — Conductor (PWA)

1. Entrar a `http://localhost:5173/conductor` con `jdoe@vunotek.com`.
2. Si el run está `draft`: presionar **"Iniciar jornada"** (pasa a `active`).
3. Verificar: ruta asignada (run `active`), progreso/estadísticas del día.
4. Mapa conductor: stops a entregar + trayectoria recorrida naranja + GPS en vivo.
   Botón **"Re-centrar ruta"** para volver al encuadre de la ruta si perdés el zoom.
5. Detalle de parada → **Entregar** (firma digital + foto evidencia) u **Omitir** /
   **No se pudo entregar** (observación obligatoria; se arrastran al día siguiente).
6. Historial del día actualizado.
7. Al terminar todas las paradas (resueltas como visitada/skipped/failed → `pending_stops = 0`),
   aparece el botón **"Terminar jornada"** → cierra el run a `completed` y setea `end_time`.
   Con paradas pendientes el botón queda deshabilitado "Quedan X paradas pendientes".

---

## 9. Errores comunes y su causa

| Síntoma | Causa |
|---|---|
| Modal "Asignar paquetes" vacío | No hay paquetes `pending`/`received` en las **zonas de la ruta**, o todos ya tienen parada en el run. Revisar la zona al geolocalizar en Admisión. |
| "Error al optimizar" | <2 paradas `pending` con coordenadas (botón ahora deshabilitado + aviso). No es falla de OSRM. |
| Publicar/editar da "Token CSRF inválido" (403) | Cookie CSRF vencida (1h) vs JWT (24h). **Corregido**: cookie ahora dura 24h + el frontend se auto-recupera refrescando la sesión ante 403. Si ocurre tras la corrección, recargar la página (el `verify` regenera el token). |
| Paquete con dirección de la costa atlántica en una ruta del Pacífico | El paquete se geolocalizó/guardó con otra zona. La ruta Sur solo acepta zonas 1/5/4. |
| Mapa en blanco al entrar a "/conductor/ruta" o al volver al detalle del despacho | Bug de init: el mapa se inicializaba cuando el contenedor aún no estaba montado (`loading=true`). **Corregido**: se inicializa después del render + `invalidateSize` + `ResizeObserver` + botón "Re-centrar ruta". |
| Entregar/accionar parada da 409 "El despacho debe estar en estado active" | Comportamiento esperado: registros de parada solo con el run en curso. Iniciá la jornada (conductor/admin) primero. |
| "Terminar jornada" deshabilitado / 409 con pendientes | Correcto: el run solo se completa con `pending_stops = 0`. Resolvé las paradas restantes (entregada/skipped/failed) y reintentá. No hay auto-complete al entregar la última parada. |

---

## 10. Nota técnica — validación del bug CSRF

- **Síntoma**: `POST /package/create.php → 403` con sesión activa (>1h de uso continuo).
- **Causa raíz**: `generateCsrfToken()` fijaba expiración de la cookie en 3600s; el JWT dura
  86400s. Al vencer la cookie, todo POST/PUT/DELETE devolvía 403 y el frontend (que solo
  refresca ante 401) no regeneraba el token.
- **Fix**: cookie CSRF ahora expira junto al JWT (86400) + interceptor del backoffice que,
  ante 403 "Token CSRF inválido", renueva la sesión vía `/admin/refresh.php` y reintenta 1 vez.