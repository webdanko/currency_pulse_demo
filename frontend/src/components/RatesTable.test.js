import { describe, expect, it } from 'vitest'
import { mount } from '@vue/test-utils'
import { nextTick } from 'vue'
import RatesTable from './RatesTable.vue'

describe('RatesTable', () => {
  it('renders a row per currency and emits select on click', async () => {
    const wrapper = mount(RatesTable, {
      props: { base: 'EUR', rates: { USD: 1.08, GBP: 0.85 }, selected: 'USD' },
    })

    const rows = wrapper.findAll('tbody tr')
    expect(rows).toHaveLength(2)

    await rows[1].trigger('click')
    expect(wrapper.emitted('select')[0]).toEqual(['GBP'])
  })

  it('flashes green with an up arrow when a rate rises', async () => {
    const wrapper = mount(RatesTable, {
      props: { base: 'EUR', rates: { USD: 1.08 }, selected: '' },
    })

    // First change after the baseline (seeded on mount) should flash.
    await wrapper.setProps({ rates: { USD: 1.09 } })
    await nextTick()

    const cell = wrapper.find('tbody tr td:last-child')
    expect(cell.classes()).toContain('text-emerald-300')
    expect(cell.text()).toContain('▲')
  })

  it('flashes red with a down arrow when a rate falls', async () => {
    const wrapper = mount(RatesTable, {
      props: { base: 'EUR', rates: { USD: 1.09 }, selected: '' },
    })

    await wrapper.setProps({ rates: { USD: 1.08 } })
    await nextTick()

    const cell = wrapper.find('tbody tr td:last-child')
    expect(cell.classes()).toContain('text-rose-300')
    expect(cell.text()).toContain('▼')
  })
})
