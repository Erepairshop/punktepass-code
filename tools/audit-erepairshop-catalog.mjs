import { readFile } from 'node:fs/promises';

const expectedArg = process.argv.indexOf('--expected');
const expectedPath = expectedArg >= 0 ? process.argv[expectedArg + 1] : '';
if (!expectedPath) throw new Error('Usage: node audit-erepairshop-catalog.mjs --expected <generated-json>');

const expected = JSON.parse(await readFile(expectedPath, 'utf8'));
const body = new URLSearchParams({ action: 'ppv_shop_widget_config', store_slug: 'erepairshop' });
const response = await fetch('https://punktepass.de/wp-admin/admin-ajax.php', { method: 'POST', body });
if (!response.ok) throw new Error(`PunktePass config: HTTP ${response.status}`);
const payload = await response.json();
if (!payload.success || !Array.isArray(payload.data?.services)) throw new Error('Invalid live config payload');

const live = payload.data.services;
const rowKey = row => JSON.stringify([row.name || '', row.category || '', row.price || '', row.time || '']);
const expectedKeys = expected.services.map(rowKey);
const liveKeys = live.map(rowKey);
const expectedSet = new Set(expectedKeys);
const liveSet = new Set(liveKeys);
const missingRows = expectedKeys.filter(key => !liveSet.has(key));
const extraRows = liveKeys.filter(key => !expectedSet.has(key));
const duplicateNames = [...new Set(live.map(row => row.name).filter((name, index, names) => names.indexOf(name) !== index))];

const samples = [
  'Samsung Galaxy Alpha Displaytausch (Original)',
  'Samsung Galaxy S6 Displaytausch (Original)',
  'Huawei Honor 200 Pro Displaytausch (Original)',
  'Huawei Mate X2 Außendisplaytausch (Original)',
  'Poco F4 GT Displaytausch (Original)',
  'Redmi Note 15 Pro+ 5G Displaytausch (Original)'
].map(name => live.find(row => row.name === name) || { name, missing: true });

const result = {
  expectedCount: expected.services.length,
  liveCount: live.length,
  missingRows: missingRows.length,
  extraRows: extraRows.length,
  duplicateNames: duplicateNames.length,
  samples
};
process.stdout.write(`${JSON.stringify(result, null, 2)}\n`);
if (expected.services.length !== live.length || missingRows.length || extraRows.length || duplicateNames.length) process.exitCode = 1;
