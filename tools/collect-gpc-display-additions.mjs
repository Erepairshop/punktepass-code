import { writeFile } from 'node:fs/promises';

const OUTPUT_ARG = process.argv.indexOf('--output');
const outputPath = OUTPUT_ARG >= 0 ? process.argv[OUTPUT_ARG + 1] : '';
const GROUP_ARG = process.argv.indexOf('--group');
const selectedGroup = GROUP_ARG >= 0 ? process.argv[GROUP_ARG + 1] : '';
const CDP_ENDPOINT = process.env.GPC_CDP_ENDPOINT || 'http://127.0.0.1:9227';

const TARGETS = {
  samsung: ['A7', 'Alpha', 'S5 Active', 'S5 Mini', 'S6'],
  huawei: [
    'Enjoy 5', 'Honor 4X', 'Honor 7C', 'Honor 7X', 'Honor 8', 'Honor 8X Max',
    'Honor 9', 'Honor 9 Lite', 'Honor 9X Lite', 'Honor 10', 'Honor 10i',
    'Honor 20 Pro', 'Honor 200 Pro', 'Honor 300', 'Honor 300 Pro',
    'Honor 300 Ultra', 'Honor 400 Lite 5G', 'Honor 400 Pro',
    'Honor Magic 4 Pro', 'Honor Magic 5', 'Honor Magic 5 Pro',
    'Honor Magic 6 Pro', 'Honor Magic 7 Pro', 'Honor Magic V2 5G',
    'Honor Magic V3', 'Honor View 10', 'Honor X5 Plus', 'Honor X5C Plus',
    'Honor X6A', 'Honor X6B', 'Honor X6C', 'Honor X7B', 'Honor X7C 5G',
    'Honor X8B', 'Honor X8C 4G', 'Honor X9 5G', 'Honor X9C', 'Honor X40',
    'Mate 9 Lite', 'Mate 10 Lite', 'Mate 10 Pro', 'Mate 20 X', 'Mate 60 Pro+',
    'Mate X2', 'Nova', 'Nova 2', 'Nova 10', 'Nova 12 SE', 'Nova 12i',
    'Nova 12s', 'Nova Y91', 'P8', 'P10', 'P10 Lite', 'P40 Pro Plus',
    'P50 Pocket', 'P60 Pro', 'Y5 (2019)', 'Y6 Pro', 'Y7 Prime'
  ],
  xiaomi: [
    'Poco F3 GT', 'Poco F4', 'Poco F4 GT', 'Poco F5 Pro', 'Poco F7 5G',
    'Poco M6 Pro 4G', 'Poco M6 Pro 5G', 'Poco M7 Pro 5G', 'Poco M8 5G',
    'Poco M8 Pro 5G', 'Poco X3', 'Poco X4 GT', 'Poco X8 Pro 5G',
    'Poco X8 Pro Max', 'Redmi 7', 'Redmi 8A', 'Redmi 10A', 'Redmi A4 5G',
    'Redmi A7 Pro 4G', 'Redmi Go', 'Redmi Note 2', 'Redmi Note 4X',
    'Redmi Note 5', 'Redmi Note 5 Pro', 'Redmi Note 5A Prime',
    'Redmi Note 11E Pro 5G', 'Redmi Note 14S', 'Redmi Note 15 Pro 4G',
    'Redmi Note 15 Pro+ 5G'
  ]
};

const delay = ms => new Promise(resolve => setTimeout(resolve, ms));

async function connectPage() {
  let pages = [];
  const deadline = Date.now() + 15000;
  while (Date.now() < deadline) {
    try {
      pages = await fetch(`${CDP_ENDPOINT}/json/list`).then(response => response.json());
      if (pages.length) break;
    } catch {}
    await delay(500);
  }
  const page = pages.find(item => item.type === 'page' && item.url.startsWith('https://www.gsmpartscenter.com'));
  if (!page) throw new Error('Authenticated GSM Parts Center browser page not found');
  const ws = new WebSocket(page.webSocketDebuggerUrl);
  await new Promise((resolve, reject) => {
    ws.addEventListener('open', resolve, { once: true });
    ws.addEventListener('error', reject, { once: true });
  });
  let nextId = 1;
  const pending = new Map();
  ws.addEventListener('message', event => {
    const message = JSON.parse(event.data);
    if (!message.id || !pending.has(message.id)) return;
    const item = pending.get(message.id);
    pending.delete(message.id);
    if (message.error) item.reject(new Error(message.error.message));
    else item.resolve(message.result);
  });
  return {
    ws,
    send(method, params = {}) {
      const id = nextId++;
      return new Promise((resolve, reject) => {
        pending.set(id, { resolve, reject });
        ws.send(JSON.stringify({ id, method, params }));
      });
    }
  };
}

