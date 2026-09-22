'use strict';

const tabLogin = document.getElementById('tab-login');
const tabSignup = document.getElementById('tab-signup');
const loginForm = document.getElementById('login-form');
const signupForm = document.getElementById('signup-form');
const lineLogin = document.getElementById('line-login');
const lineSignup = document.getElementById('line-signup');
const loginError = document.getElementById('login-error');
const signupError = document.getElementById('signup-error');

function showTab(which) {
  const isLogin = which === 'login';
  tabLogin.classList.toggle('active', isLogin);
  tabSignup.classList.toggle('active', !isLogin);
  loginForm.classList.toggle('active', isLogin);
  signupForm.classList.toggle('active', !isLogin);
  lineLogin.style.display = isLogin ? '' : 'none';
  lineSignup.style.display = isLogin ? 'none' : '';
  loginError.classList.remove('show');
  signupError.classList.remove('show');
}

tabLogin.addEventListener('click', () => showTab('login'));
tabSignup.addEventListener('click', () => showTab('signup'));
document.getElementById('go-signup').addEventListener('click', () => showTab('signup'));
document.getElementById('go-login').addEventListener('click', () => showTab('login'));

function setError(box, message) {
  box.textContent = message;
  box.classList.toggle('show', !!message);
}

function firstValidationError(body) {
  if (!body) return 'Something went wrong. Please try again.';
  if (body.errors) {
    const firstKey = Object.keys(body.errors)[0];
    if (firstKey) return body.errors[firstKey][0];
  }
  return body.message || 'Something went wrong. Please try again.';
}

function setBusy(button, busy, label) {
  button.disabled = busy;
  button.innerHTML = busy ? `<span class="spinner"></span>${label}` : label;
}

// After a successful login/register: persist the token, resolve which
// business to use, then hand off to the main POS window.
async function finishAuth(accessToken, user) {
  await window.electronAPI.setConfig({ token: accessToken, user });

  const bizRes = await API.businesses();
  const business = bizRes.status === 200 ? (bizRes.body.data || [])[0] : null;
  if (!business) {
    throw new Error('No business is linked to this account yet.');
  }
  await window.electronAPI.setConfig({ business_id: business.id, business_name: business.name, branch_id: null });
  await window.electronAPI.authSuccess();
}

// ── Login ───────────────────────────────────────────────────────────────
loginForm.addEventListener('submit', async (e) => {
  e.preventDefault();
  setError(loginError, '');
  const email = document.getElementById('login-email').value.trim();
  const password = document.getElementById('login-password').value;
  const submitBtn = document.getElementById('login-submit');

  setBusy(submitBtn, true, 'Log In');
  try {
    const res = await API.login(email, password);
    if (res.status !== 200) {
      setError(loginError, firstValidationError(res.body));
      return;
    }
    await finishAuth(res.body.access_token, res.body.user);
  } catch (err) {
    setError(loginError, err.message);
  } finally {
    setBusy(submitBtn, false, 'Log In');
  }
});

// ── Sign up ─────────────────────────────────────────────────────────────
async function loadBusinessCategories() {
  const select = document.getElementById('su-category');
  try {
    const res = await API.businessCategories();
    const options = res.status === 200 ? (res.body.data || []) : [];
    select.innerHTML = '<option value="">Select a category…</option>' +
      options.map((o) => `<option value="${o.value}">${o.label}</option>`).join('');
  } catch (_) {
    select.innerHTML = '<option value="">Unable to load categories</option>';
  }
}
loadBusinessCategories();

signupForm.addEventListener('submit', async (e) => {
  e.preventDefault();
  setError(signupError, '');

  const name = document.getElementById('su-name').value.trim();
  const businessName = document.getElementById('su-business').value.trim();
  const businessCategory = document.getElementById('su-category').value;
  const email = document.getElementById('su-email').value.trim();
  const password = document.getElementById('su-password').value;
  const password2 = document.getElementById('su-password2').value;
  const submitBtn = document.getElementById('signup-submit');

  if (password !== password2) {
    setError(signupError, 'Passwords do not match.');
    return;
  }

  setBusy(submitBtn, true, 'Create Account');
  try {
    const res = await API.register({
      name,
      business_name: businessName,
      business_category: businessCategory,
      email,
      password,
    });
    if (res.status !== 201) {
      setError(signupError, firstValidationError(res.body));
      return;
    }
    await finishAuth(res.body.access_token, res.body.user);
  } catch (err) {
    setError(signupError, err.message);
  } finally {
    setBusy(submitBtn, false, 'Create Account');
  }
});
