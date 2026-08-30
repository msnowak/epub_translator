import { useQuery } from '@tanstack/react-query'
import { Link, useParams } from 'react-router-dom'
import { ApiError } from '../../api/problem'
import { getProject, listChapters } from '../../api/projects'
import { useT } from '../../i18n/useT'
import { ChapterTable } from './ChapterTable'
import { FailedSegments } from './FailedSegments'
import { ProgressBar } from './ProgressBar'
import { ProjectActions } from './ProjectActions'
import { PROJECT_STATUS_KEYS, failedCount, isBusy } from './status'

export function ProjectDetailPage() {
  const { t } = useT()
  const { id = '' } = useParams()
  const { data, error, isError, isPending } = useQuery({
    queryKey: ['project', id],
    queryFn: () => getProject(id),
    refetchInterval: (query) => (query.state.data && isBusy(query.state.data.status) ? 4000 : false),
  })
  const chapters = useQuery({
    queryKey: ['chapters', id],
    queryFn: () => listChapters(id),
    // Rzadziej niz sam projekt: to N wierszy zamiast jednego, a licznik per
    // rozdzial i tak rusza sie wolniej niz globalny. Warunek patrzy na projekt,
    // nie na wlasne dane tego zapytania - to on wie, czy cos jeszcze trwa.
    refetchInterval: () => (data && isBusy(data.status) ? 8000 : false),
  })

  if (isPending) {
    return <p className="text-neutral-500">{t('common.loading')}</p>
  }

  if (isError) {
    return (
      <p className="text-red-600">
        {error instanceof ApiError ? error.detail : t('common.networkError')}
      </p>
    )
  }

  const failed = failedCount(data)

  return (
    <section className="flex flex-col gap-6">
      <div className="flex flex-col gap-2">
        <Link className="text-sm underline" to="/">
          ← {t('projects.detail.back')}
        </Link>
        <h1 className="text-2xl font-semibold">{data.title}</h1>
        <p className="text-sm font-medium">{t(PROJECT_STATUS_KEYS[data.status])}</p>
        <p className="text-sm text-neutral-600">
          {data.sourceLanguage ?? t('projects.detail.detectedLanguage')} → {data.targetLanguage} ·{' '}
          {data.ollamaModel} · {data.originalFilename}
        </p>
        <ProgressBar project={data} />
        {failed > 0 ? (
          <p className="text-sm text-red-600">{t('projects.detail.failedNotice', { count: failed })}</p>
        ) : null}
        {null !== data.errorMessage ? (
          <p className="rounded-md border border-red-200 bg-red-50 p-3 text-sm text-red-700">
            {data.errorMessage}
          </p>
        ) : null}
      </div>

      <ProjectActions project={data} />

      <div className="flex flex-col gap-2">
        <h2 className="text-lg font-medium">{t('projects.detail.chapters')}</h2>
        {chapters.isError ? (
          <p className="text-red-600">
            {chapters.error instanceof ApiError ? chapters.error.detail : t('common.networkError')}
          </p>
        ) : chapters.isPending ? (
          // Osobny stan, bo pusta tabela mowi "rozdzialow jeszcze nie ma",
          // a to nieprawda, dopoki zapytanie leci.
          <p className="text-neutral-500">{t('common.loading')}</p>
        ) : (
          <ChapterTable chapters={chapters.data} projectId={id} />
        )}
      </div>

      {failed > 0 ? (
        <div className="flex flex-col gap-2">
          <h2 className="text-lg font-medium">{t('projects.detail.failedHeading')}</h2>
          <FailedSegments projectId={id} />
        </div>
      ) : null}
    </section>
  )
}
