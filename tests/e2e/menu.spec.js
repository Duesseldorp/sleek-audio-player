import { expect, test } from "@playwright/test";
import { Player } from "./helpers/player.js";

test.describe("More menu", () => {
  test("opens and shows its items", async ({ page }) => {
    await page.goto("/player-page/");
    const player = new Player(page);

    await expect(player.moreMenu).toBeHidden();
    await player.openMoreMenu();

    await expect(player.moreWrapper).toHaveClass(/active/);
    const { count } = await player.menuItemGaps();
    expect(count).toBeGreaterThan(3);
  });

  // Regression test for 2.5.0: page builders run wpautop over the rendered
  // shortcode output, which injected <br>/<p> between the menu items and blew
  // the menu apart. /player-page/ deliberately wraps the shortcode in prose
  // with blank lines so wpautop has something to do.
  test("items are not spaced apart by injected markup", async ({ page }) => {
    await page.goto("/player-page/");
    const player = new Player(page);
    await player.openMoreMenu();

    const { gaps, menuHeight } = await player.menuItemGaps();

    // Real gaps are 0-3px (dividers). Injected <br> line boxes were ~24px.
    for (const gap of gaps) {
      expect(gap).toBeLessThan(12);
    }
    expect(menuHeight).toBeLessThan(400);
  });

  test("no foreign br/p elements inside the player markup", async ({ page }) => {
    await page.goto("/player-page/");
    const player = new Player(page);

    // render_player() strips inter-tag whitespace, so wpautop cannot inject
    // anything. The player template itself never emits <br> or <p>.
    const foreign = await player.foreignBlockElements();
    expect(foreign.br).toBe(0);
    expect(foreign.p).toBe(0);
  });

  // The menu is position:fixed and closes on scroll so it cannot drift away
  // from its button. Momentum scrolling on touch devices emits tiny scroll
  // events right after the opening tap, so there is a small tolerance.
  test("survives a tiny scroll but closes on a real one", async ({ page }) => {
    await page.goto("/player-page/");
    const player = new Player(page);
    await player.openMoreMenu();

    // Within the threshold: stays open.
    // This is the one legitimate fixed wait in the suite - the assertion is
    // that something does NOT happen, and there is no condition to wait for.
    await page.mouse.wheel(0, 3);
    await page.waitForTimeout(300);
    await expect(player.moreWrapper).toHaveClass(/active/);

    // Clearly beyond it: closes
    await page.mouse.wheel(0, 200);
    await expect(player.moreWrapper).not.toHaveClass(/active/);
  });

  // Guards the one real failure mode of the i18n rework: a missing or
  // misspelled key in sapText() must never reach the visitor as "undefined"
  // or an empty label. sapText() falls back to the English original instead.
  test("no menu label is empty or renders undefined", async ({ page }) => {
    await page.goto("/player-page/");
    const player = new Player(page);
    await player.openMoreMenu();

    const labels = await player.moreMenu.evaluate((menu) =>
      Array.from(menu.querySelectorAll(".sap-more-item"))
        .filter((el) => el.offsetHeight > 0)
        .map((el) => el.textContent.trim())
    );

    expect(labels.length).toBeGreaterThan(3);
    for (const label of labels) {
      expect(label, "empty menu label").not.toBe("");
      expect(label, `label contains undefined: ${label}`).not.toMatch(/undefined/i);
    }

    // Cycling the stateful labels must keep them intact too
    for (const selector of [".sap-repeat", ".sap-speed"]) {
      await player.root.locator(`.sap-more-item${selector}`).click();
      const text = (await player.root.locator(`.sap-more-item${selector}`).textContent())?.trim();
      expect(text).toBeTruthy();
      expect(text).not.toMatch(/undefined|NaN/i);
    }
  });

  test("streaming links only appear for tracks that have them", async ({ page }) => {
    await page.goto("/player-page/");
    const player = new Player(page);
    await player.openMoreMenu();

    // Track one has Spotify but no SoundCloud (see tests/seed.php)
    await expect(player.moreMenu.locator(".sap-stream-spotify")).toBeVisible();
    await expect(player.moreMenu.locator(".sap-stream-apple")).toBeHidden();
  });
});
