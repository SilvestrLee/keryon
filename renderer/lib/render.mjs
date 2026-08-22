import { chromium } from 'playwright';
import { CHROMIUM_REVISION, CHROMIUM_VERSION, FORMATS, PLAYWRIGHT_VERSION, RENDER_TIMEOUT_MS } from './contract.mjs';
import { renderDocument } from './template.mjs';

export async function renderPng(payload) {
    const html = renderDocument(payload);
    const dimensions = FORMATS[payload.format];
    const browser = await chromium.launch({ headless: true, timeout: RENDER_TIMEOUT_MS });
    try {
        const actualVersion = browser.version();
        if (actualVersion !== CHROMIUM_VERSION) throw new Error('browser_version_mismatch');
        const page = await browser.newPage({ viewport: dimensions, deviceScaleFactor: 1 });
        await page.route(/^https?:\/\//i, route => route.abort('blockedbyclient'));
        await page.setContent(html, { waitUntil: 'load', timeout: RENDER_TIMEOUT_MS });
        const png = await page.screenshot({ type: 'png', fullPage: false, animations: 'disabled', timeout: RENDER_TIMEOUT_MS });
        return { png, evidence: { nodeVersion: process.version, playwrightVersion: PLAYWRIGHT_VERSION, chromiumRevision: CHROMIUM_REVISION, chromiumVersion: actualVersion, templateKey: payload.templateKey, templateVersion: payload.templateVersion, format: payload.format, fontMapping: payload.brand } };
    } finally {
        await browser.close();
    }
}
