<?php
/**
 * Seeds the wp-env test site with the content the end-to-end tests expect.
 *
 * Run via:  npm run seed
 *   (wp-env run cli wp eval-file wp-content/plugins/sleek-audio-player/tests/seed.php)
 *
 * Creates, idempotently:
 *   - a playlist "E2E Playlist" with three tracks (durations filled in, so a
 *     regression of the duration-loss bug shows up in the frontend)
 *   - page /player-page/   - shortcode surrounded by prose, so wpautop runs
 *     over the rendered output exactly like a page builder would
 *   - page /no-player-page/ - must load zero plugin assets
 *   - page /player-mini/    - mini layout
 *
 * Test files are mapped into uploads by .wp-env.json.
 */

defined('ABSPATH') || exit;

// A fresh WordPress uses plain permalinks (?p=123), so /player-page/ and
// /playlist/<slug>/ would 404 and the tests would see a 404 page instead of
// a player. Pretty permalinks are also what the plugin's public playlist
// URLs and SEO output assume.
if (get_option('permalink_structure') !== '/%postname%/') {
    update_option('permalink_structure', '/%postname%/');
}

$uploads_url = content_url('/uploads/sap-test');

$tracks = array(
    array(
        'title'        => 'E2E Track One',
        'artist'       => 'Test Artist',
        'url'          => $uploads_url . '/track1.wav',
        'attachment_id' => 0,
        'cover_id'     => 0,
        'cover_url'    => $uploads_url . '/cover.png',
        'spotify'      => 'https://open.spotify.com/track/e2e',
        'apple'        => '',
        'amazon'       => '',
        'soundcloud'   => '',
        'downloadable' => false,
        'duration'     => '0:03',
    ),
    array(
        'title'        => 'E2E Track Two',
        'artist'       => 'Test Artist',
        'url'          => $uploads_url . '/track2.wav',
        'attachment_id' => 0,
        'cover_id'     => 0,
        'cover_url'    => $uploads_url . '/cover.png',
        'spotify'      => '',
        'apple'        => '',
        'amazon'       => '',
        'soundcloud'   => 'https://soundcloud.com/e2e',
        'downloadable' => true,
        'duration'     => '0:03',
    ),
    array(
        'title'        => 'E2E Track Three',
        'artist'       => 'Test Artist',
        'url'          => $uploads_url . '/track3.wav',
        'attachment_id' => 0,
        'cover_id'     => 0,
        'cover_url'    => $uploads_url . '/cover.png',
        'spotify'      => '',
        'apple'        => '',
        'amazon'       => '',
        'soundcloud'   => '',
        'downloadable' => false,
        'duration'     => '0:03',
    ),
);

/**
 * Create or update a post by slug.
 */
function sleekaudio_e2e_upsert($slug, $type, $title, $content) {
    $existing = get_page_by_path($slug, OBJECT, $type);
    $data = array(
        'post_name'    => $slug,
        'post_title'   => $title,
        'post_type'    => $type,
        'post_status'  => 'publish',
        'post_content' => $content,
    );
    if ($existing) {
        $data['ID'] = $existing->ID;
        wp_update_post($data);
        return $existing->ID;
    }
    return wp_insert_post($data);
}

$playlist_id = sleekaudio_e2e_upsert('e2e-playlist', 'sap_playlist', 'E2E Playlist', '');
update_post_meta($playlist_id, '_sap_tracks', $tracks);

// Prose around the shortcode with blank lines: this is what makes wpautop
// insert <p>/<br> around and inside the rendered player on some setups.
$player_content = "Intro text above the player.\n\n[sleek_player id=\"{$playlist_id}\"]\n\nOutro text below the player.";
$player_page = sleekaudio_e2e_upsert('player-page', 'page', 'Player Page', $player_content);

$mini_content = "[sleek_player id=\"{$playlist_id}\" layout=\"mini\"]";
$mini_page = sleekaudio_e2e_upsert('player-mini', 'page', 'Player Mini', $mini_content);

// Legacy shortcode alias - a documented compatibility promise
$legacy_page = sleekaudio_e2e_upsert(
    'player-legacy',
    'page',
    'Player Legacy',
    "[simple_player id=\"{$playlist_id}\"]"
);

// Gutenberg block - the second official embedding path
$block_page = sleekaudio_e2e_upsert(
    'player-block',
    'page',
    'Player Block',
    '<!-- wp:sleek-audio-player/player {"playlistId":"' . $playlist_id . '","layout":"wide"} /-->'
);

// Two players on one page - only one may play at a time
$two_page = sleekaudio_e2e_upsert(
    'player-two',
    'page',
    'Two Players',
    "First player:\n\n[sleek_player id=\"{$playlist_id}\"]\n\nSecond player:\n\n[sleek_player id=\"{$playlist_id}\" layout=\"mini\"]"
);

$no_player = sleekaudio_e2e_upsert(
    'no-player-page',
    'page',
    'No Player Page',
    "This page contains no player at all.\n\nIt must not load any plugin assets."
);

flush_rewrite_rules(true);

// WP-CLI has no $_SERVER['SERVER_SOFTWARE'], so WordPress does not consider
// itself to be running under Apache and flush_rewrite_rules(true) silently
// skips writing .htaccess - pretty permalinks then 404. Write it ourselves.
global $wp_rewrite;
$htaccess_file = ABSPATH . '.htaccess';
$rules = $wp_rewrite->mod_rewrite_rules();
if (empty($rules)) {
    $rules = "<IfModule mod_rewrite.c>\n"
        . "RewriteEngine On\n"
        . "RewriteBase /\n"
        . "RewriteRule ^index\\.php$ - [L]\n"
        . "RewriteCond %{REQUEST_FILENAME} !-f\n"
        . "RewriteCond %{REQUEST_FILENAME} !-d\n"
        . "RewriteRule . /index.php [L]\n"
        . "</IfModule>\n";
}
$written = file_put_contents($htaccess_file, "# BEGIN WordPress\n" . $rules . "# END WordPress\n");
if ($written === false) {
    WP_CLI::warning('Could not write ' . $htaccess_file . ' - pretty permalinks may 404.');
}

WP_CLI::success(sprintf(
    'Seeded: playlist #%d, pages: player #%d, mini #%d, legacy #%d, block #%d, two #%d, none #%d (permalinks: %s, .htaccess: %d bytes)',
    $playlist_id,
    $player_page,
    $mini_page,
    $legacy_page,
    $block_page,
    $two_page,
    $no_player,
    get_option('permalink_structure'),
    (int) $written
));
