import {execFileSync} from 'node:child_process'
import {mkdir, rm} from 'node:fs/promises'
await mkdir('dist', {recursive: true})
for (const file of ['dist/antelope-wallets-wordpress.zip','dist/antelope_wallets-drupal.zip']) {
  await rm(file, {force: true})
}
execFileSync('zip', ['-qr', '../../dist/antelope-wallets-wordpress.zip', 'antelope-wallets'], {cwd: 'wordpress', stdio: 'inherit'})
execFileSync('zip', ['-qr', '../../dist/antelope_wallets-drupal.zip', 'antelope_wallets'], {cwd: 'drupal', stdio: 'inherit'})
