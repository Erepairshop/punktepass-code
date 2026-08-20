import { writeFile } from 'node:fs/promises';

const DISPLAY_ENDPOINT = 'https://4phones.eu/collections/displays/products.json';
const CONFIG_ENDPOINT = 'https://punktepass.de/wp-admin/admin-ajax.php';
const ORIGINAL_QUALITIES = new Set(['Servicepack', 'Service Pack Pre-Assembled', 'Original']);
const familyOrder = new Map([
  ['A', 0], ['S', 1], ['Z Flip', 2], ['Z Fold', 3], ['M', 4],
  ['Note', 5], ['J', 6], ['XCover', 7], ['Other', 8]
]);
const collator = new Intl.Collator('de', { numeric: true, sensitivity: 'base' });

function tagsWithPrefix(product, prefix) {
  return (product.tags || []).filter(tag => tag.startsWith(prefix)).map(tag => tag.slice(prefix.length));
}

function normalizeModel(input) {
  let value = String(input || '').trim().replace(/^Samsung\s+/i, '');
  if (!/^Galaxy\s+/i.test(value)) value = `Galaxy ${value}`;
  value = value
    .replace(/\s+SM-[A-Z0-9/-]+/gi, '')
    .replace(/\s+[A-Z]\d{3,4}[A-Z]{0,3}(?:\/[A-Z]\d{3,4}[A-Z]{0,3})*/g, '')
    .replace(/\s*\((?:19|20)\d{2}\)/g, '')
    .replace(/\s*\((?:EU|Non.?EU|Global|USA|US)\)/gi, '')
    .replace(/Galaxy Fold([23])\s*\(5G\)/i, 'Galaxy Z Fold $1 5G')
    .replace(/Galaxy Z Flip([3-7])/i, 'Galaxy Z Flip $1')
    .replace(/Galaxy Z Fold([4-7])/i, 'Galaxy Z Fold $1')
    .replace(/Galaxy Z Flip\s*\(5G\)/i, 'Galaxy Z Flip 5G')
    .replace(/Galaxy Z (Flip|Fold) (\d+)\s*\(5G\)/i, 'Galaxy Z $1 $2 5G')
    .replace(/Galaxy Xcover\s*/i, 'Galaxy XCover ')
    .replace(/Galaxy A12\s*\(Nacho\)/i, 'Galaxy A12 Nacho')
    .replace(/Galaxy S(\d+) Plus\b/gi, 'Galaxy S$1+')
    .replace(/\s+/g, ' ')
    .trim();
  return value;
}

