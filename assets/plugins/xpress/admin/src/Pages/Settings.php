<?php
namespace UiXpress\Pages;
use UiXpress\Options\GlobalOptions;
use UiXpress\Options\Settings as SettingsOptions;
use UiXpress\Update\Updater;
use UiXpress\Rest\RestLogout;
use UiXpress\Utility\Scripts;

// Prevent direct access to this file
defined("ABSPATH") || exit();

/**
 * Class uixpress
 *
 * Main class for initialising the uixpress app.
 */
class Settings
{
  private static $screen = null;
  /**
   * uixpress constructor.
   *
   * Initialises the main app.
   */
  public function __construct()
  {
    add_action("admin_menu", ["UiXpress\Pages\Settings", "admin_settings_page"]);
  }

  /**
   * Adds settings page.
   *
   * Calls add_menu_page to add new page .
   */
  public static function admin_settings_page()
  {
    $plugin_name = SettingsOptions::get_setting("plugin_name", "uiXpress");
    $menu_name = $plugin_name != "" ? esc_html($plugin_name) : "uiXpress";

    $url = plugins_url("uixpress/assets/icons/uixpress-logo.svg");
    // Add top-level menu page - callback set to null to prevent default submenu
    add_menu_page($menu_name, $menu_name, "manage_options", "uipx-settings", null, $url);
    
    // Replace the default submenu item by using the same slug as parent
    // This removes the duplicate "uiXpress" submenu item
    $hook_suffix = add_submenu_page('uipx-settings', __("Settings", "uixpress"), __("Settings", "uixpress"), "manage_options", "uipx-settings", ["UiXpress\Pages\Settings", "build_uipc"]);

    // Load styles for admin menu logo
    add_action('admin_head', ['UiXpress\Pages\Settings', 'load_admin_menu_logo']);
    add_action("admin_head-{$hook_suffix}", ["UiXpress\Pages\Settings", "load_styles"]);
    add_action("admin_head-{$hook_suffix}", ["UiXpress\Pages\Settings", "load_scripts"]);
  }

  /**
   * Loads admin menu logo css
   */
  public static function load_admin_menu_logo()
  {
    $css = "
    #adminmenu #toplevel_page_uipx-settings .wp-menu-image img {
      width: 16px;
      height: 16px;
    } 
    ";
    echo '<style type="text/css">' . esc_html($css) . '</style>';
  }

  /**
   * uixpress settings page.
   *
   * Outputs the app holder
   */
  public static function build_uipc()
  {
    // Enqueue the media library
    wp_enqueue_media();
    // Output the app
    echo "<div id='uipc-settings-app'></div>";
  }

  /**
   * uixpress styles.
   *
   * Loads main lp styles
   */
  public static function load_styles()
  {
    // Get plugin url
    $url = plugins_url("uixpress/");
    $style = $url . "app/dist/assets/styles/settings.css";
    wp_enqueue_style("uixpress-settings", $style, [], uixpress_plugin_version);
  }

  /**
   * uixpress scripts.
   *
   * Loads main lp scripts
   */
  public static function load_scripts()
  {
    // Get plugin url
    $url = plugins_url("uixpress/");
    $script_name = Scripts::get_base_script_path("uixpressSettings.js");

    // Setup script object
    $builderScript = [
      "id" => "uipc-settings-script",
      "src" => $url . "app/dist/{$script_name}",
      "type" => "module",
    ];

    // Print tag
    wp_print_script_tag($builderScript);
  }
}
