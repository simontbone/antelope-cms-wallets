<?php
namespace Drupal\antelope_wallets\Form;

use Drupal\Core\Form\ConfigFormBase;
use Drupal\Core\Form\FormStateInterface;

final class AntelopeWalletsSettingsForm extends ConfigFormBase {
  public function getFormId(): string { return 'antelope_wallets_settings'; }
  protected function getEditableConfigNames(): array { return ['antelope_wallets.settings']; }

  public function buildForm(array $form, FormStateInterface $form_state): array {
    $c = $this->config('antelope_wallets.settings');
    $form['notice'] = ['#markup' => '<p>' . $this->t('Signing occurs in Anchor or My Cloud Wallet. This module never receives or stores private keys.') . '</p>'];
    $form['app_name'] = ['#type'=>'textfield','#title'=>$this->t('Application name'),'#default_value'=>$c->get('app_name'),'#required'=>TRUE];
    $form['chain_id'] = ['#type'=>'textfield','#title'=>$this->t('Chain ID'),'#default_value'=>$c->get('chain_id'),'#required'=>TRUE,'#maxlength'=>64];
    $form['rpc_endpoint'] = ['#type'=>'url','#title'=>$this->t('RPC endpoint'),'#default_value'=>$c->get('rpc_endpoint'),'#required'=>TRUE];
    $form['token_contract'] = ['#type'=>'textfield','#title'=>$this->t('Token contract'),'#default_value'=>$c->get('token_contract'),'#required'=>TRUE];
    $form['token_symbol'] = ['#type'=>'textfield','#title'=>$this->t('Token symbol'),'#default_value'=>$c->get('token_symbol'),'#required'=>TRUE];
    $form['token_precision'] = ['#type'=>'number','#title'=>$this->t('Token precision'),'#default_value'=>$c->get('token_precision'),'#min'=>0,'#max'=>18,'#required'=>TRUE];
    $form['enable_anchor'] = ['#type'=>'checkbox','#title'=>$this->t('Enable Anchor'),'#default_value'=>$c->get('enable_anchor')];
    $form['enable_cloud_wallet'] = ['#type'=>'checkbox','#title'=>$this->t('Enable WAX My Cloud Wallet'),'#default_value'=>$c->get('enable_cloud_wallet')];
    return parent::buildForm($form, $form_state);
  }

  public function validateForm(array &$form, FormStateInterface $form_state): void {
    if (!preg_match('/^[a-f0-9]{64}$/i', (string) $form_state->getValue('chain_id'))) $form_state->setErrorByName('chain_id', $this->t('Chain ID must be 64 hexadecimal characters.'));
    if (!$form_state->getValue('enable_anchor') && !$form_state->getValue('enable_cloud_wallet')) $form_state->setErrorByName('enable_anchor', $this->t('Enable at least one wallet.'));
    parent::validateForm($form, $form_state);
  }

  public function submitForm(array &$form, FormStateInterface $form_state): void {
    $this->configFactory->getEditable('antelope_wallets.settings')
      ->set('app_name', trim((string)$form_state->getValue('app_name')))
      ->set('chain_id', strtolower(trim((string)$form_state->getValue('chain_id'))))
      ->set('rpc_endpoint', rtrim((string)$form_state->getValue('rpc_endpoint'), '/'))
      ->set('token_contract', trim((string)$form_state->getValue('token_contract')))
      ->set('token_symbol', strtoupper(trim((string)$form_state->getValue('token_symbol'))))
      ->set('token_precision', (int)$form_state->getValue('token_precision'))
      ->set('enable_anchor', (bool)$form_state->getValue('enable_anchor'))
      ->set('enable_cloud_wallet', (bool)$form_state->getValue('enable_cloud_wallet'))
      ->save();
    parent::submitForm($form, $form_state);
  }
}
