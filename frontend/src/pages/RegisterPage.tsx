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

const schema = z.object({
  email: z.email('To nie jest poprawny adres e-mail.'),
  // Lustro reguly z backendu: User::$plainPassword ma Length(min: 8).
  password: z.string().min(8, 'Hasło musi mieć co najmniej 8 znaków.'),
})

type Values = z.infer<typeof schema>

export function RegisterPage() {
  const { signUp } = useAuth()
  const navigate = useNavigate()
  const [serverError, setServerError] = useState<string | null>(null)
  const form = useForm<Values>({
    resolver: zodResolver(schema),
    defaultValues: { email: '', password: '' },
  })

  async function onSubmit(values: Values) {
    setServerError(null)

    try {
      await signUp(values.email, values.password)
      navigate('/', { replace: true })
    } catch (error) {
      if (!(error instanceof ApiError)) {
        setServerError('Nie udało się połączyć z serwerem.')

        return
      }

      const fieldErrors = error.fieldErrors()

      // Serwer waliduje to samo co zod, tylko dodatkowo wie o zajetych
      // adresach - jego komunikat trafia pod to samo pole.
      for (const [field, message] of Object.entries(fieldErrors)) {
        if ('email' === field) {
          form.setError('email', { message })
        }

        if ('plainPassword' === field) {
          form.setError('password', { message })
        }
      }

      if (0 === Object.keys(fieldErrors).length) {
        setServerError(error.detail)
      }
    }
  }

  return (
    <main className="mx-auto flex max-w-sm flex-col gap-6 p-8">
      <h1 className="text-2xl font-semibold">Załóż konto</h1>
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
            autoComplete="new-password"
            {...form.register('password')}
          />
          {form.formState.errors.password ? (
            <p className="text-sm text-red-600">{form.formState.errors.password.message}</p>
          ) : null}
        </div>
        {null !== serverError ? <p className="text-sm text-red-600">{serverError}</p> : null}
        <Button type="submit" disabled={form.formState.isSubmitting}>
          Załóż konto
        </Button>
      </form>
      <p className="text-sm text-neutral-600">
        Masz już konto?{' '}
        <Link className="underline" to="/logowanie">
          Zaloguj się
        </Link>
        .
      </p>
    </main>
  )
}
