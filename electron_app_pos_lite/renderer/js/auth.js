'use strict';

const tabLogin = document.getElementById('tab-login');
const tabSignup = document.getElementById('tab-signup');
const loginForm = document.getElementById('login-form');
const signupWizard = document.getElementById('signup-wizard');
const signupForm = document.getElementById('signup-form');
const lineLogin = document.getElementById('line-login');
const lineSignup = document.getElementById('line-signup');
const loginError = document.getElementById('login-error');
const signupError = document.getElementById('signup-error');

const cashierForm = document.getElementById('cashier-form');

// 'login' | 'cashier' | 'signup' — the cashier form lives under the Login tab.
function showTab(which) {
  const isLogin = which !== 'signup';
  const wasSignupActive = tabSignup.classList.contains('active');
  tabLogin.classList.toggle('active', isLogin);
  tabSignup.classList.toggle('active', !isLogin);
  loginForm.classList.toggle('active', which === 'login');
  cashierForm.classList.toggle('active', which === 'cashier');
  signupWizard.classList.toggle('active', !isLogin);
  lineLogin.style.display = isLogin ? '' : 'none';
  lineSignup.style.display = isLogin ? 'none' : '';
  setError(loginError, '');
  setError(signupError, '');
  if (!isLogin && !wasSignupActive) _obSetStep(1); // fresh entry into sign-up: start the wizard over
}

tabLogin.addEventListener('click', () => showTab('login'));
tabSignup.addEventListener('click', () => showTab('signup'));
document.getElementById('go-signup').addEventListener('click', () => showTab('signup'));
document.getElementById('go-login').addEventListener('click', () => showTab('login'));
document.getElementById('go-cashier').addEventListener('click', () => {
  showTab('cashier');
  const biz = document.getElementById('cashier-business');
  (biz.value ? document.getElementById('cashier-username') : biz).focus();
});
document.getElementById('go-owner').addEventListener('click', () => showTab('login'));

// Inputs per form, keyed by the API's validation-error field names, so a
// server error can highlight the field it's about.
const FIELD_INPUTS = {
  login: { email: 'login-email', password: 'login-password' },
  cashier: { slug: 'cashier-business', username: 'cashier-username', password: 'cashier-password' },
  signup: { name: 'su-name', business_name: 'su-business', business_category: 'su-category', email: 'su-email', password: 'su-password' },
};

function clearFieldErrors() {
  document.querySelectorAll('.card .invalid').forEach((el) => el.classList.remove('invalid'));
}

function markField(inputId) {
  const input = inputId && document.getElementById(inputId);
  if (!input) return;
  input.classList.add('invalid');
  input.focus();
}

document.querySelectorAll('.card input, .card select').forEach((el) => {
  const clear = () => el.classList.remove('invalid');
  el.addEventListener('input', clear);
  el.addEventListener('change', clear);
});

// message: string (empty hides the box). opts.field: input id to highlight.
// opts.action: { label, onClick } — an inline link such as "Log in instead".
function setError(box, message, opts = {}) {
  if (!message) {
    box.classList.remove('show');
    box.innerHTML = '';
    clearFieldErrors();
    return;
  }
  box.innerHTML = '<i class="fa-solid fa-circle-exclamation"></i><div class="error-text"></div>';
  box.querySelector('.error-text').textContent = message;
  if (opts.action) {
    const link = document.createElement('a');
    link.textContent = opts.action.label;
    link.addEventListener('click', opts.action.onClick);
    box.querySelector('.error-text').append(' ', link);
  }
  box.classList.remove('show');
  void box.offsetWidth; // restart the shake animation on repeated errors
  box.classList.add('show');
  markField(opts.field);
  box.scrollIntoView({ block: 'nearest', behavior: 'smooth' });
}

// Turns raw Laravel/API messages into plain-language ones.
function friendlyMessage(field, raw) {
  const msg = String(raw || '');
  if (/already been taken/i.test(msg) && field === 'email') {
    return t('An account with this email already exists. Log in instead, or use a different email.');
  }
  if (/credentials (are incorrect|do not match)/i.test(msg)) return t('Incorrect email or password. Please check and try again.');
  if (/invalid username or password/i.test(msg)) return t('Incorrect username or password. Please check and try again.');
  if (/business not found/i.test(msg)) return t("We couldn't find a business with that name. Check the spelling and try again.");
  if (/must be a valid email/i.test(msg)) return t('Please enter a valid email address.');
  const min = msg.match(/password.*at least (\d+) characters/i);
  if (min) return t('Password must be at least {n} characters.').replace('{n}', min[1]);
  if (/password.*confirmation does not match/i.test(msg)) return t('Passwords do not match.');
  if (/field is required/i.test(msg)) return t('Please fill in all required fields.');
  return t(msg);
}

