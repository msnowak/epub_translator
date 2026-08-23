import { useMutation, useQueryClient } from '@tanstack/react-query'
import { useState } from 'react'
import { useNavigate } from 'react-router-dom'
import { downloadProject } from '../../api/download'
import { ApiError } from '../../api/problem'
import { deleteProject, runAction, type ProjectAction } from '../../api/projects'
import type { Project } from '../../api/types'
import { canDownload, canRun } from './status'
import { Button } from '@/components/ui/button'

const ACTION_LABELS: Record<ProjectAction, string> = {
  start: 'Rozpocznij tłumaczenie',
  pause: 'Wstrzymaj',
  resume: 'Wznów',
  cancel: 'Anuluj',
  'retry-failed': 'Ponów nieudane',
}

// Jawna lista zamiast Object.keys: kolejnosc przyciskow jest wtedy nasza,
// a nie taka, w jakiej ktos dopisal klucze.
const ACTIONS: ProjectAction[] = ['start', 'pause', 'resume', 'cancel', 'retry-failed']

export function ProjectActions({ project }: { project: Project }) {
  const queryClient = useQueryClient()
  const navigate = useNavigate()
  const [error, setError] = useState<string | null>(null)
  const [confirmingDelete, setConfirmingDelete] = useState(false)

  function report(caught: unknown): void {
    setError(caught instanceof ApiError ? caught.detail : 'Nie udało się połączyć z serwerem.')
  }

  const control = useMutation({
    mutationFn: (action: ProjectAction) => runAction(project.id, action),
    onMutate: () => setError(null),
    onSuccess: async () => {
      // Statusy zmienia worker, wiec po akcji pytamy serwer, zamiast zgadywac
      // w kliencie, w co przeszedl projekt.
      await queryClient.invalidateQueries({ queryKey: ['project', project.id] })
      await queryClient.invalidateQueries({ queryKey: ['projects'] })
    },
    onError: report,
  })

  const download = useMutation({
    mutationFn: () => downloadProject(project.id),
    onMutate: () => setError(null),
    onError: report,
  })

  const remove = useMutation({
    mutationFn: () => deleteProject(project.id),
    onMutate: () => setError(null),
    onSuccess: async () => {
      await queryClient.invalidateQueries({ queryKey: ['projects'] })
      navigate('/', { replace: true })
    },
    onError: report,
  })

  return (
    <div className="flex flex-col gap-2">
      <div className="flex flex-wrap gap-2">
        {ACTIONS.filter((action) => canRun(action, project.status)).map((action) => (
          <Button
            key={action}
            variant={'start' === action ? 'default' : 'secondary'}
            disabled={control.isPending}
            onClick={() => control.mutate(action)}
          >
            {ACTION_LABELS[action]}
          </Button>
        ))}
        {canDownload(project.status) ? (
          <Button variant="secondary" disabled={download.isPending} onClick={() => download.mutate()}>
            {download.isPending ? 'Przygotowywanie pliku…' : 'Pobierz książkę'}
          </Button>
        ) : null}
        <Button variant="destructive" onClick={() => setConfirmingDelete(true)}>
          Usuń projekt
        </Button>
      </div>

      {canDownload(project.status) ? (
        <p className="text-sm text-neutral-600">
          Możesz pobrać książkę w każdej chwili — akapity bez tłumaczenia zostaną w oryginale.
        </p>
      ) : null}

      {confirmingDelete ? (
        <div className="flex flex-col gap-2 rounded-md border border-red-200 bg-red-50 p-4">
          <p className="text-sm">
            Na pewno usunąć „{project.title}"? Plik i tłumaczenia znikną bezpowrotnie.
          </p>
          <div className="flex gap-2">
            <Button variant="destructive" disabled={remove.isPending} onClick={() => remove.mutate()}>
              Tak, usuń
            </Button>
            <Button variant="secondary" onClick={() => setConfirmingDelete(false)}>
              Zostaw
            </Button>
          </div>
        </div>
      ) : null}

      {null !== error ? <p className="text-sm text-red-600">{error}</p> : null}
    </div>
  )
}
