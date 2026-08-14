/**
 * Page object for the Sleek Audio Player.
 *
 * ALL selectors and player interactions live here. When the markup changes,
 * this file is the only one that needs updating - the spec files stay
 * readable and stable. Add new interactions here rather than reaching into
 * the DOM from a test.
 */
export class Player {
  /**
   * @param {import('@playwright/test').Page} page
   * @param {number} index which player on the page (for multi-player tests)
   */
  constructor(page, root = ".sap-player", index = 0) {
    this.page = page;
    this.root = page.locator(root).nth(index);
    this.audio = this.root.locator("audio.sap-audio");
    this.playButton = this.root.locator(".sap-play");
    this.nextButton = this.root.locator(".sap-next");
    this.prevButton = this.root.locator(".sap-prev");
    this.moreButton = this.root.locator(".sap-more-btn");
    this.moreMenu = this.root.locator(".sap-more-menu");
    this.moreWrapper = this.root.locator(".sap-more-wrapper");
    this.nowPlaying = this.root.locator(".sap-now-playing");
    this.trackList = this.root.locator(".sap-track");
    this.repeatItem = this.root.locator(".sap-more-item.sap-repeat");
  }

  async closeMoreMenu() {
    await this.page.mouse.click(2, 2); // click outside the menu
    await this.page.waitForTimeout(150);
  }

  /**
   * Cycles the repeat button until it reads the wanted mode.
   * Labels come from player.js: off -> 'Off', playlist -> 'All', track -> 'One'.
   */
  async setRepeat(mode) {
    await this.openMoreMenu();
    for (let i = 0; i < 4; i++) {
      const label = (await this.repeatItem.textContent())?.trim() || "";
      if (label.includes(mode)) break;
      await this.repeatItem.click();
      await this.page.waitForTimeout(150);
    }
    const label = (await this.repeatItem.textContent())?.trim();
    if (!label?.includes(mode)) {
      throw new Error(`Could not set repeat to "${mode}", label is "${label}"`);
    }
    await this.closeMoreMenu();
  }

  /** Clicks the nth entry in the track list (0-based). */
  async selectTrack(index) {
    await this.trackList.nth(index).click();
    await this.waitUntilPlaying();
  }

  /** Seeks close to the end and waits for whatever the player does next. */
  async seekToEnd() {
    await this.audio.evaluate((el) => {
      if (Number.isFinite(el.duration) && el.duration > 0.5) {
        el.currentTime = el.duration - 0.4;
      }
    });
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

  /**
   * Opens the More menu and waits until it is really open, so tests that
   * inspect the menu cannot pass vacuously against a closed menu (hidden
   * elements still have layout).
   */
  async openMoreMenu() {
    // Scroll FIRST, then click - do not leave the scrolling to click()'s own
    // actionability step. The player closes the menu on any scroll event
    // (the menu is position:fixed and would otherwise drift), and a scroll
    // dispatched by the click itself arrives after the menu opened, closing
    // it again. This cost a full CI round to find.
    await this.moreButton.scrollIntoViewIfNeeded();
    await this.moreButton.click();

    try {
      await this.page.waitForFunction(
        () => document.querySelector(".sap-more-wrapper")?.classList.contains("active"),
        undefined,
        { timeout: 5000 }
      );
    } catch (e) {
      // Collect why it stayed closed instead of just timing out on a class assertion
      const info = await this.page.evaluate(() => {
        const btn = document.querySelector(".sap-more-btn");
        const wrapper = document.querySelector(".sap-more-wrapper");
        const rect = btn?.getBoundingClientRect();
        const atPoint = rect
          ? document.elementFromPoint(rect.left + rect.width / 2, rect.top + rect.height / 2)
          : null;
        // Does a scripted click work where the real mouse click did not?
        btn?.click();
        const openedByScript = wrapper?.classList.contains("active") || false;
        return {
          buttonFound: !!btn,
          wrapperClass: wrapper?.className || null,
          buttonRect: rect ? { x: Math.round(rect.x), y: Math.round(rect.y), w: Math.round(rect.width), h: Math.round(rect.height) } : null,
          elementAtButtonCentre: atPoint ? atPoint.tagName + "." + String(atPoint.className).split(" ").join(".") : null,
          playerCount: document.querySelectorAll(".sap-player").length,
          moreMenuExists: !!document.querySelector(".sap-more-menu"),
          openedByScriptedClick: openedByScript,
        };
      });
      throw new Error("More menu did not open after a real click. Diagnostics: " + JSON.stringify(info));
    }

    await this.page.waitForTimeout(200); // let the open transition settle
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
