import { useMutation, useQueryClient } from '@tanstack/react-query'
import { useCallback, useEffect, useRef, useState } from 'react'
import { ApiError } from '../../api/problem'
import { getSegment, retranslateSegment } from '../../api/segments'
import type { Segment } from '../../api/types'
import { useT } from '../../i18n/useT'
import { detokenize } from './detokenize'

const POLL_MS = 2000

/**
 * Retranslating one paragraph is the only thing in the editor that changes
 * server-side on its own, so it is the only thing the editor polls - one
 * segment at a time, never the whole chapter.
 */
export function useRetranslation(chapterId: string, onPreview: (segmentId: string, html: string) => void) {
  const { t } = useT()
  const queryClient = useQueryClient()
  const [awaiting, setAwaiting] = useState<Set<string>>(new Set())
  const [error, setError] = useState<string | null>(null)
  const timer = useRef<ReturnType<typeof setInterval> | null>(null)
  // Odczytywany przez tick interwalu, zeby ten zawsze widzial biezacy zbior
  // oczekujacych id bez zmuszania efektu ponizej do restartu interwalu przy
  // kazdym dodaniu lub usunieciu - patrz komentarz przy tablicy zaleznosci.
  const awaitingRef = useRef<Set<string>>(awaiting)

  useEffect(() => {
    awaitingRef.current = awaiting
  }, [awaiting])

  // Ten sam powod co przy awaitingRef ponizej efektu z interwalem: "t" zmienia
  // tozsamosc przy kazdej zmianie jezyka, a interwal ma sie zbroic od nowa
  // tylko wtedy, gdy hasAwaiting faktycznie sie zmieni (patrz komentarz przy
  // tablicy zaleznosci tamtego efektu) - nie przy kazdym przelaczeniu jezyka
  // w trakcie trwajacego pollingu. Ref trzyma najnowsze "t" poza ta tablica,
  // catch() ponizej czyta je przez tRef.current zamiast domykac stara wartosc.
  const tRef = useRef(t)

  useEffect(() => {
    tRef.current = t
  }, [t])

  const write = useCallback(
    (segment: Segment) => {
      queryClient.setQueryData<Segment[]>(['segments', chapterId], (current) =>
        current?.map((item) => (item.id === segment.id ? segment : item)),
      )
    },
    [chapterId, queryClient],
  )

  // Osobny callback (nie surowe queryClient w tablicy zaleznosci ponizej) -
  // ta sama stabilnosc referencji co "write", zeby efekt nie przezbrajal
  // interwalu, gdy queryClient sie nie zmienil.
  const invalidateFailedList = useCallback(() => {
    void queryClient.invalidateQueries({ queryKey: ['segments', 'failed'] })
    // Zapytanie podgladu ma zostac tylko oznaczone jako nieaktualne (nie
    // odswiezone teraz) - swiezy dokument tego rozdzialu i tak jest juz
    // widoczny, bo wlasciwy "zywy" zapis do ramki robi onPreview() ponizej.
    // To uniewaznienie jest tu na wypadek, gdyby ktos wrocil do rozdzialu
    // pozniej z pustym cache'em (np. po odswiezeniu karty), a nie glowna
    // sciezka aktualizacji podgladu.
    void queryClient.invalidateQueries({ queryKey: ['preview'], refetchType: 'none' })
  }, [queryClient])

  const mutation = useMutation({
    mutationFn: (segmentId: string) => retranslateSegment(segmentId),
    onSuccess: (segment) => {
      setError(null)
      write(segment)
      setAwaiting((current) => new Set(current).add(segment.id))
    },
    onError: (failure: unknown) => {
      setError(failure instanceof ApiError ? failure.detail : t('editor.error.retranslate'))
    },
  })

  const hasAwaiting = awaiting.size > 0

  useEffect(() => {
    if (!hasAwaiting) {
      return
    }

    timer.current = setInterval(() => {
      for (const id of awaitingRef.current) {
        void getSegment(id)
          .then((segment) => {
            write(segment)

            if ('processing' === segment.status) {
              return
            }

            if (null !== segment.translatedText) {
              // Ponowienie skonczyle sie tlumaczeniem - wpisujemy je do
              // otwartej wlasnie ramki tak samo, jak reczna edycja robi to
              // przy kazdym klawiszu (patrz onPreview w useSegmentEditor).
              // Nieudane ponowienie (segment.translatedText === null) nie ma
              // czym podmienic wezla - lepiej zostawic stara tresc w
              // podgladzie, niz wyczyscic akapit do pustego akapitu.
              onPreview(segment.id, detokenize(segment.translatedText, segment.previewPlaceholders))
            }

            // Paragraf mogl przestac byc "failed" (albo, po nieudanym
            // ponowieniu, dopiero nim zostac) - widok projektu trzyma wlasna,
            // niepowiazana liste nieudanych akapitow i musi ja odswiezyc.
            invalidateFailedList()

            setAwaiting((current) => {
              const next = new Set(current)
              next.delete(segment.id)

              return next
            })
          })
          .catch((failure: unknown) => {
            // Cichy nieudany odczyt polowalby bez konca i bez sladu na
            // ekranie - lepiej przerwac odpytywanie tego akapitu i pokazac,
            // co poszlo nie tak, niz probowac w nieskonczonosc.
            setError(failure instanceof ApiError ? failure.detail : tRef.current('editor.error.status'))

            setAwaiting((current) => {
              const next = new Set(current)
              next.delete(id)

              return next
            })
          })
      }
    }, POLL_MS)

    return () => {
      if (null !== timer.current) {
        clearInterval(timer.current)
      }
    }
    // Zaleznosc po hasAwaiting (bool), nie po samym awaiting (Set) - inny
    // Set przy kazdym dodaniu/usunieciu przezbrajalby interwal od nowa i
    // przesuwal czas kolejnego ticku dla akapitow juz oczekujacych. Z tego
    // samego powodu nie ma tu "t" - w catch() ponizej czytane jest przez
    // tRef.current, zeby przelaczenie jezyka w trakcie pollingu tez nie
    // zbroilo interwalu od nowa.
  }, [hasAwaiting, write, invalidateFailedList, onPreview])

  const retranslate = useCallback(
    (segmentId: string) => {
      mutation.mutate(segmentId)
    },
    [mutation],
  )

  return { retranslate, awaiting, error }
}
