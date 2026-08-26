import { useVirtualizer } from '@tanstack/react-virtual'
import { useEffect, useRef } from 'react'
import type { Segment } from '../../api/types'
import { SegmentRow } from './SegmentRow'

interface Props {
  segments: Segment[]
  chapterId: string
  activeId: string | null
  // Zadanie przewiniecia listy do konkretnego akapitu, wystawiane przez
  // rodzica wylacznie dla aktywacji spoza listy (klikniecie w podgladzie,
  // link "?akapit=") - patrz komentarz przy activate() w EditorPage.
  scrollToId: string | null
  onPreview: (segmentId: string, html: string) => void
  onActivate: (segmentId: string) => void
  onRetranslate: (segmentId: string) => void
}

export function SegmentList({ segments, chapterId, activeId, scrollToId, onPreview, onActivate, onRetranslate }: Props) {
  const scrollRef = useRef<HTMLDivElement>(null)
  // Czytany w efekcie przewijania ponizej, zeby nie trzeba bylo wpisywac
  // "segments" do jego zaleznosci - inaczej kazde przeliczenie widocznej
  // listy (np. przelaczenie filtra "tylko nieudane") tworzyloby nowa tablice
  // i przewijalo od nowa, mimo ze scrollToId sie nie zmienil.
  const segmentsRef = useRef(segments)
  segmentsRef.current = segments

  // Rozdzial potrafi miec setki akapitow, a wysokosci nie znamy z gory - stad
  // pomiar dynamiczny zamiast stalej wysokosci wiersza.
  const virtualizer = useVirtualizer({
    count: segments.length,
    getScrollElement: () => scrollRef.current,
    estimateSize: () => 180,
    overscan: 6,
  })

  useEffect(() => {
    if (null === scrollToId) {
      return
    }

    const index = segmentsRef.current.findIndex((segment) => segment.id === scrollToId)

    // Filtr "tylko nieudane" potrafi wywalic wlasnie ten akapit z widoku -
    // nie ma wtedy do czego przewijac (patrz punkt 4 w opisie zadania).
    if (-1 === index) {
      return
    }

    // Lista jest wirtualizowana, wiec wiersz poza oknem widocznosci moze w
    // ogole nie istniec w DOM-ie - scrollToIndex to jedyny sposob, zeby go
    // tam sprowadzic; szukanie elementu w dokumencie by tu nie zadzialalo.
    virtualizer.scrollToIndex(index, { align: 'center' })
  }, [scrollToId, virtualizer])

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
