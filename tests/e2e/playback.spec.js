import { expect, test } from "@playwright/test";
import { Player, watchForErrors } from "./helpers/player.js";

test.describe("Playback", () => {
  test("player renders with all tracks", async ({ page }) => {
    await page.goto("/player-page/");
    const player = new Player(page);

    await expect(player.root).toBeVisible();
    await expect(player.playButton).toBeVisible();
    await expect(player.trackList).toHaveCount(3);
  });

  test("clicking play starts playback", async ({ page }) => {
    const errors = watchForErrors(page);
    await page.goto("/player-page/");
    const player = new Player(page);

    await player.play();
    await player.waitUntilPlaying();

    const state = await player.state();
    expect(state.paused).toBe(false);
    expect(state.currentTime).toBeGreaterThan(0);
    expect(state.error).toBeNull();
    expect(errors).toEqual([]);
  });

  // The regression test this suite exists for: playback stopping between
  // tracks was shipped as "fixed" five times (2.1.3, 2.1.4, 2.3.1, 2.5.0,
  // 2.5.4) because every check was manual.
  test("advances to the next track when one ends", async ({ page }) => {
    await page.goto("/player-page/");
    const player = new Player(page);

    await player.play();
    await player.waitUntilPlaying();

    const first = await player.skipToEndAndAwaitNextTrack();
    expect(first.to).not.toBe(first.from);
    await expect(player.nowPlaying).toHaveText(/E2E Track Two/i);

    // A second transition - the reported failure happened after ~2 songs
    const second = await player.skipToEndAndAwaitNextTrack();
    expect(second.to).not.toBe(second.from);
    await expect(player.nowPlaying).toHaveText(/E2E Track Three/i);

    const state = await player.state();
    expect(state.paused).toBe(false);
  });

  test("next and previous buttons switch tracks", async ({ page }) => {
    await page.goto("/player-page/");
    const player = new Player(page);

    await player.play();
    await player.waitUntilPlaying();
    const first = await player.state();

    await player.nextButton.click();
    await player.waitUntilPlaying();
    const second = await player.state();
    expect(second.file).not.toBe(first.file);

    await player.prevButton.click();
    await player.waitUntilPlaying();
    const third = await player.state();
    expect(third.file).toBe(first.file);
  });

  test("track durations from the playlist are rendered", async ({ page }) => {
    // Guards the 2.5.0 data-loss bug from the frontend side: durations were
    // read everywhere but silently dropped when saving a playlist.
    await page.goto("/player-page/");
    const durations = page.locator(".sap-player .sap-track-duration");

    await expect(durations).toHaveCount(3);
    await expect(durations.first()).toHaveText(/\d+:\d{2}/);
  });
});
