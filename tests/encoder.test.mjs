// The QR encoder, assets/qr.js, checked against an independent implementation
// (the qrcode package) and a decoder (jsQR).
import { test } from 'node:test';
import assert from 'node:assert/strict';
import { encode } from '../assets/qr.js';
import { capacity, reference, decode } from './lib/qr.mjs';

const LEVELS = ['L', 'M', 'Q', 'H'];

// Text of a given length in UTF-8 bytes, with lowercase letters, digits and
// punctuation so that only byte mode can hold it.
function byteText(length) {
	const chars = 'https://example.com/a?b=c&d#e_';
	let text = '';
	for (let i = 0; text.length < length; i++) {
		text += chars[(i * 7) % chars.length];
	}
	return text;
}

test('byte mode: same symbol as the reference, at every version and level', () => {
	for (const level of LEVELS) {
		for (let version = 1; version <= 40; version++) {
			const text = byteText(capacity(version, level, 'byte'));
			const ours = encode(text, level);
			assert.equal(ours.version, version, `${level} v${version}: version`);
			const ref = reference(text, level, 'byte', ours.mask);
			assert.deepEqual(ours.modules, ref.modules, `${level} v${version}: modules`);
		}
	}
});

test('byte mode: one more byte takes the next version', () => {
	for (const level of LEVELS) {
		for (let version = 1; version < 40; version++) {
			const text = byteText(capacity(version, level, 'byte') + 1);
			assert.equal(encode(text, level).version, version + 1, `${level} v${version}`);
		}
	}
});

test('text too long for version 40 is refused', () => {
	assert.throws(() => encode(byteText(capacity(40, 'H', 'byte') + 1), 'H'), RangeError);
});

test('accents and emoji are encoded as UTF-8 and read back', () => {
	for (const text of ['Ñandú, pingüino y café', 'Hola 👋 🐞', 'https://example.com/?q=año']) {
		assert.equal(decode(encode(text, 'M').modules), text);
	}
});

test('every version decodes back to the text, or fails the same way as the reference', () => {
	for (const level of LEVELS) {
		for (let version = 1; version <= 40; version++) {
			const text = byteText(capacity(version, level, 'byte'));
			const ours = encode(text, level);
			if (decode(ours.modules) !== text) {
				// jsQR cannot read a few large symbols; then it cannot read the
				// reference's identical symbol either.
				assert.equal(decode(reference(text, level, 'byte', ours.mask).modules), null, `${level} v${version}`);
			}
		}
	}
});

test('the level changes the symbol and an unknown level is refused', () => {
	assert.notDeepEqual(encode('LadyQR', 'L').modules, encode('LadyQR', 'H').modules);
	assert.throws(() => encode('LadyQR', 'X'), TypeError);
});
