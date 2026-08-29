import { Link } from 'react-router-dom'
import type { Chapter } from '../../api/types'
import { useT } from '../../i18n/useT'
import { chapterLabel } from '../projects/chapterLabel'

interface Props {
  projectId: string
  chapters: Chapter[]
  currentId: string
}

export function ChapterNav({ projectId, chapters, currentId }: Props) {
  const { t } = useT()

  return (
    <nav className="flex h-full flex-col gap-1 overflow-y-auto border-r p-3">
      {chapters.map((chapter) => {
        const { failed = 0 } = chapter.segmentCounts

        return (
          <Link
            key={chapter.id}
            to={`/projects/${projectId}/chapters/${chapter.id}`}
            className={`rounded-md px-2 py-1 text-sm ${chapter.id === currentId ? 'bg-neutral-200 font-medium' : 'hover:bg-neutral-100'}`}
          >
            {chapterLabel(chapter, t)}
            {failed > 0 ? <span className="ml-1 text-xs text-red-600">({failed})</span> : null}
          </Link>
        )
      })}
    </nav>
  )
}
