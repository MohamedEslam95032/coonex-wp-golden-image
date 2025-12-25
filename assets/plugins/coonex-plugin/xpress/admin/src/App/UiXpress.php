<?php

namespace UiXpress\App;

use UiXpress\Options\GlobalOptions;
use UiXpress\Options\Settings;
use UiXpress\Update\Updater;
use UiXpress\Rest\RestLogout;
use UiXpress\Rest\UserRoles;
use UiXpress\Rest\UserCapabilities;
use UiXpress\Rest\RoleEditor;
use UiXpress\Rest\ServerHealth;
use UiXpress\Rest\MediaAnalytics;
use UiXpress\Rest\UserAnalytics;
use UiXpress\Rest\SearchMeta;
use UiXpress\Rest\PostsTables;
use UiXpress\Rest\AdminNotices;
use UiXpress\Rest\PluginManager;
use UiXpress\Rest\MediaReplace;
use UiXpress\Rest\Media as RestMedia;
use UiXpress\Rest\MediaTags;
use UiXpress\Rest\MediaBulk;
use UiXpress\Rest\Analytics;
use UiXpress\Pages\Settings as SettingsPage;
use UiXpress\Pages\Login;
use UiXpress\Pages\MenuBuilder;
use UiXpress\Pages\CustomPluginsPage;
use UiXpress\Pages\FrontEnd;
use UiXpress\Pages\PostsList;
use UiXpress\Pages\CustomDashboardPage;
use UiXpress\Pages\UixAnalytics;
use UiXpress\Pages\CustomMediaPage;
use UiXpress\Pages\CustomUsersPage;
use UiXpress\Pages\CustomCommentsPage;
use UiXpress\Pages\CustomActivityLogPage;
use UiXpress\Pages\DatabaseExplorerPage;
use UiXpress\Pages\RoleEditorPage;
use UiXpress\Rest\DatabaseExplorer;
use UiXpress\Rest\ActivityLog as RestActivityLog;
use UiXpress\Utility\Scripts;
use UiXpress\App\UiXpressFrontEnd;
use UiXpress\Analytics\AnalyticsDatabase;
use UiXpress\Analytics\AnalyticsCron;
use UiXpress\Activity\ActivityDatabase;
use UiXpress\Activity\ActivityLogger;
use UiXpress\Activity\ActivityCron;
use UiXpress\Activity\ActivityHooks;
//use UiXpress\Pages\DummyDataPage;

// Prevent direct access to this file
defined("ABSPATH") || exit();

/**
 * Class uixpress
 *
 * Main class for initialising the uixpress app.
 */
class UiXpress
{
  private static $screen = null;
  private static $options = [];
  private static $script_name = false;
  private static $plugin_url = false;

  /**
   * uixpress constructor.
   *
   * Initialises the main app.
   */
  public function __construct()
  {

    if (self::is_updater_iframe()) {
      add_action("admin_enqueue_scripts", [$this, "load_styles"], 1);
      return;
    }

    add_action("init", [$this, "languages_loader"]);

    

    add_action("admin_init", [$this, "get_global_options"], 0);
    add_action("admin_enqueue_scripts", [$this, "get_screen"], 0);


    add_action("admin_enqueue_scripts", [$this, "load_styles"], 1);
    add_action("admin_enqueue_scripts", [$this, "load_base_script"], 1);
    add_action("admin_head", [$this, "output_app"], 2);


    add_action("in_admin_header", [$this, "build_uixpress"], 1);


    add_action("all_plugins", [$this, "change_plugin_name"]);

    add_action("admin_enqueue_scripts", [$this, "maybe_remove_assets"], 100);

    add_filter( 'admin_body_class', [$this, "uixpress_add_admin_body_class"] );




    // Starts apps
    new GlobalOptions();
    new Updater();
    new RestLogout();
    new UserRoles();
    new UserCapabilities();
        new ServerHealth();
        new MediaAnalytics();
        new UserAnalytics();
    new SettingsPage();
    new Login();
    new SearchMeta();
    new MenuBuilder();
    new CustomPluginsPage();
    new UiXpressFrontEnd();
    new PluginManager();
    new MediaReplace();
    new MediaTags();
    new MediaBulk();
    // Register REST media filters (e.g., unused attachments)
    RestMedia::init();
    new Analytics();
    new PostsList();
    new PostsTables();
    new AdminNotices();
    new CustomDashboardPage();
    
    // Conditionally load Role Editor if enabled
    if (Settings::is_enabled("enable_role_editor")) {
      new RoleEditor();
      new RoleEditorPage();
    }
    new AnalyticsDatabase();
    new AnalyticsCron();
    new UixAnalytics();
    new CustomMediaPage();
    new CustomUsersPage();
    new CustomCommentsPage();
    new CustomActivityLogPage();
    new DatabaseExplorerPage();
    new DatabaseExplorer();
    new ActivityDatabase();
    new ActivityLogger();
    new ActivityCron();
    new ActivityHooks();
    new RestActivityLog();
    //new DummyDataPage();

    //Mailpoet
    add_filter("mailpoet_conflict_resolver_whitelist_style", [$this, "handle_script_whitelist"]);
    add_filter("mailpoet_conflict_resolver_whitelist_script", [$this, "handle_script_whitelist"]);
  }

