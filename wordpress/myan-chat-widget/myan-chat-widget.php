<?php
/**
 * Plugin Name: Myan Chat Support Widget
 * Description: Embed your self-hosted Myan Chat Support widget in WordPress.
 * Version: 1.0.2
 * Requires at least: 6.3
 * Requires PHP: 8.2
 * Author: Min SiThu
 * Author URI: https://minsithu.org
 * Text Domain: myan-chat-widget
 */
if (!defined('ABSPATH')) exit;
add_action('admin_menu',static function(){add_options_page('Myan Chat Support','Myan Chat Support','manage_options','mcs-widget','mcs_wp_settings');});
add_action('admin_init',static function(){
 register_setting('mcs_widget_group','mcs_widget_settings',['type'=>'array','sanitize_callback'=>static function($input){
  $input=is_array($input)?$input:[];$raw=esc_url_raw(trim((string)($input['server']??'')));
  $scheme=wp_parse_url($raw,PHP_URL_SCHEME);$host=wp_parse_url($raw,PHP_URL_HOST);
  if($scheme!=='https' && !($scheme==='http'&&in_array($host,['localhost','127.0.0.1'],true)))$raw='';
  $key=strtolower(trim((string)($input['key']??'')));if(!preg_match('/^[0-9a-f]{48}$/D',$key))$key='';
  return ['server'=>untrailingslashit($raw),'key'=>$key,'enabled'=>!empty($input['enabled'])?1:0];
 }]);
});
function mcs_wp_settings(){if(!current_user_can('manage_options'))return;$o=get_option('mcs_widget_settings',[]);?>
<div class="wrap"><h1>Myan Chat Support</h1><p>Use your own self-hosted server and public website key. No private API keys are required.</p>
<form method="post" action="options.php"><?php settings_fields('mcs_widget_group'); ?>
<table class="form-table"><tr><th>Chat server URL</th><td><input class="regular-text" type="url" name="mcs_widget_settings[server]" value="<?php echo esc_attr($o['server']??''); ?>" placeholder="https://chat.example.com" required><p class="description">HTTPS required on public websites.</p></td></tr>
<tr><th>Public website key</th><td><input class="large-text" name="mcs_widget_settings[key]" maxlength="48" value="<?php echo esc_attr($o['key']??''); ?>" required></td></tr>
<tr><th>Enable widget</th><td><label><input type="checkbox" name="mcs_widget_settings[enabled]" value="1" <?php checked(!empty($o['enabled'])); ?>> Show on public pages</label></td></tr></table>
<?php submit_button('Save widget settings'); ?></form><p><strong>Developed by Min SiThu</strong></p></div>
<?php }
add_action('wp_footer',static function(){
 $o=get_option('mcs_widget_settings',[]);if(empty($o['enabled'])||empty($o['server'])||empty($o['key']))return;
 $url=esc_url(trailingslashit($o['server']).'widget.js?v=1.0.2');$key=esc_attr($o['key']);
 echo '<script src="'.$url.'" data-site-id="'.$key.'" async></script>';
},50);
