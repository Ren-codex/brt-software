/**
 * Verify: Accounts module fixes
 *   1. Search — keyword filter now applied server-side
 *   2. Create modal — Escape closes, backdrop click closes
 *   3. Reset modal — "Cancel" label fixed (was "Cancels")
 *   4. Role modal — Escape closes; AddRole sub-modal Escape closes
 *   5. Remove role modal — role name shown (was "undefined")
 *   6. Probe: nonsense keyword → 0 results
 */
import { chromium } from 'playwright';

const BASE  = 'http://127.0.0.1:8000';
const EMAIL = 'administrator@example.com';
const PASS  = 'testpass123';

const steps    = [];
const findings = [];
const shots    = [];

function step(icon, action, result) {
  steps.push({ icon, action, result });
  console.log(`${icon}  ${action} → ${result}`);
}
function find(level, note) {
  findings.push({ level, note });
  console.log(`  ${level === 'warn' ? '⚠️ ' : '🔍'} ${note}`);
}
async function ss(page, name) {
  const p = `/tmp/acct-${name}.png`;
  await page.screenshot({ path: p, fullPage: false });
  shots.push({ name, p });
  return p;
}

const browser = await chromium.launch({ headless: true });
const ctx     = await browser.newContext({ viewport: { width: 1440, height: 900 } });
const page    = await ctx.newPage();

// ── LOGIN ──────────────────────────────────────────────────────────────────
await page.goto(`${BASE}/login`);
await page.waitForLoadState('networkidle');
await page.locator('input[type="email"]').first().fill(EMAIL);
await page.locator('input[type="password"]').first().fill(PASS);
await page.locator('input[type="password"]').first().press('Enter');
await page.waitForURL(u => !u.toString().includes('/login'), { timeout: 15000 }).catch(() => {});
step(page.url().includes('/login') ? '❌' : '✅', 'Login', page.url());

// ── Navigate to Users ─────────────────────────────────────────────────────
await page.goto(`${BASE}/users`);
await page.waitForLoadState('networkidle');
await page.waitForTimeout(1200);
await ss(page, '01-users-list');

const rowsBefore = await page.locator('tbody tr').count();
step(rowsBefore > 0 ? '✅' : '⚠️', 'Users list loaded', `${rowsBefore} rows visible`);

// ── 1. SEARCH — keyword filtering ─────────────────────────────────────────
// placeholder is "Search Employee..."
const searchInput = page.locator('input[placeholder="Search Employee..."]').first();
const searchVisible = await searchInput.isVisible();
step(searchVisible ? '✅' : '❌', 'Search input visible', searchVisible ? 'found' : 'not found');

if (searchVisible) {
  // Watch for an XHR that includes keyword param
  const respPromise = page.waitForResponse(
    r => r.url().includes('/users') && r.url().includes('keyword=') && r.status() === 200,
    { timeout: 5000 }
  ).catch(() => null);

  await searchInput.fill('admin');
  await page.waitForTimeout(700); // debounce is 300ms
  const searchResp = await respPromise;

  const rowsAfter = await page.locator('tbody tr').count();
  step(searchResp !== null ? '✅' : '❌', 'Keyword sent to server (XHR includes keyword=)',
    searchResp ? `${searchResp.url().split('?')[1]}` : 'no matching XHR — keyword still ignored');
  step(rowsAfter < rowsBefore ? '✅' : '⚠️',
    `Rows filtered: ${rowsBefore} → ${rowsAfter}`,
    rowsAfter < rowsBefore ? 'fewer rows ✓' : rowsAfter === rowsBefore ? 'same (all users match?)' : 'more rows?');
  await ss(page, '02-search-admin');

  // Probe: nonsense keyword → empty state
  await searchInput.fill('xqz999nomatch');
  await page.waitForTimeout(700);
  const rowsNone   = await page.locator('tbody tr').count();
  const emptySpan  = await page.locator('tbody td[colspan]').count();
  find(rowsNone <= 1 ? 'info' : 'warn',
    `🔍 Nonsense search → ${rowsNone} row(s), empty-state cell: ${emptySpan > 0 ? 'yes' : 'no'}`);
  await ss(page, '03-search-nomatch');

  // Probe: clear → all back
  await searchInput.fill('');
  await page.waitForTimeout(700);
  const rowsCleared = await page.locator('tbody tr').count();
  find('info', `🔍 Clear search → ${rowsCleared} rows (was ${rowsBefore})`);
}