// Reads an API response into { message, field (API key), raw }.
function describeApiError(res) {
  const body = res?.body;
  const status = res?.status;
  if (status === 0) return { message: t("Can't reach the server. Check your internet connection and try again.") };
  if (status === 429) return { message: t('Too many attempts. Please wait a minute and try again.') };
  if (status >= 500) return { message: t('The server ran into a problem. Please try again in a moment.') };
  if (body && typeof body === 'object') {
    const firstKey = body.errors ? Object.keys(body.errors)[0] : null;
    if (firstKey) {
      const raw = [].concat(body.errors[firstKey])[0];
      return { message: friendlyMessage(firstKey, raw), field: firstKey, raw };
    }
    if (body.message) return { message: friendlyMessage(null, body.message), raw: body.message };
  }
  return { message: t('Something went wrong. Please try again.') };
}

// Shows an API error in `box`, highlighting the matching field of `form`.
function showApiError(box, res, form) {
  const err = describeApiError(res);
  const opts = { field: FIELD_INPUTS[form]?.[err.field] };
  if (form === 'login' && !err.field && res?.status === 401) opts.field = 'login-password';
  if (form === 'signup' && err.field === 'email' && /already been taken/i.test(err.raw || '')) {
    opts.action = {
      label: t('Log in instead'),
      onClick: () => {
        document.getElementById('login-email').value = document.getElementById('su-email').value.trim();
        showTab('login');
        document.getElementById('login-password').focus();
      },
    };
  }
  setError(box, err.message, opts);
  return err;
}

function setBusy(button, busy, label) {
  button.disabled = busy;
  button.innerHTML = busy ? `<span class="spinner"></span>${label}` : label;
}

// Persists the token + resolves which business to use, WITHOUT handing off to
// the main window yet — used mid-payment so config survives the app being
// closed while the system browser is open (see main.js's deep-link handler).
async function persistSession(accessToken, user) {
  await window.electronAPI.setConfig({ token: accessToken, user, is_cashier: false });

  const bizRes = await API.businesses();
  const business = bizRes.status === 200 ? (bizRes.body.data || [])[0] : null;
  if (!business) {
    throw new Error(t('No business is linked to this account yet.'));
  }
  await window.electronAPI.setConfig({ business_id: business.id, business_name: business.name, branch_id: null });
}

// After a successful login/register (and, for sign-up, a settled payment):
// persist the session, then hand off to the main POS window.
async function finishAuth(accessToken, user) {
  await persistSession(accessToken, user);
  await window.electronAPI.authSuccess();
}

// ── Login ───────────────────────────────────────────────────────────────
loginForm.addEventListener('submit', async (e) => {
  e.preventDefault();
  setError(loginError, '');
  const email = document.getElementById('login-email').value.trim();
  const password = document.getElementById('login-password').value;
  const submitBtn = document.getElementById('login-submit');

  setBusy(submitBtn, true, t('Log In'));
  try {
    const res = await API.login(email, password);
    if (res.status !== 200) {
      showApiError(loginError, res, 'login');
      return;
    }
    await finishAuth(res.body.access_token, res.body.user);
  } catch (err) {
    setError(loginError, err.message);
  } finally {
    setBusy(submitBtn, false, t('Log In'));
  }
});

// ── Cashier login ───────────────────────────────────────────────────────
// Cashier accounts are created by the owner (Cashiers screen) and scoped to
// one business, which /cashier/login finds by Str::slug(business name) — so
// the cashier types the business name and we slug it the same way here.
// The session is POS-only: main.js blocks other screens, role-guard.js
// locks the dashboard tiles, navbar.js hides owner menu items.
const CASHIER_BUSINESS_KEY = 'cashierBusinessName';

function laravelSlug(value) {
  return value
    .normalize('NFKD').replace(/[̀-ͯ]/g, '')
    .replace(/_+/g, '-')
    .replace(/@/g, '-at-')
    .toLowerCase()
    .replace(/[^-\p{L}\p{N}\s]+/gu, '')
    .replace(/[-\s]+/g, '-')
    .replace(/^-+|-+$/g, '');
}

try {
  document.getElementById('cashier-business').value = localStorage.getItem(CASHIER_BUSINESS_KEY) || '';
} catch (_) {}

