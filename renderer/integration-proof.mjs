import assert from 'node:assert/strict';
import { mkdir, readFile, rm, writeFile } from 'node:fs/promises';
import { tmpdir } from 'node:os';
import { join } from 'node:path';
import { chromium } from 'playwright';
import { CHROMIUM_REVISION, CHROMIUM_VERSION, FONT_MAPPING, FORMATS, PLAYWRIGHT_VERSION } from './lib/contract.mjs';
import { renderPng } from './lib/render.mjs';

const outputDirectory = process.env.DESIGN_RENDER_ARTIFACT_DIR ?? join(tmpdir(), `keryon-render-proof-${process.pid}`);
await mkdir(outputDirectory, { recursive: true });
const fixture = format => ({ templateKey: 'sunday-modern-reference', templateVersion: 1, variant: 'default', format, identity: { churchName: 'Grace Community Church' }, slots: { title: 'Sunday Encounter', date: '23 August 2026', time: '9:30 AM', theme: 'A Place to Belong', scripture: 'Psalm 100:2', speaker: 'Pastor Jordan Reed', cta: 'Join us this Sunday' }, brand: { background: '#173F35', primary_text: '#FFFFFF', emphasis: '#F3C969', accent: '#F3C969', cta_background: '#F3C969', cta_text: '#111827', heading_font: 'playfair_display', body_font: 'inter' }, media: {} });

try {
    assert.match(chromium.executablePath(), new RegExp(`chromium-${CHROMIUM_REVISION}`));
    const results = [];
    for (const [format, dimensions] of Object.entries(FORMATS)) {
        const { png, evidence } = await renderPng(fixture(format));
        assert.equal(png.readUInt32BE(16), dimensions.width);
        assert.equal(png.readUInt32BE(20), dimensions.height);
        const path = join(outputDirectory, `${format}.png`);
        await writeFile(path, png);
        const saved = await readFile(path);
        assert.equal(saved.readUInt32BE(16), dimensions.width);
        assert.equal(saved.readUInt32BE(20), dimensions.height);
        results.push({ format, ...dimensions, bytes: saved.length, evidence });
    }
    console.log(JSON.stringify({ ok: true, nodeVersion: process.version, playwrightVersion: PLAYWRIGHT_VERSION, chromiumRevision: CHROMIUM_REVISION, chromiumVersion: CHROMIUM_VERSION, fontMapping: FONT_MAPPING, outputs: results }, null, 2));
} catch (error) {
    if (!process.env.DESIGN_RENDER_ARTIFACT_DIR) await rm(outputDirectory, { recursive: true, force: true });
    throw error;
}
