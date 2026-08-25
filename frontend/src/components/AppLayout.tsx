import { Link, Outlet, useNavigate } from 'react-router-dom'
import { useAuth } from '../auth/useAuth'
import { Button } from '@/components/ui/button'

export function AppLayout({ wide = false }: { wide?: boolean }) {
  const { signOut } = useAuth()
  const navigate = useNavigate()

  async function onSignOut() {
    await signOut()
    navigate('/logowanie', { replace: true })
  }

  return (
    <div className="flex h-screen flex-col">
      <header className="border-b">
        <div className={`flex items-center justify-between p-4 ${wide ? 'px-6' : 'mx-auto max-w-4xl'}`}>
          <Link className="font-semibold" to="/">
            EPUB Translator
          </Link>
          <Button variant="secondary" onClick={onSignOut}>
            Wyloguj się
          </Button>
        </div>
      </header>
      <main className={`min-h-0 flex-1 ${wide ? 'px-6 py-4' : 'mx-auto max-w-4xl p-8'}`}>
        <Outlet />
      </main>
    </div>
  )
}
