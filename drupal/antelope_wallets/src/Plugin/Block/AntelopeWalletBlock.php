<?php
namespace Drupal\antelope_wallets\Plugin\Block;

use Drupal\Core\Block\BlockBase;

/**
 * @Block(
 *   id = "antelope_wallet_block",
 *   admin_label = @Translation("Antelope wallet")
 * )
 */
final class AntelopeWalletBlock extends BlockBase {
  public function build(): array {
    $c = \Drupal::config('antelope_wallets.settings');
    $markup = '<section class="antelope-wallet" data-antelope-wallet>'
      . '<div class="antelope-wallet__header"><strong>' . $this->t('Blockchain wallet') . '</strong><span data-antelope-account>' . $this->t('Not connected') . '</span></div>'
      . '<div class="antelope-wallet__actions"><button type="button" data-antelope-connect>' . $this->t('Connect wallet') . '</button><button type="button" data-antelope-disconnect hidden>' . $this->t('Disconnect') . '</button><button type="button" data-antelope-refresh data-antelope-connected-only hidden>' . $this->t('Refresh balance') . '</button></div>'
      . '<p data-antelope-connected-only hidden>' . $this->t('Balance:') . ' <strong data-antelope-balance>—</strong></p>'
      . '<form data-antelope-transfer data-antelope-connected-only hidden><h4>' . $this->t('Transfer @symbol', ['@symbol'=>$c->get('token_symbol')]) . '</h4><label>' . $this->t('To') . ' <input name="to" required></label><label>' . $this->t('Amount') . ' <input name="quantity" type="number" min="0" step="any" required></label><label>' . $this->t('Memo') . ' <input name="memo" maxlength="256"></label><button type="submit">' . $this->t('Review in wallet') . '</button></form>'
      . '<details data-antelope-connected-only hidden><summary>' . $this->t('Advanced contract action') . '</summary><form data-antelope-action><label>' . $this->t('Contract') . ' <input name="account" required></label><label>' . $this->t('Action') . ' <input name="name" required></label><label>' . $this->t('JSON data') . ' <textarea name="data" rows="5">{}</textarea></label><button type="submit">' . $this->t('Review action in wallet') . '</button></form></details>'
      . '<div class="antelope-wallet__status" data-antelope-status aria-live="polite"></div></section>';
    return [
      '#markup' => $markup,
      '#attached' => [
        'library' => ['antelope_wallets/wallet'],
        'drupalSettings' => ['antelopeWallets' => [
          'appName'=>$c->get('app_name'), 'chainId'=>$c->get('chain_id'), 'rpcEndpoint'=>$c->get('rpc_endpoint'),
          'tokenContract'=>$c->get('token_contract'), 'tokenSymbol'=>$c->get('token_symbol'), 'tokenPrecision'=>(int)$c->get('token_precision'),
          'enableAnchor'=>(bool)$c->get('enable_anchor'), 'enableCloudWallet'=>(bool)$c->get('enable_cloud_wallet'),
        ]],
      ],
      '#cache' => ['max-age' => 0],
    ];
  }
}