// ── 2. CREATE MODAL — Escape closes ──────────────────────────────────────
const createBtn = page.locator('button').filter({ hasText: /Create User/i }).first();
step(await createBtn.isVisible() ? '✅' : '❌', '"Create User" button visible', '');

await createBtn.click();
await page.waitForTimeout(500);
const createOpen = await page.evaluate(() => !!document.querySelector('.modal-overlay.active'));
step(createOpen ? '✅' : '❌', 'Create modal opens', createOpen ? 'overlay.active present' : 'missing');
await ss(page, '04-create-modal-open');

if (createOpen) {
  await page.keyboard.press('Escape');
  await page.waitForTimeout(400);
  const afterEsc = await page.evaluate(() => !!document.querySelector('.modal-overlay.active'));
  step(!afterEsc ? '✅' : '❌', 'Escape closes Create modal', !afterEsc ? 'closed ✓' : 'still open');

  // Backdrop click
  await createBtn.click();
  await page.waitForTimeout(400);
  // click top-left corner of overlay (outside modal container)
  await page.evaluate(() => {
    const overlay = document.querySelector('.modal-overlay.active');
    if (overlay) overlay.dispatchEvent(new MouseEvent('click', { bubbles: true }));
  });
  await page.waitForTimeout(400);
  const afterBackdrop = await page.evaluate(() => !!document.querySelector('.modal-overlay.active'));
  step(!afterBackdrop ? '✅' : '⚠️', 'Backdrop click closes Create modal', !afterBackdrop ? 'closed ✓' : 'still open');
}

// ── 3. RESET MODAL — Cancel label ────────────────────────────────────────
// Action buttons: ri-lock-password-line = Reset Password
const resetBtn = page.locator('button:has(.ri-lock-password-line)').first();
const resetVisible = await resetBtn.isVisible().catch(() => false);
step(resetVisible ? '✅' : '⚠️', 'Reset Password button visible (first user row)', resetVisible ? 'found' : 'not found');

if (resetVisible) {
  await resetBtn.click();
  await page.waitForTimeout(700);
  await ss(page, '05-reset-modal');

  const cancelLabel  = await page.locator('button').filter({ hasText: /^Cancel$/ }).first().textContent().catch(() => '?');
  const cancelsBad   = await page.locator('button').filter({ hasText: /^Cancels$/ }).count();
  step(cancelLabel.trim() === 'Cancel' && cancelsBad === 0 ? '✅' : '❌',
    'Reset modal: Cancel button label',
    `label="${cancelLabel.trim()}", stale "Cancels" count=${cancelsBad}`);

  find('info', `🔍 Reset modal opened — password rules list visible: ${await page.locator('ul li').count() > 0}`);

  // Close b-modal via Escape (supported unless no-close-on-esc; Reset.vue doesn't set it)
  await page.keyboard.press('Escape');
  // Wait for Bootstrap modal + backdrop to fully unmount
  await page.waitForFunction(() => !document.querySelector('.modal.show'), { timeout: 5000 }).catch(() => {});
  await page.waitForTimeout(600);
}

// ── 4. ROLE MODAL + sub-modals ────────────────────────────────────────────
const roleBtn = page.locator('button:has(.ri-group-2-line)').first();
const roleVisible = await roleBtn.isVisible().catch(() => false);
step(roleVisible ? '✅' : '⚠️', 'Set Roles button visible (first user row)', roleVisible ? 'found' : 'not found');