async function main() {
  const cdp = await connectPage();
  const globalObject = await cdp.send('Runtime.evaluate', { expression: 'globalThis' });
  const result = await cdp.send('Runtime.callFunctionOn', {
    objectId: globalObject.result.objectId,
    awaitPromise: true,
    returnByValue: true,
    functionDeclaration: `async function(targetGroups) {
      const delay = ms => new Promise(resolve => setTimeout(resolve, ms));
      const parser = new DOMParser();
      const result = {};
      const searchPrefix = {
        samsung: model => 'Samsung Galaxy ' + model,
        huawei: model => 'Huawei ' + model,
        xiaomi: model => 'Xiaomi ' + model
      };
      const parseField = (text, label) => {
        const escaped = label;
        return text.match(new RegExp('(?:^|\\n)' + escaped + '\\n([^\\n]+)', 'i'))?.[1]?.trim() || '';
      };
      const fetchText = async url => {
        for (let attempt = 0; attempt < 6; attempt += 1) {
          const response = await fetch(url, { credentials: 'include' });
          if (response.ok) return response.text();
          if (response.status !== 429) throw new Error('HTTP ' + response.status + ' for ' + url);
          await delay(2500 * (attempt + 1));
        }
        throw new Error('Repeated HTTP 429 for ' + url);
      };
      const normalize = value => String(value || '')
        .toLowerCase()
        .replace(/lcd display.*$/i, '')
        .replace(/[^a-z0-9+]+/g, ' ')
        .replace(/\\s+/g, ' ')
        .trim();
      for (const [group, models] of Object.entries(targetGroups)) {
        result[group] = [];
        for (let offset = 0; offset < models.length; offset += 2) {
          const batch = models.slice(offset, offset + 2);
          const items = await Promise.all(batch.map(async model => {
            const query = searchPrefix[group](model) + ' LCD Display Touchscreen';
            const searchUrl = '/en/catalogsearch/result/?q=' + encodeURIComponent(query);
            const searchHtml = await fetchText(searchUrl);
            const searchDoc = parser.parseFromString(searchHtml, 'text/html');
            const links = [...searchDoc.querySelectorAll('.product-item-link')]
              .map(link => ({ name: link.textContent.trim(), href: link.href }))
              .filter(item => /LCD Display\\s*\\+\\s*Touchscreen/i.test(item.name))
              .filter(item => {
                const wanted = normalize(model).split(' ').filter(Boolean);
                const found = normalize(item.name);
                return wanted.every(token => found.includes(token));
              })
              .slice(0, 15);
            const products = [];
            for (let index = 0; index < links.length; index += 5) {
              const productBatch = links.slice(index, index + 5);
              const details = await Promise.all(productBatch.map(async link => {
                const html = await fetchText(link.href);
                const doc = parser.parseFromString(html, 'text/html');
                const main = doc.querySelector('.product-info-main');
                const text = main?.innerText || main?.textContent || '';
                const priceNode = main?.querySelector('[data-price-amount]');
                return {
                  name: link.name,
                  href: link.href,
                  sku: parseField(text, 'SKU'),
                  availability: parseField(text, 'Availability'),
                  color: parseField(text, 'Color'),
                  brand: parseField(text, 'Brand'),
                  sourceModel: parseField(text, 'Model'),
                  productType: parseField(text, 'Product Type'),
                  quality: parseField(text, 'Quality'),
                  price: Number.parseFloat(priceNode?.getAttribute('data-price-amount') || ''),
                  withFrame: /(?:\\+ Frame|With Frame|Complete Housing)/i.test(link.name + ' ' + text),
                  outerDisplay: /(?:Outer Display|Front LCD|Small LCD|Sub Display)/i.test(link.name + ' ' + text)
                };
              }));
              products.push(...details);
              await delay(150);
            }
            return { model, query, products };
          }));
          result[group].push(...items);
          await delay(900);
        }
      }
      return result;
    }`,
    arguments: [{ value: selectedGroup ? { [selectedGroup]: TARGETS[selectedGroup] } : TARGETS }]
  });
  cdp.ws.close();
  if (result.exceptionDetails) {
    throw new Error(result.exceptionDetails.exception?.description || result.exceptionDetails.text || 'Browser collection failed');
  }
  const collected = result.result.value;
  const payload = {
    collectedAt: new Date().toISOString(),
    source: 'https://www.gsmpartscenter.com/',
    targets: TARGETS,
    groups: collected
  };
  const serialized = `${JSON.stringify(payload, null, 2)}\n`;
  if (outputPath) await writeFile(outputPath, serialized, 'utf8');
  else process.stdout.write(serialized);
  console.error(JSON.stringify({
    collectedAt: payload.collectedAt,
    targets: Object.fromEntries(Object.entries(collected).map(([group, items]) => [group, items.length])),
    products: Object.fromEntries(Object.entries(collected).map(([group, items]) => [group, items.reduce((sum, item) => sum + item.products.length, 0)]))
  }));
}

main().catch(error => {
  console.error(error instanceof Error ? error.stack : String(error));
  process.exitCode = 1;
});
