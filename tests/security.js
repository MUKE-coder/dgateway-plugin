/* Standalone frontend regressions: node tests/security.js */
'use strict';
const assert = require('node:assert/strict');
const fs = require('node:fs');
const vm = require('node:vm');
const path = require('node:path');
const escapeText = text => String(text).replace(/&/g, '&amp;').replace(/</g, '&lt;').replace(/>/g, '&gt;');
const elements = [];
const document = {
    createElement() {
        const element = {
            innerHTML: '',
            addEventListener() {},
            appendChild(child) { if ('text' in child) this.innerHTML += escapeText(child.text); },
            querySelector() { return null; },
            querySelectorAll() { return []; },
            remove() {},
        };
        elements.push(element);
        return element;
    },
    createTextNode(text) { return { text }; },
    addEventListener() {},
};
const context = { document, window: { location: { href: 'https://shop.example.test/checkout/' } }, URL };
vm.runInNewContext(fs.readFileSync(path.join(__dirname, '../assets/js/dgw-modal.js'), 'utf8'), context);
const modal = context.window.DGW_Modal;
modal._config = { amount: 100, currency: '<img src=x onerror=alert(1)>' };
modal._state = 'method_select';
modal._render();
const html = elements.map(element => element.innerHTML).find(value => value.includes('dgw-modal-amount-value'));
assert.ok(html.includes('&lt;img src=x onerror=alert(1)&gt;'), 'Currency must be rendered as text');
assert.ok(!html.includes('<img'), 'Currency must not create an HTML element');
for (const value of ['javascript:alert(1)', 'JaVaScRiPt:alert(1)', 'data:text/html,test', '\n javascript:alert(1)', 'javascript\t:alert(1)']) {
    assert.equal(modal._safeRedirectUrl(value), '', 'Executable URL scheme must be rejected');
}
assert.equal(modal._safeRedirectUrl('/thanks'), 'https://shop.example.test/thanks');
assert.equal(modal._safeRedirectUrl('https://example.test/thanks'), 'https://example.test/thanks');

// Exercise the actual admin AJAX response handler with an upstream error.
const handlers = {};
const output = {};
let ajaxDone;
function jquery(selector) {
    return {
        length: 0,
        on(event, fn) { handlers[selector + ':' + event] = fn; return this; },
        prop() { return this; },
        text() { return this; },
        html(value) { output[selector] = value; return this; },
        val() { return ''; },
    };
}
jquery.post = function () {
    const chain = {
        done(fn) { ajaxDone = fn; return chain; },
        fail() { return chain; },
        always() { return chain; },
    };
    return chain;
};
vm.runInNewContext(fs.readFileSync(path.join(__dirname, '../assets/js/dgw-admin.js'), 'utf8'), {
    jQuery: jquery, document, window: {}, dgwAdmin: { ajaxUrl: '/ajax', nonce: 'test' },
});
handlers['#dgw-test-connection:click']();
ajaxDone({ success: false, data: { message: '<img src=x onerror=alert(1)>' } });
assert.ok(output['#dgw-test-result'].includes('&lt;img'));
assert.ok(!output['#dgw-test-result'].includes('<img'));
console.log('Passed frontend security regressions.');
