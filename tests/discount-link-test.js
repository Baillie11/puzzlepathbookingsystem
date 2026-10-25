// npm install --no-save --package-lock=false jsdom jquery@3
// node tests/discount-link-test.js
const assert = require('node:assert/strict');
const fs = require('node:fs');
const { JSDOM } = require('jsdom');
const capture = fs.readFileSync(__dirname + '/../js/discount-link.js', 'utf8');
const booking = fs.readFileSync(__dirname + '/../js/booking-form.js', 'utf8');
const key = 'puzzlepath.discount.v1';
function page(query = '', stored, blocked = false) {
    const dom = new JSDOM(`<form id="booking-form">
      <select id="event_id"><option value="">Choose</option>
      <option value="1" data-price="40" data-seats="20">First</option>
      <option value="2" data-price="60" data-seats="20">Second</option></select>
      <input id="tickets" value="1"><input id="coupon_code">
      <button type="button" id="apply-coupon">Apply Coupon</button>
      <p id="coupon-message"></p><span id="subtotal"></span>
      <div id="discount-line"><span id="discount"></span></div><span id="total"></span>
      <button type="button" id="submit-payment">Book</button></form>`,
      { url: 'https://puzzlepath.com.au/' + query, runScripts: 'outside-only' });
    if (stored) dom.window.localStorage.setItem(key, stored);
    if (blocked) Object.defineProperty(dom.window, 'localStorage', { get() { throw Error('Blocked'); } });
    dom.window.eval(capture);
    return dom;
}
async function checkout(dom, repeat = false) {
    const w = dom.window;
    const $ = require('jquery')(w);
    w.$ = w.jQuery = $;
    w.puzzlepath_data = { ajax_url: '/ajax', coupon_nonce: 'fixture' };
    if (repeat) w.ppRepeatHunter = { code: 'RH-test' };
    const requests = [];
    $.ajax = options => requests.push(options);
    w.eval(booking);
    await new Promise(resolve => w.setTimeout(resolve, 20));
    return { $, requests, w };
}
function success(request, percent = 25) {
    request.success({ success: true, data: { discount_percent: percent } });
    request.complete();
}
(async () => {
    const home = page('?discount=BROADBEACHCOLLECTIVE');
    assert.equal(home.window.ppDiscountLink.get(), 'BROADBEACHCOLLECTIVE');
    const stored = home.window.localStorage.getItem(key);
    const { $, requests, w } = await checkout(page('', stored));
    $('#event_id').val('1').trigger('change');
    assert.equal(requests[0].data.coupon_code, 'BROADBEACHCOLLECTIVE');
    const click = new w.MouseEvent('click', { bubbles: true, cancelable: true });
    w.document.getElementById('submit-payment').dispatchEvent(click);
    assert.equal(click.defaultPrevented, true, 'Payment must wait for validation');
    success(requests[0]);
    assert.equal($('#total').text(), '30.00');
    $('#tickets').val('2').trigger('input');
    assert.equal($('#total').text(), '60.00');
    $('#event_id').val('2').trigger('change');
    success(requests[1]);
    assert.equal($('#total').text(), '90.00');
    // A late response from a previously selected quest cannot restore a stale discount.
    $('#event_id').val('1').trigger('change');
    $('#event_id').val('2').trigger('change');
    success(requests[2], 100);
    assert.equal($('#total').text(), '120.00');
    requests[3].success({ success: false, data: { message: 'Expired voucher' } });
    requests[3].complete();
    assert.equal($('#coupon-message').text(), 'Expired voucher');
    const denied = new w.MouseEvent('click', { bubbles: true, cancelable: true });
    w.document.getElementById('submit-payment').dispatchEvent(denied);
    assert.equal(denied.defaultPrevented, true);
    $('button').filter((i, el) => el.textContent === 'Remove voucher').trigger('click');
    assert.equal(w.ppDiscountLink.get(), '');
    assert.equal($('#coupon_code').val(), '');
    $('#event_id').val('1').trigger('change');
    assert.equal(requests.length, 4);
    assert.equal(page('', JSON.stringify({ code: 'OLD', expires: 1 })).window.ppDiscountLink.get(), '');
    assert.equal(page('?discount=NEW', stored).window.ppDiscountLink.get(), 'NEW');
    assert.equal(page('?discount=', stored).window.ppDiscountLink.get(), '');
    assert.equal(page('?discount=RH-private').window.ppDiscountLink.get(), '');
    assert.equal(page('?discount=%3Cscript%3E').window.ppDiscountLink.get(), '');
    assert.equal(page('?discount=CODE', null, true).window.ppDiscountLink.get(), 'CODE');
    const repeat = await checkout(page('', stored), true);
    repeat.$('#event_id').val('1').trigger('change');
    assert.equal(repeat.requests.length, 0, 'QR voucher must not override Repeat Hunter');
    const preselected = page('', stored);
    preselected.window.document.getElementById('event_id').value = '1';
    const initial = await checkout(preselected);
    assert.equal(initial.requests.length, 1);
    success(initial.requests[0], 100);
    assert.equal(initial.w.puzzlepathCurrentTotal, 0);
    console.log('PASS: QR capture, persistence, expiry, replacement, removal, blocked storage, quest changes, stale responses, payment guards, preselection, free bookings and Repeat Hunter isolation.');
})().catch(error => { console.error(error); process.exitCode = 1; });
