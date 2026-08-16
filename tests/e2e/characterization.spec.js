import { expect, test } from "@playwright/test";
import {
  TRACK_COUNT,
  TRACK_DURATION_LABEL,
  TRACK_SECONDS,
  TRACKS_HAVE_COVERS,
} from "./fixtures.js";

// Pins what the other tests are allowed to assume about the seeded data.
// Without it, changing the fixtures breaks a handful of unrelated tests with
// misleading messages - which is exactly what happened on 2026-08-16, twice.
test.describe("Test data", () => {
  test("the fixtures are what the tests assume", async ({ page }) => {
    await page.goto("/player-page/");
    const player = page.locator(".sap-player").first();

    await expect(player.locator(".sap-track"), "track count changed").toHaveCount(TRACK_COUNT);

    await expect(
      player.locator(".sap-track-duration").first(),
      "track length changed - seek steps in other tests are sized for this"
    ).toHaveText(TRACK_DURATION_LABEL);

    // Measured on a detached element: touching the player's own audio would
    // change the state the rest of the suite runs against
    const url = await player.locator(".sap-track").first().getAttribute("data-url");
    const seconds = await page.evaluate(
      (src) =>
        new Promise((done) => {
          const probe = new Audio();
          probe.preload = "metadata";
          probe.addEventListener("loadedmetadata", () => done(probe.duration), { once: true });
          probe.addEventListener("error", () => done(null), { once: true });
          probe.src = src;
        }),
      url
    );

    expect(Math.round(seconds), "the generated audio is no longer 3s long").toBe(TRACK_SECONDS);

    if (TRACKS_HAVE_COVERS) {
      await expect(
        player.locator(".sap-cover-slide"),
        "covers vanished - the carousel tests have nothing to look at"
      ).toHaveCount(TRACK_COUNT);
    }
  });
});

/**
 * Characterization tests - the safety net for refactoring.
 *
 * These do not describe what the plugin *should* do, they pin down what it
 * *currently* does. Their job is to make a pure code move provably harmless:
 * move the code, run these, and if everything still matches, nothing broke.
 *
 * Deliberately written as explicit assertions rather than snapshot files, so
 * they guard from the very first run and stay readable in review.
 *
 * If one fails after a refactor -> the refactor changed behaviour, fix it.
 * If one fails after an intentional change -> update it in the same commit
 * and say so in the commit message.
 */

