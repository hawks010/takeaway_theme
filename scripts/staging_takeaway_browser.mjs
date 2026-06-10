import fs from 'node:fs/promises';
import path from 'node:path';
import { chromium, devices } from '/Users/sonny-work/.cache/codex-runtimes/codex-primary-runtime/dependencies/node/node_modules/playwright/index.mjs';

const baseUrl = process.env.TAKEAWAY_BASE_URL || 'https://takeaway.thatdeveloper.co.uk';
const username = process.env.TAKEAWAY_ADMIN_USER;
const password = process.env.TAKEAWAY_ADMIN_PASS;
const screenshotDir = process.env.TAKEAWAY_SCREENSHOT_DIR || path.resolve('test-artifacts/screenshots');
const summaryPath = process.env.TAKEAWAY_SUMMARY_PATH || path.resolve('test-artifacts/screenshots/browser-summary.json');
const executablePath = process.env.PLAYWRIGHT_EXECUTABLE_PATH || undefined;

if (!username || !password) {
  throw new Error('Missing TAKEAWAY_ADMIN_USER or TAKEAWAY_ADMIN_PASS');
}

await fs.mkdir(screenshotDir, { recursive: true });

const browser = await chromium.launch({ headless: true, executablePath });
const adminContext = await browser.newContext({ viewport: { width: 1440, height: 900 } });
const adminPage = await adminContext.newPage();

const summary = {
  screenshots: [],
  setupHealthBefore: [],
  setupHealthAfter: [],
  repairsRun: [],
  checkout: {},
  orderId: null,
};

async function saveShot(page, name, fullPage = true) {
  const target = path.join(screenshotDir, name);
  await page.screenshot({ path: target, fullPage });
  summary.screenshots.push(target);
  return target;
}

async function login() {
  await adminPage.goto(`${baseUrl}/wp-login.php`, { waitUntil: 'networkidle' });
  await adminPage.fill('#user_login', username);
  await adminPage.fill('#user_pass', password);
  await Promise.all([
    adminPage.waitForLoadState('networkidle'),
    adminPage.click('#wp-submit'),
  ]);
}

async function openThemeSetupAndUpdate() {
  await adminPage.goto(`${baseUrl}/wp-admin/themes.php?page=takeaway-theme-setup`, { waitUntil: 'networkidle' });
  await saveShot(adminPage, '01-theme-setup-update-screen.png');
  const updateButton = adminPage.getByRole('button', { name: /update bundled takeaway os/i });
  const activateButton = adminPage.getByRole('button', { name: /activate takeaway os/i });
  const installButton = adminPage.getByRole('button', { name: /install takeaway os/i });

  if (await updateButton.count()) {
    await Promise.all([
      adminPage.waitForURL(/takeaway-os-launchpad|themes\.php\?page=takeaway-theme-setup/i, { timeout: 120000 }),
      updateButton.click(),
    ]);
  } else if (await activateButton.count()) {
    await Promise.all([
      adminPage.waitForURL(/takeaway-os-launchpad/i, { timeout: 120000 }),
      activateButton.click(),
    ]);
  } else if (await installButton.count()) {
    await Promise.all([
      adminPage.waitForURL(/takeaway-os-launchpad/i, { timeout: 120000 }),
      installButton.click(),
    ]);
  }
  await adminPage.waitForLoadState('networkidle');
  await saveShot(adminPage, '02-launchpad.png');
}

async function collectSetupHealth() {
  await adminPage.goto(`${baseUrl}/wp-admin/admin.php?page=takeaway-os-setup-health`, { waitUntil: 'networkidle' });
  const cards = await adminPage.locator('.ttos-setup-health-card').evaluateAll((nodes) =>
    nodes.map((node) => {
      const title = node.querySelector('h3')?.textContent?.trim() || '';
      const status = node.querySelector('.ttos-good,.ttos-warn,.ttos-bad')?.textContent?.trim() || '';
      const message = node.querySelector('p')?.textContent?.trim() || '';
      return { title, status, message };
    })
  );
  return cards;
}

