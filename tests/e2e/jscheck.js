// Browser check of Kunena's JavaScript (the curl-based scenarios never run it): the installation wizard (when pending),
// every administrator page, the tabs of the tabbed pages, a modal and some frontend pages, in headless Chrome; prints the
// JavaScript errors per page. Needs playwright-core and Chrome/Chromium:
//   PLAYWRIGHT_CORE=/path/to/node_modules/playwright-core CHROME=/usr/bin/google-chrome \
//   node tests/e2e/jscheck.js http://127.0.0.1:8101/ admin Admin12345678
// Exit code 1 when a page raised a JavaScript error, the wizard got stuck or a tab didn't switch.
const { chromium } = require(process.env.PLAYWRIGHT_CORE || 'playwright-core');
const base = process.argv[2], user = process.argv[3], pass = process.argv[4];
(async () => {
  const browser = await chromium.launch({ executablePath: process.env.CHROME || '/usr/bin/google-chrome', headless: true, args: ['--ignore-certificate-errors'] });
  const ctx = await browser.newContext({ viewport: { width: 1400, height: 1000 }, ignoreHTTPSErrors: true });
  const page = await ctx.newPage();
  let current = '';
  const errors = {};
  const note = (msg) => { (errors[current] = errors[current] || []).push(msg.substring(0, 300)); };
  page.on('pageerror', e => note('pageerror: ' + e.message));
  page.on('console', m => { if (m.type() === 'error') note('console: ' + m.text()); });
  current = 'login';
  await page.goto(base + 'administrator/index.php');
  await page.fill('#mod-login-username', user);
  await page.fill('#mod-login-password', pass);
  await Promise.all([page.waitForNavigation(), page.click('button.login-button, button[type=submit]')]);
  const tpl = await page.evaluate(() => document.body.className);
  // The wizard: runs by itself from the dashboard, ends on Kunena's control panel
  current = 'wizard';
  await page.goto(base + 'administrator/index.php?option=com_kunena&view=cpanel');
  let wizard = 'not shown';
  if (await page.locator('#kunena-install').count()) {
    try {
      await page.waitForURL(u => !/view=install|view=cpanel/.test(u.toString()) || /option=com_kunena$/.test(u.toString()), { timeout: 240000 });
      await page.waitForLoadState('load');
      wizard = 'finished: ' + page.url();
    } catch (e) { wizard = 'stuck: ' + (await page.locator('#kunena-install h2').textContent()); }
  }
  const views = ['cpanel', 'categories', 'users', 'attachments', 'smilies', 'ranks', 'templates', 'config', 'plugins', 'logs', 'baldrick', 'statistics', 'tools', 'trash', 'tools&layout=report', 'tools&layout=diagnostics', 'tools&layout=prune', 'tools&layout=syncusers', 'tools&layout=recount', 'tools&layout=menu', 'tools&layout=emptytrash', 'tools&layout=sampledata', 'tools&layout=cleanupip', 'categories&layout=create', 'categories&layout=edit&catid=2', 'templates&layout=edit&name=crypsis', 'templates&layout=edit&name=blackadder' ];
  for (const v of views) {
    current = 'admin:' + v;
    await page.goto(base + 'administrator/index.php?option=com_kunena&view=' + v);
    await page.waitForTimeout(300);
  }
  // Every tabbed page: the second tab shows its pane
  const tabsWork = {};
  let tabCount = 0;
  for (const v of ['config', 'categories&layout=edit&catid=2', 'USEREDIT', 'templates&layout=edit&name=crypsis', 'smilies', 'ranks']) {
    current = 'admin:tabs:' + v;
    if (v === 'USEREDIT') {
      // The user editor opens from the users list (a form task)
      await page.goto(base + 'administrator/index.php?option=com_kunena&view=users');
      await Promise.all([page.waitForNavigation(), page.locator('a[href="#edit"]').first().click()]);
    } else {
      await page.goto(base + 'administrator/index.php?option=com_kunena&view=' + v);
    }
    await page.waitForLoadState('load'); await page.waitForTimeout(800);
    const tabs = page.locator('a[data-toggle="tab"]:visible');
    const n = await tabs.count();
    tabCount += n;
    if (n > 1) {
      const target = await tabs.nth(1).getAttribute('href');
      await tabs.nth(1).click();
      await page.waitForTimeout(300);
      tabsWork[v] = await page.locator(target).isVisible();
    } else {
      tabsWork[v] = 'tabs: ' + n;
    }
  }
  // Inline handlers run only on click: the lists' "Clear" filter buttons and the trash's search reset
  const clears = {};
  for (const v of ['attachments', 'categories', 'logs', 'plugins', 'ranks', 'smilies', 'statistics', 'users', 'trash', 'trash&layout=messages']) {
    current = 'admin:clear:' + v;
    await page.goto(base + 'administrator/index.php?option=com_kunena&view=' + v);
    const buttons = page.locator('button[onclick*="filter"]:visible');
    const n = await buttons.count();
    clears[v] = n;
    if (n) {
      await Promise.all([page.waitForNavigation({ timeout: 15000 }).catch(() => note('no reload after Clear')), buttons.first().click()]);
    }
  }

  // A plugin's settings modal (Kunena > Plugins)
  current = 'admin:plugins-modal';
  await page.goto(base + 'administrator/index.php?option=com_kunena&view=plugins');
  let modal = null;
  const opener = page.locator('a[data-toggle="modal"]').first();
  if (await opener.count()) {
    await opener.click();
    await page.waitForTimeout(800);
    modal = await page.locator('.modal:visible, .modal.in, .modal.show').count();
  }
  // Frontend pages
  for (const v of ['', '&view=category&layout=list', '&view=topics&mode=replies', '&view=search', '&view=user&layout=list', '&view=statistics', '&view=topic&layout=create']) {
    current = 'site:' + (v || 'index');
    await page.goto(base + 'index.php?option=com_kunena' + v);
    await page.waitForTimeout(300);
  }
  await page.screenshot({ path: process.env.SHOT || 'jscheck.png' });
  console.log(JSON.stringify({ tpl, wizard, tabCount, tabsWork, clears, modal, errors }, null, 1));
  await browser.close();
  // A guest may not open the new topic form (HTTP 401): expected
  const real = Object.keys(errors).filter((k) => k !== 'site:&view=topic&layout=create');
  const tabsFailed = Object.keys(tabsWork).filter((k) => tabsWork[k] === false);
  process.exit(real.length || tabsFailed.length || /stuck/.test(wizard) || !modal ? 1 : 0);
})().catch(e => { console.error(e); process.exit(1); });
