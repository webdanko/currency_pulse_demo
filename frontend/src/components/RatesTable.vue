<script setup>
import { computed, onUnmounted, ref, watch } from 'vue'

const props = defineProps({
  base: { type: String, required: true },
  rates: { type: Object, required: true },
  selected: { type: String, default: '' },
})

const emit = defineEmits(['select'])

const flags = {
  USD: '🇺🇸', GBP: '🇬🇧', JPY: '🇯🇵', CHF: '🇨🇭',
  PLN: '🇵🇱', EUR: '🇪🇺', CAD: '🇨🇦', AUD: '🇦🇺',
}

const rows = computed(() =>
  Object.entries(props.rates).map(([code, value]) => ({
    code,
    value,
    flag: flags[code] ?? '🏳️',
  })),
)

// Flash a row green/red for a moment whenever its rate ticks up or down —
// the strongest "the data is live" signal.
const prev = ref({})
const flash = ref({})
const timers = {}

watch(
  () => props.rates,
  (curr) => {
    for (const [code, val] of Object.entries(curr)) {
      const p = prev.value[code]
      if (p !== undefined && val !== p) {
        flash.value = { ...flash.value, [code]: val > p ? 'up' : 'down' }
        clearTimeout(timers[code])
        timers[code] = setTimeout(() => {
          const next = { ...flash.value }
          delete next[code]
          flash.value = next
        }, 800)
      }
    }
    prev.value = { ...curr }
  },
  { deep: true, immediate: true },
)

onUnmounted(() => Object.values(timers).forEach(clearTimeout))

function valueClass(code) {
  const f = flash.value[code]
  if (f === 'up') return 'text-emerald-300'
  if (f === 'down') return 'text-rose-300'
  return 'text-slate-200'
}

function arrow(code) {
  const f = flash.value[code]
  return f === 'up' ? '▲' : f === 'down' ? '▼' : ''
}
</script>

<template>
  <table class="w-full text-left">
    <thead>
      <tr class="text-xs uppercase tracking-wider text-slate-400">
        <th class="pb-3 font-medium">Currency</th>
        <th class="pb-3 text-right font-medium">1 {{ base }} =</th>
      </tr>
    </thead>
    <tbody class="divide-y divide-slate-800">
      <tr
        v-for="row in rows"
        :key="row.code"
        class="cursor-pointer transition-colors hover:bg-slate-800/40"
        :class="row.code === selected ? 'bg-slate-800/60' : ''"
        @click="emit('select', row.code)"
      >
        <td class="py-3">
          <span class="mr-2 text-lg">{{ row.flag }}</span>
          <span class="font-medium text-slate-100">{{ row.code }}</span>
        </td>
        <td
          class="py-3 text-right font-mono tabular-nums transition-colors duration-700"
          :class="valueClass(row.code)"
        >
          <span class="mr-1 inline-block w-2 text-[9px]">{{ arrow(row.code) }}</span>
          {{ row.value.toFixed(4) }}
        </td>
      </tr>
    </tbody>
  </table>
</template>
