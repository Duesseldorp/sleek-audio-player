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

test.describe("Social previews", () => {
  // With an SEO plugin active, a shared link carried two sets of Open Graph
  // tags: the SEO plugin's share image and the player's track cover. On the
  // production site the cover was a 1.3-2 MB PNG and the preview showed no
  // image. tests/mu-plugins/sap-e2e-seo.php plays the SEO plugins.
  test("beside an SEO plugin the player prints no second set of tags", async ({ page }) => {
    await page.goto("/playlist/e2e-playlist/?e2e_seo=yoast&track=2");

    const images = page.locator('meta[property="og:image"]');
    await expect(images).toHaveCount(1);
    await expect(images).toHaveAttribute("content", "https://example.org/seo-share.jpg");
    await expect(page.locator('meta[property="og:title"]')).toHaveCount(1);
    await expect(page.locator('meta[name="twitter:image"]')).toHaveCount(0);
  });

  // 2.14.0 stepped aside entirely, so a shared song showed the page's preview.
  test("All in One SEO shows the shared track, not the page", async ({ page }) => {
    await page.goto("/player-page/?e2e_seo=aioseo");
    const playlistId = await new Player(page).root.getAttribute("data-playlist-id");

    await page.goto(`/player-page/?e2e_seo=aioseo&playlist=${playlistId}&track=2&play=1`);

    const image = page.locator('meta[property="og:image"]');
    await expect(image).toHaveCount(1);
    await expect(image).toHaveAttribute("content", /\/sap-test\/cover\.png$/);
    await expect(page.locator('meta[property="og:title"]')).toHaveAttribute(
      "content",
      "E2E Track Two - Test Artist"
    );
    await expect(page.locator('meta[property="og:url"]')).toHaveAttribute("content", /track=2/);
    await expect(page.locator('meta[name="twitter:image"]')).toHaveAttribute(
      "content",
      /\/sap-test\/cover\.png$/
    );
    // The fixture cover is a bare URL, not an attachment: its size is unknown
    // and must not keep the SEO plugin's 1200 px claim
    await expect(page.locator('meta[property="og:image:width"]')).toHaveCount(0);
  });

  test("All in One SEO keeps its own preview when no track is shared", async ({ page }) => {
    await page.goto("/player-page/?e2e_seo=aioseo");

    await expect(page.locator('meta[property="og:image"]')).toHaveAttribute(
      "content",
      "https://example.org/seo-share.jpg"
    );
    await expect(page.locator('meta[property="og:title"]')).toHaveAttribute(
      "content",
      "SEO plugin title"
    );
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