function needsRepair(cards) {
  return cards.some((card) => {
    const title = card.title.toLowerCase();
    const status = card.status.toLowerCase();
    if (status === 'pass') {
      return false;
    }
    return (
      title.includes('home') ||
      title.includes('menu page') ||
      title.includes('basket') ||
      title.includes('checkout') ||
      title.includes('my account') ||
      title.includes('header menu') ||
      title.includes('footer menu')
    );
  });
}

function needsStarterContent(cards) {
  return cards.some((card) => card.title.toLowerCase().includes('starter menu content') && card.status.toLowerCase() !== 'pass');
}

async function runSetupHealthActionsIfNeeded() {
  summary.setupHealthBefore = await collectSetupHealth();
  await saveShot(adminPage, '03-setup-health-before.png');

  if (needsRepair(summary.setupHealthBefore)) {
    await Promise.all([
      adminPage.waitForLoadState('networkidle'),
      adminPage.getByRole('button', { name: /repair pages and menus/i }).click(),
    ]);
    summary.repairsRun.push('repair pages and menus');
  }

  if (needsStarterContent(summary.setupHealthBefore)) {
    await Promise.all([
      adminPage.waitForLoadState('networkidle'),
      adminPage.getByRole('button', { name: /create starter content/i }).click(),
    ]);
    summary.repairsRun.push('create starter content');
  }

  if (summary.repairsRun.length > 0) {
    await adminPage.goto(`${baseUrl}/wp-admin/admin.php?page=takeaway-os-setup-health`, { waitUntil: 'networkidle' });
  }

  summary.setupHealthAfter = await collectSetupHealth();
  await saveShot(adminPage, '04-setup-health-after.png');
}

async function captureAdminScreens() {
  await adminPage.goto(`${baseUrl}/wp-admin/admin.php?page=takeaway-os-orders`, { waitUntil: 'networkidle' });
  await saveShot(adminPage, '12-order-cockpit.png');

  await adminPage.goto(`${baseUrl}/wp-admin/admin.php?page=takeaway-os-kitchen`, { waitUntil: 'networkidle' });
  await saveShot(adminPage, '13-kitchen-screen.png');

  await adminPage.goto(`${baseUrl}/wp-admin/admin.php?page=takeaway-os-customers`, { waitUntil: 'networkidle' });
  await saveShot(adminPage, '14-crm-dashboard.png');

  await adminPage.goto(`${baseUrl}/wp-admin/admin.php?page=takeaway-os-reports`, { waitUntil: 'networkidle' });
  await saveShot(adminPage, '15-reports-dashboard.png');
}

async function getShopUrl() {
  await adminPage.goto(`${baseUrl}/`, { waitUntil: 'networkidle' });
  const orderNow = adminPage.getByRole('link', { name: /order now|start order/i }).first();
  if (await orderNow.count()) {
    return await orderNow.getAttribute('href');
  }
  return `${baseUrl}/menu/`;
}

