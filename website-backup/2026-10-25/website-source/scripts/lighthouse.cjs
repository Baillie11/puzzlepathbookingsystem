const fs = require("node:fs");
const path = require("node:path");
(async () => {
  const { default: lighthouse } = await import("lighthouse");
  const { launch } = await import("chrome-launcher");
  const desktop = process.argv[2] === "desktop";
  const chrome = await launch({
    chromePath: process.env.CHROME_PATH || "C:\\Program Files\\Google\\Chrome\\Application\\chrome.exe",
    chromeFlags: ["--headless=new"]
  });
  try {
    const url = process.env.AUDIT_URL || "http://127.0.0.1:4173/";
    const result = await lighthouse(url, {
      port: chrome.port, output: ["html", "json"], onlyCategories: ["performance", "accessibility", "best-practices", "seo"],
      ...(desktop ? { formFactor: "desktop", screenEmulation: { mobile: false, width: 1440, height: 900, deviceScaleFactor: 1, disabled: false },
        throttling: { rttMs: 40, throughputKbps: 10240, cpuSlowdownMultiplier: 1 } } : {})
    });
    const name = desktop ? "desktop" : "mobile";
    const out = path.join(__dirname, "../reports");
    fs.mkdirSync(out, { recursive: true });
    fs.writeFileSync(path.join(out, `lighthouse-${name}.html`), result.report[0]);
    fs.writeFileSync(path.join(out, `lighthouse-${name}.json`), result.report[1]);
    const lhr = result.lhr;
    console.log(JSON.stringify({ url, mode: name, scores: Object.fromEntries(Object.entries(lhr.categories).map(([k,v]) => [k, Math.round(v.score * 100)])),
      metrics: Object.fromEntries(["first-contentful-paint", "largest-contentful-paint", "cumulative-layout-shift", "total-blocking-time", "total-byte-weight"].map(k => [k, lhr.audits[k].displayValue])) }, null, 2));
  } finally { await chrome.kill(); }
})().catch(e => { console.error(e); process.exitCode = 1; });
