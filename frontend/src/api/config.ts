/**
 * Address of the API as the browser sees it. Compose injects it; the fallback
 * keeps `npm run test` working with no environment at all.
 */
export const API_URL = import.meta.env.VITE_API_URL ?? 'http://localhost:8000'
