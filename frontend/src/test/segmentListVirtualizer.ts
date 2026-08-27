import { vi } from 'vitest'
import type { Virtualizer } from '@tanstack/react-virtual'

/**
 * @tanstack/react-virtual keeps its Virtualizer instance behind a useState
 * inside useVirtualizer, so nothing outside SegmentList can reach
 * scrollToIndex to spy on it, and jsdom does not implement real scrolling
 * anyway. Pair this with a per-file mock of '@tanstack/react-virtual' that
 * wraps the real useVirtualizer and calls captureScrollToIndex() with the
 * instance it returns:
 *
 *   vi.mock('@tanstack/react-virtual', async (importOriginal) => {
 *     const actual = await importOriginal<typeof import('@tanstack/react-virtual')>()
 *     return {
 *       ...actual,
 *       useVirtualizer: (options: Parameters<typeof actual.useVirtualizer>[0]) => {
 *         const instance = actual.useVirtualizer(options)
 *         captureScrollToIndex(instance)
 *         return instance
 *       },
 *     }
 *   })
 *
 * The hook hands back the very same instance on every re-render, so the spy
 * is installed once per mount; call resetScrollToIndexSpy() in beforeEach so
 * the next test's mount gets spied on again instead of finding one already
 * installed from a previous render.
 */
function spyOnScrollToIndex(instance: Virtualizer<Element, Element>) {
  return vi.spyOn(instance, 'scrollToIndex')
}

let spy: ReturnType<typeof spyOnScrollToIndex> | null = null

export function captureScrollToIndex(instance: Virtualizer<Element, Element>): void {
  if (null === spy) {
    spy = spyOnScrollToIndex(instance)
  }
}

export function scrollToIndexSpy(): ReturnType<typeof spyOnScrollToIndex> {
  if (null === spy) {
    throw new Error('scrollToIndex nie zostal jeszcze przechwycony - zamontuj komponent przed odczytem spy')
  }

  return spy
}

export function resetScrollToIndexSpy(): void {
  spy = null
}