if (roleVisible) {
  await roleBtn.click();
  await page.waitForTimeout(600);
  const roleOpen = await page.evaluate(() => !!document.querySelector('.modal-overlay.active'));
  step(roleOpen ? '✅' : '❌', 'Role modal opens', roleOpen ? 'overlay.active ✓' : 'missing');
  await ss(page, '06-role-modal');

  if (roleOpen) {
    // Check for a remove button (role row)
    const removeBtn = page.locator('.remove-list').first();
    const removeVisible = await removeBtn.isVisible().catch(() => false);

    if (removeVisible) {
      await removeBtn.click();
      await page.waitForTimeout(400);
      const removeOpen = await page.evaluate(() => document.querySelectorAll('.modal-overlay.active').length);
      step(removeOpen >= 1 ? '✅' : '❌', 'Remove role sub-modal opens', `${removeOpen} active overlay(s)`);
      await ss(page, '07-remove-modal');

      if (removeOpen >= 1) {
        // 5. Check "undefined" is gone
        const alertText = await page.locator('.alert-content p').first().textContent().catch(() => '');
        const hasUndefined = alertText.toLowerCase().includes('undefined');
        step(!hasUndefined ? '✅' : '❌',
          'Remove modal shows role name (not "undefined")',
          `Text: "${alertText.trim().slice(0, 90)}"`);

        // Escape closes sub-modal, parent stays
        await page.keyboard.press('Escape');
        await page.waitForTimeout(400);
        const afterRemoveEsc = await page.evaluate(() => document.querySelectorAll('.modal-overlay.active').length);
        step(afterRemoveEsc === 1 ? '✅' : afterRemoveEsc === 0 ? '⚠️' : '❌',
          'Escape closes Remove sub-modal (Role modal stays open)',
          `Active overlays: ${afterRemoveEsc} (want 1)`);
      }
    } else {
      find('info', 'No active remove buttons — user may have no removable roles; skipping Remove sub-modal test');
    }

    // AddRole sub-modal
    const addBtn = page.locator('button').filter({ hasText: /Add Role/i }).first();
    if (await addBtn.isVisible().catch(() => false)) {
      await addBtn.click();
      await page.waitForTimeout(400);
      const addOpen = await page.evaluate(() => document.querySelectorAll('.modal-overlay.active').length);
      step(addOpen >= 2 ? '✅' : '⚠️', 'AddRole sub-modal opens (stacked)', `${addOpen} active overlay(s)`);
      await ss(page, '08-addrole-modal');

      await page.keyboard.press('Escape');
      await page.waitForTimeout(400);
      const afterAddEsc = await page.evaluate(() => document.querySelectorAll('.modal-overlay.active').length);
      step(afterAddEsc <= 1 ? '✅' : '❌', 'Escape closes AddRole sub-modal', `Active: ${afterAddEsc}`);
    } else {
      find('info', 'Add Role button not visible — all roles may already be assigned');
    }

    // Escape closes Role modal
    await page.keyboard.press('Escape');
    await page.waitForTimeout(400);
    const roleStillOpen = await page.evaluate(() => !!document.querySelector('.modal-overlay.active'));
    step(!roleStillOpen ? '✅' : '❌', 'Escape closes Role modal', !roleStillOpen ? 'closed ✓' : 'still open');
  }
}

await browser.close();

console.log('\n════════════ STEPS ════════════');
steps.forEach(s => console.log(`${s.icon}  ${s.action} → ${s.result}`));
console.log('\n════════════ FINDINGS ════════════');
if (findings.length === 0) console.log('  (none)');
findings.forEach(f => console.log(`  ${f.level === 'warn' ? '⚠️ ' : '🔍'} ${f.note}`));
console.log('\n════════════ SCREENSHOTS ════════════');
shots.forEach(s => console.log(`  ${s.name}: ${s.p}`));
