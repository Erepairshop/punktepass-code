import { writeFile } from 'node:fs/promises';

const CONFIG_ENDPOINT = 'https://punktepass.de/wp-admin/admin-ajax.php';
const TARGETS = new Map([
  ['Akku Original', { suffix: 'Akkutausch (Original)', time: '1 Std' }],
  ['Ladebuchse Austausch', { suffix: 'Ladebuchse Austausch', time: '1.5 Std' }],
  ['Backcover/Rückseite Austausch', { suffix: 'Backcover Austausch', time: '40 Min' }]
]);
const collator = new Intl.Collator('de', { numeric: true, sensitivity: 'base' });

function normalizeModel(input) {
  return String(input || '')
    .trim()
    .replace(/^Samsung\s+/i, '')
    .replace(/^Galaxy\s+/i, 'Galaxy ')
    .replace(/\bPlus\b/gi, '+')
    .replace(/\s*\+\s*/g, '+')
    .replace(/\s+/g, ' ')
    .trim();
}

function modelKey(model) {
  return normalizeModel(model)
    .replace(/\s+(?:4G|5G)\b/gi, '')
    .toLowerCase();
}

function extractDisplayModel(name) {
  return normalizeModel(String(name || '')
    .replace(/^Samsung\s+/i, '')
    .replace(/\s+Displaytausch\s*\(Original\).*$/i, ''));
}

function extractServiceModel(name) {
  return normalizeModel(String(name || '')
    .replace(/^Samsung\s+/i, '')
    .replace(/\s+(?:Akkutausch\s*\(Original\)|Ladebuchse Austausch|Backcover Austausch).*$/i, ''));
}

function family(model) {
  if (/^Galaxy A/i.test(model)) return 'A';
  if (/^Galaxy S\d/i.test(model)) return 'S';
  if (/^Galaxy Z Flip/i.test(model)) return 'Z Flip';
  if (/^Galaxy Z Fold/i.test(model)) return 'Z Fold';
  if (/^Galaxy M/i.test(model)) return 'M';
  if (/^Galaxy Note/i.test(model)) return 'Note';
  if (/^Galaxy J/i.test(model)) return 'J';
  if (/^Galaxy XCover/i.test(model)) return 'XCover';
  return 'Other';
}

function simplePrice(category, model) {
  const group = family(model);
  if (category === 'Akku Original') {
    if (group === 'Z Flip' || group === 'Z Fold') return 120;
    if (group === 'S' || group === 'Note' || group === 'XCover') return 70;
    return 60;
  }
  if (category === 'Ladebuchse Austausch') {
    return group === 'A' || group === 'M' || group === 'J' ? 50 : 70;
  }
  throw new Error(`No simple price rule for ${category}`);
}

function numericPart(model, prefix) {
  const match = model.match(new RegExp(`^Galaxy ${prefix}\\s*(\\d+)`, 'i'));
  return match ? Number(match[1]) : null;
}

function nearestExistingPrice(model, existing) {
  const group = family(model);
  const prefix = group === 'A' ? 'A' : group === 'M' ? 'M' : group === 'J' ? 'J' : '';
  const number = prefix ? numericPart(model, prefix) : null;
  if (number == null) return null;
  const candidates = [...existing.entries()]
    .filter(([candidate]) => family(candidate) === group)
    .map(([candidate, price]) => ({ candidate, price, number: numericPart(candidate, prefix) }))
    .filter(item => item.number != null)
    .sort((left, right) => Math.abs(left.number - number) - Math.abs(right.number - number));
  return candidates[0]?.price ?? null;
}

