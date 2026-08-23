import { apiJson } from './client'

interface ModelsResponse {
  models: string[]
}

export async function listOllamaModels(): Promise<string[]> {
  const body = await apiJson<ModelsResponse>('/api/ollama/models')

  return body.models
}
