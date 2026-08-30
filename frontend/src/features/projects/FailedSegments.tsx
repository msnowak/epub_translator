import { useQuery } from '@tanstack/react-query'
import { Link } from 'react-router-dom'
import { ApiError } from '../../api/problem'
import { listProjectSegments } from '../../api/segments'
import { useT } from '../../i18n/useT'
import { chapterLabel } from './chapterLabel'
import { workerErrorMessage } from './workerError'

export function FailedSegments({ projectId }: { projectId: string }) {
  const { t } = useT()
  const { data, error, isError, isPending } = useQuery({
    queryKey: ['segments', 'failed', projectId],
    queryFn: () => listProjectSegments(projectId, 'failed'),
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

  if (0 === data.length) {
    return <p className="text-neutral-600">{t('failed.none')}</p>
  }

  return (
    <ul className="flex flex-col gap-2">
      {data.map((segment) => {
        const message = workerErrorMessage(segment.errorCode, segment.errorParams, t)

        return (
          <li key={segment.id} className="rounded-md border border-red-200 bg-red-50 p-3 text-sm">
            <Link
              className="font-medium underline"
              // Edytor odczyta ?paragraph= i przewinie do tego wiersza.
              to={`/projects/${projectId}/chapters/${segment.chapter.id}?paragraph=${segment.id}`}
            >
              {t('failed.link', { chapter: chapterLabel(segment.chapter, t), position: segment.position + 1 })}
            </Link>
            <p className="mt-1 text-neutral-700">{segment.sourceText.slice(0, 160)}</p>
            {null !== message ? <p className="mt-1 text-red-700">{message}</p> : null}
          </li>
        )
      })}
    </ul>
  )
}
