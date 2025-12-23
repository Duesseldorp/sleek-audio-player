<?php
/**
 * Simple Audio Player - Uninstall
 * 
 * Removes all plugin data when the plugin is deleted.
 * This file is called automatically by WordPress when the plugin is deleted.
 */

// Exit if not called by WordPress
if (!defined('WP_UNINSTALL_PLUGIN')) {
    exit;
}

global $wpdb;

// Delete themes table
$table_name = $wpdb->prefix . 'sap_themes';
$wpdb->query("DROP TABLE IF EXISTS $table_name");

// Delete options
delete_option('sap_active_theme_id');
delete_option('sap_cdn_url');
delete_option('sap_umami_tracking');
delete_option('sap_url_protection');
delete_option('sap_cover_click_play');
delete_option('sap_remember_position');
delete_option('sap_visualizer_type');

// Optional: Delete all playlist post meta (uncomment if desired)
// $wpdb->query("DELETE FROM {$wpdb->postmeta} WHERE meta_key LIKE '_sap_%'");

// Optional: Delete playlist posts (uncomment if desired)
// $wpdb->query("DELETE FROM {$wpdb->posts} WHERE post_type = 'sap_playlist'");
