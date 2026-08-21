import { writeFile } from 'node:fs/promises';

const DISPLAY_ENDPOINT = 'https://4phones.eu/collections/displays/products.json';
const CONFIG_ENDPOINT = 'https://punktepass.de/wp-admin/admin-ajax.php';
const EXPECTED_MODEL_COUNT = 203;
const QUALITY_SCORE = new Map([
  ['Servicepack', 4],
  ['Original', 3],
  ['Refurbished', 2],
  ['OEM', 1]
]);
const familyOrder = new Map([
  ['Xiaomi', 0], ['Mi', 1], ['Redmi Note', 2], ['Redmi', 3],
  ['Poco F', 4], ['Poco X', 5], ['Poco M', 6], ['Poco C', 7],
  ['Poco', 8], ['Other', 9]
]);
const collator = new Intl.Collator('de', { numeric: true, sensitivity: 'base' });

function tagsWithPrefix(product, prefix) {
  return (product.tags || []).filter(tag => tag.startsWith(prefix)).map(tag => tag.slice(prefix.length));
}

function normalizeModel(input) {
  return String(input || '')
    .trim()
    .replace(/^Xiaomi\s+/i, '')
    .replace(/^RedMi\b/i, 'Redmi')
    .replace(/\s+/g, ' ');
}

function correctedModels(product) {
  return tagsWithPrefix(product, 'model|').map(rawModel => {
    const model = normalizeModel(rawModel);
    if (model === 'M2') return 'Poco M2';
    if (/^9(?:A|AT|C|T)?$/i.test(model)) return `Redmi ${model.toUpperCase()}`;
    return model;
  });
}

function modelFamily(model) {
  if (/^Mi\s/i.test(model)) return 'Mi';
  if (/^Redmi Note\s/i.test(model)) return 'Redmi Note';
  if (/^Redmi\s/i.test(model)) return 'Redmi';
  if (/^Poco F/i.test(model)) return 'Poco F';
  if (/^Poco X/i.test(model)) return 'Poco X';
  if (/^Poco M/i.test(model)) return 'Poco M';
  if (/^Poco C/i.test(model)) return 'Poco C';
  if (/^(?:Poco|Pocophone)\s/i.test(model)) return 'Poco';
  if (/^(?:\d|Civi\s)/i.test(model)) return 'Xiaomi';
  return 'Other';
}

function modelKey(model) {
  return normalizeModel(model)
    .replace(/[()]/g, '')
    .replace(/\bPlus\b/gi, '+')
    .replace(/\s*\+\s*/g, '+')
    .replace(/\s+/g, ' ')
    .toLowerCase();
}

function serviceName(model) {
  const prefix = /^(?:Redmi|Poco|Pocophone)\s/i.test(model) ? '' : 'Xiaomi ';
  return `${prefix}${model} Displaytausch (Original)`;
}

function extractServiceModel(name) {
  return normalizeModel(String(name || '')
    .replace(/\s+Displaytausch\s*\(Original\).*$/i, ''));
}

function isXiaomiDisplay(service) {
  return service.category === 'Displaytausch Original' &&
    /^(?:Xiaomi|Redmi|Poco|Pocophone)\s/i.test(service.name || '');
}

function maxVariantPrice(product) {
  return Math.max(0, ...(product.variants || [])
    .map(variant => Number.parseFloat(variant.price))
    .filter(price => Number.isFinite(price) && price > 0));
}

function hasFrame(product) {
  if (/Without Frame/i.test(product.title || '')) return false;
  if (/With Frame|Complete/i.test(product.title || '')) return true;
  return (product.tags || []).includes('attribute_includes_frame|true');
}

function isBetter(candidate, existing) {
  if (!existing) return true;
  if (candidate.qualityScore !== existing.qualityScore) return candidate.qualityScore > existing.qualityScore;
  if (candidate.frame !== existing.frame) return candidate.frame;
  return candidate.purchasePrice > existing.purchasePrice;
}

function roundCustomerPrice(purchasePrice) {
  return Math.ceil((purchasePrice + 75) / 5) * 5;
}

