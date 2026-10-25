// The QR icon before each short link in the admin.
import { test, after } from 'node:test';
import assert from 'node:assert/strict';
import { startSite, configFile } from './lib/site.mjs';
import { launch, NO_BROWSER } from './lib/browser.mjs';

const DATA = `<?php
defined( 'LADYQR' ) || exit;
return array(
	'menu' => array( 'status' => 302, 'target' => 'https://example.com/menu' ),
	'Wifi-2' => array( 'status' => 301, 'target' => 'https://example.com/wifi' ),
);
`;

const sites = [];
after(async () => {
	await Promise.all(sites.map((site) => site.stop()));
});

// Starts a site with the admin open (no login) and two short links.
async function adminSite(extra = {}) {
	const site = await startSite({ config: configFile({ SHORTENER: true, REQUIRE_LOGIN: false, ...extra }), data: DATA });
	sites.push(site);
	return site;
}

// The QR links in the admin's list, as { label, target, rel, text }.
async function qrLinks(site) {
	const html = await (await fetch(site.url + '/admin/')).text();
	return [...html.matchAll(/<a class="qr-link" ([^>]*)>/g)].map(([, attrs]) => {
		const attr = (name) => new RegExp(`${name}="([^"]*)"`).exec(attrs)?.[1].replaceAll('&amp;', '&');
		const href = attr('href');
		return { href, label: attr('aria-label'), target: attr('target'), rel: attr('rel'), text: decodeURIComponent(href.split('#text=')[1]) };
	});
}

test('each short link gets a QR icon that opens the generator in a new tab', async () => {
	const site = await adminSite();
	const links = await qrLinks(site);
	assert.equal(links.length, 2);
	assert.deepEqual(links.map((link) => link.label), ['QR code for /menu', 'QR code for /Wifi-2']);
	for (const link of links) {
		assert.ok(link.href.startsWith(site.url + '/#text='), link.href);
		assert.equal(link.target, '_blank');
		assert.equal(link.rel, 'noopener');
	}
});

test('without LADYQR_QR_BASE the code holds the full short link', async () => {
	const site = await adminSite();
	assert.deepEqual((await qrLinks(site)).map((link) => link.text), [site.url + '/menu', site.url + '/Wifi-2']);
});

test('with LADYQR_QR_BASE the code holds the base and the slug in uppercase', async () => {
	for (const base of ['1QR.ES/', '1QR.ES', ' 1QR.ES// ']) {
		const site = await adminSite({ QR_BASE: base });
		assert.deepEqual((await qrLinks(site)).map((link) => link.text), ['1QR.ES/MENU', '1QR.ES/WIFI-2'], JSON.stringify(base));
	}
});

test('the uppercase slug still finds the link', async () => {
	const site = await adminSite({ QR_BASE: '1QR.ES/' });
	await fetch(site.url + '/admin/'); // Writes the .htaccess block.
	const answer = await fetch(site.url + '/WIFI-2', { redirect: 'manual' });
	assert.equal(answer.status, 301);
	assert.equal(answer.headers.get('location'), 'https://example.com/wifi');
});

test('the icon opens the generator with the short link filled in', { skip: NO_BROWSER }, async () => {
	const site = await adminSite({ QR_BASE: '1QR.ES/' });
	const browser = await launch();
	try {
		const page = await browser.newPage();
		await page.goto(site.url + '/admin/', { waitUntil: 'networkidle0' });
		const opened = new Promise((resolve) => browser.once('targetcreated', (target) => resolve(target.page())));
		await page.click('.qr-link');
		const generator = await opened;
		await generator.waitForSelector('#qr > svg');
		assert.equal(await generator.$eval('#content', (input) => input.value), '1QR.ES/MENU');
		assert.equal(await generator.evaluate(() => location.href), site.url + '/');
	} finally {
		await browser.close();
	}
});
