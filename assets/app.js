// The editor: reads the form, builds the QR code and handles downloads.
// Everything happens in the browser; nothing is sent anywhere.

import { encode } from './qr.js';
import { toSvg, toPngBlob, contrastRatio, isInverted } from './render.js';

// Colors at or below this contrast ratio get a warning.
const LOW_CONTRAST = 4;
const STORAGE_KEY = 'ladyqr-settings';
const DEFAULTS = { level: 'M', margin: 4, fg: '#1d1a1b', bg: '#ffffff' };

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

function setLevel(value) {
	level = value;
	for (const button of $('ecc').children) {
		button.setAttribute('aria-pressed', button.dataset.level === value ? 'true' : 'false');
	}
}

// The settings are remembered in this browser only. The text is never stored.
function saveSettings() {
	try {
		localStorage.setItem(STORAGE_KEY, JSON.stringify({ level, margin: Number(margin.value), fg: fg.value, bg: bg.value }));
	} catch {
		// Storage can be blocked or full; the editor works without it.
	}
}

function resetSettings() {
	setLevel(DEFAULTS.level);
	margin.value = DEFAULTS.margin;
	fg.value = DEFAULTS.fg;
	bg.value = DEFAULTS.bg;
	try {
		localStorage.removeItem(STORAGE_KEY);
	} catch {
		// Nothing to remove if storage is blocked.
	}
	update();
}

function isDefault() {
	return level === DEFAULTS.level && Number(margin.value) === DEFAULTS.margin && fg.value === DEFAULTS.fg && bg.value === DEFAULTS.bg;
}

// One warning for low contrast, inverted colors, or both.
function updateWarning() {
	const low = contrastRatio(fg.value, bg.value) <= LOW_CONTRAST;
	const inverted = isInverted(fg.value, bg.value);
	const key = low && inverted ? 'lowContrastInverted' : low ? 'lowContrast' : inverted ? 'inverted' : null;
	$('contrast-warning').hidden = key === null;
	if (key) {
		$('contrast-warning-text').textContent = strings[key];
	}
}

function loadSettings() {
	try {
		const saved = JSON.parse(localStorage.getItem(STORAGE_KEY));
		if (!saved || typeof saved !== 'object') {
			return;
		}
		if (['L', 'M', 'Q', 'H'].includes(saved.level)) {
			setLevel(saved.level);
		}
		if (Number.isInteger(saved.margin) && saved.margin >= 0 && saved.margin <= 8) {
			margin.value = saved.margin;
		}
		for (const [input, value] of [[fg, saved.fg], [bg, saved.bg]]) {
			if (typeof value === 'string' && /^#[0-9a-f]{6}$/i.test(value)) {
				input.value = value.toLowerCase();
			}
		}
	} catch {
		// Nothing saved, or it could not be read: keep the defaults.
	}
}

function options() {
	return { margin: Number(margin.value), fg: fg.value, bg: bg.value };
}

// Shows a message over the idealized QR code drawing.
function showMessage(text) {
	const empty = document.createElement('div');
	empty.className = 'qr-empty';
	const message = document.createElement('p');
	message.className = 'qr-message';
	message.textContent = text;
	empty.append($('qr-skeleton').content.cloneNode(true), message);
	qr.replaceChildren(empty);
	qr.setAttribute('aria-label', text);
}

function update() {
	$('margin-value').textContent = margin.value;
	$('settings-state-text').textContent = `${level} · ${strings.margin.replace('%s', margin.value)}`;
	$('swatch-fg').style.background = fg.value;
	$('swatch-bg').style.background = bg.value;
	updateWarning();
	$('reset').disabled = isDefault();
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

content.addEventListener('input', update);
for (const input of [margin, fg, bg]) {
	input.addEventListener('input', () => {
		saveSettings();
		update();
	});
}

$('ecc').addEventListener('click', (event) => {
	const button = event.target.closest('button');
	if (!button) {
		return;
	}
	setLevel(button.dataset.level);
	saveSettings();
	update();
});

$('reset').addEventListener('click', resetSettings);

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

// The website embeds the editor without its help button and dialog.
const dialog = $('help-dialog');
if (dialog) {
	$('help-open').addEventListener('click', () => dialog.showModal());
	$('help-close').addEventListener('click', () => dialog.close());
	dialog.addEventListener('click', (event) => {
		if (event.target === dialog) {
			dialog.close();
		}
	});
}

// On wide screens the settings are a column on the right and always open. On
// narrow ones they fold under the QR code, and each size remembers its own state.
const settingsPanel = document.querySelector('.settings');
const settingsToggle = settingsPanel.querySelector('summary');
// A .compact editor (the website's) keeps the narrow layout at every width.
const compact = settingsPanel.closest('.editor').classList.contains('compact');
const wideScreen = window.matchMedia(compact ? 'not all' : '(min-width: 900px)');
let settingsOpenWhenNarrow = settingsPanel.open;

function layoutSettings() {
	if (wideScreen.matches) {
		settingsPanel.open = true;
		settingsToggle.tabIndex = -1;
	} else {
		settingsPanel.open = settingsOpenWhenNarrow;
		settingsToggle.removeAttribute('tabindex');
	}
}

settingsToggle.addEventListener('click', (event) => {
	if (wideScreen.matches) {
		event.preventDefault();
	} else {
		// The click is about to flip it. Remember the result now, rather than
		// waiting for the toggle event, which can arrive after a resize.
		settingsOpenWhenNarrow = !settingsPanel.open;
	}
});
if (wideScreen.addEventListener) {
	wideScreen.addEventListener('change', layoutSettings);
} else {
	wideScreen.addListener(layoutSettings); // Older Safari.
}
layoutSettings();

// A link can bring the text in the address fragment (#text=…), which never
// reaches the server. It fills the field once and is then cleared from the
// address, so reloading or sharing the page does not carry it.
function readFragment() {
	const text = new URLSearchParams(location.hash.slice(1)).get('text');
	if (text !== null) {
		content.value = text;
		history.replaceState(null, '', location.pathname + location.search);
	}
}

readFragment();
loadSettings();
update();
