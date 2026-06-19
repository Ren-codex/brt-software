/**
 * Pagination verification — inventory module
 * Covers:
 *   - AccountsPayableTab  (client-side, page 2 row # continues from 16)
 *   - ProductsTab         (server-side meta.from, page 1 row # = 1)
 *   - PurchaseRequestsTab (server-side meta.from, page 1 row # = 1)
 *   - PurchaseOrdersTab   (server-side meta.from, page 1 row # = 1)
 *   - InventoryStocksTab  (server-side meta.from, both Available and Consumed)
 *   - Escape key on modals (PayAccountsPayable, ReturnPurchaseOrder, CreatePurchaseOrder)
 *   - Close-btn style on ReturnPurchaseOrderModal (white bg / border)
 */
import { chromium } from 'playwright';

const BASE  = 'http://127.0.0.1:8000';
const EMAIL = 'administrator@example.com';
const PASS  = 'testpass123';

const steps    = [];
const findings = [];
const shots    = [];

function step(icon, action, result, evidence = '') {
  steps.push({ icon, action, result, evidence });
  console.log(`${icon} ${action}`);
  console.log(`   → ${result}`);
  if (evidence) console.log(`   ${evidence}`);
}

function find(level, note) {
  findings.push({ level, note });
  console.log(`  ${level === 'warn' ? '⚠️' : '🔍'} ${note}`);
}

async function ss(page, name) {
  const p = `/tmp/pag-${name}.png`;
  await page.screenshot({ path: p, fullPage: false });
  shots.push({ name, p });
  return p;
}

// ─── BOOT ────────────────────────────────────────────────────────────────────
const browser = await chromium.launch({ headless: true });
const ctx     = await browser.newContext({ viewport: { width: 1440, height: 900 } });
const page    = await ctx.newPage();

// ─── LOGIN ───────────────────────────────────────────────────────────────────
await page.goto(`${BASE}/login`);
await page.waitForLoadState('networkidle');
await page.locator('input[type="email"]').first().fill(EMAIL);
await page.locator('input[type="password"]').first().fill(PASS);
await page.locator('input[type="password"]').first().press('Enter');
await page.waitForURL(u => !u.toString().includes('/login'), { timeout: 15000 }).catch(() => {});
if (page.url().includes('/login')) {
  step('❌', 'Login', `Still on login — credentials rejected`, page.url());
  await browser.close(); process.exit(1);
}
step('✅', 'Login', `Redirected to ${page.url()}`);

// ─── HELPER: click inventory tab ────────────────────────────────────────────
async function goTab(label) {
  await page.goto(`${BASE}/inventory`);
  await page.waitForLoadState('networkidle');
  await page.waitForTimeout(800);
  const btn = page.locator('button, a').filter({ hasText: new RegExp(label, 'i') }).first();
  if (await btn.isVisible()) {
    await btn.click();
    await page.waitForTimeout(1500);
    return true;
  }
  return false;
}

// ─── HELPER: first-row # in the visible table ────────────────────────────────
async function firstRowNum(sel = 'tbody tr:first-child td:first-child') {
  return (await page.locator(sel).textContent().catch(() => '?')).trim();
}

// ─════════════════════════════════════════════════════════════════════════════
// 1. ACCOUNTS PAYABLE TAB — client-side pagination (pageSize=15, 18 seeded)
// ─════════════════════════════════════════════════════════════════════════════
await goTab('Accounts Payable');
await ss(page, '01-ap-tab');

const apRows = await page.locator('tbody tr.main-table-row').count();
step('✅', 'Accounts Payable tab — row count', `${apRows} rows visible`);

if (apRows > 0) {
  const r1 = await firstRowNum('tbody tr.main-table-row:first-child td:first-child');
  step(r1 === '1' ? '✅' : '❌', 'AP page 1 — first row #', `Got "${r1}" (expect "1")`);

  const nextBtn = page.locator('.ap-page-btn').filter({ hasText: /Next/i });
  if (await nextBtn.isVisible() && !await nextBtn.isDisabled()) {
    await nextBtn.click();
    await page.waitForTimeout(600);
    await ss(page, '02-ap-tab-page2');
    const r1p2 = await firstRowNum('tbody tr.main-table-row:first-child td:first-child');
    const passed = r1p2 === '16';
    step(
      passed ? '✅' : '❌',
      'AP page 2 — first row # continues (expect 16)',
      `Got "${r1p2}" ${passed ? '✓' : '← BUG: still resets to 1'}`
    );
    if (!passed) find('warn', `AP page 2 first row # = "${r1p2}", expected "16"`);

    // Probe: go back to page 1 — should return to row 1
    await page.locator('.ap-page-btn').filter({ hasText: /Prev/i }).click();
    await page.waitForTimeout(400);
    const back = await firstRowNum('tbody tr.main-table-row:first-child td:first-child');
    step(back === '1' ? '✅' : '❌', 'AP back to page 1 — row # resets to 1', `Got "${back}"`);
  } else {
    find('info', `AP "Next" button not visible — only ${apRows} rows (need >15 for page 2)`);
  }
} else {
  find('warn', 'AP tab: zero rows — seeded data not showing');
}

