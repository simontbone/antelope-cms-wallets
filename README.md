# Antelope CMS Wallets

Open-source WordPress and Drupal integrations for Antelope blockchains using **WharfKit**, with **Anchor** and **WAX My Cloud Wallet** support.

## What v0.2 does

- Built-in **WAX**, **Telos**, and **Vaulta** mainnet presets.
- Preset selection fills chain ID, RPC endpoint, native token contract, symbol, and precision while leaving every field editable. Editing preset values automatically turns the saved configuration into **Custom**.
- Connect with Anchor on all included networks.
- Connect with WAX My Cloud Wallet on WAX.
- Restore and clear browser wallet sessions.
- Show the connected account and configured token balance.
- Transfer the configured token after wallet approval.
- Submit an arbitrary contract action after wallet approval.
- Never receives or stores a user's private keys.

## Built-in mainnet presets

| Preset | Chain ID | RPC | Native token | Wallets |
| --- | --- | --- | --- | --- |
| WAX | `1064487b3cd1a897ce03ae5b6a865651747e2e152090f99c1d19d44e01aea5a4` | `https://wax.greymass.com` | `eosio.token` / `WAX` / 8 | Anchor, My Cloud Wallet |
| Telos | `4667b205c6838ef70ff7988f6e8257e8be0e1284a2f59699054a018f743b1d11` | `https://telos.greymass.com` | `eosio.token` / `TLOS` / 4 | Anchor |
| Vaulta | `aca376f206b8fc25a6ed44dbdc66547c36c6c33e3a119ffbeaef943642f0e906` | `https://eos.greymass.com` | `core.vaulta` / `A` / 4 | Anchor |

Vaulta uses the original EOS mainnet chain ID because Vaulta is the upgraded/rebranded network rather than a new chain.

## Preset verification

`config/chain-presets.json` is the canonical preset file. The build copies it into both CMS packages.

```bash
npm run test:presets       # structural consistency
npm run test:presets:live  # query each live RPC, verify chain ID and native token stats
```

A dedicated GitHub Actions workflow runs the live checks whenever preset definitions or their verification code change.

## Why WharfKit

WAX documentation recommends WharfKit for modern web/app wallet integration. WharfKit provides SessionKit wallet plugins for Anchor and My Cloud Wallet, allowing both CMS plugins to share one wallet layer.

## Repository layout

- `config/chain-presets.json` canonical chain presets
- `packages/wallet-core/` shared browser wallet code
- `packages/admin-presets/` shared preset settings helper
- `wordpress/antelope-wallets/` WordPress plugin
- `drupal/antelope_wallets/` Drupal module
- `scripts/` build, package, and preset verification helpers

## Build

```bash
npm install
npm run build
npm run test:presets
npm run package
```

Build output is copied into both CMS plugins. Packaging creates installable ZIPs in `dist/`.

## WordPress

1. Build or download the release ZIP.
2. Install `antelope-wallets-wordpress.zip`.
3. Go to **Settings → Antelope Wallets**.
4. Choose a preset or **Custom**.
5. Add `[antelope_wallet]` to a page or post.

## Drupal

1. Build or download the release ZIP.
2. Extract `antelope_wallets` under `web/modules/custom/`.
3. Enable **Antelope Wallets**.
4. Configure `/admin/config/services/antelope-wallets`.
5. Choose a preset or **Custom**.
6. Place the **Antelope wallet** block.

## JavaScript events

Each widget emits DOM events that custom themes/plugins can consume:

- `antelope:connected`
- `antelope:disconnected`
- `antelope:balance`
- `antelope:transaction`
- `antelope:error`

## Roadmap

- Safer administrator-defined action templates so normal visitors do not need the generic action form.
- Testnet presets.
- NFT/AtomicAssets read widgets for WAX.
- Account/resource widgets.
- Gutenberg block and Drupal field/formatter integrations.
- Optional server-side read cache while keeping signing client-side.
- Automated WordPress and Drupal compatibility tests.

## Security model

The user's wallet signs transactions. Neither CMS receives the user's private key. Do not add private-key fields to these plugins.

## License

MIT. Third-party dependencies retain their own licenses.
