import { useQuery } from '@tanstack/react-query'
import { Link } from 'react-router-dom'
import { ApiError } from '../../api/problem'
import { listProjectSegments } from '../../api/segments'
import { useT } from '../../i18n/useT'
import { chapterLabel } from './chapterLabel'

export function FailedSegments({ projectId }: { projectId: string }) {
  const { t } = useT()
  const { data, error, isError, isPending } = useQuery({
    queryKey: ['segments', 'failed', projectId],
    queryFn: () => listProjectSegments(projectId, 'failed'),
  })

  if (isPending) {
    return <p className="text-neutral-500">Wczytywanie…</p>
  }

  if (isError) {
    return (
      <p className="text-red-600">
        {error instanceof ApiError ? error.detail : 'Nie udało się połączyć z serwerem.'}
      </p>
    )
  }

  if (0 === data.length) {
    return <p className="text-neutral-600">Żaden akapit nie zgłosił błędu.</p>
  }

  return (
    <ul className="flex flex-col gap-2">
      {data.map((segment) => (
        <li key={segment.id} className="rounded-md border border-red-200 bg-red-50 p-3 text-sm">
          <Link
            className="font-medium underline"
            // Edytor odczyta ?paragraph= i przewinie do tego wiersza.
            to={`/projects/${projectId}/chapters/${segment.chapter.id}?paragraph=${segment.id}`}
          >
            {chapterLabel(segment.chapter, t)}, akapit {segment.position + 1}
          </Link>
          <p className="mt-1 text-neutral-700">{segment.sourceText.slice(0, 160)}</p>
          {null !== segment.errorMessage ? <p className="mt-1 text-red-700">{segment.errorMessage}</p> : null}
        </li>
      ))}
    </ul>
  )
}
