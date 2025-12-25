<?php
namespace UiXpress\Options;

use enshrined\svgSanitize\Sanitizer;

// Prevent direct access to this file
defined("ABSPATH") || exit();

/**
 * Class MediaOptions
 *
 * Handles media-related options such as SVG uploads.
 *
 * @package UiXpress\Options
 */
class MediaOptions
{
  /**
   * Stores the global options.
   *
   * @var array|null
   */
  private static $options = null;

  /**
   * MediaOptions constructor.
   *
   * Initializes the class and adds filters for media options.
   */
  public function __construct()
  {
    add_filter("upload_mimes", [$this, "maybe_enable_svg_uploads"], 10, 1);
    add_filter("wp_check_filetype_and_ext", [$this, "fix_svg_filetype_check"], 10, 5);
    add_filter("wp_prepare_attachment_for_js", [$this, "fix_svg_media_thumbnails"], 10, 3);
    add_filter("wp_handle_upload_prefilter", [$this, "sanitize_svg_upload"], 10, 1);
  }

  /**
   * Checks if SVG uploads are enabled.
   *
   * @return bool True if SVG uploads are enabled, false otherwise
   */
  private static function is_svg_uploads_enabled()
  {
    return Settings::is_enabled("enable_svg_uploads");
  }

  /**
   * Enables SVG uploads if the setting is enabled.
   *
   * @param array $mimes Array of allowed mime types
   * @return array Modified array of mime types
   */
  public static function maybe_enable_svg_uploads($mimes)
  {
    if (!self::is_svg_uploads_enabled()) {
      return $mimes;
    }

    // Enable SVG uploads
    $mimes["svg"] = "image/svg+xml";
    $mimes["svgz"] = "image/svg+xml";

    return $mimes;
  }

  /**
   * Fixes WordPress filetype check for SVG files.
   *
   * WordPress doesn't recognize SVG files by default, so we need to help it.
   *
   * @param array $data File data array
   * @param string $file Full path to the file
   * @param string $filename The name of the file
   * @param array $mimes Array of mime types keyed by the file extension regex
   * @param string $real_mime The actual mime type or empty if unknown
   * @return array Modified file data array
   */
  public static function fix_svg_filetype_check($data, $file, $filename, $mimes, $real_mime = null)
  {
    if (!self::is_svg_uploads_enabled()) {
      return $data;
    }

    // Check if file is SVG
    $wp_filetype = wp_check_filetype($filename, $mimes);
    $ext = $wp_filetype["ext"];
    $type = $wp_filetype["type"];

    // If it's an SVG file, fix the filetype check
    if ($ext === "svg" || $type === "image/svg+xml") {
      $data = [
        "ext" => "svg",
        "type" => "image/svg+xml",
        "proper_filename" => $filename,
      ];
    }

    return $data;
  }

  /**
   * Fixes SVG thumbnails in the media library.
   *
   * Ensures SVG files display correctly in the WordPress media library.
   *
   * @param array $response Array of prepared attachment data
   * @param WP_Post $attachment Attachment object
   * @param array|false $meta Array of attachment meta data, or false if there is none
   * @return array Modified response array
   */
  public static function fix_svg_media_thumbnails($response, $attachment, $meta)
  {
    if (!self::is_svg_uploads_enabled()) {
      return $response;
    }

    // Check if attachment is an SVG
    if ($response["mime"] === "image/svg+xml") {
      // Get the attachment URL
      $attachment_url = wp_get_attachment_url($attachment->ID);

      // Set image data for SVG
      $response["image"] = [
        "src" => $attachment_url,
        "width" => 150,
        "height" => 150,
      ];

      // Set thumbnail sizes
      $response["sizes"] = [
        "full" => [
          "url" => $attachment_url,
          "width" => 150,
          "height" => 150,
          "orientation" => "landscape",
        ],
        "thumbnail" => [
          "url" => $attachment_url,
          "width" => 150,
          "height" => 150,
          "orientation" => "landscape",
        ],
        "medium" => [
          "url" => $attachment_url,
          "width" => 300,
          "height" => 300,
          "orientation" => "landscape",
        ],
        "large" => [
          "url" => $attachment_url,
          "width" => 1024,
          "height" => 1024,
          "orientation" => "landscape",
        ],
      ];
    }

    return $response;
  }

  /**
   * Sanitizes SVG files before they are uploaded.
   *
   * This filter runs before WordPress processes the upload and sanitizes
   * SVG content to remove potentially malicious code.
   *
   * @param array $file Array of file data
   * @return array Modified file data array, or WP_Error on failure
   */
  public static function sanitize_svg_upload($file)
  {
    if (!self::is_svg_uploads_enabled()) {
      return $file;
    }

    // Check if file is SVG
    $filetype = wp_check_filetype($file["name"]);
    if ($filetype["ext"] !== "svg" && $filetype["type"] !== "image/svg+xml") {
      return $file;
    }

    // Check if file exists and is readable
    if (!file_exists($file["tmp_name"]) || !is_readable($file["tmp_name"])) {
      return $file;
    }

    // Read SVG content
    $svg_content = file_get_contents($file["tmp_name"]);
    if ($svg_content === false) {
      return new \WP_Error(
        "svg_read_error",
        __("Unable to read SVG file for sanitization.", "uixpress"),
        ["status" => 400]
      );
    }

    // Sanitize SVG content
    try {
      $sanitizer = new Sanitizer();
      $sanitized_svg = $sanitizer->sanitize($svg_content);

      // Check if sanitization failed
      if ($sanitized_svg === false) {
        return new \WP_Error(
          "svg_sanitize_error",
          __("SVG file could not be sanitized. The file may be corrupted or contain invalid content.", "uixpress"),
          ["status" => 400]
        );
      }

      // Write sanitized content back to temp file
      $result = file_put_contents($file["tmp_name"], $sanitized_svg);
      if ($result === false) {
        return new \WP_Error(
          "svg_write_error",
          __("Unable to write sanitized SVG file.", "uixpress"),
          ["status" => 500]
        );
      }

      // Update file size after sanitization
      $file["size"] = filesize($file["tmp_name"]);
    } catch (\Exception $e) {
      return new \WP_Error(
        "svg_sanitize_exception",
        sprintf(
          __("Error sanitizing SVG file: %s", "uixpress"),
          $e->getMessage()
        ),
        ["status" => 500]
      );
    }

    return $file;
  }
}

