/* Offline shell is explicitly limited to the OnGround experience.
   Never cache authenticated API, registration or checkout responses. */
const CACHE = 'digisangam-offline-v4'
const scopePath = new URL(self.registration.scope).pathname.replace(/\/$/, '')
const appPath = path => scopePath + (path.startsWith('/') ? path : '/' + path)
const OFFLINE_ROUTES = [appPath('/onground')]
const isAsset = path => path.startsWith(appPath('/assets/')) || path.startsWith(appPath('/icons/'))
const isShell = path => OFFLINE_ROUTES.includes(path) || path === appPath('/') || path === appPath('/index.html')
const cacheable = response => response.ok && response.type === 'basic' && !response.headers.has('Set-Cookie')

self.addEventListener('install', event => {
  event.waitUntil(caches.open(CACHE).then(cache => cache.addAll([appPath('/onground'), appPath('/manifest.webmanifest')])).catch(() => {}))
  self.skipWaiting()
})
self.addEventListener('activate', event => {
  event.waitUntil(caches.keys().then(keys => Promise.all(keys.filter(k => k.startsWith('digisangam-') && k !== CACHE).map(k => caches.delete(k)))))
  self.clients.claim()
})
self.addEventListener('fetch', event => {
  const request = event.request
  if (request.method !== 'GET' || request.headers.has('authorization')) return
  const url = new URL(request.url)
  if (url.origin !== self.location.origin) return
  if (url.pathname.startsWith(appPath('/api/'))) return
  if (!(isAsset(url.pathname) || isShell(url.pathname) || url.pathname === appPath('/manifest.webmanifest'))) return
  event.respondWith((async () => {
    const cache = await caches.open(CACHE)
    if (isAsset(url.pathname)) {
      const cached = await cache.match(request)
      if (cached) return cached
      const response = await fetch(request)
      if (cacheable(response)) await cache.put(request, response.clone())
      return response
    }
    try {
      const response = await fetch(request)
      if (cacheable(response) && (OFFLINE_ROUTES.includes(url.pathname) || url.pathname === appPath('/manifest.webmanifest'))) {
        await cache.put(request, response.clone())
      }
      return response
    } catch (error) {
      const cached = await cache.match(request)
      if (cached) return cached
      if (OFFLINE_ROUTES.includes(url.pathname)) {
        const shell = await cache.match(appPath('/onground'))
        if (shell) return shell
      }
      throw error
    }
  })())
})
