import { afterEach, describe, expect, it, vi } from 'vitest'
import { createAlert, deleteAlert, fetchIntraday, fetchRates } from './api.js'

function mockFetch(ok, body, status = 200) {
  return vi.fn().mockResolvedValue({
    ok,
    status,
    json: () => Promise.resolve(body),
  })
}

describe('api client', () => {
  afterEach(() => vi.unstubAllGlobals())

  it('fetchRates unwraps the data envelope', async () => {
    vi.stubGlobal('fetch', mockFetch(true, { data: { base: 'EUR', rates: { USD: 1.08 } } }))
    const data = await fetchRates()
    expect(data.rates.USD).toBe(1.08)
  })

  it('fetchRates throws with the server message on error', async () => {
    vi.stubGlobal('fetch', mockFetch(false, { message: 'boom' }, 502))
    await expect(fetchRates()).rejects.toThrow('boom')
  })

  it('fetchIntraday hits the intraday endpoint with the symbol', async () => {
    const fetchMock = mockFetch(true, { data: { symbol: 'USD', points: [] } })
    vi.stubGlobal('fetch', fetchMock)
    await fetchIntraday('USD')
    expect(fetchMock).toHaveBeenCalledWith(
      expect.stringContaining('/api/rates/intraday?symbol=USD'),
      expect.anything(),
    )
  })

  it('createAlert POSTs the rule and returns it', async () => {
    const fetchMock = mockFetch(true, { data: { id: 1, quote: 'USD' } })
    vi.stubGlobal('fetch', fetchMock)
    const rule = await createAlert({ quote: 'USD', operator: 'above', threshold: 1.1 })
    expect(rule.id).toBe(1)
    expect(fetchMock).toHaveBeenCalledWith(
      expect.stringContaining('/api/alerts'),
      expect.objectContaining({ method: 'POST' }),
    )
  })

  it('deleteAlert issues a DELETE and tolerates 204', async () => {
    const fetchMock = mockFetch(true, {}, 204)
    vi.stubGlobal('fetch', fetchMock)
    await expect(deleteAlert(7)).resolves.toBeUndefined()
    expect(fetchMock).toHaveBeenCalledWith(
      expect.stringContaining('/api/alerts/7'),
      expect.objectContaining({ method: 'DELETE' }),
    )
  })
})
