import { renderPng } from './lib/render.mjs';

const FAILURE_CODES = new Set(['invalid_payload', 'unsupported_payload_key', 'unsupported_template', 'unsupported_variant', 'unsupported_format', 'invalid_slots', 'unsupported_slot_key', 'missing_required_slot', 'invalid_brand', 'unsupported_brand_key', 'invalid_brand_color', 'unsupported_font', 'invalid_media', 'unsupported_media_key', 'unsupported_media_property_key']);

async function input() {
    const chunks = [];
    for await (const chunk of process.stdin) chunks.push(chunk);
    if (Buffer.concat(chunks).length > 25_000_000) throw new Error('payload_too_large');
    return JSON.parse(Buffer.concat(chunks).toString('utf8'));
}

try {
    const payload = await input();
    const result = await renderPng(payload);
    process.stdout.write(JSON.stringify({ ok: true, png: result.png.toString('base64'), width: 1080, height: result.png.readUInt32BE(20), evidence: result.evidence }));
} catch (error) {
    const candidate = error instanceof SyntaxError ? 'invalid_json' : String(error?.message ?? 'renderer_failed');
    const code = FAILURE_CODES.has(candidate) || candidate.startsWith('invalid_') || candidate.startsWith('unsupported_') ? candidate : 'renderer_runtime_failed';
    process.stdout.write(JSON.stringify({ ok: false, code }));
    process.exitCode = 1;
}