  /**
   * Check for upgrade iframe and only load styles if on it
   *
   *
   * @return boolean
   * @since 1.0.6
   */
  private static function is_updater_iframe()
  {
    $action = isset($_GET["action"]) ? sanitize_text_field($_GET["action"]) : '';
    return $action === "update-selected-themes" || $action === "update-selected";
  }

  /**
   * Mailpoet white list functions
   *
   * @param array $scripts array of scripts / styles
   *
   * @return array
   * @since 3.2.13
   */
  public static function handle_script_whitelist($scripts)
  {
    $scripts[] = "uixpress"; // plugin name to whitelist
    return $scripts;
  }

  public static function uixpress_add_admin_body_class( $classes ) {
    $screen = get_current_screen();
    
    // Define array of page IDs where you want the class
    $uixpress_pages = array(
        'toplevel_page_uipx-settings',
        'dashboard',
        'uixpress_page_uixpress-admin-notices',
        'uixpress_page_uipx-menucreator',
        'uixpress_page_uixpress-database-explorer',
        'uixpress_page_uipx-role-editor',
        'uixpress_page_uixpress-activity-log'
    );

    $uixPressOptions = self::return_global_options();
    if (isset($uixPressOptions["use_modern_users_page"]) && $uixPressOptions["use_modern_users_page"] === true) {
      $uixpress_pages[] = 'users';
    }

    if (isset($uixPressOptions["use_modern_comments_page"]) && $uixPressOptions["use_modern_comments_page"] === true) {
      $uixpress_pages[] = 'edit-comments';
    }

    if (isset($uixPressOptions["use_modern_plugin_page"]) && $uixPressOptions["use_modern_plugin_page"] === true) {
      $uixpress_pages[] = 'toplevel_page_plugin-manager';
    }

    if (isset($uixPressOptions["use_modern_media_page"]) && $uixPressOptions["use_modern_media_page"] === true) {
      $uixpress_pages[] = 'upload';
    }
    



    
    if ( $screen && in_array( $screen->id, $uixpress_pages ) ) {
        $classes .= ' uixpress-custom-page';
    }

    
    return $classes;
}


  /**
   * Remove css that causes issues with uixpress
   *
   * @param array $scripts array of scripts / styles
   *
   * @return array
   * @since 3.2.13
   */
  public static function maybe_remove_assets()
  {
    $pageid = property_exists(self::$screen, "id") ? self::$screen->id : "";

    if ($pageid != "toplevel_page_latepoint") {
      wp_dequeue_style("latepoint-main-admin");
      wp_deregister_style("latepoint-main-admin");
    }
  }

  /**
   * Loads translation files
   *
   * @since 1.0.8
   */
  public static function languages_loader()
  {
    load_plugin_textdomain("uixpress", false, dirname(dirname(dirname(dirname(plugin_basename(__FILE__))))) . "/languages");
  }

  /**
   * Loads empty translation script
   *
   * @since 1.0.8
   */
  public static function load_base_script()
  {
    // Don't load on site-editor
    if (self::is_site_editor() || self::is_mainwp_page()) {
      return;
    }

    // Get plugin url
    self::$plugin_url = plugins_url("uixpress/");
    self::$script_name = Scripts::get_base_script_path("uixpress.js");



    if (!self::$script_name) {
      return;
    }

    self::output_script_attributes();

    if (self::is_theme_disabled()) {
      return;
    }

    $file = self::$script_name;
    $url = self::$plugin_url;

    // Setup script object
    $builderScript = [
      "id" => "uixpress-app-js",
      "type" => "module",
      "src" => $url . "app/dist/{$file}",
    ];
    wp_print_script_tag($builderScript);

    // Set up translations
    wp_enqueue_script("uixpress", $url . "assets/js/translations.js", ["wp-i18n"], false);
    wp_set_script_translations("uixpress", "uixpress", uixpress_plugin_path . "/languages/");
  }

