import axios, { AxiosError } from 'axios'
import type { ValidationErrors } from '@/types'

/**
 * Central HTTP client. Authentication relies on the Laravel session cookie (HTTP-only),
 * so no token is ever stored in the browser: `withCredentials` sends the cookies and
 * `withXSRFToken` copies the XSRF-TOKEN cookie into the X-XSRF-TOKEN header.
 */
export const api = axios.create({
  baseURL: '/api',
  withCredentials: true,
  withXSRFToken: true,
  headers: {
    Accept: 'application/json',
    'X-Requested-With': 'XMLHttpRequest',
  },
})

/** Initializes the CSRF protection cookie (required before login/register). */
export async function ensureCsrfCookie(): Promise<void> {
  await axios.get('/sanctum/csrf-cookie', { withCredentials: true })
}

/** Error body returned by the API (see ApiErrorResponse in the backend). */
interface ApiErrorBody {
  code?: string
  message?: string
  errors?: ValidationErrors
  request_id?: string | null
}

/** Normalized error rejected by every API call. */
export class ApiError extends Error {
  readonly status: number
  readonly errors: ValidationErrors
  /** Stable identifier (e.g. "VALIDATION_FAILED", "INSUFFICIENT_STOCK"); null for network errors. */
  readonly code: string | null
  /** Shown to support to find the request in the logs. */
  readonly requestId: string | null

  constructor(
    status: number,
    message: string,
    errors: ValidationErrors = {},
    code: string | null = null,
    requestId: string | null = null,
  ) {
    super(message)
    this.status = status
    this.errors = errors
    this.code = code
    this.requestId = requestId
  }
}

interface ApiErrorHandlers {
  /** 401 on a store route: the shopper session ended. */
  onUnauthorized?: () => void
  /** 401 on an admin route: the staff session ended. */
  onStaffUnauthorized?: () => void
  onForbidden?: (message: string) => void
  onServerError?: (message: string) => void
}

const handlers: ApiErrorHandlers = {}

/** Global handlers are registered in main.ts (avoids importing the router here). */
export function configureApiErrorHandlers(newHandlers: ApiErrorHandlers): void {
  Object.assign(handlers, newHandlers)
}

function toApiError(error: AxiosError<ApiErrorBody>): ApiError {
  const status = error.response?.status ?? 0
  const data = error.response?.data
  const fallback = status === 0 ? 'Não foi possível conectar ao servidor.' : 'Ocorreu um erro inesperado.'

  return new ApiError(status, data?.message ?? fallback, data?.errors ?? {}, data?.code ?? null, data?.request_id ?? null)
}

/** Handles the errors every page reacts to the same way; the others are left to each page. */
export function reportGlobalError(apiError: ApiError, url: string): void {
  switch (true) {
    // 401: session expired or not logged in. Each area has its own login, and the session
    // checks ("auth/me", "admin/auth/me") are expected to fail for visitors.
    case apiError.status === 401 && !url.startsWith('auth/') && !url.startsWith('admin/auth/'):
      if (url.startsWith('admin/')) {
        handlers.onStaffUnauthorized?.()
      } else {
        handlers.onUnauthorized?.()
      }
      break
    // 403: authenticated, but not allowed.
    case apiError.status === 403:
      handlers.onForbidden?.(apiError.message)
      break
    // 419: CSRF token mismatch (usually an expired session).
    case apiError.status === 419:
      handlers.onServerError?.('Sua sessão expirou. Recarregue a página.')
      break
    // 429: a throttled route (checkout, payment); the page would otherwise show nothing.
    case apiError.status === 429:
      handlers.onServerError?.('Muitas tentativas em pouco tempo. Aguarde um minuto e tente novamente.')
      break
    case apiError.status >= 500 || apiError.status === 0:
      handlers.onServerError?.(apiError.status === 0 ? apiError.message : 'Erro no servidor. Tente novamente em instantes.')
      break
    // 402, 404, 409 and 422 are handled by each page (messages / field errors).
  }
}

api.interceptors.response.use(
  (response) => response,
  (error: AxiosError<ApiErrorBody>) => {
    const apiError = toApiError(error)
    reportGlobalError(apiError, error.config?.url ?? '')
    return Promise.reject(apiError)
  },
)
