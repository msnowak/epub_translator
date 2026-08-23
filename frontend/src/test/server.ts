import { setupServer } from 'msw/node'

/** One MSW server for the whole suite; each test adds its own handlers. */
export const server = setupServer()
