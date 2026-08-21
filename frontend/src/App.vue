<script setup>
import { computed, onMounted, onUnmounted, ref } from 'vue'
import {
  createAlert,
  deleteAlert,
  fetchAlerts,
  fetchIntraday,
  fetchRates,
  fetchTimeseries,
  updateAlert,
} from './api.js'
import { createEcho } from './echo.js'
import RatesTable from './components/RatesTable.vue'
import HistoryChart from './components/HistoryChart.vue'

const REFRESH_MS = 60_000
const MAX_POINTS = 240

const state = ref('loading') // 'loading' | 'ready' | 'error'
const payload = ref(null)
const error = ref('')
const selected = ref('')
const timeframe = ref('24h') // '24h' | '7d' | '30d'
const timeframes = ['24h', '7d', '30d']
const history = ref([])
const loadingSeries = ref(false)
const live = ref(false)
const toast = ref('')
const lastTickAt = ref(0)
const nowMs = ref(0)

const form = ref({ operator: 'above', threshold: '' })
const formError = ref('')
const submitting = ref(false)
const alerts = ref([])

let echo = null
let timer = null
let toastTimer = null
let heartbeat = null

// Seconds since the last update the client actually received.
const secondsAgo = computed(() =>
  lastTickAt.value ? Math.max(0, Math.floor((nowMs.value - lastTickAt.value) / 1000)) : null,
)
// If updates dry up for a while, the stream is probably stuck.
const stale = computed(() => secondsAgo.value !== null && secondsAgo.value > 30)

function markTick() {
  lastTickAt.value = Date.now()
  nowMs.value = lastTickAt.value
}

async function loadRates() {
  try {
    payload.value = await fetchRates()
    state.value = 'ready'
    error.value = ''
    markTick()
    if (!selected.value) {
      const keys = Object.keys(payload.value.rates)
      selected.value = keys.includes('USD') ? 'USD' : keys[0]
    }
  } catch (e) {
    error.value = e.message
    state.value = payload.value ? 'ready' : 'error'
  }
}

async function loadSeries(silent = false) {
  const symbol = selected.value
  if (!symbol) return
  if (!silent) loadingSeries.value = true
  try {
    const data =
      timeframe.value === '24h'
        ? await fetchIntraday(symbol)
        : await fetchTimeseries(symbol, timeframe.value === '7d' ? 7 : 30)
    history.value = data.points
  } catch {
    if (!silent) history.value = []
  } finally {
    loadingSeries.value = false
  }
}

function selectSymbol(code) {
  selected.value = code
  loadSeries()
}

function setTimeframe(tf) {
  timeframe.value = tf
  loadSeries()
}

function showToast(message) {
  toast.value = message
  clearTimeout(toastTimer)
  toastTimer = setTimeout(() => (toast.value = ''), 6000)
}

function setupEcho() {
  echo = createEcho()
  if (!echo) return

  // Reflect the real socket state so the badge is truthful: "live" only once
  // Reverb actually accepts the connection, back to "polling" if it drops.
  const conn = echo.connector?.pusher?.connection
  if (conn) {
    conn.bind('connected', () => (live.value = true))
    conn.bind('disconnected', () => (live.value = false))
    conn.bind('unavailable', () => (live.value = false))
    conn.bind('error', () => (live.value = false))
  }

  echo.channel('rates').listen('.rates.updated', (e) => {
    payload.value = {
      ...payload.value,
      base: e.base,
      rates: e.rates,
      fetched_at: e.captured_at,
      cached: false,
    }
    markTick()
    const rate = e.rates[selected.value]
    if (rate !== undefined && timeframe.value === '24h') {
      history.value = [...history.value, { rate, captured_at: e.captured_at }].slice(-MAX_POINTS)
    }
  })

  echo.channel('alerts').listen('.alert.triggered', (e) => {
    showToast(`🔔 ${e.message}`)
    loadAlerts()
  })
}

async function submitAlert() {
  formError.value = ''
  const threshold = Number(form.value.threshold)
  if (!selected.value || !Number.isFinite(threshold) || threshold <= 0) {
    formError.value = 'Pick a currency and a positive threshold.'
    return
  }
  submitting.value = true
  try {
    await createAlert({ quote: selected.value, operator: form.value.operator, threshold })
    showToast(`Alert saved: ${selected.value} ${form.value.operator} ${threshold}`)
    form.value.threshold = ''
    await loadAlerts()
  } catch (e) {
    formError.value = e.message
  } finally {
    submitting.value = false
  }
}

