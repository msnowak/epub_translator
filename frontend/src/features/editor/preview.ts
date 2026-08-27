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
  if (null === target || !isElementLike(target)) {
    return null
  }

  return target.closest('[data-segment-id]')?.getAttribute('data-segment-id') ?? null
}

/**
 * The clicked node comes from the iframe's document, a different realm than
 * this module - Element in the frame's window is a different constructor
 * object than Element here, so "target instanceof Element" is false always,
 * not just sometimes. We check a capability (does it have closest?) instead
 * of relying on constructor identity across realms. Do not "simplify" this
 * back to instanceof; that is exactly the bug this guards against.
 */
function isElementLike(target: EventTarget): target is Element {
  const candidate = target as { closest?: unknown }

  return 'function' === typeof candidate.closest
}

function findBlock(document: Document, segmentId: string): Element | null {
  // Identyfikator to UUID, wiec w cudzyslowie selektora nie ma czego uciekac.
  return document.querySelector(`[data-segment-id="${segmentId}"]`)
}
