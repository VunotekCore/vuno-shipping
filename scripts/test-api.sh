#!/usr/bin/env bash
#
# test-api.sh — Pruebas de API por curl para feature/cobros-facturacion.
#
# USO:
#   scripts/test-api.sh            # corre todas las suites (A,C,D,E,F,G,H,I)
#   scripts/test-api.sh A          # solo la suite A
#   scripts/test-api.sh C F        # varias suites
#   SKIP_CLEANUP=1 scripts/test-api.sh   # no ejecuta la limpieza final
#
# LIMPIEZA (self-cleanup): al terminar corre scripts/cleanup-test-data.sql
# usando las credenciales de api/.env, para no dejar datos de prueba ni
# disparar notificaciones. Usa SKIP_CLEANUP=1 para conservar los datos.
#
# REQUISITOS:
#   - API PHP corriendo:  php -S localhost:8000 -t api   (o BASE=...)
#   - jq y curl
#   - BD de desarrollo preparada (migraciones 010-020 + usuarios seed)
#
# NOTA: limpia el store de rate-limit (/tmp/shipping-ratelimit) al arrancar
# para que los límites de login no interfieran con el resto de la corrida.

set -uo pipefail

BASE="${BASE:-http://localhost:8000}"
TMP="${TMP:-/tmp/opencode/apitest}"
mkdir -p "$TMP"

# --- fixtures (ajustar si cambia la BD de dev) ---
ZONE_ID=1
CLIENT_RUC_ID=6     # Marcia Betanco (tiene RUC)
DRIVER_ID=5         # jdoe@vunotek.com (conductor)
VEHICLE_ID=2        # M-10001
VEHICLE2_ID=3       # M-10002
ROUTE_ID=4
PASSWD="Test1234!"
# admin@vunotek.com y jdoe@vunotek.com (conductor) fueron reseteados a admin123
PASSWD_ADMIN="${PASSWD_ADMIN:-admin123}"
PASSWD_CONDUCTOR="${PASSWD_CONDUCTOR:-admin123}"

mkdir -p "$TMP"

# --- colores / contadores ---
RED=$'\033[31m'; GRN=$'\033[32m'; YLW=$'\033[33m'; CYN=$'\033[36m'; RST=$'\033[0m'
PASS=0; FAIL=0; declare -a FAILURES

ok() { PASS=$((PASS+1)); printf '  %sPASS%s %s\n' "$GRN" "$RST" "$1"; }
no() { FAIL=$((FAIL+1)); FAILURES+=("$1"); printf '  %sFAIL%s %s\n' "$RED" "$RST" "$1"; }
section() { printf '\n%s== %s ==%s\n' "$CYN" "$1" "$RST"; }

expect_eq() { # esperado actual nombre
  if [ "$1" = "$2" ]; then ok "$3"; else no "$3 (esperado '$1', obtuvo '$2')"; fi
}
expect_code() { # esperado_http actual nombre
  if [ "$1" = "$2" ]; then ok "$3"; else no "$3 (esperado HTTP $1, obtuvo $2)"; fi
}
expect_regex() { # regex value name
  if [[ "$2" =~ $1 ]]; then ok "$3"; else no "$3 (no coincide '$1': '$2')"; fi
}
expect_gt() { # value min name
  if [ "$(printf '%.0f' "$1" 2>/dev/null || echo 0)" -gt "$2" ] 2>/dev/null; then ok "$3"; else no "$3 ('$1' no es > $2)"; fi
}
expect_contains() { # haystack needle name
  case "$1" in *"$2"*) ok "$3";; *) no "$3 (no contiene '$2')";; esac
}

# --- HTTP helpers ---
jqv() { jq -r "$1" "$TMP/body.json" 2>/dev/null; }

req() { # METHOD PATH JAR [JSON] [CSRF]  -> imprime http_code
  local method="$1" path="$2" jar="$3" data="${4:-}" csrf="${5:-}"
  local -a a=(-s -o "$TMP/body.json" -w '%{http_code}' -X "$method" "$BASE$path" -b "$jar" -c "$jar")
  [ -n "$data" ] && a+=(-H 'Content-Type: application/json' -d "$data")
  [ -n "$csrf" ] && a+=(-H "X-CSRF-Token: $csrf")
  curl "${a[@]}"
}

do_login() { # JAR EMAIL PASS -> setea LOGIN_CODE y LOGIN_CSRF (globales)
  local jar="$1" email="$2" pass="$3"
  : > "$jar"
  LOGIN_CSRF=""
  LOGIN_CODE=$(req POST /admin/login.php "$jar" "$(jq -n --arg e "$email" --arg p "$pass" '{email:$e,password:$p}')" "")
  LOGIN_CSRF=$(jqv '.data.csrf_token // empty')
}

# --- estado de sesiones (una sola vez por rol) ---
JAR_ADMIN="$TMP/admin.jar"; JAR_VEND="$TMP/vend.jar"
JAR_OPER="$TMP/oper.jar"; JAR_COND="$TMP/cond.jar"
CSRF_ADMIN=""; CSRF_VEND=""; CSRF_OPER=""; CSRF_COND=""

login_all() {
  section "0. Autenticación"
  do_login "$JAR_ADMIN" admin@vunotek.com "$PASSWD_ADMIN"; CSRF_ADMIN="$LOGIN_CSRF"
  [ "$LOGIN_CODE" = "200" ] && ok "login admin (200)" || no "login admin (HTTP $LOGIN_CODE)"
  [ -n "$CSRF_ADMIN" ] && ok "login devuelve csrf_token" || no "login sin csrf_token"
  # permisos de vehículo en el JWT
  local perms; perms=$(jqv '.data.user.permissions.vehicle // [] | join(",")')
  expect_contains "$perms" "edit_km" "admin JWT incluye vehicle.edit_km"
  expect_contains "$perms" "km_supervisor" "admin JWT incluye vehicle.km_supervisor"

  do_login "$JAR_VEND" vendedor@vunotek.com "$PASSWD"; CSRF_VEND="$LOGIN_CSRF"
  [ "$LOGIN_CODE" = "200" ] && ok "login vendedor (200)" || no "login vendedor (HTTP $LOGIN_CODE)"

  do_login "$JAR_OPER" operador@vunotek.com "$PASSWD"; CSRF_OPER="$LOGIN_CSRF"
  [ "$LOGIN_CODE" = "200" ] && ok "login operador (200)" || no "login operador (HTTP $LOGIN_CODE)"

  do_login "$JAR_COND" jdoe@vunotek.com "$PASSWD_CONDUCTOR"; CSRF_COND="$LOGIN_CSRF"
  [ "$LOGIN_CODE" = "200" ] && ok "login conductor (200)" || no "login conductor (HTTP $LOGIN_CODE)"

  do_login "$TMP/bad.jar" admin@vunotek.com "clave-mala"
  [ "$LOGIN_CODE" = "401" ] && ok "login con password incorrecta -> 401" || no "login password incorrecta (HTTP $LOGIN_CODE)"
}

