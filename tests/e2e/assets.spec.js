import { expect, test } from "@playwright/test";
import { Player, watchForErrors, watchPluginAssets } from "./helpers/player.js";

test.describe("Asset loading", () => {
  // Regression test for 2.5.0: before conditional loading, ~200 KB of plugin
  // JS/CSS was requested on every page of the site.
  test("a page without a player loads zero plugin assets", async ({ page }) => {
    const assets = watchPluginAssets(page);
    const errors = watchForErrors(page);

    await page.goto("/no-player-page/");
    await page.waitForLoadState("networkidle");

    expect(assets).toEqual([]);
    expect(errors).toEqual([]);
    await expect(page.locator(".sap-player")).toHaveCount(0);
  });

  test("a page with a player loads exactly one script and one stylesheet", async ({ page }) => {
    const assets = watchPluginAssets(page);

    await page.goto("/player-page/");
    await page.waitForLoadState("networkidle");

    const scripts = assets.filter((url) => url.includes(".js"));
    const styles = assets.filter((url) => url.includes(".css"));

    expect(scripts.length).toBe(1);
    expect(styles.length).toBe(1);
    // SCRIPT_DEBUG is off in .wp-env.json, so the minified build must be served
    expect(scripts[0]).toContain("player.min.js");
    expect(styles[0]).toContain("player.min.css");
  });

  test("mini layout renders and plays", async ({ page }) => {
    const errors = watchForErrors(page);
    await page.goto("/player-mini/");
    const player = new Player(page);

    await expect(player.root).toHaveClass(/sap-mini/);
    await player.play();
    await player.waitUntilPlaying();

    expect(errors).toEqual([]);
  });

  test("playlist page renders its own player and JSON-LD", async ({ page }) => {
    await page.goto("/playlist/e2e-playlist/");
    await expect(page.locator(".sap-player")).toBeVisible();

    const schema = await page
      .locator('script[type="application/ld+json"]')
      .first()
      .textContent();
    expect(schema).toContain("MusicPlaylist");
    expect(schema).toContain("E2E Track One");
  });
});