async function captureFrontendAndPlaceOrder() {
  const desktopContext = await browser.newContext({ viewport: { width: 1440, height: 900 } });
  const desktopPage = await desktopContext.newPage();
  const mobileContext = await browser.newContext({ ...devices['iPhone 13'] });
  const mobilePage = await mobileContext.newPage();
  const shopUrl = await getShopUrl();

  await desktopPage.goto(baseUrl, { waitUntil: 'networkidle' });
  await saveShot(desktopPage, '05-homepage-desktop.png');

  await mobilePage.goto(baseUrl, { waitUntil: 'networkidle' });
  await saveShot(mobilePage, '06-homepage-mobile.png');

  await desktopPage.goto(shopUrl, { waitUntil: 'networkidle' });
  await saveShot(desktopPage, '07-menu-page-desktop.png');

  await mobilePage.goto(shopUrl, { waitUntil: 'networkidle' });
  await saveShot(mobilePage, '08-menu-page-mobile.png');

  const configureButton = desktopPage.getByRole('button', { name: /configure item/i }).first();
  const addButton = desktopPage.getByRole('button', { name: /add to basket/i }).first();

  if (await configureButton.count()) {
    await configureButton.click();
    await desktopPage.waitForTimeout(1000);
    await saveShot(desktopPage, '09-product-configurator-modal.png');

    const totalBefore = (await desktopPage.locator('.ttos-config-total strong').textContent())?.trim() || '';
    const firstCheckbox = desktopPage.locator('.ttos-config-option input[type="checkbox"]:not([disabled])').first();
    const firstRadio = desktopPage.locator('.ttos-config-option input[type="radio"]:not([disabled])').nth(1);
    if (await firstCheckbox.count()) {
      await firstCheckbox.check();
    } else if (await firstRadio.count()) {
      await firstRadio.check();
    }
    await desktopPage.waitForTimeout(500);
    const totalAfter = (await desktopPage.locator('.ttos-config-total strong').textContent())?.trim() || '';
    summary.checkout.modalTotalBefore = totalBefore;
    summary.checkout.modalTotalAfter = totalAfter;

    await desktopPage.locator('.ttos-config-form button[type="submit"]').click();
  } else if (await addButton.count()) {
    await addButton.click();
  }

  await desktopPage.waitForLoadState('networkidle');
  await desktopPage.goto(`${baseUrl}/basket/`, { waitUntil: 'networkidle' });
  await saveShot(desktopPage, '10-basket-page.png');

  await desktopPage.goto(`${baseUrl}/checkout/`, { waitUntil: 'networkidle' });
  await saveShot(desktopPage, '11-checkout-page.png');

  summary.checkout.hasFulfilmentControl = await desktopPage.locator('#ttos_fulfilment_method_field').count() > 0;
  summary.checkout.hasRequestedTime = await desktopPage.locator('#ttos_requested_time_field').count() > 0;

  if (await desktopPage.locator('input[name=\"payment_method\"]').count() > 0) {
    await desktopPage.fill('#billing_first_name', 'Codex');
    await desktopPage.fill('#billing_last_name', 'Staging');
    await desktopPage.fill('#billing_address_1', '1 Test Street');
    await desktopPage.fill('#billing_city', 'London');
    await desktopPage.fill('#billing_postcode', 'SW1A 1AA');
    await desktopPage.fill('#billing_phone', '07123456789');
    await desktopPage.fill('#billing_email', 'codex-staging@example.com');

    const cod = desktopPage.locator('input[name="payment_method"][value="cod"]');
    const bacs = desktopPage.locator('input[name="payment_method"][value="bacs"]');
    if (await cod.count()) {
      await cod.check();
      summary.checkout.gatewayUsed = 'cod';
    } else if (await bacs.count()) {
      await bacs.check();
      summary.checkout.gatewayUsed = 'bacs';
    } else {
      summary.checkout.gatewayUsed = await desktopPage.locator('input[name="payment_method"]').first().getAttribute('value');
      await desktopPage.locator('input[name="payment_method"]').first().check();
    }

    const placeOrder = desktopPage.locator('#place_order');
    if (await placeOrder.count()) {
      await Promise.all([
        desktopPage.waitForLoadState('networkidle'),
        placeOrder.click(),
      ]);
      const confirmationText = await desktopPage.textContent('body');
      const orderMatch = confirmationText?.match(/order number[:\s#]*([0-9]+)/i) || desktopPage.url().match(/order-received\/([0-9]+)/i);
      if (orderMatch) {
        summary.orderId = orderMatch[1];
      }
    }
  }

  await desktopContext.close();
  await mobileContext.close();
}

try {
  await login();
  await openThemeSetupAndUpdate();
  await runSetupHealthActionsIfNeeded();
  await captureFrontendAndPlaceOrder();
  await captureAdminScreens();
} finally {
  await fs.writeFile(summaryPath, JSON.stringify(summary, null, 2));
  await browser.close();
}

console.log(JSON.stringify(summary, null, 2));