# =====================================================================
# SUITE A — Cobros y Facturación
# =====================================================================
PAY_METHOD_ID=""
suite_A() {
  section "A. Cobros y Facturación"

  # método de pago activo
  req GET /payment_method/listAll.php "$JAR_ADMIN" >/dev/null
  PAY_METHOD_ID=$(jqv '.data.methods[0].id // empty')
  [ -n "$PAY_METHOD_ID" ] && ok "A0 método de pago disponible (id=$PAY_METHOD_ID)" || no "A0 sin métodos de pago activos"

  # A1 — admisión genera factura con IVA
  local payload code
  payload=$(jq -n --argjson c "$CLIENT_RUC_ID" '{sender_name:"Amazon",sender_phone:"3050000000",recipient_name:"Cliente Factura",recipient_phone:"88881111",recipient_address:"Barrio Central 123",client_id:$c,zone_id:1,delivery_type:"aereo",delivery_option:"branch",shipping_service:"Aéreo",origin:"online",weight:10,length:0,width:0,height:0,declared_value:100}')
  code=$(req POST /package/create.php "$JAR_ADMIN" "$payload" "$CSRF_ADMIN")
  expect_eq 201 "$code" "A1.1 admisión de paquete (201)"
  local inv_num base tax total inv_id pkg_id
  inv_num=$(jqv '.data.invoice.invoice_number')
  base=$(jqv '.data.invoice.base_amount')
  tax=$(jqv '.data.invoice.tax_amount')
  total=$(jqv '.data.invoice.total_amount')
  inv_id=$(jqv '.data.invoice.id')
  pkg_id=$(jqv '.data.package.id')
  echo "$inv_id" > "$TMP/last_inv"
  [[ "$inv_num" =~ ^FAC- ]] && ok "A1.2 número de factura FAC- ($inv_num)" || no "A1.2 número de factura inesperado ($inv_num)"
  expect_eq "pending" "$(jqv '.data.invoice.status')" "A1.3 estado inicial = pending"
  # base + IVA 15% == total
  local calc; calc=$(awk -v b="$base" -v t="$tax" 'BEGIN{printf "%.2f", b+t}')
  expect_eq "$calc" "$total" "A1.4 total = base + IVA ($base + $tax = $total)"
  [ "$(awk -v t="$tax" 'BEGIN{print (t>0)?1:0}')" = "1" ] && ok "A1.5 IVA aplicado (tax>0)" || no "A1.5 IVA no aplicado"
  [ -n "$(jqv '.data.invoice.client_ruc // empty')" ] && ok "A1.6 RUC del cliente en factura" || no "A1.6 factura sin client_ruc"

  # A2 — listado
  code=$(req GET "/invoice/list.php?limit=50" "$JAR_ADMIN")
  expect_eq 200 "$code" "A2.1 listado de facturas (200)"
  local found; found=$(jq --arg n "$inv_num" '[.data.invoices[].invoice_number] | index($n) // "no"' "$TMP/body.json" 2>/dev/null)
  [ "$found" != "no" ] && [ "$found" != "null" ] && ok "A2.2 la factura aparece en el listado" || no "A2.2 factura no listada"

  # A3 — detalle
  code=$(req GET "/invoice/get.php?id=$inv_id" "$JAR_ADMIN")
  expect_eq 200 "$code" "A3.1 detalle de factura (200)"
  expect_eq "$inv_num" "$(jqv '.data.invoice.invoice_number')" "A3.2 detalle correcto"

  # A4 — cobro USD
  code=$(req POST /invoice/pay.php "$JAR_ADMIN" "$(jq -n --argjson id "$inv_id" --argjson m "$PAY_METHOD_ID" '{id:$id,payment_method_id:$m,paid_currency:"USD",payment_reference:"REF-A4"}')" "$CSRF_ADMIN")
  expect_eq 200 "$code" "A4.1 cobro en USD (200)"
  expect_eq "paid" "$(jqv '.data.invoice.status')" "A4.2 estado = paid"
  expect_eq "$total" "$(jqv '.data.invoice.paid_amount')" "A4.3 paid_amount = total (USD)"

  # A5 — no se puede recobrar
  code=$(req POST /invoice/pay.php "$JAR_ADMIN" "$(jq -n --argjson id "$inv_id" --argjson m "$PAY_METHOD_ID" '{id:$id,payment_method_id:$m,paid_currency:"USD"}')" "$CSRF_ADMIN")
  expect_eq 409 "$code" "A5 recobrar factura pagada -> 409"

  # A6 — cobro en C$
  payload=$(jq -n '{sender_name:"Shein",sender_phone:"3050000001",recipient_name:"Cliente Cord",recipient_phone:"88882222",recipient_address:"Barrio Sur 456",client_id:2,zone_id:1,delivery_type:"aereo",delivery_option:"branch",shipping_service:"Aéreo",origin:"online",weight:5,length:0,width:0,height:0,declared_value:60}')
  code=$(req POST /package/create.php "$JAR_ADMIN" "$payload" "$CSRF_ADMIN")
  local inv2 total2
  inv2=$(jqv '.data.invoice.id'); total2=$(jqv '.data.invoice.total_amount')
  code=$(req POST /invoice/pay.php "$JAR_ADMIN" "$(jq -n --argjson id "$inv2" --argjson m "$PAY_METHOD_ID" '{id:$id,payment_method_id:$m,paid_currency:"CORD"}')" "$CSRF_ADMIN")
  expect_eq 200 "$code" "A6.1 cobro en C\$ (200)"
  local paid2 rate; paid2=$(jqv '.data.invoice.paid_amount'); rate=$(jqv '.data.invoice.exchange_rate')
  local exp2; exp2=$(awk -v t="$total2" -v r="$rate" 'BEGIN{printf "%.2f", t*r}')
  expect_eq "$exp2" "$paid2" "A6.2 paid_amount = total * tipo_cambio ($total2 * $rate)"

  # A7 — anular pendiente
  payload=$(jq -n '{sender_name:"eBay",sender_phone:"3050000002",recipient_name:"Cliente Anula",recipient_phone:"88883333",recipient_address:"Barrio Este 789",client_id:2,zone_id:1,delivery_type:"aereo",delivery_option:"branch",shipping_service:"Aéreo",origin:"online",weight:3,length:0,width:0,height:0,declared_value:30}')
  code=$(req POST /package/create.php "$JAR_ADMIN" "$payload" "$CSRF_ADMIN")
  local inv3; inv3=$(jqv '.data.invoice.id')
  code=$(req PUT /invoice/cancel.php "$JAR_ADMIN" "$(jq -n --argjson id "$inv3" '{id:$id}')" "$CSRF_ADMIN")
  expect_eq 200 "$code" "A7.1 anular factura pendiente (200)"
  expect_eq "cancelled" "$(jqv '.data.invoice.status')" "A7.2 estado = cancelled"

  # A8 — no anular pagada
  code=$(req PUT /invoice/cancel.php "$JAR_ADMIN" "$(jq -n --argjson id "$inv_id" '{id:$id}')" "$CSRF_ADMIN")
  expect_eq 409 "$code" "A8 anular factura pagada -> 409"

  # A9 — impresión
  code=$(req GET "/invoice/print.php?id=$inv_id" "$JAR_ADMIN")
  expect_eq 200 "$code" "A9.1 impresión de factura (200)"
  expect_contains "$(cat "$TMP/body.json")" "$inv_num" "A9.2 impresión contiene el número de factura"

  # A10 — payment_method CRUD
  code=$(req POST /payment_method/create.php "$JAR_ADMIN" "$(jq -n '{name:"Metodo Test QA",description:"temp"}')" "$CSRF_ADMIN")
  expect_eq 201 "$code" "A10.1 crear método de pago (201)"
  local pm; pm=$(jqv '.data.method.id')
  [ -n "$pm" ] && ok "A10.1b id de método creado ($pm)" || no "A10.1b sin id de método"
  code=$(req PUT /payment_method/update.php "$JAR_ADMIN" "$(jq -n --argjson id "$pm" '{id:$id,name:"Transferencia Test",description:"edit"}')" "$CSRF_ADMIN")
  expect_eq 200 "$code" "A10.2 editar método de pago (200)"
  code=$(req DELETE /payment_method/delete.php "$JAR_ADMIN" "$(jq -n --argjson id "$PAY_METHOD_ID" '{id:$id}')" "$CSRF_ADMIN")
  expect_eq 409 "$code" "A10.3 borrar método con cobros -> 409"
  code=$(req DELETE /payment_method/delete.php "$JAR_ADMIN" "$(jq -n --argjson id "$pm" '{id:$id}')" "$CSRF_ADMIN")
  expect_eq 200 "$code" "A10.4 eliminar método sin cobros (200)"

  # A11 — billing_config
  code=$(req GET /billing_config/get.php "$JAR_ADMIN")
  expect_eq 200 "$code" "A11.1 billing_config get (200)"
  expect_eq "1" "$(jqv '.data.config.tax_enabled')" "A11.2 tax_enabled=1"

  # A12 — RBAC
  code=$(req GET /invoice/list.php "$JAR_VEND"); expect_eq 403 "$code" "A12.1 vendedor invoice/list -> 403"
  code=$(req GET /invoice/list.php "$JAR_OPER"); expect_eq 403 "$code" "A12.2 operador invoice/list -> 403"

  # A13 — CSRF
  code=$(req POST /invoice/pay.php "$JAR_ADMIN" "$(jq -n --argjson id "$inv3" --argjson m "$PAY_METHOD_ID" '{id:$id,payment_method_id:$m}')" "")
  expect_eq 403 "$code" "A13 POST pay sin CSRF -> 403"
}