// ─════════════════════════════════════════════════════════════════════════════
// 2. PRODUCTS TAB — server-side (meta.from)
// ─════════════════════════════════════════════════════════════════════════════
const gotProducts = await goTab('Products');
if (gotProducts) {
  await ss(page, '03-products-tab');
  await page.waitForTimeout(600);
  const rows = await page.locator('tbody tr').count();
  step('✅', 'Products tab loaded', `${rows} rows`);
  if (rows > 0) {
    const r1 = await firstRowNum();
    step(r1 === '1' ? '✅' : '❌', 'Products page 1 — row #1 is "1"', `Got "${r1}"`);

    const nextBtn = page.locator('button, a').filter({ hasText: /next/i }).first();
    if (await nextBtn.isVisible() && !await nextBtn.isDisabled()) {
      await nextBtn.click();
      await page.waitForTimeout(1000);
      const r1p2 = await firstRowNum();
      step(
        r1p2 !== '1' ? '✅' : '❌',
        'Products page 2 — row # continues (should be 11)',
        `Got "${r1p2}"`
      );
      if (r1p2 === '1') find('warn', 'Products page 2 first row still shows "1"');
    } else {
      find('info', `Products: no page 2 available (${rows} ≤ 10)`);
    }
  }
} else {
  find('warn', 'Products tab button not found');
}

// ─════════════════════════════════════════════════════════════════════════════
// 3. PURCHASE REQUESTS TAB — server-side (meta.from)
// ─════════════════════════════════════════════════════════════════════════════
const gotPR = await goTab('Purchase Request|Requests');
if (gotPR) {
  await ss(page, '04-pr-tab');
  await page.waitForTimeout(600);
  const rows = await page.locator('tbody tr').count();
  step('✅', 'Purchase Requests tab loaded', `${rows} rows`);
  if (rows > 0) {
    const r1 = await firstRowNum();
    step(r1 === '1' ? '✅' : '❌', 'PR page 1 — row #1 is "1"', `Got "${r1}"`);
    find('info', `PR: ${rows} rows on page 1 — page 2 needs >10 records`);
  }
} else {
  find('warn', 'Purchase Requests tab button not found');
}

// ─════════════════════════════════════════════════════════════════════════════
// 4. PURCHASE ORDERS TAB — server-side (meta.from)
// ─════════════════════════════════════════════════════════════════════════════
const gotPO = await goTab('Purchase Order|Orders');
if (gotPO) {
  await ss(page, '05-po-tab');
  await page.waitForTimeout(600);
  const rows = await page.locator('tbody tr').count();
  step('✅', 'Purchase Orders tab loaded', `${rows} rows`);
  if (rows > 0) {
    const r1 = await firstRowNum();
    step(r1 === '1' ? '✅' : '❌', 'PO page 1 — row #1 is "1"', `Got "${r1}"`);
    find('info', `PO: ${rows} rows on page 1 — page 2 needs >10 records`);
  }
} else {
  find('warn', 'Purchase Orders tab button not found');
}

