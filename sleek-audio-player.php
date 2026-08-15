<?php
/**
 * Plugin Name: Sleek Audio Player
 * Description: Minimal audio player with download, shuffle, cover art, and visualization
 * Version: 2.7.1
 * Author: Martin Gräbing
 * Author URI: https://www.duesseldorp.de
 * Plugin URI: https://www.duesseldorp.de/sleek-audio-player
 * Text Domain: sleek-audio-player
 * Domain Path: /languages
 * Requires at least: 5.0
 * Requires PHP: 7.4
 * License: GPL v2 or later
 * License URI: https://www.gnu.org/licenses/gpl-2.0.html
 *
 * @package   Sleek_Audio_Player
 * @copyright 2025-2026 Martin Gräbing
 *
 * Sleek Audio Player is free software: you can redistribute it and/or modify
 * it under the terms of the GNU General Public License as published by the
 * Free Software Foundation, either version 2 of the License, or (at your
 * option) any later version.
 *
 * Sleek Audio Player is distributed in the hope that it will be useful, but
 * WITHOUT ANY WARRANTY; without even the implied warranty of MERCHANTABILITY
 * or FITNESS FOR A PARTICULAR PURPOSE. See the GNU General Public License
 * for more details.
 */

defined('ABSPATH') || exit;

define('SLEEKAUDIO_VERSION', '2.7.1');
define('SLEEKAUDIO_DEBUG', defined('WP_DEBUG') && WP_DEBUG);

/**
 * Logging helper for debugging
 */
function sleekaudio_log($message, $data = null) {
    if (!SLEEKAUDIO_DEBUG) return;
    
    $log_message = '[SAP] ' . $message;
    if ($data !== null) {
        $log_message .= ' | Data: ' . (is_array($data) || is_object($data) ? wp_json_encode($data) : $data);
    }
    // phpcs:ignore WordPress.PHP.DevelopmentFunctions.error_log_error_log -- Only reachable when WP_DEBUG is enabled (see SLEEKAUDIO_DEBUG above); silent in production
    error_log($log_message);
}

/**
 * Validate track data array
 * @param array $track Track data
 * @return array Sanitized track data with defaults
 */
function sleekaudio_validate_track($track) {
    if (!is_array($track)) {
        return null;
    }
    
    return array(
        'title' => isset($track['title']) ? sanitize_text_field($track['title']) : '',
        'artist' => isset($track['artist']) ? sanitize_text_field($track['artist']) : '',
        'url' => isset($track['url']) ? esc_url_raw($track['url']) : '',
        'attachment_id' => isset($track['attachment_id']) ? absint($track['attachment_id']) : 0,
        'cover_id' => isset($track['cover_id']) ? absint($track['cover_id']) : 0,
        'cover_url' => isset($track['cover_url']) ? esc_url_raw($track['cover_url']) : '',
        'spotify' => isset($track['spotify']) ? esc_url_raw($track['spotify']) : '',
        'apple' => isset($track['apple']) ? esc_url_raw($track['apple']) : '',
        'amazon' => isset($track['amazon']) ? esc_url_raw($track['amazon']) : '',
        'soundcloud' => isset($track['soundcloud']) ? esc_url_raw($track['soundcloud']) : '',
        'downloadable' => !empty($track['downloadable']),
        'duration' => isset($track['duration']) ? sanitize_text_field($track['duration']) : '',
        'waveform' => isset($track['waveform']) && is_array($track['waveform']) ? $track['waveform'] : null,
    );
}

/**
 * Validate playlist tracks array
 * @param mixed $tracks Tracks data from post meta
 * @return array Validated tracks array
 */
function sleekaudio_validate_tracks($tracks) {
    if (!is_array($tracks)) {
        return array();
    }
    
    $validated = array();
    foreach ($tracks as $track) {
        $valid_track = sleekaudio_validate_track($track);
        if ($valid_track && !empty($valid_track['url'])) {
            $validated[] = $valid_track;
        }
    }
    
    return $validated;
}

/**
 * Safe database query wrapper with fallback for older WordPress versions
 * @param string $query SQL query
 * @param mixed ...$args Query arguments
 * @return mixed Query result or false on error
 */
function sleekaudio_db_query($query, ...$args) {
    global $wpdb;
    
    try {
        // Check if using %i placeholder (WP 6.2+)
        if (strpos($query, '%i') !== false) {
            // Check WordPress version for %i support
            global $wp_version;
            if (version_compare($wp_version, '6.2', '<')) {
                // Fallback: Replace %i with backtick-escaped table name
                // This is a simplified fallback - works for single table queries
                $query = preg_replace('/%i/', '`' . esc_sql($args[0]) . '`', $query, 1);
                array_shift($args);
                if (!empty($args)) {
                    // phpcs:ignore WordPress.DB.PreparedSQL.NotPrepared -- Query is built safely above with esc_sql
                    return $wpdb->prepare($query, ...$args);
                }
                return $query;
            }
        }
        
        if (!empty($args)) {
            // phpcs:ignore WordPress.DB.PreparedSQL.NotPrepared -- Variable query with proper placeholders
            return $wpdb->prepare($query, ...$args);
        }
        return $query;
    } catch (Exception $e) {
        sleekaudio_log('Database query error', $e->getMessage());
        return false;
    }
}
define('SLEEKAUDIO_PLUGIN_DIR', plugin_dir_path(__FILE__));
define('SLEEKAUDIO_PLUGIN_URL', plugin_dir_url(__FILE__));

// Must be resolved here, like register_activation_hook() below: only in this
// file does __FILE__ identify the plugin. Code in includes/ that derives a
// plugin path from its own __FILE__ silently gets ".../includes/..." instead.
define('SLEEKAUDIO_PLUGIN_BASENAME', plugin_basename(__FILE__));

// Translations are loaded by SleekAudio_Player::load_textdomain() on 'init'.
// WordPress only auto-loads language packs from wordpress.org; the bundled
// files in languages/ need the explicit call.

require_once SLEEKAUDIO_PLUGIN_DIR . 'includes/class-theme-manager.php';
require_once SLEEKAUDIO_PLUGIN_DIR . 'includes/class-waveform-manager.php';
require_once SLEEKAUDIO_PLUGIN_DIR . 'includes/class-player.php';

// Initialize theme manager
SleekAudio_Theme_Manager::get_instance();

// Activation hook for creating table.
// Must stay in this file: __FILE__ identifies the plugin to WordPress.
register_activation_hook(__FILE__, array('SleekAudio_Theme_Manager', 'create_table'));

// Initialize waveform manager
SleekAudio_Waveform_Manager::get_instance();

// Initialize plugin
SleekAudio_Player::get_instance();
