/**
 * Follow-up probes for 3 surfaces not covered in the first run:
 *   A. ReturnPurchaseOrder modal — Escape key + close-btn style
 *   B. InventoryStocksTab (Available/Consumed) via ?tab=inventoryStocks URL param
 *   C. ProductsTab via ?tab=products URL param
 */
import { chromium } from 'playwright';

const BASE  = 'http://127.0.0.1:8000';
const EMAIL = 'administrator@example.com';
const PASS  = 'testpass123';

const steps   = [];
const findings = [];
const shots   = [];

function step(icon, action, result) {
  steps.push({ icon, action, result });
  console.log(`${icon} ${action} → ${result}`);
}
function find(level, note) {
  findings.push({ level, note });
  console.log(`  ${level === 'warn' ? '⚠️' : '🔍'} ${note}`);
}
async function ss(page, name) {
  const p = `/tmp/pag2-${name}.png`;
  await page.screenshot({ path: p });
  shots.push({ name, p });
  return p;
}

const browser = await chromium.launch({ headless: true });
const ctx     = await browser.newContext({ viewport: { width: 1440, height: 900 } });
const page    = await ctx.newPage();

// LOGIN
await page.goto(`${BASE}/login`);
await page.waitForLoadState('networkidle');
await page.locator('input[type="email"]').first().fill(EMAIL);
await page.locator('input[type="password"]').first().fill(PASS);
await page.locator('input[type="password"]').first().press('Enter');
await page.waitForURL(u => !u.toString().includes('/login'), { timeout: 15000 }).catch(() => {});
step(page.url().includes('/login') ? '❌' : '✅', 'Login', page.url());

// ─── A. ReturnPurchaseOrder modal ────────────────────────────────────────────
await page.goto(`${BASE}/inventory?tab=stockReturns`);
await page.waitForLoadState('networkidle');
await page.waitForTimeout(1200);
await ss(page, 'A1-stock-returns');

const returnBtn = page.locator('button').filter({ hasText: /Return Stock/i }).first();
const btnVisible = await returnBtn.isVisible();
step(btnVisible ? '✅' : '❌', 'Return Stock button visible', btnVisible ? 'found' : 'not found');

if (btnVisible) {
  await returnBtn.click();
  // wait longer — openReturnStockModal() does async fetchReturnableOrders()
  await page.waitForTimeout(3000);
  await ss(page, 'A2-after-click');

  const modalOpen = await page.evaluate(() => !!document.querySelector('.modal-overlay.active'));
  step(modalOpen ? '✅' : '❌', 'ReturnPO modal opened after click + 3s wait', modalOpen ? 'overlay visible' : 'overlay not found — check if returnableOrders empty');

  if (!modalOpen) {
    // Check if a toast appeared instead (means no returnable orders)
    const toastText = await page.locator('.inventory-toast, [class*="toast"]').textContent().catch(() => '');
    find('warn', `ReturnPO modal did not open. Toast/error visible: "${toastText.trim() || 'none'}"`);

    // Check the purchase orders API response
    const poApiCheck = await page.evaluate(async () => {
      try {
        const r = await fetch('/purchase-orders?option=list&count=10');
        const data = await r.json();
        const orders = data?.data || [];
        return {
          count: orders.length,
          sample: orders[0] ? { id: orders[0].id, items: orders[0].items?.length, firstItemReceivedQty: orders[0].items?.[0]?.received_quantity } : null
        };
      } catch (e) { return { error: e.message }; }
    });
    find('info', `PO API /purchase-orders?option=list → ${JSON.stringify(poApiCheck)}`);
  } else {
    // Escape key test
    await page.keyboard.press('Escape');
    await page.waitForTimeout(500);
    const stillOpen = await page.evaluate(() => !!document.querySelector('.modal-overlay.active'));
    step(!stillOpen ? '✅' : '❌', 'Escape closes ReturnPO modal', !stillOpen ? 'gone ✓' : 'still visible ← not wired');

    // Close-btn computed style
    await returnBtn.click();
    await page.waitForTimeout(3000);
    const closeBg = await page.evaluate(() => {
      const btn = document.querySelector('.modal-overlay.active .close-btn');
      if (!btn) return 'not found';
      const s = window.getComputedStyle(btn);
      return { bg: s.backgroundColor, border: s.border };
    });
    const isWhite = typeof closeBg === 'object' && closeBg.bg?.includes('255, 255, 255');
    const isTransparent = typeof closeBg === 'object' && (closeBg.bg === 'rgba(0, 0, 0, 0)' || closeBg.bg === 'transparent');
    step(
      isWhite ? '✅' : (isTransparent ? '❌' : '⚠️'),
      'ReturnPO close-btn background (library = white)',
      typeof closeBg === 'string' ? closeBg : JSON.stringify(closeBg)
    );
    if (isTransparent) find('warn', 'close-btn still transparent — scoped override not removed');
    await ss(page, 'A3-return-modal-open');

    await page.locator('.close-btn').first().click().catch(() => {});
    await page.waitForTimeout(300);
  }
}

