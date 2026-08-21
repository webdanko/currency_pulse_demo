const BASE_URL = import.meta.env.VITE_API_URL ?? 'http://localhost:8000'

async function getJson(path) {
  const response = await fetch(`${BASE_URL}${path}`, {
    headers: { Accept: 'application/json' },
  })

  if (!response.ok) {
    const body = await response.json().catch(() => ({}))
    throw new Error(body.message ?? `Request failed (${response.status})`)
  }

  return response.json()
}

/** Current rates (cached server-side). */
export async function fetchRates() {
  const { data } = await getJson('/api/rates')
  return data
}

/** Historical series for one currency. */
export async function fetchHistory(symbol, hours = 24) {
  const { data } = await getJson(`/api/rates/history?symbol=${symbol}&hours=${hours}`)
  return data
}

/** Intraday series for the last 24h (provider, with server-side snapshot fallback). */
export async function fetchIntraday(symbol) {
  const { data } = await getJson(`/api/rates/intraday?symbol=${symbol}`)
  return data
}

/** Daily historical series pulled from the provider (for multi-day charts). */
export async function fetchTimeseries(symbol, days = 30) {
  const { data } = await getJson(`/api/rates/timeseries?symbol=${symbol}&days=${days}`)
  return data
}

/** List alert rules. */
export async function fetchAlerts() {
  const { data } = await getJson('/api/alerts')
  return data
}

/** Create an alert rule. */
export async function createAlert(rule) {
  const response = await fetch(`${BASE_URL}/api/alerts`, {
    method: 'POST',
    headers: { 'Content-Type': 'application/json', Accept: 'application/json' },
    body: JSON.stringify(rule),
  })

  const body = await response.json().catch(() => ({}))
  if (!response.ok) {
    throw new Error(body.message ?? `Request failed (${response.status})`)
  }
  return body.data
}

/** Enable/disable (or edit) an alert rule. */
export async function updateAlert(id, patch) {
  const response = await fetch(`${BASE_URL}/api/alerts/${id}`, {
    method: 'PATCH',
    headers: { 'Content-Type': 'application/json', Accept: 'application/json' },
    body: JSON.stringify(patch),
  })
  const body = await response.json().catch(() => ({}))
  if (!response.ok) throw new Error(body.message ?? `Request failed (${response.status})`)
  return body.data
}

/** Delete an alert rule. */
export async function deleteAlert(id) {
  const response = await fetch(`${BASE_URL}/api/alerts/${id}`, {
    method: 'DELETE',
    headers: { Accept: 'application/json' },
  })
  if (!response.ok && response.status !== 204) {
    throw new Error(`Request failed (${response.status})`)
  }
}
