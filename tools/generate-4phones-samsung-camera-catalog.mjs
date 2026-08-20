import { writeFile } from 'node:fs/promises';

const CAMERA_ENDPOINT = 'https://4phones.eu/collections/camera-front-back/products.json';
const CONFIG_ENDPOINT = 'https://punktepass.de/wp-admin/admin-ajax.php';
const collator = new Intl.Collator('de', { numeric: true, sensitivity: 'base' });

function tagsWithPrefix(product, prefix) {
  return (product.tags || []).filter(tag => tag.startsWith(prefix)).map(tag => tag.slice(prefix.length));
}

function normalizeModel(input) {
  let value = String(input || '').trim().replace(/^Samsung\s+/i, '');
  if (!/^Galaxy\s+/i.test(value)) value = `Galaxy ${value}`;
  return value
    .replace(/\s+SM-[A-Z0-9/-]+/gi, '')
    .replace(/\s+[A-Z]\d{3,4}[A-Z]{0,3}(?:\/[A-Z]\d{3,4}[A-Z]{0,3})*/g, '')
    .replace(/\s*\((?:19|20)\d{2}\)/g, '')
    .replace(/\s*\((?:EU|Non.?EU|Global|USA|US)\)/gi, '')
    .replace(/Galaxy Fold\s*([2-7])\s*\(?(5G)?\)?/i, 'Galaxy Z Fold $1 $2')
    .replace(/Galaxy Z Flip([3-7])/i, 'Galaxy Z Flip $1')
    .replace(/Galaxy Z Fold([2-7])/i, 'Galaxy Z Fold $1')
    .replace(/Galaxy Z Flip\s*\(5G\)/i, 'Galaxy Z Flip 5G')
    .replace(/Galaxy Z (Flip|Fold) (\d+)\s*\(5G\)/i, 'Galaxy Z $1 $2 5G')
    .replace(/Galaxy Xcover\s*/i, 'Galaxy XCover ')
    .replace(/Galaxy A12\s*\(Nacho\)/i, 'Galaxy A12 Nacho')
    .replace(/Galaxy S(\d+) Plus\b/gi, 'Galaxy S$1+')
    .replace(/Galaxy Note (\d+) Plus\b/gi, 'Galaxy Note $1+')
    .replace(/\bPlus\b/gi, '+')
    .replace(/\s*\+\s*/g, '+')
    .replace(/\s+/g, ' ')
    .trim();
}

function exactKey(model) {
  return normalizeModel(model).toLowerCase();
}

function baseKey(model) {
  return exactKey(model).replace(/\s+(?:4g|5g)\b/g, '');
}

function extractDisplayModel(name) {
  return normalizeModel(String(name || '')
    .replace(/^Samsung\s+/i, '')
    .replace(/\s+Displaytausch\s*\(Original\).*$/i, ''));
}

function extractCameraModel(name) {
  return normalizeModel(String(name || '')
    .replace(/^Samsung\s+/i, '')
    .replace(/\s+Hauptkamera Austausch.*$/i, ''));
}

function cameraRole(title) {
  if (/Camera Set/i.test(title)) return 'set';
  if (/Periscope/i.test(title)) return 'periscope';
  if (/Ultrawide|Ultra Wide/i.test(title)) return 'ultrawide';
  if (/Telephoto|Tele Camera/i.test(title)) return 'telephoto';
  if (/Macro/i.test(title)) return 'macro';
  if (/Depth/i.test(title)) return 'depth';
  if (/Wide|Standard/i.test(title)) return 'wide';
  return 'other';
}

function maxVariantPrice(product) {
  return Math.max(0, ...(product.variants || [])
    .map(variant => Number.parseFloat(variant.price))
    .filter(price => Number.isFinite(price) && price > 0));
}

function maxPriced(items) {
  return items.reduce((best, item) => !best || item.price > best.price ? item : best, null);
}

function roundCustomerPrice(partsPrice) {
  return Math.ceil((partsPrice + 75) / 5) * 5;
}