// ─── B. InventoryStocksTab via URL param ────────────────────────────────────
await page.goto(`${BASE}/inventory?tab=inventoryStocks`);
await page.waitForLoadState('networkidle');
await page.waitForTimeout(1500);
await ss(page, 'B1-inv-stocks-tab');

const invTabTitle = await page.locator('h2, h3, h4, .header-title, [class*="page-title"]').first().textContent().catch(() => '');
step('✅', 'InventoryStocks tab via ?tab=inventoryStocks', `Title area: "${invTabTitle.trim().slice(0, 60)}"`);

const availRows = await page.locator('tbody tr').count();
step(availRows >= 0 ? '✅' : '⚠️', 'InventoryStocks Available rows count', `${availRows} rows`);

if (availRows > 0) {
  const r1 = (await page.locator('tbody tr:first-child td:first-child').textContent().catch(() => '?')).trim();
  step(r1 === '1' ? '✅' : '❌', 'InventoryStocks Available row #1', `Got "${r1}" (expect "1")`);
}

// Switch to Consumed sub-tab (BTabs title)
const consumedTab = page.locator('[role="tab"], .nav-link, a, button').filter({ hasText: /Consumed/i }).first();
if (await consumedTab.isVisible()) {
  await consumedTab.click();
  await page.waitForTimeout(700);
  const consumedRows = await page.locator('tbody tr').count();
  step('✅', 'InventoryStocks Consumed sub-tab', `${consumedRows} rows`);
  if (consumedRows > 0) {
    const r1c = (await page.locator('tbody tr:first-child td:first-child').textContent().catch(() => '?')).trim();
    step(r1c === '1' ? '✅' : '❌', 'Consumed row #1', `Got "${r1c}" (expect "1")`);
  } else {
    find('info', 'Consumed rows = 0 (all stocks have quantity > 0 in test DB)');
  }
  await ss(page, 'B2-consumed-tab');
} else {
  find('warn', 'Consumed sub-tab not found — check BTab title selector');
  const allTabs = await page.locator('[role="tab"], .nav-link').allTextContents();
  find('info', `Found tabs: ${JSON.stringify(allTabs)}`);
}

// ─── C. ProductsTab via URL param ───────────────────────────────────────────
await page.goto(`${BASE}/inventory?tab=products`);
await page.waitForLoadState('networkidle');
await page.waitForTimeout(1500);
await ss(page, 'C1-products-tab');

const prodRows = await page.locator('tbody tr').count();
step(prodRows >= 0 ? '✅' : '⚠️', 'Products tab via ?tab=products', `${prodRows} rows`);

if (prodRows > 0) {
  const r1p = (await page.locator('tbody tr:first-child td:first-child').textContent().catch(() => '?')).trim();
  step(r1p === '1' ? '✅' : '❌', 'Products row #1', `Got "${r1p}" (expect "1")`);
}

// ─── D. CreatePurchaseOrder modal — Escape key ──────────────────────────────
await page.goto(`${BASE}/inventory?tab=purchaseOrders`);
await page.waitForLoadState('networkidle');
await page.waitForTimeout(1200);

const createBtn = page.locator('button').filter({ hasText: /Create|New|Add/i }).first();
const createBtnVisible = await createBtn.isVisible();
step(createBtnVisible ? '✅' : '⚠️', 'PO Create button visible', createBtnVisible ? 'found' : 'not found');
if (createBtnVisible) {
  await createBtn.click();
  await page.waitForTimeout(800);
  const modalOpen = await page.evaluate(() => !!document.querySelector('.modal-overlay.active'));
  step(modalOpen ? '✅' : '⚠️', 'CreatePO modal opens', modalOpen ? 'visible' : 'not visible');
  if (modalOpen) {
    await page.keyboard.press('Escape');
    await page.waitForTimeout(500);
    const stillOpen = await page.evaluate(() => !!document.querySelector('.modal-overlay.active'));
    step(!stillOpen ? '✅' : '❌', 'Escape closes CreatePO modal', !stillOpen ? 'gone ✓' : 'still visible');
    await ss(page, 'D1-create-po-escaped');
  }
}

await browser.close();

console.log('\n════════════ STEPS ════════════');
steps.forEach(s => console.log(`${s.icon} ${s.action} → ${s.result}`));
console.log('\n════════════ FINDINGS ════════════');
findings.forEach(f => console.log(`${f.level === 'warn' ? '⚠️' : '🔍'} ${f.note}`));
console.log('\n════════════ SCREENSHOTS ════════════');
shots.forEach(s => console.log(`  ${s.name}: ${s.p}`));