  /**
   * Check if the current user has access based on user ID or role.
   *
   * @param array $access_list An array of user IDs and roles to check against.
   * @return bool True if the current user has access, false otherwise.
   */
  private static function is_theme_disabled()
  {
    $access_list = isset(self::$options["disable_theme"]) && is_array(self::$options["disable_theme"]) ? self::$options["disable_theme"] : false;

    if (!$access_list) {
      return;
    }

    // Get the current user
    $current_user = wp_get_current_user();

    // Get the current user's ID and roles
    $current_user_id = $current_user->ID;
    $current_user_roles = $current_user->roles;

    foreach ($access_list as $item) {
      // Check user
      if ($item["type"] == "user") {
        if ($current_user_id == $item["id"]) {
          return true;
        }
      }
      // Check if role
      elseif ($item["type"] == "role") {
        if (in_array($item["value"], $current_user_roles)) {
          return true;
        }
      }
    }

    // If no match found, return false
    return false;
  }

  /**
   * Changes the name and description of a specific plugin in the WordPress plugins list.
   *
   * This function modifies the name and description of the 'uixpress' plugin
   * if a custom name is set in the site options. It replaces occurrences of 'uixpress'
   * in the plugin description with 'toast'.
   *
   * @param array $all_plugins An associative array of all WordPress plugins.
   *                           The keys are plugin paths and the values are plugin data.
   *
   * @return array The modified array of plugins. If no custom name is set or
   *               the custom name is empty, the original array is returned unchanged.
   */
  public static function change_plugin_name($all_plugins)
  {
    $options = self::$options;
    $menu_name = isset($options["plugin_name"]) && $options["plugin_name"] != "" ? esc_html($options["plugin_name"]) : false;

    // No custom name so bail
    if (!$menu_name || $menu_name == "") {
      return $all_plugins;
    }

    $slug = "uixpress/uixpress.php";

    // the & means we're modifying the original $all_plugins array
    foreach ($all_plugins as $plugin_file => &$plugin_data) {
      if ($slug === $plugin_file) {
        $plugin_data["Name"] = $menu_name;
        $plugin_data["Author"] = $menu_name;
        $plugin_data["Description"] = str_ireplace("uixpress", $menu_name, $plugin_data["Description"]);
      }
    }

    return $all_plugins;
  }

  /**
   * Saves the current screen
   *
   */
  public static function get_screen()
  {
    // Get screen
    self::$screen = get_current_screen();
  }

  /**
   * Saves the global options
   *
   */
  public static function get_global_options()
  {
    self::$options = Settings::get();
  }

  /**
   *Return global options
   *
   */
  public static function return_global_options()
  {
    if (empty(self::$options)) {
      self::$options = Settings::get();
    }

    self::$options['layout'] = 'rounded';

    return self::$options;
  }


  /**
   * Page holder.
   *
   * Outputs the app holder
   */
  public static function page_holder()
  {
   return;
  }

  /**
   * uixpress styles.
   *
   * Loads main lp styles
   */
  public static function output_app()
  {
    if (self::is_site_editor() || self::is_theme_disabled() || self::is_mainwp_page()) {
      return;
    }

    return;
    echo '<div id="uip-classic-app" class="bg-white dark:bg-zinc-900" style="position: fixed;
    inset: 0 0 0 0;
    height: 100dvh;
    width: 100dvw;
    z-index: 2;"></div>';
  }

  /**
   * uixpress styles.
   *
   * Loads main lp styles
   */
  public static function load_styles()
  {
    // Don't load anything on site editor page
    if (self::is_site_editor() || self::is_theme_disabled() || self::is_mainwp_page()) {
      return;
    }

    // Get plugin url
    $url = plugins_url("uixpress/");
    $style = $url . "app/dist/assets/styles/app.css";
    $options = self::get_global_options();

    // Load external styles
    if (is_array(self::$options) && isset(self::$options["external_stylesheets"]) && is_array(self::$options["external_stylesheets"])) {
      $index = 0;
      foreach (self::$options["external_stylesheets"] as $stylekey) {
        $index++;
        // Validate and sanitize URL to prevent XSS and SSRF
        $url = esc_url_raw($stylekey);
        $parsed = parse_url($url);
        // Only allow http and https schemes
        if (!isset($parsed['scheme']) || !in_array($parsed['scheme'], ['http', 'https'])) {
          continue;
        }
        wp_enqueue_style("uix-external-{$index}", $url, [], null);
      }
    }

    wp_enqueue_style("uixpress", $style, [], uixpress_plugin_version);

    // Check if we are on block editor page. Don't load theme if so
    if (self::is_block_editor()) {
      self::load_block_styles();
      return;
    }

    $theme = $url . "app/dist/assets/styles/theme.css";
    wp_enqueue_style("uixpress-theme", $theme, [], uixpress_plugin_version);
  }

