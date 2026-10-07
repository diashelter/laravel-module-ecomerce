import { beforeEach, describe, expect, it, vi } from 'vitest'
import { ApiError } from '@/services/api'
import { addressService } from '@/services/addressService'
import type { AddressPayload, CustomerAddress } from '@/types'
import { useAddressBook } from './useAddressBook'

vi.mock('@/services/addressService', () => ({
  addressService: { list: vi.fn(), create: vi.fn(), update: vi.fn(), remove: vi.fn() },
}))

function address(id: number, name = 'Ana Souza'): CustomerAddress {
  return {
    id,
    recipient_name: name,
    postal_code: '01310100',
    street: 'Avenida Paulista',
    number: '1000',
    complement: null,
    district: 'Bela Vista',
    city: 'São Paulo',
    state: 'SP',
    created_at: '',
  }
}

const payload: AddressPayload = {
  recipient_name: 'Ana Souza',
  postal_code: '01310-100',
  street: 'Avenida Paulista',
  number: '1000',
  complement: '',
  district: 'Bela Vista',
  city: 'São Paulo',
  state: 'SP',
}

describe('useAddressBook', () => {
  beforeEach(() => {
    vi.clearAllMocks()
  })

  it('reports a failed address book load', async () => {
    let reject!: (reason: Error) => void
    vi.mocked(addressService.list).mockReturnValue(new Promise((_, r) => (reject = r)))
    const book = useAddressBook()

    const loading = book.load()
    expect(book.loading.value).toBe(true)

    reject(new Error('network'))
    await loading

    expect(book.loadError.value).toBe('Não foi possível carregar os endereços.')
    expect(book.loading.value).toBe(false)
  })

  it('flags an empty address book and keeps the API order', async () => {
    const book = useAddressBook()

    vi.mocked(addressService.list).mockResolvedValue([])
    await book.load()
    expect(book.isEmpty.value).toBe(true)

    vi.mocked(addressService.list).mockResolvedValue([address(5), address(2), address(9)])
    await book.load()
    expect(book.isEmpty.value).toBe(false)
    expect(book.addresses.value.map((current) => current.id)).toEqual([5, 2, 9])
  })

  it('maps address form errors from the API', async () => {
    const book = useAddressBook()

    vi.mocked(addressService.create).mockRejectedValueOnce(
      new ApiError(422, 'Dados inválidos.', { postal_code: ['O CEP deve ter 8 dígitos.'], city: ['O campo cidade é obrigatório.'] }),
    )
    expect(await book.save(payload)).toBeNull()
    expect(book.firstError('postal_code')).toBe('O CEP deve ter 8 dígitos.')
    expect(book.firstError('city')).toBe('O campo cidade é obrigatório.')
    expect(book.firstError('street')).toBeUndefined()
    expect(book.formMessage.value).toBeNull()

    vi.mocked(addressService.create).mockRejectedValueOnce(new ApiError(409, 'Você pode cadastrar até 10 endereços.'))
    expect(await book.save(payload)).toBeNull()
    expect(book.formMessage.value).toBe('Você pode cadastrar até 10 endereços.')
    expect(book.firstError('postal_code')).toBeUndefined()
  })

  it('puts a saved address first and replaces an edited one in place', async () => {
    vi.mocked(addressService.list).mockResolvedValue([address(2), address(1)])
    const book = useAddressBook()
    await book.load()

    vi.mocked(addressService.create).mockResolvedValue(address(3))
    await book.save(payload)
    expect(book.addresses.value.map((current) => current.id)).toEqual([3, 2, 1])

    vi.mocked(addressService.update).mockResolvedValue(address(2, 'Beto'))
    await book.save(payload, 2)
    expect(addressService.update).toHaveBeenCalledWith(2, payload)
    expect(book.addresses.value.map((current) => current.recipient_name)).toEqual(['Ana Souza', 'Beto', 'Ana Souza'])
  })

  it('asks before deleting an address', async () => {
    const confirm = vi.fn()
    vi.stubGlobal('window', { confirm })
    vi.mocked(addressService.list).mockResolvedValue([address(1), address(2)])
    vi.mocked(addressService.remove).mockResolvedValue()
    const book = useAddressBook()
    await book.load()

    confirm.mockReturnValue(false)
    expect(await book.remove(address(1))).toBe(false)
    expect(confirm).toHaveBeenCalledWith('Excluir o endereço de "Ana Souza"?')
    expect(addressService.remove).not.toHaveBeenCalled()
    expect(book.addresses.value).toHaveLength(2)

    confirm.mockReturnValue(true)
    expect(await book.remove(address(1))).toBe(true)
    expect(addressService.remove).toHaveBeenCalledWith(1)
    expect(book.addresses.value.map((current) => current.id)).toEqual([2])

    vi.unstubAllGlobals()
  })

  it('keeps the address and reports a failed delete', async () => {
    vi.stubGlobal('window', { confirm: vi.fn().mockReturnValue(true) })
    vi.mocked(addressService.list).mockResolvedValue([address(1)])
    vi.mocked(addressService.remove).mockRejectedValue(new ApiError(404, 'Registro não encontrado.', {}))
    const book = useAddressBook()
    await book.load()

    expect(await book.remove(address(1))).toBe(false)
    expect(book.formMessage.value).toBe('Registro não encontrado.')
    expect(book.addresses.value.map((current) => current.id)).toEqual([1])

    vi.unstubAllGlobals()
  })
})
