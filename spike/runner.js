// Runs the spike tests in the browser and builds a report.
// A classic script on purpose: it must work even if ES modules do not.

(function () {
	'use strict';

	var base = location.pathname.replace(/[^/]*$/, '');
	var results = [];
	var env = null;
	var lab = {};

	function url(path) {
		return base + path;
	}

	function sleep(ms) {
		return new Promise(function (resolve) { setTimeout(resolve, ms); });
	}

	// Fetches a path of the spike and returns what matters about the response.
	async function get(path, options) {
		var res = await fetch(url(path), Object.assign({ cache: 'no-store', credentials: 'same-origin' }, options || {}));
		var text = await res.text();
		var final = new URL(res.url);
		return {
			status: res.status,
			text: text,
			type: res.headers.get('content-type') || '',
			headers: res.headers,
			redirected: res.redirected,
			finalPath: final.pathname + final.search
		};
	}

	function post(action) {
		return fetch(url('t-write/lab.php?action=' + action), { method: 'POST', cache: 'no-store' }).then(function (res) {
			return res.json();
		});
	}

	function pass(detail) { return { status: 'pass', detail: detail || '' }; }
	function fail(detail) { return { status: 'fail', detail: detail || '' }; }
	function warn(detail) { return { status: 'warn', detail: detail || '' }; }
	function info(detail) { return { status: 'info', detail: detail || '' }; }

	var tests = [
		{
			title: '0. PHP runs and reports its environment',
			run: async function () {
				var res = await get('env.php');
				try {
					env = JSON.parse(res.text);
				} catch (e) {
					return fail('env.php did not return JSON (HTTP ' + res.status + '). PHP may not be running here.');
				}
				return info('PHP ' + env.php_version + ' (' + env.php_sapi + '), ' + env.server_software);
			}
		},
		{
			title: '1. Redirect and RedirectMatch (mod_alias) work in .htaccess',
			run: async function () {
				var a = await get('t-redirect/go');
				var b = await get('t-redirect/m/abc-1');
				var okA = a.redirected && a.finalPath === base + 'target.php?via=Redirect' && a.text.indexOf('LADYQR-SPIKE-TARGET') !== -1;
				var okB = b.redirected && b.finalPath.indexOf('slug=abc-1') !== -1 && b.text.indexOf('via=RedirectMatch') !== -1;
				if (okA && okB) { return pass('Both redirected and the slug was captured.'); }
				return fail('Redirect: HTTP ' + a.status + ' ended at ' + a.finalPath + '. RedirectMatch: HTTP ' + b.status + ' ended at ' + b.finalPath + '.');
			}
		},
		{
			title: '2. RewriteRule redirects (mod_rewrite) work in .htaccess',
			run: async function () {
				var a = await get('t-rewrite/go');
				var b = await get('t-rewrite/m/abc-2');
				var okA = a.redirected && a.finalPath === base + 'target.php?via=RewriteRule';
				var okB = b.redirected && b.finalPath.indexOf('slug=abc-2') !== -1;
				if (okA && okB) { return pass('Both redirected and the slug was captured.'); }
				return fail('go: HTTP ' + a.status + ' ended at ' + a.finalPath + '. m/abc-2: HTTP ' + b.status + ' ended at ' + b.finalPath + '.');
			}
		},
		{
			title: '3. ErrorDocument 404 loads a PHP page and keeps the 404 status',
			run: async function () {
				var r = await get('t-errordoc/missing/page');
				var marked = r.text.indexOf('LADYQR-SPIKE-404') !== -1;
				var lines = r.text.split('\n').filter(function (l) { return /^(initial_status|redirect_status|redirect_url)=/.test(l); }).join(', ');
				if (r.status === 404 && marked) { return pass('HTTP 404 with the PHP page. ' + lines); }
				if (marked) { return fail('The PHP page loaded but the status was ' + r.status + ', not 404. ' + lines); }
				return fail('HTTP ' + r.status + ' and the PHP page did not load. ' + lines);
			}
		},
		{
			title: '4. Header works in .htaccess (mod_headers)',
			run: async function () {
				var r = await get('t-headers/ping.txt');
				var h = r.headers.get('x-spike-header');
				if (h === 'mod_headers') { return pass('Apache added the header.'); }
				return warn('The header is missing (HTTP ' + r.status + '). The CSP can still be sent from PHP with header().');
			}
		},
		{
			title: '5. A file can be blocked in .htaccess (for the configuration file)',
			run: async function () {
				var r = await get('t-deny/secret.txt');
				if (r.status === 403) { return pass('HTTP 403.'); }
				if (r.status === 404) { return warn('HTTP 404: hidden, but not an explicit deny.'); }
				return fail('HTTP ' + r.status + (r.text.indexOf('never be served') !== -1 ? ': the file was served.' : '.'));
			}
		},
		{
			title: '6. .htaccess can tell the request came over HTTPS',
			run: async function () {
				if (location.protocol !== 'https:') {
					return info('Open this page over https:// to run this test.');
				}
				var outcomes = [];
				var names = ['check', 'check-either'];
				for (var i = 0; i < names.length; i++) {
					try {
						var r = await get('t-https/' + names[i]);
						outcomes.push(names[i] + ': ' + (r.text.indexOf('https-rule-ok') !== -1 && !r.redirected ? 'ok' : 'unexpected (HTTP ' + r.status + ', ended at ' + r.finalPath + ')'));
					} catch (e) {
						outcomes.push(names[i] + ': redirect loop');
					}
				}
				var detail = outcomes.join('; ') + '. Indicators seen by PHP: ' + (env ? JSON.stringify(env.https_indicators) : 'unknown');
				if (outcomes[0].indexOf(': ok') !== -1) { return pass(detail); }
				if (outcomes[1].indexOf(': ok') !== -1) { return warn('%{HTTPS} is not reliable here, but X-Forwarded-Proto works. ' + detail); }
				return fail('A "force HTTPS" rule would loop here. ' + detail);
			}
		},
		{
			title: '7. Static files are served with the right MIME types',
			run: async function () {
				var files = [['test.js', /javascript|ecmascript/], ['test.css', /text\/css/], ['test.svg', /image\/svg\+xml/], ['test.png', /image\/png/]];
				var bad = [];
				var seen = [];
				for (var i = 0; i < files.length; i++) {
					var r = await get('t-mime/' + files[i][0]);
					seen.push(files[i][0] + ' = ' + (r.type || 'none'));
					if (!files[i][1].test(r.type)) { bad.push(files[i][0]); }
				}
				var moduleOk = false;
				try {
					var m = await import(url('t-mime/test.js'));
					moduleOk = m.ok === true;
				} catch (e) { moduleOk = false; }
				var fixed = [];
				var rj = await get('t-mime-fix/test.js');
				fixed.push('with AddType, test.js = ' + (rj.type || 'none'));
				var detail = seen.join('; ') + '. Loading test.js as an ES module: ' + (moduleOk ? 'works' : 'FAILED') + '. ' + fixed.join('; ');
				if (!bad.length && moduleOk) { return pass(detail); }
				return fail('Wrong: ' + bad.join(', ') + '. ' + detail);
			}
		},
		{
			title: '8. PHP can rewrite a block of .htaccess safely',
			run: async function () {
				var steps = [];
				var failures = [];
				var i = await post('info');
				lab.info = i;
				steps.push('file writable: ' + i.htaccess_writable + ', directory writable: ' + i.dir_writable + ', permissions ' + i.perms);
				steps.push('the server can request itself: ' + i.loopback_works + ' (' + (i.loopback.via || 'no way') + ', ' + i.loopback.ms + ' ms' + (i.loopback.error ? ', ' + i.loopback.error : '') + ')');
				if (!i.htaccess_writable || !i.dir_writable) {
					return fail('PHP cannot write the file. ' + steps.join('. '));
				}

				// Write the block.
				var w = await post('write');
				lab.write = w;
				steps.push('write: ' + (w.write.ok ? 'ok' : 'FAILED') + ' in ' + w.write.ms + ' ms, lock ' + w.write.locked + ', permissions ' + w.write.perms + ', block present ' + w.has_block + ', static lines kept ' + w.static_line_kept);
				if (!w.write.ok || !w.has_block || !w.static_line_kept) { failures.push('the write did not do what it should'); }

				// How soon does the new redirect work?
				var started = Date.now();
				var worked = false;
				while (Date.now() - started < 8000) {
					var r = await get('t-write/dyn-test');
					if (r.redirected && r.finalPath.indexOf('via=lab') !== -1) { worked = true; break; }
					await sleep(500);
				}
				var delay = Date.now() - started;
				lab.delayMs = delay;
				steps.push('the new redirect ' + (worked ? 'worked after ' + delay + ' ms' : 'did NOT work within 8 s'));
				if (!worked) { failures.push('the redirect never took effect'); }

				// Remove it again.
				var rm = await post('remove');
				steps.push('remove: ' + (rm.write.ok ? 'ok' : 'FAILED') + ', static lines kept ' + rm.static_line_kept);
				var gone = false;
				started = Date.now();
				while (Date.now() - started < 8000) {
					var g = await get('t-write/dyn-test');
					if (!g.redirected && g.status === 404) { gone = true; break; }
					await sleep(500);
				}
				steps.push('the redirect ' + (gone ? 'stopped working' : 'kept working after removal'));
				if (!gone) { failures.push('the removal did not take effect'); }

				// Break it on purpose.
				var b = await post('break');
				lab.broken = b;
				if (b.skipped) {
					steps.push('deliberate break: skipped. ' + b.skipped);
				} else {
					steps.push('deliberate break: the server answered ' + b.during.status + ' with an invalid directive (failure detected: ' + b.detected_failure + '), rolled back ' + (b.rolled_back && b.rolled_back.ok) + ', healthy afterwards ' + b.healthy_after);
					if (!b.healthy_after) { failures.push('the site did not recover after the rollback'); }
				}

				var health = await get('t-write/ping.txt');
				steps.push('the folder answers at the end: HTTP ' + health.status);
				if (health.status !== 200) { failures.push('the folder is broken now'); }

				if (failures.length) { return fail(failures.join('; ') + '. ' + steps.join('. ')); }
				if (!i.loopback_works) { return warn('It works, but the server cannot request itself, so the self-check and rollback cannot be used. ' + steps.join('. ')); }
				return pass(steps.join('. '));
			}
		},
		{
			title: '9. Sessions keep state between requests',
			run: async function () {
				var a = JSON.parse((await get('t-session/counter.php')).text);
				var b = JSON.parse((await get('t-session/counter.php')).text);
				var detail = 'save path: ' + (a.save_path || 'default') + ', cookie ' + JSON.stringify(a.cookie_params);
				if (b.n === a.n + 1) { return pass('Counter went ' + a.n + ' to ' + b.n + '. ' + detail); }
				return fail('Counter went ' + a.n + ' to ' + b.n + '. ' + detail);
			}
		},
		{
			title: '10. Passwords can be hashed',
			run: async function () {
				if (!env || !env.functions) { return fail('No environment data.'); }
				var p = env.password || {};
				if (!env.functions.password_hash || !env.functions.password_verify) { return fail('password_hash() or password_verify() is missing.'); }
				var detail = 'hash ' + p.hash_ms + ' ms, verify ' + p.verify_ms + ' ms (verified ' + p.verify_ok + '), default algorithm ' + p.default_algo + ', random_bytes ' + p.random_bytes + ', argon2id ' + p.argon2id;
				if (!p.verify_ok) { return fail(detail); }
				if (p.hash_ms > 500) { return warn('Slow. ' + detail); }
				return pass(detail);
			}
		},
		{
			title: '11. PHP can read a file one level above the web root',
			run: async function () {
				if (!env) { return fail('No environment data.'); }
				var detail = 'open_basedir: ' + (env.ini.open_basedir || 'not set') + ', parent readable: ' + env.paths.parent_readable + ', probe: ' + env.parent_probe;
				if (/^included/.test(env.parent_probe)) { return pass(detail); }
				if (/not present/.test(env.parent_probe)) { return info('Upload parent-probe/ladyqr-parent-probe.php one level above the web root and run again. ' + detail); }
				return fail(detail);
			}
		},
		{
			title: '12. A lock blocks a second request (flock)',
			run: async function () {
				if (!env || !env.functions.flock) { return fail('flock() is not available.'); }
				var holding = post('hold');
				await sleep(500);
				var during = await post('trylock');
				await holding;
				var after = await post('trylock');
				var detail = 'while held: acquired ' + during.acquired + ', after release: acquired ' + after.acquired;
				if (!during.acquired && after.acquired) { return pass(detail); }
				if (during.acquired) { return fail('The lock did not block the second request, so it cannot protect concurrent writes. ' + detail); }
				return warn(detail);
			}
		},
		{
			title: '13. Room and limits',
			run: async function () {
				if (!env) { return fail('No environment data.'); }
				return info('free ' + env.disk.free_mb + ' MB of ' + env.disk.total_mb + ' MB, memory_limit ' + env.ini.memory_limit + ', max_execution_time ' + env.ini.max_execution_time + ', upload_max_filesize ' + env.ini.upload_max_filesize + ', .ovhconfig: ' + (env.ovhconfig ? env.ovhconfig.replace(/\s+/g, ' ') : 'none') + ', .htaccess already in the web root: ' + env.docroot_htaccess + ', disable_functions: ' + (env.ini.disable_functions || 'none'));
			}
		}
	];

	function render() {
		var body = document.getElementById('results');
		body.innerHTML = '';
		results.forEach(function (r) {
			var tr = document.createElement('tr');
			var s = document.createElement('td');
			s.className = 'status ' + r.status;
			s.textContent = r.status.toUpperCase();
			var t = document.createElement('td');
			t.textContent = r.title;
			var d = document.createElement('small');
			d.textContent = r.detail;
			t.appendChild(d);
			tr.appendChild(s);
			tr.appendChild(t);
			body.appendChild(tr);
		});
	}

	function report() {
		var count = function (s) { return results.filter(function (r) { return r.status === s; }).length; };
		var lines = [];
		lines.push('LadyQR hosting spike (M1) report');
		lines.push('Date: ' + new Date().toISOString());
		lines.push('Page: ' + location.href);
		lines.push('Browser: ' + navigator.userAgent);
		lines.push('Summary: ' + count('pass') + ' pass, ' + count('fail') + ' fail, ' + count('warn') + ' warn, ' + count('info') + ' info');
		lines.push('');
		results.forEach(function (r) {
			lines.push('[' + r.status.toUpperCase() + '] ' + r.title);
			if (r.detail) { lines.push('    ' + r.detail); }
		});
		if (env) {
			lines.push('');
			lines.push('Environment (env.php):');
			lines.push(JSON.stringify(env, null, 2));
		}
		return lines.join('\n');
	}

	async function runAll() {
		var run = document.getElementById('run');
		run.disabled = true;
		document.getElementById('copy').disabled = true;
		results = [];
		env = null;
		for (var i = 0; i < tests.length; i++) {
			var result;
			try {
				result = await tests[i].run();
			} catch (e) {
				result = fail('The test crashed: ' + e.message);
			}
			results.push({ title: tests[i].title, status: result.status, detail: result.detail });
			render();
		}
		document.getElementById('report').value = report();
		document.getElementById('copy').disabled = false;
		run.disabled = false;
	}

	if (base !== '/spike/') {
		var banner = document.getElementById('path-warning');
		banner.hidden = false;
		banner.textContent = 'This page is at ' + base + ', but the .htaccess files in the test folders point to /spike/. Upload the folder so it is reachable at /spike/, or most tests will fail for that reason alone.';
	}

	document.getElementById('run').addEventListener('click', runAll);
	document.getElementById('copy').addEventListener('click', function () {
		var box = document.getElementById('report');
		box.select();
		if (navigator.clipboard && navigator.clipboard.writeText) {
			navigator.clipboard.writeText(box.value).catch(function () { document.execCommand('copy'); });
		} else {
			document.execCommand('copy');
		}
	});
}());
