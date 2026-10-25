const { test } = require("node:test");
const assert = require("node:assert/strict");
const fs = require("node:fs");
const vm = require("node:vm");
const source = fs.readFileSync(require("node:path").join(__dirname, "../assets/js/main.js"), "utf8");

function run(search = "", nav = {}, fail = false) {
  const events = [];
  const handlers = {};
  const link = {
    href: "https://puzzlepath.com.au/book-today/?event_id=12&utm_campaign=brochure_dl_2026",
    dataset: { questId: "12" },
    addEventListener: (name, fn) => { handlers[name] = fn; }
  };
  vm.runInNewContext(source, {
    location: { search }, navigator: nav, window: { isSecureContext: true },
    URL, URLSearchParams,
    CustomEvent: class { constructor(name, options) { this.detail = options.detail; } },
    document: {
      querySelectorAll: () => [link], querySelector: () => null, getElementById: () => null,
      dispatchEvent: event => { if (fail) throw Error("collector unavailable"); events.push(event.detail); }
    }
  });
  return { events, handlers, link };
}
test("fixed bounded visit event", () => {
  const result = run("?email=private@example.com&booking=SECRET&placement=hotel");
  assert.equal(result.events[0].event, "landing_visit");
  assert.equal(result.events[0].placement, "hotel");
  assert.equal(JSON.stringify(result.events).includes("private"), false);
  assert.equal(result.link.href.includes("SECRET"), false);
});
test("unknown placement is not forwarded", () => {
  const result = run("?placement=private@example.com");
  assert.equal(result.events[0].placement, "unspecified");
  assert.equal(result.link.href.includes("utm_content"), false);
});
test("DNT suppresses events", () => assert.equal(run("", { doNotTrack: "1" }).events.length, 0));
test("GPC suppresses events", () => assert.equal(run("", { globalPrivacyControl: true }).events.length, 0));
test("booking handlers do not cancel navigation", () => {
  const result = run();
  assert.doesNotThrow(() => result.handlers.click());
  assert.equal(result.events.length, 3);
});
test("broken event collector cannot break booking handler", () => {
  const result = run("", {}, true);
  assert.doesNotThrow(() => result.handlers.click());
});
