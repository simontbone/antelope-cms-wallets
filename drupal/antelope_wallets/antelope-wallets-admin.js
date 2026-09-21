(() => {
  function bootPresetForm(form, presets) {
    if (!form || form.dataset.antelopePresetReady === '1') return
    const select = form.querySelector('[data-antelope-preset-select]')
    if (!select) return
    form.dataset.antelopePresetReady = '1'
    const field = (name) => form.querySelector(`[data-antelope-preset-field="${name}"]`)
    const setValue = (name, value) => { const el = field(name); if (el) el.value = value }
    select.addEventListener('change', () => {
      const preset = presets?.[select.value]
      if (!preset) return
      setValue('chain_id', preset.chain_id)
      setValue('rpc_endpoint', preset.rpc_endpoint)
      setValue('token_contract', preset.token_contract)
      setValue('token_symbol', preset.token_symbol)
      setValue('token_precision', preset.token_precision)
      const anchor = field('enable_anchor')
      const cloud = field('enable_cloud_wallet')
      if (anchor) anchor.checked = true
      if (cloud) cloud.checked = Boolean(preset.enable_cloud_wallet)
    })
  }
  function boot() {
    const presets = window.AntelopeCMSPresets || window.drupalSettings?.antelopeWalletPresets || {}
    document.querySelectorAll('[data-antelope-preset-form]').forEach((form) => bootPresetForm(form, presets))
  }
  if (document.readyState === 'loading') document.addEventListener('DOMContentLoaded', boot)
  else boot()
  document.addEventListener('drupalBehaviorAttach', boot)
})()
