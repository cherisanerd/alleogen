/**
 * PHP/MySQL backend client for the AEO File Generator.
 *
 * Keeps the existing `base44.*` surface so pages don't need rewrites.
 * All calls route to the self-hosted PHP API under /tools/alleogen/api/.
 *
 * Auth model:
 *   - Session cookie for subscribers (set by POST /api/auth/login).
 *   - Bearer token for one-timers (stored in localStorage after landing
 *     on /g/:token). Sent as Authorization: Bearer <token>.
 */

const API_BASE = import.meta.env.VITE_API_BASE || '/tools/alleogen/api';
const TOKEN_KEY = 'alleogen_access_token';

export const tokenStore = {
  get: () => {
    try { return localStorage.getItem(TOKEN_KEY); } catch { return null; }
  },
  set: (token) => {
    try { localStorage.setItem(TOKEN_KEY, token); } catch {}
  },
  clear: () => {
    try { localStorage.removeItem(TOKEN_KEY); } catch {}
  },
};

async function apiCall(endpoint, { method = 'GET', body = null, query = null } = {}) {
  const url = new URL(`${API_BASE}${endpoint}`, window.location.origin);
  if (query && typeof query === 'object') {
    for (const [k, v] of Object.entries(query)) {
      if (v !== undefined && v !== null && v !== '') url.searchParams.set(k, String(v));
    }
  }

  const headers = { Accept: 'application/json' };
  const token = tokenStore.get();
  if (token) headers['Authorization'] = `Bearer ${token}`;
  if (body !== null) headers['Content-Type'] = 'application/json';

  const response = await fetch(url.toString().replace(window.location.origin, ''), {
    method,
    headers,
    credentials: 'include',
    body: body !== null ? JSON.stringify(body) : null,
  });

  let data = null;
  const ct = response.headers.get('content-type') || '';
  if (ct.includes('application/json')) {
    try { data = await response.json(); } catch { data = null; }
  }

  if (!response.ok) {
    const err = new Error(data?.error || `Request failed: ${response.status}`);
    err.status = response.status;
    err.data = data;
    throw err;
  }
  return data ?? {};
}

// ---------------------------------------------------------------
// Auth
// ---------------------------------------------------------------

const auth = {
  /**
   * Returns the authenticated user row for subscribers, or a
   * generation row for token-based one-timer access. Mirrors the
   * shape the Base44 SDK exposed.
   */
  me: async () => {
    const res = await apiCall('/auth/me');
    if (!res?.authenticated) {
      const err = new Error('Not authenticated');
      err.status = 401;
      throw err;
    }
    // Subscribers get the user row; return it directly so legacy pages
    // that do `user.id`, `user.email`, `user.is_super_admin` keep working.
    if (res.type === 'session' && res.user) {
      return { ...res.user, is_super_admin: !!res.user.is_admin };
    }
    // One-timers get a generation envelope. Expose as a thin user-shaped
    // object so token-gated pages can still read id/email.
    if (res.type === 'token' && res.generation) {
      return {
        id: `token:${res.generation.id}`,
        email: '',
        plan_type: res.generation.package_tier,
        _tokenGeneration: res.generation,
      };
    }
    throw new Error('Unexpected /auth/me payload');
  },

  login: async (email, password) => {
    const res = await apiCall('/auth/login', { method: 'POST', body: { email, password } });
    return res.user;
  },

  logout: async (redirectUrl) => {
    try { await apiCall('/auth/logout', { method: 'POST' }); } catch {}
    tokenStore.clear();
    if (redirectUrl) window.location.href = redirectUrl;
  },

  changePassword: (currentPassword, newPassword) =>
    apiCall('/auth/change-password', {
      method: 'POST',
      body: { current_password: currentPassword, new_password: newPassword },
    }),

  redirectToLogin: (returnUrl) => {
    // If we're already on the login page, don't kick off another
    // redirect — otherwise repeated calls during render/effect cycles
    // build up stacked `?return=<prev-url>` params and tip the browser
    // into an exponentially-growing URL loop.
    if (/\/login(\/|\?|$)/.test(window.location.pathname)) return;
    const ret = encodeURIComponent(returnUrl || window.location.href);
    window.location.href = `${import.meta.env.BASE_URL || '/tools/alleogen/'}login?return=${ret}`;
  },
};

