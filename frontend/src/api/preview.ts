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
 *
 * An EPUB 2 cover embeds its image as SVG's <image xlink:href="…">. The
 * qualified name of that attribute is "xlink:href", so a "[href]" selector
 * and getAttribute('href') both miss it - the same problem the backend
 * already solved in App\Preview\ElementSanitizer::rewriteUrls(). We walk
 * every element's attributes and match by localName instead, then write the
 * value back through the attribute node itself so its qualified name and
 * namespace stay untouched.
 */
export function absolutizeAssetUrls(html: string): string {
  const document = new DOMParser().parseFromString(html, 'text/html')

  for (const element of document.querySelectorAll('*')) {
    for (const attribute of Array.from(element.attributes)) {
      const name = attribute.localName.toLowerCase()

      if (!URL_ATTRIBUTES.includes(name) || !attribute.value.startsWith('/api/')) {
        continue
      }

      attribute.value = `${API_URL}${attribute.value}`
    }
  }

  return `<!doctype html>${document.documentElement.outerHTML}`
}
