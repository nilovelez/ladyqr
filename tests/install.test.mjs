// The installer and the admin, end to end, on throwaway sites served by PHP's
// built-in server. What only Apache can show (that it obeys the rules written
// in .htaccess) is not checked here: the route check and the server checks
// answer "could not be tested", as they do on any server that cannot request
// itself.
import { test, before, after } from 'node:test';
import assert from 'node:assert/strict';
import fs from 'node:fs';
import { startSite } from './lib/site.mjs';
import { launch, NO_BROWSER } from './lib/browser.mjs';

const skip = NO_BROWSER;
let browser;
const errors = [];
let contexts = [];

before(async () => {
	if (!NO_BROWSER) {
		browser = await launch();
	}
});

after(async () => {
	await browser?.close();
});

async function newPage(width = 1100) {
	const context = await browser.createBrowserContext();
	contexts.push(context);
	const page = await context.newPage();
	page.on('console', (message) => message.type() === 'error' && errors.push(`${message.text()} (${message.location().url})`));
	await page.setViewport({ width, height: 900 });
	return page;
}

// Closes the pages before their site's server stops.
async function closePages() {
	await Promise.all(contexts.map((context) => context.close()));
	contexts = [];
}

const text = (page, selector = 'main') => page.$eval(selector, (element) => element.innerText);

async function submit(page, selector = 'button[type=submit]') {
	await Promise.all([page.waitForNavigation(), page.click(selector)]);
}

async function addLink(page, slug, target) {
	await page.type('#add-slug', slug);
	await page.type('#add-target', target);
	await submit(page, 'form:has(input[value=add]) button[type=submit]');
}

