export type ProjectStatus =
  | 'parsing'
  | 'ready'
  | 'translating'
  | 'paused'
  | 'completed'
  | 'completed_with_errors'
  | 'cancelled'
  | 'failed'

export type SegmentStatus = 'pending' | 'processing' | 'translated' | 'failed' | 'edited'

/** A status missing from the map has no segments in it - the API omits zeros. */
export type SegmentCounts = Partial<Record<SegmentStatus, number>>

export interface Project {
  id: string
  title: string
  sourceLanguage: string | null
  targetLanguage: string
  ollamaModel: string
  customPrompt: string | null
  status: ProjectStatus
  originalFilename: string
  errorMessage: string | null
  createdAt: string
  updatedAt: string
  segmentCounts: SegmentCounts
  totalSegments: number
}

export interface Chapter {
  id: string
  spineOrder: number
  title: string | null
  segmentCounts: SegmentCounts
  totalSegments: number
}

export interface SegmentChapter {
  id: string
  spineOrder: number
  title: string | null
}

export interface Segment {
  id: string
  position: number
  nodeIndex: number
  subIndex: number
  sourceText: string
  translatedText: string | null
  status: SegmentStatus
  errorMessage: string | null
  /** The opening markup each token stands for, already sanitized by the backend so it is safe to inject into the preview document. */
  previewPlaceholders: Record<string, string>
  chapter: SegmentChapter
}
