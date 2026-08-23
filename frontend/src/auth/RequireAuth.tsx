import { Navigate, Outlet } from 'react-router-dom'
import { useAuth } from './useAuth'

export function RequireAuth() {
  const { state } = useAuth()

  if ('unknown' === state) {
    // Trzeci stan istnieje po to, zeby nie wyrzucic na logowanie osoby,
    // ktorej sesja wlasnie sie odswieza.
    return <p className="p-8 text-neutral-500">Wczytywanie…</p>
  }

  return 'authenticated' === state ? <Outlet /> : <Navigate to="/logowanie" replace />
}
