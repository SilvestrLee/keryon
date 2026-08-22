export const PLAYWRIGHT_VERSION = '1.62.1';
export const CHROMIUM_REVISION = '1234';
export const CHROMIUM_VERSION = '151.0.7922.34';
export const TEMPLATE_KEY = 'sunday-modern-reference';
export const TEMPLATE_VERSION = 1;
export const RENDER_TIMEOUT_MS = 30_000;

export const FORMATS = Object.freeze({
    square: Object.freeze({ width: 1080, height: 1080 }),
    portrait: Object.freeze({ width: 1080, height: 1350 }),
    story: Object.freeze({ width: 1080, height: 1920 }),
});

export const FONT_MAPPING = Object.freeze({
    inter: 'Arial, Helvetica, sans-serif',
    geist: 'Arial, Helvetica, sans-serif',
    playfair_display: 'Georgia, Times New Roman, serif',
    merriweather: 'Georgia, Times New Roman, serif',
    source_serif: 'Georgia, Times New Roman, serif',
});

const SLOT_KEYS = new Set(['title', 'date', 'time', 'theme', 'scripture', 'speaker', 'cta']);
const BRAND_KEYS = new Set(['background', 'primary_text', 'emphasis', 'accent', 'cta_background', 'cta_text', 'heading_font', 'body_font']);
const MEDIA_KEYS = new Set(['background', 'speaker']);

function exactKeys(value, allowed, label) {
    for (const key of Object.keys(value)) {
        if (!allowed.has(key)) throw new Error(`unsupported_${label}_key`);
    }
}

function boundedText(value, maximum, label) {
    if (typeof value !== 'string' || value.length > maximum) throw new Error(`invalid_${label}`);
    return value;
}

export function validatePayload(payload) {
    if (!payload || typeof payload !== 'object' || Array.isArray(payload)) throw new Error('invalid_payload');
    exactKeys(payload, new Set(['templateKey', 'templateVersion', 'variant', 'format', 'identity', 'slots', 'brand', 'media']), 'payload');
    if (payload.templateKey !== TEMPLATE_KEY || payload.templateVersion !== TEMPLATE_VERSION) throw new Error('unsupported_template');
    if (!['default', 'minimal'].includes(payload.variant)) throw new Error('unsupported_variant');
    if (!FORMATS[payload.format]) throw new Error('unsupported_format');
    if (!payload.identity || typeof payload.identity !== 'object' || Array.isArray(payload.identity)) throw new Error('invalid_identity');
    exactKeys(payload.identity, new Set(['churchName']), 'identity');
    boundedText(payload.identity.churchName, 120, 'church_name');
    if (!payload.slots || typeof payload.slots !== 'object' || Array.isArray(payload.slots)) throw new Error('invalid_slots');
    exactKeys(payload.slots, SLOT_KEYS, 'slot');
    for (const [key, value] of Object.entries(payload.slots)) boundedText(value, 160, `slot_${key}`);
    if (!payload.slots.title || !payload.slots.date || !payload.slots.time) throw new Error('missing_required_slot');
    if (!payload.brand || typeof payload.brand !== 'object' || Array.isArray(payload.brand)) throw new Error('invalid_brand');
    exactKeys(payload.brand, BRAND_KEYS, 'brand');
    for (const key of ['background', 'primary_text', 'emphasis', 'accent', 'cta_background', 'cta_text']) {
        if (!/^#[0-9a-fA-F]{6}$/.test(payload.brand[key] ?? '')) throw new Error('invalid_brand_color');
    }
    for (const key of ['heading_font', 'body_font']) {
        if (!FONT_MAPPING[payload.brand[key]]) throw new Error('unsupported_font');
    }
    if (!payload.media || typeof payload.media !== 'object' || Array.isArray(payload.media)) throw new Error('invalid_media');
    exactKeys(payload.media, MEDIA_KEYS, 'media');
    for (const [key, media] of Object.entries(payload.media)) {
        if (!media || typeof media !== 'object' || !['image/png', 'image/jpeg', 'image/webp'].includes(media.mimeType)) throw new Error(`unsupported_media_${key}`);
        exactKeys(media, new Set(['mimeType', 'base64']), 'media_property');
        if (typeof media.base64 !== 'string' || media.base64.length > 20_000_000 || !/^[A-Za-z0-9+/]*={0,2}$/.test(media.base64)) throw new Error(`invalid_media_${key}`);
    }
    return payload;
}
