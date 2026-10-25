const { chromium } = require("playwright");
const AxeBuilder = require("@axe-core/playwright").default;
const assert = require("node:assert/strict");
const fs = require("node:fs");
const path = require("node:path");
const base = process.env.PREVIEW_URL || "http://127.0.0.1:4173";
const out = path.join(__dirname, "../reports");
fs.mkdirSync(out, { recursive: true });

(async () => {
  const browser = await chromium.launch({
    executablePath: process.env.CHROME_PATH || "C:\\Program Files\\Google\\Chrome\\Application\\chrome.exe",
    headless: true
  });
  const report = { checkedAt: new Date().toISOString(), base, viewports: [] };
  try {
    for (const width of [320, 375, 390, 430, 768, 1440]) {
      const viewportContext = await browser.newContext({ viewport: { width, height: 900 }, reducedMotion: "reduce" });
      const page = await viewportContext.newPage();
      const errors = [];
      page.on("pageerror", e => errors.push(e.message));
      page.on("response", r => { if (r.status() >= 400) errors.push(`${r.status()} ${r.url()}`); });
      await page.goto(base, { waitUntil: "networkidle" });
      const overflow = await page.evaluate(() => ({
        viewport: innerWidth, scroll: document.documentElement.scrollWidth,
        elements: [...document.querySelectorAll("body *")].filter(e => e.getBoundingClientRect().right > innerWidth + 1).map(e => e.className).slice(0, 8)
      }));
      assert.ok(overflow.scroll <= width + 1, `Overflow at ${width}: ${JSON.stringify(overflow)}`);
      assert.equal(await page.locator(".quest-card").count(), 11);
      for (const image of await page.locator("img").all()) {
        await image.scrollIntoViewIfNeeded();
        await image.evaluate(img => img.decode());
      }
      await page.locator(".site-footer").scrollIntoViewIfNeeded();
      await page.waitForTimeout(200);
      const broken = await page.locator("img").evaluateAll(images => images.filter(i => i.complete && i.naturalWidth === 0).map(i => i.src));
      assert.deepEqual(broken, []);
      const axe = await new AxeBuilder({ page }).withTags(["wcag2a", "wcag2aa", "wcag21aa"]).analyze();
      fs.writeFileSync(path.join(out, `axe-${width}.json`), JSON.stringify(axe.violations, null, 2));
      assert.deepEqual(axe.violations.map(v => ({ id: v.id, nodes: v.nodes.map(n => n.target) })), [], `Accessibility violations at ${width}`);
      await page.evaluate(() => window.scrollTo(0, 0));
      await page.screenshot({ path: path.join(out, `preview-${width}.png`), fullPage: true });
      assert.deepEqual(errors, []);
      report.viewports.push({ width, overflow: false, brokenImages: 0, axeViolations: 0, errors });
      await viewportContext.close();
      console.log(`PASS ${width}px: layout, images, accessibility, console`);
    }
    const context = await browser.newContext({ permissions: ["clipboard-read", "clipboard-write"] });
    const page = await context.newPage();
    await page.goto(`${base}/?placement=hotel&email=do-not-forward@example.com`);
    await page.locator("[data-copy-coupon]").click();
    assert.equal(await page.evaluate(() => navigator.clipboard.readText()), "BROCHURE25");
    const link = await page.locator(".booking-link").first().getAttribute("href");
    assert.ok(link.includes("utm_content=hotel"));
    assert.ok(!link.includes("do-not-forward"));
    await page.route("https://puzzlepath.com.au/book-today/**", route => route.fulfill({ status: 200, contentType: "text/html", body: "<title>Safe booking navigation test</title><p>No production request sent.</p>" }));
    await page.locator(".booking-link").first().click();
    await page.waitForURL("https://puzzlepath.com.au/book-today/**");
    report.couponCopy = true;
    report.bookingNavigationIntercepted = true;
    await context.close();
    const noJS = await browser.newContext({ javaScriptEnabled: false });
    const fallback = await noJS.newPage();
    await fallback.goto(base);
    assert.equal(await fallback.locator(".booking-link").count(), 11);
    assert.ok((await fallback.locator(".booking-link").first().getAttribute("href")).includes("event_id=12"));
    assert.equal(await fallback.locator("[data-copy-coupon]").isVisible(), false);
    report.noJavaScript = true;
    await fallback.goto(`${base}/privacy.html`);
    assert.equal(await fallback.locator("h1").textContent(), "Campaign privacy information");
    await noJS.close();
    fs.writeFileSync(path.join(out, "browser-results.json"), JSON.stringify(report, null, 2));
    console.log("PASS coupon copy, campaign allowlist, safe navigation, no-JS and privacy page");
  } finally { await browser.close(); }
})().catch(e => { console.error(e); process.exitCode = 1; });