function backcoverPrice(model, existing) {
  const group = family(model);
  const known = existing.get(modelKey(model));
  if (known != null) return known;

  if (group === 'A') return nearestExistingPrice(model, existing) ?? 49;
  if (group === 'M' || group === 'J' || group === 'Other') return 49;
  if (group === 'XCover') {
    if (/XCover 7/i.test(model)) return 79;
    if (/XCover 6|XCover Pro/i.test(model)) return 69;
    return 59;
  }
  if (group === 'Note') {
    if (/Note 20 Ultra/i.test(model)) return 99;
    if (/Note 20/i.test(model)) return 89;
    if (/Note 10\+/i.test(model)) return 89;
    if (/Note 10/i.test(model)) return 79;
    return 69;
  }
  if (group === 'Z Flip') {
    const generation = numericPart(model, 'Z Flip') ?? 1;
    if (generation >= 6) return 109;
    if (generation === 5) return 99;
    return 89;
  }
  if (group === 'Z Fold') {
    const generation = numericPart(model, 'Z Fold') ?? 1;
    if (generation >= 7) return 149;
    if (generation === 6) return 139;
    if (generation === 5) return 129;
    return 119;
  }
  if (group === 'S') {
    const generation = numericPart(model, 'S') ?? 0;
    const isUltraOrEdge = /\b(?:Ultra|Edge)\b/i.test(model);
    const isFeOrLite = /\b(?:FE|Lite)\b/i.test(model);
    if (generation < 20) {
      if (isUltraOrEdge) return 89;
      if (/\+/.test(model)) return 79;
      return 69;
    }
    const base = generation >= 26 ? 109 + ((generation - 26) * 10) : 79;
    if (isFeOrLite) return Math.max(69, base - 10);
    if (isUltraOrEdge) return base + 10;
    return base;
  }
  return 49;
}

async function fetchCurrentConfig() {
  const body = new URLSearchParams({ action: 'ppv_shop_widget_config', store_slug: 'erepairshop' });
  const response = await fetch(CONFIG_ENDPOINT, { method: 'POST', body });
  if (!response.ok) throw new Error(`PunktePass config: HTTP ${response.status}`);
  const payload = await response.json();
  if (!payload.success || !payload.data) throw new Error('Invalid PunktePass config payload');
  return payload.data;
}

function priceNumber(value) {
  const parsed = Number.parseFloat(String(value || '').replace(',', '.'));
  return Number.isFinite(parsed) ? parsed : null;
}

async function main() {
  const outputArg = process.argv.indexOf('--output');
  const outputPath = outputArg >= 0 ? process.argv[outputArg + 1] : '';
  const config = await fetchCurrentConfig();
  const services = config.services || [];
  const models = [...new Set(services
    .filter(service => service.category === 'Displaytausch Original' && /^Samsung Galaxy/i.test(service.name || ''))
    .map(service => extractDisplayModel(service.name)))]
    .sort(collator.compare);
  if (models.length < 150) throw new Error(`Expected at least 150 Samsung models, got ${models.length}`);

  const generatedByCategory = new Map();
  const audit = [];
  for (const [category, settings] of TARGETS) {
    const oldRows = services.filter(service => service.category === category && /^Samsung Galaxy/i.test(service.name || ''));
    const existing = new Map(oldRows.map(service => [modelKey(extractServiceModel(service.name)), priceNumber(service.price)]));
    const rows = models.map(model => {
      const price = category === 'Backcover/Rückseite Austausch'
        ? backcoverPrice(model, existing)
        : simplePrice(category, model);
      audit.push({ category, model, price, preservedPrice: existing.get(modelKey(model)) === price });
      return {
        name: `Samsung ${model} ${settings.suffix}`,
        category,
        price: `${price} EUR`,
        time: settings.time
      };
    });
    generatedByCategory.set(category, rows);
  }

  const inserted = new Set();
  const updatedServices = [];
  for (const service of services) {
    const category = service.category || '';
    if (TARGETS.has(category) && /^Samsung Galaxy/i.test(service.name || '')) {
      if (!inserted.has(category)) {
        updatedServices.push(...generatedByCategory.get(category));
        inserted.add(category);
      }
      continue;
    }
    updatedServices.push(service);
  }

  const result = {
    generatedAt: new Date().toISOString(),
    summary: {
      modelCount: models.length,
      oldServiceCount: services.length,
      newServiceCount: updatedServices.length,
      categories: Object.fromEntries([...TARGETS.keys()].map(category => [category, {
        oldRows: services.filter(service => service.category === category && /^Samsung Galaxy/i.test(service.name || '')).length,
        newRows: generatedByCategory.get(category).length,
        prices: [...new Set(generatedByCategory.get(category).map(row => row.price))]
      }]))
    },
    services: updatedServices,
    audit
  };
  const serialized = `${JSON.stringify(result, null, 2)}\n`;
  if (outputPath) await writeFile(outputPath, serialized, 'utf8');
  else process.stdout.write(serialized);
}

main().catch(error => {
  console.error(error instanceof Error ? error.stack : String(error));
  process.exitCode = 1;
});
