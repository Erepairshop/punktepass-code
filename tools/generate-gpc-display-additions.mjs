import { readFile, writeFile } from 'node:fs/promises';

const CONFIG_ENDPOINT = 'https://punktepass.de/wp-admin/admin-ajax.php';
const collator = new Intl.Collator('de', { numeric: true, sensitivity: 'base' });

const args = new Map();
for (let index = 2; index < process.argv.length; index += 2) {
  args.set(process.argv[index], process.argv[index + 1]);
}

const inputPaths = {
  samsung: args.get('--samsung'),
  huawei: args.get('--huawei'),
  xiaomi: args.get('--xiaomi')
};
const outputPath = args.get('--output');
if (!Object.values(inputPaths).every(Boolean) || !outputPath) {
  throw new Error('Usage: node generate-gpc-display-additions.mjs --samsung <json> --huawei <json> --xiaomi <json> --output <json>');
}

const HUAWEI_NEWER_MODELS = new Set([
  'Honor 90 Smart', 'Honor 200', 'Honor 200 Lite', 'Honor 200 Smart',
  'Honor 200 Pro', 'Honor 300', 'Honor 300 Pro', 'Honor 300 Ultra',
  'Honor 400 Lite 5G', 'Honor 400 Pro', 'Honor 400 Smart 5G',
  'Honor Magic 6 Lite', 'Honor Magic 6 Pro', 'Honor Magic 7 Pro',
  'Honor Magic V3', 'Honor X5C Plus', 'Honor X6C', 'Honor X7C 5G',
  'Honor X8C 4G', 'Honor X9C', 'Mate X6', 'Mate XT', 'Nova 12',
  'Nova 12 SE', 'Nova 12i', 'Nova 12s', 'Nova 13', 'Nova 13 Pro',
  'Pura 70', 'Pura 70 Pro', 'Pura 70 Pro+', 'Pura 70 Ultra',
  'Pura 80 Pro', 'Pura 80 Ultra'
]);
const HUAWEI_FOLDABLE_MODELS = new Set([
  'Honor Magic V2 5G', 'Honor Magic V3', 'P50 Pocket', 'Mate X2',
  'Mate X3', 'Mate X5', 'Mate X6', 'Mate Xs', 'Mate Xs 2', 'Mate XT'
]);
const XIAOMI_NEWER_MODELS = new Set([
  '14', '14 Ultra', '14T', '14T Pro', '15', '15 Pro', '15 Ultra',
  '15T', '15T Pro', '17', 'Civi 5 Pro', 'Poco C71', 'Poco C75 4G',
  'Poco C75 5G', 'Poco C85x', 'Poco F6', 'Poco F6 Pro', 'Poco F7 5G',
  'Poco F7 Pro', 'Poco F7 Ultra', 'Poco F8 Pro', 'Poco F8 Ultra',
  'Poco M6 4G', 'Poco M6 Pro 4G', 'Poco M7 Pro 5G', 'Poco M8 5G',
  'Poco M8 Pro 5G', 'Poco X6', 'Poco X6 Pro', 'Poco X7', 'Poco X7 Pro',
  'Poco X8 Pro 5G', 'Poco X8 Pro Max', 'Redmi 13 4G', 'Redmi 14C',
  'Redmi 14C 5G', 'Redmi 15 4G', 'Redmi 15 5G', 'Redmi 15a',
  'Redmi 15C', 'Redmi 15C 4G', 'Redmi A3', 'Redmi A3 Pro', 'Redmi A3x',
  'Redmi A4 5G', 'Redmi A5', 'Redmi A7 Pro 4G', 'Redmi K70 Ultra',
  'Redmi K80', 'Redmi K80 Pro', 'Redmi K80 Ultra', 'Redmi K90',
  'Redmi K90 Pro Max', 'Redmi Note 13 (2312DRAABC)', 'Redmi Note 13 4G',
  'Redmi Note 13 5G', 'Redmi Note 13 Pro', 'Redmi Note 13 Pro 4G',
  'Redmi Note 13 Pro Plus', 'Redmi Note 13R', 'Redmi Note 14 4G',
  'Redmi Note 14 5G', 'Redmi Note 14 Pro 4G', 'Redmi Note 14 Pro 5G',
  'Redmi Note 14S', 'Redmi Note 15 5G', 'Redmi Note 15 Pro 4G',
  'Redmi Note 15 Pro+ 5G', 'Redmi Turbo 3', 'Redmi Turbo 4',
  'Redmi Turbo 4 Pro'
]);

