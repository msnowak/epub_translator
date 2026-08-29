import { useContext } from 'react'
import { LocaleContext, type LocaleContextValue } from './LocaleProvider'

export function useT(): LocaleContextValue {
  const value = useContext(LocaleContext)

  if (null === value) {
    throw new Error('useT must be used inside a LocaleProvider.')
  }

  return value
}
