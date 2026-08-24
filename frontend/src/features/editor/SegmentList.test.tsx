import { QueryClient, QueryClientProvider } from '@tanstack/react-query'
import { render, screen } from '@testing-library/react'
import userEvent from '@testing-library/user-event'
import type { ReactNode } from 'react'
import { beforeEach, describe, expect, it, vi } from 'vitest'
import type { Segment } from '../../api/types'
import { stubLayoutForVirtualization } from '../../test/virtualization'
import { SegmentList } from './SegmentList'

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

function renderList(items: Segment[], onActivate = vi.fn()) {
  const client = new QueryClient()

  function Wrapper({ children }: { children: ReactNode }) {
    return <QueryClientProvider client={client}>{children}</QueryClientProvider>
  }

  return render(
    <SegmentList
      segments={items}
      chapterId="ch-1"
      activeId={null}
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
})
