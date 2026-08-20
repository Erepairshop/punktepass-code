import { writeFile } from 'node:fs/promises';

const DISPLAY_ENDPOINT = 'https://4phones.eu/collections/displays/products.json';
const CONFIG_ENDPOINT = 'https://punktepass.de/wp-admin/admin-ajax.php';
const collator = new Intl.Collator('de', { numeric: true, sensitivity: 'base' });

function tagsWithPrefix(product, prefix) {
  return (product.tags || []).filter(tag => tag.startsWith(prefix)).map(tag => tag.slice(prefix.length));
}

function normalizeModel(input) {
  const value = String(input || '')
    .trim()
    .replace(/^For Apple\s+/i, '')
    .replace(/^Apple\s+/i, '')
    .replace(/\s+/g, ' ');
  return /^iPhone\s+/i.test(value) ? value.replace(/^iphone/i, 'iPhone') : `iPhone ${value}`;
}

function extractServiceModel(name) {
  return normalizeModel(String(name || '').replace(/\s+Displaytausch\s*\(Original\).*$/i, ''));
}

function maxVariantPrice(product) {
  return Math.max(0, ...(product.variants || [])
    .map(variant => Number.parseFloat(variant.price))
    .filter(price => Number.isFinite(price) && price > 0));
}

function roundCustomerPrice(purchasePrice) {
  return Math.ceil((purchasePrice + 120) / 5) * 5;
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
    if (!(product.tags || []).includes('device_type|Phone')) continue;
    if (!(product.tags || []).includes('attribute_quality|Servicepack')) continue;
    if (!/iPhone/i.test(product.title || '')) continue;
    if (/Calibrated Used|Pulled|Refurbished/i.test(product.title || '')) continue;
    const price = maxVariantPrice(product);
    if (price <= 0) continue;
    for (const sourceModel of tagsWithPrefix(product, 'model|')) {
      const model = normalizeModel(sourceModel);
      const existing = candidates.get(model);
      if (!existing || price > existing.purchasePrice) {
        candidates.set(model, {
          model,
          purchasePrice: price,
          available: (product.variants || []).some(variant => variant.available),
          title: product.title,
          handle: product.handle,
          sku: product.variants?.[0]?.sku || ''
        });
      }
    }
  }

  if (candidates.size !== 15) throw new Error(`Expected 15 new iPhone Service Pack models, got ${candidates.size}`);
  const generated = [...candidates.values()]
    .sort((left, right) => collator.compare(left.model, right.model))
    .map(item => ({
      name: `${item.model} Displaytausch (Original)`,
      category: 'Displaytausch Original',
      price: `${roundCustomerPrice(item.purchasePrice)} EUR`,
      time: '1 Std'
    }));
  const generatedByModel = new Map(generated.map(service => [extractServiceModel(service.name), service]));
  const updatedModels = new Set();
  const updatedServices = (config.services || []).map(service => {
    if (service.category !== 'Displaytausch Original' || !/^iPhone\s+/i.test(service.name || '')) return service;
    const model = extractServiceModel(service.name);
    if (!generatedByModel.has(model)) return service;
    updatedModels.add(model);
    return generatedByModel.get(model);
  });
  const missing = generated.filter(service => !updatedModels.has(extractServiceModel(service.name)));
  let lastIphoneIndex = -1;
  for (let index = 0; index < updatedServices.length; index += 1) {
    const service = updatedServices[index];
    if (service.category === 'Displaytausch Original' && /^iPhone\s+/i.test(service.name || '')) lastIphoneIndex = index;
  }
  updatedServices.splice(lastIphoneIndex + 1, 0, ...missing);

  const audit = [...candidates.values()]
    .sort((left, right) => collator.compare(left.model, right.model))
    .map(item => ({
      ...item,
      markup: 120,
      customerPrice: roundCustomerPrice(item.purchasePrice)
    }));
  const result = {
    generatedAt: new Date().toISOString(),
    source: DISPLAY_ENDPOINT,
    summary: {
      sourceProducts: products.length,
      servicePackModels: candidates.size,
      updatedExistingRows: updatedModels.size,
      insertedRows: missing.length,
      oldServiceCount: (config.services || []).length,
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
