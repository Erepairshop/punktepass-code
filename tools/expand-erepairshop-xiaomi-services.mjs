import { writeFile } from 'node:fs/promises';

const CONFIG_ENDPOINT = 'https://punktepass.de/wp-admin/admin-ajax.php';
const EXPECTED_MODEL_COUNT = 203;
const TARGETS = new Map([
  ['Akku Original', { suffix: 'Akkutausch (Original)' }],
  ['Ladebuchse Austausch', { suffix: 'Ladebuchse Austausch' }]
]);

// Europai/globalis modellstart 2024-tol. Explicit lista, mert a modellnevben
// szereplo szam nem minden esetben az evet vagy ugyanazt a piacot jelenti.
const NEWER_MODELS = new Map([
  ['14', 2024],
  ['14 Ultra', 2024],
  ['14T', 2024],
  ['14T Pro', 2024],
  ['15', 2025],
  ['15 Pro', 2024],
  ['15 Ultra', 2025],
  ['15T', 2025],
  ['15T Pro', 2025],
  ['17', 2025],
  ['Civi 5 Pro', 2025],
  ['Poco C71', 2025],
  ['Poco C75 4G', 2024],
  ['Poco C75 5G', 2025],
  ['Poco C85x', 2025],
  ['Poco F6', 2024],
  ['Poco F6 Pro', 2024],
  ['Poco F7 Pro', 2025],
  ['Poco F7 Ultra', 2025],
  ['Poco F8 Pro', 2025],
  ['Poco F8 Ultra', 2025],
  ['Poco M6 4G', 2024],
  ['Poco X6', 2024],
  ['Poco X6 Pro', 2024],
  ['Poco X7', 2025],
  ['Poco X7 Pro', 2025],
  ['Redmi 13 4G', 2024],
  ['Redmi 14C', 2024],
  ['Redmi 14C 5G', 2025],
  ['Redmi 15 4G', 2025],
  ['Redmi 15 5G', 2025],
  ['Redmi 15a', 2025],
  ['Redmi 15C', 2025],
  ['Redmi 15C 4G', 2025],
  ['Redmi A3', 2024],
  ['Redmi A3 Pro', 2024],
  ['Redmi A3x', 2024],
  ['Redmi A5', 2025],
  ['Redmi K70 Ultra', 2024],
  ['Redmi K80', 2024],
  ['Redmi K80 Pro', 2024],
  ['Redmi K80 Ultra', 2025],
  ['Redmi K90', 2025],
  ['Redmi K90 Pro Max', 2025],
  ['Redmi Note 13 (2312DRAABC)', 2024],
  ['Redmi Note 13 4G', 2024],
  ['Redmi Note 13 5G', 2024],
  ['Redmi Note 13 Pro', 2024],
  ['Redmi Note 13 Pro 4G', 2024],
  ['Redmi Note 13 Pro Plus', 2024],
  ['Redmi Note 13R', 2024],
  ['Redmi Note 14 4G', 2025],
  ['Redmi Note 14 5G', 2025],
  ['Redmi Note 14 Pro 4G', 2025],
  ['Redmi Note 14 Pro 5G', 2025],
  ['Redmi Note 15 5G', 2025],
  ['Redmi Turbo 3', 2024],
  ['Redmi Turbo 4', 2025],
  ['Redmi Turbo 4 Pro', 2025]
]);

const FOLDABLE_MODELS = new Set();

function isXiaomiName(name) {
  return /^(?:Xiaomi|Redmi|Poco|Pocophone)\s/i.test(name || '');
}

function displayBaseName(name) {
  return String(name || '').replace(/\s+Displaytausch\s*\(Original\).*$/i, '').trim();
}

function canonicalModel(baseName) {
  return String(baseName || '').replace(/^Xiaomi\s+/i, '').replace(/\s+/g, ' ').trim();
}

function isTargetService(service) {
  return TARGETS.has(service.category || '') && isXiaomiName(service.name);
}

