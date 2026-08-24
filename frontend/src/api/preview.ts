import { apiFetch } from './client'
import { API_URL } from './config'
import { toApiError } from './problem'

const URL_ATTRIBUTES = ['src', 'href', 'poster']

/**
 * The preview endpoint sits behind the JWT firewall, so an <iframe src> would
 * get a 401 - the document has to be fetched with the token and injected
 * through srcdoc instead.
 */
export async function loadChapterPreview(projectId: string, chapterId: string): Promise<string> {
  const response = await apiFetch(`/api/projects/${projectId}/preview/${chapterId}`)

  if (!response.ok) {
    throw await toApiError(response)
  }

  return absolutizeAssetUrls(await response.text())
}

/**
 * Asset paths come back relative to the API, but the document is injected into
 * a frame on our own origin. A <base> would fix them and break every "#note"
 * anchor in the book, so the attributes are rewritten one by one instead.
 */
export function absolutizeAssetUrls(html: string): string {
  const document = new DOMParser().parseFromString(html, 'text/html')

  for (const element of document.querySelectorAll('[src], [href], [poster]')) {
    for (const name of URL_ATTRIBUTES) {
      const value = element.getAttribute(name)

      if (null === value || !value.startsWith('/api/')) {
        continue
      }

      element.setAttribute(name, `${API_URL}${value}`)
    }
  }

  return `<!doctype html>${document.documentElement.outerHTML}`
}
