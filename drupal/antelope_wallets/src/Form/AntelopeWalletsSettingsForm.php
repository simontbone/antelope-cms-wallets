<?php
namespace Drupal\antelope_wallets\Form;

use Drupal\Core\Form\ConfigFormBase;
use Drupal\Core\Form\FormStateInterface;

final class AntelopeWalletsSettingsForm extends ConfigFormBase {
  public function getFormId(): string { return 'antelope_wallets_settings'; }
  protected function getEditableConfigNames(): array { return ['antelope_wallets.settings']; }

  private static function presets(): array {
    static $presets = NULL;
    if ($presets !== NULL) return $presets;
    $path = dirname(__DIR__, 2) . '/chain-presets.json';
    $decoded = is_readable($path) ? json_decode((string) file_get_contents($path), TRUE) : NULL;
    $presets = is_array($decoded) ? $decoded : [];
    return $presets;
  }

  public function buildForm(array $form, FormStateInterface $form_state): array {
    $c = $this->config('antelope_wallets.settings');
    $presets = self::presets();
    $preset_options = ['custom' => $this->t('Custom')];
    foreach ($presets as $key => $preset) $preset_options[$key] = $preset['label'] ?? ucfirst($key);

    $form['#attributes']['data-antelope-preset-form'] = '1';
    $form['#attached']['library'][] = 'antelope_wallets/admin';
    $form['#attached']['drupalSettings']['antelopeWalletPresets'] = $presets;
    $form['notice'] = ['#markup' => '<p>' . $this->t('Signing occurs in Anchor or My Cloud Wallet. This module never receives or stores private keys.') . '</p>'];
    $form['app_name'] = ['#type'=>'textfield','#title'=>$this->t('Application name'),'#default_value'=>$c->get('app_name'),'#required'=>TRUE];
    $form['network_preset'] = ['#type'=>'select','#title'=>$this->t('Network preset'),'#options'=>$preset_options,'#default_value'=>$c->get('network_preset') ?: 'wax','#attributes'=>['data-antelope-preset-select'=>'1'],'#description'=>$this->t('Selecting a preset fills the fields below. Edit any field afterward to use a custom configuration.')];
    $form['chain_id'] = ['#type'=>'textfield','#title'=>$this->t('Chain ID'),'#default_value'=>$c->get('chain_id'),'#required'=>TRUE,'#maxlength'=>64,'#attributes'=>['data-antelope-preset-field'=>'chain_id']];
    $form['rpc_endpoint'] = ['#type'=>'url','#title'=>$this->t('RPC endpoint'),'#default_value'=>$c->get('rpc_endpoint'),'#required'=>TRUE,'#attributes'=>['data-antelope-preset-field'=>'rpc_endpoint']];
    $form['token_contract'] = ['#type'=>'textfield','#title'=>$this->t('Token contract'),'#default_value'=>$c->get('token_contract'),'#required'=>TRUE,'#attributes'=>['data-antelope-preset-field'=>'token_contract']];
    $form['token_symbol'] = ['#type'=>'textfield','#title'=>$this->t('Token symbol'),'#default_value'=>$c->get('token_symbol'),'#required'=>TRUE,'#attributes'=>['data-antelope-preset-field'=>'token_symbol']];
    $form['token_precision'] = ['#type'=>'number','#title'=>$this->t('Token precision'),'#default_value'=>$c->get('token_precision'),'#min'=>0,'#max'=>18,'#required'=>TRUE,'#attributes'=>['data-antelope-preset-field'=>'token_precision']];
    $form['enable_anchor'] = ['#type'=>'checkbox','#title'=>$this->t('Enable Anchor'),'#default_value'=>$c->get('enable_anchor'),'#attributes'=>['data-antelope-preset-field'=>'enable_anchor']];
    $form['enable_cloud_wallet'] = ['#type'=>'checkbox','#title'=>$this->t('Enable WAX My Cloud Wallet (WAX only)'),'#default_value'=>$c->get('enable_cloud_wallet'),'#attributes'=>['data-antelope-preset-field'=>'enable_cloud_wallet']];
    return parent::buildForm($form, $form_state);
  }

  public function validateForm(array &$form, FormStateInterface $form_state): void {
    $chain_id = strtolower(trim((string) $form_state->getValue('chain_id')));
    if (!preg_match('/^[a-f0-9]{64}$/i', $chain_id)) $form_state->setErrorByName('chain_id', $this->t('Chain ID must be 64 hexadecimal characters.'));
    $wax_id = strtolower(self::presets()['wax']['chain_id'] ?? '');
    $is_wax = $chain_id === $wax_id;
    if (!$is_wax && $form_state->getValue('enable_cloud_wallet')) $form_state->setErrorByName('enable_cloud_wallet', $this->t('WAX My Cloud Wallet can only be enabled for the WAX chain ID.'));
    if (!$form_state->getValue('enable_anchor') && !$form_state->getValue('enable_cloud_wallet')) $form_state->setErrorByName('enable_anchor', $this->t('Enable at least one compatible wallet.'));
    parent::validateForm($form, $form_state);
  }

  public function submitForm(array &$form, FormStateInterface $form_state): void {
    $preset_key = (string) $form_state->getValue('network_preset');
    $presets = self::presets();
    $chain_id = strtolower(trim((string)$form_state->getValue('chain_id')));
    $rpc_endpoint = rtrim((string)$form_state->getValue('rpc_endpoint'), '/');
    $token_contract = trim((string)$form_state->getValue('token_contract'));
    $token_symbol = strtoupper(trim((string)$form_state->getValue('token_symbol')));
    $token_precision = (int)$form_state->getValue('token_precision');

    if ($preset_key !== 'custom' && isset($presets[$preset_key])) {
      $p = $presets[$preset_key];
      $matches = $chain_id === strtolower($p['chain_id'])
        && $rpc_endpoint === rtrim($p['rpc_endpoint'], '/')
        && $token_contract === $p['token_contract']
        && $token_symbol === strtoupper($p['token_symbol'])
        && $token_precision === (int)$p['token_precision'];
      if (!$matches) $preset_key = 'custom';
    }
    elseif ($preset_key !== 'custom') $preset_key = 'custom';

    $this->configFactory->getEditable('antelope_wallets.settings')
      ->set('app_name', trim((string)$form_state->getValue('app_name')))
      ->set('network_preset', $preset_key)
      ->set('chain_id', $chain_id)
      ->set('rpc_endpoint', $rpc_endpoint)
      ->set('token_contract', $token_contract)
      ->set('token_symbol', $token_symbol)
      ->set('token_precision', $token_precision)
      ->set('enable_anchor', (bool)$form_state->getValue('enable_anchor'))
      ->set('enable_cloud_wallet', (bool)$form_state->getValue('enable_cloud_wallet'))
      ->save();
    parent::submitForm($form, $form_state);
  }
}
