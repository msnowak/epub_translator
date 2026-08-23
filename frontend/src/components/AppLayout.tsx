import { Link, Outlet, useNavigate } from 'react-router-dom'
import { useAuth } from '../auth/useAuth'
import { Button } from '@/components/ui/button'

export function AppLayout() {
  const { signOut } = useAuth()
  const navigate = useNavigate()

  async function onSignOut() {
    await signOut()
    navigate('/logowanie', { replace: true })
  }

  return (
    <div className="min-h-screen">
      <header className="border-b">
        <div className="mx-auto flex max-w-4xl items-center justify-between p-4">
          <Link className="font-semibold" to="/">
            EPUB Translator
          </Link>
          <Button variant="secondary" onClick={onSignOut}>
            Wyloguj się
          </Button>
        </div>
      </header>
      <main className="mx-auto max-w-4xl p-8">
        <Outlet />
      </main>
    </div>
  )
}
