<?php
/**
 * Plugin Name: Antelope Wallets
 * Description: Connect WordPress pages to Antelope blockchains with Anchor and WAX My Cloud Wallet via WharfKit.
 * Version: 0.1.0
 * Author: Tavares Simon
 * License: MIT
 */

if (!defined('ABSPATH')) { exit; }

final class Antelope_Wallets_Plugin {
    const OPTION = 'antelope_wallets_options';

    public static function init() {
        add_action('admin_menu', [__CLASS__, 'admin_menu']);
        add_action('admin_init', [__CLASS__, 'register_settings']);
        add_shortcode('antelope_wallet', [__CLASS__, 'shortcode']);
    }

    public static function defaults() {
        return [
            'app_name' => get_bloginfo('name') ?: 'Antelope CMS Wallet',
            'chain_id' => '1064487b3cd1a897ce03ae5b6a865651747e2e152090f99c1d19d44e01aea5a4',
            'rpc_endpoint' => 'https://wax.greymass.com',
            'token_contract' => 'eosio.token',
            'token_symbol' => 'WAX',
            'token_precision' => 8,
            'enable_anchor' => 1,
            'enable_cloud_wallet' => 1,
        ];
    }

    public static function options() {
        return wp_parse_args((array) get_option(self::OPTION, []), self::defaults());
    }

    public static function sanitize($input) {
        $d = self::defaults();
        $out = [];
        $out['app_name'] = sanitize_text_field($input['app_name'] ?? $d['app_name']);
        $out['chain_id'] = preg_replace('/[^a-f0-9]/i', '', $input['chain_id'] ?? $d['chain_id']);
        $out['rpc_endpoint'] = esc_url_raw($input['rpc_endpoint'] ?? $d['rpc_endpoint']);
        $out['token_contract'] = sanitize_text_field($input['token_contract'] ?? $d['token_contract']);
        $out['token_symbol'] = strtoupper(sanitize_text_field($input['token_symbol'] ?? $d['token_symbol']));
        $out['token_precision'] = min(18, max(0, absint($input['token_precision'] ?? $d['token_precision'])));
        $out['enable_anchor'] = empty($input['enable_anchor']) ? 0 : 1;
        $out['enable_cloud_wallet'] = empty($input['enable_cloud_wallet']) ? 0 : 1;
        if (strlen($out['chain_id']) !== 64) $out['chain_id'] = $d['chain_id'];
        return $out;
    }

    public static function register_settings() {
        register_setting('antelope_wallets', self::OPTION, ['sanitize_callback' => [__CLASS__, 'sanitize']]);
    }

    public static function admin_menu() {
        add_options_page('Antelope Wallets', 'Antelope Wallets', 'manage_options', 'antelope-wallets', [__CLASS__, 'settings_page']);
    }

    public static function settings_page() {
        if (!current_user_can('manage_options')) return;
        $o = self::options();
        ?>
        <div class="wrap"><h1>Antelope Wallets</h1>
        <p>Wallet signing happens in Anchor or My Cloud Wallet. This plugin never receives or stores private keys.</p>
        <form method="post" action="options.php">
        <?php settings_fields('antelope_wallets'); ?>
        <table class="form-table" role="presentation">
        <?php self::row('Application name', 'app_name', $o['app_name']); ?>
        <?php self::row('Chain ID', 'chain_id', $o['chain_id'], 72); ?>
        <?php self::row('RPC endpoint', 'rpc_endpoint', $o['rpc_endpoint'], 72, 'url'); ?>
        <?php self::row('Token contract', 'token_contract', $o['token_contract']); ?>
        <?php self::row('Token symbol', 'token_symbol', $o['token_symbol']); ?>
        <?php self::row('Token precision', 'token_precision', $o['token_precision'], 5, 'number'); ?>
        <tr><th>Wallets</th><td>
          <label><input type="checkbox" name="<?php echo esc_attr(self::OPTION); ?>[enable_anchor]" value="1" <?php checked($o['enable_anchor'], 1); ?>> Anchor</label><br>
          <label><input type="checkbox" name="<?php echo esc_attr(self::OPTION); ?>[enable_cloud_wallet]" value="1" <?php checked($o['enable_cloud_wallet'], 1); ?>> WAX My Cloud Wallet</label>
        </td></tr>
        </table>
        <?php submit_button(); ?>
        </form>
        <h2>Usage</h2><p>Add <code>[antelope_wallet]</code> to any page or post.</p>
        </div>
        <?php
    }

    private static function row($label, $name, $value, $size = 40, $type = 'text') {
        printf('<tr><th><label for="aw-%1$s">%2$s</label></th><td><input id="aw-%1$s" class="regular-text" size="%3$d" type="%4$s" name="%5$s[%1$s]" value="%6$s"></td></tr>',
            esc_attr($name), esc_html($label), absint($size), esc_attr($type), esc_attr(self::OPTION), esc_attr($value));
    }

    public static function enqueue() {
        wp_enqueue_style('antelope-wallets', plugins_url('assets/antelope-wallets.css', __FILE__), [], '0.1.0');
        wp_enqueue_script('antelope-wallets', plugins_url('assets/antelope-wallets.js', __FILE__), [], '0.1.0', true);
        $o = self::options();
        wp_add_inline_script('antelope-wallets', 'window.AntelopeCMS=' . wp_json_encode([
            'appName' => $o['app_name'], 'chainId' => $o['chain_id'], 'rpcEndpoint' => $o['rpc_endpoint'],
            'tokenContract' => $o['token_contract'], 'tokenSymbol' => $o['token_symbol'], 'tokenPrecision' => (int) $o['token_precision'],
            'enableAnchor' => (bool) $o['enable_anchor'], 'enableCloudWallet' => (bool) $o['enable_cloud_wallet'],
        ]) . ';', 'before');
    }

    public static function shortcode($atts = []) {
        self::enqueue();
        $o = self::options();
        $uid = wp_unique_id('antelope-wallet-');
        ob_start(); ?>
        <section id="<?php echo esc_attr($uid); ?>" class="antelope-wallet" data-antelope-wallet>
          <div class="antelope-wallet__header"><strong>Blockchain wallet</strong><span data-antelope-account>Not connected</span></div>
          <div class="antelope-wallet__actions">
            <button type="button" data-antelope-connect>Connect wallet</button>
            <button type="button" data-antelope-disconnect hidden>Disconnect</button>
            <button type="button" data-antelope-refresh data-antelope-connected-only hidden>Refresh balance</button>
          </div>
          <p data-antelope-connected-only hidden>Balance: <strong data-antelope-balance>—</strong></p>
          <form data-antelope-transfer data-antelope-connected-only hidden>
            <h4>Transfer <?php echo esc_html($o['token_symbol']); ?></h4>
            <label>To <input name="to" required></label>
            <label>Amount <input name="quantity" type="number" min="0" step="any" required></label>
            <label>Memo <input name="memo" maxlength="256"></label>
            <button type="submit">Review in wallet</button>
          </form>
          <details data-antelope-connected-only hidden><summary>Advanced contract action</summary>
            <form data-antelope-action>
              <label>Contract <input name="account" required></label>
              <label>Action <input name="name" required></label>
              <label>JSON data <textarea name="data" rows="5">{}</textarea></label>
              <button type="submit">Review action in wallet</button>
            </form>
          </details>
          <div class="antelope-wallet__status" data-antelope-status aria-live="polite"></div>
        </section>
        <?php return ob_get_clean();
    }
}
Antelope_Wallets_Plugin::init();
