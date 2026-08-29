import { useQuery } from '@tanstack/react-query'
import { Link } from 'react-router-dom'
import { ApiError } from '../../api/problem'
import { listProjects } from '../../api/projects'
import type { SimpleKey } from '../../i18n/messages'
import { useT } from '../../i18n/useT'
import { ProgressBar } from './ProgressBar'
import { PROJECT_STATUS_KEYS, isBusy } from './status'
import { buttonVariants } from '@/components/ui/button'

export function ProjectListPage() {
  const { t } = useT()
  const { data, error, isError, isPending } = useQuery({
    queryKey: ['projects'],
    queryFn: listProjects,
    // Odpytujemy tylko wtedy, gdy jest na co czekac. Lista samych ukonczonych
    // ksiazek nie ma powodu odswiezac sie co cztery sekundy.
    refetchInterval: (query) =>
      query.state.data?.some((project) => isBusy(project.status)) ? 4000 : false,
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
      <div className="flex items-center justify-between">
        <h1 className="text-2xl font-semibold">Twoje książki</h1>
        {/* Link ostylowany jak przycisk: ten wariant shadcn stoi na Base UI,
            ktore nie zna "asChild" - podmiana elementu idzie przez klasy. */}
        <Link className={buttonVariants()} to="/projects/new">
          Wgraj książkę
        </Link>
      </div>
      {0 === data.length ? (
        <p className="text-neutral-600">Nie masz jeszcze żadnej książki.</p>
      ) : (
        <ul className="flex flex-col gap-4">
          {data.map((project) => (
            <li key={project.id} className="flex flex-col gap-2 rounded-lg border p-4">
              <Link className="text-lg font-medium underline" to={`/projects/${project.id}`}>
                {project.title}
              </Link>
              <p className="text-sm text-neutral-600">{t(PROJECT_STATUS_KEYS[project.status] as SimpleKey)}</p>
              <ProgressBar project={project} />
            </li>
          ))}
        </ul>
      )}
    </section>
  )
}
