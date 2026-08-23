import { QueryClient, QueryClientProvider } from '@tanstack/react-query'
import { StrictMode } from 'react'
import { createRoot } from 'react-dom/client'
import { BrowserRouter } from 'react-router-dom'
import App from './App.tsx'
import { AuthProvider } from './auth/AuthProvider.tsx'
import './index.css'

const queryClient = new QueryClient()

const container = document.getElementById('root')

if (null === container) {
  throw new Error('The page has no #root element to mount into.')
}

// Kolejnosc taka sama jak w renderWithProviders - inaczej testy przechodzilyby
// przy zepsutej aplikacji.
createRoot(container).render(
  <StrictMode>
    <BrowserRouter>
      <QueryClientProvider client={queryClient}>
        <AuthProvider>
          <App />
        </AuthProvider>
      </QueryClientProvider>
    </BrowserRouter>
  </StrictMode>,
)