test.describe("Characterization: rendered markup", () => {
  test("the player element tree is unchanged", async ({ page }) => {
    await page.goto("/player-page/");
    const player = page.locator(".sap-player").first();

    // Root attributes that the frontend JS depends on
    const attrs = await player.evaluate((el) => ({
      classes: el.className.trim(),
      hasPlaylistData: !!el.dataset.playlist,
      hasPlaylistId: !!el.dataset.playlistId,
      hasDefaultCover: el.dataset.defaultCover !== undefined,
      role: el.getAttribute("role"),
    }));
    expect(attrs.classes).toBe("sap-player");
    expect(attrs.hasPlaylistData).toBe(true);
    expect(attrs.hasPlaylistId).toBe(true);
    expect(attrs.hasDefaultCover).toBe(true);
    expect(attrs.role).toBe("region");

    // Every structural hook the JS and CSS rely on must exist exactly once.
    // These names are taken from the markup in render_player(), not guessed -
    // getting them from anywhere else cost three CI rounds.
    for (const selector of [
      ".sap-cover-carousel",
      ".sap-cover-track",
      ".sap-content",
      ".sap-now-playing",
      ".sap-controls",
      ".sap-prev",
      ".sap-play",
      ".sap-next",
      ".sap-more-wrapper",
      ".sap-more-btn",
      ".sap-more-menu",
      ".sap-volume-wrapper",
      ".sap-volume-btn",
      ".sap-volume-slider",
      ".sap-playlist", // the track list container
      ".sap-progress-bar",
      ".sap-visualizer",
      "audio.sap-audio",
    ]) {
      await expect(player.locator(selector), `missing or duplicated: ${selector}`).toHaveCount(1);
    }

    await expect(player.locator(".sap-track")).toHaveCount(3);
    // wpautop defence: the template emits neither of these
    await expect(player.locator("br")).toHaveCount(0);
    await expect(player.locator("p")).toHaveCount(0);
  });

  test("the playlist data handed to the frontend keeps its exact shape", async ({ page }) => {
    // This is the direct guard for the 2.5.0 data-loss class of bug: if a
    // field is dropped anywhere between save_meta, sleekaudio_validate_track
    // and render_player, the key set below stops matching.
    await page.goto("/player-page/");
    const tracks = await page
      .locator(".sap-player")
      .first()
      .evaluate((el) => JSON.parse(el.dataset.playlist));

    expect(tracks).toHaveLength(3);

    const expectedKeys = [
      "amazon",
      "apple",
      "attachment_id",
      "cover_id",
      "cover_url",
      "downloadable",
      "duration",
      "soundcloud",
      "spotify",
      "title",
      "url",
    ];
    for (const track of tracks) {
      expect(Object.keys(track).sort()).toEqual(expect.arrayContaining(expectedKeys));
    }

    expect(tracks[0].title).toBe("E2E Track One");
    expect(tracks[0].duration).toBe("0:03");
    expect(tracks[0].spotify).toBe("https://open.spotify.com/track/e2e");
    expect(tracks[1].soundcloud).toBe("https://soundcloud.com/e2e");
    expect(tracks[1].downloadable).toBeTruthy();
    expect(tracks[2].title).toBe("E2E Track Three");
  });
});

test.describe("Characterization: SEO output", () => {
  test("JSON-LD keeps its structure and values", async ({ page }) => {
    await page.goto("/playlist/e2e-playlist/");
    const raw = await page.locator('script[type="application/ld+json"]').first().textContent();
    const schema = JSON.parse(raw);

    expect(schema["@context"]).toBe("https://schema.org");
    expect(schema["@type"]).toBe("MusicPlaylist");
    expect(schema.name).toBe("E2E Playlist");
    expect(schema.numTracks).toBe(3);
    expect(schema.duration).toBe("PT9S"); // 3 x 0:03
    expect(schema.track).toHaveLength(3);

    const first = schema.track[0];
    expect(first["@type"]).toBe("MusicRecording");
    expect(first.position).toBe(1);
    expect(first.name).toBe("E2E Track One");
    expect(first.byArtist).toEqual({ "@type": "MusicGroup", name: "Test Artist" });
    expect(first.duration).toBe("PT3S");
    expect(first.sameAs).toContain("https://open.spotify.com/track/e2e");

    // SoundCloud in sameAs was added in 2.5.2 - keep it
    expect(schema.track[1].sameAs).toContain("https://soundcloud.com/e2e");
  });

  test("Open Graph and Twitter tags keep their values", async ({ page }) => {
    await page.goto("/playlist/e2e-playlist/");
    const meta = await page.evaluate(() => {
      const out = {};
      document
        .querySelectorAll('meta[property^="og:"], meta[property^="music:"], meta[name^="twitter:"]')
        .forEach((m) => {
          out[m.getAttribute("property") || m.getAttribute("name")] = m.getAttribute("content");
        });
      return out;
    });

    expect(meta["og:type"]).toBe("music.playlist");
    expect(meta["og:title"]).toBe("E2E Playlist");
    expect(meta["music:song_count"]).toBe("3");
    expect(meta["twitter:card"]).toBe("summary_large_image");
    expect(meta["twitter:title"]).toBe("E2E Playlist");
  });

  test("a shared track link changes the social preview to that track", async ({ page }) => {
    await page.goto("/playlist/e2e-playlist/?track=2");
    const title = await page.evaluate(
      () => document.querySelector('meta[property="og:title"]')?.getAttribute("content")
    );
    expect(title).toContain("E2E Track Two");
  });
});
