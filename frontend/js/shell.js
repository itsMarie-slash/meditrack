import { api, setCsrfToken, setCurrentUser, getCurrentUser } from './api.js';
import { showToast } from './components/toast.js';

const NAV = [
  { key: 'dashboard', label: 'Dashboard', href: 'dashboard.html', roles: ['bhw', 'midwife', 'ipho'] },
  { key: 'senior-citizens', label: 'Senior Citizens', href: 'senior-citizens.html', roles: ['bhw', 'midwife'] },
  { key: 'medicines', label: 'Medicine Inventory', href: 'medicines.html', roles: ['bhw', 'midwife'] },
  { key: 'distribution-schedules', label: 'Distribution Schedules', href: 'distribution-schedules.html', roles: ['bhw', 'midwife'] },
  { key: 'distribution-log', label: 'Distribution Log', href: 'distribution-log.html', roles: ['bhw', 'midwife'] },
  { key: 'gis-map', label: 'GIS Map', href: 'gis-map.html', roles: ['bhw', 'midwife'] },
  { key: 'forecasting', label: 'Demand Forecasting', href: 'forecasting.html', roles: ['bhw', 'midwife', 'ipho'] },
  { key: 'reports', label: 'Reports', href: 'reports.html', roles: ['bhw', 'midwife', 'ipho'] },
  { key: 'users', label: 'User Management', href: 'users.html', roles: ['bhw'] },
  { key: 'audit-logs', label: 'Audit Logs', href: 'audit-logs.html', roles: ['bhw'] },
];

async function requireAuth() {
  let user = getCurrentUser();
  try {
    const data = await api.get('/auth/me');
    setCsrfToken(data.csrf_token);
    setCurrentUser(data.user);
    user = data.user;
  } catch {
    // api.js already redirects to the login page on 401.
    return null;
  }
  return user;
}

function shellHtml(user, activeKey, pageTitle) {
  const navHtml = NAV.filter((item) => item.roles.includes(user.role))
    .map(
      (item) => `<a href="${item.href}" class="${item.key === activeKey ? 'active' : ''}"><span class="label">${item.label}</span></a>`
    )
    .join('');

  return `
    <div class="app-shell">
      <aside class="sidebar">
        <div class="brand">MediTrack</div>
        <nav>${navHtml}</nav>
      </aside>
      <div class="main-area">
        <header class="topbar">
          <h1>${pageTitle}</h1>
          <div class="user-menu">
            <span>${user.full_name} · ${user.role.toUpperCase()}</span>
            <button id="logout-btn">Log out</button>
          </div>
        </header>
        <main class="content" id="page-content"></main>
      </div>
    </div>
  `;
}

export async function initShell(activeKey, pageTitle) {
  const user = await requireAuth();
  if (!user) return null;

  document.body.innerHTML = shellHtml(user, activeKey, pageTitle);
  document.getElementById('logout-btn').addEventListener('click', async () => {
    try {
      await api.post('/auth/logout');
    } catch {
      // best-effort — still send the user back to login
    }
    showToast('Signed out.', 'success');
    location.href = 'index.html';
  });

  return { user, content: document.getElementById('page-content') };
}
