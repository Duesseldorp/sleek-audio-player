<?php
/**
 * Test-only stand-in for an SEO plugin, mapped into wp-env by .wp-env.json.
 *
 * Active only on requests carrying ?e2e_seo=1, so every other test keeps
 * running against a site without an SEO plugin. It defines the constant
 * Yoast SEO defines - which is what the player's detection looks for - and
 * prints the tags such a plugin would.
 */

defined('ABSPATH') || exit;

// phpcs:ignore WordPress.Security.NonceVerification.Recommended -- test switch, read-only
if (isset($_GET['e2e_seo'])) {
    define('WPSEO_VERSION', 'e2e');

    add_action('wp_head', function () {
        echo '<meta property="og:title" content="SEO plugin title">' . "\n";
        echo '<meta property="og:image" content="https://example.org/seo-share.jpg">' . "\n";
    }, 1);
}
