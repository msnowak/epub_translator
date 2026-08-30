import { Button } from '@/components/ui/button'
import type { Segment } from '../../api/types'
import type { SimpleKey } from '../../i18n/messages'
import { useT } from '../../i18n/useT'
import { workerErrorMessage } from '../projects/workerError'
import { type SaveState, useSegmentEditor } from './useSegmentEditor'

const STATE_KEYS: Record<SaveState, SimpleKey | null> = {
  clean: null,
  dirty: 'editor.state.dirty',
  saving: 'editor.state.saving',
  saved: 'editor.state.saved',
  blocked: null,
  error: null,
}

interface Props {
  segment: Segment
  chapterId: string
  active: boolean
  onPreview: (segmentId: string, html: string) => void
  onActivate: (segmentId: string) => void
  onRetranslate: (segmentId: string) => void
  onDirtyChange: (segmentId: string, dirty: boolean) => void
}

export function SegmentRow({ segment, chapterId, active, onPreview, onActivate, onRetranslate, onDirtyChange }: Props) {
  const { t } = useT()
  const { value, state, message, change } = useSegmentEditor({ segment, chapterId, onPreview, onDirtyChange })
  const failed = 'failed' === segment.status
  const stateKey = STATE_KEYS[state]
  const workerMessage = workerErrorMessage(segment.errorCode, segment.errorParams, t)

  return (
    <div className={`grid grid-cols-2 gap-4 border-b p-4 ${active ? 'bg-neutral-50' : ''}`}>
      <p className="text-sm text-neutral-700">{segment.sourceText}</p>

      <div className="flex flex-col gap-1">
        <textarea
          className="min-h-24 w-full resize-y rounded-md border p-2 text-sm"
          value={value}
          aria-label={t('editor.row.label', { position: segment.position + 1 })}
          onFocus={() => onActivate(segment.id)}
          onChange={(event) => change(event.target.value)}
        />

        <div className="flex items-center justify-between gap-2 text-xs">
          <span className={'error' === state || 'blocked' === state ? 'text-red-600' : 'text-neutral-500'}>
            {message ?? (null === stateKey ? '' : t(stateKey))}
          </span>
          <Button variant="ghost" size="sm" onClick={() => onRetranslate(segment.id)}>
            {t('editor.row.retranslate')}
          </Button>
        </div>

        {failed && null !== workerMessage ? <p className="text-xs text-red-600">{workerMessage}</p> : null}
      </div>
    </div>
  )
}
