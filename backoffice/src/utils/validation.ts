/**
 * Validacion de identificacion fiscal nicaraguense.
 *
 * Un mismo campo (RUC) cubre dos naturalezas juridicas con formatos distintos:
 *
 * - Persona natural -> cedula ciudadana: 3 digitos de municipio, 6 de fecha
 *   de nacimiento (DDMMAA), 4 de consecutivo y 1 letra de validacion al
 *   final. Sin guiones son 14 caracteres: `0012912890042T`. El fisco lo emite
 *   con guiones como `001-291289-0042T`.
 * - Persona juridica -> RUC: misma cantidad de digitos pero la letra va al
 *   principio, seguida de 13 digitos: `J1234567890123`.
 *
 * La validacion es ESTRUCTURAL a proposito. Tres datos del documento no son
 * publicos y no se pueden verificar sin ellos:
 *   1. La tabla de codigos de municipio.
 *   2. El consecutivo de inscripcion.
 *   3. El algoritmo de la letra de validacion (modulo 23).
 * Por eso no se comprueba el municipio, ni el consecutivo, ni la letra: solo
 * la forma, la posicion de la letra y que la fecha exista de verdad.
 *
 * Espejo en PHP de `validateRuc()` (api/bootstrap.php). Si cambia una regla,
 * cambia en los dos lados: el frontend es evadible y el backend es la
 * autoridad.
 */

export type IdNature = 'natural' | 'juridica'

export interface IdClassification {
  /** null cuando el campo esta vacio (opcional) o cuando no se pudo clasificar */
  kind: IdNature | null
  /** Valor canonico: 14 caracteres, sin guiones y en mayusculas */
  normalized: string
  /** null cuando el valor es valido o esta vacio */
  error: string | null
}

/** Cantidad de caracteres del valor canonico (ambos formatos) */
export const ID_LENGTH = 14

/**
 * Techo de tecleo. NO trunca a 14: si un valor excede la longitud hay que
 * reportarlo, porque recortar en silencio convertiria el numero de otra
 * persona en un documento valido pero distinto al que se escribio.
 */
const MAX_TYPED_LENGTH = 20

/** Letras excluidas por el algoritmo modulo 23 que genera la letra final */
const RESERVED_LETTERS = ['I', 'O', 'Z']

/**
 * Deja solo caracteres alfanumericos en mayusculas: es el valor canonico que
 * se persiste. Los guiones son solo presentacion.
 */
export function normalizeId(raw: string | number | null | undefined): string {
  return String(raw ?? '')
    .toUpperCase()
    .replace(/[^A-Z0-9]/g, '')
    .slice(0, MAX_TYPED_LENGTH)
}

/**
 * Como se ve el valor mientras se escribe. La persona juridica no se agrupa
 * porque el fisco no publica los cortes de sus 13 digitos; la cedula si sigue
 * el patron `NNN-DDMMAA-CCCCL`.
 *
 * El excedente a 14 caracteres se muestra sin agrupar: el campo lo va a marcar
 * como invalido, pero el usuario debe ver lo que escribio para corregirlo.
 */
export function formatIdForDisplay(raw: string | number | null | undefined): string {
  const core = normalizeId(raw)
  if (!core) return ''
  if (/^[A-Z]/.test(core)) return core

  const head = core.slice(0, ID_LENGTH)
  const overflow = core.slice(ID_LENGTH)
  let grouped: string
  if (head.length <= 3) grouped = head
  else if (head.length <= 9) grouped = `${head.slice(0, 3)}-${head.slice(3)}`
  else grouped = `${head.slice(0, 3)}-${head.slice(3, 9)}-${head.slice(9)}`

  return overflow ? `${grouped}${overflow}` : grouped
}

/** Etiqueta del tipo detectado, para mostrarla como ayuda bajo el campo. */
export function idNatureLabel(raw: string | number | null | undefined): string {
  const kind = classifyNicaraguanId(raw).kind
  if (kind === 'juridica') return 'RUC · persona jurídica'
  if (kind === 'natural') return 'Cédula · persona natural'
  return ''
}