function modelFamily(model) {
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

function baseModelKey(model) {
  return normalizeModel(model)
    .replace(/\s+(?:4G|5G)\b/gi, '')
    .replace(/\s+/g, ' ')
    .toLowerCase();
}

function extractCurrentModel(serviceName) {
  return normalizeModel(String(serviceName || '')
    .replace(/^Samsung\s+/i, '')
    .replace(/\s+(?:Au(?:ß|ss)endisplaytausch|Displaytausch).*$/i, ''));
}

function isSamsungDisplayService(service) {
  return /^Samsung\s+/i.test(service.name || '') &&
    /^(?:Displaytausch Original|Außendisplaytausch Original)$/i.test(service.category || '');
}

function roundCustomerPrice(purchasePrice, markup) {
  return Math.ceil((purchasePrice + markup) / 5) * 5;
}

function maxPrice(candidates) {
  if (!candidates.length) return null;
  return candidates.reduce((best, item) => item.price > best.price ? item : best);
}

function chooseMainCandidate(model, candidates) {
  const family = modelFamily(model);
  const nonSub = candidates.filter(item => !/\bSub\s+Display\b/i.test(item.title));
  if (family === 'S') {
    const withoutFrame = nonSub.filter(item => /\bWithout Frame\b/i.test(item.title));
    if (withoutFrame.length) return maxPrice(withoutFrame);
    const withFrame = nonSub.filter(item => /\bWith Frame\b/i.test(item.title));
    return maxPrice(withFrame.length ? withFrame : nonSub);
  }
  if (family === 'Z Flip' || family === 'Z Fold') {
    return maxPrice(nonSub.filter(item => /\bMain Display\b/i.test(item.title) && /\bWith Frame\b/i.test(item.title)));
  }
  const withFrame = nonSub.filter(item => /\bWith Frame\b/i.test(item.title));
  return maxPrice(withFrame.length ? withFrame : nonSub);
}

function markupFor(model) {
  const family = modelFamily(model);
  if (family === 'A') return 70;
  if (family === 'S' || family === 'Z Flip' || family === 'Z Fold') return 100;
  return 75;
}

function timeFor(model, external = false) {
  if (external) return '1 Std';
  const family = modelFamily(model);
  if (family === 'Z Fold') return '2 Std';
  if (family === 'Z Flip') return '1,5 Std';
  return '1 Std';
}

async function fetchAllDisplays() {
  const products = [];
  for (let page = 1; page <= 20; page += 1) {
    const response = await fetch(`${DISPLAY_ENDPOINT}?limit=250&page=${page}`);
    if (!response.ok) throw new Error(`4Phones page ${page}: HTTP ${response.status}`);
    const batch = (await response.json()).products || [];
    products.push(...batch);
    if (batch.length < 250) break;
  }
  return products;
}

async function fetchCurrentConfig() {
  const body = new URLSearchParams({
    action: 'ppv_shop_widget_config',
    store_slug: 'erepairshop'
  });
  const response = await fetch(CONFIG_ENDPOINT, { method: 'POST', body });
  if (!response.ok) throw new Error(`PunktePass config: HTTP ${response.status}`);
  const payload = await response.json();
  if (!payload.success || !payload.data) throw new Error('PunktePass config payload is invalid');
  return payload.data;
}

async function main() {
  const outputArg = process.argv.indexOf('--output');
  const outputPath = outputArg >= 0 ? process.argv[outputArg + 1] : '';
  const [products, config] = await Promise.all([fetchAllDisplays(), fetchCurrentConfig()]);
  const candidateMap = new Map();

  for (const product of products) {
    if (!(product.tags || []).includes('brand|Samsung')) continue;
    if (!(product.tags || []).includes('device_type|Phone')) continue;
    const qualities = tagsWithPrefix(product, 'attribute_quality|');
    if (!qualities.some(quality => ORIGINAL_QUALITIES.has(quality))) continue;
    const models = tagsWithPrefix(product, 'model|').map(normalizeModel).filter(model => !/^Galaxy Tab/i.test(model));
    for (const model of models) {
      if (!candidateMap.has(model)) candidateMap.set(model, []);
      for (const variant of product.variants || []) {
        const price = Number.parseFloat(variant.price);
        if (!Number.isFinite(price) || price <= 0) continue;
        candidateMap.get(model).push({
          title: product.title,
          price,
          available: Boolean(variant.available),
          sku: variant.sku || '',
          quality: qualities[0] || ''
        });
      }
    }
  }

  const currentSamsung = (config.services || []).filter(isSamsungDisplayService);
  const currentByBase = new Map();
  for (const service of currentSamsung) {
    if (/Au(?:ß|ss)endisplaytausch/i.test(service.name || '')) continue;
    currentByBase.set(baseModelKey(extractCurrentModel(service.name)), service);
  }

  const generated = [];
  const audit = [];
  const sourceBaseKeys = new Set([...candidateMap.keys()].map(baseModelKey));
  const sortedModels = [...candidateMap.keys()].sort((left, right) => {
    const familyDiff = (familyOrder.get(modelFamily(left)) ?? 99) - (familyOrder.get(modelFamily(right)) ?? 99);
    return familyDiff || collator.compare(left, right);
  });

  for (const model of sortedModels) {
    const candidates = candidateMap.get(model) || [];
    const main = chooseMainCandidate(model, candidates);
    const fallback = currentByBase.get(baseModelKey(model));
    const markup = markupFor(model);
    const price = main ? roundCustomerPrice(main.price, markup) : null;
    generated.push({
      name: `Samsung ${model} Displaytausch (Original)`,
      category: 'Displaytausch Original',
      ...(price ? { price: `${price} EUR` } : fallback?.price ? { price: fallback.price } : {}),
      time: timeFor(model)
    });
    audit.push({
      model,
      service: 'main',
      family: modelFamily(model),
      purchasePrice: main?.price ?? null,
      markup,
      customerPrice: price ?? fallback?.price ?? null,
      sourceTitle: main?.title ?? null,
      sourceAvailable: main?.available ?? null,
      fallback: !main && Boolean(fallback)
    });

    if (modelFamily(model) === 'Z Flip' || modelFamily(model) === 'Z Fold') {
      const external = maxPrice(candidates.filter(item => /\bSub\s+Display\b/i.test(item.title)));
      if (external) {
        const externalPrice = roundCustomerPrice(external.price, 90);
        generated.push({
          name: `Samsung ${model} Außendisplaytausch (Original)`,
          category: 'Außendisplaytausch Original',
          price: `${externalPrice} EUR`,
          time: timeFor(model, true)
        });
        audit.push({
          model,
          service: 'external',
          family: modelFamily(model),
          purchasePrice: external.price,
          markup: 90,
          customerPrice: externalPrice,
          sourceTitle: external.title,
          sourceAvailable: external.available,
          fallback: false
        });
      }
    }
  }

  for (const service of currentSamsung) {
    if (/Au(?:ß|ss)endisplaytausch/i.test(service.name || '')) continue;
    const model = extractCurrentModel(service.name);
    if (sourceBaseKeys.has(baseModelKey(model))) continue;
    generated.push({
      name: `Samsung ${model} Displaytausch (Original)`,
      category: 'Displaytausch Original',
      ...(service.price ? { price: service.price } : {}),
      time: service.time || timeFor(model)
    });
    audit.push({
      model,
      service: 'main',
      family: modelFamily(model),
      customerPrice: service.price || null,
      preservedExisting: true
    });
  }

  generated.sort((left, right) => {
    const leftModel = extractCurrentModel(left.name);
    const rightModel = extractCurrentModel(right.name);
    const familyDiff = (familyOrder.get(modelFamily(leftModel)) ?? 99) - (familyOrder.get(modelFamily(rightModel)) ?? 99);
    const modelDiff = collator.compare(leftModel, rightModel);
    const externalDiff = Number(/Au(?:ß|ss)endisplaytausch/i.test(left.name)) - Number(/Au(?:ß|ss)endisplaytausch/i.test(right.name));
    return familyDiff || modelDiff || externalDiff;
  });

  const services = config.services || [];
  const firstSamsungIndex = services.findIndex(isSamsungDisplayService);
  const insertIndex = services.slice(0, firstSamsungIndex < 0 ? services.length : firstSamsungIndex)
    .filter(service => !isSamsungDisplayService(service)).length;
  const updatedServices = services.filter(service => !isSamsungDisplayService(service));
  updatedServices.splice(insertIndex, 0, ...generated);

  const result = {
    generatedAt: new Date().toISOString(),
    source: DISPLAY_ENDPOINT,
    summary: {
      sourceProducts: products.length,
      sourceModels: candidateMap.size,
      currentSamsungRows: currentSamsung.length,
      generatedSamsungRows: generated.length,
      mainRows: audit.filter(item => item.service === 'main').length,
      externalRows: audit.filter(item => item.service === 'external').length,
      missingMainPriceRows: audit.filter(item => item.service === 'main' && item.customerPrice == null).map(item => item.model),
      preservedExistingRows: audit.filter(item => item.preservedExisting).map(item => item.model),
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
