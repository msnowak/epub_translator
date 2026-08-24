import { Button } from '@/components/ui/button'
import type { Segment } from '../../api/types'
import { type SaveState, useSegmentEditor } from './useSegmentEditor'

const STATE_LABELS: Record<SaveState, string> = {
  clean: '',
  dirty: 'Niezapisane…',
  saving: 'Zapisywanie…',
  saved: 'Zapisano',
  blocked: '',
  error: '',
}

interface Props {
  segment: Segment
  chapterId: string
  active: boolean
  onPreview: (segmentId: string, html: string) => void
  onActivate: (segmentId: string) => void
  onRetranslate: (segmentId: string) => void
}

export function SegmentRow({ segment, chapterId, active, onPreview, onActivate, onRetranslate }: Props) {
  const { value, state, message, change } = useSegmentEditor({ segment, chapterId, onPreview })
  const failed = 'failed' === segment.status

  return (
    <div
      className={`grid grid-cols-2 gap-4 border-b p-4 ${active ? 'bg-neutral-50' : ''}`}
      data-segment-row={segment.id}
    >
      <p className="text-sm text-neutral-700">{segment.sourceText}</p>

      <div className="flex flex-col gap-1">
        <textarea
          className="min-h-24 w-full resize-y rounded-md border p-2 text-sm"
          value={value}
          aria-label={`Tłumaczenie akapitu ${segment.position + 1}`}
          onFocus={() => onActivate(segment.id)}
          onChange={(event) => change(event.target.value)}
        />

        <div className="flex items-center justify-between gap-2 text-xs">
          <span className={'error' === state || 'blocked' === state ? 'text-red-600' : 'text-neutral-500'}>
            {message ?? STATE_LABELS[state]}
          </span>
          <Button variant="ghost" size="sm" onClick={() => onRetranslate(segment.id)}>
            Przetłumacz ponownie
          </Button>
        </div>

        {failed && null !== segment.errorMessage ? (
          <p className="text-xs text-red-600">{segment.errorMessage}</p>
        ) : null}
      </div>
    </div>
  )
}
