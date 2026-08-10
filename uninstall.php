<?php
/**
 * Sleek Audio Player - Uninstall
 * 
 * Removes all plugin data when the plugin is deleted.
 * This file is called automatically by WordPress when the plugin is deleted.
 * 
 * @author Martin Gräbing
 * @link https://www.duesseldorp.de
 * @license GPL-2.0-or-later
 */

// Exit if not called by WordPress
if (!defined('WP_UNINSTALL_PLUGIN')) {
    exit;
}

global $wpdb;

// Delete themes table
$sleekaudio_table_name = esc_sql( $wpdb->prefix . 'sap_themes' );
// phpcs:ignore WordPress.DB.DirectDatabaseQuery.DirectQuery, WordPress.DB.DirectDatabaseQuery.NoCaching, WordPress.DB.DirectDatabaseQuery.SchemaChange, WordPress.DB.PreparedSQL.InterpolatedNotPrepared -- uninstall must drop the plugin's own custom table; identifier from $wpdb->prefix, escaped with esc_sql() (%i requires WP 6.2+, plugin supports 5.0+)
$wpdb->query( "DROP TABLE IF EXISTS `{$sleekaudio_table_name}`" );

// Delete options
delete_option('sap_active_theme_id');
delete_option('sap_cdn_url');
delete_option('sap_umami_tracking');
delete_option('sap_umami_script_url');
delete_option('sap_umami_website_id');
delete_option('sap_url_protection');
delete_option('sap_cover_click_play');
delete_option('sap_remember_position');
delete_option('sap_visualizer_type');

// Delete waveform data stored on audio attachments (regenerable, plugin-specific)
// phpcs:ignore WordPress.DB.DirectDatabaseQuery.DirectQuery, WordPress.DB.DirectDatabaseQuery.NoCaching
$wpdb->query( $wpdb->prepare( "DELETE FROM {$wpdb->postmeta} WHERE meta_key = %s", '_sap_waveform' ) );

// Optional: Delete all playlist post meta (uncomment if desired)
// $wpdb->query("DELETE FROM {$wpdb->postmeta} WHERE meta_key LIKE '_sap_%'");

// Optional: Delete playlist posts (uncomment if desired)
// $wpdb->query("DELETE FROM {$wpdb->posts} WHERE post_type = 'sap_playlist'");
