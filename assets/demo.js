'use strict';

(() => {
    const texts = JSON.parse(document.body.dataset.texts || '{}');
    const t = text => texts[text] ?? text;
    const reduced = matchMedia('(prefers-reduced-motion: reduce)');
    const motionButton = document.querySelector('[data-motion]');
    const sceneButton = document.querySelector('[data-scenes]');
    const dialog = document.querySelector('.scene-dialog');
    const sceneOptions = [...document.querySelectorAll('[data-scene]')];
    let paused = reduced.matches;
    let selection = 'auto';
    let sceneRenderer = null;
    let liveScene = null;
    let disposePage = () => {};
    let pending = false;
    let stamp = null;
    try {
        paused = reduced.matches || sessionStorage.getItem('atmos-motion') === 'paused';
        const saved = sessionStorage.getItem('atmos-scene');
        if (sceneOptions.some(button => button.dataset.scene === saved)) selection = saved;
    } catch { /* Storage is optional, including in private browsing. */ }

    function setMotion(value) {
        paused = reduced.matches || value;
        if (motionButton) motionButton.disabled = reduced.matches;
        document.documentElement.classList.toggle('motion-paused', paused);
        motionButton?.setAttribute('aria-pressed', String(paused));
        motionButton?.setAttribute('aria-label', paused ? t('Resume animations') : t('Pause animations'));
        motionButton?.querySelector('path')?.setAttribute('d', paused ? 'm9 5 11 7-11 7Z' : 'M8 5v14M16 5v14');
        sceneRenderer?.resume();
    }
    motionButton?.addEventListener('click', () => {
        setMotion(!paused);
        try { sessionStorage.setItem('atmos-motion', paused ? 'paused' : 'running'); } catch {}
    });
    reduced.addEventListener('change', event => setMotion(event.matches));
    if (motionButton) motionButton.hidden = false;
    setMotion(paused);

    function setScene() {
        const hero = document.querySelector('.hero');
        if (!hero) return;
        const preview = selection !== 'auto';
        const [kind, phase] = preview ? selection.split('-') : [liveScene.kind, liveScene.phase];
        hero.dataset.weather = kind;
        hero.dataset.phase = phase;
        const phaseLabel = hero.querySelector('[data-phase-label]');
        if (phaseLabel) phaseLabel.textContent = {day: t('Day'), night: t('Night'), twilight: t('Twilight')}[phase];
        const tag = hero.querySelector('[data-scene-tag]');
        const option = sceneOptions.find(button => button.dataset.scene === selection);
        tag.textContent = preview ? t('Effect preview') : liveScene.source;
        tag.hidden = !tag.textContent;
        // Forecast/measurement provenance remains distinct from the effect preview.
        hero.querySelector('[data-condition]').textContent = preview ? option.querySelector('span').textContent : liveScene.label;
        const iconName = kind === 'clear' || kind === 'unknown' ? (phase === 'night' ? 'clear-night' : 'clear-day') : `${kind}-${phase}`;
        const iconSource = sceneOptions.find(button => button.dataset.scene === iconName)
            || sceneOptions.find(button => button.dataset.scene.startsWith(`${kind}-`));
        if (iconSource) hero.querySelector('.condition-icon').replaceChildren(iconSource.querySelector('svg').cloneNode(true));
        for (const button of sceneOptions) button.setAttribute('aria-pressed', String(button.dataset.scene === selection));
        sceneRenderer?.resume();
    }
    sceneButton?.addEventListener('click', () => dialog.showModal());
    document.querySelector('[data-close-scenes]')?.addEventListener('click', () => dialog.close());
    dialog?.addEventListener('click', event => {
        if (event.target !== dialog) return;
        const bounds = dialog.getBoundingClientRect();
        if (event.clientX < bounds.left || event.clientX > bounds.right || event.clientY < bounds.top || event.clientY > bounds.bottom) dialog.close();
    });
    for (const button of sceneOptions) button.addEventListener('click', () => {
        selection = button.dataset.scene;
        try { sessionStorage.setItem('atmos-scene', selection); } catch {}
        setScene();
        dialog.close();
    });

    class Atmosphere {
        constructor(hero) {
            this.hero = hero;
            this.canvas = hero.querySelector('canvas');
            this.ctx = this.canvas.getContext('2d');
            this.landscape = this.ctx && window.AtmosLandscape ? new window.AtmosLandscape(this.ctx) : null;
            if (this.landscape) hero.classList.add('landscape-ready');
            this.frame = 0;
            this.last = 0;
            this.time = 0;
            this.visible = true;
            this.particles = Array.from({length: 94}, () => ({x: Math.random(), y: Math.random(), speed: .25 + Math.random() * .8, size: .4 + Math.random() * 1.7, phase: Math.random() * Math.PI * 2}));
            this.resizeObserver = new ResizeObserver(() => this.resize());
            this.resizeObserver.observe(hero);
            this.visibilityObserver = new IntersectionObserver(entries => {
                this.visible = entries[0].isIntersecting;
                hero.classList.toggle('scene-offscreen', !this.visible);
                this.resume();
            });
            this.visibilityObserver.observe(hero);
            this.onVisibility = () => this.resume();
            document.addEventListener('visibilitychange', this.onVisibility);
        }
        resize() {
            this.width = this.hero.clientWidth;
            this.height = this.hero.clientHeight;
            const ratio = Math.min(devicePixelRatio || 1, 2);
            this.canvas.width = Math.round(this.width * ratio);
            this.canvas.height = Math.round(this.height * ratio);
            this.ctx?.setTransform(ratio, 0, 0, ratio, 0, 0);
            this.resume();
        }
        resume() {
            cancelAnimationFrame(this.frame);
            this.frame = 0;
            this.last = 0;
            if (!this.ctx || !this.width) return;
            if (this.landscape) {
                const month = Number(new Intl.DateTimeFormat('en', {month: 'numeric', timeZone: this.hero.dataset.zone}).format(new Date()));
                this.hero.dataset.season = window.AtmosLandscape.season(Number.parseFloat(this.hero.dataset.temperature), month, Number.parseFloat(this.hero.dataset.latitude));
            }
            this.draw(0);
            if (!paused && !reduced.matches && !document.hidden && this.visible) this.frame = requestAnimationFrame(time => this.tick(time));
        }
        tick(time) {
            if (this.last === 0) this.last = time;
            const delta = time - this.last;
            if (delta >= 32) {
                this.time += Math.min(delta, 60) / 1000;
                this.draw(Math.min(delta, 60) / 1000);
                this.last = time;
            }
            this.frame = requestAnimationFrame(next => this.tick(next));
        }
        draw(dt) {
            const ctx = this.ctx, w = this.width, h = this.height, t = this.time;
            const kind = this.hero.dataset.weather, phase = this.hero.dataset.phase;
            const rain = kind === 'rain' || kind === 'storm';
            const snow = kind === 'snow';
            const night = phase === 'night';
            const drift = .025 + Math.min(60, Number(this.hero.dataset.wind) || 0) * .002;
            ctx.clearRect(0, 0, w, h);
            const rgb = phase === 'twilight' ? '245,163,98' : night ? '173,203,213' : '255,232,186';
            this.landscape?.draw(w, h, t, kind, phase, Number.parseFloat(this.hero.dataset.temperature), this.hero.dataset.season);
            for (const p of this.particles) {
                if (rain) {
                    p.y = (p.y + dt * p.speed * 1.2) % 1;
                    p.x = (p.x + dt * drift) % 1;
                    const x = p.x * w, y = p.y * h;
                    ctx.strokeStyle = `rgba(180,212,226,${.09 + p.speed * .15})`;
                    ctx.lineWidth = .6 + p.size * .3;
                    ctx.beginPath(); ctx.moveTo(x, y); ctx.lineTo(x + 5, y + 12 + p.speed * 15); ctx.stroke();
                    if (p.y > .85) {
                        ctx.beginPath(); ctx.ellipse(x, h * .9 + p.phase * 3, 3 + p.y * 3, 1.4, 0, 0, Math.PI * 2);
                        ctx.strokeStyle = 'rgba(180,212,226,.08)'; ctx.stroke();
                    }
                } else if (snow) {
                    p.y = (p.y + dt * p.speed * .09) % 1;
                    const x = ((p.x + Math.sin(t * .4 + p.phase) * .025 + 1) % 1) * w;
                    ctx.beginPath(); ctx.arc(x, p.y * h, p.size, 0, Math.PI * 2);
                    ctx.fillStyle = `rgba(225,240,244,${.15 + p.speed * .45})`; ctx.fill();
                } else if (kind === 'fog' || kind === 'cloudy') {
                    if (p.phase > .45) continue;
                    const gradient = ctx.createRadialGradient(p.x * w + Math.sin(t * .15) * 25, p.y * h, 0, p.x * w, p.y * h, w * .4);
                    gradient.addColorStop(0, 'rgba(201,212,199,.035)'); gradient.addColorStop(1, 'rgba(201,212,199,0)');
                    ctx.fillStyle = gradient; ctx.fillRect(0, 0, w, h);
                } else {
                    if (!night && p.phase > 2.2) continue;
                    const x = p.x * w, y = (p.y * h * .44 + Math.sin(t * .12 + p.phase) * 4);
                    const alpha = night ? .16 + (Math.sin(t * .6 + p.phase) + 1) * .15 : .11;
                    ctx.fillStyle = `rgba(${rgb},${alpha * (kind === 'unknown' ? .5 : 1)})`;
                    ctx.beginPath(); ctx.arc(x, y, night ? p.size * .48 : p.size * .7, 0, Math.PI * 2); ctx.fill();
                }
            }
            if (kind === 'storm') {
                if (this.previousWeather !== kind || !this.bolt || t >= this.nextStrike) this.strike();
                this.drawLightning(paused || reduced.matches ? .22 : t - this.struckAt);
            }
            this.previousWeather = kind;
            if (kind === 'fog') this.drawFog(t);
        }
        fogTexture() {
            if (this.fogSheet) return this.fogSheet;
            const sheet = document.createElement('canvas');
            sheet.width = 384; sheet.height = 128;
            const context = sheet.getContext('2d');
            const pixels = context.createImageData(sheet.width, sheet.height);
            const hash = (x, y, period) => {
                const value = Math.sin(((x % period + period) % period) * 127.1 + y * 311.7) * 43758.5453;
                return value - Math.floor(value);
            };
            const noise = (x, y, period) => {
                const ix = Math.floor(x), iy = Math.floor(y);
                const sx = x - ix, sy = y - iy;
                const fx = sx * sx * (3 - 2 * sx), fy = sy * sy * (3 - 2 * sy);
                const a = hash(ix, iy, period), b = hash(ix + 1, iy, period);
                const c = hash(ix, iy + 1, period), d = hash(ix + 1, iy + 1, period);
                return (a + (b - a) * fx) * (1 - fy) + (c + (d - c) * fx) * fy;
            };
            for (let y = 0; y < sheet.height; y++) {
                for (let x = 0; x < sheet.width; x++) {
                    const nx = x / sheet.width, ny = y / sheet.height;
                    const billow = noise(nx * 4, ny * 3, 4) * .58
                        + noise(nx * 8, ny * 6, 8) * .28
                        + noise(nx * 16, ny * 12, 16) * .14;
                    const center = .49 + Math.sin(nx * Math.PI * 4) * .10 + (billow - .5) * .55;
                    const veil = Math.exp(-Math.pow((ny - center) / (.14 + billow * .17), 2) * 2);
                    const edge = Math.sin(ny * Math.PI) ** 2;
                    const alpha = Math.max(0, billow * 1.5 - .31) * veil * edge;
                    const i = (y * sheet.width + x) * 4;
                    pixels.data[i] = 205; pixels.data[i + 1] = 216; pixels.data[i + 2] = 212;
                    pixels.data[i + 3] = Math.round(alpha * 205);
                }
            }
            context.putImageData(pixels, 0, 0);
            this.fogSheet = sheet;
            return sheet;
        }
        drawFog(t) {
            const ctx = this.ctx, w = this.width, h = this.height;
            const sheet = this.fogTexture();
            ctx.save();
            // Seamless turbulent sheets: different speeds, heights and directions create parallax.
            for (let layer = 0; layer < 5; layer++) {
                const width = w * (1.1 + layer * .27);
                const height = h * (.38 + layer * .025);
                const direction = layer % 2 === 0 ? 1 : -1;
                const offset = ((t * (9 + layer * 3) * direction + layer * w * .37) % width + width) % width;
                const baseY = h * (.01 + layer * .16) + Math.sin(t * .23 + layer * 1.7) * 16;
                ctx.globalAlpha = .48 + layer * .055;
                ctx.save();
                ctx.translate(0, baseY);
                ctx.transform(1, Math.sin(t * .15 + layer) * .035,
                    Math.sin(t * .19 + layer) * .12, 1 + Math.sin(t * .21 + layer) * .09, 0, 0);
                for (let repeat = -1; repeat <= 1; repeat++) {
                    ctx.drawImage(sheet, offset + repeat * width, 0, width, height);
                }
                ctx.restore();
            }
            ctx.restore();
        }
        strike() {
            this.struckAt = this.time;
            this.nextStrike = this.time + 6 + Math.random() * 7;
            const startX = .75 + Math.random() * .12;
            this.bolt = [[startX, .03]];
            for (let i = 1; i <= 9; i++) {
                this.bolt.push([Math.max(.66, Math.min(.94, startX + (Math.random() - .5) * .13 - i * .004)), .03 + i * .059]);
            }
            this.branches = [3, 5, 6].map((index, branch) => {
                const from = this.bolt[index];
                const direction = branch % 2 === 0 ? 1 : -1;
                return [from, [from[0] + .05 * direction, from[1] + .025], [from[0] + .025 * direction, from[1] + .055], [from[0] + .10 * direction, from[1] + .11]];
            });
        }
        drawLightning(age) {
            if (age > 1.3) return;
            const ctx = this.ctx, w = this.width, h = this.height;
            const alpha = Math.exp(-age * 3.5);
            ctx.save();
            const light = ctx.createRadialGradient(w * .81, h * .21, 0, w * .81, h * .21, w * .65);
            light.addColorStop(0, `rgba(205,221,255,${alpha * .32})`);
            light.addColorStop(.45, `rgba(145,181,238,${alpha * .1})`);
            light.addColorStop(1, 'rgba(145,181,238,0)');
            ctx.fillStyle = light; ctx.fillRect(0, 0, w, h);
            const trace = (points, width, opacity) => {
                ctx.beginPath();
                points.forEach(([x, y], index) => index === 0 ? ctx.moveTo(x * w, y * h) : ctx.lineTo(x * w, y * h));
                ctx.lineWidth = width; ctx.strokeStyle = `rgba(235,245,255,${opacity})`; ctx.stroke();
            };
            ctx.lineJoin = 'round'; ctx.lineCap = 'round';
            ctx.shadowColor = '#a8cfff'; ctx.shadowBlur = 22;
            trace(this.bolt, 3.8, alpha * .6);
            ctx.shadowBlur = 9;
            trace(this.bolt, 1.45, alpha);
            for (const branch of this.branches) trace(branch, .75, alpha * .65);
            // Broken highlights on the wet foreground follow the same fading strike.
            ctx.shadowBlur = 7;
            for (let i = 0; i < 7; i++) {
                const x = this.bolt[9][0] * w + Math.sin(i * 4) * 16;
                const y = h * .8 + i * 5;
                ctx.strokeStyle = `rgba(174,208,245,${alpha * .18 * (1 - i / 8)})`;
                ctx.lineWidth = .8; ctx.beginPath(); ctx.moveTo(x - 5 - i, y); ctx.lineTo(x + 8 + i, y); ctx.stroke();
            }
            ctx.restore();
        }
        destroy() {
            cancelAnimationFrame(this.frame);
            this.resizeObserver.disconnect();
            this.visibilityObserver.disconnect();
            document.removeEventListener('visibilitychange', this.onVisibility);
        }
    }

    function initChart() {
        const chart = document.querySelector('[data-chart]');
        if (!chart) return;
        const svg = chart.querySelector('svg');
        const points = [...document.querySelectorAll('[data-point-x]')].map(row => ({x: Number(row.dataset.pointX), y: row.dataset.pointY === '' ? null : Number(row.dataset.pointY), time: row.cells[0].textContent, value: row.cells[1].textContent}));
        const tooltip = chart.querySelector('output');
        const crosshair = svg.querySelector('.chart-crosshair');
        const cursor = svg.querySelector('.chart-cursor');
        let index = Math.max(0, points.length - 1);
        function show(next) {
            index = Math.max(0, Math.min(points.length - 1, next));
            const point = points[index];
            if (!point) return;
            tooltip.hidden = false;
            tooltip.textContent = `${point.time} · ${point.value}${point.y === null ? '' : ' °C'}`;
            crosshair.setAttribute('x1', point.x); crosshair.setAttribute('x2', point.x); crosshair.setAttribute('visibility', 'visible');
            cursor.setAttribute('visibility', point.y === null ? 'hidden' : 'visible');
            cursor.setAttribute('cx', point.x); if (point.y !== null) cursor.setAttribute('cy', point.y);
            const mapped = new DOMPoint(point.x, 0).matrixTransform(svg.getScreenCTM());
            const left = mapped.x - chart.getBoundingClientRect().left - tooltip.offsetWidth / 2;
            tooltip.style.left = `${Math.max(0, Math.min(chart.clientWidth - tooltip.offsetWidth, left))}px`;
        }
        const pointFromPointer = event => {
            const matrix = svg.getScreenCTM();
            if (!matrix) return;
            const x = new DOMPoint(event.clientX, event.clientY).matrixTransform(matrix.inverse()).x;
            let nearest = 0;
            for (let i = 1; i < points.length; i++) if (Math.abs(points[i].x - x) < Math.abs(points[nearest].x - x)) nearest = i;
            show(nearest);
        };
        chart.addEventListener('pointerdown', pointFromPointer);
        chart.addEventListener('pointermove', pointFromPointer);
        const hide = () => { tooltip.hidden = true; cursor.setAttribute('visibility', 'hidden'); crosshair.setAttribute('visibility', 'hidden'); };
        chart.addEventListener('pointerleave', event => { if (event.pointerType !== 'touch') hide(); });
        chart.addEventListener('pointercancel', hide);
        chart.addEventListener('blur', hide);
        chart.addEventListener('keydown', event => {
            const offsets = {ArrowLeft: -1, ArrowRight: 1, Home: -points.length, End: points.length};
            if (event.key === 'Escape') { hide(); return; }
            if (!(event.key in offsets)) return;
            event.preventDefault(); show(index + offsets[event.key]);
        });
    }

    function initPage() {
        disposePage();
        const hero = document.querySelector('.hero');
        const cleanups = [];
        if (hero) {
            liveScene = {kind: hero.dataset.weather, phase: hero.dataset.phase, label: hero.querySelector('[data-condition]').textContent, source: hero.querySelector('[data-scene-tag]').textContent};
            sceneRenderer = new Atmosphere(hero);
            cleanups.push(() => sceneRenderer.destroy());
            setScene();
            if (sceneButton) sceneButton.hidden = false;
        }
        initChart();
        const motionObserver = new IntersectionObserver(entries => {
            for (const entry of entries) entry.target.classList.toggle('motion-offscreen', !entry.isIntersecting);
        });
        document.querySelectorAll('.metric-card, .temperature-panel, .rain-panel').forEach(element => motionObserver.observe(element));
        cleanups.push(() => motionObserver.disconnect());
        const links = [...document.querySelectorAll('.dock a')];
        const targets = links.map(link => document.querySelector(link.getAttribute('href')));
        let queued = false;
        const highlight = () => {
            queued = false;
            let active = 0;
            // Compare vertical positions, since the sun card is beside the plot on desktop.
            let nearestTop = -Infinity;
            targets.forEach((target, index) => {
                const top = target?.getBoundingClientRect().top;
                if (top !== undefined && top <= innerHeight * .4 && top > nearestTop) { active = index; nearestTop = top; }
            });
            links.forEach((link, index) => { if (index === active) link.setAttribute('aria-current', 'location'); else link.removeAttribute('aria-current'); });
        };
        const onScroll = () => { if (!queued) { queued = true; requestAnimationFrame(highlight); } };
        addEventListener('scroll', onScroll, {passive: true});
        cleanups.push(() => removeEventListener('scroll', onScroll));
        highlight();
        disposePage = () => cleanups.forEach(dispose => dispose());
    }
    initPage();

    async function refresh() {
        if (document.hidden || pending || !document.querySelector('.hero')) return;
        pending = true;
        const controller = new AbortController();
        const timeout = setTimeout(() => controller.abort(), 10000);
        try {
            const range = document.querySelector('.range a[aria-current]')?.getAttribute('href')?.includes('range=7d') ? '7d' : '24h';
            const response = await fetch(`data.php?range=${range}`, {signal: controller.signal, cache: 'no-store'});
            if (!response.ok) throw new Error('unavailable');
            const snapshot = await response.json();
            for (const element of document.querySelectorAll('[data-value]')) {
                const text = snapshot.formatted[element.dataset.value];
                if (typeof text === 'string' && element.textContent !== text) {
                    element.textContent = text;
                    element.classList.remove('value-updated');
                    requestAnimationFrame(() => element.classList.add('value-updated'));
                }
            }
            const live = document.querySelector('[data-live]');
            if (live) { live.hidden = snapshot.live.value === null; live.textContent = `Live: ${snapshot.liveLabel}${snapshot.live.status === 'stale' ? t(' · stale') : ''}`; }
            const status = document.querySelector('[data-refresh-status]');
            if (status) { status.textContent = snapshot.status; status.hidden = snapshot.status === ''; }
            const temperature = snapshot.data.temperature?.value;
            document.querySelector('.hero').dataset.temperature = Number.isFinite(temperature) ? String(temperature) : '';
            if (snapshot.atmosphere) { liveScene = snapshot.atmosphere; setScene(); }
            const nextStamp = JSON.stringify([snapshot.updated, snapshot.forecast, snapshot.climate, ...Object.values(snapshot.data).map(value => value.computedAt ?? value.periods?.computedAt)]);
            if (stamp !== null && stamp !== nextStamp) {
                // Preserve an active chart/table interaction; update after it finishes.
                if (dialog.open || document.activeElement?.closest('[data-chart], details')) return;
                const page = await fetch(`${location.pathname}${location.search}`, {signal: controller.signal, cache: 'no-store'});
                if (!page.ok) throw new Error('unavailable');
                const parsed = new DOMParser().parseFromString(await page.text(), 'text/html');
                const nextMain = parsed.querySelector('main');
                if (!nextMain?.querySelector('.hero')) throw new Error('unavailable');
                const scroll = scrollY;
                document.querySelector('main').replaceWith(nextMain);
                initPage();
                window.scrollTo({top: scroll, behavior: 'instant'});
            }
            stamp = nextStamp;
        } catch {
            const status = document.querySelector('[data-refresh-status]');
            if (status) { status.textContent = t('Connection interrupted'); status.hidden = false; }
        } finally { clearTimeout(timeout); pending = false; }
    }
    setInterval(refresh, 15000);
    document.addEventListener('visibilitychange', () => { if (!document.hidden) refresh(); });
    refresh();
})();
