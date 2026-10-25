// The QR encoder, assets/qr.js, checked against an independent implementation
// (the qrcode package) and a decoder (jsQR).
import { test } from 'node:test';
import assert from 'node:assert/strict';
import { encode } from '../assets/qr.js';
import { capacity, reference, decode } from './lib/qr.mjs';

const LEVELS = ['L', 'M', 'Q', 'H'];

// Characters that only the given mode (or a wider one) can hold. The byte
// set has lowercase letters and punctuation outside the alphanumeric set.
const CHARS = {
	numeric: '0123456789',
	alphanumeric: 'HTTPS://1QR.ES/AB-C $%*+0123456789',
	byte: 'https://example.com/a?b=c&d#e_',
};

// Text of a given length that goes in the given mode.
function textFor(mode, length) {
	const chars = CHARS[mode];
	let text = '';
	for (let i = 0; text.length < length; i++) {
		text += chars[(i * 7) % chars.length];
	}
	// Make sure no narrower mode could take it.
	if ('alphanumeric' === mode) {
		text = 'A' + text.slice(1);
	} else if ('byte' === mode) {
		text = 'a' + text.slice(1);
	}
	return text;
}

for (const mode of ['numeric', 'alphanumeric', 'byte']) {
	test(`${mode} mode: same symbol as the reference, at every version and level`, () => {
		for (const level of LEVELS) {
			for (let version = 1; version <= 40; version++) {
				const text = textFor(mode, capacity(version, level, mode));
				const ours = encode(text, level);
				assert.equal(ours.mode, mode, `${level} v${version}: mode`);
				assert.equal(ours.version, version, `${level} v${version}: version`);
				const ref = reference(text, level, mode, ours.mask);
				assert.deepEqual(ours.modules, ref.modules, `${level} v${version}: modules`);
			}
		}
	});

	test(`${mode} mode: one more character takes the next version`, () => {
		for (const level of LEVELS) {
			for (let version = 1; version < 40; version++) {
				const text = textFor(mode, capacity(version, level, mode) + 1);
				assert.equal(encode(text, level).version, version + 1, `${level} v${version}`);
			}
		}
	});

	test(`${mode} mode: short texts of every length decode back`, () => {
		for (let length = 1; length <= 40; length++) {
			const text = textFor(mode, length);
			const ours = encode(text, 'M');
			assert.equal(ours.mode, mode, text);
			assert.deepEqual(ours.modules, reference(text, 'M', mode, ours.mask).modules, text);
			assert.equal(decode(ours.modules), text);
		}
	});
}

test('the most compact mode is picked', () => {
	const cases = {
		'0123456789': 'numeric',
		'HTTP://1QR.ES/PEPE': 'alphanumeric',
		'1QR.ES/ABC-123': 'alphanumeric',
		'https://1qr.es/pepe': 'byte',
		'1QR.ES/ABC_123': 'byte',
		'A,B': 'byte',
		'Ñ': 'byte',
		'12 34': 'alphanumeric',
		'': 'byte',
	};
	for (const [text, mode] of Object.entries(cases)) {
		assert.equal(encode(text, 'M').mode, mode, JSON.stringify(text));
	}
});

test('version 1-M holds 34 digits, 20 alphanumeric characters or 14 bytes', () => {
	assert.equal(encode('1'.repeat(34), 'M').version, 1);
	assert.equal(encode('1'.repeat(35), 'M').version, 2);
	assert.equal(encode('HTTP://1QR.ES/123456', 'M').version, 1);
	assert.equal(encode('HTTP://1QR.ES/1234567', 'M').version, 2);
	assert.equal(encode('1qr.es/1234567', 'M').version, 1);
	assert.equal(encode('1qr.es/12345678', 'M').version, 2);
});

test('text too long for version 40 is refused', () => {
	for (const mode of ['numeric', 'alphanumeric', 'byte']) {
		assert.throws(() => encode(textFor(mode, capacity(40, 'H', mode) + 1), 'H'), RangeError, mode);
	}
});

test('accents and emoji are encoded as UTF-8 and read back', () => {
	for (const text of ['Ñandú, pingüino y café', 'Hola 👋 🐞', 'https://example.com/?q=año']) {
		assert.equal(decode(encode(text, 'M').modules), text);
	}
});

test('every version decodes back to the text, or fails the same way as the reference', () => {
	for (const level of LEVELS) {
		for (let version = 1; version <= 40; version++) {
			const text = textFor('byte', capacity(version, level, 'byte'));
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
