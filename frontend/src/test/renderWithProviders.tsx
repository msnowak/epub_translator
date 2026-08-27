import { QueryClient, QueryClientProvider } from '@tanstack/react-query'
import { type RenderResult, render } from '@testing-library/react'
import type { ReactElement, ReactNode } from 'react'
import { MemoryRouter } from 'react-router-dom'
import { AuthProvider } from '../auth/AuthProvider'

export function renderWithProviders(
  ui: ReactElement,
  options: { route?: string } = {},
): RenderResult & { queryClient: QueryClient } {
  // retry: false - inaczej kazdy test bledu czekalby na trzy nieudane proby.
  const queryClient = new QueryClient({
    defaultOptions: { queries: { retry: false }, mutations: { retry: false } },
  })

  function Wrapper({ children }: { children: ReactNode }) {
    return (
      <MemoryRouter initialEntries={[options.route ?? '/']}>
        <QueryClientProvider client={queryClient}>
          <AuthProvider>{children}</AuthProvider>
        </QueryClientProvider>
      </MemoryRouter>
    )
  }

  // queryClient wystawiony wprost - testy, ktore chca zaobserwowac kanal
  // w cache'u zapytan pisany spoza komponentu (np. blad zapisu po
  // odmontowaniu wiersza, patrz useSegmentEditor), inaczej nie majace jak go
  // odczytac. Object.assign (nie rozklad {...}) zeby TypeScript zachowal
  // dokladny typ RenderResult zamiast wywodzic go od nowa z kopii wlasnosci.
  return Object.assign(render(ui, { wrapper: Wrapper }), { queryClient })
}
