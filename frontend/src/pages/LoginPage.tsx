import { zodResolver } from '@hookform/resolvers/zod'
import { useMemo, useState } from 'react'
import { useForm } from 'react-hook-form'
import { Link, useNavigate } from 'react-router-dom'
import { z } from 'zod'
import { ApiError } from '../api/problem'
import { useAuth } from '../auth/useAuth'
import { useT } from '../i18n/useT'
import type { Translate } from '../i18n/messages'
import { Button } from '@/components/ui/button'
import { Input } from '@/components/ui/input'
import { Label } from '@/components/ui/label'
import { LocaleSwitcher } from '@/components/LocaleSwitcher'

function buildSchema(t: Translate) {
  return z.object({
    email: z.email(t('validation.email.invalid')),
    password: z.string().min(1, t('validation.password.required')),
  })
}

type Values = z.infer<ReturnType<typeof buildSchema>>

export function LoginPage() {
  const { t } = useT()
  const { signIn } = useAuth()
  const navigate = useNavigate()
  const [serverError, setServerError] = useState<string | null>(null)
  const schema = useMemo(() => buildSchema(t), [t])
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
      setServerError(error instanceof ApiError ? error.detail : t('common.networkError'))
    }
  }

  return (
    <main className="mx-auto flex max-w-sm flex-col gap-6 p-8">
      <div className="flex justify-end">
        <LocaleSwitcher />
      </div>
      <h1 className="text-2xl font-semibold">{t('auth.login.heading')}</h1>
      <form className="flex flex-col gap-4" onSubmit={form.handleSubmit(onSubmit)} noValidate>
        <div className="flex flex-col gap-2">
          <Label htmlFor="email">{t('auth.email')}</Label>
          <Input id="email" type="email" autoComplete="email" {...form.register('email')} />
          {form.formState.errors.email ? (
            <p className="text-sm text-red-600">{form.formState.errors.email.message}</p>
          ) : null}
        </div>
        <div className="flex flex-col gap-2">
          <Label htmlFor="password">{t('auth.password')}</Label>
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
          {t('auth.login.submit')}
        </Button>
      </form>
      <p className="text-sm text-neutral-600">
        {t('auth.noAccount')}{' '}
        <Link className="underline" to="/register">
          {t('auth.createOne')}
        </Link>
        .
      </p>
    </main>
  )
}
