/*
 * 랜딩·소개 페이지의 스크롤 인터랙션.
 *
 *   data-reveal            화면에 들어오면 나타난다 ("left" | "right" | "zoom" 지원)
 *   data-reveal-delay="80" 등장 지연(ms)
 *   data-reveal-stagger    자식들을 순서대로 등장시킨다 (간격 ms, 기본 80)
 *   data-count-to="45499"  화면에 들어오면 0에서 그 값까지 세어 올린다
 *   data-count-suffix      숫자 뒤에 붙일 문자
 *   data-count-decimals    소수 자릿수
 *   data-parallax="0.12"   스크롤에 따라 살짝 어긋나게 움직인다
 *
 * 모션을 줄이도록 설정한 사용자에게는 최종 상태만 즉시 보여 준다.
 */

const reduceMotion = window.matchMedia('(prefers-reduced-motion: reduce)').matches;

/* ── 스크롤 등장 ───────────────────────────────────────────────────────── */
function setupReveal() {
    const targets = document.querySelectorAll('[data-reveal], [data-reveal-stagger]');

    if (!targets.length) return;

    // 묶음 등장: 자식마다 지연을 계산해 붙여 둔다.
    document.querySelectorAll('[data-reveal-stagger]').forEach((group) => {
        const step = Number(group.dataset.revealStagger) || 80;

        Array.from(group.children).forEach((child, index) => {
            if (!child.hasAttribute('data-reveal')) child.setAttribute('data-reveal', '');
            child.style.setProperty('--reveal-delay', `${index * step}ms`);
        });
    });

    document.querySelectorAll('[data-reveal-delay]').forEach((el) => {
        el.style.setProperty('--reveal-delay', `${el.dataset.revealDelay}ms`);
    });

    const items = document.querySelectorAll('[data-reveal]');

    if (reduceMotion || !('IntersectionObserver' in window)) {
        items.forEach((el) => el.classList.add('is-in'));
        return;
    }

    const observer = new IntersectionObserver(
        (entries) => {
            entries.forEach((entry) => {
                if (!entry.isIntersecting) return;
                entry.target.classList.add('is-in');
                observer.unobserve(entry.target);
            });
        },
        { rootMargin: '0px 0px -12% 0px', threshold: 0.12 },
    );

    items.forEach((el) => observer.observe(el));
}

/* ── 숫자 카운트업 ─────────────────────────────────────────────────────── */
function countUp(el) {
    const target = Number(String(el.dataset.countTo).replace(/,/g, ''));
    const decimals = Number(el.dataset.countDecimals) || 0;
    const suffix = el.dataset.countSuffix || '';
    const format = (value) =>
        value.toLocaleString('ko-KR', {
            minimumFractionDigits: decimals,
            maximumFractionDigits: decimals,
        }) + suffix;

    if (reduceMotion || !Number.isFinite(target)) {
        el.textContent = format(target || 0);
        return;
    }

    const duration = 1400;
    const start = performance.now();

    const tick = (now) => {
        const progress = Math.min(1, (now - start) / duration);
        // easeOutExpo — 빠르게 올라갔다가 부드럽게 멈춘다.
        const eased = progress === 1 ? 1 : 1 - Math.pow(2, -10 * progress);

        el.textContent = format(target * eased);

        if (progress < 1) requestAnimationFrame(tick);
    };

    requestAnimationFrame(tick);
}

function setupCounters() {
    const counters = document.querySelectorAll('[data-count-to]');

    if (!counters.length) return;

    if (!('IntersectionObserver' in window)) {
        counters.forEach(countUp);
        return;
    }

    const observer = new IntersectionObserver(
        (entries) => {
            entries.forEach((entry) => {
                if (!entry.isIntersecting) return;
                countUp(entry.target);
                observer.unobserve(entry.target);
            });
        },
        { threshold: 0.5 },
    );

    counters.forEach((el) => observer.observe(el));
}

/* ── 가벼운 패럴랙스 ───────────────────────────────────────────────────── */
function setupParallax() {
    const layers = document.querySelectorAll('[data-parallax]');

    if (!layers.length || reduceMotion) return;

    let ticking = false;

    const update = () => {
        const viewport = window.innerHeight;

        layers.forEach((el) => {
            const rect = el.getBoundingClientRect();

            if (rect.bottom < -200 || rect.top > viewport + 200) return;

            const depth = Number(el.dataset.parallax) || 0.1;
            const offset = (rect.top + rect.height / 2 - viewport / 2) * depth;

            el.style.transform = `translate3d(0, ${offset.toFixed(1)}px, 0)`;
        });

        ticking = false;
    };

    window.addEventListener(
        'scroll',
        () => {
            if (ticking) return;
            ticking = true;
            requestAnimationFrame(update);
        },
        { passive: true },
    );

    update();
}

function init() {
    setupReveal();
    setupCounters();
    setupParallax();
}

if (document.readyState === 'loading') {
    document.addEventListener('DOMContentLoaded', init);
} else {
    init();
}
