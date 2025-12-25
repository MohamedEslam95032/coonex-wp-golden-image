<?php
namespace UiXpress\Utility;
/**
 * Class Scripts
 *
 * Main class for initialising the uixpress app.
 */
class Scripts
{
  /**
   * Get the path of the Vite-built base script.
   *
   * This function reads the Vite manifest file and finds the compiled filename
   * for the 'uixpress.js' entry point. It uses WordPress file reading functions
   * for better compatibility and security.
   *
   * @return string|null The filename of the compiled base script, or null if not found.
   */
  public static function get_base_script_path($filename)
  {
    $manifest_path = uixpress_plugin_path . "app/dist/.vite/manifest.json";

    if (!file_exists($manifest_path)) {
      return null;
    }

    $manifest_content = file_get_contents($manifest_path);
    if ($manifest_content === false) {
      return null;
    }

    $manifest = json_decode($manifest_content, true);
    if (!is_array($manifest)) {
      return null;
    }

    foreach ($manifest as $key => $value) {
      if (isset($value["src"]) && $value["src"] === "apps/js/{$filename}") {
        return $value["file"];
      }
    }

    return null;
  }
}
