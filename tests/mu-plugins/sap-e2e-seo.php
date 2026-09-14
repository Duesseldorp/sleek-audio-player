<?php
/**
 * Test-only stand-ins for SEO plugins, mapped into wp-env by .wp-env.json.
 *
 * Active only on requests that ask for one, so every other test keeps running
 * against a site without an SEO plugin:
 *
 *   ?e2e_seo=yoast   defines the constant Yoast SEO defines and prints fixed
 *                    tags. The player has to stay out entirely.
 *   ?e2e_seo=aioseo  defines aioseo() and prints tags the way All in One SEO
 *                    does: through the aioseo_facebook_tags and
 *                    aioseo_twitter_tags filters, empty values dropped, each
 *                    value escaped with esc_attr() (app/Common/Social/Output.php
 *                    and app/Common/Views/main/social.php in its source).
 */

defined('ABSPATH') || exit;

// phpcs:ignore WordPress.Security.NonceVerification.Recommended -- test switch, read-only
$sap_e2e_seo = isset($_GET['e2e_seo']) ? sanitize_key(wp_unslash($_GET['e2e_seo'])) : '';

if ($sap_e2e_seo === 'yoast') {
    define('WPSEO_VERSION', 'e2e');

    add_action('wp_head', function () {
        echo '<meta property="og:title" content="SEO plugin title">' . "\n";
        echo '<meta property="og:image" content="https://example.org/seo-share.jpg">' . "\n";
    }, 1);
}

if ($sap_e2e_seo === 'aioseo') {
    function aioseo() {
        return null;
    }

    add_action('wp_head', function () {
        $facebook = array_filter(apply_filters('aioseo_facebook_tags', array(
            'og:type'             => 'article',
            'og:title'            => 'SEO plugin title',
            'og:description'      => 'SEO plugin description',
            'og:url'              => home_url('/player-page/'),
            'og:image'            => 'https://example.org/seo-share.jpg',
            'og:image:secure_url' => 'https://example.org/seo-share.jpg',
            'og:image:width'      => 1200,
            'og:image:height'     => 1200,
        )));
        foreach ($facebook as $key => $value) {
            echo '<meta property="' . esc_attr($key) . '" content="' . esc_attr($value) . '" />' . "\n";
        }

        $twitter = array_filter(apply_filters('aioseo_twitter_tags', array(
            'twitter:card'        => 'summary_large_image',
            'twitter:title'       => 'SEO plugin title',
            'twitter:description' => 'SEO plugin description',
            'twitter:image'       => 'https://example.org/seo-share.jpg',
        )));
        foreach ($twitter as $key => $value) {
            echo '<meta name="' . esc_attr($key) . '" content="' . esc_attr($value) . '" />' . "\n";
        }
    }, 1);
}
