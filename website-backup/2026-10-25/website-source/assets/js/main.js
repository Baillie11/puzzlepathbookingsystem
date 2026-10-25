/* Progressive enhancement only. Booking links work without this file. */
(() => {
  "use strict";
  const campaign = "brochure_dl_2026";
  // Only known, non-personal distribution identifiers are forwarded.
  const placements = new Set(["hotel", "cafe", "tourist_information", "other"]);
  const requestedPlacement = new URLSearchParams(location.search).get("placement");
  const placement = placements.has(requestedPlacement) ? requestedPlacement : "unspecified";

  // No analytics service, cookies, storage or beacon is enabled by default.
  // An approved first-party collector may subscribe after privacy review.
  function track(name, questId) {
    if (navigator.doNotTrack === "1" || navigator.globalPrivacyControl === true) return;
    try {
      document.dispatchEvent(new CustomEvent("puzzlepath:campaign", {
        detail: Object.freeze({
          event: name, campaign, placement,
          ...(questId ? { questId: Number(questId) } : {})
        })
      }));
    } catch (_) { /* Measurement must never interfere with navigation. */ }
  }

  document.querySelectorAll(".booking-link").forEach(link => {
    if (placement !== "unspecified") {
      const url = new URL(link.href);
      url.searchParams.set("utm_content", placement);
      link.href = url.href;
    }
    // Never prevent default, wait for analytics, or manipulate payments.
    link.addEventListener("click", () => {
      track("quest_selection", link.dataset.questId);
      track("outbound_booking_click", link.dataset.questId);
    });
    link.addEventListener("auxclick", event => {
      if (event.button === 1) track("outbound_booking_click", link.dataset.questId);
    });
  });

  const button = document.querySelector("[data-copy-coupon]");
  const status = document.getElementById("copy-status");
  if (button && navigator.clipboard && window.isSecureContext) {
    button.hidden = false;
    button.addEventListener("click", async () => {
      try {
        await navigator.clipboard.writeText(document.getElementById("coupon-code").textContent);
        button.textContent = "Copied!";
        status.textContent = "BROCHURE25 copied. Paste it into the coupon field at checkout.";
      } catch (_) {
        status.textContent = "Copy is unavailable. Enter BROCHURE25 manually at checkout.";
        button.textContent = "Copy code";
      }
    });
  }
  track("landing_visit");
})();
