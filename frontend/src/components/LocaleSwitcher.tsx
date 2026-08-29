import { useT } from '@/i18n/useT'
import { LOCALES, LOCALE_NAMES, type Locale } from '@/i18n/locales'
import { Select, SelectContent, SelectItem, SelectTrigger, SelectValue } from '@/components/ui/select'

export function LocaleSwitcher() {
  const { t, locale, setLocale } = useT()

  return (
    <Select
      value={locale}
      onValueChange={(next: Locale | null) => {
        if (null !== next) {
          setLocale(next)
        }
      }}
    >
      <SelectTrigger size="sm" aria-label={t('app.language')}>
        <SelectValue />
      </SelectTrigger>
      <SelectContent>
        {LOCALES.map((candidate) => (
          <SelectItem key={candidate} value={candidate}>
            {LOCALE_NAMES[candidate]}
          </SelectItem>
        ))}
      </SelectContent>
    </Select>
  )
}
