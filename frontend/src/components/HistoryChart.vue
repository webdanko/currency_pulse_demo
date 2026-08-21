<script setup>
import { computed, ref } from 'vue'

const props = defineProps({
  points: { type: Array, required: true }, // [{ rate, captured_at }]
})

// viewBox geometry (uniform scaling; width:100% via CSS).
const W = 360
const H = 200
const PAD = { left: 44, right: 14, top: 14, bottom: 26 }
const plotW = W - PAD.left - PAD.right
const plotH = H - PAD.top - PAD.bottom

const svgEl = ref(null)
const hoverIndex = ref(-1)

const scale = computed(() => {
  const values = props.points.map((p) => p.rate)
  if (values.length < 2) return null

  const rawMin = Math.min(...values)
  const rawMax = Math.max(...values)
  const pad = (rawMax - rawMin || rawMax || 1) * 0.12
  const min = rawMin - pad
  const max = rawMax + pad
  const span = max - min || 1

  const x = (i) => PAD.left + (i / (props.points.length - 1)) * plotW
  const y = (v) => PAD.top + (1 - (v - min) / span) * plotH

  const coords = props.points.map((p, i) => ({ x: x(i), y: y(p.rate) }))
  const line = coords.map((c) => `${c.x.toFixed(1)},${c.y.toFixed(1)}`).join(' ')
  const area =
    `${PAD.left},${PAD.top + plotH} ` +
    line +
    ` ${PAD.left + plotW},${PAD.top + plotH}`

  // 4 horizontal gridlines / y labels
  const ticks = Array.from({ length: 4 }, (_, k) => {
    const v = min + (span * k) / 3
    return { v, y: y(v) }
  })

  return { min, max, span, x, y, coords, line, area, ticks, up: values.at(-1) >= values[0] }
})

// X-axis labels: intraday shows time, multi-day shows date.
const intraday = computed(() => {
  if (props.points.length < 2) return true
  const first = new Date(props.points[0].captured_at)
  const last = new Date(props.points.at(-1).captured_at)
  return last - first < 36 * 3600 * 1000
})

function fmtX(ts) {
  const d = new Date(ts)
  return intraday.value
    ? d.toLocaleTimeString([], { hour: '2-digit', minute: '2-digit' })
    : d.toLocaleDateString([], { month: 'short', day: 'numeric' })
}

function fmtTip(ts) {
  const d = new Date(ts)
  return intraday.value
    ? d.toLocaleTimeString([], { hour: '2-digit', minute: '2-digit' })
    : d.toLocaleDateString([], { month: 'short', day: 'numeric' })
}

const xLabels = computed(() => {
  const n = props.points.length
  if (n < 2 || !scale.value) return []
  return [0, Math.floor((n - 1) / 2), n - 1].map((i) => ({
    x: scale.value.x(i),
    text: fmtX(props.points[i].captured_at),
  }))
})

const hover = computed(() => {
  if (hoverIndex.value < 0 || !scale.value) return null
  const p = props.points[hoverIndex.value]
  const c = scale.value.coords[hoverIndex.value]
  return {
    x: c.x,
    y: c.y,
    rate: p.rate,
    label: fmtTip(p.captured_at),
    tipX: Math.min(Math.max(c.x, PAD.left + 34), W - PAD.right - 34),
  }
})

function onMove(e) {
  if (!scale.value || !svgEl.value) return
  const rect = svgEl.value.getBoundingClientRect()
  const vx = ((e.clientX - rect.left) / rect.width) * W
  const ratio = (vx - PAD.left) / plotW
  const i = Math.round(ratio * (props.points.length - 1))
  hoverIndex.value = Math.min(Math.max(i, 0), props.points.length - 1)
}

function onLeave() {
  hoverIndex.value = -1
}
</script>

<template>
  <div>
    <div v-if="!scale" class="flex h-40 items-center justify-center text-sm text-slate-500">
      Not enough data yet — the poller is still collecting.
    </div>

    <svg
      v-else
      ref="svgEl"
      :viewBox="`0 0 ${W} ${H}`"
      class="w-full select-none"
      @mousemove="onMove"
      @mouseleave="onLeave"
    >
      <defs>
        <linearGradient :id="scale.up ? 'g-up' : 'g-down'" x1="0" y1="0" x2="0" y2="1">
          <stop offset="0%" :stop-color="scale.up ? '#34d399' : '#fb7185'" stop-opacity="0.28" />
          <stop offset="100%" :stop-color="scale.up ? '#34d399' : '#fb7185'" stop-opacity="0" />
        </linearGradient>
      </defs>

      <!-- gridlines + y labels -->
      <g class="text-slate-500" font-size="8">
        <template v-for="(t, i) in scale.ticks" :key="i">
          <line
            :x1="PAD.left"
            :x2="W - PAD.right"
            :y1="t.y.toFixed(1)"
            :y2="t.y.toFixed(1)"
            stroke="currentColor"
            stroke-opacity="0.15"
          />
          <text :x="PAD.left - 6" :y="t.y + 2.5" text-anchor="end" fill="currentColor">
            {{ t.v.toFixed(4) }}
          </text>
        </template>
      </g>

      <!-- x labels -->
      <g class="text-slate-500" font-size="8">
        <text
          v-for="(l, i) in xLabels"
          :key="i"
          :x="l.x"
          :y="H - 8"
          text-anchor="middle"
          fill="currentColor"
        >
          {{ l.text }}
        </text>
      </g>

      <!-- area + line -->
      <polygon :points="scale.area" :fill="`url(#${scale.up ? 'g-up' : 'g-down'})`" />
      <polyline
        :points="scale.line"
        fill="none"
        stroke-width="1.75"
        stroke-linejoin="round"
        stroke-linecap="round"
        :class="scale.up ? 'stroke-emerald-400' : 'stroke-rose-400'"
      />

      <!-- hover crosshair + tooltip -->
      <g v-if="hover">
        <line
          :x1="hover.x"
          :x2="hover.x"
          :y1="PAD.top"
          :y2="PAD.top + plotH"
          stroke="currentColor"
          class="text-slate-500"
          stroke-opacity="0.4"
          stroke-dasharray="2 2"
        />
        <circle :cx="hover.x" :cy="hover.y" r="3" class="fill-slate-100" />
        <g :transform="`translate(${hover.tipX}, ${PAD.top + 2})`">
          <rect x="-34" y="-2" width="68" height="26" rx="5" class="fill-slate-800" stroke="#334155" />
          <text x="0" y="8" text-anchor="middle" font-size="9" class="fill-slate-100" font-weight="600">
            {{ hover.rate.toFixed(4) }}
          </text>
          <text x="0" y="18" text-anchor="middle" font-size="7" class="fill-slate-400">
            {{ hover.label }}
          </text>
        </g>
      </g>
    </svg>
  </div>
</template>
