// Helpers for the encoder tests: the reference encoder (the qrcode package)
// and a decoder (jsQR), both working on the same module matrix as LadyQR's.
import QRCode from 'qrcode';
import Version from 'qrcode/lib/core/version.js';
import Mode from 'qrcode/lib/core/mode.js';
import ECLevel from 'qrcode/lib/core/error-correction-level.js';
import jsQR from 'jsqr';

const MODES = { numeric: Mode.NUMERIC, alphanumeric: Mode.ALPHANUMERIC, byte: Mode.BYTE };

/**
 * The most characters a version holds in one mode, from the reference.
 */
export function capacity(version, level, mode) {
	return Version.getCapacity(version, ECLevel.from(level), MODES[mode]);
}

/**
 * The reference symbol for the text in one mode, with the given mask.
 * Returns the matrix as rows of booleans, like encode() does.
 */
export function reference(text, level, mode, mask) {
	const data = 'byte' === mode ? new TextEncoder().encode(text) : text;
	const qr = QRCode.create([{ data, mode }], { errorCorrectionLevel: level, maskPattern: mask });
	const size = qr.modules.size;
	const modules = Array.from({ length: size }, (_, y) => Array.from({ length: size }, (_, x) => 1 === qr.modules.data[y * size + x]));
	return { version: qr.version, size, mask: qr.maskPattern, modules };
}

/**
 * Decodes a matrix with jsQR, drawn at 4 px per module with a 4-module margin.
 * Returns the text, or null if it cannot be read.
 */
export function decode(modules) {
	const scale = 4;
	const quiet = 4;
	const side = (modules.length + quiet * 2) * scale;
	const pixels = new Uint8ClampedArray(side * side * 4).fill(255);
	modules.forEach((row, y) => row.forEach((dark, x) => {
		if (!dark) {
			return;
		}
		for (let dy = 0; dy < scale; dy++) {
			for (let dx = 0; dx < scale; dx++) {
				const i = (((y + quiet) * scale + dy) * side + (x + quiet) * scale + dx) * 4;
				pixels[i] = pixels[i + 1] = pixels[i + 2] = 0;
			}
		}
	}));
	const result = jsQR(pixels, side, side, { inversionAttempts: 'dontInvert' });
	return result ? new TextDecoder().decode(new Uint8Array(result.binaryData)) : null;
}
