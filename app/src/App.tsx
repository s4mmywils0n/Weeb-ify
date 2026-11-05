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
  const [showType, setShowType] = useState(() => new URLSearchParams(window.location.search).get('showtype') ?? '')
  const [ageRating, setAgeRating] = useState(() => new URLSearchParams(window.location.search).get('agerating') ?? '')
  const [sort, setSort] = useState(() => new URLSearchParams(window.location.search).get('sort') ?? '')
  const [activePage, setActivePage] = useState(page)
  const [pageLimit, setPageLimit] = useState(() => {
    const requestedLimit = Number(new URLSearchParams(window.location.search).get('limit') ?? 20)
    return Number.isInteger(requestedLimit) && requestedLimit >= 1 && requestedLimit <= 100 ? requestedLimit : 20
  })

  const animeParams = new URLSearchParams()
  animeParams.set('page', String(activePage))
  animeParams.set('limit', String(pageLimit))
  if (resource === 'anime') {
    if (showType) animeParams.set('showtype', showType)
    if (ageRating) animeParams.set('agerating', ageRating)
    if (sort) animeParams.set('sort', sort)
  }

  useEffect(() => {
    let active = true
    setLoading(true)
    setError('')
    apiRequest<RecordData[]>(`${apiBase}/${resource}?${animeParams.toString()}`)
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
  }, [apiBase, activePage, reloadKey, resource, showType, ageRating, sort, pageLimit])

  function pageHref(nextPage: number) {
    const params = new URLSearchParams(animeParams)
    params.set('page', String(nextPage))
    return `${routeBase}/${resource}?${params.toString()}`
  }

  function changeFilter(update: () => void) {
    update()
    setActivePage(1)
  }

  function animeDetailHref(recordId: number) {
    const params = new URLSearchParams(animeParams)
    params.set('id', String(recordId))
    return `${routeBase}/anime-id?${params.toString()}`
  }

  const title = resource === 'anime' ? 'Anime' : 'Manga'

  return (
    <main className="page-shell">
      <div className="page-heading">
        <div>
          <span className="eyebrow">Your collection</span>
          <h1>{title} <span>favorites.</span></h1>
        </div>
        <p className="page-number">Page {activePage}</p>
      </div>

      <div className="list-heading">
        <h2>{title} collection</h2>
        <button className="quiet-button" type="button" onClick={() => setReloadKey((key) => key + 1)}>Refresh</button>
      </div>

      <div className="catalog-filters" aria-label="Catalog options">
        {resource === 'anime' && (
          <>
          <label className="filter-field">
            <span>Show type</span>
            <select value={showType} onChange={(event) => changeFilter(() => setShowType(event.target.value))}>
              <option value="">All show types</option>
              <option value="TV">TV</option>
              <option value="movie">Movie</option>
              <option value="OVA">OVA</option>
              <option value="ONA">ONA</option>
              <option value="special">Special</option>
              <option value="music">Music</option>
            </select>
          </label>
          <label className="filter-field">
            <span>Age rating</span>
            <select value={ageRating} onChange={(event) => changeFilter(() => setAgeRating(event.target.value))}>
              <option value="">All age ratings</option>
              <option value="G">G</option>
              <option value="PG">PG</option>
              <option value="R">R</option>
              <option value="R18">R18</option>
              <option value="R+">R+</option>
            </select>
          </label>
          <label className="filter-field sort-field">
            <span>Sort by</span>
            <select value={sort} onChange={(event) => changeFilter(() => setSort(event.target.value))}>
              <option value="">Default order</option>
              <option value="title_asc">Title (A–Z)</option>
              <option value="title_desc">Title (Z–A)</option>
              <option value="createdat_asc">Created date (oldest first)</option>
              <option value="createdat_desc">Created date (newest first)</option>
              <option value="popularityrank_asc">Popularity rank (most popular)</option>
              <option value="popularityrank_desc">Popularity rank (least popular)</option>
            </select>
          </label>
          {(showType || ageRating || sort) && (
            <button className="quiet-button clear-filters" type="button" onClick={() => { setShowType(''); setAgeRating(''); setSort(''); setActivePage(1) }}>
              Clear filters
            </button>
          )}
          </>
        )}
        <label className="filter-field">
          <span>Results per page</span>
          <select value={pageLimit} onChange={(event) => changeFilter(() => setPageLimit(Number(event.target.value)))}>
            <option value={10}>10</option>
            <option value={20}>20</option>
            <option value={50}>50</option>
            <option value={100}>100</option>
          </select>
        </label>
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
                <a className="record-link" href={animeDetailHref(record.id)} aria-label={`View details for ${record.title ?? 'anime'}`}>
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
        <a className={activePage <= 1 ? 'disabled' : ''} aria-disabled={activePage <= 1} href={activePage > 1 ? pageHref(activePage - 1) : undefined}>Previous</a>
        <span>Page {activePage}</span>
        <a className={records.length < pageLimit || loading ? 'disabled' : ''} aria-disabled={records.length < pageLimit || loading} href={records.length >= pageLimit && !loading ? pageHref(activePage + 1) : undefined}>Next</a>
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
  const returnParams = new URLSearchParams(window.location.search)
  returnParams.delete('id')
  returnParams.set('page', String(page))

  return (
    <main className="page-shell">
      <a className="back-link" href={`${routeBase}/anime?${returnParams.toString()}`}>← Back to anime</a>
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
