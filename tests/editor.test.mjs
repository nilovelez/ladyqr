// The editor page in a real browser.
import { test, before, after } from 'node:test';
import assert from 'node:assert/strict';
import { startSite, configFile } from './lib/site.mjs';
import { launch, NO_BROWSER } from './lib/browser.mjs';

let site;
let browser;

before(async () => {
	site = await startSite({ config: configFile({ LANGUAGE: 'en_US' }) });
	if (!NO_BROWSER) {
		browser = await launch();
	}
});

after(async () => {
	await browser?.close();
	await site.stop();
});

async function open(address) {
	const page = await browser.newPage();
	await page.goto(site.url + address, { waitUntil: 'networkidle0' });
	return page;
}

test('without a fragment the field is empty and there is no code', { skip: NO_BROWSER }, async () => {
	const page = await open('/');
	assert.equal(await page.$eval('#content', (input) => input.value), '');
	assert.equal(await page.$('#qr > svg'), null);
	await page.close();
});

test('#text= fills the field, draws the code and leaves the address', { skip: NO_BROWSER }, async () => {
	for (const text of ['HTTP://1QR.ES/PEPE', 'https://example.com/?a=1&b=2+3', 'Ñandú 100% 🐞 #tag']) {
		const page = await open('/#text=' + encodeURIComponent(text));
		assert.equal(await page.$eval('#content', (input) => input.value), text);
		assert.ok(await page.$('#qr > svg'), 'code drawn');
		assert.equal(await page.evaluate(() => location.hash), '');
		assert.equal(await page.evaluate(() => location.pathname), '/');
		await page.close();
	}
});

test('other fragments are ignored', { skip: NO_BROWSER }, async () => {
	const page = await open('/#other=1');
	assert.equal(await page.$eval('#content', (input) => input.value), '');
	assert.equal(await page.evaluate(() => location.hash), '#other=1');
	await page.close();
});
