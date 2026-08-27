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
  // Opcjonalny, bo tego haka uzywa tez SegmentList.test.tsx bez rodzica, ktory
  // sledziyby "brudne" wiersze - patrz activate()/retranslationPreview w
  // EditorPage, ktore go faktycznie podaja.
  onDirtyChange?: (segmentId: string, dirty: boolean) => void
}

export function useSegmentEditor({ segment, chapterId, onPreview, onDirtyChange }: Options) {
  const queryClient = useQueryClient()
  const [value, setValue] = useState(segment.translatedText ?? '')
  const [state, setState] = useState<SaveState>('clean')
  const [message, setMessage] = useState<string | null>(null)

  const dirty = useRef(false)
  const latest = useRef(value)
  const mounted = useRef(true)
  const previewTimer = useRef<ReturnType<typeof setTimeout> | null>(null)
  const saveTimer = useRef<ReturnType<typeof setTimeout> | null>(null)

  useEffect(() => {
    mounted.current = true

    return () => {
      mounted.current = false
    }
  }, [])

  // Jedno miejsce, ktore aktualizuje ref lokalny (czytelny synchronicznie w
  // change()) i informuje rodzica - to on trzyma, ktore akapity maja
  // niezapisana zmiane, zeby retranslacja w tle wiedziala, ktorych wezlow
  // podgladu nie wolno jej dotykac.
  const markDirty = useCallback(
    (next: boolean) => {
      dirty.current = next
      onDirtyChange?.(segment.id, next)
    },
    [onDirtyChange, segment.id],
  )

  const applySaved = useCallback(
    (saved: Segment) => {
      markDirty(false)
      setState('saved')
      setMessage(null)
      queryClient.setQueryData<Segment[]>(['segments', chapterId], (current) =>
        current?.map((item) => (item.id === saved.id ? saved : item)),
      )
      // Zapisana recznie poprawka wraca jako "edited" - jesli akapit byl
      // wczesniej "failed", projektowa lista nieudanych akapitow musi to
      // zauwazyc, a nie trzymac stary blad.
      void queryClient.invalidateQueries({ queryKey: ['segments', 'failed'] })
      // Podglad tego rozdzialu jest teraz nieaktualny wzgledem cache'u
      // zapytania - ale zywy dokument w ramce juz ma ta zmiane (patrz
      // patchPreview/onPreview), wiec odswiezenie ma zaczekac do nastepnego
      // wejscia w rozdzial, a nie wyrywac dokument spod kursora teraz.
      // refetchType: 'none' oznacza zapytanie jako nieaktualne bez
      // natychmiastowego przeladowania aktywnej ramki.
      void queryClient.invalidateQueries({ queryKey: ['preview'], refetchType: 'none' })
    },
    [chapterId, markDirty, queryClient],
  )

  const reportError = useCallback(
    (error: unknown) => {
      const detail = error instanceof ApiError ? error.detail : 'Nie udało się zapisać zmiany.'

      if (mounted.current) {
        setState('error')
        setMessage(detail)

        return
      }

      // Wiersza juz nie ma (odmontowany, patrz cleanup nizej) - lokalny stan
      // bledu nie ma jak sie pokazac. Kanal w cache'u zapytan przezyje
      // odmontowanie i EditorPage go czyta, wiec to jedyne miejsce, gdzie ten
      // blad moze jeszcze dotrzec do uzytkownika. Niezapisana zmiana i tak
      // przepadla z punktu widzenia tego wiersza - dalsze sledzenie jej jako
      // "brudnej" tylko zablokowaloby na stale podglad tego akapitu.
      markDirty(false)
      queryClient.setQueryData<string | null>(['segments', 'save-error', chapterId], detail)
    },
    [chapterId, markDirty, queryClient],
  )

  const mutation = useMutation({
    mutationFn: (vars: { text: string; keepalive?: boolean }) =>
      updateSegment(segment.id, vars.text, { keepalive: vars.keepalive }),
    onSuccess: applySaved,
    onError: reportError,
  })

  // useMutation zwraca nowy obiekt-wynik przy kazdym renderze, wiec sam
  // "mutation" nie nadaje sie na zaleznosc efektu ponizej: gdyby nia byl,
  // kazdy render (nie tylko odmontowanie) uruchamialby cleanup i kasowal
  // wciaz czekajacy debounce. Ten ref trzyma najswiezsza metode mutate poza
  // tablica zaleznosci - synchronizowany efektem (nie bezposrednio w renderze,
  // refy nie sa do tego), .mutate() zawsze czyta biezacy stan obserwatora
  // niezaleznie od tego, ktora referencja go woła.
  const mutateRef = useRef(mutation.mutate)

  useEffect(() => {
    mutateRef.current = mutation.mutate
  }, [mutation.mutate])

  const change = useCallback(
    (next: string) => {
      setValue(next)
      latest.current = next
      markDirty(true)
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
        mutation.mutate({ text: next })
      }, SAVE_DELAY_MS)
    },
    [markDirty, mutation, onPreview, segment.id, segment.previewPlaceholders, segment.sourceText],
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
      if (!dirty.current) {
        return
      }

      // Ta sama mutacja co przy zwyklym zapisie (nie goly fetch): queryClient
      // przezyje odmontowanie tego wiersza, wiec onSuccess/onError powyzej i
      // tak zapisza wynik tam, gdzie kod spoza tego komponentu moze go jeszcze
      // zobaczyc - cache przy sukcesie, kanal bledu przy porazce. keepalive:
      // true trzyma zadanie przy zyciu, gdyby to bylo odejscie z calej karty,
      // nie tylko odmontowanie wiersza przez wirtualizacje.
      mutateRef.current({ text: latest.current, keepalive: true })
    },
    [segment.id],
  )

  return { value, state, message, change }
}
