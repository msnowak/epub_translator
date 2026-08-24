import { useMutation, useQueryClient } from '@tanstack/react-query'
import { useCallback, useEffect, useRef, useState } from 'react'
import { ApiError } from '../../api/problem'
import { updateSegment } from '../../api/segments'
import type { Segment } from '../../api/types'
import { detokenize, tokenSignature } from './detokenize'

/** Podglad wyprzedza zapis: zmiane widac, zanim zdazy sie zapisac. */
export const PREVIEW_DELAY_MS = 400
export const SAVE_DELAY_MS = 800

export type SaveState = 'clean' | 'dirty' | 'saving' | 'saved' | 'blocked' | 'error'

interface Options {
  segment: Segment
  chapterId: string
  onPreview: (segmentId: string, html: string) => void
}

export function useSegmentEditor({ segment, chapterId, onPreview }: Options) {
  const queryClient = useQueryClient()
  const [value, setValue] = useState(segment.translatedText ?? '')
  const [state, setState] = useState<SaveState>('clean')
  const [message, setMessage] = useState<string | null>(null)

  const dirty = useRef(false)
  const latest = useRef(value)
  const previewTimer = useRef<ReturnType<typeof setTimeout> | null>(null)
  const saveTimer = useRef<ReturnType<typeof setTimeout> | null>(null)

  const mutation = useMutation({
    mutationFn: (text: string) => updateSegment(segment.id, text),
    onSuccess: (saved) => {
      dirty.current = false
      setState('saved')
      setMessage(null)
      queryClient.setQueryData<Segment[]>(['segments', chapterId], (current) =>
        current?.map((item) => (item.id === saved.id ? saved : item)),
      )
    },
    onError: (error: unknown) => {
      setState('error')
      // Front nie pisze wlasnych komunikatow - backend mowi po polsku w detail.
      setMessage(error instanceof ApiError ? error.detail : 'Nie udało się zapisać zmiany.')
    },
  })

  const change = useCallback(
    (next: string) => {
      setValue(next)
      latest.current = next
      dirty.current = true
      setState('dirty')

      if (null !== previewTimer.current) {
        clearTimeout(previewTimer.current)
      }

      previewTimer.current = setTimeout(() => {
        onPreview(segment.id, detokenize(next, segment.previewPlaceholders))
      }, PREVIEW_DELAY_MS)

      if (null !== saveTimer.current) {
        clearTimeout(saveTimer.current)
      }

      if (tokenSignature(next) !== tokenSignature(segment.sourceText)) {
        // Backend odrzuci to z 422; nie ma po co go pytac, dopoki uzytkownik
        // jest w polowie poprawiania znacznika.
        setState('blocked')
        setMessage('Niezapisane — tłumaczenie musi mieć te same znaczniki co oryginał.')

        return
      }

      setMessage(null)
      saveTimer.current = setTimeout(() => {
        // Zerujemy referencje w momencie odpalenia zapisu: cleanup przy
        // odmontowaniu ma dogonic tylko zmiane wciaz czekajaca w debouncie,
        // a nie powtarzac zapis, ktory juz ruszyl (i mogl sie nie udac).
        saveTimer.current = null
        setState('saving')
        mutation.mutate(next)
      }, SAVE_DELAY_MS)
    },
    [mutation, onPreview, segment.id, segment.previewPlaceholders, segment.sourceText],
  )

  useEffect(() => {
    if (dirty.current) {
      // Wiersz z niezapisana zmiana nie oddaje jej odpowiedzi z serwera.
      return
    }

    const incoming = segment.translatedText ?? ''

    setValue(incoming)
    latest.current = incoming
  }, [segment.translatedText])

  useEffect(
    () => () => {
      if (null !== previewTimer.current) {
        clearTimeout(previewTimer.current)
      }

      if (null === saveTimer.current) {
        return
      }

      clearTimeout(saveTimer.current)

      // Wyjscie z rozdzialu nie moze zjesc zmiany czekajacej w debouncie.
      if (dirty.current) {
        void updateSegment(segment.id, latest.current, { keepalive: true })
      }
    },
    [segment.id],
  )

  return { value, state, message, change }
}