async function loadAlerts() {
  try {
    alerts.value = await fetchAlerts()
  } catch {
    /* non-critical */
  }
}

async function toggleAlert(rule) {
  try {
    const updated = await updateAlert(rule.id, { active: !rule.active })
    alerts.value = alerts.value.map((a) => (a.id === updated.id ? updated : a))
  } catch {
    showToast("Couldn't update the alert")
  }
}

async function removeAlert(rule) {
  try {
    await deleteAlert(rule.id)
    alerts.value = alerts.value.filter((a) => a.id !== rule.id)
  } catch {
    showToast("Couldn't delete the alert")
  }
}

onMounted(async () => {
  await loadRates()
  await loadSeries()
  loadAlerts()
  setupEcho()
  // Polling fallback: refresh rates every minute, and — when no socket is
  // connected — refresh the live chart too so it still grows without Reverb.
  timer = setInterval(() => {
    loadRates()
    if (timeframe.value === '24h' && !live.value) loadSeries(true)
  }, REFRESH_MS)
  // 1s heartbeat so the "updated Ns ago" counter ticks even between updates.
  heartbeat = setInterval(() => (nowMs.value = Date.now()), 1000)
})

onUnmounted(() => {
  clearInterval(timer)
  clearInterval(heartbeat)
  clearTimeout(toastTimer)
  echo?.disconnect()
})
</script>

