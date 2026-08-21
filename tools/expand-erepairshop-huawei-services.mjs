import { readFile, writeFile } from 'node:fs/promises';

const CONFIG_ENDPOINT = 'https://punktepass.de/wp-admin/admin-ajax.php';
const EXPECTED_MODEL_COUNT = 82;
const TARGETS = new Map([
  ['Akku Original', { suffix: 'Akkutausch (Original)' }],
  ['Ladebuchse Austausch', { suffix: 'Ladebuchse Austausch' }]
]);

// Modelle mit Marktstart ab 2024. Die explizite Liste verhindert, dass eine
// Nummer im Modellnamen versehentlich als Erscheinungsjahr interpretiert wird.
const NEWER_MODELS = new Map([
  ['Honor 90 Smart', 2024],
  ['Honor 200', 2024],
  ['Honor 200 Lite', 2024],
  ['Honor 200 Smart', 2024],
  ['Honor 400 Smart 5G', 2025],
  ['Honor Magic 6 Lite', 2024],
  ['Mate X6', 2024],
  ['Mate XT', 2024],
  ['Nova 12', 2024],
  ['Nova 12 SE', 2024],
  ['Nova 12i', 2024],
  ['Nova 12s', 2024],
  ['Nova 13', 2024],
  ['Nova 13 Pro', 2024],
  ['Pura 70', 2024],
  ['Pura 70 Pro', 2024],
  ['Pura 70 Pro+', 2024],
  ['Pura 70 Ultra', 2024],
  ['Pura 80 Pro', 2025],
  ['Pura 80 Ultra', 2025]
]);

const FOLDABLE_MODELS = new Set([
  'P50 Pocket',
  'Mate X2',
  'Mate X3',
  'Mate X5',
  'Mate X6',
  'Mate Xs',
  'Mate Xs 2',
  'Mate XT'
]);

function normalizeModel(input) {
  return String(input || '')
    .trim()
    .replace(/^Huawei\s+/i, '')
    .replace(/\s+/g, ' ');
}

function extractDisplayModel(name) {
  return normalizeModel(String(name || '')
    .replace(/^Huawei\s+/i, '')
    .replace(/\s+Displaytausch\s*\(Original\).*$/i, ''));
}

function isHuaweiName(name) {
  return /^(?:Huawei|Honor)\s+/i.test(name || '');
}

function isHuaweiTarget(service) {
  return TARGETS.has(service.category || '') && isHuaweiName(service.name);
}

async function fetchCurrentConfig() {
  const body = new URLSearchParams({ action: 'ppv_shop_widget_config', store_slug: 'erepairshop' });
  const response = await fetch(CONFIG_ENDPOINT, { method: 'POST', body });
  if (!response.ok) throw new Error(`PunktePass config: HTTP ${response.status}`);
  const payload = await response.json();
  if (!payload.success || !payload.data) throw new Error('Invalid PunktePass config payload');
  return payload.data;
}

function serviceRow(category, model) {
  if (category === 'Akku Original') {
    const foldable = FOLDABLE_MODELS.has(model);
    return {
      name: `Huawei ${model} ${TARGETS.get(category).suffix}`,
      category,
      price: `${foldable ? 120 : 65} EUR`,
      time: foldable ? '1 Std' : '45 Min'
    };
  }
  const newer = NEWER_MODELS.has(model);
  return {
    name: `Huawei ${model} ${TARGETS.get(category).suffix}`,
    category,
    price: `${newer ? 70 : 60} EUR`,
    time: '1.5 Std'
  };
}

async function main() {
  const outputArg = process.argv.indexOf('--output');
  const outputPath = outputArg >= 0 ? process.argv[outputArg + 1] : '';
  const inputArg = process.argv.indexOf('--input');
  const inputPath = inputArg >= 0 ? process.argv[inputArg + 1] : '';
  const config = inputPath ? JSON.parse(await readFile(inputPath, 'utf8')) : await fetchCurrentConfig();
  const services = config.services || [];
  const models = [...new Set(services
    .filter(service => service.category === 'Displaytausch Original' && /^Huawei\s+/i.test(service.name || ''))
    .map(service => extractDisplayModel(service.name)))];

  if (models.length !== EXPECTED_MODEL_COUNT) {
    throw new Error(`Expected ${EXPECTED_MODEL_COUNT} Huawei/Honor display models, got ${models.length}`);
  }
  const generatedByCategory = new Map(
    [...TARGETS.keys()].map(category => [category, models.map(model => serviceRow(category, model))])
  );
  const inserted = new Set();
  const updatedServices = [];
  for (const service of services) {
    const category = service.category || '';
    if (isHuaweiTarget(service)) {
      if (!inserted.has(category)) {
        updatedServices.push(...generatedByCategory.get(category));
        inserted.add(category);
      }
      continue;
    }
    updatedServices.push(service);
  }
  for (const category of TARGETS.keys()) {
    if (!inserted.has(category)) updatedServices.push(...generatedByCategory.get(category));
  }

  const result = {
    generatedAt: new Date().toISOString(),
    rule: {
      chargingPort: '2024 and newer: 70 EUR; older: 60 EUR',
      battery: 'foldable: 120 EUR; other: 65 EUR'
    },
    summary: {
      modelCount: models.length,
      newerModelCount: models.filter(model => NEWER_MODELS.has(model)).length,
      foldableModelCount: models.filter(model => FOLDABLE_MODELS.has(model)).length,
      oldServiceCount: services.length,
      newServiceCount: updatedServices.length,
      categories: Object.fromEntries([...TARGETS.keys()].map(category => [category, {
        oldRows: services.filter(service => service.category === category && isHuaweiName(service.name)).length,
        newRows: generatedByCategory.get(category).length,
        priceCounts: Object.fromEntries([...new Set(generatedByCategory.get(category).map(row => row.price))]
          .map(price => [price, generatedByCategory.get(category).filter(row => row.price === price).length]))
      }]))
    },
    services: updatedServices,
    audit: models.map(model => ({
      model,
      releaseYear: NEWER_MODELS.get(model) || null,
      foldable: FOLDABLE_MODELS.has(model),
      batteryPrice: FOLDABLE_MODELS.has(model) ? 120 : 65,
      chargingPortPrice: NEWER_MODELS.has(model) ? 70 : 60
    }))
  };

  const serialized = `${JSON.stringify(result, null, 2)}\n`;
  if (outputPath) await writeFile(outputPath, serialized, 'utf8');
  else process.stdout.write(serialized);
}

main().catch(error => {
  console.error(error instanceof Error ? error.stack : String(error));
  process.exitCode = 1;
});