cashierForm.addEventListener('submit', async (e) => {
  e.preventDefault();
  setError(loginError, '');
  const businessName = document.getElementById('cashier-business').value.trim();
  const username = document.getElementById('cashier-username').value.trim();
  const password = document.getElementById('cashier-password').value;
  const submitBtn = document.getElementById('cashier-submit');

  const slug = laravelSlug(businessName);
  if (!slug) {
    setError(loginError, t('Enter your business name.'), { field: 'cashier-business' });
    return;
  }

  setBusy(submitBtn, true, t('Cashier Log In'));
  try {
    const res = await API.cashierLogin(slug, username, password);
    const d = res.body?.data;
    if (res.status !== 200 || !d?.token) {
      const err = showApiError(loginError, res, 'cashier');
      if (!err.field && /business not found/i.test(err.raw || '')) markField('cashier-business');
      else if (!err.field && res.status === 401) markField('cashier-password');
      return;
    }
    try { localStorage.setItem(CASHIER_BUSINESS_KEY, businessName); } catch (_) {}
    await window.electronAPI.setConfig({
      token: d.token,
      business_id: d.business_id,
      business_name: d.business_name,
      branch_id: null,
      is_cashier: true,
      user: { name: d.cashier_name, username: d.cashier_username, cashier_id: d.cashier_id, is_cashier: true },
    });
    await window.electronAPI.authSuccess();
  } catch (err) {
    setError(loginError, err.message);
  } finally {
    setBusy(submitBtn, false, t('Cashier Log In'));
  }
});

// ── Sign up: business categories (needed by step 1) ───────────────────────
async function loadBusinessCategories() {
  const select = document.getElementById('su-category');
  try {
    const res = await API.businessCategories();
    const options = res.status === 200 ? (res.body.data || []) : [];
    select.innerHTML = `<option value="">${t('Select a category…')}</option>` +
      options.map((o) => `<option value="${o.value}">${t(o.label)}</option>`).join('');
  } catch (_) {
    select.innerHTML = `<option value="">${t('Unable to load categories')}</option>`;
  }
}
loadBusinessCategories();

// ── Sign-up wizard: Account → Package → Review → Payment ──────────────────
const STEP_COUNT = 4;
let obStep = 1;
let obPackage = null;         // the single "Support POS Lite" package (or null if none configured)
let obPackageLoaded = false;
let obPendingPaymentId = null; // set once /auth/register has created a pending Payment row

function _obSetStep(step) {
  obStep = step;
  document.querySelectorAll('#ob-steps .ob-step').forEach((el) => {
    const n = Number(el.dataset.step);
    el.classList.toggle('active', n === step);
    el.classList.toggle('done', n < step);
  });
  document.querySelectorAll('#signup-wizard > .form[data-step-panel]').forEach((el) => {
    el.classList.toggle('active', Number(el.dataset.stepPanel) === step);
  });
  setError(signupError, '');

  if (step === 2 && !obPackageLoaded) _obLoadPackage();
  if (step === 3) _obRenderReview();
  if (step === 4) _obRenderPaymentSummary();
}

function _obFormatPrice(pkg) {
  const symbol = pkg.currency_symbol || '';
  const hasDiscount = pkg.discounted_price !== null && pkg.discounted_price !== undefined && Number(pkg.discounted_price) < Number(pkg.price);
  const amount = hasDiscount ? pkg.discounted_price : pkg.price;
  const fmt = (n) => Number(n).toLocaleString(undefined, { minimumFractionDigits: 2, maximumFractionDigits: 2 });
  if (pkg.is_free || Number(amount) <= 0) return { html: `<p class="ob-package-price">${t('Free')}</p>`, amount: 0, hasDiscount: false };
  const strike = hasDiscount ? `<span class="ob-package-price-strike">${symbol}${fmt(pkg.price)}</span>` : '';
  return {
    html: `<p class="ob-package-price">${strike}${symbol}${fmt(amount)} <span class="ob-price-period">/ ${t('month')}</span></p>`,
    amount: Number(amount),
    hasDiscount,
  };
}

function _obPackageCardHtml(pkg) {
  const price = _obFormatPrice(pkg);
  const features = (pkg.feature_labels || []).map((f) => `<li><i class="fa-solid fa-circle-check"></i>${f}</li>`).join('');
  return `
    <p class="ob-package-name">${pkg.name}</p>
    ${price.html}
    ${pkg.description ? `<p class="ob-muted" style="margin:6px 0 0;text-align:left;">${pkg.description}</p>` : ''}
    ${features ? `<ul class="ob-feature-list">${features}</ul>` : ''}
  `;
}

