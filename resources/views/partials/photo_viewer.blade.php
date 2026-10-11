{{-- Full-size photo viewer.

     A gallery crops its photos to fit its tiles, so this is where a guest
     sees a photo whole, and the only way to reach the photos a gallery has
     no tile for. Include it once with the photos in display order:

         ['photos' => [['src' => $url, 'alt' => 'Villa Elena'], …]]

     Any element with `data-photo-index="N"` opens it on photo N. Make that
     element a <button>.

     A <dialog> with showModal(), like partials/confirm_dialog: it sits in
     the browser's top layer above the fixed nav and the chat button, and
     Esc and the focus trap come with it. A small photo is shown at its own
     size, never enlarged to fill the screen. --}}
<dialog class="photo-viewer" id="photoViewer" aria-label="Photos">
    <div class="pv-bar">
        <span class="pv-count" id="pvCount" aria-live="polite"></span>
        <button type="button" class="pv-btn" id="pvClose" aria-label="Close photos">
            <i class="bi bi-x-lg" aria-hidden="true"></i>
        </button>
    </div>
    <div class="pv-stage" id="pvStage">
        <img class="pv-img" id="pvImg" alt="">
        <button type="button" class="pv-btn pv-nav pv-prev" id="pvPrev" aria-label="Previous photo">
            <i class="bi bi-chevron-left" aria-hidden="true"></i>
        </button>
        <button type="button" class="pv-btn pv-nav pv-next" id="pvNext" aria-label="Next photo">
            <i class="bi bi-chevron-right" aria-hidden="true"></i>
        </button>
    </div>
</dialog>

<style>
    .photo-viewer {
        /* The section stylesheets reset margin and padding on everything;
           the size is stated in full so nothing is left to the browser's
           own dialog defaults. */
        width: 100vw;
        max-width: none;
        height: 100vh;
        height: 100dvh;
        max-height: none;
        margin: 0;
        padding: 0;
        border: 0;
        background: var(--stone);
        color: #fff;
        overflow: hidden;
        overscroll-behavior: contain;
    }

    .photo-viewer[open] {
        display: grid;
        grid-template-rows: auto minmax(0, 1fr);
    }

    .photo-viewer::backdrop {
        background: var(--stone);
    }

    .pv-bar {
        display: flex;
        align-items: center;
        justify-content: space-between;
        gap: 12px;
        padding: 12px 16px;
        font-family: var(--font-body);
        font-size: 14px;
    }

    .pv-count {
        color: rgba(255, 255, 255, .75);
        font-variant-numeric: tabular-nums;
    }

    /* One grid cell with a floor of zero, so the photo's `max-*: 100%`
       measures the space left on screen and not the photo itself. */
    .pv-stage {
        position: relative;
        display: grid;
        grid-template: minmax(0, 1fr) / minmax(0, 1fr);
        place-items: center;
        min-height: 0;
        padding: 0 16px 20px;
    }

    /* Auto size with two maximums: shrinks to fit, keeps its shape, and is
       never enlarged past its own pixels. The box is the photo itself, so a
       click beside it lands on the stage. */
    .pv-img {
        display: block;
        width: auto;
        height: auto;
        max-width: 100%;
        max-height: 100%;
        user-select: none;
    }

    .pv-btn {
        width: 44px;
        height: 44px;
        flex: none;
        display: inline-flex;
        align-items: center;
        justify-content: center;
        border: 1px solid rgba(255, 255, 255, .25);
        border-radius: 50%;
        background: rgba(44, 36, 22, .7);
        color: #fff;
        font-size: 18px;
        cursor: pointer;
        transition: background .15s, border-color .15s;
    }

    .pv-btn:hover {
        background: rgba(255, 255, 255, .16);
        border-color: rgba(255, 255, 255, .5);
    }

    .pv-btn:focus-visible {
        outline: 2px solid var(--gold-light);
        outline-offset: 2px;
    }

    .pv-nav {
        position: absolute;
        top: 50%;
        transform: translateY(-50%);
    }

    .pv-prev { left: 16px; }
    .pv-next { right: 16px; }

    .pv-nav[hidden] { display: none; }

    @media (max-width: 480px) {
        .pv-stage { padding: 0 0 16px; }
        .pv-prev { left: 8px; }
        .pv-next { right: 8px; }
    }
</style>

<script>
    (function() {
        const photos = @json($photos);
        const dialog = document.getElementById('photoViewer');
        if (!dialog || !photos.length || typeof dialog.showModal !== 'function') return;

        const img = document.getElementById('pvImg');
        const count = document.getElementById('pvCount');
        const stage = document.getElementById('pvStage');
        const prev = document.getElementById('pvPrev');
        const next = document.getElementById('pvNext');
        const many = photos.length > 1;
        let at = 0;

        prev.hidden = next.hidden = !many;

        function show(index) {
            at = (index + photos.length) % photos.length;
            img.src = photos[at].src;
            img.alt = photos[at].alt;
            count.textContent = many ? 'Photo ' + (at + 1) + ' of ' + photos.length : '';
            // The next one is fetched now so stepping forward does not wait.
            if (many) new Image().src = photos[(at + 1) % photos.length].src;
        }

        document.addEventListener('click', function(e) {
            const opener = e.target.closest('[data-photo-index]');
            if (!opener) return;
            show(Number(opener.dataset.photoIndex) || 0);
            dialog.showModal();
            // The page behind must not scroll under a wheel or a swipe.
            document.documentElement.style.overflow = 'hidden';
        });

        dialog.addEventListener('close', function() {
            document.documentElement.style.overflow = '';
        });

        document.getElementById('pvClose').addEventListener('click', () => dialog.close());
        prev.addEventListener('click', () => show(at - 1));
        next.addEventListener('click', () => show(at + 1));

        // A click on the empty area around the photo closes, as a guest
        // expects of a backdrop. The photo and the buttons do not.
        stage.addEventListener('click', function(e) {
            if (e.target === stage) dialog.close();
        });

        dialog.addEventListener('keydown', function(e) {
            if (!many) return;
            if (e.key === 'ArrowLeft') show(at - 1);
            if (e.key === 'ArrowRight') show(at + 1);
        });

        // Swipe on a phone. Only a mostly-horizontal drag counts, so a
        // vertical scroll gesture is not read as "next photo".
        let startX = null;
        let startY = null;
        stage.addEventListener('touchstart', function(e) {
            startX = e.changedTouches[0].clientX;
            startY = e.changedTouches[0].clientY;
        }, { passive: true });
        stage.addEventListener('touchend', function(e) {
            if (startX === null || !many) return;
            const dx = e.changedTouches[0].clientX - startX;
            const dy = e.changedTouches[0].clientY - startY;
            startX = null;
            if (Math.abs(dx) < 50 || Math.abs(dx) < Math.abs(dy) * 1.5) return;
            show(dx < 0 ? at + 1 : at - 1);
        }, { passive: true });
    })();
</script>
