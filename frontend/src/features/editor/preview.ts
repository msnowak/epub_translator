/**
 * Everything here takes a Document rather than the iframe, so the same code is
 * testable without contentDocument and works on the live frame unchanged.
 */
export function applyTranslation(document: Document, segmentId: string, html: string): boolean {
  const block = findBlock(document, segmentId)

  if (null === block) {
    return false
  }

  block.innerHTML = html

  return true
}

export function scrollSegmentIntoView(document: Document, segmentId: string): void {
  findBlock(document, segmentId)?.scrollIntoView({ block: 'center' })
}

export function readSegmentId(target: EventTarget | null): string | null {
  if (!(target instanceof Element)) {
    return null
  }

  return target.closest('[data-segment-id]')?.getAttribute('data-segment-id') ?? null
}

function findBlock(document: Document, segmentId: string): Element | null {
  // Identyfikator to UUID, wiec w cudzyslowie selektora nie ma czego uciekac.
  return document.querySelector(`[data-segment-id="${segmentId}"]`)
}