test('root install with the shortener, in Spanish, then the admin', { skip }, async (t) => {
	const site = await startSite();
	t.after(async () => {
		await closePages();
		await site.stop();
	});
	const get = (address) => fetch(site.url + address, { redirect: 'manual' });
	const read = (name) => fs.readFileSync(site.file(name), 'utf8');

	assert.ok(!fs.existsSync(site.file('.htaccess')), 'no .htaccess in the zip');
	let answer = await get('/');
	assert.equal(answer.status, 302, 'not installed: the home page sends to the installer');
	assert.equal(answer.headers.get('location'), '/admin/install.php');
	answer = await get('/admin/');
	assert.equal(answer.status, 302, '/admin/ sends to the installer');
	assert.equal(answer.headers.get('location'), 'install.php');

	const page = await newPage();
	await page.goto(site.url + '/admin/install.php');
	assert.equal(await page.$eval('#lang', (select) => select.value), 'en_US', 'screen 1: English preselected');

	// The Spanish installer first, to check the translation and going back.
	await page.select('#lang', 'es_ES');
	await submit(page);
	assert.match(await text(page), /¿Qué componentes quieres instalar\?/, 'screen 2 in Spanish');
	await submit(page, 'a.back');
	assert.equal(await page.$eval('#lang', (select) => select.value), 'es_ES', 'back to screen 1 keeps the language');
	await page.select('#lang', 'en_US');
	await submit(page);

	const welcome = await text(page);
	assert.match(welcome, /I'm LadyQR/);
	assert.match(welcome, /Which components do you want to install\?/);
	assert.equal(await page.$('.checks'), null, 'screen 2: no server checks yet');
	const choices = await page.$$eval('.start-choice', (elements) => elements.map((element) => [element.innerText.trim(), Math.round(element.getBoundingClientRect().top)]));
	assert.deepEqual(choices.map(([label]) => label), ['QR code generator\n+ link shortener', 'QR code generator only']);
	assert.equal(choices[0][1], choices[1][1], 'the two choices side by side');

	await submit(page, 'a.start-choice');
	assert.ok(page.url().endsWith('/admin/install.php?lang=en_US&step=shortener'), 'the shortener choice opens screen 3');
	assert.equal(await page.$eval('#language', (select) => select.value), 'en_US', "screen 3: the installer's language preselected for the site");
	assert.match(await text(page, '.checks'), /It could not be tested now/, 'the route check cannot be made here');
	assert.ok(!fs.readdirSync(site.root).some((name) => name.startsWith('.ladyqr-route-check')), 'no throwaway check folder left');
	const hint = await page.$eval('#site', (input) => input.closest('.field-row').innerText);
	assert.ok(hint.includes(`your short links will start with it (for example, ${site.url}/abc)`), hint);
	const suggested = await page.$eval('#password', (input) => input.value);
	assert.match(suggested, /^[a-km-np-zA-HJ-NP-Z2-9]{20}$/, 'suggested password: 20 characters, none ambiguous');

	await page.select('#language', 'es_ES');
	await page.type('#user', 'admin');
	await page.type('#site_description', 'Haz un código QR para un enlace.');
	await page.$eval('#site', (input) => { input.value = 'ftp://nope'; });
	await page.$eval('#site_title', (input) => { input.value = 'x'.repeat(121); });
	await submit(page);
	assert.ok(await page.$('#site-error'), 'a bad address is refused');
	assert.ok(await page.$('#site_title-error'), 'a title of more than 120 characters is refused');
	assert.equal(await page.$('#site_description-error'), null, 'and the description is fine');
	await page.$eval('#site_title', (input) => { input.value = "Códigos QR de O'Brien"; });
	assert.ok(!fs.existsSync(site.file('ladyqr-config.php')), 'nothing written after an error');
	await page.$eval('#site', (input, url) => { input.value = url; }, site.url);
	await page.$eval('#password', (input) => { input.value = 'short'; });
	await submit(page);
	assert.ok(await page.$('#password-error'), 'a short password is refused');
	await page.$eval('#password', (input, value) => { input.value = value; }, suggested);
	await submit(page);

	const done = await text(page);
	assert.match(done, /LadyQR is installed/);
	assert.ok(done.includes(suggested), 'the final screen shows the password once');
	assert.match(done, /On, but they could not be tested/);
	const rows = await page.$$eval('table.summary:not(.credentials table) th', (cells) => cells.map((cell) => cell.textContent.trim()));
	assert.equal(rows[1], 'Admin panel', 'summary: the admin panel on the second line');
	assert.equal(await page.$eval('table.summary tr:nth-child(2) a', (link) => link.getAttribute('href')), site.url + '/admin/');
	const buttons = await page.$$eval('main .form-actions .btn', (elements) => elements.map((element) => [element.textContent.trim(), element.className]));
	assert.equal(buttons.length, 2, 'two final buttons');
	assert.equal(buttons[0][0], 'Go to the link shortener', 'the shortener first');
	assert.ok(buttons.every(([, className]) => 'btn secondary' === className), 'both secondary');

	const config = read('ladyqr-config.php');
	assert.match(config, /\$2y\$/, 'the configuration holds a hash');
	assert.ok(!config.includes(suggested), 'and not the password');
	assert.ok(config.includes(`define( 'LADYQR_URL', '${site.url}' );`));
	assert.ok(config.includes("define( 'LADYQR_LANGUAGE', 'es_ES' );"));
	assert.ok(config.includes("define( 'LADYQR_SHORTENER', true );"));
	assert.ok(config.includes("define( 'LADYQR_SITE_TITLE', 'Códigos QR de O\\'Brien' );"), 'the title is written, quoted');
	assert.ok(config.includes("define( 'LADYQR_SITE_DESCRIPTION', 'Haz un código QR para un enlace.' );"));
	assert.match(await (await get('/')).text(), /<title>Códigos QR de O&#039;Brien<\/title>/, 'the generator uses the title');
	assert.match(read('ladyqr-data.php'), /defined\( 'LADYQR' \) \|\| exit;\n\nreturn array \(\n\);\n$/, 'the data file is a PHP array');
	const htaccess = read('.htaccess');
	assert.ok(htaccess.indexOf('RewriteCond %{HTTP_HOST}') < htaccess.indexOf('# BEGIN LadyQR'), '.htaccess: the address rules on top, then the block');
	assert.ok(htaccess.includes('RewriteRule ^ /index.php [L]'));
	assert.ok(!htaccess.includes('%{HTTPS}'), 'no https rule on an http site');

	await page.reload();
	assert.match(await text(page), /already installed/, 'reloading: already installed');
	assert.ok(!(await page.content()).includes(suggested), 'and the password is not shown again');

	for (const file of ['/ladyqr-config.php', '/ladyqr-data.php', '/includes/config.php', '/includes/data.php']) {
		assert.equal(await (await get(file)).text(), '', `a direct request prints nothing: ${file}`);
	}

	// Log in and add a link.
	await page.goto(site.url + '/admin/');
	await page.type('#user', 'admin');
	await page.type('#password', suggested);
	await submit(page);
	assert.ok(await page.$('.account'), 'logged in with the installed password');
	assert.match(await text(page), /Enlaces cortos/, 'the admin in Spanish');
	await addLink(page, 'menu', 'https://example.com/menu?a=1&b=$x');
	for (const slug of ['menu', 'Menu', 'MENU']) {
		answer = await get('/' + slug);
		assert.equal(answer.status, 302, `/${slug} redirects`);
		assert.equal(answer.headers.get('location'), 'https://example.com/menu?a=1&b=$x');
	}
	await addLink(page, 'MENU', 'https://example.com/other');
	assert.match(await text(page), /Ya hay un enlace corto con ese nombre/, 'a slug that differs only in case is refused');
	assert.equal((await get('/menu')).headers.get('location'), 'https://example.com/menu?a=1&b=$x', 'the first link is kept');
	assert.equal((await get('/nothing/here')).status, 404);
	assert.equal((await get('/menu/extra')).status, 404);
	assert.equal(read('.htaccess'), htaccess, '.htaccess untouched by adding links');

	// .htaccess lost: the admin puts the block back.
	fs.rmSync(site.file('.htaccess'));
	assert.equal((await get('/')).status, 200, 'without .htaccess the editor works');
	assert.equal((await get('/menu')).status, 404, 'without .htaccess a link does not reach PHP');
	await page.goto(site.url + '/admin/');
	assert.ok(read('.htaccess').includes('# BEGIN LadyQR'), 'the block is put back');
	assert.equal((await get('/menu')).status, 302, 'and the link works again');

	// An old version of the block is rewritten.
	fs.writeFileSync(site.file('.htaccess'), read('.htaccess').replace('# Version 1.', '# Version 0.'));
	await page.goto(site.url + '/admin/');
	assert.ok(read('.htaccess').includes('# Version 1.'), 'an old block is rewritten');

	// Someone else's .htaccess without the block: the block is added, the rest kept.
	fs.writeFileSync(site.file('.htaccess'), '# Host rules\nOptions -Indexes\n');
	await page.goto(site.url + '/admin/');
	const mixed = read('.htaccess');
	assert.ok(mixed.startsWith('# Host rules\nOptions -Indexes\n'), "someone else's rules are kept");
	assert.ok(mixed.includes('# BEGIN LadyQR'), 'and the block is added after them');
});

test('installs in a folder: generator only, with the shortener, by hand, and without the admin', { skip }, async (t) => {
	const site = await startSite({ folder: 'sub' });
	t.after(async () => {
		await closePages();
		await site.stop();
	});
	const get = (address) => fetch(site.url + address, { redirect: 'manual' });
	const read = (name) => fs.readFileSync(site.file(name), 'utf8');
	const reset = () => {
		for (const name of ['ladyqr-config.php', 'ladyqr-data.php', '.htaccess', '.htaccess.bak']) {
			fs.rmSync(site.file(name), { force: true });
		}
	};

	let answer = await get('/sub/');
	assert.equal(answer.status, 302, 'not installed in a folder: sends to its installer');
	assert.equal(answer.headers.get('location'), '/sub/admin/install.php');

	// Generator only, on a phone: the installer in Spanish, the site in English.
	let page = await newPage(390);
	await page.goto(site.url + '/sub/');
	assert.ok(page.url().endsWith('/sub/admin/install.php'));
	await page.select('#lang', 'es_ES');
	await submit(page);
	const tops = await page.$$eval('.start-choice', (elements) => elements.map((element) => Math.round(element.getBoundingClientRect().top)));
	assert.ok(tops[0] < tops[1], 'phone: the two choices one under the other');
	assert.ok(!(await page.evaluate(() => document.documentElement.scrollWidth > innerWidth)), 'phone: no horizontal overflow');
	await submit(page, '.start-choice:nth-child(2)');
	assert.equal(await page.$$eval('.checks li', (items) => items.length), 2, 'generator only: just two checks, PHP and writing');
	assert.ok(!fs.existsSync(site.file('ladyqr-config.php')), 'nothing written before confirming');
	assert.ok(await page.$('#site'), 'the address is asked for');
	assert.ok(await page.$('#site_title') && await page.$('#site_description'), 'and the optional title and description');
	assert.equal(await page.$('#user'), null, 'but no user');
	assert.equal(await page.$('#password'), null, 'and no password');
	assert.doesNotMatch(await page.$eval('#site', (input) => input.closest('.field-row').innerText), /enlaces cortos/, 'the address hint does not mention short links');
	assert.equal(await page.$eval('#language', (select) => select.value), 'es_ES', "the installer's language preselected for the site");
	await page.$eval('#site', (input, url) => { input.value = url; }, site.url);
	await page.select('#language', 'en_US');
	await submit(page);
	const done = await text(page);
	assert.match(done, /edita el archivo ladyqr-config\.php, o bórralo para volver a usar este instalador/, 'explains how to change your mind, in Spanish');
	assert.doesNotMatch(done, /Guarda ahora/);
	assert.equal(await page.$eval('main .btn', (link) => link.getAttribute('href')), site.url + '/sub/', 'a link to the generator');
	const config = read('ladyqr-config.php');
	assert.ok(config.includes("define( 'LADYQR_LANGUAGE', 'en_US' );"));
	assert.ok(config.includes("define( 'LADYQR_SHORTENER', false );"));
	assert.ok(config.includes(`define( 'LADYQR_URL', '${site.url}/sub' );`));
	assert.ok(!config.includes('LADYQR_USER'));
	assert.ok(!config.includes('LADYQR_SITE_TITLE') && !config.includes('LADYQR_SITE_DESCRIPTION'), 'the title and the description are optional and were left empty');
	assert.ok(!fs.existsSync(site.file('ladyqr-data.php')), 'no data file');
	const htaccess = read('.htaccess');
	assert.ok(htaccess.includes(`RewriteCond %{HTTP_HOST} !=${new URL(site.url).host} [NC]`), '.htaccess with the address rules');
	assert.ok(!htaccess.includes('# BEGIN LadyQR'), 'and without the block');
	await page.goto(site.url + '/sub/admin/');
	assert.match(await text(page), /turned off in the configuration/, 'the admin says the shortener is off');
	assert.equal((await get('/sub/')).status, 200, 'the generator, with no redirect');
	assert.match(await (await get('/sub/admin/install.php')).text(), /already installed/);

	// With the shortener, to check the rules in a folder.
	fs.rmSync(site.file('ladyqr-config.php'));
	page = await newPage();
	await page.goto(site.url + '/sub/admin/install.php?lang=en_US&step=shortener');
	await page.type('#user', 'admin');
	await submit(page);
	assert.match(await text(page), /LadyQR is installed/);
	assert.ok(read('.htaccess').includes('RewriteRule ^ /sub/index.php [L]'), 'the rule points to the folder');
	assert.equal((await get('/sub/whatever')).status, 404);

	// By hand: the sample copied and edited, as Alfonso would do.
	reset();
	const sample = read('ladyqr-config.sample.php')
		.replace("// define( 'LADYQR_SHORTENER', true );", "define( 'LADYQR_SHORTENER', true );")
		.replace("// define( 'LADYQR_USER', 'your-user' );", "define( 'LADYQR_USER', 'alfonso' );")
		.replace("// define( 'LADYQR_PASSWORD', 'your-password' );", "define( 'LADYQR_PASSWORD', 'plain-password-1' );");
	fs.writeFileSync(site.file('ladyqr-config.php'), sample);
	page = await newPage();
	await page.goto(site.url + '/sub/admin/');
	await page.type('#user', 'alfonso');
	await page.type('#password', 'plain-password-1');
	await submit(page);
	const secured = read('ladyqr-config.php');
	assert.match(secured, /define\( 'LADYQR_PASSWORD', '\$2y\$[^']+' \);/, 'the plain password is replaced by a hash');
	assert.ok(!secured.includes('plain-password-1'));
	assert.equal(secured.replace(/'\$2y\$[^']+'/, "'X'"), sample.replace("'plain-password-1'", "'X'"), 'the rest of the file is kept');
	assert.match(await text(page), /secure hash/, 'the admin says the password is secured');
	assert.ok(read('.htaccess').includes('RewriteRule ^ /sub/index.php [L]'), 'the block is written on the first visit');
	await addLink(page, 'b', 'https://b.example/');
	await addLink(page, 'a', 'https://a.example/?q=1&r=$x');
	let data = read('ladyqr-data.php');
	assert.ok(data.indexOf("'a' =>") < data.indexOf("'b' =>"), 'the links are stored sorted');
	assert.ok(data.includes("'target' => 'https://a.example/?q=1&r=$x'"));
	answer = await get('/sub/a');
	assert.equal(answer.status, 302);
	assert.equal(answer.headers.get('location'), 'https://a.example/?q=1&r=$x', '& and $ in a target survive');
	await page.goto(site.url + '/sub/admin/?edit=b');
	await page.$eval('#edit-slug', (input) => { input.value = 'bee'; });
	await page.select('#edit-status', '301');
	await submit(page, 'form:has(input[value=edit]) button[type=submit]');
	assert.equal((await get('/sub/bee')).status, 301, 'editing renames and changes the code at once');
	assert.equal((await get('/sub/b')).status, 404);
	await page.goto(site.url + '/sub/admin/?delete=bee');
	await submit(page, 'form:has(input[value=delete]) button[type=submit]');
	data = read('ladyqr-data.php');
	assert.ok(!data.includes("'bee'"), 'deleting removes it');
	assert.equal((await get('/sub/bee')).status, 404);
	assert.equal(await (await get('/sub/ladyqr-data.php')).text(), '', 'the data file prints nothing');

	// No admin folder: only the QR code generator, with nothing to install.
	reset();
	fs.rmSync(site.file('admin'), { recursive: true, force: true });
	answer = await get('/sub/');
	const plain = await answer.text();
	assert.equal(answer.status, 200, 'no configuration and no admin: the generator, with no redirect');
	assert.ok(plain.includes('id="content"'));
	assert.doesNotMatch(plain.replace(/<script[\s\S]*?<\/script>/g, ''), /install/i, 'no warning about installing');
	assert.equal((await get('/sub/admin/install.php')).status, 404, 'the installer is gone');
	assert.ok(!fs.existsSync(site.file('ladyqr-config.php')), 'nothing written');
	assert.ok(!fs.existsSync(site.file('.htaccess')));
});

test('no errors in the browser console', { skip }, () => {
	assert.deepEqual(errors, []);
});
