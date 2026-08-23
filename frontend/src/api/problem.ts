export interface Violation {
  propertyPath: string
  message: string
}

/**
 * Every error this API can produce, in one shape. The backend writes what the
 * user should read into "detail", in Polish, so the frontend never invents a
 * message of its own - except when there is no envelope at all.
 */
export class ApiError extends Error {
  readonly status: number
  readonly detail: string
  readonly violations: Violation[]

  // Pola wypisane osobno, nie jako wlasciwosci konstruktora: tsconfig ma
  // erasableSyntaxOnly, ktore dopuszcza wylacznie skladnie dajaca sie usunac
  // bez zmiany zachowania.
  constructor(status: number, detail: string, violations: Violation[] = []) {
    super(detail)
    this.name = 'ApiError'
    this.status = status
    this.detail = detail
    this.violations = violations
  }

  /** propertyPath -> first message, ready to hand to a form. */
  fieldErrors(): Record<string, string> {
    const errors: Record<string, string> = {}

    for (const violation of this.violations) {
      errors[violation.propertyPath] ??= violation.message
    }

    return errors
  }
}

const GENERIC_DETAIL = 'Coś poszło nie tak. Spróbuj ponownie.'

export async function toApiError(response: Response): Promise<ApiError> {
  const body = await readJson(response)

  if (null === body) {
    return new ApiError(response.status, GENERIC_DETAIL)
  }

  const violations = Array.isArray(body.violations) ? body.violations.filter(isViolation) : []

  if (violations.length > 0) {
    // Przy 422 "detail" sklei wszystkie naruszenia znakami nowej linii -
    // nadaje sie do logu, nie pod pole formularza.
    return new ApiError(response.status, violations[0].message, violations)
  }

  const detail = 'string' === typeof body.detail && '' !== body.detail ? body.detail : GENERIC_DETAIL

  // Pola "message" celowo nie czytamy: firewall wpisuje tam angielski tekst
  // ("JWT Token not found"), ktorego uzytkownikowi pokazywac nie chcemy.
  return new ApiError(response.status, detail)
}

async function readJson(response: Response): Promise<Record<string, unknown> | null> {
  try {
    const parsed: unknown = await response.json()

    return 'object' === typeof parsed && null !== parsed ? (parsed as Record<string, unknown>) : null
  } catch {
    return null
  }
}

function isViolation(value: unknown): value is Violation {
  if ('object' !== typeof value || null === value) {
    return false
  }

  const candidate = value as Record<string, unknown>

  return 'string' === typeof candidate.propertyPath && 'string' === typeof candidate.message
}
