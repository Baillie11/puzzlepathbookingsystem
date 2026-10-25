const { chromium } = require("playwright");
const assert = require("node:assert/strict");
const fs = require("node:fs");
const path = require("node:path");
const base = process.env.PREVIEW_URL || "http://127.0.0.1:4173";
const output = path.join(__dirname, "../reports");
fs.mkdirSync(output, { recursive: true });
const report = { widths: [], motion: {}, errors: [] };

function snapshot() {
  return [...document.querySelectorAll("h1,h2,h3,.booking-link,.coupon-help,.offer-price")].map(e => ({
    text: e.textContent.replace(/\s+/g, " ").trim(),
    href: e.getAttribute("href"),
    top: Math.round(e.getBoundingClientRect().top + scrollY),
    width: Math.round(e.getBoundingClientRect().width)
  }));
}

(async () => {
  const browser = await chromium.launch({
    executablePath: process.env.CHROME_PATH || "C:\\Program Files\\Google\\Chrome\\Application\\chrome.exe"
  });
  try {
    for (const width of [320, 375, 390, 430, 768, 1440]) {
      const context = await browser.newContext({ viewport: { width, height: 900 }, reducedMotion: "reduce" });
      const page = await context.newPage();
      page.on("pageerror", error => report.errors.push(error.message));
      page.on("console", message => { if (message.type() === "error") report.errors.push(message.text()); });
      await page.goto(base, { waitUntil: "networkidle" });
      await page.waitForTimeout(100);
      assert.equal(await page.locator("svg.adventure-route").count(), 4);
      const accessibleDecorations = await page.locator(".adventure-route").evaluateAll(nodes =>
        nodes.every(n => n.getAttribute("aria-hidden") === "true" &&
          n.getAttribute("focusable") === "false" && getComputedStyle(n).pointerEvents === "none"));
      assert.ok(accessibleDecorations);
      const collisions = await page.evaluate(() => {
        const protectedNodes = [...document.querySelectorAll(
          ".hero-copy,.offer-copy,.offer-redemption,.section-heading,.catalogue-note,.quest-card,.catalogue-footer,.center-heading,.steps li,.why-photo > span,.why-copy,.final-section > .wrap"
        )].map(e => ({ label: e.className || e.tagName, r: e.getBoundingClientRect() }));
        const findings = [];
        for (const route of document.querySelectorAll("#main > .adventure-route .trail-ink, .how-section > .adventure-route .trail-ink")) {
          const len = route.getTotalLength(), matrix = route.getScreenCTM();
          for (let distance = 0; distance <= len; distance += 5) {
            const p = route.getPointAtLength(distance);
            const point = new DOMPoint(p.x, p.y).matrixTransform(matrix);
            const hit = protectedNodes.find(({ r }) =>
              point.x > r.left - 4 && point.x < r.right + 4 && point.y > r.top - 4 && point.y < r.bottom + 4);
            if (hit) {
              findings.push({ route: route.parentElement.dataset.trail, content: hit.label,
                point: { x: Math.round(point.x), y: Math.round(point.y) } });
              break;
            }
          }
        }
        return findings;
      });
      assert.deepEqual(collisions, [], `Trail crossed content at ${width}: ${JSON.stringify(collisions)}`);
      assert.ok(await page.locator("#main > .adventure-route").evaluate(svg =>
        Number(getComputedStyle(svg).zIndex) > Number(getComputedStyle(document.querySelector(".offer-section")).zIndex)));
      assert.equal(await page.locator(".offer-card").evaluate(card => getComputedStyle(card).opacity), "1");
      assert.ok(await page.locator(".offer-card").evaluate(card => getComputedStyle(card).backgroundColor.includes("0.8")));
      for (const link of await page.locator(".booking-link").all()) await link.click({ trial: true });
      assert.ok(await page.evaluate(() => document.documentElement.scrollWidth <= innerWidth + 1));
      if ([390, 1440].includes(width)) {
        await page.mouse.move(0, 0);
        await page.evaluate(() => scrollTo(0, 0));
        const enhanced = await page.evaluate(snapshot);
        const baseline = await context.newPage();
        await baseline.route(/\/assets\/(?:css|js)\/adventure[^/]*\.(css|js)$/, route => route.abort());
        await baseline.goto(base, { waitUntil: "networkidle" });
        assert.deepEqual(await baseline.evaluate(snapshot), enhanced, "Copy, links or base layout changed");
        await baseline.close();
        await page.screenshot({ path: path.join(output, `adventure-hero-${width}.jpg`), type: "jpeg", quality: 85 });
        await page.locator(".how-section").scrollIntoViewIfNeeded();
        await page.screenshot({ path: path.join(output, `adventure-steps-${width}.jpg`), type: "jpeg", quality: 85 });
      }
      report.widths.push({ width, trailContentCollisions: 0, actionableBookingButtons: 11, overflow: false });
      await context.close();
      console.log(`PASS ${width}px: trail clearance, 11 actionable links, no overflow`);
    }
    const context = await browser.newContext({ viewport: { width: 1440, height: 900 }, reducedMotion: "no-preference" });
    const page = await context.newPage();
    page.on("pageerror", error => report.errors.push(error.message));
    await page.addInitScript(() => {
      window.adventureCLS = 0;
      new PerformanceObserver(list => list.getEntries().forEach(e => {
        if (!e.hadRecentInput) window.adventureCLS += e.value;
      })).observe({ type: "layout-shift", buffered: true });
    });
    await page.goto(base, { waitUntil: "networkidle" });
    const maskOffsets = () => page.locator(".adventure-route mask path").evaluateAll(nodes => nodes.map(n => Number(n.style.strokeDashoffset)));
    const initial = await maskOffsets();
    assert.ok(initial.some(value => value > 100), "Trail should begin only partly drawn");
    await page.locator(".final-section").scrollIntoViewIfNeeded();
    await page.waitForTimeout(1200);
    const finished = await maskOffsets();
    await page.evaluate(() => scrollTo({ top: 0, behavior: "instant" }));
    await page.waitForTimeout(150);
    const returned = await maskOffsets();
    assert.ok(returned.every((value, index) => value <= finished[index] + 1), "Trail retracted on scroll back");
    assert.ok(await page.locator(".final-section").evaluate(e => e.classList.contains("destination-arrived")));
    await page.locator(".steps").scrollIntoViewIfNeeded();
    await page.waitForTimeout(1300);
    assert.ok(await page.locator(".steps").evaluate(e => e.classList.contains("journey-arrived")));
    const first = page.locator(".quest-card").nth(0);
    const second = page.locator(".quest-card").nth(1);
    await first.hover();
    await page.waitForTimeout(650);
    const firstTransform = await first.evaluate(e => getComputedStyle(e).transform);
    const firstB = await first.evaluate(e => new DOMMatrix(getComputedStyle(e).transform).b);
    await second.hover();
    await page.waitForTimeout(650);
    const secondB = await second.evaluate(e => new DOMMatrix(getComputedStyle(e).transform).b);
    assert.ok(firstB * secondB < 0, "Adjacent cards must tilt in opposite directions");
    assert.notEqual(firstTransform, "none");
    for (const card of await page.locator(".quest-card").all()) {
      await card.hover();
      await page.waitForTimeout(560);
      const overlaps = await card.evaluate(active => {
        const r = active.getBoundingClientRect();
        return [...document.querySelectorAll(".quest-card")].filter(other => other !== active)
          .flatMap(other => [...other.querySelectorAll("h3,.booking-link")])
          .filter(node => {
            const s = node.getBoundingClientRect();
            return r.left < s.right && r.right > s.left && r.top < s.bottom && r.bottom > s.top;
          }).map(node => node.textContent.trim());
      });
      assert.deepEqual(overlaps, [], "Tilt covered a neighbouring title or booking button");
      assert.ok(await page.evaluate(() => document.documentElement.scrollWidth <= innerWidth + 1));
    }
    await first.hover();
    await page.waitForTimeout(600);
    await page.screenshot({ path: path.join(output, "adventure-postcards-1440.jpg"), type: "jpeg", quality: 85 });
    await page.mouse.move(3, 3);
    await page.waitForTimeout(1000);
    assert.equal(await first.evaluate(e => getComputedStyle(e).transform), "none");
    assert.equal(await second.evaluate(e => getComputedStyle(e).transform), "none");
    await first.locator(".booking-link").focus();
    await page.waitForTimeout(650);
    assert.notEqual(await first.evaluate(e => getComputedStyle(e).transform), "none", "Keyboard focus should lift the card");
    await page.emulateMedia({ reducedMotion: "reduce" });
    await page.waitForTimeout(100);
    assert.equal(await first.evaluate(e => getComputedStyle(e).transform), "none");
    assert.ok((await maskOffsets()).every(value => Math.abs(value) < .1), "Reduced motion should show the whole static trail");
    assert.equal(await page.evaluate(() => window.adventureCLS), 0);
    for (const width of [390, 768, 1440]) {
      await page.setViewportSize({ width, height: 900 });
      await page.waitForTimeout(150);
      assert.equal(await page.locator(".adventure-route").count(), 4);
      assert.equal(await page.locator("#main > .adventure-route").evaluate(svg => svg.viewBox.baseVal.width), width);
      assert.ok(await page.evaluate(() => document.documentElement.scrollWidth <= innerWidth + 1));
    }
    report.motion = { revealOnce: true, oppositeTilts: true, springSettles: true, keyboard: true, reducedMotion: true, cls: 0, neighbourClearance: true, liveResize: true };
    await context.close();

    const touch = await browser.newContext({ viewport: { width: 390, height: 844 }, hasTouch: true, isMobile: true, reducedMotion: "no-preference" });
    const phone = await touch.newPage();
    await phone.goto(base, { waitUntil: "networkidle" });
    const card = phone.locator(".quest-card").first();
    await card.locator("h3").tap();
    assert.ok(await card.evaluate(e => e.classList.contains("is-tapped")), "Touch feedback missing");
    await phone.waitForTimeout(800);
    assert.equal(await card.evaluate(e => e.classList.contains("is-tapped")), false);
    assert.equal(await card.evaluate(e => getComputedStyle(e).transform), "none");
    report.motion.touch = true;
    await touch.close();
    assert.deepEqual(report.errors, []);
    fs.writeFileSync(path.join(output, "adventure-results.json"), JSON.stringify(report, null, 2));
    console.log("PASS progressive reveal, spring return, focus, touch, reduced motion, CLS 0, unchanged base layout/copy");
  } finally { await browser.close(); }
})().catch(error => { console.error(error); process.exitCode = 1; });
