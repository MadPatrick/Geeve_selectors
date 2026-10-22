/**
 * Rooktest in een echte browser (zie CLAUDE.md, "Browsercompatibiliteit"): opent de hoofdpagina's van de portal en meldt
 * JavaScript-fouten, console-fouten en mislukte verzoeken naar eigen bestanden. Gebruik:
 *   php -S 127.0.0.1:8099 -t .  (in een ander venster), daarna
 *   NODE_PATH=$(npm root -g) node tools/browsertest.js [basis-url] [chromium|firefox|webkit]
 * Werkt met de browsers die Playwright lokaal heeft staan (Chromium wel, Firefox/WebKit alleen als geinstalleerd).
 * IE11 en Edge Legacy bestaan niet in Playwright en zijn hiermee dus niet te testen.
 */
const base = (process.argv[2] || 'http://127.0.0.1:8099').replace(/\/$/, '');
const naam = process.argv[3] || 'chromium';
const paden = ['/', '/hoses/', '/adapters/', '/stauff/', '/configurator/', '/slkprinten/', '/gebogenbuis/', '/klantartikel/',
  '/klantkorting/', '/reports/offerte-import/', '/reports/orderhistorie/', '/reports/msp/', '/stickers/'];

(async () => {
  const pw = require('playwright');
  let browser;
  try {
    browser = await pw[naam].launch({ executablePath: naam === 'chromium' ? process.env.CHROMIUM_PATH || undefined : undefined });
  } catch (e) {
    console.log('Browser ' + naam + ' niet beschikbaar: ' + String(e.message).split('\n')[0]);
    process.exit(2);
  }
  let fouten = 0;
  for (const pad of paden) {
    const page = await browser.newPage();
    const meldingen = [];
    page.on('pageerror', (e) => meldingen.push('JS-fout: ' + e.message));
    page.on('console', (m) => { if (m.type() === 'error') meldingen.push('console: ' + m.text()); });
    page.on('requestfailed', (r) => { if (r.url().indexOf(base) === 0) meldingen.push('verzoek mislukt: ' + r.url()); });
    page.on('response', (r) => { if (r.url().indexOf(base) === 0 && r.status() >= 400) meldingen.push('HTTP ' + r.status() + ': ' + r.url()); });
    let status = '?';
    try {
      const res = await page.goto(base + pad, { waitUntil: 'networkidle', timeout: 20000 });
      status = res ? res.status() : '?';
    } catch (e) {
      meldingen.push('laden mislukt: ' + String(e.message).split('\n')[0]);
    }
    console.log((meldingen.length ? 'LET OP ' : 'ok     ') + pad + '  (HTTP ' + status + ')');
    meldingen.forEach((m) => console.log('         ' + m));
    fouten += meldingen.length;
    await page.close();
  }
  await browser.close();
  console.log('\n' + naam + ': ' + fouten + ' melding(en). Pagina\'s die een database nodig hebben kunnen buiten de server fouten geven.');
  process.exit(fouten ? 1 : 0);
})();
