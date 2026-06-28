'use strict';

// ── State ──────────────────────────────────────────────────────────────────
const state = {
  config: null,
  products: [],
  categories: [],
  activeCategory: 0,
  searchQuery: '',
  currentPage: 1,
  sessionOpen: false,
  isFullscreen: false,
  currency: '',
  receiptSettings: {},
  productUnits: [],
  // POS multi-session tabs
  posTabs: [],
  activePosTabId: null,
  _nextPosTabId: 1,
  // Parked sales (held transactions)
  parkedSales: [],
  _nextParkedId: 1,
  // Logged-in user identity
  _userName:  null,
  _userEmail: null,
  // Feature flags loaded after login — null means "show everything"
  features: null,
};

// ── DOM helpers ────────────────────────────────────────────────────────────
const $ = (sel) => document.querySelector(sel);
const $$ = (sel) => document.querySelectorAll(sel);

function toast(msg, type = 'info') {
  if (type === 'success') return;
  const el = document.createElement('div');
  el.className = `toast ${type}`;
  el.textContent = msg;
  $('#toast-container').appendChild(el);
  setTimeout(() => el.remove(), 3500);
}

function setStatus(msg) { $('#status-text').textContent = msg; }

// ── Window controls ────────────────────────────────────────────────────────
// Login screen window controls (Windows/Linux — non-macOS)
const lMinEl   = document.getElementById('l-min-win');
const lCloseEl = document.getElementById('l-close-win');
if (lMinEl)   lMinEl.addEventListener('click',   () => window.electronAPI.minimize());
if (lCloseEl) lCloseEl.addEventListener('click', () => window.electronAPI.close());
$('#wc-min').addEventListener('click', () => window.electronAPI.minimize());
$('#wc-close').addEventListener('click', () => window.electronAPI.close());
$('#wc-max').addEventListener('click', () => window.electronAPI.maximize());

window.electronAPI.onWindowState((s) => {
  const icon = $('#wc-max-icon');
  const btn  = $('#wc-max');
  if (s === 'maximized') {
    icon.className = 'fa fa-window-restore';
    btn.title = 'Restore Down';
  } else {
    icon.className = 'fa fa-square';
    btn.title = 'Maximize';
  }
});

// ── Clock ──────────────────────────────────────────────────────────────────
function updateClock() {
  const now = new Date();
  $('#status-time').textContent = now.toLocaleTimeString([], { hour: '2-digit', minute: '2-digit' });
}
updateClock();
setInterval(updateClock, 30000);

// ── Dark mode ──────────────────────────────────────────────────────────────
function applyDarkMode(dark) {
  document.body.classList.toggle('dark', dark);
  $('#toggle-dark').checked = dark;
}

$('#toggle-dark').addEventListener('change', async (e) => {
  const dark = e.target.checked;
  applyDarkMode(dark);
  await window.electronAPI.setConfig({ dark_mode: dark });
});

// ── Ribbon tabs ────────────────────────────────────────────────────────────
function activateTab(tabName) {
  $$('.ribbon-tab').forEach(t => t.classList.toggle('active', t.dataset.tab === tabName));
  $$('.ribbon-page').forEach(p => p.classList.toggle('active', p.dataset.page === tabName));

  const panelMap = { home: 'panel-home', pos: 'panel-pos', sales: 'panel-sales', inventory: 'panel-inventory', finance: 'panel-finance', hr: 'panel-hr', design: 'panel-design', view: 'panel-view' };
  $$('.content-panel').forEach(p => p.classList.remove('active'));
  const target = $('#' + (panelMap[tabName] || 'panel-pos'));
  if (target) target.classList.add('active');

  if (tabName === 'home')      loadHomeDashboard();
  if (tabName === 'sales')     { _sal.all = []; _salSwitchView(_sal.view || 'transactions'); }
  if (tabName === 'inventory') { if (typeof switchInvView === 'function') switchInvView('products'); else loadInventory(); }
  if (tabName === 'finance')   { switchFinView('flow'); }
  if (tabName === 'hr')        { switchHrView('employees'); }
  if (tabName === 'design')    { _dsAllData = []; switchDesignView('all'); }
}

$$('.ribbon-tab').forEach(tab => {
  tab.addEventListener('click', () => activateTab(tab.dataset.tab));
});

// ── Backstage (File Menu) ──────────────────────────────────────────────────
function openBackstage(page) {
  $('#backstage').classList.add('open');
  if (page) _bsSwitchPage(page);
  if ($('#backstage .bs-page.active')?.dataset.bsPage === 'sales') loadSalesPage();
}
function closeBackstage() { $('#backstage').classList.remove('open'); }

function _bsSwitchPage(page) {
  $$('.bs-menu-item[data-bs-page]').forEach(i => i.classList.toggle('active', i.dataset.bsPage === page));
  $$('.bs-page').forEach(p => p.classList.toggle('active', p.dataset.bsPage === page));
  if (page === 'sales') loadSalesPage();
}

$('#ribbon-file-btn').addEventListener('click', () => openBackstage('new'));
$('#bs-close-btn').addEventListener('click', closeBackstage);

$$('.bs-menu-item[data-bs-page]').forEach(item => {
  item.addEventListener('click', () => _bsSwitchPage(item.dataset.bsPage));
});

// ── Backstage action dispatcher ────────────────────────────────────────────
function _bsAction(action) {
  const C = closeBackstage;
  const nav = (tab, view, modal) => { C(); activateTab(tab); if (view) { if (tab==='inventory') switchInvView(view); else if (tab==='finance') switchFinView(view); else if (tab==='hr') switchHrView(view); else if (tab==='home') switchHomeView(view); } if (modal) setTimeout(modal, 80); };
  switch (action) {
    // POS & Sales
    case 'openPos':          nav('pos'); break;
    case 'createCustomer':   nav('pos'); setTimeout(() => $('#btn-add-customer')?.click(), 100); break;
    // Inventory — create
    case 'createProduct':    nav('inventory','products'); setTimeout(openAddProductModal, 80); break;
    case 'createCategory':   nav('inventory','categories'); setTimeout(() => $('#cat-add-btn')?.click(), 80); break;
    case 'createBrand':      nav('inventory','brands'); setTimeout(() => $('#brand-add-btn')?.click(), 80); break;
    case 'createSupplier':   nav('inventory','suppliers'); setTimeout(_supOpenModal, 80); break;
    case 'createUnit':       nav('inventory','units'); setTimeout(() => $('#unit-add-btn')?.click(), 80); break;
    case 'createPO':         nav('inventory','po'); setTimeout(openPOCreateModal, 80); break;
    case 'openBarcodes':     nav('inventory','barcodes'); break;
    // Finance — create
    case 'createBill':       nav('finance','bills',       openBillCreateModal); break;
    case 'createLoan':       nav('finance','loans',       openLoanCreateModal); break;
    case 'createRental':     nav('finance','rentals',     openRentalCreateModal); break;
    case 'createModification': nav('finance','modifications', openModificationCreateModal); break;
    // HR — create
    case 'createEmployee':   nav('hr','employees'); setTimeout(() => $('#emp-create-open-btn')?.click(), 80); break;
    case 'createDept':       nav('hr','departments'); setTimeout(() => $('#dept-add-btn')?.click(), 80); break;
    case 'createPayroll':    nav('hr','payroll'); setTimeout(() => $('#payroll-cycle-create-btn')?.click(), 80); break;
    // Design
    case 'newDesign':        nav('design'); setTimeout(() => $('#rb-design-new')?.click(), 80); break;
    // Open — navigation
    case 'navHome':          nav('home','flow'); break;
    case 'navAnalytics':     nav('home','analytics'); break;
    case 'navActivity':      nav('home','activity'); break;
    case 'navPos':           nav('pos'); break;
    case 'navCustomers':     nav('pos'); setTimeout(() => $('#rb-customers')?.click(), 80); break;
    case 'navProducts':      nav('inventory','products'); break;
    case 'navCategories':    nav('inventory','categories'); break;
    case 'navBrands':        nav('inventory','brands'); break;
    case 'navUnits':         nav('inventory','units'); break;
    case 'navDiscounts':     nav('inventory','discounts'); break;
    case 'navSuppliers':     nav('inventory','suppliers'); break;
    case 'navAudit':         nav('inventory','audit'); break;
    case 'navPO':            nav('inventory','po'); break;
    case 'navGRN':           nav('inventory','grn'); break;
    case 'navCheques':       nav('inventory','cheques'); break;
    case 'navBarcodes':      nav('inventory','barcodes'); break;
    case 'navBills':         nav('finance','bills'); break;
    case 'navLoans':         nav('finance','loans'); break;
    case 'navRentals':       nav('finance','rentals'); break;
    case 'navProperties':    nav('finance','properties'); break;
    case 'navModifications': nav('finance','modifications'); break;
    case 'navEmployees':     nav('hr','employees'); break;
    case 'navDepartments':   nav('hr','departments'); break;
    case 'navPayroll':       nav('hr','payroll'); break;
    case 'navDesign':        nav('design'); break;
    // Settings
    case 'navPosSettings':   nav('pos'); setTimeout(() => $('#rb-pos-settings')?.click(), 80); break;
    case 'toggleDark':       $('#toggle-dark').click(); break;
    case 'showShortcuts':    C(); showShortcutsModal(); break;
    case 'switchBranch':     C(); $('#tpm-switch-branch')?.click(); break;
    case 'signOut':          C(); document.dispatchEvent(new CustomEvent('logout-requested')); break;
    // Existing expense/income/hr actions
    case 'viewEmployees':    nav('hr','employees'); break;
    case 'viewDepartments':  nav('hr','departments'); break;
    case 'viewPayroll':      nav('hr','payroll'); break;
  }
}

// ── Card / section builders ────────────────────────────────────────────────
function buildCards(grid, cards) {
  grid.innerHTML = cards.map(c => `
    <div class="bs-card" data-action="${c.action || ''}">
      <div class="card-icon" style="background:${c.color}"><i class="fa ${c.icon}"></i></div>
      <div class="card-label">${c.label}</div>
      <div class="card-desc">${c.desc}</div>
    </div>`).join('');
  grid.querySelectorAll('.bs-card').forEach(card => {
    card.addEventListener('click', () => { if (card.dataset.action) _bsAction(card.dataset.action); });
  });
}

function _buildSections(container, sections) {
  container.innerHTML = sections.map(sec => `
    <div class="bs-section">
      <div class="bs-section-hdr"><i class="fa ${sec.icon}"></i> ${sec.title}</div>
      <div class="card-grid" data-sec-grid></div>
    </div>`).join('');
  container.querySelectorAll('[data-sec-grid]').forEach((grid, i) => buildCards(grid, sections[i].cards));
}

function _buildSettingsGrid(container, items) {
  container.innerHTML = `<div class="bs-settings-grid">${items.map(it => `
    <div class="bs-set-card" data-action="${it.action||''}">
      <div class="bs-set-icon" style="background:${it.color}"><i class="fa ${it.icon}"></i></div>
      <div class="bs-set-info">
        <div class="bs-set-name">${it.label}</div>
        <div class="bs-set-desc">${it.desc}</div>
      </div>
    </div>`).join('')}</div>`;
  container.querySelectorAll('.bs-set-card').forEach(c => {
    c.addEventListener('click', () => { if (c.dataset.action) _bsAction(c.dataset.action); });
  });
}

// ── NEW page sections ──────────────────────────────────────────────────────
const _bsNewSections = [
  { icon:'fa-cart-shopping', title:'POS & Sales', cards:[
    { label:'New Sale',       desc:'Open POS for a quick sale',          icon:'fa-cash-register',       color:'#4caf7d', action:'openPos' },
    { label:'New Customer',   desc:'Add a customer to the database',     icon:'fa-user-plus',            color:'#4e8ef7', action:'createCustomer' },
  ]},
  { icon:'fa-boxes-stacked', title:'Inventory', cards:[
    { label:'Add Product',    desc:'Create a new inventory product',     icon:'fa-plus',                color:'#4e8ef7', action:'createProduct' },
    { label:'Add Category',   desc:'Create a product category',          icon:'fa-tags',                color:'#9c6ef7', action:'createCategory' },
    { label:'Add Brand',      desc:'Create a product brand',             icon:'fa-award',               color:'#f7a54e', action:'createBrand' },
    { label:'Add Supplier',   desc:'Register a new supplier',            icon:'fa-building-user',       color:'#06b6d4', action:'createSupplier' },
    { label:'Add Unit',       desc:'Create a measurement unit',          icon:'fa-ruler',               color:'#64748b', action:'createUnit' },
    { label:'Purchase Order', desc:'Raise a supplier purchase order',    icon:'fa-file-invoice',        color:'#0ea5e9', action:'createPO' },
    { label:'Barcode Sheets', desc:'Select products & print barcodes',   icon:'fa-barcode',             color:'#334155', action:'openBarcodes' },
  ]},
  { icon:'fa-file-invoice-dollar', title:'Finance — Outgoing', cards:[
    { label:'Create Bill',    desc:'Record a new payable bill',          icon:'fa-file-invoice-dollar', color:'#4e8ef7', action:'createBill' },
    { label:'Setup Loan',     desc:'Configure loan terms & repayments',  icon:'fa-money-check-dollar',  color:'#9c6ef7', action:'createLoan' },
    { label:'Make Rental',    desc:'Log a rental or lease payment',      icon:'fa-house',               color:'#f7a54e', action:'createRental' },
    { label:'Modification',   desc:'Track repair & upgrade costs',       icon:'fa-screwdriver-wrench',  color:'#f0a030', action:'createModification' },
  ]},
  { icon:'fa-users', title:'Human Resources', cards:[
    { label:'Add Employee',   desc:'Create a new employee record',       icon:'fa-user-plus',           color:'#4e8ef7', action:'createEmployee' },
    { label:'Add Department', desc:'Create a department structure',      icon:'fa-sitemap',             color:'#9c6ef7', action:'createDept' },
    { label:'Payroll Cycle',  desc:'Start a new payroll processing cycle',icon:'fa-money-check-dollar',color:'#4caf7d', action:'createPayroll' },
  ]},
  { icon:'fa-palette', title:'Design Studio', cards:[
    { label:'New Design',     desc:'Create a new marketing design',      icon:'fa-paintbrush',          color:'#e040fb', action:'newDesign' },
  ]},
];

// ── OPEN page sections ─────────────────────────────────────────────────────
const _bsOpenSections = [
  { icon:'fa-house', title:'Home', cards:[
    { label:'Dashboard',       desc:'Business flow overview',             icon:'fa-gauge-high',          color:'#4caf7d', action:'navHome' },
    { label:'Analytics',       desc:'Revenue charts & expense overview',  icon:'fa-chart-line',          color:'#4e8ef7', action:'navAnalytics' },
    { label:'Recent Activity', desc:'Latest sale transactions',           icon:'fa-clock-rotate-left',   color:'#9c6ef7', action:'navActivity' },
  ]},
  { icon:'fa-cart-shopping', title:'POS & Sales', cards:[
    { label:'Point of Sale',   desc:'Cashier checkout interface',         icon:'fa-cash-register',       color:'#4caf7d', action:'navPos' },
    { label:'Customers',       desc:'Customer database & credit accounts',icon:'fa-users',               color:'#4e8ef7', action:'navCustomers' },
  ]},
  { icon:'fa-boxes-stacked', title:'Inventory', cards:[
    { label:'Products',        desc:'Product catalog & stock levels',     icon:'fa-boxes-stacked',       color:'#4e8ef7', action:'navProducts' },
    { label:'Categories',      desc:'Product category tree',              icon:'fa-tags',                color:'#9c6ef7', action:'navCategories' },
    { label:'Brands',          desc:'Product brand management',           icon:'fa-award',               color:'#f7a54e', action:'navBrands' },
    { label:'Units',           desc:'Measurement units',                  icon:'fa-ruler',               color:'#64748b', action:'navUnits' },
    { label:'Discounts',       desc:'Product discounts & promotions',     icon:'fa-tag',                 color:'#f74e6c', action:'navDiscounts' },
    { label:'Suppliers',       desc:'Supplier directory',                 icon:'fa-building-user',       color:'#06b6d4', action:'navSuppliers' },
    { label:'Stock Audit',     desc:'Stock count & adjustments',          icon:'fa-clipboard-list',      color:'#64748b', action:'navAudit' },
    { label:'Purchase Orders', desc:'Supplier purchase orders',           icon:'fa-file-invoice',        color:'#0ea5e9', action:'navPO' },
    { label:'Goods Receive',   desc:'Receive goods against orders',       icon:'fa-truck-ramp-box',      color:'#4caf7d', action:'navGRN' },
    { label:'Cheques',         desc:'Cheque clearing & management',       icon:'fa-money-check',         color:'#9c6ef7', action:'navCheques' },
    { label:'Barcode Sheets',  desc:'Print product barcodes',             icon:'fa-barcode',             color:'#334155', action:'navBarcodes' },
  ]},
  { icon:'fa-file-invoice-dollar', title:'Finance', cards:[
    { label:'Bills',           desc:'Recurring & one-time bills',         icon:'fa-file-invoice-dollar', color:'#4e8ef7', action:'navBills' },
    { label:'Loans',           desc:'Loan tracking & repayments',         icon:'fa-money-bill-trend-up', color:'#9c6ef7', action:'navLoans' },
    { label:'Rentals',         desc:'Rental & lease payments',            icon:'fa-house-circle-check',  color:'#f7a54e', action:'navRentals' },
    { label:'Properties',      desc:'Property & asset management',        icon:'fa-building',            color:'#64748b', action:'navProperties' },
    { label:'Modifications',   desc:'Repair & upgrade expense tracking',  icon:'fa-screwdriver-wrench',  color:'#f0a030', action:'navModifications' },
  ]},
  { icon:'fa-users', title:'Human Resources', cards:[
    { label:'Employees',       desc:'Employee records & profiles',        icon:'fa-users',               color:'#4e8ef7', action:'navEmployees' },
    { label:'Departments',     desc:'Department management',              icon:'fa-sitemap',             color:'#9c6ef7', action:'navDepartments' },
    { label:'Payroll',         desc:'Payroll cycles & salary sheets',     icon:'fa-money-check-dollar',  color:'#4caf7d', action:'navPayroll' },
  ]},
  { icon:'fa-palette', title:'Design Studio', cards:[
    { label:'All Designs',     desc:'Browse all marketing designs',       icon:'fa-layer-group',         color:'#e040fb', action:'navDesign' },
  ]},
];

// ── Settings items ─────────────────────────────────────────────────────────
const _bsSettingsItems = [
  { label:'POS Settings',       desc:'Receipt, tax & checkout preferences', icon:'fa-cash-register',       color:'#4e8ef7', action:'navPosSettings' },
  { label:'Dark Mode',          desc:'Toggle between light and dark theme',  icon:'fa-moon',                color:'#334155', action:'toggleDark' },
  { label:'Keyboard Shortcuts', desc:'View all keyboard shortcuts',          icon:'fa-keyboard',            color:'#9c6ef7', action:'showShortcuts' },
  { label:'Switch Branch',      desc:'Change the active business branch',    icon:'fa-code-branch',         color:'#f7a54e', action:'switchBranch' },
  { label:'Sign Out',           desc:'Log out of your account',              icon:'fa-right-from-bracket',  color:'#f74e6c', action:'signOut' },
];

// ── About page ─────────────────────────────────────────────────────────────
const _bsFeatureChips = [
  { icon:'fa-cart-shopping',        label:'Point of Sale',        color:'#4caf7d' },
  { icon:'fa-boxes-stacked',        label:'Products & Inventory', color:'#4e8ef7' },
  { icon:'fa-tags',                 label:'Categories & Brands',  color:'#9c6ef7' },
  { icon:'fa-file-invoice',         label:'Purchase Orders',      color:'#0ea5e9' },
  { icon:'fa-truck-ramp-box',       label:'Goods Receive',        color:'#4caf7d' },
  { icon:'fa-money-check',          label:'Cheque Management',    color:'#9c6ef7' },
  { icon:'fa-clipboard-list',       label:'Stock Audit',          color:'#64748b' },
  { icon:'fa-building-user',        label:'Supplier Management',  color:'#06b6d4' },
  { icon:'fa-barcode',              label:'Barcode Sheets',       color:'#334155' },
  { icon:'fa-file-invoice-dollar',  label:'Bills & Expenses',     color:'#4e8ef7' },
  { icon:'fa-money-bill-trend-up',  label:'Loans',                color:'#9c6ef7' },
  { icon:'fa-house-circle-check',   label:'Rentals & Properties', color:'#f7a54e' },
  { icon:'fa-screwdriver-wrench',   label:'Modifications',        color:'#f0a030' },
  { icon:'fa-users',                label:'HR — Employees',       color:'#4e8ef7' },
  { icon:'fa-sitemap',              label:'HR — Departments',     color:'#9c6ef7' },
  { icon:'fa-money-check-dollar',   label:'Payroll Processing',   color:'#4caf7d' },
  { icon:'fa-chart-line',           label:'Analytics Dashboard',  color:'#4e8ef7' },
  { icon:'fa-diagram-project',      label:'Business Flow Chart',  color:'#9c6ef7' },
  { icon:'fa-palette',              label:'Design Studio',        color:'#e040fb' },
  { icon:'fa-tag',                  label:'Discounts & Promos',   color:'#f74e6c' },
];

// ── Build all backstage pages on load ─────────────────────────────────────
_buildSections($('#bs-new-body'),  _bsNewSections);
_buildSections($('#bs-open-body'), _bsOpenSections);
_buildSettingsGrid($('#settings-grid'), _bsSettingsItems);

$('#bs-about-body').innerHTML = `
  <div class="bs-about-hero">
    <div class="bs-about-z">Z</div>
    <div>
      <div class="bs-about-name">Zeebroo POS</div>
      <div class="bs-about-ver">Version 1.0.0 — Desktop Edition</div>
      <div class="bs-about-copy">&copy; 2026 Zeebroo. All rights reserved.</div>
    </div>
  </div>
  <div>
    <div class="bs-features-title"><i class="fa fa-check-circle" style="color:var(--accent)"></i> Installed Features</div>
    <div class="bs-features-grid">
      ${_bsFeatureChips.map(f => `<div class="bs-feature-chip"><i class="fa ${f.icon}" style="color:${f.color}"></i>${f.label}</div>`).join('')}
    </div>
  </div>`;

// ── Existing expense / income / HR cards ──────────────────────────────────
const expenseCards = [
  { label:'Create a Bill',   desc:'Record a new payable bill',         icon:'fa-file-invoice-dollar', color:'#4e8ef7', action:'createBill' },
  { label:'Loan Setup',      desc:'Configure loan terms & repayments', icon:'fa-money-check-dollar',  color:'#9c6ef7', action:'createLoan' },
  { label:'Make Rental',     desc:'Log rental or lease payments',      icon:'fa-house',               color:'#f7a54e', action:'createRental' },
  { label:'Modification',    desc:'Track repair & upgrade costs',      icon:'fa-screwdriver-wrench',  color:'#f0a030', action:'createModification' },
  { label:'Purchase Order',  desc:'Raise a supplier purchase order',   icon:'fa-bag-shopping',        color:'#0ea5e9', action:'createPO' },
  { label:'Legal',           desc:'Legal fees & compliance costs',     icon:'fa-scale-balanced',      color:'#f74e6c' },
  { label:'Transport',       desc:'Freight, fuel & travel expenses',   icon:'fa-plane',               color:'#60c060' },
  { label:'Marketing',       desc:'Ads, promotions & campaigns',       icon:'fa-bullhorn',            color:'#e040fb' },
  { label:'Other Expenses',  desc:'Miscellaneous uncategorised costs', icon:'fa-calculator',          color:'#90a4ae' },
];
const incomeCards = [
  { label:'POS',                 desc:'Open point-of-sale for quick sales',   icon:'fa-cart-shopping',  color:'#4caf7d', action:'openPos' },
  { label:'Create Sales Invoice',desc:'Issue an invoice to a customer',       icon:'fa-file-invoice',   color:'#f7a54e' },
  { label:'Credit Recovery',     desc:'Track & recover outstanding credits',  icon:'fa-credit-card',    color:'#f74e6c' },
  { label:'Profit Analytics',    desc:'View revenue trends & profit reports', icon:'fa-chart-line',     color:'#e040fb', action:'navAnalytics' },
];
const hrCards = [
  { label:'Employees',   desc:'View and manage employee records',   icon:'fa-users',              color:'#4e8ef7', action:'viewEmployees' },
  { label:'Departments', desc:'Manage department structure',        icon:'fa-sitemap',            color:'#9c6ef7', action:'viewDepartments' },
  { label:'Payroll',     desc:'Process monthly salary & payslips', icon:'fa-money-check-dollar', color:'#4caf7d', action:'viewPayroll' },
];

buildCards($('#expenses-grid'), expenseCards);
buildCards($('#income-grid'),   incomeCards);
buildCards($('#hr-grid'),       hrCards);

async function loadSalesPage() {
  const container = $('#sales-summary');
  container.innerHTML = '<div class="bs-loading"><i class="fa fa-spinner fa-spin"></i> Loading recent sales…</div>';
  const res = await API.sales('');
  if (res.status !== 200) { container.innerHTML = '<div class="bs-loading"><i class="fa fa-circle-exclamation" style="color:#e74c3c"></i> Failed to load sales</div>'; return; }
  const items = (res.body?.data || []).slice(0, 30);
  if (!items.length) { container.innerHTML = '<div class="bs-loading">No sales found.</div>'; return; }
  container.innerHTML = `<table class="bs-sales-tbl">
    <thead><tr>
      <th>Sale #</th><th>Date / Time</th><th>Customer</th><th>Payment</th><th>Status</th><th style="text-align:right">Total</th>
    </tr></thead>
    <tbody>
    ${items.map(s => {
      const dt  = s.sold_at ? new Date(s.sold_at).toLocaleString(undefined, { month:'short', day:'numeric', hour:'2-digit', minute:'2-digit' }) : '—';
      const cls = s.status === 'voided' ? 'voided' : 'completed';
      const lbl = s.status === 'voided' ? 'Voided' : 'Completed';
      return `<tr>
        <td class="td-ref"><i class="fa fa-receipt" style="margin-right:6px;opacity:.6"></i>${escHtml(s.sale_number||String(s.id))}</td>
        <td class="td-muted">${dt}</td>
        <td class="td-muted">${escHtml(s.customer_name||'—')}</td>
        <td class="td-muted">${escHtml(s.payment_method||'—')}</td>
        <td><span class="bs-sales-badge ${cls}">${lbl}</span></td>
        <td class="td-amt" style="color:var(--accent)">${parseFloat(s.total||0).toFixed(2)}</td>
      </tr>`;
    }).join('')}
    </tbody>
  </table>`;
}

// ── Sales Panel ────────────────────────────────────────────────────────────
const _sal = {
  all: [], filtered: [],
  channel: '', status: '', search: '',
  activeSale: null,
  view: 'transactions',   // 'transactions' | 'history' | 'quotes'
};
let _salSearchTimer;

function _salSwitchView(view) {
  _sal.view = view;
  $$('.sal-view-btn').forEach(b => {
    if (b.dataset.salView === view) b.classList.add('active');
    else b.classList.remove('active');
  });
  $$('.sal-txn-ctrl').forEach(el => el.style.display = view === 'transactions' ? '' : 'none');
  const showTxn      = view === 'transactions';
  const showHist     = view === 'history';
  const showQuotes   = view === 'quotes';
  const showInvoices = view === 'invoices';
  $('#sal-list-view').style.display      = showTxn      ? '' : 'none';
  $('#sal-history-view').style.display   = showHist     ? '' : 'none';
  $('#sal-quotes-view').style.display    = showQuotes   ? 'flex' : 'none';
  $('#sal-invoices-view').style.display  = showInvoices ? 'flex' : 'none';
  $('#sal-detail-view').style.display    = 'none';
  if (showTxn      && !_sal.all.length) loadSalesList();
  if (showHist)     loadSalesHistory();
  if (showQuotes)   loadQuotesList();
  if (showInvoices) loadInvoicesList();
}

async function loadSalesList() {
  _salCloseDetail();
  $('#sal-list-view').style.display    = '';
  $('#sal-history-view').style.display = 'none';
  $('#sal-table-wrap').innerHTML = '<div class="finance-loading"><i class="fa fa-spinner fa-spin"></i> Loading sales…</div>';
  const res = await API.sales('');
  if (res.status !== 200) {
    $('#sal-table-wrap').innerHTML = '<div class="finance-loading"><i class="fa fa-circle-exclamation" style="color:#ef4444"></i> Failed to load sales</div>';
    return;
  }
  _sal.all = res.body?.data || [];
  _salApplyFilters();
}

function _salApplyFilters() {
  const q = _sal.search.toLowerCase();
  _sal.filtered = _sal.all.filter(s => {
    if (_sal.channel && s.channel !== _sal.channel) return false;
    if (_sal.status  && s.status  !== _sal.status)  return false;
    if (q && !(
      (s.sale_number  || '').toLowerCase().includes(q) ||
      (s.customer_name|| '').toLowerCase().includes(q) ||
      (s.payment_method||'').toLowerCase().includes(q)
    )) return false;
    return true;
  });
  _salRenderList();
}

function _salRenderList() {
  const cur = state.currency ? ' ' + state.currency : '';
  const list = _sal.filtered;

  // KPI bar
  const completed = list.filter(s => s.status !== 'voided');
  const revenue   = completed.reduce((n, s) => n + parseFloat(s.total || 0), 0);
  const kpiBar    = $('#sal-kpi-bar');
  if (list.length) {
    kpiBar.style.display = '';
    kpiBar.innerHTML = `
      <div class="sal-kpi-item">
        <span class="sal-kpi-label">Total Transactions</span>
        <span class="sal-kpi-val">${list.length}</span>
      </div>
      <div class="sal-kpi-item">
        <span class="sal-kpi-label">Completed</span>
        <span class="sal-kpi-val">${completed.length}</span>
      </div>
      <div class="sal-kpi-item">
        <span class="sal-kpi-label">Revenue</span>
        <span class="sal-kpi-val accent">${revenue.toFixed(2)}${cur}</span>
      </div>`;
  } else {
    kpiBar.style.display = 'none';
  }

  // Count
  $('#sal-count').textContent = list.length ? `${list.length} transaction${list.length !== 1 ? 's' : ''}` : 'No transactions';

  if (!list.length) {
    $('#sal-table-wrap').innerHTML = '<div class="finance-loading">No sales found.</div>';
    return;
  }

  $('#sal-table-wrap').innerHTML = `<table class="sal-tbl">
    <thead><tr>
      <th>Sale #</th><th>Date / Time</th><th>Customer</th><th>Channel</th><th>Payment</th><th>Status</th><th style="text-align:right">Total</th>
    </tr></thead>
    <tbody>
    ${list.map(s => {
      const dt  = s.sold_at ? new Date(s.sold_at).toLocaleString(undefined, { month: 'short', day: 'numeric', year: 'numeric', hour: '2-digit', minute: '2-digit' }) : '—';
      const cls = s.status === 'voided' ? 'voided' : 'completed';
      const lbl = s.status === 'voided' ? 'Voided' : 'Completed';
      const ch  = s.channel === 'online' ? 'online' : '';
      const chLbl = s.channel === 'online' ? 'Online' : 'POS';
      return `<tr data-sal-id="${s.id}">
        <td class="td-ref"><i class="fa fa-receipt" style="margin-right:6px;opacity:.55"></i>${escHtml(s.sale_number || String(s.id))}</td>
        <td class="td-muted">${dt}</td>
        <td class="td-muted">${escHtml(s.customer_name || '—')}</td>
        <td><span class="sal-channel-chip ${ch}">${chLbl}</span></td>
        <td class="td-muted">${escHtml(s.payment_method || '—')}</td>
        <td><span class="bs-sales-badge ${cls}">${lbl}</span></td>
        <td class="td-amt">${parseFloat(s.total || 0).toFixed(2)}${cur}</td>
      </tr>`;
    }).join('')}
    </tbody>
  </table>`;

  $('#sal-table-wrap').querySelectorAll('tr[data-sal-id]').forEach(row => {
    row.addEventListener('click', () => _salSelectSale(Number(row.dataset.salId)));
  });
}

async function _salSelectSale(id) {
  $('#sal-list-view').style.display   = 'none';
  $('#sal-detail-view').style.display = 'flex';
  $('#sal-detail-title').textContent  = 'Loading…';
  $('#sal-detail-body').innerHTML     = '<div class="finance-loading"><i class="fa fa-spinner fa-spin"></i></div>';

  const res = await API.sale(id);
  if (res.status !== 200) {
    $('#sal-detail-body').innerHTML = '<div class="finance-loading"><i class="fa fa-circle-exclamation" style="color:#ef4444"></i> Failed to load sale</div>';
    return;
  }
  const sale = res.body?.data || res.body;
  _sal.activeSale = sale;

  const num     = sale.sale_number || String(sale.id);
  const cur     = state.currency ? ' ' + state.currency : '';
  const isVoided = sale.status === 'voided';

  $('#sal-detail-title').textContent = `Sale #${num}`;
  $('#sal-void-btn').style.display   = isVoided ? 'none' : '';
  $('#sal-return-btn').style.display = isVoided ? 'none' : '';

  const dateStr = sale.sold_at
    ? new Date(sale.sold_at).toLocaleString([], { dateStyle: 'medium', timeStyle: 'short' })
    : '—';

  const itemsRows = (sale.items || []).map(i => `<tr>
    <td>${escHtml(i.product_name || '—')}</td>
    <td class="td-r">${parseFloat(i.quantity) % 1 === 0 ? parseInt(i.quantity) : parseFloat(i.quantity).toFixed(2)}</td>
    <td class="td-r">${parseFloat(i.unit_sell_price || 0).toFixed(2)}${cur}</td>
    <td class="td-r"><strong>${parseFloat(i.line_total || 0).toFixed(2)}${cur}</strong></td>
  </tr>`).join('');

  const discount = parseFloat(sale.discount_amount || 0);
  const change   = parseFloat(sale.change_amount || 0);

  let totalsHTML = `
    <div class="sal-total-row"><span class="label">Subtotal</span><span>${parseFloat(sale.subtotal || 0).toFixed(2)}${cur}</span></div>`;
  if (discount > 0) {
    totalsHTML += `<div class="sal-total-row"><span class="label">Discount</span><span>-${discount.toFixed(2)}${cur}</span></div>`;
  }
  totalsHTML += `
    <div class="sal-total-row grand"><span class="label">Total</span><span>${parseFloat(sale.total || 0).toFixed(2)}${cur}</span></div>
    <div class="sal-total-row"><span class="label">Paid (${escHtml(sale.payment_method_label || sale.payment_method || '')})</span><span>${parseFloat(sale.amount_paid || sale.total || 0).toFixed(2)}${cur}</span></div>`;
  if (change > 0) {
    totalsHTML += `<div class="sal-total-row"><span class="label">Change</span><span>${change.toFixed(2)}${cur}</span></div>`;
  }

  $('#sal-detail-body').innerHTML = `
    ${isVoided ? '<div class="sal-void-notice"><i class="fa fa-ban"></i> This sale has been voided</div>' : ''}
    <div class="sal-detail-meta">
      <div class="sal-detail-meta-item">
        <span class="sal-detail-meta-label">Sale #</span>
        <span class="sal-detail-meta-val">${escHtml(num)}</span>
      </div>
      <div class="sal-detail-meta-item">
        <span class="sal-detail-meta-label">Date</span>
        <span class="sal-detail-meta-val">${dateStr}</span>
      </div>
      <div class="sal-detail-meta-item">
        <span class="sal-detail-meta-label">Customer</span>
        <span class="sal-detail-meta-val">${escHtml(sale.customer_name || '—')}</span>
      </div>
      ${sale.cashier?.name ? `<div class="sal-detail-meta-item">
        <span class="sal-detail-meta-label">Cashier</span>
        <span class="sal-detail-meta-val">${escHtml(sale.cashier.name)}</span>
      </div>` : ''}
      <div class="sal-detail-meta-item">
        <span class="sal-detail-meta-label">Status</span>
        <span class="sal-detail-meta-val"><span class="bs-sales-badge ${isVoided ? 'voided' : 'completed'}">${isVoided ? 'Voided' : 'Completed'}</span></span>
      </div>
    </div>

    <div class="sal-section-title">Items</div>
    <table class="sal-items-tbl">
      <thead><tr><th>Product</th><th class="td-r">Qty</th><th class="td-r">Unit Price</th><th class="td-r">Total</th></tr></thead>
      <tbody>${itemsRows}</tbody>
    </table>

    <div class="sal-section-title">Totals</div>
    <div class="sal-totals">${totalsHTML}</div>`;
}

function _salCloseDetail() {
  _sal.activeSale = null;
  $('#sal-detail-view').style.display = 'none';
  if (_sal.view === 'history') {
    $('#sal-history-view').style.display = '';
  } else {
    $('#sal-list-view').style.display = '';
  }
}

// ── View switcher ─────────────────────────────────────────────────────────
$$('.sal-view-btn').forEach(btn => {
  btn.addEventListener('click', () => _salSwitchView(btn.dataset.salView));
});

// ── Transactions filters ──────────────────────────────────────────────────
$('#sal-search').addEventListener('input', e => {
  clearTimeout(_salSearchTimer);
  _sal.search = e.target.value.trim();
  _salSearchTimer = setTimeout(_salApplyFilters, 250);
});

// ── Detail actions ────────────────────────────────────────────────────────
$('#sal-detail-back').addEventListener('click', _salCloseDetail);
$('#sal-print-btn').addEventListener('click', () => { if (_sal.activeSale) showReceiptModal(_sal.activeSale); });
$('#sal-return-btn').addEventListener('click', () => { _salCloseDetail(); openRefundModal(); });
$('#sal-void-btn').addEventListener('click', async () => {
  const sale = _sal.activeSale;
  if (!sale) return;
  const btn = $('#sal-void-btn');
  btn.disabled = true;
  btn.innerHTML = '<i class="fa fa-spinner fa-spin"></i> Voiding…';
  const res = await API.voidSale(sale.id);
  btn.disabled = false;
  btn.innerHTML = '<i class="fa fa-ban"></i> Void';
  if (res.status >= 400) { toast(res.body?.message || 'Could not void sale', 'error'); return; }
  toast(`Sale #${sale.sale_number} voided`, 'info');
  const idx = _sal.all.findIndex(s => s.id === sale.id);
  if (idx >= 0) _sal.all[idx] = { ..._sal.all[idx], status: 'voided' };
  _salApplyFilters();
  await _salSelectSale(sale.id);
});

// ── History view ──────────────────────────────────────────────────────────
const _salHist = {
  period: '30', page: 1, lastPage: 1,
  dateFrom: '', dateTo: '',
};

function _salHistDates() {
  const today = new Date();
  const fmt   = d => d.toISOString().slice(0, 10);
  const todayStr = fmt(today);
  if (_salHist.period === 'month') {
    const first = new Date(today.getFullYear(), today.getMonth(), 1);
    return { date_from: fmt(first), date_to: todayStr };
  }
  if (_salHist.period === 'custom') {
    return { date_from: _salHist.dateFrom, date_to: _salHist.dateTo };
  }
  const days = parseInt(_salHist.period) || 30;
  const from = new Date(today);
  from.setDate(from.getDate() - (days - 1));
  return { date_from: fmt(from), date_to: todayStr };
}

async function loadSalesHistory(page) {
  if (page) _salHist.page = page;
  const dates = _salHistDates();
  const params = {
    ...dates,
    channel: $('#sal-hist-channel').value || '',
    status:  $('#sal-hist-status').value  || 'all',
    page:    _salHist.page,
  };

  $('#sal-hist-table-wrap').innerHTML = '<div class="finance-loading"><i class="fa fa-spinner fa-spin"></i> Loading…</div>';
  $('#sal-hist-kpi').style.display    = 'none';
  $('#sal-hist-pagination').style.display = 'none';

  const res = await API.salesHistory(params);
  if (res.status !== 200) {
    $('#sal-hist-table-wrap').innerHTML = '<div class="finance-loading"><i class="fa fa-circle-exclamation" style="color:#ef4444"></i> Failed to load history</div>';
    return;
  }

  const data    = res.body?.data    || [];
  const meta    = res.body?.meta    || {};
  const summary = res.body?.summary || {};
  const chart   = res.body?.chart   || [];

  _salHist.lastPage = meta.last_page || 1;

  _salHistRenderKPIs(summary);
  _salHistRenderChart(chart, dates);
  _salHistRenderTable(data);

  // Pagination
  const pag = $('#sal-hist-pagination');
  if (meta.last_page > 1) {
    pag.style.display = '';
    $('#sal-hist-page-info').textContent = `Page ${meta.current_page} of ${meta.last_page}  (${meta.total} records)`;
    $('#sal-hist-prev').disabled = meta.current_page <= 1;
    $('#sal-hist-next').disabled = meta.current_page >= meta.last_page;
  }
}

function _salHistRenderKPIs(summary) {
  const cur  = state.currency ? ' ' + state.currency : '';
  const bar  = $('#sal-hist-kpi');
  const avg  = summary.completed_count > 0 ? summary.completed_total / summary.completed_count : 0;
  bar.style.display = '';
  bar.innerHTML = `
    <div class="sal-kpi-item">
      <span class="sal-kpi-label">Total Transactions</span>
      <span class="sal-kpi-val">${summary.count ?? 0}</span>
    </div>
    <div class="sal-kpi-item">
      <span class="sal-kpi-label">Completed</span>
      <span class="sal-kpi-val">${summary.completed_count ?? 0}</span>
    </div>
    <div class="sal-kpi-item">
      <span class="sal-kpi-label">Voided</span>
      <span class="sal-kpi-val">${summary.void_count ?? 0}</span>
    </div>
    <div class="sal-kpi-item">
      <span class="sal-kpi-label">Revenue</span>
      <span class="sal-kpi-val accent">${parseFloat(summary.completed_total ?? 0).toFixed(2)}${cur}</span>
    </div>
    <div class="sal-kpi-item">
      <span class="sal-kpi-label">Avg Order</span>
      <span class="sal-kpi-val">${avg.toFixed(2)}${cur}</span>
    </div>`;
}

function _salHistRenderChart(chartData, dates) {
  const container = $('#sal-hist-chart');
  if (!container || !window.d3 || !chartData.length) {
    container.innerHTML = '<div class="finance-loading" style="height:100%;display:flex;align-items:center;justify-content:center">No data for this period</div>';
    return;
  }

  const lbl = {
    '7': 'Last 7 days', '30': 'Last 30 days', '90': 'Last 90 days',
    month: 'This Month', custom: `${dates.date_from} – ${dates.date_to}`,
  }[_salHist.period] || 'Revenue trend';
  $('#sal-hist-chart-label').textContent = lbl;

  const data = chartData.map(r => ({ date: new Date(r.date + 'T00:00:00'), value: r.total }));

  container.innerHTML = '';
  const W = container.clientWidth  || 500;
  const H = container.clientHeight || 150;
  const m = { top: 10, right: 16, bottom: 26, left: 50 };
  const w = W - m.left - m.right;
  const h = H - m.top  - m.bottom;
  if (w <= 0 || h <= 0) return;

  const st     = getComputedStyle(document.body);
  const accent = st.getPropertyValue('--accent').trim()     || '#6366f1';
  const muted  = st.getPropertyValue('--text-muted').trim() || '#94a3b8';
  const border = st.getPropertyValue('--border').trim()     || '#e2e8f0';

  const svg = d3.select(container).append('svg').attr('width', W).attr('height', H);
  const g   = svg.append('g').attr('transform', `translate(${m.left},${m.top})`);

  const xScale = d3.scaleTime().domain(d3.extent(data, d => d.date)).range([0, w]);
  const yMax   = d3.max(data, d => d.value) || 1;
  const yScale = d3.scaleLinear().domain([0, yMax * 1.12]).range([h, 0]);

  g.append('g').call(d3.axisLeft(yScale).ticks(4).tickSize(-w).tickFormat(''))
    .call(g2 => { g2.select('.domain').remove(); g2.selectAll('line').attr('stroke', border).attr('stroke-dasharray', '3,3'); });

  const gradId = 'sal-hist-grad';
  const defs = svg.append('defs');
  const grad = defs.append('linearGradient').attr('id', gradId).attr('x1','0%').attr('y1','0%').attr('x2','0%').attr('y2','100%');
  grad.append('stop').attr('offset','0%').attr('stop-color', accent).attr('stop-opacity', 0.2);
  grad.append('stop').attr('offset','100%').attr('stop-color', accent).attr('stop-opacity', 0.02);

  g.append('path').datum(data)
    .attr('fill', `url(#${gradId})`)
    .attr('d', d3.area().x(d => xScale(d.date)).y0(h).y1(d => yScale(d.value)).curve(d3.curveMonotoneX));
  g.append('path').datum(data)
    .attr('fill', 'none').attr('stroke', accent).attr('stroke-width', 2)
    .attr('d', d3.line().x(d => xScale(d.date)).y(d => yScale(d.value)).curve(d3.curveMonotoneX));

  if (data.length <= 31) {
    g.selectAll('circle').data(data).join('circle')
      .attr('cx', d => xScale(d.date)).attr('cy', d => yScale(d.value))
      .attr('r', 3).attr('fill', accent).attr('stroke', '#fff').attr('stroke-width', 1.5);
  }

  const tickCount = data.length <= 7 ? data.length : (data.length <= 31 ? 6 : 8);
  const tickFmt   = data.length <= 7 ? d3.timeFormat('%a %-d') : d3.timeFormat('%b %-d');
  g.append('g').attr('transform', `translate(0,${h})`)
    .call(d3.axisBottom(xScale).ticks(tickCount).tickFormat(tickFmt))
    .call(g2 => { g2.select('.domain').attr('stroke', border); g2.selectAll('text').attr('fill', muted).style('font-size','10px'); g2.selectAll('line').attr('stroke', border); });
  g.append('g')
    .call(d3.axisLeft(yScale).ticks(4).tickFormat(v => v >= 1000 ? `${(v/1000).toFixed(1)}k` : v.toFixed(0)))
    .call(g2 => { g2.select('.domain').attr('stroke', border); g2.selectAll('text').attr('fill', muted).style('font-size','10px'); g2.selectAll('line').attr('stroke', border); });
}

function _salHistRenderTable(data) {
  const cur = state.currency ? ' ' + state.currency : '';
  if (!data.length) {
    $('#sal-hist-table-wrap').innerHTML = '<div class="finance-loading">No transactions for this period.</div>';
    return;
  }
  $('#sal-hist-table-wrap').innerHTML = `<table class="sal-tbl">
    <thead><tr>
      <th>Sale #</th><th>Date / Time</th><th>Customer</th><th>Channel</th><th>Payment</th><th>Status</th><th style="text-align:right">Total</th>
    </tr></thead>
    <tbody>
    ${data.map(s => {
      const dt  = s.sold_at ? new Date(s.sold_at).toLocaleString(undefined, { month:'short', day:'numeric', year:'numeric', hour:'2-digit', minute:'2-digit' }) : '—';
      const cls = s.status === 'voided' ? 'voided' : 'completed';
      const ch  = s.channel === 'online' ? 'online' : '';
      return `<tr data-sal-id="${s.id}">
        <td class="td-ref"><i class="fa fa-receipt" style="margin-right:6px;opacity:.55"></i>${escHtml(s.sale_number || String(s.id))}</td>
        <td class="td-muted">${dt}</td>
        <td class="td-muted">${escHtml(s.customer_name || '—')}</td>
        <td><span class="sal-channel-chip ${ch}">${s.channel === 'online' ? 'Online' : 'POS'}</span></td>
        <td class="td-muted">${escHtml(s.payment_method || '—')}</td>
        <td><span class="bs-sales-badge ${cls}">${cls === 'voided' ? 'Voided' : 'Completed'}</span></td>
        <td class="td-amt">${parseFloat(s.total || 0).toFixed(2)}${cur}</td>
      </tr>`;
    }).join('')}
    </tbody>
  </table>`;
  $('#sal-hist-table-wrap').querySelectorAll('tr[data-sal-id]').forEach(row => {
    row.addEventListener('click', () => {
      $('#sal-history-view').style.display = 'none';
      _salSelectSale(Number(row.dataset.salId));
    });
  });
}

// Period buttons
$$('.sal-period-btn').forEach(btn => {
  btn.addEventListener('click', () => {
    $$('.sal-period-btn').forEach(b => b.classList.remove('active'));
    btn.classList.add('active');
    _salHist.period = btn.dataset.period;
    _salHist.page   = 1;
    $('#sal-date-range').style.display = _salHist.period === 'custom' ? '' : 'none';
    if (_salHist.period !== 'custom') loadSalesHistory(1);
  });
});

// Custom date apply
$('#sal-hist-date-apply').addEventListener('click', () => {
  _salHist.dateFrom = $('#sal-hist-date-from').value;
  _salHist.dateTo   = $('#sal-hist-date-to').value;
  if (_salHist.dateFrom && _salHist.dateTo) loadSalesHistory(1);
});

// Channel / status selects
$('#sal-hist-channel').addEventListener('change', () => loadSalesHistory(1));
$('#sal-hist-status').addEventListener('change',  () => loadSalesHistory(1));

// Refresh button
$('#sal-hist-refresh').addEventListener('click', () => loadSalesHistory(1));

// Pagination
$('#sal-hist-prev').addEventListener('click', () => {
  if (_salHist.page > 1) loadSalesHistory(_salHist.page - 1);
});
$('#sal-hist-next').addEventListener('click', () => {
  if (_salHist.page < _salHist.lastPage) loadSalesHistory(_salHist.page + 1);
});

// ── Ribbon buttons ────────────────────────────────────────────────────────
$('#rb-sal-refresh')?.addEventListener('click', () => {
  if (_sal.view === 'history') loadSalesHistory(1); else { _sal.all = []; loadSalesList(); }
});
$('#rb-sal-all')?.addEventListener('click', () => {
  _sal.channel = ''; _salSwitchView('transactions'); _salApplyFilters();
});
$('#rb-sal-pos')?.addEventListener('click', () => {
  _sal.channel = 'retail'; _salSwitchView('transactions'); _salApplyFilters();
});
$('#rb-sal-return')?.addEventListener('click', openRefundModal);
// ── End of Day Settlement ─────────────────────────────────────────────────
const _eod = { open: false };

function _eodOpen() {
  $('#eod-overlay').style.display = 'flex';
  _eod.open = true;
  _eodLoad();
}

function _eodClose() {
  $('#eod-overlay').style.display = 'none';
  _eod.open = false;
}

async function _eodLoad() {
  const body   = $('#eod-body');
  const footer = $('#eod-footer');
  footer.style.display = 'none';
  body.innerHTML = '<div class="eod-loading"><i class="fa fa-spinner fa-spin"></i> Loading…</div>';

  const res = await API.eodStatus();
  if (res.status !== 200) {
    body.innerHTML = '<div class="eod-loading"><i class="fa fa-circle-exclamation" style="color:#ef4444"></i> Failed to load EOD status.</div>';
    return;
  }

  const d        = res.body;
  const currency = d.currency ? ' ' + d.currency : '';
  const summary  = d.summary  || {};
  const unsettled = d.unsettled || [];
  const history   = d.history   || [];
  const byMethod  = summary.by_method || {};

  let html = '';

  // Summary cards
  html += `<div class="eod-summary-bar">
    <div class="eod-stat eod-stat--highlight">
      <p class="eod-stat__label">Unsettled Sales</p>
      <p class="eod-stat__value">${summary.total_count ?? 0}</p>
    </div>
    <div class="eod-stat eod-stat--highlight">
      <p class="eod-stat__label">Cash Pending</p>
      <p class="eod-stat__value">${(byMethod.cash?.total ?? 0).toFixed(2)}${currency}</p>
    </div>
    <div class="eod-stat eod-stat--highlight">
      <p class="eod-stat__label">Card Pending</p>
      <p class="eod-stat__value">${(byMethod.card?.total ?? 0).toFixed(2)}${currency}</p>
    </div>
  </div>`;

  if (unsettled.length === 0) {
    html += `<div class="eod-all-ok"><i class="fa fa-circle-check"></i> All sales are settled. Nothing pending for today.</div>`;
  } else {
    html += `<p class="eod-section-lbl"><i class="fa fa-clock"></i> Pending settlement (${unsettled.length})</p>`;
    html += `<div class="eod-tbl-wrap"><table class="eod-tbl">
      <thead><tr>
        <th>Sale #</th><th>Time</th><th>Payment</th><th>Account</th><th style="text-align:right">Total</th>
      </tr></thead>
      <tbody>
      ${unsettled.map(s => {
        const t = s.sold_at ? new Date(s.sold_at).toLocaleTimeString([], { hour: '2-digit', minute: '2-digit' }) : '—';
        const badge = s.payment_method === 'card' ? 'eod-badge-card' : 'eod-badge-cash';
        const label = s.payment_method === 'card' ? 'Card' : 'Cash';
        return `<tr>
          <td style="font-weight:700">${escHtml(s.sale_number || String(s.id))}</td>
          <td style="color:var(--text-muted)">${t}</td>
          <td><span class="${badge}">${label}</span></td>
          <td style="color:var(--text-muted);font-size:11px">${escHtml(s.account_label || '—')}</td>
          <td style="text-align:right;font-weight:700">${s.total.toFixed(2)}${currency}</td>
        </tr>`;
      }).join('')}
      </tbody>
      <tfoot><tr>
        <td colspan="4" style="text-align:right;font-size:11px;font-weight:700;color:var(--text-muted);padding:8px 10px">Total</td>
        <td style="text-align:right;font-size:15px;font-weight:800;padding:8px 10px">${(summary.total_amount ?? 0).toFixed(2)}${currency}</td>
      </tr></tfoot>
    </table></div>`;

    footer.style.display = '';
    $('#eod-footer-info').textContent = `${summary.total_count} sale${summary.total_count !== 1 ? 's' : ''} · ${(summary.total_amount ?? 0).toFixed(2)}${currency} total`;
    const btn = $('#eod-settle-btn');
    btn.disabled = false;
    btn.innerHTML = '<i class="fa fa-check-circle"></i> Settle All to Bank';
  }

  // History
  if (history.length) {
    html += `<p class="eod-section-lbl" style="margin-top:10px"><i class="fa fa-history"></i> Recent settlements</p>`;
    html += `<div class="eod-tbl-wrap"><div>`;
    html += history.map(r => {
      const dt = new Date(r.date + 'T00:00:00').toLocaleDateString(undefined, { month: 'short', day: 'numeric', year: 'numeric' });
      return `<div class="eod-history-row">
        <span class="eod-history-date">${dt}</span>
        <span class="eod-history-count">${r.sale_count} sale${r.sale_count !== 1 ? 's' : ''}</span>
        <span class="eod-history-total">${r.total.toFixed(2)}${currency}</span>
      </div>`;
    }).join('');
    html += `</div></div>`;
  }

  body.innerHTML = html;
}

$('#eod-settle-btn').addEventListener('click', async () => {
  const btn = $('#eod-settle-btn');
  btn.disabled = true;
  btn.innerHTML = '<i class="fa fa-spinner fa-spin"></i> Settling…';

  const res = await API.eodSettle();
  if (res.status === 200) {
    toast(res.body?.message || 'Settlement complete.', 'success');
    // reload EOD status in panel
    $('#eod-footer').style.display = 'none';
    _eodLoad();
    // also refresh sales list if open
    if (_sal.view === 'transactions') { _sal.all = []; loadSalesList(); }
  } else {
    toast(res.body?.message || 'Settlement failed.', 'error');
    btn.disabled = false;
    btn.innerHTML = '<i class="fa fa-check-circle"></i> Settle All to Bank';
  }
});

$('#eod-close-btn').addEventListener('click', _eodClose);
$('#eod-overlay').addEventListener('click', e => { if (e.target === $('#eod-overlay')) _eodClose(); });
$('#rb-eod-open')?.addEventListener('click', _eodOpen);
// ── End of Day Settlement ─────────────────────────────────────────────────

// ── End Sales Panel ────────────────────────────────────────────────────────

// ── Quotations Panel ──────────────────────────────────────────────────────
const _qt = {
  list:        [],
  status:      'all',
  search:      '',
  activeId:    null,
  editingId:   null,   // null = new, number = edit
  customers:   [],
  lineSeq:     0,
};
let _qtSearchTimer = null;

// ── List ──────────────────────────────────────────────────────────────────
async function loadQuotesList() {
  _qtShowView('list');
  $('#qt-list-loading').style.display = '';
  $('#qt-list-body').innerHTML = '';

  const res = await API.quotations(_qt.search, _qt.status);
  $('#qt-list-loading').style.display = 'none';

  if (res.status !== 200) {
    $('#qt-list-body').innerHTML = '<div class="qt-empty"><i class="fa fa-circle-exclamation"></i> Failed to load quotations.</div>';
    return;
  }

  _qt.list = res.body?.data || [];
  _qtRenderList();
}

function _qtRenderList() {
  const cur = state.currency ? ' ' + state.currency : '';
  if (!_qt.list.length) {
    $('#qt-list-body').innerHTML = `<div class="qt-empty">
      <i class="fa fa-file-circle-question"></i>
      <h4>No Quotations Found</h4>
      <p>Create your first quotation to get started.</p>
    </div>`;
    return;
  }

  // Compute stats
  const counts = { draft: 0, sent: 0, accepted: 0, rejected: 0 };
  let totalVal = 0;
  _qt.list.forEach(q => { if (counts[q.status] !== undefined) counts[q.status]++; totalVal += parseFloat(q.total || 0); });

  let html = `<div class="qt-stats">
    <div class="qt-stat"><span class="qt-stat-val">${_qt.list.length}</span><span class="qt-stat-lbl">Total</span></div>
    <div class="qt-stat blue"><span class="qt-stat-val">${counts.sent}</span><span class="qt-stat-lbl">Sent</span></div>
    <div class="qt-stat green"><span class="qt-stat-val">${counts.accepted}</span><span class="qt-stat-lbl">Accepted</span></div>
    <div class="qt-stat"><span class="qt-stat-val">${counts.draft}</span><span class="qt-stat-lbl">Draft</span></div>
    <div class="qt-stat" style="flex:2"><span class="qt-stat-val" style="font-size:16px">${totalVal.toFixed(2)}${cur}</span><span class="qt-stat-lbl">Total Value</span></div>
  </div>
  <div class="qt-tbl-wrap"><table class="qt-tbl">
    <thead><tr>
      <th>Quote #</th><th>Customer</th><th>Date</th><th>Valid Until</th><th>Status</th>
      <th style="text-align:right">Amount</th><th style="width:28px"></th>
    </tr></thead>
    <tbody>`;

  _qt.list.forEach(q => {
    const date   = q.quote_date  ? new Date(q.quote_date  + 'T00:00:00').toLocaleDateString(undefined, { month:'short', day:'numeric', year:'numeric' }) : '—';
    const expiry = q.expiry_date ? new Date(q.expiry_date + 'T00:00:00').toLocaleDateString(undefined, { month:'short', day:'numeric', year:'numeric' }) : '—';
    html += `<tr data-qt-id="${q.id}">
      <td><span class="qt-tbl-num">${escHtml(q.quote_number || String(q.id))}</span></td>
      <td class="qt-tbl-customer">${escHtml(q.customer_name || 'Walk-in')}</td>
      <td class="qt-tbl-muted">${date}</td>
      <td class="qt-tbl-muted">${expiry}</td>
      <td><span class="qt-badge qt-badge-${q.status}">${escHtml(q.status_label)}</span></td>
      <td class="qt-tbl-amt">${parseFloat(q.total || 0).toFixed(2)}${cur}</td>
      <td class="qt-tbl-chevron"><i class="fa fa-chevron-right"></i></td>
    </tr>`;
  });

  html += '</tbody></table></div>';
  $('#qt-list-body').innerHTML = html;
  $$('#qt-list-body tr[data-qt-id]').forEach(row => {
    row.addEventListener('click', () => _qtOpenDetail(Number(row.dataset.qtId)));
  });
}

// ── Detail ────────────────────────────────────────────────────────────────
async function _qtOpenDetail(id) {
  _qt.activeId = id;
  _qtShowView('detail');
  $('#qt-detail-title').textContent = 'Loading…';
  $('#qt-detail-body').innerHTML    = '<div class="qt-loading"><i class="fa fa-spinner fa-spin"></i></div>';
  $('#qt-detail-actions').innerHTML = '';

  const res = await API.quotation(id);
  if (res.status !== 200) {
    $('#qt-detail-body').innerHTML = '<div class="qt-empty"><i class="fa fa-circle-exclamation"></i> Failed to load.</div>';
    return;
  }

  const q   = res.body?.data;
  const cur = state.currency ? ' ' + state.currency : '';
  $('#qt-detail-title').textContent = q.quote_number;

  // Actions in topbar
  let acts = '';
  if (q.is_editable) {
    acts += `<button class="qt-act-btn" id="qd-edit"><i class="fa fa-pen"></i> Edit</button>`;
    if (q.status === 'draft') acts += `<button class="qt-act-btn" id="qd-sent"><i class="fa fa-paper-plane"></i> Mark Sent</button>`;
    if (q.status === 'sent')  acts += `<button class="qt-act-btn accent" id="qd-accept"><i class="fa fa-circle-check"></i> Accept</button>`;
    if (q.status !== 'rejected') acts += `<button class="qt-act-btn" id="qd-reject"><i class="fa fa-circle-xmark"></i> Reject</button>`;
  }
  acts += `<button class="qt-act-btn" id="qd-print"><i class="fa fa-print"></i> Print</button>`;
  acts += `<button class="qt-act-btn danger" id="qd-delete"><i class="fa fa-trash"></i> Delete</button>`;
  $('#qt-detail-actions').innerHTML = acts;

  // Document body
  const dateStr   = q.quote_date  ? new Date(q.quote_date  + 'T00:00:00').toLocaleDateString(undefined, { month:'long', day:'numeric', year:'numeric' }) : '—';
  const expiryStr = q.expiry_date ? new Date(q.expiry_date + 'T00:00:00').toLocaleDateString(undefined, { month:'long', day:'numeric', year:'numeric' }) : '—';

  const itemRows = (q.items || []).map((item, idx) => `<tr>
    <td class="qt-item-n">${idx + 1}</td>
    <td>${escHtml(item.description || '—')}</td>
    <td class="td-r">${parseFloat(item.quantity) % 1 === 0 ? parseInt(item.quantity) : parseFloat(item.quantity).toFixed(2)}</td>
    <td class="td-r">${parseFloat(item.unit_price).toFixed(2)}${cur}</td>
    <td class="td-r qt-item-total">${parseFloat(item.line_total).toFixed(2)}${cur}</td>
  </tr>`).join('');

  const body = `<div class="qt-doc">
    <div class="qt-doc-banner">
      <div class="qt-doc-banner-left">
        <div class="qt-doc-banner-num">${escHtml(q.quote_number)}</div>
        <div class="qt-doc-banner-type">Quotation</div>
      </div>
      <span class="qt-badge qt-badge-${q.status}">${escHtml(q.status_label)}</span>
    </div>
    <div class="qt-doc-body">
      <div class="qt-doc-info">
        <div>
          <div class="qt-doc-bill-label">Bill To</div>
          <div class="qt-doc-bill-name">${escHtml(q.customer_name || 'Walk-in Customer')}</div>
          ${q.reference ? `<div class="qt-doc-bill-ref"><i class="fa fa-hashtag" style="font-size:9px;opacity:.6"></i> ${escHtml(q.reference)}</div>` : ''}
        </div>
        <div class="qt-doc-meta">
          <div class="qt-doc-meta-row"><span>Quote Date</span><strong>${dateStr}</strong></div>
          <div class="qt-doc-meta-row"><span>Valid Until</span><strong>${expiryStr}</strong></div>
        </div>
      </div>

      <div class="qt-doc-items-wrap">
        <table class="qt-doc-items">
          <thead><tr>
            <th style="width:32px">#</th>
            <th>Description</th>
            <th class="td-r" style="width:64px">Qty</th>
            <th class="td-r" style="width:120px">Unit Price</th>
            <th class="td-r" style="width:120px">Total</th>
          </tr></thead>
          <tbody>${itemRows}</tbody>
        </table>
      </div>

      <div class="qt-doc-totals-wrap">
        <div class="qt-doc-totals">
          <div class="qt-doc-total-line"><span>Subtotal</span><span>${q.subtotal.toFixed(2)}${cur}</span></div>
          ${q.discount_amount > 0 ? `<div class="qt-doc-total-line"><span>Discount</span><span>-${q.discount_amount.toFixed(2)}${cur}</span></div>` : ''}
          ${q.tax_amount > 0      ? `<div class="qt-doc-total-line"><span>Tax</span><span>+${q.tax_amount.toFixed(2)}${cur}</span></div>` : ''}
          <div class="qt-doc-total-line grand"><span>Total</span><span>${q.total.toFixed(2)}${cur}</span></div>
        </div>
      </div>

      ${q.notes ? `<hr class="qt-doc-divider">
      <div class="qt-doc-notes-label"><i class="fa fa-note-sticky"></i> Notes</div>
      <div class="qt-doc-notes-box">${escHtml(q.notes)}</div>` : ''}
    </div>
  </div>`;

  $('#qt-detail-body').innerHTML = body;

  // Wire action buttons
  $('#qd-print')?.addEventListener('click', () => _qtPrint(q));
  $('#qd-edit')?.addEventListener('click', () => _qtOpenForm(q));
  $('#qd-sent')?.addEventListener('click', async () => {
    const r = await API.quotationSent(q.id);
    if (r.status === 200) { toast(`Marked as sent`, 'success'); _qtOpenDetail(q.id); } else toast(r.body?.message || 'Error', 'error');
  });
  $('#qd-accept')?.addEventListener('click', async () => {
    const r = await API.quotationAccept(q.id);
    if (r.status === 200) { toast(`Quotation accepted`, 'success'); _qtOpenDetail(q.id); } else toast(r.body?.message || 'Error', 'error');
  });
  $('#qd-reject')?.addEventListener('click', async () => {
    const r = await API.quotationReject(q.id);
    if (r.status === 200) { toast(`Quotation rejected`, 'info'); _qtOpenDetail(q.id); } else toast(r.body?.message || 'Error', 'error');
  });
  $('#qd-delete')?.addEventListener('click', async () => {
    if (!confirm(`Delete ${q.quote_number}?`)) return;
    const r = await API.deleteQuotation(q.id);
    if (r.status === 200) { toast('Quotation deleted', 'info'); loadQuotesList(); }
    else toast(r.body?.message || 'Cannot delete', 'error');
  });
}

// ── Print (with optional letterhead) ─────────────────────────────────────
async function _qtPrint(q) {
  // Look for a letterhead design that has canvas content
  let lhFull = null;
  const lhStub = _dsAllData.find(d => d.type === 'letterhead');
  if (lhStub && lhStub.has_canvas) {
    const res = await API.design(lhStub.id);
    if (res.status === 200 && res.body?.data) lhFull = res.body.data;
  }
  await window.electronAPI.openQuotePrint({ quote: q, letterhead: lhFull, currency: state.currency });
}

// ── Form (Create / Edit) ──────────────────────────────────────────────────
async function _qtOpenForm(existing) {
  _qt.editingId  = existing?.id ?? null;
  _qt.lineSeq    = 0;
  _qtShowView('form');
  $('#qt-form-title').textContent = existing ? `Edit ${existing.quote_number}` : 'New Quotation';
  $('#qt-form-alert').style.display = 'none';

  // Load customers if needed
  if (!_qt.customers.length) {
    const cr = await API.customers('', 1);
    _qt.customers = cr.body?.data || [];
  }

  // Populate customer select
  const custSel = $('#qt-f-customer');
  custSel.innerHTML = '<option value="">— Walk-in —</option>';
  _qt.customers.forEach(c => {
    const opt = document.createElement('option');
    opt.value       = c.id;
    opt.textContent = c.name;
    custSel.appendChild(opt);
  });

  const today = new Date().toISOString().slice(0, 10);
  if (existing) {
    custSel.value             = existing.customer_id || '';
    $('#qt-f-ref').value      = existing.reference   || '';
    $('#qt-f-date').value     = existing.quote_date  || today;
    $('#qt-f-expiry').value   = existing.expiry_date || '';
    $('#qt-f-notes').value    = existing.notes       || '';
    $('#qt-f-discount').value = existing.discount_amount > 0 ? existing.discount_amount : '';
    $('#qt-f-tax').value      = existing.tax_amount      > 0 ? existing.tax_amount      : '';
  } else {
    custSel.value             = '';
    $('#qt-f-ref').value      = '';
    $('#qt-f-date').value     = today;
    $('#qt-f-expiry').value   = '';
    $('#qt-f-notes').value    = '';
    $('#qt-f-discount').value = '';
    $('#qt-f-tax').value      = '';
  }

  // Line items
  $('#qt-items-body').innerHTML = '';
  if (existing?.items?.length) {
    existing.items.forEach(i => _qtAddLine(i.description, i.quantity, i.unit_price));
  }
  _qtRecalc();
}

function _qtAddLine(desc = '', qty = 1, price = 0) {
  const id  = ++_qt.lineSeq;
  const row = document.createElement('div');
  row.className   = 'qt-line-row';
  row.dataset.lid = id;
  row.innerHTML = `
    <div class="qt-line-desc">
      <input type="text" class="qt-line-input qt-line-desc-input" placeholder="Description or search product…" value="${escHtml(desc)}" data-lid="${id}">
      <div class="qt-product-suggest" id="qt-sug-${id}"></div>
    </div>
    <input type="number" class="qt-line-input qt-line-num" min="0.001" step="any" value="${qty}" data-lid="${id}" data-role="qty">
    <input type="number" class="qt-line-input qt-line-price" min="0" step="any" value="${price || ''}" data-lid="${id}" data-role="price" placeholder="0.00">
    <div class="qt-line-total" id="qt-lt-${id}">${(qty * price).toFixed(2)}</div>
    <button class="qt-line-del" data-lid="${id}"><i class="fa fa-xmark"></i></button>`;
  $('#qt-items-body').appendChild(row);

  // Product search on desc input
  const descInp = row.querySelector('.qt-line-desc-input');
  const sug     = row.querySelector('.qt-product-suggest');
  let sugTimer;
  descInp.addEventListener('input', () => {
    clearTimeout(sugTimer);
    const q = descInp.value.trim();
    if (q.length < 2) { sug.style.display = 'none'; sug.innerHTML = ''; return; }
    sugTimer = setTimeout(async () => {
      const r = await API.productSearch(q, 8);
      const items = r.body?.data || [];
      if (!items.length) { sug.style.display = 'none'; return; }
      sug.innerHTML = items.map(p => `<div class="qt-suggest-item" data-pid="${p.id}" data-name="${escHtml(p.name)}" data-price="${p.unit_sell_price ?? 0}">
        ${escHtml(p.name)}<span class="qt-suggest-sku">${p.sku ? escHtml(p.sku) : ''}</span>
      </div>`).join('');
      sug.style.display = '';
      sug.querySelectorAll('.qt-suggest-item').forEach(el => {
        el.addEventListener('click', () => {
          descInp.value = el.dataset.name;
          row.querySelector('[data-role="price"]').value = parseFloat(el.dataset.price || 0).toFixed(2);
          sug.style.display = 'none';
          _qtRecalc();
        });
      });
    }, 200);
  });
  descInp.addEventListener('blur', () => setTimeout(() => { sug.style.display = 'none'; }, 200));

  row.querySelector('.qt-line-del').addEventListener('click', () => { row.remove(); _qtRecalc(); });
  row.querySelectorAll('[data-role="qty"],[data-role="price"]').forEach(inp => inp.addEventListener('input', _qtRecalc));
}

function _qtRecalc() {
  let subtotal = 0;
  $$('#qt-items-body .qt-line-row').forEach(row => {
    const qty   = parseFloat(row.querySelector('[data-role="qty"]')?.value   || 0);
    const price = parseFloat(row.querySelector('[data-role="price"]')?.value || 0);
    const lt    = isNaN(qty) || isNaN(price) ? 0 : qty * price;
    const lid   = row.dataset.lid;
    const ltEl  = $(`#qt-lt-${lid}`);
    if (ltEl) ltEl.textContent = lt.toFixed(2);
    subtotal += lt;
  });
  const discount = parseFloat($('#qt-f-discount').value) || 0;
  const tax      = parseFloat($('#qt-f-tax').value)      || 0;
  const total    = Math.max(0, subtotal - discount + tax);
  $('#qt-subtotal').textContent   = subtotal.toFixed(2);
  $('#qt-grand-total').textContent = total.toFixed(2);
}

$('#qt-add-line').addEventListener('click', () => _qtOpenAddModal());
$('#qt-f-discount').addEventListener('input', _qtRecalc);
$('#qt-f-tax').addEventListener('input', _qtRecalc);

// ── Quotation: Add Product Modal ──────────────────────────────────
const _qtMod = { product: null, timer: null };

function _qtOpenAddModal() {
  _qtMod.product = null;
  $('#qt-add-modal-q').value = '';
  $('#qt-add-modal-list').innerHTML = '<div class="qt-add-modal-empty"><i class="fa fa-magnifying-glass"></i><p>Type to search products</p></div>';
  $('#qt-add-modal-bottom').style.display = 'none';
  $('#qt-add-modal-add').style.display = 'none';
  $('#qt-add-modal-qty').value = '1';
  $('#qt-add-modal-price').value = '';
  $('#qt-add-modal-disc').value = '0';
  $('#qt-add-modal-total').textContent = '0.00';
  $('#qt-add-overlay').style.display = '';
  setTimeout(() => $('#qt-add-modal-q').focus(), 60);
}

function _qtCloseAddModal() {
  $('#qt-add-overlay').dataset.invMode = '';
  $('#qt-add-overlay').style.display = 'none';
}

async function _qtModalSearch(q) {
  const list = $('#qt-add-modal-list');
  if (!q) {
    list.innerHTML = '<div class="qt-add-modal-empty"><i class="fa fa-magnifying-glass"></i><p>Type to search products</p></div>';
    return;
  }
  list.innerHTML = '<div class="qt-add-modal-empty"><i class="fa fa-spinner fa-spin"></i><p>Searching…</p></div>';
  const res = await API.productSearch(q, 20);
  const items = res.body?.data || [];
  if (!items.length) {
    list.innerHTML = '<div class="qt-add-modal-empty"><i class="fa fa-box-open"></i><p>No products found</p></div>';
    return;
  }
  list.innerHTML = items.map(p => {
    const stock   = parseFloat(p.total_stock ?? p.quantity_on_hand ?? 0);
    const inStock = stock > 0;
    const price   = parseFloat(p.unit_sell_price ?? 0);
    return `<div class="qt-add-modal-product" data-name="${escHtml(p.name)}" data-price="${price}">
      <div class="qt-prod-ico"><i class="fa fa-box"></i></div>
      <div class="qt-prod-nfo">
        <div class="qt-prod-nm">${escHtml(p.name)}</div>
        <div class="qt-prod-sub">
          ${p.sku ? `<span>${escHtml(p.sku)}</span>` : ''}
          <span class="qt-prod-stk ${inStock ? 'in' : 'out'}">${inStock ? stock + ' in stock' : 'Out of stock'}</span>
        </div>
      </div>
      <div class="qt-prod-prc">${price.toFixed(2)}</div>
    </div>`;
  }).join('');
  list.querySelectorAll('.qt-add-modal-product').forEach(el => {
    el.addEventListener('click', () => {
      list.querySelectorAll('.qt-add-modal-product').forEach(e => e.classList.remove('sel'));
      el.classList.add('sel');
      _qtMod.product = { name: el.dataset.name, price: parseFloat(el.dataset.price || 0) };
      $('#qt-add-modal-price').value = _qtMod.product.price.toFixed(2);
      $('#qt-add-modal-disc').value  = '0';
      $('#qt-add-modal-qty').value   = '1';
      $('#qt-add-modal-sel-name').textContent = _qtMod.product.name;
      $('#qt-add-modal-bottom').style.display = '';
      $('#qt-add-modal-add').style.display    = '';
      _qtModalRecalc();
    });
  });
}

function _qtModalRecalc() {
  const qty   = parseFloat($('#qt-add-modal-qty').value)   || 0;
  const price = parseFloat($('#qt-add-modal-price').value) || 0;
  const disc  = parseFloat($('#qt-add-modal-disc').value)  || 0;
  $('#qt-add-modal-total').textContent = Math.max(0, qty * price - disc).toFixed(2);
}

$('#qt-add-modal-close').addEventListener('click', _qtCloseAddModal);
$('#qt-add-modal-cancel').addEventListener('click', _qtCloseAddModal);
$('#qt-add-overlay').addEventListener('click', e => { if (e.target === $('#qt-add-overlay')) _qtCloseAddModal(); });
document.addEventListener('keydown', e => {
  if (e.key === 'Escape' && $('#qt-add-overlay').style.display !== 'none') _qtCloseAddModal();
});
$('#qt-add-modal-q').addEventListener('input', () => {
  clearTimeout(_qtMod.timer);
  _qtMod.timer = setTimeout(() => _qtModalSearch($('#qt-add-modal-q').value.trim()), 220);
});
['#qt-add-modal-qty', '#qt-add-modal-price', '#qt-add-modal-disc'].forEach(sel => {
  $(sel).addEventListener('input', _qtModalRecalc);
});
$('#qt-add-modal-add').addEventListener('click', () => {
  if (!_qtMod.product) return;
  const qty   = parseFloat($('#qt-add-modal-qty').value)   || 1;
  const price = parseFloat($('#qt-add-modal-price').value) || 0;
  const disc  = parseFloat($('#qt-add-modal-disc').value)  || 0;
  const effectivePrice = qty > 0 ? Math.max(0, (qty * price - disc) / qty) : price;
  _qtAddLine(_qtMod.product.name, qty, effectivePrice);
  _qtCloseAddModal();
  _qtRecalc();
});

// Save
$('#qt-form-save').addEventListener('click', async () => {
  const btn   = $('#qt-form-save');
  const alert = $('#qt-form-alert');
  alert.style.display = 'none';

  // Build items
  const items = [];
  $$('#qt-items-body .qt-line-row').forEach(row => {
    const desc  = row.querySelector('.qt-line-desc-input')?.value.trim() || '';
    const qty   = parseFloat(row.querySelector('[data-role="qty"]')?.value   || 0);
    const price = parseFloat(row.querySelector('[data-role="price"]')?.value || 0);
    if (!desc && qty <= 0 && price <= 0) return;
    items.push({ item_type: 'custom', description: desc, quantity: qty || 1, unit_price: price });
  });

  if (!items.length) {
    alert.textContent = 'Add at least one line item.';
    alert.className   = 'alert alert-danger';
    alert.style.display = '';
    return;
  }

  const body = {
    customer_id:     $('#qt-f-customer').value || null,
    reference:       $('#qt-f-ref').value.trim() || null,
    quote_date:      $('#qt-f-date').value,
    expiry_date:     $('#qt-f-expiry').value || null,
    notes:           $('#qt-f-notes').value.trim() || null,
    discount_amount: parseFloat($('#qt-f-discount').value) || 0,
    tax_amount:      parseFloat($('#qt-f-tax').value)      || 0,
    items,
  };

  btn.disabled = true;
  btn.innerHTML = '<i class="fa fa-spinner fa-spin"></i> Saving…';

  const res = _qt.editingId
    ? await API.updateQuotation(_qt.editingId, body)
    : await API.createQuotation(body);

  btn.disabled = false;
  btn.innerHTML = '<i class="fa fa-floppy-disk"></i> Save Quotation';

  if (res.status >= 400) {
    const msg = res.body?.errors ? Object.values(res.body.errors).flat().join(' ') : (res.body?.message || 'Save failed.');
    alert.textContent    = msg;
    alert.className      = 'alert alert-danger';
    alert.style.display  = '';
    return;
  }

  toast(res.body?.message || 'Saved.', 'success');
  const saved = res.body?.data;
  if (saved) {
    _qt.activeId = saved.id;
    _qtOpenDetail(saved.id);
  } else {
    loadQuotesList();
  }
});

// Form cancel
$('#qt-form-back').addEventListener('click', () => {
  if (_qt.activeId) _qtOpenDetail(_qt.activeId); else _qtShowView('list');
});
$('#qt-form-cancel').addEventListener('click', () => {
  if (_qt.activeId) _qtOpenDetail(_qt.activeId); else _qtShowView('list');
});

// Detail back
$('#qt-detail-back').addEventListener('click', () => { _qt.activeId = null; loadQuotesList(); });

// ── View switcher ─────────────────────────────────────────────────────────
function _qtShowView(view) {
  // Ensure the parent quotes view is visible inside the Sales panel
  if ($('#sal-quotes-view').style.display === 'none') {
    _salSwitchView('quotes');
    return; // _salSwitchView → loadQuotesList → _qtShowView('list')
  }
  $('#qt-list-view').style.display   = view === 'list'   ? ''     : 'none';
  $('#qt-detail-view').style.display = view === 'detail' ? 'flex' : 'none';
  $('#qt-form-view').style.display   = view === 'form'   ? 'flex' : 'none';
}

// ── Search & status filter ────────────────────────────────────────────────
$('#qt-search').addEventListener('input', e => {
  clearTimeout(_qtSearchTimer);
  _qt.search = e.target.value.trim();
  _qtSearchTimer = setTimeout(loadQuotesList, 280);
});

// Status chips — scoped to quotation chips only (data-qst), not invoice chips (data-ist)
$$('.qt-chip[data-qst]').forEach(btn => {
  btn.addEventListener('click', () => {
    $$('.qt-chip[data-qst]').forEach(b => b.classList.remove('active'));
    btn.classList.add('active');
    _qt.status = btn.dataset.qst;
    loadQuotesList();
  });
});

// Ribbon buttons (switch to quotes view first, then act)
$('#rb-qt-new')?.addEventListener('click', () => { _salSwitchView('quotes'); setTimeout(() => _qtOpenForm(null), 50); });
$('#rb-qt-refresh')?.addEventListener('click', () => { _salSwitchView('quotes'); });
$('#qt-new-btn').addEventListener('click', () => _qtOpenForm(null));
// ── End Quotations Panel ──────────────────────────────────────────────────

// ── Invoices Panel ────────────────────────────────────────────────────────
const _inv = { view: 'list', search: '', status: 'all', activeId: null, editingId: null };
let _invSearchTimer;

function _invShowView(v) {
  $('#sinv-list-view').style.display   = v === 'list'   ? '' : 'none';
  $('#sinv-detail-view').style.display = v === 'detail' ? 'flex' : 'none';
  $('#sinv-form-view').style.display   = v === 'form'   ? 'flex' : 'none';
  _inv.view = v;
}

async function loadInvoicesList() {
  _invShowView('list');
  $('#sinv-list-loading').style.display = '';
  $('#sinv-list-body').innerHTML = '';

  const res = await API.invoices(_inv.search, _inv.status);
  $('#sinv-list-loading').style.display = 'none';

  if (res.status !== 200) {
    $('#sinv-list-body').innerHTML = '<div class="qt-empty"><i class="fa fa-circle-exclamation"></i> Failed to load invoices.</div>';
    return;
  }

  const items = res.body?.data || [];
  if (!items.length) {
    $('#sinv-list-body').innerHTML = `<div class="qt-empty">
      <i class="fa fa-file-invoice"></i>
      <h4>No Invoices Found</h4>
      <p>Create your first invoice to get started.</p>
      <button class="qt-new-btn" id="sinv-empty-new" style="margin-top:14px"><i class="fa fa-file-circle-plus"></i> New Invoice</button>
    </div>`;
    $('#sinv-empty-new')?.addEventListener('click', () => _invOpenForm(null));
    return;
  }

  const cur = state.currency ? ' ' + state.currency : '';

  // KPI stats
  const counts = { draft: 0, sent: 0, paid: 0, overdue: 0, cancelled: 0 };
  let totalVal = 0;
  items.forEach(inv => {
    const key = inv.is_overdue && inv.status !== 'paid' && inv.status !== 'cancelled' ? 'overdue' : inv.status;
    if (counts[key] !== undefined) counts[key]++;
    totalVal += parseFloat(inv.total || 0);
  });

  let html = `<div class="qt-stats">
    <div class="qt-stat"><span class="qt-stat-val">${items.length}</span><span class="qt-stat-lbl">Total</span></div>
    <div class="qt-stat blue"><span class="qt-stat-val">${counts.sent}</span><span class="qt-stat-lbl">Sent</span></div>
    <div class="qt-stat green"><span class="qt-stat-val">${counts.paid}</span><span class="qt-stat-lbl">Paid</span></div>
    <div class="qt-stat" style="--val-col:#ef4444"><span class="qt-stat-val" style="color:#ef4444">${counts.overdue}</span><span class="qt-stat-lbl">Overdue</span></div>
    <div class="qt-stat" style="flex:2"><span class="qt-stat-val" style="font-size:16px">${totalVal.toFixed(2)}${cur}</span><span class="qt-stat-lbl">Total Value</span></div>
  </div>
  <div class="qt-tbl-wrap"><table class="qt-tbl">
    <thead><tr>
      <th>Invoice #</th><th>Customer</th><th>Issue Date</th><th>Due Date</th><th>Status</th>
      <th style="text-align:right">Amount</th><th style="width:28px"></th>
    </tr></thead>
    <tbody>`;

  items.forEach(inv => {
    const isOverdue = inv.is_overdue && inv.status !== 'paid' && inv.status !== 'cancelled';
    const statusKey = isOverdue ? 'overdue' : inv.status;
    const issueDate = inv.issue_date ? new Date(inv.issue_date + 'T00:00:00').toLocaleDateString(undefined, { month:'short', day:'numeric', year:'numeric' }) : '—';
    const dueDate   = inv.due_date   ? new Date(inv.due_date   + 'T00:00:00').toLocaleDateString(undefined, { month:'short', day:'numeric', year:'numeric' }) : '—';
    const overdueClass = isOverdue ? 'style="color:#ef4444;font-weight:600"' : '';
    html += `<tr data-sinv-id="${inv.id}">
      <td><span class="qt-tbl-num">${escHtml(inv.invoice_number)}</span></td>
      <td class="qt-tbl-customer">${escHtml(inv.customer_name || 'Walk-in')}</td>
      <td class="qt-tbl-muted">${issueDate}</td>
      <td class="qt-tbl-muted" ${overdueClass}>${dueDate}</td>
      <td><span class="qt-badge qt-badge-${statusKey}">${escHtml(inv.status_label)}</span></td>
      <td class="qt-tbl-amt">${parseFloat(inv.total || 0).toFixed(2)}${cur}</td>
      <td class="qt-tbl-chevron"><i class="fa fa-chevron-right"></i></td>
    </tr>`;
  });

  html += '</tbody></table></div>';
  $('#sinv-list-body').innerHTML = html;

  $$('#sinv-list-body tr[data-sinv-id]').forEach(row => {
    row.addEventListener('click', () => _invOpenDetail(Number(row.dataset.sinvId)));
  });
}

async function _invOpenDetail(id) {
  _inv.activeId = id;
  _invShowView('detail');
  $('#sinv-detail-title').textContent = 'Loading…';
  $('#sinv-detail-body').innerHTML    = '<div class="qt-loading"><i class="fa fa-spinner fa-spin"></i></div>';
  $('#sinv-detail-actions').innerHTML = '';

  const res = await API.invoice(id);
  if (res.status !== 200) {
    $('#sinv-detail-body').innerHTML = '<div class="qt-empty"><i class="fa fa-circle-exclamation"></i> Failed to load.</div>';
    return;
  }

  const inv = res.body?.data;
  const cur  = state.currency ? ' ' + state.currency : '';
  $('#sinv-detail-title').textContent = inv.invoice_number;

  // Action buttons
  let acts = '';
  if (inv.is_editable) {
    acts += `<button class="qt-act-btn" id="invd-edit"><i class="fa fa-pen"></i> Edit</button>`;
  }
  if (inv.status === 'draft') {
    acts += `<button class="qt-act-btn" id="invd-sent"><i class="fa fa-paper-plane"></i> Mark Sent</button>`;
  }
  if (inv.status === 'sent' || inv.status === 'overdue') {
    acts += `<button class="qt-act-btn accent" id="invd-paid"><i class="fa fa-circle-check"></i> Mark Paid</button>`;
  }
  if (inv.status === 'sent') {
    acts += `<button class="qt-act-btn" id="invd-overdue"><i class="fa fa-circle-exclamation"></i> Mark Overdue</button>`;
  }
  if (inv.status !== 'paid' && inv.status !== 'cancelled') {
    acts += `<button class="qt-act-btn" id="invd-cancel"><i class="fa fa-ban"></i> Cancel</button>`;
  }
  acts += `<button class="qt-act-btn" id="invd-print"><i class="fa fa-print"></i> Print</button>`;
  if (inv.status !== 'paid') {
    acts += `<button class="qt-act-btn danger" id="invd-delete"><i class="fa fa-trash"></i> Delete</button>`;
  }
  $('#sinv-detail-actions').innerHTML = acts;

  const dateStr = inv.issue_date ? new Date(inv.issue_date + 'T00:00:00').toLocaleDateString(undefined, { month:'long', day:'numeric', year:'numeric' }) : '—';
  const dueStr  = inv.due_date   ? new Date(inv.due_date   + 'T00:00:00').toLocaleDateString(undefined, { month:'long', day:'numeric', year:'numeric' }) : '—';

  const itemRows = (inv.items || []).map((item, idx) => `<tr>
    <td class="qt-item-n">${idx + 1}</td>
    <td>${escHtml(item.description || '—')}</td>
    <td class="td-r">${parseFloat(item.quantity) % 1 === 0 ? parseInt(item.quantity) : parseFloat(item.quantity).toFixed(2)}</td>
    <td class="td-r">${parseFloat(item.unit_price).toFixed(2)}${cur}</td>
    <td class="td-r qt-item-total">${parseFloat(item.line_total).toFixed(2)}${cur}</td>
  </tr>`).join('');

  const body = `<div class="qt-doc">
    <div class="qt-doc-banner">
      <div class="qt-doc-banner-left">
        <div class="qt-doc-banner-num">${escHtml(inv.invoice_number)}</div>
        <div class="qt-doc-banner-type">Invoice</div>
      </div>
      <span class="qt-badge qt-badge-${inv.status}">${escHtml(inv.status_label)}</span>
    </div>
    <div class="qt-doc-body">
      <div class="qt-doc-info">
        <div>
          <div class="qt-doc-bill-label">Bill To</div>
          <div class="qt-doc-bill-name">${escHtml(inv.customer_name || 'Walk-in Customer')}</div>
          ${inv.reference ? `<div class="qt-doc-bill-ref"><i class="fa fa-hashtag" style="font-size:9px;opacity:.6"></i> ${escHtml(inv.reference)}</div>` : ''}
        </div>
        <div class="qt-doc-meta">
          <div class="qt-doc-meta-row"><span>Issue Date</span><strong>${dateStr}</strong></div>
          <div class="qt-doc-meta-row"><span>Due Date</span><strong>${dueStr}</strong></div>
        </div>
      </div>

      <div class="qt-doc-items-wrap">
        <table class="qt-doc-items">
          <thead><tr>
            <th style="width:32px">#</th>
            <th>Description</th>
            <th class="td-r" style="width:64px">Qty</th>
            <th class="td-r" style="width:120px">Unit Price</th>
            <th class="td-r" style="width:120px">Total</th>
          </tr></thead>
          <tbody>${itemRows}</tbody>
        </table>
      </div>

      <div class="qt-doc-totals-wrap">
        <div class="qt-doc-totals">
          <div class="qt-doc-total-line"><span>Subtotal</span><span>${parseFloat(inv.subtotal).toFixed(2)}${cur}</span></div>
          ${parseFloat(inv.discount_amount) > 0 ? `<div class="qt-doc-total-line"><span>Discount</span><span>-${parseFloat(inv.discount_amount).toFixed(2)}${cur}</span></div>` : ''}
          ${parseFloat(inv.tax_amount) > 0      ? `<div class="qt-doc-total-line"><span>Tax</span><span>+${parseFloat(inv.tax_amount).toFixed(2)}${cur}</span></div>` : ''}
          <div class="qt-doc-total-line grand"><span>Total</span><span>${parseFloat(inv.total).toFixed(2)}${cur}</span></div>
        </div>
      </div>

      ${inv.notes ? `<hr class="qt-doc-divider">
      <div class="qt-doc-notes-label"><i class="fa fa-note-sticky"></i> Notes</div>
      <div class="qt-doc-notes-box">${escHtml(inv.notes)}</div>` : ''}
    </div>
  </div>`;

  $('#sinv-detail-body').innerHTML = body;

  // Wire actions
  $('#invd-edit')?.addEventListener('click', () => _invOpenForm(inv));
  $('#invd-sent')?.addEventListener('click', async () => {
    const r = await API.invoiceSent(inv.id);
    if (r.status === 200) { toast('Marked as sent', 'success'); _invOpenDetail(inv.id); }
    else toast(r.body?.message || 'Error', 'error');
  });
  $('#invd-paid')?.addEventListener('click', async () => {
    const r = await API.invoicePaid(inv.id);
    if (r.status === 200) { toast('Invoice marked as paid', 'success'); _invOpenDetail(inv.id); }
    else toast(r.body?.message || 'Error', 'error');
  });
  $('#invd-overdue')?.addEventListener('click', async () => {
    const r = await API.invoiceOverdue(inv.id);
    if (r.status === 200) { toast('Marked as overdue', 'info'); _invOpenDetail(inv.id); }
    else toast(r.body?.message || 'Error', 'error');
  });
  $('#invd-cancel')?.addEventListener('click', async () => {
    if (!confirm(`Cancel invoice ${inv.invoice_number}?`)) return;
    const r = await API.invoiceCancel(inv.id);
    if (r.status === 200) { toast('Invoice cancelled', 'info'); _invOpenDetail(inv.id); }
    else toast(r.body?.message || 'Error', 'error');
  });
  $('#invd-print')?.addEventListener('click', () => _invPrint(inv));
  $('#invd-delete')?.addEventListener('click', async () => {
    if (!confirm(`Delete ${inv.invoice_number}?`)) return;
    const r = await API.deleteInvoice(inv.id);
    if (r.status === 200) { toast('Invoice deleted', 'info'); loadInvoicesList(); }
    else toast(r.body?.message || 'Error', 'error');
  });
}

// ── Invoice print (with optional letterhead) ──────────────────────────────
async function _invPrint(inv) {
  let lhFull = null;
  const lhStub = _dsAllData.find(d => d.type === 'letterhead');
  if (lhStub && lhStub.has_canvas) {
    const res = await API.design(lhStub.id);
    if (res.status === 200 && res.body?.data) lhFull = res.body.data;
  }
  await window.electronAPI.openQuotePrint({
    quote:      { ...inv, quote_number: inv.invoice_number, issue_date: inv.issue_date, due_date: inv.due_date, doc_type: 'Invoice' },
    letterhead: lhFull,
    currency:   state.currency,
  });
}

// ── Invoice form ──────────────────────────────────────────────────────────
let _invLineSeq = 0;

function _invAddLine(desc = '', qty = 1, price = 0) {
  const id  = ++_invLineSeq;
  const row = document.createElement('div');
  row.className      = 'qt-item-row';
  row.dataset.lineId = id;
  row.innerHTML = `
    <input type="text"   class="qt-item-desc"  placeholder="Description" value="${escHtml(desc)}">
    <input type="number" class="qt-item-qty"   value="${qty}" min="0.001" step="any">
    <input type="number" class="qt-item-price" value="${price.toFixed(2)}" min="0" step="any">
    <span  class="qt-item-total-disp">0.00</span>
    <button class="qt-item-del" title="Remove"><i class="fa fa-xmark"></i></button>`;
  const recalc = () => {
    const q = parseFloat(row.querySelector('.qt-item-qty').value)   || 0;
    const p = parseFloat(row.querySelector('.qt-item-price').value) || 0;
    row.querySelector('.qt-item-total-disp').textContent = (q * p).toFixed(2);
    _invRecalc();
  };
  row.querySelector('.qt-item-qty').addEventListener('input', recalc);
  row.querySelector('.qt-item-price').addEventListener('input', recalc);
  row.querySelector('.qt-item-del').addEventListener('click', () => { row.remove(); _invRecalc(); });
  $('#sinv-items-body').appendChild(row);
  recalc();
}

function _invRecalc() {
  let sub = 0;
  $$('#sinv-items-body .qt-item-row').forEach(row => {
    sub += (parseFloat(row.querySelector('.qt-item-qty').value)   || 0)
         * (parseFloat(row.querySelector('.qt-item-price').value) || 0);
  });
  const disc = parseFloat($('#sinv-f-discount').value) || 0;
  const tax  = parseFloat($('#sinv-f-tax').value)      || 0;
  $('#sinv-subtotal').textContent    = sub.toFixed(2);
  $('#sinv-grand-total').textContent = Math.max(0, sub - disc + tax).toFixed(2);
}

async function _invOpenForm(existing) {
  _inv.editingId = existing?.id ?? null;
  _invLineSeq    = 0;
  _invShowView('form');
  $('#sinv-form-title').textContent    = existing ? `Edit ${existing.invoice_number}` : 'New Invoice';
  $('#sinv-form-alert').style.display  = 'none';
  $('#sinv-items-body').innerHTML      = '';
  $('#sinv-f-discount').value          = '';
  $('#sinv-f-tax').value               = '';
  $('#sinv-f-notes').value             = '';
  $('#sinv-f-ref').value               = '';
  $('#sinv-subtotal').textContent      = '0.00';
  $('#sinv-grand-total').textContent   = '0.00';
  $('#sinv-f-date').value              = existing?.issue_date || new Date().toISOString().slice(0, 10);
  $('#sinv-f-due').value               = existing?.due_date   || '';

  if (existing?.discount_amount) $('#sinv-f-discount').value = existing.discount_amount;
  if (existing?.tax_amount)      $('#sinv-f-tax').value      = existing.tax_amount;
  if (existing?.notes)           $('#sinv-f-notes').value    = existing.notes;
  if (existing?.reference)       $('#sinv-f-ref').value      = existing.reference;

  // Populate customer dropdown
  const sel = $('#sinv-f-customer');
  sel.innerHTML = '<option value="">— Walk-in —</option>';
  const custRes = await API.customers('', 1);
  if (custRes.status === 200) {
    (custRes.body?.data || []).forEach(c => {
      const opt = document.createElement('option');
      opt.value = c.id; opt.textContent = c.name;
      if (existing?.customer_id === c.id) opt.selected = true;
      sel.appendChild(opt);
    });
  }

  if (existing?.items?.length) {
    existing.items.forEach(i => _invAddLine(i.description, i.quantity, i.unit_price));
  }
  _invRecalc();
}

// ── Invoice Add Product Modal (shares qt-add-overlay) ────────────────────
function _invOpenAddModal() {
  $('#qt-add-modal-q').value = '';
  $('#qt-add-modal-list').innerHTML = '<div class="qt-add-modal-empty"><i class="fa fa-magnifying-glass"></i><p>Type to search products</p></div>';
  $('#qt-add-modal-bottom').style.display = 'none';
  $('#qt-add-modal-add').style.display    = 'none';
  $('#qt-add-modal-qty').value   = '1';
  $('#qt-add-modal-price').value = '';
  $('#qt-add-modal-disc').value  = '0';
  $('#qt-add-modal-total').textContent = '0.00';
  _qtMod.product = null; // reset so quotation bubble handler won't fire in inv mode
  $('#qt-add-overlay').dataset.invMode = '1';
  $('#qt-add-overlay').style.display = '';
  setTimeout(() => $('#qt-add-modal-q').focus(), 60);
}

// Capture-phase: fires before quotation bubble listener.
// stopImmediatePropagation() prevents the quotation handler when in inv mode.
$('#qt-add-modal-add').addEventListener('click', e => {
  if ($('#qt-add-overlay').dataset.invMode !== '1') return;
  e.stopImmediatePropagation();
  if (!_qtMod.product) return;
  const qty   = parseFloat($('#qt-add-modal-qty').value)   || 1;
  const price = parseFloat($('#qt-add-modal-price').value) || 0;
  const disc  = parseFloat($('#qt-add-modal-disc').value)  || 0;
  const effectivePrice = qty > 0 ? Math.max(0, (qty * price - disc) / qty) : price;
  _invAddLine(_qtMod.product.name, qty, effectivePrice);
  $('#qt-add-overlay').dataset.invMode = '';
  $('#qt-add-overlay').style.display = 'none';
  _invRecalc();
}, true);

$('#sinv-add-line').addEventListener('click', () => _invOpenAddModal());
$('#sinv-f-discount').addEventListener('input', _invRecalc);
$('#sinv-f-tax').addEventListener('input', _invRecalc);

$('#sinv-form-save').addEventListener('click', async () => {
  const btn   = $('#sinv-form-save');
  const lines = [];
  $$('#sinv-items-body .qt-item-row').forEach(row => {
    const qty   = parseFloat(row.querySelector('.qt-item-qty').value)   || 0;
    const price = parseFloat(row.querySelector('.qt-item-price').value) || 0;
    const desc  = row.querySelector('.qt-item-desc').value.trim();
    if (qty > 0 || price > 0 || desc) {
      lines.push({ item_type: 'custom', description: desc, quantity: qty, unit_price: price });
    }
  });

  if (!lines.length) {
    $('#sinv-form-alert').textContent = 'Add at least one line item.';
    $('#sinv-form-alert').style.display = '';
    return;
  }

  $('#sinv-form-alert').style.display = 'none';
  btn.disabled = true;
  btn.innerHTML = '<i class="fa fa-spinner fa-spin"></i> Saving…';

  const body = {
    customer_id:     parseInt($('#sinv-f-customer').value) || null,
    reference:       $('#sinv-f-ref').value.trim()    || null,
    issue_date:      $('#sinv-f-date').value,
    due_date:        $('#sinv-f-due').value            || null,
    notes:           $('#sinv-f-notes').value.trim()   || null,
    discount_amount: parseFloat($('#sinv-f-discount').value) || 0,
    tax_amount:      parseFloat($('#sinv-f-tax').value)      || 0,
    items:           lines,
  };

  const res = _inv.editingId
    ? await API.updateInvoice(_inv.editingId, body)
    : await API.createInvoice(body);

  btn.disabled = false;
  btn.innerHTML = '<i class="fa fa-floppy-disk"></i> Save Invoice';

  if (res.status === 200 || res.status === 201) {
    const saved = res.body?.data;
    toast(res.status === 201 ? `Invoice ${saved.invoice_number} created` : 'Invoice updated', 'success');
    _inv.activeId = saved.id;
    _invOpenDetail(saved.id);
  } else {
    $('#sinv-form-alert').textContent = res.body?.message || 'Failed to save invoice.';
    $('#sinv-form-alert').style.display = '';
  }
});

$('#sinv-form-back').addEventListener('click', () => {
  if (_inv.activeId) _invOpenDetail(_inv.activeId); else _invShowView('list');
});
$('#sinv-form-cancel').addEventListener('click', () => {
  if (_inv.activeId) _invOpenDetail(_inv.activeId); else _invShowView('list');
});
$('#sinv-detail-back').addEventListener('click', () => { _inv.activeId = null; loadInvoicesList(); });

$('#sinv-search').addEventListener('input', () => {
  _inv.search = $('#sinv-search').value.trim();
  clearTimeout(_invSearchTimer);
  _invSearchTimer = setTimeout(loadInvoicesList, 280);
});

$$('.sinv-chip').forEach(btn => {
  btn.addEventListener('click', () => {
    $$('.sinv-chip').forEach(b => b.classList.remove('active'));
    btn.classList.add('active');
    _inv.status = btn.dataset.ist;
    loadInvoicesList();
  });
});

$('#sinv-new-btn').addEventListener('click', () => _invOpenForm(null));
// ── End Invoices Panel ────────────────────────────────────────────────────

// ── Login flow ─────────────────────────────────────────────────────────────
async function init() {
  state.config = await window.electronAPI.getConfig();
  applyDarkMode(state.config.dark_mode || false);

  if (state.config.token && state.config.business_id) {
    showApp();
    return;
  }
  showLogin();
}

function showLogin() {
  window.electronAPI.narrowAuth?.();
  const body = $('#login-body');
  if (body) { body.style.display = ''; body.style.padding = ''; }
  $('#login-screen').style.display = 'flex';
  $('#app-shell').style.display = 'none';
  $('#signin-card').style.display = '';
  $('#signup-card').style.cssText = 'display:none';
  $('#login-step-1').style.display = '';
  $('#login-step-2').style.display = 'none';
  $('#login-alert').style.display = 'none';
  $('#login-email').value = '';
  $('#login-password').value = '';
  $('#login-email').focus();
}

let _obStep = 1;
let _obSelectedCat = '';
let _obFeatureSet  = new Set(['point_of_sale', 'product_management', 'stock_management']);

const _obCatIcons = {
  education:              'fa-graduation-cap',
  software_industry:      'fa-laptop-code',
  local_retail:           'fa-store',
  food_beverage:          'fa-utensils',
  healthcare:             'fa-heart-pulse',
  finance:                'fa-chart-pie',
  creative_media:         'fa-palette',
  ecommerce:              'fa-cart-shopping',
  manufacturing:          'fa-industry',
  real_estate:            'fa-building',
  nonprofit:              'fa-hand-holding-heart',
  professional_services:  'fa-briefcase',
  other:                  'fa-ellipsis',
};

const _obCatColors = {
  education:              '#7c3aed',
  software_industry:      '#0ea5e9',
  local_retail:           '#f59e0b',
  food_beverage:          '#ef4444',
  healthcare:             '#10b981',
  finance:                '#3b82f6',
  creative_media:         '#ec4899',
  ecommerce:              '#8b5cf6',
  manufacturing:          '#64748b',
  real_estate:            '#14b8a6',
  nonprofit:              '#22c55e',
  professional_services:  '#6366f1',
  other:                  '#9ca3af',
};

const _obDefaultCats = [
  { value: 'education',             label: 'Education' },
  { value: 'software_industry',     label: 'Software Industry' },
  { value: 'local_retail',          label: 'Local Retail' },
  { value: 'food_beverage',         label: 'Food & Beverage' },
  { value: 'healthcare',            label: 'Healthcare' },
  { value: 'finance',               label: 'Finance' },
  { value: 'creative_media',        label: 'Creative & Media' },
  { value: 'ecommerce',             label: 'E-Commerce' },
  { value: 'manufacturing',         label: 'Manufacturing' },
  { value: 'real_estate',           label: 'Real Estate' },
  { value: 'nonprofit',             label: 'Non-Profit' },
  { value: 'professional_services', label: 'Professional Services' },
  { value: 'other',                 label: 'Other' },
];

const _obStepSubs = {
  1: 'Your login credentials',
  2: 'About you & your business',
  3: "What's your industry?",
  4: 'Which features do you need?',
};

const _obFeatureDefs = [
  { key: 'point_of_sale',         img: 'img/features/point-of-sale.png',              name: 'Point of Sale',        desc: 'Sell products at a physical counter',  color: '#f59e0b' },
  { key: 'product_management',    img: 'img/features/product-management.svg',         name: 'Product Management',   desc: 'Manage products, variants & pricing',  color: '#3b82f6' },
  { key: 'stock_management',      img: 'img/features/stock-management.png',           name: 'Stock Management',     desc: 'Track inventory levels & movements',   color: '#0ea5e9' },
  { key: 'bill_management',       img: 'img/features/bill-management.png',            name: 'Bill Management',      desc: 'Record and pay supplier invoices',     color: '#ef4444' },
  { key: 'human_resources',       img: 'img/features/human-resource-management.png', name: 'Human Resources',      desc: 'Employees, payroll & departments',     color: '#8b5cf6' },
  { key: 'service_management',    img: 'img/features/service-management.svg',         name: 'Services',             desc: 'Manage appointments & service jobs',   color: '#10b981' },
  { key: 'social_media_campaign', img: 'img/features/social-media-campaign.png',      name: 'Social Campaigns',     desc: 'Create and schedule ad creatives',     color: '#ec4899' },
];

function _obBuildCatGrid(cats) {
  const grid = $('#ob-cat-grid');
  grid.innerHTML = cats.map(o => {
    const iconCls = _obCatIcons[o.value] || 'fa-briefcase';
    const color   = _obCatColors[o.value] || '#4e8ef7';
    return `<div class="ob-cat-card" data-cat="${escHtml(o.value)}" style="--cat-color:${color}">
      <div class="ob-cat-icon"><i class="fa ${escHtml(iconCls)}"></i></div>
      <span class="ob-cat-name">${escHtml(o.label)}</span>
    </div>`;
  }).join('');
  grid.querySelectorAll('.ob-cat-card').forEach(card => {
    card.addEventListener('click', () => {
      _obSelectedCat = card.dataset.cat;
      grid.querySelectorAll('.ob-cat-card').forEach(c => c.classList.remove('active'));
      card.classList.add('active');
    });
  });
}

function _obBuildFeatureList() {
  const list = $('#ob-feature-list');
  list.innerHTML = _obFeatureDefs.map(f => {
    const active = _obFeatureSet.has(f.key) ? ' active' : '';
    return `<div class="ob-feat-card${active}" data-fkey="${f.key}" style="--feat-color:${f.color}">
      <div class="ob-feat-check"><i class="fa fa-check"></i></div>
      <div class="ob-feat-img-wrap">
        <img src="${escHtml(f.img)}" alt="${escHtml(f.name)}" class="ob-feat-img">
      </div>
      <div class="ob-feat-name">${escHtml(f.name)}</div>
      <div class="ob-feat-desc">${escHtml(f.desc)}</div>
      <div class="ob-feat-toggle">
        <div class="ob-toggle"><div class="ob-toggle-thumb"></div></div>
      </div>
    </div>`;
  }).join('');
  list.querySelectorAll('.ob-feat-card').forEach(card => {
    card.addEventListener('click', () => {
      const key = card.dataset.fkey;
      if (_obFeatureSet.has(key)) { _obFeatureSet.delete(key); card.classList.remove('active'); }
      else                        { _obFeatureSet.add(key);    card.classList.add('active'); }
    });
  });
}

function _obSetStep(n) {
  _obStep = n;
  $('#ob-step-num').textContent = String(n);

  // Update sidebar step circles and vertical lines
  $$('[data-ob-dot]').forEach(d => {
    const dotN = Number(d.dataset.obDot);
    d.classList.toggle('active', dotN === n);
    d.classList.toggle('done',   dotN < n);
  });
  $$('[data-ob-vline]').forEach(line => {
    const lineN = Number(line.dataset.obVline);
    line.classList.toggle('filled', lineN < n);
  });

  // Show/hide panels
  ['1','2','3','4'].forEach(i => {
    const p = $(`#ob-panel-${i}`);
    if (p) p.style.display = (String(n) === i) ? '' : 'none';
  });

  // Show/hide bottom-bar back buttons
  const backIds = { 2: 'ob-back-2-btn', 3: 'ob-back-3-btn', 4: 'ob-back-4-btn' };
  Object.values(backIds).forEach(id => { const el = $(`#${id}`); if (el) el.style.display = 'none'; });
  if (backIds[n]) { const el = $(`#${backIds[n]}`); if (el) el.style.display = ''; }

  // Show/hide bottom-bar next/submit buttons
  const nextIds = { 1:'ob-next-btn', 2:'ob-next-2-btn', 3:'ob-next-3-btn', 4:'signup-btn' };
  Object.values(nextIds).forEach(id => { const el = $(`#${id}`); if (el) el.style.display = 'none'; });
  { const el = $(`#${nextIds[n]}`); if (el) el.style.display = ''; }

  // Update progress bar (hidden but JS still sets it)
  const pb = $('#ob-progress-bar');
  if (pb) pb.style.width = `${n * 25}%`;

  $('#signup-alert').style.display = 'none';
  const focusMap = { 1: '#su-email', 2: '#su-name' };
  const focusSel = focusMap[n];
  if (focusSel) setTimeout(() => $(focusSel)?.focus(), 50);
}

function showSignup() {
  window.electronAPI.wideAuth?.();
  const body = $('#login-body');
  if (body) { body.style.display = 'block'; body.style.padding = '0'; }
  $('#signin-card').style.display = 'none';
  $('#signup-card').style.cssText = 'display:grid; width:100%; height:100%';
  $('#su-email').value    = '';
  $('#su-password').value = '';
  $('#su-name').value     = '';
  $('#su-biz').value      = '';
  _obSelectedCat = '';
  _obFeatureSet  = new Set(['point_of_sale', 'product_management', 'stock_management']);
  _obBuildFeatureList();
  _obSetStep(1);
}

// ── Business feature visibility ────────────────────────────────────────────
function hasFeature(key) {
  if (!state.features) return true;
  return state.features.has(key);
}

function applyFeatureVisibility() {
  // Helper: hide/show the ribbon-group that contains a given button
  const grp = (sel, show) => {
    const g = $(sel)?.closest('.ribbon-group');
    if (g) g.style.display = show ? '' : 'none';
  };

  const pos   = hasFeature('point_of_sale');
  const prod  = hasFeature('product_management');
  const stock = hasFeature('stock_management');
  const bill  = hasFeature('bill_management');
  const hr    = hasFeature('human_resources');
  const svc   = hasFeature('service_management');
  const camp  = hasFeature('social_media_campaign');

  // ── Ribbon tabs ──
  const tabFeatures = {
    pos:       pos || svc,
    sales:     pos,
    inventory: prod || stock,
    finance:   bill,
    hr:        hr,
    design:    camp,
  };
  $$('.ribbon-tab[data-tab]').forEach(tab => {
    const show = tabFeatures[tab.dataset.tab];
    tab.style.display = (show === undefined || show) ? '' : 'none';
  });

  // ── Home ribbon groups (Home tab is always visible) ──
  grp('#rb-home-pos',      pos || svc);             // Quick Actions
  // Overview group — always visible (no anchor needed, no change)
  grp('#rb-home-orders',   pos || prod || stock);   // Operations
  grp('#rb-home-expenses', bill || hr);             // Finance shortcuts
  // Tools group — always visible

  // ── Inventory ribbon groups ──
  grp('#rb-inv-products', prod);                    // Catalog
  grp('#rb-inv-audit',    prod || stock);           // Stock (brands/discounts + audit)
  grp('#rb-orders',       stock);                   // Purchasing (PO, GRN, cheques)
  grp('#rb-inv-suppliers',prod || stock);           // Suppliers
  grp('#rb-inv-barcodes', prod || stock);           // Print

  // ── Finance sub-nav: hide bill items when disabled ──
  const billFinViews = ['bills', 'loans', 'rentals', 'properties', 'modifications'];
  billFinViews.forEach(v => {
    const btn = $(`#panel-finance .fin-subnav-btn[data-fin="${v}"]`);
    if (btn) btn.style.display = bill ? '' : 'none';
  });
  if (!bill) {
    const activeFin = $('#panel-finance .fin-subnav-btn.active');
    if (activeFin && billFinViews.includes(activeFin.dataset.fin)) switchFinView('flow');
  }

  // ── Finance ribbon groups ──
  grp('#rb-create-bill', bill);   // Bills & Loans
  grp('#rb-rentals',     bill);   // Assets & Liabilities

  // ── Backstage sections ──
  const newFeat = {
    'POS & Sales':        [pos || svc],
    'Inventory':          [prod || stock],
    'Finance — Outgoing': [bill],
    'Human Resources':    [hr],
    'Design Studio':      [camp],
  };
  const openFeat = {
    'POS & Sales':     [pos || svc],
    'Inventory':       [prod || stock],
    'Finance':         [bill],
    'Human Resources': [hr],
    'Design Studio':   [camp],
  };
  const newBody = $('#bs-new-body');
  if (newBody) _buildSections(newBody, _bsNewSections.filter(s => newFeat[s.title]?.[0] !== false));
  const openBody = $('#bs-open-body');
  if (openBody) _buildSections(openBody, _bsOpenSections.filter(s => openFeat[s.title]?.[0] !== false));
}

async function loadFeatures() {
  const res = await API.features();
  if (res.status === 200) {
    state.features = new Set(res.body?.data || []);
  } else {
    state.features = null;
  }
  applyFeatureVisibility();
}

function showApp() {
  window.electronAPI.expandWindow?.();
  $('#login-screen').style.display = 'none';
  $('#app-shell').style.display = 'flex';
  if (state.config.branch_id) {
    $('#status-branch').innerHTML = `<i class="fa fa-code-branch"></i> Branch #${state.config.branch_id}`;
  }
  // Init first POS session tab if needed
  if (!state.posTabs.length) addPosTab();
  updateParkedBadge();
  // Start on Home tab; load products in background for POS
  loadProducts();
  // Give one frame for layout + title update before activating Home
  requestAnimationFrame(() => activateTab('home'));
  // Check if bank account onboarding is needed
  _checkBankOnboarding();
  // Load business + branch switchers
  _bizSwInit();
  // Apply feature-based tab and backstage visibility
  loadFeatures();
}

// ── Bank Account Setup Wizard ──────────────────────────────────────────────
const _bwz = { step: 1, banks: [], bankTypes: [] };

async function _checkBankOnboarding() {
  const res = await API.accounts();
  if (res.status !== 200) return;
  if ((res.body?.data || []).length === 0) _bankWizOpen();
}

async function _bankWizOpen() {
  _bwz.step = 1;
  // Reset all fields
  $('#bwz-f-name').value     = '';
  $('#bwz-f-category').value = 'operating';
  $('#bwz-f-acctno').value   = '';
  $('#bwz-f-branch').value   = '';
  $('#bwz-f-balance').value  = '0';
  $('#bwz-f-notes').value    = '';
  $('#bwz-f-bank-type').innerHTML = '<option value="">Loading…</option>';
  $('#bwz-f-bank').innerHTML      = '<option value="">Select a bank…</option>';

  // Show currency symbol from state if available
  const sym = state.currency ? state.currency.split(' ')[0] : '';
  $('#bwz-currency-prefix').textContent = sym || '0.00';

  _bwzSetStep(1);
  $('#bwz-overlay').style.display = 'flex';
  setTimeout(() => $('#bwz-f-name').focus(), 80);

  // Load bank types + banks in parallel
  const [btRes, bRes] = await Promise.all([API.bankTypes(), API.banks()]);
  if (btRes.status === 200) {
    _bwz.bankTypes = btRes.body?.data || [];
    $('#bwz-f-bank-type').innerHTML = _bwz.bankTypes
      .map(t => `<option value="${t.id}">${escHtml(t.name)}</option>`)
      .join('');
  }
  if (bRes.status === 200) {
    _bwz.banks = bRes.body?.data || [];
    $('#bwz-f-bank').innerHTML = '<option value="">Select a bank…</option>' +
      _bwz.banks.map(b => `<option value="${b.id}">${escHtml(b.name)}</option>`).join('');
  }
}

function _bwzSetStep(n) {
  _bwz.step = n;
  document.querySelectorAll('[data-bwz-dot]').forEach(dot => {
    const num = Number(dot.dataset.bwzDot);
    dot.classList.toggle('active', num === n);
    dot.classList.toggle('done',   num < n);
  });
  document.querySelectorAll('[data-bwz-line]').forEach(line => {
    line.classList.toggle('filled', Number(line.dataset.bwzLine) < n);
  });
  ['1','2','3'].forEach(i => {
    const p = $(`#bwz-panel-${i}`);
    if (p) p.style.display = (i === String(n)) ? '' : 'none';
  });
  const back   = $('#bwz-back-btn');
  const next   = $('#bwz-next-btn');
  const submit = $('#bwz-submit-btn');
  if (back)   back.style.display   = n > 1   ? '' : 'none';
  if (next)   next.style.display   = n < 3   ? '' : 'none';
  if (submit) submit.style.display = n === 3 ? '' : 'none';
}

async function _bwzSubmit() {
  const bankTypeId = Number($('#bwz-f-bank-type').value);
  if (!bankTypeId) { toast('Select an account type', 'error'); return; }
  const balance = parseFloat($('#bwz-f-balance').value) || 0;
  if (balance < 0) { toast('Balance cannot be negative', 'error'); return; }

  const bankId  = Number($('#bwz-f-bank').value) || null;
  const bankOpt = $('#bwz-f-bank').selectedOptions[0];
  const bankName = (bankId && bankOpt) ? bankOpt.text : null;

  const btn = $('#bwz-submit-btn');
  btn.disabled = true;
  btn.innerHTML = '<i class="fa fa-spinner fa-spin"></i> Creating…';

  const res = await API.createAccount({
    account_name:        $('#bwz-f-name').value.trim(),
    category:            $('#bwz-f-category').value,
    bank_type_id:        bankTypeId,
    bank_id:             bankId,
    bank_name:           bankName,
    bank_account_number: $('#bwz-f-acctno').value.trim() || null,
    branch:              $('#bwz-f-branch').value.trim() || null,
    current_balance:     balance,
    notes:               $('#bwz-f-notes').value.trim() || null,
  });

  btn.disabled = false;
  btn.innerHTML = '<i class="fa fa-check"></i> Create Account';

  if (res.status !== 201 && res.status !== 200) {
    const errs = res.body?.errors;
    const msg  = errs ? Object.values(errs).flat().join(' ') : (res.body?.message || 'Failed to create account');
    toast(msg, 'error');
    return;
  }

  $('#bwz-overlay').style.display = 'none';
  toast('Bank account created', 'success');
}

// Wire wizard buttons once on page load
$('#bwz-skip-btn')?.addEventListener('click', () => { $('#bwz-overlay').style.display = 'none'; });
$('#bwz-back-btn')?.addEventListener('click', () => { if (_bwz.step > 1) _bwzSetStep(_bwz.step - 1); });
$('#bwz-next-btn')?.addEventListener('click', () => {
  if (_bwz.step === 1) {
    const name = $('#bwz-f-name').value.trim();
    if (!name) { toast('Enter an account name', 'error'); $('#bwz-f-name').focus(); return; }
  }
  if (_bwz.step === 2) {
    if (!$('#bwz-f-bank-type').value) { toast('Select an account type', 'error'); return; }
  }
  if (_bwz.step < 3) _bwzSetStep(_bwz.step + 1);
});
$('#bwz-submit-btn')?.addEventListener('click', _bwzSubmit);

function updateProfileUI(bizName, email, userName) {
  // Button shows business name as context
  const bizInitial  = (bizName  || 'Z').trim()[0].toUpperCase();
  document.getElementById('tb-avatar').textContent       = bizInitial;
  document.getElementById('tb-profile-name').textContent = bizName || 'Account';
  // Dropdown header shows the logged-in user
  const displayName = userName || state._userName || bizName || 'Account';
  const userInitial = displayName.trim()[0].toUpperCase();
  document.getElementById('tpm-avatar').textContent = userInitial;
  document.getElementById('tpm-name').textContent   = displayName;
  document.getElementById('tpm-email').textContent  = email || state._userEmail || '—';
}

// Toggle between sign-in / sign-up
$('#go-signup').addEventListener('click', e => { e.preventDefault(); showSignup(); });
$('#go-signin').addEventListener('click', e => { e.preventDefault(); showLogin(); });

// Step 1: email + password
$('#login-btn').addEventListener('click', doLogin);
$('#login-password').addEventListener('keydown', e => { if (e.key === 'Enter') doLogin(); });

// Onboarding wizard — step navigation
$('#ob-next-btn').addEventListener('click', () => {
  const email    = $('#su-email').value.trim();
  const password = $('#su-password').value;
  const alert    = $('#signup-alert');
  if (!email)                       { showAlert(alert, 'Please enter your email'); return; }
  if (!/\S+@\S+\.\S+/.test(email)) { showAlert(alert, 'Please enter a valid email'); return; }
  if (password.length < 8)          { showAlert(alert, 'Password must be at least 8 characters'); return; }
  _obSetStep(2);
});
$('#su-password').addEventListener('keydown', e => { if (e.key === 'Enter') $('#ob-next-btn').click(); });

$('#ob-back-2-btn').addEventListener('click', () => _obSetStep(1));
$('#ob-next-2-btn').addEventListener('click', async () => {
  const name  = $('#su-name').value.trim();
  const biz   = $('#su-biz').value.trim();
  const alert = $('#signup-alert');
  if (!name) { showAlert(alert, 'Please enter your name'); return; }
  if (!biz)  { showAlert(alert, 'Please enter a business name'); return; }

  _obSetStep(3);
  _obBuildCatGrid(_obDefaultCats);

  API.businessCategories().then(res => {
    const cats = res.body?.data;
    if (Array.isArray(cats) && cats.length) _obBuildCatGrid(cats);
  });
});
$('#su-biz').addEventListener('keydown', e => { if (e.key === 'Enter') $('#ob-next-2-btn').click(); });

$('#ob-back-3-btn').addEventListener('click', () => _obSetStep(2));
$('#ob-next-3-btn').addEventListener('click', () => {
  const alert = $('#signup-alert');
  if (!_obSelectedCat) { showAlert(alert, 'Please select your industry'); return; }
  _obSetStep(4);
});

$('#ob-back-4-btn').addEventListener('click', () => _obSetStep(3));

// Password visibility toggle
$('#su-pw-toggle').addEventListener('click', () => {
  const inp = $('#su-password');
  const ico = $('#su-pw-toggle').querySelector('i');
  if (inp.type === 'password') { inp.type = 'text';     ico.className = 'fa fa-eye-slash'; }
  else                         { inp.type = 'password'; ico.className = 'fa fa-eye'; }
});

// Sign-up — final step submit
$('#signup-btn').addEventListener('click', doSignup);

async function doSignup() {
  const email    = $('#su-email').value.trim();
  const password = $('#su-password').value;
  const name     = $('#su-name').value.trim();
  const biz      = $('#su-biz').value.trim();
  const category = _obSelectedCat;
  const features = [..._obFeatureSet];
  const btn      = $('#signup-btn');
  const alert    = $('#signup-alert');

  if (!name)     { _obSetStep(2); showAlert(alert, 'Please enter your name'); return; }
  if (!biz)      { _obSetStep(2); showAlert(alert, 'Please enter a business name'); return; }
  if (!category) { _obSetStep(3); showAlert(alert, 'Please select your industry'); return; }

  btn.disabled = true;
  btn.innerHTML = '<i class="fa fa-spinner fa-spin"></i> Creating account…';
  alert.style.display = 'none';

  const deviceName = state.config?.device_name || 'pos-desktop-1';
  const res = await API.register(name, biz, category, features, email, password, deviceName);

  btn.disabled = false;
  btn.innerHTML = '<i class="fa fa-check"></i>&nbsp; Create Account';

  if (res.status !== 200 && res.status !== 201) {
    const errs = res.body?.errors || {};
    const msg  = Object.keys(errs).length
      ? Object.values(errs).flat().join(' ')
      : (res.body?.message || `Registration failed (${res.status})`);
    if (errs.email || errs.password) _obSetStep(1);
    showAlert($('#signup-alert'), msg);
    return;
  }

  const token = res.body?.access_token || res.body?.token;
  const suEmail = $('#su-email').value.trim();
  state._userName  = res.body?.user?.name  || $('#su-name').value.trim() || null;
  state._userEmail = res.body?.user?.email || suEmail;
  await window.electronAPI.setConfig({ token });
  state.config = await window.electronAPI.getConfig();

  // Auto-select the newly created business
  const bizRes = await API.businesses();
  const businesses = bizRes.body?.data || [];
  if (businesses.length) {
    const b = businesses[0];
    await window.electronAPI.setConfig({ business_id: b.id, branch_id: null });
    state.config = await window.electronAPI.getConfig();
    state._bizName = b.name;
    $('#app-title').textContent = `Zeebroo POS — ${b.name}`;
    updateProfileUI(b.name, state._userEmail || email, state._userName);
    $('#status-branch').innerHTML = `<i class="fa fa-building"></i> ${b.name}`;
  }

  showApp();
}

async function doLogin() {
  const email    = $('#login-email').value.trim();
  const password = $('#login-password').value;
  const btn      = $('#login-btn');
  const alert    = $('#login-alert');

  if (!email || !password) { showAlert(alert, 'Enter email and password'); return; }

  btn.disabled = true;
  btn.innerHTML = '<i class="fa fa-spinner fa-spin"></i> Signing in…';
  alert.style.display = 'none';

  const deviceName = state.config?.device_name || 'pos-desktop-1';
  console.log('[login] POST', state.config?.api_base_url, email);
  const res = await API.login(email, password, deviceName);
  console.log('[login] response', res.status, JSON.stringify(res.body).slice(0, 200));
  btn.disabled = false;
  btn.innerHTML = '<i class="fa fa-arrow-right-to-bracket"></i>&nbsp; Sign In';

  if (res.status !== 200 && res.status !== 201) {
    showAlert(alert, res.body?.message || `Login failed (${res.status})`);
    return;
  }

  const token = res.body?.token || res.body?.data?.token || res.body?.access_token;
  console.log('[login] token extracted:', token ? token.slice(0,20)+'…' : 'NONE');
  state._userName  = res.body?.user?.name  || null;
  state._userEmail = res.body?.user?.email || email;
  await window.electronAPI.setConfig({ token });
  state.config = await window.electronAPI.getConfig();

  await showBusinessStep();
}

async function showBusinessStep() {
  const res = await API.businesses();
  console.log('[businesses] response', res.status, JSON.stringify(res.body).slice(0, 200));
  if (res.status !== 200) {
    showAlert($('#login-alert'), `Failed to load businesses (${res.status}): ${res.body?.message || ''}`);
    return;
  }
  const businesses = res.body?.data || [];
  const sel = $('#sel-business');
  sel.innerHTML = businesses.map(b => `<option value="${b.id}">${b.name}</option>`).join('');

  $('#login-step-1').style.display = 'none';
  $('#login-step-2').style.display = 'block';

  sel.addEventListener('change', () => loadBranches(sel.value));
  await loadBranches(businesses[0]?.id);
}

async function loadBranches(businessId) {
  await window.electronAPI.setConfig({ business_id: Number(businessId) });
  state.config = await window.electronAPI.getConfig();

  const res = await API.branches();
  const raw = res.body?.data;
  // Response shape: { data: { branch_pos_separate: bool, branches: [...] } }
  const branches = (raw?.branches ?? raw) ?? [];
  const sel = $('#sel-branch');
  sel.innerHTML = branches.length
    ? branches.map(b => `<option value="${b.id}">${b.name}</option>`).join('')
    : '<option value="">— No branch (optional) —</option>';
}

$('#select-biz-btn').addEventListener('click', async () => {
  const businessId = Number($('#sel-business').value);
  if (!businessId) { showAlert($('#biz-alert'), 'Select a business to continue'); return; }

  const branchId = Number($('#sel-branch').value) || null;
  await window.electronAPI.setConfig({ business_id: businessId, branch_id: branchId });
  state.config = await window.electronAPI.getConfig();

  const bizName = $('#sel-business').selectedOptions[0]?.text || '';
  const email   = $('#login-email').value.trim();
  state._bizName = bizName;
  $('#app-title').textContent = bizName ? `Zeebroo POS — ${bizName}` : 'Zeebroo POS';
  updateProfileUI(bizName, state._userEmail || email, state._userName);
  $('#status-branch').innerHTML = branchId
    ? `<i class="fa fa-code-branch"></i> Branch #${branchId}`
    : `<i class="fa fa-building"></i> ${bizName}`;
  showApp();
});

function showAlert(el, msg) {
  el.textContent = msg;
  el.style.display = 'block';
}

// ── Unauthorized auto-logout ───────────────────────────────────────────────
window.addEventListener('api-unauthorized', async () => {
  await window.electronAPI.setConfig({ token: null, business_id: null, branch_id: null });
  state.config = await window.electronAPI.getConfig();
  state.posTabs = []; state.activePosTabId = null; state._nextPosTabId = 1;
  showLogin();
  toast('Session expired — please sign in again', 'error');
});

// ── Profile dropdown ───────────────────────────────────────────────────────
const profileBtn  = $('#tb-profile-btn');
const profileMenu = $('#tb-profile-menu');

function openProfileMenu() {
  profileBtn.classList.add('open');
  profileMenu.classList.add('open');
}

function closeProfileMenu() {
  profileBtn.classList.remove('open');
  profileMenu.classList.remove('open');
}

profileBtn.addEventListener('click', (e) => {
  e.stopPropagation();
  profileMenu.classList.contains('open') ? closeProfileMenu() : openProfileMenu();
});

// Close on outside click
document.addEventListener('click', (e) => {
  if (!$('#tb-profile-wrap').contains(e.target)) closeProfileMenu();
});

// Menu item actions
$('#tpm-settings').addEventListener('click', () => {
  closeProfileMenu();
  toast('Settings panel coming soon', 'info');
});
$('#tpm-my-profile').addEventListener('click', () => {
  closeProfileMenu();
  toast('Profile page coming soon', 'info');
});
$('#tpm-notifications').addEventListener('click', () => {
  closeProfileMenu();
  toast('Notifications coming soon', 'info');
});
$('#tpm-switch-branch').addEventListener('click', async () => {
  closeProfileMenu();
  await window.electronAPI.setConfig({ branch_id: null });
  state.config = await window.electronAPI.getConfig();
  showLogin();
  toast('Select a branch to switch', 'info');
});
$('#tpm-feature-mgmt').addEventListener('click', () => {
  closeProfileMenu();
  openFeatureMgmtModal();
});
$('#tpm-user-mgmt').addEventListener('click', () => {
  closeProfileMenu();
  toast('User Management coming soon', 'info');
});
$('#tpm-integrations').addEventListener('click', () => {
  closeProfileMenu();
  toast('Integrations coming soon', 'info');
});
$('#tpm-keyboard-shortcuts').addEventListener('click', () => {
  closeProfileMenu();
  showShortcutsModal();
});
$('#tpm-about').addEventListener('click', () => {
  closeProfileMenu();
  toast('Zeebroo POS v1.0.0 — © 2024 Zeebroo', 'info');
});
$('#tpm-check-updates').addEventListener('click', () => {
  closeProfileMenu();
  openUpdateModal();
});

function _updateModalClose() {
  $('#update-modal-overlay').style.display = 'none';
}
$('#update-modal-close').addEventListener('click', _updateModalClose);
$('#update-modal-overlay').addEventListener('click', e => { if (e.target === $('#update-modal-overlay')) _updateModalClose(); });

function _semverGt(a, b) {
  const pa = String(a).split('.').map(Number);
  const pb = String(b).split('.').map(Number);
  for (let i = 0; i < 3; i++) {
    if ((pa[i] || 0) > (pb[i] || 0)) return true;
    if ((pa[i] || 0) < (pb[i] || 0)) return false;
  }
  return false;
}

async function openUpdateModal() {
  const overlay = $('#update-modal-overlay');
  const body    = $('#update-modal-body');
  overlay.style.display = 'flex';

  body.innerHTML = `<div style="text-align:center;padding:20px 0;color:var(--text-muted)">
    <i class="fa fa-spinner fa-spin" style="font-size:28px;margin-bottom:14px;display:block"></i>
    Checking for updates…
  </div>`;

  const current = state.config?.app_version || '0.0.0';
  const res     = await window.electronAPI.checkForUpdate();
  const release = res?.body?.data;

  if (!res || res.status === 0) {
    body.innerHTML = `<div style="text-align:center;padding:16px 0">
      <i class="fa fa-triangle-exclamation" style="font-size:28px;color:#f59e0b;margin-bottom:12px;display:block"></i>
      <div style="font-weight:600;margin-bottom:6px">Could not check for updates</div>
      <div style="font-size:13px;color:var(--text-muted)">Please check your internet connection and try again.</div>
    </div>`;
    return;
  }

  if (!release) {
    body.innerHTML = `<div style="text-align:center;padding:16px 0">
      <i class="fa fa-circle-check" style="font-size:36px;color:#22c55e;margin-bottom:12px;display:block"></i>
      <div style="font-weight:700;font-size:16px;margin-bottom:6px">You're up to date!</div>
      <div style="font-size:13px;color:var(--text-muted)">Zeebroo POS <strong>v${escHtml(current)}</strong> is the latest version.</div>
    </div>`;
    return;
  }

  const latest    = release.version;
  const hasUpdate = _semverGt(latest, current);
  const platform  = window.electronAPI.platform;
  const dlUrl     = platform === 'win32'  ? release.windows_url
                  : platform === 'darwin' ? release.macos_url
                  : release.linux_url;

  const notes = Array.isArray(release.notes) && release.notes.length
    ? `<ul style="margin:8px 0 0;padding-left:18px;font-size:13px;color:var(--text-muted)">
        ${release.notes.map(n => `<li>${escHtml(n)}</li>`).join('')}
       </ul>`
    : '';

  if (!hasUpdate) {
    body.innerHTML = `<div style="text-align:center;padding:16px 0">
      <i class="fa fa-circle-check" style="font-size:36px;color:#22c55e;margin-bottom:12px;display:block"></i>
      <div style="font-weight:700;font-size:16px;margin-bottom:6px">You're up to date!</div>
      <div style="font-size:13px;color:var(--text-muted)">Zeebroo POS <strong>v${escHtml(current)}</strong> is the latest version.</div>
    </div>`;
    return;
  }

  body.innerHTML = `
    <div style="display:flex;align-items:center;gap:14px;margin-bottom:18px">
      <div style="width:48px;height:48px;border-radius:12px;background:var(--accent);display:flex;align-items:center;justify-content:center;flex-shrink:0">
        <i class="fa fa-download" style="color:#fff;font-size:20px"></i>
      </div>
      <div>
        <div style="font-weight:700;font-size:16px">Update Available</div>
        <div style="font-size:13px;color:var(--text-muted);margin-top:2px">Version <strong>v${escHtml(latest)}</strong> is ready to download</div>
      </div>
    </div>
    <div style="background:var(--surface2);border-radius:8px;padding:12px 14px;font-size:13px;margin-bottom:18px">
      <div style="display:flex;justify-content:space-between;margin-bottom:4px">
        <span style="color:var(--text-muted)">Current version</span>
        <span style="font-weight:600">v${escHtml(current)}</span>
      </div>
      <div style="display:flex;justify-content:space-between">
        <span style="color:var(--text-muted)">Latest version</span>
        <span style="font-weight:600;color:#22c55e">v${escHtml(latest)}</span>
      </div>
      ${release.release_date ? `<div style="display:flex;justify-content:space-between;margin-top:4px"><span style="color:var(--text-muted)">Released</span><span>${escHtml(release.release_date)}</span></div>` : ''}
    </div>
    ${notes}
    <div style="display:flex;gap:10px;margin-top:20px;justify-content:flex-end">
      <button class="po-btn-ghost" onclick="$('#update-modal-overlay').style.display='none'">Later</button>
      ${dlUrl ? `<button class="po-btn-primary" id="update-download-btn"><i class="fa fa-download"></i> Download v${escHtml(latest)}</button>` : '<span style="font-size:12px;color:var(--text-muted)">No download available for your platform.</span>'}
    </div>`;

  if (dlUrl) {
    $('#update-download-btn')?.addEventListener('click', () => {
      window.electronAPI.openExternal(dlUrl);
    });
  }
}
$('#tpm-logout').addEventListener('click', async () => {
  closeProfileMenu();
  await window.electronAPI.setConfig({ token: null, business_id: null, branch_id: null });
  state.posTabs = []; state.activePosTabId = null; state._nextPosTabId = 1;
  showLogin();
  toast('Signed out', 'info');
});

// ── Feature Management Modal ───────────────────────────────────────────────
const _featDefs = [
  { key: 'account_management',   name: 'Account Management',    desc: 'Financial accounts & bank management',    icon: 'fa-building-columns',   color: '#4e8ef7', locked: true },
  { key: 'point_of_sale',        name: 'Point of Sale',         desc: 'Cashier checkout interface & quick sales',icon: 'fa-cash-register',      color: '#4caf7d' },
  { key: 'product_management',   name: 'Product Management',    desc: 'Product catalog, categories & brands',    icon: 'fa-boxes-stacked',      color: '#0ea5e9' },
  { key: 'stock_management',     name: 'Stock Management',      desc: 'Stock audits & inventory adjustments',    icon: 'fa-clipboard-list',     color: '#64748b' },
  { key: 'bill_management',      name: 'Bill Management',       desc: 'Bills, loans & expense tracking',         icon: 'fa-file-invoice-dollar',color: '#9c6ef7' },
  { key: 'human_resources',      name: 'Human Resources',       desc: 'Employees, departments & payroll',        icon: 'fa-users',              color: '#f7a54e' },
  { key: 'service_management',   name: 'Services',              desc: 'Service-bound products & job management', icon: 'fa-screwdriver-wrench', color: '#f0a030' },
  { key: 'social_media_campaign',name: 'Social Media Campaign', desc: 'Design studio & marketing assets',        icon: 'fa-bullhorn',           color: '#e040fb' },
];

function openFeatureMgmtModal() {
  const list = $('#feat-mgmt-list');
  list.innerHTML = _featDefs.map(f => {
    const isOn = !state.features || state.features.has(f.key);
    const iconBg = f.color + '22';
    return `<div class="feat-mgmt-row${f.locked ? ' feat-mgmt-row-locked' : ''}">
      <div class="feat-mgmt-icon" style="background:${iconBg};color:${f.color}"><i class="fa ${f.icon}"></i></div>
      <div class="feat-mgmt-info">
        <div class="feat-mgmt-name">${f.name}${f.locked ? ' <span class="feat-mgmt-badge">Always On</span>' : ''}</div>
        <div class="feat-mgmt-desc">${f.desc}</div>
      </div>
      <div class="feat-mgmt-toggle${isOn ? ' on' : ''}${f.locked ? ' locked' : ''}" data-fm-key="${f.key}"></div>
    </div>`;
  }).join('');
  list.querySelectorAll('.feat-mgmt-toggle:not(.locked)').forEach(t => {
    t.addEventListener('click', () => t.classList.toggle('on'));
  });
  $('#feat-mgmt-alert').style.display = 'none';
  $('#feat-mgmt-overlay').style.display = 'flex';
}

function _featMgmtClose() { $('#feat-mgmt-overlay').style.display = 'none'; }

async function _featMgmtSave() {
  const btn = $('#feat-mgmt-save');
  btn.disabled = true;
  btn.innerHTML = '<i class="fa fa-spinner fa-spin"></i> Saving…';
  const features = {};
  _featDefs.forEach(f => {
    features[f.key] = !!$(`[data-fm-key="${f.key}"]`)?.classList.contains('on');
  });
  features.account_management = true;
  const res = await API.updateFeatures({ features });
  if (res.status !== 200) {
    btn.disabled = false;
    btn.innerHTML = '<i class="fa fa-check"></i> Save Changes';
    const al = $('#feat-mgmt-alert');
    al.textContent = res.body?.message || 'Failed to save. Please try again.';
    al.style.display = '';
    return;
  }
  btn.innerHTML = '<i class="fa fa-rotate fa-spin"></i> Restarting…';
  window.location.reload();
}

$('#feat-mgmt-close').addEventListener('click', _featMgmtClose);
$('#feat-mgmt-cancel').addEventListener('click', _featMgmtClose);
$('#feat-mgmt-save').addEventListener('click', _featMgmtSave);
$('#feat-mgmt-overlay').addEventListener('click', e => { if (e.target === $('#feat-mgmt-overlay')) _featMgmtClose(); });

function showShortcutsModal() {
  const shortcuts = [
    ['F2 / F3',   'Search products / Scan barcode'],
    ['F4',        'Add new product'],
    ['F5',        'Refresh products'],
    ['F6',        'Park current sale'],
    ['F7',        'Open parked sales'],
    ['F8',        'Clear cart'],
    ['F9',        'Return / Refund'],
    ['F10',       'Open customer panel'],
    ['F11',       'Toggle full screen'],
    ['F12',       'Checkout'],
    ['Ctrl+T',    'New POS session tab'],
    ['Ctrl+Z',    'Undo last cart item'],
    ['Ctrl+F1',   'Collapse / expand ribbon'],
    ['—',         ''],
    ['Ctrl+1',    'Inventory → Products'],
    ['Ctrl+2',    'Inventory → Suppliers'],
    ['Ctrl+3',    'Inventory → Purchase Orders'],
    ['Ctrl+4',    'Inventory → Goods Receive'],
    ['Ctrl+5',    'Inventory → Cheques'],
    ['Ctrl+6',    'Inventory → Stock Audit'],
    ['Ctrl+7',    'Inventory → Categories'],
    ['Ctrl+8',    'Inventory → Units'],
    ['Ctrl+9',    'Inventory → Discounts'],
    ['Ctrl+0',    'Inventory → Brands'],
    ['Ctrl+B',    'Inventory → Barcode Sheets'],
  ];
  const overlay = document.createElement('div');
  overlay.className = 'modal-overlay';
  overlay.innerHTML = `
    <div class="modal" style="width:360px">
      <h3><i class="fa fa-keyboard"></i> Keyboard Shortcuts</h3>
      <table style="width:100%;border-collapse:collapse;max-height:65vh;display:block;overflow-y:auto">
        ${shortcuts.map(([k, d]) => k === '—'
          ? `<tr><td colspan="2" style="padding:4px 6px"><hr style="border:none;border-top:1px solid var(--border);margin:2px 0"></td></tr>`
          : `<tr style="border-bottom:1px solid var(--border-light)">
              <td style="padding:6px 6px;white-space:nowrap"><kbd style="background:var(--surface3);border:1px solid var(--border);border-radius:4px;padding:2px 7px;font-size:11px;font-family:inherit">${k}</kbd></td>
              <td style="padding:6px 6px;font-size:12px;color:var(--text)">${d}</td>
            </tr>`).join('')}
      </table>
      <div class="modal-footer">
        <button class="btn-secondary" id="sc-close">Close</button>
      </div>
    </div>`;
  document.body.appendChild(overlay);
  overlay.querySelector('#sc-close').addEventListener('click', () => overlay.remove());
  overlay.addEventListener('click', (e) => { if (e.target === overlay) overlay.remove(); });
}

// ── Sign out (ribbon View tab button) ─────────────────────────────────────
$('#rb-logout').addEventListener('click', async () => {
  await window.electronAPI.setConfig({ token: null, business_id: null, branch_id: null });
  state.posTabs = []; state.activePosTabId = null; state._nextPosTabId = 1;
  showLogin();
  toast('Signed out', 'info');
});

// ── Fullscreen ─────────────────────────────────────────────────────────────
$('#rb-fullscreen').addEventListener('click', () => {
  state.isFullscreen = !state.isFullscreen;
  window.electronAPI.fullscreen(state.isFullscreen);
  $('#fs-icon').className = state.isFullscreen ? 'fa fa-compress' : 'fa fa-expand';
  $('#rb-fullscreen').title = state.isFullscreen ? 'Exit Full Screen (F11)' : 'Full Screen (F11)';
});

// ── Ribbon collapse / expand (pin button + Ctrl+F1) ────────────────────────
$('#ribbon-pin-btn').addEventListener('click', toggleRibbonCollapse);
document.addEventListener('keydown', (e) => {
  if (e.key === 'F1' && e.ctrlKey) { toggleRibbonCollapse(); e.preventDefault(); }
});

function toggleRibbonCollapse() {
  const ribbon = $('#ribbon');
  const icon   = $('#ribbon-pin-icon');
  const collapsed = ribbon.classList.toggle('collapsed');
  icon.className  = collapsed ? 'fa fa-chevron-down' : 'fa fa-chevron-up';
  $('#ribbon-pin-btn').title = collapsed
    ? 'Pin the Ribbon (Ctrl+F1)'
    : 'Collapse the Ribbon (Ctrl+F1)';
}

// Double-click a tab to collapse / expand ribbon
$$('.ribbon-tab').forEach(tab => {
  tab.addEventListener('dblclick', toggleRibbonCollapse);
});

// ── Quick Access Toolbar stubs ─────────────────────────────────────────────
$('#qat-save').addEventListener('click', () => toast('Saved', 'success'));
$('#qat-undo').addEventListener('click', () => toast('Nothing to undo', 'info'));
$('#qat-redo').addEventListener('click', () => toast('Nothing to redo', 'info'));

// ── Home Dashboard ─────────────────────────────────────────────────────────
function loadHomeDashboard() {
  const hr  = new Date().getHours();
  const greet = hr < 12 ? 'Good morning' : hr < 17 ? 'Good afternoon' : 'Good evening';
  // Derive biz name from config (more reliable than title bar at this point)
  const storedBiz = state._bizName || $('#app-title').textContent.replace('Zeebroo POS — ', '').replace('Zeebroo POS', '').trim();
  const bizName = storedBiz || '';
  $('#home-greeting').textContent = bizName ? `${greet}, ${bizName}` : greet;
  $('#home-date').textContent = new Date().toLocaleDateString(undefined, { weekday: 'long', year: 'numeric', month: 'long', day: 'numeric' });
  if (state.products.length) $('#kpi-products').textContent = state.products.length;

  // Delay one frame so the panel flex layout resolves before we measure dimensions
  requestAnimationFrame(() => renderBusinessChart(bizName || 'Your Business'));
  loadHomeKPIs();
  _homeRightPanelLoad();
}

async function loadHomeKPIs() {
  const res = await API.sales('');
  if (res.status !== 200) return;
  const items = res.body?.data || [];
  $('#kpi-sales').textContent   = items.length;
  const rev = items.reduce((s, i) => s + parseFloat(i.total || 0), 0);
  $('#kpi-revenue').textContent = rev.toFixed(2);
}

$('#home-refresh').addEventListener('click', loadHomeDashboard);

// ── Home tab switching ─────────────────────────────────────────────────────
function switchHomeView(view) {
  $$('.home-view').forEach(el => el.style.display = 'none');
  $$('.home-tab-btn').forEach(btn => btn.classList.toggle('active', btn.dataset.homeView === view));
  const target = $(`#home-view-${view}`);
  if (target) target.style.display = 'flex';
  if (view === 'activity')  _homeActivityLoad();
  if (view === 'analytics') requestAnimationFrame(_homeAnalyticsLoad);
}

$$('.home-tab-btn').forEach(btn => {
  btn.addEventListener('click', () => switchHomeView(btn.dataset.homeView));
});

// ── Home right-panel ───────────────────────────────────────────────────────
async function _homeRightPanelLoad() {
  _homeRightPanelToday();
  _homeRightPanelBills();
}

async function _homeRightPanelToday() {
  const today = new Date().toISOString().slice(0, 10);
  const res = await API.sales('');
  if (res.status !== 200) return;
  const all = res.body?.data || [];
  const todaySales = all.filter(s => (s.sold_at || '').slice(0, 10) === today);
  const todayRev   = todaySales.reduce((sum, s) => sum + parseFloat(s.total || 0), 0);
  const todayItems = todaySales.reduce((sum, s) => sum + (s.items_count || 0), 0);
  if ($('#hrp-today-sales')) $('#hrp-today-sales').textContent = todaySales.length;
  if ($('#hrp-today-rev'))   $('#hrp-today-rev').textContent   = todayRev.toFixed(2);
  if ($('#hrp-today-items')) $('#hrp-today-items').textContent = todayItems || '—';
  // Also update top KPIs
  $('#kpi-sales').textContent   = all.length;
  const rev = all.reduce((s, i) => s + parseFloat(i.total || 0), 0);
  $('#kpi-revenue').textContent = rev.toFixed(2);
}

async function _homeRightPanelBills() {
  const el = $('#hrp-bills');
  if (!el) return;
  const res = await API.financeFlow();
  if (res.status !== 200) { el.innerHTML = '<div class="hrp-empty">No data</div>'; return; }
  // Merge bills + rentals (overdue first), loans shown separately with cadence
  const all = [
    ...(res.body?.bills   || []).map(b => ({ name: b.name,         amt: b.amount_fmt,  cadence: b.cadence, overdue: b.overdue })),
    ...(res.body?.rentals || []).map(r => ({ name: r.name,         amt: r.cost_fmt,    cadence: r.cadence, overdue: r.overdue })),
    ...(res.body?.loans   || []).map(l => ({ name: l.name,         amt: l.monthly_fmt, cadence: l.cadence, overdue: l.overdue })),
  ].sort((a, b2) => (b2.overdue ? 1 : 0) - (a.overdue ? 1 : 0)).slice(0, 6);
  if (!all.length) { el.innerHTML = '<div class="hrp-empty">No bills recorded</div>'; return; }
  el.innerHTML = all.map(b => {
    const cls     = b.overdue ? 'overdue' : 'ok';
    const daysTxt = b.overdue ? 'Overdue' : b.cadence || '—';
    return `<div class="hrp-bill-row">
      <div class="hrp-bill-dot" style="background:${b.overdue ? '#ef4444' : '#22c55e'}"></div>
      <div class="hrp-bill-name" title="${escHtml(b.name)}">${escHtml(b.name)}</div>
      <div class="hrp-bill-days ${cls}">${escHtml(daysTxt)}</div>
    </div>`;
  }).join('');
}

// ── Home activity feed ─────────────────────────────────────────────────────
async function _homeActivityLoad() {
  const list = $('#home-act-list');
  if (!list) return;
  list.innerHTML = '<div class="inv-loading"><i class="fa fa-spinner fa-spin"></i> Loading…</div>';
  const res = await API.sales('');
  if (res.status !== 200) {
    list.innerHTML = '<div class="inv-loading" style="color:#ef4444"><i class="fa fa-triangle-exclamation"></i> Failed to load</div>';
    return;
  }
  const rows = (res.body?.data || []).slice(0, 50);
  if (!rows.length) { list.innerHTML = '<div class="inv-loading">No recent activity</div>'; return; }
  list.innerHTML = rows.map(s => {
    const total = parseFloat(s.total || 0).toFixed(2);
    const dt    = s.sold_at ? new Date(s.sold_at).toLocaleString(undefined, { month:'short', day:'numeric', hour:'2-digit', minute:'2-digit' }) : '';
    const ref   = s.sale_number || `#${s.id}`;
    return `<div class="home-act-row">
      <div class="home-act-icon sale"><i class="fa fa-cart-shopping"></i></div>
      <div class="home-act-info">
        <div class="home-act-label">Sale ${escHtml(ref)}</div>
        <div class="home-act-meta">${dt}</div>
      </div>
      <div class="home-act-amt">${total}</div>
    </div>`;
  }).join('');
}

$('#home-act-refresh')?.addEventListener('click', _homeActivityLoad);

// ── Home Analytics ────────────────────────────────────────────────────────────
const _han = { period: 30, sales: null, flow: null };

async function _homeAnalyticsLoad() {
  const [sr, fr] = await Promise.all([API.sales(''), API.financeFlow()]);
  _han.sales = sr.status === 200 ? (sr.body?.data || []) : [];
  _han.flow  = fr.status === 200 ? fr.body : null;
  _hanKPIs();
  _hanLineChart();
  _hanBarChart();
  _hanExpenses();
}

function _hanKPIs() {
  const today = new Date().toISOString().slice(0, 10);
  const month = new Date().toISOString().slice(0, 7);
  const active = _han.sales.filter(s => s.status !== 'voided');
  const todaySales = active.filter(s => (s.sold_at || '').slice(0, 10) === today);
  const todayRev   = todaySales.reduce((a, s) => a + parseFloat(s.total || 0), 0);
  const monthRev   = active.filter(s => (s.sold_at || '').slice(0, 7) === month)
                           .reduce((a, s) => a + parseFloat(s.total || 0), 0);
  const billCount = (_han.flow?.summary?.bills_count  || 0)
                  + (_han.flow?.summary?.loans_count   || 0)
                  + (_han.flow?.summary?.rentals_count || 0);
  const fmt = n => n >= 1000 ? `${(n/1000).toFixed(1)}k` : n.toFixed(2);
  $('#han-kpi-sales').textContent = todaySales.length;
  $('#han-kpi-rev').textContent   = fmt(todayRev);
  $('#han-kpi-month').textContent = fmt(monthRev);
  $('#han-kpi-bills').textContent = billCount;
}

function _hanLineChart() {
  const container = $('#han-line-chart');
  if (!container || !window.d3) return;
  const p = _han.period;

  // Build daily revenue buckets for the last p days
  const cutoff = new Date(); cutoff.setDate(cutoff.getDate() - p); cutoff.setHours(0,0,0,0);
  const buckets = {};
  for (let i = 1; i <= p; i++) {
    const d = new Date(cutoff); d.setDate(d.getDate() + i);
    buckets[d.toISOString().slice(0, 10)] = 0;
  }
  _han.sales.filter(s => s.status !== 'voided' && (s.sold_at || '') >= cutoff.toISOString().slice(0, 10)).forEach(s => {
    const k = (s.sold_at || '').slice(0, 10);
    if (k in buckets) buckets[k] += parseFloat(s.total || 0);
  });
  const data = Object.entries(buckets).map(([d, v]) => ({ date: new Date(d + 'T00:00:00'), value: v }));

  const lbl = {7:'Last 7 days', 30:'Last 30 days', 90:'Last 90 days'}[p] || `Last ${p} days`;
  const lblEl = $('#han-line-label');
  if (lblEl) lblEl.textContent = lbl;

  container.innerHTML = '';
  const W = container.clientWidth || 400;
  const H = container.clientHeight || 180;
  const m = { top: 12, right: 18, bottom: 28, left: 46 };
  const w = W - m.left - m.right;
  const h = H - m.top - m.bottom;
  if (w <= 0 || h <= 0) return;

  const st = getComputedStyle(document.body);
  const accent = st.getPropertyValue('--accent').trim() || '#6366f1';
  const muted  = st.getPropertyValue('--text-muted').trim() || '#94a3b8';
  const border = st.getPropertyValue('--border').trim() || '#e2e8f0';

  const svg = d3.select(container).append('svg').attr('width', W).attr('height', H);
  const g   = svg.append('g').attr('transform', `translate(${m.left},${m.top})`);

  const xScale = d3.scaleTime().domain(d3.extent(data, d => d.date)).range([0, w]);
  const yMax   = d3.max(data, d => d.value) || 1;
  const yScale = d3.scaleLinear().domain([0, yMax * 1.12]).range([h, 0]);

  // Grid
  g.append('g').call(d3.axisLeft(yScale).ticks(4).tickSize(-w).tickFormat(''))
    .call(g2 => { g2.select('.domain').remove(); g2.selectAll('line').attr('stroke', border).attr('stroke-dasharray','3,3'); });

  // Gradient fill
  const gradId = 'han-lg';
  const defs = svg.append('defs');
  const grad = defs.append('linearGradient').attr('id', gradId).attr('x1','0%').attr('y1','0%').attr('x2','0%').attr('y2','100%');
  grad.append('stop').attr('offset','0%').attr('stop-color', accent).attr('stop-opacity', 0.22);
  grad.append('stop').attr('offset','100%').attr('stop-color', accent).attr('stop-opacity', 0.02);

  const area = d3.area().x(d => xScale(d.date)).y0(h).y1(d => yScale(d.value)).curve(d3.curveMonotoneX);
  g.append('path').datum(data).attr('fill', `url(#${gradId})`).attr('d', area);

  const line = d3.line().x(d => xScale(d.date)).y(d => yScale(d.value)).curve(d3.curveMonotoneX);
  g.append('path').datum(data).attr('fill','none').attr('stroke', accent).attr('stroke-width', 2).attr('d', line);

  // Dots (only for short periods)
  if (p <= 30) {
    g.selectAll('circle').data(data).join('circle')
      .attr('cx', d => xScale(d.date)).attr('cy', d => yScale(d.value))
      .attr('r', 3).attr('fill', accent).attr('stroke','#fff').attr('stroke-width', 1.5);
  }

  // Axes
  const ticks = p <= 7 ? p : (p <= 30 ? 6 : 8);
  const fmt   = p <= 7 ? d3.timeFormat('%a') : d3.timeFormat('%m/%d');
  g.append('g').attr('transform',`translate(0,${h})`)
    .call(d3.axisBottom(xScale).ticks(ticks).tickFormat(fmt))
    .call(g2 => { g2.select('.domain').attr('stroke', border); g2.selectAll('text').attr('fill', muted).style('font-size','10px'); g2.selectAll('line').attr('stroke', border); });
  g.append('g')
    .call(d3.axisLeft(yScale).ticks(4).tickFormat(v => v >= 1000 ? `${(v/1000).toFixed(1)}k` : v.toFixed(0)))
    .call(g2 => { g2.select('.domain').attr('stroke', border); g2.selectAll('text').attr('fill', muted).style('font-size','10px'); g2.selectAll('line').attr('stroke', border); });
}

function _hanBarChart() {
  const container = $('#han-bar-chart');
  if (!container || !window.d3) return;
  const p = _han.period;

  const cutoff = new Date(); cutoff.setDate(cutoff.getDate() - p); cutoff.setHours(0,0,0,0);
  const days   = ['Mon','Tue','Wed','Thu','Fri','Sat','Sun'];
  const totals = [0,0,0,0,0,0,0];
  const cutoffStr = cutoff.toISOString().slice(0, 10);
  _han.sales.filter(s => s.status !== 'voided' && (s.sold_at || '') >= cutoffStr).forEach(s => {
    const dow = new Date((s.sold_at || '').slice(0, 10) + 'T00:00:00').getDay(); // 0=Sun
    const idx = dow === 0 ? 6 : dow - 1;
    totals[idx] += parseFloat(s.total || 0);
  });
  const data = days.map((label, i) => ({ label, value: totals[i] }));

  container.innerHTML = '';
  const W = container.clientWidth || 300;
  const H = container.clientHeight || 180;
  const m = { top: 10, right: 18, bottom: 28, left: 46 };
  const w = W - m.left - m.right;
  const h = H - m.top - m.bottom;
  if (w <= 0 || h <= 0) return;

  const st     = getComputedStyle(document.body);
  const accent = st.getPropertyValue('--accent').trim() || '#6366f1';
  const muted  = st.getPropertyValue('--text-muted').trim() || '#94a3b8';
  const border = st.getPropertyValue('--border').trim() || '#e2e8f0';

  const svg = d3.select(container).append('svg').attr('width', W).attr('height', H);
  const g   = svg.append('g').attr('transform', `translate(${m.left},${m.top})`);

  const xScale = d3.scaleBand().domain(days).range([0, w]).padding(0.28);
  const yMax   = d3.max(data, d => d.value) || 1;
  const yScale = d3.scaleLinear().domain([0, yMax * 1.12]).range([h, 0]);

  // Grid
  g.append('g').call(d3.axisLeft(yScale).ticks(4).tickSize(-w).tickFormat(''))
    .call(g2 => { g2.select('.domain').remove(); g2.selectAll('line').attr('stroke', border).attr('stroke-dasharray','3,3'); });

  // Bars — weekend bars slightly lighter
  g.selectAll('rect').data(data).join('rect')
    .attr('x', d => xScale(d.label))
    .attr('y', d => yScale(d.value))
    .attr('width', xScale.bandwidth())
    .attr('height', d => Math.max(0, h - yScale(d.value)))
    .attr('rx', 3)
    .attr('fill', accent)
    .attr('opacity', (d, i) => i >= 5 ? 0.55 : 0.85);

  // Axes
  g.append('g').attr('transform',`translate(0,${h})`)
    .call(d3.axisBottom(xScale))
    .call(g2 => { g2.select('.domain').attr('stroke', border); g2.selectAll('text').attr('fill', muted).style('font-size','10px'); g2.selectAll('line').attr('stroke', border); });
  g.append('g')
    .call(d3.axisLeft(yScale).ticks(4).tickFormat(v => v >= 1000 ? `${(v/1000).toFixed(1)}k` : v.toFixed(0)))
    .call(g2 => { g2.select('.domain').attr('stroke', border); g2.selectAll('text').attr('fill', muted).style('font-size','10px'); g2.selectAll('line').attr('stroke', border); });
}

function _hanExpenses() {
  const list = $('#han-expenses-list');
  if (!list) return;
  if (!_han.flow) { list.innerHTML = '<div class="hrp-empty">No data</div>'; return; }

  const all = [
    ...(_han.flow.bills   || []).map(b => ({ name: b.name, amt: b.amount_fmt,  cadence: b.cadence, overdue: b.overdue, _t:'bill'   })),
    ...(_han.flow.rentals || []).map(r => ({ name: r.name, amt: r.cost_fmt,    cadence: r.cadence, overdue: r.overdue, _t:'rental' })),
    ...(_han.flow.loans   || []).map(l => ({ name: l.name, amt: l.monthly_fmt, cadence: l.cadence, overdue: l.overdue, _t:'loan'   })),
  ].sort((a, b2) => (b2.overdue ? 1 : 0) - (a.overdue ? 1 : 0)).slice(0, 12);

  if (!all.length) { list.innerHTML = '<div class="hrp-empty">No expenses recorded</div>'; return; }

  const icons  = { bill:'fa-file-invoice-dollar', loan:'fa-hand-holding-dollar', rental:'fa-building' };
  const colors = { bill:'#3b82f6', loan:'#8b5cf6', rental:'#f59e0b' };
  list.innerHTML = all.map(item => {
    const cls     = item.overdue ? 'overdue' : 'ok';
    const status  = item.overdue ? 'Overdue' : (item.cadence || 'Active');
    const ico = icons[item._t]  || 'fa-receipt';
    const col = colors[item._t] || '#94a3b8';
    return `<div class="han-exp-row">
      <div class="han-exp-icon" style="color:${col}"><i class="fa ${ico}"></i></div>
      <div class="han-exp-info">
        <div class="han-exp-name">${escHtml(item.name || '—')}</div>
        <div class="han-exp-meta">${item._t[0].toUpperCase()+item._t.slice(1)} &middot; <span class="hrp-bill-days ${cls}">${escHtml(status)}</span></div>
      </div>
      <div class="han-exp-amt">${escHtml(item.amt || '—')}</div>
    </div>`;
  }).join('');
}

// Period selector
$$('#han-period-btns .han-period-btn').forEach(btn => {
  btn.addEventListener('click', () => {
    $$('#han-period-btns .han-period-btn').forEach(b => b.classList.remove('active'));
    btn.classList.add('active');
    _han.period = parseInt(btn.dataset.period);
    _hanLineChart();
    _hanBarChart();
  });
});
$('#han-refresh')?.addEventListener('click', _homeAnalyticsLoad);

// Right-panel quick actions
$('#hrp-new-sale')?.addEventListener('click',    () => activateTab('pos'));
$('#hrp-add-product')?.addEventListener('click', () => { activateTab('pos'); openAddProductModal(); });
$('#hrp-new-bill')?.addEventListener('click',    () => { activateTab('finance'); switchFinView('bills'); openBillCreateModal(); });
$('#hrp-view-orders')?.addEventListener('click', () => { activateTab('inventory'); switchInvView('po'); });
$('#hrp-barcodes')?.addEventListener('click',    () => { activateTab('inventory'); switchInvView('barcodes'); });

// Ribbon Home buttons
$('#rb-home-pos').addEventListener('click',           () => activateTab('pos'));
$('#rb-home-new-sale').addEventListener('click',      () => { activateTab('pos'); });
$('#rb-home-daily-summary').addEventListener('click', () => { activateTab('home'); loadHomeDashboard(); });
$('#rb-home-dashboard').addEventListener('click',     () => activateTab('home'));
$('#rb-home-analytics').addEventListener('click',     () => { activateTab('home'); switchHomeView('analytics'); });
$('#rb-home-orders').addEventListener('click',        () => activateTab('inventory'));
$('#rb-home-customers').addEventListener('click',     () => toast('Customers coming soon', 'info'));
$('#rb-home-suppliers').addEventListener('click', () => { activateTab('inventory'); switchInvView('suppliers'); });
$('#rb-home-expenses').addEventListener('click',      () => activateTab('finance'));
$('#rb-home-profit').addEventListener('click',        () => toast('Profit Report coming soon', 'info'));
$('#rb-home-payroll').addEventListener('click',       () => toast('Payroll coming soon', 'info'));
$('#rb-home-settings').addEventListener('click',      () => toast('Settings coming soon', 'info'));
$('#rb-home-help').addEventListener('click',          () => showShortcutsModal());

// ── React Flow Business Flowchart ─────────────────────────────────────────
function renderBusinessChart(bizName) {
  const container = document.getElementById('home-chart');
  if (!container) return;

  const RF = window.ReactFlow;
  if (!RF || !RF.ReactFlow) {
    container.innerHTML = '<div style="padding:40px;text-align:center;color:var(--text-muted)">Chart not available</div>';
    return;
  }

  const isDark = document.body.classList.contains('dark');
  const C_ROOT = '#4e8ef7', C_EXP = '#dc2626', C_INC = '#16a34a';
  const { Handle, Position, Controls, MarkerType, useNodesState, useEdgesState } = RF;
  const ReactFlowComp = RF.ReactFlow;
  const makeH = (_c, extra) => ({ width: 1, height: 1, background: 'transparent', border: 'none', opacity: 0, ...extra });

  // ── Node types ────────────────────────────────────────────────────────────
  const RootNode = ({ data }) =>
    React.createElement('div', { style: {
      background: C_ROOT, color: '#fff', borderRadius: 26,
      padding: '0 22px', height: 52, display: 'flex', alignItems: 'center',
      fontWeight: 700, fontSize: 13, fontFamily: "'Segoe UI',sans-serif",
      boxShadow: '0 6px 18px rgba(78,142,247,.5)', cursor: 'pointer', userSelect: 'none', whiteSpace: 'nowrap',
    }},
      React.createElement(Handle, { type: 'source', position: Position.Left,   id: 'src-l', style: makeH(C_ROOT) }),
      React.createElement(Handle, { type: 'source', position: Position.Right,  id: 'src-r', style: makeH(C_ROOT) }),
      React.createElement(Handle, { type: 'source', position: Position.Bottom, id: 'src-b', style: makeH(C_ROOT) }),
      data.label
    );

  const ExpHubNode = ({ data }) =>
    React.createElement('div', { style: {
      background: C_EXP, color: '#fff', borderRadius: 20,
      padding: '0 18px', height: 40, display: 'flex', alignItems: 'center',
      fontWeight: 700, fontSize: 12, fontFamily: "'Segoe UI',sans-serif",
      boxShadow: `0 4px 12px ${C_EXP}88`, cursor: 'pointer', userSelect: 'none', whiteSpace: 'nowrap',
    }},
      React.createElement(Handle, { type: 'target', position: Position.Right,  id: 'tgt-r',  style: makeH(C_EXP) }),
      React.createElement(Handle, { type: 'source', position: Position.Left,   id: 'src-l0', style: makeH(C_EXP, { top: '25%' }) }),
      React.createElement(Handle, { type: 'source', position: Position.Left,   id: 'src-l1', style: makeH(C_EXP, { top: '50%' }) }),
      React.createElement(Handle, { type: 'source', position: Position.Left,   id: 'src-l2', style: makeH(C_EXP, { top: '75%' }) }),
      React.createElement(Handle, { type: 'source', position: Position.Top,    id: 'src-t0', style: makeH(C_EXP, { left: '10%' }) }),
      React.createElement(Handle, { type: 'source', position: Position.Top,    id: 'src-t1', style: makeH(C_EXP, { left: '30%' }) }),
      React.createElement(Handle, { type: 'source', position: Position.Top,    id: 'src-t2', style: makeH(C_EXP, { left: '50%' }) }),
      React.createElement(Handle, { type: 'source', position: Position.Top,    id: 'src-t3', style: makeH(C_EXP, { left: '70%' }) }),
      React.createElement(Handle, { type: 'source', position: Position.Top,    id: 'src-t4', style: makeH(C_EXP, { left: '90%' }) }),
      React.createElement(Handle, { type: 'source', position: Position.Bottom, id: 'src-b0', style: makeH(C_EXP, { left: '33%' }) }),
      React.createElement(Handle, { type: 'source', position: Position.Bottom, id: 'src-b1', style: makeH(C_EXP, { left: '67%' }) }),
      data.label
    );

  const IncHubNode = ({ data }) =>
    React.createElement('div', { style: {
      background: C_INC, color: '#fff', borderRadius: 20,
      padding: '0 18px', height: 40, display: 'flex', alignItems: 'center',
      fontWeight: 700, fontSize: 12, fontFamily: "'Segoe UI',sans-serif",
      boxShadow: `0 4px 12px ${C_INC}88`, cursor: 'pointer', userSelect: 'none', whiteSpace: 'nowrap',
    }},
      React.createElement(Handle, { type: 'target', position: Position.Left,   id: 'tgt-l',  style: makeH(C_INC) }),
      React.createElement(Handle, { type: 'source', position: Position.Top,    id: 'src-t0', style: makeH(C_INC) }),
      React.createElement(Handle, { type: 'source', position: Position.Right,  id: 'src-r0', style: makeH(C_INC, { top: '33%' }) }),
      React.createElement(Handle, { type: 'source', position: Position.Right,  id: 'src-r1', style: makeH(C_INC, { top: '67%' }) }),
      React.createElement(Handle, { type: 'source', position: Position.Bottom, id: 'src-b0', style: makeH(C_INC) }),
      data.label
    );

  const posMap = { top: Position.Top, right: Position.Right, bottom: Position.Bottom, left: Position.Left };
  const LeafNode = ({ data }) => {
    const isExp  = data.variant === 'exp';
    const hColor = isExp ? '#f87171' : '#34d399';
    return React.createElement('div', { style: {
      background: isExp ? (isDark ? '#450a0a' : '#fee2e2') : (isDark ? '#052e16' : '#d1fae5'),
      color:      isExp ? (isDark ? '#fca5a5' : '#991b1b') : (isDark ? '#6ee7b7' : '#065f46'),
      border: `1.5px solid ${hColor}`, borderRadius: 17, padding: '0 14px', height: 36,
      display: 'flex', alignItems: 'center', gap: 6,
      fontWeight: 600, fontSize: 11, fontFamily: "'Segoe UI',sans-serif",
      boxShadow: '0 2px 8px rgba(0,0,0,.12)', cursor: 'pointer', userSelect: 'none', whiteSpace: 'nowrap',
    }},
      React.createElement(Handle, { type: 'target', position: posMap[data.ts] || Position.Right, id: 'tgt', style: makeH(hColor) }),
      data.label
    );
  };

  // ── Bills hub node — target on right, 6 bottom sources for due-bill children ──
  const BillsHubNode = ({ data }) => {
    const expanded = data.expanded;
    const bg  = expanded ? '#ef4444' : (isDark ? '#450a0a' : '#fee2e2');
    const col = expanded ? '#fff'    : (isDark ? '#fca5a5' : '#991b1b');
    return React.createElement('div', { style: {
      background: bg, color: col, border: `1.5px solid #f87171`,
      borderRadius: 17, padding: '0 14px', height: 36,
      display: 'flex', alignItems: 'center', gap: 6,
      fontWeight: 700, fontSize: 11, fontFamily: "'Segoe UI',sans-serif",
      boxShadow: expanded ? '0 0 0 3px rgba(239,68,68,.3), 0 3px 10px rgba(239,68,68,.4)' : '0 2px 8px rgba(0,0,0,.12)',
      cursor: 'pointer', userSelect: 'none', whiteSpace: 'nowrap',
    }},
      React.createElement(Handle, { type: 'target', position: Position.Right, id: 'tgt', style: makeH('#f87171') }),
      ...[0,1,2,3,4,5,6,7].map(i =>
        React.createElement(Handle, { key: i, type: 'source', position: Position.Left, id: `src-b${i}`, style: makeH('#f87171', { top: `${6 + i * 12}%` }) })
      ),
      React.createElement('i', { className: 'fa fa-file-invoice-dollar', style: { fontSize: 10 } }),
      'Bills',
      data.dueCount > 0 && React.createElement('span', { style: {
        background: expanded ? 'rgba(255,255,255,.25)' : '#ef4444',
        color: '#fff', borderRadius: 9, padding: '1px 5px',
        fontSize: 9, fontWeight: 800, lineHeight: '14px',
      }}, data.dueCount)
    );
  };

  // ── Due bill child node ────────────────────────────────────────────────────
  const CHART_CAT_COLORS = {
    water: '#3b82f6', electricity: '#f59e0b', telephone: '#8b5cf6',
    internet: '#06b6d4', gas: '#f97316', waste: '#6b7280', other: '#10b981',
  };
  const CHART_CAT_ICONS = {
    water: 'fa-droplet', electricity: 'fa-bolt', telephone: 'fa-phone',
    internet: 'fa-wifi', gas: 'fa-fire-flame-curved', waste: 'fa-trash-can', other: 'fa-tag',
  };

  const DueBillNode = ({ data }) => {
    const b   = data.bill;
    const col = CHART_CAT_COLORS[b.bill_category] || '#10b981';
    const ico = CHART_CAT_ICONS[b.bill_category]  || 'fa-tag';
    const amt = b.amount_varies_by_usage
      ? 'Variable'
      : (b.recurring_cost != null ? parseFloat(b.recurring_cost).toFixed(2) : '—');
    const due = b.due_date || b.first_installment_due_date || '';
    return React.createElement('div', { style: {
      background: isDark ? '#1c0a0a' : '#fff',
      border: '2px solid #ef4444',
      borderRadius: 10, padding: '8px 12px', minWidth: 130,
      display: 'flex', flexDirection: 'column', gap: 4,
      fontFamily: "'Segoe UI',sans-serif",
      boxShadow: '0 3px 12px rgba(239,68,68,.25)',
      cursor: 'default', userSelect: 'none',
    }},
      React.createElement(Handle, { type: 'target', position: Position.Right, id: 'tgt', style: makeH('#ef4444') }),
      // Bill name row
      React.createElement('div', { style: { display: 'flex', alignItems: 'center', gap: 5 } },
        React.createElement('div', { style: {
          width: 20, height: 20, borderRadius: '50%', flexShrink: 0,
          background: col + '22', display: 'flex', alignItems: 'center', justifyContent: 'center',
        }},
          React.createElement('i', { className: `fa ${ico}`, style: { fontSize: 9, color: col } })
        ),
        React.createElement('span', { style: {
          fontWeight: 700, fontSize: 11,
          color: isDark ? '#fca5a5' : '#991b1b',
          whiteSpace: 'nowrap', overflow: 'hidden', textOverflow: 'ellipsis', maxWidth: 110,
        }}, b.name)
      ),
      // Due date
      React.createElement('div', { style: {
        fontSize: 10, fontWeight: 600,
        color: isDark ? '#fda4af' : '#dc2626',
        display: 'flex', alignItems: 'center', gap: 4,
      }},
        React.createElement('i', { className: 'fa fa-calendar-exclamation', style: { fontSize: 9 } }),
        due
      ),
      // Amount
      React.createElement('div', { style: {
        fontSize: 10, color: isDark ? '#9ca3af' : '#6b7280',
      }}, amt !== '—' ? amt : '')
    );
  };

  // ── Loans & Rentals hub nodes + item card nodes ───────────────────────────
  const C_LOAN = '#7c3aed', C_RENT = '#ea580c';

  const makeHubNode = (color, icon, label) => ({ data }) => {
    const exp = data.expanded;
    const bgLight = color === C_LOAN ? '#ede9fe' : '#fff7ed';
    const bgDark  = color === C_LOAN ? '#2e1065' : '#431407';
    const txtLight = color === C_LOAN ? '#5b21b6' : '#c2410c';
    const txtDark  = color === C_LOAN ? '#c4b5fd' : '#fdba74';
    return React.createElement('div', { style: {
      background: exp ? color : (isDark ? bgDark : bgLight),
      color: exp ? '#fff' : (isDark ? txtDark : txtLight),
      border: `1.5px solid ${color}`, borderRadius: 17,
      padding: '0 14px', height: 36,
      display: 'flex', alignItems: 'center', gap: 6,
      fontWeight: 700, fontSize: 11, fontFamily: "'Segoe UI',sans-serif",
      boxShadow: exp ? `0 0 0 3px ${color}40,0 3px 10px ${color}60` : '0 2px 8px rgba(0,0,0,.12)',
      cursor: 'pointer', userSelect: 'none', whiteSpace: 'nowrap',
    }},
      React.createElement(Handle, { type: 'target', position: Position.Right, id: 'tgt', style: makeH(color) }),
      ...[0,1,2,3,4,5,6,7].map(i =>
        React.createElement(Handle, { key: i, type: 'source', position: Position.Left, id: `src-l${i}`, style: makeH(color, { top: `${6+i*12}%` }) })
      ),
      React.createElement('i', { className: `fa ${icon}`, style: { fontSize: 10 } }),
      label,
      data.count > 0 && React.createElement('span', { style: {
        background: exp ? 'rgba(255,255,255,.25)' : color, color: '#fff',
        borderRadius: 9, padding: '1px 5px', fontSize: 9, fontWeight: 800, lineHeight: '14px',
      }}, data.count)
    );
  };
  const LoansHubNode   = makeHubNode(C_LOAN, 'fa-hand-holding-dollar', 'Loans');
  const RentalsHubNode = makeHubNode(C_RENT, 'fa-house', 'Rentals');

  const BillItemNode = ({ data }) => {
    const b = data.bill; const over = b.overdue;
    const bc = over ? '#ef4444' : '#4e8ef7';
    return React.createElement('div', { style: {
      background: isDark ? (over ? '#1c0a0a' : '#0f172a') : (over ? '#fff5f5' : '#f0f7ff'),
      border: `2px solid ${bc}`, borderRadius: 10, padding: '8px 12px', minWidth: 140,
      display: 'flex', flexDirection: 'column', gap: 3,
      fontFamily: "'Segoe UI',sans-serif",
      boxShadow: `0 3px 12px ${bc}30`, cursor: 'default', userSelect: 'none',
    }},
      React.createElement(Handle, { type: 'target', position: Position.Right, id: 'tgt',     style: makeH(bc) }),
      React.createElement(Handle, { type: 'source', position: Position.Top,   id: 'src-top', style: makeH(C_ASSET) }),
      React.createElement('div', { style: { display: 'flex', alignItems: 'center', gap: 5 } },
        React.createElement('i', { className: 'fa fa-file-invoice-dollar', style: { fontSize: 9, color: bc } }),
        React.createElement('span', { style: { fontWeight: 700, fontSize: 11, color: isDark ? (over ? '#fca5a5' : '#93c5fd') : (over ? '#991b1b' : '#1e3a8a'), whiteSpace: 'nowrap', overflow: 'hidden', textOverflow: 'ellipsis', maxWidth: 115 }}, b.name)
      ),
      over && React.createElement('div', { style: { fontSize: 10, fontWeight: 700, color: '#ef4444', display: 'flex', alignItems: 'center', gap: 3 } },
        React.createElement('i', { className: 'fa fa-triangle-exclamation', style: { fontSize: 9 } }), 'Overdue'
      ),
      React.createElement('div', { style: { fontSize: 10, color: isDark ? '#9ca3af' : '#6b7280' } }, `${b.amount_fmt} · ${b.cadence}`),
      data.assignedProperty && React.createElement('div', { style: { fontSize: 9, color: C_ASSET, display: 'flex', alignItems: 'center', gap: 3, marginTop: 1 } },
        React.createElement('i', { className: 'fa fa-building', style: { fontSize: 8 } }),
        data.assignedProperty.slice(0, 16)
      )
    );
  };

  const LoanItemNode = ({ data }) => {
    const l = data.loan;
    return React.createElement('div', { style: {
      background: isDark ? '#1e1b4b' : '#ede9fe',
      border: `2px solid ${C_LOAN}`, borderRadius: 10, padding: '8px 12px', minWidth: 140,
      display: 'flex', flexDirection: 'column', gap: 3,
      fontFamily: "'Segoe UI',sans-serif",
      boxShadow: `0 3px 12px ${C_LOAN}30`, cursor: 'default', userSelect: 'none',
    }},
      React.createElement(Handle, { type: 'target', position: Position.Right, id: 'tgt', style: makeH(C_LOAN) }),
      React.createElement('div', { style: { display: 'flex', alignItems: 'center', gap: 5 } },
        React.createElement('i', { className: 'fa fa-hand-holding-dollar', style: { fontSize: 9, color: C_LOAN } }),
        React.createElement('span', { style: { fontWeight: 700, fontSize: 11, color: isDark ? '#c4b5fd' : '#5b21b6', whiteSpace: 'nowrap', overflow: 'hidden', textOverflow: 'ellipsis', maxWidth: 115 }}, l.name)
      ),
      React.createElement('div', { style: { fontSize: 10, color: isDark ? '#9ca3af' : '#6b7280' } }, `${l.monthly_fmt} / mo · ${l.cadence}`)
    );
  };

  const RentalItemNode = ({ data }) => {
    const r = data.rental; const over = r.overdue;
    return React.createElement('div', { style: {
      background: isDark ? '#1c0a00' : '#fff7ed',
      border: `2px solid ${C_RENT}`, borderRadius: 10, padding: '8px 12px', minWidth: 140,
      display: 'flex', flexDirection: 'column', gap: 3,
      fontFamily: "'Segoe UI',sans-serif",
      boxShadow: `0 3px 12px ${C_RENT}30`, cursor: 'default', userSelect: 'none',
    }},
      React.createElement(Handle, { type: 'target', position: Position.Right, id: 'tgt', style: makeH(C_RENT) }),
      React.createElement('div', { style: { display: 'flex', alignItems: 'center', gap: 5 } },
        React.createElement('i', { className: 'fa fa-house', style: { fontSize: 9, color: C_RENT } }),
        React.createElement('span', { style: { fontWeight: 700, fontSize: 11, color: isDark ? '#fdba74' : '#c2410c', whiteSpace: 'nowrap', overflow: 'hidden', textOverflow: 'ellipsis', maxWidth: 115 }}, r.name)
      ),
      over && React.createElement('div', { style: { fontSize: 10, fontWeight: 700, color: '#ef4444', display: 'flex', alignItems: 'center', gap: 3 } },
        React.createElement('i', { className: 'fa fa-triangle-exclamation', style: { fontSize: 9 } }), 'Overdue'
      ),
      React.createElement('div', { style: { fontSize: 10, color: isDark ? '#9ca3af' : '#6b7280' } }, `${r.cost_fmt} · ${r.cadence}`)
    );
  };

  // ── Assets hub + item nodes ───────────────────────────────────────────────
  const C_ASSET = '#d97706';

  const AssetsHubNode = ({ data }) => {
    const exp = data.expanded;
    return React.createElement('div', { style: {
      background: exp ? C_ASSET : (isDark ? '#451a03' : '#fef3c7'),
      color: exp ? '#fff' : (isDark ? '#fcd34d' : '#92400e'),
      border: `1.5px solid ${C_ASSET}`, borderRadius: 20,
      padding: '0 18px', height: 40,
      display: 'flex', alignItems: 'center', gap: 6,
      fontWeight: 700, fontSize: 12, fontFamily: "'Segoe UI',sans-serif",
      boxShadow: exp ? `0 0 0 3px ${C_ASSET}40,0 3px 10px ${C_ASSET}60` : '0 2px 8px rgba(0,0,0,.12)',
      cursor: 'pointer', userSelect: 'none', whiteSpace: 'nowrap',
    }},
      React.createElement(Handle, { type: 'target', position: Position.Top,  id: 'tgt-t', style: makeH(C_ASSET) }),
      ...[0,1,2,3,4,5].map(i =>
        React.createElement(Handle, { key: i, type: 'source', position: Position.Right, id: `src-r${i}`, style: makeH(C_ASSET, { top: `${8+i*16}%` }) })
      ),
      React.createElement('i', { className: 'fa fa-coins', style: { fontSize: 10 } }),
      'Assets',
      data.count > 0 && React.createElement('span', { style: {
        background: exp ? 'rgba(255,255,255,.25)' : C_ASSET, color: '#fff',
        borderRadius: 9, padding: '1px 5px', fontSize: 9, fontWeight: 800, lineHeight: '14px',
      }}, data.count)
    );
  };

  const AssetItemNode = ({ data }) => {
    const p = data.property;
    const alert = p.expired || p.expiring_soon;
    const ac = p.expired ? '#ef4444' : p.expiring_soon ? '#f59e0b' : C_ASSET;
    return React.createElement('div', { style: {
      background: isDark ? '#1c1100' : '#fffbeb',
      border: `2px solid ${ac}`, borderRadius: 10, padding: '8px 12px', minWidth: 140,
      display: 'flex', flexDirection: 'column', gap: 3,
      fontFamily: "'Segoe UI',sans-serif",
      boxShadow: `0 3px 12px ${ac}30`, cursor: 'default', userSelect: 'none',
    }},
      React.createElement(Handle, { type: 'target', position: Position.Left, id: 'tgt',     style: makeH(ac) }),
      React.createElement(Handle, { type: 'target', position: Position.Top,  id: 'tgt-top', style: makeH(C_ASSET) }),
      React.createElement('div', { style: { display: 'flex', alignItems: 'center', gap: 5 } },
        React.createElement('i', { className: 'fa fa-building', style: { fontSize: 9, color: ac } }),
        React.createElement('span', { style: { fontWeight: 700, fontSize: 11, color: isDark ? '#fcd34d' : '#92400e', whiteSpace: 'nowrap', overflow: 'hidden', textOverflow: 'ellipsis', maxWidth: 115 }}, p.property_name)
      ),
      alert && React.createElement('div', { style: { fontSize: 10, fontWeight: 700, color: ac, display: 'flex', alignItems: 'center', gap: 3 } },
        React.createElement('i', { className: 'fa fa-triangle-exclamation', style: { fontSize: 9 } }),
        p.expired ? 'Expired' : 'Expiring soon'
      ),
      React.createElement('div', { style: { fontSize: 10, color: isDark ? '#9ca3af' : '#6b7280' } }, `${p.property_type} · ${p.cost_fmt}`)
    );
  };

  // ── Initial static nodes ───────────────────────────────────────────────────
  const initNodes = [
    // ── Core hubs ─────────────────────────────────────────────────────────────
    { id: 'root',   type: 'rootNode',      position: { x: 500, y: 400 }, data: { label: bizName } },
    { id: 'exp',    type: 'expHubNode',    position: { x: 270, y: 400 }, data: { label: 'Expenses' } },
    { id: 'inc',    type: 'incHubNode',    position: { x: 740, y: 200 }, data: { label: 'Income'   } },
    { id: 'assets', type: 'assetsHubNode', position: { x: 740, y: 580 }, data: { expanded: false, count: 0 } },
    // ── Expense sub-hubs — each aligned with its own y-band ───────────────────
    { id: 'e2', type: 'billsHubNode',   position: { x:  90, y: 140 }, data: { label: 'Bills',   expanded: false, dueCount: 0 } },
    { id: 'e0', type: 'loansHubNode',   position: { x:  90, y: 400 }, data: { expanded: false, count: 0 } },
    { id: 'e1', type: 'rentalsHubNode', position: { x:  90, y: 660 }, data: { expanded: false, count: 0 } },
    // ── Expense static leaves — spread above, connected via top handles ────────
    { id: 'e3', type: 'leafNode', position: { x:  30, y: -90 }, data: { label: 'Employee Salary', variant: 'exp', ts: 'bottom' } },
    { id: 'e4', type: 'leafNode', position: { x: 150, y:-110 }, data: { label: 'Modification',    variant: 'exp', ts: 'bottom' } },
    { id: 'e5', type: 'leafNode', position: { x: 280, y:-120 }, data: { label: 'Purchase Order',  variant: 'exp', ts: 'bottom' } },
    { id: 'e6', type: 'leafNode', position: { x: 400, y:-110 }, data: { label: 'Marketing',       variant: 'exp', ts: 'bottom' } },
    { id: 'e7', type: 'leafNode', position: { x: 510, y: -90 }, data: { label: 'Legal',           variant: 'exp', ts: 'bottom' } },
    // ── Income leaves ─────────────────────────────────────────────────────────
    { id: 'i0', type: 'leafNode', position: { x: 740, y:  55 }, data: { label: 'POS Sales',       variant: 'inc', ts: 'bottom' } },
    { id: 'i1', type: 'leafNode', position: { x: 920, y: 120 }, data: { label: 'Quotations',      variant: 'inc', ts: 'left'   } },
    { id: 'i2', type: 'leafNode', position: { x: 930, y: 300 }, data: { label: 'Invoices',        variant: 'inc', ts: 'left'   } },
    { id: 'i3', type: 'leafNode', position: { x: 820, y: 420 }, data: { label: 'Credit Recovery', variant: 'inc', ts: 'top'    } },
  ];

  const mGray  = { type: MarkerType.ArrowClosed, color: isDark ? '#94a3b8' : '#64748b', width: 14, height: 14 };
  const mRed   = { type: MarkerType.ArrowClosed, color: isDark ? '#f87171' : '#ef4444', width: 12, height: 12 };
  const mGreen = { type: MarkerType.ArrowClosed, color: isDark ? '#4ade80' : '#22c55e', width: 12, height: 12 };
  const mAmber = { type: MarkerType.ArrowClosed, color: C_ASSET, width: 12, height: 12 };
  const sGray  = { stroke: isDark ? '#94a3b8' : '#64748b', strokeWidth: 2.5 };
  const sRed   = { stroke: isDark ? '#f87171' : '#ef4444', strokeWidth: 1.8 };
  const sGreen = { stroke: isDark ? '#4ade80' : '#22c55e', strokeWidth: 1.8 };
  const sAmber = { stroke: C_ASSET, strokeWidth: 2.5 };
  const ss     = 'smoothstep';

  const initEdges = [
    // Root → main hubs
    { id: 're',    source: 'root', sourceHandle: 'src-l',  target: 'exp',    targetHandle: 'tgt-r', type: ss, style: sGray,  markerEnd: mGray  },
    { id: 'ri',    source: 'root', sourceHandle: 'src-r',  target: 'inc',    targetHandle: 'tgt-l', type: ss, style: sGray,  markerEnd: mGray  },
    { id: 'ra',    source: 'root', sourceHandle: 'src-b',  target: 'assets', targetHandle: 'tgt-t', type: ss, style: sAmber, markerEnd: mAmber },
    // Expenses → 5 static leaves via top handles (fan upward, no crossings)
    { id: 'ex-e3', source: 'exp',  sourceHandle: 'src-t0', target: 'e3',  targetHandle: 'tgt',   type: ss, style: sRed,   markerEnd: mRed   },
    { id: 'ex-e4', source: 'exp',  sourceHandle: 'src-t1', target: 'e4',  targetHandle: 'tgt',   type: ss, style: sRed,   markerEnd: mRed   },
    { id: 'ex-e5', source: 'exp',  sourceHandle: 'src-t2', target: 'e5',  targetHandle: 'tgt',   type: ss, style: sRed,   markerEnd: mRed   },
    { id: 'ex-e6', source: 'exp',  sourceHandle: 'src-t3', target: 'e6',  targetHandle: 'tgt',   type: ss, style: sRed,   markerEnd: mRed   },
    { id: 'ex-e7', source: 'exp',  sourceHandle: 'src-t4', target: 'e7',  targetHandle: 'tgt',   type: ss, style: sRed,   markerEnd: mRed   },
    // Expenses → 3 sub-hubs via left handles (each in its own y-band)
    { id: 'ex-e2', source: 'exp',  sourceHandle: 'src-l0', target: 'e2',  targetHandle: 'tgt',   type: ss, style: sRed,   markerEnd: mRed   },
    { id: 'ex-e0', source: 'exp',  sourceHandle: 'src-l1', target: 'e0',  targetHandle: 'tgt',   type: ss, style: sRed,   markerEnd: mRed   },
    { id: 'ex-e1', source: 'exp',  sourceHandle: 'src-l2', target: 'e1',  targetHandle: 'tgt',   type: ss, style: sRed,   markerEnd: mRed   },
    // Income leaves
    { id: 'in-i0', source: 'inc',  sourceHandle: 'src-t0', target: 'i0',  targetHandle: 'tgt',   type: ss, style: sGreen, markerEnd: mGreen },
    { id: 'in-i1', source: 'inc',  sourceHandle: 'src-r0', target: 'i1',  targetHandle: 'tgt',   type: ss, style: sGreen, markerEnd: mGreen },
    { id: 'in-i2', source: 'inc',  sourceHandle: 'src-r1', target: 'i2',  targetHandle: 'tgt',   type: ss, style: sGreen, markerEnd: mGreen },
    { id: 'in-i3', source: 'inc',  sourceHandle: 'src-b0', target: 'i3',  targetHandle: 'tgt',   type: ss, style: sGreen, markerEnd: mGreen },
  ];

  // ── Main React Flow component (controlled state) ──────────────────────────
  const FlowApp = () => {
    const [nodes, setNodes, onNodesChange] = useNodesState(initNodes);
    const [edges, setEdges, onEdgesChange] = useEdgesState(initEdges);
    const [rfInst, setRfInst] = React.useState(null);

    // Fit view on init + resize
    React.useEffect(() => {
      if (!rfInst) return;
      const fit = () => rfInst.fitView({ padding: 0.14, duration: 250 });
      const t = setTimeout(fit, 80);
      window.addEventListener('resize', fit);
      return () => { clearTimeout(t); window.removeEventListener('resize', fit); };
    }, [rfInst]);

    // Load all bills, loans, rentals, properties and expand hub nodes
    React.useEffect(() => {
      if (!rfInst) return;
      Promise.all([API.financeFlow(), API.propertyList()]).then(([flowRes, propRes]) => {
        if (flowRes.status !== 200) return;
        const { bills, loans, rentals } = flowRes.body;
        const properties = propRes.status === 200 ? (propRes.body?.data || []) : [];

        const sB = { stroke: '#ef4444', strokeWidth: 1.8, strokeDasharray: '5 3' };
        const mB = { type: MarkerType.ArrowClosed, color: '#ef4444', width: 10, height: 10 };
        const sL = { stroke: C_LOAN,   strokeWidth: 1.5, strokeDasharray: '5 3' };
        const mL = { type: MarkerType.ArrowClosed, color: C_LOAN, width: 10, height: 10 };
        const sR = { stroke: C_RENT,   strokeWidth: 1.5, strokeDasharray: '5 3' };
        const mR = { type: MarkerType.ArrowClosed, color: C_RENT, width: 10, height: 10 };

        const newNodes = [], newEdges = [];

        // Build property name map for bill assignment badges
        const propNameMap = new Map(properties.map(p => [p.id, p.property_name]));

        // ── Bills — y-band centred at 140, single column x=-180 ──────────────
        const bSlice = bills.slice(0, 5);
        const vB = 78, syB = 140 - ((bSlice.length - 1) * vB) / 2;
        bSlice.forEach((b, i) => {
          const assignedProperty = b.property_id ? (propNameMap.get(b.property_id) || null) : null;
          newNodes.push({ id: `db-${b.id}`, type: 'billItemNode', position: { x: -180, y: syB + i * vB }, data: { bill: b, assignedProperty } });
          newEdges.push({ id: `dbe-${b.id}`, source: 'e2', sourceHandle: `src-b${i}`, target: `db-${b.id}`, targetHandle: 'tgt', type: ss, style: sB, markerEnd: mB, animated: b.overdue });
        });

        // ── Loans — y-band centred at 400, same column x=-180 ────────────────
        const lSlice = loans.slice(0, 5);
        const vL = 78, syL = 400 - ((lSlice.length - 1) * vL) / 2;
        lSlice.forEach((l, i) => {
          newNodes.push({ id: `dl-${l.id}`, type: 'loanItemNode', position: { x: -180, y: syL + i * vL }, data: { loan: l } });
          newEdges.push({ id: `dle-${l.id}`, source: 'e0', sourceHandle: `src-l${i}`, target: `dl-${l.id}`, targetHandle: 'tgt', type: ss, style: sL, markerEnd: mL, animated: false });
        });

        // ── Rentals — y-band centred at 660, same column x=-180 ──────────────
        const rSlice = rentals.slice(0, 5);
        const vR = 78, syR = 660 - ((rSlice.length - 1) * vR) / 2;
        rSlice.forEach((r, i) => {
          newNodes.push({ id: `dr-${r.id}`, type: 'rentalItemNode', position: { x: -180, y: syR + i * vR }, data: { rental: r } });
          newEdges.push({ id: `dre-${r.id}`, source: 'e1', sourceHandle: `src-l${i}`, target: `dr-${r.id}`, targetHandle: 'tgt', type: ss, style: sR, markerEnd: mR, animated: r.overdue });
        });

        // ── Properties — column to the RIGHT of Assets hub at x=910 ──────────
        const sA = { stroke: C_ASSET, strokeWidth: 1.5, strokeDasharray: '5 3' };
        const mA = { type: MarkerType.ArrowClosed, color: C_ASSET, width: 10, height: 10 };
        const pSlice = properties.slice(0, 5);
        const vP = 78, syP = 580 - ((pSlice.length - 1) * vP) / 2;
        const propIds = new Set(pSlice.map(p => p.id));
        pSlice.forEach((p, i) => {
          newNodes.push({ id: `dp-${p.id}`, type: 'assetItemNode', position: { x: 910, y: syP + i * vP }, data: { property: p } });
          newEdges.push({ id: `dpe-${p.id}`, source: 'assets', sourceHandle: `src-r${i}`, target: `dp-${p.id}`, targetHandle: 'tgt', type: ss, style: sA, markerEnd: mA, animated: p.expired });
        });

        // Bill → Property arc edges (routed above the chart, no crossings)
        bSlice.forEach(b => {
          if (b.property_id && propIds.has(b.property_id)) {
            newEdges.push({
              id: `dbpe-${b.id}`,
              source: `db-${b.id}`,  sourceHandle: 'src-top',
              target: `dp-${b.property_id}`, targetHandle: 'tgt-top',
              type: 'billProperty',
            });
          }
        });

        if (!newNodes.length) return;

        setNodes(ns => [
          ...ns.map(n => {
            if (n.id === 'e2')     return { ...n, data: { ...n.data, expanded: !!bSlice.length, dueCount: bills.filter(b => b.overdue).length } };
            if (n.id === 'e0')     return { ...n, data: { ...n.data, expanded: !!lSlice.length, count: lSlice.length } };
            if (n.id === 'e1')     return { ...n, data: { ...n.data, expanded: !!rSlice.length, count: rSlice.length } };
            if (n.id === 'assets') return { ...n, data: { ...n.data, expanded: !!pSlice.length, count: pSlice.length } };
            return n;
          }),
          ...newNodes,
        ]);
        setEdges(es => [...es, ...newEdges]);
        setTimeout(() => rfInst.fitView({ padding: 0.14, duration: 400 }), 80);
      });
    }, [rfInst]);

    const onNodeClick = React.useCallback((_e, node) => {
      const actions = {
        root:   () => {},
        exp:    () => activateTab('finance'),
        inc:    () => activateTab('pos'),
        assets: () => { activateTab('finance'); switchFinView('properties'); },
        e0:   () => { activateTab('finance'); switchFinView('loans'); },
        e1:   () => { activateTab('finance'); switchFinView('rentals'); },
        e2:   () => { activateTab('finance'); switchFinView('bills'); },
        e3:   () => toast('Payroll coming soon', 'info'),
        e4:   () => toast('Modifications coming soon', 'info'),
        e5:   () => activateTab('inventory'),
        e6:   () => toast('Marketing coming soon', 'info'),
        e7:   () => toast('Legal coming soon', 'info'),
        i0:   () => activateTab('pos'),
        i1:   () => toast('Quotations coming soon', 'info'),
        i2:   () => toast('Invoices coming soon', 'info'),
        i3:   () => toast('Credit Recovery coming soon', 'info'),
      };
      if (node.id.startsWith('db-')) { activateTab('finance'); switchFinView('bills');      return; }
      if (node.id.startsWith('dl-')) { activateTab('finance'); switchFinView('loans');      return; }
      if (node.id.startsWith('dr-')) { activateTab('finance'); switchFinView('rentals');    return; }
      if (node.id.startsWith('dp-')) { activateTab('finance'); switchFinView('properties'); return; }
      const act = actions[node.id];
      if (act) act();
    }, []);

    // ── Bill→Property arc edge (routes ABOVE the chart to avoid all crossings) ─
    const BillPropertyEdge = ({ id, sourceX, sourceY, targetX, targetY }) => {
      const arcY = Math.min(sourceY, targetY) - 280;
      const mid  = { x: (sourceX + targetX) / 2, y: arcY - 10 };
      const d    = `M${sourceX},${sourceY} C${sourceX},${arcY} ${targetX},${arcY} ${targetX},${targetY}`;
      const mId  = `ap-mk-${id}`;
      return React.createElement('g', null,
        React.createElement('defs', null,
          React.createElement('marker', { id: mId, markerWidth: 10, markerHeight: 7, refX: 9, refY: 3.5, orient: 'auto' },
            React.createElement('polygon', { points: '0 0,10 3.5,0 7', fill: C_ASSET })
          )
        ),
        React.createElement('path', { d, fill: 'none', stroke: C_ASSET, strokeWidth: 1.5, strokeDasharray: '5 3', opacity: 0.75, markerEnd: `url(#${mId})` }),
        React.createElement('text', { x: mid.x, y: mid.y, textAnchor: 'middle', style: { fontSize: 9, fill: C_ASSET, fontWeight: 600, userSelect: 'none' } }, 'assigned to')
      );
    };

    const nodeTypes = React.useMemo(() => ({
      rootNode:      RootNode,
      expHubNode:    ExpHubNode,
      incHubNode:    IncHubNode,
      leafNode:      LeafNode,
      billsHubNode:  BillsHubNode,
      dueBillNode:   DueBillNode,
      loansHubNode:  LoansHubNode,
      rentalsHubNode: RentalsHubNode,
      billItemNode:  BillItemNode,
      loanItemNode:  LoanItemNode,
      rentalItemNode: RentalItemNode,
      assetsHubNode:  AssetsHubNode,
      assetItemNode:  AssetItemNode,
    }), []);

    const edgeTypes = React.useMemo(() => ({ billProperty: BillPropertyEdge }), []);

    return React.createElement(ReactFlowComp, {
      nodes, edges, onNodesChange, onEdgesChange,
      nodeTypes, edgeTypes,
      fitView: true, fitViewOptions: { padding: 0.12 },
      minZoom: 0.15, maxZoom: 2.5,
      nodesDraggable: true, nodesConnectable: false, elementsSelectable: false,
      zoomOnScroll: true, zoomOnPinch: true, panOnDrag: true, panOnScroll: false,
      proOptions: { hideAttribution: true },
      onInit: setRfInst,
      onNodeClick,
      style: { background: isDark ? '#1a1b2e' : '#f8fafc', width: '100%', height: '100%' },
    },
      React.createElement(Controls, { showInteractive: false })
    );
  };

  if (!container._rfRoot) container._rfRoot = window.ReactDOM.createRoot(container);
  container._rfRoot.render(React.createElement(FlowApp));
}

// ── Inventory panel ────────────────────────────────────────────────────────
const invState    = { search: '', catId: 0, page: 1, total: 0, loaded: false, stockStatus: '', brandId: 0, sort: 'name_asc' };
const invSelected = new Set();

function _invUpdateBulkBar() {
  const n   = invSelected.size;
  const bar = $('#inv-bulk-bar');
  bar.style.display = n ? 'flex' : 'none';
  $('#inv-bulk-count').textContent = `${n} selected`;
  const allChecks = $$('.inv-row-check');
  const selAll    = $('#inv-select-all');
  if (selAll) {
    selAll.indeterminate = n > 0 && n < allChecks.length;
    selAll.checked       = allChecks.length > 0 && n >= allChecks.length;
  }
}

async function loadInventory(search = invState.search, catId = invState.catId, page = 1) {
  invState.search = search;
  invState.catId  = catId;
  invState.page   = page;

  // Clear selection on each load
  invSelected.clear();
  _invUpdateBulkBar();

  const tbody = $('#inv-tbody');
  tbody.innerHTML = `<tr><td colspan="7" class="inv-loading"><i class="fa fa-spinner fa-spin"></i> Loading products…</td></tr>`;

  const filters = {
    stockStatus: invState.stockStatus || null,
    brandId:     invState.brandId     || null,
    sort:        invState.sort        || 'name_asc',
  };
  const res = await API.bootstrap(search, catId, page, filters);
  if (res.status !== 200) {
    tbody.innerHTML = `<tr><td colspan="6" class="inv-loading" style="color:#e74c3c"><i class="fa fa-circle-exclamation"></i> Failed to load products</td></tr>`;
    return;
  }

  const body = res.body?.data || res.body || {};
  const products   = body.products   || [];
  const categories = body.categories || [];
  const brands     = body.brands     || [];
  const meta       = body.products_meta || body.meta || {};
  const total      = meta.total     || products.length;
  const last_page  = meta.last_page || 1;

  // Category filter chips
  if (!invState.loaded || categories.length) {
    const bar = $('#inv-cat-filter');
    bar.innerHTML = `<div class="inv-chip ${catId === 0 ? 'active' : ''}" data-cat="0">All</div>` +
      categories.map(c => `<div class="inv-chip ${c.id === catId ? 'active' : ''}" data-cat="${c.id}">${escHtml(c.name)}</div>`).join('');
    bar.querySelectorAll('.inv-chip').forEach(chip =>
      chip.addEventListener('click', () => loadInventory(invState.search, Number(chip.dataset.cat)))
    );

    // Populate brand dropdown on first load (or when brands list arrives)
    if (brands.length) {
      const sel = $('#inv-brand-filter');
      const cur = sel.value;
      sel.innerHTML = `<option value="">All Brands</option>` +
        brands.map(b => `<option value="${b.id}">${escHtml(b.name)}</option>`).join('');
      sel.value = cur; // restore selection
    }

    invState.loaded = true;
  }

  // Count & pagination
  const totalItems = total || products.length;
  $('#inv-count').textContent = `${totalItems} product${totalItems !== 1 ? 's' : ''}`;
  buildInvPagination(page, last_page);

  // Table rows
  if (!products.length) {
    tbody.innerHTML = `<tr><td colspan="7" class="inv-loading"><i class="fa fa-box-open" style="opacity:.3"></i>&nbsp; No products found</td></tr>`;
    return;
  }

  // Build a category map for display
  const catMap = {};
  categories.forEach(c => { catMap[c.id] = c.name; });

  tbody.innerHTML = products.map(p => {
    const stock = p.stock_quantity;
    const hasStock = stock != null;
    const low  = hasStock && stock > 0 && stock <= 5;
    const out  = hasStock && stock <= 0;
    const statusClass = out ? 'inv-status-out' : low ? 'inv-status-low' : 'inv-status-ok';
    const statusText  = out ? 'Out of stock' : low ? 'Low stock' : 'In stock';
    const imgSrc = p.image_url || p.image || null;
    const img = imgSrc
      ? `<img src="${imgSrc}" alt="${escHtml(p.name)}" class="inv-thumb">`
      : `<div class="inv-thumb-ph"><i class="fa fa-box"></i></div>`;
    const catId0 = Array.isArray(p.category_ids) ? p.category_ids[0] : p.category_id;
    const catName = catMap[catId0] || p.category?.name || '—';
    const price = p.discounted_sell_price ?? p.unit_sell_price ?? p.price ?? 0;
    return `
      <tr class="inv-row" data-id="${p.id}">
        <td class="inv-td-check"><input type="checkbox" class="inv-row-check inv-chk" data-id="${p.id}"></td>
        <td>${img}</td>
        <td><span class="inv-name">${escHtml(p.name)}</span>${p.sku ? `<br><span class="inv-sku">${escHtml(p.sku)}</span>` : ''}</td>
        <td class="inv-cat">${escHtml(catName)}</td>
        <td class="inv-price">${parseFloat(price).toFixed(2)}</td>
        <td class="inv-stock-cell">${hasStock ? stock : '—'}</td>
        <td><span class="inv-status ${statusClass}">${hasStock ? statusText : '—'}</span></td>
      </tr>`;
  }).join('');
}

function buildInvPagination(current, last) {
  const pg = $('#inv-pagination');
  if (last <= 1) { pg.innerHTML = ''; return; }
  const pages = [];
  for (let i = Math.max(1, current - 2); i <= Math.min(last, current + 2); i++) pages.push(i);
  pg.innerHTML =
    (current > 1 ? `<button class="inv-pg-btn" data-p="${current - 1}"><i class="fa fa-chevron-left"></i></button>` : '') +
    pages.map(p => `<button class="inv-pg-btn ${p === current ? 'active' : ''}" data-p="${p}">${p}</button>`).join('') +
    (current < last ? `<button class="inv-pg-btn" data-p="${current + 1}"><i class="fa fa-chevron-right"></i></button>` : '');
  pg.querySelectorAll('.inv-pg-btn').forEach(btn =>
    btn.addEventListener('click', () => loadInventory(invState.search, invState.catId, Number(btn.dataset.p)))
  );
}

// Inventory search + refresh + filters
let invSearchTimer;
$('#inv-search').addEventListener('input', e => {
  clearTimeout(invSearchTimer);
  invSearchTimer = setTimeout(() => loadInventory(e.target.value, invState.catId), 350);
});
$('#inv-refresh').addEventListener('click', () => { invState.loaded = false; loadInventory('', 0); });

$('#inv-stock-filter').addEventListener('change', e => {
  invState.stockStatus = e.target.value;
  loadInventory(invState.search, invState.catId, 1);
});
$('#inv-brand-filter').addEventListener('change', e => {
  invState.brandId = Number(e.target.value) || 0;
  loadInventory(invState.search, invState.catId, 1);
});
$('#inv-sort-filter').addEventListener('change', e => {
  invState.sort = e.target.value;
  loadInventory(invState.search, invState.catId, 1);
});
$('#inv-filter-clear').addEventListener('click', () => {
  invState.stockStatus = '';
  invState.brandId     = 0;
  invState.sort        = 'name_asc';
  $('#inv-stock-filter').value = '';
  $('#inv-brand-filter').value = '';
  $('#inv-sort-filter').value  = 'name_asc';
  loadInventory(invState.search, invState.catId, 1);
});

// Row click → product detail
$('#inv-tbody').addEventListener('click', e => {
  // Checkbox click — toggle selection, don't open detail
  if (e.target.matches('.inv-row-check')) {
    const id  = e.target.dataset.id;
    const row = e.target.closest('.inv-row');
    if (e.target.checked) invSelected.add(id); else invSelected.delete(id);
    row.classList.toggle('inv-row-selected', e.target.checked);
    _invUpdateBulkBar();
    return;
  }
  const row = e.target.closest('.inv-row');
  if (!row) return;
  const id   = row.dataset.id;
  const name = row.querySelector('.inv-name')?.textContent || '';
  openProductDetail(id, name);
});

$('#inv-select-all').addEventListener('change', e => {
  const checked = e.target.checked;
  $$('.inv-row-check').forEach(chk => {
    chk.checked = checked;
    const id = chk.dataset.id;
    if (checked) invSelected.add(id); else invSelected.delete(id);
    chk.closest('.inv-row').classList.toggle('inv-row-selected', checked);
  });
  _invUpdateBulkBar();
});

async function _invBulkDelete() {
  const ids = [...invSelected];
  if (!ids.length) return;
  if (!confirm(`Delete ${ids.length} product${ids.length > 1 ? 's' : ''}? This cannot be undone.`)) return;
  const results = await Promise.all(ids.map(id => API.deleteProduct(Number(id))));
  const failed  = results.filter(r => r.status !== 200).length;
  invSelected.clear();
  invState.loaded = false;
  loadInventory(invState.search, invState.catId, 1);
  if (failed) toast(`${ids.length - failed} deleted, ${failed} failed`, 'error');
  else toast(`${ids.length} product${ids.length > 1 ? 's' : ''} deleted`, 'success');
}

function _invExportCsv() {
  const ids = new Set([...invSelected]);
  const rows = [...$$('#inv-tbody .inv-row')]
    .filter(r => ids.has(r.dataset.id))
    .map(r => {
      const name  = r.querySelector('.inv-name')?.textContent      || '';
      const sku   = r.querySelector('.inv-sku')?.textContent       || '';
      const cat   = r.querySelector('.inv-cat')?.textContent       || '';
      const price = r.querySelector('.inv-price')?.textContent     || '';
      const stock = r.querySelector('.inv-stock-cell')?.textContent || '';
      return [name, sku, cat, price, stock].map(v => `"${v.replace(/"/g, '""')}"`).join(',');
    });
  const csv  = ['Name,SKU,Category,Price,Stock', ...rows].join('\n');
  const blob = new Blob([csv], { type: 'text/csv' });
  const url  = URL.createObjectURL(blob);
  const a    = document.createElement('a');
  a.href     = url;
  a.download = `products-${new Date().toISOString().slice(0, 10)}.csv`;
  a.click();
  URL.revokeObjectURL(url);
}

$('#inv-bulk-delete').addEventListener('click',   _invBulkDelete);
$('#inv-bulk-export').addEventListener('click',   _invExportCsv);
$('#inv-bulk-deselect').addEventListener('click', () => {
  invSelected.clear();
  $$('.inv-row-check').forEach(chk => { chk.checked = false; });
  $$('.inv-row-selected').forEach(r => r.classList.remove('inv-row-selected'));
  _invUpdateBulkBar();
});

// Back button — return to list
$('#inv-back-btn').addEventListener('click', () => {
  $('#inv-detail-view').style.display = 'none';
  $('#inv-list-view').style.display   = 'flex';
});

// ── Inventory sub-nav switching ───────────────────────────────────────────
function switchInvView(view) {
  const views = { products: '#inv-products-view', suppliers: '#inv-suppliers-view', po: '#inv-po-view', grn: '#inv-grn-view', cheques: '#inv-cheques-view', audit: '#inv-audit-view', categories: '#inv-categories-view', units: '#inv-units-view', discounts: '#inv-discounts-view', brands: '#inv-brands-view', barcodes: '#inv-barcodes-view' };
  Object.entries(views).forEach(([k, sel]) => {
    const el = $(sel);
    if (el) el.style.display = k === view ? 'flex' : 'none';
  });
  $$('.inv-subnav-btn').forEach(btn => btn.classList.toggle('active', btn.dataset.invView === view));
  if (view === 'products')  loadInventory();
  if (view === 'suppliers') _supLoad();
  if (view === 'po')        _poLoad();
  if (view === 'grn')       _grnLoad();
  if (view === 'cheques')   _chqLoad();
  if (view === 'audit')      _auditLoad();
  if (view === 'categories') _catLoad();
  if (view === 'units')      _unitLoad();
  if (view === 'discounts')  _discLoad();
  if (view === 'brands')     _brandLoad();
  if (view === 'barcodes')   _barcodeLoad();
}

$$('.inv-subnav-btn').forEach(btn => {
  btn.addEventListener('click', () => switchInvView(btn.dataset.invView));
});

// ── Supplier Management ───────────────────────────────────────────────────
const _sup = { page: 1, lastPage: 1, total: 0, q: '', timer: null, editingId: null };

async function _supLoad() {
  const tbody = $('#sup-tbody');
  tbody.innerHTML = `<tr><td colspan="7" class="inv-loading"><i class="fa fa-spinner fa-spin"></i> Loading…</td></tr>`;
  const res = await API.suppliers(_sup.q, _sup.page);
  if (res.status !== 200) {
    tbody.innerHTML = `<tr><td colspan="7" class="inv-loading" style="color:#ef4444"><i class="fa fa-triangle-exclamation"></i> Failed to load</td></tr>`;
    return;
  }
  const list      = res.body?.data ?? [];
  _sup.lastPage   = res.body?.meta?.last_page ?? 1;
  _sup.total      = res.body?.meta?.total ?? list.length;
  _supRenderTable(list);
  _supRenderPagination();
  $('#sup-count').textContent = `${_sup.total} supplier${_sup.total !== 1 ? 's' : ''}`;
}

function _supRenderTable(list) {
  const tbody = $('#sup-tbody');
  if (!list.length) {
    tbody.innerHTML = `<tr><td colspan="7" class="inv-loading"><i class="fa fa-building-user" style="font-size:22px;display:block;margin-bottom:6px;opacity:.3"></i>${_sup.q ? 'No suppliers match your search' : 'No suppliers yet — click <strong>New Supplier</strong> to add one'}</td></tr>`;
    return;
  }
  tbody.innerHTML = list.map(s => `
    <tr class="inv-row" data-id="${s.id}">
      <td>
        <div style="font-weight:700;font-size:13px">${escHtml(s.name)}</div>
        ${s.address ? `<div style="font-size:11px;color:var(--text-muted)">${escHtml(s.address)}</div>` : ''}
      </td>
      <td>${s.contact_name ? escHtml(s.contact_name) : '<span style="color:var(--text-light)">—</span>'}</td>
      <td>${s.phone       ? escHtml(s.phone)         : '<span style="color:var(--text-light)">—</span>'}</td>
      <td>${s.email       ? escHtml(s.email)         : '<span style="color:var(--text-light)">—</span>'}</td>
      <td style="text-align:center"><span class="sup-orders-count">${s.purchases_count ?? 0}</span></td>
      <td style="text-align:center">
        <span class="sup-status-badge ${s.is_active ? 'active' : 'inactive'}">
          <i class="fa fa-circle" style="font-size:6px"></i> ${s.is_active ? 'Active' : 'Inactive'}
        </span>
      </td>
      <td style="text-align:right">
        <button class="sup-action-btn sup-edit-btn" data-id="${s.id}" title="Edit"><i class="fa fa-pen"></i></button>
        <button class="sup-action-btn del sup-del-btn" data-id="${s.id}" data-name="${escHtml(s.name)}" title="${s.is_active ? 'Deactivate' : 'Already inactive'}" ${!s.is_active ? 'disabled style="opacity:.35"' : ''}><i class="fa fa-ban"></i></button>
      </td>
    </tr>`).join('');

  tbody.querySelectorAll('.sup-edit-btn').forEach(btn => {
    btn.addEventListener('click', e => { e.stopPropagation(); _supOpenModal(Number(btn.dataset.id)); });
  });
  tbody.querySelectorAll('.sup-del-btn').forEach(btn => {
    btn.addEventListener('click', e => {
      e.stopPropagation();
      const name = btn.dataset.name;
      if (!confirm(`Deactivate "${name}"? They will no longer appear in active supplier lists.`)) return;
      API.deleteSupplier(Number(btn.dataset.id)).then(res => {
        if (res.status !== 200) { toast('Failed to deactivate supplier', 'error'); return; }
        toast(`"${name}" deactivated`, 'success');
        _supLoad();
      });
    });
  });
}

function _supRenderPagination() {
  const el = $('#sup-pagination');
  if (_sup.lastPage <= 1) { el.innerHTML = ''; return; }
  el.innerHTML = `
    <button class="cm-page-btn" id="sup-pg-prev" ${_sup.page <= 1 ? 'disabled' : ''}><i class="fa fa-chevron-left"></i></button>
    <span style="font-size:11px">${_sup.page} / ${_sup.lastPage}</span>
    <button class="cm-page-btn" id="sup-pg-next" ${_sup.page >= _sup.lastPage ? 'disabled' : ''}><i class="fa fa-chevron-right"></i></button>`;
  $('#sup-pg-prev')?.addEventListener('click', () => { if (_sup.page > 1) { _sup.page--; _supLoad(); } });
  $('#sup-pg-next')?.addEventListener('click', () => { if (_sup.page < _sup.lastPage) { _sup.page++; _supLoad(); } });
}

async function _supOpenModal(id = null) {
  _sup.editingId = id;
  $('#sup-modal-title').textContent = id ? 'Edit Supplier' : 'New Supplier';
  $('#sup-f-name').value    = '';
  $('#sup-f-contact').value = '';
  $('#sup-f-phone').value   = '';
  $('#sup-f-email').value   = '';
  $('#sup-f-address').value = '';
  $('#sup-f-notes').value   = '';

  if (id) {
    const res = await API.supplier(id);
    if (res.status !== 200) { toast('Failed to load supplier', 'error'); return; }
    const s = res.body?.data;
    if (s) {
      $('#sup-f-name').value    = s.name ?? '';
      $('#sup-f-contact').value = s.contact_name ?? '';
      $('#sup-f-phone').value   = s.phone ?? '';
      $('#sup-f-email').value   = s.email ?? '';
      $('#sup-f-address').value = s.address ?? '';
      $('#sup-f-notes').value   = s.notes ?? '';
    }
  }

  $('#sup-modal').style.display = 'flex';
  requestAnimationFrame(() => $('#sup-f-name').focus());
}

async function _supSave() {
  const name    = $('#sup-f-name').value.trim();
  const contact = $('#sup-f-contact').value.trim() || null;
  const phone   = $('#sup-f-phone').value.trim() || null;
  const email   = $('#sup-f-email').value.trim() || null;
  const address = $('#sup-f-address').value.trim() || null;
  const notes   = $('#sup-f-notes').value.trim() || null;

  if (!name) { toast('Supplier name is required', 'error'); $('#sup-f-name').focus(); return; }

  const btn = $('#sup-form-save');
  btn.disabled = true; btn.innerHTML = '<i class="fa fa-spinner fa-spin"></i> Saving…';

  const body = { name, contact_name: contact, phone, email, address, notes };
  const res  = _sup.editingId
    ? await API.updateSupplier(_sup.editingId, body)
    : await API.createSupplier(body);

  btn.disabled = false; btn.innerHTML = '<i class="fa fa-check"></i> Save Supplier';

  if (res.status !== 200 && res.status !== 201) {
    const msg = res.body?.errors?.name?.[0] || res.body?.message || 'Failed to save';
    toast(msg, 'error'); return;
  }

  $('#sup-modal').style.display = 'none';
  toast(_sup.editingId ? 'Supplier updated' : 'Supplier created', 'success');
  _supLoad();
}

// Supplier modal controls
$('#sup-modal')?.addEventListener('click', e => { if (e.target === e.currentTarget) $('#sup-modal').style.display = 'none'; });
$('#sup-modal-close')?.addEventListener('click', () => { $('#sup-modal').style.display = 'none'; });
$('#sup-form-cancel')?.addEventListener('click', () => { $('#sup-modal').style.display = 'none'; });
$('#sup-form-save')?.addEventListener('click', _supSave);
$('#sup-add-btn')?.addEventListener('click', () => _supOpenModal());
$('#sup-refresh')?.addEventListener('click', _supLoad);

let _supSearchTimer;
$('#sup-search')?.addEventListener('input', e => {
  clearTimeout(_supSearchTimer);
  _sup.q = e.target.value.trim();
  _sup.page = 1;
  _supSearchTimer = setTimeout(_supLoad, 300);
});
$('#sup-search')?.addEventListener('keydown', e => {
  if (e.key === 'Escape') { _sup.q = ''; e.target.value = ''; _sup.page = 1; _supLoad(); }
});

['#sup-f-name','#sup-f-contact','#sup-f-phone','#sup-f-email','#sup-f-address'].forEach(sel => {
  $(sel)?.addEventListener('keydown', e => {
    if (e.key === 'Enter') _supSave();
    if (e.key === 'Escape') $('#sup-modal').style.display = 'none';
  });
});

// ── Purchase Orders ───────────────────────────────────────────────────────
const _po = { q: '', status: '', list: [], activePOId: null, products: [], suppliers: [] };
let _poSearchTimer;

async function _poLoad() {
  $('#po-tbody').innerHTML = `<tr><td colspan="6" style="text-align:center;padding:20px;color:var(--text-muted)"><i class="fa fa-spinner fa-spin"></i></td></tr>`;
  const res = await API.purchaseOrders(_po.q, _po.status);
  if (res.status !== 200) { $('#po-tbody').innerHTML = `<tr><td colspan="6" style="text-align:center;padding:20px;color:var(--text-muted)">Failed to load</td></tr>`; return; }
  _po.list = res.body?.data || [];
  _poRenderTable();
}

function _poStatusBadge(status, label) {
  return `<span class="po-status-badge ${status}">${label}</span>`;
}

function _poRenderTable() {
  const cur = state.currency || '$';
  if (!_po.list.length) {
    $('#po-tbody').innerHTML = `<tr><td colspan="6" style="text-align:center;padding:20px;color:var(--text-muted)">No purchase orders found</td></tr>`;
    $('#po-count').textContent = '0 orders';
    return;
  }
  $('#po-count').textContent = `${_po.list.length} order${_po.list.length !== 1 ? 's' : ''}`;
  $('#po-tbody').innerHTML = _po.list.map(po => {
    const active = po.id === _po.activePOId ? ' active' : '';
    return `<tr data-id="${po.id}" class="${active}">
      <td><strong>${po.po_number}</strong></td>
      <td>${po.supplier_name || '—'}</td>
      <td>${po.purchase_date || '—'}</td>
      <td>${cur}${(po.total || 0).toFixed(2)}</td>
      <td>${_poStatusBadge(po.status, po.status_label)}</td>
      <td><button class="po-dv-action-btn" data-action="view" data-id="${po.id}" style="padding:3px 8px;font-size:10px"><i class="fa fa-eye"></i></button></td>
    </tr>`;
  }).join('');
  $('#po-tbody').querySelectorAll('tr[data-id]').forEach(row => {
    row.addEventListener('click', e => {
      if (e.target.closest('[data-action]')) return;
      _poSelectPO(parseInt(row.dataset.id));
    });
    row.querySelector('[data-action="view"]')?.addEventListener('click', () => _poSelectPO(parseInt(row.dataset.id)));
  });
}

async function _poSelectPO(id) {
  _po.activePOId = id;
  _poRenderTable();
  _poShowDetail(false);
  $('#po-detail-empty').innerHTML = '<i class="fa fa-spinner fa-spin" style="font-size:28px;opacity:.3"></i>';
  $('#po-detail-empty').style.display = 'flex';
  const res = await API.purchaseOrder(id);
  if (res.status !== 200) { toast('Failed to load PO', 'error'); return; }
  const po = res.body?.data;
  if (!po) return;
  _poRenderDetail(po);
  _poShowDetail(true);
}

function _poShowDetail(show) {
  $('#po-detail-empty').style.display = show ? 'none' : 'flex';
  $('#po-detail-view').style.display  = show ? 'flex' : 'none';
  if (!show && !$('#po-detail-empty').querySelector('.fa-spinner')) {
    $('#po-detail-empty').innerHTML = '<i class="fa fa-file-invoice"></i><span>Select a purchase order to view details</span>';
  }
}

function _poRenderDetail(po) {
  const cur = state.currency || '$';
  $('#po-dv-number').textContent = po.po_number;
  $('#po-dv-meta').textContent = `${po.purchase_date || ''} · ${po.supplier_name || 'No supplier'}`;

  // Summary grid
  $('#po-dv-summary').innerHTML = `
    <div><div class="po-dv-sf-label">Status</div><div class="po-dv-sf-val">${_poStatusBadge(po.status, po.status_label)}</div></div>
    <div><div class="po-dv-sf-label">Supplier</div><div class="po-dv-sf-val">${po.supplier_name || '—'}</div></div>
    <div><div class="po-dv-sf-label">Expected Delivery</div><div class="po-dv-sf-val">${po.expected_delivery_date || '—'}</div></div>
    <div><div class="po-dv-sf-label">Reference</div><div class="po-dv-sf-val">${po.reference || '—'}</div></div>
  `;

  // Action buttons
  const actions = [];
  if (po.status === 'draft')   actions.push(`<button class="po-dv-action-btn primary" data-po-action="place"><i class="fa fa-paper-plane"></i> Place Order</button>`);
  if (po.status === 'ordered' || po.status === 'partially_received')
                               actions.push(`<button class="po-dv-action-btn primary" data-po-action="receive"><i class="fa fa-truck-ramp-box"></i> Receive Goods</button>`);
  if (po.status !== 'cancelled' && po.status !== 'received')
                               actions.push(`<button class="po-dv-action-btn danger" data-po-action="cancel"><i class="fa fa-ban"></i> Cancel</button>`);
  $('#po-dv-actions').innerHTML = actions.join('');
  $('#po-dv-actions').querySelectorAll('[data-po-action]').forEach(btn => {
    btn.addEventListener('click', () => _poAction(po.id, btn.dataset.poAction));
  });

  // Items
  $('#po-dv-items').innerHTML = (po.items || []).map(it => `
    <tr>
      <td>${it.product_name || '—'}</td>
      <td style="color:var(--text-muted);font-size:10px">${it.sku || '—'}</td>
      <td>${it.quantity}</td>
      <td>${cur}${it.unit_cost.toFixed(2)}</td>
      <td>${cur}${it.line_total.toFixed(2)}</td>
    </tr>`).join('');

  // Totals
  $('#po-dv-totals').innerHTML = `<span>Total</span><strong>${cur}${(po.total || 0).toFixed(2)}</strong>`;

  // Notes
  const notesEl = $('#po-dv-notes');
  if (po.notes) { notesEl.textContent = po.notes; notesEl.style.display = 'block'; }
  else           { notesEl.style.display = 'none'; }
}

async function _poAction(id, action) {
  const labels = { place: 'Place Order', receive: 'Receive Goods', cancel: 'Cancel PO' };
  if (action === 'cancel' && !confirm(`Cancel this purchase order?`)) return;

  // Receive goods opens the GRN creation modal instead of auto-receiving
  if (action === 'receive') {
    await _grnOpenCreateModal(id);
    return;
  }

  let res;
  if (action === 'place')  res = await API.placePO(id);
  if (action === 'cancel') res = await API.cancelPO(id);

  if (res.status === 200 || res.status === 201) {
    toast(res.body?.message || `${labels[action]} done`, 'success');
    await _poLoad();
    if (_po.activePOId === id) _poSelectPO(id);
  } else {
    toast(res.body?.message || `Action failed`, 'error');
  }
}

// ── PO Create Modal ───────────────────────────────────────────────────────
async function _poOpenModal() {
  // Ensure products & suppliers loaded
  if (!_po.products.length) {
    const res = await API.bootstrap('', '', 1);
    if (res.status === 200) {
      _po.products = res.body?.products || res.body?.data?.products || [];
    }
  }
  if (!_po.suppliers.length) {
    const res = await API.suppliers('', 1);
    if (res.status === 200) {
      _po.suppliers = res.body?.data || [];
    }
  }

  // Populate supplier dropdown
  const supSel = $('#po-f-supplier');
  supSel.innerHTML = '<option value="">— No supplier —</option>' +
    _po.suppliers.map(s => `<option value="${s.id}">${s.name}</option>`).join('');

  // Default date to today
  $('#po-f-date').value = new Date().toISOString().substring(0, 10);
  $('#po-f-reference').value = '';
  $('#po-f-delivery').value = '';
  $('#po-f-notes').value = '';
  $('#po-form-items-tbody').innerHTML = '';
  const cur = state.currency || '$';
  $('#po-form-total').textContent       = cur + '0.00';
  $('#po-summary-subtotal').textContent = cur + '0.00';
  $('#po-summary-items').textContent    = '0';
  const emEl = $('#po-items-empty'); if (emEl) emEl.style.display = 'flex';

  $('#po-modal').style.display = 'flex';
  setTimeout(() => $('#po-f-date').focus(), 60);
}

// Append a confirmed item as a clean display row
function _poAddFormItem(productId, productName, qty, cost) {
  const cur    = state.currency || '$';
  const rowNum = $('#po-form-items-tbody').rows.length + 1;
  const row    = document.createElement('tr');
  row.innerHTML = `
    <td class="po-td-no">${rowNum}</td>
    <td class="po-td-product">
      <input type="hidden" class="po-item-product-id" value="${productId}">
      <div class="po-row-product-name">${productName}</div>
    </td>
    <td class="po-td-num"><input type="number" class="po-item-num po-item-qty" value="${qty}" min="0.001" step="any"></td>
    <td class="po-td-num"><input type="number" class="po-item-num po-item-cost" value="${parseFloat(cost).toFixed(2)}" min="0" step="0.01"></td>
    <td class="po-td-total po-item-line-total">${cur}${(qty * cost).toFixed(2)}</td>
    <td style="text-align:center"><button class="po-item-del-btn" title="Remove"><i class="fa fa-xmark"></i></button></td>
  `;
  const qtyEl  = row.querySelector('.po-item-qty');
  const costEl = row.querySelector('.po-item-cost');
  row.querySelector('.po-item-del-btn').addEventListener('click', () => { row.remove(); _poRenumberRows(); _poCalcFormTotal(); });
  qtyEl.addEventListener('input',  () => _poUpdateRowTotal(row));
  costEl.addEventListener('input', () => _poUpdateRowTotal(row));
  $('#po-form-items-tbody').appendChild(row);
  const emEl = $('#po-items-empty'); if (emEl) emEl.style.display = 'none';
  _poCalcFormTotal();
}

// ── Add Item modal ────────────────────────────────────────────────────────
const _poAim = { selectedId: null, selectedName: '', selectedCost: 0, acTimer: null };

function _poOpenAddItemModal() {
  _poAim.selectedId = null; _poAim.selectedName = ''; _poAim.selectedCost = 0;
  const input = $('#po-aim-product-input');
  const dd    = $('#po-aim-dd');
  if (input) { input.value = ''; }
  if (dd)    { dd.style.display = 'none'; dd.innerHTML = ''; }
  $('#po-aim-product-id').value = '';
  $('#po-aim-cost').value = '0.00';
  $('#po-aim-qty').value  = '1';
  $('#po-aim-total').textContent = '—';
  $('#po-add-item-modal').style.display = 'flex';
  setTimeout(() => input?.focus(), 60);
}

function _poAimUpdateTotal() {
  const qty  = parseFloat($('#po-aim-qty').value)  || 0;
  const cost = parseFloat($('#po-aim-cost').value) || 0;
  const cur  = state.currency || '$';
  $('#po-aim-total').textContent = qty > 0 && cost > 0 ? `${cur}${(qty * cost).toFixed(2)}` : '—';
}

function _poAimRenderDd(q) {
  const cur  = state.currency || '$';
  const dd   = $('#po-aim-dd');
  const term = q.toLowerCase();
  const matches = _po.products.filter(p =>
    p.name.toLowerCase().includes(term) || (p.sku || '').toLowerCase().includes(term)
  ).slice(0, 12);
  if (!matches.length) {
    dd.innerHTML = `<div class="po-ac-empty">No products found</div>`;
    dd.style.display = 'block'; return;
  }
  dd.innerHTML = matches.map(p => {
    const cost  = p.layers?.[0]?.unit_cost ?? 0;
    const stock = p.stock_quantity ?? 0;
    return `<div class="po-ac-item" tabindex="0"
        data-id="${p.id}" data-name="${p.name.replace(/"/g,'&quot;')}" data-cost="${cost}">
      <div class="po-ac-name">${p.name}</div>
      <div class="po-ac-meta">
        ${p.sku ? `<span class="po-ac-sku">${p.sku}</span>` : ''}
        <span class="po-ac-stock ${stock <= 0 ? 'po-ac-out' : ''}">${stock <= 0 ? 'Out of stock' : 'Stock: '+stock}</span>
        <span class="po-ac-cost">Cost: ${cur}${parseFloat(cost).toFixed(2)}</span>
      </div>
    </div>`;
  }).join('');
  dd.style.display = 'block';
}

function _poAimSelect(id, name, cost) {
  _poAim.selectedId   = id;
  _poAim.selectedName = name;
  _poAim.selectedCost = cost;
  $('#po-aim-product-id').value    = id;
  $('#po-aim-product-input').value = name;
  $('#po-aim-cost').value          = parseFloat(cost).toFixed(2);
  $('#po-aim-dd').style.display    = 'none';
  _poAimUpdateTotal();
  setTimeout(() => $('#po-aim-qty')?.focus(), 30);
}

function _poAimConfirm() {
  const id   = $('#po-aim-product-id').value;
  const name = $('#po-aim-product-input').value.trim();
  const qty  = parseFloat($('#po-aim-qty').value)  || 0;
  const cost = parseFloat($('#po-aim-cost').value) || 0;
  if (!id || !name) { toast('Please select a product', 'error'); return; }
  if (qty <= 0)     { toast('Quantity must be greater than 0', 'error'); return; }
  _poAddFormItem(id, name, qty, cost);
  $('#po-add-item-modal').style.display = 'none';
}

// Wire Add Item modal events
$('#po-item-add-btn')?.addEventListener('click', _poOpenAddItemModal);
$('#po-aim-close')?.addEventListener('click',  () => { $('#po-add-item-modal').style.display = 'none'; });
$('#po-aim-cancel')?.addEventListener('click', () => { $('#po-add-item-modal').style.display = 'none'; });
$('#po-aim-add')?.addEventListener('click',    _poAimConfirm);
$('#po-aim-qty')?.addEventListener('input',    _poAimUpdateTotal);
$('#po-aim-cost')?.addEventListener('input',   _poAimUpdateTotal);

$('#po-aim-product-input')?.addEventListener('input', e => {
  $('#po-aim-product-id').value = '';
  _poAim.selectedId = null;
  clearTimeout(_poAim.acTimer);
  const q = e.target.value.trim();
  if (!q) { $('#po-aim-dd').style.display = 'none'; return; }
  _poAim.acTimer = setTimeout(() => _poAimRenderDd(q), 120);
});

$('#po-aim-product-input')?.addEventListener('keydown', e => {
  const dd = $('#po-aim-dd');
  if (e.key === 'Escape') { dd.style.display = 'none'; return; }
  if (e.key === 'ArrowDown') {
    const first = dd.querySelector('.po-ac-item');
    if (first) { e.preventDefault(); first.focus(); }
  }
});

$('#po-aim-dd')?.addEventListener('keydown', e => {
  const dd    = $('#po-aim-dd');
  const items = [...dd.querySelectorAll('.po-ac-item')];
  const idx   = items.indexOf(document.activeElement);
  if (e.key === 'ArrowDown') { e.preventDefault(); items[idx + 1]?.focus(); }
  if (e.key === 'ArrowUp')   { e.preventDefault(); idx > 0 ? items[idx - 1].focus() : $('#po-aim-product-input').focus(); }
  if (e.key === 'Enter' && idx >= 0) {
    e.preventDefault();
    const el = items[idx];
    _poAimSelect(el.dataset.id, el.dataset.name, parseFloat(el.dataset.cost));
  }
  if (e.key === 'Escape') { dd.style.display = 'none'; $('#po-aim-product-input').focus(); }
});

$('#po-aim-dd')?.addEventListener('mousedown', e => {
  const item = e.target.closest('.po-ac-item');
  if (!item) return;
  e.preventDefault();
  _poAimSelect(item.dataset.id, item.dataset.name, parseFloat(item.dataset.cost));
});

$('#po-aim-product-input')?.addEventListener('blur', () => {
  setTimeout(() => {
    if (!$('#po-aim-dd')?.contains(document.activeElement)) {
      if ($('#po-aim-dd')) $('#po-aim-dd').style.display = 'none';
    }
  }, 150);
});

$('#po-aim-qty')?.addEventListener('keydown', e => { if (e.key === 'Enter') _poAimConfirm(); });

function _poRenumberRows() {
  $('#po-form-items-tbody').querySelectorAll('.po-td-no').forEach((td, i) => { td.textContent = i + 1; });
}

function _poUpdateRowTotal(row) {
  const qty  = parseFloat(row.querySelector('.po-item-qty').value)  || 0;
  const cost = parseFloat(row.querySelector('.po-item-cost').value) || 0;
  const cur  = state.currency || '$';
  row.querySelector('.po-item-line-total').textContent = cur + (qty * cost).toFixed(2);
  _poCalcFormTotal();
}

function _poCalcFormTotal() {
  let total = 0; let count = 0;
  $('#po-form-items-tbody').querySelectorAll('tr').forEach(row => {
    const qty  = parseFloat(row.querySelector('.po-item-qty')?.value)  || 0;
    const cost = parseFloat(row.querySelector('.po-item-cost')?.value) || 0;
    total += qty * cost;
    count++;
  });
  const cur = state.currency || '$';
  $('#po-form-total').textContent       = cur + total.toFixed(2);
  $('#po-summary-subtotal').textContent = cur + total.toFixed(2);
  $('#po-summary-items').textContent    = count;
  const emptyEl = $('#po-items-empty');
  if (emptyEl) emptyEl.style.display = count > 0 ? 'none' : 'flex';
}

function _poCollectFormItems() {
  const items = [];
  $('#po-form-items-tbody').querySelectorAll('tr').forEach(row => {
    const productId = parseInt(row.querySelector('.po-item-product-id')?.value);
    const qty       = parseFloat(row.querySelector('.po-item-qty')?.value);
    const cost      = parseFloat(row.querySelector('.po-item-cost')?.value);
    if (productId && qty > 0) items.push({ product_id: productId, quantity: qty, unit_cost: cost || 0 });
  });
  return items;
}

async function _poSave(status) {
  const items = _poCollectFormItems();
  if (!items.length) { toast('Add at least one line item', 'error'); return; }
  const dateVal = $('#po-f-date').value;
  if (!dateVal) { toast('Purchase date is required', 'error'); $('#po-f-date').focus(); return; }

  const body = {
    purchase_date:          dateVal,
    expected_delivery_date: $('#po-f-delivery').value || null,
    supplier_id:            parseInt($('#po-f-supplier').value) || null,
    reference:              $('#po-f-reference').value.trim() || null,
    notes:                  $('#po-f-notes').value.trim()     || null,
    status,
    items,
  };

  const savingBtn = status === 'draft' ? $('#po-form-save-draft') : $('#po-form-place');
  savingBtn.disabled = true;

  const res = await API.createPO(body);
  savingBtn.disabled = false;

  if (res.status === 201) {
    toast(res.body?.message || 'Purchase order created', 'success');
    $('#po-modal').style.display = 'none';
    await _poLoad();
    if (res.body?.data?.id) _poSelectPO(res.body.data.id);
  } else {
    const msg = res.body?.errors ? Object.values(res.body.errors).flat()[0] : res.body?.message || 'Failed to save';
    toast(msg, 'error');
  }
}

// Event wiring
$('#po-modal')?.addEventListener('click', e => { if (e.target === e.currentTarget) $('#po-modal').style.display = 'none'; });
$('#po-modal-close')?.addEventListener('click', () => { $('#po-modal').style.display = 'none'; });
$('#po-form-cancel')?.addEventListener('click', () => { $('#po-modal').style.display = 'none'; });
$('#po-form-save-draft')?.addEventListener('click', () => _poSave('draft'));
$('#po-form-place')?.addEventListener('click',      () => _poSave('ordered'));
$('#po-add-btn')?.addEventListener('click',         _poOpenModal);
// Quick-add supplier inside PO modal
function _poQSupOpen() {
  $('#po-qsup-form').style.display = 'flex';
  $('#po-qsup-name').value = '';
  $('#po-qsup-phone').value = '';
  $('#po-qsup-email').value = '';
  $('#po-add-sup-toggle').style.display = 'none';
  setTimeout(() => $('#po-qsup-name').focus(), 60);
}
function _poQSupClose() {
  $('#po-qsup-form').style.display = 'none';
  $('#po-add-sup-toggle').style.display = 'flex';
}
async function _poQSupSave() {
  const name = $('#po-qsup-name').value.trim();
  if (!name) { $('#po-qsup-name').focus(); return; }

  const saveBtn = $('#po-qsup-save');
  saveBtn.disabled = true;

  const res = await API.createSupplier({
    name,
    phone:   $('#po-qsup-phone').value.trim() || null,
    email:   $('#po-qsup-email').value.trim() || null,
  });

  saveBtn.disabled = false;

  if (res.status === 200 || res.status === 201) {
    const sup = res.body?.data || res.body;
    // Add to cached list and dropdown
    _po.suppliers.push(sup);
    const opt = document.createElement('option');
    opt.value = sup.id;
    opt.textContent = sup.name;
    $('#po-f-supplier').appendChild(opt);
    $('#po-f-supplier').value = sup.id;
    // Also refresh the full supplier list cache
    _sup.page = 1; _supLoad();
    _poQSupClose();
    toast(`Supplier "${sup.name}" added`, 'success');
  } else {
    const msg = res.body?.errors?.name?.[0] || res.body?.message || 'Failed to save supplier';
    toast(msg, 'error');
  }
}

$('#po-add-sup-toggle')?.addEventListener('click', _poQSupOpen);
$('#po-qsup-cancel')?.addEventListener('click',    _poQSupClose);
$('#po-qsup-save')?.addEventListener('click',      _poQSupSave);
['#po-qsup-name','#po-qsup-phone','#po-qsup-email'].forEach(sel => {
  $(sel)?.addEventListener('keydown', e => {
    if (e.key === 'Enter')  _poQSupSave();
    if (e.key === 'Escape') _poQSupClose();
  });
});

$('#po-search')?.addEventListener('input', e => {
  clearTimeout(_poSearchTimer);
  _po.q = e.target.value.trim();
  _poSearchTimer = setTimeout(_poLoad, 300);
});
$('#po-search')?.addEventListener('keydown', e => {
  if (e.key === 'Escape') { _po.q = ''; e.target.value = ''; _poLoad(); }
});

$$('#po-status-filter .po-sf-btn').forEach(btn => {
  btn.addEventListener('click', () => {
    $$('#po-status-filter .po-sf-btn').forEach(b => b.classList.remove('active'));
    btn.classList.add('active');
    _po.status = btn.dataset.status;
    _poLoad();
  });
});

// ── Goods Receive Notes ───────────────────────────────────────────────────
const _grn = {
  q: '', payment: 'all', list: [], activeGrnId: null,
  accounts: [], formPurchaseId: null, formItems: [],
  payGrnId: null,
};
let _grnSearchTimer;

async function _grnLoad() {
  const tbody = $('#grn-tbody');
  if (!tbody) return;
  tbody.innerHTML = `<tr><td colspan="7" class="inv-loading"><i class="fa fa-spinner fa-spin"></i> Loading…</td></tr>`;
  const res = await API.grns(_grn.q, _grn.payment);
  if (res.status !== 200) {
    tbody.innerHTML = `<tr><td colspan="7" class="inv-loading" style="color:#ef4444"><i class="fa fa-triangle-exclamation"></i> Failed to load</td></tr>`;
    return;
  }
  _grn.list = res.body?.data ?? [];
  _grnRenderList();
}

function _grnPayBadge(g) {
  const map = { paid_full: ['grn-badge-paid',    'Paid'],
                paid_partial: ['grn-badge-partial', 'Partial'],
                pending:  ['grn-badge-pending',  'Pending'],
                no_amount:['grn-badge-none',     'No amount'] };
  const [cls, label] = map[g.payment_status] || ['grn-badge-pending', g.payment_status_label || 'Pending'];
  return `<span class="grn-payment-badge ${cls}">${label}</span>`;
}

function _grnRenderList() {
  const tbody = $('#grn-tbody');
  const cur = window._activeSession?.currency || '';
  if (!_grn.list.length) {
    tbody.innerHTML = `<tr><td colspan="7" class="inv-loading" style="color:var(--text-muted)"><i class="fa fa-inbox"></i> No GRNs found</td></tr>`;
    $('#grn-count').textContent = '0 records';
    return;
  }
  tbody.innerHTML = _grn.list.map(g => `
    <tr class="po-row${_grn.activeGrnId === g.id ? ' po-row-active' : ''}" data-grn-id="${g.id}">
      <td><strong>${g.grn_number}</strong></td>
      <td>${g.po_number || '—'}</td>
      <td>${g.supplier_name || '—'}</td>
      <td>${g.received_date || '—'}</td>
      <td style="text-align:right">${cur}${(g.total||0).toFixed(2)}</td>
      <td>${_grnPayBadge(g)}</td>
      <td style="text-align:center">
        ${g.payment_status !== 'paid_full' && g.payment_status !== 'no_amount'
          ? `<button class="grn-pay-row-btn" data-grn-id="${g.id}" data-total="${g.total||0}" data-paid="${g.amount_paid||0}" data-outstanding="${g.amount_outstanding||0}" title="Make payment"><i class="fa fa-money-bill-wave"></i></button>`
          : ''}
      </td>
    </tr>`).join('');
  $('#grn-count').textContent = `${_grn.list.length} record${_grn.list.length !== 1 ? 's' : ''}`;

  tbody.querySelectorAll('.po-row').forEach(row => {
    row.addEventListener('click', e => {
      if (e.target.closest('.grn-pay-row-btn')) return;
      _grnSelectGrn(+row.dataset.grnId);
    });
  });
  tbody.querySelectorAll('.grn-pay-row-btn').forEach(btn => {
    btn.addEventListener('click', e => {
      e.stopPropagation();
      _grnOpenPayModal(+btn.dataset.grnId, {
        total: +btn.dataset.total, amount_paid: +btn.dataset.paid,
        amount_outstanding: +btn.dataset.outstanding,
      });
    });
  });
}

async function _grnSelectGrn(id) {
  _grn.activeGrnId = id;
  _grnRenderList();
  _grnShowDetail(false);
  const res = await API.grn(id);
  if (res.status !== 200) { toast('Failed to load GRN detail', 'error'); return; }
  _grnRenderDetail(res.body?.data ?? res.body);
}

function _grnShowDetail(show) {
  const empty = $('#grn-detail-empty');
  const view  = $('#grn-detail-view');
  if (!empty || !view) return;
  empty.style.display = show ? 'none' : 'flex';
  view.style.display  = show ? 'block' : 'none';
}

function _grnRenderDetail(g) {
  const cur = window._activeSession?.currency || '';
  $('#grn-dv-number').textContent = g.grn_number;
  $('#grn-dv-meta').innerHTML = `<span>${g.supplier_name || '—'}</span> &nbsp;|&nbsp; PO: <strong>${g.po_number || '—'}</strong> &nbsp;|&nbsp; ${g.received_date || '—'}`;

  // Actions
  const actDiv = $('#grn-dv-actions');
  actDiv.innerHTML = '';
  if (g.payment_status !== 'paid_full' && g.payment_status !== 'no_amount') {
    const payBtn = document.createElement('button');
    payBtn.className = 'po-btn-primary';
    payBtn.innerHTML = '<i class="fa fa-money-bill-wave"></i> Make Payment';
    payBtn.addEventListener('click', () => _grnOpenPayModal(g.id, g));
    actDiv.appendChild(payBtn);
  }

  // Summary cards
  $('#grn-dv-summary').innerHTML = `
    <div class="po-dv-summary">
      <div><div class="po-dv-sf-label">Status</div><div class="po-dv-sf-val">${_grnPayBadge(g)}</div></div>
      <div><div class="po-dv-sf-label">Total</div><div class="po-dv-sf-val">${cur}${(g.total||0).toFixed(2)}</div></div>
      <div><div class="po-dv-sf-label">Paid</div><div class="po-dv-sf-val">${cur}${(g.amount_paid||0).toFixed(2)}</div></div>
      <div><div class="po-dv-sf-label">Outstanding</div><div class="po-dv-sf-val">${cur}${(g.amount_outstanding||0).toFixed(2)}</div></div>
      ${g.payment_method ? `<div><div class="po-dv-sf-label">Pay method</div><div class="po-dv-sf-val">${g.payment_method}</div></div>` : ''}
      ${g.reference ? `<div><div class="po-dv-sf-label">Reference</div><div class="po-dv-sf-val">${g.reference}</div></div>` : ''}
    </div>`;

  // Items
  const items = g.items || [];
  $('#grn-dv-items').innerHTML = items.length
    ? items.map(it => `
        <tr>
          <td>${it.product_name}</td>
          <td>${it.sku || '—'}</td>
          <td style="text-align:right">${(it.quantity_received||0)}</td>
          <td style="text-align:right">${cur}${(it.unit_cost||0).toFixed(2)}</td>
          <td style="text-align:right">${cur}${(it.line_total||0).toFixed(2)}</td>
        </tr>`).join('')
    : `<tr><td colspan="5" style="text-align:center;color:var(--text-muted)">No items</td></tr>`;

  // Payments
  const payments = g.payments || [];
  $('#grn-dv-payments').innerHTML = payments.length
    ? `<table class="po-items-table"><thead><tr><th>Date</th><th>Account</th><th style="text-align:right">Amount</th></tr></thead><tbody>
        ${payments.map(p => `<tr><td>${p.date||'—'}</td><td>${p.account||'—'}</td><td style="text-align:right">${cur}${(p.amount||0).toFixed(2)}</td></tr>`).join('')}
       </tbody></table>`
    : `<div style="padding:8px 0;color:var(--text-muted);font-size:12px">No payments recorded</div>`;

  $('#grn-dv-totals').innerHTML = `
    <div class="po-dv-totals">
      <span>Subtotal: ${cur}${(g.subtotal||0).toFixed(2)}</span>
      <strong>Total: ${cur}${(g.total||0).toFixed(2)}</strong>
    </div>`;

  $('#grn-dv-notes').innerHTML = g.notes
    ? `<div class="po-dv-notes">${g.notes}</div>`
    : '';

  _grnShowDetail(true);
}

// ── Create GRN Modal ──────────────────────────────────────────────────────
async function _grnOpenCreateModal(purchaseId) {
  _grn.formPurchaseId = purchaseId;
  _grn.formItems = [];

  const modal = $('#grn-modal');
  modal.style.display = 'flex';

  // Set today's date
  const today = new Date().toISOString().slice(0, 10);
  $('#grn-f-date').value = today;
  $('#grn-f-reference').value = '';
  $('#grn-f-notes').value = '';
  // Reset payment method to cash
  $$('#grn-method-btns .grn-method-btn').forEach(b => b.classList.toggle('active', b.dataset.method === 'cash'));
  $('#grn-payment-fields').style.display = '';
  const cf = $('#grn-cheque-fields'); if (cf) cf.style.display = 'none';
  $('#grn-f-cheque-ref').value = '';
  $('#grn-f-cheque-date').value = '';
  document.querySelectorAll('input[name="grn-pay-opt"]').forEach(r => { r.checked = r.value === 'full'; });
  $('#grn-partial-row').style.display = 'none';

  // Load accounts
  if (!_grn.accounts.length) {
    const res = await API.accounts();
    if (res.status === 200) _grn.accounts = res.body?.data || [];
  }
  const accSel = $('#grn-f-account');
  accSel.innerHTML = _grn.accounts.map(a => `<option value="${a.id}">${a.account_name || a.name}</option>`).join('');

  // Load PO items for this form
  $('#grn-items-tbody').innerHTML = `<tr><td colspan="7" style="text-align:center;padding:20px"><i class="fa fa-spinner fa-spin"></i> Loading items…</td></tr>`;
  const res = await API.grnForm(purchaseId);
  if (res.status !== 200) {
    toast('Failed to load PO items', 'error');
    modal.style.display = 'none';
    return;
  }

  const data = res.body?.data ?? {};
  const po   = data.purchase || {};
  _grn.formItems = data.items || [];

  $('#grn-modal-title').textContent = `Receive Goods — ${po.po_number || 'PO'}`;
  $('#grn-modal-sub').textContent   = `Supplier: ${po.supplier_name || '—'}`;

  _grnRenderFormItems();
  _grnCalcFormTotal();
}

function _grnRenderFormItems() {
  const cur = window._activeSession?.currency || '';
  const tbody = $('#grn-items-tbody');
  if (!_grn.formItems.length) {
    tbody.innerHTML = `<tr><td colspan="7" style="text-align:center;color:var(--text-muted);padding:20px">No items on this PO</td></tr>`;
    return;
  }
  tbody.innerHTML = _grn.formItems.map((it, idx) => `
    <tr class="grn-item-row" data-idx="${idx}">
      <td class="grn-td-product">${it.product_name}</td>
      <td class="grn-td-num">${(it.quantity_ordered||0)}</td>
      <td class="grn-td-num">${(it.quantity_received||0)}</td>
      <td class="grn-td-num">${(it.quantity_remaining||0)}</td>
      <td class="grn-td-num"><input type="number" class="grn-item-qty po-item-num" data-idx="${idx}" value="${(it.quantity_remaining||0)}" min="0" step="0.001" max="${it.quantity_remaining||0}"></td>
      <td class="grn-td-num"><input type="number" class="grn-item-cost po-item-num" data-idx="${idx}" value="${(it.unit_cost||0).toFixed(2)}" min="0" step="0.01"></td>
      <td class="grn-td-num grn-item-linetotal">${cur}${((it.quantity_remaining||0)*(it.unit_cost||0)).toFixed(2)}</td>
    </tr>`).join('');

  tbody.querySelectorAll('.grn-item-qty,.grn-item-cost').forEach(inp => {
    inp.addEventListener('input', () => {
      const idx = +inp.dataset.idx;
      const row = tbody.querySelector(`.grn-item-row[data-idx="${idx}"]`);
      const qty  = parseFloat(row.querySelector('.grn-item-qty').value)  || 0;
      const cost = parseFloat(row.querySelector('.grn-item-cost').value) || 0;
      const cur  = window._activeSession?.currency || '';
      row.querySelector('.grn-item-linetotal').textContent = `${cur}${(qty*cost).toFixed(2)}`;
      _grnCalcFormTotal();
    });
  });
}

function _grnCalcFormTotal() {
  const cur = window._activeSession?.currency || '';
  const tbody = $('#grn-items-tbody');
  let total = 0, items = 0;
  (tbody?.querySelectorAll('.grn-item-row') || []).forEach(row => {
    const qty  = parseFloat(row.querySelector('.grn-item-qty')?.value)  || 0;
    const cost = parseFloat(row.querySelector('.grn-item-cost')?.value) || 0;
    if (qty > 0) { total += qty * cost; items++; }
  });
  const elItems = $('#grn-summary-items');
  const elTotal = $('#grn-summary-total');
  if (elItems) elItems.textContent = items;
  if (elTotal) elTotal.textContent = `${cur}${total.toFixed(2)}`;
}

async function _grnSubmitCreate() {
  const btn = $('#grn-modal-save');
  btn.disabled = true;

  const tbody = $('#grn-items-tbody');
  const items = [];
  (tbody?.querySelectorAll('.grn-item-row') || []).forEach((row, i) => {
    const fi = _grn.formItems[i];
    if (!fi) return;
    const qty = parseFloat(row.querySelector('.grn-item-qty')?.value) || 0;
    if (qty <= 0) return;
    items.push({
      purchase_item_id:   fi.id,
      quantity_received:  qty,
      unit_cost:          parseFloat(row.querySelector('.grn-item-cost')?.value) || fi.unit_cost || 0,
    });
  });

  if (!items.length) { toast('Enter at least one item quantity', 'error'); btn.disabled = false; return; }

  const method    = $('#grn-method-btns')?.querySelector('.active')?.dataset.method || 'cash';
  const payOption = document.querySelector('input[name="grn-pay-opt"]:checked')?.value || 'full';
  const isCash    = method === 'cash' || method === 'cheque';
  const body = {
    received_date:       $('#grn-f-date').value,
    reference:           $('#grn-f-reference').value.trim() || null,
    notes:               $('#grn-f-notes').value.trim() || null,
    payment_method:      method,
    payment_option:      isCash ? payOption : null,
    deduct_account_id:   isCash ? (+$('#grn-f-account').value || null) : null,
    pay_amount:          (isCash && payOption === 'partial') ? (parseFloat($('#grn-f-amount').value) || null) : null,
    payment_reference:   method === 'cheque' ? ($('#grn-f-cheque-ref').value.trim() || null) : null,
    cheque_due_date:     method === 'cheque' ? ($('#grn-f-cheque-date').value || null) : null,
    items,
  };

  const res = await API.createGrn(_grn.formPurchaseId, body);
  btn.disabled = false;
  if (res.status === 201 || res.status === 200) {
    toast(res.body?.message || 'GRN recorded', 'success');
    $('#grn-modal').style.display = 'none';
    // refresh both PO detail and GRN list if visible
    if (_grn.activeGrnId) _grnSelectGrn(_grn.activeGrnId);
    switchInvView('grn');
  } else {
    const msg = Object.values(res.body?.errors || {}).flat().join(', ') || res.body?.message || 'Failed to create GRN';
    toast(msg, 'error');
  }
}

// ── Pay GRN Modal ─────────────────────────────────────────────────────────
function _grnOpenPayModal(grnId, g) {
  _grn.payGrnId = grnId;
  const cur = window._activeSession?.currency || '';
  $('#grn-pay-modal').style.display = 'flex';
  $('#grn-pay-subtitle').textContent = `Outstanding: ${cur}${(g.amount_outstanding||g.total||0).toFixed(2)}`;
  $('#grn-pay-total').textContent       = `${cur}${(g.total||0).toFixed(2)}`;
  $('#grn-pay-paid').textContent        = `${cur}${(g.amount_paid||0).toFixed(2)}`;
  $('#grn-pay-outstanding').textContent = `${cur}${(g.amount_outstanding||g.total||0).toFixed(2)}`;
  // populate accounts
  const sel = $('#grn-pay-account');
  sel.innerHTML = _grn.accounts.length
    ? _grn.accounts.map(a => `<option value="${a.id}">${a.account_name || a.name}</option>`).join('')
    : '<option value="">Loading…</option>';
  if (!_grn.accounts.length) {
    API.accounts().then(r => {
      if (r.status === 200) { _grn.accounts = r.body?.data || []; }
      sel.innerHTML = _grn.accounts.map(a => `<option value="${a.id}">${a.account_name || a.name}</option>`).join('');
    });
  }
  // reset radios
  document.querySelectorAll('input[name="grn-pay2-opt"]').forEach(r => { r.checked = r.value === 'full'; });
  $('#grn-pay-partial-row').style.display = 'none';
  // reset method btns and cheque fields
  $('#grn-pay-method-btns')?.querySelectorAll('.grn-method-btn').forEach(b => b.classList.toggle('active', b.dataset.method === 'cash'));
  const pcf = $('#grn-pay-cheque-fields');
  if (pcf) { pcf.style.display = 'none'; }
  const pRef = $('#grn-pay-cheque-ref');   if (pRef)  pRef.value  = '';
  const pDt  = $('#grn-pay-cheque-date');  if (pDt)   pDt.value   = '';
}

async function _grnSubmitPay() {
  const btn = $('#grn-pay-save');
  btn.disabled = true;
  const method    = $('#grn-pay-method-btns')?.querySelector('.active')?.dataset.method || 'cash';
  const payOption = document.querySelector('input[name="grn-pay2-opt"]:checked')?.value || 'full';
  const body = {
    payment_method:    method,
    deduct_account_id: +$('#grn-pay-account').value || null,
    payment_option:    payOption,
    pay_amount:        payOption === 'partial' ? (parseFloat($('#grn-pay-amount').value) || null) : null,
    payment_reference: method === 'cheque' ? ($('#grn-pay-cheque-ref').value.trim() || null) : null,
    cheque_due_date:   method === 'cheque' ? ($('#grn-pay-cheque-date').value || null) : null,
  };
  const res = await API.payGrn(_grn.payGrnId, body);
  btn.disabled = false;
  if (res.status === 200) {
    toast(res.body?.message || 'Payment recorded', 'success');
    $('#grn-pay-modal').style.display = 'none';
    _grnLoad();
    if (_grn.activeGrnId === _grn.payGrnId) _grnSelectGrn(_grn.payGrnId);
  } else {
    const msg = Object.values(res.body?.errors || {}).flat().join(', ') || res.body?.message || 'Failed to record payment';
    toast(msg, 'error');
  }
}

// ── GRN event listeners ───────────────────────────────────────────────────
$('#grn-search')?.addEventListener('input', e => {
  clearTimeout(_grnSearchTimer);
  _grn.q = e.target.value.trim();
  _grnSearchTimer = setTimeout(_grnLoad, 300);
});
$('#grn-search')?.addEventListener('keydown', e => {
  if (e.key === 'Escape') { _grn.q = ''; e.target.value = ''; _grnLoad(); }
});
$('#grn-refresh-btn')?.addEventListener('click', _grnLoad);

$$('#grn-payment-filter .grn-pf-btn').forEach(btn => {
  btn.addEventListener('click', () => {
    $$('#grn-payment-filter .grn-pf-btn').forEach(b => b.classList.remove('active'));
    btn.classList.add('active');
    _grn.payment = btn.dataset.payment;
    _grnLoad();
  });
});

$('#grn-modal-close')?.addEventListener('click',  () => { $('#grn-modal').style.display = 'none'; });
$('#grn-modal-cancel')?.addEventListener('click', () => { $('#grn-modal').style.display = 'none'; });
$('#grn-modal-save')?.addEventListener('click',   _grnSubmitCreate);

// Method toggle in create modal
$('#grn-method-btns')?.addEventListener('click', e => {
  const btn = e.target.closest('.grn-method-btn');
  if (!btn) return;
  $$('#grn-method-btns .grn-method-btn').forEach(b => b.classList.remove('active'));
  btn.classList.add('active');
  const method = btn.dataset.method;
  $('#grn-payment-fields').style.display = method === 'credit' ? 'none' : '';
  const cf = $('#grn-cheque-fields');
  if (cf) { cf.style.display = method === 'cheque' ? 'flex' : 'none'; }
});

// Partial amount toggle in create modal
document.querySelectorAll('input[name="grn-pay-opt"]').forEach(r => {
  r.addEventListener('change', () => {
    $('#grn-partial-row').style.display = r.value === 'partial' && r.checked ? '' : 'none';
  });
});

// Pay GRN modal
$('#grn-pay-close')?.addEventListener('click',  () => { $('#grn-pay-modal').style.display = 'none'; });
$('#grn-pay-cancel')?.addEventListener('click', () => { $('#grn-pay-modal').style.display = 'none'; });
$('#grn-pay-save')?.addEventListener('click',   _grnSubmitPay);

$('#grn-pay-method-btns')?.addEventListener('click', e => {
  const btn = e.target.closest('.grn-method-btn');
  if (!btn) return;
  $$('#grn-pay-method-btns .grn-method-btn').forEach(b => b.classList.remove('active'));
  btn.classList.add('active');
  const cf = $('#grn-pay-cheque-fields');
  if (cf) cf.style.display = btn.dataset.method === 'cheque' ? 'flex' : 'none';
});

document.querySelectorAll('input[name="grn-pay2-opt"]').forEach(r => {
  r.addEventListener('change', () => {
    $('#grn-pay-partial-row').style.display = r.value === 'partial' && r.checked ? '' : 'none';
  });
});

$('#grn-receive-all-btn')?.addEventListener('click', () => {
  $$('#grn-items-tbody .grn-item-qty').forEach(inp => {
    const max = parseFloat(inp.max) || 0;
    inp.value = max;
    inp.dispatchEvent(new Event('input'));
  });
});

// ── End Goods Receive Notes ────────────────────────────────────────────────

// ── Cheques ───────────────────────────────────────────────────────────────
const _chq = { filter: 'all', list: [], accounts: [], clearingId: null };

async function _chqLoad() {
  const tbody = $('#chq-tbody');
  if (!tbody) return;
  tbody.innerHTML = `<tr><td colspan="9" class="inv-loading"><i class="fa fa-spinner fa-spin"></i> Loading…</td></tr>`;
  const res = await API.cheques(_chq.filter);
  if (res.status !== 200) {
    tbody.innerHTML = `<tr><td colspan="9" class="inv-loading" style="color:#ef4444"><i class="fa fa-triangle-exclamation"></i> Failed to load</td></tr>`;
    return;
  }
  _chq.list = res.body?.data ?? [];
  const summary = res.body?.summary ?? {};
  _chqRenderSummary(summary);
  _chqRenderList();
}

function _chqRenderSummary(s) {
  const cur = window._activeSession?.currency || '';
  const el = id => $(id);
  if (el('#chq-stat-amount'))  el('#chq-stat-amount').textContent  = `${cur}${(s.pending_amount||0).toLocaleString(undefined,{minimumFractionDigits:2,maximumFractionDigits:2})}`;
  if (el('#chq-stat-pending')) el('#chq-stat-pending').textContent = s.pending ?? '—';
  if (el('#chq-stat-overdue')) el('#chq-stat-overdue').textContent = s.overdue ?? '—';
  if (el('#chq-stat-cleared')) el('#chq-stat-cleared').textContent = s.cleared ?? '—';
}

function _chqBadge(status) {
  const map = {
    cleared: ['chq-badge-cleared', 'Cleared'],
    overdue: ['chq-badge-overdue', 'Overdue'],
    due:     ['chq-badge-due',     'Due'],
    pending: ['chq-badge-pending', 'Pending'],
  };
  const [cls, label] = map[status] || ['chq-badge-pending', status];
  return `<span class="chq-badge ${cls}">${label}</span>`;
}

function _chqRenderList() {
  const tbody = $('#chq-tbody');
  const cur   = window._activeSession?.currency || '';
  if (!_chq.list.length) {
    tbody.innerHTML = `<tr><td colspan="9" class="inv-loading" style="color:var(--text-muted)"><i class="fa fa-inbox"></i> No cheques found</td></tr>`;
    $('#chq-count').textContent = '0 records';
    return;
  }
  tbody.innerHTML = _chq.list.map(c => `
    <tr>
      <td>${_chqBadge(c.status)}</td>
      <td><strong>${c.cheque_number || '—'}</strong></td>
      <td class="${c.status === 'overdue' ? 'chq-overdue-date' : ''}">${c.due_date || '—'}</td>
      <td style="text-align:right;font-weight:600">${cur}${(c.amount||0).toFixed(2)}</td>
      <td>${c.grn_number || '—'}</td>
      <td><div style="font-size:11px;font-weight:600">${c.po_number||'—'}</div><div style="font-size:10px;color:var(--text-muted)">${c.supplier_name||''}</div></td>
      <td style="font-size:11px;color:var(--text-muted)">${c.account||'—'}</td>
      <td style="text-align:center;font-size:11px">${c.paid_at||'—'}</td>
      <td style="text-align:center">
        ${c.status !== 'cleared'
          ? `<button class="chq-clear-btn" data-chq-id="${c.id}" data-chq-num="${c.cheque_number||''}" data-chq-amount="${c.amount||0}" data-chq-grn="${c.grn_number||''}" data-chq-supplier="${c.supplier_name||''}">
               <i class="fa fa-check"></i> Clear
             </button>`
          : `<span style="color:var(--text-muted);font-size:11px">${c.cleared_at||'—'}</span>`
        }
      </td>
    </tr>`).join('');
  $('#chq-count').textContent = `${_chq.list.length} record${_chq.list.length !== 1 ? 's' : ''}`;

  tbody.querySelectorAll('.chq-clear-btn').forEach(btn => {
    btn.addEventListener('click', () => _chqOpenClearModal(
      +btn.dataset.chqId,
      btn.dataset.chqNum,
      +btn.dataset.chqAmount,
      btn.dataset.chqGrn,
      btn.dataset.chqSupplier,
    ));
  });
}

async function _chqOpenClearModal(id, chequeNum, amount, grnNum, supplierName) {
  _chq.clearingId = id;
  const cur = window._activeSession?.currency || '';

  $('#chq-clear-info').innerHTML = `
    <strong>${chequeNum}</strong>
    <div style="margin-top:6px;display:flex;gap:16px">
      <span><span style="color:var(--text-muted)">Amount</span> <strong>${cur}${amount.toFixed(2)}</strong></span>
      <span><span style="color:var(--text-muted)">GRN</span> ${grnNum}</span>
      <span style="color:var(--text-muted)">${supplierName}</span>
    </div>`;

  // Load accounts if needed
  if (!_chq.accounts.length) {
    const res = await API.accounts();
    if (res.status === 200) _chq.accounts = res.body?.data || [];
  }
  $('#chq-clear-account').innerHTML = _chq.accounts.map(a =>
    `<option value="${a.id}">${a.account_name}${a.bank_name ? ' · ' + a.bank_name : ''}</option>`
  ).join('');

  $('#chq-clear-modal').style.display = 'flex';
}

async function _chqSubmitClear() {
  const btn = $('#chq-clear-save');
  btn.disabled = true;
  const accountId = +$('#chq-clear-account').value || null;
  const res = await API.clearCheque(_chq.clearingId, { deduct_account_id: accountId });
  btn.disabled = false;
  if (res.status === 200) {
    toast(res.body?.message || 'Cheque cleared', 'success');
    $('#chq-clear-modal').style.display = 'none';
    _chqLoad();
  } else {
    const msg = Object.values(res.body?.errors || {}).flat().join(', ') || res.body?.message || 'Failed to clear cheque';
    toast(msg, 'error');
  }
}

// Cheque event listeners
$('#chq-refresh-btn')?.addEventListener('click', _chqLoad);
$('#chq-clear-close')?.addEventListener('click',  () => { $('#chq-clear-modal').style.display = 'none'; });
$('#chq-clear-cancel')?.addEventListener('click', () => { $('#chq-clear-modal').style.display = 'none'; });
$('#chq-clear-save')?.addEventListener('click',   _chqSubmitClear);

$$('#chq-filter-btns .grn-pf-btn').forEach(btn => {
  btn.addEventListener('click', () => {
    $$('#chq-filter-btns .grn-pf-btn').forEach(b => b.classList.remove('active'));
    btn.classList.add('active');
    _chq.filter = btn.dataset.chqFilter;
    _chqLoad();
  });
});

// ── End Cheques ───────────────────────────────────────────────────────────

// ── Stock Audit ───────────────────────────────────────────────────────────
const _audit = {
  page: 1, lastPage: 1, total: 0, q: '',
  activeId: null, activeAudit: null, dirty: false,
};
let _auditSearchTimer;

async function _auditLoad() {
  const tbody = $('#audit-tbody');
  if (!tbody) return;
  tbody.innerHTML = `<tr><td colspan="5" class="inv-loading"><i class="fa fa-spinner fa-spin"></i> Loading…</td></tr>`;
  const res = await API.stockAudits(_audit.page);
  if (res.status !== 200) {
    tbody.innerHTML = `<tr><td colspan="5" class="inv-loading" style="color:#ef4444"><i class="fa fa-triangle-exclamation"></i> Failed to load</td></tr>`;
    return;
  }
  const list = res.body?.data ?? [];
  _audit.lastPage = res.body?.meta?.last_page ?? 1;
  _audit.total    = res.body?.meta?.total ?? list.length;
  _auditRenderList(list);
}

function _auditStatusBadge(status) {
  const cls = status === 'finalized' ? 'audit-status-finalized' : 'audit-status-open';
  const lbl = status === 'finalized' ? 'Finalized' : 'Open';
  return `<span class="po-status-badge ${cls}">${lbl}</span>`;
}

function _auditRenderList(list) {
  const tbody = $('#audit-tbody');
  $('#audit-count').textContent = `${_audit.total} audit${_audit.total !== 1 ? 's' : ''}`;
  const prevBtn = $('#audit-prev-btn'); const nextBtn = $('#audit-next-btn');
  const pageLabel = $('#audit-page-label');
  if (prevBtn) prevBtn.disabled = _audit.page <= 1;
  if (nextBtn) nextBtn.disabled = _audit.page >= _audit.lastPage;
  if (pageLabel) pageLabel.textContent = _audit.lastPage > 1 ? `${_audit.page} / ${_audit.lastPage}` : '';

  if (!list.length) {
    tbody.innerHTML = `<tr><td colspan="5" class="inv-loading" style="color:var(--text-muted)"><i class="fa fa-inbox"></i> No audits yet</td></tr>`;
    return;
  }
  tbody.innerHTML = list.map(a => {
    const counted = a.counted_lines_count ?? 0;
    const total   = a.total_lines_count   ?? 0;
    const pct     = total > 0 ? Math.round((counted / total) * 100) : 0;
    return `<tr class="po-row${_audit.activeId === a.id ? ' po-row-active' : ''}" data-audit-id="${a.id}">
      <td><strong>${a.audit_number}</strong></td>
      <td>${a.audit_date || '—'}</td>
      <td>${_auditStatusBadge(a.status)}</td>
      <td>
        <div style="display:flex;align-items:center;gap:6px">
          <div style="flex:1;height:4px;background:var(--border-light);border-radius:2px;min-width:60px">
            <div style="width:${pct}%;height:100%;background:var(--accent);border-radius:2px"></div>
          </div>
          <span style="font-size:10px;color:var(--text-muted);white-space:nowrap">${counted}/${total}</span>
        </div>
      </td>
      <td style="text-align:center;font-size:11px;color:var(--text-muted)">${a.variance_lines_count ?? 0}</td>
    </tr>`;
  }).join('');

  tbody.querySelectorAll('.po-row').forEach(row => {
    row.addEventListener('click', () => _auditSelect(+row.dataset.auditId));
  });
}

async function _auditSelect(id) {
  _audit.activeId = id;
  // re-render list to highlight
  const res0 = await API.stockAudits(_audit.page);
  if (res0.status === 200) _auditRenderList(res0.body?.data ?? []);

  _auditShowDetail(false);
  const res = await API.stockAudit(id);
  if (res.status !== 200) { toast('Failed to load audit', 'error'); return; }
  _audit.activeAudit = res.body?.data ?? res.body;
  _audit.dirty = false;
  _auditRenderDetail(_audit.activeAudit);
}

function _auditShowDetail(show) {
  const empty = $('#audit-detail-empty');
  const view  = $('#audit-detail-view');
  if (!empty || !view) return;
  empty.style.display = show ? 'none' : 'flex';
  view.style.display  = show ? 'flex' : 'none';
}

function _auditRenderDetail(audit) {
  $('#audit-dv-number').textContent = audit.audit_number;
  $('#audit-dv-meta').innerHTML = `${audit.audit_date || '—'} &nbsp;|&nbsp; ${_auditStatusBadge(audit.status)}${audit.notes ? ' &nbsp;|&nbsp; ' + audit.notes : ''}`;

  const isOpen = audit.status !== 'finalized';
  const actDiv = $('#audit-dv-actions');
  actDiv.innerHTML = '';

  if (isOpen) {
    const finalizeBtn = document.createElement('button');
    finalizeBtn.className = 'po-btn-primary';
    finalizeBtn.innerHTML = '<i class="fa fa-lock"></i> Finalize';
    finalizeBtn.addEventListener('click', () => _auditFinalize(audit.id));
    actDiv.appendChild(finalizeBtn);

    const delBtn = document.createElement('button');
    delBtn.className = 'po-btn-ghost';
    delBtn.style.cssText = 'color:#dc2626;border-color:#fca5a5';
    delBtn.innerHTML = '<i class="fa fa-trash"></i>';
    delBtn.title = 'Delete audit';
    delBtn.addEventListener('click', () => _auditDelete(audit.id));
    actDiv.appendChild(delBtn);
  }

  const lines  = audit.lines ?? [];
  const counted = lines.filter(l => l.counted_qty !== null).length;
  $('#audit-progress-label').textContent = `${counted} of ${lines.length} counted`;

  _auditRenderLines(lines, isOpen);
  $('#audit-line-search').value = '';

  const saveBar = $('#audit-save-bar');
  if (saveBar) saveBar.style.display = isOpen ? 'flex' : 'none';

  _auditShowDetail(true);
}

function _auditRenderLines(lines, isOpen, filter) {
  const tbody = $('#audit-lines-tbody');
  const term  = (filter || '').toLowerCase();
  const visible = term ? lines.filter(l =>
    (l.product_name || '').toLowerCase().includes(term) ||
    (l.sku || '').toLowerCase().includes(term)
  ) : lines;

  if (!visible.length) {
    tbody.innerHTML = `<tr><td colspan="6" style="text-align:center;padding:20px;color:var(--text-muted)">No products found</td></tr>`;
    return;
  }

  tbody.innerHTML = visible.map(l => {
    const expected = parseFloat(l.expected_qty) || 0;
    const counted  = l.counted_qty !== null ? parseFloat(l.counted_qty) : '';
    const variance = counted !== '' ? (counted - expected) : 0;
    const vClass   = counted !== '' ? (variance > 0 ? 'surplus' : variance < 0 ? 'deficit' : 'ok') : 'ok';
    const vLabel   = counted !== '' ? (variance > 0 ? '+' : '') + variance.toFixed(3).replace(/\.?0+$/, '') : '—';
    const rowCls   = counted !== '' ? 'audit-row-counted' : '';

    return `<tr class="${rowCls}" data-line-id="${l.id}">
      <td>${l.product_name || '—'}</td>
      <td style="color:var(--text-muted);font-size:11px">${l.sku || '—'}</td>
      <td style="text-align:right">${expected}</td>
      <td style="text-align:right">
        ${isOpen
          ? `<input type="number" class="audit-counted-input" data-line-id="${l.id}" data-expected="${expected}" value="${counted}" min="0" step="any" placeholder="—">`
          : (counted !== '' ? counted : '—')
        }
      </td>
      <td class="audit-variance ${vClass}" data-line-id-v="${l.id}">${vLabel}</td>
      <td>
        ${isOpen
          ? `<input type="text" class="audit-notes-input" data-line-id="${l.id}" value="${(l.notes || '').replace(/"/g, '&quot;')}" placeholder="Notes…" maxlength="500">`
          : (l.notes || '')
        }
      </td>
    </tr>`;
  }).join('');

  if (isOpen) {
    tbody.querySelectorAll('.audit-counted-input').forEach(inp => {
      inp.addEventListener('input', () => {
        const expected = parseFloat(inp.dataset.expected) || 0;
        const counted  = inp.value !== '' ? parseFloat(inp.value) : null;
        const variance = counted !== null ? counted - expected : 0;
        const vCell    = tbody.querySelector(`td[data-line-id-v="${inp.dataset.lineId}"]`);
        if (vCell) {
          const v = counted !== null ? (variance > 0 ? '+' : '') + variance.toFixed(3).replace(/\.?0+$/, '') : '—';
          vCell.textContent = v;
          vCell.className = 'audit-variance ' + (counted !== null ? (variance > 0 ? 'surplus' : variance < 0 ? 'deficit' : 'ok') : 'ok');
        }
        const row = inp.closest('tr');
        if (row) row.classList.toggle('audit-row-counted', counted !== null);
        _audit.dirty = true;
      });
    });
    tbody.querySelectorAll('.audit-notes-input').forEach(inp => {
      inp.addEventListener('input', () => { _audit.dirty = true; });
    });
  }
}

async function _auditSaveCounts() {
  const btn = $('#audit-save-btn');
  if (btn) btn.disabled = true;
  const lines = {};
  $$('#audit-lines-tbody .audit-counted-input').forEach(inp => {
    const id = inp.dataset.lineId;
    const notesInp = document.querySelector(`.audit-notes-input[data-line-id="${id}"]`);
    lines[id] = {
      counted_qty: inp.value !== '' ? parseFloat(inp.value) : null,
      notes:       notesInp ? notesInp.value.trim() || null : null,
    };
  });
  const res = await API.saveAuditLines(_audit.activeId, { lines });
  if (btn) btn.disabled = false;
  if (res.status === 200) {
    toast('Counts saved', 'success');
    _audit.dirty = false;
    _audit.activeAudit = res.body?.data ?? res.body;
    _auditRenderDetail(_audit.activeAudit);
    _auditLoad();
  } else {
    toast(res.body?.message || 'Failed to save', 'error');
  }
}

async function _auditFinalize(id) {
  if (!confirm('Finalize this audit? Stock quantities will be updated to match counted values. This cannot be undone.')) return;
  const res = await API.finalizeAudit(id);
  if (res.status === 200) {
    toast('Audit finalized — stock updated', 'success');
    _audit.activeAudit = res.body?.data ?? res.body;
    _auditRenderDetail(_audit.activeAudit);
    _auditLoad();
  } else {
    toast(res.body?.message || 'Failed to finalize', 'error');
  }
}

async function _auditDelete(id) {
  if (!confirm('Delete this audit? This cannot be undone.')) return;
  const res = await API.deleteAudit(id);
  if (res.status === 200) {
    toast('Audit deleted', 'success');
    _audit.activeId = null; _audit.activeAudit = null;
    _auditShowDetail(false);
    _auditLoad();
  } else {
    toast(res.body?.message || 'Failed to delete', 'error');
  }
}

// ── Audit event listeners ─────────────────────────────────────────────────
$('#audit-new-btn')?.addEventListener('click', () => {
  $('#audit-f-date').value  = new Date().toISOString().slice(0, 10);
  $('#audit-f-notes').value = '';
  $('#audit-modal').style.display = 'flex';
  setTimeout(() => $('#audit-f-date')?.focus(), 60);
});
$('#audit-modal-close')?.addEventListener('click',  () => { $('#audit-modal').style.display = 'none'; });
$('#audit-modal-cancel')?.addEventListener('click', () => { $('#audit-modal').style.display = 'none'; });
$('#audit-modal-save')?.addEventListener('click', async () => {
  const btn  = $('#audit-modal-save');
  const date = $('#audit-f-date').value;
  if (!date) { toast('Enter an audit date', 'error'); return; }
  btn.disabled = true;
  const res = await API.createStockAudit({ audit_date: date, notes: $('#audit-f-notes').value.trim() || null });
  btn.disabled = false;
  if (res.status === 201) {
    toast(`${res.body?.data?.audit_number || 'Audit'} created`, 'success');
    $('#audit-modal').style.display = 'none';
    await _auditLoad();
    const newAudit = res.body?.data;
    if (newAudit?.id) _auditSelect(newAudit.id);
  } else {
    toast(res.body?.message || 'Failed to create audit', 'error');
  }
});

$('#audit-save-btn')?.addEventListener('click',  _auditSaveCounts);
$('#audit-reset-btn')?.addEventListener('click', () => {
  if (_audit.activeAudit) _auditRenderDetail(_audit.activeAudit);
});

$('#audit-line-search')?.addEventListener('input', e => {
  if (!_audit.activeAudit) return;
  _auditRenderLines(_audit.activeAudit.lines ?? [], _audit.activeAudit.status !== 'finalized', e.target.value.trim());
});

$('#audit-prev-btn')?.addEventListener('click', () => { if (_audit.page > 1) { _audit.page--; _auditLoad(); } });
$('#audit-next-btn')?.addEventListener('click', () => { if (_audit.page < _audit.lastPage) { _audit.page++; _auditLoad(); } });

$('#audit-search')?.addEventListener('input', e => {
  clearTimeout(_auditSearchTimer);
  _audit.q = e.target.value.trim();
  _audit.page = 1;
  _auditSearchTimer = setTimeout(_auditLoad, 300);
});

// ── End Stock Audit ───────────────────────────────────────────────────────

// ── Product Categories ────────────────────────────────────────────────────
const _cat = {
  q: '', status: '', page: 1, lastPage: 1, total: 0,
  editingId: null, parentOpts: [],
};
let _catSearchTimer;

async function _catLoad() {
  const tbody = $('#cat-tbody');
  if (!tbody) return;
  tbody.innerHTML = `<tr><td colspan="6" class="inv-loading"><i class="fa fa-spinner fa-spin"></i> Loading…</td></tr>`;
  const res = await API.categories(_cat.q, _cat.status, _cat.page);
  if (res.status !== 200) {
    tbody.innerHTML = `<tr><td colspan="6" class="inv-loading" style="color:#ef4444"><i class="fa fa-triangle-exclamation"></i> Failed to load</td></tr>`;
    return;
  }
  const list = res.body?.data ?? [];
  _cat.lastPage = res.body?.meta?.last_page ?? 1;
  _cat.total    = res.body?.meta?.total      ?? list.length;

  $('#cat-count').textContent = `${_cat.total} categor${_cat.total !== 1 ? 'ies' : 'y'}`;
  const prevBtn = $('#cat-prev-btn'), nextBtn = $('#cat-next-btn'), pageLabel = $('#cat-page-label');
  if (prevBtn)   prevBtn.disabled   = _cat.page <= 1;
  if (nextBtn)   nextBtn.disabled   = _cat.page >= _cat.lastPage;
  if (pageLabel) pageLabel.textContent = _cat.lastPage > 1 ? `${_cat.page} / ${_cat.lastPage}` : '';

  if (!list.length) {
    tbody.innerHTML = `<tr><td colspan="6" class="inv-loading" style="color:var(--text-muted)"><i class="fa fa-inbox"></i> No categories yet</td></tr>`;
    return;
  }

  tbody.innerHTML = list.map(c => `
    <tr>
      <td>
        ${c.parent_id ? '<span class="cat-indent" style="color:var(--border);margin-right:4px">└</span>' : ''}
        <strong>${c.name}</strong>
        ${c.description ? `<div style="font-size:10px;color:var(--text-muted);margin-top:2px">${c.description}</div>` : ''}
      </td>
      <td style="color:var(--text-muted);font-size:11px">${c.parent_name || '—'}</td>
      <td style="text-align:center">
        <span class="cat-status-badge ${c.is_active ? 'cat-status-active' : 'cat-status-inactive'}">${c.is_active ? 'Active' : 'Inactive'}</span>
      </td>
      <td style="text-align:right;color:var(--text-muted)">${c.products_count ?? 0}</td>
      <td style="text-align:right;color:var(--text-muted)">${c.children_count ?? 0}</td>
      <td style="text-align:center">
        <div style="display:flex;justify-content:center;gap:6px">
          <button class="cat-action-btn cat-edit-btn" data-cat-id="${c.id}"><i class="fa fa-pen"></i> Edit</button>
          <button class="cat-action-btn danger cat-del-btn" data-cat-id="${c.id}" data-cat-name="${c.name.replace(/"/g,'&quot;')}" data-cat-products="${c.products_count??0}" data-cat-children="${c.children_count??0}">
            <i class="fa fa-trash"></i>
          </button>
        </div>
      </td>
    </tr>`).join('');

  tbody.querySelectorAll('.cat-edit-btn').forEach(btn => {
    btn.addEventListener('click', () => _catOpenModal(+btn.dataset.catId));
  });
  tbody.querySelectorAll('.cat-del-btn').forEach(btn => {
    btn.addEventListener('click', () => _catDelete(+btn.dataset.catId, btn.dataset.catName, +btn.dataset.catChildren));
  });
}

async function _catLoadParentOpts(excludeId) {
  const res = await API.categoryParentOpts(excludeId || null);
  _cat.parentOpts = res.status === 200 ? (res.body?.data ?? []) : [];
  const sel = $('#cat-f-parent');
  sel.innerHTML = '<option value="">— None (top-level) —</option>' +
    _cat.parentOpts.map(o => `<option value="${o.id}">${'·· '.repeat(o.depth)}${o.label}</option>`).join('');
}

async function _catOpenModal(editId) {
  _cat.editingId = editId || null;
  $('#cat-modal-title').textContent = editId ? 'Edit Category' : 'New Category';
  $('#cat-f-name').value        = '';
  $('#cat-f-description').value = '';
  $('#cat-f-active').checked    = true;
  $('#cat-f-parent').innerHTML  = '<option value="">Loading…</option>';
  $('#cat-modal').style.display = 'flex';
  await _catLoadParentOpts(editId);

  if (editId) {
    // Find from current list
    const tbody = $('#cat-tbody');
    const editBtn = tbody?.querySelector(`.cat-edit-btn[data-cat-id="${editId}"]`);
    const row = editBtn?.closest('tr');
    // Re-fetch from API if we don't have full data locally — use a quick GET
    const res = await API.categories('', '', 1);
    // Find the category in pages — easiest is just re-fetch the page that contains it
    // Since we already have the list, grab from rendered data
    const allRows = tbody ? [...tbody.querySelectorAll('tr')] : [];
    const targetRow = allRows.find(r => r.querySelector(`.cat-edit-btn[data-cat-id="${editId}"]`));
    if (targetRow) {
      const nameEl = targetRow.querySelector('strong');
      if (nameEl) $('#cat-f-name').value = nameEl.textContent;
      const descEl = targetRow.querySelector('div[style*="10px"]');
      if (descEl) $('#cat-f-description').value = descEl.textContent;
      const isActive = targetRow.querySelector('.cat-status-active') !== null;
      $('#cat-f-active').checked = isActive;
    }
    // Set parent
    const sel = $('#cat-f-parent');
    // get current parent from data- we stored in edit btn via api. Re-render needed
    // Simply fetch full list for the current page and find it
    const fullList = res.body?.data ?? [];
    const found = fullList.find(c => c.id === editId);
    if (found) {
      $('#cat-f-name').value        = found.name;
      $('#cat-f-description').value = found.description || '';
      $('#cat-f-active').checked    = found.is_active;
      if (found.parent_id && sel) sel.value = String(found.parent_id);
    }
  }

  setTimeout(() => $('#cat-f-name')?.focus(), 60);
}

async function _catSave() {
  const btn  = $('#cat-modal-save');
  const name = $('#cat-f-name').value.trim();
  if (!name) { toast('Category name is required', 'error'); return; }

  btn.disabled = true;
  const body = {
    name,
    description: $('#cat-f-description').value.trim() || null,
    parent_id:   $('#cat-f-parent').value ? +$('#cat-f-parent').value : null,
    is_active:   $('#cat-f-active').checked,
  };

  const res = _cat.editingId
    ? await API.updateCategory(_cat.editingId, body)
    : await API.createCategory(body);
  btn.disabled = false;

  if (res.status === 200 || res.status === 201) {
    toast(_cat.editingId ? 'Category updated' : 'Category created', 'success');
    $('#cat-modal').style.display = 'none';
    _catLoad();
  } else {
    const msg = Object.values(res.body?.errors || {}).flat().join(', ') || res.body?.message || 'Failed to save';
    toast(msg, 'error');
  }
}

async function _catDelete(id, name, childrenCount) {
  if (childrenCount > 0) {
    toast(`Cannot delete "${name}" — it has sub-categories. Remove them first.`, 'error');
    return;
  }
  if (!confirm(`Delete category "${name}"?`)) return;
  const res = await API.deleteCategory(id);
  if (res.status === 200) {
    toast('Category deleted', 'success');
    _catLoad();
  } else {
    const msg = Object.values(res.body?.errors || {}).flat().join(', ') || res.body?.message || 'Failed to delete';
    toast(msg, 'error');
  }
}

// Category event listeners
$('#cat-add-btn')?.addEventListener('click', () => _catOpenModal(null));
$('#cat-modal-close')?.addEventListener('click',  () => { $('#cat-modal').style.display = 'none'; });
$('#cat-modal-cancel')?.addEventListener('click', () => { $('#cat-modal').style.display = 'none'; });
$('#cat-modal-save')?.addEventListener('click',   _catSave);
$('#cat-f-name')?.addEventListener('keydown', e => { if (e.key === 'Enter') _catSave(); });

$('#cat-search')?.addEventListener('input', e => {
  clearTimeout(_catSearchTimer);
  _cat.q = e.target.value.trim();
  _cat.page = 1;
  _catSearchTimer = setTimeout(_catLoad, 300);
});

$$('#cat-status-filter .grn-pf-btn').forEach(btn => {
  btn.addEventListener('click', () => {
    $$('#cat-status-filter .grn-pf-btn').forEach(b => b.classList.remove('active'));
    btn.classList.add('active');
    _cat.status = btn.dataset.catStatus;
    _cat.page   = 1;
    _catLoad();
  });
});

$('#cat-prev-btn')?.addEventListener('click', () => { if (_cat.page > 1)              { _cat.page--; _catLoad(); } });
$('#cat-next-btn')?.addEventListener('click', () => { if (_cat.page < _cat.lastPage)  { _cat.page++; _catLoad(); } });

// ── End Product Categories ────────────────────────────────────────────────

// ── Product Units ─────────────────────────────────────────────────────────
const _unit = { list: [], q: '', editingId: null };

async function _unitLoad() {
  const tbody = $('#unit-tbody');
  if (!tbody) return;
  tbody.innerHTML = `<tr><td colspan="5" class="inv-loading"><i class="fa fa-spinner fa-spin"></i> Loading…</td></tr>`;
  const res = await API.units();
  if (res.status !== 200) {
    tbody.innerHTML = `<tr><td colspan="5" class="inv-loading" style="color:#ef4444"><i class="fa fa-triangle-exclamation"></i> Failed to load</td></tr>`;
    return;
  }
  _unit.list = res.body?.data ?? [];
  _unitRender();
}

function _unitRender() {
  const tbody = $('#unit-tbody');
  const q     = _unit.q.toLowerCase();
  const list  = q ? _unit.list.filter(u => u.name.toLowerCase().includes(q) || (u.abbreviation||'').toLowerCase().includes(q)) : _unit.list;

  $('#unit-count').textContent = `${_unit.list.length} unit${_unit.list.length !== 1 ? 's' : ''}`;

  if (!list.length) {
    tbody.innerHTML = `<tr><td colspan="5" class="inv-loading" style="color:var(--text-muted)"><i class="fa fa-inbox"></i> No units yet</td></tr>`;
    return;
  }

  tbody.innerHTML = list.map(u => `
    <tr>
      <td><strong>${u.name}</strong></td>
      <td><code style="background:var(--surface2);padding:2px 6px;border-radius:4px;font-size:11px">${u.abbreviation || '—'}</code></td>
      <td style="text-align:center">
        <span class="cat-status-badge ${u.is_active ? 'cat-status-active' : 'cat-status-inactive'}">${u.is_active ? 'Active' : 'Inactive'}</span>
      </td>
      <td style="text-align:right;color:var(--text-muted)">${u.products_count ?? 0}</td>
      <td style="text-align:center">
        <div style="display:flex;justify-content:center;gap:6px">
          <button class="cat-action-btn unit-edit-btn" data-unit-id="${u.id}"><i class="fa fa-pen"></i> Edit</button>
          <button class="cat-action-btn danger unit-del-btn" data-unit-id="${u.id}" data-unit-name="${u.name.replace(/"/g,'&quot;')}" data-unit-products="${u.products_count??0}">
            <i class="fa fa-trash"></i>
          </button>
        </div>
      </td>
    </tr>`).join('');

  tbody.querySelectorAll('.unit-edit-btn').forEach(btn => {
    btn.addEventListener('click', () => _unitOpenModal(+btn.dataset.unitId));
  });
  tbody.querySelectorAll('.unit-del-btn').forEach(btn => {
    btn.addEventListener('click', () => _unitDelete(+btn.dataset.unitId, btn.dataset.unitName, +btn.dataset.unitProducts));
  });
}

function _unitOpenModal(editId) {
  _unit.editingId = editId || null;
  $('#unit-modal-title').textContent = editId ? 'Edit Unit' : 'New Unit';

  if (editId) {
    const u = _unit.list.find(x => x.id === editId);
    if (u) {
      $('#unit-f-name').value    = u.name;
      $('#unit-f-abbr').value    = u.abbreviation || '';
      $('#unit-f-active').checked = u.is_active;
    }
  } else {
    $('#unit-f-name').value     = '';
    $('#unit-f-abbr').value     = '';
    $('#unit-f-active').checked  = true;
  }

  $('#unit-modal').style.display = 'flex';
  setTimeout(() => $('#unit-f-name')?.focus(), 60);
}

async function _unitSave() {
  const btn  = $('#unit-modal-save');
  const name = $('#unit-f-name').value.trim();
  if (!name) { toast('Unit name is required', 'error'); return; }

  btn.disabled = true;
  const body = {
    name,
    abbreviation: $('#unit-f-abbr').value.trim() || null,
    is_active:    $('#unit-f-active').checked,
  };

  const res = _unit.editingId
    ? await API.updateUnit(_unit.editingId, body)
    : await API.createUnit(body);
  btn.disabled = false;

  if (res.status === 200 || res.status === 201) {
    toast(_unit.editingId ? 'Unit updated' : 'Unit created', 'success');
    $('#unit-modal').style.display = 'none';
    _unitLoad();
  } else {
    const msg = Object.values(res.body?.errors || {}).flat().join(', ') || res.body?.message || 'Failed to save';
    toast(msg, 'error');
  }
}

async function _unitDelete(id, name, productsCount) {
  if (productsCount > 0) {
    toast(`Cannot delete "${name}" — it is used by ${productsCount} product${productsCount !== 1 ? 's' : ''}.`, 'error');
    return;
  }
  if (!confirm(`Delete unit "${name}"?`)) return;
  const res = await API.deleteUnit(id);
  if (res.status === 200) {
    toast('Unit deleted', 'success');
    _unitLoad();
  } else {
    toast(res.body?.message || 'Failed to delete', 'error');
  }
}

// Unit event listeners
$('#unit-add-btn')?.addEventListener('click',       () => _unitOpenModal(null));
$('#unit-modal-close')?.addEventListener('click',   () => { $('#unit-modal').style.display = 'none'; });
$('#unit-modal-cancel')?.addEventListener('click',  () => { $('#unit-modal').style.display = 'none'; });
$('#unit-modal-save')?.addEventListener('click',    _unitSave);
$('#unit-f-name')?.addEventListener('keydown',      e => { if (e.key === 'Enter') _unitSave(); });

$('#unit-search')?.addEventListener('input', e => {
  _unit.q = e.target.value.trim();
  _unitRender();
});

// ── End Product Units ─────────────────────────────────────────────────────

// ── Product Discounts ─────────────────────────────────────────────────────
const _disc = {
  list: [], q: '', status: '', editingId: null,
  productSearchTimer: null, productResults: [],
};

async function _discLoad() {
  const tbody = $('#disc-tbody');
  tbody.innerHTML = `<tr><td colspan="9" class="inv-loading"><i class="fa fa-spinner fa-spin"></i> Loading…</td></tr>`;
  const res = await API.discounts(_disc.q, _disc.status);
  if (res.status !== 200) {
    tbody.innerHTML = `<tr><td colspan="9" class="inv-loading"><i class="fa fa-triangle-exclamation"></i> Failed to load</td></tr>`;
    return;
  }
  _disc.list = res.body?.data || [];
  _discRender();
}

function _discStatusBadge(d) {
  if (!d.is_active) return `<span class="disc-status-inactive">Inactive</span>`;
  if (d.is_currently_active) return `<span class="disc-status-active">Active</span>`;
  const now = new Date();
  if (d.ends_at && new Date(d.ends_at) < now) return `<span class="disc-status-expired">Expired</span>`;
  return `<span class="disc-status-inactive">Pending</span>`;
}

function _discRender() {
  const tbody = $('#disc-tbody');
  $('#disc-count').textContent = `${_disc.list.length} discount${_disc.list.length !== 1 ? 's' : ''}`;
  if (!_disc.list.length) {
    tbody.innerHTML = `<tr><td colspan="9" class="inv-loading" style="font-size:12px;padding:32px 10px">No discounts found. Click <b>New Discount</b> to add one.</td></tr>`;
    return;
  }
  tbody.innerHTML = _disc.list.map(d => {
    const typeLabel = d.discount_type === 'percentage'
      ? `${d.discount_value}%`
      : `${state.currency}${d.discount_value.toFixed(2)}`;
    return `<tr>
      <td style="font-weight:600">${escHtml(d.name)}</td>
      <td>${escHtml(d.product_name)}</td>
      <td style="color:var(--text-muted)">${d.selling_unit_label ? escHtml(d.selling_unit_label) : '<span style="color:var(--text-muted);font-size:11px">Base price</span>'}</td>
      <td><span style="font-size:11px;color:var(--text-muted)">${d.discount_type === 'percentage' ? 'Percentage' : 'Flat'}</span></td>
      <td style="text-align:right;font-weight:700;color:var(--accent)">${typeLabel}</td>
      <td style="font-size:11px;color:var(--text-muted)">${d.starts_at || '—'}</td>
      <td style="font-size:11px;color:var(--text-muted)">${d.ends_at || '—'}</td>
      <td style="text-align:center">${_discStatusBadge(d)}</td>
      <td style="text-align:center">
        <button class="cat-action-btn" onclick="_discOpenModal(${d.id})"><i class="fa fa-pen"></i> Edit</button>
        <button class="cat-action-btn danger" onclick="_discDelete(${d.id})"><i class="fa fa-trash"></i></button>
      </td>
    </tr>`;
  }).join('');
}

function _discOpenModal(id) {
  _disc.editingId = id || null;
  const isEdit = !!id;
  $('#disc-modal-title').textContent = isEdit ? 'Edit Discount' : 'New Discount';

  // Reset form
  $('#disc-f-name').value = '';
  $('#disc-f-product-search').value = '';
  $('#disc-f-product-id').value = '';
  $('#disc-f-selling-unit').innerHTML = '<option value="">— Base price —</option>';
  $('#disc-f-value').value = '';
  $('#disc-f-starts').value = '';
  $('#disc-f-ends').value = '';
  $('#disc-f-active').checked = true;
  _discSetType('flat');
  $('#disc-product-dd').style.display = 'none';

  if (isEdit) {
    const d = _disc.list.find(x => x.id === id);
    if (d) {
      $('#disc-f-name').value = d.name;
      $('#disc-f-product-search').value = d.product_name;
      $('#disc-f-product-id').value = d.product_id;
      $('#disc-f-value').value = d.discount_value;
      $('#disc-f-starts').value = d.starts_at || '';
      $('#disc-f-ends').value = d.ends_at || '';
      $('#disc-f-active').checked = d.is_active;
      _discSetType(d.discount_type);
      // Load selling units for this product
      _discLoadSellingUnits(d.product_id, d.product_selling_unit_id);
    }
  }

  $('#discount-modal').style.display = 'flex';
  setTimeout(() => $('#disc-f-name').focus(), 80);
}

function _discSetType(type) {
  $('#disc-f-type').value = type;
  $$('.disc-type-btn').forEach(btn => {
    btn.classList.toggle('active', btn.dataset.discType === type);
  });
  $('#disc-value-label').innerHTML = type === 'percentage'
    ? 'Discount Percentage (%) <span class="po-required">*</span>'
    : 'Discount Amount <span class="po-required">*</span>';
}

async function _discLoadSellingUnits(productId, selectedId) {
  const sel = $('#disc-f-selling-unit');
  sel.innerHTML = '<option value="">— Base price —</option>';
  if (!productId) return;
  const res = await API.discountProductOpts('');
  if (res.status !== 200) return;
  const products = res.body?.data || [];
  const product = products.find(p => p.id === productId);
  if (!product) return;
  product.selling_units.forEach(su => {
    const opt = document.createElement('option');
    opt.value = su.id;
    opt.textContent = su.label;
    if (selectedId && su.id === selectedId) opt.selected = true;
    sel.appendChild(opt);
  });
}

let _discProdTimer;
function _discSearchProducts(q) {
  clearTimeout(_discProdTimer);
  const dd = $('#disc-product-dd');
  if (!q) { dd.style.display = 'none'; return; }
  _discProdTimer = setTimeout(async () => {
    dd.innerHTML = '<div style="padding:8px 12px;font-size:12px;color:var(--text-muted)"><i class="fa fa-spinner fa-spin"></i></div>';
    dd.style.display = 'block';
    const res = await API.discountProductOpts(q);
    if (res.status !== 200) { dd.style.display = 'none'; return; }
    const products = res.body?.data || [];
    if (!products.length) {
      dd.innerHTML = '<div style="padding:8px 12px;font-size:12px;color:var(--text-muted)">No products found</div>';
      return;
    }
    dd.innerHTML = products.map(p => `<div class="po-aim-dd-item" data-id="${p.id}" data-name="${escHtml(p.name)}">${escHtml(p.name)}</div>`).join('');
    dd.querySelectorAll('.po-aim-dd-item').forEach(item => {
      item.addEventListener('click', () => {
        const pid = parseInt(item.dataset.id);
        const pname = item.dataset.name;
        $('#disc-f-product-search').value = pname;
        $('#disc-f-product-id').value = pid;
        dd.style.display = 'none';
        // Load selling units
        const product = products.find(p => p.id === pid);
        const sel = $('#disc-f-selling-unit');
        sel.innerHTML = '<option value="">— Base price —</option>';
        if (product && product.selling_units.length) {
          product.selling_units.forEach(su => {
            const opt = document.createElement('option');
            opt.value = su.id;
            opt.textContent = su.label;
            sel.appendChild(opt);
          });
        }
      });
    });
  }, 280);
}

async function _discSave() {
  const name     = $('#disc-f-name').value.trim();
  const productId= parseInt($('#disc-f-product-id').value) || 0;
  const unitId   = parseInt($('#disc-f-selling-unit').value) || null;
  const type     = $('#disc-f-type').value;
  const value    = parseFloat($('#disc-f-value').value) || 0;
  const starts   = $('#disc-f-starts').value || null;
  const ends     = $('#disc-f-ends').value || null;
  const active   = $('#disc-f-active').checked;

  if (!name)       { toast('Name is required', 'error'); return; }
  if (!productId)  { toast('Select a product', 'error'); return; }
  if (value <= 0)  { toast('Discount value must be greater than 0', 'error'); return; }

  const body = {
    name,
    product_id:              productId,
    product_selling_unit_id: unitId,
    discount_type:           type,
    discount_value:          value,
    starts_at:               starts,
    ends_at:                 ends,
    is_active:               active,
  };

  const btn = $('#disc-modal-save');
  btn.disabled = true;

  const res = _disc.editingId
    ? await API.updateDiscount(_disc.editingId, body)
    : await API.createDiscount(body);

  btn.disabled = false;

  if (res.status === 200 || res.status === 201) {
    toast(_disc.editingId ? 'Discount updated' : 'Discount created', 'success');
    $('#discount-modal').style.display = 'none';
    _discLoad();
  } else {
    const errors = res.body?.errors;
    const first  = errors ? Object.values(errors)[0]?.[0] : null;
    toast(first || res.body?.message || 'Failed to save', 'error');
  }
}

async function _discDelete(id) {
  const d = _disc.list.find(x => x.id === id);
  if (!d) return;
  if (!confirm(`Delete discount "${d.name}"?`)) return;
  const res = await API.deleteDiscount(id);
  if (res.status === 200) {
    toast('Discount deleted', 'success');
    _discLoad();
  } else {
    toast(res.body?.message || 'Failed to delete', 'error');
  }
}

// Discount event listeners
$('#disc-add-btn')?.addEventListener('click',      () => _discOpenModal(null));
$('#disc-modal-close')?.addEventListener('click',  () => { $('#discount-modal').style.display = 'none'; });
$('#disc-modal-cancel')?.addEventListener('click', () => { $('#discount-modal').style.display = 'none'; });
$('#disc-modal-save')?.addEventListener('click',   _discSave);

$$('.disc-type-btn').forEach(btn => {
  btn?.addEventListener('click', () => _discSetType(btn.dataset.discType));
});

$('#disc-f-product-search')?.addEventListener('input', e => _discSearchProducts(e.target.value.trim()));
$('#disc-f-product-search')?.addEventListener('blur', () => setTimeout(() => { $('#disc-product-dd').style.display = 'none'; }, 200));

$('#disc-search')?.addEventListener('input', e => {
  _disc.q = e.target.value.trim();
  clearTimeout(_disc.productSearchTimer);
  _disc.productSearchTimer = setTimeout(_discLoad, 300);
});

$$('.disc-ft-btn').forEach(btn => {
  btn?.addEventListener('click', () => {
    $$('.disc-ft-btn').forEach(b => b.classList.remove('active'));
    btn.classList.add('active');
    _disc.status = btn.dataset.discStatus;
    _discLoad();
  });
});

// ── End Product Discounts ─────────────────────────────────────────────────

// ── Product Brands ────────────────────────────────────────────────────────
const _brand = { list: [], q: '', status: '', searchTimer: null, editingId: null };

async function _brandLoad() {
  const tbody = $('#brand-tbody');
  tbody.innerHTML = `<tr><td colspan="6" class="inv-loading"><i class="fa fa-spinner fa-spin"></i> Loading…</td></tr>`;
  const res = await API.brands(_brand.q, _brand.status);
  if (res.status !== 200) {
    tbody.innerHTML = `<tr><td colspan="6" class="inv-loading"><i class="fa fa-triangle-exclamation"></i> Failed to load</td></tr>`;
    return;
  }
  _brand.list = res.body?.data || [];
  _brandRender();
}

function _brandRender() {
  const tbody = $('#brand-tbody');
  $('#brand-count').textContent = `${_brand.list.length} brand${_brand.list.length !== 1 ? 's' : ''}`;
  if (!_brand.list.length) {
    tbody.innerHTML = `<tr><td colspan="6" class="inv-loading" style="font-size:12px;padding:32px 10px">No brands found. Click <b>New Brand</b> to add one.</td></tr>`;
    return;
  }
  tbody.innerHTML = _brand.list.map(b => {
    const statusBadge = b.is_active
      ? `<span class="cat-status-badge cat-status-active">Active</span>`
      : `<span class="cat-status-badge cat-status-inactive">Inactive</span>`;
    const website = b.website
      ? `<span style="font-size:11px;color:var(--text-muted);white-space:nowrap;overflow:hidden;text-overflow:ellipsis;max-width:160px;display:inline-block">${escHtml(b.website)}</span>`
      : `<span style="color:var(--text-muted)">—</span>`;
    const desc = b.description
      ? `<span style="font-size:11px;color:var(--text-muted)">${escHtml(b.description.slice(0, 60))}${b.description.length > 60 ? '…' : ''}</span>`
      : `<span style="color:var(--text-muted)">—</span>`;
    return `<tr>
      <td style="font-weight:600">${escHtml(b.name)}</td>
      <td>${desc}</td>
      <td>${website}</td>
      <td style="text-align:right">${b.products_count}</td>
      <td style="text-align:center">${statusBadge}</td>
      <td style="text-align:center">
        <button class="cat-action-btn" onclick="_brandOpenModal(${b.id})"><i class="fa fa-pen"></i> Edit</button>
        <button class="cat-action-btn danger" onclick="_brandDelete(${b.id})"><i class="fa fa-trash"></i></button>
      </td>
    </tr>`;
  }).join('');
}

function _brandOpenModal(id) {
  _brand.editingId = id || null;
  $('#brand-modal-title').textContent = id ? 'Edit Brand' : 'New Brand';
  $('#brand-f-name').value        = '';
  $('#brand-f-description').value = '';
  $('#brand-f-website').value     = '';
  $('#brand-f-active').checked    = true;

  if (id) {
    const b = _brand.list.find(x => x.id === id);
    if (b) {
      $('#brand-f-name').value        = b.name;
      $('#brand-f-description').value = b.description || '';
      $('#brand-f-website').value     = b.website || '';
      $('#brand-f-active').checked    = b.is_active;
    }
  }

  $('#brand-modal').style.display = 'flex';
  setTimeout(() => $('#brand-f-name').focus(), 80);
}

async function _brandSave() {
  const name   = $('#brand-f-name').value.trim();
  if (!name) { toast('Brand name is required', 'error'); return; }

  const body = {
    name,
    description: $('#brand-f-description').value.trim() || null,
    website:     $('#brand-f-website').value.trim() || null,
    is_active:   $('#brand-f-active').checked,
  };

  const btn = $('#brand-modal-save');
  btn.disabled = true;

  const res = _brand.editingId
    ? await API.updateBrand(_brand.editingId, body)
    : await API.createBrand(body);

  btn.disabled = false;

  if (res.status === 200 || res.status === 201) {
    toast(_brand.editingId ? 'Brand updated' : 'Brand created', 'success');
    $('#brand-modal').style.display = 'none';
    _brandLoad();
  } else {
    const errors = res.body?.errors;
    const first  = errors ? Object.values(errors)[0]?.[0] : null;
    toast(first || res.body?.message || 'Failed to save', 'error');
  }
}

async function _brandDelete(id) {
  const b = _brand.list.find(x => x.id === id);
  if (!b) return;
  if (!confirm(`Delete brand "${b.name}"?`)) return;
  const res = await API.deleteBrand(id);
  if (res.status === 200) {
    toast('Brand deleted', 'success');
    _brandLoad();
  } else {
    toast(res.body?.message || 'Failed to delete', 'error');
  }
}

$('#brand-add-btn')?.addEventListener('click',      () => _brandOpenModal(null));
$('#brand-modal-close')?.addEventListener('click',  () => { $('#brand-modal').style.display = 'none'; });
$('#brand-modal-cancel')?.addEventListener('click', () => { $('#brand-modal').style.display = 'none'; });
$('#brand-modal-save')?.addEventListener('click',   _brandSave);
$('#brand-f-name')?.addEventListener('keydown',     e => { if (e.key === 'Enter') _brandSave(); });

$('#brand-search')?.addEventListener('input', e => {
  _brand.q = e.target.value.trim();
  clearTimeout(_brand.searchTimer);
  _brand.searchTimer = setTimeout(_brandLoad, 300);
});

$$('.brand-ft-btn').forEach(btn => {
  btn?.addEventListener('click', () => {
    $$('.brand-ft-btn').forEach(b => b.classList.remove('active'));
    btn.classList.add('active');
    _brand.status = btn.dataset.brandStatus;
    _brandLoad();
  });
});
// ── End Product Brands ────────────────────────────────────────────────────

// ── Barcode Sheets ────────────────────────────────────────────────────────
const _bc = { q: '', timer: null, products: [], selected: {} };

async function _barcodeLoad() {
  const list = $('#bc-product-list');
  if (!list) return;
  list.innerHTML = `<div class="inv-loading"><i class="fa fa-spinner fa-spin"></i> Loading…</div>`;
  const res = await API.productSearch(_bc.q, 200);
  if (res.status !== 200) {
    list.innerHTML = `<div class="inv-loading" style="color:#ef4444"><i class="fa fa-triangle-exclamation"></i> Failed to load</div>`;
    return;
  }
  _bc.products = res.body?.data ?? [];
  _barcodeRenderList();
}

function _barcodeRenderList() {
  const list = $('#bc-product-list');
  if (!list) return;
  if (!_bc.products.length) {
    list.innerHTML = `<div class="inv-loading"><i class="fa fa-boxes-stacked"></i> No products found</div>`;
    return;
  }
  list.innerHTML = _bc.products.map(p => {
    const barcode = p.sku || String(p.id);
    const sel = !!_bc.selected[p.id];
    const qty = _bc.selected[p.id]?.qty ?? 1;
    return `<div class="bc-product-row${sel ? ' bc-selected' : ''}" data-pid="${p.id}">
      <label class="bc-chk-wrap bc-row-chk">
        <input type="checkbox" class="bc-chk" data-pid="${p.id}"${sel ? ' checked' : ''}>
      </label>
      <div class="bc-row-info">
        <div class="bc-row-name">${escHtml(p.name)}</div>
        <div class="bc-row-sku">${escHtml(barcode)}</div>
      </div>
      <div class="bc-qty-wrap">
        <label class="bc-qty-lbl">Qty</label>
        <input type="number" class="bc-qty-inp" data-pid="${p.id}" value="${qty}" min="1" max="500" ${sel ? '' : 'disabled'}>
      </div>
    </div>`;
  }).join('');
  _barcodeUpdateCount();
  // wire checkboxes
  list.querySelectorAll('.bc-chk').forEach(chk => {
    chk.addEventListener('change', () => {
      const pid = chk.dataset.pid;
      const p   = _bc.products.find(x => String(x.id) === pid);
      if (!p) return;
      if (chk.checked) {
        _bc.selected[pid] = { p, qty: 1 };
      } else {
        delete _bc.selected[pid];
      }
      const row  = chk.closest('.bc-product-row');
      const qInp = row?.querySelector('.bc-qty-inp');
      if (row)  row.classList.toggle('bc-selected', chk.checked);
      if (qInp) { qInp.disabled = !chk.checked; if (chk.checked) qInp.focus(); }
      _barcodeUpdateCount();
    });
  });
  list.querySelectorAll('.bc-qty-inp').forEach(inp => {
    inp.addEventListener('change', () => {
      const pid = inp.dataset.pid;
      if (_bc.selected[pid]) _bc.selected[pid].qty = Math.max(1, parseInt(inp.value) || 1);
    });
  });
}

function _barcodeUpdateCount() {
  const n = Object.keys(_bc.selected).length;
  const el = $('#bc-sel-count');
  const btn = $('#bc-print-btn');
  if (el)  el.innerHTML = `<i class="fa fa-tag"></i> ${n} selected`;
  if (btn) btn.disabled = n === 0;
  const allChk = $('#bc-select-all');
  if (allChk) allChk.checked = _bc.products.length > 0 && n === _bc.products.length;
}

function _barcodePrint() {
  const items = [];
  Object.values(_bc.selected).forEach(({ p, qty }) => {
    const code = p.sku || String(p.id);
    for (let i = 0; i < Math.min(qty, 500); i++) {
      items.push({ name: p.name, code, price: p.unit_sell_price ?? '' });
    }
  });
  if (!items.length) return;

  const cols = parseInt($('#bc-cols')?.value) || 3;

  const labelsHtml = items.map(it => `
    <div class="bc-plabel">
      <svg class="bc-psvg" data-code="${it.code.replace(/&/g,'&amp;').replace(/"/g,'&quot;').replace(/</g,'&lt;')}"></svg>
      <div class="bc-pname">${escHtml(it.name).substring(0, 30)}</div>
      <div class="bc-psku">${escHtml(it.code)}</div>
      ${it.price ? `<div class="bc-pprice">${escHtml(String(it.price))}</div>` : ''}
    </div>`).join('');

  const sheet = $('#bc-preview-sheet');
  const modal  = $('#bc-preview-modal');
  if (!sheet || !modal) return;

  sheet.style.gridTemplateColumns = `repeat(${cols}, 1fr)`;
  sheet.innerHTML = labelsHtml;
  modal.style.display = 'flex';

  // Render barcodes using the locally-loaded JsBarcode
  requestAnimationFrame(() => {
    sheet.querySelectorAll('.bc-psvg').forEach(svg => {
      const code = svg.dataset.code;
      if (!code) return;
      try {
        JsBarcode(svg, code, { format: 'CODE128', width: 1.5, height: 48, displayValue: false, margin: 4 });
      } catch (e) {
        const fb = document.createElement('div');
        fb.style.cssText = 'font-size:9px;text-align:center;letter-spacing:2px;font-family:monospace;padding:6px 0';
        fb.textContent = code;
        svg.replaceWith(fb);
      }
    });
  });
}

$('#bc-search')?.addEventListener('input', e => {
  _bc.q = e.target.value.trim();
  clearTimeout(_bc.timer);
  _bc.timer = setTimeout(_barcodeLoad, 300);
});

$('#bc-select-all')?.addEventListener('change', e => {
  if (e.target.checked) {
    _bc.products.forEach(p => {
      if (!_bc.selected[p.id]) _bc.selected[p.id] = { p, qty: 1 };
    });
  } else {
    _bc.selected = {};
  }
  _barcodeRenderList();
});

$('#bc-print-btn')?.addEventListener('click', _barcodePrint);
$('#bc-prev-close')?.addEventListener('click', () => { $('#bc-preview-modal').style.display = 'none'; });
$('#bc-prev-print')?.addEventListener('click', () => window.print());
// ── End Barcode Sheets ────────────────────────────────────────────────────

// ── Return / Refund Modal ─────────────────────────────────────────────────
const _rfnd = {
  q: '', page: 1, lastPage: 1, list: [],
  activeSaleId: null, activeSale: null,
};
let _rfndSearchTimer;

function openRefundModal() {
  $('#refund-modal').style.display = 'flex';
  _rfnd.q = ''; _rfnd.page = 1; _rfnd.activeSaleId = null; _rfnd.activeSale = null;
  $('#rfnd-search').value = '';
  $('#rfnd-reason').value = '';
  _rfndShowDetail(false);
  _rfndLoadSales();
  _rfndLoadAccounts();
  _rfndLoadReasons();
  setTimeout(() => $('#rfnd-search').focus(), 80);
}

function _rfndClose() {
  $('#refund-modal').style.display = 'none';
  _rfnd.activeSaleId = null; _rfnd.activeSale = null;
}

async function _rfndLoadAccounts() {
  const sel = $('#rfnd-account-select');
  if (sel.options.length > 1) return;
  const res = await API.accounts();
  if (res.status !== 200) return;
  const list = res.body?.data || res.body || [];
  sel.innerHTML = list.map(a => `<option value="${a.id}">${a.name}</option>`).join('');
}

async function _rfndLoadReasons() {
  const sel = $('#rfnd-reason');
  if (sel.options.length > 1) return;
  const res = await API.returnReasons();
  if (res.status !== 200) return;
  const list = res.body?.data || [];
  sel.innerHTML = '<option value="">— Select a reason —</option>' +
    list.map(r => `<option value="${r.key}">${r.label}</option>`).join('');
}

async function _rfndLoadSales(replace = true) {
  if (replace) $('#rfnd-sale-list').innerHTML = '<div class="rfnd-placeholder"><i class="fa fa-spinner fa-spin"></i></div>';
  const res = await API.sales(_rfnd.q, 10);
  if (res.status !== 200) { $('#rfnd-sale-list').innerHTML = '<div class="rfnd-placeholder"><i class="fa fa-triangle-exclamation"></i></div>'; return; }
  const items = res.body?.data || res.body || [];
  _rfnd.list = Array.isArray(items) ? items : [];
  _rfndRenderList();
}

function _rfndRenderList() {
  const el = $('#rfnd-sale-list');
  if (!_rfnd.list.length) {
    el.innerHTML = '<div class="rfnd-placeholder" style="font-size:12px;padding:24px 10px">No sales found</div>';
    return;
  }
  const cur = state.currency || '$';
  el.innerHTML = _rfnd.list.map(s => {
    const date = s.sold_at ? s.sold_at.substring(0, 10) : '';
    const num  = s.sale_number || `#${s.id}`;
    const total = parseFloat(s.total || 0).toFixed(2);
    const active = s.id === _rfnd.activeSaleId ? ' active' : '';
    return `<div class="rfnd-sale-item${active}" data-id="${s.id}">
      <div class="rfnd-si-num">${num} <span class="rfnd-si-total">${cur}${total}</span></div>
      <div class="rfnd-si-meta">${date} &bull; ${s.customer_name || 'Walk-in'}</div>
    </div>`;
  }).join('');
  el.querySelectorAll('.rfnd-sale-item').forEach(row => {
    row.addEventListener('click', () => _rfndSelectSale(parseInt(row.dataset.id)));
  });
}

function _rfndShowDetail(show) {
  $('#rfnd-detail-empty').style.display = show ? 'none' : 'flex';
  $('#rfnd-detail-view').style.display  = show ? 'flex' : 'none';
  if (!show) {
    $('#rfnd-items-list').innerHTML = '';
    $('#rfnd-sale-summary').innerHTML = '';
    $('#rfnd-total-display').textContent = '0.00';
    $('#rfnd-alert').style.display = 'none';
    $('#rfnd-select-all').checked = false;
  }
}

async function _rfndSelectSale(id) {
  _rfnd.activeSaleId = id;
  _rfndRenderList();
  _rfndShowDetail(false);
  $('#rfnd-detail-empty').innerHTML = '<i class="fa fa-spinner fa-spin" style="font-size:28px;opacity:.4"></i>';
  $('#rfnd-detail-empty').style.display = 'flex';

  const res = await API.sale(id);
  if (res.status !== 200) { toast('Failed to load sale', 'error'); return; }
  const sale = res.body?.data || res.body;
  _rfnd.activeSale = sale;
  _rfndRenderDetail(sale);
  _rfndShowDetail(true);
}

function _rfndRenderDetail(sale) {
  const cur = state.currency || '$';
  const num  = sale.sale_number || `#${sale.id}`;
  const date = (sale.sold_at || '').substring(0, 10);

  // Summary strip
  $('#rfnd-sale-summary').innerHTML = `
    <div class="rfnd-sum-field"><div class="rfnd-sum-label">Sale</div><div class="rfnd-sum-val">${num}</div></div>
    <div class="rfnd-sum-field"><div class="rfnd-sum-label">Date</div><div class="rfnd-sum-val">${date}</div></div>
    <div class="rfnd-sum-field"><div class="rfnd-sum-label">Total</div><div class="rfnd-sum-val amount">${cur}${parseFloat(sale.total||0).toFixed(2)}</div></div>
    <div class="rfnd-sum-field"><div class="rfnd-sum-label">Customer</div><div class="rfnd-sum-val">${sale.customer_name || 'Walk-in'}</div></div>
    <div class="rfnd-sum-field"><div class="rfnd-sum-label">Payment</div><div class="rfnd-sum-val">${sale.payment_method || '—'}</div></div>
    <div class="rfnd-sum-field"><div class="rfnd-sum-label">Status</div><div class="rfnd-sum-val">${sale.status || '—'}</div></div>
  `;

  // Items list
  const items = sale.items || [];
  const el = $('#rfnd-items-list');
  if (!items.length) { el.innerHTML = '<div style="color:var(--text-muted);font-size:12px;padding:10px 0">No line items found</div>'; return; }

  el.innerHTML = items.map(it => {
    const returnable = (it.quantity || 0) - (it.returned_quantity || 0);
    const exhausted  = returnable <= 0 ? ' exhausted' : '';
    const price      = parseFloat(it.unit_sell_price || 0).toFixed(2);
    return `<div class="rfnd-item-row${exhausted}" data-id="${it.id}" data-max="${returnable}" data-price="${it.unit_sell_price || 0}">
      <input type="checkbox" class="rfnd-item-check" ${returnable <= 0 ? 'disabled' : ''}>
      <div style="flex:1;min-width:0">
        <div class="rfnd-item-name">${it.product_name || 'Item'}</div>
        <div class="rfnd-item-meta">Qty: ${it.quantity}  Returned: ${it.returned_quantity || 0}  Available: ${returnable > 0 ? returnable : 0}</div>
      </div>
      <div class="rfnd-item-qty-wrap">
        <span class="rfnd-item-qty-label">Qty</span>
        <input type="number" class="rfnd-item-qty" value="${returnable > 0 ? 1 : 0}" min="1" max="${returnable}" ${returnable <= 0 ? 'disabled' : ''}>
      </div>
      <div class="rfnd-item-price">${cur}${price}</div>
    </div>`;
  }).join('');

  el.querySelectorAll('.rfnd-item-row').forEach(row => {
    const cb  = row.querySelector('.rfnd-item-check');
    const qty = row.querySelector('.rfnd-item-qty');
    cb.addEventListener('change',  () => { row.classList.toggle('selected', cb.checked); _rfndCalcTotal(); });
    qty.addEventListener('input',  () => { if (cb.checked) _rfndCalcTotal(); });
    qty.addEventListener('change', () => {
      const max = parseInt(row.dataset.max);
      const v   = parseInt(qty.value) || 1;
      qty.value = Math.min(max, Math.max(1, v));
      if (cb.checked) _rfndCalcTotal();
    });
  });

  _rfndCalcTotal();
}

function _rfndCalcTotal() {
  let total = 0;
  $('#rfnd-items-list').querySelectorAll('.rfnd-item-row').forEach(row => {
    const cb  = row.querySelector('.rfnd-item-check');
    const qty = row.querySelector('.rfnd-item-qty');
    if (cb && cb.checked) {
      const price = parseFloat(row.dataset.price || 0);
      const q     = parseInt(qty?.value) || 1;
      total += price * q;
    }
  });
  const cur = state.currency || '$';
  $('#rfnd-total-display').textContent = cur + total.toFixed(2);
  $('#rfnd-submit').disabled = total <= 0;
}

function _rfndGetSelectedItems() {
  const result = [];
  $('#rfnd-items-list').querySelectorAll('.rfnd-item-row').forEach(row => {
    const cb  = row.querySelector('.rfnd-item-check');
    const qty = row.querySelector('.rfnd-item-qty');
    if (cb && cb.checked) {
      result.push({ sale_item_id: parseInt(row.dataset.id), quantity: parseInt(qty?.value) || 1 });
    }
  });
  return result;
}

async function _rfndSubmit() {
  const items = _rfndGetSelectedItems();
  if (!items.length) { _rfndShowAlert('Select at least one item to return.'); return; }

  const method = document.querySelector('input[name="rfnd-method"]:checked')?.value || 'cash';
  const creditAccId = method === 'credit' ? parseInt($('#rfnd-account-select').value) || null : null;
  const reason = $('#rfnd-reason').value.trim();

  const body = { items, refund_method: method, refund_reason: reason || null };
  if (creditAccId) body.credit_account_id = creditAccId;

  $('#rfnd-submit').disabled = true;
  $('#rfnd-submit').innerHTML = '<i class="fa fa-spinner fa-spin"></i> Processing…';

  const res = await API.processReturn(_rfnd.activeSaleId, body);

  $('#rfnd-submit').innerHTML = '<i class="fa fa-rotate-left"></i> Process Return';

  if (res.status === 200 || res.status === 201) {
    toast('Return processed successfully', 'success');
    _rfndClose();
  } else {
    const msg = res.data?.message || res.data?.error || 'Return failed';
    _rfndShowAlert(msg);
    _rfndCalcTotal();
  }
}

function _rfndShowAlert(msg) {
  const el = $('#rfnd-alert');
  el.textContent = msg;
  el.style.display = 'block';
  setTimeout(() => { if (el.textContent === msg) el.style.display = 'none'; }, 4000);
}

// Refund modal event wiring
$('#refund-modal')?.addEventListener('click', e => { if (e.target === e.currentTarget) _rfndClose(); });
$('#rfnd-close')?.addEventListener('click', _rfndClose);
$('#rfnd-submit')?.addEventListener('click', _rfndSubmit);

$('#rfnd-select-all')?.addEventListener('change', e => {
  $('#rfnd-items-list').querySelectorAll('.rfnd-item-row:not(.exhausted) .rfnd-item-check').forEach(cb => {
    cb.checked = e.target.checked;
    cb.closest('.rfnd-item-row').classList.toggle('selected', e.target.checked);
  });
  _rfndCalcTotal();
});

document.querySelectorAll('input[name="rfnd-method"]').forEach(r => {
  r.addEventListener('change', () => {
    const isCred = r.value === 'credit' && r.checked;
    $('#rfnd-account-row').style.display = isCred ? 'block' : 'none';
  });
});

$('#rfnd-search')?.addEventListener('input', e => {
  clearTimeout(_rfndSearchTimer);
  _rfnd.q = e.target.value.trim();
  _rfndSearchTimer = setTimeout(() => _rfndLoadSales(), 350);
});
$('#rfnd-search')?.addEventListener('keydown', e => {
  if (e.key === 'Escape') _rfndClose();
});

// ── Product CRUD ──────────────────────────────────────────────────────────
let _prodActiveId   = null;
let _prodActiveData = null;
const _prod = {
  editingId:      null,
  _optionsLoaded: false,
  _nextTempId:    -1,
  catOptions:     [],
  brandOptions:   [],
  selectedCats:   [],
  selectedBrands: [],
  imageFileId:        null,
  bundleItems:        [],
  _bundleSearchWired: false,
};

// ── SKU generator ─────────────────────────────────────────────────────────
function _prodGenerateSku() {
  const chars = 'ABCDEFGHIJKLMNOPQRSTUVWXYZ0123456789';
  let s = 'PRD-';
  for (let i = 0; i < 8; i++) s += chars[Math.floor(Math.random() * chars.length)];
  $('#prod-f-sku').value = s;
}

// ── Image picker ──────────────────────────────────────────────────────────
const _imgPicker = { _pendingFilePath: null, _folderId: null };

function _imgPickerOpen() {
  $('#img-picker-modal').style.display = 'flex';
  _imgPickerTabSwitch('upload');
}
function _imgPickerClose() {
  $('#img-picker-modal').style.display = 'none';
  _imgPicker._pendingFilePath = null;
  $('#img-upload-preview').style.display = 'none';
  $('#img-upload-drop').style.display = 'block';
}
function _imgPickerTabSwitch(tab) {
  $$('.img-tab-btn').forEach(b => b.classList.toggle('active', b.dataset.tab === tab));
  $('#img-tab-upload').style.display = tab === 'upload' ? 'block' : 'none';
  $('#img-tab-fm').style.display     = tab === 'fm'     ? 'block' : 'none';
  if (tab === 'fm') _imgPickerLoadFm(null);
}
async function _imgPickerBrowseFile() {
  const result = await window.electronAPI.showOpenDialog({
    title: 'Select Image',
    filters: [{ name: 'Images', extensions: ['jpg','jpeg','png','gif','webp'] }],
    properties: ['openFile'],
  });
  if (result.canceled || !result.filePaths.length) return;
  const fp = result.filePaths[0];
  _imgPicker._pendingFilePath = fp;
  const name = fp.split(/[\\/]/).pop();
  $('#img-upload-preview-name').textContent = name;
  // Use file:// URL to preview locally
  $('#img-upload-preview-img').src = 'file://' + fp;
  $('#img-upload-preview').style.display = 'block';
  $('#img-upload-drop').style.display = 'none';
}
async function _imgPickerUploadAndUse() {
  if (!_imgPicker._pendingFilePath) return;
  const btn = $('#img-upload-confirm');
  btn.disabled = true;
  btn.innerHTML = '<i class="fa fa-spinner fa-spin"></i> Uploading…';
  const res = await window.electronAPI.apiUpload('/v1/pos/online/file-manager/upload', _imgPicker._pendingFilePath);
  btn.disabled = false;
  btn.innerHTML = '<i class="fa fa-upload"></i> Upload &amp; Use';
  if (res.status === 201 && res.body?.data?.length) {
    const file = res.body.data[0];
    _prodSetImage(file.id, file.url);
    _imgPickerClose();
  } else {
    const msg = res.body?.message || 'Upload failed';
    toast(msg, 'error');
  }
}
async function _imgPickerLoadFm(folderId) {
  _imgPicker._folderId = folderId;
  $('#img-fm-grid').innerHTML = '<div class="img-fm-loading"><i class="fa fa-spinner fa-spin"></i></div>';
  const res = await API.fileManagerBrowse(folderId, true);
  if (res.status !== 200) { $('#img-fm-grid').innerHTML = '<div class="img-fm-loading">Failed to load</div>'; return; }
  const { folders, files, breadcrumbs } = res.body;
  // Breadcrumb
  const bcParts = [{ id: null, name: 'Root' }, ...(breadcrumbs || [])];
  $('#img-fm-breadcrumb').innerHTML = bcParts.map((b, i) =>
    i < bcParts.length - 1
      ? `<button class="img-fm-bc-btn" data-folder="${b.id ?? ''}">${escHtml(b.name)}</button><i class="fa fa-chevron-right" style="font-size:9px"></i>`
      : `<span>${escHtml(b.name)}</span>`
  ).join('');
  $('#img-fm-breadcrumb').querySelectorAll('.img-fm-bc-btn').forEach(btn => {
    btn.addEventListener('click', () => _imgPickerLoadFm(btn.dataset.folder ? parseInt(btn.dataset.folder) : null));
  });
  // Grid
  const folderHtml = (folders || []).map(f =>
    `<div class="img-fm-folder" data-folder-id="${f.id}"><i class="fa fa-folder" style="font-size:24px;color:#f39c12"></i><span class="img-fm-file-name">${escHtml(f.name)}</span></div>`
  ).join('');
  const fileHtml = (files || []).map(f =>
    `<div class="img-fm-file" data-file-id="${f.id}" data-url="${escHtml(f.url)}">
      <img src="${escHtml(f.url)}" alt="" loading="lazy">
      <span class="img-fm-file-name">${escHtml(f.name)}</span>
    </div>`
  ).join('');
  $('#img-fm-grid').innerHTML = folderHtml + fileHtml ||
    '<div class="img-fm-loading" style="color:var(--text-muted)">No images found</div>';
  $('#img-fm-grid').querySelectorAll('.img-fm-folder').forEach(el => {
    el.addEventListener('click', () => _imgPickerLoadFm(parseInt(el.dataset.folderId)));
  });
  $('#img-fm-grid').querySelectorAll('.img-fm-file').forEach(el => {
    el.addEventListener('click', () => {
      _prodSetImage(parseInt(el.dataset.fileId), el.dataset.url);
      _imgPickerClose();
    });
  });
}
function _prodSetImage(fileId, url) {
  _prod.imageFileId = fileId;
  const thumb = $('#prod-img-thumb');
  thumb.innerHTML = url ? `<img src="${escHtml(url)}" alt="">` : '<i class="fa fa-image" style="font-size:22px;color:var(--text-muted)"></i>';
  $('#prod-img-remove').style.display = url ? 'inline-flex' : 'none';
}

// ── Bundle items ──────────────────────────────────────────────────────────
function _prodBundleRender() {
  const list  = $('#prod-bundle-items');
  const count = _prod.bundleItems.length;
  const countEl = $('#prod-bundle-count');
  if (countEl) countEl.textContent = count === 1 ? '1 item in bundle' : `${count} items in bundle`;

  if (!count) {
    list.innerHTML = '<div class="prod-bundle-empty"><i class="fa fa-box-open"></i> No items yet — search above to add products</div>';
    return;
  }

  list.innerHTML = _prod.bundleItems.map((item, i) => `
    <div class="prod-bundle-item" data-idx="${i}">
      <span class="prod-bundle-item-idx">${i + 1}</span>
      <span class="prod-bundle-item-name" title="${escHtml(item.name)}">${escHtml(item.name)}</span>
      <div class="prod-bundle-qty-ctrl">
        <button class="prod-bundle-qty-btn" data-idx="${i}" data-action="dec" type="button">−</button>
        <input type="number" class="prod-bundle-item-qty" min="0.001" step="1" value="${item.quantity}" data-idx="${i}">
        <button class="prod-bundle-qty-btn" data-idx="${i}" data-action="inc" type="button">+</button>
      </div>
      <button class="prod-bundle-item-rm" data-idx="${i}" type="button" title="Remove"><i class="fa fa-xmark"></i></button>
    </div>`).join('');

  list.querySelectorAll('.prod-bundle-item-qty').forEach(inp => {
    inp.addEventListener('change', () => {
      const idx = parseInt(inp.dataset.idx);
      if (_prod.bundleItems[idx]) {
        const v = parseFloat(inp.value);
        _prod.bundleItems[idx].quantity = v > 0 ? v : 1;
        inp.value = _prod.bundleItems[idx].quantity;
      }
    });
  });

  list.querySelectorAll('.prod-bundle-qty-btn').forEach(btn => {
    btn.addEventListener('click', () => {
      const idx  = parseInt(btn.dataset.idx);
      const item = _prod.bundleItems[idx];
      if (!item) return;
      const step = btn.dataset.action === 'inc' ? 1 : -1;
      item.quantity = Math.max(1, Math.round((item.quantity + step) * 1000) / 1000);
      const qtyInp = list.querySelector(`.prod-bundle-item-qty[data-idx="${idx}"]`);
      if (qtyInp) qtyInp.value = item.quantity;
    });
  });

  list.querySelectorAll('.prod-bundle-item-rm').forEach(btn => {
    btn.addEventListener('click', () => {
      _prod.bundleItems.splice(parseInt(btn.dataset.idx), 1);
      _prodBundleRender();
      // Re-trigger search so removed product reappears in dropdown
      const inp = $('#prod-bundle-search');
      if (inp && inp.value.trim()) inp.dispatchEvent(new Event('input'));
    });
  });
}

function _prodBundleAddItem(id, name) {
  const existing = _prod.bundleItems.find(b => b.product_id === id);
  if (existing) {
    // Product already in list — just increment qty
    existing.quantity += 1;
    _prodBundleRender();
  } else {
    _prod.bundleItems.push({ product_id: id, name, quantity: 1 });
    _prodBundleRender();
  }
}

function _prodBundleWireSearch() {
  const inp = $('#prod-bundle-search');
  const dd  = $('#prod-bundle-dd');
  if (!inp || !dd) return;

  const hide = () => { dd.style.display = 'none'; };
  let _timer = null;

  const refresh = async () => {
    const q = inp.value.trim();
    if (!q) { hide(); return; }

    const res = await API.productSearch(q, 20);
    if (res.status !== 200) { hide(); return; }
    // Bail if the query changed while we were waiting
    if (inp.value.trim() !== q) return;

    const products = res.body?.data || [];
    if (!products.length) {
      _tagPositionDd(inp, dd);
      dd.innerHTML = '<div class="tag-dd-empty">No products found</div>';
      dd.style.display = 'block';
      return;
    }

    const addedIds = new Set(_prod.bundleItems.map(b => b.product_id));
    const rows = products.map((p, i) => {
      const added = addedIds.has(p.id);
      return `<div class="bundle-dd-item${i === 0 && !added ? ' focused' : ''}" data-id="${p.id}" data-name="${escHtml(p.name)}">
        <span class="bundle-dd-item-name">${escHtml(p.name)}</span>
        ${added
          ? `<span class="bundle-dd-item-added"><i class="fa fa-check"></i> Added</span>`
          : `<button class="bundle-add-btn" type="button"><i class="fa fa-plus"></i> Add</button>`}
      </div>`;
    }).join('');

    _tagPositionDd(inp, dd);
    dd.innerHTML = rows;

    dd.querySelectorAll('.bundle-dd-item').forEach(item => {
      const addBtn = item.querySelector('.bundle-add-btn');
      if (addBtn) {
        addBtn.addEventListener('mousedown', (e) => {
          e.preventDefault();
          _prodBundleAddItem(parseInt(item.dataset.id), item.dataset.name);
          inp.dispatchEvent(new Event('input'));
          inp.focus();
        });
      }
    });

    dd.style.display = 'block';
  };

  inp.addEventListener('input', () => { clearTimeout(_timer); _timer = setTimeout(refresh, 250); });
  inp.addEventListener('focus', refresh);
  inp.addEventListener('blur', () => setTimeout(hide, 200));
  inp.addEventListener('keydown', e => {
    if (e.key === 'Escape') { hide(); inp.blur(); return; }
    if (e.key === 'Enter') {
      e.preventDefault();
      const first = dd.querySelector('.bundle-add-btn');
      if (first) first.dispatchEvent(new MouseEvent('mousedown', { bubbles: true }));
    }
  });

  const scrollParent = inp.closest('.prod-modal-body');
  if (scrollParent) scrollParent.addEventListener('scroll', hide, { passive: true });
}

// ── Tag-input helpers ─────────────────────────────────────────────────────
function _tagRender(tagsId, selected, onRemove) {
  const el = $(`#${tagsId}`);
  el.innerHTML = selected.map(item => {
    const display = item.label || item.name;
    return `<span class="tag-chip" title="${escHtml(display)}">
      <span style="overflow:hidden;text-overflow:ellipsis;max-width:160px">${escHtml(display)}</span>
      <button class="tag-chip-x" data-id="${item.id}" type="button"><i class="fa fa-xmark"></i></button>
    </span>`;
  }).join('');
  el.querySelectorAll('.tag-chip-x').forEach(btn => {
    btn.addEventListener('click', e => { e.stopPropagation(); onRemove(parseInt(btn.dataset.id)); });
  });
}

function _tagPositionDd(inputEl, ddEl) {
  const wrap = inputEl.closest('.tag-input-wrap, .prod-bundle-search-wrap') || inputEl;
  const r    = wrap.getBoundingClientRect();
  ddEl.style.top   = `${r.bottom + 3}px`;
  ddEl.style.left  = `${r.left}px`;
  ddEl.style.width = `${r.width}px`;
}

function _tagShowDd(inputEl, ddEl, options, selectedIds, onSelect, onCreate) {
  const q      = inputEl.value.trim();
  const qLower = q.toLowerCase();

  const filtered = options.filter(o =>
    !selectedIds.includes(o.id) &&
    ((o.label || o.name).toLowerCase().includes(qLower) || o.name.toLowerCase().includes(qLower))
  );

  const rows = filtered.slice(0, 30).map((o, i) => {
    const display = o.label || o.name;
    return `<div class="tag-dd-item${i === 0 ? ' focused' : ''}"
      data-id="${o.id}" data-name="${escHtml(o.name)}" data-label="${escHtml(display)}">
      ${escHtml(display)}
    </div>`;
  });

  // "Create X" option when typed query has no exact name match
  const hasExact = options.some(o => o.name.toLowerCase() === qLower);
  if (q && !hasExact && onCreate) {
    rows.push(`<div class="tag-dd-item tag-dd-create" data-create="${escHtml(q)}">
      <i class="fa fa-plus"></i> Create "${escHtml(q)}"
    </div>`);
  }

  _tagPositionDd(inputEl, ddEl);

  if (!rows.length) {
    ddEl.innerHTML = `<div class="tag-dd-empty">${q ? 'No matches' : 'Type to search…'}</div>`;
    ddEl.style.display = 'block';
    return;
  }

  ddEl.innerHTML = rows.join('');

  ddEl.querySelectorAll('.tag-dd-item:not(.tag-dd-create)').forEach(item => {
    item.addEventListener('mousedown', e => {
      e.preventDefault();
      onSelect({ id: parseInt(item.dataset.id), name: item.dataset.name, label: item.dataset.label });
      inputEl.value = '';
      ddEl.style.display = 'none';
    });
  });

  if (onCreate) {
    ddEl.querySelector('.tag-dd-create')?.addEventListener('mousedown', e => {
      e.preventDefault();
      onCreate(q);
      inputEl.value = '';
      ddEl.style.display = 'none';
    });
  }

  ddEl.style.display = 'block';
}

function _tagWireInput(inputId, ddId, getOptions, getSelected, onSelect, onCreate) {
  const inputEl = $(`#${inputId}`);
  const ddEl    = $(`#${ddId}`);
  if (!inputEl || !ddEl) return;

  const hide    = () => { ddEl.style.display = 'none'; };
  const refresh = () => _tagShowDd(inputEl, ddEl, getOptions(), getSelected().map(x => x.id), onSelect, onCreate);

  inputEl.addEventListener('focus', refresh);
  inputEl.addEventListener('input', refresh);
  inputEl.addEventListener('blur',  () => setTimeout(hide, 200));
  inputEl.addEventListener('keydown', e => {
    if (e.key === 'Escape') { hide(); inputEl.blur(); return; }
    if (e.key === 'Enter') {
      e.preventDefault();
      const first = ddEl.querySelector('.tag-dd-item');
      if (first) first.dispatchEvent(new MouseEvent('mousedown', { bubbles: true }));
    }
  });

  // Hide if the scrollable modal body scrolls (dropdown is position:fixed so it won't follow)
  const scrollParent = inputEl.closest('.prod-modal-body, .po-aim-body, .psm-content');
  if (scrollParent) scrollParent.addEventListener('scroll', hide, { passive: true });
  window.addEventListener('resize', hide, { passive: true });
}

// ── Product CRUD ──────────────────────────────────────────────────────────
async function _prodOpenModal(editId) {
  _prod.editingId = editId || null;
  const isEdit = !!editId;
  $('#prod-modal-title').textContent  = isEdit ? 'Edit Product' : 'New Product';
  $('#prod-stock-label').textContent   = isEdit ? 'Stock Quantity' : 'Opening Stock';

  // Reset fields
  $('#prod-f-name').value        = '';
  $('#prod-f-sku').value         = '';
  $('#prod-f-price').value       = '';
  $('#prod-f-stock').value       = '';
  $('#prod-f-description').value = '';
  $('#prod-f-active').checked    = true;
  $('#prod-f-unit').value        = '';
  $('#prod-f-bundle').checked    = false;
  $('#prod-bundle-section').style.display = 'none';

  // Reset image
  _prod.imageFileId = null;
  _prodSetImage(null, null);

  // Reset bundle
  _prod.bundleItems = [];
  _prodBundleRender();

  // Reset tag selections
  _prod._nextTempId    = -1;
  _prod.selectedCats   = [];
  _prod.selectedBrands = [];
  _tagRender('prod-cat-tags',   [], _prodRemoveCat);
  _tagRender('prod-brand-tags', [], _prodRemoveBrand);
  $('#prod-cat-input').value   = '';
  $('#prod-brand-input').value = '';

  // Load options once
  await _prodLoadFormOptions();

  if (isEdit && _prodActiveData) {
    const p = _prodActiveData;
    $('#prod-f-name').value        = p.name        || '';
    $('#prod-f-sku').value         = p.sku         || '';
    $('#prod-f-price').value       = p.unit_price  ?? p.price ?? '';
    $('#prod-f-stock').value       = p.stock_quantity ?? p.total_stock ?? '';
    $('#prod-f-description').value = p.description || '';
    $('#prod-f-active').checked    = p.is_active !== false;
    if (p.product_unit_id) $('#prod-f-unit').value = p.product_unit_id;

    // Image
    if (p.file_manager_file_id) {
      const imgUrl = p.image_url || p.images?.[0]?.url || null;
      _prodSetImage(p.file_manager_file_id, imgUrl);
    }

    // Bundle
    if (p.is_bundle && p.bundle_items?.length) {
      $('#prod-f-bundle').checked = true;
      $('#prod-bundle-section').style.display = 'flex';
      _prod.bundleItems = p.bundle_items.map(bi => ({ product_id: bi.product_id, name: bi.name, quantity: bi.quantity }));
      _prodBundleRender();
      if (!_prod._bundleSearchWired) { _prodBundleWireSearch(); _prod._bundleSearchWired = true; }
    }

    // Tags
    const catIds   = p.category_ids || [];
    const brandIds = p.brand_ids    || [];
    _prod.selectedCats   = _prod.catOptions.filter(o => catIds.includes(o.id));
    _prod.selectedBrands = _prod.brandOptions.filter(o => brandIds.includes(o.id));
    _tagRender('prod-cat-tags',   _prod.selectedCats,   _prodRemoveCat);
    _tagRender('prod-brand-tags', _prod.selectedBrands, _prodRemoveBrand);
  }

  $('#product-modal').style.display = 'flex';
  setTimeout(() => $('#prod-f-name').focus(), 80);
}

function _prodRemoveCat(id) {
  _prod.selectedCats = _prod.selectedCats.filter(x => x.id !== id);
  _tagRender('prod-cat-tags', _prod.selectedCats, _prodRemoveCat);
}
function _prodRemoveBrand(id) {
  _prod.selectedBrands = _prod.selectedBrands.filter(x => x.id !== id);
  _tagRender('prod-brand-tags', _prod.selectedBrands, _prodRemoveBrand);
}

async function _prodLoadFormOptions() {
  if (_prod._optionsLoaded) return;

  const [unitRes, catRes, brandRes] = await Promise.all([
    API.units(),
    API.categoryParentOpts(),
    API.brands('', ''),
  ]);

  // Units select
  if (unitRes.status === 200) {
    const units = unitRes.body?.data || [];
    $('#prod-f-unit').innerHTML = '<option value="">— No unit —</option>' +
      units.map(u => `<option value="${u.id}">${escHtml(u.name)}${u.abbreviation ? ` (${escHtml(u.abbreviation)})` : ''}</option>`).join('');
  }

  // Category options store
  if (catRes.status === 200) {
    _prod.catOptions = (catRes.body?.data || []).map(c => ({
      id: c.id, name: c.name || c.label.trim(), label: c.label,
    }));
  }

  // Brand options store
  if (brandRes.status === 200) {
    _prod.brandOptions = (brandRes.body?.data || []).map(b => ({ id: b.id, name: b.name }));
  }

  // Wire tag inputs
  _tagWireInput(
    'prod-cat-input', 'prod-cat-dd',
    () => _prod.catOptions,
    () => _prod.selectedCats,
    item => { if (!_prod.selectedCats.find(x => x.id === item.id)) { _prod.selectedCats.push(item); _tagRender('prod-cat-tags', _prod.selectedCats, _prodRemoveCat); } },
    (name) => {
      if (_prod.selectedCats.find(x => x.name.toLowerCase() === name.toLowerCase())) return;
      const opt = { id: _prod._nextTempId--, name, label: name, _new: true };
      _prod.selectedCats.push(opt);
      _tagRender('prod-cat-tags', _prod.selectedCats, _prodRemoveCat);
    },
  );
  _tagWireInput(
    'prod-brand-input', 'prod-brand-dd',
    () => _prod.brandOptions,
    () => _prod.selectedBrands,
    item => { if (!_prod.selectedBrands.find(x => x.id === item.id)) { _prod.selectedBrands.push(item); _tagRender('prod-brand-tags', _prod.selectedBrands, _prodRemoveBrand); } },
    (name) => {
      if (_prod.selectedBrands.find(x => x.name.toLowerCase() === name.toLowerCase())) return;
      const opt = { id: _prod._nextTempId--, name, _new: true };
      _prod.selectedBrands.push(opt);
      _tagRender('prod-brand-tags', _prod.selectedBrands, _prodRemoveBrand);
    },
  );

  _prod._optionsLoaded = true;
}

async function _prodSave() {
  const name  = $('#prod-f-name').value.trim();
  const price = parseFloat($('#prod-f-price').value);
  if (!name)                    { toast('Product name is required', 'error'); return; }
  if (isNaN(price) || price < 0) { toast('Selling price must be 0 or more', 'error'); return; }

  const btn = $('#prod-modal-save');
  btn.disabled = true;

  // Create any pending new categories first
  const catIds = [];
  for (const cat of _prod.selectedCats) {
    if (cat._new) {
      const res = await API.createCategory({ name: cat.name, is_active: true });
      if (res.status !== 201) {
        const msg = res.body?.errors ? Object.values(res.body.errors)[0]?.[0] : null;
        toast(msg || `Failed to create category "${cat.name}"`, 'error');
        btn.disabled = false;
        return;
      }
      catIds.push(res.body.data.id);
    } else {
      catIds.push(cat.id);
    }
  }

  // Create any pending new brands first
  const brandIds = [];
  for (const brand of _prod.selectedBrands) {
    if (brand._new) {
      const res = await API.createBrand({ name: brand.name, is_active: true });
      if (res.status !== 201) {
        const msg = res.body?.errors ? Object.values(res.body.errors)[0]?.[0] : null;
        toast(msg || `Failed to create brand "${brand.name}"`, 'error');
        btn.disabled = false;
        return;
      }
      brandIds.push(res.body.data.id);
    } else {
      brandIds.push(brand.id);
    }
  }

  const isBundle   = $('#prod-f-bundle').checked;
  const bundleItems = _prod.bundleItems.map(b => ({ product_id: b.product_id, quantity: b.quantity }));
  if (isBundle && !bundleItems.length) {
    toast('Add at least one product to the bundle', 'error');
    btn.disabled = false;
    return;
  }

  const body = {
    name,
    sku:                   $('#prod-f-sku').value.trim()         || null,
    unit_price:            price,
    stock_quantity:        parseFloat($('#prod-f-stock').value)  || 0,
    description:           $('#prod-f-description').value.trim() || null,
    product_unit_id:       parseInt($('#prod-f-unit').value)     || null,
    product_category_ids:  catIds,
    product_brand_ids:     brandIds,
    is_active:             $('#prod-f-active').checked,
    is_bundle:             isBundle,
    bundle_items:          bundleItems,
  };
  if (_prod.imageFileId) body.file_manager_file_ids = [_prod.imageFileId];

  const res = _prod.editingId
    ? await API.updateProduct(_prod.editingId, body)
    : await API.createProduct(body);

  btn.disabled = false;

  if (res.status === 200 || res.status === 201) {
    toast(_prod.editingId ? 'Product updated' : 'Product created', 'success');
    $('#product-modal').style.display = 'none';
    if (_prod.editingId) {
      openProductDetail(_prod.editingId, body.name);
    } else {
      invState.loaded = false;
      loadInventory('', 0, 1);
    }
  } else {
    const errors = res.body?.errors;
    const first  = errors ? Object.values(errors)[0]?.[0] : null;
    toast(first || res.body?.message || 'Failed to save', 'error');
  }
}

async function _prodDelete() {
  if (!_prodActiveId) return;
  const name = _prodActiveData?.name || 'this product';
  if (!confirm(`Delete "${name}"? This cannot be undone.`)) return;

  const res = await API.deleteProduct(_prodActiveId);
  if (res.status === 200) {
    toast('Product deleted', 'success');
    _prodActiveId   = null;
    _prodActiveData = null;
    $('#inv-detail-view').style.display = 'none';
    $('#inv-list-view').style.display   = 'flex';
    invState.loaded = false;
    loadInventory('', 0, 1);
  } else {
    toast(res.body?.message || 'Failed to delete product', 'error');
  }
}

$('#inv-new-product-btn')?.addEventListener('click', () => _prodOpenModal(null));
$('#prod-edit-btn')?.addEventListener('click',        () => _prodOpenModal(_prodActiveId));
$('#prod-delete-btn')?.addEventListener('click',      _prodDelete);
$('#prod-modal-close')?.addEventListener('click',   () => { $('#product-modal').style.display = 'none'; });
$('#prod-modal-cancel')?.addEventListener('click',  () => { $('#product-modal').style.display = 'none'; });
$('#prod-modal-save')?.addEventListener('click',    _prodSave);

// SKU generate
$('#prod-sku-gen')?.addEventListener('click', _prodGenerateSku);

// Image
$('#prod-img-choose')?.addEventListener('click', _imgPickerOpen);
$('#prod-img-remove')?.addEventListener('click', () => _prodSetImage(null, null));
$('#img-picker-close')?.addEventListener('click', _imgPickerClose);
$('#img-upload-btn')?.addEventListener('click',    _imgPickerBrowseFile);
$('#img-upload-change')?.addEventListener('click', _imgPickerBrowseFile);
$('#img-upload-confirm')?.addEventListener('click', _imgPickerUploadAndUse);
$$('.img-tab-btn').forEach(btn => btn.addEventListener('click', () => _imgPickerTabSwitch(btn.dataset.tab)));

// Bundle
$('#prod-f-bundle')?.addEventListener('change', function () {
  const on  = this.checked;
  const sec = $('#prod-bundle-section');
  sec.style.display = on ? 'flex' : 'none';
  if (on) {
    if (!_prod._bundleSearchWired) { _prodBundleWireSearch(); _prod._bundleSearchWired = true; }
    setTimeout(() => {
      sec.scrollIntoView({ behavior: 'smooth', block: 'nearest' });
      $('#prod-bundle-search')?.focus();
    }, 50);
  }
});

// Click inside tag-input wrap focuses the input
$('#prod-cat-wrap')?.addEventListener('click',   () => $('#prod-cat-input').focus());
$('#prod-brand-wrap')?.addEventListener('click', () => $('#prod-brand-input').focus());
// ── End Product CRUD ──────────────────────────────────────────────────────

// ── Product detail ────────────────────────────────────────────────────────
async function openProductDetail(productId, productName) {
  _prodActiveId   = productId;
  _prodActiveData = null;
  $('#inv-list-view').style.display   = 'none';
  $('#inv-detail-view').style.display = 'flex';
  $('#inv-detail-breadcrumb').textContent = productName || 'Product Detail';

  // Reset to Overview tab
  $$('#inv-tabs .inv-tab').forEach(t => t.classList.remove('active'));
  $('#inv-tabs .inv-tab[data-tab="overview"]').classList.add('active');
  $$('.inv-tab-pane').forEach(p => p.classList.remove('active'));
  $('#inv-pane-overview').classList.add('active');

  // Loading state in hero
  $('#inv-hero-name').textContent = productName || '…';
  $('#inv-hero-meta').innerHTML   = '<i class="fa fa-spinner fa-spin"></i>';
  $('#inv-hero-badges').innerHTML = '';
  $('#inv-hero-img').innerHTML    = '<div class="inv-thumb-ph inv-thumb-lg"><i class="fa fa-box"></i></div>';
  ['overview','pricing','stock','images','variants'].forEach(t => {
    $(`#inv-pane-${t}`).innerHTML = `<div class="inv-pane-loading"><i class="fa fa-spinner fa-spin"></i> Loading…</div>`;
  });

  // Fetch product + initial chart in parallel
  const [res] = await Promise.all([
    API.product(productId),
  ]);
  const p = res.body?.data || res.body || null;
  if (!p || res.status !== 200) {
    $('#inv-hero-meta').innerHTML = '<span style="color:#e74c3c">Failed to load product</span>';
    return;
  }

  renderProductDetail(p);
}

function renderProductDetail(p) {
  _prodActiveData = p;
  // ── Hero ──
  const images = p.images || p.product_images || (p.image ? [{ url: p.image }] : []);
  const firstImg = images[0]?.url || images[0]?.image_url || p.image || null;
  $('#inv-hero-img').innerHTML = firstImg
    ? `<img src="${firstImg}" class="inv-hero-img-el" alt="${escHtml(p.name)}">`
    : `<div class="inv-thumb-ph inv-thumb-lg"><i class="fa fa-box"></i></div>`;

  $('#inv-hero-name').textContent = p.name || '—';

  const metaParts = [];
  if (p.sku)       metaParts.push(`<span><i class="fa fa-barcode"></i> ${escHtml(p.sku)}</span>`);
  if (p.barcode)   metaParts.push(`<span><i class="fa fa-qrcode"></i> ${escHtml(p.barcode)}</span>`);
  if (p.category?.name) metaParts.push(`<span><i class="fa fa-tag"></i> ${escHtml(p.category.name)}</span>`);
  $('#inv-hero-meta').innerHTML = metaParts.join('<span class="inv-sep">·</span>');

  const stock = p.stock_quantity;
  const badges = [];
  if (p.is_active === false || p.status === 'inactive') badges.push(`<span class="inv-badge inv-badge-gray">Inactive</span>`);
  else badges.push(`<span class="inv-badge inv-badge-green">Active</span>`);
  if (stock != null) {
    if (stock <= 0) badges.push(`<span class="inv-badge inv-badge-red">Out of stock</span>`);
    else if (stock <= 5) badges.push(`<span class="inv-badge inv-badge-amber">Low stock</span>`);
    else badges.push(`<span class="inv-badge inv-badge-blue">In stock</span>`);
  }
  if (p.has_variants || p.variants?.length) badges.push(`<span class="inv-badge inv-badge-purple">Has variants</span>`);
  $('#inv-hero-badges').innerHTML = badges.join('');

  // ── Overview tab ──
  const statusLabel = (p.is_active === false) ? 'Inactive' : 'Active';
  const statusClass = (p.is_active === false) ? 'inv-badge-gray' : 'inv-badge-green';
  const typeLabel   = p.is_bundle ? 'Bundle' : 'Single';

  const categories = p.category
    ? [p.category]
    : (Array.isArray(p.category_ids) && p.category_ids.length ? p.category_ids.map(id => ({ id, name: String(id) })) : []);
  const catChips = categories.length
    ? categories.map(c => `<span class="inv-detail-chip">${escHtml(c.name || String(c))}</span>`).join('')
    : '<span class="inv-detail-none">—</span>';

  const brandName = p.brand?.name || p.brand_name || p.brand || null;
  const brandChip = brandName
    ? `<span class="inv-detail-chip">${escHtml(brandName)}</span>`
    : '<span class="inv-detail-none">—</span>';

  const unitName = p.unit_name || p.unit?.name || p.unit || null;

  const detailCells = [
    { label: 'SKU',        value: p.sku        ? escHtml(p.sku)      : '<span class="inv-detail-none">—</span>' },
    { label: 'STATUS',     value: `<span class="inv-badge ${statusClass}">${statusLabel}</span>` },
    { label: 'TYPE',       value: `<strong>${typeLabel}</strong>` },
    { label: 'CATEGORIES', value: catChips },
    { label: 'BRANDS',     value: brandChip },
    { label: 'UNIT',       value: unitName ? escHtml(unitName) : '<span class="inv-detail-none">—</span>' },
  ];

  const descHtml = (p.description || p.short_description)
    ? `<div class="inv-section" style="margin-top:14px">
        <div class="inv-section-title"><i class="fa fa-align-left"></i> Description</div>
        <div class="inv-desc-body">${escHtml(String(p.description || p.short_description))}</div>
       </div>`
    : '';

  const metaHtml = (p.created_at || p.updated_at)
    ? `<div class="inv-detail-meta-row">
        ${p.created_at ? `<span><i class="fa fa-calendar-plus"></i> Created ${new Date(p.created_at).toLocaleDateString()}</span>` : ''}
        ${p.updated_at ? `<span><i class="fa fa-calendar-check"></i> Updated ${new Date(p.updated_at).toLocaleDateString()}</span>` : ''}
       </div>`
    : '';

  $('#inv-pane-overview').innerHTML = `
    <div class="inv-chart-block">
      <div class="inv-chart-header">
        <div class="inv-chart-title"><i class="fa fa-chart-bar"></i> Units sold</div>
        <div class="inv-chart-periods">
          <button class="inv-chart-period" data-period="daily">Daily</button>
          <button class="inv-chart-period active" data-period="weekly">Weekly</button>
          <button class="inv-chart-period" data-period="monthly">Monthly</button>
        </div>
      </div>
      <div id="inv-chart-container" class="inv-chart-container"></div>
    </div>
    <div class="inv-section" style="margin-top:14px">
      <div class="inv-section-title"><i class="fa fa-circle-info"></i> Details</div>
      <div class="inv-detail-grid">
        ${detailCells.map(c => `
          <div class="inv-detail-cell">
            <div class="inv-detail-cell-label">${c.label}</div>
            <div class="inv-detail-cell-value">${c.value}</div>
          </div>`).join('')}
      </div>
    </div>
    ${descHtml}
    ${metaHtml}`;

  // Load chart & wire period buttons
  loadAndRenderChart(p.id, 'weekly');
  $('#inv-pane-overview').addEventListener('click', e => {
    const btn = e.target.closest('.inv-chart-period');
    if (btn) loadAndRenderChart(p.id, btn.dataset.period);
  });

  // ── Pricing tab ──
  const price     = parseFloat(p.price || 0).toFixed(2);
  const costPrice = p.cost_price != null ? parseFloat(p.cost_price).toFixed(2) : null;
  const compareAt  = p.compare_at_price != null ? parseFloat(p.compare_at_price).toFixed(2) : null;
  const taxRate   = p.tax_rate ?? p.tax?.rate;
  const discount  = p.discount || p.discount_value;
  const margin    = costPrice ? (((p.price - p.cost_price) / p.price) * 100).toFixed(1) + '%' : null;

  const priceRows = [
    ['Selling Price',   `<strong style="font-size:18px;color:var(--accent)">${price}</strong>`],
    ['Cost Price',      costPrice  ? costPrice  : null],
    ['Compare-at Price',compareAt  ? `<s style="color:var(--text-muted)">${compareAt}</s>` : null],
    ['Tax Rate',        taxRate    != null ? `${taxRate}%` : null],
    ['Discount',        discount   ? String(discount) : null],
    ['Gross Margin',    margin],
  ].filter(([, v]) => v != null);

  $('#inv-pane-pricing').innerHTML = `
    <div class="inv-section">
      <div class="inv-section-title"><i class="fa fa-dollar-sign"></i> Pricing</div>
      <table class="inv-detail-table">
        ${priceRows.map(([label, val]) => `
          <tr><td class="inv-dt-label">${label}</td><td class="inv-dt-val">${val}</td></tr>`).join('')}
      </table>
    </div>`;

  // ── Stock tab ──
  const locations = p.stock_locations || p.warehouses || [];
  const stockRows = [
    ['Current Stock', stock != null ? stock : '—'],
    ['Low Stock Alert', p.low_stock_threshold ?? p.alert_quantity ?? '—'],
    ['Manage Stock',  p.manage_stock != null ? (p.manage_stock ? 'Yes' : 'No') : '—'],
    ['Allow Backorder', p.allow_backorder != null ? (p.allow_backorder ? 'Yes' : 'No') : '—'],
    ['Weight',        p.weight ? `${p.weight} ${p.weight_unit || ''}`.trim() : null],
    ['Dimensions',    (p.length || p.width || p.height) ? `${p.length||0} × ${p.width||0} × ${p.height||0}` : null],
  ].filter(([, v]) => v != null);

  const locHtml = locations.length ? `
    <div class="inv-section" style="margin-top:16px">
      <div class="inv-section-title"><i class="fa fa-warehouse"></i> Stock Locations</div>
      <table class="inv-detail-table">
        <thead><tr><th class="inv-dt-label">Location</th><th class="inv-dt-val">Qty</th></tr></thead>
        <tbody>${locations.map(l => `<tr><td class="inv-dt-label">${escHtml(l.name||l.warehouse_name||'—')}</td><td class="inv-dt-val">${l.quantity??l.stock??'—'}</td></tr>`).join('')}</tbody>
      </table>
    </div>` : '';

  $('#inv-pane-stock').innerHTML = `
    <div class="inv-section">
      <div class="inv-section-title"><i class="fa fa-boxes-stacked"></i> Stock Information</div>
      <table class="inv-detail-table">
        ${stockRows.map(([label, val]) => `
          <tr><td class="inv-dt-label">${label}</td><td class="inv-dt-val">${escHtml(String(val))}</td></tr>`).join('')}
      </table>
    </div>${locHtml}`;

  // ── Images tab ──
  if (images.length) {
    $('#inv-pane-images').innerHTML = `
      <div class="inv-section">
        <div class="inv-section-title"><i class="fa fa-images"></i> Product Images (${images.length})</div>
        <div class="inv-images-grid">
          ${images.map(img => {
            const url = img.url || img.image_url || img;
            return `<div class="inv-img-card">
              <img src="${url}" alt="Product image" loading="lazy">
            </div>`;
          }).join('')}
        </div>
      </div>`;
  } else {
    $('#inv-pane-images').innerHTML = `<div class="inv-pane-empty"><i class="fa fa-image"></i><p>No images uploaded</p></div>`;
  }

  // ── Variants tab ──
  const variants = p.variants || p.product_variants || [];
  if (variants.length) {
    $('#inv-pane-variants').innerHTML = `
      <div class="inv-section">
        <div class="inv-section-title"><i class="fa fa-layer-group"></i> Variants (${variants.length})</div>
        <table class="inv-detail-table inv-variants-table">
          <thead>
            <tr>
              <th class="inv-dt-label">Variant</th>
              <th class="inv-dt-val">SKU</th>
              <th class="inv-dt-val">Price</th>
              <th class="inv-dt-val">Stock</th>
              <th class="inv-dt-val">Status</th>
            </tr>
          </thead>
          <tbody>
            ${variants.map(v => `
              <tr>
                <td class="inv-dt-label">${escHtml(v.name || v.variant_name || v.options?.map(o => o.value).join(' / ') || '—')}</td>
                <td class="inv-dt-val">${escHtml(v.sku || '—')}</td>
                <td class="inv-dt-val">${v.price != null ? parseFloat(v.price).toFixed(2) : '—'}</td>
                <td class="inv-dt-val">${v.stock_quantity ?? v.stock ?? '—'}</td>
                <td class="inv-dt-val"><span class="inv-badge ${v.is_active !== false ? 'inv-badge-green' : 'inv-badge-gray'}">${v.is_active !== false ? 'Active' : 'Inactive'}</span></td>
              </tr>`).join('')}
          </tbody>
        </table>
      </div>`;
  } else {
    $('#inv-pane-variants').innerHTML = `<div class="inv-pane-empty"><i class="fa fa-layer-group"></i><p>No variants for this product</p></div>`;
  }
}

// ── Sales chart (Canvas) ─────────────────────────────────────────────────
function drawSalesChart(canvasId, labels, series) {
  const canvas = document.getElementById(canvasId);
  if (!canvas) return;
  const ctx    = canvas.getContext('2d');
  const W = canvas.width  = canvas.offsetWidth  * window.devicePixelRatio;
  const H = canvas.height = canvas.offsetHeight * window.devicePixelRatio;
  ctx.scale(window.devicePixelRatio, window.devicePixelRatio);
  const w = canvas.offsetWidth;
  const h = canvas.offsetHeight;

  const PAD = { top: 24, right: 16, bottom: 48, left: 44 };
  const chartW = w - PAD.left - PAD.right;
  const chartH = h - PAD.top  - PAD.bottom;

  const isDark  = document.body.classList.contains('dark');
  const colBar  = isDark ? 'rgba(130,150,255,0.75)' : 'rgba(109,120,255,0.65)';
  const colGrid = isDark ? 'rgba(255,255,255,.08)'   : 'rgba(0,0,0,.07)';
  const colText = isDark ? 'rgba(255,255,255,.45)'   : 'rgba(0,0,0,.4)';

  ctx.clearRect(0, 0, w, h);

  const maxVal = Math.max(...series, 1);
  const nice   = v => v === Math.floor(v) ? String(v) : v.toFixed(1);

  // Grid lines & Y labels
  const yTicks = 4;
  ctx.font      = `11px system-ui`;
  ctx.textAlign = 'right';
  for (let i = 0; i <= yTicks; i++) {
    const val = (maxVal / yTicks) * i;
    const y   = PAD.top + chartH - (chartH * i / yTicks);
    ctx.strokeStyle = colGrid;
    ctx.lineWidth   = 1;
    ctx.beginPath(); ctx.moveTo(PAD.left, y); ctx.lineTo(PAD.left + chartW, y); ctx.stroke();
    ctx.fillStyle = colText;
    ctx.fillText(nice(val), PAD.left - 6, y + 4);
  }

  // Bars
  const n       = labels.length;
  const barW    = Math.max(4, (chartW / n) * 0.55);
  const gap     = chartW / n;

  series.forEach((val, i) => {
    const barH = chartH * (val / maxVal);
    const x    = PAD.left + gap * i + (gap - barW) / 2;
    const y    = PAD.top  + chartH - barH;
    const r    = Math.min(4, barW / 2);
    ctx.fillStyle = colBar;
    ctx.beginPath();
    ctx.moveTo(x + r, y);
    ctx.lineTo(x + barW - r, y);
    ctx.quadraticCurveTo(x + barW, y, x + barW, y + r);
    ctx.lineTo(x + barW, y + barH);
    ctx.lineTo(x, y + barH);
    ctx.lineTo(x, y + r);
    ctx.quadraticCurveTo(x, y, x + r, y);
    ctx.closePath();
    ctx.fill();
  });

  // X labels — show every Nth to avoid crowding
  const step = Math.ceil(n / 8);
  ctx.fillStyle  = colText;
  ctx.textAlign  = 'center';
  ctx.font       = '10px system-ui';
  labels.forEach((lbl, i) => {
    if (i % step !== 0 && i !== n - 1) return;
    const x = PAD.left + gap * i + gap / 2;
    ctx.fillText(lbl, x, h - PAD.bottom + 16);
  });
}

async function loadAndRenderChart(productId, period) {
  const container = $('#inv-chart-container');
  if (!container) return;

  // Update active period button
  $$('.inv-chart-period').forEach(b => b.classList.toggle('active', b.dataset.period === period));

  container.innerHTML = '<div class="inv-chart-loading"><i class="fa fa-spinner fa-spin"></i></div>';

  const res = await API.productSalesChart(productId, period);
  if (res.status !== 200) { container.innerHTML = ''; return; }

  const { labels = [], series = [], total = 0, subtitle = '' } = res.body?.data || {};

  container.innerHTML = `
    <div class="inv-chart-summary">
      <span class="inv-chart-total"><strong>${total % 1 === 0 ? total : total.toFixed(3)}</strong> units in this period</span>
      <span class="inv-chart-subtitle">${escHtml(subtitle)}</span>
    </div>
    <div class="inv-chart-wrap"><canvas id="inv-sales-canvas"></canvas></div>`;

  requestAnimationFrame(() => drawSalesChart('inv-sales-canvas', labels, series));
}

// Tab switching in detail view
$('#inv-tabs').addEventListener('click', e => {
  const tab = e.target.closest('.inv-tab');
  if (!tab) return;
  $$('#inv-tabs .inv-tab').forEach(t => t.classList.remove('active'));
  tab.classList.add('active');
  $$('.inv-tab-pane').forEach(p => p.classList.remove('active'));
  $(`#inv-pane-${tab.dataset.tab}`).classList.add('active');
});

// ── Products ───────────────────────────────────────────────────────────────
async function loadProducts(search = '', catId = 0, page = 1) {
  const grid = $('#product-grid');
  grid.innerHTML = '<div style="grid-column:1/-1;text-align:center;padding:40px;color:var(--text-muted)"><i class="fa fa-spinner fa-spin" style="font-size:24px"></i></div>';
  setStatus('Loading products…');

  const res = await API.bootstrap(search, catId, page);
  if (res.status !== 200) {
    grid.innerHTML = `<div style="grid-column:1/-1;text-align:center;padding:40px;color:#e74c3c"><i class="fa fa-circle-exclamation"></i> Failed to load products</div>`;
    setStatus('Error loading products');
    return;
  }

  const { products = [], categories = [], currency = '', settings = {}, business = {}, product_units = [] } = res.body?.data || res.body || {};
  state.products        = products;
  state.categories      = categories;
  state.receiptSettings = settings;
  state.productUnits    = product_units;
  if (currency)       state.currency = currency;
  if (business?.name) state._bizName = state._bizName || business.name;

  buildCategoryBar(categories, catId);
  buildProductGrid(products);
  setStatus(`Zeebroo POS · ${products.length} products`);
}

function buildCategoryBar(categories, active) {
  const bar = $('#category-filter');
  bar.innerHTML = `<div class="cat-chip ${active === 0 ? 'active' : ''}" data-cat="0">All</div>`;
  categories.forEach(c => {
    const chip = document.createElement('div');
    chip.className = `cat-chip ${c.id === active ? 'active' : ''}`;
    chip.dataset.cat = c.id;
    chip.textContent = c.name;
    bar.appendChild(chip);
  });
  bar.querySelectorAll('.cat-chip').forEach(chip => {
    chip.addEventListener('click', () => {
      state.activeCategory = Number(chip.dataset.cat);
      _ssClose();
      loadProducts(state.searchQuery, state.activeCategory);
    });
  });
}

function buildProductGrid(products) {
  _pgSelIdx = -1;
  const grid = $('#product-grid');
  if (!products.length) {
    grid.innerHTML = '<div style="grid-column:1/-1;text-align:center;padding:60px;color:var(--text-muted)"><i class="fa fa-box-open" style="font-size:32px;display:block;margin-bottom:10px;opacity:.3"></i>No products found</div>';
    return;
  }
  grid.innerHTML = products.map(p => {
    const outOfStock    = (parseFloat(p.stock_quantity) || 0) <= 0;
    const effectivePrice = p.discounted_sell_price ?? p.unit_sell_price ?? 0;
    const origPrice     = p.discounted_sell_price !== null && p.discounted_sell_price !== undefined ? p.unit_sell_price : null;
    const discount      = p.discount ?? null;

    let priceHtml;
    if (origPrice !== null) {
      let badge = '';
      if (discount) {
        const lbl = discount.type === 'percentage'
          ? `-${Math.round(discount.value)}%`
          : `-${parseFloat(discount.amount ?? 0).toFixed(2)}`;
        badge = ` <span class="p-discount-badge">${lbl}</span>`;
      }
      priceHtml = `<span class="p-price-orig">${parseFloat(origPrice).toFixed(2)}</span> ${parseFloat(effectivePrice).toFixed(2)}${badge}`;
    } else {
      priceHtml = parseFloat(effectivePrice).toFixed(2);
    }

    const metaParts = [];
    if (p.sku) metaParts.push(escHtml(p.sku));
    if (p.stock_quantity != null) metaParts.push(`${p.stock_quantity} in stock`);

    return `<div class="product-card${outOfStock ? ' is-out' : ''}" data-id="${p.id}" data-name="${escHtml(p.name)}" data-price="${effectivePrice}" data-stock="${p.stock_quantity ?? ''}">
      ${p.image_url ? `<img src="${p.image_url}" alt="${escHtml(p.name)}" loading="lazy">` : `<div class="p-icon"><i class="fa fa-box"></i></div>`}
      <div class="p-name">${escHtml(p.name)}</div>
      ${metaParts.length ? `<div class="p-meta">${metaParts.join(' · ')}</div>` : ''}
      <div class="p-price">${priceHtml}</div>
    </div>`;
  }).join('');

  grid.querySelectorAll('.product-card:not(.is-out)').forEach(card => {
    const p = state.products.find(pr => pr.id === Number(card.dataset.id));
    card.addEventListener('click', () => {
      if (p) handleProductClick(p);
      else addToCart({ id: Number(card.dataset.id), name: card.dataset.name, price: parseFloat(card.dataset.price), stock: card.dataset.stock !== '' ? Number(card.dataset.stock) : null, layerId: null, layerLabel: null });
    });
  });
}

// ── Search with auto-suggest ───────────────────────────────────────────────
let searchTimer;
let _ssSelIdx  = -1;
const MAX_SUGGEST = 8;

function _ssItems() { return [...$$('#search-suggest .ss-item')]; }

function _ssHighlight(idx) {
  const items = _ssItems();
  if (!items.length) return;
  _ssSelIdx = Math.max(0, Math.min(items.length - 1, idx));
  items.forEach((el, i) => el.classList.toggle('active', i === _ssSelIdx));
  items[_ssSelIdx]?.scrollIntoView({ block: 'nearest' });
}

function _ssClose() {
  $('#search-suggest').style.display = 'none';
  _ssSelIdx = -1;
}

function _ssOpen() {
  $('#search-suggest').style.display = '';
}

// Add product directly to cart without showing layer picker (barcode scanner mode)
function _addToCartDirectly(p) {
  const layers = p.layers || [];
  const layer  = layers.find(l => parseFloat(l.quantity_remaining) > 0) || null;
  if (layer) {
    addToCart({
      id: p.id, layerId: layer.id, layerLabel: layer.label || null,
      name: p.name, price: parseFloat(layer.unit_sell_price),
      stock: parseFloat(layer.quantity_remaining),
    });
  } else {
    // No in-stock layer — omit layerId so server uses FIFO or surfaces stock error
    addToCart({
      id: p.id, layerId: null, layerLabel: null,
      name: p.name, price: parseFloat(p.discounted_sell_price ?? layers[0]?.unit_sell_price ?? p.unit_sell_price ?? 0),
      stock: p.stock_quantity != null ? parseFloat(p.stock_quantity) : null,
    });
  }
}

// Find product by exact SKU (case-insensitive, trimmed)
function _findBySku(q) {
  const lower = q.trim().toLowerCase();
  if (!lower) return null;
  return state.products.find(p => p.sku && p.sku.trim().toLowerCase() === lower) || null;
}

// Try exact SKU match → add to cart and clear; returns true if matched
function _trySkuAdd(q) {
  const match = _findBySku(q);
  if (!match) return false;
  _addToCartDirectly(match);
  $('#product-search').value = '';
  state.searchQuery = '';
  _ssClose();
  clearTimeout(searchTimer);
  return true;
}

function _ssRender(q) {
  const box = $('#search-suggest');
  if (!q) { _ssClose(); return; }

  // Exact SKU match → auto-add immediately, no dropdown (barcode scanner mode)
  if (_trySkuAdd(q)) return;

  const lower = q.toLowerCase();
  const matches = state.products.filter(p =>
    p.name.toLowerCase().includes(lower) ||
    (p.sku && p.sku.toLowerCase().includes(lower))
  );

  if (!matches.length) {
    box.innerHTML = `<div class="ss-empty"><i class="fa fa-box-open"></i>No products found for "<strong>${escHtml(q)}</strong>"</div>`;
    _ssOpen(); return;
  }

  const shown  = matches.slice(0, MAX_SUGGEST);
  const more   = matches.length - shown.length;
  const cur    = state.currency ? ' ' + state.currency : '';

  box.innerHTML = shown.map((p, idx) => {
    const price      = parseFloat(p.discounted_sell_price ?? p.unit_sell_price ?? 0);
    const outOfStock = (parseFloat(p.stock_quantity) || 0) <= 0;
    const imgHtml    = p.image_url
      ? `<img class="ss-thumb" src="${p.image_url}" alt="">`
      : `<div class="ss-icon"><i class="fa fa-box"></i></div>`;
    const metaParts  = [];
    if (p.sku) metaParts.push(p.sku);
    if (p.category_name) metaParts.push(p.category_name);

    return `<div class="ss-item${outOfStock ? ' ss-out' : ''}" data-id="${p.id}" data-idx="${idx}">
      ${imgHtml}
      <div class="ss-body">
        <div class="ss-name">${escHtml(p.name)}</div>
        ${metaParts.length ? `<div class="ss-meta">${escHtml(metaParts.join(' · '))}</div>` : ''}
      </div>
      <div class="ss-right">
        <span class="ss-price">${price.toFixed(2)}${cur}</span>
        ${outOfStock
          ? '<span class="ss-out-badge">Out of stock</span>'
          : (p.stock_quantity != null ? `<span class="ss-stock">${p.stock_quantity} in stock</span>` : '')}
      </div>
    </div>`;
  }).join('') + (more > 0 ? `<div class="ss-footer"><i class="fa fa-ellipsis"></i> ${more} more — keep typing to narrow down</div>` : '');

  box.querySelectorAll('.ss-item').forEach(el => {
    el.addEventListener('mousedown', ev => {
      ev.preventDefault(); // prevent blur before click fires
      const p = state.products.find(pr => pr.id === Number(el.dataset.id));
      if (!p) return;
      if ((parseFloat(p.stock_quantity) || 0) <= 0 && (p.layers || []).every(l => parseFloat(l.quantity_remaining) <= 0)) {
        toast(`${p.name} is out of stock`, 'info'); return;
      }
      handleProductClick(p);
      $('#product-search').value = '';
      state.searchQuery = '';
      _ssClose();
      clearTimeout(searchTimer);
      loadProducts('', state.activeCategory);
    });
    el.addEventListener('mousemove', () => {
      _ssSelIdx = Number(el.dataset.idx);
      _ssItems().forEach((x, i) => x.classList.toggle('active', i === _ssSelIdx));
    });
  });

  _ssOpen();
  _ssSelIdx = -1;
}

$('#product-search').addEventListener('focus', () => {
  _pgClear();
  if ($('#product-search').value.trim()) _ssRender($('#product-search').value.trim());
});

$('#product-search').addEventListener('blur', () => {
  setTimeout(_ssClose, 150);
});

$('#product-search').addEventListener('input', (e) => {
  clearTimeout(searchTimer);
  const q = e.target.value;
  state.searchQuery = q;
  _ssRender(q.trim());
  if (q.trim()) {
    searchTimer = setTimeout(() => loadProducts(state.searchQuery, state.activeCategory), 400);
  }
});

$('#product-search').addEventListener('keydown', (e) => {
  const box  = $('#search-suggest');
  const open = box.style.display !== 'none';
  const q    = $('#product-search').value.trim();

  if (e.key === 'ArrowDown') {
    e.preventDefault();
    if (!open && q) { _ssRender(q); return; }
    _ssHighlight(_ssSelIdx < 0 ? 0 : _ssSelIdx + 1);
    return;
  }
  if (e.key === 'ArrowUp') {
    e.preventDefault();
    if (open) _ssHighlight(_ssSelIdx <= 0 ? 0 : _ssSelIdx - 1);
    return;
  }
  if (e.key === 'Enter') {
    e.preventDefault();
    // 1. Exact SKU match (barcode scanner fires Enter after the scan)
    if (q && _trySkuAdd(q)) return;
    // 2. Highlighted suggestion
    if (open && _ssSelIdx >= 0) {
      _ssItems()[_ssSelIdx]?.dispatchEvent(new MouseEvent('mousedown', { bubbles: true })); return;
    }
    // 3. First available suggestion
    if (open) {
      const first = $('#search-suggest .ss-item:not(.ss-out)');
      first?.dispatchEvent(new MouseEvent('mousedown', { bubbles: true }));
    }
    return;
  }
  if (e.key === 'Escape') {
    e.preventDefault();
    _ssClose();
    return;
  }
});

$('#btn-refresh-products').addEventListener('click', () => loadProducts(state.searchQuery, state.activeCategory));
$('#rb-refresh').addEventListener('click', () => loadProducts(state.searchQuery, state.activeCategory));

// ── Product grid keyboard navigation ───────────────────────────────────────
let _pgSelIdx = -1;

function _pgCards() {
  return [...$$('#product-grid .product-card:not(.is-out)')];
}

function _pgColumns() {
  const cards = _pgCards();
  if (cards.length < 2) return 1;
  const firstTop = cards[0].getBoundingClientRect().top;
  let cols = 0;
  for (const c of cards) {
    if (Math.abs(c.getBoundingClientRect().top - firstTop) > 4) break;
    cols++;
  }
  return Math.max(1, cols);
}

function _pgSelect(idx) {
  const cards = _pgCards();
  if (!cards.length) return;
  _pgSelIdx = Math.max(0, Math.min(cards.length - 1, idx));
  $$('#product-grid .product-card').forEach(c => c.classList.remove('kbd-selected'));
  cards[_pgSelIdx].classList.add('kbd-selected');
  cards[_pgSelIdx].scrollIntoView({ block: 'nearest', behavior: 'smooth' });
}

function _pgClear() {
  _pgSelIdx = -1;
  $$('#product-grid .product-card').forEach(c => c.classList.remove('kbd-selected'));
}

function _posIsActive() {
  return $('#panel-pos')?.classList.contains('active');
}

function _noModalOpen() {
  return ![...$$('.modal-overlay')].some(m => m.style.display !== 'none') &&
         $('#pos-layer-picker')?.getAttribute('aria-hidden') !== 'false' &&
         $('#search-suggest')?.style.display === 'none';
}

// ── End product grid keyboard navigation ───────────────────────────────────

// ── Cart item keyboard navigation ──────────────────────────────────────────
let _ciSelIdx = -1;
let _ciSelKey = null;

function _ciItems() {
  return [...$$('#cart-items .cart-item')];
}

function _ciSelect(idx) {
  const items = _ciItems();
  if (!items.length) return;
  _ciSelIdx = Math.max(0, Math.min(items.length - 1, idx));
  _ciSelKey = items[_ciSelIdx]?.dataset.key || null;
  items.forEach((el, i) => el.classList.toggle('cart-item--selected', i === _ciSelIdx));
  items[_ciSelIdx]?.scrollIntoView({ block: 'nearest', behavior: 'smooth' });
  _pgClear(); // deselect product grid when cart is active
}

function _ciClear() {
  _ciSelIdx = -1;
  _ciSelKey = null;
  _ciItems().forEach(el => el.classList.remove('cart-item--selected'));
}

function _ciRestoreSelection() {
  if (!_ciSelKey) return;
  const items = _ciItems();
  const idx = items.findIndex(el => el.dataset.key === _ciSelKey);
  if (idx >= 0) {
    _ciSelIdx = idx;
    items[idx].classList.add('cart-item--selected');
  } else {
    _ciSelIdx = -1;
    _ciSelKey = null;
  }
}
// ── Cart item discount modal ────────────────────────────────────────────────
function openDiscountModal() {
  const tab = activeTab();
  if (!tab || _ciSelKey === null) return;
  const item = tab.cart.find(i => i._key === _ciSelKey);
  if (!item) return;

  const modal = $('#ci-discount-modal');
  const input = $('#ci-discount-input');
  if (!modal || !input) return;

  $('#ci-discount-title').textContent = `Discount — ${item.name}`;
  input.value = item._discountPct != null ? item._discountPct : '';
  modal.style.display = 'flex';
  requestAnimationFrame(() => { input.focus(); input.select(); });
}

function _closeDiscountModal() {
  $('#ci-discount-modal').style.display = 'none';
}

$('#ci-discount-close')?.addEventListener('click',  _closeDiscountModal);
$('#ci-discount-cancel')?.addEventListener('click', _closeDiscountModal);
$('#ci-discount-modal')?.addEventListener('click', e => { if (e.target === e.currentTarget) _closeDiscountModal(); });

$('#ci-discount-apply')?.addEventListener('click', () => {
  const tab  = activeTab();
  const item = tab?.cart.find(i => i._key === _ciSelKey);
  if (!item) { _closeDiscountModal(); return; }

  const pct = parseFloat($('#ci-discount-input').value);
  if (isNaN(pct) || pct < 0 || pct > 100) {
    toast('Enter a discount between 0 and 100', 'error'); return;
  }
  item._discountPct = pct;
  item.price = pct > 0
    ? parseFloat((item._basePrice * (1 - pct / 100)).toFixed(2))
    : item._basePrice;
  _closeDiscountModal();
  renderCart();
  toast(pct > 0 ? `${pct}% discount applied` : 'Discount removed', 'success');
});

$('#ci-discount-input')?.addEventListener('keydown', e => {
  if (e.key === 'Enter')  { $('#ci-discount-apply').click(); e.preventDefault(); }
  if (e.key === 'Escape') { _closeDiscountModal(); e.preventDefault(); }
});

// ── Cart item note / modifier modal ─────────────────────────────────────────
function openNoteModal() {
  const tab = activeTab();
  if (!tab || _ciSelKey === null) return;
  const item = tab.cart.find(i => i._key === _ciSelKey);
  if (!item) return;

  const modal = $('#ci-note-modal');
  const input = $('#ci-note-input');
  if (!modal || !input) return;

  $('#ci-note-title').textContent = `Note — ${item.name}`;
  input.value = item._note ?? '';
  modal.style.display = 'flex';
  requestAnimationFrame(() => { input.focus(); });
}

function _closeNoteModal() {
  $('#ci-note-modal').style.display = 'none';
}

$('#ci-note-close')?.addEventListener('click',  _closeNoteModal);
$('#ci-note-cancel')?.addEventListener('click', _closeNoteModal);
$('#ci-note-modal')?.addEventListener('click', e => { if (e.target === e.currentTarget) _closeNoteModal(); });

$('#ci-note-apply')?.addEventListener('click', () => {
  const tab  = activeTab();
  const item = tab?.cart.find(i => i._key === _ciSelKey);
  if (!item) { _closeNoteModal(); return; }

  item._note = $('#ci-note-input').value.trim() || null;
  _closeNoteModal();
  renderCart();
  toast(item._note ? 'Note saved' : 'Note cleared', 'success');
});

$('#ci-note-input')?.addEventListener('keydown', e => {
  if (e.key === 'Enter')  { $('#ci-note-apply').click(); e.preventDefault(); }
  if (e.key === 'Escape') { _closeNoteModal(); e.preventDefault(); }
});

// ── Undo last scan ──────────────────────────────────────────────────────────
function undoLastCartItem() {
  const tab = activeTab();
  if (!tab || !tab.cart.length) { toast('Nothing to undo', 'info'); return; }
  const last = tab.cart[tab.cart.length - 1];
  if (last.qty > 1) {
    last.qty -= 1;
  } else {
    tab.cart.pop();
  }
  _ciClear();
  renderCart();
  renderPosTabBar();
  toast('Last item undone', 'info');
}

// ── Park / Hold sale ────────────────────────────────────────────────────────
function parkCurrentSale() {
  const tab = activeTab();
  if (!tab || !tab.cart.length) { toast('Cart is empty — nothing to park', 'info'); return; }
  const label = prompt(`Name this held sale:`, `Hold ${new Date().toLocaleTimeString([], { hour: '2-digit', minute: '2-digit' })}`);
  if (label === null) return; // cancelled
  state.parkedSales.push({
    id: state._nextParkedId++,
    label: label.trim() || `Hold ${state._nextParkedId}`,
    cart: JSON.parse(JSON.stringify(tab.cart)), // deep copy
    customer: tab._customer || null,
    parkedAt: Date.now(),
  });
  tab.cart = [];
  tab._customer = null;
  renderCart();
  renderPosTabBar();
  renderCartCustomer();
  toast(`Sale held as "${state.parkedSales[state.parkedSales.length - 1].label}"`, 'success');
  updateParkedBadge();
}

function updateParkedBadge() {
  const badge = $('#park-badge');
  if (badge) badge.textContent = state.parkedSales.length || '';
  badge && (badge.style.display = state.parkedSales.length ? '' : 'none');
}

function openParkedSalesModal() {
  const modal  = $('#park-modal');
  const list   = $('#park-modal-list');
  if (!modal || !list) return;

  if (!state.parkedSales.length) {
    list.innerHTML = '<div class="park-empty"><i class="fa fa-inbox"></i><span>No held sales</span></div>';
  } else {
    const cur = state.currency ? ' ' + state.currency : '';
    list.innerHTML = state.parkedSales.map(ps => {
      const total = ps.cart.reduce((s, i) => s + i.price * i.qty, 0);
      const items = ps.cart.reduce((s, i) => s + i.qty, 0);
      const time  = new Date(ps.parkedAt).toLocaleTimeString([], { hour: '2-digit', minute: '2-digit' });
      return `<div class="park-row" data-id="${ps.id}">
        <div class="park-row-main">
          <span class="park-row-label">${escHtml(ps.label)}</span>
          <span class="park-row-meta">${items} item${items !== 1 ? 's' : ''} &middot; ${time}</span>
        </div>
        <div class="park-row-total">${total.toFixed(2)}${cur}</div>
        <button class="park-row-recall" data-id="${ps.id}" title="Recall"><i class="fa fa-rotate-left"></i></button>
        <button class="park-row-del" data-id="${ps.id}" title="Discard"><i class="fa fa-trash"></i></button>
      </div>`;
    }).join('');

    list.querySelectorAll('.park-row-recall').forEach(btn => {
      btn.addEventListener('click', e => { e.stopPropagation(); recallParkedSale(Number(btn.dataset.id)); });
    });
    list.querySelectorAll('.park-row-del').forEach(btn => {
      btn.addEventListener('click', e => {
        e.stopPropagation();
        if (!confirm('Discard this held sale?')) return;
        state.parkedSales = state.parkedSales.filter(p => p.id !== Number(btn.dataset.id));
        updateParkedBadge();
        openParkedSalesModal(); // re-render
      });
    });
    list.querySelectorAll('.park-row').forEach(row => {
      row.addEventListener('click', () => recallParkedSale(Number(row.dataset.id)));
    });
  }

  modal.style.display = 'flex';
}

function recallParkedSale(id) {
  const ps = state.parkedSales.find(p => p.id === id);
  if (!ps) return;
  const tab = activeTab();
  if (!tab) return;

  if (tab.cart.length > 0) {
    if (!confirm('Replace current cart with the held sale?')) return;
  }
  tab.cart = JSON.parse(JSON.stringify(ps.cart));
  tab._customer = ps.customer;
  state.parkedSales = state.parkedSales.filter(p => p.id !== id);
  updateParkedBadge();
  $('#park-modal').style.display = 'none';
  renderCart();
  renderPosTabBar();
  renderCartCustomer();
  toast(`"${ps.label}" recalled`, 'success');
}

$('#park-modal')?.addEventListener('click', e => { if (e.target === e.currentTarget) $('#park-modal').style.display = 'none'; });
$('#park-modal-close')?.addEventListener('click', () => { $('#park-modal').style.display = 'none'; });

// ── Customer selection (F10) ─────────────────────────────────────────────────
let _custSearchTimer = null;

function renderCartCustomer() {
  const tab = activeTab();
  const bar = $('#cart-customer-bar');
  if (!bar) return;
  if (tab?._customer) {
    bar.innerHTML = `<i class="fa fa-user" style="color:var(--accent)"></i>
      <span class="cart-cust-name">${escHtml(tab._customer.name)}</span>
      ${tab._customer.phone ? `<span class="cart-cust-phone">${escHtml(tab._customer.phone)}</span>` : ''}
      <button class="cart-cust-clear" id="cart-cust-clear" title="Remove customer"><i class="fa fa-xmark"></i></button>`;
    bar.style.display = 'flex';
    $('#cart-cust-clear')?.addEventListener('click', () => {
      if (tab) tab._customer = null;
      renderCartCustomer();
    });
  } else {
    bar.style.display = 'none';
    bar.innerHTML = '';
  }
}

function openCustomerModal() {
  const modal = $('#cust-modal');
  if (!modal) return;
  modal.style.display = 'flex';
  const input = $('#cust-search-input');
  if (input) { input.value = ''; input.focus(); }
  $('#cust-results').innerHTML = '';
  $('#cust-new-form').style.display = 'none';
}

async function _searchCustomers(q) {
  const res = await API.customers(q);
  const list = res.body?.data ?? [];
  const el = $('#cust-results');
  if (!el) return;
  if (!list.length) {
    el.innerHTML = `<div class="cust-empty"><i class="fa fa-user-slash"></i> No customers found.<br><a href="#" id="cust-create-link">Create "${escHtml(q)}"</a></div>`;
    $('#cust-create-link')?.addEventListener('click', e => { e.preventDefault(); _showCustomerCreateForm(q); });
    return;
  }
  el.innerHTML = list.map(c => `
    <div class="cust-row" data-id="${c.id}">
      <div class="cust-row-name">${escHtml(c.name)}</div>
      ${c.phone ? `<div class="cust-row-meta">${escHtml(c.phone)}</div>` : ''}
    </div>`).join('');
  el.querySelectorAll('.cust-row').forEach(row => {
    row.addEventListener('click', () => {
      const c = list.find(x => x.id === Number(row.dataset.id));
      if (!c) return;
      const tab = activeTab();
      if (tab) tab._customer = c;
      $('#cust-modal').style.display = 'none';
      renderCartCustomer();
      toast(`Customer: ${c.name}`, 'success');
    });
  });
}

function _showCustomerCreateForm(prefill) {
  const form = $('#cust-new-form');
  if (!form) return;
  $('#cust-new-name').value  = prefill || '';
  $('#cust-new-phone').value = '';
  form.style.display = '';
  $('#cust-new-name').focus();
}

$('#cust-modal')?.addEventListener('click', e => { if (e.target === e.currentTarget) $('#cust-modal').style.display = 'none'; });
$('#cust-modal-close')?.addEventListener('click', () => { $('#cust-modal').style.display = 'none'; });
$('#cust-show-create')?.addEventListener('click', () => _showCustomerCreateForm(''));

$('#cust-search-input')?.addEventListener('input', e => {
  clearTimeout(_custSearchTimer);
  const q = e.target.value.trim();
  if (q.length < 1) { $('#cust-results').innerHTML = ''; return; }
  _custSearchTimer = setTimeout(() => _searchCustomers(q), 300);
});

$('#cust-search-input')?.addEventListener('keydown', e => {
  if (e.key === 'Escape') { $('#cust-modal').style.display = 'none'; e.preventDefault(); }
  if (e.key === 'Enter') {
    const first = $('#cust-results .cust-row');
    if (first) first.click();
  }
});

$('#cust-new-cancel')?.addEventListener('click', () => { $('#cust-new-form').style.display = 'none'; });

$('#cust-new-save')?.addEventListener('click', async () => {
  const name  = $('#cust-new-name')?.value.trim();
  const phone = $('#cust-new-phone')?.value.trim();
  if (!name) { toast('Name is required', 'error'); return; }
  const res = await API.createCustomer({ name, phone: phone || null });
  if (res.status !== 201) { toast('Failed to create customer', 'error'); return; }
  const c = res.body?.data;
  if (!c) return;
  const tab = activeTab();
  if (tab) tab._customer = c;
  $('#cust-modal').style.display = 'none';
  renderCartCustomer();
  toast(`Customer "${c.name}" created and selected`, 'success');
});

$('#cust-new-name')?.addEventListener('keydown', e => {
  if (e.key === 'Enter') $('#cust-new-save')?.click();
  if (e.key === 'Escape') { $('#cust-new-form').style.display = 'none'; e.preventDefault(); }
});

// ── Customers Management Modal ──────────────────────────────────────────────
const _cm = {
  page: 1, lastPage: 1, total: 0,
  searchQ: '', searchTimer: null,
  selectedId: null, editingId: null,
  list: [],
};

function openCustomersModal() {
  $('#customers-modal').style.display = 'flex';
  _cm.page = 1; _cm.searchQ = ''; _cm.selectedId = null; _cm.editingId = null;
  $('#cm-search').value = '';
  _cmShowDetail(false); _cmShowForm(false);
  _cmLoadList();
  requestAnimationFrame(() => $('#cm-search').focus());
}

function _cmClose() {
  $('#customers-modal').style.display = 'none';
}

async function _cmLoadList() {
  const list = $('#cm-list');
  list.innerHTML = '<div class="cm-list-empty"><i class="fa fa-spinner fa-spin"></i></div>';
  const res = await API.customers(_cm.searchQ, _cm.page);
  if (res.status !== 200) { list.innerHTML = '<div class="cm-list-empty"><i class="fa fa-triangle-exclamation"></i> Failed to load</div>'; return; }
  _cm.list      = res.body?.data ?? [];
  _cm.lastPage  = res.body?.meta?.last_page ?? 1;
  _cm.total     = res.body?.meta?.total ?? _cm.list.length;
  _cmRenderList();
  _cmRenderPagination();
}

function _cmRenderList() {
  const list = $('#cm-list');
  if (!_cm.list.length) {
    list.innerHTML = `<div class="cm-list-empty"><i class="fa fa-user-slash"></i><span>${_cm.searchQ ? 'No results' : 'No customers yet'}</span></div>`;
    return;
  }
  list.innerHTML = _cm.list.map(c => {
    const initial = (c.name || '?')[0].toUpperCase();
    const sub     = c.phone || c.email || '';
    return `<div class="cm-item${c.id === _cm.selectedId ? ' active' : ''}" data-id="${c.id}">
      <div class="cm-item-avatar">${escHtml(initial)}</div>
      <div class="cm-item-body">
        <div class="cm-item-name">${escHtml(c.name)}</div>
        ${sub ? `<div class="cm-item-sub">${escHtml(sub)}</div>` : ''}
      </div>
    </div>`;
  }).join('');

  list.querySelectorAll('.cm-item').forEach(el => {
    el.addEventListener('click', () => _cmSelectCustomer(Number(el.dataset.id)));
  });
}

function _cmRenderPagination() {
  const el = $('#cm-pagination');
  if (_cm.lastPage <= 1) { el.innerHTML = `<span>${_cm.total} customer${_cm.total !== 1 ? 's' : ''}</span>`; return; }
  el.innerHTML = `
    <button class="cm-page-btn" id="cm-pg-prev" ${_cm.page <= 1 ? 'disabled' : ''}><i class="fa fa-chevron-left"></i></button>
    <span>${_cm.page} / ${_cm.lastPage}</span>
    <button class="cm-page-btn" id="cm-pg-next" ${_cm.page >= _cm.lastPage ? 'disabled' : ''}><i class="fa fa-chevron-right"></i></button>`;
  $('#cm-pg-prev')?.addEventListener('click', () => { if (_cm.page > 1) { _cm.page--; _cmLoadList(); } });
  $('#cm-pg-next')?.addEventListener('click', () => { if (_cm.page < _cm.lastPage) { _cm.page++; _cmLoadList(); } });
}

async function _cmSelectCustomer(id) {
  _cm.selectedId = id;
  _cm.editingId  = null;
  _cmRenderList();
  _cmShowForm(false);
  _cmShowDetail(true);
  const pane = $('#cm-detail-view');
  if (pane) pane.style.opacity = '.5';

  const res = await API.customer(id);
  if (res.status !== 200) { _cmShowDetail(false); return; }
  const c = res.body?.data;
  if (!c) return;

  if (pane) pane.style.opacity = '1';
  const init = (c.name || '?')[0].toUpperCase();
  $('#cm-dv-avatar').textContent = init;
  $('#cm-dv-name').textContent   = c.name;
  $('#cm-dv-sales-badge').textContent = `${c.sales_count ?? 0} sale${(c.sales_count ?? 0) !== 1 ? 's' : ''}`;

  const fields = [
    { label: 'Phone',   val: c.phone },
    { label: 'Email',   val: c.email },
    { label: 'Address', val: c.address },
    { label: 'Notes',   val: c.notes },
  ];
  $('#cm-dv-fields').innerHTML = fields.map(f => `
    <div class="cm-dv-field">
      <div class="cm-dv-field-label">${f.label}</div>
      <div class="cm-dv-field-val${f.val ? '' : ' empty'}">${f.val ? escHtml(f.val) : '—'}</div>
    </div>`).join('');

  const history = $('#cm-dv-history');
  if (c.recent_sales?.length) {
    history.innerHTML = `<div class="cm-dv-history-title"><i class="fa fa-clock-rotate-left"></i> Recent Sales</div>` +
      c.recent_sales.map(s => `
        <div class="cm-dv-sale-row">
          <span class="cm-dv-sale-num">${escHtml(s.sale_number || `#${s.id}`)}</span>
          <span class="cm-dv-sale-date">${s.sold_at ? s.sold_at.slice(0, 10) : ''}</span>
          <span class="cm-dv-sale-amt">${parseFloat(s.total || 0).toFixed(2)}</span>
        </div>`).join('');
  } else {
    history.innerHTML = '<div class="cm-dv-no-sales"><i class="fa fa-receipt"></i> No sales yet</div>';
  }
}

function _cmShowDetail(show) {
  $('#cm-detail-empty').style.display = show ? 'none' : '';
  $('#cm-detail-view').style.display  = show ? 'flex' : 'none';
}

function _cmShowForm(show, customer = null) {
  $('#cm-form-view').style.display   = show ? 'flex' : 'none';
  $('#cm-detail-empty').style.display = (!show && !_cm.selectedId) ? '' : 'none';
  $('#cm-detail-view').style.display  = (!show && _cm.selectedId) ? 'flex' : 'none';

  if (show) {
    const isEdit = customer !== null;
    $('#cm-form-title').textContent  = isEdit ? 'Edit Customer' : 'New Customer';
    $('#cm-f-name').value    = customer?.name    ?? '';
    $('#cm-f-phone').value   = customer?.phone   ?? '';
    $('#cm-f-email').value   = customer?.email   ?? '';
    $('#cm-f-address').value = customer?.address ?? '';
    $('#cm-f-notes').value   = customer?.notes   ?? '';
    requestAnimationFrame(() => $('#cm-f-name').focus());
  }
}

async function _cmSave() {
  const name    = $('#cm-f-name').value.trim();
  const phone   = $('#cm-f-phone').value.trim() || null;
  const email   = $('#cm-f-email').value.trim() || null;
  const address = $('#cm-f-address').value.trim() || null;
  const notes   = $('#cm-f-notes').value.trim() || null;

  if (!name) { toast('Name is required', 'error'); $('#cm-f-name').focus(); return; }

  const btn = $('#cm-form-save');
  btn.disabled = true; btn.innerHTML = '<i class="fa fa-spinner fa-spin"></i> Saving…';

  let res;
  if (_cm.editingId) {
    res = await API.updateCustomer(_cm.editingId, { name, phone, email, address, notes });
  } else {
    res = await API.createCustomer({ name, phone, email, address, notes });
  }

  btn.disabled = false; btn.innerHTML = '<i class="fa fa-check"></i> Save';

  if (res.status !== 200 && res.status !== 201) {
    const msg = res.body?.errors?.name?.[0] || res.body?.message || 'Failed to save';
    toast(msg, 'error'); return;
  }

  const c = res.body?.data;
  const wasEditing = !!_cm.editingId;
  _cm.editingId = null;
  _cmShowForm(false);
  await _cmLoadList();
  if (c) { _cm.selectedId = c.id; _cmRenderList(); _cmSelectCustomer(c.id); }
  toast(wasEditing ? 'Customer updated' : 'Customer saved', 'success');
}

async function _cmDelete() {
  if (!_cm.selectedId) return;
  const c = _cm.list.find(x => x.id === _cm.selectedId);
  if (!confirm(`Delete "${c?.name ?? 'this customer'}"? This cannot be undone.`)) return;
  const res = await API.deleteCustomer(_cm.selectedId);
  if (res.status !== 200 && res.status !== 204) { toast('Failed to delete', 'error'); return; }
  _cm.selectedId = null;
  _cmShowDetail(false);
  _cmLoadList();
  toast('Customer deleted', 'success');
}

// Wire modal controls
$('#cm-close')?.addEventListener('click', _cmClose);
$('#customers-modal')?.addEventListener('click', e => { if (e.target === e.currentTarget) _cmClose(); });

$('#cm-new-btn')?.addEventListener('click', () => {
  _cm.editingId = null; _cm.selectedId = null;
  _cmRenderList();
  _cmShowDetail(false);
  _cmShowForm(true);
});

$('#cm-btn-edit')?.addEventListener('click', async () => {
  if (!_cm.selectedId) return;
  const res = await API.customer(_cm.selectedId);
  if (res.status !== 200) return;
  const c = res.body?.data;
  _cm.editingId = _cm.selectedId;
  _cmShowForm(true, c);
});

$('#cm-btn-assign')?.addEventListener('click', () => {
  const c = _cm.list.find(x => x.id === _cm.selectedId);
  if (!c) return;
  const tab = activeTab();
  if (tab) tab._customer = c;
  renderCartCustomer();
  _cmClose();
  toast(`Customer "${c.name}" assigned to sale`, 'success');
});

$('#cm-btn-delete')?.addEventListener('click', _cmDelete);

$('#cm-form-cancel')?.addEventListener('click', () => {
  _cm.editingId = null;
  _cmShowForm(false);
  if (_cm.selectedId) _cmShowDetail(true);
});

$('#cm-form-save')?.addEventListener('click', _cmSave);

$('#cm-search')?.addEventListener('input', e => {
  clearTimeout(_cm.searchTimer);
  _cm.searchQ = e.target.value.trim();
  _cm.page = 1;
  _cm.searchTimer = setTimeout(_cmLoadList, 300);
});

$('#cm-search')?.addEventListener('keydown', e => {
  if (e.key === 'Escape') { e.preventDefault(); if (_cm.searchQ) { _cm.searchQ = ''; e.target.value = ''; _cm.page = 1; _cmLoadList(); } else _cmClose(); }
  if (e.key === 'Enter' && _cm.list.length) { _cmSelectCustomer(_cm.list[0].id); }
});

['#cm-f-name','#cm-f-phone','#cm-f-email','#cm-f-address','#cm-f-notes'].forEach(sel => {
  $(sel)?.addEventListener('keydown', e => { if (e.key === 'Enter' && sel !== '#cm-f-notes') _cmSave(); if (e.key === 'Escape') $('#cm-form-cancel')?.click(); });
});

// ── End cart keyboard navigation ────────────────────────────────────────────

// ── Account balance dropdown ───────────────────────────────────────────────
let _posAccountsCache = null;

async function loadPosAccounts() {
  const list = $('#pos-account-list');
  list.innerHTML = '<div class="pos-acct-loading"><i class="fa fa-spinner fa-spin"></i> Loading…</div>';
  $('#pos-account-total').textContent = '—';

  try {
    const res = await API.accounts();
    const accounts = res.body?.data ?? res.body ?? [];
    if (!Array.isArray(accounts)) throw new Error('unexpected format');
    _posAccountsCache = accounts;
    renderPosAccounts();
  } catch (err) {
    $('#pos-account-list').innerHTML = '<div class="pos-acct-empty"><i class="fa fa-triangle-exclamation"></i> Failed to load</div>';
  }
}

function renderPosAccounts() {
  const list = $('#pos-account-list');
  const cur = state.currency ? ' ' + state.currency : '';
  const accounts = _posAccountsCache;

  if (!accounts || accounts.length === 0) {
    list.innerHTML = '<div class="pos-acct-empty">No accounts found</div>';
    $('#pos-account-total').textContent = '—';
    return;
  }

  list.innerHTML = accounts.map(a => {
    const balance = parseFloat(a.current_balance || 0).toFixed(2);
    return `<div class="pos-acct-row">
      <div class="pos-acct-info">
        <div class="pos-acct-name">${escHtml(a.account_name || '')}</div>
        ${a.bank_name ? `<div class="pos-acct-bank">${escHtml(a.bank_name)}</div>` : ''}
      </div>
      <div class="pos-acct-balance">${balance}${cur}</div>
    </div>`;
  }).join('');

  const total = accounts.reduce((s, a) => s + parseFloat(a.current_balance || 0), 0);
  $('#pos-account-total').textContent = total.toFixed(2) + cur;
}

// ── Business / Branch switcher ────────────────────────────────────────────
const _bizSw = { businesses: [], branches: [], ready: false };

async function _bizSwInit() {
  // Fetch current user info if not already set (startup / already-logged-in path)
  if (!state._userName) {
    const meRes = await API.me();
    if (meRes.status === 200) {
      state._userName  = meRes.body?.data?.name  || null;
      state._userEmail = meRes.body?.data?.email || null;
      // Refresh the dropdown header now that we have the real user
      const currentBizName = $('#tb-profile-name').textContent;
      updateProfileUI(currentBizName, state._userEmail, state._userName);
    }
  }

  const res = await API.businesses();
  if (res.status !== 200) return;
  _bizSw.businesses = res.body?.data || [];
  if (!_bizSw.businesses.length) return;

  const curBiz = _bizSw.businesses.find(b => b.id === state.config.business_id)
                 || _bizSw.businesses[0];
  $('#tb-biz-name').textContent = curBiz?.name || state._bizName || 'Business';
  $('#tb-biz-wrap').style.display = '';
  _bizSwRenderBizList();

  await _bizSwLoadBranches();
  _bizSw.ready = true;
}

function _bizSwRenderBizList() {
  const list = $('#tb-biz-list');
  if (!_bizSw.businesses.length) {
    list.innerHTML = '<div class="tb-sw-empty">No businesses found</div>';
    return;
  }
  list.innerHTML = _bizSw.businesses.map(b => {
    const active = b.id === state.config.business_id;
    return `<button class="tb-sw-item${active ? ' active' : ''}" data-biz-id="${b.id}">
      <i class="fa fa-building tb-sw-item-icon"></i>
      ${escHtml(b.name)}
      ${active ? '<i class="fa fa-check tb-sw-item-check"></i>' : ''}
    </button>`;
  }).join('');
  list.querySelectorAll('[data-biz-id]').forEach(btn => {
    btn.addEventListener('click', async () => {
      const id = Number(btn.dataset.bizId);
      _bizSwClose();
      if (id !== state.config.business_id) await _bizSwSwitchBiz(id);
    });
  });
}

async function _bizSwSwitchBiz(bizId) {
  const biz = _bizSw.businesses.find(b => b.id === bizId);
  if (!biz) return;
  await window.electronAPI.setConfig({ business_id: bizId, branch_id: null });
  state.config   = await window.electronAPI.getConfig();
  state._bizName = biz.name;
  $('#app-title').textContent   = `Zeebroo POS — ${biz.name}`;
  $('#tb-biz-name').textContent = biz.name;
  updateProfileUI(biz.name, state._userEmail, state._userName);
  $('#status-branch').innerHTML = `<i class="fa fa-building"></i> ${biz.name}`;
  _bizSwRenderBizList();
  await _bizSwLoadBranches();
  _bizSwRefreshAll();
}

function _bizSwRefreshAll() {
  // Clear all module-level caches
  _posAccountsCache = null;
  invState.loaded   = false;
  _dsAllData        = [];

  // Reset POS carts — they belong to the old business
  state.posTabs          = [];
  state.activePosTabId   = null;
  state._nextPosTabId    = 1;
  addPosTab();

  // Re-run whichever tab is currently visible
  const activeTabName = $('.ribbon-tab.active')?.dataset.tab || 'home';
  switch (activeTabName) {
    case 'home': {
      const homeView = $('.home-tab-btn.active')?.dataset.homeView || 'kpi';
      loadHomeDashboard();
      if (homeView !== 'kpi') switchHomeView(homeView);
      break;
    }
    case 'pos':
      loadProducts();
      break;
    case 'sales':
      if (_sal.view === 'quotes') loadQuotesList(); else loadSalesList();
      break;
    case 'inventory': {
      const invView = $('.inv-subnav-btn.active')?.dataset.invView || 'products';
      switchInvView(invView);
      break;
    }
    case 'finance': {
      const finView = $('#panel-finance .fin-subnav-btn.active')?.dataset.fin || 'flow';
      switchFinView(finView);
      break;
    }
    case 'hr': {
      const hrView = $('#panel-hr .fin-subnav-btn.active')?.dataset.hr || 'employees';
      switchHrView(hrView);
      break;
    }
    case 'design':
      switchDesignView('all');
      break;
    default:
      loadProducts();
  }
}

async function _bizSwLoadBranches() {
  const res = await API.branches();
  const raw  = res.body?.data;
  const branches = (raw?.branches ?? raw) ?? [];
  _bizSw.branches = branches;

  if (!branches.length) {
    $('#tb-branch-wrap').style.display = 'none';
    return;
  }
  const curBranch = branches.find(b => b.id === state.config.branch_id);
  $('#tb-branch-name').textContent = curBranch?.name || 'All Branches';
  $('#tb-branch-wrap').style.display = '';
  _bizSwRenderBranchList();
}

function _bizSwRenderBranchList() {
  const list        = $('#tb-branch-list');
  const curBranchId = state.config.branch_id;
  const allActive   = !curBranchId;
  list.innerHTML = `
    <button class="tb-sw-item${allActive ? ' active' : ''}" data-branch-id="">
      <i class="fa fa-building tb-sw-item-icon"></i>
      All Branches
      ${allActive ? '<i class="fa fa-check tb-sw-item-check"></i>' : ''}
    </button>
    ${_bizSw.branches.map(b => {
      const active = b.id === curBranchId;
      return `<button class="tb-sw-item${active ? ' active' : ''}" data-branch-id="${b.id}">
        <i class="fa fa-code-branch tb-sw-item-icon"></i>
        ${escHtml(b.name)}
        ${active ? '<i class="fa fa-check tb-sw-item-check"></i>' : ''}
      </button>`;
    }).join('')}`;
  list.querySelectorAll('[data-branch-id]').forEach(btn => {
    btn.addEventListener('click', async () => {
      const id = btn.dataset.branchId ? Number(btn.dataset.branchId) : null;
      _bizSwClose();
      await _bizSwSwitchBranch(id);
    });
  });
}

async function _bizSwSwitchBranch(branchId) {
  await window.electronAPI.setConfig({ branch_id: branchId });
  state.config = await window.electronAPI.getConfig();
  const branch = _bizSw.branches.find(b => b.id === branchId);
  const name   = branch?.name || 'All Branches';
  $('#tb-branch-name').textContent = name;
  $('#status-branch').innerHTML = branchId
    ? `<i class="fa fa-code-branch"></i> ${name}`
    : `<i class="fa fa-building"></i> ${state._bizName || ''}`;
  _bizSwRenderBranchList();
  _bizSwRefreshAll();
}

function _bizSwClose() {
  $('#tb-biz-btn').classList.remove('open');
  $('#tb-biz-menu').classList.remove('open');
  $('#tb-branch-btn').classList.remove('open');
  $('#tb-branch-menu').classList.remove('open');
}

$('#tb-biz-btn')?.addEventListener('click', e => {
  e.stopPropagation();
  const opening = !$('#tb-biz-menu').classList.contains('open');
  _bizSwClose();
  if (opening) {
    $('#tb-biz-menu').classList.add('open');
    $('#tb-biz-btn').classList.add('open');
    // also close account menu
    _posAcctDrop?.classList.remove('open');
    _posAcctBtn?.classList.remove('active');
  }
});

$('#tb-branch-btn')?.addEventListener('click', e => {
  e.stopPropagation();
  const opening = !$('#tb-branch-menu').classList.contains('open');
  _bizSwClose();
  if (opening) {
    $('#tb-branch-menu').classList.add('open');
    $('#tb-branch-btn').classList.add('open');
    _posAcctDrop?.classList.remove('open');
    _posAcctBtn?.classList.remove('active');
  }
});

document.addEventListener('click', e => {
  if (!$('#tb-biz-wrap')?.contains(e.target) && !$('#tb-branch-wrap')?.contains(e.target)) {
    _bizSwClose();
  }
});
// ── End business/branch switcher ──────────────────────────────────────────

// ── Business onboarding wizard ─────────────────────────────────────────────
const _bbwz = { step: 1, selectedCat: null, selectedFeatures: new Set(), cats: [] };

function _bbwzOpen() {
  _bizSwClose();
  _bbwz.step = 1;
  _bbwz.selectedCat = null;
  _bbwz.selectedFeatures = new Set();
  $('#bbwz-name').value = '';
  $('#bbwz-alert').style.display = 'none';
  $('#bbwz-submit-alert').style.display = 'none';
  _bbwzSetStep(1);
  if (_bbwz.cats.length) {
    _bbwzBuildCatGrid();
  } else {
    API.businessCategories().then(res => {
      _bbwz.cats = res.body?.data || [];
      _bbwzBuildCatGrid();
    });
  }
  _bbwzBuildFeatList();
  $('#bbwz-overlay').style.display = 'flex';
  setTimeout(() => $('#bbwz-name').focus(), 60);
}

function _bbwzClose() {
  $('#bbwz-overlay').style.display = 'none';
}

function _bbwzBuildCatGrid() {
  const grid = $('#bbwz-cat-grid');
  grid.innerHTML = _bbwz.cats.map(o => {
    const iconCls = _obCatIcons[o.value] || 'fa-briefcase';
    const color   = _obCatColors[o.value] || '#4e8ef7';
    const active  = o.value === _bbwz.selectedCat ? ' active' : '';
    return `<div class="bbwz-cat-item${active}" data-cat="${escHtml(o.value)}" style="--cat-color:${color}">
      <div class="bbwz-cat-icon"><i class="fa ${escHtml(iconCls)}"></i></div>
      <span>${escHtml(o.label)}</span>
    </div>`;
  }).join('');
  grid.querySelectorAll('.bbwz-cat-item').forEach(item => {
    item.addEventListener('click', () => {
      _bbwz.selectedCat = item.dataset.cat;
      grid.querySelectorAll('.bbwz-cat-item').forEach(c => c.classList.remove('active'));
      item.classList.add('active');
    });
  });
}

function _bbwzBuildFeatList() {
  const list = $('#bbwz-feat-list');
  list.innerHTML = _obFeatureDefs.map(f => {
    const active = _bbwz.selectedFeatures.has(f.key) ? ' active' : '';
    return `<div class="bbwz-feat-card${active}" data-fkey="${f.key}" style="--feat-color:${f.color}">
      <div class="bbwz-feat-check"><i class="fa fa-check"></i></div>
      <img src="${escHtml(f.img)}" alt="${escHtml(f.name)}" class="bbwz-feat-img">
      <div class="bbwz-feat-name">${escHtml(f.name)}</div>
    </div>`;
  }).join('');
  list.querySelectorAll('.bbwz-feat-card').forEach(card => {
    card.addEventListener('click', () => {
      const key = card.dataset.fkey;
      if (_bbwz.selectedFeatures.has(key)) { _bbwz.selectedFeatures.delete(key); card.classList.remove('active'); }
      else                                  { _bbwz.selectedFeatures.add(key);    card.classList.add('active'); }
    });
  });
}

function _bbwzBuildReview() {
  const catLabel    = _bbwz.cats.find(c => c.value === _bbwz.selectedCat)?.label || _bbwz.selectedCat || '—';
  const featureNames = [..._bbwz.selectedFeatures].map(k => _obFeatureDefs.find(f => f.key === k)?.name || k);
  $('#bbwz-review').innerHTML = `
    <div class="bbwz-review-row">
      <div class="bbwz-review-label">Name</div>
      <div class="bbwz-review-val">${escHtml($('#bbwz-name').value.trim())}</div>
    </div>
    <div class="bbwz-review-row">
      <div class="bbwz-review-label">Industry</div>
      <div class="bbwz-review-val">${escHtml(catLabel)}</div>
    </div>
    <div class="bbwz-review-row">
      <div class="bbwz-review-label">Features</div>
      <div class="bbwz-review-val">${featureNames.length
        ? `<div class="bbwz-feat-tags">${featureNames.map(n => `<span class="bbwz-feat-tag">${escHtml(n)}</span>`).join('')}</div>`
        : '<span style="color:var(--text-muted)">None selected</span>'
      }</div>
    </div>`;
}

function _bbwzSetStep(n) {
  _bbwz.step = n;
  [1, 2, 3].forEach(i => {
    const dot  = $(`[data-bbwz-dot="${i}"]`);
    const line = $(`[data-bbwz-line="${i - 1}"]`);
    dot.classList.toggle('active', i === n);
    dot.classList.toggle('done',   i < n);
    if (line) line.classList.toggle('filled', i <= n);
    $(`#bbwz-panel-${i}`).style.display = i === n ? '' : 'none';
  });
  $('#bbwz-back-btn').style.display   = n > 1 ? '' : 'none';
  $('#bbwz-next-btn').style.display   = n < 3 ? '' : 'none';
  $('#bbwz-submit-btn').style.display = n === 3 ? '' : 'none';
}

$('#bbwz-next-btn').addEventListener('click', () => {
  if (_bbwz.step === 1) {
    const name = $('#bbwz-name').value.trim();
    if (!name) { showAlert($('#bbwz-alert'), 'Please enter a business name'); $('#bbwz-name').focus(); return; }
    $('#bbwz-alert').style.display = 'none';
    _bbwzSetStep(2);
  } else if (_bbwz.step === 2) {
    _bbwzBuildReview();
    _bbwzSetStep(3);
  }
});

$('#bbwz-back-btn').addEventListener('click', () => {
  if (_bbwz.step > 1) _bbwzSetStep(_bbwz.step - 1);
});

$('#bbwz-cancel-btn').addEventListener('click', () => _bbwzClose());

$('#bbwz-submit-btn').addEventListener('click', async () => {
  const name = $('#bbwz-name').value.trim();
  if (!name) { _bbwzSetStep(1); $('#bbwz-name').focus(); return; }
  const btn = $('#bbwz-submit-btn');
  btn.disabled = true;
  btn.innerHTML = '<i class="fa fa-spinner fa-spin"></i> Creating…';
  try {
    const res = await API.createBusiness({
      name,
      category: _bbwz.selectedCat || 'other',
      features: [..._bbwz.selectedFeatures],
    });
    if (res.status >= 400) throw new Error(res.body?.message || `Error ${res.status}`);
    const newBiz = res.body?.data;
    if (newBiz) {
      _bizSw.businesses.push(newBiz);
      await _bizSwSwitchBiz(newBiz.id);
    }
    _bbwzClose();
  } catch (err) {
    showAlert($('#bbwz-submit-alert'), err.message || 'Failed to create business');
  } finally {
    btn.disabled = false;
    btn.innerHTML = '<i class="fa fa-check"></i> Create Business';
  }
});

$('#bbwz-overlay').addEventListener('click', e => {
  if (e.target === e.currentTarget) _bbwzClose();
});

$('#tb-biz-add-btn').addEventListener('click', () => _bbwzOpen());
// ── End business onboarding wizard ────────────────────────────────────────

const _posAcctBtn  = $('#tb-acct-btn');
const _posAcctDrop = $('#tb-acct-menu');

_posAcctBtn.addEventListener('click', (e) => {
  e.stopPropagation();
  const opening = !_posAcctDrop.classList.contains('open');
  _posAcctDrop.classList.toggle('open', opening);
  _posAcctBtn.classList.toggle('active', opening);
  if (opening) loadPosAccounts();
});

$('#pos-account-refresh').addEventListener('click', (e) => {
  e.stopPropagation();
  _posAccountsCache = null;
  loadPosAccounts();
});

document.addEventListener('click', (e) => {
  if (!$('#tb-acct-wrap').contains(e.target)) {
    _posAcctDrop.classList.remove('open');
    _posAcctBtn.classList.remove('active');
  }
});
_posAcctBtn.addEventListener('click', () => _bizSwClose());
// ── End account dropdown ───────────────────────────────────────────────────

// ── POS Settings Modal ─────────────────────────────────────────────────────
async function openPosSettings() {
  const modal = $('#pos-settings-modal');
  modal.style.display = 'flex';
  psmShowTab('general');

  const [sRes, aRes] = await Promise.all([API.settingsGet(), API.accounts()]);

  if (sRes.status !== 200) { toast('Failed to load POS settings', 'error'); return; }

  const s        = sRes.body?.data ?? {};
  const accounts = aRes.body?.data ?? [];

  // accounts dropdown
  const sel = $('#psm-default-account');
  sel.innerHTML = '<option value="">— None —</option>' +
    accounts.map(a => `<option value="${a.id}">${escHtml(a.account_name)}${a.bank_name ? ' · ' + escHtml(a.bank_name) : ''}</option>`).join('');

  // General
  $('#psm-theme').value              = s.display_theme ?? 'inherit';
  $('#psm-discount-field').checked   = !!s.discount_field_enabled;
  $('#psm-checkout-modal').checked   = !!s.checkout_modal_enabled;
  $('#psm-service-products').checked = !!s.show_service_bound_products;
  $('#psm-featured-products').value  = s.featured_products_limit ?? 0;
  $('#psm-featured-categories').value = s.featured_categories_limit ?? 0;

  // Accounts
  sel.value = s.default_deposit_account_id ?? '';
  $('#psm-settlement-mode').value    = s.payment_settlement_mode ?? 'immediate';
  $('#psm-show-account-info').checked = !!s.show_account_info;

  // Receipt
  $('#psm-show-biz-name').checked    = !!s.show_business_name;
  $('#psm-show-biz-address').checked = !!s.show_business_address;
  $('#psm-receipt-header').value     = s.receipt_header ?? '';
  $('#psm-receipt-footer').value     = s.receipt_footer ?? '';
}

function psmShowTab(tab) {
  $$('.psm-nav-item').forEach(b => b.classList.toggle('active', b.dataset.tab === tab));
  $$('.psm-panel').forEach(p => { p.style.display = p.id === 'psm-tab-' + tab ? 'block' : 'none'; });
}

$$('.psm-nav-item').forEach(btn => btn.addEventListener('click', () => psmShowTab(btn.dataset.tab)));

$('#psm-close').addEventListener('click',  () => { $('#pos-settings-modal').style.display = 'none'; });
$('#psm-cancel').addEventListener('click', () => { $('#pos-settings-modal').style.display = 'none'; });
$('#pos-settings-modal').addEventListener('click', (e) => {
  if (e.target === e.currentTarget) e.currentTarget.style.display = 'none';
});

$('#rb-pos-settings').addEventListener('click', openPosSettings);

$('#psm-save').addEventListener('click', async () => {
  const btn = $('#psm-save');
  btn.disabled = true;
  btn.innerHTML = '<i class="fa fa-spinner fa-spin"></i> Saving…';

  const payload = {
    display_theme:               $('#psm-theme').value,
    discount_field_enabled:      $('#psm-discount-field').checked,
    checkout_modal_enabled:      $('#psm-checkout-modal').checked,
    show_service_bound_products: $('#psm-service-products').checked,
    featured_products_limit:     parseInt($('#psm-featured-products').value) || 0,
    featured_categories_limit:   parseInt($('#psm-featured-categories').value) || 0,
    default_deposit_account_id:  $('#psm-default-account').value || null,
    payment_settlement_mode:     $('#psm-settlement-mode').value,
    show_account_info:           $('#psm-show-account-info').checked,
    show_business_name:          $('#psm-show-biz-name').checked,
    show_business_address:       $('#psm-show-biz-address').checked,
    receipt_header:              $('#psm-receipt-header').value,
    receipt_footer:              $('#psm-receipt-footer').value,
  };

  const res = await API.settingsUpdate(payload);
  btn.disabled = false;
  btn.innerHTML = '<i class="fa fa-floppy-disk"></i> Save settings';

  if (res.status !== 200) {
    toast('Failed to save: ' + (res.body?.message ?? 'unknown error'), 'error');
    return;
  }

  $('#pos-settings-modal').style.display = 'none';
  toast('POS settings saved', 'success');
});
// ── End POS Settings Modal ─────────────────────────────────────────────────

// ── Receipt Print ──────────────────────────────────────────────────────────
function buildReceiptHTML(sale) {
  const s   = state.receiptSettings || {};
  const cur = state.currency ? ' ' + state.currency : '';
  const bizName = s.show_business_name !== false ? (state._bizName || '') : '';

  const dateStr = sale.sold_at
    ? new Date(sale.sold_at).toLocaleString([], { dateStyle: 'short', timeStyle: 'short' })
    : new Date().toLocaleString([], { dateStyle: 'short', timeStyle: 'short' });

  const itemsHTML = (sale.items || []).map(i => `
    <div class="rcpt-item">
      <span class="ri-name">${escHtml(i.product_name)}</span>
      <span class="ri-qty">${i.quantity % 1 === 0 ? parseInt(i.quantity) : parseFloat(i.quantity).toFixed(2)}</span>
      <span class="ri-price">${i.unit_sell_price.toFixed(2)}</span>
      <span class="ri-total">${i.line_total.toFixed(2)}</span>
    </div>`).join('');

  const discount = parseFloat(sale.discount_amount || 0);
  const change   = parseFloat(sale.change_amount || 0);

  let totalsHTML = `
    <div class="rcpt-total-row"><span>Subtotal</span><span>${parseFloat(sale.subtotal).toFixed(2)}${cur}</span></div>`;
  if (discount > 0) {
    totalsHTML += `<div class="rcpt-total-row"><span>Discount${sale.discount_percent ? ' (' + sale.discount_percent + '%)' : ''}</span><span>-${discount.toFixed(2)}${cur}</span></div>`;
  }
  totalsHTML += `
    <hr class="rcpt-divider-solid">
    <div class="rcpt-total-row grand"><span>TOTAL</span><span>${parseFloat(sale.total).toFixed(2)}${cur}</span></div>
    <div class="rcpt-total-row"><span>Paid (${escHtml(sale.payment_method_label || sale.payment_method || '')})</span><span>${parseFloat(sale.amount_paid || sale.total).toFixed(2)}${cur}</span></div>`;
  if (change > 0.005) {
    totalsHTML += `<div class="rcpt-total-row change"><span>Change</span><span>${change.toFixed(2)}${cur}</span></div>`;
  }

  const header = s.receipt_header ? `<div class="rcpt-biz-sub">${escHtml(s.receipt_header)}</div>` : '';
  const footer = s.receipt_footer || 'Thank you for your purchase!';

  return `
    ${bizName ? `<div class="rcpt-biz-name">${escHtml(bizName)}</div>` : ''}
    ${header}
    <hr class="rcpt-divider">
    <div class="rcpt-meta"><span>Receipt #</span><span>${escHtml(sale.sale_number || '')}</span></div>
    <div class="rcpt-meta"><span>Date</span><span>${dateStr}</span></div>
    ${sale.cashier?.name ? `<div class="rcpt-meta"><span>Cashier</span><span>${escHtml(sale.cashier.name)}</span></div>` : ''}
    <hr class="rcpt-divider">
    <div class="rcpt-items-header">
      <span class="ih-name">Item</span>
      <span class="ih-qty">Qty</span>
      <span class="ih-price">Price</span>
      <span class="ih-total">Total</span>
    </div>
    ${itemsHTML}
    <hr class="rcpt-divider">
    <div class="rcpt-totals">${totalsHTML}</div>
    <div class="rcpt-thank">${escHtml(footer)}</div>
    <hr class="rcpt-divider">
    <div class="rcpt-footer">
      <span class="rcpt-payment-badge">${escHtml(sale.payment_method_label || sale.payment_method || 'Paid')}</span>
    </div>`;
}

function showReceiptModal(sale) {
  const html = buildReceiptHTML(sale);
  $('#rcpt-paper').innerHTML         = html;
  $('#receipt-printable').innerHTML  = `<div class="rcpt-paper">${html}</div>`;
  $('#receipt-modal').style.display  = 'flex';
}

$('#rcpt-close').addEventListener('click', () => { $('#receipt-modal').style.display = 'none'; });
$('#receipt-modal').addEventListener('click', (e) => {
  if (e.target === e.currentTarget) e.currentTarget.style.display = 'none';
});

$('#rcpt-print').addEventListener('click', () => {
  $('#receipt-printable').style.display = 'block';
  window.print();
  setTimeout(() => { $('#receipt-printable').style.display = 'none'; }, 500);
});
// ── End Receipt Print ──────────────────────────────────────────────────────
$('#rb-clear-filters').addEventListener('click', () => {
  state.searchQuery = '';
  state.activeCategory = 0;
  $('#product-search').value = '';
  loadProducts();
});

$('#rb-search').addEventListener('click', () => {
  activateTab('pos');
  $('#product-search').focus();
});

// Keyboard shortcuts
document.addEventListener('keydown', (e) => {
  const mod = e.ctrlKey || e.metaKey;

  // ── Global navigation (always fire) ──────────────────────────────────────
  if (e.key === 'Insert' || e.key === 'F3' || e.key === 'F2') {
    activateTab('pos'); $('#product-search')?.focus(); e.preventDefault(); return;
  }
  if (e.key === 'F4')  { activateTab('pos'); openAddProductModal(); e.preventDefault(); return; }
  if (e.key === 'F5')  { loadProducts(state.searchQuery, state.activeCategory); e.preventDefault(); return; }
  if (e.key === 'F6')  { parkCurrentSale(); e.preventDefault(); return; }
  if (e.key === 'F7')  { openParkedSalesModal(); e.preventDefault(); return; }
  if (e.key === 'F8')  { clearCart(); e.preventDefault(); return; }
  if (e.key === 'F9')  { openRefundModal(); e.preventDefault(); return; }
  if (e.key === 'F10') { openCustomerModal(); e.preventDefault(); return; }
  if (e.key === 'F11') {
    state.isFullscreen = !state.isFullscreen;
    window.electronAPI.fullscreen(state.isFullscreen);
    $('#fs-icon').className = state.isFullscreen ? 'fa fa-compress' : 'fa fa-expand';
    e.preventDefault(); return;
  }
  if (e.key === 'F12' || e.key === 'Enter' && e.location === 3 /* numpad */) {
    const _t = activeTab(); if (_t && _t.cart.length) openCheckout(); e.preventDefault(); return;
  }
  if (mod && e.key === 't') { activateTab('pos'); addPosTab(); e.preventDefault(); return; }

  // ── Inventory subnav shortcuts (Ctrl+1…0, Ctrl+B) ────────────────────────
  if (mod && !e.shiftKey) {
    const invMap = {
      '1': 'products', '2': 'suppliers', '3': 'po', '4': 'grn',
      '5': 'cheques',  '6': 'audit',     '7': 'categories', '8': 'units',
      '9': 'discounts','0': 'brands',    'b': 'barcodes',
    };
    const view = invMap[e.key];
    if (view) { activateTab('inventory'); switchInvView(view); e.preventDefault(); return; }
  }

  if (mod && e.shiftKey && (e.key === 'w' || e.key === 'W')) {
    closePosTab(state.activePosTabId); e.preventDefault(); return;
  }
  if (mod && (e.key === 'z' || e.key === 'Z') && !e.shiftKey) {
    undoLastCartItem(); e.preventDefault(); return;
  }
  if (mod && e.key === 'F1') { toggleRibbonCollapse(); e.preventDefault(); return; }

  const inInput = ['INPUT','TEXTAREA','SELECT'].includes(document.activeElement?.tagName);

  // ── Cart row keyboard navigation ──────────────────────────────────────────
  if (_posIsActive() && _noModalOpen() && !inInput) {
    if (e.key === 'ArrowDown' && _ciSelIdx >= 0) {
      e.preventDefault(); _ciSelect(_ciSelIdx + 1); return;
    }
    if (e.key === 'ArrowUp' && _ciSelIdx >= 0) {
      e.preventDefault();
      if (_ciSelIdx === 0) { _ciClear(); } else { _ciSelect(_ciSelIdx - 1); }
      return;
    }
    if (e.key === 'Escape' && _ciSelIdx >= 0) {
      e.preventDefault(); _ciClear(); return;
    }
    if (e.key === 'Delete' && _ciSelIdx >= 0) {
      e.preventDefault();
      const tab = activeTab(); if (!tab) return;
      const key = _ciSelKey;
      const newIdx = Math.max(0, _ciSelIdx - 1);
      tab.cart = tab.cart.filter(i => i._key !== key);
      renderCart();
      if (tab.cart.length > 0) { _ciSelect(newIdx); } else { _ciClear(); }
      return;
    }
    if ((e.key === '+' || e.key === '=' || e.key === 'Add') && _ciSelIdx >= 0) {
      e.preventDefault();
      const tab = activeTab(); if (!tab) return;
      const item = tab.cart.find(i => i._key === _ciSelKey);
      if (item) { item.qty += 1; renderCart(); renderPosTabBar(); }
      return;
    }
    if ((e.key === '-' || e.key === 'Subtract') && _ciSelIdx >= 0) {
      e.preventDefault();
      const tab = activeTab(); if (!tab) return;
      const item = tab.cart.find(i => i._key === _ciSelKey);
      if (item) { item.qty = Math.max(1, item.qty - 1); renderCart(); renderPosTabBar(); }
      return;
    }
    if ((e.key === 'q' || e.key === 'Q') && _ciSelIdx >= 0) {
      e.preventDefault();
      const input = $(`#cart-items .ci-qty-input[data-key="${CSS.escape(_ciSelKey)}"]`);
      if (input) { input.focus(); input.select(); }
      return;
    }
    if ((e.key === 'd' || e.key === 'D') && _ciSelIdx >= 0) {
      e.preventDefault(); openDiscountModal(); return;
    }
    if ((e.key === 'm' || e.key === 'M') && _ciSelIdx >= 0) {
      e.preventDefault(); openNoteModal(); return;
    }
  }

  // ── Product grid arrow-key navigation ────────────────────────────────────
  if (_posIsActive() && _noModalOpen() && !inInput) {
    const arrowKeys = ['ArrowLeft','ArrowRight','ArrowUp','ArrowDown'];
    if (arrowKeys.includes(e.key)) {
      e.preventDefault();
      const cards = _pgCards();
      if (!cards.length) return;
      const cols = _pgColumns();
      if (_pgSelIdx < 0) { _pgSelect(0); return; }
      if (e.key === 'ArrowRight') _pgSelect(_pgSelIdx + 1);
      if (e.key === 'ArrowLeft')  _pgSelect(_pgSelIdx - 1);
      if (e.key === 'ArrowDown')  _pgSelect(_pgSelIdx + cols);
      if (e.key === 'ArrowUp')    _pgSelect(_pgSelIdx - cols);
    }
    if (e.key === 'Enter' && _pgSelIdx >= 0) {
      e.preventDefault();
      const cards = _pgCards();
      const p = state.products.find(pr => pr.id === Number(cards[_pgSelIdx]?.dataset.id));
      if (p) handleProductClick(p);
    }
    if (e.key === 'Escape' && _pgSelIdx >= 0) {
      _pgClear(); e.preventDefault();
    }
  }
});

// ── POS Session Tabs ───────────────────────────────────────────────────────
function activeTab() {
  return state.posTabs.find(t => t.id === state.activePosTabId) || state.posTabs[0];
}

function addPosTab() {
  const id = state._nextPosTabId++;
  state.posTabs.push({ id, name: 'Session ' + id, cart: [] });
  state.activePosTabId = id;
  renderPosTabBar();
  renderCart();
}

function switchPosTab(id) {
  state.activePosTabId = id;
  renderPosTabBar();
  renderCart();
}

function closePosTab(id) {
  const tab = state.posTabs.find(t => t.id === id);
  if (!tab) return;
  const itemCount = tab.cart.reduce((s, i) => s + i.qty, 0);
  if (itemCount > 0 && !confirm(`Close "${tab.name}"? The ${itemCount} item(s) in the cart will be lost.`)) return;
  state.posTabs = state.posTabs.filter(t => t.id !== id);
  if (!state.posTabs.length) { addPosTab(); return; }
  if (state.activePosTabId === id) {
    state.activePosTabId = state.posTabs[state.posTabs.length - 1].id;
  }
  renderPosTabBar();
  renderCart();
}

function renderPosTabBar() {
  const container = $('#pos-tabs');
  if (!container) return;
  container.innerHTML = state.posTabs.map(tab => {
    const isActive = tab.id === state.activePosTabId;
    const count    = tab.cart.reduce((s, i) => s + i.qty, 0);
    return `<div class="pos-tab${isActive ? ' active' : ''}" data-id="${tab.id}">
      <i class="fa fa-cart-shopping pos-tab-icon"></i>
      <span class="pos-tab-name">${escHtml(tab.name)}</span>
      ${count > 0 ? `<span class="pos-tab-count">${count}</span>` : ''}
      <button class="pos-tab-close" data-close="${tab.id}" title="Close session"><i class="fa fa-xmark"></i></button>
    </div>`;
  }).join('');
  container.querySelectorAll('.pos-tab').forEach(el => {
    const id = Number(el.dataset.id);
    el.addEventListener('click', e => { if (!e.target.closest('.pos-tab-close')) switchPosTab(id); });
    el.addEventListener('dblclick', e => {
      if (e.target.closest('.pos-tab-close')) return;
      const t = state.posTabs.find(t => t.id === id);
      if (!t) return;
      const name = prompt('Rename session:', t.name);
      if (name && name.trim()) { t.name = name.trim(); renderPosTabBar(); }
    });
  });
  container.querySelectorAll('.pos-tab-close').forEach(btn => {
    btn.addEventListener('click', e => { e.stopPropagation(); closePosTab(Number(btn.dataset.close)); });
  });
  const nameEl = $('#cart-session-name');
  const tab = activeTab();
  if (nameEl && tab) nameEl.textContent = tab.name;
}

$('#pos-tab-add').addEventListener('click', () => addPosTab());

// ── Stock layer picker ─────────────────────────────────────────────────────
let _layerPickerResolve = null;

function _fmtQty(n) { return String((parseFloat(n) || 0).toFixed(3)).replace(/\.?0+$/, ''); }

function posLayersDifferInPrice(layers) {
  if (!layers || layers.length < 2) return false;
  const first = Number(layers[0].unit_sell_price || 0).toFixed(2);
  for (let i = 1; i < layers.length; i++) {
    if (Number(layers[i].unit_sell_price || 0).toFixed(2) !== first) return true;
  }
  return false;
}

function openPosLayerPicker() {
  const m = $('#pos-layer-picker');
  if (m) { m.classList.add('is-open'); m.setAttribute('aria-hidden', 'false'); }
  // Auto-highlight first option
  requestAnimationFrame(() => _lpHighlight(0));
}

function closePosLayerPicker() {
  _lpClear();
  const m = $('#pos-layer-picker');
  if (m) { m.classList.remove('is-open'); m.setAttribute('aria-hidden', 'true'); }
  if (_layerPickerResolve) { const r = _layerPickerResolve; _layerPickerResolve = null; r(null); }
}

$('#pos-layer-picker-cancel').addEventListener('click', closePosLayerPicker);
$('#pos-layer-picker-backdrop').addEventListener('click', closePosLayerPicker);

let _lpSelIdx = -1;

function _lpOptions() {
  return [...$$('#pos-layer-picker-list .pos-layer-picker__option')];
}

function _lpHighlight(idx) {
  const opts = _lpOptions();
  if (!opts.length) return;
  _lpSelIdx = Math.max(0, Math.min(opts.length - 1, idx));
  opts.forEach((o, i) => o.classList.toggle('kbd-active', i === _lpSelIdx));
  opts[_lpSelIdx].scrollIntoView({ block: 'nearest', behavior: 'smooth' });
}

function _lpClear() {
  _lpSelIdx = -1;
  _lpOptions().forEach(o => o.classList.remove('kbd-active'));
}

document.addEventListener('keydown', e => {
  if (!$('#pos-layer-picker')?.classList.contains('is-open')) return;

  if (e.key === 'Escape')     { e.preventDefault(); closePosLayerPicker(); return; }
  if (e.key === 'ArrowDown')  { e.preventDefault(); _lpHighlight(_lpSelIdx < 0 ? 0 : _lpSelIdx + 1); return; }
  if (e.key === 'ArrowUp')    { e.preventDefault(); _lpHighlight(_lpSelIdx < 0 ? 0 : _lpSelIdx - 1); return; }
  if (e.key === 'Enter' && _lpSelIdx >= 0) {
    e.preventDefault();
    _lpOptions()[_lpSelIdx]?.click();
  }
});

function posPickStockLayer(product, layers) {
  return new Promise(resolve => {
    const subtitleEl = $('#pos-layer-picker-subtitle');
    const listEl     = $('#pos-layer-picker-list');
    if (!listEl || !layers?.length) { resolve(null); return; }

    // Group same-price batches into one row
    const priceMap = {};
    layers.forEach(l => {
      const key = Number(l.unit_sell_price || 0).toFixed(2);
      (priceMap[key] = priceMap[key] || []).push(l);
    });
    const opts = Object.values(priceMap).map(group => {
      if (group.length === 1) return { layer: group[0], merged: false };
      const totalStock = group.reduce((s, l) => s + (parseFloat(l.quantity_remaining) || 0), 0);
      return {
        layer: { ...group[0], quantity_remaining: totalStock },
        merged: true,
        mergedLabel: `${group[0].label || 'Batch #' + group[0].id} + ${group.length - 1} more batch${group.length > 2 ? 'es' : ''}`,
        batchCount: group.length,
        totalStock,
      };
    });

    // Auto-pick if only one price option remains
    if (opts.length === 1) { resolve(opts[0].layer); return; }

    _layerPickerResolve = resolve;
    if (subtitleEl) subtitleEl.textContent = `${product.name} — choose a batch to add to the cart.`;

    listEl.innerHTML = '';
    opts.forEach(opt => {
      const layer = opt.layer;
      const btn = document.createElement('button');
      btn.type = 'button';
      btn.className = 'pos-layer-picker__option';
      const metaText = opt.merged
        ? `${_fmtQty(opt.totalStock)} total in stock (${opt.batchCount} batches)`
        : `${_fmtQty(layer.quantity_remaining)} in stock`;
      btn.innerHTML = `
        <span class="pos-layer-picker__option__main">
          <span class="pos-layer-picker__option__label">${escHtml(opt.merged ? opt.mergedLabel : (layer.label || 'Batch #' + layer.id))}</span>
          <span class="pos-layer-picker__option__meta">${escHtml(metaText)}</span>
        </span>
        <span class="pos-layer-picker__option__price">${parseFloat(layer.unit_sell_price || 0).toFixed(2)}</span>`;
      btn.addEventListener('click', () => {
        _layerPickerResolve = null;
        closePosLayerPicker();
        resolve(layer);
      });
      listEl.appendChild(btn);
    });

    openPosLayerPicker();
  });
}

async function handleProductClick(p) {
  const layers = p.layers || [];
  const inStock = layers.filter(l => parseFloat(l.quantity_remaining) > 0);

  if (layers.length > 1 && posLayersDifferInPrice(layers)) {
    // Show picker — filter to in-stock layers only; if none, show all (server enforces stock)
    const pickable = inStock.length > 0 ? inStock : layers;
    const layer = await posPickStockLayer(p, pickable);
    if (!layer) return;
    addToCart({
      id: p.id, layerId: layer.id, layerLabel: layer.label || `Batch #${layer.id}`,
      name: p.name, price: parseFloat(layer.unit_sell_price),
      stock: parseFloat(layer.quantity_remaining),
    });
  } else if (inStock.length > 0) {
    // Pick first in-stock layer automatically
    const layer = inStock[0];
    addToCart({
      id: p.id, layerId: layer.id, layerLabel: layer.label || `Batch #${layer.id}`,
      name: p.name, price: parseFloat(layer.unit_sell_price),
      stock: parseFloat(layer.quantity_remaining),
    });
  } else if (layers.length >= 1) {
    // All layers depleted — send no layerId so server uses FIFO (or surfaces a real stock error)
    const layer = layers[0];
    addToCart({
      id: p.id, layerId: null, layerLabel: null,
      name: p.name, price: parseFloat(layer.unit_sell_price),
      stock: 0,
    });
  } else {
    addToCart({
      id: p.id, layerId: null, layerLabel: null,
      name: p.name, price: parseFloat(p.discounted_sell_price ?? p.unit_sell_price ?? 0),
      stock: p.stock_quantity != null ? parseFloat(p.stock_quantity) : null,
    });
  }
}

// ── Cart ───────────────────────────────────────────────────────────────────
const _beep = new Audio('sounds/beep.wav');

function addToCart(product) {
  _beep.currentTime = 0;
  _beep.play().catch(() => {});
  const tab = activeTab();
  if (!tab) return;
  const key = product.layerId != null ? `${product.id}:${product.layerId}` : `${product.id}`;
  const existing = tab.cart.find(i => i._key === key);
  if (existing) {
    existing.qty += 1;
  } else {
    tab.cart.push({ ...product, qty: 1, _key: key, _basePrice: product.price, _discountPct: null, _note: null });
  }
  renderCart();
  renderPosTabBar();
  // Focus qty input of the item just added/updated
  requestAnimationFrame(() => {
    const input = $(`#cart-items .ci-qty-input[data-key="${CSS.escape(key)}"]`);
    if (input) { input.focus(); input.select(); }
  });
}

// Stable reference so innerHTML wipes never lose the node
const _cartEmptyEl = $('#cart-empty');

function renderCart() {
  const tab    = activeTab();
  const cart   = tab ? tab.cart : [];
  const items  = $('#cart-items');
  const footer = $('#cart-footer');
  const empty  = _cartEmptyEl;

  if (!cart.length) {
    empty.style.display = '';
    footer.style.display = 'none';
    items.innerHTML = '';
    items.appendChild(empty);
    return;
  }

  // Detach empty first so innerHTML swap doesn't destroy it
  if (empty.parentNode) empty.parentNode.removeChild(empty);
  empty.style.display = 'none';
  footer.style.display = '';

  items.innerHTML = cart.map((item, idx) => {
    const discountBadge = item._discountPct > 0
      ? `<span class="ci-badge ci-badge--discount"><i class="fa fa-tag"></i> ${item._discountPct}%</span>` : '';
    const noteBadge = item._note
      ? `<span class="ci-badge ci-badge--note"><i class="fa fa-pen-to-square"></i> ${escHtml(item._note)}</span>` : '';
    const extras = (discountBadge || noteBadge)
      ? `<div class="ci-badges">${discountBadge}${noteBadge}</div>` : '';
    return `
    <div class="cart-item" data-key="${escHtml(item._key)}">
      <div class="ci-name">
        ${escHtml(item.name)}
        ${item.layerLabel ? `<div class="ci-layer">${escHtml(item.layerLabel)}</div>` : ''}
        ${extras}
      </div>
      <div class="ci-qty">
        <button class="ci-qty-btn" data-action="dec" data-idx="${idx}">-</button>
        <input class="ci-qty-input" type="number" data-idx="${idx}" value="${item.qty}" min="1" data-key="${escHtml(item._key)}">
        <button class="ci-qty-btn" data-action="inc" data-idx="${idx}">+</button>
      </div>
      <div class="ci-price">${(item.price * item.qty).toFixed(2)}</div>
      <button class="ci-remove" data-action="remove" data-idx="${idx}"><i class="fa fa-xmark"></i></button>
    </div>`;
  }).join('');

  // Click on a cart item row → select it
  items.querySelectorAll('.cart-item').forEach((el, i) => {
    el.addEventListener('click', () => _ciSelect(i));
  });

  // Restore previous selection if the item is still in the cart
  _ciRestoreSelection();

  items.querySelectorAll('.ci-qty-btn, .ci-remove').forEach(btn => {
    btn.addEventListener('click', (e) => { e.stopPropagation(); // don't deselect via row click
      const t = activeTab();
      if (!t) return;
      const idx = Number(btn.dataset.idx);
      if (btn.dataset.action === 'inc')    { t.cart[idx].qty += 1; }
      if (btn.dataset.action === 'dec')    { t.cart[idx].qty = Math.max(1, t.cart[idx].qty - 1); }
      if (btn.dataset.action === 'remove') { t.cart.splice(idx, 1); renderCart(); renderPosTabBar(); return; }
      renderCart();
      renderPosTabBar();
    });
  });

  items.querySelectorAll('.ci-qty-input').forEach(input => {
    input.addEventListener('click', e => e.stopPropagation());
    input.addEventListener('change', () => {
      const t = activeTab();
      if (!t) return;
      const idx = Number(input.dataset.idx);
      const val = parseInt(input.value) || 1;
      t.cart[idx].qty = Math.max(1, val);
      renderCart();
      renderPosTabBar();
    });
    input.addEventListener('keydown', e => {
      if (e.key === 'Enter') { e.preventDefault(); $('#product-search').focus(); }
      if (e.key === 'Escape') { e.preventDefault(); $('#product-search').focus(); }
    });
    // select all on focus for quick overwrite
    input.addEventListener('focus', () => input.select());
  });

  const baseTotal    = cart.reduce((s, i) => s + (i._basePrice ?? i.price) * i.qty, 0);
  const discountAmt  = cart.reduce((s, i) => s + ((i._basePrice ?? i.price) - i.price) * i.qty, 0);
  const subtotal     = baseTotal - discountAmt;
  $('#cart-subtotal').textContent = baseTotal.toFixed(2);
  $('#cart-discount').textContent = discountAmt > 0 ? `-${discountAmt.toFixed(2)}` : '0.00';
  $('#cart-total').textContent = subtotal.toFixed(2);
}

function clearCart() {
  const tab = activeTab();
  if (tab) tab.cart = [];
  renderCart();
  renderPosTabBar();
  toast('Cart cleared', 'info');
}

$('#rb-clear-cart').addEventListener('click', clearCart);
$('#checkout-btn').addEventListener('click', openCheckout);
$('#rb-checkout').addEventListener('click', () => { const t = activeTab(); if (t && t.cart.length) openCheckout(); });
$('#btn-park')?.addEventListener('click', parkCurrentSale);
$('#btn-recall')?.addEventListener('click', openParkedSalesModal);
$('#btn-customer')?.addEventListener('click', openCustomerModal);

// ── Checkout ───────────────────────────────────────────────────────────────
let _coSubtotal = 0; // base subtotal before discount
let _coAmount   = ''; // numpad string

function _coGetTotal() {
  const pct = Math.min(100, Math.max(0, parseFloat($('#co-discount-pct')?.value) || 0));
  return Math.max(0, Math.round(_coSubtotal * (1 - pct / 100) * 100) / 100);
}

function _coGetMethod() {
  return $$('.co-pay-method.active')[0]?.dataset.method || 'cash';
}

function _coRefresh() {
  const pct      = Math.min(100, Math.max(0, parseFloat($('#co-discount-pct')?.value) || 0));
  const total    = _coGetTotal();
  const saved    = Math.round((_coSubtotal - total) * 100) / 100;
  const cur      = state.currency ? ' ' + state.currency : '';
  const amount   = parseFloat(_coAmount) || 0;
  const change   = Math.round((amount - total) * 100) / 100;

  if ($('#co-subtotal')) $('#co-subtotal').textContent = _coSubtotal.toFixed(2) + cur;
  if ($('#co-saved'))    $('#co-saved').textContent    = saved > 0 ? saved.toFixed(2) + cur : '—';
  if ($('#co-total'))    $('#co-total').textContent    = total.toFixed(2);
  if ($('#co-currency')) $('#co-currency').textContent = state.currency || '';
  if ($('#co-amount'))   $('#co-amount').textContent   = _coAmount || '0.00';
  if ($('#co-amount-due')) $('#co-amount-due').textContent = total.toFixed(2) + cur;
  const changeEl = $('#co-change');
  if (changeEl) {
    changeEl.textContent  = change.toFixed(2) + cur;
    changeEl.className    = change >= 0 ? 'co-change-pos' : 'co-change-neg';
  }
}

function _coNumpadKey(key) {
  if (key === 'exact') {
    _coAmount = _coGetTotal().toFixed(2);
  } else if (key === 'clear') {
    _coAmount = '';
  } else if (key === 'back') {
    _coAmount = _coAmount.slice(0, -1);
  } else if (key === '.') {
    if (!_coAmount.includes('.')) _coAmount += '.';
  } else {
    if (_coAmount === '0' || _coAmount === '') _coAmount = key;
    else _coAmount += key;
    // Max 2 decimal places
    const parts = _coAmount.split('.');
    if (parts[1] && parts[1].length > 2) _coAmount = parts[0] + '.' + parts[1].slice(0, 2);
  }
  _coRefresh();
}

function openCheckout() {
  const tab = activeTab();
  if (!tab || !tab.cart.length) { toast('Cart is empty', 'error'); return; }
  _coSubtotal = tab.cart.reduce((s, i) => s + i.price * i.qty, 0);
  _coAmount   = _coSubtotal.toFixed(2);

  // Reset discount
  const discEl = $('#co-discount-pct');
  if (discEl) discEl.value = '0';

  // Reset payment method to cash
  $$('.co-pay-method').forEach(b => b.classList.remove('active'));
  $$('.co-pay-method')[0]?.classList.add('active');

  // Reset notes & alert
  const notesEl = $('#co-notes');
  if (notesEl) notesEl.value = '';
  $('#checkout-alert').style.display = 'none';

  _coRefresh();
  $('#checkout-modal').style.display = 'flex';
}

$('#checkout-cancel').addEventListener('click', () => { $('#checkout-modal').style.display = 'none'; });

// Payment method tabs
$$('.co-pay-method').forEach(btn => {
  btn.addEventListener('click', () => {
    $$('.co-pay-method').forEach(b => b.classList.remove('active'));
    btn.classList.add('active');
  });
});

// Discount input
$('#co-discount-pct').addEventListener('input', _coRefresh);

// Numpad
$$('.co-key').forEach(btn => {
  btn.addEventListener('click', () => _coNumpadKey(btn.dataset.key));
});

$('#checkout-confirm').addEventListener('click', async () => {
  const method = _coGetMethod();
  const amount = parseFloat(_coAmount) || 0;
  const notes  = $('#co-notes')?.value || '';
  const alertEl = $('#checkout-alert');
  const btn    = $('#checkout-confirm');
  const tab    = activeTab();
  const cart   = tab ? tab.cart : [];
  const total  = _coGetTotal();

  if (method !== 'credit' && amount < total) {
    showAlert(alertEl, `Amount given (${amount.toFixed(2)}) is less than total (${total.toFixed(2)})`);
    return;
  }

  btn.disabled = true;
  btn.innerHTML = '<i class="fa fa-spinner fa-spin"></i> Processing…';
  alertEl.style.display = 'none';

  const discountPct = Math.min(100, Math.max(0, parseFloat($('#co-discount-pct')?.value) || 0));
  const body = {
    payment_method: method,
    amount_paid:     method === 'credit' ? total : amount,
    amount_tendered: method === 'cash'   ? amount : undefined,
    notes,
    discount_percent: discountPct > 0 ? discountPct : undefined,
    pos_customer_id: tab?._customer?.id ?? undefined,
    items: cart.map(i => ({ product_id: i.id, quantity: i.qty, product_stock_layer_id: i.layerId ?? undefined })),
  };

  const res = await API.checkout(body);
  btn.disabled = false;
  btn.innerHTML = '<i class="fa fa-check"></i> Complete sale';

  if (res.status !== 200 && res.status !== 201) {
    showAlert(alertEl, res.body?.message || 'Checkout failed');
    return;
  }

  $('#checkout-modal').style.display = 'none';
  if (tab) { tab.cart = []; tab._customer = null; }
  renderCart();
  renderPosTabBar();
  renderCartCustomer();
  loadProducts(state.searchQuery, state.activeCategory);

  const sale = res.body?.data;
  if (sale) showReceiptModal(sale);
});

// ── Ribbon action wiring ───────────────────────────────────────────────────
$('#rb-new-session').addEventListener('click', () => {
  activateTab('pos');
  addPosTab();
  toast('New session added', 'success');
});
$('#rb-close-session').addEventListener('click', () => {
  const tab = activeTab();
  if (tab) closePosTab(tab.id);
});
$('#rb-barcode').addEventListener('click', () => {
  const sku = prompt('Enter barcode / SKU:');
  if (!sku) return;
  API.productBySku(sku).then(res => {
    if (res.status !== 200) { toast('Product not found', 'error'); return; }
    const p = res.body?.data || res.body;
    addToCart({ id: p.id, name: p.name, price: parseFloat(p.discounted_sell_price ?? p.unit_sell_price ?? p.price ?? 0), stock: p.stock_quantity ?? null });
  });
});
// ── Quick Add Product ──────────────────────────────────────────────────────
function openAddProductModal() {
  // Reset form
  $('#qap-name').value  = '';
  $('#qap-sku').value   = '';
  $('#qap-price').value = '';
  $('#qap-stock').value = '';
  $('#qap-alert').style.display = 'none';

  // Currency prefix
  $('#qap-currency-prefix').textContent = state.currency || '';

  // Populate unit dropdown
  const unitSel = $('#qap-unit');
  unitSel.innerHTML = '<option value="">— None —</option>' +
    (state.productUnits || []).map(u => `<option value="${u.id}">${escHtml(u.name || u.label || String(u.id))}</option>`).join('');

  // Save button reset
  const btn = $('#qap-save');
  btn.disabled = false;
  btn.innerHTML = '<i class="fa fa-plus"></i> Add Product';

  $('#qap-modal').style.display = 'flex';
  setTimeout(() => $('#qap-name').focus(), 80);
}

async function submitAddProduct() {
  const name  = $('#qap-name').value.trim();
  const price = parseFloat($('#qap-price').value);
  const alertEl = $('#qap-alert');

  alertEl.style.display = 'none';

  if (!name) {
    alertEl.textContent = 'Product name is required.';
    alertEl.style.display = 'block';
    $('#qap-name').focus();
    return;
  }
  if (isNaN(price) || price < 0) {
    alertEl.textContent = 'Enter a valid selling price.';
    alertEl.style.display = 'block';
    $('#qap-price').focus();
    return;
  }

  const btn = $('#qap-save');
  btn.disabled = true;
  btn.innerHTML = '<i class="fa fa-spinner fa-spin"></i> Adding…';

  const body = {
    name,
    unit_price:     price,
    sku:            $('#qap-sku').value.trim() || undefined,
    stock_quantity: parseFloat($('#qap-stock').value) || 0,
    product_unit_id: $('#qap-unit').value ? parseInt($('#qap-unit').value) : undefined,
  };

  const res = await API.createProduct(body);
  btn.disabled = false;
  btn.innerHTML = '<i class="fa fa-plus"></i> Add Product';

  if (res.status !== 201 && res.status !== 200) {
    const firstError = res.body?.errors ? Object.values(res.body.errors)[0]?.[0] : null;
    alertEl.textContent = firstError || res.body?.message || 'Failed to add product.';
    alertEl.style.display = 'block';
    return;
  }

  $('#qap-modal').style.display = 'none';
  toast(`"${escHtml(name)}" added to catalog`, 'success');
  loadProducts(state.searchQuery, state.activeCategory);
}

$('#qap-close').addEventListener('click',  () => { $('#qap-modal').style.display = 'none'; });
$('#qap-cancel').addEventListener('click', () => { $('#qap-modal').style.display = 'none'; });
$('#qap-modal').addEventListener('click', (e) => {
  if (e.target === e.currentTarget) e.currentTarget.style.display = 'none';
});
$('#qap-save').addEventListener('click', submitAddProduct);
$('#qap-modal').addEventListener('keydown', (e) => {
  if (e.key === 'Enter' && !e.shiftKey) { e.preventDefault(); submitAddProduct(); }
  if (e.key === 'Escape') $('#qap-modal').style.display = 'none';
});

$('#rb-add-product').addEventListener('click', () => { activateTab('pos'); openAddProductModal(); });
$('#rb-customers').addEventListener('click', openCustomersModal);
$('#rb-home-customers').addEventListener('click', openCustomersModal);
$('#rb-accounts').addEventListener('click', () => toast('Accounts panel coming soon', 'info'));
// ── Inventory ribbon buttons ───────────────────────────────────────────────
$('#rb-inv-products')?.addEventListener('click',  () => { activateTab('inventory'); switchInvView('products'); });
$('#rb-inv-categories')?.addEventListener('click',() => { activateTab('inventory'); switchInvView('categories'); });
$('#rb-inv-units')?.addEventListener('click',     () => { activateTab('inventory'); switchInvView('units'); });
$('#rb-inv-audit')?.addEventListener('click',     () => { activateTab('inventory'); switchInvView('audit'); });
$('#rb-inv-brands')?.addEventListener('click',    () => { activateTab('inventory'); switchInvView('brands'); });
$('#rb-inv-discounts')?.addEventListener('click', () => { activateTab('inventory'); switchInvView('discounts'); });
$('#rb-orders')?.addEventListener('click',        () => { activateTab('inventory'); switchInvView('po'); });
$('#rb-inv-grn')?.addEventListener('click',       () => { activateTab('inventory'); switchInvView('grn'); });
$('#rb-inv-cheques')?.addEventListener('click',   () => { activateTab('inventory'); switchInvView('cheques'); });
$('#rb-inv-suppliers')?.addEventListener('click', () => { activateTab('inventory'); switchInvView('suppliers'); });
$('#rb-add-supplier')?.addEventListener('click',  () => { activateTab('inventory'); switchInvView('suppliers'); _supOpenModal(); });
$('#rb-inv-barcodes')?.addEventListener('click',  () => { activateTab('inventory'); switchInvView('barcodes'); });
$('#rb-create-bill').addEventListener('click', () => { activateTab('finance'); switchFinView('bills'); openBillCreateModal(); });
$('#rb-bills-list').addEventListener('click', () => { activateTab('finance'); switchFinView('bills'); });
$('#rb-loans').addEventListener('click',      () => { activateTab('finance'); switchFinView('loans'); });
$('#rb-rentals').addEventListener('click',    () => { activateTab('finance'); switchFinView('rentals'); });
$('#rb-properties').addEventListener('click', () => { activateTab('finance'); switchFinView('properties'); });
$('#rb-return').addEventListener('click', () => openRefundModal());

// ── Finance Panel ──────────────────────────────────────────────────────────
const BILL_CAT = {
  water:       { icon: 'fa-droplet',         color: '#3b82f6', label: 'Water'       },
  electricity: { icon: 'fa-bolt',            color: '#f59e0b', label: 'Electricity' },
  telephone:   { icon: 'fa-phone',           color: '#8b5cf6', label: 'Telephone'   },
  internet:    { icon: 'fa-wifi',            color: '#06b6d4', label: 'Internet'     },
  gas:         { icon: 'fa-fire-flame-curved', color: '#f97316', label: 'Gas'       },
  waste:       { icon: 'fa-trash-can',       color: '#6b7280', label: 'Waste'       },
  other:       { icon: 'fa-tag',             color: '#10b981', label: 'Other'       },
};

const BILL_REC_LABEL = { per_day: 'Per day', per_month: 'Per month', per_year: 'Per year' };

let financeSearchFilter = '';
let _financeAllBills    = [];

async function loadFinance(search) {
  if (search !== undefined) financeSearchFilter = search;
  const area = $('#finance-cards-area');
  area.innerHTML = `<div class="finance-loading"><i class="fa fa-spinner fa-spin" style="font-size:20px"></i><span>Loading bills…</span></div>`;

  const res = await API.bills();
  if (res.status !== 200) {
    area.innerHTML = `<div class="finance-loading" style="color:#e74c3c"><i class="fa fa-circle-exclamation" style="font-size:20px"></i><span>Failed to load bills</span></div>`;
    return;
  }

  _financeAllBills = res.body?.data || [];
  renderBillCards();
}

function renderBillCards() {
  const area = $('#finance-cards-area');
  let bills = _financeAllBills;

  if (financeSearchFilter) {
    const q = financeSearchFilter.toLowerCase();
    bills = bills.filter(b => (b.name || '').toLowerCase().includes(q) ||
      (b.bill_category || '').toLowerCase().includes(q));
  }

  $('#finance-count').textContent = `${bills.length} bill${bills.length !== 1 ? 's' : ''}`;

  if (!bills.length) {
    area.innerHTML = `
      <div class="finance-empty">
        <div class="finance-empty-icon"><i class="fa fa-file-invoice-dollar"></i></div>
        <p>${financeSearchFilter ? 'No matching bills' : 'No bills yet'}</p>
        <span>${financeSearchFilter ? 'Try a different search term' : 'Click "Create Bill" to add your first bill'}</span>
      </div>`;
    return;
  }

  area.innerHTML = bills.map(b => buildBillCard(b)).join('');
}

function billProgress(b) {
  const MS_PER_DAY = 86400000;
  const today = new Date(); today.setHours(0, 0, 0, 0);
  const todayStr = today.toISOString().slice(0, 10);
  const anchor = b.due_date || b.first_installment_due_date;

  // Server-accurate overdue flag
  const isOverdue = ('overdue' in b) ? Boolean(b.overdue) : (anchor && anchor <= todayStr);
  if (isOverdue) return { pct: 100, daysLeft: 0, overdue: true };
  if (!anchor)   return { pct: 0, daysLeft: null, overdue: false };

  // Advance the anchor date through the recurrence until we find the next upcoming due date
  function addCadence(d, type) {
    const r = new Date(d);
    if (type === 'per_year')  { r.setFullYear(r.getFullYear() + 1); }
    else if (type === 'per_day') { r.setDate(r.getDate() + 1); }
    else                      { r.setMonth(r.getMonth() + 1); } // per_month default
    return r;
  }

  if (b.payment_mode === 'recurring') {
    let periodStart = new Date(anchor + 'T00:00:00');
    let periodEnd   = addCadence(periodStart, b.recurring_type);

    // Walk forward until periodEnd is in the future (next due date after today)
    let guard = 0;
    while (periodEnd <= today && guard < 500) {
      periodStart = periodEnd;
      periodEnd   = addCadence(periodStart, b.recurring_type);
      guard++;
    }

    const periodLen = Math.max(1, Math.round((periodEnd - periodStart) / MS_PER_DAY));
    const elapsed   = Math.max(0, Math.round((today - periodStart) / MS_PER_DAY));
    const daysLeft  = Math.max(0, Math.round((periodEnd - today) / MS_PER_DAY));
    const pct       = Math.min(100, Math.round((elapsed / periodLen) * 100));
    return { pct, daysLeft, overdue: false };
  }

  // one_time — days remaining until due date, 30-day urgency window
  const due      = new Date(anchor + 'T00:00:00');
  const daysLeft = Math.max(0, Math.round((due - today) / MS_PER_DAY));
  const WINDOW   = 30;
  const pct      = Math.max(0, Math.min(100, Math.round(((WINDOW - daysLeft) / WINDOW) * 100)));
  return { pct, daysLeft, overdue: false };
}

function buildBillCard(b) {
  const cat      = BILL_CAT[b.bill_category] || BILL_CAT.other;
  const catLabel = b.bill_category === 'other' ? (b.bill_category_other || 'Other') : cat.label;
  const color    = cat.color;

  // Date helper
  const fmtDate = d => {
    if (!d) return null;
    const dt = new Date(d + 'T00:00:00');
    return dt.toLocaleDateString(undefined, { month: 'short', day: 'numeric', year: 'numeric' });
  };

  // Overdue / fully-paid — server flags are authoritative
  const today        = new Date().toISOString().slice(0, 10);
  const dueDate      = b.due_date || b.first_installment_due_date;
  const isOverdue    = ('overdue' in b) ? Boolean(b.overdue) : (dueDate && dueDate <= today);
  const isFullyPaid  = Boolean(b.is_fully_paid);

  // Mode pill
  const modeHtml = b.payment_mode === 'one_time'
    ? `<span class="bc-pill bc-pill-mode-once"><i class="fa fa-calendar-check"></i> One-time</span>`
    : `<span class="bc-pill bc-pill-mode-rec"><i class="fa fa-rotate"></i> ${BILL_REC_LABEL[b.recurring_type] || 'Recurring'}</span>`;

  // Status dot
  const statusHtml = isFullyPaid
    ? `<span class="bill-status-dot paid"><i class="fa fa-circle-check" style="font-size:10px"></i> Paid</span>`
    : isOverdue
      ? `<span class="bill-status-dot overdue"><i class="fa fa-circle-exclamation" style="font-size:10px"></i> Overdue</span>`
      : `<span class="bill-status-dot active">Active</span>`;

  // Description
  const descHtml = b.description
    ? `<div class="bill-card-desc">${escHtml(b.description.slice(0, 90))}${b.description.length > 90 ? '…' : ''}</div>`
    : '';

  // Detail chips
  const chips = [];
  if (b.payment_mode === 'recurring') {
    const cLabel = { per_day: 'Daily', per_month: 'Monthly', per_year: 'Yearly' };
    chips.push(`<span class="bc-detail"><i class="fa fa-rotate"></i> ${cLabel[b.recurring_type] || 'Recurring'}</span>`);
    if (b.agreement_valid_until_year)
      chips.push(`<span class="bc-detail"><i class="fa fa-calendar"></i> Until ${b.agreement_valid_until_year}</span>`);
  }
  if (b.assignment_type && b.assignment_type !== 'none') {
    const aLabel = b.assignment_type.charAt(0).toUpperCase() + b.assignment_type.slice(1);
    const aName  = b.assignable?.name || b.branch?.name || b.department?.name || b.employee?.name || null;
    chips.push(`<span class="bc-detail"><i class="fa fa-sitemap"></i> ${escHtml(aLabel)}${aName ? ': ' + escHtml(aName) : ''}</span>`);
  }
  const acctName = b.account?.name || b.account?.account_name || null;
  if (acctName)
    chips.push(`<span class="bc-detail"><i class="fa fa-building-columns"></i> ${escHtml(acctName)}</span>`);
  if (b.remind_before_days && !isFullyPaid)
    chips.push(`<span class="bc-detail"><i class="fa fa-bell"></i> Remind ${b.remind_before_days}d</span>`);
  if (b.allow_split_payment)
    chips.push(`<span class="bc-detail"><i class="fa fa-code-branch"></i> Split payments</span>`);
  if (b.notes)
    chips.push(`<span class="bc-detail bc-detail-notes"><i class="fa fa-note-sticky"></i> ${escHtml(b.notes.slice(0, 45))}${b.notes.length > 45 ? '…' : ''}</span>`);

  const sep = '<span class="bc-detail-sep">·</span>';
  const detailsHtml = chips.length
    ? `<div class="bill-card-details">${statusHtml}${sep}${chips.join(sep)}</div>`
    : `<div class="bill-card-details">${statusHtml}</div>`;

  // Progress bar — hide for fully-paid bills
  const prog = billProgress(b);
  let progressHtml = '';
  if (prog && !isFullyPaid) {
    const pct    = prog.pct;
    const barCls = prog.overdue       ? 'bp-overdue'
                 : pct >= 85          ? 'bp-urgent'
                 : pct >= 60          ? 'bp-warn'
                 :                      'bp-ok';
    const daysLeftLabel = prog.overdue
      ? `<span class="bp-label bp-label-overdue"><i class="fa fa-circle-exclamation"></i> Overdue</span>`
      : prog.daysLeft === 0
        ? `<span class="bp-label bp-label-urgent">Due today</span>`
        : pct >= 60
          ? `<span class="bp-label bp-label-${pct >= 85 ? 'urgent' : 'warn'}">${prog.daysLeft}d left</span>`
          : `<span class="bp-label bp-label-ok">${prog.daysLeft}d left</span>`;
    progressHtml = `
      <div class="bill-progress-wrap">
        <div class="bill-progress-bar ${barCls}" style="width:${pct}%"></div>
        ${daysLeftLabel}
      </div>`;
  }

  // Date stat
  const dateLabel = b.payment_mode === 'recurring' ? 'Next due' : 'Due date';
  const dateVal   = fmtDate(dueDate) || '—';

  // Amount stat
  const amtLabel = b.amount_varies_by_usage ? 'Amount' : 'Per cycle';
  const amtHtml  = b.amount_varies_by_usage
    ? `<span class="bill-stat-val varies-val">Varies by usage</span>`
    : `<span class="bill-stat-val">${b.recurring_cost != null ? parseFloat(b.recurring_cost).toFixed(2) : '—'}</span>`;

  return `
    <div class="bill-card${isOverdue ? ' bill-card-overdue' : ''}" style="--bill-color:${color}" data-bill-id="${b.id}">
      <div class="bill-card-ribbon"></div>
      <div class="bill-card-icon"><i class="fa ${cat.icon}"></i></div>
      <div class="bill-card-main">
        <div class="bill-card-title-row">
          <span class="bill-card-name">${escHtml(b.name)}</span>
          <span class="bc-pill bc-pill-cat" style="--bill-color:${color}"><i class="fa ${cat.icon}"></i> ${escHtml(catLabel)}</span>
          ${modeHtml}
        </div>
        ${descHtml}
        ${detailsHtml}
        ${progressHtml}
      </div>
      <div class="bill-stat${isOverdue ? ' bill-stat--overdue' : ''}">
        ${isOverdue ? '<span class="bill-stat-overdue-badge"><i class="fa fa-circle-exclamation"></i> OVERDUE</span>' : ''}
        <span class="bill-stat-label">${dateLabel}</span>
        <span class="bill-stat-val">${dateVal}</span>
      </div>
      <div class="bill-stat">
        <span class="bill-stat-label">${amtLabel}</span>
        ${amtHtml}
      </div>
      <button class="bill-delete-btn" data-bill-id="${b.id}" title="Delete bill">
        <i class="fa fa-trash"></i>
      </button>
    </div>`;
}

let financeSearchTimer;
$('#finance-search').addEventListener('input', e => {
  clearTimeout(financeSearchTimer);
  financeSearchFilter = e.target.value;
  financeSearchTimer = setTimeout(renderBillCards, 250);
});

// Delete bill via event delegation on the cards area
$('#finance-cards-area').addEventListener('click', async e => {
  // Delete button takes priority
  const btn = e.target.closest('.bill-delete-btn');
  if (!btn) {
    // Card body click → open detail page
    const card = e.target.closest('.bill-card');
    if (card && card.dataset.billId) {
      const b = _financeAllBills.find(x => String(x.id) === String(card.dataset.billId));
      if (b) openBillDetailPage(b);
    }
    return;
  }
  const id   = btn.dataset.billId;
  const card = btn.closest('.bill-card');
  const name = card?.querySelector('.bill-card-name')?.textContent || 'this bill';

  if (!confirm(`Delete "${name}"? This cannot be undone.`)) return;

  btn.disabled = true;
  btn.innerHTML = '<i class="fa fa-spinner fa-spin"></i>';

  const res = await API.deleteBill(id);
  if (res.status === 200) {
    card.style.transition = 'opacity .2s, transform .2s';
    card.style.opacity = '0';
    card.style.transform = 'translateX(12px)';
    setTimeout(() => {
      _financeAllBills = _financeAllBills.filter(b => String(b.id) !== String(id));
      renderBillCards();
    }, 200);
    toast('Bill deleted', 'success');
  } else {
    btn.disabled = false;
    btn.innerHTML = '<i class="fa fa-trash"></i>';
    const msg = res.body?.message || `Failed to delete (${res.status})`;
    toast(msg, 'error');
  }
});

$('#btn-finance-create-bill').addEventListener('click', openBillCreateModal);

// ── Bill Create Modal ──────────────────────────────────────────────────────
const billData = { targets: null, accounts: null };

async function openBillCreateModal() {
  // Reset every field
  $('#bill-name').value       = '';
  $('#bill-category').value   = '';
  $('#bill-cat-other-wrap').style.display = 'none';
  $('#bill-cat-other').value  = '';
  document.querySelector('input[name="bill-mode"][value="recurring"]').checked = true;
  $('#bill-description').value = '';
  $('#bill-amount').value      = '';
  $('#bill-rec-type').value    = 'per_month';
  $('#bill-valid-year').value  = new Date().getFullYear() + 1;
  $('#bill-due-date').value    = '';
  $('#bill-varies-usage').checked = false;
  $('#bill-allow-split').checked  = true;
  $('#bill-remind-days').value    = '';
  $('#bill-assign-type').value    = 'none';
  $('#bill-assign-target-wrap').style.display = 'none';
  $('#bill-first-installment').value = '';
  $('#bill-notes').value = '';
  $('#bill-modal-alert').style.display = 'none';
  $('#bill-submit').disabled = false;
  $('#bill-submit').innerHTML = '<i class="fa fa-check"></i>&nbsp; Create Bill';

  syncBillModeFields('recurring');

  // Load targets + accounts on each open so property/rental lists stay fresh
  {
    $('#bill-account').innerHTML = '<option value="">Loading…</option>';
    const [targRes, acctRes] = await Promise.all([API.billTargets(), API.accounts()]);
    billData.targets  = targRes.status  === 200 ? (targRes.body?.data  || {}) : {};
    billData.accounts = acctRes.status  === 200 ? (acctRes.body?.data  || []) : [];
    const accts = billData.accounts;
    $('#bill-account').innerHTML = '<option value="">None (manual payment)</option>' +
      accts.map(a => `<option value="${a.id}">${escHtml(a.name || a.account_name || String(a.id))}</option>`).join('');
  }

  $('#bill-modal').style.display = 'flex';
  $('#bill-name').focus();
}

function syncBillModeFields(mode) {
  const rec = mode === 'recurring';
  $('#bill-rec-type-wrap').style.display         = rec ? '' : 'none';
  $('#bill-valid-year-wrap').style.display        = rec ? '' : 'none';
  $('#bill-first-installment-wrap').style.display = rec ? '' : 'none';
}

function syncAssignTarget(type) {
  const wrap = $('#bill-assign-target-wrap');
  if (type === 'none') { wrap.style.display = 'none'; return; }
  wrap.style.display = '';
  const labelMap = { branch: 'Branch', department: 'Department', property: 'Property', employee: 'Employee', modification: 'Modification', rental: 'Rental' };
  const keyMap   = { branch: 'branches', department: 'departments', property: 'properties', employee: 'employees', modification: 'modifications', rental: 'rentals' };
  $('#bill-assign-target-label').textContent = labelMap[type] || 'Select…';
  const items = (billData.targets || {})[keyMap[type] || type + 's'] || [];
  $('#bill-assign-target').innerHTML = items.length
    ? `<option value="">Select ${labelMap[type] || type}…</option>` + items.map(i => `<option value="${i.id}">${escHtml(i.name || String(i.id))}</option>`).join('')
    : `<option value="">No ${(labelMap[type] || type).toLowerCase()}s available</option>`;
}

// Wire mode radio change
document.querySelectorAll('input[name="bill-mode"]').forEach(r =>
  r.addEventListener('change', () => syncBillModeFields(r.value))
);

// Category "other" reveal
$('#bill-category').addEventListener('change', () => {
  $('#bill-cat-other-wrap').style.display = $('#bill-category').value === 'other' ? '' : 'none';
});

// Assignment type change
$('#bill-assign-type').addEventListener('change', () => syncAssignTarget($('#bill-assign-type').value));

// Close create modal
$('#bill-modal-close').addEventListener('click', () => { $('#bill-modal').style.display = 'none'; });
$('#bill-cancel').addEventListener('click',      () => { $('#bill-modal').style.display = 'none'; });
$('#bill-modal').addEventListener('click', e => { if (e.target === $('#bill-modal')) $('#bill-modal').style.display = 'none'; });

// ── Bill detail page ──────────────────────────────────────────────────────
$('#bill-detail-back').addEventListener('click', () => {
  $('#bill-detail-view').style.display  = 'none';
  $('#finance-list-view').style.display = '';
  $('.fin-subnav').style.display        = '';
});

// Tab switching — reuses inv-tab / inv-tab-pane classes
$('#bd-inv-tabs').addEventListener('click', e => {
  const tab = e.target.closest('.inv-tab');
  if (!tab) return;
  $$('#bd-inv-tabs .inv-tab').forEach(t => t.classList.toggle('active', t === tab));
  const key = tab.dataset.tab;
  $$('.inv-tab-pane[id^="bd-pane-"]').forEach(p => p.classList.toggle('active', p.id === `bd-pane-${key}`));
});

function refreshBillDetailOverdue(billId) {
  API.bill(billId).then(res => {
    if (res.status !== 200) return;
    const d = res.body?.data || {};
    const isOverdue   = d.is_overdue === true;
    const isFPaid     = d.is_fully_paid === true;
    const cat = BILL_CAT[d.bill_category] || BILL_CAT.other;

    const badges = [];
    if (isFPaid)   badges.push('<span class="inv-badge inv-badge-green">Paid</span>');
    else if (isOverdue) badges.push('<span class="inv-badge inv-badge-red">Overdue</span>');
    else           badges.push('<span class="inv-badge inv-badge-green">Active</span>');
    if (d.amount_varies_by_usage)        badges.push('<span class="inv-badge inv-badge-amber">Variable amount</span>');
    if (d.payment_mode === 'recurring')  badges.push('<span class="inv-badge inv-badge-blue">Recurring</span>');
    if (d.agreement_valid_until_year)    badges.push(`<span class="inv-badge inv-badge-gray">Through ${d.agreement_valid_until_year}</span>`);
    $('#bd-hero-badges').innerHTML = badges.join('');

    // Remove overdue alert when no longer overdue
    const alertEl = $('#bd-pane-overview .bd-alert');
    if (alertEl && !isOverdue) alertEl.remove();

    // Update local cache
    const idx = _financeAllBills.findIndex(x => String(x.id) === String(billId));
    if (idx !== -1) _financeAllBills[idx] = { ..._financeAllBills[idx], overdue: isOverdue, is_fully_paid: isFPaid };
  });
}

function openBillDetailPage(b) {
  const full     = _financeAllBills.find(x => String(x.id) === String(b.id)) || b;
  const cat      = BILL_CAT[full.bill_category] || BILL_CAT.other;
  const catLabel = full.bill_category === 'other' ? (full.bill_category_other || 'Other') : cat.label;
  const dueDate  = full.due_date || full.first_installment_due_date || null;
  const today    = new Date().toISOString().slice(0, 10);
  // Use cached overdue flag if available; date-compare is a fallback only
  const isOverdue   = ('overdue' in full) ? Boolean(full.overdue) : (dueDate && dueDate <= today);
  const isFullyPaid = Boolean(full.is_fully_paid);
  const modeLabel = full.payment_mode === 'one_time'
    ? 'One-time' : (BILL_REC_LABEL[full.recurring_type] || 'Recurring');

  // Show detail, hide list + subnav
  $('.fin-subnav').style.display        = 'none';
  $('#finance-list-view').style.display = 'none';
  $('#bill-detail-view').style.display  = 'flex';

  // Reset to overview tab
  $$('#bd-inv-tabs .inv-tab').forEach(t => t.classList.toggle('active', t.dataset.tab === 'overview'));
  $$('.inv-tab-pane[id^="bd-pane-"]').forEach(p => p.classList.toggle('active', p.id === 'bd-pane-overview'));

  // ── Hero (same structure as product detail) ────────────────────────────
  $('#bd-breadcrumb').textContent = full.name;

  $('#bd-hero-icon').innerHTML = `
    <div class="inv-thumb-ph inv-thumb-lg" style="background:${cat.color}1a;color:${cat.color};font-size:28px">
      <i class="fa ${cat.icon}"></i>
    </div>`;

  $('#bd-hero-name').textContent = full.name;

  const metaParts = [
    `<span><i class="fa ${cat.icon}"></i> ${escHtml(catLabel)}</span>`,
    `<span><i class="fa fa-rotate"></i> ${modeLabel}</span>`,
  ];
  if (full.assignment_type && full.assignment_type !== 'none' && (full.assignment_name || full.assignment_target_name)) {
    metaParts.push(`<span><i class="fa fa-user"></i> ${escHtml(full.assignment_name || full.assignment_target_name)}</span>`);
  }
  $('#bd-hero-meta').innerHTML = metaParts.join('<span class="inv-sep">·</span>');

  const badges = [];
  if (isFullyPaid) badges.push('<span class="inv-badge inv-badge-green">Paid</span>');
  else if (isOverdue) badges.push('<span class="inv-badge inv-badge-red">Overdue</span>');
  else             badges.push('<span class="inv-badge inv-badge-green">Active</span>');
  if (full.amount_varies_by_usage)
    badges.push('<span class="inv-badge inv-badge-amber">Variable amount</span>');
  if (full.payment_mode === 'recurring')
    badges.push('<span class="inv-badge inv-badge-blue">Recurring</span>');
  if (full.agreement_valid_until_year)
    badges.push(`<span class="inv-badge inv-badge-gray">Through ${full.agreement_valid_until_year}</span>`);
  $('#bd-hero-badges').innerHTML = badges.join('');

  // ── Overview pane ─────────────────────────────────────────────────────
  let html = '';

  // Overdue alert
  if (isOverdue)
    html += `<div class="bd-alert" style="margin-bottom:14px">
      <i class="fa fa-circle-exclamation"></i>
      <div><strong>Unpaid billing due</strong> — A scheduled payment on or before today has no ledger record. Log the payment to keep your schedule accurate.</div>
    </div>`;

  // ── Details section (same pattern as product Details section) ─────────
  const { pct, daysLeft, overdue } = billProgress(full);
  const pfClass  = pct >= 100 ? 'pf-overdue' : pct >= 80 ? 'pf-urgent' : pct >= 50 ? 'pf-warn' : 'pf-ok';

  const amtVal = full.amount_varies_by_usage
    ? '<span style="color:var(--text-muted);font-style:italic">Variable (by usage)</span>'
    : `<strong>${full.recurring_cost != null ? parseFloat(full.recurring_cost).toFixed(2) : '0.00'}</strong>`;

  const dueDateVal = isOverdue
    ? `<span style="color:#dc2626;font-weight:600">${escHtml(dueDate)}</span>&nbsp;<span class="inv-badge inv-badge-red" style="font-size:10px">Overdue</span>`
    : escHtml(dueDate || '—');

  const schedRows = [
    ['Next due',        dueDateVal],
    ['Schedule',        escHtml(modeLabel)],
    ['Amount',          amtVal],
  ];
  if (full.agreement_valid_until_year)
    schedRows.push(['Valid through', escHtml(String(full.agreement_valid_until_year))]);
  if (full.remind_before_days && !isFullyPaid)
    schedRows.push(['Reminder', `${full.remind_before_days} day(s) before due`]);

  const detailRows = [
    ['Category',   `<span class="inv-badge inv-badge-gray">${escHtml(catLabel)}</span>`],
  ];
  if (full.assignment_type && full.assignment_type !== 'none') {
    const who = full.assignment_name || full.assignment_target_name || full.assignment_target_id || '—';
    detailRows.push(['Assigned to', escHtml(String(who))]);
  }
  const acctName = full.account?.name || full.account?.account_name;
  if (acctName) detailRows.push(['Account', escHtml(acctName)]);

  const note = full.notes || full.description;
  if (note) detailRows.push(['Notes', `<span style="white-space:pre-wrap">${escHtml(note)}</span>`]);

  const rowsHtml = rows => rows.map(([l, v]) =>
    `<tr><td class="inv-dt-label">${escHtml(l)}</td><td class="inv-dt-val">${v}</td></tr>`).join('');

  const progressSection = isFullyPaid
    ? `<div style="padding:10px 14px 12px;display:flex;align-items:center;gap:8px;color:#4ade80;font-size:12px;font-weight:600;">
        <i class="fa fa-circle-check"></i> All payments recorded — no pending obligations.
       </div>`
    : `<div style="padding:10px 14px 12px">
        <div style="display:flex;justify-content:space-between;font-size:10px;font-weight:700;letter-spacing:.06em;text-transform:uppercase;color:var(--text-muted);margin-bottom:4px">
          <span>Approaching due date</span><span>${pct}%</span>
        </div>
        <div style="height:5px;border-radius:4px;background:var(--surface3);overflow:hidden">
          <div class="bd-progress-fill ${pfClass}" style="width:${pct}%;height:100%;border-radius:4px;transition:width .4s ease"></div>
        </div>
      </div>`;

  html += `
    <div class="inv-section">
      <div class="inv-section-title"><i class="fa fa-calendar-check"></i> Schedule</div>
      <table class="inv-detail-table">${rowsHtml(schedRows)}</table>
      ${progressSection}
    </div>
    <div class="inv-section" style="margin-top:14px">
      <div class="inv-section-title"><i class="fa fa-circle-info"></i> Details</div>
      <table class="inv-detail-table">${rowsHtml(detailRows)}</table>
    </div>
    <div class="inv-section" style="margin-top:14px">
      <div class="inv-section-title" style="color:#dc2626"><i class="fa fa-trash"></i> Remove bill</div>
      <div style="padding:12px 14px;display:flex;align-items:center;justify-content:space-between;gap:12px">
        <span style="font-size:12px;color:var(--text-muted);line-height:1.5">Deletes this bill and its payment schedule. Existing ledger rows in Transactions are kept for history.</span>
        <button class="bd-danger-btn" id="bd-delete-bill" style="flex-shrink:0">
          <i class="fa fa-trash"></i> Remove
        </button>
      </div>
    </div>`;

  $('#bd-pane-overview').innerHTML = html;

  // Always re-verify overdue status from server (list cache uses date-only comparison)
  refreshBillDetailOverdue(full.id);

  // Load transaction details tab on demand
  let _bdScheduleLoaded = false;
  $$('#bd-inv-tabs .inv-tab').forEach(tab => {
    if (tab.dataset.tab !== 'transactions') return;
    tab.addEventListener('click', () => {
      if (_bdScheduleLoaded) return;
      _bdScheduleLoaded = true;
      loadBillTransactionTab(full.id);
    }, { once: false });
  });
  // Show spinner in tab until loaded
  $('#bd-pane-transactions').innerHTML = `<div class="inv-pane-loading"><i class="fa fa-spinner fa-spin" style="font-size:22px;opacity:.4"></i></div>`;

  $('#bd-delete-bill').addEventListener('click', async () => {
    if (!confirm(`Delete "${full.name}"? This cannot be undone.`)) return;
    const btn = $('#bd-delete-bill');
    btn.disabled = true; btn.innerHTML = '<i class="fa fa-spinner fa-spin"></i>';
    const res = await API.deleteBill(full.id);
    if (res.status === 200) {
      _financeAllBills = _financeAllBills.filter(x => String(x.id) !== String(full.id));
      $('#bill-detail-back').click(); renderBillCards();
      toast('Bill deleted', 'success');
    } else {
      btn.disabled = false; btn.innerHTML = '<i class="fa fa-trash"></i> Remove bill';
      toast(res.body?.message || 'Failed to delete', 'error');
    }
  });
}

async function loadBillTransactionTab(billId) {
  const pane = $('#bd-pane-transactions');
  const res  = await API.bill(billId);
  if (res.status !== 200) {
    pane.innerHTML = `<div class="inv-pane-empty"><i class="fa fa-circle-exclamation"></i><p>Could not load schedule.</p></div>`;
    return;
  }
  const data     = res.body?.data || {};
  const schedule = data.schedule || [];
  const ledger   = data.ledger   || [];

  function statusBadge(row) {
    if (row.paid)
      return `<span class="bd-sched-badge bd-sched-paid"><i class="fa fa-check-circle"></i> Paid</span>`;
    if (row.past_due_unpaid && row.needs_period_charge_declaration)
      return `<span class="bd-sched-badge bd-sched-due-var"><i class="fa fa-circle-exclamation"></i> Due · set invoice total when paying</span>`;
    if (row.past_due_unpaid && row.partially_paid)
      return `<span class="bd-sched-badge bd-sched-partial"><i class="fa fa-circle-half-stroke"></i> Past due · partial</span>`;
    if (row.past_due_unpaid)
      return `<span class="bd-sched-badge bd-sched-overdue"><i class="fa fa-circle-exclamation"></i> Past due · unpaid</span>`;
    if (row.partially_paid)
      return `<span class="bd-sched-badge bd-sched-partial"><i class="fa fa-circle-half-stroke"></i> Partially paid</span>`;
    if (row.needs_period_charge_declaration)
      return `<span class="bd-sched-badge bd-sched-await"><i class="fa fa-circle-info"></i> Awaiting usage / invoice</span>`;
    return `<span class="bd-sched-badge bd-sched-await"><i class="fa fa-circle-info"></i> Outstanding</span>`;
  }

  function amtCell(row) {
    if (row.amount_varies && !row.period_charge_declared)
      return `<span style="color:var(--text-muted);font-style:italic">Varies by usage</span>`;
    return `LKR ${escHtml(row.amount_formatted)}`;
  }

  const schedRows = schedule.map((row, i) => {
    const rowClass  = row.past_due_unpaid && !row.paid ? 'bd-sched-row-due' : '';
    const actionBtn = row.paid
      ? `<span style="color:var(--text-muted);font-size:11px">—</span>`
      : `<button class="bd-pay-btn" data-due="${escHtml(row.due_ymd)}" data-amt="${escHtml(row.amount_formatted)}" data-varies="${row.amount_varies ? '1' : '0'}" data-outstanding="${row.outstanding_formatted}" data-needs-decl="${row.needs_period_charge_declaration ? '1' : '0'}">
           <i class="fa fa-money-bill-wave"></i> Make payment
         </button>`;
    return `<tr class="${rowClass}">
      <td class="bd-sched-num">${i + 1}</td>
      <td class="bd-sched-cell">${escHtml(row.due_ymd)}</td>
      <td class="bd-sched-cell">${amtCell(row)}</td>
      <td class="bd-sched-cell">${statusBadge(row)}</td>
      <td class="bd-sched-cell bd-sched-paid-col">${row.paid_total > 0 ? `LKR ${escHtml(row.paid_total_formatted)}` : '—'}</td>
      <td class="bd-sched-cell">${actionBtn}</td>
    </tr>`;
  }).join('');

  const schedHtml = schedule.length
    ? `<div class="bd-sched-scroll">
        <table class="bd-sched-table">
          <thead><tr>
            <th class="bd-sched-num">#</th>
            <th>Due date</th>
            <th>Billing amount</th>
            <th>Status</th>
            <th>Paid</th>
            <th>Actions</th>
          </tr></thead>
          <tbody>${schedRows}</tbody>
        </table>
       </div>`
    : `<div class="inv-pane-empty" style="padding:30px 14px"><p>No billing dates generated.</p></div>`;

  const ledgerHtml = ledger.length
    ? `<table class="bd-sched-table">
        <thead><tr>
          <th>Date posted</th>
          <th>Billing date</th>
          <th style="text-align:right">Amount</th>
          <th>Account</th>
        </tr></thead>
        <tbody>${ledger.map(tx => `<tr>
          <td class="bd-sched-cell">${escHtml(tx.created_at || '—')}</td>
          <td class="bd-sched-cell">${escHtml(tx.occurrence_date || '—')}</td>
          <td class="bd-sched-cell" style="text-align:right;font-weight:600">LKR ${parseFloat(tx.amount).toFixed(2)}</td>
          <td class="bd-sched-cell">${escHtml(tx.account_name || '—')}${tx.bank_name ? ` <span style="color:var(--text-muted);font-size:11px">· ${escHtml(tx.bank_name)}</span>` : ''}</td>
        </tr>`).join('')}</tbody>
      </table>`
    : `<div class="inv-pane-empty" style="padding:24px 14px"><i class="fa fa-receipt"></i><p>No bill payments in the ledger yet. Record payments from the schedule above.</p></div>`;

  pane.innerHTML = `
    <div class="inv-section">
      <div class="inv-section-title"><i class="fa fa-calendar-days"></i> Billing schedule &amp; payment status</div>
      <p style="font-size:11.5px;color:var(--text-muted);padding:10px 14px 0;line-height:1.5;margin:0">
        Make payment can settle the remaining balance at once (<strong>full</strong>), pay a <strong>partial</strong>, or <strong>split</strong> the charge across multiple debit accounts.
      </p>
      ${schedHtml}
    </div>
    <div class="inv-section" style="margin-top:14px">
      <div class="inv-section-title"><i class="fa fa-receipt"></i> Ledger payments logged</div>
      ${ledgerHtml}
    </div>`;

  // Wire up Make payment buttons
  pane.addEventListener('click', e => {
    const btn = e.target.closest('.bd-pay-btn');
    if (!btn) return;
    openBillPayModal(billId, btn.dataset);
  });
}

// ── Bill pay modal ────────────────────────────────────────────────────────────
let _bpmAccounts = [];

async function openBillPayModal(billId, rowData) {
  const modal = $('#bill-pay-modal');
  const { due, amt, varies, outstanding, needsDecl } = rowData;

  // Populate info blocks
  $('#bpm-date').textContent        = due || '—';
  $('#bpm-scheduled').textContent   = varies === '1' ? 'LKR Varies by usage' : `LKR ${amt}`;
  $('#bpm-outstanding').textContent = outstanding !== '—' && outstanding ? `LKR ${outstanding}` : '(LKR) Enter period total above';

  // Show/hide period charge field
  const chargeWrap = $('#bpm-period-charge-wrap');
  chargeWrap.style.display = (varies === '1' && needsDecl === '1') ? '' : 'none';
  $('#bpm-period-charge').value = '';

  // Reset form state
  document.querySelectorAll('input[name="bpm-option"]').forEach(r => { r.checked = r.value === 'full'; });
  $('#bpm-partial-wrap').style.display  = 'none';
  $('#bpm-account-wrap').style.display  = '';
  $('#bpm-split-wrap').style.display    = 'none';
  $('#bpm-partial-amount').value        = '';
  $('#bpm-error').style.display         = 'none';
  $('#bpm-confirm-btn').disabled        = false;
  $('#bpm-confirm-btn').innerHTML       = '<i class="fa fa-check"></i> Confirm payment';

  modal.style.display = 'flex';

  // Load accounts if not cached
  if (!_bpmAccounts.length) {
    const res = await API.accounts();
    _bpmAccounts = res.status === 200 ? (res.body?.data || res.body || []) : [];
  }
  const acctOpts = _bpmAccounts.length
    ? _bpmAccounts.map(a => {
        const label = [a.account_number || a.number, a.bank?.name || a.bank_name, a.account_type_label || a.account_type, a.balance != null ? `Balance ${parseFloat(a.balance).toFixed(2)}` : ''].filter(Boolean).join(' · ');
        return `<option value="${a.id}">${escHtml(label || a.account_name || String(a.id))}</option>`;
      }).join('')
    : '<option value="">No accounts found</option>';
  $$('#bpm-account, .bpm-split-account').forEach(sel => {
    const cur = sel.value;
    sel.innerHTML = '<option value="">Select account</option>' + acctOpts;
    if (cur) sel.value = cur;
  });

  // Store context on modal
  modal.dataset.billId = billId;
  modal.dataset.due    = due;
}

// Modal close
['#bill-pay-close', '#bpm-cancel-btn'].forEach(id => {
  $(id).addEventListener('click', () => { $('#bill-pay-modal').style.display = 'none'; });
});
$('#bill-pay-modal').addEventListener('click', e => { if (e.target === $('#bill-pay-modal')) $('#bill-pay-modal').style.display = 'none'; });

// Payment option radio → show/hide conditional fields
$('#bill-pay-modal').addEventListener('change', e => {
  const radio = e.target.closest('input[name="bpm-option"]');
  if (!radio) return;
  const opt = radio.value;
  $('#bpm-partial-wrap').style.display = opt === 'partial' ? '' : 'none';
  $('#bpm-account-wrap').style.display = opt === 'split'   ? 'none' : '';
  $('#bpm-split-wrap').style.display   = opt === 'split'   ? '' : 'none';
});

// Confirm payment
$('#bpm-confirm-btn').addEventListener('click', async () => {
  const modal   = $('#bill-pay-modal');
  const billId  = modal.dataset.billId;
  const due     = modal.dataset.due;
  const option  = document.querySelector('input[name="bpm-option"]:checked')?.value || 'full';
  const errEl   = $('#bpm-error');

  errEl.style.display = 'none';

  const body = { occurrence_date: due, payment_option: option };

  const periodCharge = parseFloat($('#bpm-period-charge').value);
  if ($('#bpm-period-charge-wrap').style.display !== 'none' && periodCharge > 0) {
    body.period_charge_total = periodCharge;
  }

  if (option === 'full' || option === 'partial') {
    const acct = parseInt($('#bpm-account').value);
    if (!acct) { errEl.textContent = 'Select a debit account.'; errEl.style.display = ''; return; }
    body.deduct_account_id = acct;
    if (option === 'partial') {
      const partial = parseFloat($('#bpm-partial-amount').value);
      if (!partial || partial <= 0) { errEl.textContent = 'Enter a valid partial amount.'; errEl.style.display = ''; return; }
      body.partial_amount = partial;
    }
  } else {
    const splitAccounts = $$('.bpm-split-account');
    const splitAmounts  = $$('.bpm-split-amount');
    body.split_rows = [...splitAccounts].map((sel, i) => ({
      deduct_account_id: parseInt(sel.value) || 0,
      amount: parseFloat(splitAmounts[i]?.value) || 0,
    }));
    const valid = body.split_rows.filter(r => r.deduct_account_id > 0 && r.amount > 0);
    if (valid.length < 2) { errEl.textContent = 'Enter two accounts and amounts for split payment.'; errEl.style.display = ''; return; }
  }

  const btn = $('#bpm-confirm-btn');
  btn.disabled = true; btn.innerHTML = '<i class="fa fa-spinner fa-spin"></i> Processing…';

  const res = await API.payBill(billId, body);
  if (res.status === 200) {
    modal.style.display = 'none';
    toast(res.body?.message || 'Payment recorded.', 'success');

    // Reload the transactions tab
    const txTab = $('#bd-pane-transactions');
    txTab.innerHTML = `<div class="inv-pane-loading"><i class="fa fa-spinner fa-spin" style="font-size:22px;opacity:.4"></i></div>`;
    loadBillTransactionTab(billId);

    // Refresh hero overdue badge from server
    refreshBillDetailOverdue(billId);

    // Refresh bill list cards in background
    API.bills().then(r => { if (r.status === 200) { _financeAllBills = r.body?.data || []; renderBillCards(); } });
  } else {
    const errs = res.body?.errors || {};
    const firstErr = Object.values(errs)[0]?.[0] || res.body?.message || 'Payment failed.';
    errEl.textContent = firstErr; errEl.style.display = '';
    btn.disabled = false; btn.innerHTML = '<i class="fa fa-check"></i> Confirm payment';
  }
});

// Submit
$('#bill-submit').addEventListener('click', submitBillForm);

function showBillAlert(msg) {
  const el = $('#bill-modal-alert');
  el.textContent = msg;
  el.style.display = 'block';
}

async function submitBillForm() {
  const name          = $('#bill-name').value.trim();
  const category      = $('#bill-category').value;
  const catOther      = $('#bill-cat-other').value.trim();
  const mode          = document.querySelector('input[name="bill-mode"]:checked')?.value || 'recurring';
  const description   = $('#bill-description').value.trim();
  const variesUsage   = $('#bill-varies-usage').checked;
  const amount        = parseFloat($('#bill-amount').value) || 0;
  const recType       = $('#bill-rec-type').value;
  const validYear     = parseInt($('#bill-valid-year').value) || null;
  const dueDate             = $('#bill-due-date').value;
  const firstInstallment    = $('#bill-first-installment').value;
  const allowSplit    = $('#bill-allow-split').checked;
  const remindDays    = parseInt($('#bill-remind-days').value) || null;
  const assignType    = $('#bill-assign-type').value;
  const assignTarget  = parseInt($('#bill-assign-target').value) || null;
  const accountId     = parseInt($('#bill-account').value) || null;
  const notes         = $('#bill-notes').value.trim();

  // Validate
  if (!name)                                          { showBillAlert('Bill name is required'); $('#bill-name').focus(); return; }
  if (!category)                                      { showBillAlert('Select a bill category'); $('#bill-category').focus(); return; }
  if (category === 'other' && !catOther)              { showBillAlert('Describe the category'); $('#bill-cat-other').focus(); return; }
  if (mode === 'recurring' && !recType)               { showBillAlert('Select recurring type'); return; }
  if (mode === 'recurring' && !validYear)             { showBillAlert('Enter the valid until year'); $('#bill-valid-year').focus(); return; }
  if (mode === 'one_time' && !dueDate)                { showBillAlert('Enter the due date'); $('#bill-due-date').focus(); return; }
  if (assignType !== 'none' && !assignTarget)         { showBillAlert(`Select a ${assignType} to assign this bill to`); return; }

  $('#bill-modal-alert').style.display = 'none';
  const btn = $('#bill-submit');
  btn.disabled = true;
  btn.innerHTML = '<i class="fa fa-spinner fa-spin"></i> Creating…';

  const body = {
    name,
    bill_category:         category,
    payment_mode:          mode,
    amount_varies_by_usage: variesUsage,
    allow_split_payment:    allowSplit,
    assignment_type:       assignType,
  };

  if (category === 'other')  body.bill_category_other = catOther;
  if (description)           body.description = description;
  if (!variesUsage)          body.recurring_cost = amount;
  if (mode === 'recurring')  { body.recurring_type = recType; body.agreement_valid_until_year = validYear; }
  if (mode === 'one_time')   body.due_date = dueDate;
  if (firstInstallment)      body.first_installment_due_date = firstInstallment;
  if (remindDays)            body.remind_before_days = remindDays;
  if (accountId)             body.deduct_account_id = accountId;
  if (notes)                 body.notes = notes;

  if (assignType !== 'none' && assignTarget) {
    const keyMap = { branch: 'branch_id', department: 'department_id', property: 'property_id', employee: 'employee_id', modification: 'modification_id', rental: 'rental_id' };
    if (keyMap[assignType]) body[keyMap[assignType]] = assignTarget;
  }

  const res = await API.createBill(body);
  btn.disabled = false;
  btn.innerHTML = '<i class="fa fa-check"></i>&nbsp; Create Bill';

  if (res.status !== 200 && res.status !== 201) {
    let msg = res.body?.message || `Failed to create bill (${res.status})`;
    if (res.body?.errors) msg = Object.values(res.body.errors).flat().join(' ');
    showBillAlert(msg);
    return;
  }

  $('#bill-modal').style.display = 'none';
  toast('Bill created successfully', 'success');
  billData.targets  = null; // invalidate cache so next open re-fetches
  billData.accounts = null;
  loadFinance();
}

// ── Finance sub-nav (Bills ↔ Loans) ────────────────────────────────────────
// ── Finance Flow Overview ─────────────────────────────────────────────────────

async function loadFinanceFlow() {
  const root = $('#fc-root');
  root.innerHTML = `<div class="mm-loading"><i class="fa fa-spinner fa-spin"></i> Loading overview…</div>`;

  const res = await API.financeFlow();
  if (res.status !== 200) {
    root.innerHTML = `<div class="mm-loading" style="color:#e74c3c"><i class="fa fa-circle-exclamation"></i> Failed to load overview.</div>`;
    return;
  }

  const { bills, loans, rentals, summary, business_name } = res.body;
  root.innerHTML = renderFinanceFlowMindMap(bills, loans, rentals, summary, business_name);

  root.addEventListener('click', e => {
    const node = e.target.closest('[data-mm-goto]');
    if (!node) return;
    const [section] = (node.dataset.mmGoto || '').split(':');
    if (section === 'bills')      switchFinView('bills');
    if (section === 'loans')      switchFinView('loans');
    if (section === 'rentals')    switchFinView('rentals');
    if (section === 'properties') switchFinView('properties');
  });

  requestAnimationFrame(() => setTimeout(drawMindMapConnectors, 30));
}

function renderFinanceFlowMindMap(bills, loans, rentals, summary, businessName) {
  const name = businessName || ($('#sel-business').selectedOptions[0]?.text) || 'Business';

  // ── Individual item nodes ──────────────────────────────────────────────────
  const billNodes = bills.map(b => ({
    id: `mm-b${b.id}`, type: 'bill', icon: 'fa-file-invoice-dollar',
    label: b.name,
    meta: `${b.amount_fmt} · ${b.cadence}`,
    alert: b.overdue, goto: `bills:${b.id}`,
  }));

  const loanNodes = loans.map(l => ({
    id: `mm-l${l.id}`, type: 'loan', icon: 'fa-hand-holding-dollar',
    label: l.name,
    meta: `${l.monthly_fmt} / mo · ${l.cadence}`,
    alert: false, goto: `loans:${l.id}`,
  }));

  const rentalNodes = rentals.map(r => ({
    id: `mm-r${r.id}`, type: 'rental', icon: 'fa-house',
    label: r.name,
    meta: `${r.cost_fmt} · ${r.cadence}`,
    alert: r.overdue, goto: `rentals:${r.id}`,
  }));

  const expNodes = [...billNodes, ...loanNodes, ...rentalNodes];

  const incNodes = [
    { id: 'mm-n-pos',  icon: 'fa-cash-register', label: 'POS Sales',       meta: 'Sales transactions'  },
    { id: 'mm-n-quo',  icon: 'fa-file-pen',       label: 'Quotations',      meta: 'Web panel'           },
    { id: 'mm-n-inv',  icon: 'fa-file-invoice',   label: 'Invoices',        meta: 'Web panel'           },
    { id: 'mm-n-cred', icon: 'fa-rotate-left',    label: 'Credit Recovery', meta: 'Outstanding credits' },
  ];

  const expNodesHtml = expNodes.length === 0
    ? `<div class="mm-empty-col"><i class="fa fa-inbox"></i><span>No expenses recorded</span></div>`
    : expNodes.map(n => `
    <div class="mm-node mm-node-${n.type}${n.alert ? ' mm-node-alert' : ''}" id="${n.id}" data-mm-goto="${n.goto}">
      <i class="fa ${n.icon}"></i>
      <div class="mm-node-body">
        <div class="mm-node-label">${escHtml(n.label)}</div>
        <div class="mm-node-meta">${escHtml(n.meta)}${n.alert ? ' <span class="mm-overdue-badge">· Overdue</span>' : ''}</div>
      </div>
    </div>`).join('');

  const incNodesHtml = incNodes.map(n => `
    <div class="mm-node mm-node-inc mm-node-dim" id="${n.id}">
      <div class="mm-node-body">
        <div class="mm-node-label">${escHtml(n.label)}</div>
        <div class="mm-node-meta">${escHtml(n.meta)}</div>
      </div>
      <i class="fa ${n.icon}"></i>
    </div>`).join('');

  return `
    <div class="mm-canvas" id="mm-canvas">
      <svg class="mm-svg" id="mm-svg" aria-hidden="true"></svg>
      <div class="mm-left-nodes"  id="mm-left-nodes">${expNodesHtml}</div>
      <div class="mm-mid-col">
        <div class="mm-hub mm-hub-exp" id="mm-hub-exp">
          <div class="mm-hub-label">Expenses</div>
          <div class="mm-hub-sub">≈ ${escHtml(summary.total_monthly_fmt)} / mo</div>
        </div>
      </div>
      <div class="mm-center-col">
        <div class="mm-center-hub" id="mm-center-hub">
          <div class="mm-center-name">${escHtml(name)}</div>
        </div>
      </div>
      <div class="mm-mid-col">
        <div class="mm-hub mm-hub-inc" id="mm-hub-inc">
          <div class="mm-hub-label">Income</div>
          <div class="mm-hub-sub">Web panel</div>
        </div>
      </div>
      <div class="mm-right-nodes" id="mm-right-nodes">${incNodesHtml}</div>
    </div>`;
}

function drawMindMapConnectors() {
  const canvas = document.getElementById('mm-canvas');
  const svg    = document.getElementById('mm-svg');
  if (!canvas || !svg) return;

  const cr = canvas.getBoundingClientRect();
  svg.setAttribute('viewBox', `0 0 ${canvas.offsetWidth} ${canvas.offsetHeight}`);

  function pt(el, side) {
    const r = el.getBoundingClientRect();
    return {
      x: (side === 'left' ? r.left : r.right) - cr.left,
      y: r.top + r.height / 2 - cr.top,
    };
  }

  function curve(p1, p2, color, arrowId) {
    const cx = (p1.x + p2.x) / 2;
    return `<path d="M${p1.x.toFixed(1)},${p1.y.toFixed(1)} C${cx.toFixed(1)},${p1.y.toFixed(1)} ${cx.toFixed(1)},${p2.y.toFixed(1)} ${p2.x.toFixed(1)},${p2.y.toFixed(1)}" stroke="${color}" stroke-width="1.8" fill="none" opacity="0.7" marker-end="url(#${arrowId})"/>`;
  }

  const hubExp = document.getElementById('mm-hub-exp');
  const hubInc = document.getElementById('mm-hub-inc');
  const center = document.getElementById('mm-center-hub');
  if (!hubExp || !hubInc || !center) return;

  const TYPE_COLOR = { bill: '#4e8ef7', loan: '#7c3aed', rental: '#ea580c' };
  const INC = '#22c55e', HUB = '#64748b';
  const paths = [];
  // marker ids keyed by hex (strip #)
  const usedMarkers = new Set();

  function arrowId(color) { return 'mm-arr-' + color.replace('#', ''); }

  function coloredCurve(p1, p2, color) {
    usedMarkers.add(color);
    return curve(p1, p2, color, arrowId(color));
  }

  paths.push(coloredCurve(pt(center, 'left'),  pt(hubExp, 'right'), HUB));
  paths.push(coloredCurve(pt(center, 'right'), pt(hubInc, 'left'),  HUB));

  document.querySelectorAll('#mm-left-nodes .mm-node').forEach(node => {
    const type = [...node.classList].find(c => c.startsWith('mm-node-') && c !== 'mm-node-alert')?.replace('mm-node-', '');
    const color = TYPE_COLOR[type] || '#ef4444';
    paths.push(coloredCurve(pt(node, 'right'), pt(hubExp, 'left'), color));
  });

  document.querySelectorAll('#mm-right-nodes .mm-node').forEach(node => {
    paths.push(coloredCurve(pt(hubInc, 'right'), pt(node, 'left'), INC));
  });

  const markerDefs = [...usedMarkers].map(c =>
    `<marker id="${arrowId(c)}" markerWidth="8" markerHeight="6" refX="7" refY="3" orient="auto">
      <polygon points="0 0,8 3,0 6" fill="${c}"/>
    </marker>`).join('');

  svg.innerHTML = `<defs>${markerDefs}</defs>${paths.join('\n')}`;
}

function switchFinView(view) {
  $$('#panel-finance .fin-subnav-btn[data-fin]').forEach(b => {
    b.classList.toggle('active', b.dataset.fin === view);
  });
  $('#bill-detail-view').style.display           = 'none';
  $('#loan-detail-view').style.display           = 'none';
  $('#rental-detail-view').style.display         = 'none';
  $('#modification-detail-view').style.display   = 'none';
  $('#finance-flow-view').style.display          = view === 'flow'          ? '' : 'none';
  $('#finance-list-view').style.display          = view === 'bills'         ? '' : 'none';
  $('#loans-list-view').style.display            = view === 'loans'         ? '' : 'none';
  $('#rentals-list-view').style.display          = view === 'rentals'       ? '' : 'none';
  $('#properties-list-view').style.display       = view === 'properties'    ? '' : 'none';
  $('#modifications-list-view').style.display    = view === 'modifications' ? '' : 'none';
  if (view === 'flow')          loadFinanceFlow();
  if (view === 'bills')         loadFinance();
  if (view === 'loans')         loadLoans();
  if (view === 'rentals')       loadRentals();
  if (view === 'properties')    loadProperties();
  if (view === 'modifications') loadModifications();
}

$$('#panel-finance .fin-subnav-btn[data-fin]').forEach(btn => {
  btn.addEventListener('click', () => switchFinView(btn.dataset.fin));
});

// ── Rental Panel ─────────────────────────────────────────────────────────────
const RENTAL_REC_LABEL = { per_day: 'Daily', per_month: 'Monthly', per_year: 'Yearly' };

let rentalsSearchFilter = '';
let _rentalsAll  = [];
let _rentalsStats = { active_count: 0, overdue_count: 0, total_monthly_fmt: '0.00' };

async function loadRentals(search) {
  if (search !== undefined) rentalsSearchFilter = search;
  const area = $('#rentals-cards-area');
  area.innerHTML = '<div class="finance-loading"><i class="fa fa-spinner fa-spin"></i> Loading rentals…</div>';
  const res = await API.rentalList();
  if (res.status === 200 && res.body) {
    _rentalsAll   = res.body.data || [];
    _rentalsStats = {
      active_count:       res.body.active_count      ?? 0,
      overdue_count:      res.body.overdue_count     ?? 0,
      total_monthly_fmt:  res.body.total_monthly_fmt ?? '0.00',
    };
  } else {
    _rentalsAll   = [];
    _rentalsStats = { active_count: 0, overdue_count: 0, total_monthly_fmt: '0.00' };
  }
  renderRentalCards();
}

function renderRentalCards() {
  const area = $('#rentals-cards-area');
  const q = rentalsSearchFilter.trim().toLowerCase();
  const list = q
    ? _rentalsAll.filter(r => (r.property_type||'').toLowerCase().includes(q) || (r.purpose||'').toLowerCase().includes(q))
    : _rentalsAll;

  $('#rentals-count').textContent = `${list.length} rental${list.length !== 1 ? 's' : ''}`;

  const statsBar = $('#rentals-stats-bar');
  if (_rentalsAll.length > 0) {
    $('#rm-active-count').textContent   = _rentalsStats.active_count;
    $('#rm-overdue-count').textContent  = _rentalsStats.overdue_count;
    $('#rm-monthly-outflow').textContent= _rentalsStats.total_monthly_fmt;
    statsBar.style.display = '';
  } else {
    statsBar.style.display = 'none';
  }

  if (!list.length) {
    area.innerHTML = `
      <div class="finance-empty">
        <div class="finance-empty-icon"><i class="fa fa-house"></i></div>
        <p>${q ? 'No rentals match your search' : 'No rentals yet'}</p>
        <span>${q ? 'Try a different term' : 'Click Add Rental to record your first lease'}</span>
      </div>`;
    return;
  }
  area.innerHTML = list.map(buildRentalCard).join('');
}

function buildRentalCard(r) {
  const cadence   = (RENTAL_REC_LABEL[r.recurring_type] || 'Recurring').toUpperCase();
  const isOverdue = Boolean(r.overdue);

  const statusBadge = isOverdue
    ? `<span class="lm-badge rm-badge-overdue"><i class="fa fa-circle-exclamation"></i> Overdue</span>`
    : `<span class="lm-badge lm-badge-active"><i class="fa fa-circle"></i> Active</span>`;

  let debitHtml = '<span class="lm-info-val lm-muted">—</span>';
  if (r.account_name) {
    const parts = [escHtml(r.account_name)];
    if (r.account_number)    parts.push(`<span class="lm-muted">${escHtml(r.account_number)}</span>`);
    if (r.account_bank_name) parts.push(`<span class="lm-muted">${escHtml(r.account_bank_name)}</span>`);
    if (r.account_type_name) parts.push(`<span class="lm-muted">${escHtml(r.account_type_name)}</span>`);
    if (r.account_balance != null) parts.push(`<span class="lm-balance">Bal. ${escHtml(r.account_balance)}</span>`);
    debitHtml = `<span class="lm-info-val lm-acct-val">${parts.join(' · ')}</span>`;
  }

  const validUntil = r.agreement_valid_until_year ? `Until ${r.agreement_valid_until_year}` : '—';

  return `
    <div class="lm-card rm-card${isOverdue ? ' rm-card-overdue' : ''}" data-rental-id="${r.id}">
      <div class="lm-card-header">
        <div class="lm-card-icon rm-card-icon"><i class="fa fa-house"></i></div>
        <div class="lm-card-title-wrap">
          <span class="lm-card-name">${escHtml(r.property_type || '—')}</span>
          <div class="lm-card-pills">
            ${statusBadge}
            ${r.purpose ? `<span class="lm-pill rm-pill-purpose"><i class="fa fa-tag"></i> ${escHtml(r.purpose)}</span>` : ''}
            <span class="lm-pill lm-pill-cadence"><i class="fa fa-rotate"></i> ${cadence}</span>
          </div>
        </div>
        <button class="lm-remove-btn" data-rental-id="${r.id}" title="Remove rental">
          <i class="fa fa-trash"></i> Remove
        </button>
      </div>

      <div class="lm-stats-row">
        <div class="lm-stat-block">
          <span class="lm-stat-block-label">RENT AMOUNT</span>
          <span class="lm-stat-block-val${isOverdue ? ' rm-overdue-val' : ''}">${escHtml(r.recurring_cost_fmt || '—')}</span>
        </div>
        <div class="lm-stat-block">
          <span class="lm-stat-block-label">KEY MONEY</span>
          <span class="lm-stat-block-val">${r.key_money_fmt ? escHtml(r.key_money_fmt) : '—'}</span>
        </div>
        <div class="lm-stat-block">
          <span class="lm-stat-block-label">VALID UNTIL</span>
          <span class="lm-stat-block-val">${escHtml(validUntil)}</span>
        </div>
      </div>

      <div class="lm-info-rows">
        <div class="lm-info-row">
          <span class="lm-info-key"><i class="fa fa-calendar-day"></i> First due</span>
          <span class="lm-info-val">${r.due_date_fmt ? escHtml(r.due_date_fmt) : '—'}</span>
        </div>
        <div class="lm-info-row">
          <span class="lm-info-key"><i class="fa fa-building-columns"></i> Debit account</span>
          ${debitHtml}
        </div>
        ${r.notes ? `<div class="lm-info-row">
          <span class="lm-info-key"><i class="fa fa-note-sticky"></i> Notes</span>
          <span class="lm-info-val lm-muted">${escHtml(r.notes.slice(0, 80))}${r.notes.length > 80 ? '…' : ''}</span>
        </div>` : ''}
      </div>
    </div>`;
}

// Click rental card → open detail (ignore Remove button)
$('#rentals-cards-area').addEventListener('click', async e => {
  if (e.target.closest('.lm-remove-btn[data-rental-id]')) {
    const btn  = e.target.closest('.lm-remove-btn');
    const id   = btn.dataset.rentalId;
    const card = btn.closest('.rm-card');
    const name = card?.querySelector('.lm-card-name')?.textContent || 'this rental';
    if (!confirm(`Remove "${name}"? This cannot be undone.`)) return;
    btn.disabled = true; btn.innerHTML = '<i class="fa fa-spinner fa-spin"></i>';
    const res = await API.deleteRental(id);
    if (res.status === 200) {
      card.style.transition = 'opacity .2s, transform .2s';
      card.style.opacity = '0'; card.style.transform = 'translateX(12px)';
      setTimeout(() => { _rentalsAll = _rentalsAll.filter(x => String(x.id) !== String(id)); renderRentalCards(); }, 200);
      toast('Rental removed', 'success');
    } else {
      btn.disabled = false; btn.innerHTML = '<i class="fa fa-trash"></i> Remove';
      toast(res.body?.message || `Failed (${res.status})`, 'error');
    }
    return;
  }
  const card = e.target.closest('.rm-card[data-rental-id]');
  if (!card) return;
  const r = _rentalsAll.find(x => String(x.id) === String(card.dataset.rentalId));
  if (r) openRentalDetailPage(r);
});

// Search
let rentalsSearchTimer;
$('#rentals-search').addEventListener('input', e => {
  clearTimeout(rentalsSearchTimer);
  rentalsSearchFilter = e.target.value;
  rentalsSearchTimer = setTimeout(renderRentalCards, 250);
});

// ── Rental Detail Page ────────────────────────────────────────────────────────

$('#rental-detail-back').addEventListener('click', () => {
  $('#rental-detail-view').style.display  = 'none';
  $('.fin-subnav').style.display          = '';
  $('#rentals-list-view').style.display   = '';
});

$$('#rd-inv-tabs .inv-tab').forEach(tab => {
  tab.addEventListener('click', () => {
    $$('#rd-inv-tabs .inv-tab').forEach(t => t.classList.remove('active'));
    tab.classList.add('active');
    $$('.inv-tab-pane[id^="rd-pane-"]').forEach(p => p.classList.toggle('active', p.id === `rd-pane-${tab.dataset.tab}`));
  });
});

function openRentalDetailPage(r) {
  const cadence   = (RENTAL_REC_LABEL[r.recurring_type] || 'Recurring').toUpperCase();
  const isOverdue = Boolean(r.overdue);

  $('.fin-subnav').style.display        = 'none';
  $('#rentals-list-view').style.display = 'none';
  $('#rental-detail-view').style.display= 'flex';

  $$('#rd-inv-tabs .inv-tab').forEach(t => t.classList.toggle('active', t.dataset.tab === 'overview'));
  $$('.inv-tab-pane[id^="rd-pane-"]').forEach(p => p.classList.toggle('active', p.id === 'rd-pane-overview'));

  $('#rd-breadcrumb').textContent = r.property_type || 'Rental';
  $('#rd-hero-icon').innerHTML = `
    <div class="inv-thumb-ph inv-thumb-lg" style="background:#fff7ed;color:#ea580c;font-size:28px">
      <i class="fa fa-house"></i>
    </div>`;
  $('#rd-hero-name').textContent = r.property_type || '—';

  const metaParts = [];
  if (r.purpose) metaParts.push(`<span><i class="fa fa-tag"></i> ${escHtml(r.purpose)}</span>`);
  metaParts.push(`<span><i class="fa fa-rotate"></i> ${cadence}</span>`);
  $('#rd-hero-meta').innerHTML = metaParts.join('<span class="inv-sep">·</span>');

  const badges = [];
  if (isOverdue) badges.push('<span class="inv-badge inv-badge-red">Overdue</span>');
  else           badges.push('<span class="inv-badge inv-badge-green">Active</span>');
  if (r.agreement_valid_until_year)
    badges.push(`<span class="inv-badge inv-badge-gray">Until ${r.agreement_valid_until_year}</span>`);
  badges.push(`<span class="inv-badge inv-badge-blue">${escHtml(cadence)}</span>`);
  $('#rd-hero-badges').innerHTML = badges.join('');

  const rowsHtml = rows => rows.map(([label, val]) =>
    `<tr><td class="inv-dt-label">${escHtml(label)}</td><td class="inv-dt-val">${val}</td></tr>`).join('');

  const payRows = [
    ['Rent amount',  `<strong>${escHtml(r.recurring_cost_fmt || '—')}</strong>`],
    ['Cadence',      escHtml(cadence)],
  ];
  if (r.due_date_fmt) payRows.push(['First due', escHtml(r.due_date_fmt)]);
  if (r.key_money_fmt) payRows.push(['Key money', escHtml(r.key_money_fmt)]);
  if (r.agreement_valid_until_year)
    payRows.push(['Valid until', String(r.agreement_valid_until_year)]);

  let acctSection = '';
  if (r.account_name) {
    const acctRows = [['Account', escHtml(r.account_name)]];
    if (r.account_number)    acctRows.push(['Account No.',  escHtml(r.account_number)]);
    if (r.account_bank_name) acctRows.push(['Bank',         escHtml(r.account_bank_name)]);
    if (r.account_type_name) acctRows.push(['Type',         escHtml(r.account_type_name)]);
    if (r.account_balance != null)
      acctRows.push(['Balance', `<strong>${escHtml(r.account_balance)}</strong>`]);
    acctSection = `
      <div class="inv-section" style="margin-top:14px">
        <div class="inv-section-title"><i class="fa fa-building-columns"></i> Debit account</div>
        <table class="inv-detail-table">${rowsHtml(acctRows)}</table>
      </div>`;
  }

  const notesSection = r.notes
    ? `<div class="inv-section" style="margin-top:14px">
        <div class="inv-section-title"><i class="fa fa-note-sticky"></i> Notes</div>
        <div style="padding:10px 14px;font-size:12px;color:var(--text-muted);white-space:pre-wrap;line-height:1.6">${escHtml(r.notes)}</div>
       </div>` : '';

  const overdueAlert = isOverdue
    ? `<div class="bd-alert" style="margin-bottom:14px">
        <i class="fa fa-circle-exclamation"></i>
        <div><strong>Rent overdue</strong> — A scheduled billing date has no ledger record. Record the payment to clear this alert.</div>
       </div>` : '';

  $('#rd-pane-overview').innerHTML = `
    ${overdueAlert}
    <div class="inv-section">
      <div class="inv-section-title"><i class="fa fa-house"></i> Rental details</div>
      <table class="inv-detail-table">${rowsHtml(payRows)}</table>
    </div>
    ${acctSection}
    ${notesSection}
    <div class="inv-section" style="margin-top:14px">
      <div class="inv-section-title" style="color:#dc2626"><i class="fa fa-trash"></i> Remove rental</div>
      <div style="padding:12px 14px;display:flex;align-items:center;justify-content:space-between;gap:12px">
        <span style="font-size:12px;color:var(--text-muted);line-height:1.5">Removes this rental record. Existing ledger rows in Transactions are kept for history.</span>
        <button class="bd-danger-btn" id="rd-delete-rental" style="flex-shrink:0">
          <i class="fa fa-trash"></i> Remove
        </button>
      </div>
    </div>`;

  $('#rd-delete-rental').addEventListener('click', async () => {
    if (!confirm(`Remove "${r.property_type}"? This cannot be undone.`)) return;
    const btn = $('#rd-delete-rental');
    btn.disabled = true; btn.innerHTML = '<i class="fa fa-spinner fa-spin"></i>';
    const res = await API.deleteRental(r.id);
    if (res.status === 200) {
      _rentalsAll = _rentalsAll.filter(x => String(x.id) !== String(r.id));
      $('#rental-detail-back').click(); renderRentalCards();
      toast('Rental removed', 'success');
    } else {
      btn.disabled = false; btn.innerHTML = '<i class="fa fa-trash"></i> Remove';
      toast(res.body?.message || 'Failed to remove', 'error');
    }
  });

  // Lazy-load tabs (schedule / bills / land)
  let _rdScheduleLoaded = false;
  let _rdBillsLoaded    = false;
  let _rdLandLoaded     = false;
  $$('#rd-inv-tabs .inv-tab').forEach(tab => {
    tab.addEventListener('click', () => {
      const t = tab.dataset.tab;
      if (t === 'schedule' && !_rdScheduleLoaded) { _rdScheduleLoaded = true; loadRentalScheduleTab(r.id); }
      if (t === 'bills'    && !_rdBillsLoaded)    { _rdBillsLoaded    = true; loadRentalBillsTab(r.id); }
      if (t === 'land'     && !_rdLandLoaded)     { _rdLandLoaded     = true; loadRentalLandTab(r.id); }
    });
  });
  const _rdSpinner = `<div class="inv-pane-loading"><i class="fa fa-spinner fa-spin" style="font-size:22px;opacity:.4"></i></div>`;
  $('#rd-pane-schedule').innerHTML = _rdSpinner;
  $('#rd-pane-bills').innerHTML    = _rdSpinner;
  $('#rd-pane-land').innerHTML     = _rdSpinner;
}

async function loadRentalScheduleTab(rentalId) {
  const pane = $('#rd-pane-schedule');
  pane.innerHTML = `<div class="inv-pane-loading"><i class="fa fa-spinner fa-spin" style="font-size:22px;opacity:.4"></i></div>`;
  const res = await API.rental(rentalId);
  if (res.status !== 200) {
    pane.innerHTML = `<div class="inv-pane-empty"><i class="fa fa-circle-exclamation"></i><p>Could not load schedule.</p></div>`;
    return;
  }
  const data     = res.body?.data || {};
  const schedule = data.schedule || [];

  function statusBadge(row) {
    if (row.paid)
      return `<span class="bd-sched-badge bd-sched-paid"><i class="fa fa-check-circle"></i> Paid</span>`;
    if (row.past_due_unpaid)
      return `<span class="bd-sched-badge bd-sched-overdue"><i class="fa fa-circle-exclamation"></i> ${escHtml(row.status_label)}</span>`;
    return `<span class="bd-sched-badge bd-sched-await"><i class="fa fa-circle-info"></i> Outstanding</span>`;
  }

  const schedRows = schedule.map(row => {
    const rowClass  = row.past_due_unpaid && !row.paid ? 'bd-sched-row-due' : '';
    const actionBtn = row.paid
      ? `<span style="color:var(--text-muted);font-size:11px">—</span>`
      : `<button class="rd-pay-btn" data-due="${escHtml(row.due_ymd)}" data-amt="${escHtml(row.amount_formatted)}">
           <i class="fa fa-money-bill-wave"></i> Make payment
         </button>`;
    return `<tr class="${rowClass}">
      <td class="bd-sched-num">${row.period}</td>
      <td class="bd-sched-cell">${escHtml(row.due_ymd)}</td>
      <td class="bd-sched-cell" style="font-variant-numeric:tabular-nums">${escHtml(row.amount_formatted)}</td>
      <td class="bd-sched-cell">${statusBadge(row)}</td>
      <td class="bd-sched-cell">${actionBtn}</td>
    </tr>`;
  }).join('');

  pane.innerHTML = schedule.length
    ? `<div class="inv-section">
        <div class="inv-section-title"><i class="fa fa-calendar-days"></i> Billing schedule &amp; payment status</div>
        <div class="bd-sched-scroll">
          <table class="bd-sched-table">
            <thead><tr>
              <th class="bd-sched-num">#</th>
              <th>Billing date</th>
              <th>Amount</th>
              <th>Status</th>
              <th>Action</th>
            </tr></thead>
            <tbody>${schedRows}</tbody>
          </table>
        </div>
       </div>`
    : `<div class="inv-pane-empty"><i class="fa fa-calendar-days"></i><p>No billing dates generated yet. Set a due date or first installment date to see the schedule.</p></div>`;

  pane.addEventListener('click', e => {
    const btn = e.target.closest('.rd-pay-btn');
    if (!btn) return;
    openRentalPayModal(rentalId, btn.dataset);
  });
}

async function loadRentalBillsTab(rentalId) {
  const pane = $('#rd-pane-bills');
  pane.innerHTML = `<div class="inv-pane-loading"><i class="fa fa-spinner fa-spin" style="font-size:22px;opacity:.4"></i></div>`;
  const res = await API.rental(rentalId);
  if (res.status !== 200) {
    pane.innerHTML = `<div class="inv-pane-empty"><i class="fa fa-circle-exclamation"></i><p>Could not load linked bills.</p></div>`;
    return;
  }
  const bills = res.body?.data?.bills || [];
  if (!bills.length) {
    pane.innerHTML = `<div class="inv-pane-empty">
      <i class="fa fa-file-invoice-dollar"></i>
      <p>No bills linked to this rental yet.</p>
      <span>When adding or editing a bill in the web panel, enable <strong>Link to a rental record</strong> and choose this property.</span>
    </div>`;
    return;
  }
  const rows = bills.map(b => {
    const amtCell = b.amount_varies
      ? `Varies`
      : escHtml(b.recurring_cost_fmt);
    const schedCell = b.is_one_time
      ? escHtml(b.payment_mode_label)
      : escHtml(b.recurring_type_label);
    return `<tr>
      <td><strong>${escHtml(b.name)}</strong>${b.description ? `<br><span style="font-size:11px;color:var(--text-muted)">${escHtml(b.description.substring(0,80))}</span>` : ''}</td>
      <td>${escHtml(b.category_label)}</td>
      <td>${schedCell}</td>
      <td style="font-variant-numeric:tabular-nums">${amtCell}</td>
    </tr>`;
  }).join('');
  pane.innerHTML = `
    <div class="inv-section">
      <div class="inv-section-title"><i class="fa fa-file-invoice-dollar"></i> Linked bills (${bills.length})</div>
      <p style="padding:0 14px 8px;font-size:12px;color:var(--text-muted);line-height:1.5">Bills attached to this rental — utilities, services, charges. Payments are managed on each bill's own schedule.</p>
      <div class="bd-sched-scroll">
        <table class="bd-sched-table">
          <thead><tr>
            <th>Bill</th>
            <th>Category</th>
            <th>Schedule</th>
            <th>Amount</th>
          </tr></thead>
          <tbody>${rows}</tbody>
        </table>
      </div>
    </div>`;
}

async function loadRentalLandTab(rentalId) {
  const pane = $('#rd-pane-land');
  pane.innerHTML = `<div class="inv-pane-loading"><i class="fa fa-spinner fa-spin" style="font-size:22px;opacity:.4"></i></div>`;
  const res = await API.rental(rentalId);
  if (res.status !== 200) {
    pane.innerHTML = `<div class="inv-pane-empty"><i class="fa fa-circle-exclamation"></i><p>Could not load land details.</p></div>`;
    return;
  }
  const d        = res.body?.data || {};
  const landlord = d.landlord || null;

  const rowsHtml = rows => rows.map(([label, val]) =>
    `<tr><td class="inv-dt-label">${escHtml(label)}</td><td class="inv-dt-val">${val}</td></tr>`).join('');

  const premiseRows = [['Property type', escHtml(d.property_type || '—')]];
  if (d.warehouse_name) premiseRows.push(['Branch / site', escHtml(d.warehouse_name)]);
  if (d.purpose)        premiseRows.push(['Purpose / use', escHtml(d.purpose)]);
  if (d.agreement_valid_until_year)
    premiseRows.push(['Agreement ends', String(d.agreement_valid_until_year)]);
  if (d.notes) premiseRows.push(['Notes', `<span style="white-space:pre-wrap">${escHtml(d.notes)}</span>`]);

  let landlordSection = '';
  if (landlord) {
    const initial = (landlord.name || '?')[0].toUpperCase();
    const contacts = [];
    if (landlord.email)
      contacts.push(`<div class="rd-land-row"><span class="rd-land-ico"><i class="fa fa-envelope"></i></span><div><div class="rd-land-lab">Email</div><div class="rd-land-val">${escHtml(landlord.email)}</div></div></div>`);
    if (landlord.phone)
      contacts.push(`<div class="rd-land-row"><span class="rd-land-ico"><i class="fa fa-phone"></i></span><div><div class="rd-land-lab">Phone</div><div class="rd-land-val">${escHtml(landlord.phone)}</div></div></div>`);
    if (landlord.street_address)
      contacts.push(`<div class="rd-land-row"><span class="rd-land-ico"><i class="fa fa-location-dot"></i></span><div><div class="rd-land-lab">Address</div><div class="rd-land-val" style="white-space:pre-wrap">${escHtml(landlord.street_address)}</div></div></div>`);
    if (landlord.bank_account_details)
      contacts.push(`<div class="rd-land-row"><span class="rd-land-ico"><i class="fa fa-building-columns"></i></span><div><div class="rd-land-lab">Bank / payment</div><div class="rd-land-val" style="white-space:pre-wrap">${escHtml(landlord.bank_account_details)}</div></div></div>`);
    if (landlord.notes)
      contacts.push(`<div class="rd-land-row"><span class="rd-land-ico"><i class="fa fa-note-sticky"></i></span><div><div class="rd-land-lab">Contact notes</div><div class="rd-land-val" style="white-space:pre-wrap;font-style:italic">${escHtml(landlord.notes)}</div></div></div>`);
    landlordSection = `
      <div class="inv-section" style="margin-top:14px">
        <div class="inv-section-title"><i class="fa fa-address-book"></i> Landlord / Owner</div>
        <div style="padding:10px 14px">
          <div style="display:flex;align-items:center;gap:10px;margin-bottom:12px;padding-bottom:12px;border-bottom:1px solid var(--border)">
            <div class="rd-land-avatar">${escHtml(initial)}</div>
            <div>
              <div style="font-size:13px;font-weight:700;color:var(--text)">${escHtml(landlord.name || '—')}</div>
              <div style="font-size:10px;color:var(--text-muted);text-transform:uppercase;letter-spacing:.04em;margin-top:2px">Address book contact</div>
            </div>
          </div>
          ${contacts.join('')}
        </div>
      </div>`;
  } else {
    landlordSection = `
      <div class="inv-section" style="margin-top:14px">
        <div class="inv-section-title"><i class="fa fa-address-book"></i> Landlord / Owner</div>
        <div class="inv-pane-empty" style="margin:10px 14px;border:1px dashed var(--border);border-radius:8px">
          <i class="fa fa-user-slash"></i><p>No landlord linked.</p>
        </div>
      </div>`;
  }

  pane.innerHTML = `
    <div class="inv-section">
      <div class="inv-section-title"><i class="fa fa-building"></i> Premises &amp; use</div>
      <table class="inv-detail-table">${rowsHtml(premiseRows)}</table>
    </div>
    ${landlordSection}`;
}

// ── Rental pay modal ─────────────────────────────────────────────────────────

let _rpmAccounts       = [];
let _rpmCurrentRentalId = null;

$('#rpm-close').addEventListener('click',       () => { $('#rental-pay-modal').style.display = 'none'; });
$('#rental-pay-modal').addEventListener('click', e => { if (e.target === $('#rental-pay-modal')) $('#rental-pay-modal').style.display = 'none'; });

async function openRentalPayModal(rentalId, rowData) {
  _rpmCurrentRentalId = rentalId;
  const { due, amt } = rowData;
  $('#rpm-due-date').textContent = due || '—';
  $('#rpm-amount').textContent   = amt ? `LKR ${amt}` : '—';
  $('#rpm-error').style.display  = 'none';
  $('#rpm-confirm-btn').disabled = false;
  $('#rpm-confirm-btn').innerHTML= '<i class="fa fa-circle-check"></i> Confirm payment';
  $('#rental-pay-modal').style.display = 'flex';

  const select = $('#rpm-account-select');
  if (_rpmAccounts.length === 0) {
    select.innerHTML = '<option value="">Loading…</option>';
    const res = await API.accounts();
    _rpmAccounts = res.status === 200 ? (res.body?.data || []) : [];
  }
  select.innerHTML = _rpmAccounts.length
    ? _rpmAccounts.map(a => `<option value="${a.id}">${escHtml(a.label || a.name || a.account_name || String(a.id))}</option>`).join('')
    : '<option value="">No accounts found</option>';
}

$('#rpm-confirm-btn').addEventListener('click', async () => {
  const accountId = parseInt($('#rpm-account-select').value);
  const errEl = $('#rpm-error');
  if (!accountId) {
    errEl.textContent = 'Please select a debit account.';
    errEl.style.display = ''; return;
  }
  errEl.style.display = 'none';
  const btn = $('#rpm-confirm-btn');
  btn.disabled = true; btn.innerHTML = '<i class="fa fa-spinner fa-spin"></i> Saving…';

  const res = await API.payRental(_rpmCurrentRentalId, {
    due_date:   $('#rpm-due-date').textContent,
    account_id: accountId,
  });
  if (res.status === 200) {
    $('#rental-pay-modal').style.display = 'none';
    toast('Rent payment recorded', 'success');
    const pane = $('#rd-pane-schedule');
    pane.innerHTML = `<div class="inv-pane-loading"><i class="fa fa-spinner fa-spin" style="font-size:22px;opacity:.4"></i></div>`;
    await loadRentalScheduleTab(_rpmCurrentRentalId);
  } else {
    const msg = res.body?.message || `Failed (${res.status})`;
    errEl.textContent = msg; errEl.style.display = '';
    btn.disabled = false; btn.innerHTML = '<i class="fa fa-circle-check"></i> Confirm payment';
  }
});

// ── Rental Create Modal ───────────────────────────────────────────────────────

let _rcAccounts = null;

const rcClose = () => { $('#rental-create-modal').style.display = 'none'; };
$('#rental-modal-close').addEventListener('click', rcClose);
$('#rental-cancel').addEventListener('click',      rcClose);
$('#rental-create-modal').addEventListener('click', e => { if (e.target === $('#rental-create-modal')) rcClose(); });
$('#btn-rental-create').addEventListener('click', openRentalCreateModal);
$('#rental-submit').addEventListener('click', submitRentalForm);

async function openRentalCreateModal() {
  // Reset form
  ['rc-property-type','rc-purpose','rc-key-money','rc-owner-name','rc-owner-email',
   'rc-owner-phone','rc-owner-address','rc-owner-bank','rc-owner-notes',
   'rc-cost','rc-remind','rc-due-date','rc-first-install','rc-notes'].forEach(id => {
    const el = $('#' + id);
    if (el) el.value = '';
  });
  const yearEl = $('#rc-valid-year');
  if (yearEl) yearEl.value = new Date().getFullYear() + 1;
  $('#rc-recurring-type').value = 'per_month';
  $('#rental-modal-alert').style.display = 'none';
  $('#rental-submit').disabled = false;
  $('#rental-submit').innerHTML = '<i class="fa fa-check"></i> Save rental';

  // Load accounts if not cached
  if (!_rcAccounts) {
    const res = await API.accounts();
    _rcAccounts = res.status === 200 ? (res.body?.data || []) : [];
  }
  const sel = $('#rc-account');
  sel.innerHTML = '<option value="">None</option>'
    + _rcAccounts.map(a => `<option value="${a.id}">${escHtml(a.label || a.name || a.account_name || String(a.id))}</option>`).join('');

  $('#rental-create-modal').style.display = 'flex';
  setTimeout(() => $('#rc-property-type').focus(), 80);
}

async function submitRentalForm() {
  const alertEl = $('#rental-modal-alert');
  alertEl.style.display = 'none';

  const body = {
    property_type:              $('#rc-property-type').value.trim(),
    purpose:                    $('#rc-purpose').value.trim() || null,
    key_money:                  $('#rc-key-money').value || null,
    agreement_valid_until_year: $('#rc-valid-year').value || null,
    owner_name:                 $('#rc-owner-name').value.trim(),
    owner_email:                $('#rc-owner-email').value.trim() || null,
    owner_phone:                $('#rc-owner-phone').value.trim() || null,
    owner_address:              $('#rc-owner-address').value.trim() || null,
    owner_bank_details:         $('#rc-owner-bank').value.trim() || null,
    owner_notes:                $('#rc-owner-notes').value.trim() || null,
    deduct_account_id:          $('#rc-account').value || null,
    recurring_cost:             $('#rc-cost').value || null,
    recurring_type:             $('#rc-recurring-type').value,
    remind_before_days:         $('#rc-remind').value || null,
    due_date:                   $('#rc-due-date').value || null,
    first_installment_due_date: $('#rc-first-install').value || null,
    notes:                      $('#rc-notes').value.trim() || null,
  };

  // Client-side required check
  if (!body.property_type) { showRcError('Property type is required.'); return; }
  if (!body.owner_name)    { showRcError('Owner name is required.'); return; }
  if (!body.owner_email && !body.owner_phone) { showRcError('Enter landlord email or phone.'); return; }
  if (!body.recurring_cost) { showRcError('Recurring cost is required.'); return; }
  if (!body.agreement_valid_until_year) { showRcError('Agreement valid until (year) is required.'); return; }

  const btn = $('#rental-submit');
  btn.disabled = true;
  btn.innerHTML = '<i class="fa fa-spinner fa-spin"></i> Saving…';

  const res = await API.createRental(body);
  if (res.status === 201) {
    rcClose();
    toast('Rental added', 'success');
    await loadRentals();
  } else {
    const msg = res.body?.message || res.body?.errors
      ? (Object.values(res.body.errors || {})[0]?.[0] || res.body.message)
      : `Failed (${res.status})`;
    showRcError(msg);
    btn.disabled = false;
    btn.innerHTML = '<i class="fa fa-check"></i> Save rental';
  }
}

function showRcError(msg) {
  const el = $('#rental-modal-alert');
  el.textContent = msg;
  el.style.display = '';
  el.scrollIntoView({ behavior: 'smooth', block: 'nearest' });
}

// ── Properties Panel ──────────────────────────────────────────────────────────

const PROP_TYPE_ICON = {
  accessories: 'fa-wrench',
  machinery:   'fa-gears',
  landing:     'fa-map',
  land:        'fa-map',
  building:    'fa-building',
  office:      'fa-building-user',
  shop:        'fa-store',
  warehouse:   'fa-warehouse',
  vehicle:     'fa-car',
  furniture:   'fa-couch',
  electronics: 'fa-laptop',
  other:       'fa-box',
};

let propertiesSearchFilter = '';
let _propertiesAll = [];
let _propertiesStats = { total_count: 0, expiring_count: 0, total_cost_fmt: '0.00' };

async function loadProperties(search) {
  if (search !== undefined) propertiesSearchFilter = search;
  const area = $('#properties-cards-area');
  area.innerHTML = `<div class="finance-loading"><i class="fa fa-spinner fa-spin"></i> Loading properties…</div>`;

  const res = await API.propertyList();
  if (res.status !== 200) {
    area.innerHTML = `<div class="finance-empty"><p>Failed to load properties.</p><span>${res.body?.message || ''}</span></div>`;
    return;
  }
  _propertiesAll   = res.body?.data  || [];
  _propertiesStats = {
    total_count:    res.body?.total_count    ?? 0,
    expiring_count: res.body?.expiring_count ?? 0,
    total_cost_fmt: res.body?.total_cost_fmt ?? '0.00',
  };

  const statsBar = $('#properties-stats-bar');
  if (_propertiesAll.length) {
    statsBar.style.display       = '';
    $('#prop-total-count').textContent    = _propertiesStats.total_count;
    $('#prop-expiring-count').textContent = _propertiesStats.expiring_count;
    $('#prop-total-cost').textContent     = _propertiesStats.total_cost_fmt;
  } else {
    statsBar.style.display = 'none';
  }

  renderPropertyCards();
}

function renderPropertyCards() {
  const q    = propertiesSearchFilter.toLowerCase();
  const list = q
    ? _propertiesAll.filter(p => p.property_name.toLowerCase().includes(q) || p.property_type.toLowerCase().includes(q))
    : _propertiesAll;

  const area = $('#properties-cards-area');
  $('#properties-count').textContent = `${list.length} propert${list.length === 1 ? 'y' : 'ies'}`;

  if (!list.length) {
    area.innerHTML = `<div class="finance-empty">
      <i class="fa fa-building" style="font-size:28px;opacity:.3;margin-bottom:8px"></i>
      <p>${q ? 'No properties match your search' : 'No properties yet'}</p>
      <span>${q ? 'Try a different term' : 'Click Add Property to record your first asset'}</span>
    </div>`;
    return;
  }
  area.innerHTML = list.map(p => buildPropertyCard(p)).join('');
}

function buildPropertyCard(p) {
  const icon      = PROP_TYPE_ICON[p.property_type] || 'fa-box';
  const expBadge  = p.expired
    ? `<span class="lm-badge" style="background:#fee2e2;color:#b91c1c;border-color:#fca5a5"><i class="fa fa-calendar-xmark"></i> Expired</span>`
    : p.expiring_soon
    ? `<span class="lm-badge" style="background:#fef9c3;color:#854d0e;border-color:#fde68a"><i class="fa fa-clock"></i> Expiring soon</span>`
    : '';
  const typeBadge = `<span class="lm-badge">${escHtml(p.property_type)}</span>`;
  const expiryRow = p.has_expiry && p.expire_date_fmt
    ? `<div class="lm-info-row"><span class="lm-info-key"><i class="fa fa-calendar-xmark"></i> Expiry</span><span class="lm-info-val ${p.expired ? 'lm-acct-val' : ''}">${escHtml(p.expire_date_fmt)}</span></div>`
    : '';
  const descRow = p.description
    ? `<div class="lm-info-row"><span class="lm-info-key"><i class="fa fa-note-sticky"></i> Note</span><span class="lm-info-val lm-muted">${escHtml(p.description.substring(0,60))}${p.description.length > 60 ? '…' : ''}</span></div>`
    : '';

  return `<div class="lm-card prop-card${p.expired ? ' prop-card-expired' : p.expiring_soon ? ' prop-card-expiring' : ''}" data-property-id="${p.id}">
    <div class="lm-card-header">
      <span class="lm-card-icon prop-card-icon"><i class="fa ${icon}"></i></span>
      <div class="lm-card-name">${escHtml(p.property_name)}</div>
      <div class="lm-card-pills">${typeBadge}${expBadge}</div>
      <button class="lm-remove-btn" data-property-id="${p.id}" title="Remove property"><i class="fa fa-xmark"></i></button>
    </div>
    <div class="lm-stats-row">
      <div class="lm-stat-block">
        <span class="lm-stat-label">Asset value</span>
        <span class="lm-balance">${escHtml(p.cost_fmt)}</span>
      </div>
    </div>
    <div class="lm-info-rows">
      ${expiryRow}
      ${descRow}
    </div>
  </div>`;
}

$('#properties-search').addEventListener('input', e => {
  propertiesSearchFilter = e.target.value;
  renderPropertyCards();
});

$('#properties-cards-area').addEventListener('click', async e => {
  const removeBtn = e.target.closest('.lm-remove-btn[data-property-id]');
  if (removeBtn) {
    const pid  = removeBtn.dataset.propertyId;
    const prop = _propertiesAll.find(p => String(p.id) === String(pid));
    if (!confirm(`Remove "${prop?.property_name || 'this property'}"? This cannot be undone.`)) return;
    removeBtn.innerHTML = '<i class="fa fa-spinner fa-spin"></i>';
    removeBtn.disabled = true;
    const res = await API.deleteProperty(pid);
    if (res.status === 200) {
      _propertiesAll = _propertiesAll.filter(p => String(p.id) !== String(pid));
      toast('Property removed', 'success');
      renderPropertyCards();
      $('#properties-stats-bar').style.display = _propertiesAll.length ? '' : 'none';
    } else {
      toast(res.body?.message || 'Failed to remove', 'error');
      removeBtn.innerHTML = '<i class="fa fa-xmark"></i>';
      removeBtn.disabled = false;
    }
    return;
  }
});

// ── Property Create Modal ─────────────────────────────────────────────────────

const pcClose = () => { $('#property-create-modal').style.display = 'none'; };
$('#property-modal-close').addEventListener('click', pcClose);
$('#property-cancel').addEventListener('click',      pcClose);
$('#property-create-modal').addEventListener('click', e => { if (e.target === $('#property-create-modal')) pcClose(); });
$('#btn-property-create').addEventListener('click', openPropertyCreateModal);
$('#property-submit').addEventListener('click', submitPropertyForm);

// Type-picker: clicking a tile sets the hidden input and toggles active state
$('#pf-type-picker').addEventListener('click', e => {
  const btn = e.target.closest('.pf-type-btn');
  if (!btn) return;
  $$('.pf-type-btn').forEach(b => b.classList.remove('active'));
  btn.classList.add('active');
  $('#pf-type').value = btn.dataset.value;
  const isOther = btn.dataset.value === 'other';
  $('#pf-other-wrap').style.display = isOther ? '' : 'none';
  if (!isOther) $('#pf-type-other').value = '';
  $('#property-modal-alert').style.display = 'none';
});

$('#pf-has-expiry').addEventListener('change', () => {
  $('#pf-expire-date-wrap').style.display = $('#pf-has-expiry').checked ? '' : 'none';
  if (!$('#pf-has-expiry').checked) $('#pf-expire-date').value = '';
});

function openPropertyCreateModal() {
  $('#pf-name').value            = '';
  $('#pf-type').value            = '';
  $('#pf-type-other').value      = '';
  $('#pf-cost').value            = '';
  $('#pf-description').value     = '';
  $('#pf-has-expiry').checked    = false;
  $('#pf-expire-date').value     = '';
  $('#pf-other-wrap').style.display       = 'none';
  $('#pf-expire-date-wrap').style.display = 'none';
  $('#property-modal-alert').style.display = 'none';
  $$('.pf-type-btn').forEach(b => b.classList.remove('active'));
  $('#property-create-modal').style.display = 'flex';
  setTimeout(() => $('#pf-name').focus(), 60);
}

async function submitPropertyForm() {
  const name        = $('#pf-name').value.trim();
  const type        = $('#pf-type').value;
  const typeOther   = $('#pf-type-other').value.trim();
  const cost        = $('#pf-cost').value.trim();
  const description = $('#pf-description').value.trim();
  const hasExpiry   = $('#pf-has-expiry').checked;
  const expireDate  = $('#pf-expire-date').value;

  if (!name)                                 { showPcError('Property name is required.'); return; }
  if (!type)                                 { showPcError('Select an asset type above.'); return; }
  if (type === 'other' && !typeOther)        { showPcError('Enter a custom type name.'); return; }
  if (!cost || isNaN(parseFloat(cost)))      { showPcError('Asset cost is required.'); return; }
  if (hasExpiry && !expireDate)              { showPcError('Enter the expiry date.'); return; }

  const body = {
    property_name: name,
    property_type: type,
    cost,
    has_expiry:   hasExpiry,
  };
  if (description)                body.description  = description;
  if (type === 'other')           body.property_type_other = typeOther;
  if (hasExpiry && expireDate)    body.expire_date  = expireDate;

  const btn = $('#property-submit');
  btn.disabled = true;
  btn.innerHTML = '<i class="fa fa-spinner fa-spin"></i> Saving…';

  const res = await API.createProperty(body);
  btn.disabled = false;
  btn.innerHTML = '<i class="fa fa-building"></i> Save Property';

  if (res.status === 201) {
    pcClose();
    toast('Property saved', 'success');
    await loadProperties();
  } else {
    const msg = res.body?.errors
      ? Object.values(res.body.errors).flat().join(' ')
      : (res.body?.message || `Error (${res.status})`);
    showPcError(msg);
  }
}

function showPcError(msg) {
  const el = $('#property-modal-alert');
  el.textContent = msg;
  el.style.display = '';
  el.scrollIntoView({ behavior: 'smooth', block: 'nearest' });
}

// ── Loan Panel ─────────────────────────────────────────────────────────────
const LOAN_REC_LABEL = { per_day: 'Daily', per_month: 'Monthly', per_year: 'Yearly' };

let loansSearchFilter = '';
let _loansAll = [];
let _loansStats = { active_count: 0, total_principal_fmt: '0.00', total_monthly_fmt: '0.00' };

async function loadLoans(search) {
  if (search !== undefined) loansSearchFilter = search;
  const area = $('#loans-cards-area');
  area.innerHTML = '<div class="finance-loading"><i class="fa fa-spinner fa-spin"></i> Loading loans…</div>';
  const res = await API.loans();
  if (res.status === 200 && res.body) {
    _loansAll   = res.body.data || [];
    _loansStats = {
      active_count:        res.body.active_count        ?? 0,
      total_principal_fmt: res.body.total_principal_fmt ?? '0.00',
      total_monthly_fmt:   res.body.total_monthly_fmt   ?? '0.00',
    };
  } else {
    _loansAll   = [];
    _loansStats = { active_count: 0, total_principal_fmt: '0.00', total_monthly_fmt: '0.00' };
  }
  renderLoanCards();
}

function renderLoanCards() {
  const area = $('#loans-cards-area');
  const q = loansSearchFilter.trim().toLowerCase();
  const list = q
    ? _loansAll.filter(l => (l.name||'').toLowerCase().includes(q) || (l.bank_name||'').toLowerCase().includes(q))
    : _loansAll;

  $('#loans-count').textContent = `${list.length} loan${list.length !== 1 ? 's' : ''}`;

  // stats bar
  const statsBar = $('#loans-stats-bar');
  if (_loansAll.length > 0) {
    $('#lm-active-count').textContent    = _loansStats.active_count;
    $('#lm-total-principal').textContent = _loansStats.total_principal_fmt;
    $('#lm-monthly-outflow').textContent = _loansStats.total_monthly_fmt;
    statsBar.style.display = '';
  } else {
    statsBar.style.display = 'none';
  }

  if (!list.length) {
    area.innerHTML = `
      <div class="finance-empty">
        <div class="finance-empty-icon"><i class="fa fa-hand-holding-dollar"></i></div>
        <p>${q ? 'No loans match your search' : 'No loans yet'}</p>
        <span>${q ? 'Try a different term' : 'Click Add Loan to record your first facility'}</span>
      </div>`;
    return;
  }
  area.innerHTML = list.map(buildLoanCard).join('');
}

function buildLoanCard(l) {
  const todayStr  = new Date().toISOString().slice(0, 10);
  const completed = l.loan_ending_date && l.loan_ending_date < todayStr;
  const cadence   = (l.cadence_label || LOAN_REC_LABEL[l.recurring_type] || 'Recurring').toUpperCase();
  const rateType  = l.interest_rate_type_label || (l.interest_rate_type === 'flat' ? 'Flat' : 'Percentage');
  const rateStr   = l.interest_rate_type === 'flat'
    ? `${l.interest_rate}  flat`
    : `${l.interest_rate}% APR`;

  // Status badge
  const statusBadge = completed
    ? `<span class="lm-badge lm-badge-done"><i class="fa fa-circle-check"></i> Completed</span>`
    : `<span class="lm-badge lm-badge-active"><i class="fa fa-circle"></i> Active</span>`;

  // Debit account block
  let debitHtml = '<span class="lm-info-val lm-muted">—</span>';
  if (l.account_name) {
    const parts = [escHtml(l.account_name)];
    if (l.account_number) parts.push(`<span class="lm-muted">${escHtml(l.account_number)}</span>`);
    if (l.account_bank_name) parts.push(`<span class="lm-muted">${escHtml(l.account_bank_name)}</span>`);
    if (l.account_type_name) parts.push(`<span class="lm-muted">${escHtml(l.account_type_name)}</span>`);
    if (l.account_balance != null) parts.push(`<span class="lm-balance">Bal. ${escHtml(l.account_balance)}</span>`);
    debitHtml = `<span class="lm-info-val lm-acct-val">${parts.join(' · ')}</span>`;
  }

  const schedFirst = l.first_installment_due_fmt || l.first_installment_due_date || '—';
  const schedLast  = l.loan_ending_date_fmt      || l.loan_ending_date           || '—';
  const schedStr   = schedLast !== '—' ? `${schedFirst} → ${schedLast}` : schedFirst;

  return `
    <div class="lm-card${completed ? ' lm-card-done' : ''}" data-loan-id="${l.id}">
      <div class="lm-card-header">
        <div class="lm-card-icon"><i class="fa fa-hand-holding-dollar"></i></div>
        <div class="lm-card-title-wrap">
          <span class="lm-card-name">${escHtml(l.name)}</span>
          <div class="lm-card-pills">
            ${statusBadge}
            ${l.bank_name ? `<span class="lm-pill lm-pill-bank"><i class="fa fa-building-columns"></i> ${escHtml(l.bank_name)}</span>` : ''}
            <span class="lm-pill lm-pill-cadence"><i class="fa fa-rotate"></i> ${cadence}</span>
          </div>
        </div>
        <button class="lm-remove-btn" data-loan-id="${l.id}" title="Remove loan">
          <i class="fa fa-trash"></i> Remove
        </button>
      </div>

      <div class="lm-stats-row">
        <div class="lm-stat-block">
          <span class="lm-stat-block-label">PRINCIPAL</span>
          <span class="lm-stat-block-val">${escHtml(l.borrowed_amount_fmt || parseFloat(l.borrowed_amount||0).toFixed(2))}</span>
        </div>
        <div class="lm-stat-block">
          <span class="lm-stat-block-label">PAYMENT &middot; PER PERIOD</span>
          <span class="lm-stat-block-val">${escHtml(l.payment_formatted || '—')}</span>
        </div>
        <div class="lm-stat-block">
          <span class="lm-stat-block-label">BUDGET &middot; MONTHLY EQUIV.</span>
          <span class="lm-stat-block-val">${escHtml(l.approx_monthly_formatted || '—')}</span>
        </div>
      </div>

      <div class="lm-info-rows">
        <div class="lm-info-row">
          <span class="lm-info-key"><i class="fa fa-percent"></i> Interest structure</span>
          <span class="lm-info-val">${escHtml(rateType)} &middot; ${escHtml(rateStr)}</span>
        </div>
        <div class="lm-info-row">
          <span class="lm-info-key"><i class="fa fa-list-ol"></i> Installments</span>
          <span class="lm-info-val">${l.period_count ?? '—'} &middot; <span class="lm-muted">${escHtml(l.period_source || '')}</span></span>
        </div>
        <div class="lm-info-row">
          <span class="lm-info-key"><i class="fa fa-calendar-days"></i> Schedule</span>
          <span class="lm-info-val">${escHtml(schedStr)}</span>
        </div>
        <div class="lm-info-row">
          <span class="lm-info-key"><i class="fa fa-building-columns"></i> Debit account</span>
          ${debitHtml}
        </div>
      </div>
    </div>`;
}

// Delete via event delegation
$('#loans-cards-area').addEventListener('click', async e => {
  const btn = e.target.closest('.lm-remove-btn[data-loan-id]');
  if (!btn) return;
  const id   = btn.dataset.loanId;
  const card = btn.closest('.lm-card');
  const name = card?.querySelector('.lm-card-name')?.textContent || 'this loan';
  if (!confirm(`Remove "${name}"? This cannot be undone.`)) return;
  btn.disabled = true;
  btn.innerHTML = '<i class="fa fa-spinner fa-spin"></i>';
  const res = await API.deleteLoan(id);
  if (res.status === 200) {
    card.style.transition = 'opacity .2s, transform .2s';
    card.style.opacity = '0';
    card.style.transform = 'translateX(12px)';
    setTimeout(() => {
      _loansAll = _loansAll.filter(l => String(l.id) !== String(id));
      renderLoanCards();
    }, 200);
    toast('Loan removed', 'success');
  } else {
    btn.disabled = false;
    btn.innerHTML = '<i class="fa fa-trash"></i> Remove';
    toast(res.body?.message || `Failed (${res.status})`, 'error');
  }
});

// Open detail page when clicking a card (but not the Remove button)
$('#loans-cards-area').addEventListener('click', e => {
  if (e.target.closest('.lm-remove-btn')) return;
  const card = e.target.closest('.lm-card[data-loan-id]');
  if (!card) return;
  const id = card.dataset.loanId;
  const l  = _loansAll.find(x => String(x.id) === String(id));
  if (l) openLoanDetailPage(l);
});

// ── Loan Detail Page ──────────────────────────────────────────────────────────

$('#loan-detail-back').addEventListener('click', () => {
  $('#loan-detail-view').style.display  = 'none';
  $('.fin-subnav').style.display        = '';
  $('#loans-list-view').style.display   = '';
});

// Tab switching
$$('#ld-inv-tabs .inv-tab').forEach(tab => {
  tab.addEventListener('click', () => {
    $$('#ld-inv-tabs .inv-tab').forEach(t => t.classList.remove('active'));
    tab.classList.add('active');
    $$('.inv-tab-pane[id^="ld-pane-"]').forEach(p => p.classList.toggle('active', p.id === `ld-pane-${tab.dataset.tab}`));
  });
});

function openLoanDetailPage(l) {
  const todayStr  = new Date().toISOString().slice(0, 10);
  const completed = l.loan_ending_date && l.loan_ending_date < todayStr;
  const cadence   = (l.cadence_label || LOAN_REC_LABEL[l.recurring_type] || 'Recurring').toUpperCase();
  const rateType  = l.interest_rate_type_label || (l.interest_rate_type === 'flat' ? 'Flat' : 'Percentage');
  const rateStr   = l.interest_rate_type === 'flat'
    ? `${l.interest_rate}  flat`
    : `${l.interest_rate}% APR`;

  // Show detail view, hide list + subnav
  $('.fin-subnav').style.display       = 'none';
  $('#loans-list-view').style.display  = 'none';
  $('#loan-detail-view').style.display = 'flex';

  // Reset to overview tab
  $$('#ld-inv-tabs .inv-tab').forEach(t => t.classList.toggle('active', t.dataset.tab === 'overview'));
  $$('.inv-tab-pane[id^="ld-pane-"]').forEach(p => p.classList.toggle('active', p.id === 'ld-pane-overview'));

  // Hero
  $('#ld-breadcrumb').textContent = l.name;
  $('#ld-hero-icon').innerHTML = `
    <div class="inv-thumb-ph inv-thumb-lg" style="background:#ede9fe;color:#7c3aed;font-size:28px">
      <i class="fa fa-hand-holding-dollar"></i>
    </div>`;
  $('#ld-hero-name').textContent = l.name;

  const metaParts = [];
  if (l.bank_name) metaParts.push(`<span><i class="fa fa-building-columns"></i> ${escHtml(l.bank_name)}</span>`);
  metaParts.push(`<span><i class="fa fa-rotate"></i> ${cadence}</span>`);
  $('#ld-hero-meta').innerHTML = metaParts.join('<span class="inv-sep">·</span>');

  const badges = [];
  if (completed)
    badges.push('<span class="inv-badge inv-badge-gray">Completed</span>');
  else
    badges.push('<span class="inv-badge inv-badge-green">Active</span>');
  badges.push(`<span class="inv-badge inv-badge-blue">${escHtml(cadence)}</span>`);
  $('#ld-hero-badges').innerHTML = badges.join('');

  // ── Overview pane ──────────────────────────────────────────────────────────
  const rowsHtml = rows => rows.map(([label, val]) =>
    `<tr><td class="inv-dt-label">${escHtml(label)}</td><td class="inv-dt-val">${val}</td></tr>`).join('');

  const payRows = [
    ['Principal',          `<strong>${escHtml(l.borrowed_amount_fmt || parseFloat(l.borrowed_amount||0).toFixed(2))}</strong>`],
    ['Payment / period',   `<strong>${escHtml(l.payment_formatted || '—')}</strong>`],
    ['Monthly equivalent', escHtml(l.approx_monthly_formatted || '—')],
    ['Cadence',            escHtml(cadence)],
    ['Installments',       `${l.period_count ?? '—'} <span style="color:var(--text-muted);font-size:11px">· ${escHtml(l.period_source || '')}</span>`],
  ];

  const schedRows = [];
  if (l.first_installment_due_fmt || l.first_installment_due_date)
    schedRows.push(['First installment', escHtml(l.first_installment_due_fmt || l.first_installment_due_date)]);
  if (l.loan_ending_date_fmt || l.loan_ending_date)
    schedRows.push(['Last installment',  escHtml(l.loan_ending_date_fmt || l.loan_ending_date)]);

  const interestRows = [
    ['Interest type', escHtml(rateType)],
    ['Rate',          escHtml(rateStr)],
  ];

  let acctSection = '';
  if (l.account_name) {
    const acctRows = [['Account', escHtml(l.account_name)]];
    if (l.account_number)   acctRows.push(['Account No.',  escHtml(l.account_number)]);
    if (l.account_bank_name) acctRows.push(['Bank',        escHtml(l.account_bank_name)]);
    if (l.account_type_name) acctRows.push(['Type',        escHtml(l.account_type_name)]);
    if (l.account_balance != null)
      acctRows.push(['Balance', `<strong>${escHtml(l.account_balance)}</strong>`]);
    acctSection = `
      <div class="inv-section" style="margin-top:14px">
        <div class="inv-section-title"><i class="fa fa-building-columns"></i> Debit account</div>
        <table class="inv-detail-table">${rowsHtml(acctRows)}</table>
      </div>`;
  }

  const descHtml = l.description
    ? `<div class="inv-section" style="margin-top:14px">
        <div class="inv-section-title"><i class="fa fa-align-left"></i> Notes</div>
        <div style="padding:10px 14px;font-size:12px;color:var(--text-muted);white-space:pre-wrap;line-height:1.6">${escHtml(l.description)}</div>
       </div>` : '';

  $('#ld-pane-overview').innerHTML = `
    <div class="inv-section">
      <div class="inv-section-title"><i class="fa fa-coins"></i> Payment overview</div>
      <table class="inv-detail-table">${rowsHtml(payRows)}</table>
    </div>
    ${schedRows.length ? `
    <div class="inv-section" style="margin-top:14px">
      <div class="inv-section-title"><i class="fa fa-calendar-days"></i> Schedule</div>
      <table class="inv-detail-table">${rowsHtml(schedRows)}</table>
    </div>` : ''}
    <div class="inv-section" style="margin-top:14px">
      <div class="inv-section-title"><i class="fa fa-percent"></i> Interest</div>
      <table class="inv-detail-table">${rowsHtml(interestRows)}</table>
    </div>
    ${acctSection}
    ${descHtml}
    <div class="inv-section" style="margin-top:14px">
      <div class="inv-section-title" style="color:#dc2626"><i class="fa fa-trash"></i> Remove loan</div>
      <div style="padding:12px 14px;display:flex;align-items:center;justify-content:space-between;gap:12px">
        <span style="font-size:12px;color:var(--text-muted);line-height:1.5">Removes this loan facility. Existing ledger rows in Transactions are kept for history.</span>
        <button class="bd-danger-btn" id="ld-delete-loan" style="flex-shrink:0">
          <i class="fa fa-trash"></i> Remove
        </button>
      </div>
    </div>`;

  $('#ld-delete-loan').addEventListener('click', async () => {
    if (!confirm(`Remove "${l.name}"? This cannot be undone.`)) return;
    const btn = $('#ld-delete-loan');
    btn.disabled = true; btn.innerHTML = '<i class="fa fa-spinner fa-spin"></i>';
    const res = await API.deleteLoan(l.id);
    if (res.status === 200) {
      _loansAll = _loansAll.filter(x => String(x.id) !== String(l.id));
      $('#loan-detail-back').click(); renderLoanCards();
      toast('Loan removed', 'success');
    } else {
      btn.disabled = false; btn.innerHTML = '<i class="fa fa-trash"></i> Remove';
      toast(res.body?.message || 'Failed to remove', 'error');
    }
  });

  // Schedule tab (load on first click)
  let _ldScheduleLoaded = false;
  $$('#ld-inv-tabs .inv-tab').forEach(tab => {
    if (tab.dataset.tab !== 'schedule') return;
    tab.addEventListener('click', () => {
      if (_ldScheduleLoaded) return;
      _ldScheduleLoaded = true;
      loadLoanScheduleTab(l.id);
    }, { once: false });
  });
  $('#ld-pane-schedule').innerHTML = `<div class="inv-pane-loading"><i class="fa fa-spinner fa-spin" style="font-size:22px;opacity:.4"></i></div>`;
}

async function loadLoanScheduleTab(loanId) {
  const pane = $('#ld-pane-schedule');
  pane.innerHTML = `<div class="inv-pane-loading"><i class="fa fa-spinner fa-spin" style="font-size:22px;opacity:.4"></i></div>`;
  const res = await API.loan(loanId);
  if (res.status !== 200) {
    pane.innerHTML = `<div class="inv-pane-empty"><i class="fa fa-circle-exclamation"></i><p>Could not load schedule.</p></div>`;
    return;
  }
  const data     = res.body?.data || {};
  const schedule = data.schedule || [];

  function statusBadge(row) {
    if (row.paid && row.paid_via_ledger)
      return `<span class="bd-sched-badge bd-sched-paid"><i class="fa fa-check-circle"></i> Paid</span>`;
    if (row.paid)
      return `<span class="bd-sched-badge bd-sched-paid"><i class="fa fa-check-circle"></i> Paid (external)</span>`;
    if (row.past_due_unpaid)
      return `<span class="bd-sched-badge bd-sched-overdue"><i class="fa fa-circle-exclamation"></i> Past due · unpaid</span>`;
    return `<span class="bd-sched-badge bd-sched-await"><i class="fa fa-circle-info"></i> Outstanding</span>`;
  }

  const schedRows = schedule.map(row => {
    const rowClass  = row.past_due_unpaid && !row.paid ? 'bd-sched-row-due' : '';
    const actionBtn = row.paid
      ? `<span style="color:var(--text-muted);font-size:11px">—</span>`
      : `<button class="ld-pay-btn"
           data-due="${escHtml(row.due_ymd)}"
           data-amt="${escHtml(row.amount_formatted)}">
           <i class="fa fa-money-bill-wave"></i> Make payment
         </button>`;
    return `<tr class="${rowClass}">
      <td class="bd-sched-num">${row.period}</td>
      <td class="bd-sched-cell">${escHtml(row.due_ymd)}</td>
      <td class="bd-sched-cell" style="font-variant-numeric:tabular-nums">${escHtml(row.amount_formatted)}</td>
      <td class="bd-sched-cell">${statusBadge(row)}</td>
      <td class="bd-sched-cell">${actionBtn}</td>
    </tr>`;
  }).join('');

  pane.innerHTML = schedule.length
    ? `<div class="inv-section">
        <div class="inv-section-title"><i class="fa fa-calendar-days"></i> Installment schedule</div>
        <div class="bd-sched-scroll">
          <table class="bd-sched-table">
            <thead><tr>
              <th class="bd-sched-num">#</th>
              <th>Due date</th>
              <th>Amount</th>
              <th>Status</th>
              <th>Action</th>
            </tr></thead>
            <tbody>${schedRows}</tbody>
          </table>
        </div>
       </div>`
    : `<div class="inv-pane-empty"><i class="fa fa-calendar-days"></i><p>No installment dates generated yet.</p></div>`;

  pane.addEventListener('click', e => {
    const btn = e.target.closest('.ld-pay-btn');
    if (!btn) return;
    openLoanPayModal(loanId, btn.dataset);
  });
}

// ── Loan installment payment modal ────────────────────────────────────────────

let _lpmAccounts = [];
let _lpmCurrentLoanId = null;

$('#lpm-close').addEventListener('click',      () => { $('#loan-pay-modal').style.display = 'none'; });
$('#loan-pay-modal').addEventListener('click', e => { if (e.target === $('#loan-pay-modal')) $('#loan-pay-modal').style.display = 'none'; });

// Toggle account picker visibility based on recording option
document.querySelectorAll('input[name="lpm-option"]').forEach(r => {
  r.addEventListener('change', () => {
    const isLedger = $('#lpm-opt-ledger').checked;
    $('#lpm-account-wrap').style.display = isLedger ? '' : 'none';
    $('#lpm-note').textContent = isLedger
      ? 'Creates a ledger row for this due date and reduces the selected account balance by the installment amount.'
      : 'Marks this installment as already paid. No ledger row or account deduction is created.';
  });
});

async function openLoanPayModal(loanId, rowData) {
  _lpmCurrentLoanId = loanId;
  const { due, amt } = rowData;

  $('#lpm-due-date').textContent = due || '—';
  $('#lpm-amount').textContent   = amt ? `LKR ${amt}` : '—';
  $('#lpm-opt-ledger').checked   = true;
  $('#lpm-account-wrap').style.display = '';
  $('#lpm-note').textContent = 'Creates a ledger row for this due date and reduces the selected account balance by the installment amount.';
  $('#lpm-error').style.display = 'none';
  $('#lpm-confirm-btn').disabled = false;
  $('#lpm-confirm-btn').innerHTML = '<i class="fa fa-circle-check"></i> Confirm payment';

  $('#loan-pay-modal').style.display = 'flex';

  // Load accounts if not cached
  const select = $('#lpm-account-select');
  if (_lpmAccounts.length === 0) {
    select.innerHTML = '<option value="">Loading…</option>';
    const res = await API.accounts();
    _lpmAccounts = res.status === 200 ? (res.body?.data || []) : [];
  }
  select.innerHTML = _lpmAccounts.length
    ? _lpmAccounts.map(a => `<option value="${a.id}">${escHtml(a.label || a.name || a.account_name || String(a.id))}</option>`).join('')
    : '<option value="">No accounts found</option>';
}

$('#lpm-confirm-btn').addEventListener('click', async () => {
  const isLedger = $('#lpm-opt-ledger').checked;
  const accountId = isLedger ? parseInt($('#lpm-account-select').value) : null;
  const errEl = $('#lpm-error');

  if (isLedger && !accountId) {
    errEl.textContent = 'Please select a debit account.';
    errEl.style.display = '';
    return;
  }
  errEl.style.display = 'none';

  const btn = $('#lpm-confirm-btn');
  btn.disabled = true;
  btn.innerHTML = '<i class="fa fa-spinner fa-spin"></i> Saving…';

  const body = {
    due_date:         $('#lpm-due-date').textContent,
    recording_option: isLedger ? 'ledger' : 'external',
    account_id:       accountId,
  };

  const res = await API.payLoan(_lpmCurrentLoanId, body);
  if (res.status === 200) {
    $('#loan-pay-modal').style.display = 'none';
    toast('Installment recorded', 'success');
    // Reload schedule pane
    const pane = $('#ld-pane-schedule');
    pane.innerHTML = `<div class="inv-pane-loading"><i class="fa fa-spinner fa-spin" style="font-size:22px;opacity:.4"></i></div>`;
    await loadLoanScheduleTab(_lpmCurrentLoanId);
  } else {
    const msg = res.body?.message || `Failed (${res.status})`;
    errEl.textContent = msg;
    errEl.style.display = '';
    btn.disabled = false;
    btn.innerHTML = '<i class="fa fa-circle-check"></i> Confirm payment';
  }
});

// Search
let loansSearchTimer;
$('#loans-search').addEventListener('input', e => {
  clearTimeout(loansSearchTimer);
  loansSearchFilter = e.target.value;
  loansSearchTimer = setTimeout(renderLoanCards, 250);
});

// ── Loan Create Modal ────────────────────────────────────────────────────────
const loanData = { banks: null, accounts: null };

$('#btn-loan-create').addEventListener('click', openLoanCreateModal);
$('#loan-modal-close').addEventListener('click', () => { $('#loan-modal').style.display = 'none'; });
$('#loan-cancel').addEventListener('click',      () => { $('#loan-modal').style.display = 'none'; });
$('#loan-modal').addEventListener('click', e => { if (e.target === $('#loan-modal')) $('#loan-modal').style.display = 'none'; });
$('#loan-submit').addEventListener('click', submitLoanForm);

// Live preview collapse toggle
$('#loan-lp-toggle').addEventListener('click', () => {
  const body = $('#loan-lp-body');
  const icon = $('#loan-lp-toggle').querySelector('i');
  const collapsed = body.style.display === 'none';
  body.style.display = collapsed ? '' : 'none';
  icon.className = collapsed ? 'fa fa-chevron-up' : 'fa fa-chevron-down';
});

// Auto-fill loan ending date when first-due or periods/cadence change
function autoFillEndingDate() {
  const firstDue = $('#loan-first-due').value;
  if (!firstDue) return;
  // Only auto-fill if ending date is empty (don't override user input)
  if ($('#loan-ending-date').value) return;
  const cadence  = $('#loan-recurring-type').value;
  const estN     = parseInt($('#loan-est-periods').value) ||
                   (cadence === 'per_month' ? 12 : cadence === 'per_day' ? 30 : 5);
  const d = new Date(firstDue + 'T00:00:00');
  if (cadence === 'per_month') d.setMonth(d.getMonth() + estN - 1);
  else if (cadence === 'per_year') d.setFullYear(d.getFullYear() + estN - 1);
  else d.setDate(d.getDate() + estN - 1);
  $('#loan-ending-date').value = d.toISOString().slice(0, 10);
}
$('#loan-first-due').addEventListener('change', () => { autoFillEndingDate(); updateLoanPreview(); });
$('#loan-ending-date').addEventListener('change', updateLoanPreview);

// Live preview calculator
['loan-amount','loan-interest-type','loan-interest-rate','loan-recurring-type','loan-est-periods'].forEach(id => {
  $('#' + id).addEventListener('input', updateLoanPreview);
});

function updateLoanPreview() {
  const amount   = parseFloat($('#loan-amount').value) || 0;
  const rate     = parseFloat($('#loan-interest-rate').value) || 0;
  const type     = $('#loan-interest-type').value;
  const cadence  = $('#loan-recurring-type').value;
  const fmt = n => (isFinite(n) && n > 0) ? n.toFixed(2) : '—';

  // Reset all tiles to —
  ['lp-payment','lp-principal','lp-interest','lp-total','lp-periods','lp-rate-period']
    .forEach(id => { $('#' + id).textContent = '—'; });

  if (!amount || !rate) return;

  const periodsPerYear = cadence === 'per_month' ? 12 : cadence === 'per_day' ? 365 : 1;
  const defaultN       = cadence === 'per_month' ? 12 : cadence === 'per_day' ? 30 : 5;
  const estN           = parseInt($('#loan-est-periods').value) || defaultN;

  let payment, totalInterest, totalRepayment, ratePeriod;

  if (type === 'flat') {
    payment        = rate;
    totalRepayment = payment * estN;
    totalInterest  = totalRepayment - amount;
    ratePeriod     = ((rate / amount) * 100);
  } else {
    // Standard amortization
    ratePeriod = (rate / 100) / periodsPerYear;
    if (ratePeriod === 0) return;
    const factor = Math.pow(1 + ratePeriod, estN);
    payment        = (amount * ratePeriod * factor) / (factor - 1);
    totalRepayment = payment * estN;
    totalInterest  = totalRepayment - amount;
    ratePeriod     = ratePeriod * 100;
  }

  // Auto-fill ending date if first due is set
  autoFillEndingDate();

  const cadLabel = cadence.replace('per_', '');
  $('#lp-payment').textContent    = fmt(payment);
  $('#lp-principal').textContent  = fmt(amount);
  $('#lp-interest').textContent   = fmt(totalInterest);
  $('#lp-total').textContent      = fmt(totalRepayment);
  $('#lp-periods').textContent    = `${estN} × ${cadLabel}`;
  $('#lp-rate-period').textContent = fmt(ratePeriod) + '%';
}

async function openLoanCreateModal() {
  ['loan-name','loan-description','loan-amount','loan-interest-rate',
   'loan-est-periods','loan-remind-days','loan-first-due','loan-ending-date']
    .forEach(id => { $('#' + id).value = ''; });
  $('#loan-interest-type').value  = 'percentage';
  $('#loan-recurring-type').value = 'per_month';
  // Reset preview tiles
  ['lp-payment','lp-principal','lp-interest','lp-total','lp-periods','lp-rate-period']
    .forEach(id => { $('#' + id).textContent = '—'; });
  // Ensure preview bar is expanded
  $('#loan-lp-body').style.display = '';
  $('#loan-lp-toggle').querySelector('i').className = 'fa fa-chevron-up';
  $('#loan-modal-alert').style.display = 'none';
  $('#loan-submit').disabled = false;
  $('#loan-submit').innerHTML = '<i class="fa fa-check"></i> Save loan';

  if (!loanData.banks || !loanData.accounts) {
    $('#loan-bank').innerHTML    = '<option value="">Loading…</option>';
    $('#loan-account').innerHTML = '<option value="">Loading…</option>';
    const [bankRes, acctRes] = await Promise.all([API.banks(), API.accounts()]);
    loanData.banks    = bankRes.status === 200 ? (bankRes.body?.data  || []) : [];
    loanData.accounts = acctRes.status === 200 ? (acctRes.body?.data  || []) : [];
  }

  $('#loan-bank').innerHTML = '<option value="">Select bank</option>' +
    loanData.banks.map(b => `<option value="${b.id}">${escHtml(b.name)}</option>`).join('');
  $('#loan-account').innerHTML = '<option value="">None &mdash; no deduction account</option>' +
    loanData.accounts.map(a => `<option value="${a.id}">${escHtml(a.name || a.account_name || String(a.id))}</option>`).join('');

  $('#loan-modal').style.display = 'flex';
  $('#loan-name').focus();
}

function showLoanAlert(msg) {
  const el = $('#loan-modal-alert');
  el.textContent = msg;
  el.style.display = 'block';
}

async function submitLoanForm() {
  const name         = $('#loan-name').value.trim();
  const bankId       = parseInt($('#loan-bank').value) || null;
  const description  = $('#loan-description').value.trim();
  const amount       = parseFloat($('#loan-amount').value) || 0;
  const intType      = $('#loan-interest-type').value;
  const intRate      = parseFloat($('#loan-interest-rate').value) || 0;
  const recurType    = $('#loan-recurring-type').value;
  const firstDue     = $('#loan-first-due').value;
  const endingDate   = $('#loan-ending-date').value;
  const accountId    = parseInt($('#loan-account').value) || null;
  const remindDays   = parseInt($('#loan-remind-days').value) || null;

  if (!name)   { showLoanAlert('Facility name is required'); $('#loan-name').focus(); return; }
  if (!bankId) { showLoanAlert('Select a lender bank'); $('#loan-bank').focus(); return; }
  if (!amount) { showLoanAlert('Enter the borrowed amount'); $('#loan-amount').focus(); return; }
  if (!intRate){ showLoanAlert('Enter the interest rate'); $('#loan-interest-rate').focus(); return; }

  $('#loan-modal-alert').style.display = 'none';
  const btn = $('#loan-submit');
  btn.disabled = true;
  btn.innerHTML = '<i class="fa fa-spinner fa-spin"></i> Saving…';

  const body = {
    name,
    bank_id:          bankId,
    borrowed_amount:  amount,
    interest_rate_type: intType,
    interest_rate:    intRate,
    recurring_type:   recurType,
  };
  if (description)  body.description               = description;
  if (firstDue)     body.first_installment_due_date = firstDue;
  if (endingDate)   body.loan_ending_date           = endingDate;
  if (accountId)    body.deduct_account_id          = accountId;
  if (remindDays)   body.remind_before_days         = remindDays;

  const res = await API.createLoan(body);
  btn.disabled = false;
  btn.innerHTML = '<i class="fa fa-check"></i> Save loan';

  if (res.status !== 200 && res.status !== 201) {
    let msg = res.body?.message || `Failed (${res.status})`;
    if (res.body?.errors) msg = Object.values(res.body.errors).flat().join(' ');
    showLoanAlert(msg);
    return;
  }

  $('#loan-modal').style.display = 'none';
  loanData.banks = null; loanData.accounts = null;
  toast('Loan added successfully', 'success');
  loadLoans();
}

// ── Utilities ──────────────────────────────────────────────────────────────
function escHtml(str) {
  return String(str).replace(/&/g,'&amp;').replace(/</g,'&lt;').replace(/>/g,'&gt;').replace(/"/g,'&quot;');
}

// ── Platform class ─────────────────────────────────────────────────────────
const isMac = window.electronAPI.platform === 'darwin';
if (isMac) {
  document.body.classList.add('macos');

  // Wire up HTML macOS traffic light buttons (main window)
  document.getElementById('tl-close').addEventListener('click', () => window.electronAPI.close());
  document.getElementById('tl-min').addEventListener('click',   () => window.electronAPI.minimize());
  document.getElementById('tl-max').addEventListener('click',   () => window.electronAPI.maximize());

  // Wire up login screen traffic lights
  const lMax = document.getElementById('l-max-login');
  if (lMax) lMax.addEventListener('click', () => window.electronAPI.maximize());

  // Dim traffic lights when window loses focus
  window.addEventListener('blur',  () => document.body.classList.add('win-inactive'));
  window.addEventListener('focus', () => document.body.classList.remove('win-inactive'));
}

// ── Modifications Panel ───────────────────────────────────────────────────

let modificationsSearchFilter = '';
let _modificationsAll   = [];
let _modificationsStats = { total_count: 0, total_cost_fmt: '0.00' };

const MOD_TYPE_LABEL = { renovation: 'Renovation', property: 'Property', other: 'Other' };

async function loadModifications(search) {
  if (search !== undefined) modificationsSearchFilter = search;
  const area = $('#modifications-cards-area');
  area.innerHTML = '<div class="finance-loading"><i class="fa fa-spinner fa-spin"></i> Loading modifications…</div>';
  const res = await API.modifications();
  if (res.status === 200 && res.body) {
    _modificationsAll   = res.body.data || [];
    _modificationsStats = {
      total_count:    res.body.total_count    ?? 0,
      total_cost_fmt: res.body.total_cost_fmt ?? '0.00',
    };
  } else {
    _modificationsAll   = [];
    _modificationsStats = { total_count: 0, total_cost_fmt: '0.00' };
  }
  renderModificationCards();
}

function renderModificationCards() {
  const area = $('#modifications-cards-area');
  const q    = modificationsSearchFilter.trim().toLowerCase();
  const list = q
    ? _modificationsAll.filter(m =>
        (m.name || '').toLowerCase().includes(q) ||
        (m.assignment_display || '').toLowerCase().includes(q) ||
        (m.assignment_type || '').toLowerCase().includes(q))
    : _modificationsAll;

  $('#modifications-count').textContent =
    `${list.length} modification${list.length !== 1 ? 's' : ''}`;

  const statsBar = $('#modifications-stats-bar');
  if (_modificationsAll.length > 0) {
    $('#mm-total-count').textContent = _modificationsStats.total_count;
    $('#mm-total-cost').textContent  = _modificationsStats.total_cost_fmt;
    statsBar.style.display = '';
  } else {
    statsBar.style.display = 'none';
  }

  if (!list.length) {
    area.innerHTML = `
      <div class="finance-empty">
        <div class="finance-empty-icon"><i class="fa fa-screwdriver-wrench"></i></div>
        <p>${q ? 'No modifications match your search' : 'No modifications yet'}</p>
        <span>${q ? 'Try a different term' : 'Click Add Modification to record your first one'}</span>
      </div>`;
    return;
  }
  area.innerHTML = list.map(buildModificationCard).join('');
}

function buildModificationCard(m) {
  const typeLabel = MOD_TYPE_LABEL[m.assignment_type] || m.assignment_type || '—';

  const refLine = m.assignment_display
    ? `<div class="lm-info-row">
        <span class="lm-info-key"><i class="fa fa-link"></i> Reference</span>
        <span class="lm-info-val">${escHtml(m.assignment_display)}</span>
       </div>`
    : '';

  const durationLine = m.duration
    ? `<div class="lm-info-row">
        <span class="lm-info-key"><i class="fa fa-clock"></i> Duration</span>
        <span class="lm-info-val">${escHtml(m.duration)}</span>
       </div>`
    : '';

  const billsBadge = m.bills_count > 0
    ? `<span class="lm-pill" style="background:color-mix(in srgb,var(--accent) 12%,transparent);color:var(--accent)">
         <i class="fa fa-file-invoice-dollar"></i> ${m.bills_count} bill${m.bills_count !== 1 ? 's' : ''}
       </span>`
    : '';

  return `
    <div class="lm-card mm-card" data-mod-id="${m.id}">
      <div class="lm-card-header">
        <div class="lm-card-icon" style="background:#f0fdf4;color:#16a34a;font-size:18px">
          <i class="fa fa-screwdriver-wrench"></i>
        </div>
        <div class="lm-card-title-wrap">
          <span class="lm-card-name">${escHtml(m.name)}</span>
          <div class="lm-card-pills">
            <span class="lm-pill lm-pill-cadence"><i class="fa fa-tag"></i> ${escHtml(typeLabel)}</span>
            ${m.work_type_label ? `<span class="lm-pill"><i class="fa fa-wrench"></i> ${escHtml(m.work_type_label)}</span>` : ''}
            ${billsBadge}
          </div>
        </div>
        <button class="lm-remove-btn" data-mod-id="${m.id}" title="Remove modification">
          <i class="fa fa-trash"></i> Remove
        </button>
      </div>

      <div class="lm-stats-row">
        <div class="lm-stat-block">
          <span class="lm-stat-block-label">EST. COST</span>
          <span class="lm-stat-block-val">${escHtml(m.estimated_cost_fmt || '—')}</span>
        </div>
        <div class="lm-stat-block">
          <span class="lm-stat-block-label">TYPE</span>
          <span class="lm-stat-block-val">${escHtml(typeLabel)}</span>
        </div>
        <div class="lm-stat-block">
          <span class="lm-stat-block-label">BILLS</span>
          <span class="lm-stat-block-val">${m.bills_count}</span>
        </div>
      </div>

      <div class="lm-info-rows">
        ${refLine}
        ${durationLine}
        ${m.description ? `<div class="lm-info-row">
          <span class="lm-info-key"><i class="fa fa-note-sticky"></i> Notes</span>
          <span class="lm-info-val lm-muted">${escHtml(m.description.slice(0, 80))}${m.description.length > 80 ? '…' : ''}</span>
        </div>` : ''}
      </div>
    </div>`;
}

// Card area click — delete or open detail
$('#modifications-cards-area').addEventListener('click', async e => {
  if (e.target.closest('.lm-remove-btn[data-mod-id]')) {
    const btn  = e.target.closest('.lm-remove-btn');
    const id   = btn.dataset.modId;
    const card = btn.closest('.mm-card');
    const name = card?.querySelector('.lm-card-name')?.textContent || 'this modification';
    if (!confirm(`Remove "${name}"? This cannot be undone.`)) return;
    btn.disabled = true; btn.innerHTML = '<i class="fa fa-spinner fa-spin"></i>';
    const res = await API.deleteModification(id);
    if (res.status === 200) {
      card.style.transition = 'opacity .2s, transform .2s';
      card.style.opacity = '0'; card.style.transform = 'translateX(12px)';
      setTimeout(() => {
        _modificationsAll = _modificationsAll.filter(x => String(x.id) !== String(id));
        renderModificationCards();
      }, 200);
      toast('Modification removed', 'success');
    } else {
      btn.disabled = false; btn.innerHTML = '<i class="fa fa-trash"></i> Remove';
      toast(res.body?.message || `Failed (${res.status})`, 'error');
    }
    return;
  }
  const card = e.target.closest('.mm-card[data-mod-id]');
  if (!card) return;
  const m = _modificationsAll.find(x => String(x.id) === String(card.dataset.modId));
  if (m) openModificationDetailPage(m);
});

// Search
let _modSearchTimer;
$('#modifications-search').addEventListener('input', e => {
  clearTimeout(_modSearchTimer);
  modificationsSearchFilter = e.target.value;
  _modSearchTimer = setTimeout(renderModificationCards, 250);
});

// ── Modification Detail Page ──────────────────────────────────────────────

$('#modification-detail-back').addEventListener('click', () => {
  $('#modification-detail-view').style.display  = 'none';
  $('.fin-subnav').style.display                = '';
  $('#modifications-list-view').style.display   = '';
});

$$('#md-inv-tabs .inv-tab').forEach(tab => {
  tab.addEventListener('click', () => {
    $$('#md-inv-tabs .inv-tab').forEach(t => t.classList.remove('active'));
    tab.classList.add('active');
    $$('.inv-tab-pane[id^="md-pane-"]').forEach(p =>
      p.classList.toggle('active', p.id === `md-pane-${tab.dataset.tab}`));
  });
});

function openModificationDetailPage(m) {
  const typeLabel = MOD_TYPE_LABEL[m.assignment_type] || m.assignment_type || '—';

  $('.fin-subnav').style.display             = 'none';
  $('#modifications-list-view').style.display= 'none';
  $('#modification-detail-view').style.display = 'flex';

  $$('#md-inv-tabs .inv-tab').forEach(t => t.classList.toggle('active', t.dataset.tab === 'overview'));
  $$('.inv-tab-pane[id^="md-pane-"]').forEach(p => p.classList.toggle('active', p.id === 'md-pane-overview'));

  $('#md-breadcrumb').textContent = m.name || 'Modification';
  $('#md-hero-icon').innerHTML = `
    <div class="inv-thumb-ph inv-thumb-lg" style="background:#f0fdf4;color:#16a34a;font-size:28px">
      <i class="fa fa-screwdriver-wrench"></i>
    </div>`;
  $('#md-hero-name').textContent = m.name || '—';

  const metaParts = [];
  metaParts.push(`<span><i class="fa fa-tag"></i> ${escHtml(typeLabel)}</span>`);
  if (m.assignment_display) metaParts.push(`<span><i class="fa fa-link"></i> ${escHtml(m.assignment_display)}</span>`);
  $('#md-hero-meta').innerHTML = metaParts.join('<span class="inv-sep">·</span>');

  const badges = [];
  badges.push(`<span class="inv-badge inv-badge-green">${escHtml(typeLabel)}</span>`);
  if (m.work_type_label) badges.push(`<span class="inv-badge inv-badge-blue">${escHtml(m.work_type_label)}</span>`);
  if (m.bills_count > 0) badges.push(`<span class="inv-badge inv-badge-gray">${m.bills_count} bill${m.bills_count !== 1 ? 's' : ''}</span>`);
  $('#md-hero-badges').innerHTML = badges.join('');

  const rowsHtml = rows => rows.map(([label, val]) =>
    `<tr><td class="inv-dt-label">${escHtml(label)}</td><td class="inv-dt-val">${val}</td></tr>`).join('');

  const detailRows = [
    ['Assign to',     escHtml(typeLabel)],
    ['Estimated cost', `<strong>${escHtml(m.estimated_cost_fmt || '—')}</strong>`],
  ];
  if (m.assignment_display) detailRows.push(['Reference',  escHtml(m.assignment_display)]);
  if (m.work_type_label)    detailRows.push(['Work type',  escHtml(m.work_type_label)]);
  if (m.duration)           detailRows.push(['Duration',   escHtml(m.duration)]);
  if (m.created_at)         detailRows.push(['Created',    escHtml(m.created_at)]);

  const notesSection = m.description
    ? `<div class="inv-section" style="margin-top:14px">
        <div class="inv-section-title"><i class="fa fa-note-sticky"></i> Description</div>
        <div style="padding:10px 14px;font-size:12px;color:var(--text-muted);white-space:pre-wrap;line-height:1.6">${escHtml(m.description)}</div>
       </div>` : '';

  $('#md-pane-overview').innerHTML = `
    <div class="inv-section">
      <div class="inv-section-title"><i class="fa fa-screwdriver-wrench"></i> Modification details</div>
      <table class="inv-detail-table">${rowsHtml(detailRows)}</table>
    </div>
    ${notesSection}
    <div class="inv-section" style="margin-top:14px">
      <div class="inv-section-title" style="color:#dc2626"><i class="fa fa-trash"></i> Remove modification</div>
      <div style="padding:12px 14px;display:flex;align-items:center;justify-content:space-between;gap:12px">
        <span style="font-size:12px;color:var(--text-muted);line-height:1.5">Removes this modification record. Bills linked to it will be unlinked.</span>
        <button class="bd-danger-btn" id="md-delete-btn" style="flex-shrink:0">
          <i class="fa fa-trash"></i> Remove
        </button>
      </div>
    </div>`;

  $('#md-delete-btn').addEventListener('click', async () => {
    if (!confirm(`Remove "${m.name}"? This cannot be undone.`)) return;
    const btn = $('#md-delete-btn');
    btn.disabled = true; btn.innerHTML = '<i class="fa fa-spinner fa-spin"></i>';
    const res = await API.deleteModification(m.id);
    if (res.status === 200) {
      _modificationsAll = _modificationsAll.filter(x => String(x.id) !== String(m.id));
      $('#modification-detail-back').click();
      renderModificationCards();
      toast('Modification removed', 'success');
    } else {
      btn.disabled = false; btn.innerHTML = '<i class="fa fa-trash"></i> Remove';
      toast(res.body?.message || 'Failed to remove', 'error');
    }
  });

  // Lazy-load bills tab
  let _mdBillsLoaded = false;
  $('#md-pane-bills').innerHTML =
    `<div class="inv-pane-loading"><i class="fa fa-spinner fa-spin" style="font-size:22px;opacity:.4"></i></div>`;

  $$('#md-inv-tabs .inv-tab').forEach(tab => {
    tab.addEventListener('click', () => {
      if (tab.dataset.tab === 'bills' && !_mdBillsLoaded) {
        _mdBillsLoaded = true;
        loadModificationBillsTab(m.id);
      }
    });
  });
}

async function loadModificationBillsTab(modId) {
  const pane = $('#md-pane-bills');
  pane.innerHTML = `<div class="inv-pane-loading"><i class="fa fa-spinner fa-spin" style="font-size:22px;opacity:.4"></i></div>`;
  const res = await API.modification(modId);
  if (res.status !== 200) {
    pane.innerHTML = `<div class="inv-pane-empty"><i class="fa fa-circle-exclamation"></i><p>Could not load linked bills.</p></div>`;
    return;
  }
  const bills = res.body?.data?.bills || [];
  if (!bills.length) {
    pane.innerHTML = `<div class="inv-pane-empty">
      <i class="fa fa-file-invoice-dollar"></i>
      <p>No bills linked to this modification yet.</p>
      <span>When adding or editing a bill in the web panel, set the assignment to <strong>Modification</strong> and choose this record.</span>
    </div>`;
    return;
  }
  const rows = bills.map(b => `
    <tr>
      <td class="bd-sched-cell" style="font-weight:600">${escHtml(b.name || '—')}</td>
      <td class="bd-sched-cell">${escHtml(b.category_label || '—')}</td>
      <td class="bd-sched-cell">${escHtml(b.payment_mode_label || '—')}</td>
      <td class="bd-sched-cell" style="font-variant-numeric:tabular-nums">${escHtml(b.recurring_cost_fmt || '—')}</td>
      <td class="bd-sched-cell">${escHtml(b.due_date || '—')}</td>
    </tr>`).join('');

  pane.innerHTML = `
    <div class="inv-section">
      <div class="inv-section-title"><i class="fa fa-file-invoice-dollar"></i> Linked bills (${bills.length})</div>
      <div class="bd-sched-scroll">
        <table class="bd-sched-table">
          <thead><tr>
            <th>Bill</th><th>Category</th><th>Payment</th><th>Amount</th><th>Due date</th>
          </tr></thead>
          <tbody>${rows}</tbody>
        </table>
      </div>
    </div>`;
}

// ── Modification Create Modal ─────────────────────────────────────────────

function mcSyncRefFields() {
  const type = $('#mc-assignment-type').value;
  $('#mc-ref-renovation-wrap').style.display       = type === 'renovation' ? '' : 'none';
  $('#mc-ref-property-wrap').style.display         = type === 'property'   ? '' : 'none';
  $('#mc-ref-other-wrap').style.display            = type === 'other'      ? '' : 'none';
  // renovation custom input
  const isCustomReno = type === 'renovation' && $('#mc-renovation-ref').value === 'other_custom';
  $('#mc-ref-renovation-custom-wrap').style.display = isCustomReno ? '' : 'none';
  // property work type other
  const isOtherWork = type === 'property' && $('#mc-property-worktype').value === 'other';
  $('#mc-ref-property-other-wrap').style.display   = isOtherWork ? '' : 'none';
}

$('#mc-assignment-type').addEventListener('change', mcSyncRefFields);
$('#mc-renovation-ref').addEventListener('change', mcSyncRefFields);
$('#mc-property-worktype').addEventListener('change', mcSyncRefFields);

const mcClose = () => { $('#modification-create-modal').style.display = 'none'; };
$('#mc-modal-close').addEventListener('click', mcClose);
$('#mc-cancel').addEventListener('click',      mcClose);
$('#modification-create-modal').addEventListener('click', e => {
  if (e.target === $('#modification-create-modal')) mcClose();
});

$('#btn-modification-create').addEventListener('click', openModificationCreateModal);

function openModificationCreateModal() {
  ['mc-name', 'mc-estimated-cost', 'mc-duration', 'mc-description',
   'mc-renovation-custom', 'mc-property-worktype-other', 'mc-other-ref'].forEach(id => {
    const el = $('#' + id); if (el) el.value = '';
  });
  $('#mc-assignment-type').value  = 'renovation';
  $('#mc-renovation-ref').value   = '';
  $('#mc-property-worktype').value = 'repair';
  $('#mc-modal-alert').style.display = 'none';
  $('#mc-submit').disabled = false;
  $('#mc-submit').innerHTML = '<i class="fa fa-check"></i> Save modification';
  mcSyncRefFields();
  $('#modification-create-modal').style.display = 'flex';
  setTimeout(() => $('#mc-name').focus(), 80);
}

$('#mc-submit').addEventListener('click', submitModificationForm);

async function submitModificationForm() {
  const alertEl = $('#mc-modal-alert');
  alertEl.style.display = 'none';

  const name  = $('#mc-name').value.trim();
  const type  = $('#mc-assignment-type').value;
  const cost  = $('#mc-estimated-cost').value;

  if (!name) { showMcError('Name is required.'); return; }
  if (!cost || isNaN(parseFloat(cost))) { showMcError('Estimated cost is required.'); return; }

  // Build assignment_reference
  let assignmentReference = null;
  let propertyWorkType    = null;
  let propertyWorkTypeOther = null;

  if (type === 'renovation') {
    const sel = $('#mc-renovation-ref').value;
    if (!sel) { showMcError('Select a renovation type.'); return; }
    if (sel === 'other_custom') {
      const custom = $('#mc-renovation-custom').value.trim();
      if (!custom) { showMcError('Enter a custom renovation description.'); return; }
      assignmentReference = custom;
    } else {
      assignmentReference = sel;
    }
  } else if (type === 'property') {
    propertyWorkType = $('#mc-property-worktype').value;
    if (propertyWorkType === 'other') {
      propertyWorkTypeOther = $('#mc-property-worktype-other').value.trim();
      if (!propertyWorkTypeOther) { showMcError('Describe the other work type.'); return; }
    }
  } else {
    assignmentReference = $('#mc-other-ref').value.trim() || null;
  }

  const body = {
    name:                     name,
    assignment_type:          type,
    assignment_reference:     assignmentReference,
    property_work_type:       propertyWorkType,
    property_work_type_other: propertyWorkTypeOther,
    estimated_cost:           parseFloat(cost),
    duration:                 $('#mc-duration').value.trim() || null,
    description:              $('#mc-description').value.trim() || null,
  };

  const btn = $('#mc-submit');
  btn.disabled = true;
  btn.innerHTML = '<i class="fa fa-spinner fa-spin"></i> Saving…';

  const res = await API.createModification(body);
  if (res.status === 201) {
    mcClose();
    toast('Modification added', 'success');
    await loadModifications();
  } else {
    const errData = res.body;
    const msg = errData?.errors
      ? Object.values(errData.errors)[0]?.[0] || errData.message || `Failed (${res.status})`
      : errData?.message || `Failed (${res.status})`;
    showMcError(msg);
    btn.disabled = false;
    btn.innerHTML = '<i class="fa fa-check"></i> Save modification';
  }
}

function showMcError(msg) {
  const el = $('#mc-modal-alert');
  el.textContent = msg;
  el.style.display = '';
  el.scrollIntoView({ behavior: 'smooth', block: 'nearest' });
}

// ── HR Panel ───────────────────────────────────────────────────────────────

function switchHrView(view) {
  $$('#panel-hr > .fin-subnav .fin-subnav-btn[data-hr]').forEach(b => b.classList.toggle('active', b.dataset.hr === view));
  $('#employees-list-view').style.display       = view === 'employees'   ? 'flex' : 'none';
  $('#employee-detail-view').style.display      = 'none';
  $('#departments-list-view').style.display     = view === 'departments' ? 'flex' : 'none';
  $('#payroll-list-view').style.display         = view === 'payroll'     ? 'flex' : 'none';
  $('#payroll-cycle-detail-view').style.display = 'none';
  $('#rule-sets-list-view').style.display       = view === 'rule-sets'       ? 'flex' : 'none';
  $('#rule-set-detail-view').style.display      = 'none';
  $('#template-page-view').style.display        = 'none';
  $('#allowance-types-view').style.display      = view === 'allowance-types' ? 'flex' : 'none';
  if (view === 'employees')        loadEmployees();
  if (view === 'departments')      loadDepartments();
  if (view === 'payroll')          loadPayrollCycles();
  if (view === 'rule-sets')        loadRuleSets();
  if (view === 'allowance-types')  loadAllowanceTypes();
}

$$('#panel-hr .fin-subnav-btn[data-hr]').forEach(btn => {
  btn.addEventListener('click', () => switchHrView(btn.dataset.hr));
});

$('#rb-employees').addEventListener('click',   () => { activateTab('hr'); switchHrView('employees'); });
$('#rb-departments').addEventListener('click', () => { activateTab('hr'); switchHrView('departments'); });
$('#rb-hr-payroll').addEventListener('click',  () => { activateTab('hr'); switchHrView('payroll'); });

// ── Employees list ──────────────────────────────────────────────────────────

let hrEmployees = [];

async function loadEmployees(search) {
  const area   = $('#emp-cards-area');
  const footer = $('#emp-footer-text');
  area.innerHTML = '<p style="color:var(--text-muted);padding:20px 0"><i class="fa fa-spinner fa-spin"></i> Loading employees…</p>';

  const res = await API.employees();
  if (res.status !== 200) {
    area.innerHTML = '<p style="color:#e74c3c"><i class="fa fa-circle-exclamation"></i> Failed to load employees.</p>';
    return;
  }

  const body = res.body;
  hrEmployees = body.data || [];

  $('#emp-total-count').textContent     = body.total_count     ?? hrEmployees.length;
  $('#emp-full-time-count').textContent = body.full_time_count ?? '—';
  $('#emp-part-time-count').textContent = body.part_time_count ?? '—';
  $('#emp-contract-count').textContent  = body.contract_count  ?? '—';

  const q = (search || '').toLowerCase().trim();
  const filtered = q
    ? hrEmployees.filter(e => (e.name || '').toLowerCase().includes(q) || (e.employee_id || '').toLowerCase().includes(q) || (e.department || '').toLowerCase().includes(q))
    : hrEmployees;

  renderEmployeeCards(filtered);
  footer.textContent = `${filtered.length} employee${filtered.length !== 1 ? 's' : ''}`;
}

function renderEmployeeCards(list) {
  const area = $('#emp-cards-area');
  if (!list.length) {
    area.innerHTML = '<p style="color:var(--text-muted);text-align:center;padding:40px 0"><i class="fa fa-users" style="font-size:32px;display:block;margin-bottom:12px;opacity:.3"></i>No employees found.</p>';
    return;
  }
  area.innerHTML = list.map(e => buildEmployeeCard(e)).join('');
  area.querySelectorAll('.lm-card').forEach(card => {
    card.addEventListener('click', () => {
      const id = parseInt(card.dataset.id, 10);
      const emp = hrEmployees.find(e => e.id === id);
      if (emp) openEmployeeDetailPage(emp);
    });
  });
}

function buildEmployeeCard(e) {
  const typeColor = { full_time: '#4caf7d', part_time: '#f7a54e', contract: '#9c6ef7' };
  const color = typeColor[e.employment_type] || '#90a4ae';
  const meta = [
    e.employee_id ? `#${escHtml(e.employee_id)}` : '',
    e.department  ? escHtml(e.department)  : '',
    e.job_title   ? escHtml(e.job_title)   : '',
  ].filter(Boolean).join(' · ');
  return `
    <div class="lm-card" data-id="${e.id}" style="cursor:pointer">
      <div class="lm-card-header">
        <div class="lm-card-icon" style="background:${color}20;color:${color}"><i class="fa fa-user"></i></div>
        <div class="lm-card-title-wrap">
          <span class="lm-card-name">${escHtml(e.name || '—')}</span>
          <div class="lm-card-pills">
            <span class="lm-pill" style="background:${color}18;color:${color}">${escHtml(e.type_label || e.employment_type || '—')}</span>
            ${meta ? `<span class="lm-pill" style="background:var(--sidebar-bg);color:var(--text-muted)">${meta}</span>` : ''}
          </div>
        </div>
        ${e.basic_salary_fmt ? `<span style="font-size:13px;font-weight:700;color:var(--accent);margin-left:auto;white-space:nowrap">$${escHtml(e.basic_salary_fmt)}</span>` : ''}
      </div>
    </div>`;
}

$('#emp-search').addEventListener('input', e => loadEmployees(e.target.value));

$('#emp-add-btn').addEventListener('click', openEmpCreateModal);

// ── Employee detail page ────────────────────────────────────────────────────

async function openEmployeeDetailPage(emp) {
  $('#employees-list-view').style.display   = 'none';
  $('#departments-list-view').style.display = 'none';
  $('#employee-detail-view').style.display  = 'flex';

  const typeColor = { full_time: '#4caf7d', part_time: '#f7a54e', contract: '#9c6ef7' };
  const color = typeColor[emp.employment_type] || '#90a4ae';

  $('#emp-detail-name').textContent        = emp.name || '—';
  $('#emp-detail-breadcrumb').textContent  = emp.name || 'Employee Detail';
  $('#emp-detail-meta').textContent        = [emp.employee_id ? '#' + emp.employee_id : '', emp.date_of_joining || ''].filter(Boolean).join(' · ');
  $('#emp-detail-badges').innerHTML = [
    `<span class="lm-badge" style="background:${color}20;color:${color};border:1px solid ${color}40">${escHtml(emp.type_label || emp.employment_type || '—')}</span>`,
    emp.department ? `<span class="lm-badge" style="background:var(--sidebar-bg);color:var(--text-muted)">${escHtml(emp.department)}</span>` : '',
    emp.job_title  ? `<span class="lm-badge" style="background:var(--sidebar-bg);color:var(--text-muted)">${escHtml(emp.job_title)}</span>`  : '',
  ].join('');

  // Overview pane
  const ovPane = $('#emp-pane-overview');
  ovPane.innerHTML = `
    <div style="padding:20px;display:grid;grid-template-columns:1fr 1fr;gap:16px">
      ${empDetailRow('Employee ID', '#' + (emp.employee_id || '—'))}
      ${empDetailRow('Employment Type', emp.type_label || '—')}
      ${empDetailRow('Department', emp.department || '—')}
      ${empDetailRow('Job Title', emp.job_title || '—')}
      ${empDetailRow('Basic Salary', emp.basic_salary_fmt ? '$' + emp.basic_salary_fmt : '—')}
      ${empDetailRow('Date of Joining', emp.date_of_joining || '—')}
      ${empDetailRow('Phone', emp.phone_number || '—')}
    </div>`;

  // Switch to overview tab
  $$('#employee-detail-view .inv-tab').forEach(t => t.classList.remove('active'));
  $$('#employee-detail-view .inv-tab-pane').forEach(p => p.classList.remove('active'));
  $('#employee-detail-view .inv-tab[data-tab="emp-overview"]').classList.add('active');
  $('#emp-pane-overview').classList.add('active');
  $('#emp-pane-personal').innerHTML = '';

  // Lazy-load personal details
  loadEmployeePersonalTab(emp.id);
}

function empDetailRow(label, value) {
  return `
    <div style="background:var(--bg-card,var(--sidebar-bg));border-radius:8px;padding:14px 16px">
      <div style="font-size:11px;font-weight:600;color:var(--text-muted);text-transform:uppercase;letter-spacing:.06em;margin-bottom:4px">${label}</div>
      <div style="font-size:15px;font-weight:600;color:var(--text)">${value}</div>
    </div>`;
}

async function loadEmployeePersonalTab(empId) {
  const pane = $('#emp-pane-personal');
  pane.innerHTML = '<p style="padding:20px;color:var(--text-muted)"><i class="fa fa-spinner fa-spin"></i> Loading…</p>';
  const res = await API.employee(empId);
  if (res.status !== 200) {
    pane.innerHTML = '<p style="padding:20px;color:#e74c3c"><i class="fa fa-circle-exclamation"></i> Failed to load details.</p>';
    return;
  }
  const e = res.body.data || {};
  pane.innerHTML = `
    <div style="padding:20px;display:grid;grid-template-columns:1fr 1fr;gap:16px">
      ${empDetailRow('Personal Email',    e.personal_email    || '—')}
      ${empDetailRow('Permanent Address', e.permanent_address || '—')}
      ${empDetailRow('EPF Number',        e.epf_number        || '—')}
      ${empDetailRow('ETF Number',        e.etf_number        || '—')}
    </div>`;
}

$('#emp-back-btn').addEventListener('click', () => {
  $('#employee-detail-view').style.display = 'none';
  $('#employees-list-view').style.display  = 'flex';
});

$$('#employee-detail-view .inv-tab').forEach(tab => {
  tab.addEventListener('click', () => {
    $$('#employee-detail-view .inv-tab').forEach(t => t.classList.remove('active'));
    $$('#employee-detail-view .inv-tab-pane').forEach(p => p.classList.remove('active'));
    tab.classList.add('active');
    const paneId = tab.dataset.tab === 'emp-overview' ? 'emp-pane-overview' : 'emp-pane-personal';
    $('#' + paneId).classList.add('active');
  });
});

// ── Departments list ────────────────────────────────────────────────────────

let hrDepartments = [];

async function loadDepartments(search) {
  const area   = $('#dept-cards-area');
  const footer = $('#dept-footer-text');
  area.innerHTML = '<p style="color:var(--text-muted);padding:20px 0"><i class="fa fa-spinner fa-spin"></i> Loading departments…</p>';

  const res = await API.departments();
  if (res.status !== 200) {
    area.innerHTML = '<p style="color:#e74c3c"><i class="fa fa-circle-exclamation"></i> Failed to load departments.</p>';
    return;
  }

  hrDepartments = res.body.data || [];

  const q = (search || '').toLowerCase().trim();
  const filtered = q ? hrDepartments.filter(d => d.name.toLowerCase().includes(q)) : hrDepartments;

  $('#dept-total-count').textContent = hrDepartments.length;

  if (!filtered.length) {
    area.innerHTML = '<p style="color:var(--text-muted);text-align:center;padding:40px 0"><i class="fa fa-sitemap" style="font-size:32px;display:block;margin-bottom:12px;opacity:.3"></i>No departments found.</p>';
    footer.textContent = '0 departments';
    return;
  }

  area.innerHTML = filtered.map(d => buildDepartmentCard(d)).join('');
  footer.textContent = `${filtered.length} department${filtered.length !== 1 ? 's' : ''}`;

  area.querySelectorAll('.dept-delete-btn').forEach(btn => {
    btn.addEventListener('click', async (e) => {
      e.stopPropagation();
      const id   = parseInt(btn.dataset.id, 10);
      const name = btn.dataset.name;
      if (!confirm(`Delete department "${name}"? This cannot be undone.`)) return;
      btn.disabled = true;
      btn.innerHTML = '<i class="fa fa-spinner fa-spin"></i>';
      const r = await API.deleteDepartment(id);
      if (r.status === 200) {
        toast('Department deleted', 'success');
        await loadDepartments($('#dept-search').value);
      } else {
        toast(r.body?.message || 'Failed to delete department', 'error');
        btn.disabled = false;
        btn.innerHTML = '<i class="fa fa-trash"></i>';
      }
    });
  });
}

function buildDepartmentCard(d) {
  const salRange = (d.salary_range_min && d.salary_range_max)
    ? `${d.salary_range_min} – ${d.salary_range_max}`
    : d.salary_range_min ? `From ${d.salary_range_min}` : '';
  const canDelete = (d.employees_count || 0) === 0;
  return `
    <div class="lm-card" style="cursor:default">
      <div class="lm-card-header">
        <div class="lm-card-icon" style="background:#9c6ef720;color:#9c6ef7"><i class="fa fa-sitemap"></i></div>
        <div class="lm-card-title-wrap">
          <span class="lm-card-name">${escHtml(d.name || '—')}</span>
          <div class="lm-card-pills">
            <span class="lm-pill" style="background:#9c6ef718;color:#9c6ef7">${d.employees_count || 0} employee${(d.employees_count || 0) !== 1 ? 's' : ''}</span>
            ${salRange ? `<span class="lm-pill" style="background:var(--sidebar-bg);color:var(--text-muted)">${escHtml(salRange)}</span>` : ''}
          </div>
        </div>
        ${canDelete
          ? `<button class="lm-remove-btn dept-delete-btn" data-id="${d.id}" data-name="${escHtml(d.name)}" title="Delete department"><i class="fa fa-trash"></i></button>`
          : `<span style="font-size:11px;color:var(--text-muted);white-space:nowrap;padding-right:4px">In use</span>`}
      </div>
    </div>`;
}

$('#dept-search').addEventListener('input', e => loadDepartments(e.target.value));
$('#dept-add-btn').addEventListener('click', openDeptCreateModal);

// ── Create Department Modal ─────────────────────────────────────────────────

function openDeptCreateModal() {
  $('#dc-name').value = '';
  showDcError('');
  $('#dept-create-modal').style.display = '';
  setTimeout(() => $('#dc-name').focus(), 60);
}

function closeDeptCreateModal() {
  $('#dept-create-modal').style.display = 'none';
}

$('#dept-create-modal-close').addEventListener('click', closeDeptCreateModal);
$('#dept-create-modal-cancel').addEventListener('click', closeDeptCreateModal);
$('#dept-create-modal').addEventListener('click', e => { if (e.target === $('#dept-create-modal')) closeDeptCreateModal(); });

$('#dc-name').addEventListener('keydown', e => { if (e.key === 'Enter') submitDeptCreate(); });

$('#dept-create-modal-save').addEventListener('click', submitDeptCreate);

function showDcError(msg) {
  const el = $('#dept-create-alert');
  el.textContent = msg;
  el.style.display = msg ? '' : 'none';
}

async function submitDeptCreate() {
  const name = $('#dc-name').value.trim();
  if (!name) { showDcError('Department name is required.'); return; }

  const btn = $('#dept-create-modal-save');
  btn.disabled = true;
  btn.innerHTML = '<i class="fa fa-spinner fa-spin"></i> Saving…';

  const res = await API.createDepartment({ name });

  btn.disabled = false;
  btn.innerHTML = '<i class="fa fa-plus"></i> Save department';

  if (res.status === 201 || res.status === 200) {
    closeDeptCreateModal();
    toast('Department created', 'success');
    await loadDepartments();
  } else {
    const msg = res.body?.message || res.body?.errors?.name?.[0] || 'Failed to create department.';
    showDcError(msg);
  }
}

// ── Create Employee Modal ───────────────────────────────────────────────────

function openEmpCreateModal() {
  resetEmpCreateForm();
  $('#emp-create-modal').style.display = '';
  populateEmpCreateDropdowns();
}

function closeEmpCreateModal() {
  $('#emp-create-modal').style.display = 'none';
}

$('#emp-create-modal-close').addEventListener('click', closeEmpCreateModal);
$('#emp-create-modal-cancel').addEventListener('click', closeEmpCreateModal);
$('#emp-create-modal').addEventListener('click', e => { if (e.target === $('#emp-create-modal')) closeEmpCreateModal(); });

$('#emp-create-modal-save').addEventListener('click', submitEmpCreate);

function resetEmpCreateForm() {
  ['ec-full-name','ec-dob','ec-nic','ec-permanent-addr','ec-current-addr',
   'ec-phone','ec-email','ec-employee-id','ec-doj','ec-new-job-title-name',
   'ec-new-dept-name','ec-basic-salary','ec-monthly-salary',
   'ec-ec-name','ec-ec-rel','ec-ec-phone',
   'ec-bank-holder','ec-bank-branch','ec-bank-acct',
   'ec-epf','ec-etf','ec-tin'].forEach(id => { const el = $('#' + id); if (el) el.value = ''; });
  ['ec-job-title-id','ec-dept-id','ec-emp-type','ec-bank-id'].forEach(id => {
    const el = $('#' + id); if (el) el.value = '';
  });
  $('#ec-new-job-title-wrap').style.display = 'none';
  $('#ec-new-dept-wrap').style.display      = 'none';
  $('#ec-salary-hint').textContent          = '';
  showEcError('');
}

async function populateEmpCreateDropdowns() {
  const [jtRes, deptRes, bankRes] = await Promise.all([API.jobTitles(), API.departments(), API.banks()]);

  const jtSel = $('#ec-job-title-id');
  jtSel.innerHTML = '<option value="">Choose…</option>';
  if (jtRes.status === 200) {
    (jtRes.body.data || []).forEach(jt => {
      jtSel.insertAdjacentHTML('beforeend', `<option value="${jt.id}">${escHtml(jt.name)}</option>`);
    });
  }
  jtSel.insertAdjacentHTML('beforeend', '<option value="new">+ New job title…</option>');

  const deptSel = $('#ec-dept-id');
  deptSel.innerHTML = '<option value="">Choose…</option>';
  if (deptRes.status === 200) {
    (deptRes.body.data || []).forEach(d => {
      deptSel.insertAdjacentHTML('beforeend', `<option value="${d.id}">${escHtml(d.name)}</option>`);
    });
  }
  deptSel.insertAdjacentHTML('beforeend', '<option value="new">+ New department…</option>');

  const bankSel = $('#ec-bank-id');
  bankSel.innerHTML = '<option value="">Choose…</option>';
  if (bankRes.status === 200) {
    (bankRes.body.data || []).forEach(b => {
      bankSel.insertAdjacentHTML('beforeend', `<option value="${b.id}">${escHtml(b.name)}</option>`);
    });
  }
}

// New-row toggle wires
$('#ec-job-title-id').addEventListener('change', () => {
  const isNew = $('#ec-job-title-id').value === 'new';
  $('#ec-new-job-title-wrap').style.display = isNew ? '' : 'none';
  if (!isNew) $('#ec-new-job-title-name').value = '';
});
$('#ec-dept-id').addEventListener('change', () => {
  const isNew = $('#ec-dept-id').value === 'new';
  $('#ec-new-dept-wrap').style.display = isNew ? '' : 'none';
  if (!isNew) $('#ec-new-dept-name').value = '';
});

// Salary hint
function updateEcSalaryHint() {
  const basic = parseFloat($('#ec-basic-salary').value) || 0;
  const gross = parseFloat($('#ec-monthly-salary').value) || 0;
  const hint  = $('#ec-salary-hint');
  const diff  = Math.abs(gross - basic);
  if ($('#ec-basic-salary').value || $('#ec-monthly-salary').value) {
    hint.textContent = `Expected monthly gross (basic): ${basic.toFixed(2)}`;
    hint.style.color = diff > 0.02 && $('#ec-monthly-salary').value ? '#e74c3c' : 'var(--text-muted)';
  } else {
    hint.textContent = '';
  }
}
$('#ec-basic-salary').addEventListener('input', updateEcSalaryHint);
$('#ec-monthly-salary').addEventListener('input', updateEcSalaryHint);

function showEcError(msg) {
  const el = $('#emp-create-alert');
  el.textContent = msg;
  el.style.display = msg ? '' : 'none';
  if (msg) el.scrollIntoView({ behavior: 'smooth', block: 'nearest' });
}

async function submitEmpCreate() {
  showEcError('');

  const body = {
    full_name:                       $('#ec-full-name').value.trim(),
    date_of_birth:                   $('#ec-dob').value,
    nic_passport_number:             $('#ec-nic').value.trim(),
    permanent_address:               $('#ec-permanent-addr').value.trim(),
    current_address:                 $('#ec-current-addr').value.trim(),
    phone_number:                    $('#ec-phone').value.trim(),
    personal_email:                  $('#ec-email').value.trim(),
    employee_id:                     $('#ec-employee-id').value.trim(),
    job_title_id:                    $('#ec-job-title-id').value,
    new_job_title_name:              $('#ec-new-job-title-name').value.trim() || null,
    department_id:                   $('#ec-dept-id').value,
    new_department_name:             $('#ec-new-dept-name').value.trim() || null,
    date_of_joining:                 $('#ec-doj').value,
    employment_type:                 $('#ec-emp-type').value,
    basic_salary:                    parseFloat($('#ec-basic-salary').value) || 0,
    salary:                          parseFloat($('#ec-monthly-salary').value) || 0,
    emergency_contact_name:          $('#ec-ec-name').value.trim(),
    emergency_contact_relationship:  $('#ec-ec-rel').value.trim(),
    emergency_contact_phone:         $('#ec-ec-phone').value.trim(),
    bank_account_holder_name:        $('#ec-bank-holder').value.trim(),
    bank_id:                         parseInt($('#ec-bank-id').value, 10) || null,
    bank_branch:                     $('#ec-bank-branch').value.trim(),
    bank_account_number:             $('#ec-bank-acct').value.trim(),
    epf_number:                      $('#ec-epf').value.trim() || null,
    etf_number:                      $('#ec-etf').value.trim() || null,
    tax_tin:                         $('#ec-tin').value.trim() || null,
  };

  // Basic client-side checks
  if (!body.full_name)           { showEcError('Full name is required.'); return; }
  if (!body.date_of_birth)       { showEcError('Date of birth is required.'); return; }
  if (!body.nic_passport_number) { showEcError('NIC / Passport number is required.'); return; }
  if (!body.permanent_address)   { showEcError('Permanent address is required.'); return; }
  if (!body.current_address)     { showEcError('Current address is required.'); return; }
  if (!body.phone_number)        { showEcError('Phone number is required.'); return; }
  if (!body.personal_email)      { showEcError('Personal email is required.'); return; }
  if (!body.employee_id)         { showEcError('Employee ID is required.'); return; }
  if (!body.job_title_id)        { showEcError('Job title is required.'); return; }
  if (!body.department_id)       { showEcError('Department is required.'); return; }
  if (!body.date_of_joining)     { showEcError('Date of joining is required.'); return; }
  if (!body.employment_type)     { showEcError('Employment type is required.'); return; }
  if (!body.emergency_contact_name)  { showEcError('Emergency contact name is required.'); return; }
  if (!body.emergency_contact_phone) { showEcError('Emergency contact phone is required.'); return; }
  if (!body.bank_account_holder_name) { showEcError('Account holder name is required.'); return; }
  if (!body.bank_id)             { showEcError('Bank name is required.'); return; }
  if (!body.bank_branch)         { showEcError('Bank branch is required.'); return; }
  if (!body.bank_account_number) { showEcError('Bank account number is required.'); return; }

  const btn = $('#emp-create-modal-save');
  btn.disabled = true;
  btn.innerHTML = '<i class="fa fa-spinner fa-spin"></i> Creating…';

  const res = await API.createEmployee(body);

  btn.disabled = false;
  btn.innerHTML = '<i class="fa fa-user-plus"></i> Create employee';

  if (res.status === 201 || res.status === 200) {
    closeEmpCreateModal();
    toast('Employee created successfully', 'success');
    await loadEmployees();
  } else {
    const msg = res.body?.message || res.body?.errors?.[Object.keys(res.body?.errors || {})[0]]?.[0] || 'Failed to create employee.';
    showEcError(msg);
  }
}

// ── Payroll ────────────────────────────────────────────────────────────────

let _payrollCycles = [];
let _currentPayrollCycle = null;
let __payrollRecomputeTarget = null;

const PAYROLL_STATUS_BADGE = {
  draft:     'inv-badge-amber',
  computed:  'inv-badge-blue',
  finalized: 'inv-badge-green',
};
const PAYROLL_STATUS_ICON = {
  draft:     'fa-pen-to-square',
  computed:  'fa-calculator',
  finalized: 'fa-lock',
};
const PAYROLL_HERO_COLOR = {
  draft:     { bg: '#fff7ed', color: '#ea580c' },
  computed:  { bg: '#eff6ff', color: '#2563eb' },
  finalized: { bg: '#f0fdf4', color: '#16a34a' },
};

// ── Payroll list ─────────────────────────────────────────────────────────

async function loadPayrollCycles(search) {
  const area   = $('#payroll-cycles-area');
  const footer = $('#payroll-footer-text');
  area.innerHTML = '<div class="finance-loading"><i class="fa fa-spinner fa-spin"></i> Loading payroll cycles…</div>';

  const res = await API.payrollCycles();
  if (res.status !== 200) {
    area.innerHTML = '<div class="finance-loading" style="color:#e74c3c"><i class="fa fa-circle-exclamation"></i> Failed to load payroll cycles.</div>';
    return;
  }

  _payrollCycles = res.body.data || [];
  $('#payroll-draft-count').textContent     = res.body.draft_count     ?? 0;
  $('#payroll-computed-count').textContent  = res.body.computed_count  ?? 0;
  $('#payroll-finalized-count').textContent = res.body.finalized_count ?? 0;

  let list = _payrollCycles;
  if (search) {
    const q = search.toLowerCase();
    list = list.filter(c => (c.name || '').toLowerCase().includes(q) || (c.month_label || '').toLowerCase().includes(q));
  }

  if (list.length === 0) {
    area.innerHTML = `<div class="finance-loading"><i class="fa fa-calendar-xmark" style="font-size:28px;opacity:.35"></i><br><br>${_payrollCycles.length ? 'No cycles match your search.' : 'No payroll cycles yet. Click <strong>New Cycle</strong> to get started.'}</div>`;
    footer.textContent = '';
    return;
  }

  area.innerHTML = '';
  list.forEach(c => area.appendChild(buildPayrollCycleCard(c)));
  footer.textContent = `${list.length} cycle${list.length === 1 ? '' : 's'}`;
}

function buildPayrollCycleCard(c) {
  const hc       = PAYROLL_HERO_COLOR[c.status] || { bg: '#f5f5f5', color: '#888' };
  const icon     = PAYROLL_STATUS_ICON[c.status] || 'fa-money-check-dollar';
  const badgeCls = PAYROLL_STATUS_BADGE[c.status] || '';

  const card = document.createElement('div');
  card.className = 'lm-card';
  card.style.cursor = 'pointer';

  card.innerHTML = `
    <div class="lm-card-header">
      <div class="lm-card-icon" style="background:${hc.bg};color:${hc.color}">
        <i class="fa ${icon}"></i>
      </div>
      <div class="lm-card-title-wrap">
        <div class="lm-card-name">${escHtml(c.name || (c.month_label + ' ' + c.year))}</div>
        <div class="lm-card-pills">
          <span class="inv-badge ${badgeCls}"><i class="fa ${icon}"></i> ${escHtml(c.status_label)}</span>
          ${c.items_count ? `<span class="inv-badge inv-badge-gray"><i class="fa fa-users"></i> ${c.items_count} employees</span>` : ''}
        </div>
      </div>
    </div>
    <div class="lm-stats-row">
      <div class="lm-stat-block">
        <span class="lm-stat-block-label">NET PAY</span>
        <span class="lm-balance">${escHtml(c.currency || 'LKR')} ${escHtml(c.total_net_pay_fmt || '0.00')}</span>
      </div>
      <div class="lm-stat-block">
        <span class="lm-stat-block-label">PERIOD</span>
        <span class="lm-stat-block-val">${escHtml(c.month_label)} ${c.year}</span>
      </div>
    </div>
    <div class="lm-info-rows">
      <div class="lm-info-row">
        <span class="lm-info-key"><i class="fa fa-calendar-range"></i> Pay period</span>
        <span class="lm-info-val">${escHtml(c.period_start || '—')} → ${escHtml(c.period_end || '—')}</span>
      </div>
      ${c.rule_set_name ? `<div class="lm-info-row">
        <span class="lm-info-key"><i class="fa fa-file-contract"></i> Rule set</span>
        <span class="lm-info-val">${escHtml(c.rule_set_name)}</span>
      </div>` : ''}
    </div>`;

  card.addEventListener('click', () => openPayrollCycleDetail(c.id));
  return card;
}

$('#payroll-search').addEventListener('input', e => loadPayrollCycles(e.target.value));
$('#payroll-add-btn').addEventListener('click', openPayrollCreateModal);

// ── Payroll cycle detail ─────────────────────────────────────────────────

const _pdSpinner = `<div class="inv-pane-loading"><i class="fa fa-spinner fa-spin" style="font-size:22px;opacity:.4"></i></div>`;

function openPayrollCycleDetail(cycleId) {
  $('#payroll-list-view').style.display         = 'none';
  $('#payroll-cycle-detail-view').style.display = 'flex';

  // Reset to overview tab
  $$('#pd-inv-tabs .inv-tab').forEach(t => t.classList.toggle('active', t.dataset.tab === 'overview'));
  $$('#payroll-cycle-detail-view .inv-tab-pane').forEach(p => p.classList.remove('active'));
  $('#pd-pane-overview').classList.add('active');

  // Set skeleton state
  $('#pd-cycle-name').textContent = 'Loading…';
  $('#pd-cycle-meta').innerHTML   = '';
  $('#pd-cycle-badges').innerHTML = '';
  $('#pd-pane-overview').innerHTML  = _pdSpinner;
  $('#pd-pane-employees').innerHTML = _pdSpinner;
  $('#pd-pane-sheet').innerHTML     = `<div class="inv-pane-empty"><i class="fa fa-table-columns"></i><p>Switch to this tab to load the salary sheet.</p></div>`;

  loadPayrollCycleDetail(cycleId);
}

let _pdSheetLoaded = false;

async function loadPayrollCycleDetail(cycleId) {
  _pdSheetLoaded = false;

  const res = await API.payrollCycle(cycleId);
  if (res.status !== 200) {
    $('#pd-cycle-name').textContent = 'Failed to load';
    $('#pd-pane-overview').innerHTML = `<div class="inv-pane-empty"><i class="fa fa-circle-exclamation"></i><p>Could not load payroll cycle.</p></div>`;
    return;
  }

  const c = res.body.data;
  _currentPayrollCycle = c;

  // ── Hero ──────────────────────────────────────────────────────────────
  const hc  = PAYROLL_HERO_COLOR[c.status] || { bg: '#f5f5f5', color: '#888' };
  const icon = PAYROLL_STATUS_ICON[c.status] || 'fa-money-check-dollar';
  const badgeCls = PAYROLL_STATUS_BADGE[c.status] || '';

  $('#payroll-cycle-breadcrumb').textContent = c.name || (c.month_label + ' ' + c.year);
  $('#pd-cycle-name').textContent = c.name || (c.month_label + ' ' + c.year);

  const metaParts = [];
  metaParts.push(`<span><i class="fa fa-calendar-days"></i> ${escHtml(c.month_label)} ${c.year}</span>`);
  if (c.period_start) metaParts.push(`<span><i class="fa fa-calendar-range"></i> ${escHtml(c.period_start)} → ${escHtml(c.period_end || '—')}</span>`);
  if (c.rule_set_name) metaParts.push(`<span><i class="fa fa-file-contract"></i> ${escHtml(c.rule_set_name)}</span>`);
  $('#pd-cycle-meta').innerHTML = metaParts.join('<span class="inv-sep">·</span>');

  const badges = [`<span class="inv-badge ${badgeCls}"><i class="fa ${icon}"></i> ${escHtml(c.status_label)}</span>`];
  if (c.is_paid) badges.push('<span class="inv-badge inv-badge-green"><i class="fa fa-circle-check"></i> Paid</span>');
  if (c.items_count) badges.push(`<span class="inv-badge inv-badge-gray"><i class="fa fa-users"></i> ${c.items_count} employees</span>`);
  $('#pd-cycle-badges').innerHTML = badges.join('');

  $('#pd-hero-img').innerHTML = `<div class="inv-thumb-ph inv-thumb-lg" style="background:${hc.bg};color:${hc.color};font-size:28px"><i class="fa ${icon}"></i></div>`;

  // ── Overview pane ─────────────────────────────────────────────────────
  const s   = c.summary || {};
  const cur = c.currency || 'LKR';
  const isFinalized = c.status === 'finalized';
  const isPaid      = !!c.is_paid;

  const kpiHtml = `
    <div class="lm-stats-row">
      <div class="lm-stat-block">
        <span class="lm-stat-block-label">GROSS EARNINGS</span>
        <span class="lm-stat-block-val">${cur} ${s.gross_earnings_fmt || '0.00'}</span>
      </div>
      <div class="lm-stat-block">
        <span class="lm-stat-block-label">TOTAL DEDUCTIONS</span>
        <span class="lm-stat-block-val">${cur} ${s.total_deductions_fmt || '0.00'}</span>
      </div>
      <div class="lm-stat-block">
        <span class="lm-stat-block-label">NET PAY</span>
        <span class="lm-balance">${cur} ${s.net_pay_fmt || '0.00'}</span>
      </div>
    </div>
    <div class="lm-stats-row">
      <div class="lm-stat-block">
        <span class="lm-stat-block-label">EPF (EMPLOYEE)</span>
        <span class="lm-stat-block-val">${cur} ${s.epf_employee_fmt || '0.00'}</span>
      </div>
      <div class="lm-stat-block">
        <span class="lm-stat-block-label">ETF (EMPLOYER)</span>
        <span class="lm-stat-block-val">${cur} ${s.etf_employer_fmt || '0.00'}</span>
      </div>
      <div class="lm-stat-block">
        <span class="lm-stat-block-label">APIT</span>
        <span class="lm-stat-block-val">${cur} ${s.apit_fmt || '0.00'}</span>
      </div>
    </div>`;

  const detailRows = [
    ['Cycle name',   escHtml(c.name || '—')],
    ['Period',       `${escHtml(c.month_label)} ${c.year}`],
    ['Pay period',   `${escHtml(c.period_start || '—')} → ${escHtml(c.period_end || '—')}`],
    ['Rule set',     escHtml(c.rule_set_name || '—')],
    ['Currency',     escHtml(cur)],
  ];
  if (c.computed_at)  detailRows.push(['Computed at',  escHtml(c.computed_at)]);
  if (c.finalized_at) detailRows.push(['Finalized at', escHtml(c.finalized_at)]);

  const detailRowsHtml = detailRows.map(([l, v]) =>
    `<tr><td class="inv-dt-label">${l}</td><td class="inv-dt-val">${v}</td></tr>`).join('');

  // Actions
  let actionsHtml = '';
  if (!isFinalized) {
    actionsHtml = `
      <div class="inv-section" style="margin-top:14px">
        <div class="inv-section-title"><i class="fa fa-calculator"></i> Run payroll</div>
        <div style="padding:12px 14px;display:flex;align-items:center;justify-content:space-between;gap:12px">
          <span style="font-size:12px;color:var(--text-muted);line-height:1.5">Compute earnings and deductions for all employees in this cycle using the configured rule set.</span>
          <button class="bill-save-btn pd-compute-btn-inline" data-cycle-id="${c.id}" style="flex-shrink:0;min-width:130px">
            <i class="fa fa-calculator"></i> Compute All
          </button>
        </div>
      </div>
      <div class="inv-section" style="margin-top:14px">
        <div class="inv-section-title" style="color:#16a34a"><i class="fa fa-lock"></i> Finalize cycle</div>
        <div style="padding:12px 14px;display:flex;align-items:center;justify-content:space-between;gap:12px">
          <span style="font-size:12px;color:var(--text-muted);line-height:1.5">Locks all payroll data. Once finalized the cycle cannot be edited. Only finalized cycles can be paid.</span>
          <button class="bill-save-btn pd-finalize-btn-inline" data-cycle-id="${c.id}" style="flex-shrink:0;min-width:130px;background:#16a34a">
            <i class="fa fa-lock"></i> Finalize
          </button>
        </div>
      </div>`;
  }

  if (isFinalized && !isPaid) {
    actionsHtml += `
      <div class="inv-section" style="margin-top:14px">
        <div class="inv-section-title" style="color:#7c3aed"><i class="fa fa-money-bill-transfer"></i> Record payment</div>
        <div style="padding:12px 14px;display:flex;align-items:center;justify-content:space-between;gap:12px">
          <span style="font-size:12px;color:var(--text-muted);line-height:1.5">Mark this cycle as paid by deducting the net pay total from the selected account.</span>
          <button class="bill-save-btn pd-payment-btn-inline" data-cycle-id="${c.id}" style="flex-shrink:0;min-width:130px;background:#7c3aed">
            <i class="fa fa-money-bill-transfer"></i> Record Payment
          </button>
        </div>
      </div>`;
  }

  if (!isPaid) {
    actionsHtml += `
      <div class="inv-section" style="margin-top:14px">
        <div class="inv-section-title" style="color:#dc2626"><i class="fa fa-trash"></i> Delete cycle</div>
        <div style="padding:12px 14px;display:flex;align-items:center;justify-content:space-between;gap:12px">
          <span style="font-size:12px;color:var(--text-muted);line-height:1.5">Permanently removes this payroll cycle. This cannot be undone.</span>
          <button class="bd-danger-btn pd-delete-btn-inline" data-cycle-id="${c.id}" style="flex-shrink:0">
            <i class="fa fa-trash"></i> Delete
          </button>
        </div>
      </div>`;
  }

  $('#pd-pane-overview').innerHTML = `
    <div class="inv-section">
      <div class="inv-section-title"><i class="fa fa-chart-pie"></i> Summary</div>
      ${kpiHtml}
    </div>
    <div class="inv-section" style="margin-top:14px">
      <div class="inv-section-title"><i class="fa fa-circle-info"></i> Cycle details</div>
      <table class="inv-detail-table">${detailRowsHtml}</table>
    </div>
    ${actionsHtml}`;

  // Wire overview pane buttons
  $('#pd-pane-overview').querySelectorAll('.pd-compute-btn-inline').forEach(btn => {
    btn.addEventListener('click', () => pdComputeCycle(parseInt(btn.dataset.cycleId, 10), btn));
  });
  $('#pd-pane-overview').querySelectorAll('.pd-finalize-btn-inline').forEach(btn => {
    btn.addEventListener('click', () => pdFinalizeCycle(parseInt(btn.dataset.cycleId, 10), btn));
  });
  $('#pd-pane-overview').querySelectorAll('.pd-payment-btn-inline').forEach(btn => {
    btn.addEventListener('click', () => { if (_currentPayrollCycle) openPayrollPaymentModal(_currentPayrollCycle); });
  });
  $('#pd-pane-overview').querySelectorAll('.pd-delete-btn-inline').forEach(btn => {
    btn.addEventListener('click', () => pdDeleteCycle(parseInt(btn.dataset.cycleId, 10), btn));
  });

  // ── Employees pane ────────────────────────────────────────────────────
  renderPayrollEmployeesTab(c, c.items || []);
}

async function pdComputeCycle(cycleId, btn) {
  const orig = btn.innerHTML;
  btn.disabled = true;
  btn.innerHTML = '<i class="fa fa-spinner fa-spin"></i> Computing…';

  const res = await API.computePayrollCycle(cycleId);
  btn.disabled = false;
  btn.innerHTML = orig;

  if (res.status === 200) {
    const errors = res.body.computation_errors || [];
    toast(errors.length ? `Computed with ${errors.length} error(s). Check Employees tab.` : (res.body.message || 'Cycle computed.'), errors.length ? 'error' : 'success');
    await loadPayrollCycleDetail(cycleId);
  } else {
    toast(res.body?.message || 'Failed to compute cycle.', 'error');
  }
}

async function pdFinalizeCycle(cycleId, btn) {
  if (!confirm('Finalizing the cycle locks all payroll data permanently. Continue?')) return;
  const orig = btn.innerHTML;
  btn.disabled = true;
  btn.innerHTML = '<i class="fa fa-spinner fa-spin"></i> Finalizing…';

  const res = await API.finalizePayrollCycle(cycleId);
  btn.disabled = false;
  btn.innerHTML = orig;

  if (res.status === 200) {
    toast('Cycle finalized.', 'success');
    await loadPayrollCycleDetail(cycleId);
  } else {
    toast(res.body?.message || 'Failed to finalize.', 'error');
  }
}

async function pdDeleteCycle(cycleId, btn) {
  if (!confirm('Delete this payroll cycle? This cannot be undone.')) return;
  const orig = btn.innerHTML;
  btn.disabled = true;
  btn.innerHTML = '<i class="fa fa-spinner fa-spin"></i>';

  const res = await API.deletePayrollCycle(cycleId);
  if (res.status === 200) {
    toast('Cycle deleted.', 'success');
    $('#payroll-cycle-detail-view').style.display = 'none';
    $('#payroll-list-view').style.display         = 'flex';
    _currentPayrollCycle = null;
    await loadPayrollCycles();
  } else {
    btn.disabled = false;
    btn.innerHTML = orig;
    toast(res.body?.message || 'Failed to delete cycle.', 'error');
  }
}

function renderPayrollEmployeesTab(cycle, items) {
  const el = $('#pd-pane-employees');
  if (!items.length) {
    el.innerHTML = `<div class="inv-pane-empty"><i class="fa fa-users-slash"></i><p>No employees computed yet.</p><p style="font-size:12px">Use "Compute All" in the Overview tab to run payroll.</p></div>`;
    return;
  }

  const isFinalized = cycle.status === 'finalized';
  const cur = cycle.currency || 'LKR';

  const rows = items.map(it => {
    const errBadge = it.has_errors
      ? `<span class="inv-badge inv-badge-red"><i class="fa fa-circle-exclamation"></i> Error</span>`
      : '';
    const statusBadge = it.has_errors
      ? `<span class="inv-badge inv-badge-red">error</span>`
      : (it.status === 'computed' || it.status === 'finalized')
        ? `<span class="inv-badge inv-badge-green">${escHtml(it.status)}</span>`
        : `<span class="inv-badge inv-badge-gray">${escHtml(it.status || 'pending')}</span>`;

    const recomputeBtn = isFinalized ? '' :
      `<button class="btn-secondary pr-recompute-btn" style="font-size:11px;padding:3px 9px" data-item-id="${it.id}" data-cycle-id="${cycle.id}" title="Recompute">
         <i class="fa fa-rotate-right"></i> Adjust
       </button>`;

    const errRow = it.has_errors
      ? `<tr class="inv-row" style="background:#fff5f5">
           <td colspan="8" style="padding:6px 14px;font-size:11px;color:#b91c1c">
             <i class="fa fa-circle-exclamation"></i> ${escHtml((it.errors || []).join(' · '))}
           </td>
         </tr>` : '';

    return `<tr class="inv-row">
      <td>${statusBadge}</td>
      <td><strong>${escHtml(it.employee_name || '—')}</strong><br><span style="font-size:11px;color:var(--text-muted);font-family:monospace">${escHtml(it.employee_code || '')}</span></td>
      <td class="inv-price" style="color:var(--text)">${cur} ${escHtml(it.basic_salary_fmt)}</td>
      <td class="inv-price" style="color:var(--text)">${cur} ${escHtml(it.overtime_amount_fmt)}</td>
      <td class="inv-price" style="color:var(--text)">${cur} ${escHtml(it.gross_earnings_fmt)}</td>
      <td class="inv-price" style="color:#dc2626">${cur} ${escHtml(it.total_deductions_fmt)}</td>
      <td class="inv-price" style="color:var(--accent);font-weight:800">${cur} ${escHtml(it.net_pay_fmt)}</td>
      <td style="text-align:right">${recomputeBtn}</td>
    </tr>${errRow}`;
  }).join('');

  el.innerHTML = `<div style="overflow-x:auto">
    <table class="inv-table" style="min-width:720px">
      <thead>
        <tr>
          <th>Status</th>
          <th>Employee</th>
          <th style="text-align:right">Basic</th>
          <th style="text-align:right">Overtime</th>
          <th style="text-align:right">Gross</th>
          <th style="text-align:right">Deductions</th>
          <th style="text-align:right">Net Pay</th>
          <th></th>
        </tr>
      </thead>
      <tbody>${rows}</tbody>
    </table>
  </div>`;

  el.querySelectorAll('.pr-recompute-btn').forEach(btn => {
    btn.addEventListener('click', e => {
      e.stopPropagation();
      const item = items.find(i => i.id == btn.dataset.itemId);
      if (item) openPayrollRecomputeModal(parseInt(btn.dataset.cycleId, 10), item);
    });
  });
}

// ── Salary Sheet ─────────────────────────────────────────────────────────

async function loadPayrollSalarySheet(cycleId) {
  const pane = $('#pd-pane-sheet');
  pane.innerHTML = _pdSpinner;

  const res = await API.payrollSalarySheet(cycleId);
  if (res.status !== 200) {
    pane.innerHTML = `<div class="inv-pane-empty"><i class="fa fa-circle-exclamation"></i><p>Failed to load salary sheet.</p></div>`;
    return;
  }

  const sheet   = res.body.data || {};
  const columns = sheet.columns || [];
  const rows    = sheet.rows    || [];

  if (!rows.length) {
    pane.innerHTML = `<div class="inv-pane-empty"><i class="fa fa-table-columns"></i><p>No salary sheet data yet.</p><p style="font-size:12px">Compute the cycle first to generate salary data.</p></div>`;
    return;
  }

  const ths = columns.map((col, i) => {
    const align = i === 0 ? 'left' : 'right';
    return `<th style="text-align:${align};white-space:nowrap">${escHtml(col.label || col.key)}</th>`;
  }).join('');

  const trs = rows.map(row => {
    const cells = columns.map((col, i) => {
      const val     = row[col.key];
      const isNum   = typeof val === 'number';
      const align   = i === 0 ? 'left' : 'right';
      const display = (val === null || val === undefined) ? '—'
        : isNum ? val.toLocaleString(undefined, { minimumFractionDigits: 2, maximumFractionDigits: 2 })
        : escHtml(String(val));
      const bold = isNum && col.key && (col.key.includes('net') || col.key.includes('total')) ? 'font-weight:700;color:var(--accent)' : '';
      return `<td class="inv-row" style="text-align:${align};${bold}">${display}</td>`;
    }).join('');
    return `<tr class="inv-row">${cells}</tr>`;
  }).join('');

  pane.innerHTML = `<div style="overflow-x:auto;padding:4px 0">
    <table class="inv-table" style="min-width:600px;font-size:12px">
      <thead><tr>${ths}</tr></thead>
      <tbody>${trs}</tbody>
    </table>
  </div>`;
}

// ── Tab switching for cycle detail ────────────────────────────────────────

$$('#pd-inv-tabs .inv-tab').forEach(tab => {
  tab.addEventListener('click', async () => {
    $$('#pd-inv-tabs .inv-tab').forEach(t => t.classList.remove('active'));
    $$('#payroll-cycle-detail-view .inv-tab-pane').forEach(p => p.classList.remove('active'));
    tab.classList.add('active');
    const paneId = 'pd-pane-' + tab.dataset.tab;
    const pane = $('#' + paneId);
    if (pane) pane.classList.add('active');
    if (tab.dataset.tab === 'sheet' && !_pdSheetLoaded && _currentPayrollCycle) {
      _pdSheetLoaded = true;
      await loadPayrollSalarySheet(_currentPayrollCycle.id);
    }
  });
});

$('#payroll-back-btn').addEventListener('click', () => {
  $('#payroll-cycle-detail-view').style.display = 'none';
  $('#payroll-list-view').style.display         = 'flex';
  _currentPayrollCycle = null;
  _pdSheetLoaded = false;
  loadPayrollCycles($('#payroll-search').value);
});

// ── Payment modal ─────────────────────────────────────────────────────────

function openPayrollPaymentModal(cycle) {
  const modal = $('#payroll-payment-modal');
  const accountSel = $('#pp-account');
  const totalEl    = $('#pp-total-net');

  // Set total
  const s = cycle.summary || {};
  totalEl.textContent = (cycle.currency || 'LKR') + ' ' + (s.net_pay_fmt || cycle.total_net_pay_fmt || '0.00');

  // Load accounts
  accountSel.innerHTML = '<option value="">— loading accounts —</option>';
  API.accounts().then(res => {
    accountSel.innerHTML = '<option value="">— select account —</option>';
    (res.body?.data || res.body || []).forEach(acc => {
      const opt = document.createElement('option');
      opt.value = acc.id;
      opt.textContent = acc.name + (acc.balance_fmt ? ' (' + acc.balance_fmt + ')' : '');
      accountSel.appendChild(opt);
    });
  });

  $('#payroll-payment-alert').style.display = 'none';
  $('#payroll-payment-alert').textContent = '';
  modal.style.display = 'flex';
  modal._cycleId = cycle.id;
}

function closePayrollPaymentModal() {
  $('#payroll-payment-modal').style.display = 'none';
}

document.getElementById('payroll-payment-modal-close').addEventListener('click', closePayrollPaymentModal);
document.getElementById('payroll-payment-modal-cancel').addEventListener('click', closePayrollPaymentModal);
document.getElementById('payroll-payment-modal').addEventListener('click', e => { if (e.target === e.currentTarget) closePayrollPaymentModal(); });

document.getElementById('payroll-payment-modal-save').addEventListener('click', async () => {
  const modal    = $('#payroll-payment-modal');
  const cycleId  = modal._cycleId;
  const alertEl  = $('#payroll-payment-alert');
  const accountId = parseInt($('#pp-account').value, 10);

  alertEl.style.display = 'none';
  if (!accountId) { alertEl.textContent = 'Please select an account.'; alertEl.style.display = ''; return; }

  const btn = document.getElementById('payroll-payment-modal-save');
  btn.disabled = true;
  btn.innerHTML = '<i class="fa fa-spinner fa-spin"></i> Recording…';

  const res = await API.payrollPayment(cycleId, { deduct_account_id: accountId });

  btn.disabled = false;
  btn.innerHTML = '<i class="fa fa-money-bill-transfer"></i> Record Payment';

  if (res.status === 200) {
    closePayrollPaymentModal();
    toast('Payment recorded.', 'success');
    await loadPayrollCycleDetail(cycleId);
  } else {
    alertEl.textContent = res.body?.message || 'Failed to record payment.';
    alertEl.style.display = '';
  }
});

// ── Create Cycle modal ────────────────────────────────────────────────────

// ── Create Cycle modal ────────────────────────────────────────────────────

const _PC_MONTHS = ['','January','February','March','April','May','June','July','August','September','October','November','December'];

function _pcAutoFill() {
  const year  = parseInt($('#pc-year').value, 10);
  const month = parseInt($('#pc-month').value, 10);
  if (year >= 2020 && year <= 2100 && month >= 1 && month <= 12) {
    const start = new Date(year, month - 1, 1);
    const end   = new Date(year, month, 0);
    $('#pc-period-start').value = start.toISOString().slice(0, 10);
    $('#pc-period-end').value   = end.toISOString().slice(0, 10);
    if (!$('#pc-name').value.trim()) {
      $('#pc-name').value = _PC_MONTHS[month] + ' ' + year + ' Payroll';
    }
  }
}

$('#pc-year').addEventListener('change',  _pcAutoFill);
$('#pc-month').addEventListener('change', _pcAutoFill);

async function openPayrollCreateModal() {
  const rsSel = $('#pc-rule-set');

  rsSel.innerHTML = '<option value="">— loading —</option>';
  const res = await API.payrollRuleSets();
  rsSel.innerHTML = '<option value="">— select rule set —</option>';
  (res.body?.data || []).forEach(rs => {
    const opt = document.createElement('option');
    opt.value       = rs.id;
    opt.textContent = rs.name + (rs.currency ? ' (' + rs.currency + ')' : '') + (rs.is_default ? ' [default]' : '');
    rsSel.appendChild(opt);
  });
  if (rsSel.options.length === 2) rsSel.selectedIndex = 1;

  const now = new Date();
  $('#pc-year').value  = now.getFullYear();
  $('#pc-month').value = now.getMonth() + 1;
  $('#pc-name').value  = '';
  _pcAutoFill();

  $('#payroll-create-alert').style.display = 'none';
  $('#payroll-create-modal').style.display = 'flex';
}

function closePayrollCreateModal() {
  $('#payroll-create-modal').style.display = 'none';
  $('#payroll-create-alert').style.display = 'none';
  $('#pc-name').value = '';
}

document.getElementById('payroll-create-modal-close').addEventListener('click',   closePayrollCreateModal);
document.getElementById('payroll-create-modal-cancel').addEventListener('click',  closePayrollCreateModal);
document.getElementById('payroll-create-modal').addEventListener('click', e => { if (e.target === e.currentTarget) closePayrollCreateModal(); });

document.getElementById('payroll-create-modal-save').addEventListener('click', async () => {
  const alertEl = $('#payroll-create-alert');
  alertEl.style.display = 'none';

  const body = {
    rule_set_id:  parseInt($('#pc-rule-set').value, 10)  || null,
    name:         $('#pc-name').value.trim(),
    year:         parseInt($('#pc-year').value, 10)       || null,
    month:        parseInt($('#pc-month').value, 10)      || null,
    period_start: $('#pc-period-start').value,
    period_end:   $('#pc-period-end').value,
  };

  if (!body.year)         { alertEl.textContent = 'Year is required.';         alertEl.style.display = ''; return; }
  if (!body.month)        { alertEl.textContent = 'Select a month.';           alertEl.style.display = ''; return; }
  if (!body.period_start) { alertEl.textContent = 'Period start is required.'; alertEl.style.display = ''; return; }
  if (!body.period_end)   { alertEl.textContent = 'Period end is required.';   alertEl.style.display = ''; return; }
  if (!body.rule_set_id)  { alertEl.textContent = 'Select a rule set.';        alertEl.style.display = ''; return; }
  if (!body.name)         { alertEl.textContent = 'Cycle name is required.';   alertEl.style.display = ''; return; }

  const btn = document.getElementById('payroll-create-modal-save');
  btn.disabled = true;
  btn.innerHTML = '<i class="fa fa-spinner fa-spin"></i> Creating…';

  const res = await API.createPayrollCycle(body);

  btn.disabled = false;
  btn.innerHTML = '<i class="fa fa-plus"></i> Create Cycle';

  if (res.status === 201 || res.status === 200) {
    closePayrollCreateModal();
    toast('Payroll cycle created.', 'success');
    const newCycle = res.body.data;
    await loadPayrollCycles();
    if (newCycle?.id) openPayrollCycleDetail(newCycle.id);
  } else {
    alertEl.textContent = res.body?.message || 'Failed to create cycle.';
    alertEl.style.display = '';
  }
});

// ── Recompute employee modal ──────────────────────────────────────────────

function openPayrollRecomputeModal(cycleId, item) {
  _payrollRecomputeTarget = { cycleId, item };

  const modal = $('#payroll-recompute-modal');
  const inputs = item.inputs_json || {};

  $('#pr-employee-label').textContent = item.employee_name + (item.employee_code ? ' (' + item.employee_code + ')' : '');
  $('#pr-overtime-hours').value  = inputs.overtime_hours         ?? '';
  $('#pr-overtime-rate').value   = inputs.overtime_rate          ?? '';
  $('#pr-attendance-days').value = inputs.attendance_days        ?? '';
  $('#pr-working-days').value    = inputs.working_days           ?? '';
  $('#pr-lwp-days').value        = inputs.leave_without_pay_days ?? '';
  $('#pr-salary-advance').value  = inputs.salary_advance         ?? '';
  $('#pr-stamp-duty').value      = inputs.stamp_duty             ?? '';

  $('#payroll-recompute-alert').style.display = 'none';
  modal.style.display = 'flex';
}

function closePayrollRecomputeModal() {
  $('#payroll-recompute-modal').style.display = 'none';
  _payrollRecomputeTarget = null;
}

document.getElementById('payroll-recompute-modal-close').addEventListener('click', closePayrollRecomputeModal);
document.getElementById('payroll-recompute-modal-cancel').addEventListener('click', closePayrollRecomputeModal);
document.getElementById('payroll-recompute-modal').addEventListener('click', e => { if (e.target === e.currentTarget) closePayrollRecomputeModal(); });

document.getElementById('payroll-recompute-modal-save').addEventListener('click', async () => {
  if (!_payrollRecomputeTarget) return;
  const { cycleId, item } = _payrollRecomputeTarget;
  const alertEl = $('#payroll-recompute-alert');
  alertEl.style.display = 'none';

  const toNum = (id) => { const v = $(id).value; return v !== '' ? parseFloat(v) : undefined; };
  const body = {};
  const oh  = toNum('#pr-overtime-hours');  if (oh  !== undefined) body.overtime_hours          = oh;
  const or_ = toNum('#pr-overtime-rate');   if (or_ !== undefined) body.overtime_rate            = or_;
  const ad  = toNum('#pr-attendance-days'); if (ad  !== undefined) body.attendance_days          = ad;
  const wd  = toNum('#pr-working-days');    if (wd  !== undefined) body.working_days             = wd;
  const lw  = toNum('#pr-lwp-days');        if (lw  !== undefined) body.leave_without_pay_days   = lw;
  const sa  = toNum('#pr-salary-advance');  if (sa  !== undefined) body.salary_advance           = sa;
  const sd  = toNum('#pr-stamp-duty');      if (sd  !== undefined) body.stamp_duty               = sd;

  const btn = document.getElementById('payroll-recompute-modal-save');
  btn.disabled = true;
  btn.innerHTML = '<i class="fa fa-spinner fa-spin"></i> Recomputing…';

  const res = await API.recomputePayrollItem(cycleId, item.id, body);

  btn.disabled = false;
  btn.innerHTML = '<i class="fa fa-calculator"></i> Recompute';

  if (res.status === 200) {
    const errors = res.body.errors || [];
    if (errors.length) {
      alertEl.textContent = 'Computed with errors: ' + errors.join(', ');
      alertEl.style.display = '';
    } else {
      closePayrollRecomputeModal();
      toast('Employee recomputed.', 'success');
    }
    // Refresh the detail view
    await loadPayrollCycleDetail(cycleId);
  } else {
    alertEl.textContent = res.body?.message || 'Recompute failed.';
    alertEl.style.display = '';
  }
});

// ── HR Rule Sets ────────────────────────────────────────────────────────────

let _ruleSets = [];
let _currentRuleSet = null;
let _ruleCreateTargetRsId = null;

const RS_TYPE_BADGE = {
  earning: 'inv-badge-green', deduction: 'inv-badge-red', statutory: 'inv-badge-blue',
  overtime: 'inv-badge-amber', informational: 'inv-badge-gray', employer_tracking: 'inv-badge-gray',
};
const RS_TYPE_LABEL = {
  earning: 'Earning', deduction: 'Deduction', statutory: 'Statutory',
  overtime: 'Overtime', informational: 'Info', employer_tracking: 'Employer',
};
const RS_TYPE_ICON = {
  earning:          { icon: 'fa-arrow-trend-up',  bg: '#dcfce7', color: '#16a34a' },
  deduction:        { icon: 'fa-arrow-trend-down', bg: '#fee2e2', color: '#dc2626' },
  statutory:        { icon: 'fa-landmark',          bg: '#dbeafe', color: '#2563eb' },
  overtime:         { icon: 'fa-clock',             bg: '#fef3c7', color: '#d97706' },
  informational:    { icon: 'fa-circle-info',       bg: '#f3f4f6', color: '#6b7280' },
  employer_tracking:{ icon: 'fa-building',          bg: '#f3f4f6', color: '#6b7280' },
};
const RS_TYPE_ACCENT = {
  earning: '#16a34a', deduction: '#dc2626', statutory: '#2563eb',
  overtime: '#d97706', informational: '#9ca3af', employer_tracking: '#9ca3af',
};
const RS_MODE_LABEL = {
  fixed: 'Fixed', percentage: 'Percentage', slab: 'Slab', formula: 'Formula',
};
const RS_MODE_BADGE = {
  fixed: 'inv-badge-gray', percentage: 'inv-badge-gray', slab: 'inv-badge-gray', formula: 'inv-badge-gray',
};

async function loadRuleSets(search) {
  const area   = $('#rs-cards-area');
  const footer = $('#rs-footer-text');
  if (!area) return;
  area.innerHTML = '<div class="finance-loading"><i class="fa fa-spinner fa-spin"></i> Loading rule sets…</div>';

  const res = await API.payrollRuleSets();
  if (res.status !== 200) {
    area.innerHTML = '<div class="finance-loading">Failed to load rule sets.</div>';
    return;
  }

  _ruleSets = res.body.data || [];
  let list = _ruleSets;
  if (search) {
    const q = search.toLowerCase();
    list = list.filter(rs => rs.name.toLowerCase().includes(q));
  }

  if (!list.length) {
    area.innerHTML = `<div class="finance-loading"><i class="fa fa-sliders" style="font-size:28px;opacity:.35"></i><br><br>${_ruleSets.length ? 'No rule sets match your search.' : 'No rule sets yet. Click <strong>New Rule Set</strong> to get started.'}</div>`;
    if (footer) footer.textContent = 'No rule sets';
    return;
  }

  area.innerHTML = '';
  list.forEach(rs => {
    const card = document.createElement('div');
    card.className = 'lm-card';
    card.dataset.id = rs.id;
    const defaultBadge = rs.is_default ? '<span class="inv-badge inv-badge-green">Default</span>' : '';
    const activeBadge  = !rs.is_active  ? '<span class="inv-badge inv-badge-gray">Inactive</span>' : '';
    card.innerHTML = `
      <div class="lm-card-header">
        <div class="lm-card-icon" style="background:#eff6ff;color:#2563eb"><i class="fa fa-file-contract"></i></div>
        <div class="lm-card-title-wrap">
          <div class="lm-card-name">${rs.name}</div>
          <div class="lm-card-pills">${defaultBadge}${activeBadge}</div>
        </div>
      </div>
      <div class="lm-stats-row">
        <div class="lm-stat-block">
          <span class="lm-stat-block-label">RULES</span>
          <span class="lm-stat-block-val">${rs.rules_count}</span>
        </div>
        <div class="lm-stat-block">
          <span class="lm-stat-block-label">CURRENCY</span>
          <span class="lm-stat-block-val">${rs.currency || 'LKR'}</span>
        </div>
      </div>
      <div class="lm-info-rows">
        <div class="lm-info-row"><span class="lm-info-key">Effective From</span><span class="lm-info-val">${rs.effective_from || '—'}</span></div>
      </div>`;
    card.addEventListener('click', () => openRuleSetDetail(rs.id));
    area.appendChild(card);
  });

  if (footer) footer.textContent = `${list.length} rule set${list.length !== 1 ? 's' : ''}`;
}

function openRuleSetDetail(id) {
  $('#rule-sets-list-view').style.display = 'none';
  $('#rule-set-detail-view').style.display = 'flex';
  loadRuleSetDetail(id);
}

function closeRuleSetDetail() {
  $('#rule-set-detail-view').style.display = 'none';
  $('#rule-sets-list-view').style.display = 'flex';
}

async function loadRuleSetDetail(id) {
  _currentRuleSet = null;

  // Skeleton
  const heroName   = $('#rs-hero-name');
  const heroMeta   = $('#rs-hero-meta');
  const heroBadges = $('#rs-hero-badges');
  const overPane   = $('#rs-pane-overview');
  const rulesPane  = $('#rs-pane-rules');

  if (heroName)   heroName.textContent = '…';
  if (heroBadges) heroBadges.innerHTML = '';
  if (overPane)   overPane.innerHTML = '<div class="inv-pane-loading"><i class="fa fa-spinner fa-spin"></i></div>';
  if (rulesPane)  rulesPane.innerHTML = '<div class="inv-pane-loading"><i class="fa fa-spinner fa-spin"></i></div>';

  const res = await API.payrollRuleSet(id);
  if (res.status !== 200) {
    if (overPane) overPane.innerHTML = '<div class="inv-pane-empty"><i class="fa fa-triangle-exclamation"></i><br>Failed to load rule set.</div>';
    return;
  }

  const rs = res.body.data;
  _currentRuleSet = rs;

  // Hero
  if (heroName) heroName.textContent = rs.name;
  if (heroMeta) heroMeta.textContent = `${rs.currency || 'LKR'} · ${rs.rules_count} rule${rs.rules_count !== 1 ? 's' : ''}`;
  if (heroBadges) {
    heroBadges.innerHTML = '';
    if (rs.is_default) heroBadges.innerHTML += '<span class="inv-badge inv-badge-green">Default</span>';
    if (!rs.is_active)  heroBadges.innerHTML += '<span class="inv-badge inv-badge-gray">Inactive</span>';
    if (rs.is_active && !rs.is_default) heroBadges.innerHTML += '<span class="inv-badge inv-badge-blue">Active</span>';
  }

  // Overview pane
  if (overPane) {
    overPane.innerHTML = `
      <div class="inv-section">
        <div class="inv-section-title">Rule Set Details</div>
        <div class="inv-detail-table">
          <div class="inv-row"><span class="inv-dt-label">Name</span><span class="inv-dt-val">${rs.name}</span></div>
          <div class="inv-row"><span class="inv-dt-label">Currency</span><span class="inv-dt-val">${rs.currency || 'LKR'}</span></div>
          <div class="inv-row"><span class="inv-dt-label">Effective From</span><span class="inv-dt-val">${rs.effective_from || '—'}</span></div>
          <div class="inv-row"><span class="inv-dt-label">Effective To</span><span class="inv-dt-val">${rs.effective_to || 'Open-ended'}</span></div>
          <div class="inv-row"><span class="inv-dt-label">Default</span><span class="inv-dt-val">${rs.is_default ? 'Yes' : 'No'}</span></div>
          <div class="inv-row"><span class="inv-dt-label">Active</span><span class="inv-dt-val">${rs.is_active ? 'Yes' : 'No'}</span></div>
          ${rs.notes ? `<div class="inv-row"><span class="inv-dt-label">Notes</span><span class="inv-dt-val">${rs.notes}</span></div>` : ''}
        </div>
      </div>
      <div class="inv-section">
        <div class="inv-section-title">Actions</div>
        <div style="display:flex;gap:8px;flex-wrap:wrap">
          <button class="bill-save-btn" id="rsd-add-rule-btn"><i class="fa fa-plus"></i> Add Rule</button>
          <button class="bd-danger-btn" id="rsd-delete-rs-btn"><i class="fa fa-trash"></i> Delete Rule Set</button>
        </div>
      </div>`;

    document.getElementById('rsd-add-rule-btn')?.addEventListener('click', () => openRuleCreateModal(rs.id));
    document.getElementById('rsd-delete-rs-btn')?.addEventListener('click', () => rsDeleteCurrent());
  }

  // Rules pane
  renderRulesTab(rs, rs.rules || []);

  // Activate first tab
  $$('#rule-set-detail-view .inv-tab').forEach(t => t.classList.toggle('active', t.dataset.tab === 'overview'));
  $$('#rule-set-detail-view .inv-tab-pane').forEach(p => p.classList.toggle('active', p.id === 'rs-pane-overview'));
}

function renderRulesTab(rs, rules) {
  const pane = $('#rs-pane-rules');
  if (!pane) return;

  if (!rules.length) {
    pane.innerHTML = `
      <div class="inv-pane-empty"><i class="fa fa-list-check"></i><br>No rules yet.<br><br>
        <button class="bill-save-btn" id="rs-rules-add-btn"><i class="fa fa-plus"></i> Add First Rule</button>
      </div>`;
    document.getElementById('rs-rules-add-btn')?.addEventListener('click', () => openRuleCreateModal(rs.id));
    return;
  }

  const rows = rules.map(r => {
    const ti     = RS_TYPE_ICON[r.component_type] || { icon: 'fa-circle', bg: '#f3f4f6', color: '#6b7280' };
    const accent = RS_TYPE_ACCENT[r.component_type] || '#9ca3af';
    const flags  = [
      r.is_taxable   ? '<span class="inv-badge inv-badge-amber">Taxable</span>'     : '',
      r.is_statutory ? '<span class="inv-badge inv-badge-blue">Statutory</span>'    : '',
      !r.is_active   ? '<span class="inv-badge inv-badge-gray">Inactive</span>'     : '',
    ].filter(Boolean).join('');
    return `<tr class="pr-rule-row">
      <td class="pr-rule-accent" style="background:${accent}"></td>
      <td class="pr-rule-icon-cell">
        <div class="pr-rule-icon" style="background:${ti.bg};color:${ti.color}"><i class="fa ${ti.icon}"></i></div>
      </td>
      <td class="pr-rule-name">
        ${r.name}
        <small>${r.code}</small>
      </td>
      <td class="pr-rule-badges">
        <span class="inv-badge ${RS_TYPE_BADGE[r.component_type] || 'inv-badge-gray'}">${RS_TYPE_LABEL[r.component_type] || r.component_type}</span>
      </td>
      <td class="pr-rule-badges">
        <span class="inv-badge inv-badge-gray">${RS_MODE_LABEL[r.calculation_mode] || r.calculation_mode}</span>
      </td>
      <td class="pr-rule-flags">${flags || '<span style="color:var(--text-muted);font-size:11px">—</span>'}</td>
      <td class="pr-rule-actions">
        <button class="bd-danger-btn" style="padding:5px 10px" data-rule-id="${r.id}" data-rs-id="${rs.id}"><i class="fa fa-trash"></i></button>
      </td>
    </tr>`;
  }).join('');

  pane.innerHTML = `
    <div class="inv-section">
      <div class="inv-section-title" style="justify-content:space-between">
        <span><i class="fa fa-list-check" style="margin-right:6px;opacity:.6"></i>Rules (${rules.length})</span>
        <button class="bill-save-btn" style="font-size:12px;padding:5px 14px" id="rs-rules-tab-add-btn"><i class="fa fa-plus"></i> Add Rule</button>
      </div>
      <table class="pr-rules-table">
        <thead>
          <tr>
            <th style="width:4px;padding:0"></th>
            <th style="width:36px;padding-right:0"></th>
            <th>Name / Code</th>
            <th>Type</th>
            <th>Mode</th>
            <th>Flags</th>
            <th></th>
          </tr>
        </thead>
        <tbody>${rows}</tbody>
      </table>
    </div>`;

  document.getElementById('rs-rules-tab-add-btn')?.addEventListener('click', () => openRuleCreateModal(rs.id));

  pane.querySelectorAll('[data-rule-id]').forEach(btn => {
    btn.addEventListener('click', async () => {
      if (!confirm('Delete this rule?')) return;
      btn.disabled = true;
      const res = await API.deletePayrollRule(btn.dataset.rsId, btn.dataset.ruleId);
      if (res.status === 200) {
        toast('Rule deleted.', 'success');
        await loadRuleSetDetail(_currentRuleSet.id);
      } else {
        toast(res.body?.message || 'Delete failed.', 'error');
        btn.disabled = false;
      }
    });
  });
}

async function rsDeleteCurrent() {
  if (!_currentRuleSet) return;
  if (!confirm(`Delete rule set "${_currentRuleSet.name}"? This cannot be undone.`)) return;
  const btn = document.getElementById('rsd-delete-rs-btn');
  if (btn) { btn.disabled = true; btn.innerHTML = '<i class="fa fa-spinner fa-spin"></i> Deleting…'; }
  const res = await API.deletePayrollRuleSet(_currentRuleSet.id);
  if (res.status === 200) {
    toast('Rule set deleted.', 'success');
    closeRuleSetDetail();
    loadRuleSets();
  } else {
    toast(res.body?.message || 'Delete failed.', 'error');
    if (btn) { btn.disabled = false; btn.innerHTML = '<i class="fa fa-trash"></i> Delete Rule Set'; }
  }
}

// Rule set detail tab switching
document.getElementById('rule-set-detail-view')?.addEventListener('click', e => {
  const tab = e.target.closest('.inv-tab');
  if (!tab || !tab.dataset.tab) return;
  $$('#rule-set-detail-view .inv-tab').forEach(t => t.classList.toggle('active', t === tab));
  $$('#rule-set-detail-view .inv-tab-pane').forEach(p => p.classList.toggle('active', p.id === `rs-pane-${tab.dataset.tab}`));
});

$('#rs-back-btn')?.addEventListener('click', closeRuleSetDetail);
$('#rs-search')?.addEventListener('input', e => loadRuleSets(e.target.value));
$('#rs-add-btn')?.addEventListener('click', () => openRuleSetCreateModal());
$('#rs-template-btn')?.addEventListener('click', () => openTemplatePage());

// ── Rule Set Create Modal ─────────────────────────────────────────────────────

function openRuleSetCreateModal() {
  const alertEl = $('#rs-create-alert');
  if (alertEl) alertEl.style.display = 'none';
  const nameEl = $('#rsc-name'); if (nameEl) nameEl.value = '';
  const currEl = $('#rsc-currency'); if (currEl) currEl.value = 'LKR';
  const fromEl = $('#rsc-effective-from'); if (fromEl) fromEl.value = '';
  const notesEl = $('#rsc-notes'); if (notesEl) notesEl.value = '';
  const defEl = $('#rsc-is-default'); if (defEl) defEl.checked = false;
  $('#rs-create-modal').style.display = 'flex';
}

function closeRuleSetCreateModal() {
  $('#rs-create-modal').style.display = 'none';
}

document.getElementById('rs-create-modal-close')?.addEventListener('click',  closeRuleSetCreateModal);
document.getElementById('rs-create-modal-cancel')?.addEventListener('click', closeRuleSetCreateModal);
document.getElementById('rs-create-modal')?.addEventListener('click', e => { if (e.target === e.currentTarget) closeRuleSetCreateModal(); });

document.getElementById('rs-create-modal-save')?.addEventListener('click', async () => {
  const alertEl = $('#rs-create-alert');
  alertEl.style.display = 'none';

  const name        = $('#rsc-name').value.trim();
  const currency    = $('#rsc-currency').value.trim();
  const effFrom     = $('#rsc-effective-from').value;
  const notes       = $('#rsc-notes').value.trim();
  const is_default  = $('#rsc-is-default').checked;

  if (!name || !currency || !effFrom) {
    alertEl.textContent = 'Name, currency and effective date are required.';
    alertEl.style.display = '';
    return;
  }

  const btn = document.getElementById('rs-create-modal-save');
  btn.disabled = true;
  btn.innerHTML = '<i class="fa fa-spinner fa-spin"></i> Creating…';

  const body = { name, currency, effective_from: effFrom, is_default };
  if (notes) body.notes = notes;

  const res = await API.createPayrollRuleSet(body);

  btn.disabled = false;
  btn.innerHTML = '<i class="fa fa-plus"></i> Create Rule Set';

  if (res.status === 201) {
    closeRuleSetCreateModal();
    toast('Rule set created.', 'success');
    loadRuleSets();
  } else {
    alertEl.textContent = res.body?.message || 'Failed to create rule set.';
    alertEl.style.display = '';
  }
});

// ── Rule Create Modal ──────────────────────────────────────────────────────────

function openRuleCreateModal(ruleSetId) {
  _ruleCreateTargetRsId = ruleSetId;
  const alertEl = $('#rule-create-alert');
  if (alertEl) alertEl.style.display = 'none';
  $('#rc-code').value = '';
  $('#rc-name').value = '';
  $('#rc-type').value = '';
  $('#rc-mode').value = '';
  $('#rc-sort-order').value = '0';
  $('#rc-is-taxable').checked = false;
  $('#rc-is-statutory').checked = false;
  _rcShowConfigSection('');
  $('#rule-create-modal').style.display = 'flex';
}

function closeRuleCreateModal() {
  $('#rule-create-modal').style.display = 'none';
  _ruleCreateTargetRsId = null;
}

function _rcShowConfigSection(mode) {
  $('#rc-cfg-fixed').style.display      = mode === 'fixed'      ? '' : 'none';
  $('#rc-cfg-percentage').style.display = mode === 'percentage'  ? '' : 'none';
  $('#rc-cfg-json').style.display       = (mode === 'slab' || mode === 'formula') ? '' : 'none';
  $('#rc-cfg-placeholder').style.display = mode ? 'none' : '';
  if (mode === 'fixed') { $('#rc-fixed-amount').value = ''; }
  if (mode === 'percentage') { $('#rc-pct-percent').value = ''; $('#rc-pct-base').value = 'basic_salary'; }
  if (mode === 'slab' || mode === 'formula') { $('#rc-config-json').value = ''; }
}

$('#rc-mode')?.addEventListener('change', e => _rcShowConfigSection(e.target.value));

document.getElementById('rule-create-modal-close')?.addEventListener('click',  closeRuleCreateModal);
document.getElementById('rule-create-modal-cancel')?.addEventListener('click', closeRuleCreateModal);
document.getElementById('rule-create-modal')?.addEventListener('click', e => { if (e.target === e.currentTarget) closeRuleCreateModal(); });

document.getElementById('rule-create-modal-save')?.addEventListener('click', async () => {
  const alertEl = $('#rule-create-alert');
  alertEl.style.display = 'none';

  const code   = $('#rc-code').value.trim().toUpperCase().replace(/[^A-Z0-9_]/g, '_');
  const name   = $('#rc-name').value.trim();
  const type   = $('#rc-type').value;
  const mode   = $('#rc-mode').value;
  const sortOrd = parseInt($('#rc-sort-order').value) || 0;
  const isTax  = $('#rc-is-taxable').checked;
  const isStat = $('#rc-is-statutory').checked;

  if (!code || !name || !type || !mode) {
    alertEl.textContent = 'Code, name, component type and calculation mode are required.';
    alertEl.style.display = '';
    return;
  }

  const body = {
    code, name,
    component_type: type,
    calculation_mode: mode,
    sort_order: sortOrd,
    is_taxable: isTax,
    is_statutory: isStat,
  };

  if (mode === 'fixed') {
    const amt = parseFloat($('#rc-fixed-amount').value);
    if (isNaN(amt)) { alertEl.textContent = 'Amount is required for fixed mode.'; alertEl.style.display = ''; return; }
    body.config_json = JSON.stringify({ amount: amt });
  } else if (mode === 'percentage') {
    const pct  = parseFloat($('#rc-pct-percent').value);
    const base = $('#rc-pct-base').value;
    if (isNaN(pct)) { alertEl.textContent = 'Percent is required for percentage mode.'; alertEl.style.display = ''; return; }
    body.config_json = JSON.stringify({ percent: pct, base_field: base });
  } else if (mode === 'slab' || mode === 'formula') {
    const jsonStr = $('#rc-config-json').value.trim();
    if (!jsonStr) { alertEl.textContent = 'Config JSON is required for this mode.'; alertEl.style.display = ''; return; }
    try { JSON.parse(jsonStr); } catch(e) { alertEl.textContent = 'Config JSON is not valid JSON.'; alertEl.style.display = ''; return; }
    body.config_json = jsonStr;
  }

  const btn = document.getElementById('rule-create-modal-save');
  btn.disabled = true;
  btn.innerHTML = '<i class="fa fa-spinner fa-spin"></i> Adding…';

  const res = await API.createPayrollRule(_ruleCreateTargetRsId, body);

  btn.disabled = false;
  btn.innerHTML = '<i class="fa fa-plus"></i> Add Rule';

  if (res.status === 201) {
    closeRuleCreateModal();
    toast('Rule added.', 'success');
    if (_currentRuleSet && _currentRuleSet.id === _ruleCreateTargetRsId) {
      await loadRuleSetDetail(_currentRuleSet.id);
    } else {
      loadRuleSets();
    }
  } else {
    alertEl.textContent = res.body?.message || 'Failed to add rule.';
    alertEl.style.display = '';
  }
});

// ── Template Install Page ──────────────────────────────────────────────────────

const TPL_PALETTES = [
  { bar: '#7c3aed', bg: '#f5f3ff', color: '#7c3aed', icon: 'fa-landmark'        },
  { bar: '#2563eb', bg: '#eff6ff', color: '#2563eb', icon: 'fa-building-columns' },
  { bar: '#d97706', bg: '#fef3c7', color: '#d97706', icon: 'fa-globe'            },
  { bar: '#16a34a', bg: '#dcfce7', color: '#16a34a', icon: 'fa-leaf'             },
  { bar: '#e11d48', bg: '#ffe4e6', color: '#e11d48', icon: 'fa-scale-balanced'   },
];

function openTemplatePage() {
  // Hide all other HR views
  $('#rule-sets-list-view').style.display    = 'none';
  $('#rule-set-detail-view').style.display   = 'none';
  $('#payroll-list-view').style.display      = 'none';
  $('#payroll-cycle-detail-view').style.display = 'none';
  $('#employees-list-view').style.display    = 'none';
  $('#departments-list-view').style.display  = 'none';
  // Clear subnav active state
  $$('#panel-hr > .fin-subnav .fin-subnav-btn[data-hr]').forEach(b => b.classList.remove('active'));

  const alertEl = $('#template-page-alert');
  if (alertEl) alertEl.style.display = 'none';
  const area = $('#template-page-cards');
  if (area) area.innerHTML = '<div class="tpl-cards-loading"><i class="fa fa-spinner fa-spin"></i> Loading templates…</div>';
  $('#template-page-view').style.display = 'flex';

  loadTemplatePage();
}

async function loadTemplatePage() {
  const area = $('#template-page-cards');
  if (!area) return;

  const res = await API.payrollTemplates();
  if (res.status !== 200) {
    area.innerHTML = '<div class="tpl-cards-loading">Failed to load templates.</div>';
    return;
  }

  const templates = res.body.data || [];
  if (!templates.length) {
    area.innerHTML = '<div class="tpl-cards-loading"><i class="fa fa-inbox" style="font-size:28px;opacity:.3"></i><br><br>No templates available.</div>';
    return;
  }

  area.innerHTML = '';
  templates.forEach((t, idx) => {
    const pal = TPL_PALETTES[idx % TPL_PALETTES.length];
    const highlights = (t.highlights || [])
      .map(h => `<li><i class="fa fa-check" style="color:${pal.color}"></i>${h}</li>`)
      .join('');
    const rulesHint = (t.highlights || []).length
      ? `${(t.highlights || []).length} included components`
      : '';
    const card = document.createElement('div');
    card.className = 'tpl-card';
    card.innerHTML = `
      <div class="tpl-card-bar" style="background:${pal.bar}"></div>
      <div class="tpl-card-header">
        <div class="tpl-card-icon-wrap" style="background:${pal.bg};color:${pal.color}">
          <i class="fa ${pal.icon}"></i>
        </div>
        <div class="tpl-card-title">${t.title || t.key}</div>
      </div>
      <div class="tpl-card-content">
        ${t.description ? `<p class="tpl-card-desc">${t.description}</p>` : ''}
        ${highlights ? `<ul class="tpl-card-highlights">${highlights}</ul>` : ''}
      </div>
      <div class="tpl-card-footer">
        <span class="tpl-card-footer-meta">${rulesHint}</span>
        <button class="tpl-install-btn" data-key="${t.key}">
          <i class="fa fa-wand-magic-sparkles"></i> Install
        </button>
      </div>`;
    area.appendChild(card);
  });

  area.querySelectorAll('.tpl-install-btn').forEach(btn => {
    btn.addEventListener('click', async () => {
      const key = btn.dataset.key;
      const alertEl = $('#template-page-alert');
      if (alertEl) alertEl.style.display = 'none';
      btn.disabled = true;
      btn.innerHTML = '<i class="fa fa-spinner fa-spin"></i> Installing…';
      const res = await API.installPayrollTemplate(key);
      btn.disabled = false;
      btn.innerHTML = '<i class="fa fa-wand-magic-sparkles"></i> Install';
      if (res.status === 200) {
        toast(res.body?.message || 'Template installed.', 'success');
        switchHrView('rule-sets');
      } else {
        if (alertEl) { alertEl.textContent = res.body?.message || 'Installation failed.'; alertEl.style.display = ''; }
      }
    });
  });
}

$('#tpl-page-back-btn')?.addEventListener('click', () => switchHrView('rule-sets'));

// ── HR Allowance Types ─────────────────────────────────────────────────────

let _allowanceTypes = [];

async function loadAllowanceTypes(search) {
  const area   = $('#at-cards-area');
  const footer = $('#at-footer-text');
  area.innerHTML = '<p style="color:var(--text-muted);padding:20px 0"><i class="fa fa-spinner fa-spin"></i> Loading allowance types…</p>';

  const res = await API.allowanceTypes();
  if (res.status !== 200) {
    area.innerHTML = '<p style="color:#e74c3c"><i class="fa fa-circle-exclamation"></i> Failed to load allowance types.</p>';
    return;
  }

  _allowanceTypes = res.body.data || [];

  const q = (search || '').toLowerCase().trim();
  const filtered = q ? _allowanceTypes.filter(t => t.name.toLowerCase().includes(q)) : _allowanceTypes;

  $('#at-total-count').textContent = _allowanceTypes.length;

  if (!filtered.length) {
    area.innerHTML = `<p style="color:var(--text-muted);text-align:center;padding:40px 0"><i class="fa fa-coins" style="font-size:32px;display:block;margin-bottom:12px;opacity:.3"></i>${_allowanceTypes.length ? 'No allowance types match your search.' : 'No allowance types yet. Click <strong>Add Allowance Type</strong> to get started.'}</p>`;
    footer.textContent = filtered.length + ' allowance types';
    return;
  }

  area.innerHTML = filtered.map(t => buildAllowanceTypeCard(t)).join('');
  footer.textContent = `${filtered.length} allowance type${filtered.length !== 1 ? 's' : ''}`;

  area.querySelectorAll('.at-delete-btn').forEach(btn => {
    btn.addEventListener('click', async (e) => {
      e.stopPropagation();
      const id   = parseInt(btn.dataset.id, 10);
      const name = btn.dataset.name;
      if (!confirm(`Delete allowance type "${name}"? This cannot be undone.`)) return;
      btn.disabled = true;
      btn.innerHTML = '<i class="fa fa-spinner fa-spin"></i>';
      const r = await API.deleteAllowanceType(id);
      if (r.status === 200) {
        toast('Allowance type deleted.', 'success');
        await loadAllowanceTypes($('#at-search').value);
      } else {
        toast(r.body?.message || 'Failed to delete.', 'error');
        btn.disabled = false;
        btn.innerHTML = '<i class="fa fa-trash"></i>';
      }
    });
  });
}

function buildAllowanceTypeCard(t) {
  const canDelete = (t.employees_count || 0) === 0;
  return `
    <div class="lm-card" style="cursor:default">
      <div class="lm-card-header">
        <div class="lm-card-icon" style="background:#fef3c720;color:#d97706"><i class="fa fa-coins"></i></div>
        <div class="lm-card-title-wrap">
          <span class="lm-card-name">${escHtml(t.name)}</span>
          <div class="lm-card-pills">
            <span class="lm-pill" style="background:#fef3c718;color:#d97706">${t.employees_count || 0} employee${(t.employees_count || 0) !== 1 ? 's' : ''}</span>
          </div>
        </div>
        ${canDelete
          ? `<button class="lm-remove-btn at-delete-btn" data-id="${t.id}" data-name="${escHtml(t.name)}" title="Delete allowance type"><i class="fa fa-trash"></i></button>`
          : `<span style="font-size:11px;color:var(--text-muted);white-space:nowrap;padding-right:4px">In use</span>`}
      </div>
    </div>`;
}

$('#at-search').addEventListener('input', e => loadAllowanceTypes(e.target.value));
$('#at-add-btn').addEventListener('click', openAtCreateModal);

// ── Allowance Type: Create Modal ───────────────────────────────────────────

function openAtCreateModal() {
  $('#at-modal-name').value = '';
  showAtError('');
  $('#at-create-modal').style.display = '';
  setTimeout(() => $('#at-modal-name').focus(), 60);
}

function closeAtCreateModal() {
  $('#at-create-modal').style.display = 'none';
}

function showAtError(msg) {
  const el = $('#at-create-alert');
  el.textContent = msg;
  el.style.display = msg ? '' : 'none';
}

$('#at-create-modal-close').addEventListener('click', closeAtCreateModal);
$('#at-create-modal-cancel').addEventListener('click', closeAtCreateModal);
$('#at-create-modal').addEventListener('click', e => { if (e.target === $('#at-create-modal')) closeAtCreateModal(); });
$('#at-modal-name').addEventListener('keydown', e => { if (e.key === 'Enter') submitAtCreate(); });
$('#at-create-modal-save').addEventListener('click', submitAtCreate);

async function submitAtCreate() {
  const name = $('#at-modal-name').value.trim();
  if (!name) { showAtError('Allowance type name is required.'); return; }

  const btn = $('#at-create-modal-save');
  btn.disabled = true;
  btn.innerHTML = '<i class="fa fa-spinner fa-spin"></i> Saving…';

  const res = await API.createAllowanceType({ name });

  btn.disabled = false;
  btn.innerHTML = '<i class="fa fa-plus"></i> Create';

  if (res.status === 201) {
    closeAtCreateModal();
    toast('Allowance type created.', 'success');
    await loadAllowanceTypes();
  } else {
    const msg = res.body?.errors?.name?.[0] || res.body?.message || 'Failed to create allowance type.';
    showAtError(msg);
  }
}

// ── Design Studio ──────────────────────────────────────────────────────────

const DS_TYPES = {
  'letterhead':       { label: 'Letterhead',       icon: 'fa-file-lines',   color: '#6366f1', bg: '#6366f120' },
  'company-profile':  { label: 'Business Profile', icon: 'fa-building',     color: '#0ea5e9', bg: '#0ea5e920' },
  'social-media':     { label: 'Social Media',     icon: 'fa-share-nodes',  color: '#ec4899', bg: '#ec489920' },
  'business-card':    { label: 'Business Card',    icon: 'fa-id-card',      color: '#f59e0b', bg: '#f59e0b20' },
  'custom':           { label: 'Custom',           icon: 'fa-paintbrush',   color: '#10b981', bg: '#10b98120' },
};

// These types are singletons — only one allowed per business; shown as fixed cards, not in the list
const DS_SINGLETON_TYPES = ['letterhead', 'company-profile'];

const DS_TYPE_DEFAULTS = {
  'letterhead':       { w: 794,  h: 1123,  title: 'Business Letterhead' },
  'company-profile':  { w: 1920, h: 1080,  title: 'Business Profile' },
  'social-media':     { w: 1080, h: 1080,  title: '' },
  'business-card':    { w: 1050, h: 600,   title: '' },
};

let _dsActiveType = 'all';
let _dsAllData    = [];

function switchDesignView(type) {
  _dsActiveType = type || 'all';
  $$('#panel-design .fin-subnav .fin-subnav-btn[data-ds]').forEach(b => {
    b.classList.toggle('active', b.dataset.ds === _dsActiveType);
  });
  _dsAllData = [];
  loadDesigns();
}

const DS_EMPTY_META = {
  'all':          { icon: 'fa-paintbrush',        color: '#6366f1', bg: '#6366f120', title: 'No designs yet',            sub: 'Create your first social media post, business card, or custom design.' },
  'social-media': { icon: 'fa-share-nodes',        color: '#ec4899', bg: '#ec489920', title: 'No social media posts yet', sub: 'Design eye-catching posts for Instagram, Facebook, YouTube and more.' },
  'business-card':{ icon: 'fa-id-card',            color: '#f59e0b', bg: '#f59e0b20', title: 'No business cards yet',    sub: 'Create sleek, professional business card designs ready to print.' },
  'custom':       { icon: 'fa-wand-magic-sparkles', color: '#10b981', bg: '#10b98120', title: 'No custom designs yet',   sub: 'Start with a blank canvas at any size for your own creative projects.' },
};

function updateSingletonCards() {
  DS_SINGLETON_TYPES.forEach(type => {
    const design   = _dsAllData.find(d => d.type === type);
    const statusEl = $('#ds-singleton-status-' + type);
    if (!statusEl) return;
    if (design) {
      statusEl.className = 'ds-singleton-badge ds-singleton-badge-ready';
      statusEl.innerHTML = '<i class="fa fa-pen-to-square"></i> ' + escHtml(design.has_canvas ? 'Edit' : 'Open');
    } else {
      statusEl.className = 'ds-singleton-badge ds-singleton-badge-new';
      statusEl.innerHTML = '<i class="fa fa-plus"></i> Create';
    }
  });
}

async function loadDesigns() {
  const search = ($('#ds-search').value || '').toLowerCase().trim();
  const area   = $('#ds-cards-area');

  if (_dsAllData.length === 0) {
    area.innerHTML = '<div class="ds-loading"><i class="fa fa-spinner fa-spin"></i> Loading designs…</div>';
    const res = await API.designs();
    if (res.status !== 200) {
      area.innerHTML = '<div class="ds-empty"><div class="ds-empty-icon-wrap" style="background:#fef2f2;color:#dc2626"><i class="fa fa-triangle-exclamation"></i></div><p class="ds-empty-title">Failed to load designs</p><p class="ds-empty-sub">Check your connection and try again.</p></div>';
      return;
    }
    // Only overwrite if still empty — prevents a concurrent openSingletonDesign
    // call that already populated the array from being clobbered by this stale response.
    if (_dsAllData.length === 0) {
      _dsAllData = res.body?.data || [];
    } else {
      // Merge server data in without losing any items already added client-side
      const serverIds = new Set((res.body?.data || []).map(d => d.id));
      const clientOnly = _dsAllData.filter(d => !serverIds.has(d.id));
      _dsAllData = [...(res.body?.data || []), ...clientOnly];
    }

    const byType = res.body?.by_type || {};
    $('#ds-total-count').textContent          = res.body?.total_count ?? _dsAllData.length;
    $('#ds-count-social-media').textContent   = byType['social-media']   ?? 0;
    $('#ds-count-business-card').textContent  = byType['business-card']  ?? 0;
    $('#ds-count-custom').textContent         = byType['custom']         ?? 0;
    updateSingletonCards();
  }

  // Singletons are shown in their own section — exclude from the list
  let list = _dsAllData.filter(d => !DS_SINGLETON_TYPES.includes(d.type));
  if (_dsActiveType !== 'all') {
    if (_dsActiveType === 'custom') {
      list = list.filter(d => !d.type || d.type === 'custom');
    } else {
      list = list.filter(d => d.type === _dsActiveType);
    }
  }
  if (search) {
    list = list.filter(d => (d.title || '').toLowerCase().includes(search));
  }

  if (list.length === 0) {
    const em = DS_EMPTY_META[_dsActiveType] || DS_EMPTY_META['all'];
    const btnLabel = _dsActiveType !== 'all'
      ? `New ${DS_TYPES[_dsActiveType]?.label || 'Design'}`
      : 'New Design';
    area.innerHTML = `
      <div class="ds-empty">
        <div class="ds-empty-icon-wrap" style="background:${em.bg};color:${em.color}">
          <i class="fa ${em.icon}"></i>
        </div>
        <p class="ds-empty-title">${em.title}</p>
        <p class="ds-empty-sub">${em.sub}</p>
        <button class="ds-empty-btn" onclick="openDsCreateModal()">
          <i class="fa fa-plus"></i> ${escHtml(btnLabel)}
        </button>
      </div>`;
    $('#ds-footer-text').textContent = '';
    return;
  }

  area.innerHTML = list.map(buildDesignCard).join('');
  $('#ds-footer-text').textContent = `${list.length} design${list.length !== 1 ? 's' : ''}`;

  area.querySelectorAll('.ds-delete-btn').forEach(btn => {
    btn.addEventListener('click', async () => {
      const id    = parseInt(btn.dataset.id, 10);
      const title = btn.dataset.title;
      if (!confirm(`Delete "${title}"? This cannot be undone.`)) return;
      btn.disabled = true;
      const res = await API.deleteDesign(id);
      if (res.status === 200) {
        toast('Design deleted.', 'success');
        _dsAllData = _dsAllData.filter(d => d.id !== id);
        loadDesigns();
      } else {
        btn.disabled = false;
        toast(res.body?.message || 'Failed to delete design.', 'error');
      }
    });
  });

  area.querySelectorAll('.ds-open-btn').forEach(btn => {
    btn.addEventListener('click', () => openDesignEditor(parseInt(btn.dataset.id, 10)));
  });
}

async function openDesignEditor(id) {
  const btn = $('#ds-cards-area').querySelector(`.ds-open-btn[data-id="${id}"]`);
  if (btn) { btn.disabled = true; btn.innerHTML = '<i class="fa fa-spinner fa-spin"></i>'; }
  const res = await API.design(id);
  if (btn) { btn.disabled = false; btn.innerHTML = '<i class="fa fa-pen-to-square"></i>'; }
  if (res.status === 200 && res.body?.data) {
    await window.electronAPI.openEditor(res.body.data);
  } else {
    toast(res.body?.message || 'Failed to open editor.', 'error');
  }
}

async function openSingletonDesign(type) {
  const cardEl = $('#ds-singleton-' + type);
  if (cardEl) cardEl.classList.add('ds-singleton-loading');

  try {
    // Load all designs if not yet cached
    if (_dsAllData.length === 0) {
      const list = await API.designs();
      if (list.status === 200) {
        // Only overwrite if still empty — avoids clobbering data another
        // concurrent call (e.g. loadDesigns) may have written between the
        // fetch start and now.
        if (_dsAllData.length === 0) {
          _dsAllData = list.body?.data || [];
        }
        updateSingletonCards();
      }
    }

    let design = _dsAllData.find(d => d.type === type);

    if (!design) {
      // Auto-create the singleton with fixed defaults
      const def = DS_TYPE_DEFAULTS[type];
      const createRes = await API.createDesign({
        title: def.title,
        type,
        width:  def.w,
        height: def.h,
      });
      if (createRes.status === 201) {
        design = createRes.body.data;
        _dsAllData.push(design);
        updateSingletonCards();
      } else if (createRes.status === 422 && createRes.body?.data) {
        // Singleton already existed on the server — use the returned record
        design = createRes.body.data;
        if (!_dsAllData.find(d => d.id === design.id)) {
          _dsAllData.push(design);
          updateSingletonCards();
        }
      } else {
        toast(createRes.body?.message || 'Failed to create design.', 'error');
        return;
      }
    }

    // Fetch full design (includes canvas_json) then open editor
    const res = await API.design(design.id);
    if (res.status === 200 && res.body?.data) {
      const fresh = res.body.data;
      // Sync has_canvas in the cache so the badge shows the right state
      const idx = _dsAllData.findIndex(d => d.id === fresh.id);
      if (idx !== -1) {
        _dsAllData[idx] = { ..._dsAllData[idx], has_canvas: fresh.has_canvas };
        updateSingletonCards();
      }
      await window.electronAPI.openEditor(fresh);
    } else {
      toast(res.body?.message || 'Failed to open editor.', 'error');
    }
  } catch (err) {
    toast(err?.message || 'Failed to open design.', 'error');
  } finally {
    if (cardEl) cardEl.classList.remove('ds-singleton-loading');
  }
}

function buildDesignCard(d) {
  const typeKey = d.type || 'custom';
  const t  = DS_TYPES[typeKey] || DS_TYPES['custom'];
  const dim  = `${d.width} × ${d.height}`;
  const date = d.updated_at ? d.updated_at.split(' ')[0] : (d.created_at || '');
  const canvasBadge = d.has_canvas
    ? `<span class="ds-card-pill" style="background:#10b98115;color:#059669"><i class="fa fa-check"></i> Saved</span>`
    : `<span class="ds-card-pill"><i class="fa fa-plus"></i> New</span>`;

  return `<div class="ds-card">
    <div class="ds-card-bar" style="background:${t.color}"></div>
    <div class="ds-card-body">
      <div class="ds-card-top">
        <div class="ds-card-icon" style="background:${t.bg};color:${t.color}"><i class="fa ${t.icon}"></i></div>
        <div class="ds-card-meta">
          <span class="ds-card-title" title="${escHtml(d.title || 'Untitled')}">${escHtml(d.title || 'Untitled')}</span>
          <span class="ds-card-type-badge" style="background:${t.bg};color:${t.color}">${escHtml(t.label)}</span>
        </div>
      </div>
      <div class="ds-card-pills">
        <span class="ds-card-pill"><i class="fa fa-ruler-combined"></i> ${escHtml(dim)}</span>
        ${canvasBadge}
      </div>
    </div>
    <div class="ds-card-footer">
      <span class="ds-card-date"><i class="fa fa-clock" style="margin-right:3px"></i>${escHtml(date)}</span>
      <div style="display:flex;gap:5px;align-items:center">
        <button class="ds-open-btn" data-id="${d.id}" title="Open in editor"><i class="fa fa-pen-to-square"></i></button>
        <button class="ds-delete-btn" data-id="${d.id}" data-title="${escHtml(d.title || 'Untitled')}" title="Delete design"><i class="fa fa-trash"></i></button>
      </div>
    </div>
  </div>`;
}

// ── Design: subnav ─────────────────────────────────────────────────────────
$('#panel-design .fin-subnav').addEventListener('click', e => {
  const btn = e.target.closest('[data-ds]');
  if (btn) switchDesignView(btn.dataset.ds);
});

$('#ds-search').addEventListener('input', () => loadDesigns());

$('#ds-new-btn').addEventListener('click', openDsCreateModal);

// ── Design: singleton cards ────────────────────────────────────────────────
$('#ds-singleton-letterhead').addEventListener('click',      () => openSingletonDesign('letterhead'));
$('#ds-singleton-company-profile').addEventListener('click', () => openSingletonDesign('company-profile'));

// ── Design: ribbon buttons ─────────────────────────────────────────────────
$('#rb-design-new').addEventListener('click', () => { activateTab('design'); openDsCreateModal(); });
$('#rb-design-all').addEventListener('click', () => { activateTab('design'); switchDesignView('all'); });
// Singletons: ribbon buttons open/create directly in the editor
$('#rb-design-letterhead').addEventListener('click',      () => { activateTab('design'); openSingletonDesign('letterhead'); });
$('#rb-design-company-profile').addEventListener('click', () => { activateTab('design'); openSingletonDesign('company-profile'); });
$('#rb-design-social-media').addEventListener('click',    () => { activateTab('design'); switchDesignView('social-media'); });
$('#rb-design-business-card').addEventListener('click',   () => { activateTab('design'); switchDesignView('business-card'); });

// ── Design: Create Modal ───────────────────────────────────────────────────

function openDsCreateModal() {
  $('#ds-modal-title').value  = '';
  $('#ds-modal-type').value   = _dsActiveType !== 'all' && _dsActiveType !== 'custom' ? _dsActiveType : '';
  $('#ds-modal-preset').value = '';
  _dsApplyTypeDefaults($('#ds-modal-type').value);
  showDsError('');
  $('#ds-create-modal').style.display = '';
  setTimeout(() => $('#ds-modal-title').focus(), 60);
}

function closeDsCreateModal() {
  $('#ds-create-modal').style.display = 'none';
}

function showDsError(msg) {
  const el = $('#ds-create-alert');
  el.textContent = msg;
  el.style.display = msg ? '' : 'none';
}

function _dsApplyTypeDefaults(type) {
  const d = DS_TYPE_DEFAULTS[type];
  if (d) {
    $('#ds-modal-width').value  = d.w;
    $('#ds-modal-height').value = d.h;
  }
}

$('#ds-modal-type').addEventListener('change', () => {
  $('#ds-modal-preset').value = '';
  _dsApplyTypeDefaults($('#ds-modal-type').value);
});

$('#ds-modal-preset').addEventListener('change', () => {
  const val = $('#ds-modal-preset').value;
  if (!val) return;
  const [w, h] = val.split('x').map(Number);
  $('#ds-modal-width').value  = w;
  $('#ds-modal-height').value = h;
});

$('#ds-create-modal-close').addEventListener('click', closeDsCreateModal);
$('#ds-create-modal-cancel').addEventListener('click', closeDsCreateModal);
$('#ds-create-modal').addEventListener('click', e => { if (e.target === $('#ds-create-modal')) closeDsCreateModal(); });

$('#ds-create-modal-save').addEventListener('click', submitDsCreate);

async function submitDsCreate() {
  const title  = $('#ds-modal-title').value.trim();
  const type   = $('#ds-modal-type').value.trim() || null;
  const width  = parseInt($('#ds-modal-width').value, 10);
  const height = parseInt($('#ds-modal-height').value, 10);

  if (!title) { showDsError('Design title is required.'); return; }
  if (!width  || width  < 100 || width  > 8000) { showDsError('Width must be between 100 and 8000 px.'); return; }
  if (!height || height < 100 || height > 8000) { showDsError('Height must be between 100 and 8000 px.'); return; }

  const btn = $('#ds-create-modal-save');
  btn.disabled = true;
  btn.innerHTML = '<i class="fa fa-spinner fa-spin"></i> Creating…';

  const res = await API.createDesign({ title, type, width, height });

  btn.disabled = false;
  btn.innerHTML = '<i class="fa fa-plus"></i> Create Design';

  if (res.status === 201) {
    closeDsCreateModal();
    toast('Design created.', 'success');
    _dsAllData = [];
    switchDesignView(_dsActiveType);
  } else {
    const msg = res.body?.errors?.title?.[0] || res.body?.message || 'Failed to create design.';
    showDsError(msg);
  }
}

// ── Boot ───────────────────────────────────────────────────────────────────
init();
