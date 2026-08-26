import { QueryClient, QueryClientProvider } from '@tanstack/react-query'
import { render, screen } from '@testing-library/react'
import userEvent from '@testing-library/user-event'
import type { ReactNode } from 'react'
import { beforeEach, describe, expect, it, vi } from 'vitest'
import type { Segment } from '../../api/types'
import { captureScrollToIndex, resetScrollToIndexSpy, scrollToIndexSpy } from '../../test/segmentListVirtualizer'
import { stubLayoutForVirtualization } from '../../test/virtualization'
import { SegmentList } from './SegmentList'

vi.mock('@tanstack/react-virtual', async (importOriginal) => {
  const actual = await importOriginal<typeof import('@tanstack/react-virtual')>()

  return {
    ...actual,
    useVirtualizer: (options: Parameters<typeof actual.useVirtualizer>[0]) => {
      const instance = actual.useVirtualizer(options)

      captureScrollToIndex(instance)

      return instance
    },
  }
})

function segments(count: number): Segment[] {
  return Array.from({ length: count }, (_, index) => ({
    id: `seg-${index}`,
    position: index,
    nodeIndex: index,
    subIndex: 0,
    sourceText: `Paragraph ${index}.`,
    translatedText: `Akapit ${index}.`,
    status: 'translated' as const,
    errorMessage: null,
    previewPlaceholders: {},
    chapter: { id: 'ch-1', spineOrder: 0, title: 'Rozdział pierwszy' },
  }))
}

function renderList(
  items: Segment[],
  onActivate = vi.fn(),
  scrollTo: { id: string; token: number } | null = null,
) {
  const client = new QueryClient()

  function Wrapper({ children }: { children: ReactNode }) {
    return <QueryClientProvider client={client}>{children}</QueryClientProvider>
  }

  return render(
    <SegmentList
      segments={items}
      chapterId="ch-1"
      activeId={null}
      scrollTo={scrollTo}
      onPreview={vi.fn()}
      onActivate={onActivate}
      onRetranslate={vi.fn()}
    />,
    { wrapper: Wrapper },
  )
}

describe('SegmentList', () => {
  beforeEach(() => {
    stubLayoutForVirtualization()
    resetScrollToIndexSpy()
  })

  it('shows the source next to its translation', () => {
    renderList(segments(3))

    expect(screen.getByText('Paragraph 0.')).toBeInTheDocument()
    expect(screen.getByDisplayValue('Akapit 0.')).toBeInTheDocument()
  })

  it('renders a window, not two hundred paragraphs', () => {
    renderList(segments(200))

    // Wirtualizacja: w DOM-ie siedzi okno widoczne plus overscan, nie calosc.
    expect(screen.getAllByRole('textbox').length).toBeLessThan(200)
    expect(screen.getAllByRole('textbox').length).toBeGreaterThan(0)
  })

  it('tells the page which paragraph the user is in', async () => {
    const onActivate = vi.fn()

    renderList(segments(3), onActivate)
    await userEvent.click(screen.getByDisplayValue('Akapit 1.'))

    expect(onActivate).toHaveBeenCalledWith('seg-1')
  })

  it('scrolls to an off-screen paragraph when asked to from outside the list', () => {
    // seg-150 nie siedzi w oknie wyrenderowanych wierszy przy 200 akapitach -
    // to wlasnie przypadek, ktorego szukanie w dokumencie nie potrafi znalezc.
    renderList(segments(200), vi.fn(), { id: 'seg-150', token: 1 })

    expect(scrollToIndexSpy()).toHaveBeenCalledWith(150, { align: 'center' })
  })

  it('does not scroll when the requested paragraph is not in the (filtered) list', () => {
    renderList(segments(3), vi.fn(), { id: 'seg-not-visible', token: 1 })

    expect(scrollToIndexSpy()).not.toHaveBeenCalled()
  })

  it('scrolls again when the same paragraph is requested a second time', () => {
    // Klikniecie tego samego akapitu drugi raz z rzedu (np. po tym jak
    // uzytkownik sam odsunal liste gdzie indziej) niesie ten sam id - bez
    // rosnacego tokena setState identycznym stringiem bylby przez Reacta
    // pominiety i efekt przewijajacy w ogole by sie nie uruchomil ponownie.
    const { rerender } = renderList(segments(200), vi.fn(), { id: 'seg-150', token: 1 })

    expect(scrollToIndexSpy()).toHaveBeenNthCalledWith(1, 150, { align: 'center' })

    rerender(
      <SegmentList
        segments={segments(200)}
        chapterId="ch-1"
        activeId={null}
        scrollTo={{ id: 'seg-150', token: 2 }}
        onPreview={vi.fn()}
        onActivate={vi.fn()}
        onRetranslate={vi.fn()}
      />,
    )

    expect(scrollToIndexSpy()).toHaveBeenNthCalledWith(2, 150, { align: 'center' })
    expect(scrollToIndexSpy()).toHaveBeenCalledTimes(2)
  })
})
