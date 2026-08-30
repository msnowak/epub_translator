import { Link, Outlet, useNavigate } from 'react-router-dom'
import { useAuth } from '../auth/useAuth'
import { useT } from '../i18n/useT'
import { Button } from '@/components/ui/button'
import { LocaleSwitcher } from '@/components/LocaleSwitcher'

export function AppLayout({ wide = false }: { wide?: boolean }) {
  const { t } = useT()
  const { signOut } = useAuth()
  const navigate = useNavigate()

  async function onSignOut() {
    await signOut()
    navigate('/login', { replace: true })
  }

  return (
    <div className="flex h-screen flex-col">
      <header className="border-b">
        <div className={`flex items-center justify-between p-4 ${wide ? 'px-6' : 'mx-auto max-w-4xl'}`}>
          <Link className="font-semibold" to="/">
            {t('app.name')}
          </Link>
          <div className="flex items-center gap-2">
            <LocaleSwitcher />
            <Button variant="secondary" onClick={onSignOut}>
              {t('app.signOut')}
            </Button>
          </div>
        </div>
      </header>
      <main className="min-h-0 flex-1">
        <div className={wide ? 'h-full px-6 py-4' : 'mx-auto max-w-4xl p-8'}>
          <Outlet />
        </div>
      </main>
    </div>
  )
}