// ─════════════════════════════════════════════════════════════════════════════
// 5. INVENTORY STOCKS TAB — server-side (meta.from), Available + Consumed
// ─════════════════════════════════════════════════════════════════════════════
const gotInv = await goTab('Inventory Stocks|Product Inventory');
if (gotInv) {
  await ss(page, '06-inv-stocks-tab');
  await page.waitForTimeout(800);

  // Available stocks sub-tab (usually default)
  const availRows = await page.locator('tbody tr').count();
  step('✅', 'Inventory Stocks tab loaded (Available)', `${availRows} rows`);
  if (availRows > 0) {
    const r1 = await firstRowNum();
    step(r1 === '1' ? '✅' : '❌', 'Inventory Stocks page 1 — row #1 is "1"', `Got "${r1}"`);
  }

  // Switch to Consumed sub-tab
  const consumedTab = page.locator('a, button, [role="tab"]').filter({ hasText: /Consumed/i }).first();
  if (await consumedTab.isVisible()) {
    await consumedTab.click();
    await page.waitForTimeout(600);
    const consumedRows = await page.locator('tbody tr').count();
    step('✅', 'Consumed Stocks sub-tab', `${consumedRows} rows`);
    if (consumedRows > 0) {
      const r1 = await firstRowNum();
      step(r1 === '1' ? '✅' : '❌', 'Consumed Stocks page 1 — row #1 is "1"', `Got "${r1}"`);
    } else {
      find('info', 'Consumed stocks: 0 rows (quantity > 0 for all stocks)');
    }
    await ss(page, '07-inv-consumed');
  } else {
    find('info', 'Consumed sub-tab not found');
  }
} else {
  find('warn', 'Inventory Stocks tab button not found');
}

// ─════════════════════════════════════════════════════════════════════════════
// 6. ESCAPE KEY — PayAccountsPayable modal
// ─════════════════════════════════════════════════════════════════════════════
await goTab('Accounts Payable');
await page.waitForTimeout(500);

const payBtn = page.locator('.pay-btn').first();
if (await payBtn.isVisible()) {
  await payBtn.click();
  await page.waitForTimeout(700);
  const openBefore = await page.evaluate(() => !!document.querySelector('.modal-overlay.active'));
  step(openBefore ? '✅' : '⚠️', 'AP Pay modal opens', openBefore ? 'overlay visible' : 'overlay not found');

  await page.keyboard.press('Escape');
  await page.waitForTimeout(500);
  const openAfter = await page.evaluate(() => !!document.querySelector('.modal-overlay.active'));
  step(!openAfter ? '✅' : '❌', 'Escape closes AP Pay modal', !openAfter ? 'overlay gone ✓' : 'overlay still present ← Escape not wired');
  if (openAfter) find('warn', 'PayAccountsPayable modal: Escape key does NOT close it');
  await ss(page, '08-esc-ap-modal');
} else {
  find('warn', 'Pay button not visible — cannot test Escape on AP modal');
}

// ─════════════════════════════════════════════════════════════════════════════
// 7. ESCAPE KEY — ViewAccountsPayable modal
// ─════════════════════════════════════════════════════════════════════════════
const detailsBtn = page.locator('button').filter({ hasText: /Details/i }).first();
if (await detailsBtn.isVisible()) {
  await detailsBtn.click();
  await page.waitForTimeout(700);
  const openBefore = await page.evaluate(() => !!document.querySelector('.modal-overlay.active'));
  step(openBefore ? '✅' : '⚠️', 'ViewAccountsPayable modal opens', openBefore ? 'visible' : 'not found');

  await page.keyboard.press('Escape');
  await page.waitForTimeout(500);
  const openAfter = await page.evaluate(() => !!document.querySelector('.modal-overlay.active'));
  step(!openAfter ? '✅' : '❌', 'Escape closes ViewAccountsPayable modal', !openAfter ? 'gone ✓' : 'still open ← not wired');
  if (openAfter) find('warn', 'ViewAccountsPayable: Escape does not close it');
}

// ─════════════════════════════════════════════════════════════════════════════
// 8. ESCAPE KEY — ReturnPurchaseOrder modal + close-btn style check
// ─════════════════════════════════════════════════════════════════════════════
await goTab('Stock Return|Returns');
await page.waitForTimeout(800);