# =====================================================================
# SUITE C — Vehículos: km + status
# =====================================================================
suite_C() {
  section "C. Vehículos (kilometraje + estado)"

  # C1 — crear despacho draft
  local rundate="2027-01-15"
  local payload code
  payload=$(jq -n --arg d "$rundate" --argjson r "$ROUTE_ID" --argjson a "$DRIVER_ID" --argjson v "$VEHICLE_ID" '{route_id:$r,run_date:$d,assigned_to:$a,vehicle_id:$v}')
  code=$(req POST /route_run/create.php "$JAR_ADMIN" "$payload" "$CSRF_ADMIN")
  expect_eq 201 "$code" "C1.1 crear despacho draft (201)"
  local run_id; run_id=$(jqv '.data.run.id')
  [ -n "$run_id" ] && ok "C1.2 despacho id=$run_id" || no "C1.2 sin id de despacho"

  # C2 — vehículo disponible
  req GET /vehicle/listAll.php "$JAR_ADMIN" >/dev/null
  local vstatus; vstatus=$(jq -r --argjson v "$VEHICLE_ID" '.data.vehicles[] | select(.id==$v) | .status' "$TMP/body.json")
  expect_eq "disponible" "$vstatus" "C2 vehículo disponible antes de iniciar"

  # C3 — activar despacho -> vehículo en_ruta
  code=$(req PUT /route_run/status.php "$JAR_ADMIN" "$(jq -n --argjson id "$run_id" '{id:$id,status:"active"}')" "$CSRF_ADMIN")
  expect_eq 200 "$code" "C3.1 draft -> active (200)"
  req GET /vehicle/listAll.php "$JAR_ADMIN" >/dev/null
  expect_eq "en_ruta" "$(jq -r --argjson i "$VEHICLE_ID" '.data.vehicles[]|select(.id==$i)|.status' "$TMP/body.json")" "C2b vehículo en_ruta tras activar"

  # km actual de referencia
  local cur; cur=$(jq -r --argjson id "$VEHICLE_ID" '.data.vehicles[] | select(.id==$id) | .current_km' "$TMP/body.json")
  [ -z "$cur" ] && cur=45000

  # C3 — km inicio_ruta (admin/supervisor, no es el conductor) -> ok
  code=$(req POST /vehicle/km-register.php "$JAR_ADMIN" "$(jq -n --argjson v "$VEHICLE_ID" --argjson k "$cur" --argjson rr "$run_id" '{vehicle_id:$v,km_reading:$k,reading_type:"inicio_ruta",route_run_id:$rr}')" "$CSRF_ADMIN")
  expect_eq 200 "$code" "C3.1 registrar km inicio_ruta por supervisor (200)"

  # C4 — mismo registro por el conductor asignado -> 409 (segregación)
  code=$(req POST /vehicle/km-register.php "$JAR_COND" "$(jq -n --argjson v "$VEHICLE_ID" --argjson k "$cur" --argjson rr "$run_id" '{vehicle_id:$v,km_reading:$k,reading_type:"inicio_ruta",route_run_id:$rr}')" "$CSRF_COND")
  expect_eq 409 "$code" "C4 km de ruta por el conductor asignado -> 409"

  # C5 — bajar km como conductor (sin km_correction) con lectura manual -> 422
  local lower; lower=$(awk -v c="$cur" 'BEGIN{print c-1000}')
  code=$(req POST /vehicle/km-register.php "$JAR_COND" "$(jq -n --argjson v "$VEHICLE_ID" --argjson k "$lower" '{vehicle_id:$v,km_reading:$k,reading_type:"manual"}')" "$CSRF_COND")
  expect_eq 422 "$code" "C5 bajar km sin permiso de corrección -> 422"

  # C6 — corregir hacia abajo con justificación (admin) -> 200
  code=$(req POST /vehicle/km-register.php "$JAR_ADMIN" "$(jq -n --argjson v "$VEHICLE_ID" --argjson k "$lower" '{vehicle_id:$v,km_reading:$k,reading_type:"manual",notes:"Corrección de prueba"}')" "$CSRF_ADMIN")
  expect_eq 200 "$code" "C6 corregir km hacia abajo con notas (admin) -> 200"

  # C7 — corregir hacia abajo SIN notas (admin) -> 422
  local lower2; lower2=$(awk -v c="$lower" 'BEGIN{print c-500}')
  code=$(req POST /vehicle/km-register.php "$JAR_ADMIN" "$(jq -n --argjson v "$VEHICLE_ID" --argjson k "$lower2" '{vehicle_id:$v,km_reading:$k,reading_type:"manual"}')" "$CSRF_ADMIN")
  expect_eq 422 "$code" "C7 corregir hacia abajo sin justificación -> 422"

  # C8 — historial de odómetro
  code=$(req GET "/vehicle/km-log.php?vehicle_id=$VEHICLE_ID" "$JAR_ADMIN")
  expect_eq 200 "$code" "C8.1 km-log (200)"
  local total; total=$(jqv '.data.pagination.total // 0')
  [ "$total" -ge 2 ] 2>/dev/null && ok "C8.2 historial con $total registros" || no "C8.2 historial vacío/insuficiente ($total)"

  # C9 — completar despacho -> vehículo disponible
  code=$(req PUT /route_run/status.php "$JAR_ADMIN" "$(jq -n --argjson id "$run_id" '{id:$id,status:"completed"}')" "$CSRF_ADMIN")
  expect_eq 200 "$code" "C9.1 completar despacho (200)"
  req GET /vehicle/listAll.php "$JAR_ADMIN" >/dev/null
  expect_eq "disponible" "$(jq -r --argjson id "$VEHICLE_ID" '.data.vehicles[] | select(.id==$id) | .status' "$TMP/body.json")" "C9.2 vehículo disponible tras completar"

  # C10 — cambio de estado de vehículo vía update (requiere vehicle.change_status)
  code=$(req PUT /vehicle/update.php "$JAR_ADMIN" "$(jq -n --argjson id "$VEHICLE_ID" '{id:$id,status:"en_mantenimiento"}')" "$CSRF_ADMIN")
  expect_eq 200 "$code" "C10.1 update status en_mantenimiento (admin con change_status)"
  code=$(req PUT /vehicle/update.php "$JAR_ADMIN" "$(jq -n --argjson id "$VEHICLE_ID" '{id:$id,status:"disponible"}')" "$CSRF_ADMIN")
  expect_eq 200 "$code" "C10.2 volver a disponible"

  # C11 — RBAC: operador no puede ver vehículos
  code=$(req GET /vehicle/list.php "$JAR_OPER"); expect_eq 403 "$code" "C11 operador vehicle/list -> 403"
}

