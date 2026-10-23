// The editor: reads the form, builds the QR code and handles downloads.
// Everything happens in the browser; nothing is sent anywhere.

import { encode } from './qr.js';
import { toSvg, toPngBlob } from './render.js';

const $ = (id) => document.getElementById(id);
const strings = JSON.parse($('ladyqr-i18n').textContent);

const content = $('content');
const margin = $('margin');
const fg = $('fg');
const bg = $('bg');
const qr = $('qr');
const downloadMain = $('download-png-500');
const downloadMore = $('download-more');
const menu = $('download-menu');

let level = 'M';
let modules = null;

function options() {
	return { margin: Number(margin.value), fg: fg.value, bg: bg.value };
}

function showMessage(text) {
	const message = document.createElement('div');
	message.className = 'qr-message';
	message.textContent = text;
	qr.replaceChildren(message);
	qr.setAttribute('aria-label', text);
}

function update() {
	$('margin-value').textContent = margin.value;
	modules = null;

	if (content.value === '') {
		showMessage(strings.empty);
	} else {
		try {
			modules = encode(content.value, level).modules;
		} catch (error) {
			if (!(error instanceof RangeError)) {
				throw error;
			}
			showMessage(strings.tooLong);
		}
	}

	if (modules) {
		qr.innerHTML = toSvg(modules, options());
		qr.setAttribute('aria-label', strings.qrAlt);
	}
	downloadMain.disabled = !modules;
	downloadMore.disabled = !modules;
	if (!modules) {
		toggleMenu(false);
	}
}

function save(blob, name) {
	const url = URL.createObjectURL(blob);
	const link = document.createElement('a');
	link.href = url;
	link.download = name;
	document.body.append(link);
	link.click();
	link.remove();
	setTimeout(() => URL.revokeObjectURL(url), 1000);
}

async function downloadPng(size) {
	if (modules) {
		save(await toPngBlob(modules, { size, ...options() }), `qrcode-${size}.png`);
	}
}

function downloadSvg() {
	if (modules) {
		save(new Blob([toSvg(modules, { ...options(), size: 1000 })], { type: 'image/svg+xml' }), 'qrcode.svg');
	}
}

function toggleMenu(open) {
	menu.hidden = !open;
	downloadMore.setAttribute('aria-expanded', open ? 'true' : 'false');
}

for (const input of [content, margin, fg, bg]) {
	input.addEventListener('input', update);
}

$('ecc').addEventListener('click', (event) => {
	const button = event.target.closest('button');
	if (!button) {
		return;
	}
	level = button.dataset.level;
	for (const other of $('ecc').children) {
		other.setAttribute('aria-pressed', other === button ? 'true' : 'false');
	}
	update();
});

downloadMain.addEventListener('click', () => downloadPng(500));
$('download-png-1000').addEventListener('click', () => {
	toggleMenu(false);
	downloadPng(1000);
});
$('download-svg').addEventListener('click', () => {
	toggleMenu(false);
	downloadSvg();
});
downloadMore.addEventListener('click', (event) => {
	event.stopPropagation();
	toggleMenu(menu.hidden);
});
document.addEventListener('click', (event) => {
	if (!menu.contains(event.target)) {
		toggleMenu(false);
	}
});
document.addEventListener('keydown', (event) => {
	if (event.key === 'Escape') {
		toggleMenu(false);
	}
});

const dialog = $('help-dialog');
$('help-open').addEventListener('click', () => dialog.showModal());
$('help-close').addEventListener('click', () => dialog.close());
dialog.addEventListener('click', (event) => {
	if (event.target === dialog) {
		dialog.close();
	}
});

update();
