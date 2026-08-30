import type { Project } from '../../api/types'
import { useT } from '../../i18n/useT'
import { progressPercent, translatedCount } from './status'
import { Progress } from '@/components/ui/progress'

export function ProgressBar({ project }: { project: Project }) {
  const { t } = useT()

  if (0 === project.totalSegments) {
    // Projekt tuz po wgraniu: worker jeszcze nie policzyl akapitow, wiec pasek
    // na zero procent klamalby, ze nic nie zrobiono.
    return <p className="text-sm text-neutral-600">{t('projects.progress.unknown')}</p>
  }

  const percent = progressPercent(project)

  return (
    <div className="flex flex-col gap-1">
      <Progress value={percent} />
      <p className="text-sm text-neutral-600">
        {t('projects.progress.count', {
          done: translatedCount(project),
          count: project.totalSegments,
          percent,
        })}
      </p>
    </div>
  )
}