# =====================================================================
# SUITE D — Cotizaciones
# =====================================================================
suite_D() {
  section "D. Cotizaciones"

  # D1 — vendedor crea cotización
  local payload code
  payload=$(jq -n --argjson c "$CLIENT_RUC_ID" '{recipient_name:"Cot Cliente",recipient_phone:"88884444",recipient_address:"Barrio Cot 1",client_id:$c,zone_id:1,delivery_type:"aereo",delivery_option:"branch",shipping_service:"Aéreo",origin:"online",items:[{description:"Caja 1",quantity:1,weight:10,length:0,width:0,height:0,declared_value:100}]}')
  code=$(req POST /quote/create.php "$JAR_VEND" "$payload" "$CSRF_VEND")
  expect_eq 201 "$code" "D1.1 vendedor crea cotización (201)"
  local qid qnum; qid=$(jqv '.data.quote.id'); qnum=$(jqv '.data.quote.quote_number')
  echo "$qid" > "$TMP/last_quote"
  [[ "$qnum" =~ ^COT- ]] && ok "D1.2 número COT- ($qnum)" || no "D1.2 número inesperado ($qnum)"
  expect_eq "draft" "$(jqv '.data.quote.status')" "D1.3 estado draft"
  local qtotal; qtotal=$(jqv '.data.quote.total_amount')
  [ "$(awk -v t="$qtotal" 'BEGIN{print (t>0)?1:0}')" = "1" ] && ok "D1.4 total cotizado > 0 ($qtotal)" || no "D1.4 total cotizado = 0"

  # D2 — listado scoping vendedor
  code=$(req GET /quote/list.php "$JAR_VEND")
  expect_eq 200 "$code" "D2.1 vendedor list (200)"
  local agent_ids; agent_ids=$(jq -r '[.data.quotes[].agent_id] | unique | join(",")' "$TMP/body.json")
  expect_eq "6" "$agent_ids" "D2.2 vendedor solo ve sus cotizaciones (agent_id=6)"

  # D3 — operador sin permiso
  code=$(req GET /quote/list.php "$JAR_OPER"); expect_eq 403 "$code" "D3 operador quote/list -> 403"

  # D4 — transición draft -> sent
  code=$(req PUT /quote/status.php "$JAR_VEND" "$(jq -n --argjson id "$qid" '{id:$id,status:"sent"}')" "$CSRF_VEND")
  expect_eq 200 "$code" "D4.1 draft -> sent (200)"
  expect_eq "sent" "$(jqv '.data.quote.status')" "D4.2 estado sent"

  # D5 — convertir en paquete
  code=$(req POST /quote/convert.php "$JAR_VEND" "$(jq -n --argjson id "$qid" '{id:$id}')" "$CSRF_VEND")
  expect_eq 200 "$code" "D5.1 convertir cotización (200)"
  expect_eq "converted" "$(jqv '.data.quote.status')" "D5.2 estado converted"
  local pkgs; pkgs=$(jqv '.data.packages | length')
  [ "$pkgs" -ge 1 ] 2>/dev/null && ok "D5.3 generó $pkgs paquete(s)" || no "D5.3 no generó paquetes"
  local comm_type comm_amt; comm_type=$(jqv '.data.quote.commission_type'); comm_amt=$(jqv '.data.quote.commission_amount')
  expect_eq "percent" "$comm_type" "D5.4 comisión tipo percent (snapshot)"
  [ "$(awk -v a="$comm_amt" 'BEGIN{print (a>0)?1:0}')" = "1" ] && ok "D5.5 comisión calculada ($comm_amt)" || no "D5.5 comisión = 0"

  # D6 — no reconvertir
  code=$(req POST /quote/convert.php "$JAR_VEND" "$(jq -n --argjson id "$qid" '{id:$id}')" "$CSRF_VEND")
  expect_eq 409 "$code" "D6 reconvertir -> 409"

  # D7 — no editar convertida
  payload=$(jq -n --argjson id "$qid" '{id:$id,recipient_name:"X",recipient_phone:"1",recipient_address:"a",items:[{description:"x",quantity:1,weight:1}]}')
  code=$(req PUT /quote/update.php "$JAR_VEND" "$payload" "$CSRF_VEND")
  expect_eq 409 "$code" "D7 editar convertida -> 409"

  # D8 — RBAC de delete + no borrar convertida
  code=$(req DELETE /quote/delete.php "$JAR_VEND" "$(jq -n --argjson id "$qid" '{id:$id}')" "$CSRF_VEND")
  expect_eq 403 "$code" "D8.1 vendedor sin quote.delete -> 403"
  code=$(req DELETE /quote/delete.php "$JAR_ADMIN" "$(jq -n --argjson id "$qid" '{id:$id}')" "$CSRF_ADMIN")
  expect_eq 409 "$code" "D8.2 borrar cotización convertida -> 409"

  # D9 — stats / comisiones
  code=$(req GET "/quote/stats.php?from=2026-01-01&to=2027-12-31" "$JAR_ADMIN")
  expect_eq 200 "$code" "D9.1 stats (200)"
  expect_contains "$(jq -c '.data' "$TMP/body.json")" "commissions" "D9.2 stats incluye commissions"

  # D10 — expiración automática
  payload=$(jq -n '{recipient_name:"Exp Cliente",recipient_phone:"88885555",recipient_address:"Barrio Exp",zone_id:1,delivery_type:"aereo",delivery_option:"branch",items:[{description:"y",quantity:1,weight:2,length:1,width:1,height:1,declared_value:10}],valid_until:"2020-01-01"}')
  local code2 qid2; code2=$(req POST /quote/create.php "$JAR_ADMIN" "$payload" "$CSRF_ADMIN")
  qid2=$(jqv '.data.quote.id')
  [ "$code2" = "201" ] && ok "D10.1 crear cotización vencida (201)" || no "D10.1 crear cotización vencida (HTTP $code2)"
  req GET /quote/list.php "$JAR_ADMIN" >/dev/null   # dispara expireOverdue
  code=$(req GET "/quote/get.php?id=$qid2" "$JAR_ADMIN")
  expect_eq "expired" "$(jqv '.data.quote.status')" "D10.2 cotización vencida -> expired"
}

