const { chromium } = require("playwright");
const assert = require("node:assert/strict");
const fs = require("node:fs");
const path = require("node:path");
const base = process.env.PREVIEW_URL || "http://127.0.0.1:4173";
const report = { cases: [], errors: [] };
const folder = path.join(__dirname, "../reports");
fs.mkdirSync(folder, { recursive: true });

const rowSlots = page => page.locator(".quest-slot").all();
async function firstRow(page) {
  return page.locator(".quest-slot").evaluateAll(slots => {
    const top = slots[0].offsetTop;
    return slots.filter(slot => Math.abs(slot.offsetTop - top) < 4).length;
  });
}
async function target(page, index = 0) {
  return page.locator(".quest-slot").nth(index).evaluate(slot => {
    const r = slot.getBoundingClientRect();
    return { top: r.top + scrollY, height: r.height };
  });
}
async function scroll(page, y) {
  await page.evaluate(y => scrollTo({ top: y, behavior: "instant" }), y);
  await page.waitForTimeout(100);
}
async function warmFirst(page) {
  const r = await target(page);
  await scroll(page, Math.max(0, r.top - 1200));
  await page.waitForFunction(() => [...document.querySelectorAll(".quest-slot")].slice(0, 3)
    .every(slot => { const image = slot.querySelector("img"); return image.complete && image.naturalWidth > 0; }));
  await page.waitForTimeout(100);
  return r;
}
async function visit(page, row) {
  await scroll(page, row.top - 450);
  await page.waitForFunction(() => document.querySelector(".quest-slot").dataset.entranceCycle);
}
async function rest(page) {
  await page.waitForTimeout(1150);
  assert.ok(await page.locator(".quest-slot").evaluateAll(slots => slots.every(slot =>
    getComputedStyle(slot).transform === "none" && getComputedStyle(slot).opacity === "1" &&
    !slot.classList.contains("is-arriving") && slot.style.willChange === "")));
}
async function combo(page, count) {
  return page.locator(".quest-slot").evaluateAll((slots, n) => slots.slice(0, n)
    .map(slot => `${slot.dataset.entranceStyle}:${slot.dataset.entranceDelay}`), count);
}
function watch(page) {
  page.on("pageerror", error => report.errors.push(error.message));
}

