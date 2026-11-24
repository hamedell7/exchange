import { createContext, useCallback, useContext, useEffect, useMemo, useState } from 'react';
import { apiRequest, clearSession, getAccessToken, getTokenExpiry, saveSession } from '../api/client.js';

const AuthContext = createContext(null);

export function AuthProvider({ children }) {
  const [user, setUser] = useState(null);
  const [expiresAt, setExpiresAt] = useState(getTokenExpiry());
  const [loading, setLoading] = useState(true);

  useEffect(() => {
    let active = true;
    const token = getAccessToken();

    if (!token) {
      setLoading(false);
      return () => { active = false; };
    }

    apiRequest('/me', { token })
      .then((response) => {
        if (active) setUser(response?.data || null);
      })
      .catch(() => {
        if (active) {
          clearSession();
          setUser(null);
          setExpiresAt(null);
        }
      })
      .finally(() => {
        if (active) setLoading(false);
      });

    return () => { active = false; };
  }, []);

  const signIn = useCallback(async (email, password) => {
    const response = await apiRequest('/login', {
      method: 'POST',
      auth: false,
      body: { email: email.trim(), password, device_name: 'Exchange Web' },
    });

    const session = response?.data;
    if (!session?.access_token || !session?.user) {
      throw new Error('پاسخ ورود از سرور کامل نیست.');
    }

    saveSession(session.access_token, session.expires_at);
    setExpiresAt(session.expires_at || null);
    setUser(session.user);
    return session.user;
  }, []);

  const signOut = useCallback(async () => {
    const token = getAccessToken();

    try {
      if (token) await apiRequest('/logout', { method: 'POST', token });
    } catch {
      // Clear the local session even when the API is unavailable.
    } finally {
      clearSession();
      setExpiresAt(null);
      setUser(null);
    }
  }, []);

  const value = useMemo(
    () => ({ user, expiresAt, loading, signIn, signOut }),
    [user, expiresAt, loading, signIn, signOut],
  );

  return <AuthContext.Provider value={value}>{children}</AuthContext.Provider>;
}

export function useAuth() {
  const context = useContext(AuthContext);
  if (!context) throw new Error('useAuth must be used inside AuthProvider.');
  return context;
}
