/* Login page: email-verified registration + forgot password. Loaded after app.js on login.php */
document.addEventListener('DOMContentLoaded', () => {
  const $ = id => document.getElementById(id);
  const box = $('authMessage');
  if (!box || !$('registerForm')) return;

  // Remove app.js's old one-step register handler by replacing the node
  const oldReg = $('registerForm');
  const reg = oldReg.cloneNode(true);
  oldReg.replaceWith(reg);

  const forms = ['loginForm', 'registerForm', 'codeForm', 'forgotForm', 'resetForm'];
  const msg = (t, bad) => { box.textContent = t || ''; box.className = 'auth-message' + (t ? (bad ? ' error' : ' success') : ''); };
  const show = id => {
    forms.forEach(f => $(f).classList.toggle('active', f === id));
    document.querySelectorAll('.auth-tab').forEach(t => t.classList.toggle('active', t.dataset.tab === (id === 'loginForm' || id === 'forgotForm' || id === 'resetForm' ? 'login' : 'register')));
    if (id === 'forgotForm' || id === 'resetForm') document.querySelectorAll('.auth-tab').forEach(t => t.classList.remove('active'));
  };
  const api = async (action, payload) => {
    try {
      const r = await fetch('api_account.php?action=' + action, { method: 'POST', headers: { 'Content-Type': 'application/json' }, body: JSON.stringify(payload || {}) });
      return await r.json();
    } catch (e) { return { success: false, message: 'Network error. Please check your connection.' }; }
  };
  const busy = async (form, label, fn) => {
    const b = form.querySelector('button[type="submit"]'), o = b.innerHTML;
    b.disabled = true; b.innerHTML = '<i class="fas fa-spinner fa-spin"></i> ' + label;
    try { return await fn(); } finally { b.disabled = false; b.innerHTML = o; }
  };

  let pendingEmail = '', resetEmail = '', cd = 0, timer = null;
  const resend = $('resendBtn');
  const cooldown = (btn, label) => {
    cd = 45; clearInterval(timer);
    btn.disabled = true;
    timer = setInterval(() => {
      cd--;
      if (cd <= 0) { clearInterval(timer); btn.disabled = false; btn.textContent = label; }
      else btn.textContent = label + ' (' + cd + 's)';
    }, 1000);
    btn.textContent = label + ' (' + cd + 's)';
  };

  document.querySelectorAll('.auth-tab').forEach(t => t.addEventListener('click', () => { forms.forEach(f => $(f).classList.remove('active')); $(t.dataset.tab + 'Form').classList.add('active'); }));
  $('forgotLink').addEventListener('click', () => { msg(''); show('forgotForm'); });
  document.querySelectorAll('[data-back]').forEach(b => b.addEventListener('click', () => { msg(''); show('loginForm'); }));
  $('codeBack').addEventListener('click', () => { msg(''); show('registerForm'); });

  // Step 1: register -> send code
  reg.addEventListener('submit', async e => {
    e.preventDefault();
    const res = await busy(reg, 'Sending code...', () => api('register_request', {
      full_name: reg.full_name.value.trim(), username: reg.username.value.trim(),
      email: reg.email.value.trim(), password: reg.password.value
    }));
    if (!res.success) return msg(res.message, true);
    pendingEmail = res.data.email;
    $('codeEmail').textContent = pendingEmail;
    $('codeForm').code.value = '';
    msg(res.message, false);
    show('codeForm');
    cooldown(resend, 'Resend code');
    $('codeForm').code.focus();
  });

  // Step 2: verify code -> pending approval
  $('codeForm').addEventListener('submit', async e => {
    e.preventDefault();
    const f = $('codeForm');
    const res = await busy(f, 'Verifying...', () => api('register_verify', { email: pendingEmail, code: f.code.value.trim() }));
    if (!res.success) return msg(res.message, true);
    reg.reset();
    msg(res.message + ' You can log in once approved.', false);
    show('loginForm');
  });

  resend.addEventListener('click', async () => {
    resend.disabled = true;
    const res = await api('register_resend', { email: pendingEmail });
    msg(res.message, !res.success);
    cooldown(resend, 'Resend code');
  });

  // Forgot password
  $('forgotForm').addEventListener('submit', async e => {
    e.preventDefault();
    const f = $('forgotForm');
    const res = await busy(f, 'Sending...', () => api('forgot_request', { email: f.email.value.trim() }));
    if (!res.success) return msg(res.message, true);
    resetEmail = res.data.email;
    msg(res.message, false);
    $('resetForm').reset();
    show('resetForm');
    $('resetForm').code.focus();
  });

  $('resetForm').addEventListener('submit', async e => {
    e.preventDefault();
    const f = $('resetForm');
    if (f.password.value !== f.confirm.value) return msg('Passwords do not match.', true);
    const res = await busy(f, 'Saving...', () => api('forgot_reset', { email: resetEmail, code: f.code.value.trim(), password: f.password.value }));
    if (!res.success) return msg(res.message, true);
    f.reset();
    msg(res.message, false);
    show('loginForm');
  });
});
