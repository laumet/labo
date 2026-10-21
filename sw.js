const staticAssets = [
    './app.js'
]

self.addEventListener('install', async e => {
    const cache = await caches.open('game-static')
    cache.addAll(staticAssets)
    console.log('install')
})

self.addEventListener('fetch', e => {
    console.log('fetch');
})