async function fetchAllCameras() {
  const products = [];
  for (let page = 1; page <= 10; page += 1) {
    const response = await fetch(`${CAMERA_ENDPOINT}?limit=250&page=${page}`);
    if (!response.ok) throw new Error(`4Phones camera page ${page}: HTTP ${response.status}`);
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
  const [products, config] = await Promise.all([fetchAllCameras(), fetchCurrentConfig()]);
  const services = config.services || [];
  const targetModels = [...new Set(services
    .filter(service => service.category === 'Displaytausch Original' && /^Samsung Galaxy/i.test(service.name || ''))
    .map(service => extractDisplayModel(service.name)))]
    .sort(collator.compare);
  if (targetModels.length < 150) throw new Error(`Expected at least 150 Samsung models, got ${targetModels.length}`);

  const cameraProducts = products
    .filter(product => (product.tags || []).includes('brand|Samsung'))
    .filter(product => (product.tags || []).includes('device_type|Phone'))
    .filter(product => (product.tags || []).includes('attribute_camera_type|Back camera') || /Back Camera/i.test(product.title || ''))
    .filter(product => !/Front Camera|Lens Cover|Camera Glass/i.test(product.title || ''))
    .map(product => ({
      handle: product.handle,
      title: product.title,
      price: maxVariantPrice(product),
      available: (product.variants || []).some(variant => variant.available),
      quality: tagsWithPrefix(product, 'attribute_quality|')[0] || '',
      role: cameraRole(product.title || ''),
      models: tagsWithPrefix(product, 'model|').map(normalizeModel)
    }))
    .filter(product => product.price > 0 && product.models.length > 0);

  const candidatesFor = model => {
    const exact = cameraProducts.filter(product => product.models.some(source => exactKey(source) === exactKey(model)));
    if (exact.length) return exact;
    return cameraProducts.filter(product => product.models.some(source => baseKey(source) === baseKey(model)));
  };

  const currentRows = services.filter(service => service.category === 'Hauptkamera Austausch' && /^Samsung Galaxy/i.test(service.name || ''));
  const currentByBase = new Map(currentRows.map(service => [baseKey(extractCameraModel(service.name)), service]));
  const generatedRows = [];
  const audit = [];

  for (const model of targetModels) {
    const candidates = candidatesFor(model);
    const sets = candidates.filter(item => item.role === 'set');
    let selected = [];
    let method = '';
    if (sets.length) {
      selected = [maxPriced(sets)];
      method = 'set';
    } else if (candidates.length) {
      const byRole = new Map();
      for (const candidate of candidates) {
        const existing = byRole.get(candidate.role);
        if (!existing || candidate.price > existing.price) byRole.set(candidate.role, candidate);
      }
      selected = [...byRole.values()];
      method = selected.length >= 2 ? 'modules' : 'incomplete-modules';
    }

    const partsPrice = selected.reduce((sum, item) => sum + item.price, 0);
    const current = currentByBase.get(baseKey(model));
    if (!selected.length || method === 'incomplete-modules') {
      audit.push({ model, method: method || 'no-source', currentPrice: current?.price || null });
      continue;
    }
    const customerPrice = roundCustomerPrice(partsPrice);
    generatedRows.push({
      name: `Samsung ${model} Hauptkamera Austausch`,
      category: 'Hauptkamera Austausch',
      price: `${customerPrice} EUR`,
      time: current?.time || '1 Std'
    });
    audit.push({
      model,
      method,
      partsPrice: Number(partsPrice.toFixed(2)),
      markup: 75,
      customerPrice,
      sourceAvailable: selected.every(item => item.available),
      components: selected.map(item => ({
        role: item.role,
        price: item.price,
        quality: item.quality,
        available: item.available,
        title: item.title,
        handle: item.handle
      }))
    });
  }

  const pricedBaseKeys = new Set(audit
    .filter(item => item.method === 'set' || item.method === 'modules')
    .map(item => baseKey(item.model)));
  const generatedNames = new Set(generatedRows.map(row => row.name));
  for (const current of currentRows) {
    if (pricedBaseKeys.has(baseKey(extractCameraModel(current.name)))) continue;
    if (generatedNames.has(current.name)) continue;
    generatedRows.push(current);
    generatedNames.add(current.name);
  }

  generatedRows.sort((left, right) => collator.compare(extractCameraModel(left.name), extractCameraModel(right.name)));
  const firstIndex = services.findIndex(service => service.category === 'Hauptkamera Austausch' && /^Samsung Galaxy/i.test(service.name || ''));
  const insertIndex = services.slice(0, firstIndex < 0 ? services.length : firstIndex)
    .filter(service => !(service.category === 'Hauptkamera Austausch' && /^Samsung Galaxy/i.test(service.name || ''))).length;
  const updatedServices = services.filter(service => !(service.category === 'Hauptkamera Austausch' && /^Samsung Galaxy/i.test(service.name || '')));
  updatedServices.splice(insertIndex, 0, ...generatedRows);

  const result = {
    generatedAt: new Date().toISOString(),
    source: CAMERA_ENDPOINT,
    summary: {
      sourceProducts: products.length,
      samsungBackCameraProducts: cameraProducts.length,
      targetModels: targetModels.length,
      setPricedModels: audit.filter(item => item.method === 'set').length,
      modulePricedModels: audit.filter(item => item.method === 'modules').length,
      incompleteModuleModels: audit.filter(item => item.method === 'incomplete-modules').map(item => item.model),
      noSourceModels: audit.filter(item => item.method === 'no-source').map(item => item.model),
      generatedCameraRows: generatedRows.length,
      pricedCameraRows: generatedRows.filter(row => row.price).length,
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
