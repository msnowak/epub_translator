import { useVirtualizer } from '@tanstack/react-virtual'
import { useRef } from 'react'
import type { Segment } from '../../api/types'
import { SegmentRow } from './SegmentRow'

interface Props {
  segments: Segment[]
  chapterId: string
  activeId: string | null
  onPreview: (segmentId: string, html: string) => void
  onActivate: (segmentId: string) => void
  onRetranslate: (segmentId: string) => void
}

export function SegmentList({ segments, chapterId, activeId, onPreview, onActivate, onRetranslate }: Props) {
  const scrollRef = useRef<HTMLDivElement>(null)

  // Rozdzial potrafi miec setki akapitow, a wysokosci nie znamy z gory - stad
  // pomiar dynamiczny zamiast stalej wysokosci wiersza.
  const virtualizer = useVirtualizer({
    count: segments.length,
    getScrollElement: () => scrollRef.current,
    estimateSize: () => 180,
    overscan: 6,
  })

  return (
    <div ref={scrollRef} className="h-full overflow-y-auto">
      <div className="relative w-full" style={{ height: `${virtualizer.getTotalSize()}px` }}>
        {virtualizer.getVirtualItems().map((item) => {
          const segment = segments[item.index]

          return (
            <div
              key={segment.id}
              data-index={item.index}
              ref={virtualizer.measureElement}
              className="absolute left-0 top-0 w-full"
              style={{ transform: `translateY(${item.start}px)` }}
            >
              <SegmentRow
                segment={segment}
                chapterId={chapterId}
                active={segment.id === activeId}
                onPreview={onPreview}
                onActivate={onActivate}
                onRetranslate={onRetranslate}
              />
            </div>
          )
        })}
      </div>
    </div>
  )
}
