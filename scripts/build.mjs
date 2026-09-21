import {build} from 'esbuild'
import {mkdir, copyFile} from 'node:fs/promises'

await mkdir('build', {recursive: true})
await build({
  entryPoints: ['packages/wallet-core/src/index.js'],
  bundle: true,
  minify: true,
  sourcemap: true,
  format: 'iife',
  platform: 'browser',
  target: ['es2020'],
  outfile: 'build/antelope-wallets.js',
  define: {'process.env.NODE_ENV': '"production"'}
})
for (const target of [
  'wordpress/antelope-wallets/assets/antelope-wallets.js',
  'drupal/antelope_wallets/antelope-wallets.js'
]) {
  await copyFile('build/antelope-wallets.js', target)
  await copyFile('build/antelope-wallets.js.map', `${target}.map`)
}

for (const target of [
  'wordpress/antelope-wallets/assets/antelope-wallets-admin.js',
  'drupal/antelope_wallets/antelope-wallets-admin.js'
]) await copyFile('packages/admin-presets/src/index.js', target)

for (const target of [
  'wordpress/antelope-wallets/chain-presets.json',
  'drupal/antelope_wallets/chain-presets.json'
]) await copyFile('config/chain-presets.json', target)
