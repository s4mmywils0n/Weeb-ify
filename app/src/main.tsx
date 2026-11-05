import 'vite/modulepreload-polyfill'
import { StrictMode } from 'react'
import { createRoot } from 'react-dom/client'
import App from './App'
import './styles.css'

const mount = document.getElementById('react-root')

if (mount) {
  const page = Number(mount.dataset.page ?? '1')
  const recordId = Number(mount.dataset.recordId ?? '')

  createRoot(mount).render(
    <StrictMode>
      <App
        view={mount.dataset.view ?? 'anime'}
        apiBase={(mount.dataset.apiBase ?? '').replace(/\/$/, '')}
        routeBase={(mount.dataset.routeBase ?? '').replace(/\/$/, '')}
        page={Number.isInteger(page) && page > 0 ? page : 1}
        recordId={Number.isInteger(recordId) && recordId > 0 ? recordId : null}
      />
    </StrictMode>,
  )
}