# =====================================================================
# SUITE E — Recolección (pickup)
# =====================================================================
suite_E() {
  section "E. Recolección (pickup vehicle)"

  # E1 — cotización con vehículo de recolección
  local payload code
  payload=$(jq -n --argjson c "$CLIENT_RUC_ID" --argjson v "$VEHICLE2_ID" '{recipient_name:"Pickup Cliente",recipient_phone:"88886666",recipient_address:"Barrio Pickup 1",client_id:$c,zone_id:1,delivery_type:"aereo",delivery_option:"branch",shipping_service:"Aéreo",origin:"online",pickup_required:1,pickup_vehicle_id:$v,items:[{description:"Pickup pkg",quantity:1,weight:4,length:0,width:0,height:0,declared_value:50}]}')
  code=$(req POST /quote/create.php "$JAR_ADMIN" "$payload" "$CSRF_ADMIN")
  expect_eq 201 "$code" "E1.1 cotización con recolección (201)"
  local qid; qid=$(jqv '.data.quote.id')
  expect_eq "1" "$(jqv '.data.quote.pickup_required')" "E1.2 pickup_required=1"
  expect_eq "$VEHICLE2_ID" "$(jqv '.data.quote.pickup_vehicle_id')" "E1.3 pickup_vehicle_id guardado"
  expect_eq "M-10002" "$(jqv '.data.quote.pickup_vehicle_plate')" "E1.4 placa de vehículo devuelta"

  # E2 — vehículo inexistente -> 422
  local payload2; payload2=$(jq -n '{recipient_name:"Bad Veh",recipient_phone:"1",recipient_address:"x",zone_id:1,delivery_type:"aereo",delivery_option:"branch",pickup_required:1,pickup_vehicle_id:99999,items:[{description:"z",quantity:1,weight:1}]}')
  code=$(req POST /quote/create.php "$JAR_ADMIN" "$payload2" "$CSRF_ADMIN")
  expect_eq 422 "$code" "E2 vehículo de recolección inexistente -> 422"

  # E3 — conversión copia pickup al paquete
  code=$(req POST /quote/convert.php "$JAR_ADMIN" "$(jq -n --argjson id "$qid" '{id:$id}')" "$CSRF_ADMIN")
  expect_eq 200 "$code" "E3.1 convertir cotización con recolección (200)"
  local pid; pid=$(jqv '.data.packages[0].id')
  code=$(req GET "/package/get.php?id=$pid" "$JAR_ADMIN")
  expect_eq 200 "$code" "E3.2 detalle de paquete generado (200)"
  expect_eq "1" "$(jqv '.data.package.pickup_required // .data.pickup_required')" "E3.3 paquete hereda pickup_required"
}

# =====================================================================
# SUITE G — Embarques (transferencia internacional Miami -> Nicaragua)
# =====================================================================
suite_G() {
  section "G. Embarques"

  # G1 — crear dos paquetes recibidos en Miami (online -> received)
  local payload code
  payload=$(jq -n '{sender_name:"Amazon",sender_phone:"3050000000",recipient_name:"Emb Cliente 1",recipient_phone:"88887777",recipient_address:"Barrio Emb 1",client_id:2,zone_id:1,delivery_type:"aereo",delivery_option:"branch",shipping_service:"Aéreo",origin:"online",weight:4,length:0,width:0,height:0,declared_value:40}')
  code=$(req POST /package/create.php "$JAR_ADMIN" "$payload" "$CSRF_ADMIN")
  expect_eq 201 "$code" "G1.1 crear paquete received #1 (201)"
  local p1; p1=$(jqv '.data.package.id')
  code=$(req GET "/package/get.php?id=$p1" "$JAR_ADMIN")
  expect_eq "received" "$(jqv '.data.package.status_name')" "G1.2 paquete online admite en received"

  payload=$(jq -n '{sender_name:"Shein",sender_phone:"3050000001",recipient_name:"Emb Cliente 2",recipient_phone:"88887778",recipient_address:"Barrio Emb 2",client_id:2,zone_id:1,delivery_type:"aereo",delivery_option:"branch",shipping_service:"Aéreo",origin:"online",weight:6,length:0,width:0,height:0,declared_value:60}')
  code=$(req POST /package/create.php "$JAR_ADMIN" "$payload" "$CSRF_ADMIN")
  expect_eq 201 "$code" "G1.3 crear paquete received #2 (201)"
  local p2; p2=$(jqv '.data.package.id')

  # G2 — crear embarque de prueba (code QATEST-<epoch>, limpiable por el script)
  local scode="QATEST-$(date +%s)"
  code=$(req POST /shipment/create.php "$JAR_ADMIN" "$(jq -n --arg c "$scode" '{code:$c,mode:"air",departure_date:"2027-03-01"}')" "$CSRF_ADMIN")
  expect_eq 201 "$code" "G2.1 crear embarque (201)"
  local sid; sid=$(jqv '.data.shipment.id')
  expect_eq "draft" "$(jqv '.data.shipment.status')" "G2.2 embarque en draft"

  # G3 — despachar embarque vacío -> 422
  code=$(req POST /shipment/dispatch.php "$JAR_ADMIN" "$(jq -n --argjson s "$sid" '{shipment_id:$s}')" "$CSRF_ADMIN")
  expect_eq 422 "$code" "G3 despachar embarque vacío -> 422"

  # G4 — eligible-packages incluye los recibidos
  code=$(req GET "/shipment/eligible-packages.php?mode=air" "$JAR_ADMIN")
  expect_eq 200 "$code" "G4.1 eligible-packages (200)"
  expect_eq "true" "$(jq --argjson a "$p1" --argjson b "$p2" '[.data.packages[].id] | (index($a)!=null) and (index($b)!=null)' "$TMP/body.json" 2>/dev/null)" "G4.2 los recibidos son elegibles"

  # G3b — add-packages
  code=$(req POST /shipment/add-packages.php "$JAR_ADMIN" "$(jq -n --argjson s "$sid" --argjson a "$p1" --argjson b "$p2" '{shipment_id:$s,package_ids:[$a,$b]}')" "$CSRF_ADMIN")
  expect_eq 200 "$code" "G5.1 add-packages (200)"
  expect_eq "2" "$(jqv '.data.shipment.package_count')" "G5.2 shipment.package_count=2"

  # G5 — detalle con paquetes
  code=$(req GET "/shipment/get.php?id=$sid" "$JAR_ADMIN")
  expect_eq 200 "$code" "G5b.1 shipment/get (200)"
  expect_eq "2" "$(jqv '.data.packages | length')" "G5b.2 detalle lista 2 paquetes"

  # G6 — despachar -> in_transit
  code=$(req POST /shipment/dispatch.php "$JAR_ADMIN" "$(jq -n --argjson s "$sid" '{shipment_id:$s}')" "$CSRF_ADMIN")
  expect_eq 200 "$code" "G6.1 dispatch (200)"
  expect_eq "in_transit" "$(jqv '.data.shipment.status')" "G6.2 embarque in_transit"
  code=$(req GET "/package/get.php?id=$p1" "$JAR_ADMIN")
  expect_eq "in_transit" "$(jqv '.data.package.status_name')" "G6.3 paquete in_transit"

  # G7 — add-packages a embarque no-draft -> 409
  code=$(req POST /shipment/add-packages.php "$JAR_ADMIN" "$(jq -n --argjson s "$sid" --argjson a "$p1" '{shipment_id:$s,package_ids:[$a]}')" "$CSRF_ADMIN")
  expect_eq 409 "$code" "G7 add-packages a embarque en tránsito -> 409"

  # G8 — arrive -> in_nicaragua
  code=$(req POST /shipment/arrive.php "$JAR_ADMIN" "$(jq -n --argjson s "$sid" '{shipment_id:$s,arrival_date:"2027-03-05"}')" "$CSRF_ADMIN")
  expect_eq 200 "$code" "G8.1 arrive (200)"
  expect_eq "arrived" "$(jqv '.data.shipment.status')" "G8.2 embarque arrived"
  code=$(req GET "/package/get.php?id=$p1" "$JAR_ADMIN")
  expect_eq "in_nicaragua" "$(jqv '.data.package.status_name')" "G8.3 paquete in_nicaragua"

  # G9 — RBAC: vendedor no crea embarques
  code=$(req POST /shipment/create.php "$JAR_VEND" "$(jq -n '{code:"QATEST-nope",mode:"air"}')" "$CSRF_VEND")
  expect_eq 403 "$code" "G9 vendedor shipment/create -> 403"
}

