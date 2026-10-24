// Generates a QR code as an SVG file with LadyQR's own encoder.
//
// Usage: node tools/make-qr.mjs <text> <output.svg> [level] [margin]
//
// Example: node tools/make-qr.mjs https://ladyqr.nilovelez.com/ site/qr.svg L 4

import { writeFileSync } from 'node:fs';
import { encode } from '../assets/qr.js';
import { toSvg } from '../assets/render.js';

const [text, output, level = 'M', margin = '4'] = process.argv.slice(2);
if (!text || !output) {
	console.error('Usage: node tools/make-qr.mjs <text> <output.svg> [level] [margin]');
	process.exit(1);
}

const { modules, version } = encode(text, level);
writeFileSync(output, toSvg(modules, { margin: Number(margin), fg: '#1d1a1b', bg: '#ffffff', size: 512 }) + '\n');
console.log(`${output}: version ${version}, level ${level}, ${modules.length} x ${modules.length} modules`);
