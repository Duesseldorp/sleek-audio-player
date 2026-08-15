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

  // Seeking used to be a guess: the bar gave no clue where a click would land.
  test("hovering the waveform shows the time under the cursor", async ({ page }) => {
    await page.goto("/player-page/");
    const player = new Player(page);
    const waveform = player.root.locator(".sap-waveform-container");
    const label = player.root.locator(".sap-waveform-hover-time");

    await expect(label).toBeHidden();

    // The label needs a known duration, which arrives with the metadata
    await player.play();
    await player.waitUntilPlaying();

    const box = await waveform.boundingBox();
    await page.mouse.move(box.x + box.width * 0.5, box.y + box.height / 2);

    await expect(label).toBeVisible();
    await expect(label).toHaveText(/^\d+:\d{2}$/);

    // Moving away hides it again
    await page.mouse.move(box.x + box.width * 0.5, box.y - 60);
    await expect(label).toBeHidden();
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

test.describe("Keyboard operation", () => {
  // role="slider" promises that the element can be focused and adjusted.
  // Before 2.9.0 neither slider had a tabindex, so the promise was empty.
  // The fixtures are three seconds long (tests/make-fixtures.mjs), so this
  // deliberately avoids End and fixed step sizes - both would be swallowed by
  // the track length rather than telling us anything about the keyboard.
  test("the progress slider can be focused and operated", async ({ page }) => {
    await page.goto("/player-page/");
    const player = new Player(page);
    const slider = player.root.locator(".sap-waveform-container");

    await player.play();
    await player.waitUntilPlaying();
    await player.audio.evaluate((el) => {
      el.pause();
      el.currentTime = 0;
    });

    await slider.focus();
    await expect(slider).toBeFocused();
    await expect(slider).toHaveAttribute("aria-valuenow", "0");

    await page.keyboard.press("ArrowRight");
    await expect
      .poll(async () => Number(await slider.getAttribute("aria-valuenow")), { timeout: 5_000 })
      .toBeGreaterThan(0);

    await page.keyboard.press("Home");
    await expect(slider).toHaveAttribute("aria-valuenow", "0");
    expect(await player.audio.evaluate((el) => el.currentTime)).toBe(0);
  });

  // The panel is visibility:hidden until the wrapper has hover or focus, which
  // also keeps it out of the tab order - so it is only reachable once the
  // volume button has focus.
  test("the volume slider becomes reachable via the volume button", async ({ page }) => {
    await page.goto("/player-page/");
    const player = new Player(page);
    const slider = player.root.locator(".sap-volume-slider");

    await expect(slider).toBeHidden();

    await player.root.locator(".sap-volume-btn").focus();
    await expect(slider).toBeVisible();

    await slider.focus();
    await page.keyboard.press("Home");
    await expect(slider).toHaveAttribute("aria-valuenow", "0");

    await page.keyboard.press("End");
    await expect(slider).toHaveAttribute("aria-valuenow", "100");
  });

  // The document-level shortcuts claim the arrow keys too. The volume slider
  // makes the difference measurable where the progress slider cannot: its own
  // step is 5 %, the global one 10 %. From the default 70 a single press lands
  // on 65 if only the slider acted, on 60 if both did.
  test("a focused slider does not also trigger the global shortcut", async ({ page }) => {
    await page.goto("/player-page/");
    const player = new Player(page);
    const slider = player.root.locator(".sap-volume-slider");

    // The panel fades in over 0.2s and cannot take focus while it is still
    // visibility:hidden - focus() then fails silently, the key reaches the
    // document instead and the global 10 % step lands on 60. That is what made
    // this test flaky rather than any double handling.
    await player.root.locator(".sap-volume-btn").focus();
    await expect(slider).toBeVisible();
    await slider.focus();
    await expect(slider).toBeFocused();
    await expect(slider).toHaveAttribute("aria-valuenow", "70");

    await page.keyboard.press("ArrowDown");
    await expect(slider, "60 means the global handler fired as well").toHaveAttribute(
      "aria-valuenow",
      "65"
    );
  });

  // Regression: the global shortcut used to set audio.volume directly, past
  // the player's own setVolume(). The audio got quieter while the visible
  // slider and aria-valuenow stayed where they were.
  test("the global volume shortcut keeps the slider in step", async ({ page }) => {
    await page.goto("/player-page/");
    const player = new Player(page);
    const slider = player.root.locator(".sap-volume-slider");

    await player.play();
    await player.waitUntilPlaying();

    const before = Number(await slider.getAttribute("aria-valuenow"));
    await page.keyboard.press("ArrowDown");

    await expect
      .poll(async () => Number(await slider.getAttribute("aria-valuenow")), { timeout: 5_000 })
      .toBeLessThan(before);

    // and the audio element agrees with what the slider claims
    const reported = Number(await slider.getAttribute("aria-valuenow"));
    const actual = await player.audio.evaluate((el) => Math.round(el.volume * 100));
    expect(Math.abs(reported - actual), `slider says ${reported}, audio is ${actual}`).toBeLessThan(2);
  });
});

// Counts pixels the visualizer has actually drawn on its canvas
async function paintedPixels(player) {
  return player.root.locator(".sap-visualizer").evaluate((canvas) => {
    if (!canvas.width || !canvas.height) return 0;
    const data = canvas.getContext("2d").getImageData(0, 0, canvas.width, canvas.height).data;
    let count = 0;
    for (let i = 3; i < data.length; i += 4) if (data[i] > 0) count++;
    return count;
  });
}

// Confirms the emulation is actually in effect before a test relies on it.
// A silently inactive media query made two of these tests fail against
// perfectly good CSS; this names that cause immediately instead of leaving a
// wrong animation-name to be interpreted.
async function expectReducedMotionActive(page) {
  expect(
    await page.evaluate(() => matchMedia("(prefers-reduced-motion: reduce)").matches),
    "reduced-motion emulation is not in effect"
  ).toBe(true);
}

test.describe("Reduced motion", () => {
  // Ken Burns runs for as long as the music plays and covers the whole cover
  // image - the kind of motion the setting exists for.
  test("the continuous cover animation stops", async ({ page }) => {
    await page.emulateMedia({ reducedMotion: "reduce" });
    await page.goto("/player-page/");
    await expectReducedMotionActive(page);
    const player = new Player(page);

    await player.play();
    await player.waitUntilPlaying();

    await expect(player.root.locator(".sap-cover-slide.active img")).toHaveCSS(
      "animation-name",
      "none"
    );
  });

  test("the visualizer stays off", async ({ page }) => {
    // Before goto: the player reads the setting once while initialising
    await page.emulateMedia({ reducedMotion: "reduce" });
    await page.goto("/player-page/");
    const player = new Player(page);
    await expectReducedMotionActive(page);

    await player.play();
    await player.waitUntilPlaying();

    // A legitimate fixed wait: the assertion is that something does NOT
    // happen, so there is no condition to wait for. Long enough for many
    // animation frames.
    await page.waitForTimeout(600);

    expect(await paintedPixels(player), "the visualizer drew despite reduced motion").toBe(0);
  });

  // The setting is a default, not a lock: someone who deliberately switches a
  // visualizer on should keep it.
  test("an explicit choice still wins over the system setting", async ({ page }) => {
    await page.emulateMedia({ reducedMotion: "reduce" });
    await page.addInitScript(() => {
      localStorage.setItem("sap_visualizer_type", "bars");
    });
    await page.goto("/player-page/");
    const player = new Player(page);

    await player.play();
    await player.waitUntilPlaying();

    // Waits for the condition, not for a duration
    await expect
      .poll(async () => paintedPixels(player), { timeout: 10_000 })
      .toBeGreaterThan(0);
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
