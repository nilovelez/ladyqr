// Helpers that work on the module matrix returned by encode(): modules[y][x]
// is true for a dark module.

const COLOR = /^#[0-9a-f]{6}$/i;

function checkColor(color) {
	if (!COLOR.test(color)) {
		throw new TypeError('Colors must look like #rrggbb');
	}
	return color;
}

/**
 * Converts the boolean matrix to an array of strings, one per row, made of
 * '1' (dark) and '0' (light).
 */
export function matrixToRows(modules) {
	return modules.map((row) => row.map((dark) => (dark ? '1' : '0')).join(''));
}

/**
 * Converts an array of '0'/'1' strings back to the boolean matrix.
 */
export function rowsToMatrix(rows) {
	return rows.map((row) => Array.from(row, (ch) => ch === '1'));
}

/**
 * Builds an SVG image of the matrix.
 *
 * @param {boolean[][]} modules
 * @param {{margin?: number, fg?: string, bg?: string, size?: number}} options
 *   `margin` is the quiet zone in modules. `size`, when given, is the width and
 *   height of the image in pixels; without it the SVG scales to its container.
 * @returns {string} The SVG markup.
 */
export function toSvg(modules, { margin = 4, fg = '#000000', bg = '#ffffff', size } = {}) {
	checkColor(fg);
	checkColor(bg);
	const n = modules.length;
	const total = n + margin * 2;

	// One path segment per horizontal run of dark modules.
	let path = '';
	for (let y = 0; y < n; y++) {
		let x = 0;
		while (x < n) {
			if (!modules[y][x]) {
				x++;
				continue;
			}
			const start = x;
			while (x < n && modules[y][x]) {
				x++;
			}
			path += `M${start + margin} ${y + margin}h${x - start}v1h${start - x}z`;
		}
	}

	const dimensions = size ? ` width="${size}" height="${size}"` : '';
	return (
		`<svg xmlns="http://www.w3.org/2000/svg" viewBox="0 0 ${total} ${total}"${dimensions} shape-rendering="crispEdges">` +
		`<rect width="${total}" height="${total}" fill="${bg}"/>` +
		`<path fill="${fg}" d="${path}"/>` +
		'</svg>'
	);
}

/**
 * Draws the matrix on a canvas and returns it as a PNG.
 *
 * The image is exactly `size` x `size` pixels. Module edges are rounded to
 * whole pixels, so modules differ in width by at most one pixel but stay sharp.
 *
 * @param {boolean[][]} modules
 * @param {{size: number, margin?: number, fg?: string, bg?: string}} options
 * @returns {Promise<Blob>}
 */
export function toPngBlob(modules, { size, margin = 4, fg = '#000000', bg = '#ffffff' }) {
	checkColor(fg);
	checkColor(bg);
	const n = modules.length;
	const total = n + margin * 2;
	const scale = size / total;

	const canvas = document.createElement('canvas');
	canvas.width = size;
	canvas.height = size;
	const ctx = canvas.getContext('2d');
	ctx.fillStyle = bg;
	ctx.fillRect(0, 0, size, size);
	ctx.fillStyle = fg;
	for (let y = 0; y < n; y++) {
		const y0 = Math.round((y + margin) * scale);
		const y1 = Math.round((y + margin + 1) * scale);
		for (let x = 0; x < n; x++) {
			if (modules[y][x]) {
				const x0 = Math.round((x + margin) * scale);
				const x1 = Math.round((x + margin + 1) * scale);
				ctx.fillRect(x0, y0, x1 - x0, y1 - y0);
			}
		}
	}

	return new Promise((resolve, reject) => {
		canvas.toBlob((blob) => (blob ? resolve(blob) : reject(new Error('Could not create the PNG'))), 'image/png');
	});
}
