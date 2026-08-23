import '@testing-library/jest-dom/vitest'
import { cleanup } from '@testing-library/react'
import { afterAll, afterEach, beforeAll } from 'vitest'
import { server } from './server'

// onUnhandledRequest: 'error' - zadanie, ktorego nikt nie zaslonil, ma wywalic
// test, zamiast po cichu polecec do prawdziwego backendu.
beforeAll(() => server.listen({ onUnhandledRequest: 'error' }))

afterEach(() => {
  cleanup()
  server.resetHandlers()
})

afterAll(() => server.close())