  /**
   * loads block editor specific styles
   *
   */
  public static function load_block_styles()
  {
?>
    <style>
      body.is-fullscreen-mode.learndash-post-type #sfwd-header {
        left: 0 !important;
      }
    </style>
<?php
  }

  /**
   * uixpress page.
   *
   * Outputs the app holder
   */
  public static function build_uixpress()
  {
    // Don't run on site editor page
    if (self::is_site_editor() || self::is_theme_disabled() || self::is_mainwp_page()) {
      return;
    }
    //wp_enqueue_media();
    // Output the app
    //echo "<style>#wpadminbar, #adminmenumain, #wpfooter {display:none}#wpcontent{margin-left:0}html{font-size:14px !important;padding:0 !important;}</style>";
    echo "<style id='uipc-temporary-body-hider'>body > *:not(#uip-classic-app) {opacity:0}</style>";
  }

  /**
   * Checks if we should remove the theme css based on current page
   *
   * Some plugins are broken by the theme and in which case the theme needs to be removed.
   */
  private static function is_no_theme_page()
  {
    $page = isset($_GET["page"]) ? sanitize_text_field($_GET["page"]) : '';
    return $page === "gf_edit_forms";
  }

  /**
   * Returns whether we are on the block editor page
   *
   */
  private static function is_mainwp_page()
  {
    return is_object(self::$screen) && isset(self::$screen->id) && strpos(self::$screen->id, "mainwp") !== false;
  }

  /**
   * Returns whether we are on the block editor page
   *
   */
  private static function is_block_editor()
  {
    return is_object(self::$screen) && method_exists(self::$screen, "is_block_editor") && self::$screen->is_block_editor();
  }

  /**
   * Returns whether we are on the site editor page
   *
   */
  private static function is_site_editor()
  {
    return is_object(self::$screen) && isset(self::$screen->id) && (self::$screen->id == "site-editor" || self::$screen->id == "customize");
  }

  /**
   * uixpress scripts.
   *
   * Loads main lp scripts
   */
  public static function output_script_attributes()
  {
    // Don't load on site-editor
    if (self::is_site_editor()) {
      return;
    }

    $url = plugins_url("uixpress/");
    $rest_base = get_rest_url();
    $rest_nonce = wp_create_nonce("wp_rest");
    $admin_url = get_admin_url();
    $login_url = wp_login_url();
    global $wp_post_types;

    // Get the current user object
    $current_user = wp_get_current_user();
    $first_name = $current_user->first_name;
    $roles = (array) $current_user->roles;
    $options = self::return_global_options();
    $formatArgs = JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES | JSON_NUMERIC_CHECK;
    $manageOptions = current_user_can("manage_options") ? "true" : "false";

    // Remove the 'license_key' key
    unset($options["license_key"]);
    unset($options["instance_id"]);

    // Check if user has capability to access remote sites options
    // Prevents exposing passwords to unauthorized users
    $remote_site_capability = isset($options["remote_site_switcher_capability"]) && !empty($options["remote_site_switcher_capability"])
      ? $options["remote_site_switcher_capability"]
      : "manage_options";
    
    if (!current_user_can($remote_site_capability)) {
      unset($options["remote_sites"]);
      unset($options["remote_site_switcher_capability"]);
    }

    // If first name is empty, fall back to display name
    if (empty($first_name)) {
      $first_name = $current_user->display_name;
    }

    // Get the user's email
    $email = $current_user->user_email;

    $frontPage = is_admin() ? "false" : "true";
    $mime_types = array_values(get_allowed_mime_types());

    // Setup script object
    $builderScript = [
      "id" => "uipc-script",
      "type" => "module",
      "plugin-base" => esc_url($url),
      "rest-base" => esc_url($rest_base),
      "rest-nonce" => esc_attr($rest_nonce),
      "admin-url" => esc_url($admin_url),
      "login-url" => esc_url($login_url),
      "user-id" => esc_attr(get_current_user_id()),
      "user-roles" => esc_attr(json_encode($roles)),
      "uixpress-settings" => esc_attr(json_encode($options, $formatArgs)),
      "user-name" => esc_attr($first_name),
      "can-manage-options" => esc_attr($manageOptions),
      "user-email" => esc_attr($email),
      "site-url" => esc_url(get_home_url()),
      "front-page" => esc_attr($frontPage),
      "post_types" => esc_attr(json_encode($wp_post_types)),
      "mime_types" => esc_attr(json_encode($mime_types)),
      "current-user" => esc_attr(json_encode([
        'ID' => $current_user->ID,
        'display_name' => $current_user->display_name,
        'user_email' => $current_user->user_email,
        'roles' => $current_user->roles
      ])),
    ];

    // Print tag
    wp_print_script_tag($builderScript);
  }
}