(async () => {
  const browser = await chromium.launch({
    executablePath: process.env.CHROME_PATH || "C:\\Program Files\\Google\\Chrome\\Application\\chrome.exe"
  });
  try {
    const context = await browser.newContext({ viewport: { width: 1440, height: 900 }, reducedMotion: "no-preference" });
    const page = await context.newPage();
    watch(page);
    const requests = [];
    page.on("request", request => { if (/\/quest-(12|10|9)-\d+\.webp/.test(request.url())) requests.push(request.url()); });
    await page.addInitScript(() => {
      window.postcardCLS = 0;
      new PerformanceObserver(list => list.getEntries().forEach(e => {
        if (!e.hadRecentInput) window.postcardCLS += e.value;
      })).observe({ type: "layout-shift", buffered: true });
    });
    await page.goto(base, { waitUntil: "networkidle" });
    await page.mouse.move(0, 0);
    const count = await firstRow(page);
    assert.equal(count, 3);
    const row = await warmFirst(page);
    await visit(page, row);
    const firstCombo = await combo(page, count);
    assert.ok(new Set(firstCombo.map(value => value.split(":")[0])).size >= 2);
    const delays = firstCombo.map(value => Number(value.split(":")[1])).sort((a,b) => a-b);
    assert.deepEqual(delays, [0, 110, 220]);
    await rest(page);
    const cycle = await page.locator(".quest-slot").first().getAttribute("data-entrance-cycle");
    await scroll(page, row.top - 400);
    await scroll(page, row.top - 460);
    assert.equal(await page.locator(".quest-slot").first().getAttribute("data-entrance-cycle"), cycle);
    await scroll(page, row.top + row.height + 1300);
    const requestCount = requests.length;
    await visit(page, row);
    assert.equal(Number(await page.locator(".quest-slot").first().getAttribute("data-entrance-cycle")), Number(cycle) + 1);
    assert.notDeepEqual(await combo(page, count), firstCombo);
    await rest(page);
    assert.equal(requests.length, requestCount, "Replay fetched an already-loaded first-row image again");
    report.cases.push("distinct randomized stagger, exact rest state, minor-scroll hysteresis, upward replay, no asset reload");

    await scroll(page, row.top + row.height + 1300);
    await visit(page, row);
    const activeSlot = page.locator(".quest-slot").nth(1);
    await activeSlot.locator(".booking-link").focus();
    assert.equal(await activeSlot.evaluate(slot => slot.getAnimations().length), 0);
    assert.equal(await activeSlot.evaluate(slot => slot.style.willChange), "");
    await page.waitForTimeout(650);
    assert.notEqual(await activeSlot.locator(".quest-card").evaluate(card => getComputedStyle(card).transform), "none");
    await page.evaluate(() => document.activeElement.blur());
    await scroll(page, 0);
    await scroll(page, 11000);
    await scroll(page, row.top - 450);
    await scroll(page, 0);
    await rest(page);
    assert.equal(await page.evaluate(() => window.postcardCLS), 0);
    report.cases.push("interaction cancels entrance without blocking inner focus; rapid scroll leaves every card at rest; CLS 0");
    await scroll(page, row.top + row.height + 1300);
    await visit(page, row);
    await page.emulateMedia({ reducedMotion: "reduce" });
    await rest(page);
    report.cases.push("enabling reduced motion cancels active entrances");
    await context.close();

    const slow = await browser.newContext({ viewport: { width: 1440, height: 900 } });
    const delayed = await slow.newPage();
    watch(delayed);
    let release;
    const gate = new Promise(resolve => { release = resolve; });
    await delayed.route("**/assets/images/quest-*.webp", async route => { await gate; await route.continue(); });
    await delayed.goto(base, { waitUntil: "domcontentloaded" });
    await delayed.mouse.move(0, 0);
    const slowRow = await target(delayed);
    await scroll(delayed, slowRow.top - 450);
    await delayed.waitForTimeout(100);
    assert.equal(await delayed.locator(".quest-slot.is-arriving").count(), 0);
    assert.ok(await delayed.locator(".quest-card").first().isVisible());
    assert.ok(await delayed.locator(".booking-link").first().isVisible());
    const reservedHeight = await delayed.locator(".quest-slot").first().evaluate(slot => slot.offsetHeight);
    release();
    await delayed.waitForFunction(() => [...document.querySelectorAll(".quest-slot")].slice(0, 3)
      .every(slot => { const image = slot.querySelector("img"); return image.complete && image.naturalWidth; }));
    await delayed.waitForTimeout(300);
    assert.equal(await delayed.locator(".quest-slot.is-arriving").count(), 0);
    assert.equal(await delayed.locator(".quest-slot").first().evaluate(slot => slot.offsetHeight), reservedHeight);
    assert.equal(await delayed.locator(".quest-slot").first().getAttribute("data-entrance-cycle"), null);
    await slow.close();
    report.cases.push("slow imagery keeps text/buttons visible, reserves dimensions and never triggers a late fly-in");

    for (const mode of ["unsupported", "throws", "data-saver", "script-fails"]) {
      const fallback = await browser.newContext({ viewport: { width: 1440, height: 900 } });
      const staticPage = await fallback.newPage();
      watch(staticPage);
      if (mode === "unsupported") await staticPage.addInitScript(() => { Element.prototype.animate = undefined; });
      if (mode === "throws") await staticPage.addInitScript(() => {
        const native = Element.prototype.animate;
        Element.prototype.animate = function(...args) {
          if (this.classList.contains("quest-slot")) throw new Error("Simulated animation engine failure");
          return native.apply(this, args);
        };
      });
      if (mode === "data-saver") await staticPage.addInitScript(() => {
        Object.defineProperty(navigator, "connection", { value: { saveData: true, effectiveType: "2g", addEventListener() {} } });
      });
      if (mode === "script-fails") await staticPage.route(/\/postcards[^/]*\.js$/, route => route.abort());
      await staticPage.goto(base, { waitUntil: "networkidle" });
      const r = await warmFirst(staticPage);
      await scroll(staticPage, r.top - 450);
      await rest(staticPage);
      assert.equal(await staticPage.locator(".booking-link").count(), 11);
      await fallback.close();
      report.cases.push(`${mode}: visible static cards and 11 booking links`);
    }
    const mobile = await browser.newContext({ viewport: { width: 320, height: 850 }, isMobile: true, hasTouch: true });
    const phone = await mobile.newPage();
    watch(phone);
    await phone.goto(base, { waitUntil: "networkidle" });
    await phone.evaluate(() => document.querySelectorAll(".quest-art img").forEach(image => { image.loading = "eager"; }));
    await phone.waitForFunction(() => [...document.querySelectorAll(".quest-art img")].every(image => image.complete && image.naturalWidth));
    for (const slot of await rowSlots(phone)) {
      const r = await slot.evaluate(node => node.getBoundingClientRect().top + scrollY);
      await scroll(phone, r - 500);
      assert.ok(await phone.evaluate(() => document.documentElement.scrollWidth <= innerWidth + 1));
    }
    await rest(phone);
    await mobile.close();
    report.cases.push("320px touch: all eleven entrances bounded to viewport; no horizontal overflow");
    assert.deepEqual(report.errors, []);
    fs.writeFileSync(path.join(folder, "postcards-results.json"), JSON.stringify(report, null, 2));
    console.log(report.cases.map(value => "PASS " + value).join("\n"));
  } finally { await browser.close(); }
})().catch(error => { console.error(error); process.exitCode = 1; });
