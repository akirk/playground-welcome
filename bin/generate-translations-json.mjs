#!/usr/bin/env node
/**
 * Generates translations.json by scanning available .mo files and content directories.
 *
 * Usage: node bin/generate-translations-json.mjs
 */

import { readdirSync, existsSync, writeFileSync } from 'fs';
import { join, dirname } from 'path';
import { fileURLToPath } from 'url';

const __dirname = dirname(fileURLToPath(import.meta.url));
const pluginDir = dirname(__dirname);
const languagesDir = join(pluginDir, 'languages');
const outputFile = join(pluginDir, 'translations.json');

/**
 * Define fallbacks for regional variants.
 * When adding a new language, add its regional fallbacks here.
 */
const FALLBACKS = {
	de_AT: 'de_DE',
	de_CH: 'de_DE',
};

const manifest = {
	available: {},
	fallbacks: FALLBACKS,
};

// Scan for .mo files
const moFiles = readdirSync(languagesDir).filter(
	(f) => f.startsWith('playground-welcome-') && f.endsWith('.mo')
);

for (const moFile of moFiles) {
	const match = moFile.match(/^playground-welcome-(.+)\.mo$/);
	if (match) {
		const locale = match[1];
		const langCode = locale.substring(0, 2);

		const entry = {};

		// Check if content directory exists
		const contentDir = join(pluginDir, langCode);
		if (
			existsSync(contentDir) &&
			existsSync(join(contentDir, 'welcome-post.html'))
		) {
			entry.contentDir = langCode;
		}

		manifest.available[locale] = entry;
	}
}


// Sort keys
manifest.available = Object.fromEntries(
	Object.entries(manifest.available).sort()
);
manifest.fallbacks = Object.fromEntries(
	Object.entries(manifest.fallbacks).sort()
);

const json = JSON.stringify(manifest, null, '\t') + '\n';
writeFileSync(outputFile, json);

console.log(`Generated ${outputFile}`);
console.log(JSON.stringify(manifest, null, '\t'));