function normalize(value) {
  return String(value || '')
    .normalize('NFKD')
    .toLowerCase()
    .replace(/\bplus\b/g, '+')
    .replace(/[()]/g, ' ')
    .replace(/[^a-z0-9+]+/g, ' ')
    .replace(/\s+/g, ' ')
    .trim();
}

function exactProduct(group, model, product) {
  const base = String(product.name || '')
    .replace(/\s+LCD Display\s*\+\s*Touchscreen.*$/i, '')
    .replace(/\s+LCD Display.*$/i, '')
    .replace(/\((?!\d{4}\))[^)]*\)/g, '')
    .trim();
  const segments = base.split('/').map(segment => normalize(segment));
  const name = normalize(base);
  const wanted = normalize(model);
  if (group === 'samsung') {
    if (!name.startsWith('samsung galaxy ') || /\bgalaxy tab\b/.test(name)) return false;
    return segments.some(segment => segment === `samsung galaxy ${wanted}` || segment === `galaxy ${wanted}`);
  }
  if (group === 'huawei') {
    if (!name.startsWith('huawei ')) return false;
    return segments.some(segment =>
      segment === `huawei ${wanted}` || segment === wanted || segment.endsWith(` ${wanted}`) ||
      segment === `huawei ${wanted} front` || segment === `huawei ${wanted} small`
    );
  }
  if (!name.startsWith('xiaomi ')) return false;
  return segments.some(segment => segment === `xiaomi ${wanted}` || segment === wanted || segment.endsWith(` ${wanted}`));
}

function qualityScore(product) {
  const text = `${product.quality || ''} ${product.name || ''}`;
  if (/incell|soft oled|hard oled|premium quality|compatible/i.test(text)) return 0;
  if (/original service part|service ?pack/i.test(text)) return 4;
  if (/refurb/i.test(text)) return 3;
  if (/oem/i.test(text)) return 2;
  return product.quality ? 0 : 1;
}

function selectCandidate(group, item, outerOnly = false) {
  const candidates = item.products
    .filter(product => exactProduct(group, item.model, product))
    .filter(product => Number.isFinite(product.price) && product.price > 0)
    .filter(product => Boolean(product.outerDisplay) === outerOnly)
    .map(product => ({ ...product, qualityScore: qualityScore(product) }))
    .filter(product => product.qualityScore > 0);
  if (group === 'samsung') {
    const original = candidates.filter(product => product.qualityScore === 4);
    const noFrame = /^S\d/i.test(item.model) ? original.filter(product => !product.withFrame) : [];
    const pool = noFrame.length ? noFrame : original;
    return pool.sort((left, right) => right.price - left.price)[0] || null;
  }
  return candidates.sort((left, right) =>
    right.qualityScore - left.qualityScore ||
    Number(right.withFrame) - Number(left.withFrame) ||
    right.price - left.price
  )[0] || null;
}

function roundPrice(purchasePrice, markup) {
  return Math.ceil((purchasePrice + markup) / 5) * 5;
}

function samsungFamily(model) {
  if (/^Galaxy A\d/i.test(model)) return 'A';
  if (/^Galaxy S\d/i.test(model)) return 'S';
  if (/^Galaxy Z Flip/i.test(model)) return 'Z Flip';
  if (/^Galaxy Z Fold/i.test(model)) return 'Z Fold';
  if (/^Galaxy M/i.test(model)) return 'M';
  if (/^Galaxy Note/i.test(model)) return 'Note';
  if (/^Galaxy J/i.test(model)) return 'J';
  if (/^Galaxy XCover/i.test(model)) return 'XCover';
  return 'Other';
}

function samsungMarkup(model) {
  const family = samsungFamily(model);
  if (family === 'A') return 70;
  if (family === 'S' || family === 'Z Flip' || family === 'Z Fold') return 100;
  return 75;
}

