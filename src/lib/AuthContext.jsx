import React, { createContext, useState, useContext, useEffect, useCallback } from 'react';
import { base44 } from '@/api/base44Client';

/**
 * Auth state for the self-hosted app.
 *
 * Two valid states:
 *   - Authenticated subscriber (session cookie → user row)
 *   - Anonymous / token-gated one-timer (no session, token in localStorage)
 *
 * Anything else surfaces via `authError` so pages can react.
 */

const AuthContext = createContext(null);

export const AuthProvider = ({ children }) => {
  const [user, setUser] = useState(null);
  const [isAuthenticated, setIsAuthenticated] = useState(false);
  const [isLoadingAuth, setIsLoadingAuth] = useState(true);
  const [authError, setAuthError] = useState(null);

  // No separate "public settings" phase — settings the UI needs
  // (prices, etc.) ship via dedicated endpoints. Leave this flag so
  // consumers that reference `isLoadingPublicSettings` still work.
  const isLoadingPublicSettings = false;

  const checkUserAuth = useCallback(async () => {
    setIsLoadingAuth(true);
    setAuthError(null);
    try {
      const currentUser = await base44.auth.me();
      setUser(currentUser);
      setIsAuthenticated(true);
    } catch (error) {
      setUser(null);
      setIsAuthenticated(false);
      // Treat 401/403 as "auth required" — pages can redirect to /login.
      // Everything else surfaces as an unknown error.
      if (error?.status === 401 || error?.status === 403) {
        setAuthError({ type: 'auth_required', message: 'Not authenticated' });
      } else if (error?.message) {
        setAuthError({ type: 'unknown', message: error.message });
      }
    } finally {
      setIsLoadingAuth(false);
    }
  }, []);

  useEffect(() => { checkUserAuth(); }, [checkUserAuth]);

  const logout = (shouldRedirect = true) => {
    setUser(null);
    setIsAuthenticated(false);
    if (shouldRedirect) {
      base44.auth.logout(`${import.meta.env.BASE_URL || '/tools/alleogen/'}login`);
    } else {
      base44.auth.logout();
    }
  };

  const navigateToLogin = () => base44.auth.redirectToLogin(window.location.href);

  return (
    <AuthContext.Provider value={{
      user,
      isAuthenticated,
      isLoadingAuth,
      isLoadingPublicSettings,
      authError,
      appPublicSettings: null,
      logout,
      navigateToLogin,
      checkAppState: checkUserAuth,
    }}>
      {children}
    </AuthContext.Provider>
  );
};

export const useAuth = () => {
  const context = useContext(AuthContext);
  if (!context) throw new Error('useAuth must be used within an AuthProvider');
  return context;
};
