import { useQuery } from '@tanstack/react-query'
import { useCallback, useEffect, useMemo, useRef, useState } from 'react'
import { Link, useParams, useSearchParams } from 'react-router-dom'
import { Button } from '@/components/ui/button'
import { ApiError } from '../../api/problem'
import { loadChapterPreview } from '../../api/preview'
import { getProject, listChapters } from '../../api/projects'
import { listChapterSegments } from '../../api/segments'
import { chapterLabel } from '../projects/chapterLabel'
import { ChapterNav } from './ChapterNav'
import { PreviewPane } from './PreviewPane'
import { SegmentList } from './SegmentList'
import { applyTranslation, scrollSegmentIntoView } from './preview'
import { useRetranslation } from './useRetranslation'

export function EditorPage() {
  const { id = '', chapterId = '' } = useParams()
  const [searchParams] = useSearchParams()
  const requested = searchParams.get('akapit')
  const jumped = useRef(false)
  const frameRef = useRef<HTMLIFrameElement>(null)
  const [activeId, setActiveId] = useState<string | null>(null)
  const [onlyFailed, setOnlyFailed] = useState(false)

  const project = useQuery({ queryKey: ['project', id], queryFn: () => getProject(id) })
  // Bez refetchInterval: TanStack bierze minimum z interwalow obserwatorow,
  // wiec przy otwartym samym edytorze odpytywanie rozdzialow nie chodzi.
  const chapters = useQuery({ queryKey: ['chapters', id], queryFn: () => listChapters(id) })
  const segments = useQuery({
    queryKey: ['segments', chapterId],
    queryFn: () => listChapterSegments(chapterId),
  })
  // Rozdzial nie zmienia sie sam z siebie, wiec nie ma powodu go odpytywac -
  // staleTime: Infinity plus wylaczony refetchOnWindowFocus, inaczej powrot
  // do juz odwiedzonego rozdzialu (albo powrot fokusu do karty) najpierw
  // oddaje dokument z cache'u, po czym natychmiast leci odswiezenie w tle;
  // kazda zmiana wartosci srcDoc przeladowuje ramke od zera, wiec podglad
  // mruga i wczytuje sie dwa razy. Kod, ktory zmienia tresc rozdzialu
  // (useSegmentEditor, useRetranslation), uniewaznia to zapytanie po
  // prefiksie klucza, wiec swiezy dokument i tak trafia tu przy nastepnym
  // wejsciu w rozdzial.
  const preview = useQuery({
    queryKey: ['preview', id, chapterId],
    queryFn: () => loadChapterPreview(id, chapterId),
    staleTime: Infinity,
    refetchOnWindowFocus: false,
  })
  const patchPreview = useCallback((segmentId: string, html: string) => {
    const document = frameRef.current?.contentDocument ?? null

    if (null !== document) {
      applyTranslation(document, segmentId, html)
    }
  }, [])

  // Odczytywany przez retranslationPreview ponizej, zeby ten callback moze
  // zostac referencyjnie stabilny (tablica zaleznosci pusta poza patchPreview,
  // ktory sam jest stabilny) - inaczej zmiana aktywnego akapitu przezbrajalaby
  // interwal odpytywania w useRetranslation przy kazdym kliknieciu, tak jak
  // opisuje to komentarz przy "awaitingRef" w tamtym haku.
  const activeIdRef = useRef<string | null>(null)

  useEffect(() => {
    activeIdRef.current = activeId
  }, [activeId])

  const retranslationPreview = useCallback(
    (segmentId: string, html: string) => {
      // Wiersz z fokusem to ten, ktory uzytkownik wlasnie ogląda albo edytuje
      // - jego wlasny debounce w useSegmentEditor i tak wpisuje najnowsza
      // tresc do tego samego wezla po kazdym klawiszu. Gdyby ponowione
      // tlumaczenie nadpisalo ten wezel w tym momencie, wygraloby wyscig i
      // przez chwile pokazywaloby cudza tresc pod kursorem piszacego, wiec
      // manualna sciezka ma pierwszenstwo dla aktywnego wiersza. Kazdy inny
      // wiersz (retranslacja akapitu, ktorego uzytkownik akurat nie edytuje)
      // przyjmuje podmiane normalnie.
      if (segmentId === activeIdRef.current) {
        return
      }

      patchPreview(segmentId, html)
    },
    [patchPreview],
  )

  const retranslation = useRetranslation(chapterId, retranslationPreview)

  const activate = useCallback((segmentId: string) => {
    setActiveId(segmentId)

    const document = frameRef.current?.contentDocument ?? null

    if (null !== document) {
      scrollSegmentIntoView(document, segmentId)
    }
  }, [])

  useEffect(() => {
    if (jumped.current || null === requested || undefined === segments.data) {
      return
    }

    jumped.current = true
    activate(requested)
    // Wirtualizowana lista nie ma tego wiersza w DOM-ie, dopoki do niego nie
    // dojedzie - dlatego przewijamy przez atrybut, a nie przez ref.
    document.querySelector(`[data-segment-row="${requested}"]`)?.scrollIntoView({ block: 'center' })
  }, [activate, requested, segments.data])

  const visible = useMemo(
    () => (segments.data ?? []).filter((segment) => !onlyFailed || 'failed' === segment.status),
    [onlyFailed, segments.data],
  )

  const chapter = chapters.data?.find((item) => item.id === chapterId) ?? null

  if (segments.isError || preview.isError) {
    const error = segments.error ?? preview.error

    return (
      <p className="p-4 text-red-600">
        {error instanceof ApiError ? error.detail : 'Nie udało się połączyć z serwerem.'}
      </p>
    )
  }

  return (
    <div className="grid h-full grid-cols-[14rem_minmax(0,1fr)_minmax(0,1.35fr)]">
      {chapters.isPending ? (
        <p className="p-3 text-neutral-500">Wczytywanie…</p>
      ) : (
        <ChapterNav projectId={id} chapters={chapters.data ?? []} currentId={chapterId} />
      )}

      <section className="flex h-full flex-col overflow-hidden">
        <header className="flex flex-col gap-2 border-b p-3">
          <Link className="text-sm underline" to={`/projekty/${id}`}>
            ← {project.data?.title ?? 'Książka'}
          </Link>
          <h1 className="text-lg font-medium">{null === chapter ? 'Rozdział' : chapterLabel(chapter)}</h1>

          {'translating' === project.data?.status ? (
            <div className="flex items-center gap-3 rounded-md border border-amber-200 bg-amber-50 p-2 text-sm">
              <span>Tłumaczenie tej książki trwa — nowe akapity nie pojawią się same.</span>
              <Button size="sm" variant="outline" onClick={() => void segments.refetch()}>
                Wczytaj ponownie
              </Button>
            </div>
          ) : null}

          <Button size="sm" variant={onlyFailed ? 'default' : 'outline'} onClick={() => setOnlyFailed(!onlyFailed)}>
            {onlyFailed ? 'Wszystkie akapity' : 'Tylko nieudane'}
          </Button>
        </header>

        {null !== retranslation.error ? (
          <p className="p-3 text-sm text-red-600">{retranslation.error}</p>
        ) : null}

        {segments.isPending ? (
          <p className="p-4 text-neutral-500">Wczytywanie…</p>
        ) : 0 === visible.length ? (
          <p className="p-4 text-neutral-600">
            {onlyFailed ? 'W tym rozdziale nie ma nieudanych akapitów.' : 'Ten rozdział nie ma akapitów.'}
          </p>
        ) : (
          <SegmentList
            segments={visible}
            chapterId={chapterId}
            activeId={activeId}
            onPreview={patchPreview}
            onActivate={activate}
            onRetranslate={retranslation.retranslate}
          />
        )}
      </section>

      <section className="h-full overflow-hidden border-l">
        <PreviewPane html={preview.data ?? null} frameRef={frameRef} onSegmentClick={activate} />
      </section>
    </div>
  )
}
