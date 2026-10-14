/*
 * Service worker minimal de « Les reco de la cantine ».
 * - Met en cache UNIQUEMENT les fichiers statiques (CSS, JS, icônes, page hors connexion).
 * - Ne met JAMAIS en cache les pages HTML ni les réponses AJAX : ce sont des données privées
 *   (risque d'afficher les données d'un autre compte sur un appareil partagé).
 * - Sans réseau, une navigation affiche offline.html.
 * Le nom du cache dépend de la version du site (paramètre ?v= de l'URL d'enregistrement).
 */
'use strict';

var VERSION = new URL(self.location.href).searchParams.get('v') || '0';
var CACHE = 'cantine-static-' + VERSION;
var scope = self.registration.scope;
var OFFLINE_URL = new URL('offline.html', scope).toString();
var PRECACHE = [
    OFFLINE_URL,
    new URL('assets/css/app.css', scope).toString(), // utilisé par offline.html
    new URL('assets/css/app.css?v=' + VERSION, scope).toString(),
    new URL('assets/js/app.js?v=' + VERSION, scope).toString(),
    new URL('assets/img/favicon.svg?v=' + VERSION, scope).toString(),
    new URL('assets/img/icon-192.png', scope).toString()
];

self.addEventListener('install', function (event) {
    event.waitUntil(
        caches.open(CACHE).then(function (cache) { return cache.addAll(PRECACHE); }).then(function () { return self.skipWaiting(); })
    );
});

self.addEventListener('activate', function (event) {
    event.waitUntil(
        caches.keys().then(function (keys) {
            return Promise.all(keys.filter(function (key) {
                return key.indexOf('cantine-static-') === 0 && key !== CACHE;
            }).map(function (key) { return caches.delete(key); }));
        }).then(function () { return self.clients.claim(); })
    );
});

self.addEventListener('fetch', function (event) {
    var request = event.request;
    if (request.method !== 'GET') {
        return;
    }
    var url = new URL(request.url);

    // Navigation (pages HTML) : toujours le réseau, page hors connexion en secours, jamais de cache.
    if (request.mode === 'navigate') {
        event.respondWith(fetch(request).catch(function () { return caches.match(OFFLINE_URL); }));
        return;
    }

    // Fichiers statiques du site : cache d'abord.
    if (url.origin === self.location.origin && url.pathname.indexOf('/assets/') !== -1) {
        event.respondWith(
            caches.match(request).then(function (cached) {
                return cached || fetch(request).then(function (response) {
                    if (response.ok) {
                        var copy = response.clone();
                        caches.open(CACHE).then(function (cache) { cache.put(request, copy); });
                    }
                    return response;
                });
            })
        );
    }
    // Tout le reste (données, API, images externes) passe directement par le réseau.
});
