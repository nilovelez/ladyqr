// A throwaway LadyQR site for the tests: the runtime files copied to a new
// temporary folder and served by PHP's built-in server with router.php.
import fs from 'node:fs';
import os from 'node:os';
import path from 'node:path';
import net from 'node:net';
import { spawn } from 'node:child_process';
import { fileURLToPath } from 'node:url';

export const REPO = path.resolve(fileURLToPath(import.meta.url), '../../..');

// The same files as the release zip (tools/build-release.php).
const RUNTIME = ['index.php', '404.php', 'ladyqr-config.sample.php', 'admin', 'includes', 'assets', 'languages'];

function freePort() {
	return new Promise((resolve, reject) => {
		const server = net.createServer();
		server.on('error', reject);
		server.listen(0, '127.0.0.1', () => {
			const { port } = server.address();
			server.close(() => resolve(port));
		});
	});
}

/**
 * Creates and starts a site.
 *
 * @param {object} [options]
 * @param {string} [options.config] Contents of ladyqr-config.php, if any.
 * @param {string} [options.data]   Contents of ladyqr-data.php, if any.
 * @param {string} [options.folder] A folder of the web root to install it in,
 *   such as 'sub', instead of the web root itself.
 * @returns {Promise<{root: string, url: string, file: (name: string) => string, stop: () => Promise<void>}>}
 *   root and file() are the site's own folder; url is the web root's address.
 */
export async function startSite({ config, data, folder = '' } = {}) {
	// The web root goes one level down, because the configuration is also
	// looked up in the folder above the site.
	const base = fs.mkdtempSync(path.join(os.tmpdir(), 'ladyqr-test-'));
	const docroot = path.join(base, 'www');
	const root = path.join(docroot, folder);
	for (const name of RUNTIME) {
		fs.cpSync(path.join(REPO, name), path.join(root, name), { recursive: true });
	}
	if (config !== undefined) {
		fs.writeFileSync(path.join(root, 'ladyqr-config.php'), config);
	}
	if (data !== undefined) {
		fs.writeFileSync(path.join(root, 'ladyqr-data.php'), data);
	}

	const port = await freePort();
	const url = `http://127.0.0.1:${port}`;
	const php = spawn('php', ['-S', `127.0.0.1:${port}`, '-t', docroot, path.join(REPO, 'tests/lib/router.php')], { stdio: 'ignore' });
	for (let tries = 0; ; tries++) {
		try {
			await fetch(`${url}/${folder ? folder + '/' : ''}assets/app.css`);
			break;
		} catch (error) {
			if (tries > 50) {
				php.kill();
				throw error;
			}
			await new Promise((resolve) => setTimeout(resolve, 100));
		}
	}

	return {
		root,
		url,
		file: (name) => path.join(root, name),
		stop: async () => {
			php.kill();
			await new Promise((resolve) => php.once('exit', resolve));
			fs.rmSync(base, { recursive: true, force: true });
		},
	};
}

/**
 * A configuration file made of the given LADYQR_ constants.
 */
export function configFile(constants) {
	const lines = Object.entries(constants).map(([name, value]) => `define( 'LADYQR_${name}', ${typeof value === 'string' ? `'${value}'` : String(value)} );`);
	return `<?php\ndefined( 'LADYQR' ) || exit;\n${lines.join('\n')}\n`;
}
