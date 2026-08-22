import assert from 'node:assert/strict';
import test from 'node:test';
import { FONT_MAPPING, validatePayload } from '../lib/contract.mjs';
import { renderDocument } from '../lib/template.mjs';

const fixture = (format = 'square') => ({ templateKey: 'sunday-modern-reference', templateVersion: 1, variant: 'default', format, identity: { churchName: 'Grace Community Church' }, slots: { title: 'Sunday Encounter', date: '23 August 2026', time: '9:30 AM' }, brand: { background: '#173F35', primary_text: '#FFFFFF', emphasis: '#F3C969', accent: '#F3C969', cta_background: '#F3C969', cta_text: '#111827', heading_font: 'playfair_display', body_font: 'inter' }, media: {} });

test('tenant strings are escaped and never become executable markup', () => {
    const payload = fixture();
    payload.slots.title = '<script>globalThis.pwned=true</script><style>body{display:none}</style>';
    const html = renderDocument(payload);
    assert.doesNotMatch(html, /<script>globalThis/);
    assert.doesNotMatch(html, /<style>body/);
    assert.match(html, /&lt;script&gt;/);
});

test('arbitrary URLs, paths, CSS, JavaScript and unsupported media are rejected', () => {
    for (const mutation of [
        p => { p.url = 'https://example.com'; },
        p => { p.path = '/etc/passwd'; },
        p => { p.css = 'body{}'; },
        p => { p.javascript = 'alert(1)'; },
        p => { p.media.background = { mimeType: 'image/svg+xml', base64: 'PHN2Zz4=' }; },
        p => { p.media.background = { mimeType: 'image/png', base64: 'javascript:alert(1)' }; },
    ]) {
        const payload = fixture(); mutation(payload);
        assert.throws(() => validatePayload(payload));
    }
});

test('formats are separate compositions and font choices have licensed system fallbacks', () => {
    const documents = ['square', 'portrait', 'story'].map(format => renderDocument(fixture(format)));
    assert.equal(new Set(documents).size, 3);
    assert.deepEqual(Object.keys(FONT_MAPPING), ['inter', 'geist', 'playfair_display', 'merriweather', 'source_serif']);
});