<template>
  <main class="min-h-full bg-slate-950 px-4 py-12 text-slate-100">
    <div class="mx-auto max-w-lg">
      <header class="mb-8 flex items-start justify-between">
        <div>
          <h1 class="text-2xl font-semibold tracking-tight">FX&nbsp;Pulse</h1>
          <p class="mt-1 text-sm text-slate-400">
            Live exchange rates with history and threshold alerts.
          </p>
        </div>
        <span
          class="inline-flex items-center gap-1.5 rounded-full px-2.5 py-1 text-xs font-medium"
          :class="live ? 'bg-emerald-500/10 text-emerald-300' : 'bg-slate-800 text-slate-400'"
        >
          <span class="relative flex h-1.5 w-1.5">
            <span
              v-if="live"
              class="absolute inline-flex h-full w-full animate-ping rounded-full bg-emerald-400 opacity-75"
            />
            <span
              class="relative inline-flex h-1.5 w-1.5 rounded-full"
              :class="live ? 'bg-emerald-400' : 'bg-slate-500'"
            />
          </span>
          {{ live ? 'live' : 'polling' }}
        </span>
      </header>

      <section class="rounded-2xl border border-slate-800 bg-slate-900/60 p-6 shadow-xl">
        <div v-if="state === 'loading'" class="py-10 text-center text-slate-400">
          Loading rates…
        </div>

        <div v-else-if="state === 'error'" class="py-10 text-center">
          <p class="text-rose-400">Couldn't load rates.</p>
          <p class="mt-1 text-sm text-slate-500">{{ error }}</p>
          <button
            class="mt-4 rounded-lg bg-slate-800 px-4 py-2 text-sm font-medium text-slate-100 transition hover:bg-slate-700"
            @click="loadRates"
          >
            Retry
          </button>
        </div>

        <div v-else>
          <RatesTable
            :base="payload.base"
            :rates="payload.rates"
            :selected="selected"
            @select="selectSymbol"
          />

          <div class="mt-6 border-t border-slate-800 pt-5">
            <div class="mb-3 flex items-center justify-between">
              <h2 class="text-sm font-medium text-slate-300">
                {{ selected }}<span class="text-slate-500"> / {{ payload.base }}</span>
              </h2>
              <div class="flex gap-1 rounded-lg bg-slate-800/60 p-0.5">
                <button
                  v-for="tf in timeframes"
                  :key="tf"
                  class="rounded-md px-2.5 py-1 text-xs font-medium transition"
                  :class="tf === timeframe ? 'bg-slate-700 text-slate-100' : 'text-slate-400 hover:text-slate-200'"
                  @click="setTimeframe(tf)"
                >
                  {{ tf }}
                </button>
              </div>
            </div>
            <div :class="loadingSeries ? 'opacity-50 transition-opacity' : ''">
              <HistoryChart :points="history" />
            </div>
            <p class="mt-1 text-center text-xs text-slate-600">tap a currency above to switch the chart</p>
          </div>

          <form class="mt-6 border-t border-slate-800 pt-5" @submit.prevent="submitAlert">
            <h2 class="mb-3 text-sm font-medium text-slate-300">Alert me when {{ selected }} is…</h2>
            <div class="flex items-center gap-2">
              <select
                v-model="form.operator"
                class="rounded-lg border border-slate-700 bg-slate-800 px-2 py-2 text-sm text-slate-100"
              >
                <option value="above">above</option>
                <option value="below">below</option>
              </select>
              <input
                v-model="form.threshold"
                type="number"
                step="0.0001"
                placeholder="1.1000"
                class="w-28 rounded-lg border border-slate-700 bg-slate-800 px-3 py-2 text-sm text-slate-100 placeholder:text-slate-500"
              />
              <button
                type="submit"
                :disabled="submitting"
                class="ml-auto rounded-lg bg-emerald-600 px-4 py-2 text-sm font-medium text-white transition hover:bg-emerald-500 disabled:opacity-50"
              >
                Add
              </button>
            </div>
            <p v-if="formError" class="mt-2 text-xs text-rose-400">{{ formError }}</p>

            <ul v-if="alerts.length" class="mt-4 space-y-2">
              <li
                v-for="a in alerts"
                :key="a.id"
                class="flex items-center justify-between rounded-lg bg-slate-800/40 px-3 py-2 text-sm"
              >
                <span
                  class="flex items-center gap-2"
                  :class="a.active ? 'text-slate-200' : 'text-slate-500 line-through'"
                >
                  <span class="font-medium">{{ a.quote }}</span>
                  <span class="text-slate-400">{{ a.operator }}</span>
                  <span class="font-mono">{{ Number(a.threshold).toFixed(4) }}</span>
                  <span v-if="a.last_triggered_at" class="text-[10px] text-amber-400/80">triggered</span>
                </span>
                <span class="flex items-center gap-2">
                  <button
                    type="button"
                    class="rounded px-2 py-0.5 text-xs transition"
                    :class="a.active
                      ? 'bg-emerald-600/20 text-emerald-300 hover:bg-emerald-600/30'
                      : 'bg-slate-700 text-slate-400 hover:bg-slate-600'"
                    @click="toggleAlert(a)"
                  >
                    {{ a.active ? 'on' : 'off' }}
                  </button>
                  <button
                    type="button"
                    class="rounded px-2 py-0.5 text-xs text-rose-400 transition hover:bg-rose-500/10"
                    aria-label="delete alert"
                    @click="removeAlert(a)"
                  >
                    ✕
                  </button>
                </span>
              </li>
            </ul>
            <p v-else class="mt-3 text-xs text-slate-600">No alerts yet.</p>
          </form>

          <footer class="mt-6 flex items-center justify-between text-xs text-slate-500">
            <span>{{ live ? 'streaming live' : 'polling every 60s' }}</span>
            <span class="inline-flex items-center gap-1.5" :class="stale ? 'text-amber-400' : ''">
              <span class="relative flex h-1.5 w-1.5">
                <span
                  v-if="!stale && secondsAgo !== null"
                  class="absolute inline-flex h-full w-full animate-ping rounded-full bg-emerald-400 opacity-75"
                />
                <span
                  class="relative inline-flex h-1.5 w-1.5 rounded-full"
                  :class="stale ? 'bg-amber-400' : 'bg-emerald-400'"
                />
              </span>
              <span v-if="secondsAgo === null">connecting…</span>
              <span v-else-if="stale">no updates for {{ secondsAgo }}s</span>
              <span v-else>updated {{ secondsAgo }}s ago</span>
            </span>
          </footer>
        </div>
      </section>

      <p class="mt-6 text-center text-xs text-slate-600">
        Laravel · Reverb (WebSockets) · queues · Vue 3 · Tailwind · Docker
      </p>
    </div>

    <transition
      enter-active-class="transition duration-200"
      enter-from-class="translate-y-2 opacity-0"
      leave-active-class="transition duration-200"
      leave-to-class="translate-y-2 opacity-0"
    >
      <div
        v-if="toast"
        class="fixed inset-x-0 bottom-6 mx-auto w-fit max-w-[90%] rounded-xl border border-slate-700 bg-slate-800 px-4 py-3 text-sm text-slate-100 shadow-2xl"
      >
        {{ toast }}
      </div>
    </transition>
  </main>
</template>
