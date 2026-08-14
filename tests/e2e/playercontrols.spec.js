import { expect, test } from "@playwright/test";
import { Player } from "./helpers/player.js";

/**
 * Player controls that are documented features but were previously unverified.
 * All of these assert numbers or classes - nothing depends on animation or
 * appearance, so they stay deterministic.
 */

test.describe("Playback speed", () => {
  test("cycling speed changes the actual playbackRate", async ({ page }) => {
    await page.goto("/player-page/");
    const player = new Player(page);

    await player.play();
    await player.waitUntilPlaying();
    expect(await player.audio.evaluate((el) => el.playbackRate)).toBe(1);

    await player.openMoreMenu();
    await player.root.locator(".sap-more-item.sap-speed").click();

    // playbackSpeeds in player.js: [1, 1.25, 1.5, 2]
    await page.waitForFunction(
      () => document.querySelector(".sap-player audio.sap-audio")?.playbackRate === 1.25,
      undefined,
      { timeout: 5000 }
    );
    expect(await player.audio.evaluate((el) => el.playbackRate)).toBe(1.25);
  });
});

test.describe("Volume", () => {
  test("the volume button mutes and unmutes", async ({ page }) => {
    await page.goto("/player-page/");
    const player = new Player(page);

    await player.play();
    await player.waitUntilPlaying();
    const before = await player.audio.evaluate((el) => el.volume);
    expect(before).toBeGreaterThan(0);

    await player.root.locator(".sap-volume-btn").click();
    await page.waitForFunction(
      () => document.querySelector(".sap-player audio.sap-audio")?.volume === 0,
      undefined,
      { timeout: 5000 }
    );

    await player.root.locator(".sap-volume-btn").click();
    await page.waitForFunction(
      () => (document.querySelector(".sap-player audio.sap-audio")?.volume ?? 0) > 0,
      undefined,
      { timeout: 5000 }
    );
  });
});

test.describe("Seeking", () => {
  test("clicking the waveform jumps to that position", async ({ page }) => {
    await page.goto("/player-page/");
    const player = new Player(page);

    await player.play();
    await player.waitUntilPlaying();
    const duration = await player.audio.evaluate((el) => el.duration);
    expect(duration).toBeGreaterThan(1);

    const bar = player.root.locator(".sap-waveform-container");
    const box = await bar.boundingBox();
    await page.mouse.click(box.x + box.width * 0.5, box.y + box.height / 2);

    await page.waitForFunction(
      (expected) => {
        const el = document.querySelector(".sap-player audio.sap-audio");
        return el && Math.abs(el.currentTime - expected) < 0.5;
      },
      duration * 0.5,
      { timeout: 5000 }
    );
  });
});

test.describe("Sleep timer", () => {
  // Touches the track-transition logic, which is the historically fragile part
  test("'End of Track' stops instead of advancing", async ({ page }) => {
    await page.goto("/player-page/");
    const player = new Player(page);

    await player.openMoreMenu();
    await player.root.locator(".sap-more-item.sap-sleep-timer").click();
    const endOfTrack = player.root.locator('.sap-sleep-option[data-end-of-track="true"]');
    await expect(endOfTrack).toBeVisible();
    await endOfTrack.click();
    await player.closeMoreMenu();

    await player.play();
    await player.waitUntilPlaying();
    const before = (await player.state()).file;

    await player.seekToEnd();
    await page.waitForFunction(
      () => document.querySelector(".sap-player audio.sap-audio")?.paused === true,
      undefined,
      { timeout: 15_000 }
    );

    // Same track, stopped - not advanced to the next one
    expect((await player.state()).file).toBe(before);
    await expect(player.nowPlaying).toHaveText(/E2E Track One/i);
  });
});

test.describe("Download button", () => {
  test("appears only for tracks marked downloadable", async ({ page }) => {
    await page.goto("/player-page/");
    const player = new Player(page);
    const download = player.root.locator(".sap-more-item.sap-download");

    // Track 1 is not downloadable (see tests/seed.php)
    await player.openMoreMenu();
    await expect(download).not.toHaveClass(/visible/);
    await player.closeMoreMenu();

    // Track 2 is
    await player.selectTrack(1);
    await player.openMoreMenu();
    await expect(download).toHaveClass(/visible/);
  });
});
