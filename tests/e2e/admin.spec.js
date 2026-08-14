import { expect, test } from "@playwright/test";
import { seedIds } from "./helpers/player.js";

/**
 * The regression this file exists for: in 2.5.0 saving a playlist silently
 * destroyed every track's duration, because the field was read in four places
 * but present in neither the meta box form nor save_meta's whitelist. A save
 * round-trip is the only thing that catches that class of bug - rendering
 * seeded data does not, because seeded data never passes through save_meta.
 */

const ADMIN = { user: "admin", pass: "password" }; // wp-env defaults

async function login(page) {
  await page.goto("/wp-login.php");
  await page.fill("#user_login", ADMIN.user);
  await page.fill("#user_pass", ADMIN.pass);
  await page.click("#wp-submit");
  await expect(page.locator("#wpadminbar")).toBeVisible();
}

test.describe("Playlist admin", () => {
  test("saving a playlist without changes preserves all track fields", async ({ page }) => {
    const ids = seedIds();

    // What the frontend shows before the save
    await page.goto("/player-page/");
    const before = await page.evaluate(() => {
      const el = document.querySelector(".sap-player");
      return JSON.parse(el.dataset.playlist).map((t) => ({
        title: t.title,
        duration: t.duration,
        spotify: t.spotify,
        soundcloud: t.soundcloud,
        downloadable: !!t.downloadable,
      }));
    });
    expect(before).toHaveLength(3);
    expect(before[0].duration).toBeTruthy();
    expect(before[0].spotify).toBeTruthy();
    expect(before[1].soundcloud).toBeTruthy();
    expect(before[1].downloadable).toBe(true);

    // Open the playlist in the editor and press Update without changing anything
    await login(page);
    await page.goto(`/wp-admin/post.php?post=${ids.playlist}&action=edit`);
    await expect(page.locator(".sap-track-row").first()).toBeVisible();

    const publish = page.locator("#publish");
    await expect(publish).toBeVisible();
    await publish.click();
    await expect(page.locator("#message, .notice-success")).toBeVisible();

    // Everything must have survived the round trip
    await page.goto("/player-page/");
    const after = await page.evaluate(() => {
      const el = document.querySelector(".sap-player");
      return JSON.parse(el.dataset.playlist).map((t) => ({
        title: t.title,
        duration: t.duration,
        spotify: t.spotify,
        soundcloud: t.soundcloud,
        downloadable: !!t.downloadable,
      }));
    });

    expect(after).toEqual(before);
  });
});
