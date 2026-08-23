import { zodResolver } from '@hookform/resolvers/zod'
import { useQuery } from '@tanstack/react-query'
import { useState } from 'react'
import { useForm } from 'react-hook-form'
import { useNavigate } from 'react-router-dom'
import { z } from 'zod'
import { ApiError } from '../../api/problem'
import { listOllamaModels } from '../../api/ollama'
import { createProject } from '../../api/projects'
import { Button } from '@/components/ui/button'
import { Input } from '@/components/ui/input'
import { Label } from '@/components/ui/label'
import { Textarea } from '@/components/ui/textarea'

const schema = z.object({
  file: z.custom<FileList>(
    (value) => value instanceof FileList && value.length > 0,
    'Wybierz plik EPUB.',
  ),
  title: z.string().min(1, 'Podaj tytuł projektu.'),
  sourceLanguage: z.string(),
  targetLanguage: z.string().min(1, 'Wybierz język docelowy.'),
  ollamaModel: z.string().min(1, 'Wybierz model.'),
  customPrompt: z.string(),
})

type Values = z.infer<typeof schema>

export function ProjectWizardPage() {
  const navigate = useNavigate()
  const [serverError, setServerError] = useState<string | null>(null)
  const models = useQuery({ queryKey: ['ollama-models'], queryFn: listOllamaModels })
  const form = useForm<Values>({
    resolver: zodResolver(schema),
    defaultValues: {
      title: '',
      sourceLanguage: '',
      targetLanguage: '',
      ollamaModel: '',
      customPrompt: '',
    },
  })

  async function onSubmit(values: Values) {
    setServerError(null)

    const payload = new FormData()
    const file = values.file[0]
    // Nazwa pliku podana jawnie: bez trzeciego argumentu czesc multipartu
    // potrafi wyjsc jako "blob", a backend zapisuje ta nazwe jako
    // originalFilename ksiazki.
    payload.append('file', file, file.name)
    payload.append('title', values.title)
    payload.append('targetLanguage', values.targetLanguage)
    payload.append('ollamaModel', values.ollamaModel)

    // Pola opcjonalne dokladamy tylko wtedy, gdy cos w nich jest: pusty string
    // przeszedlby przez NotBlank inaczej niz brak pola.
    if ('' !== values.sourceLanguage) {
      payload.append('sourceLanguage', values.sourceLanguage)
    }

    if ('' !== values.customPrompt) {
      payload.append('customPrompt', values.customPrompt)
    }

    try {
      const project = await createProject(payload)
      navigate(`/projekty/${project.id}`, { replace: true })
    } catch (error) {
      // Upload odpowiada golym "detail", bez tablicy violations - pokazujemy
      // dokladnie to, co przyszlo.
      setServerError(error instanceof ApiError ? error.detail : 'Nie udało się połączyć z serwerem.')
    }
  }

  return (
    <section className="flex max-w-xl flex-col gap-6">
      <h1 className="text-2xl font-semibold">Wgraj książkę</h1>
      <form className="flex flex-col gap-4" onSubmit={form.handleSubmit(onSubmit)} noValidate>
        <div className="flex flex-col gap-2">
          <Label htmlFor="file">Plik EPUB</Label>
          <Input
            id="file"
            type="file"
            accept=".epub,application/epub+zip"
            {...form.register('file')}
          />
          {form.formState.errors.file ? (
            <p className="text-sm text-red-600">{form.formState.errors.file.message}</p>
          ) : null}
        </div>

        <div className="flex flex-col gap-2">
          <Label htmlFor="title">Tytuł</Label>
          <Input id="title" {...form.register('title')} />
          {form.formState.errors.title ? (
            <p className="text-sm text-red-600">{form.formState.errors.title.message}</p>
          ) : null}
        </div>

        <div className="flex flex-col gap-2">
          <Label htmlFor="sourceLanguage">Język źródłowy</Label>
          <Input id="sourceLanguage" {...form.register('sourceLanguage')} />
          <p className="text-sm text-neutral-600">
            Możesz zostawić puste — wtedy model rozpozna język sam.
          </p>
        </div>

        <div className="flex flex-col gap-2">
          <Label htmlFor="targetLanguage">Język docelowy</Label>
          <Input id="targetLanguage" {...form.register('targetLanguage')} />
          <p className="text-sm text-neutral-600">
            Kod języka, np. <code>pl</code>, <code>en</code>, <code>de</code>. Ta wartość trafia
            wprost do metadanych pobranej książki.
          </p>
          {form.formState.errors.targetLanguage ? (
            <p className="text-sm text-red-600">{form.formState.errors.targetLanguage.message}</p>
          ) : null}
        </div>

        <div className="flex flex-col gap-2">
          <Label htmlFor="ollamaModel">Model</Label>
          {/* Zwykly <select>, nie komponent shadcn: lista pochodzi z serwera,
              a natywna kontrolka jest dostepna z klawiatury bez naszej pomocy. */}
          <select
            id="ollamaModel"
            className="h-9 rounded-md border px-3"
            disabled={!models.isSuccess}
            {...form.register('ollamaModel')}
          >
            <option value="">Wybierz model…</option>
            {(models.data ?? []).map((model) => (
              <option key={model} value={model}>
                {model}
              </option>
            ))}
          </select>
          {models.isError ? (
            <p className="text-sm text-red-600">
              {models.error instanceof ApiError
                ? models.error.detail
                : 'Nie udało się połączyć z serwerem.'}
            </p>
          ) : null}
          {form.formState.errors.ollamaModel ? (
            <p className="text-sm text-red-600">{form.formState.errors.ollamaModel.message}</p>
          ) : null}
        </div>

        <div className="flex flex-col gap-2">
          <Label htmlFor="customPrompt">Dodatkowe wskazówki dla modelu</Label>
          <Textarea id="customPrompt" rows={3} {...form.register('customPrompt')} />
        </div>

        {null !== serverError ? <p className="text-sm text-red-600">{serverError}</p> : null}

        <Button type="submit" disabled={form.formState.isSubmitting || !models.isSuccess}>
          Wgraj i utwórz projekt
        </Button>
      </form>
    </section>
  )
}
