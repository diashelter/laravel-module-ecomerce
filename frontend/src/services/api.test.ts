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