function displayService(group, model, candidate) {
  const normalizedModel = group === 'samsung' ? `Galaxy ${model}` : model;
  const markup = group === 'samsung' ? samsungMarkup(normalizedModel) : 75;
  const baseName = group === 'samsung'
    ? `Samsung ${normalizedModel}`
    : group === 'huawei'
      ? `Huawei ${model}`
      : /^(?:Redmi|Poco|Pocophone)\s/i.test(model) ? model : `Xiaomi ${model}`;
  return {
    row: {
      name: `${baseName} Displaytausch (Original)`,
      category: 'Displaytausch Original',
      price: `${roundPrice(candidate.price, markup)} EUR`,
      time: '1 Std'
    },
    audit: {
      group, model, purchasePrice: candidate.price, markup,
      customerPrice: roundPrice(candidate.price, markup), quality: candidate.quality || 'not labelled',
      withFrame: candidate.withFrame, sourceName: candidate.name, sourceUrl: candidate.href
    }
  };
}

function outerDisplayService(group, model, candidate) {
  const baseName = group === 'huawei' ? `Huawei ${model}` : `Samsung Galaxy ${model}`;
  const markup = group === 'samsung' ? 90 : 75;
  return {
    row: {
      name: `${baseName} Außendisplaytausch (Original)`,
      category: 'Außendisplaytausch Original',
      price: `${roundPrice(candidate.price, markup)} EUR`,
      time: '1 Std'
    },
    audit: {
      group, model, service: 'outer display', purchasePrice: candidate.price, markup,
      customerPrice: roundPrice(candidate.price, markup), quality: candidate.quality || 'not labelled',
      withFrame: candidate.withFrame, sourceName: candidate.name, sourceUrl: candidate.href
    }
  };
}

function isSamsungName(name) { return /^Samsung Galaxy\s/i.test(name || ''); }
function isHuaweiName(name) { return /^Huawei\s/i.test(name || ''); }
function isXiaomiName(name) { return /^(?:Xiaomi|Redmi|Poco|Pocophone)\s/i.test(name || ''); }

function extractDisplayModel(group, name) {
  let value = String(name || '').replace(/\s+(?:Displaytausch|Außendisplaytausch)\s*\(Original\).*$/i, '').trim();
  if (group === 'samsung') value = value.replace(/^Samsung\s+/i, '');
  if (group === 'huawei') value = value.replace(/^Huawei\s+/i, '');
  if (group === 'xiaomi') value = value.replace(/^Xiaomi\s+/i, '');
  return value;
}

function modelKey(group, model) {
  return normalize(extractDisplayModel(group, model)).replace(/\b(?:4g|5g)\b/g, '').replace(/\s+/g, ' ').trim();
}

function exactModelKey(group, model) {
  return normalize(extractDisplayModel(group, model));
}

function displayNameMatches(group, name) {
  if (group === 'samsung') return isSamsungName(name);
  if (group === 'huawei') return isHuaweiName(name);
  return isXiaomiName(name);
}

