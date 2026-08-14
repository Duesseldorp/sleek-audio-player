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

$uploads_url = content_url('/uploads/sap-test');

$tracks = array(
    array(
        'title'        => 'E2E Track One',
        'artist'       => 'Test Artist',
        'url'          => $uploads_url . '/track1.wav',
        'attachment_id' => 0,
        'cover_id'     => 0,
        'cover_url'    => '',
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
        'cover_url'    => '',
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
        'cover_url'    => '',
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

$no_player = sleekaudio_e2e_upsert(
    'no-player-page',
    'page',
    'No Player Page',
    "This page contains no player at all.\n\nIt must not load any plugin assets."
);

flush_rewrite_rules();

WP_CLI::success(sprintf(
    'Seeded: playlist #%d, /player-page/ #%d, /player-mini/ #%d, /no-player-page/ #%d',
    $playlist_id,
    $player_page,
    $mini_page,
    $no_player
));
