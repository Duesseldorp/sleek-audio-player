/**
 * What the seeded test data actually is.
 *
 * Tests kept guessing these and getting them wrong. Two examples from
 * 2026-08-16, both of which cost a CI round: a seek test used 5-second steps
 * and an End key against tracks that are three seconds long, so the steps were
 * clipped by the duration and End ran into the track change; and a cover test
 * asserted on `.sap-cover-slide`, which render_player() only emits when a
 * cover exists - the seeded tracks had none at the time.
 *
 * Numbers here are not the source of truth: tests/make-fixtures.mjs generates
 * the audio and tests/seed.php creates the playlist. "the fixtures are what
 * the tests assume" in characterization.spec.js compares this file against the
 * running site, so a change on either side shows up as one clear failure
 * instead of several confusing ones.
 */

/** Number of tracks in the seeded playlist */
export const TRACK_COUNT = 3;

/** Length of every generated track, in seconds (tests/make-fixtures.mjs) */
export const TRACK_SECONDS = 3;

/** As stored in the track meta and rendered in the playlist */
export const TRACK_DURATION_LABEL = "0:03";

/** Frequency of each generated sine tone, in Hz - they are pure tones, which
 *  makes the visualizer's frequency mapping testable */
export const TRACK_FREQUENCIES = [440, 554, 659];

/** Every track carries a cover, so the carousel renders a slide per track */
export const TRACKS_HAVE_COVERS = true;

/**
 * A seek step that is safely inside a track.
 *
 * Anything at or beyond TRACK_SECONDS is clipped by the duration, or ends the
 * track - which is why a five-second step proved nothing.
 */
export const SAFE_SEEK_SECONDS = 1;
