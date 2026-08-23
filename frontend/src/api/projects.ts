import { apiJson, apiVoid } from './client'
import type { Chapter, Project } from './types'

export type ProjectAction = 'start' | 'pause' | 'resume' | 'cancel' | 'retry-failed'

export function listProjects(): Promise<Project[]> {
  return apiJson<Project[]>('/api/projects')
}

export function getProject(id: string): Promise<Project> {
  return apiJson<Project>(`/api/projects/${id}`)
}

export function listChapters(projectId: string): Promise<Chapter[]> {
  return apiJson<Chapter[]>(`/api/projects/${projectId}/chapters`)
}

export function createProject(form: FormData): Promise<Project> {
  // Bez naglowka Content-Type: przegladarka musi go ustawic sama, razem
  // z granica multipartu, ktorej my nie znamy.
  return apiJson<Project>('/api/projects', { method: 'POST', body: form })
}

export function runAction(id: string, action: ProjectAction): Promise<void> {
  // Te operacje nie maja ciala - backend czyta z nich wylacznie identyfikator.
  return apiVoid(`/api/projects/${id}/${action}`, { method: 'POST' })
}

export function deleteProject(id: string): Promise<void> {
  return apiVoid(`/api/projects/${id}`, { method: 'DELETE' })
}