/**
 * Un año de dos digitos no dice el siglo: `16` puede ser 1916 o 2016. Se asume
 * 20xx mientras el año sea <= al año actual en curso y 19xx en el resto, que
 * es el pivote habitual. Limite asumido: un año con 2 digitos siempre cae en
 * los ultimos 100 años.
 *
 * Efecto colateral conocido: una fecha real de 1916 tecleada como `16` se
 * interpreta como 2016 y se acepta. No hay forma de distinguirla con 2 digitos.
 */
function resolveBirthYear(yy: number, now: Date): number {
  return yy <= now.getFullYear() % 100 ? 2000 + yy : 1900 + yy
}

/** Edad cumplida hoy, o null si la fecha no existe. */
function ageFromBirthDate(dd: number, mm: number, yy: number, now: Date): number | null {
  const year = resolveBirthYear(yy, now)
  // Date con dia 31 en un mes de 30 rueda al mes siguiente: se compara el
  // resultado para detectar fechas como 31/02.
  const born = new Date(year, mm - 1, dd)
  if (born.getFullYear() !== year || born.getMonth() !== mm - 1 || born.getDate() !== dd) {
    return null
  }

  let age = now.getFullYear() - year
  const hadBirthday =
    now.getMonth() > born.getMonth() ||
    (now.getMonth() === born.getMonth() && now.getDate() >= born.getDate())
  if (!hadBirthday) age--
  return age
}

function validateNatural(core: string, now: Date): string | null {
  if (core.length !== ID_LENGTH) {
    const detail =
      core.length > ID_LENGTH
        ? `La cédula tiene ${core.length} caracteres y el máximo son ${ID_LENGTH}`
        : `La cédula tiene ${core.length} de ${ID_LENGTH} caracteres: 13 dígitos y 1 letra al final`
    return detail
  }
  if (!/^\d{13}[A-Z]$/.test(core)) {
    return 'La cédula debe ser 13 dígitos seguidos de una letra al final (ej. 001-291289-0042T)'
  }

  const letter = core.slice(-1)
  if (RESERVED_LETTERS.includes(letter)) {
    return `La letra final "${letter}" no es válida: el algoritmo módulo 23 excluye I, O y Z`
  }

  const dd = Number(core.slice(3, 5))
  const mm = Number(core.slice(5, 7))
  const yy = Number(core.slice(7, 9))

  if (mm < 1 || mm > 12) {
    return `El mes ${core.slice(5, 7)} no existe: la fecha va en formato DDMMAA`
  }
  if (dd < 1 || dd > 31) {
    return `El día ${core.slice(3, 5)} no existe: la fecha va en formato DDMMAA`
  }

  const age = ageFromBirthDate(dd, mm, yy, now)
  if (age === null) {
    return `La fecha ${core.slice(3, 5)}/${core.slice(5, 7)}/${core.slice(7, 9)} no existe`
  }
  if (age < 0) {
    return 'La fecha de nacimiento es futura'
  }
  // No hay un tope explicito de edad: el pivote de 2 digitos ya la acota.
  // YY=27..99 -> 1927..1999 (0..99 anos) y YY=00..26 -> 2000..2026 (0..26
  // anos), asi que ninguna fecha valida puede implicar 100 anos o mas. Una
  // persona nacida en 1926 o antes simplemente no es representable con 2
  // digitos de ano.

  return null
}

function validateJuridica(core: string): string | null {
  if (!/^[A-Z]\d{13}$/.test(core)) {
    return 'El RUC de persona jurídica debe ser una letra seguida de 13 dígitos (ej. J1234567890123)'
  }
  return null
}

/**
 * Clasifica y valida un identificacion. Devuelve siempre el valor canonico
 * para que el caller pueda persistirlo, y el error si no es valido.
 */
export function classifyNicaraguanId(raw: string | number | null | undefined): IdClassification {
  const core = normalizeId(raw)

  // Campo opcional: vacio no es error.
  if (core === '') {
    return { kind: null, normalized: '', error: null }
  }

  const now = new Date()
  // La letra define la naturaleza y va en extremos opuestos segun el tipo, asi
  // que el primer caracter decide como interpretar el resto.
  const kind: IdNature = /^[A-Z]/.test(core) ? 'juridica' : 'natural'
  const error = kind === 'juridica' ? validateJuridica(core) : validateNatural(core, now)

  return { kind: error === null ? kind : null, normalized: core, error }
}