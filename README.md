# Antelope CMS Wallets

Open-source WordPress and Drupal integrations for Antelope blockchains using **WharfKit**, with **Anchor** and **WAX My Cloud Wallet** support.

## What v0.1 does

- Connect with Anchor or WAX My Cloud Wallet.
- Restore and clear browser wallet sessions.
- Show the connected account.
- Query the configured token balance.
- Transfer the configured token after wallet approval.
- Submit an arbitrary contract action after wallet approval.
- Configure chain ID, RPC endpoint, token contract, symbol, precision, and wallet availability from the CMS.
- Never receives or stores a user's private keys.

The default configuration targets WAX mainnet:

- Chain ID: `1064487b3cd1a897ce03ae5b6a865651747e2e152090f99c1d19d44e01aea5a4`
- RPC: `https://wax.greymass.com`
- Token: `eosio.token` / `WAX` / 8 decimals

Anchor can work across Antelope networks when you configure the corresponding chain ID and RPC. My Cloud Wallet support is intended for the WAX networks it supports.

## Why WharfKit

WAX documentation now recommends WharfKit for web/app wallet integration and marks WaxJS unsupported. WharfKit provides dedicated SessionKit wallet plugins for both Anchor and My Cloud Wallet, allowing the CMS plugins to share one wallet layer.

## Repository layout

- `packages/wallet-core/` shared browser wallet code
- `wordpress/antelope-wallets/` WordPress plugin
- `drupal/antelope_wallets/` Drupal module
- `scripts/` build/package helpers

## Build

```bash
npm install
npm run build
npm run package
```

Build output is copied into both CMS plugins. Packaging creates installable ZIPs in `dist/`.

## WordPress

1. Build or download the release ZIP.
2. Install `antelope-wallets-wordpress.zip`.
3. Go to **Settings → Antelope Wallets**.
4. Add `[antelope_wallet]` to a page or post.

## Drupal

1. Build or download the release ZIP.
2. Extract `antelope_wallets` under `web/modules/custom/`.
3. Enable **Antelope Wallets**.
4. Configure `/admin/config/services/antelope-wallets`.
5. Place the **Antelope wallet** block.

## JavaScript events

Each widget emits DOM events that custom themes/plugins can consume:

- `antelope:connected`
- `antelope:disconnected`
- `antelope:balance`
- `antelope:transaction`
- `antelope:error`

## Roadmap

- Network presets for WAX, Telos, Vaulta/EOS-family chains and testnets.
- Safer administrator-defined action templates so normal visitors do not need the generic action form.
- NFT/AtomicAssets read widgets for WAX.
- Account/resource widgets.
- Gutenberg block and Drupal field/formatter integrations.
- Optional server-side read cache while keeping signing client-side.
- Automated WordPress and Drupal compatibility tests.

## Security model

The user's wallet signs transactions. Neither CMS receives the user's private key. Do not add private-key fields to these plugins.

## License

MIT. Third-party dependencies retain their own licenses.
