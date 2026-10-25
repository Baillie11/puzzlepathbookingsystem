/* Capture on every public page, including cached home pages. Never trust a URL for pricing. */
(function (window) {
    'use strict';
    var key = 'puzzlepath.discount.v1';
    var lifetime = 7 * 24 * 60 * 60 * 1000;
    function valid(code) {
        return typeof code === 'string' && code.length > 0 && code.length <= 50 &&
            !/[\x00-\x1f\x7f<>]/.test(code) && !/^RH-/i.test(code);
    }
    var saved = null;
    var persisted = false;
    try {
        saved = JSON.parse(window.localStorage.getItem(key));
        persisted = !!saved;
        if (!saved || !valid(saved.code) || !Number.isFinite(saved.expires) || saved.expires <= Date.now()) {
            saved = null;
            window.localStorage.removeItem(key);
        }
    } catch (ignore) { saved = null; }
    var params = new URL(window.location.href).searchParams;
    // Explicit offer links take priority; recognise untagged Facebook referrals too.
    var facebookReferral = /^(facebook|fb)$/i.test(params.get('utm_source') || '');
    try {
        var refHost = new URL(window.document.referrer).hostname;
        facebookReferral = facebookReferral || /(^|\.)facebook\.com$/i.test(refHost);
    } catch (ignore) {}
    if (!params.has('discount') && !saved && facebookReferral) params.set('discount', 'FACEBOOK10');
    if (params.has('discount')) {
        var code = params.get('discount').trim();
        saved = valid(code) ? { code: code, expires: Date.now() + lifetime } : null;
        try {
            if (saved) {
                window.localStorage.setItem(key, JSON.stringify(saved));
                persisted = true;
            }
            else window.localStorage.removeItem(key);
        } catch (ignore) { persisted = false; /* Current-page application still works without storage. */ }
    }
    window.ppDiscountLink = {
        get: function () { return saved && saved.expires > Date.now() ? saved.code : ''; },
        clear: function () {
            saved = null;
            try { window.localStorage.removeItem(key); } catch (ignore) {}
        }
    };
    function decorateBookingLinks() {
        var code = window.ppDiscountLink.get();
        if (!code) return;
        window.document.querySelectorAll('a[href]').forEach(function (link) {
            var url;
            try { url = new URL(link.href, window.location.href); } catch (ignore) { return; }
            if (url.origin !== window.location.origin || /\/(wp-admin|wp-login\.php|wp-json)(\/|$)/.test(url.pathname)) return;
            // Carry the offer through navigation even when browser storage is unavailable.
            if (!persisted && !url.searchParams.has('discount') && !url.hash) {
                url.searchParams.set('discount', code);
                link.href = url.href;
            }
            if (code.toUpperCase() !== 'FACEBOOK10' || !/\/book-today\/?$/.test(url.pathname) || !/book now/i.test(link.textContent) || url.hash) return;
            if (link.previousElementSibling && link.previousElementSibling.classList.contains('pp-facebook-offer')) return;
            var note = window.document.createElement('p');
            note.className = 'pp-facebook-offer';
            note.style.cssText = 'color:#25315e;font-weight:600;font-size:15px;line-height:1.5;margin:12px 0;text-align:center;';
            note.textContent = 'Facebook offer: 10% off at checkout with FACEBOOK10';
            link.before(note);
        });
    }
    function showSavedVoucher() {
        decorateBookingLinks();
        var document = window.document;
        var facebookOffer = window.ppDiscountLink.get().toUpperCase() === 'FACEBOOK10';
        if (!window.ppDiscountLink.get() ||
            (!facebookOffer && !document.body.classList.contains('home')) ||
            document.getElementById('pp-saved-voucher-notice')) return;
        var notice = document.createElement('div');
        notice.id = 'pp-saved-voucher-notice';
        notice.setAttribute('role', 'status');
        notice.style.cssText = 'box-sizing:border-box;max-width:1120px;margin:24px auto;padding:20px 24px;background:#fff1a6;border:2px solid #6745bd;border-radius:16px;color:#25315e;font-size:17px;line-height:1.5;width:calc(100% - 32px);';
        var title = document.createElement('strong');
        title.style.cssText = 'display:block;font-size:21px;margin-bottom:4px;';
        title.textContent = facebookOffer ? 'Your Facebook offer: 10% off every quest' : 'Your voucher has been saved!';
        var message = document.createElement('span');
        message.textContent = facebookOffer ? 'Choose any hunt and FACEBOOK10 will be applied automatically at checkout. Your offer stays with you as you explore the site.' : 'Choose your preferred quest and your voucher will be applied automatically at checkout. No need to remember or enter the code.';
        notice.appendChild(title);
        notice.appendChild(message);
        var content = document.getElementById('content') || document.querySelector('main');
        // Keep the notice inside the hero's spacing, below the transparent navigation.
        var heroHeading = content && content.querySelector('h1');
        var heroWidget = heroHeading && heroHeading.closest('.elementor-widget-heading');
        if (heroWidget) heroWidget.before(notice);
        else if (content) content.prepend(notice);
    }
    if (window.document.readyState === 'loading') {
        window.document.addEventListener('DOMContentLoaded', showSavedVoucher);
    } else {
        showSavedVoucher();
    }
})(window);
