<?php
/**
 * Plugin Name: Antelope Wallets
 * Description: Connect WordPress pages to Antelope blockchains with Anchor and WAX My Cloud Wallet via WharfKit.
 * Version: 0.2.0
 * Author: Tavares Simon
 * License: MIT
 */

if (!defined('ABSPATH')) { exit; }

final class Antelope_Wallets_Plugin {
    const OPTION = 'antelope_wallets_options';

    public static function init() {
        add_action('admin_menu', [__CLASS__, 'admin_menu']);
        add_action('admin_init', [__CLASS__, 'register_settings']);
        add_action('admin_enqueue_scripts', [__CLASS__, 'admin_enqueue']);
        add_shortcode('antelope_wallet', [__CLASS__, 'shortcode']);
    }

    public static function presets() {
        static $presets = null;
        if ($presets !== null) return $presets;
        $path = __DIR__ . '/chain-presets.json';
        $decoded = is_readable($path) ? json_decode((string) file_get_contents($path), true) : null;
        $presets = is_array($decoded) ? $decoded : [];
        return $presets;
    }

    public static function defaults() {
        $p = self::presets()['wax'] ?? [];
        return [
            'app_name' => get_bloginfo('name') ?: 'Antelope CMS Wallet',
            'network_preset' => 'wax',
            'chain_id' => $p['chain_id'] ?? '1064487b3cd1a897ce03ae5b6a865651747e2e152090f99c1d19d44e01aea5a4',
            'rpc_endpoint' => $p['rpc_endpoint'] ?? 'https://wax.greymass.com',
            'token_contract' => $p['token_contract'] ?? 'eosio.token',
            'token_symbol' => $p['token_symbol'] ?? 'WAX',
            'token_precision' => (int) ($p['token_precision'] ?? 8),
            'enable_anchor' => 1,
            'enable_cloud_wallet' => 1,
        ];
    }

    public static function options() {
        return wp_parse_args((array) get_option(self::OPTION, []), self::defaults());
    }

    public static function sanitize($input) {
        $d = self::defaults();
        $presets = self::presets();
        $out = [];
        $out['app_name'] = sanitize_text_field($input['app_name'] ?? $d['app_name']);
        $requested_preset = sanitize_key($input['network_preset'] ?? $d['network_preset']);
        $out['network_preset'] = ($requested_preset === 'custom' || isset($presets[$requested_preset])) ? $requested_preset : 'custom';
        $out['chain_id'] = strtolower(preg_replace('/[^a-f0-9]/i', '', $input['chain_id'] ?? $d['chain_id']));
        $out['rpc_endpoint'] = untrailingslashit(esc_url_raw($input['rpc_endpoint'] ?? $d['rpc_endpoint']));
        $out['token_contract'] = sanitize_text_field($input['token_contract'] ?? $d['token_contract']);
        $out['token_symbol'] = strtoupper(sanitize_text_field($input['token_symbol'] ?? $d['token_symbol']));
        $out['token_precision'] = min(18, max(0, absint($input['token_precision'] ?? $d['token_precision'])));
        $out['enable_anchor'] = empty($input['enable_anchor']) ? 0 : 1;
        $out['enable_cloud_wallet'] = empty($input['enable_cloud_wallet']) ? 0 : 1;
        if (strlen($out['chain_id']) !== 64) $out['chain_id'] = $d['chain_id'];

        if ($out['network_preset'] !== 'custom') {
            $p = $presets[$out['network_preset']];
            $matches = $out['chain_id'] === strtolower($p['chain_id'])
                && $out['rpc_endpoint'] === untrailingslashit($p['rpc_endpoint'])
                && $out['token_contract'] === $p['token_contract']
                && $out['token_symbol'] === strtoupper($p['token_symbol'])
                && $out['token_precision'] === (int) $p['token_precision'];
            if (!$matches) $out['network_preset'] = 'custom';
        }

        $wax_id = strtolower($presets['wax']['chain_id'] ?? $d['chain_id']);
        $is_wax = $out['chain_id'] === $wax_id;
        if (!$is_wax && $out['enable_cloud_wallet']) {
            $out['enable_cloud_wallet'] = 0;
            add_settings_error(self::OPTION, 'cloud_wallet_wax_only', 'WAX My Cloud Wallet is WAX-only and was disabled for this chain.', 'warning');
        }
        if (!$out['enable_anchor'] && !$out['enable_cloud_wallet']) {
            $out['enable_anchor'] = 1;
            add_settings_error(self::OPTION, 'wallet_required', 'At least one compatible wallet is required. Anchor was enabled.', 'warning');
        }
        return $out;
    }

