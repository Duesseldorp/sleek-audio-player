import { defineConfig, devices } from "@playwright/test";

/**
 * Playwright configuration for the Sleek Audio Player end-to-end tests.
 *
 * The tests run against a WordPress instance started by @wordpress/env
 * (see .wp-env.json). Start everything with:  npm test
 */
export default defineConfig({
  testDir: "./tests/e2e",
  // Audio playback needs a moment; keep individual assertions patient but
  // fail fast enough that a hung test doesn't stall CI.
  timeout: 60_000,
  expect: { timeout: 10_000 },

  fullyParallel: false, // one WordPress instance, keep state predictable
  forbidOnly: !!process.env.CI,
  retries: process.env.CI ? 1 : 0,
  workers: 1,

  reporter: process.env.CI ? [["github"], ["html", { open: "never" }]] : [["list"]],

  use: {
    baseURL: process.env.WP_BASE_URL || "http://localhost:8888",
    trace: "on-first-retry",
    video: "retain-on-failure",
    screenshot: "only-on-failure",
  },

  projects: [
    {
      name: "chromium",
      use: {
        ...devices["Desktop Chrome"],
        launchOptions: {
          args: [
            // Let media start without a gesture so playback tests are not
            // flaky. Real autoplay-policy behaviour is device-specific and
            // cannot be verified in CI - that stays a manual check.
            "--autoplay-policy=no-user-gesture-required",
          ],
        },
      },
    },

    // Add more browsers when needed - the tests themselves are engine-agnostic:
    // { name: "firefox", use: { ...devices["Desktop Firefox"] } },
    // { name: "webkit",  use: { ...devices["Desktop Safari"] } },
    // { name: "mobile",  use: { ...devices["Pixel 7"] } },
  ],
});
