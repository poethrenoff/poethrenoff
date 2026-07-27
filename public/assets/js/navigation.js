document.addEventListener('keydown', function (e) {
    const tag = document.activeElement?.tagName;
    if (tag === 'INPUT' || tag === 'TEXTAREA' || tag === 'SELECT' || (document.activeElement?.getAttribute('contenteditable') === 'true')) {
        return;
    }

    const nav = document.querySelector('.side-nav');
    if (!nav) return;

    if (e.key === 'ArrowLeft') {
        const link = nav.querySelector('.nav-prev');
        if (link) {
            e.preventDefault();
            link.click();
        }
    } else if (e.key === 'ArrowRight') {
        const link = nav.querySelector('.nav-next');
        if (link) {
            e.preventDefault();
            link.click();
        }
    }
});

/* Swipe Navigation for prev/next content */
(function () {
    let startX = 0;
    let startY = 0;
    const threshold = 50;

    function getNav() {
        return document.querySelector('.side-nav');
    }

    function onTouchStart(e) {
        const nav = getNav();
        if (!nav) return;

        const target = e.target;
        if (target.closest('a, button, textarea, input, [contenteditable="true"]')) {
            return;
        }

        startX = e.touches[0].clientX;
        startY = e.touches[0].clientY;
    }

    function onTouchEnd(e) {
        const nav = getNav();
        if (!nav || startX === 0) return;

        const dx = e.changedTouches[0].clientX - startX;
        const dy = e.changedTouches[0].clientY - startY;

        // Only trigger on horizontal swipe that's more horizontal than vertical
        if (Math.abs(dx) > Math.abs(dy) && Math.abs(dx) > threshold) {
            if (dx < 0) {
                const nextLink = nav.querySelector('.nav-next');
                if (nextLink) {
                    nextLink.click();
                }
            } else {
                const prevLink = nav.querySelector('.nav-prev');
                if (prevLink) {
                    prevLink.click();
                }
            }
        }
        startX = 0;
    }

    document.addEventListener('touchstart', onTouchStart, { passive: true });
    document.addEventListener('touchend', onTouchEnd);
})();
