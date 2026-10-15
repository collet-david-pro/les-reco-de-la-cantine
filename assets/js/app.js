/*
 * Les reco de la cantine — JavaScript (amélioration progressive).
 * Tout fonctionne sans JavaScript ; ce fichier rend simplement l'usage plus agréable :
 * recherche en direct, statut et note sans rechargement, confirmations, compteurs...
 * Aucun script en ligne dans les pages (politique de sécurité CSP).
 */
(function () {
    'use strict';

    document.documentElement.classList.add('js');

    var csrf = document.body ? document.body.getAttribute('data-csrf') : null;

    // --- Installation de l'appli (PWA) : on capte tôt la proposition d'installation du navigateur (Android/Chrome) ---
    var installPrompt = null;
    window.addEventListener('beforeinstallprompt', function (event) {
        event.preventDefault(); // on affichera notre propre bouton
        installPrompt = event;
    });

    function onReady(fn) {
        if (document.readyState !== 'loading') { fn(); } else { document.addEventListener('DOMContentLoaded', fn); }
    }

    /** Envoi d'un formulaire en AJAX ; réponse JSON attendue. */
    function postForm(form, extra) {
        var data = new FormData(form);
        Object.keys(extra || {}).forEach(function (key) { data.set(key, extra[key]); });
        return fetch(form.action, {
            method: 'POST',
            body: data,
            credentials: 'same-origin',
            headers: { 'Accept': 'application/json', 'X-CSRF-Token': csrf || data.get('_csrf') || '' }
        }).then(function (response) {
            return response.json().catch(function () { return { ok: false, error: 'Réponse inattendue du serveur.' }; });
        });
    }

    /** Petit message temporaire en bas de l'écran (en plus de la zone aria-live). */
    function toast(message, isError) {
        var box = document.createElement('div');
        box.className = 'flash ' + (isError ? 'flash-error' : 'flash-success') + ' toast';
        box.setAttribute('role', isError ? 'alert' : 'status');
        box.textContent = message;
        var container = document.querySelector('.flashes');
        if (!container) {
            container = document.createElement('div');
            container.className = 'flashes toast-container';
            document.body.appendChild(container);
        }
        container.appendChild(box);
        setTimeout(function () { box.remove(); }, 3500);
    }

    onReady(function () {
        // --- Images manquantes : image de remplacement locale ---
        document.addEventListener('error', function (event) {
            var img = event.target;
            if (img && img.tagName === 'IMG' && img.dataset.fallback && img.getAttribute('src') !== img.dataset.fallback) {
                img.src = img.dataset.fallback;
            }
        }, true);
        document.querySelectorAll('img[data-fallback]').forEach(function (img) {
            if (img.complete && img.naturalWidth === 0 && img.getAttribute('src') !== img.dataset.fallback) {
                img.src = img.dataset.fallback;
            }
        });

        // --- Messages flash : bouton fermer ---
        document.querySelectorAll('.flash-close').forEach(function (button) {
            button.hidden = false;
            button.addEventListener('click', function () { button.closest('.flash').remove(); });
        });

        // --- Confirmation avant les actions destructrices ---
        document.addEventListener('submit', function (event) {
            var form = event.target;
            if (form.matches('[data-confirm]') && !window.confirm(form.getAttribute('data-confirm'))) {
                event.preventDefault();
            }
        });

        // --- Filtres : envoi automatique au changement ---
        document.querySelectorAll('[data-autosubmit]').forEach(function (select) {
            select.addEventListener('change', function () { select.form.submit(); });
        });

        // --- Compteur de caractères des commentaires ---
        document.querySelectorAll('textarea[data-counter]').forEach(function (area) {
            var hint = area.parentNode.querySelector('[data-counter-text]');
            var max = parseInt(area.getAttribute('maxlength'), 10);
            if (!hint || !max) { return; }
            var update = function () { hint.textContent = area.value.length + ' / ' + max + ' caractères'; };
            area.addEventListener('input', update);
            if (area.value.length) { update(); }
        });

        // --- Recherche en direct (ajout d'une recommandation) ---
        var searchForm = document.querySelector('form[data-live-search]');
        if (searchForm) {
            var input = searchForm.querySelector('input[name="q"]');
            var results = document.querySelector('[data-search-results]');
            var manualLink = document.querySelector('[data-manual-link]');
            var type = searchForm.querySelector('input[name="type"]').value;
            var timer = null;
            var controller = null;
            var lastQuery = input.value.trim();

            var run = function () {
                var q = input.value.trim();
                var kindInput = searchForm.querySelector('input[name="kind"]:checked');
                var kind = kindInput ? kindInput.value : '';
                if (manualLink && type !== 'video') {
                    var manualUrl = new URL(manualLink.href, window.location.href);
                    manualUrl.searchParams.set('q', q);
                    manualLink.href = manualUrl.toString();
                }
                if (q.length < 2) { return; }
                if (controller) { controller.abort(); }
                controller = typeof AbortController !== 'undefined' ? new AbortController() : null;
                var url = new URL(searchForm.getAttribute('data-live-search'), window.location.href);
                url.searchParams.set('type', type);
                url.searchParams.set('q', q);
                if (kind) { url.searchParams.set('kind', kind); }
                results.classList.add('is-loading');
                fetch(url.toString(), { credentials: 'same-origin', signal: controller ? controller.signal : undefined })
                    .then(function (response) { return response.text(); })
                    .then(function (html) {
                        // Fragment produit par le serveur (valeurs déjà échappées).
                        results.innerHTML = html;
                        results.classList.remove('is-loading');
                        var pageUrl = new URL(window.location.href);
                        pageUrl.searchParams.set('q', q);
                        if (kind) { pageUrl.searchParams.set('kind', kind); }
                        history.replaceState(null, '', pageUrl.toString());
                    })
                    .catch(function (error) {
                        if (error && error.name === 'AbortError') { return; }
                        results.classList.remove('is-loading');
                    });
            };

            input.addEventListener('input', function () {
                var q = input.value.trim();
                if (q === lastQuery) { return; }
                lastQuery = q;
                clearTimeout(timer);
                timer = setTimeout(run, type === 'video' ? 150 : 400); // anti-rebond
            });
            searchForm.querySelectorAll('input[name="kind"]').forEach(function (radio) {
                radio.addEventListener('change', run);
            });
            searchForm.addEventListener('submit', function (event) {
                event.preventDefault();
                clearTimeout(timer);
                run();
            });
        }

        // --- Statut et note sur la fiche (AJAX avec mise à jour optimiste) ---
        var actions = document.querySelector('[data-media-actions]');
        if (actions) {
            var statusForm = actions.querySelector('[data-ajax-status]');
            var ratingForm = actions.querySelector('[data-ajax-rating]');
            var ratingHint = actions.querySelector('[data-rating-hint]');
            var live = actions.querySelector('[data-live]');
            var clearButton = ratingForm.querySelector('[data-clear-rating]');
            var avgBlock = document.querySelector('[data-avg-block]');

            var setPressed = function (status) {
                statusForm.querySelectorAll('button[name="status"]').forEach(function (button) {
                    button.setAttribute('aria-pressed', button.value === status ? 'true' : 'false');
                });
                ratingForm.hidden = status !== 'vu';
                ratingHint.hidden = status === 'vu';
            };

            var setRating = function (rating) {
                ratingForm.querySelectorAll('input[name="rating"]').forEach(function (radio) {
                    radio.checked = rating !== null && parseInt(radio.value, 10) === rating;
                });
                clearButton.hidden = rating === null;
            };

            var applyResult = function (result) {
                setPressed(result.status);
                setRating(result.rating);
                if (avgBlock) {
                    avgBlock.hidden = result.avg === null;
                    if (result.avg !== null) {
                        avgBlock.querySelector('[data-avg]').textContent = result.avg;
                        avgBlock.querySelector('[data-rating-count]').textContent = result.rating_count_text;
                    }
                }
                live.textContent = result.message;
                toast(result.message, false);
            };

            var currentStatus = function () {
                var pressed = statusForm.querySelector('button[aria-pressed="true"]');
                return pressed ? pressed.value : 'a_decouvrir';
            };

            statusForm.addEventListener('submit', function (event) {
                event.preventDefault();
                var button = event.submitter;
                if (!button || !button.value) { return; }
                var previous = currentStatus();
                setPressed(button.value); // optimiste
                postForm(statusForm, { status: button.value }).then(function (result) {
                    if (result.ok) { applyResult(result); } else { setPressed(previous); toast(result.error || 'Erreur', true); }
                }).catch(function () { setPressed(previous); toast('Connexion impossible. Réessayez.', true); });
            });

            var sendRating = function (value) {
                postForm(ratingForm, { rating: String(value) }).then(function (result) {
                    if (result.ok) { applyResult(result); } else { toast(result.error || 'Erreur', true); }
                }).catch(function () { toast('Connexion impossible. Réessayez.', true); });
            };

            ratingForm.querySelectorAll('input[name="rating"]').forEach(function (radio) {
                radio.addEventListener('change', function () {
                    clearButton.hidden = false;
                    sendRating(radio.value);
                });
            });
            ratingForm.addEventListener('submit', function (event) {
                event.preventDefault();
                if (event.submitter === clearButton) {
                    setRating(null);
                    sendRating(0);
                }
            });
        }

        // --- Installeur : champs MySQL visibles seulement si MySQL est choisi ---
        var mysqlFields = document.querySelector('[data-show-when="mysql"]');
        if (mysqlFields) {
            var toggle = function () {
                var checked = document.querySelector('input[name="driver"]:checked');
                mysqlFields.hidden = !checked || checked.value !== 'mysql';
            };
            document.querySelectorAll('input[name="driver"]').forEach(function (radio) { radio.addEventListener('change', toggle); });
            toggle();
        }

        // --- Sélection / copie facile ---
        document.querySelectorAll('[data-select-all]').forEach(function (area) {
            area.addEventListener('focus', function () { area.select(); });
        });
        document.querySelectorAll('[data-copy]').forEach(function (el) {
            el.setAttribute('tabindex', '0');
            el.setAttribute('title', 'Cliquer pour copier');
            el.addEventListener('click', function () {
                if (navigator.clipboard) {
                    navigator.clipboard.writeText(el.textContent).then(function () { toast('Copié !', false); });
                }
            });
        });

        // --- Bouton "Installer l'appli" (mobile uniquement, masqué si déjà installée) ---
        var installBox = document.querySelector('[data-install-app]');
        if (installBox) {
            var standalone = window.matchMedia('(display-mode: standalone)').matches || window.navigator.standalone === true;
            var mobile = window.matchMedia('(pointer: coarse)').matches;
            var ua = navigator.userAgent;
            var isIos = /iPad|iPhone|iPod/.test(ua) || (navigator.platform === 'MacIntel' && navigator.maxTouchPoints > 1);
            var dismissKey = 'cantine-install-dismissed';
            var dismissed = false;
            try {
                dismissed = installBox.hasAttribute('data-dismissible')
                    && Date.now() - parseInt(localStorage.getItem(dismissKey) || '0', 10) < 30 * 86400000;
            } catch (e) { /* stockage indisponible : on affiche */ }
            var dialog = document.querySelector('[data-install-dialog]');

            if (!standalone && mobile && !dismissed) {
                installBox.hidden = false;
            }
            installBox.querySelector('[data-install-button]').addEventListener('click', function () {
                if (installPrompt) {
                    // Android / Chrome : vraie fenêtre d'installation.
                    installPrompt.prompt();
                    installPrompt.userChoice.then(function (choice) {
                        if (choice && choice.outcome === 'accepted') { installBox.hidden = true; }
                        installPrompt = null;
                    });
                    return;
                }
                // iPhone ou navigateur sans installation automatique : explications.
                dialog.querySelector('[data-install-ios]').hidden = !isIos;
                dialog.querySelector('[data-install-other]').hidden = isIos;
                if (typeof dialog.showModal === 'function') { dialog.showModal(); } else { dialog.setAttribute('open', ''); }
            });
            var later = installBox.querySelector('[data-install-dismiss]');
            if (later) {
                later.addEventListener('click', function () {
                    installBox.hidden = true;
                    try { localStorage.setItem(dismissKey, String(Date.now())); } catch (e) { /* tant pis */ }
                });
            }
            window.addEventListener('appinstalled', function () {
                installBox.hidden = true;
                toast(installBox.getAttribute('data-done') || 'Appli installée !', false);
            });
        }

        // --- Application installable (PWA) ---
        var base = document.body.getAttribute('data-base');
        if ('serviceWorker' in navigator && base && !document.body.classList.contains('installer')) {
            var version = (document.querySelector('link[rel="stylesheet"]').getAttribute('href').split('v=')[1] || '');
            navigator.serviceWorker.register(base + 'sw.js?v=' + version, { scope: base }).catch(function () { /* facultatif */ });
        }
    });
})();
