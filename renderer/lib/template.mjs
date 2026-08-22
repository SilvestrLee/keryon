import { FONT_MAPPING, FORMATS, TEMPLATE_KEY, TEMPLATE_VERSION, validatePayload } from './contract.mjs';

export function escapeHtml(value) {
    return String(value).replace(/[&<>"']/g, character => ({ '&': '&amp;', '<': '&lt;', '>': '&gt;', '"': '&quot;', "'": '&#039;' })[character]);
}

function imageData(media) {
    return media ? `data:${media.mimeType};base64,${media.base64}` : '';
}

function layout(format) {
    if (format === 'square') return { pad: 82, title: 112, contentTop: 310, footerBottom: 74, panelWidth: 760 };
    if (format === 'portrait') return { pad: 88, title: 124, contentTop: 405, footerBottom: 88, panelWidth: 820 };
    return { pad: 90, title: 136, contentTop: 610, footerBottom: 110, panelWidth: 850 };
}

export function renderDocument(untrustedPayload) {
    const payload = validatePayload(untrustedPayload);
    const dimensions = FORMATS[payload.format];
    const composition = layout(payload.format);
    const slots = Object.fromEntries(Object.entries(payload.slots).map(([key, value]) => [key, escapeHtml(value)]));
    const churchName = escapeHtml(payload.identity.churchName);
    const brand = payload.brand;
    const background = imageData(payload.media.background);
    const speaker = imageData(payload.media.speaker);
    const headingFont = FONT_MAPPING[brand.heading_font];
    const bodyFont = FONT_MAPPING[brand.body_font];
    const backgroundLayer = background ? `<img class="background" src="${background}" alt="">` : '';
    const speakerLayer = speaker ? `<img class="speaker" src="${speaker}" alt="">` : '';
    const optional = [slots.theme && `<p class="theme">${slots.theme}</p>`, slots.scripture && `<p class="scripture">${slots.scripture}</p>`, slots.speaker && `<p class="speaker-name">${slots.speaker}</p>`].filter(Boolean).join('');
    const cta = slots.cta ? `<p class="cta">${slots.cta}</p>` : '';

    return `<!doctype html><html><head><meta charset="utf-8"><style>
*{box-sizing:border-box}html,body{margin:0;width:${dimensions.width}px;height:${dimensions.height}px;overflow:hidden}body{font-family:${bodyFont};background:${brand.background};color:${brand.primary_text}}
.canvas{position:relative;width:100%;height:100%;isolation:isolate;padding:${composition.pad}px}.background{position:absolute;inset:0;width:100%;height:100%;object-fit:cover;z-index:-3}.canvas:before{content:"";position:absolute;inset:0;background:${background ? 'rgba(0,0,0,.52)' : brand.background};z-index:-2}
.rule{width:112px;height:12px;background:${brand.emphasis};margin-bottom:40px}.content{position:absolute;left:${composition.pad}px;top:${composition.contentTop}px;width:${composition.panelWidth}px}.title{font-family:${headingFont};font-size:${composition.title}px;line-height:.94;letter-spacing:-.045em;margin:0 0 34px;overflow-wrap:break-word}.theme,.scripture,.speaker-name{font-size:32px;line-height:1.25;margin:12px 0;max-width:720px}.theme{text-transform:uppercase;letter-spacing:.13em;font-size:22px;color:${brand.emphasis}}
.schedule{position:absolute;left:${composition.pad}px;bottom:${composition.footerBottom}px;display:flex;gap:32px;align-items:center;font-weight:700;font-size:30px}.schedule span+span{border-left:3px solid ${brand.emphasis};padding-left:32px}.cta{position:absolute;right:${composition.pad}px;bottom:${composition.footerBottom}px;margin:0;padding:18px 25px;background:${brand.cta_background};color:${brand.cta_text};font-weight:700;font-size:22px;text-transform:uppercase;letter-spacing:.08em}.speaker{position:absolute;right:0;bottom:0;height:67%;width:45%;object-fit:cover;object-position:top center;z-index:-1;mask-image:linear-gradient(to left,#000 72%,transparent)}
.identity{position:absolute;top:${composition.pad}px;right:${composition.pad}px;font-size:20px;font-weight:700;letter-spacing:.14em;text-transform:uppercase;max-width:430px;text-align:right}
</style></head><body><main class="canvas" data-template="${TEMPLATE_KEY}" data-template-version="${TEMPLATE_VERSION}" data-format="${payload.format}">${backgroundLayer}${speakerLayer}<div class="identity">${churchName}</div><section class="content"><div class="rule"></div><h1 class="title">${slots.title}</h1>${optional}</section><div class="schedule"><span>${slots.date}</span><span>${slots.time}</span></div>${cta}</main></body></html>`;
}