async function fetchAllDisplays() {
  const products = [];
  for (let page = 1; page <= 20; page += 1) {
    const response = await fetch(`${DISPLAY_ENDPOINT}?limit=250&page=${page}`);
    if (!response.ok) throw new Error(`4Phones display page ${page}: HTTP ${response.status}`);
    const batch = (await response.json()).products || [];
    products.push(...batch);
    if (batch.length < 250) break;
  }
  return products;
}

async function fetchCurrentConfig() {
  const body = new URLSearchParams({ action: 'ppv_shop_widget_config', store_slug: 'erepairshop' });
  const response = await fetch(CONFIG_ENDPOINT, { method: 'POST', body });
  if (!response.ok) throw new Error(`PunktePass config: HTTP ${response.status}`);
  const payload = await response.json();
  if (!payload.success || !payload.data) throw new Error('Invalid PunktePass config payload');
  return payload.data;
}

async function main() {
  const outputArg = process.argv.indexOf('--output');
  const outputPath = outputArg >= 0 ? process.argv[outputArg + 1] : '';
  const [products, config] = await Promise.all([fetchAllDisplays(), fetchCurrentConfig()]);
  const candidates = new Map();

  for (const product of products) {
    if (!(product.tags || []).includes('brand|Xiaomi')) continue;
    if (!(product.tags || []).includes('device_type|Phone')) continue;
    const quality = tagsWithPrefix(product, 'attribute_quality|')[0] || '';
    const qualityScore = QUALITY_SCORE.get(quality) || 0;
    if (!qualityScore) continue;
    const purchasePrice = maxVariantPrice(product);
    if (purchasePrice <= 0) continue;
    for (const model of correctedModels(product)) {
      const candidate = {
        model,
        family: modelFamily(model),
        quality,
        qualityScore,
        frame: hasFrame(product),
        purchasePrice,
        available: (product.variants || []).some(variant => variant.available),
        title: product.title,
        handle: product.handle,
        sku: product.variants?.[0]?.sku || ''
      };
      if (isBetter(candidate, candidates.get(modelKey(model)))) candidates.set(modelKey(model), candidate);
    }
  }

  if (candidates.size !== EXPECTED_MODEL_COUNT) {
    throw new Error(`Expected ${EXPECTED_MODEL_COUNT} Xiaomi phone models, got ${candidates.size}`);
  }
  const generated = [...candidates.values()].map(item => ({
    name: serviceName(item.model),
    category: 'Displaytausch Original',
    price: `${roundCustomerPrice(item.purchasePrice)} EUR`,
    time: '1 Std'
  }));
  generated.sort((left, right) => {
    const leftModel = extractServiceModel(left.name);
    const rightModel = extractServiceModel(right.name);
    const familyDiff = (familyOrder.get(modelFamily(leftModel)) ?? 99) - (familyOrder.get(modelFamily(rightModel)) ?? 99);
    return familyDiff || collator.compare(leftModel, rightModel);
  });

  const services = config.services || [];
  const currentRows = services.filter(isXiaomiDisplay);
  const firstIndex = services.findIndex(isXiaomiDisplay);
  const insertIndex = services.slice(0, firstIndex < 0 ? services.length : firstIndex)
    .filter(service => !isXiaomiDisplay(service)).length;
  const updatedServices = services.filter(service => !isXiaomiDisplay(service));
  updatedServices.splice(insertIndex, 0, ...generated);

  const audit = [...candidates.values()]
    .sort((left, right) => collator.compare(left.model, right.model))
    .map(item => ({ ...item, markup: 75, customerPrice: roundCustomerPrice(item.purchasePrice) }));
  const sourceKeys = new Set(candidates.keys());
  const result = {
    generatedAt: new Date().toISOString(),
    source: DISPLAY_ENDPOINT,
    summary: {
      sourceProducts: products.length,
      sourceModels: candidates.size,
      currentXiaomiRows: currentRows.length,
      removedUnsupportedRows: currentRows.filter(service => !sourceKeys.has(modelKey(extractServiceModel(service.name)))).length,
      generatedXiaomiRows: generated.length,
      qualityCounts: Object.fromEntries([...QUALITY_SCORE.keys()].map(quality => [quality, audit.filter(item => item.quality === quality).length])),
      framedModels: audit.filter(item => item.frame).length,
      unavailableModels: audit.filter(item => !item.available).length,
      missingPrices: generated.filter(service => !service.price).length,
      oldServiceCount: services.length,
      newServiceCount: updatedServices.length
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
