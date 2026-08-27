import { vi } from 'vitest'

/**
 * In jsdom every element is zero by zero and there is no ResizeObserver, so a
 * virtualizer renders nothing at all and the test fails for a reason that has
 * nothing to do with the component. Call this before rendering a virtual list.
 */
export function stubLayoutForVirtualization(): void {
  class ResizeObserverStub {
    observe(): void {}
    unobserve(): void {}
    disconnect(): void {}
  }

  vi.stubGlobal('ResizeObserver', ResizeObserverStub)

  vi.spyOn(HTMLElement.prototype, 'getBoundingClientRect').mockReturnValue({
    x: 0,
    y: 0,
    top: 0,
    left: 0,
    right: 600,
    bottom: 800,
    width: 600,
    height: 800,
    toJSON: () => ({}),
  })

  Object.defineProperty(HTMLElement.prototype, 'clientHeight', { configurable: true, value: 800 })
  Object.defineProperty(HTMLElement.prototype, 'clientWidth', { configurable: true, value: 600 })

  // @tanstack/react-virtual sizes the scroll container from offsetWidth/
  // offsetHeight, not getBoundingClientRect - jsdom hardcodes both to zero,
  // so without this the virtualizer computes an empty range and renders
  // nothing at all.
  Object.defineProperty(HTMLElement.prototype, 'offsetHeight', { configurable: true, value: 800 })
  Object.defineProperty(HTMLElement.prototype, 'offsetWidth', { configurable: true, value: 600 })
}
