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

  test("streaming links only appear for tracks that have them", async ({ page }) => {
    await page.goto("/player-page/");
    const player = new Player(page);
    await player.openMoreMenu();

    // Track one has Spotify but no SoundCloud (see tests/seed.php)
    await expect(player.moreMenu.locator(".sap-stream-spotify")).toBeVisible();
    await expect(player.moreMenu.locator(".sap-stream-apple")).toBeHidden();
  });
});