// ---------------------------------------------------------------
// Entity helpers — same method signatures as the Base44 SDK
// (create, get, update, delete, filter, list, bulkCreate) so
// existing pages don't need code changes.
// ---------------------------------------------------------------

function entityClient(basePath) {
  return {
    create: (payload) =>
      apiCall(`${basePath}/create`, { method: 'POST', body: payload })
        .then((r) => r?.generation || r?.analysis || r?.user || r),
    get: async (id) => {
      const r = await apiCall(`${basePath}/get`, { query: { id } });
      return r?.generation || r?.analysis || r?.user || r;
    },
    update: async (id, payload) => {
      await apiCall(`${basePath}/update`, { method: 'PUT', query: { id }, body: payload });
      return true;
    },
    delete: async (id) => {
      await apiCall(`${basePath}/delete`, { method: 'DELETE', query: { id } });
      return true;
    },
    filter: async (filters, sort, limit) => {
      const q = { ...(filters || {}), sort: sort || '', limit: limit || '' };
      const r = await apiCall(`${basePath}/list`, { query: q });
      return r?.generations || r?.analyses || r?.users || r?.coupons || r?.items || [];
    },
    list: async (sort, limit) => {
      const r = await apiCall(`${basePath}/list`, { query: { sort: sort || '', limit: limit || '' } });
      return r?.generations || r?.analyses || r?.users || r?.coupons || r?.items || [];
    },
    bulkCreate: (items) => apiCall(`${basePath}/bulk-create`, { method: 'POST', body: items }),
  };
}

const entities = {
  Analysis:   entityClient('/analyses'),
  Generation: entityClient('/generations'),
  Coupon:     entityClient('/admin/coupons'),
  User:       entityClient('/admin/users'),
  BetaFeedback: entityClient('/feedback'),
  ErrorLog:   entityClient('/admin/error-logs'),
};

// ---------------------------------------------------------------
// functions.invoke() — mirror of the Base44 SDK surface. Explicit
// mapping keeps routing readable and the backend endpoints stable.
// Returns the legacy { data } envelope for drop-in compatibility.
// ---------------------------------------------------------------

const FUNCTION_ROUTES = {
  analyzeWebsite:         { path: '/analyses/create',          method: 'POST' },
  generateFiles:          { path: '/generations/generate',     method: 'POST' },
  createCheckout:         { path: '/payments/create-checkout', method: 'POST' },
  applyCoupon:            { path: '/payments/apply-coupon',    method: 'POST' },
  requestCancel:          { path: '/ghl/request-cancel',       method: 'POST' },
  // Subscription upgrades route through GHL. The backend returns a
  // { url } payload the client redirects to.
  createUpgradeCheckout:  { path: '/ghl/subscribe-url',        method: 'POST' },
  processUpgrade:         { path: '/ghl/confirm-upgrade',      method: 'POST' },
  // Legacy helpers retained for compatibility — the Stripe webhook now
  // does the post-purchase work, so processPayment is a no-op shim.
  processPayment:         { path: '/payments/confirm',         method: 'POST' },
  addCredits:             { path: '/admin/add-credits',        method: 'POST' },
};

const functions = {
  invoke: async (name, body = {}) => {
    const route = FUNCTION_ROUTES[name];
    if (!route) {
      throw new Error(`Unknown function: ${name}`);
    }
    const data = await apiCall(route.path, { method: route.method, body });
    return { data };
  },
};

// ---------------------------------------------------------------
// asServiceRole — admin equivalents. The session middleware on the
// server already distinguishes admins, so these share entity clients.
// ---------------------------------------------------------------

const asServiceRole = {
  entities: {
    Generation: entityClient('/admin/generations'),
    ErrorLog:   entityClient('/admin/error-logs'),
  },
};

export const base44 = {
  auth,
  entities,
  functions,
  asServiceRole,
};
