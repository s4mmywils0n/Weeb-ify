import { useEffect, useState } from 'react'

type Resource = 'anime' | 'manga'
type RecordData = Record<string, string | number | null> & { id: number }
type AppProps = {
  view: string
  apiBase: string
  routeBase: string
  page: number
  recordId: number | null
}

async function apiRequest<T>(url: string, options?: RequestInit): Promise<T> {
  const headers = new Headers(options?.headers)
  headers.set('Accept', 'application/json')
  if (options?.body && !headers.has('Content-Type')) {
    headers.set('Content-Type', 'application/json')
  }

  const response = await fetch(url, {
    ...options,
    headers,
  })

  const result = await response.json().catch(() => null)
  if (!response.ok) {
    throw new Error(result?.error || `Request failed (${response.status})`)
  }
  return result as T
}

function SiteHeader({ routeBase, view }: { routeBase: string; view: string }) {
  const base = routeBase.replace(/\/$/, '')
  return (
    <header className="site-header">
      <a className="brand" href={`${base}/home`} aria-label="Weebi:fy home">
        Weebi<span>:fy</span>
      </a>
      <nav className="primary-nav" aria-label="Main navigation">
        <a href={`${base}/anime`} aria-current={view === 'anime' || view === 'anime-id' ? 'page' : undefined}>Anime</a>
        <a href={`${base}/manga`} aria-current={view === 'manga' ? 'page' : undefined}>Manga</a>
        <a href={`${base}/products`}>Products</a>
      </nav>
      <form className="header-search" role="search" onSubmit={(event) => event.preventDefault()}>
        <svg aria-hidden="true" width="17" height="17" viewBox="0 0 24 24" fill="none">
          <circle cx="10.8" cy="10.8" r="6.8" stroke="currentColor" strokeWidth="1.8" />
          <path d="m16 16 4.2 4.2" stroke="currentColor" strokeWidth="1.8" strokeLinecap="round" />
        </svg>
        <input type="search" placeholder="Search your collection" aria-label="Search your collection" />
      </form>
      <a className="login-link" href="#login">Log in</a>
    </header>
  )
}

function Catalog({
  resource,
  apiBase,
  routeBase,
  page,
}: {
  resource: Resource
  apiBase: string
  routeBase: string
  page: number
}) {
  const [records, setRecords] = useState<RecordData[]>([])
  const [loading, setLoading] = useState(true)
  const [error, setError] = useState('')
  const [reloadKey, setReloadKey] = useState(0)

  useEffect(() => {
    let active = true
    setLoading(true)
    setError('')
    apiRequest<RecordData[]>(`${apiBase}/${resource}?page=${page}`)
      .then((data) => {
        if (active) setRecords(Array.isArray(data) ? data : [])
      })
      .catch((requestError: unknown) => {
        if (active) setError(requestError instanceof Error ? requestError.message : 'Could not load records.')
      })
      .finally(() => {
        if (active) setLoading(false)
      })
    return () => { active = false }
  }, [apiBase, page, reloadKey, resource])

  function pageHref(nextPage: number) {
    return `${routeBase}/${resource}?page=${nextPage}`
  }

  const title = resource === 'anime' ? 'Anime' : 'Manga'

  return (
    <main className="page-shell">
      <div className="page-heading">
        <div>
          <span className="eyebrow">Your collection</span>
          <h1>{title} <span>favorites.</span></h1>
        </div>
        <p className="page-number">Page {page}</p>
      </div>

      <div className="list-heading">
        <h2>{title} collection</h2>
        <button className="quiet-button" type="button" onClick={() => setReloadKey((key) => key + 1)}>Refresh</button>
      </div>

      {error && <p className="message error-message" role="alert">{error}</p>}
      {loading && <p className="message">Loading {title.toLowerCase()}…</p>}
      {!loading && !error && records.length === 0 && <p className="message">No records found on this page.</p>}

      <section className="record-grid" aria-label={`${title} records`}>
        {records.map((record) => {
          const image = typeof record.image === 'string' && /^https?:\/\//i.test(record.image) ? record.image : ''
          return (
            <article className="anime-card" key={record.id}>
              {resource === 'anime' && (
                <a className="record-link" href={`${routeBase}/anime-id?id=${record.id}&page=${page}`} aria-label={`View details for ${record.title ?? 'anime'}`}>
                  <div className="poster">
                    {image ? <img src={image} alt={record.title ? `${record.title} poster` : 'Anime poster'} loading="lazy" /> : (record.title ?? 'Anime')}
                  </div>
                  <div className="card-copy">
                    <h3>{record.title ?? 'Untitled anime'}</h3>
                    <p>{[record.showtype, record.episodecount ? `${record.episodecount} episodes` : null, record.status].filter(Boolean).join(' · ')}</p>
                  </div>
                </a>
              )}
              {resource === 'manga' && (
                <div className="card-copy manga-copy">
                  <h3>{record.title ?? 'Untitled manga'}</h3>
                  <p>{[record.author, record.year_published, record.status].filter(Boolean).join(' · ')}</p>
                </div>
              )}
            </article>
          )
        })}
      </section>

      <nav className="pagination" aria-label={`${title} pages`}>
        <a className={page <= 1 ? 'disabled' : ''} aria-disabled={page <= 1} href={page > 1 ? pageHref(page - 1) : undefined}>Previous</a>
        <span>Page {page}</span>
        <a className={records.length < 20 || loading ? 'disabled' : ''} aria-disabled={records.length < 20 || loading} href={records.length >= 20 && !loading ? pageHref(page + 1) : undefined}>Next</a>
      </nav>
    </main>
  )
}

