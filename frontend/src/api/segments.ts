import { apiJson } from './client'
import type { Segment, SegmentStatus } from './types'

export async function listChapterSegments(chapterId: string): Promise<Segment[]> {
  const raw = await apiJson<unknown[]>(`/api/chapters/${chapterId}/segments`)

  return raw.map(toSegment)
}

export async function listProjectSegments(projectId: string, status: SegmentStatus): Promise<Segment[]> {
  const raw = await apiJson<unknown[]>(`/api/projects/${projectId}/segments?status=${status}`)

  return raw.map(toSegment)
}

export async function getSegment(id: string): Promise<Segment> {
  return toSegment(await apiJson<unknown>(`/api/segments/${id}`))
}

export async function updateSegment(
  id: string,
  translatedText: string,
  options: { keepalive?: boolean } = {},
): Promise<Segment> {
  const raw = await apiJson<unknown>(`/api/segments/${id}`, {
    method: 'PATCH',
    // API Platform czyta PATCH wylacznie jako merge-patch+json; zwykly
    // application/json konczy sie odpowiedzia 415.
    headers: { 'Content-Type': 'application/merge-patch+json' },
    body: JSON.stringify({ translatedText }),
    keepalive: options.keepalive,
  })

  return toSegment(raw)
}

export async function retranslateSegment(id: string): Promise<Segment> {
  return toSegment(await apiJson<unknown>(`/api/segments/${id}/retranslate`, { method: 'POST' }))
}

export function toSegment(raw: unknown): Segment {
  const segment = raw as Segment & { previewPlaceholders: unknown }

  return { ...segment, previewPlaceholders: toPlaceholderMap(segment.previewPlaceholders) }
}

function toPlaceholderMap(value: unknown): Record<string, string> {
  // PHP serializuje pusta tablice jako [], nie {}. Akapit bez znacznikow to
  // przypadek najczestszy, wiec front musi go rozumiec, a nie sie o niego
  // potykac przy pierwszym odczycie.
  if (null === value || 'object' !== typeof value || Array.isArray(value)) {
    return {}
  }

  return value as Record<string, string>
}