    public static function register_settings() {
        register_setting('antelope_wallets', self::OPTION, ['sanitize_callback' => [__CLASS__, 'sanitize']]);
    }

    public static function admin_menu() {
        add_options_page('Antelope Wallets', 'Antelope Wallets', 'manage_options', 'antelope-wallets', [__CLASS__, 'settings_page']);
    }

    public static function admin_enqueue($hook) {
        if ($hook !== 'settings_page_antelope-wallets') return;
        wp_enqueue_script('antelope-wallets-admin', plugins_url('assets/antelope-wallets-admin.js', __FILE__), [], '0.2.0', true);
        wp_add_inline_script('antelope-wallets-admin', 'window.AntelopeCMSPresets=' . wp_json_encode(self::presets()) . ';', 'before');
    }

    public static function settings_page() {
        if (!current_user_can('manage_options')) return;
        $o = self::options();
        ?>
        <div class="wrap"><h1>Antelope Wallets</h1>
        <p>Wallet signing happens in Anchor or My Cloud Wallet. This plugin never receives or stores private keys.</p>
        <form method="post" action="options.php" data-antelope-preset-form>
        <?php settings_fields('antelope_wallets'); ?>
        <table class="form-table" role="presentation">
        <?php self::row('Application name', 'app_name', $o['app_name']); ?>
        <?php self::preset_row($o['network_preset']); ?>
        <?php self::row('Chain ID', 'chain_id', $o['chain_id'], 72); ?>
        <?php self::row('RPC endpoint', 'rpc_endpoint', $o['rpc_endpoint'], 72, 'url'); ?>
        <?php self::row('Token contract', 'token_contract', $o['token_contract']); ?>
        <?php self::row('Token symbol', 'token_symbol', $o['token_symbol']); ?>
        <?php self::row('Token precision', 'token_precision', $o['token_precision'], 5, 'number'); ?>
        <tr><th>Wallets</th><td>
          <label><input data-antelope-preset-field="enable_anchor" type="checkbox" name="<?php echo esc_attr(self::OPTION); ?>[enable_anchor]" value="1" <?php checked($o['enable_anchor'], 1); ?>> Anchor</label><br>
          <label><input data-antelope-preset-field="enable_cloud_wallet" type="checkbox" name="<?php echo esc_attr(self::OPTION); ?>[enable_cloud_wallet]" value="1" <?php checked($o['enable_cloud_wallet'], 1); ?>> WAX My Cloud Wallet (WAX only)</label>
        </td></tr>
        </table>
        <?php submit_button(); ?>
        </form>
        <h2>Usage</h2><p>Add <code>[antelope_wallet]</code> to any page or post.</p>
        </div>
        <?php
    }

    private static function preset_row($value) {
        $options = ['custom' => 'Custom'];
        foreach (self::presets() as $key => $preset) $options[$key] = $preset['label'] ?? ucfirst($key);
        echo '<tr><th><label for="aw-network_preset">Network preset</label></th><td><select id="aw-network_preset" data-antelope-preset-select name="' . esc_attr(self::OPTION) . '[network_preset]">';
        foreach ($options as $key => $label) printf('<option value="%s" %s>%s</option>', esc_attr($key), selected($value, $key, false), esc_html($label));
        echo '</select><p class="description">Selecting a preset fills the fields below. Edit any field afterward to use a custom configuration.</p></td></tr>';
    }

    private static function row($label, $name, $value, $size = 40, $type = 'text') {
        printf('<tr><th><label for="aw-%1$s">%2$s</label></th><td><input id="aw-%1$s" data-antelope-preset-field="%1$s" class="regular-text" size="%3$d" type="%4$s" name="%5$s[%1$s]" value="%6$s"></td></tr>',
            esc_attr($name), esc_html($label), absint($size), esc_attr($type), esc_attr(self::OPTION), esc_attr($value));
    }

    public static function enqueue() {
        wp_enqueue_style('antelope-wallets', plugins_url('assets/antelope-wallets.css', __FILE__), [], '0.2.0');
        wp_enqueue_script('antelope-wallets', plugins_url('assets/antelope-wallets.js', __FILE__), [], '0.2.0', true);
        $o = self::options();
        wp_add_inline_script('antelope-wallets', 'window.AntelopeCMS=' . wp_json_encode([
            'appName' => $o['app_name'], 'networkPreset' => $o['network_preset'], 'chainId' => $o['chain_id'], 'rpcEndpoint' => $o['rpc_endpoint'],
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