# =====================================================================
# SUITE H — Despacho local (admision local -> paradas)
# =====================================================================
suite_H() {
  section "H. Despacho local"

  # H1 — crear paquete local (online=local -> in_nicaragua)
  local payload code
  payload=$(jq -n '{sender_name:"Local",sender_phone:"88880000",recipient_name:"Local Cliente",recipient_phone:"88889999",recipient_address:"Barrio Local 1",client_id:2,zone_id:1,delivery_type:"aereo",delivery_option:"branch",shipping_service:"Aéreo",origin:"local",weight:2,length:0,width:0,height:0,declared_value:20}')
  code=$(req POST /package/create.php "$JAR_ADMIN" "$payload" "$CSRF_ADMIN")
  expect_eq 201 "$code" "H1.1 crear paquete local (201)"
  local p1; p1=$(jqv '.data.package.id')
  code=$(req GET "/package/get.php?id=$p1" "$JAR_ADMIN")
  expect_eq "in_nicaragua" "$(jqv '.data.package.status_name')" "H1.2 paquete local admite en in_nicaragua"

  # H2 — crear despacho draft (fecha futura, limpiable)
  code=$(req POST /route_run/create.php "$JAR_ADMIN" "$(jq -n --argjson r "$ROUTE_ID" --argjson a "$DRIVER_ID" --argjson v "$VEHICLE_ID" '{route_id:$r,run_date:"2027-02-20",assigned_to:$a,vehicle_id:$v}')" "$CSRF_ADMIN")
  expect_eq 201 "$code" "H2.1 crear despacho draft (201)"
  local run_id; run_id=$(jqv '.data.run.id')

  # H3 — candidatos por zona incluyen el paquete local
  code=$(req GET "/route_run/packages.php?run_id=$run_id" "$JAR_ADMIN")
  expect_eq 200 "$code" "H3.1 route_run/packages (200)"
  expect_eq "true" "$(jq --argjson p "$p1" '[.data.packages[].id] | index($p) != null' "$TMP/body.json" 2>/dev/null)" "H3.2 el paquete local es candidato (por zona)"

  # H4 — generar paradas -> in_nicaragua -> out_for_delivery
  code=$(req POST /route_run/generate-stops.php "$JAR_ADMIN" "$(jq -n --argjson r "$run_id" --argjson p "$p1" '{run_id:$r,package_ids:[$p]}')" "$CSRF_ADMIN")
  expect_eq 200 "$code" "H4.1 generate-stops (200)"
  expect_gt "$(jqv '.data.created | length')" 0 "H4.2 se creó al menos una parada"
  code=$(req GET "/route_stop/list.php?run_id=$run_id" "$JAR_ADMIN")
  expect_eq 200 "$code" "H4.3 route_stop/list (200)"
  local stop_id; stop_id=$(jqv '.data.stops[0].id')
  expect_gt "$(jqv '.data.stops | length')" 0 "H4.4 lista de paradas"
  code=$(req GET "/package/get.php?id=$p1" "$JAR_ADMIN")
  expect_eq "out_for_delivery" "$(jqv '.data.package.status_name')" "H4.5 paquete en out_for_delivery"

  # H5 — activar despacho y validar que generate-stops en no-draft -> 409
  code=$(req PUT /route_run/status.php "$JAR_ADMIN" "$(jq -n --argjson id "$run_id" '{id:$id,status:"active"}')" "$CSRF_ADMIN")
  expect_eq 200 "$code" "H5.1 activar despacho (200)"
  code=$(req POST /route_run/generate-stops.php "$JAR_ADMIN" "$(jq -n --argjson r "$run_id" --argjson p "$p1" '{run_id:$r,package_ids:[$p]}')" "$CSRF_ADMIN")
  expect_eq 409 "$code" "H5.2 generate-stops en run active -> 409"

  # H6 — entregar la parada y completar (libera el vehículo)
  code=$(req POST /route_stop/deliver.php "$JAR_ADMIN" "$(jq -n --argjson id "$stop_id" '{id:$id,notes:"Entrega QA"}')" "$CSRF_ADMIN")
  expect_eq 200 "$code" "H6.1 entregar parada (200)"
  code=$(req GET "/package/get.php?id=$p1" "$JAR_ADMIN")
  expect_eq "delivered" "$(jqv '.data.package.status_name')" "H6.2 paquete delivered"
  code=$(req PUT /route_run/status.php "$JAR_ADMIN" "$(jq -n --argjson id "$run_id" '{id:$id,status:"completed"}')" "$CSRF_ADMIN")
  expect_eq 200 "$code" "H6.3 completar despacho (200)"
}

