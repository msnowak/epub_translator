import { screen } from '@testing-library/react'
import userEvent from '@testing-library/user-event'
import { HttpResponse, http } from 'msw'
import { describe, expect, it } from 'vitest'
import { server } from '../../test/server'
import { renderWithProviders } from '../../test/renderWithProviders'
import App from '../../App'

const API = 'http://localhost:8000'
const session = http.post(`${API}/api/token/refresh`, () => HttpResponse.json({ token: 'fresh' }))
const models = http.get(`${API}/api/ollama/models`, () =>
  HttpResponse.json({ models: ['gemma4:12b', 'qwen2.5:14b'] }),
)

const parsingProject = {
  id: 'p1',
  title: 'Wyspa skarbów',
  status: 'parsing',
  targetLanguage: 'pl',
  sourceLanguage: null,
  ollamaModel: 'gemma4:12b',
  customPrompt: null,
  originalFilename: 'book.epub',
  errorMessage: null,
  createdAt: '2026-08-23T10:00:00+00:00',
  updatedAt: '2026-08-23T10:00:00+00:00',
  segmentCounts: {},
  totalSegments: 0,
}

function epub(): File {
  return new File(['PK'], 'book.epub', { type: 'application/epub+zip' })
}

async function fillTheForm(): Promise<void> {
  await userEvent.upload(await screen.findByLabelText('Plik EPUB'), epub())
  await userEvent.type(screen.getByLabelText('Tytuł'), 'Wyspa skarbów')
  await userEvent.type(screen.getByLabelText('Język docelowy'), 'pl')
  await userEvent.selectOptions(await screen.findByLabelText('Model'), 'gemma4:12b')
}

describe('ProjectWizardPage', () => {
  it('uploads the book as multipart and goes to the new project', async () => {
    // Zlapane w obiekcie, nie w zmiennej: przypisanie dzieje sie w callbacku,
    // ktorego analiza przeplywu TypeScriptu nie widzi - luzna zmienna zostalaby
    // zawezona do null.
    const captured: { form: FormData | null } = { form: null }
    server.use(
      session,
      models,
      http.post(`${API}/api/projects`, async ({ request }) => {
        captured.form = await request.formData()

        return HttpResponse.json(parsingProject, { status: 201 })
      }),
      http.get(`${API}/api/projects/p1`, () => HttpResponse.json(parsingProject)),
      http.get(`${API}/api/projects/p1/chapters`, () => HttpResponse.json([])),
    )
    renderWithProviders(<App />, { route: '/projekty/nowy' })

    await fillTheForm()
    await userEvent.click(screen.getByRole('button', { name: 'Wgraj i utwórz projekt' }))

    expect(await screen.findByRole('heading', { name: 'Wyspa skarbów' })).toBeVisible()

    const form = captured.form

    expect(form?.get('title')).toBe('Wyspa skarbów')
    expect(form?.get('targetLanguage')).toBe('pl')
    expect(form?.get('ollamaModel')).toBe('gemma4:12b')
    // Nie instanceof File i nie nazwa pliku: MSW parsuje multipart wlasna
    // implementacja poza jsdom, ktora gubi nazwe i daje obiekt z innego
    // kontekstu. Typ przechodzi przez te granice bez zmian, a nazwe
    // sprawdza sie w przegladarce - backend zapisuje ja jako originalFilename.
    const sentFile = form?.get('file')

    expect(sentFile).not.toBeNull()
    expect(typeof sentFile).not.toBe('string')
    expect(sentFile).toMatchObject({ type: 'application/epub+zip' })
    // Pola opcjonalne, ktorych uzytkownik nie wypelnil, maja w ogole nie
    // wyjsc - pusty string przeszedlby przez NotBlank inaczej niz brak pola.
    expect(form?.has('sourceLanguage')).toBe(false)
    expect(form?.has('customPrompt')).toBe(false)
  })

  it('says what happened when Ollama cannot be reached', async () => {
    server.use(
      session,
      http.get(`${API}/api/ollama/models`, () =>
        HttpResponse.json(
          {
            status: 503,
            detail: 'Nie udało się połączyć z serwerem Ollama. Sprawdź konfigurację połączenia.',
          },
          { status: 503 },
        ),
      ),
    )
    renderWithProviders(<App />, { route: '/projekty/nowy' })

    expect(
      await screen.findByText('Nie udało się połączyć z serwerem Ollama. Sprawdź konfigurację połączenia.'),
    ).toBeVisible()
  })

  it('shows the rejection when the file is not an EPUB', async () => {
    server.use(
      session,
      models,
      http.post(`${API}/api/projects`, () =>
        HttpResponse.json(
          { status: 422, detail: 'Ten plik nie jest poprawnym dokumentem EPUB.' },
          { status: 422 },
        ),
      ),
    )
    renderWithProviders(<App />, { route: '/projekty/nowy' })

    await fillTheForm()
    await userEvent.click(screen.getByRole('button', { name: 'Wgraj i utwórz projekt' }))

    // Upload odpowiada golym "detail", bez tablicy violations - kreator ma
    // pokazac dokladnie to, co przyszlo.
    expect(await screen.findByText('Ten plik nie jest poprawnym dokumentem EPUB.')).toBeVisible()
  })
})
