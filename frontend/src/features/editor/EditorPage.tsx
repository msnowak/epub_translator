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

  // Akapity z niezapisana zmiana (patrz useSegmentEditor/markDirty) - nie
  // stan Reactowy, bo retranslationPreview ponizej musi zostac referencyjnie
  // stabilny (inaczej przezbrajalby interwal odpytywania w useRetranslation
  // przy kazdym naciscietym klawiszu, patrz "awaitingRef" w tamtym haku), a
  // zawartosc tego zbioru zmienia sie po kazdym klawiszu.
  const dirtyIdsRef = useRef<Set<string>>(new Set())

  // Zmiana rozdzialu porzuca wszystkie wiersze poprzedniego - zaden z nich
  // nie moze zostac "brudny" na zawsze i blokowac podglad akapitu o tym samym
  // indeksie w nowym rozdziale.
  useEffect(() => {
    dirtyIdsRef.current = new Set()
  }, [chapterId])

  const markDirty = useCallback((segmentId: string, dirty: boolean) => {
    if (dirty) {
      dirtyIdsRef.current.add(segmentId)
    } else {
      dirtyIdsRef.current.delete(segmentId)
    }
  }, [])

  const retranslationPreview = useCallback(
    (segmentId: string, html: string) => {
      // Wiersz z niezapisana zmiana to ten, ktory uzytkownik wlasnie edytuje
      // - jego wlasny debounce w useSegmentEditor i tak wpisuje najnowsza
      // tresc do tego samego wezla po kazdym klawiszu. Gdyby ponowione
      // tlumaczenie nadpisalo ten wezel w tym momencie, wygraloby wyscig i
      // przez chwile pokazywaloby cudza tresc pod kursorem piszacego, wiec
      // manualna sciezka ma pierwszenstwo dla kazdego akapitu z niezapisana
      // zmiana. Samo skupienie na polu (bez pisania) niczego tu nie chroni -
      // kazdy inny wiersz (w tym ten po prostu ogladany) przyjmuje podmiane
      // normalnie.
      if (dirtyIdsRef.current.has(segmentId)) {
        return
      }

      patchPreview(segmentId, html)
    },
    [patchPreview],
  )

  const retranslation = useRetranslation(chapterId, retranslationPreview)

  const saveError = useQuery({
    // Klucz obejmuje chapterId: przejscie do innego rozdzialu ma zaczac z
    // czystym banerem, a nie ciagnac za soba blad zapisu sprzed przejscia.
    queryKey: ['segments', 'save-error', chapterId],
    queryFn: () => null as string | null,
    initialData: null,
    // Ta "kolejka" nigdy nie ma wlasnego zapytania sieciowego - to kanal, przez
    // ktory useSegmentEditor pisze blad zapisu po odmontowaniu wiersza (patrz
    // reportError() tam), a strona go tu tylko czyta. staleTime: Infinity
    // powstrzymuje refetchQuery przed wywolaniem tej pustej queryFn.
    staleTime: Infinity,
  })

  // Aktywacja przychodzi z dwoch bardzo roznych miejsc: fokus na polu
  // tekstowym wiersza (uzytkownik juz na niego patrzy - przewijanie listy
  // byloby co najmniej bezcelowe, a w trakcie pisania wrecz przeszkadzalo -
  // patrz punkt 2 zadania) oraz zdarzenia spoza listy, klikniecie w
  // podgladzie albo link "?akapit=" (lista wlasnie nie ma tego wiersza w
  // oknie widocznosci i to ja trzeba przewinac). "source" czyni ten podzial
  // jawnym w miejscu wywolania, zamiast zgadywac go po tym, kto zawolal.
  //
  // scrollTo nosi licznik ("token") obok id: klikniecie tego samego akapitu
  // drugi raz z rzedu (np. po tym jak uzytkownik recznie przewinal liste
  // gdzie indziej) dawaloby ten sam string co poprzednio, a React pomija
  // rerender przy setState identyczna wartoscia prymitywna - bez tokena
  // efekt przewijajacy w SegmentList w ogole by sie nie uruchomil.
  const [scrollTo, setScrollTo] = useState<{ id: string; token: number } | null>(null)
  const scrollTokenRef = useRef(0)

  const activate = useCallback((segmentId: string, source: 'row' | 'external') => {
    setActiveId(segmentId)

    if ('external' === source) {
      scrollTokenRef.current += 1
      setScrollTo({ id: segmentId, token: scrollTokenRef.current })
    }

    const document = frameRef.current?.contentDocument ?? null

    if (null !== document) {
      scrollSegmentIntoView(document, segmentId)
    }
  }, [])

  const activateFromRow = useCallback((segmentId: string) => activate(segmentId, 'row'), [activate])
  const activateFromPreview = useCallback((segmentId: string) => activate(segmentId, 'external'), [activate])

  useEffect(() => {
    if (jumped.current || null === requested || undefined === segments.data) {
      return
    }

    jumped.current = true
    activate(requested, 'external')
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
              <Button
                size="sm"
                variant="outline"
                onClick={() => {
                  // Obie kolumny czytaja ten sam rozdzial - odswiezenie
                  // samej listy akapitow zostawia podglad (staleTime:
                  // Infinity, refetchOnWindowFocus: false) na starej,
                  // nieprzetlumaczonej tresci, a to wlasnie trzeciej kolumnie
                  // ma nie wolno sie zdarzyc.
                  void segments.refetch()
                  void preview.refetch()
                }}
              >
                Wczytaj ponownie
              </Button>
            </div>
          ) : null}

          {null !== saveError.data ? <p className="text-sm text-red-600">{saveError.data}</p> : null}

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
            scrollTo={scrollTo}
            onPreview={patchPreview}
            onActivate={activateFromRow}
            onRetranslate={retranslation.retranslate}
            onDirtyChange={markDirty}
          />
        )}
      </section>

      <section className="h-full overflow-hidden border-l">
        <PreviewPane html={preview.data ?? null} frameRef={frameRef} onSegmentClick={activateFromPreview} />
      </section>
    </div>
  )
}