function sortDisplayRows(group, rows) {
  const familyOrder = group === 'samsung'
    ? ['A', 'S', 'Z Flip', 'Z Fold', 'M', 'Note', 'J', 'XCover', 'Other']
    : group === 'huawei'
      ? ['Honor', 'P', 'P Smart', 'Mate', 'Nova', 'Pura', 'Y', 'Enjoy', 'Other']
      : ['Xiaomi', 'Mi', 'Redmi Note', 'Redmi', 'Poco F', 'Poco X', 'Poco M', 'Poco C', 'Poco', 'Other'];
  const family = model => {
    if (group === 'samsung') return samsungFamily(model);
    if (group === 'huawei') {
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
  };
  return rows.sort((left, right) => {
    const leftModel = extractDisplayModel(group, left.name);
    const rightModel = extractDisplayModel(group, right.name);
    return familyOrder.indexOf(family(leftModel)) - familyOrder.indexOf(family(rightModel)) ||
      collator.compare(leftModel, rightModel);
  });
}

function replaceRows(services, predicate, rows) {
  const firstIndex = services.findIndex(predicate);
  const kept = services.filter(service => !predicate(service));
  const insertIndex = firstIndex < 0
    ? kept.length
    : services.slice(0, firstIndex).filter(service => !predicate(service)).length;
  kept.splice(insertIndex, 0, ...rows);
  return kept;
}

function numericPart(model, prefix) {
  const match = model.match(new RegExp(`^Galaxy ${prefix}\\s*(\\d+)`, 'i'));
  return match ? Number(match[1]) : null;
}

function samsungAuxPrice(category, model, existingPrices) {
  const family = samsungFamily(model);
  if (category === 'Akku Original') {
    if (family === 'Z Flip' || family === 'Z Fold') return 120;
    if (family === 'S' || family === 'Note' || family === 'XCover') return 70;
    return 60;
  }
  if (category === 'Ladebuchse Austausch') return ['A', 'M', 'J'].includes(family) ? 50 : 70;
  const known = existingPrices.get(exactModelKey('samsung', model));
  if (known != null) return known;
  if (family === 'A' || family === 'M' || family === 'J' || family === 'Other') return 49;
  if (family === 'S') {
    const generation = numericPart(model, 'S') || 0;
    if (generation < 20) return /\b(?:Ultra|Edge)\b/i.test(model) ? 89 : /\+/.test(model) ? 79 : 69;
    const base = generation >= 26 ? 109 + ((generation - 26) * 10) : 79;
    if (/\b(?:FE|Lite)\b/i.test(model)) return Math.max(69, base - 10);
    if (/\b(?:Ultra|Edge)\b/i.test(model)) return base + 10;
    return base;
  }
  if (family === 'Note') return /20 Ultra/i.test(model) ? 99 : /20|10\+/i.test(model) ? 89 : /10/i.test(model) ? 79 : 69;
  if (family === 'XCover') return /7/i.test(model) ? 79 : /6|Pro/i.test(model) ? 69 : 59;
  if (family === 'Z Flip') return (numericPart(model, 'Z Flip') || 1) >= 6 ? 109 : /5/i.test(model) ? 99 : 89;
  if (family === 'Z Fold') return (numericPart(model, 'Z Fold') || 1) >= 7 ? 149 : /6/i.test(model) ? 139 : /5/i.test(model) ? 129 : 119;
  return 49;
}

function expandAuxiliaryServices(services, group) {
  const nameMatcher = group === 'samsung' ? isSamsungName : group === 'huawei' ? isHuaweiName : isXiaomiName;
  const displayModels = services
    .filter(service => /^(?:Displaytausch|Außendisplaytausch) Original$/.test(service.category || '') && nameMatcher(service.name))
    .map(service => ({
      model: extractDisplayModel(group, service.name),
      baseName: String(service.name).replace(/\s+(?:Displaytausch|Außendisplaytausch)\s*\(Original\).*$/i, '').trim()
    }))
    .filter((item, index, items) => items.findIndex(candidate => exactModelKey(group, candidate.model) === exactModelKey(group, item.model)) === index);
  const targets = group === 'samsung'
    ? ['Akku Original', 'Ladebuchse Austausch', 'Backcover/Rückseite Austausch']
    : ['Akku Original', 'Ladebuchse Austausch'];
  for (const category of targets) {
    const oldRows = services.filter(service => service.category === category && nameMatcher(service.name));
    const existingPrices = new Map(oldRows.map(service => [
      exactModelKey(group, service.name), Number.parseFloat(String(service.price || '').replace(',', '.'))
    ]));
    const rows = displayModels.map(item => {
      let price;
      if (group === 'samsung') price = samsungAuxPrice(category, item.model, existingPrices);
      else if (category === 'Akku Original') {
        price = group === 'huawei' && HUAWEI_FOLDABLE_MODELS.has(item.model) ? 120 : 65;
      } else {
        price = (group === 'huawei' ? HUAWEI_NEWER_MODELS : XIAOMI_NEWER_MODELS).has(item.model) ? 70 : 60;
      }
      const suffix = category === 'Akku Original' ? 'Akkutausch (Original)'
        : category === 'Ladebuchse Austausch' ? 'Ladebuchse Austausch'
          : 'Backcover Austausch';
      return { name: `${item.baseName} ${suffix}`, category, price: `${price} EUR`, time: category === 'Akku Original' ? '45 Min' : '1.5 Std' };
    });
    services = replaceRows(services, service => service.category === category && nameMatcher(service.name), rows);
  }
  return services;
}

async function fetchCurrentConfig() {
  const body = new URLSearchParams({ action: 'ppv_shop_widget_config', store_slug: 'erepairshop' });
  const response = await fetch(CONFIG_ENDPOINT, { method: 'POST', body });
  if (!response.ok) throw new Error(`PunktePass config: HTTP ${response.status}`);
  const payload = await response.json();
  if (!payload.success || !payload.data || !Array.isArray(payload.data.services)) throw new Error('Invalid PunktePass config payload');
  return payload.data;
}

const [config, ...inputs] = await Promise.all([
  fetchCurrentConfig(),
  ...Object.values(inputPaths).map(path => readFile(path, 'utf8').then(JSON.parse))
]);
let services = config.services;
const audit = [];
const missing = {};

for (const [index, group] of Object.keys(inputPaths).entries()) {
  const items = inputs[index].groups?.[group] || [];
  const additions = [];
  const outerAdditions = [];
  missing[group] = [];
  for (const item of items) {
    const candidate = selectCandidate(group, item);
    if (!candidate) {
      const outerCandidate = group === 'huawei' ? selectCandidate(group, item, true) : null;
      if (outerCandidate) {
        const generated = outerDisplayService(group, item.model, outerCandidate);
        outerAdditions.push(generated.row);
        audit.push(generated.audit);
      } else {
        missing[group].push(item.model);
      }
      continue;
    }
    const generated = displayService(group, item.model, candidate);
    additions.push(generated.row);
    audit.push(generated.audit);
  }
  const currentRows = services.filter(service =>
    service.category === 'Displaytausch Original' && displayNameMatches(group, service.name));
  const existingKeys = new Set(currentRows.map(service => modelKey(group, service.name)));
  const newRows = additions.filter(service => !existingKeys.has(modelKey(group, service.name)));
  services = replaceRows(
    services,
    service => service.category === 'Displaytausch Original' && displayNameMatches(group, service.name),
    sortDisplayRows(group, [...currentRows, ...newRows])
  );
  if (outerAdditions.length) {
    const currentOuterRows = services.filter(service =>
      service.category === 'Außendisplaytausch Original' && displayNameMatches(group, service.name));
    const existingOuterKeys = new Set(currentOuterRows.map(service => modelKey(group, service.name)));
    const newOuterRows = outerAdditions.filter(service => !existingOuterKeys.has(modelKey(group, service.name)));
    services = replaceRows(
      services,
      service => service.category === 'Außendisplaytausch Original' && displayNameMatches(group, service.name),
      sortDisplayRows(group, [...currentOuterRows, ...newOuterRows])
    );
  }
}

for (const group of Object.keys(inputPaths)) services = expandAuxiliaryServices(services, group);

const duplicateNames = [...new Set(services.map(service => service.name).filter((name, index, names) => names.indexOf(name) !== index))];
const managedCategories = new Set(['Displaytausch Original', 'Akku Original', 'Ladebuchse Austausch', 'Backcover/Rückseite Austausch']);
const missingPrices = services.filter(service =>
  managedCategories.has(service.category) &&
  [isSamsungName, isHuaweiName, isXiaomiName].some(matcher => matcher(service.name)) &&
  !service.price
).map(service => service.name);
if (duplicateNames.length || missingPrices.length) {
  throw new Error(`Generated catalog failed validation: ${duplicateNames.length} duplicate names, ${missingPrices.length} missing prices`);
}

const counts = Object.fromEntries(Object.keys(inputPaths).map(group => {
  const matcher = group === 'samsung' ? isSamsungName : group === 'huawei' ? isHuaweiName : isXiaomiName;
  return [group, {
    displays: services.filter(service => service.category === 'Displaytausch Original' && matcher(service.name)).length,
    outerDisplays: services.filter(service => service.category === 'Außendisplaytausch Original' && matcher(service.name)).length,
    batteries: services.filter(service => service.category === 'Akku Original' && matcher(service.name)).length,
    chargingPorts: services.filter(service => service.category === 'Ladebuchse Austausch' && matcher(service.name)).length
  }];
}));

const result = {
  generatedAt: new Date().toISOString(),
  source: 'Authenticated GSM Parts Center product pages',
  summary: {
    oldServiceCount: config.services.length,
    newServiceCount: services.length,
    selectedAdditions: Object.fromEntries(Object.keys(inputPaths).map(group => [group, audit.filter(item => item.group === group).length])),
    missing,
    counts,
    duplicateNames: duplicateNames.length,
    missingPrices: missingPrices.length
  },
  services,
  audit
};
await writeFile(outputPath, `${JSON.stringify(result, null, 2)}\n`, 'utf8');
process.stdout.write(`${JSON.stringify(result.summary, null, 2)}\n`);