function AnimeDetail({ apiBase, routeBase, recordId, page }: Omit<AppProps, 'view'>) {
  const [record, setRecord] = useState<RecordData | null>(null)
  const [error, setError] = useState('')
  const [loading, setLoading] = useState(true)

  useEffect(() => {
    if (!recordId) {
      setError('No valid anime ID was provided.')
      setLoading(false)
      return
    }
    apiRequest<RecordData>(`${apiBase}/anime/${recordId}`)
      .then(setRecord)
      .catch((requestError: unknown) => setError(requestError instanceof Error ? requestError.message : 'Could not load anime details.'))
      .finally(() => setLoading(false))
  }, [apiBase, recordId])

  const hiddenFields = new Set(['id', 'anime_id', 'slug', 'image', 'kitsu_url', 'title', 'synopsis'])
  const image = record && typeof record.image === 'string' && /^https?:\/\//i.test(record.image) ? record.image : ''

  return (
    <main className="page-shell">
      <a className="back-link" href={`${routeBase}/anime?page=${page}`}>← Back to anime</a>
      {loading && <p className="message">Loading anime…</p>}
      {error && <p className="message error-message" role="alert">{error}</p>}
      {record && (
        <>
          <span className="eyebrow">Anime details</span>
          <h1>{record.title ?? 'Untitled'} <span>details.</span></h1>
          <article className="detail-panel">
            <div className="detail-poster">
              {image ? <img src={image} alt={record.title ? `${record.title} poster` : 'Anime poster'} /> : (record.title ?? 'Anime')}
            </div>
            <div className="detail-copy">
              {record.synopsis && <p className="synopsis">{record.synopsis}</p>}
              <dl className="details-list">
                {Object.entries(record).filter(([key]) => !hiddenFields.has(key)).map(([key, value]) => (
                  <div className="detail-item" key={key}>
                    <dt>{key.replaceAll('_', ' ')}</dt>
                    <dd>{value === null || value === '' ? '—' : String(value)}</dd>
                  </div>
                ))}
              </dl>
            </div>
          </article>
        </>
      )}
    </main>
  )
}

export default function App(props: AppProps) {
  return (
    <>
      <SiteHeader routeBase={props.routeBase} view={props.view} />
      {props.view === 'anime-id' ? (
        <AnimeDetail {...props} />
      ) : props.view === 'products' ? (
        <main className="page-shell">
          <span className="eyebrow">The shop</span>
          <h1>Products <span>coming soon.</span></h1>
          <p className="message">Product listings will appear here when that collection is ready.</p>
        </main>
      ) : (
        <Catalog resource={props.view === 'manga' ? 'manga' : 'anime'} apiBase={props.apiBase} routeBase={props.routeBase} page={props.page} />
      )}
    </>
  )
}
