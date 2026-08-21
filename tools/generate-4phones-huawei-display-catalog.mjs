import { writeFile } from 'node:fs/promises';

const DISPLAY_ENDPOINT = 'https://4phones.eu/collections/displays/products.json';
const CONFIG_ENDPOINT = 'https://punktepass.de/wp-admin/admin-ajax.php';
const QUALITY_SCORE = new Map([['Servicepack', 3], ['Refurbished', 2], ['OEM', 1]]);
const familyOrder = new Map([
  ['Honor', 0], ['P', 1], ['P Smart', 2], ['Mate', 3], ['Nova', 4],
  ['Pura', 5], ['Y', 6], ['Enjoy', 7], ['Other', 8]
]);
const collator = new Intl.Collator('de', { numeric: true, sensitivity: 'base' });

function tagsWithPrefix(product, prefix) {
  return (product.tags || []).filter(tag => tag.startsWith(prefix)).map(tag => tag.slice(prefix.length));
}

function normalizeModel(input) {
  return String(input || '')
    .trim()
    .replace(/^Huawei\s+/i, '')
    .replace(/\s+/g, ' ');
}

function correctedModels(product) {
  if (/^Honor 200 Smart\s/i.test(product.title || '')) return ['Honor 200 Smart'];
  return tagsWithPrefix(product, 'model|').map(model => {
    const value = normalizeModel(model);
    if (value === '20 Pro') return 'Enjoy 20 Pro';
    if (value === '50') return 'Honor 50';
    return value;
  });
}

function modelFamily(model) {
  if (/^Honor\s/i.test(model)) return 'Honor';
  if (/^P Smart\b/i.test(model)) return 'P Smart';
  if (/^Pura\s/i.test(model)) return 'Pura';
  if (/^P\d/i.test(model)) return 'P';
  if (/^Mate\s/i.test(model)) return 'Mate';
  if (/^Nova\s/i.test(model)) return 'Nova';
  if (/^Y\d/i.test(model)) return 'Y';
  if (/^Enjoy\s/i.test(model)) return 'Enjoy';
  return 'Other';
}

function modelKey(model) {
  return normalizeModel(model)
    .replace(/[()]/g, '')
    .replace(/\s+/g, ' ')
    .toLowerCase();
}

function extractServiceModel(name) {
  return normalizeModel(String(name || '')
    .replace(/^Huawei\s+/i, '')
    .replace(/\s+Displaytausch\s*\(Original\).*$/i, ''));
}

function isHuaweiDisplay(service) {
  return service.category === 'Displaytausch Original' &&
    /^(?:Huawei|Honor)\s+/i.test(service.name || '');
}

function maxVariantPrice(product) {
  return Math.max(0, ...(product.variants || [])
    .map(variant => Number.parseFloat(variant.price))
    .filter(price => Number.isFinite(price) && price > 0));
}

function hasFrame(product) {
  return /With Frame|Complete/i.test(product.title || '') ||
    (product.tags || []).includes('attribute_includes_frame|true');
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
    if (!(product.tags || []).includes('brand|Huawei')) continue;
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

  if (candidates.size !== 82) throw new Error(`Expected 82 Huawei/Honor models, got ${candidates.size}`);
  const currentRows = (config.services || []).filter(isHuaweiDisplay);
  const generated = [...candidates.values()].map(item => ({
    name: `Huawei ${item.model} Displaytausch (Original)`,
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
  const firstIndex = services.findIndex(isHuaweiDisplay);
  const insertIndex = services.slice(0, firstIndex < 0 ? services.length : firstIndex)
    .filter(service => !isHuaweiDisplay(service)).length;
  const updatedServices = services.filter(service => !isHuaweiDisplay(service));
  updatedServices.splice(insertIndex, 0, ...generated);

  const audit = [...candidates.values()]
    .sort((left, right) => collator.compare(left.model, right.model))
    .map(item => ({ ...item, markup: 75, customerPrice: roundCustomerPrice(item.purchasePrice) }));
  const result = {
    generatedAt: new Date().toISOString(),
    source: DISPLAY_ENDPOINT,
    summary: {
      sourceProducts: products.length,
      sourceModels: candidates.size,
      currentHuaweiRows: currentRows.length,
      removedUnsupportedRows: currentRows.length - generated.length,
      generatedHuaweiRows: generated.length,
      qualityCounts: Object.fromEntries([...QUALITY_SCORE.keys()].map(quality => [quality, audit.filter(item => item.quality === quality).length])),
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
