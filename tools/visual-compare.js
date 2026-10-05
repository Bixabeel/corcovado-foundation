/*
 * Full-page screenshots of every original page and its WordPress version at several widths.
 * External requests are blocked on both sides so the comparison is deterministic.
 * Usage: node tools/visual-compare.js ORIGINAL_BASE WP_BASE OUT_DIR pairs.json width[,width…]
 * (needs the "playwright" package; Chromium path can be set with CHROMIUM_PATH)
 */
const { chromium } = require('playwright');
const fs = require('fs');
(async () => {
  const [,, obase, wbase, out, pairsFile, widthsArg] = process.argv;
  const pairs = JSON.parse(fs.readFileSync(pairsFile, 'utf8'));
  const widths = widthsArg.split(',').map(Number);
  fs.mkdirSync(out, { recursive: true });
  const browser = await chromium.launch({ executablePath: process.env.CHROMIUM_PATH || '/opt/pw-browsers/chromium' });
  for (const width of widths) {
    const ctx = await browser.newContext({ viewport: { width, height: 900 }, reducedMotion: 'reduce' });
    await ctx.route('**/*', (route) => {
      const u = route.request().url();
      if (u.startsWith(obase) || u.startsWith(wbase)) return route.continue();
      return route.abort();
    });
    const page = await ctx.newPage();
    for (const [file, wp] of pairs) {
      const name = file.replace(/[\/.]/g, '_');
      for (const [side, url] of [['orig', obase + '/' + file], ['wp', wbase + wp]]) {
        await page.goto(url, { waitUntil: 'load' }).catch(() => {});
        await page.waitForTimeout(300);
        await page.evaluate(() => document.querySelectorAll('.reveal').forEach(el => el.classList.add('is-visible')));
        await page.screenshot({ path: `${out}/${name}-${width}-${side}.png`, fullPage: true });
      }
    }
    await ctx.close();
  }
  await browser.close();
})();
