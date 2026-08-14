import { expect, test } from "@playwright/test";
import { Player } from "./helpers/player.js";

/**
 * The ways the plugin is embedded and consumed from outside: the Gutenberg
 * block, the oEmbed provider and the embed-code generator. All three are
 * documented features that had no coverage at all.
 */

test.describe("Gutenberg block", () => {
  test("renders a working player", async ({ page }) => {
    await page.goto("/player-block/");
    const player = new Player(page);

    await expect(player.root).toBeVisible();
    await expect(player.trackList).toHaveCount(3);

    await player.play();
    await player.waitUntilPlaying();
  });
});

test.describe("oEmbed provider", () => {
  test("returns a valid oEmbed document for a playlist URL", async ({ page, baseURL }) => {
    const playlistUrl = `${baseURL}/playlist/e2e-playlist/`;
    const response = await page.request.get(
      `/wp-json/sleek-audio-player/v1/oembed?url=${encodeURIComponent(playlistUrl)}`
    );
    expect(response.ok()).toBeTruthy();

    const body = await response.json();
    expect(body.version).toBe("1.0");
    expect(body.type).toBe("rich");
    expect(body.provider_name).toBe("Sleek Audio Player");
    expect(body.title).toBe("E2E Playlist");
    expect(body.html).toContain("<iframe");
    expect(body.html).toContain("embed=1");
    expect(body.width).toBeGreaterThan(0);
    expect(body.height).toBeGreaterThan(0);
  });

  test("rejects a URL that is not a playlist", async ({ page, baseURL }) => {
    const response = await page.request.get(
      `/wp-json/sleek-audio-player/v1/oembed?url=${encodeURIComponent(baseURL + "/no-player-page/")}`
    );
    expect(response.status()).toBe(404);
  });
});

test.describe("Embed code generator", () => {
  test("produces an iframe and switches layout", async ({ page }) => {
    await page.goto("/player-page/");
    const player = new Player(page);

    await player.openMoreMenu();
    await player.root.locator(".sap-more-item.sap-embed-btn").click();

    const modal = player.root.locator(".sap-embed-modal");
    await expect(modal).toBeVisible();

    const code = player.root.locator("textarea.sap-embed-code");
    const wide = await code.inputValue();
    expect(wide).toContain("<iframe");
    expect(wide).toContain("embed=1");
    expect(wide).toContain('height="280"'); // wide layout default

    await player.root.locator('.sap-embed-layout[data-layout="mini"]').click();
    await page.waitForFunction(
      () => document.querySelector("textarea.sap-embed-code")?.value.includes('height="150"'),
      undefined,
      { timeout: 5000 }
    );

    const mini = await code.inputValue();
    expect(mini).toContain("layout=mini");
  });
});

test.describe("Embed view", () => {
  test("?embed=1 renders a standalone player", async ({ page }) => {
    await page.goto("/playlist/e2e-playlist/?embed=1");
    const player = new Player(page);

    await expect(player.root).toBeVisible();
    await player.play();
    await player.waitUntilPlaying();
  });
});