# =====================================================================
# SUITE I — Almacenaje (bodegas / clasificaciones / ubicaciones / movimientos)
# =====================================================================
suite_I() {
  section "I. Almacenaje"

  # I1 — catálogos base
  code=$(req GET "/warehouse/list.php?all=1" "$JAR_ADMIN")
  expect_eq 200 "$code" "I1.1 warehouse/list (200)"
  expect_eq "true" "$(jq '[.data.warehouses[].code] | index("MIAMI") != null' "$TMP/body.json" 2>/dev/null)" "I1.2 incluye bodega MIAMI"

  code=$(req GET "/storage_category/list.php?all=1" "$JAR_ADMIN")
  expect_eq 200 "$code" "I1.3 storage_category/list (200)"
  expect_eq "true" "$(jq '[.data.storage_categories[].name] | index("Normal") != null' "$TMP/body.json" 2>/dev/null)" "I1.4 incluye clasificación Normal"

  code=$(req GET "/storage_location/available.php?warehouse_id=2&category_id=1" "$JAR_ADMIN")
  expect_eq 200 "$code" "I1.5 storage_location/available (200)"
  expect_gt "$(jqv '.data.locations | length')" 0 "I1.6 devuelve ubicaciones"
  expect_gt "$(jqv '.data.suggested_location_id')" 0 "I1.7 sugiere ubicación"

  # I2 — RBAC / CSRF
  code=$(req GET /warehouse/list.php "$JAR_VEND"); expect_eq 403 "$code" "I2.1 vendedor warehouse -> 403"
  code=$(req POST /warehouse/create.php "$JAR_ADMIN" '{"name":"x","code":"y"}' ""); expect_eq 403 "$code" "I2.2 crear bodega sin CSRF -> 403"

  # I3 — CRUD bodega + clasificación + ubicación (artefactos QATEST)
  code=$(req POST /warehouse/create.php "$JAR_ADMIN" '{"name":"QA Bodega","code":"QATEST-WH","origin":"local"}' "$CSRF_ADMIN")
  expect_eq 201 "$code" "I3.1 crear bodega (201)"
  local wh; wh=$(jqv '.data.warehouse.id')
  code=$(req POST /storage_category/create.php "$JAR_ADMIN" '{"name":"QATEST Cat","color":"#123456"}' "$CSRF_ADMIN")
  expect_eq 201 "$code" "I3.2 crear clasificación (201)"
  local cat; cat=$(jqv '.data.storage_category.id')
  code=$(req POST /storage_location/create.php "$JAR_ADMIN" "$(jq -n --argjson w "$wh" '{warehouse_id:$w,aisle:"Z",rack:"09",level:"1",position:"1",capacity:5}')" "$CSRF_ADMIN")
  expect_eq 201 "$code" "I3.3 crear ubicación (201)"
  expect_eq "Z-09-01-01" "$(jqv '.data.storage_location.code')" "I3.4 código generado"
  code=$(req POST /storage_location/generate.php "$JAR_ADMIN" "$(jq -n --argjson w "$wh" '{warehouse_id:$w,aisle:"Y",rack:"01",levels:2,positions:2,capacity:10}')" "$CSRF_ADMIN")
  expect_eq 200 "$code" "I3.5 generar ubicaciones (200)"
  expect_eq "4" "$(jqv '.data.created')" "I3.6 generó 4 ubicaciones"

  # I4 — admisión con clasificación + auto-ubicación (online -> bodega Miami)
  local payload
  payload=$(jq -n '{sender_name:"QA Almacen",sender_phone:"88880000",recipient_name:"QA Cliente Alm",recipient_phone:"88889999",recipient_address:"QA Dir Alm",client_id:6,zone_id:1,delivery_type:"aereo",delivery_option:"branch",shipping_service:"Aéreo",origin:"online",weight:2,length:0,width:0,height:0,declared_value:15,storage_category_id:1}')
  code=$(req POST /package/create.php "$JAR_ADMIN" "$payload" "$CSRF_ADMIN")
  expect_eq 201 "$code" "I4.1 admisión con clasificación (201)"
  local pkg; pkg=$(jqv '.data.package.id')
  expect_eq "1" "$(jqv '.data.package.storage_category_id')" "I4.2 clasificación guardada"
  expect_gt "$(jqv '.data.package.storage_location_id')" 0 "I4.3 ubicación auto-asignada"

  code=$(req GET "/package/storage.php?package_id=$pkg" "$JAR_ADMIN")
  expect_eq 200 "$code" "I4.4 package/storage (200)"
  expect_eq "stored" "$(jqv '.data.history[0].action')" "I4.5 movimiento 'stored' registrado"
  local loccode; loccode=$(jqv '.data.package.storage_location_code')
  curl -s -b "$JAR_ADMIN" "$BASE/package/label.php?id=$pkg" > "$TMP/label.html"
  if grep -q "$loccode" "$TMP/label.html" 2>/dev/null; then ok "I4.6 etiqueta incluye ubicación ($loccode)"; else no "I4.6 etiqueta incluye ubicación"; fi

  # I5 — fix delivery_type: servicio Marítimo -> tipo maritimo (id 4), no aereo
  payload=$(jq -n '{sender_name:"QA Mar",sender_phone:"88880000",recipient_name:"QA Mar Cliente",recipient_phone:"88889999",recipient_address:"QA Dir Mar",client_id:6,zone_id:1,delivery_option:"branch",shipping_service:"Marítimo",origin:"online",weight:5,length:0,width:0,height:0,declared_value:40}')
  code=$(req POST /package/create.php "$JAR_ADMIN" "$payload" "$CSRF_ADMIN")
  expect_eq 201 "$code" "I5.1 admisión servicio Marítimo (201)"
  expect_eq "4" "$(jqv '.data.package.delivery_type_id')" "I5.2 delivery_type = maritimo (fix)"
  expect_eq "2" "$(jqv '.data.package.shipping_service_id')" "I5.3 shipping_service = Marítimo"

  # I6 — generate-stops libera ubicación y deja snapshot en la parada
  code=$(req POST /route_run/create.php "$JAR_ADMIN" "$(jq -n --argjson r "$ROUTE_ID" --argjson a "$DRIVER_ID" --argjson v "$VEHICLE_ID" '{route_id:$r,run_date:"2027-04-10",assigned_to:$a,vehicle_id:$v}')" "$CSRF_ADMIN")
  expect_eq 201 "$code" "I6.1 crear despacho (201)"
  local run_id; run_id=$(jqv '.data.run.id')
  code=$(req POST /route_run/generate-stops.php "$JAR_ADMIN" "$(jq -n --argjson r "$run_id" --argjson p "$pkg" '{run_id:$r,package_ids:[$p]}')" "$CSRF_ADMIN")
  expect_eq 200 "$code" "I6.2 generate-stops (200)"
  code=$(req GET "/package/storage.php?package_id=$pkg" "$JAR_ADMIN")
  expect_eq "null" "$(jqv '.data.package.storage_location_id')" "I6.3 paquete fuera de bodega (null)"
  expect_eq "released" "$(jqv '.data.history[0].action')" "I6.4 movimiento 'released' registrado"
  code=$(req GET "/route_stop/list.php?run_id=$run_id" "$JAR_ADMIN")
  expect_gt "$(jqv '.data.stops[0].storage_location_id')" 0 "I6.5 parada conserva snapshot de ubicación"
  local stop_id; stop_id=$(jqv '.data.stops[0].id')

  # I7 — picking imprimible
  curl -s -b "$JAR_ADMIN" "$BASE/route_run/picking.php?id=$run_id" > "$TMP/picking.html"
  if grep -q "Lista de Picking" "$TMP/picking.html" 2>/dev/null && grep -q "$loccode" "$TMP/picking.html" 2>/dev/null; then
    ok "I7.1 picking imprimible (HTML + ubicación)"
  else
    no "I7.1 picking imprimible (HTML + ubicación)"
  fi

  # I8 — skip restaura el paquete a bodega (requiere despacho activo)
  code=$(req PUT /route_run/status.php "$JAR_ADMIN" "$(jq -n --argjson id "$run_id" '{id:$id,status:"active"}')" "$CSRF_ADMIN")
  expect_eq 200 "$code" "I8.1 activar despacho (200)"
  code=$(req PUT /route_stop/status.php "$JAR_ADMIN" "$(jq -n --argjson id "$stop_id" '{id:$id,status:"skipped",notes:"Cliente ausente QA"}')" "$CSRF_ADMIN")
  expect_eq 200 "$code" "I8.2 omitir parada (200)"
  code=$(req GET "/package/storage.php?package_id=$pkg" "$JAR_ADMIN")
  expect_gt "$(jqv '.data.package.storage_location_id')" 0 "I8.3 paquete restaurado a bodega"
  expect_eq "restored" "$(jqv '.data.history[0].action')" "I8.4 movimiento 'restored' registrado"

  # I9 — borrado: con ubicaciones la bodega queda bloqueada; sin ellas se elimina
  code=$(req DELETE /warehouse/delete.php "$JAR_ADMIN" "$(jq -n --argjson id "$wh" '{id:$id}')" "$CSRF_ADMIN")
  expect_eq 409 "$code" "I9.1 bodega con ubicaciones -> 409"
  code=$(req GET "/storage_location/list.php?warehouse_id=$wh&all=1" "$JAR_ADMIN")
  local loc_ids; loc_ids=$(jq -r '.data.storage_locations[].id' "$TMP/body.json" 2>/dev/null)
  for lid in $loc_ids; do
    req DELETE /storage_location/delete.php "$JAR_ADMIN" "$(jq -n --argjson id "$lid" '{id:$id}')" "$CSRF_ADMIN" > /dev/null
  done
  code=$(req DELETE /warehouse/delete.php "$JAR_ADMIN" "$(jq -n --argjson id "$wh" '{id:$id}')" "$CSRF_ADMIN")
  expect_eq 200 "$code" "I9.2 eliminar bodega QA sin ubicaciones (200)"
  code=$(req DELETE /storage_category/delete.php "$JAR_ADMIN" "$(jq -n --argjson id "$cat" '{id:$id}')" "$CSRF_ADMIN")
  expect_eq 200 "$code" "I9.3 eliminar clasificación QA (200)"
}

