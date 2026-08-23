import { useQuery } from '@tanstack/react-query'
import { useParams } from 'react-router-dom'
import { ApiError } from '../../api/problem'
import { getProject } from '../../api/projects'
import { ProgressBar } from './ProgressBar'
import { PROJECT_STATUS_LABELS, isBusy } from './status'

export function ProjectDetailPage() {
  const { id = '' } = useParams()
  const { data, error, isError, isPending } = useQuery({
    queryKey: ['project', id],
    queryFn: () => getProject(id),
    refetchInterval: (query) => (query.state.data && isBusy(query.state.data.status) ? 4000 : false),
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

  return (
    <section className="flex flex-col gap-6">
      <div className="flex flex-col gap-2">
        <h1 className="text-2xl font-semibold">{data.title}</h1>
        <p className="text-sm text-neutral-600">{PROJECT_STATUS_LABELS[data.status]}</p>
        <ProgressBar project={data} />
      </div>
    </section>
  )
}
