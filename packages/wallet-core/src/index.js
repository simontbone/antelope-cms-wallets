import {SessionKit, BrowserLocalStorage} from '@wharfkit/session'
import {WebRenderer} from '@wharfkit/web-renderer'
import {WalletPluginAnchor} from '@wharfkit/wallet-plugin-anchor'
import {WalletPluginCloudWallet} from '@wharfkit/wallet-plugin-cloudwallet'

const instances = new WeakMap()

function text(el, value) {
  if (el) el.textContent = value == null ? '' : String(value)
}

function emit(root, name, detail = {}) {
  root.dispatchEvent(new CustomEvent(`antelope:${name}`, {detail, bubbles: true}))
}

function normalizeConfig(root) {
  const globalConfig = window.AntelopeCMS || window.drupalSettings?.antelopeWallets || {}
  return {
    appName: root.dataset.appName || globalConfig.appName || 'Antelope CMS Wallet',
    chainId: root.dataset.chainId || globalConfig.chainId || '1064487b3cd1a897ce03ae5b6a865651747e2e152090f99c1d19d44e01aea5a4',
    rpcEndpoint: root.dataset.rpcEndpoint || globalConfig.rpcEndpoint || 'https://wax.greymass.com',
    tokenContract: root.dataset.tokenContract || globalConfig.tokenContract || 'eosio.token',
    tokenSymbol: root.dataset.tokenSymbol || globalConfig.tokenSymbol || 'WAX',
    tokenPrecision: Number(root.dataset.tokenPrecision || globalConfig.tokenPrecision || 8),
    enableAnchor: String(root.dataset.enableAnchor ?? globalConfig.enableAnchor ?? '1') !== '0',
    enableCloudWallet: String(root.dataset.enableCloudWallet ?? globalConfig.enableCloudWallet ?? '1') !== '0'
  }
}

class AntelopeWalletWidget {
  constructor(root) {
    this.root = root
    this.config = normalizeConfig(root)
    this.session = null
    this.sessionKit = null
    this.busy = false
  }

  async init() {
    const plugins = []
    if (this.config.enableAnchor) plugins.push(new WalletPluginAnchor())
    if (this.config.enableCloudWallet) plugins.push(new WalletPluginCloudWallet())
    if (!plugins.length) throw new Error('At least one wallet must be enabled.')

    this.sessionKit = new SessionKit({
      appName: this.config.appName,
      chains: [{id: this.config.chainId, url: this.config.rpcEndpoint}],
      ui: new WebRenderer(),
      walletPlugins: plugins,
      storage: new BrowserLocalStorage('antelope-cms-wallets-')
    })

    this.bind()
    try {
      this.session = await this.sessionKit.restore({chain: this.config.chainId})
    } catch (_) {
      this.session = null
    }
    this.render()
    if (this.session) await this.refreshBalance()
  }

  bind() {
    this.root.querySelector('[data-antelope-connect]')?.addEventListener('click', () => this.login())
    this.root.querySelector('[data-antelope-disconnect]')?.addEventListener('click', () => this.logout())
    this.root.querySelector('[data-antelope-refresh]')?.addEventListener('click', () => this.refreshBalance())
    this.root.querySelector('[data-antelope-transfer]')?.addEventListener('submit', (event) => {
      event.preventDefault()
      const form = new FormData(event.currentTarget)
      this.transfer({
        to: form.get('to'),
        quantity: form.get('quantity'),
        memo: form.get('memo') || ''
      })
    })
    this.root.querySelector('[data-antelope-action]')?.addEventListener('submit', (event) => {
      event.preventDefault()
      const form = new FormData(event.currentTarget)
      let data
      try { data = JSON.parse(form.get('data') || '{}') } catch (_) { return this.setStatus('Action data must be valid JSON.', true) }
      this.transact({
        account: form.get('account'),
        name: form.get('name'),
        data
      })
    })
  }

  setBusy(value) {
    this.busy = value
    this.root.querySelectorAll('button, input, textarea').forEach(el => { el.disabled = value })
  }

  setStatus(message, isError = false) {
    const el = this.root.querySelector('[data-antelope-status]')
    if (el) {
      el.textContent = message || ''
      el.dataset.error = isError ? '1' : '0'
    }
  }

