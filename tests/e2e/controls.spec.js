import { expect, test } from "@playwright/test";
import { Player, watchForErrors } from "./helpers/player.js";

test.describe("Repeat modes", () => {
  test("repeat One restarts the same track instead of advancing", async ({ page }) => {
    await page.goto("/player-page/");
    const player = new Player(page);

    await player.play();
    await player.waitUntilPlaying();
    const first = await player.state();

    await player.setRepeat("One");
    await player.seekToEnd();

    // Same file, playing again from the start
    await page.waitForFunction(
      () => {
        const el = document.querySelector(".sap-player audio.sap-audio");
        return el && !el.paused && el.currentTime < 1.5;
      },
      undefined,
      { timeout: 15_000 }
    );
    const after = await player.state();
    expect(after.file).toBe(first.file);
    expect(after.paused).toBe(false);
  });

  test("repeat All wraps from the last track back to the first", async ({ page }) => {
    await page.goto("/player-page/");
    const player = new Player(page);

    await player.setRepeat("All");
    await player.selectTrack(2); // last of three
    const last = await player.state();

    await player.seekToEnd();
    await page.waitForFunction(
      ([previous]) => {
        const el = document.querySelector(".sap-player audio.sap-audio");
        if (!el) return false;
        const file = (el.currentSrc || el.src).split("/").pop();
        return file !== previous && !el.paused;
      },
      [last.file],
      { timeout: 20_000 }
    );

    await expect(player.nowPlaying).toHaveText(/E2E Track One/i);
  });

  test("without repeat, playback stops after the last track", async ({ page }) => {
    await page.goto("/player-page/");
    const player = new Player(page);

    await player.selectTrack(2); // last track, repeat is Off by default
    await player.seekToEnd();

    await page.waitForFunction(
      () => {
        const el = document.querySelector(".sap-player audio.sap-audio");
        return el && el.paused;
      },
      undefined,
      { timeout: 15_000 }
    );
    await expect(player.nowPlaying).toHaveText(/E2E Track Three/i);
  });
});

test.describe("Keyboard shortcuts", () => {
  // Handled globally (document-level, by e.code) so they work without focus
  test("N advances, Space pauses", async ({ page }) => {
    await page.goto("/player-page/");
    const player = new Player(page);

    await player.play();
    await player.waitUntilPlaying();
    const first = await player.state();

    await page.keyboard.press("KeyN");
    await player.waitUntilPlaying();
    const afterNext = await player.state();
    expect(afterNext.file).not.toBe(first.file);

    await page.keyboard.press("Space");
    await page.waitForFunction(
      () => document.querySelector(".sap-player audio.sap-audio")?.paused === true,
      undefined,
      { timeout: 10_000 }
    );
  });

  test("S toggles shuffle", async ({ page }) => {
    await page.goto("/player-page/");
    const player = new Player(page);

    await player.play();
    await player.waitUntilPlaying();

    await page.keyboard.press("KeyS");
    await expect(player.root.locator(".sap-menu-shuffle")).toHaveClass(/active/);
  });
});

test.describe("Multiple players on one page", () => {
  test("starting the second player pauses the first", async ({ page }) => {
    const errors = watchForErrors(page);
    await page.goto("/player-two/");

    const first = new Player(page, ".sap-player", 0);
    const second = new Player(page, ".sap-player", 1);
    await expect(page.locator(".sap-player")).toHaveCount(2);

    await first.play();
    await first.waitUntilPlaying();

    await second.play();
    await second.waitUntilPlaying();

    await page.waitForFunction(
      () => document.querySelectorAll(".sap-player audio.sap-audio")[0].paused === true,
      undefined,
      { timeout: 10_000 }
    );
    expect((await second.state()).paused).toBe(false);
    expect(errors).toEqual([]);
  });
});

