import { QueryClient, QueryClientProvider } from '@tanstack/react-query'
import { type RenderResult, render } from '@testing-library/react'
import { StrictMode } from 'react'
import type { ReactElement, ReactNode } from 'react'
import { MemoryRouter } from 'react-router-dom'
import { AuthProvider } from '../auth/AuthProvider'
import { LocaleProvider } from '../i18n/LocaleProvider'
import type { Locale } from '../i18n/locales'

export function renderWithProviders(
  ui: ReactElement,
  options: { route?: string; locale?: Locale } = {},
): RenderResult & { queryClient: QueryClient } {
  // retry: false - inaczej kazdy test bledu czekalby na trzy nieudane proby.
  const queryClient = new QueryClient({
    defaultOptions: { queries: { retry: false }, mutations: { retry: false } },
  })

  function Wrapper({ children }: { children: ReactNode }) {
    // StrictMode tutaj - zeby kolejnosc providerow dokladnie odzwierciedlala
    // produkcje (patrz main.tsx). Bez tego StrictMode double-invokes efektow
    // (mount->cleanup->mount w dev) nigdy by sie tu nie zdarzylo i testy
    // moglyby przejsc na haku, ktory dziala tylko dopoki efekt startowy
    // nie wykona sie dwa razy - patrz useSegmentEditor's "mounted" ref.
    return (
      <StrictMode>
        <LocaleProvider initial={options.locale ?? 'pl'}>
          <MemoryRouter initialEntries={[options.route ?? '/']}>
            <QueryClientProvider client={queryClient}>
              <AuthProvider>{children}</AuthProvider>
            </QueryClientProvider>
          </MemoryRouter>
        </LocaleProvider>
      </StrictMode>
    )
  }

  // queryClient wystawiony wprost - testy, ktore chca zaobserwowac kanal
  // w cache'u zapytan pisany spoza komponentu (np. blad zapisu po
  // odmontowaniu wiersza, patrz useSegmentEditor), inaczej nie majace jak go
  // odczytac. Object.assign (nie rozklad {...}) zeby TypeScript zachowal
  // dokladny typ RenderResult zamiast wywodzic go od nowa z kopii wlasnosci.
  return Object.assign(render(ui, { wrapper: Wrapper }), { queryClient })
}
