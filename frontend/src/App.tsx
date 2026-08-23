import { Route, Routes } from 'react-router-dom'
import { RequireAuth } from './auth/RequireAuth'
import { AppLayout } from './components/AppLayout'
import { ProjectListPage } from './features/projects/ProjectListPage'
import { LoginPage } from './pages/LoginPage'
import { RegisterPage } from './pages/RegisterPage'

export default function App() {
  return (
    <Routes>
      <Route path="/logowanie" element={<LoginPage />} />
      <Route path="/rejestracja" element={<RegisterPage />} />
      <Route element={<RequireAuth />}>
        <Route element={<AppLayout />}>
          <Route path="/" element={<ProjectListPage />} />
        </Route>
      </Route>
    </Routes>
  )
}
