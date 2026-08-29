import { zodResolver } from '@hookform/resolvers/zod'
import { useState } from 'react'
import { useForm } from 'react-hook-form'
import { Link, useNavigate } from 'react-router-dom'
import { z } from 'zod'
import { ApiError } from '../api/problem'
import { useAuth } from '../auth/useAuth'
import { Button } from '@/components/ui/button'
import { Input } from '@/components/ui/input'
import { Label } from '@/components/ui/label'
import { LocaleSwitcher } from '@/components/LocaleSwitcher'

const schema = z.object({
  email: z.email('To nie jest poprawny adres e-mail.'),
  password: z.string().min(1, 'Podaj hasło.'),
})

type Values = z.infer<typeof schema>

export function LoginPage() {
  const { signIn } = useAuth()
  const navigate = useNavigate()
  const [serverError, setServerError] = useState<string | null>(null)
  const form = useForm<Values>({
    resolver: zodResolver(schema),
    defaultValues: { email: '', password: '' },
  })

  async function onSubmit(values: Values) {
    setServerError(null)

    try {
      await signIn(values.email, values.password)
      navigate('/', { replace: true })
    } catch (error) {
      // Tekst pochodzi z serwera - front go nie wymysla.
      setServerError(error instanceof ApiError ? error.detail : 'Nie udało się połączyć z serwerem.')
    }
  }

  return (
    <main className="mx-auto flex max-w-sm flex-col gap-6 p-8">
      <div className="flex justify-end">
        <LocaleSwitcher />
      </div>
      <h1 className="text-2xl font-semibold">Zaloguj się</h1>
      <form className="flex flex-col gap-4" onSubmit={form.handleSubmit(onSubmit)} noValidate>
        <div className="flex flex-col gap-2">
          <Label htmlFor="email">Adres e-mail</Label>
          <Input id="email" type="email" autoComplete="email" {...form.register('email')} />
          {form.formState.errors.email ? (
            <p className="text-sm text-red-600">{form.formState.errors.email.message}</p>
          ) : null}
        </div>
        <div className="flex flex-col gap-2">
          <Label htmlFor="password">Hasło</Label>
          <Input
            id="password"
            type="password"
            autoComplete="current-password"
            {...form.register('password')}
          />
          {form.formState.errors.password ? (
            <p className="text-sm text-red-600">{form.formState.errors.password.message}</p>
          ) : null}
        </div>
        {null !== serverError ? <p className="text-sm text-red-600">{serverError}</p> : null}
        <Button type="submit" disabled={form.formState.isSubmitting}>
          Zaloguj się
        </Button>
      </form>
      <p className="text-sm text-neutral-600">
        Nie masz konta?{' '}
        <Link className="underline" to="/register">
          Załóż je
        </Link>
        .
      </p>
    </main>
  )
}
