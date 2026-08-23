import type { ProjectAction } from '../../api/projects'
import type { Project, ProjectStatus } from '../../api/types'

export const PROJECT_STATUS_LABELS: Record<ProjectStatus, string> = {
  parsing: 'Analizowanie pliku',
  ready: 'Gotowy do tłumaczenia',
  translating: 'Tłumaczenie',
  paused: 'Wstrzymany',
  completed: 'Ukończony',
  completed_with_errors: 'Ukończony z błędami',
  cancelled: 'Anulowany',
  failed: 'Błąd',
}

/** Statuses whose numbers still move, and which therefore deserve polling. */
export function isBusy(status: ProjectStatus): boolean {
  return 'parsing' === status || 'translating' === status
}

export function translatedCount(project: Project): number {
  const { translated = 0, edited = 0 } = project.segmentCounts

  // "edited" to akapit poprawiony recznie - dla postepu liczy sie tak samo jak
  // przetlumaczony maszynowo, dokladnie tak jak w ChapterComposer.
  return translated + edited
}

export function failedCount(project: Project): number {
  return project.segmentCounts.failed ?? 0
}

export function progressPercent(project: Project): number {
  return 0 === project.totalSegments
    ? 0
    : Math.round((translatedCount(project) / project.totalSegments) * 100)
}

/** Mirrors ProjectStatus::canStart() and its neighbours on the backend. */
export function canRun(action: ProjectAction, status: ProjectStatus): boolean {
  switch (action) {
    case 'start':
      return 'ready' === status || 'cancelled' === status
    case 'pause':
      return 'translating' === status
    case 'resume':
      return 'paused' === status
    case 'cancel':
      return 'translating' === status || 'paused' === status
    case 'retry-failed':
      return ['completed_with_errors', 'paused', 'cancelled', 'completed'].includes(status)
  }
}

/** Mirrors ProjectStatus::canDownload(): everything that already has chapters. */
export function canDownload(status: ProjectStatus): boolean {
  return 'parsing' !== status && 'failed' !== status
}
