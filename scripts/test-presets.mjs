import {readFile} from 'node:fs/promises'
import assert from 'node:assert/strict'

const canonicalText = await readFile('config/chain-presets.json', 'utf8')
const presets = JSON.parse(canonicalText)
const required = ['wax', 'telos', 'vaulta']
assert.deepEqual(Object.keys(presets), required)

for (const path of ['wordpress/antelope-wallets/chain-presets.json', 'drupal/antelope_wallets/chain-presets.json']) {
  assert.equal(await readFile(path, 'utf8'), canonicalText, `${path} must match config/chain-presets.json`)
}

for (const preset of Object.values(presets)) {
  assert.match(preset.chain_id, /^[a-f0-9]{64}$/)
  assert.match(preset.rpc_endpoint, /^https:\/\//)
  assert.match(preset.token_contract, /^[a-z1-5.]{1,13}$/)
  assert.match(preset.token_symbol, /^[A-Z]{1,7}$/)
  assert.ok(Number.isInteger(preset.token_precision) && preset.token_precision >= 0 && preset.token_precision <= 18)
}

if (!process.argv.includes('--live')) {
  console.log('Preset structure and CMS copies verified.')
  process.exit(0)
}

async function rpc(preset, path, body = {}) {
  const response = await fetch(`${preset.rpc_endpoint}${path}`, {
    method: 'POST',
    headers: {'content-type': 'application/json'},
    body: JSON.stringify(body),
    signal: AbortSignal.timeout(15000),
  })
  assert.equal(response.ok, true, `${preset.label} ${path} returned HTTP ${response.status}`)
  return response.json()
}

for (const [key, preset] of Object.entries(presets)) {
  const info = await rpc(preset, '/v1/chain/get_info')
  assert.equal(String(info.chain_id).toLowerCase(), preset.chain_id, `${preset.label} chain ID mismatch`)

  const stats = await rpc(preset, '/v1/chain/get_currency_stats', {
    code: preset.token_contract,
    symbol: preset.token_symbol,
  })
  const token = stats[preset.token_symbol]
  assert.ok(token, `${preset.label} ${preset.token_symbol} stats missing from ${preset.token_contract}`)
  assert.match(token.max_supply || token.supply || '', new RegExp(` ${preset.token_symbol}$`), `${preset.label} token symbol mismatch`)
  console.log(`PASS ${key}: ${preset.rpc_endpoint} -> ${info.chain_id.slice(0, 12)}…; ${preset.token_contract}/${preset.token_symbol}`)
}