async function _obLoadPackage() {
  const card = document.getElementById('ob-package-card');
  card.innerHTML = `<p class="ob-muted">${t('Loading package…')}</p>`;
  try {
    const res = await API.packages();
    const packages = res.status === 200 ? (res.body.data || []) : [];
    obPackage = packages.find((p) => p.supports_pos_lite) || packages[0] || null;
    obPackageLoaded = true;
    if (!obPackage) {
      card.innerHTML = `<p class="ob-muted">${t('No POS Lite package is configured yet. Please contact support.')}</p>`;
      document.getElementById('ob-package-next').disabled = true;
      return;
    }
    document.getElementById('ob-package-next').disabled = false;
    card.innerHTML = _obPackageCardHtml(obPackage);
  } catch (_) {
    card.innerHTML = `<p class="ob-muted">${t('Could not load the package. Please try again.')}</p>`;
  }
}

function _obRenderReview() {
  const card = document.getElementById('ob-review-card');
  const name = document.getElementById('su-name').value.trim();
  const business = document.getElementById('su-business').value.trim();
  const categorySelect = document.getElementById('su-category');
  const category = categorySelect.options[categorySelect.selectedIndex]?.text || '';
  const email = document.getElementById('su-email').value.trim();

  const price = obPackage ? _obFormatPrice(obPackage) : null;

  card.innerHTML = `
    <div class="ob-review-section">
      <h4>${t('Account')}</h4>
      <div class="ob-review-row"><span>${t('Name')}</span><span>${name}</span></div>
      <div class="ob-review-row"><span>${t('Business')}</span><span>${business}</span></div>
      <div class="ob-review-row"><span>${t('Category')}</span><span>${category}</span></div>
      <div class="ob-review-row"><span>${t('Email')}</span><span>${email}</span></div>
    </div>
    <div class="ob-review-section">
      <h4>${t('Package')}</h4>
      <div class="ob-review-row"><span>${t('Plan')}</span><span>${obPackage ? obPackage.name : t('None')}</span></div>
      ${obPackage ? `<div class="ob-review-row"><span>${t('Price')}</span><span>${price.amount > 0 ? (obPackage.currency_symbol + price.amount.toFixed(2) + ' / ' + t('month')) : t('Free')}</span></div>` : ''}
    </div>
  `;
}

function _obRenderPaymentSummary() {
  const box = document.getElementById('ob-payment-summary');
  const submitBtn = document.getElementById('ob-payment-submit');
  document.getElementById('ob-pay-waiting').style.display = 'none';
  submitBtn.style.display = '';
  document.getElementById('ob-payment-back').style.display = '';

  if (!obPackage) {
    box.innerHTML = `<p class="ob-muted">${t('No payment required.')}</p>`;
    setBusy(submitBtn, false, t('Create Account'));
    return;
  }

  const price = _obFormatPrice(obPackage);
  if (price.amount <= 0) {
    box.innerHTML = `<p class="ob-package-name">${obPackage.name}</p><p class="ob-muted">${t('No payment required.')}</p>`;
    setBusy(submitBtn, false, t('Create Account'));
  } else {
    box.innerHTML = `<p class="ob-package-name">${obPackage.name}</p>${price.html}<p class="ob-muted">${t('Billed monthly. Cancel anytime.')}</p>`;
    setBusy(submitBtn, false, t('Pay & Create Account'));
  }
}

// Step 1 (Account) — just validates and advances; the actual API call to
// /auth/register happens once the package + review steps are confirmed.
signupForm.addEventListener('submit', (e) => {
  e.preventDefault();
  setError(signupError, '');
  const password = document.getElementById('su-password').value;
  const password2 = document.getElementById('su-password2').value;
  if (password.length < 8) {
    setError(signupError, t('Password must be at least {n} characters.').replace('{n}', '8'), { field: 'su-password' });
    return;
  }
  if (password !== password2) {
    setError(signupError, t('Passwords do not match.'), { field: 'su-password2' });
    return;
  }
  _obSetStep(2);
});

document.getElementById('ob-package-back').addEventListener('click', () => _obSetStep(1));
document.getElementById('ob-package-next').addEventListener('click', () => _obSetStep(3));
document.getElementById('ob-review-back').addEventListener('click', () => _obSetStep(2));
document.getElementById('ob-review-next').addEventListener('click', () => _obSetStep(4));
document.getElementById('ob-payment-back').addEventListener('click', () => _obSetStep(3));