  render() {
    const connected = Boolean(this.session)
    text(this.root.querySelector('[data-antelope-account]'), connected ? String(this.session.actor) : 'Not connected')
    this.root.querySelector('[data-antelope-connect]')?.toggleAttribute('hidden', connected)
    this.root.querySelector('[data-antelope-disconnect]')?.toggleAttribute('hidden', !connected)
    this.root.querySelectorAll('[data-antelope-connected-only]').forEach(el => el.toggleAttribute('hidden', !connected))
    emit(this.root, connected ? 'connected' : 'disconnected', connected ? {account: String(this.session.actor)} : {})
  }

  async login() {
    try {
      this.setBusy(true)
      this.setStatus('Opening wallet…')
      const result = await this.sessionKit.login({chain: this.config.chainId})
      this.session = result.session
      this.render()
      this.setStatus('Connected.')
      await this.refreshBalance()
    } catch (error) {
      this.setStatus(error?.message || 'Wallet connection failed.', true)
      emit(this.root, 'error', {error})
    } finally {
      this.setBusy(false)
    }
  }

  async logout() {
    try {
      this.setBusy(true)
      if (this.session) await this.sessionKit.logout(this.session)
      this.session = null
      text(this.root.querySelector('[data-antelope-balance]'), '—')
      this.render()
      this.setStatus('Disconnected.')
    } catch (error) {
      this.setStatus(error?.message || 'Disconnect failed.', true)
    } finally {
      this.setBusy(false)
    }
  }

  async rpc(path, body) {
    const response = await fetch(`${this.config.rpcEndpoint.replace(/\/$/, '')}${path}`, {
      method: 'POST',
      headers: {'content-type': 'application/json'},
      body: JSON.stringify(body)
    })
    if (!response.ok) throw new Error(`RPC request failed (${response.status}).`)
    return response.json()
  }

  async refreshBalance() {
    if (!this.session) return
    try {
      const rows = await this.rpc('/v1/chain/get_currency_balance', {
        code: this.config.tokenContract,
        account: String(this.session.actor),
        symbol: this.config.tokenSymbol
      })
      text(this.root.querySelector('[data-antelope-balance]'), rows[0] || `0 ${this.config.tokenSymbol}`)
      emit(this.root, 'balance', {balance: rows[0] || null})
    } catch (error) {
      this.setStatus(error?.message || 'Could not load token balance.', true)
    }
  }

  async transfer({to, quantity, memo = ''}) {
    if (!this.session) return this.setStatus('Connect a wallet first.', true)
    const numeric = Number(quantity)
    if (!to || !Number.isFinite(numeric) || numeric <= 0) return this.setStatus('Enter a valid recipient and amount.', true)
    const asset = `${numeric.toFixed(this.config.tokenPrecision)} ${this.config.tokenSymbol}`
    return this.transact({
      account: this.config.tokenContract,
      name: 'transfer',
      data: {from: String(this.session.actor), to: String(to), quantity: asset, memo: String(memo)}
    })
  }

  async transact({account, name, data}) {
    if (!this.session) return this.setStatus('Connect a wallet first.', true)
    try {
      this.setBusy(true)
      this.setStatus('Waiting for wallet approval…')
      const result = await this.session.transact({
        action: {
          account: String(account),
          name: String(name),
          authorization: [this.session.permissionLevel],
          data
        }
      })
      const txid = result?.response?.transaction_id || result?.resolved?.transaction?.id || ''
      this.setStatus(txid ? `Transaction broadcast: ${txid}` : 'Transaction broadcast.')
      emit(this.root, 'transaction', {transactionId: String(txid), result})
      await this.refreshBalance()
      return result
    } catch (error) {
      this.setStatus(error?.message || 'Transaction failed or was rejected.', true)
      emit(this.root, 'error', {error})
      throw error
    } finally {
      this.setBusy(false)
    }
  }
}

async function boot(root) {
  if (instances.has(root)) return instances.get(root)
  const widget = new AntelopeWalletWidget(root)
  instances.set(root, widget)
  try { await widget.init() } catch (error) { widget.setStatus(error?.message || 'Initialization failed.', true) }
  return widget
}

export function initAntelopeWallets(scope = document) {
  scope.querySelectorAll('[data-antelope-wallet]').forEach(root => boot(root))
}

window.AntelopeWallets = {init: initAntelopeWallets, boot}

document.addEventListener('DOMContentLoaded', () => initAntelopeWallets())
document.addEventListener('drupalBehaviorAttach', () => initAntelopeWallets())