const returnBtn = page.locator('button').filter({ hasText: /Return Stock|Return/i }).first();
if (await returnBtn.isVisible()) {
  await returnBtn.click();
  await page.waitForTimeout(700);
  await ss(page, '09-return-po-modal');

  const openBefore = await page.evaluate(() => !!document.querySelector('.modal-overlay.active'));
  step(openBefore ? '✅' : '⚠️', 'ReturnPurchaseOrder modal opens', openBefore ? 'visible' : 'not found');

  // Close-btn style: should be white bg, not transparent
  const closeBtnBg = await page.evaluate(() => {
    const btn = document.querySelector('.modal-overlay.active .close-btn');
    if (!btn) return 'not found';
    return window.getComputedStyle(btn).backgroundColor;
  });
  const isWhiteBg = closeBtnBg.includes('255, 255, 255') || closeBtnBg === 'rgb(255, 255, 255)';
  const isTransparent = closeBtnBg === 'rgba(0, 0, 0, 0)' || closeBtnBg === 'transparent';
  step(
    isWhiteBg ? '✅' : (isTransparent ? '❌' : '⚠️'),
    'ReturnPO modal close-btn background (expect white from library)',
    `computed bg: ${closeBtnBg}`
  );
  if (isTransparent) find('warn', `ReturnPO close-btn still transparent — scoped override not removed`);
  if (!isWhiteBg && !isTransparent) find('info', `close-btn bg = ${closeBtnBg} (not pure white, check visually)`);

  await page.keyboard.press('Escape');
  await page.waitForTimeout(500);
  const openAfter = await page.evaluate(() => !!document.querySelector('.modal-overlay.active'));
  step(!openAfter ? '✅' : '❌', 'Escape closes ReturnPO modal', !openAfter ? 'gone ✓' : 'still open ← not wired');
  if (openAfter) find('warn', 'ReturnPO modal: Escape does not close it');
} else {
  find('info', 'Return Stock button not found on Stock Returns tab');
}

// ─════════════════════════════════════════════════════════════════════════════
// 9. ESCAPE KEY — CreatePurchaseOrder modal
// ─════════════════════════════════════════════════════════════════════════════
await goTab('Purchase Order|Orders');
await page.waitForTimeout(800);

const createPOBtn = page.locator('button').filter({ hasText: /Create|New|Add/i }).first();
if (await createPOBtn.isVisible()) {
  await createPOBtn.click();
  await page.waitForTimeout(700);
  const openBefore = await page.evaluate(() => !!document.querySelector('.modal-overlay.active'));
  step(openBefore ? '✅' : '⚠️', 'CreatePurchaseOrder modal opens', openBefore ? 'visible' : 'not found');

  if (openBefore) {
    await page.keyboard.press('Escape');
    await page.waitForTimeout(500);
    const openAfter = await page.evaluate(() => !!document.querySelector('.modal-overlay.active'));
    step(!openAfter ? '✅' : '❌', 'Escape closes CreatePurchaseOrder modal', !openAfter ? 'gone ✓' : 'still open');
    if (openAfter) find('warn', 'CreatePO modal: Escape does not close');
  }
} else {
  find('info', 'Create PO button not found');
}

// ─════════════════════════════════════════════════════════════════════════════
// 10. PROBE: Search on AP tab — currentPage resets to 1
// ─════════════════════════════════════════════════════════════════════════════
await goTab('Accounts Payable');
await page.waitForTimeout(600);

// Navigate to page 2 first
const nextBtn2 = page.locator('.ap-page-btn').filter({ hasText: /Next/i });
if (await nextBtn2.isVisible() && !await nextBtn2.isDisabled()) {
  await nextBtn2.click();
  await page.waitForTimeout(400);
  const pageOnP2 = await page.locator('.ap-page-info').textContent().catch(() => '');
  step(pageOnP2.includes('2') ? '✅' : '⚠️', 'AP navigated to page 2', pageOnP2.trim());

  // Now type a search — should reset to page 1
  const searchInput = page.locator('.search-input').first();
  if (await searchInput.isVisible()) {
    await searchInput.fill('REC');
    await page.waitForTimeout(400);
    const pageAfterSearch = await page.locator('.ap-page-info').textContent().catch(() => 'N/A');
    const resetOk = pageAfterSearch.includes('Page 1');
    step(
      resetOk ? '✅' : '❌',
      'AP search while on page 2 resets to page 1',
      `Page info: "${pageAfterSearch.trim()}"`
    );
    if (!resetOk) find('warn', 'AP search does not reset currentPage to 1');
    await searchInput.fill('');
    await page.waitForTimeout(300);
    find('info', `🔍 AP page-reset-on-search probe: ${resetOk ? 'passed' : 'FAILED'}`);
  }
}

await ss(page, '10-final');
await browser.close();

// ─── REPORT ──────────────────────────────────────────────────────────────────
console.log('\n════════════ STEPS ════════════');
steps.forEach(s => console.log(`${s.icon} ${s.action}\n   → ${s.result}${s.evidence ? '\n   ' + s.evidence : ''}`));
console.log('\n════════════ FINDINGS ════════════');
findings.forEach(f => console.log(`${f.level === 'warn' ? '⚠️' : '🔍'} ${f.note}`));
console.log('\n════════════ SCREENSHOTS ════════════');
shots.forEach(s => console.log(`  ${s.name}: ${s.p}`));