document.getElementById('ob-payment-submit').addEventListener('click', doCreateAccountOrPay);
document.getElementById('ob-pay-cancel-btn').addEventListener('click', () => {
  _obShowPayWaiting(false);
  setError(signupError, t("Payment canceled. Click Pay when you're ready, or go back to review your plan."));
});
document.getElementById('ob-pay-retry-btn').addEventListener('click', () => _obVerifyPaymentAndProceed(obPendingPaymentId));

// Fired by main.js after the system browser returns from Stripe Checkout
// (zeebroopos://payment deep link). Never trust the link's status alone —
// _obVerifyPaymentAndProceed() re-checks the real status against the API.
window.electronAPI.onPaymentDeepLink?.(({ status, paymentId }) => {
  if (document.getElementById('ob-pay-waiting').style.display === 'none') return; // stray/late event
  if (status === 'success') {
    _obVerifyPaymentAndProceed(paymentId || obPendingPaymentId);
  } else {
    _obShowPayWaiting(false);
    setError(signupError, t('Payment was canceled. You can try again when ready.'));
  }
});

function _obShowPayWaiting(show) {
  document.getElementById('ob-pay-waiting').style.display = show ? '' : 'none';
  document.getElementById('ob-payment-submit').style.display = show ? 'none' : '';
  document.getElementById('ob-payment-back').style.display = show ? 'none' : '';
  document.getElementById('ob-pay-waiting-error').style.display = 'none';
}

async function doCreateAccountOrPay() {
  // Registration already succeeded earlier (the user canceled Checkout, or
  // checkout-session creation failed and they're retrying) — don't call
  // /auth/register again with the same email, just restart Stripe Checkout.
  if (obPendingPaymentId) {
    await _obStartPaymentCheckout();
    return;
  }

  const name = document.getElementById('su-name').value.trim();
  const businessName = document.getElementById('su-business').value.trim();
  const businessCategory = document.getElementById('su-category').value;
  const email = document.getElementById('su-email').value.trim();
  const password = document.getElementById('su-password').value;
  const submitBtn = document.getElementById('ob-payment-submit');
  const wasFree = !obPackage || obPackage.is_free || _obFormatPrice(obPackage).amount <= 0;

  setBusy(submitBtn, true, wasFree ? t('Creating account…') : t('Starting checkout…'));
  setError(signupError, '');
  try {
    const res = await API.register({
      name,
      business_name: businessName,
      business_category: businessCategory,
      email,
      password,
      package_id: obPackage ? obPackage.id : null,
    });
    if (res.status !== 201) {
      setBusy(submitBtn, false, wasFree ? t('Create Account') : t('Pay & Create Account'));
      // Jump back to the step that owns the bad field BEFORE showing the error —
      // _obSetStep() clears the error box, which used to hide "email taken".
      const step1Fields = ['name', 'business_name', 'business_category', 'email', 'password'];
      const errKey = res.body?.errors ? Object.keys(res.body.errors)[0] : null;
      if (step1Fields.includes(errKey)) _obSetStep(1);
      else if (errKey === 'package_id') _obSetStep(2);
      showApiError(signupError, res, 'signup');
      return;
    }

    await persistSession(res.body.access_token, res.body.user);

    const payment = res.body.payment;
    if (!payment?.required) {
      await window.electronAPI.authSuccess();
      return;
    }

    obPendingPaymentId = payment.id;
    await _obStartPaymentCheckout();
  } catch (err) {
    setBusy(submitBtn, false, wasFree ? t('Create Account') : t('Pay & Create Account'));
    setError(signupError, err.message);
  }
}

async function _obStartPaymentCheckout() {
  const submitBtn = document.getElementById('ob-payment-submit');
  setBusy(submitBtn, true, t('Starting checkout…'));

  const res = await API.startPaymentCheckout(obPendingPaymentId);
  setBusy(submitBtn, false, t('Pay & Create Account'));

  const checkoutUrl = res.body?.data?.checkout_url;
  if (res.status !== 200 || !checkoutUrl) {
    if (res.status === 0) showApiError(signupError, res, 'signup');
    else setError(signupError, t('Could not start the payment. Please try again.'));
    return;
  }

  window.electronAPI.openExternal(checkoutUrl);
  _obShowPayWaiting(true);
}

async function _obVerifyPaymentAndProceed(paymentId) {
  if (!paymentId) return;
  const res = await API.paymentStatus(paymentId);

  if (res.status === 200 && res.body?.data?.payment_status === 'succeeded') {
    await window.electronAPI.authSuccess();
    return;
  }

  const err = document.getElementById('ob-pay-waiting-error');
  err.textContent = t('Payment not confirmed yet — finish it in the browser window, then check again.');
  err.style.display = '';
}