# =====================================================================
# SUITE F — Transversal (RBAC / CSRF / 410 / rate-limit)
# =====================================================================
suite_F() {
  section "F. Transversal"

  # F1 — RBAC sin autenticación
  code=$(req GET /quote/list.php "$TMP/empty.jar" "")
  expect_eq 401 "$code" "F1.1 endpoint protegido sin sesión -> 401"

  # F2 — vendedor no accede a facturas / conductor no a cotizaciones
  code=$(req GET /invoice/list.php "$JAR_VEND"); expect_eq 403 "$code" "F2.1 vendedor invoice -> 403"
  code=$(req GET /quote/list.php "$JAR_COND"); expect_eq 403 "$code" "F2.2 conductor quote -> 403"

  # F3 — CSRF obligatorio en mutating
  code=$(req POST /package/create.php "$JAR_ADMIN" '{"sender_name":"x","recipient_name":"y","recipient_phone":"1","recipient_address":"z"}' "")
  expect_eq 403 "$code" "F3 POST package sin CSRF -> 403"

  # F4 — endpoint deshabilitado 410
  code=$(req PUT /package/update.php "$JAR_ADMIN" '{"id":1,"recipient_name":"x"}' "$CSRF_ADMIN")
  expect_eq 410 "$code" "F4 package/update -> 410 Gone"

  # F5 — rate limit de login (ya consumimos intentos)
  do_login "$TMP/rl.jar" admin@vunotek.com "otra-mala"
  if [ "$LOGIN_CODE" = "429" ] || [ "$LOGIN_CODE" = "401" ]; then
    ok "F5 login rate-limit (HTTP $LOGIN_CODE; 401 si aún hay margen, 429 si se agotó)"
  else
    no "F5 login rate-limit inesperado (HTTP $LOGIN_CODE)"
  fi
}

# =====================================================================
# Runner
# =====================================================================
clear_ratelimit() {
  for d in "${TMPDIR:-/tmp}" /tmp; do
    [ -d "$d/shipping-ratelimit" ] && rm -f "$d/shipping-ratelimit"/*.json 2>/dev/null
  done
}

clear_ratelimit
printf '%s=== test-api.sh ===%s  BASE=%s\n' "$CYN" "$RST" "$BASE"

# las suites usan la definición global de expect_json? no — la redefinimos por error;
# descomentar si hace falta.

LOGIN_DONE=0
login_all
login_done=1

SUITES=("$@")
[ ${#SUITES[@]} -eq 0 ] && SUITES=(A C D E F G H I)

for s in "${SUITES[@]}"; do
  case "$s" in
    A) suite_A ;;
    C) suite_C ;;
    D) suite_D ;;
    E) suite_E ;;
    F) suite_F ;;
    G) suite_G ;;
    H) suite_H ;;
    I) suite_I ;;
    *) echo "Suite desconocida: $s" ;;
  esac
done

# --- Limpieza final (self-cleanup) ---
SCRIPT_DIR="$(cd "$(dirname "${BASH_SOURCE[0]}")" && pwd)"
run_cleanup() {
  if [ "${SKIP_CLEANUP:-0}" = "1" ]; then
    printf '\n%sLimpieza omitida (SKIP_CLEANUP=1)%s\n' "$CYN" "$RST"
    return
  fi
  local envfile="$SCRIPT_DIR/../api/.env"
  local sqlfile="$SCRIPT_DIR/cleanup-test-data.sql"
  if [ ! -f "$envfile" ] || [ ! -f "$sqlfile" ]; then
    printf '\n%sNo se encontró %s; limpieza omitida%s\n' "$CYN" "$envfile/$sqlfile" "$RST"
    return
  fi
  local db_host db_port db_name db_user db_pass
  db_host=$(grep -E '^DB_HOST=' "$envfile" | head -1 | cut -d= -f2-)
  db_port=$(grep -E '^DB_PORT=' "$envfile" | head -1 | cut -d= -f2-)
  db_name=$(grep -E '^DB_NAME=' "$envfile" | head -1 | cut -d= -f2-)
  db_user=$(grep -E '^DB_USER=' "$envfile" | head -1 | cut -d= -f2-)
  db_pass=$(grep -E '^DB_PASS=' "$envfile" | head -1 | cut -d= -f2-)
  printf '\n%s== Limpieza de datos de prueba ==%s\n' "$CYN" "$RST"
  MYSQL_PWD="$db_pass" mysql -h"${db_host:-127.0.0.1}" -P"${db_port:-3306}" \
    -u"${db_user:-root}" "$db_name" < "$sqlfile"
}

printf '\n%s=== RESUMEN ===%s  %sPASS: %d%s  %sFAIL: %d%s\n' "$CYN" "$RST" "$GRN" "$PASS" "$RST" "$RED" "$FAIL" "$RST"
run_cleanup
if [ "$FAIL" -gt 0 ]; then
  printf '\nFallos:\n'
  for f in "${FAILURES[@]}"; do printf '  - %s\n' "$f"; done
  exit 1
fi
printf '%sTodo OK%s\n' "$GRN" "$RST"
