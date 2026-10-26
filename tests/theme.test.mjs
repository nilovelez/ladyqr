// The theme folder (head.html, header.html, footer.html, custom.css) and the
// title and description of the generator page.
import { test, after } from 'node:test';
import assert from 'node:assert/strict';
import fs from 'node:fs';
import { execFileSync } from 'node:child_process';
import { startSite, configFile, REPO } from './lib/site.mjs';

const sites = [];
after(async () => {
	await Promise.all(sites.map((site) => site.stop()));
});

// Starts a site with the admin open (no login) and the theme files given.
async function themedSite(files = {}, constants = {}) {
	const site = await startSite({ config: configFile({ SHORTENER: true, REQUIRE_LOGIN: false, ...constants }) });
	sites.push(site);
	if (Object.keys(files).length > 0) {
		fs.mkdirSync(site.file('theme'));
		for (const [name, contents] of Object.entries(files)) {
			fs.writeFileSync(site.file('theme/' + name), contents);
		}
	}
	// Opening the admin writes the .htaccess block that makes the 404 page answer.
	await fetch(site.url + '/admin/');
	return site;
}

const get = async (site, path) => (await fetch(site.url + path)).text();

test('without a theme folder the pages are the plain ones', async () => {
	const site = await themedSite();
	const generator = await get(site, '/');
	assert.ok(!generator.includes('theme/'), 'no link to the theme');
	assert.ok(!generator.includes('<header'), 'no header');
	assert.match(generator, /<footer>\s*<a href="https:\/\/ladyqr\.nilovelez\.com\/"/, 'the usual footer');
	assert.ok(!(await get(site, '/admin/')).includes('theme/'));
	assert.ok(!(await get(site, '/no-such-link')).includes('theme/'));
});

test('custom.css is linked after the LadyQR styles on the generator, the admin and the 404 page', async () => {
	const site = await themedSite({ 'custom.css': 'body { color: red; }' });
	const pages = [
		['/', 'assets/app.css', 'theme/custom.css?v='],
		['/admin/', 'assets/admin.css', 'theme/custom.css?v='],
		['/no-such-link', 'assets/admin.css', 'theme/custom.css?v='],
	];
	for (const [path, before, link] of pages) {
		const html = await get(site, path);
		assert.ok(html.includes(link), `${path} links custom.css`);
		assert.ok(html.indexOf(link) > html.indexOf(before), `${path}: custom.css comes after ${before}`);
		assert.match(html, /<link rel="stylesheet" href="[^"]*theme\/custom\.css\?v=\d+">/, `${path}: the tag`);
	}
	const css = await fetch(site.url + '/theme/custom.css');
	assert.equal(css.status, 200);
});

test('the installer cannot be themed', async () => {
	const site = await startSite();
	sites.push(site);
	fs.mkdirSync(site.file('theme'));
	for (const name of ['custom.css', 'head.html', 'header.html', 'footer.html']) {
		fs.writeFileSync(site.file('theme/' + name), '<!-- theme -->');
	}
	const installer = await get(site, '/admin/install.php');
	assert.ok(installer.includes('<h1'), 'it is the installer');
	assert.ok(!installer.includes('theme'), 'with nothing from the theme');
});

test('head.html goes right before </head>, only on the generator', async () => {
	const site = await themedSite({ 'head.html': '<meta name="theme-test" content="yes">\n' });
	assert.match(await get(site, '/'), /<meta name="theme-test" content="yes">\s*<\/head>/);
	assert.ok(!(await get(site, '/admin/')).includes('theme-test'));
	assert.ok(!(await get(site, '/no-such-link')).includes('theme-test'));
});

test('header.html goes in a header element right after <body>, only on the generator', async () => {
	const site = await themedSite({ 'header.html': '<p>The top</p>' });
	assert.match(await get(site, '/'), /<body>\s*<header>\s*<p>The top<\/p>\s*<\/header>/);
	assert.ok(!(await get(site, '/admin/')).includes('The top'));
});

test('footer.html replaces what is inside the footer element, and an empty one removes it', async () => {
	let site = await themedSite({ 'footer.html': '<p>My footer</p>' });
	let html = await get(site, '/');
	assert.match(html, /<footer>\s*<p>My footer<\/p>\s*<\/footer>/);
	assert.ok(!html.includes('get yours free'), 'the credit is gone');

	site = await themedSite({ 'footer.html': '' });
	html = await get(site, '/');
	assert.match(html, /<footer>\s*<\/footer>/);
	assert.ok(!html.includes('get yours free'));
});

test('the theme files are printed as they are: PHP in them does not run', async () => {
	const site = await themedSite({ 'head.html': "<?php echo 'ran'; ?>" });
	const html = await get(site, '/');
	assert.ok(html.includes("<?php echo 'ran'; ?>"));
	assert.ok(!html.includes('>ran<'));
});

test('the theme does not change the policy of the page', async () => {
	const site = await themedSite({ 'head.html': '<style>body{}</style>' });
	const csp = (await fetch(site.url + '/')).headers.get('content-security-policy');
	assert.match(csp, /script-src 'self'/);
	assert.match(csp, /style-src 'self'(;|$)/);
	assert.match(csp, /connect-src 'none'/);
});

test('LADYQR_SITE_TITLE and LADYQR_SITE_DESCRIPTION set the title and the description of the generator only', async () => {
	const site = await themedSite({}, { SITE_TITLE: 'Codes "by" Me & Co', SITE_DESCRIPTION: 'Make <b>a</b> code.' });
	const html = await get(site, '/');
	assert.match(html, /<title>Codes &quot;by&quot; Me &amp; Co<\/title>/);
	assert.match(html, /<meta name="description" content="Make &lt;b&gt;a&lt;\/b&gt; code\.">/);
	assert.match(await get(site, '/admin/'), /<title>LadyQR admin<\/title>/);
});

test('without the constants the title is LadyQR and there is no description', async () => {
	const site = await themedSite();
	const html = await get(site, '/');
	assert.match(html, /<title>LadyQR<\/title>/);
	assert.ok(!html.includes('name="description"'));
});

test('theme is a reserved name for short links', () => {
	const reserved = JSON.parse(
		execFileSync('php', ['-r', "define( 'LADYQR', true ); require 'includes/links.php'; echo json_encode( ladyqr_reserved_slugs() );"], { cwd: REPO }).toString()
	);
	assert.ok(reserved.includes('theme'));
});
