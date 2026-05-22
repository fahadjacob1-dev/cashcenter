// =====================================================
// auth_check.js — يُضاف في كل صفحة HTML
// =====================================================

// التحقق من الجلسة عند تحميل الصفحة
(async function checkAuth() {
  try {
    const res  = await fetch('api_auth.php?action=check');
    const data = await res.json();

    if (!data.data?.logged_in) {
      location.href = 'login.html';
      return;
    }

    // حفظ بيانات المستخدم
    window.currentUser = data.data.user;
    sessionStorage.setItem('user', JSON.stringify(window.currentUser));

    // عرض اسم المستخدم في الـ header إذا موجود
    const nameEl = document.getElementById('userDisplayName');
    if (nameEl) nameEl.textContent = window.currentUser.full_name;

    // إخفاء/إظهار عناصر حسب الدور
    applyRoleVisibility(window.currentUser.role);

    // جلب قوائم الموظفين والعملاء
    await loadDropdowns();

  } catch (e) {
    console.error('Auth check failed', e);
    location.href = 'login.html';
  }
})();

// ── تطبيق الصلاحيات على العناصر ──────────────────────
function applyRoleVisibility(role) {
  // عناصر تظهر فقط للمدير والمشرف
  document.querySelectorAll('[data-role="admin,supervisor"]').forEach(el => {
    if (!['admin','supervisor'].includes(role)) el.style.display = 'none';
  });
  // عناصر للمدير فقط
  document.querySelectorAll('[data-role="admin"]').forEach(el => {
    if (role !== 'admin') el.style.display = 'none';
  });
  // عناصر للمدقق
  document.querySelectorAll('[data-role="auditor"]').forEach(el => {
    if (!['admin','supervisor','auditor'].includes(role)) el.style.display = 'none';
  });
}

// ── تسجيل الخروج ──────────────────────────────────────
async function logout() {
  await fetch('api_auth.php?action=logout', { method: 'POST' });
  sessionStorage.clear();
  location.href = 'login.html';
}

// ── جلب قوائم الموظفين والعملاء ──────────────────────
async function loadDropdowns() {
  try {
    // الموظفون
    const empRes  = await fetch('api_auth.php?action=get_employees');
    const empData = await empRes.json();
    if (empData.success) {
      window.employees = empData.data;
      fillSelect('empId',    empData.data, 'id', 'full_name');
      fillSelect('empName',  empData.data, 'id', 'full_name');
    }

    // العملاء
    const clientRes  = await fetch('api_clients.php?action=get_all');
    const clientData = await clientRes.json();
    if (clientData.success) {
      window.clients = clientData.data;
      fillSelect('clientId',   clientData.data, 'id', 'name');
      fillSelect('clientName', clientData.data, 'id', 'name');
    }
  } catch (e) {
    console.warn('Could not load dropdowns', e);
  }
}

// ── مساعد: ملء قائمة منسدلة ──────────────────────────
function fillSelect(selectId, items, valueKey, labelKey) {
  const sel = document.getElementById(selectId);
  if (!sel) return;
  const current = sel.value;
  sel.innerHTML = '<option value="">-- اختر --</option>';
  items.forEach(item => {
    const opt = document.createElement('option');
    opt.value       = item[valueKey];
    opt.textContent = item[labelKey];
    sel.appendChild(opt);
  });
  if (current) sel.value = current;
}

// ── مساعد: عرض رسالة نجاح ────────────────────────────
function showSuccess(msg, elId = 'successMsg') {
  const el = document.getElementById(elId);
  if (!el) return;
  el.textContent    = '✅ ' + msg;
  el.style.display  = 'block';
  el.style.background = '#22c55e';
  setTimeout(() => el.style.display = 'none', 3500);
}

// ── مساعد: عرض رسالة خطأ ────────────────────────────
function showError(msg, elId = 'successMsg') {
  const el = document.getElementById(elId);
  if (!el) { alert('❌ ' + msg); return; }
  el.textContent    = '❌ ' + msg;
  el.style.display  = 'block';
  el.style.background = '#ef4444';
  setTimeout(() => el.style.display = 'none', 4000);
}

// ── تنسيق الأرقام بالعربي ────────────────────────────
function fmt(n) {
  return Number(n).toLocaleString('ar-IQ');
}

// ── حساب مجموع الفئات ────────────────────────────────
function calcDenomTotal(prefix = '') {
  const DENOMS = [50000, 25000, 10000, 5000, 1000, 500, 250];
  let total = 0;
  DENOMS.forEach(d => {
    const inp = document.querySelector(`.count-input[data-denom="${d}"]`);
    if (inp) total += d * (parseInt(inp.value) || 0);
  });
  return total;
}
