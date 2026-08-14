/**
 * Page object for the Sleek Audio Player.
 *
 * ALL selectors and player interactions live here. When the markup changes,
 * this file is the only one that needs updating - the spec files stay
 * readable and stable. Add new interactions here rather than reaching into
 * the DOM from a test.
 */
export class Player {
  /** @param {import('@playwright/test').Page} page */
  constructor(page, root = ".sap-player") {
    this.page = page;
    this.root = page.locator(root).first();
    this.audio = this.root.locator("audio.sap-audio");
    this.playButton = this.root.locator(".sap-play");
    this.nextButton = this.root.locator(".sap-next");
    this.prevButton = this.root.locator(".sap-prev");
    this.moreButton = this.root.locator(".sap-more-btn");
    this.moreMenu = this.root.locator(".sap-more-menu");
    this.moreWrapper = this.root.locator(".sap-more-wrapper");
    this.nowPlaying = this.root.locator(".sap-now-playing");
    this.trackList = this.root.locator(".sap-track");
  }

  /** Reads live state straight from the <audio> element. */
  async state() {
    return this.audio.evaluate((el) => ({
      src: el.currentSrc || el.src,
      file: (el.currentSrc || el.src).split("/").pop(),
      paused: el.paused,
      ended: el.ended,
      currentTime: el.currentTime,
      duration: Number.isFinite(el.duration) ? el.duration : null,
      readyState: el.readyState,
      error: el.error ? el.error.code : null,
    }));
  }

  async play() {
    await this.playButton.click();
  }

  /** Waits until audio is actually progressing, not just "not paused". */
  async waitUntilPlaying(timeout = 15_000) {
    await this.audio.evaluate(
      (el, t) =>
        new Promise((resolve, reject) => {
          const start = el.currentTime;
          const deadline = Date.now() + t;
          const check = () => {
            if (!el.paused && el.currentTime > start) return resolve();
            if (Date.now() > deadline) return reject(new Error("audio never started playing"));
            setTimeout(check, 100);
          };
          check();
        }),
      timeout
    );
  }

  /**
   * Jumps close to the end of the current track to trigger the 'ended'
   * event, then waits for the player to move on to a different file.
   * This is the regression test for the track-transition bugs.
   */
  async skipToEndAndAwaitNextTrack(timeout = 20_000) {
    const before = (await this.state()).file;
    await this.audio.evaluate((el) => {
      if (Number.isFinite(el.duration) && el.duration > 0.5) {
        el.currentTime = el.duration - 0.4;
      }
    });
    await this.page.waitForFunction(
      ([selector, previous]) => {
        const el = document.querySelector(selector);
        if (!el) return false;
        const file = (el.currentSrc || el.src).split("/").pop();
        return file !== previous && !el.paused && el.currentTime > 0;
      },
      [".sap-player audio.sap-audio", before],
      { timeout }
    );
    return { from: before, to: (await this.state()).file };
  }

  async openMoreMenu() {
    await this.moreButton.click();
    await this.page.waitForTimeout(300); // menu transition
  }

  /**
   * Vertical gaps between visible menu items. Foreign <br>/<p> elements
   * injected by wpautop show up here as large gaps - that was the 2.5.0 bug.
   */
  async menuItemGaps() {
    return this.moreMenu.evaluate((menu) => {
      const items = Array.from(menu.querySelectorAll(".sap-more-item")).filter(
        (el) => el.offsetHeight > 0 && getComputedStyle(el).position !== "absolute"
      );
      const gaps = [];
      for (let i = 1; i < items.length; i++) {
        gaps.push(
          Math.round(items[i].getBoundingClientRect().top - items[i - 1].getBoundingClientRect().bottom)
        );
      }
      return { count: items.length, gaps, menuHeight: menu.scrollHeight };
    });
  }

  /** Foreign elements the player template never emits (wpautop defence). */
  async foreignBlockElements() {
    return this.root.evaluate((el) => ({
      br: el.querySelectorAll("br").length,
      p: el.querySelectorAll("p").length,
    }));
  }
}

/**
 * Collects console errors and failed requests for a page.
 * Usage:  const errors = watchForErrors(page);  ... expect(errors).toEqual([])
 */
export function watchForErrors(page) {
  const errors = [];
  // A bare test site has no favicon; that 404 is noise, not a plugin problem.
  const ignore = [/favicon\.ico/i, /apple-touch-icon/i];
  page.on("console", (msg) => {
    if (msg.type() !== "error") return;
    const text = msg.text();
    if (ignore.some((re) => re.test(text)) || ignore.some((re) => re.test(msg.location()?.url || ""))) {
      return;
    }
    errors.push(`console: ${text}`);
  });
  page.on("pageerror", (err) => errors.push(`pageerror: ${err.message}`));
  return errors;
}

/** Records every request URL that belongs to this plugin. */
export function watchPluginAssets(page) {
  const assets = [];
  page.on("request", (req) => {
    const url = req.url();
    if (url.includes("sleek-audio-player") && /\.(js|css)(\?|$)/.test(url)) {
      assets.push(url);
    }
  });
  return assets;
}
