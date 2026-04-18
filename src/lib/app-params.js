/**
 * Lightweight app parameter handling for the self-hosted build.
 *
 * Replaces the former Base44 SDK appId/token dance. The only parameter
 * the app reads from the URL now is `token` — used when Stripe
 * redirects a one-timer to /payment-success?token=... or when a token
 * link opens /g/:token. The token is cached in localStorage so page
 * refreshes keep working.
 *
 * Kept named `appParams` for drop-in compatibility with the old
 * imports throughout the codebase.
 */

const isNode = typeof window === 'undefined';
const TOKEN_KEY = 'alleogen_access_token';

const readStoredToken = () => {
  if (isNode) return null;
  try { return window.localStorage.getItem(TOKEN_KEY); } catch { return null; }
};

const persistToken = (token) => {
  if (isNode || !token) return;
  try { window.localStorage.setItem(TOKEN_KEY, token); } catch {}
};

const readUrlToken = () => {
  if (isNode) return null;
  const params = new URLSearchParams(window.location.search);
  const fromQuery = params.get('token') || params.get('access_token');
  if (fromQuery) {
    persistToken(fromQuery);
    // Strip the token from the URL so it doesn't end up in browser history.
    params.delete('token');
    params.delete('access_token');
    const newQs = params.toString();
    const newUrl = `${window.location.pathname}${newQs ? `?${newQs}` : ''}${window.location.hash}`;
    window.history.replaceState({}, document.title, newUrl);
    return fromQuery;
  }
  return null;
};

const resolveToken = () => readUrlToken() || readStoredToken();

export const appParams = {
  token: resolveToken(),
  fromUrl: isNode ? null : window.location.href,
};
