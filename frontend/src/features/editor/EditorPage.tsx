import { useQuery } from '@tanstack/react-query'
import { useCallback, useMemo, useRef, useState } from 'react'
import { Link, useParams } from 'react-router-dom'
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

export function EditorPage() {
  const { id = '', chapterId = '' } = useParams()
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
  const preview = useQuery({
    queryKey: ['preview', id, chapterId],
    queryFn: () => loadChapterPreview(id, chapterId),
  })

  const patchPreview = useCallback((segmentId: string, html: string) => {
    const document = frameRef.current?.contentDocument ?? null

    if (null !== document) {
      applyTranslation(document, segmentId, html)
    }
  }, [])

  const activate = useCallback((segmentId: string) => {
    setActiveId(segmentId)

    const document = frameRef.current?.contentDocument ?? null

    if (null !== document) {
      scrollSegmentIntoView(document, segmentId)
    }
  }, [])

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
    <div className="grid h-[calc(100vh-6rem)] grid-cols-[16rem_1fr_1fr]">
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
            onRetranslate={() => undefined}
          />
        )}
      </section>

      <section className="h-full overflow-hidden border-l">
        <PreviewPane html={preview.data ?? null} frameRef={frameRef} onSegmentClick={activate} />
      </section>
    </div>
  )
}
