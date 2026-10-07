import { describe, expect, it, vi } from 'vitest'
import { ApiError, configureApiErrorHandlers, reportGlobalError } from './api'

describe('reportGlobalError', () => {
  it('tells the user to wait when the API answers 429', () => {
    const onServerError = vi.fn()
    configureApiErrorHandlers({ onServerError })

    reportGlobalError(new ApiError(429, 'Too Many Attempts.', {}, 'TOO_MANY_REQUESTS'), 'orders/7/payment')

    expect(onServerError).toHaveBeenCalledWith('Muitas tentativas em pouco tempo. Aguarde um minuto e tente novamente.')
  })

  it('leaves 402 and 409 to each page', () => {
    const onServerError = vi.fn()
    const onForbidden = vi.fn()
    configureApiErrorHandlers({ onServerError, onForbidden })

    reportGlobalError(new ApiError(402, 'Pagamento recusado: saldo insuficiente.'), 'orders/7/payment')
    reportGlobalError(new ApiError(409, 'Este pedido não está aguardando pagamento.'), 'orders/7/payment')

    expect(onServerError).not.toHaveBeenCalled()
    expect(onForbidden).not.toHaveBeenCalled()
  })
})

describe('reportGlobalError 401 routing', () => {
  it.each([
    ['admin/dashboard', 'staff'],
    ['admin/users/3', 'staff'],
    ['orders', 'store'],
    ['auth/me', null],
    ['admin/auth/me', null],
  ])('routes each 401 to the login of its area: %s', (url, area) => {
    const onUnauthorized = vi.fn()
    const onStaffUnauthorized = vi.fn()
    configureApiErrorHandlers({ onUnauthorized, onStaffUnauthorized })

    reportGlobalError(new ApiError(401, 'Não autenticado.', {}, 'UNAUTHENTICATED'), url)

    expect(onStaffUnauthorized).toHaveBeenCalledTimes(area === 'staff' ? 1 : 0)
    expect(onUnauthorized).toHaveBeenCalledTimes(area === 'store' ? 1 : 0)
  })
})