test.describe("Accessible labels", () => {
  // Since 2.6.1 every label and tooltip comes from a translation call. An
  // empty or missing one leaves screen-reader users with an unnamed button,
  // and that is the only way the markup change could regress unnoticed.
  test("every control carries a non-empty accessible name", async ({ page }) => {
    await page.goto("/player-page/");
    const player = new Player(page);
    await expect(player.root).toBeVisible();

    const named = await player.root.evaluate((root) => {
      const result = {};
      const record = (key, el, attrs) => {
        result[key] = el ? attrs.map((a) => el.getAttribute(a)) : null;
      };
      record("region", root, ["aria-label"]);
      for (const sel of [
        ".sap-progress-section",
        ".sap-waveform-container",
        ".sap-controls",
        ".sap-playlist",
        ".sap-volume-slider",
      ]) {
        record(sel, root.querySelector(sel), ["aria-label"]);
      }
      for (const sel of [".sap-prev", ".sap-play", ".sap-next", ".sap-more-btn", ".sap-volume-btn"]) {
        record(sel, root.querySelector(sel), ["aria-label", "title"]);
      }
      return result;
    });

    for (const [element, values] of Object.entries(named)) {
      expect(values, `${element} is missing from the markup`).not.toBeNull();
      for (const value of values) {
        expect(value, `${element} has an empty accessible name`).toBeTruthy();
      }
    }
  });

  // Both sliders carry role="slider", which promises a machine-readable value.
  // Until 2.7.1 nothing ever wrote aria-valuenow, so assistive technology was
  // told "0" for the whole track and "70" no matter the volume.
  test("the progress slider reports its real position", async ({ page }) => {
    await page.goto("/player-page/");
    const player = new Player(page);
    const slider = player.root.locator(".sap-waveform-container");

    await expect(slider).toHaveAttribute("aria-valuenow", "0");

    await player.play();
    await player.waitUntilPlaying();

    // Wait for the value to leave zero rather than for a fixed duration
    await expect
      .poll(async () => Number(await slider.getAttribute("aria-valuenow")), { timeout: 15_000 })
      .toBeGreaterThan(0);

    // aria-valuetext gives the time, which is far more useful than a percentage
    const valueText = await slider.getAttribute("aria-valuetext");
    expect(valueText, `aria-valuetext was "${valueText}"`).toMatch(/\d+:\d{2}.+\d+:\d{2}/);
  });

  test("the volume slider reports its real value", async ({ page }) => {
    await page.goto("/player-page/");
    const player = new Player(page);
    const slider = player.root.locator(".sap-volume-slider");

    const before = Number(await slider.getAttribute("aria-valuenow"));
    expect(before).toBeGreaterThan(0);

    await player.root.locator(".sap-volume-btn").click(); // mutes
    await expect
      .poll(async () => Number(await slider.getAttribute("aria-valuenow")), { timeout: 5_000 })
      .toBe(0);
  });

  // Screen reader users get no visual cue that the track changed.
  test("the now-playing element is a live region", async ({ page }) => {
    await page.goto("/player-page/");
    const player = new Player(page);

    await expect(player.nowPlaying).toHaveAttribute("aria-live", "polite");

    await player.play();
    await player.waitUntilPlaying();
    await expect(player.nowPlaying).toHaveText(/E2E Track One/i);
  });

  // The track count is the plugin's only plural string; po2mo.py used to drop
  // plural entries on the floor, so the compiled .mo silently lost them.
  test("the track count uses the plural form", async ({ page }) => {
    await page.goto("/player-page/");
    const player = new Player(page);

    await expect(player.root.locator(".sap-meta span").first()).toHaveText(/^3 Tracks$/);
  });
});

test.describe("Compatibility", () => {
  // The legacy alias is a documented promise in readme.txt and DEVELOPMENT.md
  test("the legacy [simple_player] shortcode still renders a working player", async ({ page }) => {
    const errors = watchForErrors(page);
    await page.goto("/player-legacy/");
    const player = new Player(page);

    await expect(player.root).toBeVisible();
    await expect(player.trackList).toHaveCount(3);
    await player.play();
    await player.waitUntilPlaying();

    expect(errors).toEqual([]);
  });

  test("a shared link opens the requested track", async ({ page }) => {
    await page.goto("/playlist/e2e-playlist/?track=2&play=1");
    const player = new Player(page);

    await expect(player.root).toBeVisible();
    await expect(player.nowPlaying).toHaveText(/E2E Track Two/i, { timeout: 15_000 });
  });
});
