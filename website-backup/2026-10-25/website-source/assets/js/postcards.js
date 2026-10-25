/* Image-ready postcard entrances only. Visible static HTML is always the default. */
(() => {
  "use strict";
  const grid = document.querySelector(".quest-grid");
  if (!grid || !("IntersectionObserver" in window)) return;
  const slots = [...grid.querySelectorAll(".quest-slot")];
  const motion = matchMedia("(prefers-reduced-motion: reduce)");
  const connection = navigator.connection;
  const seen = new WeakSet();
  const decoded = new WeakSet();
  const active = new Map();
  const previous = new Map();
  let rows = [];
  let nearObserver;
  let farObserver;
  let resizeFrame = 0;
  let lastWidth = 0;
  let lastHeight = 0;
  let disabled = motion.matches || connection?.saveData === true ||
    /(^|-)2g$/.test(connection?.effectiveType || "") || !Element.prototype.animate;
  const styles = [
    { name: "left", x: -32, y: 8, angle: -2 },
    { name: "right", x: 32, y: 8, angle: 2 },
    { name: "above", x: 0, y: -34, angle: -1.5 },
    { name: "below", x: 0, y: 34, angle: 1.5 },
    { name: "diagonal", x: -26, y: 28, angle: 2.5 },
    { name: "twist", x: 15, y: -14, angle: -3 }
  ];
  function shuffle(values) {
    const result = [...values];
    for (let i = result.length - 1; i > 0; i--) {
      const j = Math.floor(Math.random() * (i + 1));
      [result[i], result[j]] = [result[j], result[i]];
    }
    return result;
  }
  function stop(slot) {
    const record = active.get(slot);
    if (!record) return;
    active.delete(slot);
    clearTimeout(record.guard);
    record.animation.cancel();
    slot.style.willChange = "";
    slot.classList.remove("is-arriving");
    slot.dataset.entranceState = "rest";
  }
  function stopAll() { [...active.keys()].forEach(stop); }
  function warm(slot) {
    const image = slot.querySelector(".quest-art img");
    image.loading = "eager";
    const ready = () => {
      if (!image.naturalWidth) return;
      const complete = () => { decoded.add(image); image.closest(".quest-art").classList.remove("is-image-unavailable"); };
      if (image.decode) image.decode().then(complete).catch(() => {});
      else complete();
    };
    if (image.complete) ready();
    else image.addEventListener("load", ready, { once: true });
    image.addEventListener("error", () => image.closest(".quest-art").classList.add("is-image-unavailable"), { once: true });
  }
  // Warm only nearby responsive sources; far-away artwork retains native lazy loading.
  const imageObserver = new IntersectionObserver(entries => entries.forEach(entry => {
    if (entry.isIntersecting) { warm(entry.target); imageObserver.unobserve(entry.target); }
  }), { rootMargin: `${Math.round(innerHeight * .9)}px 0px` });
  slots.forEach(slot => {
    imageObserver.observe(slot);
    for (const event of ["pointerenter", "pointerdown", "focusin"]) {
      slot.addEventListener(event, () => stop(slot), { capture: true, passive: true });
    }
  });

  function play(row, entry) {
    row.armed = false;
    row.played = true;
    row.slots.forEach(slot => seen.add(slot));
    if (disabled || document.hidden || performance.now() - entry.time > 180) return;
    let choices = shuffle(styles).slice(0, row.slots.length);
    const order = shuffle(row.slots.map((_, index) => index));
    const signature = () => choices.map((style, index) => `${style.name}:${order.indexOf(index)}`).join("|");
    if (signature() === previous.get(row.key)) {
      choices[0] = styles[(styles.indexOf(choices[0]) + 1) % styles.length];
    }
    previous.set(row.key, signature());
    row.cycle += 1;
    row.slots.forEach((slot, index) => {
      stop(slot);
      const image = slot.querySelector(".quest-art img");
      // Missing imagery must never postpone content or start a late fly-in.
      if (!decoded.has(image) || !image.complete || !image.naturalWidth ||
          slot.matches(":hover") && matchMedia("(hover: hover)").matches ||
          slot.contains(document.activeElement)) {
        slot.dataset.entranceState = "static";
        return;
      }
      const style = choices[index];
      const rect = slot.getBoundingClientRect();
      const angleLimit = Math.max(.2, Math.min(3,
        (Math.min(rect.left, innerWidth - rect.right) - 8) * 2 / rect.height * 180 / Math.PI));
      const angle = Math.sign(style.angle) * Math.min(Math.abs(style.angle), angleLimit);
      const radians = Math.abs(angle) * Math.PI / 180;
      const rotationBudget = rect.height / 2 * Math.sin(radians) + rect.width / 2 * (1 - Math.cos(radians)) + 2;
      const dx = Math.max(-Math.max(0, rect.left - 7 - rotationBudget),
        Math.min(style.x, Math.max(0, innerWidth - rect.right - 7 - rotationBudget)));
      const delay = order.indexOf(index) * 110;
      const start = `translate3d(${dx}px,${style.y}px,0) rotate(${angle}deg)`;
      try {
        slot.style.willChange = "transform, opacity";
        slot.classList.add("is-arriving");
        const animation = slot.animate([
          { transform: start, opacity: .94, offset: 0 },
          { transform: `translate3d(${-dx * .055}px,${-style.y * .055}px,0) rotate(${-angle * .055}deg)`, opacity: 1, offset: .82 },
          { transform: "none", opacity: 1, offset: 1 }
        ], { duration: 720, delay, easing: "cubic-bezier(.22,1,.36,1)", fill: "backwards" });
        const record = { animation, guard: setTimeout(() => stop(slot), 1100 + delay) };
        active.set(slot, record);
        slot.dataset.entranceStyle = style.name;
        slot.dataset.entranceCycle = String(row.cycle);
        slot.dataset.entranceDelay = String(delay);
        slot.dataset.entranceState = "playing";
        const cleanup = () => {
          if (active.get(slot) !== record) return;
          active.delete(slot); clearTimeout(record.guard);
          slot.style.willChange = "";
          slot.classList.remove("is-arriving");
          slot.dataset.entranceState = "rest";
        };
        animation.finished.then(cleanup, cleanup);
      } catch (_) {
        slot.style.willChange = "";
        slot.classList.remove("is-arriving");
        slot.dataset.entranceState = "static";
      }
    });
  }
  function observeRows() {
    stopAll();
    nearObserver?.disconnect();
    farObserver?.disconnect();
    rows = [];
    for (const slot of slots) {
      let row = rows.find(row => Math.abs(row.top - slot.offsetTop) < 4);
      if (!row) { row = { top: slot.offsetTop, slots: [] }; rows.push(row); }
      row.slots.push(slot);
    }
    const byAnchor = new Map();
    rows.forEach(row => {
      row.key = row.slots.map(slot => slot.querySelector(".booking-link").dataset.questId).join("-");
      row.played = row.slots.some(slot => seen.has(slot));
      row.armed = !row.played;
      row.near = false;
      row.cycle = Number(row.slots[0].dataset.entranceCycle || 0);
      byAnchor.set(row.slots[0], row);
    });
    nearObserver = new IntersectionObserver(entries => entries.forEach(entry => {
      const row = byAnchor.get(entry.target);
      row.near = entry.isIntersecting;
      if (row.near && row.armed) play(row, entry);
      else if (!row.near) row.slots.forEach(stop);
    }), { threshold: .05, rootMargin: "0px 0px -6% 0px" });
    farObserver = new IntersectionObserver(entries => entries.forEach(entry => {
      const row = byAnchor.get(entry.target);
      if (!entry.isIntersecting && row.played && !row.near) {
        row.slots.forEach(stop);
        row.armed = true;
      }
    }), { rootMargin: `${Math.max(600, innerHeight)}px 0px`, threshold: 0 });
    rows.forEach(row => {
      nearObserver.observe(row.slots[0]);
      farObserver.observe(row.slots[0]);
    });
    lastWidth = grid.offsetWidth;
    lastHeight = grid.offsetHeight;
  }
  function queueRows() {
    if (resizeFrame) return;
    resizeFrame = requestAnimationFrame(() => { resizeFrame = 0; observeRows(); });
  }
  if ("ResizeObserver" in window) new ResizeObserver(() => {
    if (grid.offsetWidth !== lastWidth || grid.offsetHeight !== lastHeight) queueRows();
  }).observe(grid);
  window.addEventListener("resize", queueRows, { passive: true });
  document.addEventListener("visibilitychange", () => { if (document.hidden) stopAll(); });
  motion.addEventListener("change", () => {
    if (motion.matches) { disabled = true; stopAll(); }
  });
  connection?.addEventListener?.("change", () => {
    if (connection.saveData || /(^|-)2g$/.test(connection.effectiveType || "")) { disabled = true; stopAll(); }
  });
  observeRows();
})();
