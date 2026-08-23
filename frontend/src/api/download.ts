import { apiFetch } from './client'
import { toApiError } from './problem'

const FALLBACK_NAME = 'ksiazka.epub'

/**
 * Reads the file name out of Content-Disposition. The backend writes both
 * forms - filename* in UTF-8 and an ASCII fallback - because book titles are
 * not always Latin.
 */
export function filenameFromDisposition(header: string | null): string | null {
  if (null === header) {
    return null
  }

  const utf8 = /filename\*=UTF-8''([^;]+)/i.exec(header)

  if (null !== utf8) {
    return decodeURIComponent(utf8[1])
  }

  const ascii = /filename="([^"]+)"/i.exec(header)

  return null === ascii ? null : ascii[1]
}

export async function downloadProject(id: string): Promise<void> {
  // Endpoint siedzi za firewallem JWT, wiec zwykly <a href> dostalby 401 -
  // plik trzeba wziac fetchem z tokenem i podac przegladarce jako blob:.
  const response = await apiFetch(`/api/projects/${id}/download`)

  if (!response.ok) {
    throw await toApiError(response)
  }

  const blob = await response.blob()
  const url = URL.createObjectURL(blob)

  try {
    const anchor = document.createElement('a')
    anchor.href = url
    anchor.download = filenameFromDisposition(response.headers.get('Content-Disposition')) ?? FALLBACK_NAME
    document.body.append(anchor)
    anchor.click()
    anchor.remove()
  } finally {
    // Klikniecie startuje pobranie synchronicznie, wiec zwolnienie adresu zaraz
    // po nim jest bezpieczne - a bez niego plik zostaje w pamieci karty.
    URL.revokeObjectURL(url)
  }
}
