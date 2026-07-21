(function () {
    var REFRESH_INTERVAL_MS = 60000; // recarrega o JSON a cada 60s (novas imagens/horários sem precisar dar F5)
    var RETRY_EMPTY_MS = 5000; // quando não há slide ativo agora, tenta de novo em breve (janela de horário pode abrir)

    function byId(id) {
        return document.getElementById(id);
    }

    function getPlaylistSlug() {
        var params = new URLSearchParams(window.location.search);
        var slug = params.get('playlist');
        return slug && /^[a-z0-9-]+$/.test(slug) ? slug : 'default';
    }

    function fetchPayload(done) {
        var xhr = new XMLHttpRequest();
        xhr.open('GET', 'data/carousel.json?t=' + new Date().getTime(), true);
        xhr.onreadystatechange = function () {
            if (xhr.readyState !== 4) {
                return;
            }

            if (xhr.status >= 200 && xhr.status < 300) {
                try {
                    done(JSON.parse(xhr.responseText || '{}'));
                    return;
                } catch (err) {
                    done(null);
                    return;
                }
            }

            done(null);
        };
        xhr.send(null);
    }

    function toMinutes(hhmm) {
        var parts = String(hhmm).split(':');
        return (parseInt(parts[0], 10) * 60) + parseInt(parts[1], 10);
    }

    function isSlideActiveNow(slide) {
        if (!slide.hora_inicio && !slide.hora_fim) {
            return true;
        }

        var now = new Date();
        var nowMinutes = (now.getHours() * 60) + now.getMinutes();
        var start = slide.hora_inicio ? toMinutes(slide.hora_inicio) : 0;
        var end = slide.hora_fim ? toMinutes(slide.hora_fim) : (24 * 60) - 1;

        if (start <= end) {
            return nowMinutes >= start && nowMinutes <= end;
        }

        // Janela que atravessa a meia-noite (ex: 22:00–06:00)
        return nowMinutes >= start || nowMinutes <= end;
    }

    function preload(src, done) {
        var img = new Image();
        img.onload = function () {
            done(true);
        };
        img.onerror = function () {
            done(false);
        };
        img.src = src;
    }

    function initCarousel() {
        var slug = getPlaylistSlug();
        var image = byId('carouselImage');
        var empty = byId('emptyState');
        var index = 0;
        var current = { duration: 8, slides: [] };

        function showPlaceholder() {
            if (image) {
                image.className = 'carousel-image';
                image.removeAttribute('src');
            }
            if (empty) {
                empty.className = 'tv-empty-state';
            }
        }

        function showSlide(slide) {
            if (!slide || !image) {
                showPlaceholder();
                return;
            }

            preload(slide.src, function (ok) {
                if (!ok) {
                    showPlaceholder();
                    return;
                }

                image.className = 'carousel-image';
                image.src = slide.src;
                image.alt = slide.title || 'Imagem do carrossel';

                if (empty) {
                    empty.className = 'tv-empty-state hidden';
                }

                window.setTimeout(function () {
                    image.className = 'carousel-image is-visible';
                }, 40);
            });
        }

        function activeSlides() {
            return (current.slides || []).filter(isSlideActiveNow);
        }

        function tick() {
            var slides = activeSlides();

            if (!slides.length) {
                showPlaceholder();
                window.setTimeout(tick, RETRY_EMPTY_MS);
                return;
            }

            index = index % slides.length;
            showSlide(slides[index]);
            index = (index + 1) % slides.length;

            var durationMs = Math.max(1, parseInt(current.duration, 10) || 8) * 1000;
            window.setTimeout(tick, durationMs);
        }

        function applyPayload(payload) {
            if (!payload || !payload.playlists) {
                return;
            }

            var playlist = payload.playlists[slug] || payload.playlists['default'] || { duration: 8, slides: [] };

            current.duration = playlist.duration || 8;
            current.slides = (playlist.slides || []).map(function (slide) {
                return {
                    src: slide.file ? ('uploads/' + encodeURIComponent(slide.file)) : '',
                    title: slide.original_name || slide.file || '',
                    hora_inicio: slide.hora_inicio || null,
                    hora_fim: slide.hora_fim || null
                };
            }).filter(function (slide) {
                return !!slide.src;
            });
        }

        function refresh(done) {
            fetchPayload(function (payload) {
                applyPayload(payload);
                if (typeof done === 'function') {
                    done();
                }
            });
        }

        refresh(function () {
            tick();
            window.setInterval(function () {
                refresh(function () {});
            }, REFRESH_INTERVAL_MS);
        });
    }

    if (document.readyState === 'loading') {
        document.addEventListener('DOMContentLoaded', initCarousel);
    } else {
        initCarousel();
    }
})();
