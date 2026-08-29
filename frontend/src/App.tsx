import { Route, Routes } from 'react-router-dom'
import { RequireAuth } from './auth/RequireAuth'
import { AppLayout } from './components/AppLayout'
import { EditorPage } from './features/editor/EditorPage'
import { ProjectDetailPage } from './features/projects/ProjectDetailPage'
import { ProjectListPage } from './features/projects/ProjectListPage'
import { ProjectWizardPage } from './features/projects/ProjectWizardPage'
import { LoginPage } from './pages/LoginPage'
import { RegisterPage } from './pages/RegisterPage'

export default function App() {
  return (
    <Routes>
      <Route path="/login" element={<LoginPage />} />
      <Route path="/register" element={<RegisterPage />} />
      <Route element={<RequireAuth />}>
        <Route element={<AppLayout />}>
          <Route path="/" element={<ProjectListPage />} />
          <Route path="/projects/new" element={<ProjectWizardPage />} />
          <Route path="/projects/:id" element={<ProjectDetailPage />} />
        </Route>
        <Route element={<AppLayout wide />}>
          <Route path="/projects/:id/chapters/:chapterId" element={<EditorPage />} />
        </Route>
      </Route>
    </Routes>
  )
}
