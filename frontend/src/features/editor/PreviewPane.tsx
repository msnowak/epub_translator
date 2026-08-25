import type { RefObject } from 'react'
import { readSegmentId } from './preview'

interface Props {
  html: string | null
  frameRef: RefObject<HTMLIFrameElement | null>
  onSegmentClick: (segmentId: string) => void
}

export function PreviewPane({ html, frameRef, onSegmentClick }: Props) {
  if (null === html) {
    return <p className="p-4 text-neutral-500">Wczytywanie podglądu…</p>
  }

  return (
    <iframe
      ref={frameRef}
      title="Podgląd rozdziału"
      // Bez allow-scripts nic z ksiazki sie nie wykona; allow-same-origin jest
      // tu wylacznie po to, zeby rodzic siegnal do contentDocument i podmienil
      // jeden wezel zamiast przeladowywac rozdzial.
      sandbox="allow-same-origin"
      srcDoc={html}
      className="h-full w-full border-0"
      onLoad={() => {
        const document = frameRef.current?.contentDocument ?? null

        document?.addEventListener('click', (event) => {
          const id = readSegmentId(event.target)

          if (null !== id) {
            onSegmentClick(id)
          }
        })
      }}
    />
  )
}
