/* Decoration only: no booking, coupon, analytics, storage or network behaviour. */
(() => {
  "use strict";
  const main = document.getElementById("main");
  if (!main || !window.matchMedia || !window.requestAnimationFrame) return;
  const motion = matchMedia("(prefers-reduced-motion: reduce)");
  const finePointer = matchMedia("(hover: hover) and (pointer: fine)");
  const ns = "http://www.w3.org/2000/svg";
  const cards = [...main.querySelectorAll(".quest-card")];
  const steps = main.querySelector(".steps");
  const how = main.querySelector(".how-section");
  const finale = main.querySelector(".final-section");
  let serial = 0;
  let segments = [];
  let drawings = [];
  const layerHosts = new Map();
  let stepLinks = [];
  let origin = 0;
  let frame = 0;
  let drawingFrame = 0;
  let drawnWidth = 0;
  let drawnHeight = 0;
  let stepsSeen = false;
  let finishSeen = false;
  const furthest = new Map();

  function svgNode(tag, attrs = {}) {
    const node = document.createElementNS(ns, tag);
    for (const [key, value] of Object.entries(attrs)) node.setAttribute(key, String(value));
    return node;
  }
  function canvas(host, width, height) {
    const svg = svgNode("svg", {
      class: "adventure-route", viewBox: `0 0 ${width} ${height}`,
      width, height, "aria-hidden": "true", focusable: "false"
    });
    layerHosts.set(svg, host);
    drawings.push(svg);
    return svg;
  }
  function footmarks(svg, path, length) {
    const marks = svgNode("g", { class: "trail-footprints" });
    for (let distance = 62; distance < length - 12; distance += 155) {
      const point = path.getPointAtLength(distance);
      const ahead = path.getPointAtLength(Math.min(distance + 2, length));
      const angle = Math.atan2(ahead.y - point.y, ahead.x - point.x) * 180 / Math.PI + 90;
      const pair = svgNode("g", { transform: `translate(${point.x} ${point.y}) rotate(${angle}) scale(.72)` });
      pair.append(
        svgNode("path", { d: "M-7-5C-10-3-9 3-6 5C-3 6-2 2-3 0L-4-4Z" }),
        svgNode("ellipse", { cx: -6, cy: -9, rx: 2.7, ry: 3.4 }),
        svgNode("path", { d: "M4 5C1 7 2 13 5 15C8 16 9 12 8 10L7 6Z" }),
        svgNode("ellipse", { cx: 5, cy: 1, rx: 2.7, ry: 3.4 })
      );
      marks.append(pair);
    }
    return marks;
  }
  function trail(svg, d, key, offsetY = 0, type = "scroll") {
    const id = `walking-reveal-${++serial}`;
    const path = svgNode("path", { d, class: "trail-ink", "stroke-dasharray": "2 12 9 12" });
    const group = svgNode("g", { mask: `url(#${id})`, "data-trail": key });
    group.append(path);
    svg.append(group);
    const length = path.getTotalLength();
    const mask = svgNode("mask", {
      id, maskUnits: "userSpaceOnUse", x: 0, y: 0,
      width: svg.viewBox.baseVal.width, height: svg.viewBox.baseVal.height
    });
    // A separate solid mask reveals a dashed path without sliding its dashes.
    const reveal = svgNode("path", { d, fill: "none", stroke: "white", "stroke-width": 32, "stroke-linecap": "round" });
    reveal.style.strokeDasharray = `${length} ${length}`;
    reveal.style.strokeDashoffset = String(length);
    mask.append(reveal);
    svg.prepend(mask);
    group.append(footmarks(svg, path, length));
    const samples = [];
    const bounds = { left: Infinity, top: Infinity, right: -Infinity, bottom: -Infinity };
    for (let i = 0; i <= 20; i++) {
      const at = length * i / 20;
      const point = path.getPointAtLength(at);
      samples.push({ at, y: point.y + offsetY });
      bounds.left = Math.min(bounds.left, point.x);
      bounds.top = Math.min(bounds.top, point.y);
      bounds.right = Math.max(bounds.right, point.x);
      bounds.bottom = Math.max(bounds.bottom, point.y);
    }
    mask.setAttribute("x", bounds.left - 24);
    mask.setAttribute("y", bounds.top - 24);
    mask.setAttribute("width", bounds.right - bounds.left + 48);
    mask.setAttribute("height", bounds.bottom - bounds.top + 48);
    const segment = { key, reveal, length, samples, progress: -1, type };
    segments.push(segment);
    return segment;
  }
  function show(segment, progress) {
    progress = Math.max(0, Math.min(1, progress));
    if (Math.abs(progress - segment.progress) < .0005) return;
    segment.progress = progress;
    segment.reveal.style.strokeDashoffset = String(segment.length * (1 - progress));
  }
  function update() {
    frame = 0;
    const horizon = scrollY + innerHeight * .88 - origin;
    for (const segment of segments) {
      if (motion.matches) { show(segment, 1); continue; }
      if (segment.type !== "scroll") continue;
      const { samples, length } = segment;
      let reached = 0;
      // Geometry is sampled only on resize, never remeasured during scrolling.
      for (let i = 0; i < samples.length; i++) {
        if (samples[i].y > horizon) break;
        reached = samples[i].at;
        const next = samples[i + 1];
        if (next && next.y > horizon) {
          reached += (next.at - reached) * (horizon - samples[i].y) / Math.max(1, next.y - samples[i].y);
          break;
        }
      }
      const progress = Math.max(furthest.get(segment.key) || 0, reached / length);
      furthest.set(segment.key, progress);
      show(segment, progress);
    }
  }
  function scrollTick() { if (!frame) frame = requestAnimationFrame(update); }
  function animateSteps() {
    stepsSeen = true;
    steps.classList.add("journey-arrived");
    stepLinks.forEach((segment, index) => {
      if (motion.matches || !segment.reveal.animate) { show(segment, 1); return; }
      show(segment, 1);
      segment.reveal.animate(
        [{ strokeDashoffset: String(segment.length) }, { strokeDashoffset: "0" }],
        { duration: 460, delay: 150 + index * 350, easing: "cubic-bezier(.22,1,.36,1)", fill: "backwards" }
      );
    });
  }
  function draw() {
    drawingFrame = 0;
    drawings.forEach(svg => svg.remove());
    layerHosts.clear();
    drawings = []; segments = []; stepLinks = [];
    const base = main.getBoundingClientRect();
    origin = base.top + scrollY;
    const width = base.width;
    const height = main.offsetHeight;
    drawnWidth = width;
    drawnHeight = height;
    const box = element => {
      const r = element.getBoundingClientRect();
      return { left: r.left - base.left, right: r.right - base.left,
        top: r.top - base.top, bottom: r.bottom - base.top, width: r.width, height: r.height };
    };
    const layoutBox = element => {
      let left = 0, top = 0, node = element;
      while (node && node !== main) {
        left += node.offsetLeft; top += node.offsetTop;
        node = node.offsetParent;
      }
      return { left, top, right: left + element.offsetWidth,
        bottom: top + element.offsetHeight, width: element.offsetWidth, height: element.offsetHeight };
    };
    const hero = box(main.querySelector(".hero"));
    const visual = box(main.querySelector(".hero-visual"));
    const offer = box(main.querySelector(".offer-section"));
    const adventures = box(main.querySelector(".adventures-section"));
    const howBox = box(how);
    const why = box(main.querySelector(".why-section"));
    const end = box(finale);
    const wide = width >= 800;
    const left = hero.left;
    const span = hero.width;
    const at = fraction => left + span * fraction;
    const page = canvas(main, width, height);
    // Short chapter-to-chapter curves, never a box following container edges.
    trail(page,
      `M ${at(.82)} ${hero.bottom - 52}
       C ${at(.74)} ${hero.bottom - 6} ${at(.61)} ${hero.bottom - 5} ${at(.53)} ${hero.bottom - 31}
       S ${at(.3)} ${hero.bottom - 60} ${at(.18)} ${offer.top + 14}`,
      "hero-to-offer");
    trail(page,
      `M ${at(.18)} ${offer.top + 14}
       C ${at(.4)} ${offer.top + 29} ${at(.58)} ${offer.top + 3} ${at(.82)} ${offer.top + 17}`,
      "offer-crossing");
    trail(page,
      `M ${at(.18)} ${offer.bottom + 16}
       C ${at(.24)} ${offer.bottom + 72} ${at(.48)} ${offer.bottom + 70} ${at(.52)} ${offer.bottom + 39}
       C ${at(.57)} ${offer.bottom + 9} ${at(.43)} ${offer.bottom + 12} ${at(.48)} ${offer.bottom + 44}
       S ${at(.72)} ${offer.bottom + 77} ${at(.82)} ${offer.bottom + 29}`,
      "offer-to-quests");
    const rows = [];
    for (const slot of main.querySelectorAll(".quest-slot")) {
      const r = layoutBox(slot);
      const row = rows.find(row => Math.abs(row.top - r.top) < 4);
      if (row) row.bottom = Math.max(row.bottom, r.bottom);
      else rows.push({ top: r.top, bottom: r.bottom });
    }
    rows.forEach((row, index) => {
      if (index === rows.length - 1 || width < 640 && index % 3 !== 2) return;
      const y = row.bottom + 12;
      const start = index % 2 ? .73 : .25;
      const end = index % 2 ? .26 : .74;
      trail(page,
        `M ${at(start)} ${y - 2}
         C ${at(start + (end - start) * .35)} ${y + 9} ${at(start + (end - start) * .65)} ${y - 9} ${at(end)} ${y + 2}`,
        `quest-hop-${index}`);
    });
    trail(page,
      `M ${at(.76)} ${adventures.bottom - 72}
       C ${at(.64)} ${adventures.bottom - 8} ${at(.32)} ${adventures.bottom - 76} ${at(.25)} ${adventures.bottom}`,
      "quests-to-steps");

    // Cross the photographs lightly, above their sky/foliage, away from faces.
    const heroSvg = canvas(main.querySelector(".hero-visual"), visual.width, visual.height);
    const picture = box(main.querySelector(".hero-visual picture"));
    const photoTop = picture.top - visual.top;
    trail(heroSvg,
      `M ${visual.width * .08} ${photoTop - 18}
       C ${visual.width * .22} ${photoTop - 68} ${visual.width * .48} ${photoTop - 62} ${visual.width * .52} ${photoTop - 31}
       C ${visual.width * .57} ${photoTop - 6} ${visual.width * .42} ${photoTop - 8} ${visual.width * .47} ${photoTop - 36}
       S ${visual.width * .76} ${photoTop - 67} ${visual.width * .86} ${photoTop - 19}
       Q ${visual.width * .94} ${photoTop + 11} ${visual.width * .72} ${photoTop + 25}`,
      "hero-discovery", visual.top);

    // Steps have their own canvas because their existing pale background is opaque.
    const stepBoxes = [...steps.children].map(layoutBox);
    const stepSvg = canvas(how, width, howBox.height);
    const relative = r => ({ ...r, top: r.top - howBox.top, bottom: r.bottom - howBox.top });
    const s = stepBoxes.map(relative);
    trail(stepSvg,
      `M ${at(.25)} 0 C ${at(.3)} 58 ${at(.52)} 12 ${at(.68)} 48`,
      "steps-entry", howBox.top);
    for (let i = 0; i < s.length - 1; i++) {
      const a = s[i], b = s[i + 1];
      let d;
      if (wide) {
        const x1 = (a.left + a.right) / 2, x2 = (b.left + b.right) / 2;
        d = `M ${x1} ${a.bottom + 7} C ${x1 + 45} ${a.bottom + 42} ${x2 - 45} ${b.bottom + 42} ${x2} ${b.bottom + 7}`;
      } else {
        const x = a.right - 30;
        d = `M ${x} ${a.bottom + 7} C ${x + 18} ${a.bottom + 12} ${x - 18} ${b.top - 12} ${x} ${b.top - 7}`;
      }
      stepLinks.push(trail(stepSvg, d, `step-${i + 1}-to-${i + 2}`, howBox.top, "step"));
    }
    const last = s[s.length - 1];
    const tailX = wide ? (last.left + last.right) / 2 : last.right - 30;
    trail(stepSvg,
      `M ${tailX} ${last.bottom + 7} C ${tailX + 20} ${last.bottom + 48} ${at(.61)} ${howBox.height - 31} ${at(.73)} ${howBox.height}`,
      "steps-exit", howBox.top);
    if (stepsSeen || motion.matches) stepLinks.forEach(segment => show(segment, 1));

    trail(page,
      `M ${at(.73)} ${howBox.bottom}
       C ${at(.82)} ${why.top + 53} ${at(.59)} ${why.top + 22} ${at(.51)} ${why.top + 62}`,
      "together-entry");
    const togetherPhoto = box(main.querySelector(".why-photo picture"));
    trail(page,
      `M ${togetherPhoto.left + togetherPhoto.width * .13} ${togetherPhoto.top + 19}
       C ${togetherPhoto.left + togetherPhoto.width * .35} ${togetherPhoto.top + 53}
         ${togetherPhoto.left + togetherPhoto.width * .63} ${togetherPhoto.top + 3}
         ${togetherPhoto.left + togetherPhoto.width * .88} ${togetherPhoto.top + 26}`,
      "together-photo");
    trail(page,
      `M ${at(.2)} ${why.bottom - 72}
       C ${at(.29)} ${why.bottom - 14} ${at(.64)} ${why.bottom - 77} ${width / 2} ${end.top}`,
      "destination");
    const finish = canvas(finale, width, end.height);
    trail(finish, `M ${width / 2} 0 Q ${width / 2 - 17} 12 ${width / 2} 23`, "finish-line", end.top);
    const flag = svgNode("g", { transform: `translate(${width / 2 - 10} 20)`, class: "finish-flag" });
    flag.append(
      svgNode("path", { d: "M2 25V0M3 1C9-4 15 6 23 1V15C15 20 9 10 3 15", fill: "none", stroke: "currentColor", "stroke-width": 2.2, "stroke-linecap": "round", "stroke-linejoin": "round" }),
      svgNode("path", { d: "M4 2L9 2L9 7L4 7ZM14 4L19 4L19 9L14 9ZM9 8L14 8L14 13L9 13ZM19 9L23 8L23 13L19 14Z", fill: "currentColor", opacity: .65 })
    );
    finish.append(flag);
    // Commit four complete SVGs together after all layout measurements.
    // Detached path geometry avoids forced layout for every footprint/sample.
    layerHosts.forEach((host, svg) => host.prepend(svg));
    update();
  }
  function queueDraw() { if (!drawingFrame) drawingFrame = requestAnimationFrame(draw); }

  // A single spring return on release, not a perpetual wiggle.
  cards.forEach((card, index) => {
    let hovered = false;
    let settling;
    let tapTimer;
    function cancelSpring() { if (settling) settling.cancel(); }
    function settle() {
      if (motion.matches || hovered || card.contains(document.activeElement) || !card.animate) return;
      cancelSpring();
      const initial = getComputedStyle(card).transform;
      const sign = index % 2 ? 1 : -1;
      settling = card.animate([
        { transform: initial, offset: 0 },
        { transform: `translateY(2px) rotate(${-sign * .55}deg)`, offset: .56 },
        { transform: `translateY(-1px) rotate(${sign * .18}deg)`, offset: .8 },
        { transform: "none", offset: 1 }
      ], { duration: 580, easing: "cubic-bezier(.22,1,.36,1)" });
    }
    card.addEventListener("pointerenter", event => {
      if (event.pointerType === "mouse" && finePointer.matches) { hovered = true; cancelSpring(); }
    });
    card.addEventListener("pointerleave", () => { const wasHovered = hovered; hovered = false; if (wasHovered) settle(); });
    card.addEventListener("focusin", cancelSpring);
    card.addEventListener("focusout", () => queueMicrotask(settle));
    card.addEventListener("pointerdown", event => {
      if (event.pointerType !== "touch" || motion.matches) return;
      clearTimeout(tapTimer);
      card.classList.add("is-tapped");
      tapTimer = setTimeout(() => card.classList.remove("is-tapped"), 220);
    }, { passive: true });
    card.addEventListener("pointercancel", () => card.classList.remove("is-tapped"), { passive: true });
  });
  if ("IntersectionObserver" in window) {
    const observer = new IntersectionObserver(entries => {
      entries.forEach(entry => {
        if (!entry.isIntersecting) return;
        if (entry.target === steps && !stepsSeen) animateSteps();
        if (entry.target === finale && !finishSeen) {
          finishSeen = true;
          finale.classList.add("destination-arrived");
        }
        observer.unobserve(entry.target);
      });
    }, { threshold: .12 });
    observer.observe(steps);
    observer.observe(finale);
  } else {
    stepsSeen = true;
    finishSeen = true;
  }
  window.addEventListener("scroll", scrollTick, { passive: true });
  window.addEventListener("resize", queueDraw, { passive: true });
  if ("ResizeObserver" in window) {
    new ResizeObserver(entries => {
      const { width, height } = entries[0].contentRect;
      if (Math.abs(width - drawnWidth) > 1 || Math.abs(height - drawnHeight) > 1) queueDraw();
    }).observe(main);
  }
  motion.addEventListener("change", () => {
    if (motion.matches) {
      cards.forEach(card => card.getAnimations().forEach(animation => animation.cancel()));
      segments.forEach(segment => segment.reveal.getAnimations().forEach(animation => animation.cancel()));
    }
    queueDraw();
  });
  draw();
})();
