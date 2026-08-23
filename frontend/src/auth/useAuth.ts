import { useContext } from 'react'
import { AuthContext, type AuthContextValue } from './AuthProvider'

export function useAuth(): AuthContextValue {
  const value = useContext(AuthContext)

  if (null === value) {
    throw new Error('useAuth must be used inside an AuthProvider.')
  }

  return value
}