async function fetchCurrentConfig() {
  const body = new URLSearchParams({ action: 'ppv_shop_widget_config', store_slug: 'erepairshop' });
  const response = await fetch(CONFIG_ENDPOINT, { method: 'POST', body });
  if (!response.ok) throw new Error(`PunktePass config: HTTP ${response.status}`);
  const payload = await response.json();
  if (!payload.success || !payload.data) throw new Error('Invalid PunktePass config payload');
  return payload.data;
}

function serviceRow(category, item) {
  const model = canonicalModel(item.baseName);
  if (category === 'Akku Original') {
    const foldable = FOLDABLE_MODELS.has(model);
    return {
      name: `${item.baseName} ${TARGETS.get(category).suffix}`,
      category,
      price: `${foldable ? 120 : 65} EUR`,
      time: foldable ? '1 Std' : '45 Min'
    };
  }
  return {
    name: `${item.baseName} ${TARGETS.get(category).suffix}`,
    category,
    price: `${NEWER_MODELS.has(model) ? 70 : 60} EUR`,
    time: '1.5 Std'
  };
}

async function main() {
  const outputArg = process.argv.indexOf('--output');
  const outputPath = outputArg >= 0 ? process.argv[outputArg + 1] : '';
  const config = await fetchCurrentConfig();
  const services = config.services || [];
  const models = services
    .filter(service => service.category === 'Displaytausch Original' && isXiaomiName(service.name))
    .map(service => ({ baseName: displayBaseName(service.name) }));
  const uniqueModels = new Set(models.map(item => canonicalModel(item.baseName).toLowerCase()));

  if (models.length !== EXPECTED_MODEL_COUNT || uniqueModels.size !== EXPECTED_MODEL_COUNT) {
    throw new Error(`Expected ${EXPECTED_MODEL_COUNT} unique Xiaomi display models, got ${models.length}/${uniqueModels.size}`);
  }
  for (const model of NEWER_MODELS.keys()) {
    if (!models.some(item => canonicalModel(item.baseName) === model)) {
      throw new Error(`Newer Xiaomi pricing model is missing from display inventory: ${model}`);
    }
  }

  const generatedByCategory = new Map(
    [...TARGETS.keys()].map(category => [category, models.map(item => serviceRow(category, item))])
  );
  const inserted = new Set();
  const updatedServices = [];
  for (const service of services) {
    const category = service.category || '';
    if (isTargetService(service)) {
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
      newerModelCount: models.filter(item => NEWER_MODELS.has(canonicalModel(item.baseName))).length,
      foldableModelCount: models.filter(item => FOLDABLE_MODELS.has(canonicalModel(item.baseName))).length,
      oldServiceCount: services.length,
      newServiceCount: updatedServices.length,
      categories: Object.fromEntries([...TARGETS.keys()].map(category => [category, {
        oldRows: services.filter(service => service.category === category && isXiaomiName(service.name)).length,
        newRows: generatedByCategory.get(category).length,
        priceCounts: Object.fromEntries([...new Set(generatedByCategory.get(category).map(row => row.price))]
          .map(price => [price, generatedByCategory.get(category).filter(row => row.price === price).length]))
      }]))
    },
    services: updatedServices,
    audit: models.map(item => {
      const model = canonicalModel(item.baseName);
      return {
        model,
        releaseYear: NEWER_MODELS.get(model) || null,
        foldable: FOLDABLE_MODELS.has(model),
        batteryPrice: FOLDABLE_MODELS.has(model) ? 120 : 65,
        chargingPortPrice: NEWER_MODELS.has(model) ? 70 : 60
      };
    })
  };
  const serialized = `${JSON.stringify(result, null, 2)}\n`;
  if (outputPath) await writeFile(outputPath, serialized, 'utf8');
  else process.stdout.write(serialized);
}

main().catch(error => {
  console.error(error instanceof Error ? error.stack : String(error));
  process.exitCode = 1;
});
