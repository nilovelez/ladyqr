// The browser for the tests: an installed Chrome, driven by puppeteer-core.
// Set CHROME_PATH if Chrome is somewhere else.
import fs from 'node:fs';
import puppeteer from 'puppeteer-core';

const CANDIDATES = [
	process.env.CHROME_PATH,
	'C:/Program Files/Google/Chrome/Application/chrome.exe',
	'/Applications/Google Chrome.app/Contents/MacOS/Google Chrome',
	'/usr/bin/google-chrome',
	'/usr/bin/chromium',
];

export const CHROME = CANDIDATES.find((file) => file && fs.existsSync(file));

// For test(..., { skip }) when there is no browser.
export const NO_BROWSER = CHROME ? false : 'Chrome not found; set CHROME_PATH';

export function launch() {
	return puppeteer.launch({ executablePath: CHROME, headless: true });
}
