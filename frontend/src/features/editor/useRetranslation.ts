import { useMutation, useQueryClient } from '@tanstack/react-query'
import { useCallback, useEffect, useRef, useState } from 'react'
import { ApiError } from '../../api/problem'
import { getSegment, retranslateSegment } from '../../api/segments'
import type { Segment } from '../../api/types'

const POLL_MS = 2000

/**
 * Retranslating one paragraph is the only thing in the editor that changes
 * server-side on its own, so it is the only thing the editor polls - one
 * segment at a time, never the whole chapter.
 */
export function useRetranslation(chapterId: string) {
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

  const write = useCallback(
    (segment: Segment) => {
      queryClient.setQueryData<Segment[]>(['segments', chapterId], (current) =>
        current?.map((item) => (item.id === segment.id ? segment : item)),
      )
    },
    [chapterId, queryClient],
  )

  const mutation = useMutation({
    mutationFn: (segmentId: string) => retranslateSegment(segmentId),
    onSuccess: (segment) => {
      setError(null)
      write(segment)
      setAwaiting((current) => new Set(current).add(segment.id))
    },
    onError: (failure: unknown) => {
      setError(failure instanceof ApiError ? failure.detail : 'Nie udało się ponowić tłumaczenia.')
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
            setError(failure instanceof ApiError ? failure.detail : 'Nie udało się sprawdzić stanu akapitu.')

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
    // przesuwal czas kolejnego ticku dla akapitow juz oczekujacych.
  }, [hasAwaiting, write])

  const retranslate = useCallback(
    (segmentId: string) => {
      mutation.mutate(segmentId)
    },
    [mutation],
  )

  return { retranslate, awaiting, error }
}
