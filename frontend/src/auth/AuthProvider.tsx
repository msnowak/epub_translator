import { createContext, useCallback, useEffect, useMemo, useState, type ReactNode } from 'react'
import { login, logout, register } from '../api/auth'
import { onSessionLost, refreshAccessToken, setAccessToken } from '../api/client'

export type SessionState = 'unknown' | 'anonymous' | 'authenticated'

export interface AuthContextValue {
  state: SessionState
  signIn: (email: string, password: string) => Promise<void>
  signUp: (email: string, password: string) => Promise<void>
  signOut: () => Promise<void>
}

export const AuthContext = createContext<AuthContextValue | null>(null)

export function AuthProvider({ children }: { children: ReactNode }) {
  const [state, setState] = useState<SessionState>('unknown')

  useEffect(() => {
    let active = true

    // Access token zyje tylko w pamieci, wiec po odswiezeniu strony jedynym
    // sladem sesji jest ciasteczko. Ta proba to jedyny sposob, zeby sie
    // dowiedziec, czy uzytkownik jest jeszcze zalogowany.
    refreshAccessToken().then(
      () => {
        if (active) {
          setState('authenticated')
        }
      },
      () => {
        if (active) {
          setState('anonymous')
        }
      },
    )

    return () => {
      active = false
    }
  }, [])

  useEffect(() => onSessionLost(() => setState('anonymous')), [])

  const signIn = useCallback(async (email: string, password: string) => {
    await login(email, password)
    setState('authenticated')
  }, [])

  const signUp = useCallback(async (email: string, password: string) => {
    await register(email, password)
    // Rejestracja nie wydaje tokenu, wiec zaraz po niej logujemy - inaczej
    // uzytkownik zakladalby konto i ladowal z powrotem na formularzu.
    await login(email, password)
    setState('authenticated')
  }, [])

  const signOut = useCallback(async () => {
    setState('anonymous')
    setAccessToken(null)
    await logout()
  }, [])

  const value = useMemo(() => ({ state, signIn, signUp, signOut }), [state, signIn, signUp, signOut])

  return <AuthContext.Provider value={value}>{children}</AuthContext.Provider>
}
