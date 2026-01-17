const IPV4_ENDPOINT = 'https://api4.ipify.org?format=json';
const IPV6_ENDPOINT = 'https://api64.ipify.org?format=json';

let ipv4 = null;
let ipv6 = null;
let geoData = {};
let batteryLevel = 'Loading...';

// Help-icon tooltip (hover on desktop, tap/click on mobile)
(() => {
    const HELP_ICON_SELECTOR = '.icon-help, .info-icon';
    let tooltipEl = null;
    let hideTimer = null;
    let currentTarget = null;

    const ensureTooltipEl = () => {
        if (tooltipEl) return tooltipEl;
        tooltipEl = document.createElement('div');
        tooltipEl.className = 'help-tooltip';
        document.body.appendChild(tooltipEl);

        // Apply styles with !important to avoid CDN/Bootstrap overrides.
        const setImportantStyles = (styles) => {
            for (const [key, value] of Object.entries(styles)) {
                tooltipEl.style.setProperty(key, value, 'important');
            }
        };
        setImportantStyles({
            'display': 'none',
            'position': 'fixed',
            'z-index': '2147483647',
            'max-width': '320px',
            'background-color': '#111827',
            'color': '#ffffff',
            'padding': '6px 10px',
            'border-radius': '6px',
            'font-size': '13px',
            'font-weight': '600',
            'line-height': '1.2',
            'font-family': 'system-ui, -apple-system, Segoe UI, Roboto, Helvetica, Arial, sans-serif',
            'white-space': 'normal',
            'pointer-events': 'none',
            'box-shadow': '0 8px 24px rgba(0, 0, 0, 0.35)',
            'opacity': '1',
            'visibility': 'visible',
            '-webkit-font-smoothing': 'antialiased',
            '-webkit-text-fill-color': '#ffffff',
            'text-shadow': '0 1px 1px rgba(0, 0, 0, 0.35)'
        });
        return tooltipEl;
    };

    const getHelpText = (el) => {
        const fromData = el.getAttribute('data-original-title');
        if (fromData) return fromData;
        const fromTitle = el.getAttribute('title');
        return fromTitle || '';
    };

    const suppressNativeTitle = (el) => {
        if (!el.hasAttribute('title')) return;
        const title = el.getAttribute('title');
        if (title && !el.getAttribute('data-original-title')) {
            el.setAttribute('data-original-title', title);
        }
        el.removeAttribute('title');
    };

    const restoreNativeTitle = (el) => {
        const original = el.getAttribute('data-original-title');
        if (!original) return;
        // Restore only if title isn't already set by something else.
        if (!el.hasAttribute('title')) {
            el.setAttribute('title', original);
        }
    };

    const positionTooltipAbove = (targetEl) => {
        const tt = ensureTooltipEl();
        const padding = 8;
        const rect = targetEl.getBoundingClientRect();

        // Temporarily show to measure.
        tt.style.setProperty('display', 'block', 'important');
        const measured = tt.getBoundingClientRect();
        const width = measured.width || 200;
        const height = measured.height || 28;

        let left = rect.left + rect.width / 2 - width / 2;
        let top = rect.top - height - 10;

        if (left < padding) left = padding;
        if (left + width > window.innerWidth - padding) left = window.innerWidth - padding - width;
        if (top < padding) top = rect.bottom + 10;
        if (top + height > window.innerHeight - padding) top = window.innerHeight - padding - height;

        tt.style.setProperty('left', `${Math.round(left)}px`, 'important');
        tt.style.setProperty('top', `${Math.round(top)}px`, 'important');
    };

    const hideTooltip = () => {
        if (!tooltipEl) return;
        tooltipEl.style.setProperty('display', 'none', 'important');
        if (hideTimer) {
            clearTimeout(hideTimer);
            hideTimer = null;
        }
        if (currentTarget) {
            restoreNativeTitle(currentTarget);
            currentTarget = null;
        }
    };

    const showTooltip = (targetEl, { autoHideMs } = {}) => {
        const text = getHelpText(targetEl);
        if (!text) return;

        const tt = ensureTooltipEl();
        tt.textContent = text;

        currentTarget = targetEl;
        suppressNativeTitle(targetEl);
        positionTooltipAbove(targetEl);

        if (hideTimer) {
            clearTimeout(hideTimer);
            hideTimer = null;
        }
        if (typeof autoHideMs === 'number') {
            hideTimer = setTimeout(hideTooltip, autoHideMs);
        }
    };

    // Hover support (mouse)
    document.addEventListener('pointerover', (e) => {
        const icon = e.target.closest?.(HELP_ICON_SELECTOR);
        if (!icon) return;
        if (e.pointerType && e.pointerType !== 'mouse') return;
        showTooltip(icon);
    });
    document.addEventListener('pointerout', (e) => {
        const icon = e.target.closest?.(HELP_ICON_SELECTOR);
        if (!icon) return;
        if (e.pointerType && e.pointerType !== 'mouse') return;
        hideTooltip();
    });

    // Click/tap support (mobile + desktop)
    document.addEventListener('click', (e) => {
        const icon = e.target.closest?.(HELP_ICON_SELECTOR);
        if (!icon) return;
        showTooltip(icon, { autoHideMs: 3000 });
    });
    document.addEventListener('pointerdown', (e) => {
        const icon = e.target.closest?.(HELP_ICON_SELECTOR);
        if (!icon) return;
        if (e.pointerType === 'mouse') return;
        showTooltip(icon, { autoHideMs: 3000 });
    }, { passive: true });

    // Reposition on resize/scroll if visible.
    window.addEventListener('scroll', () => {
        if (tooltipEl && tooltipEl.style.display !== 'none' && currentTarget) {
            positionTooltipAbove(currentTarget);
        }
    }, { passive: true });
    window.addEventListener('resize', () => {
        if (tooltipEl && tooltipEl.style.display !== 'none' && currentTarget) {
            positionTooltipAbove(currentTarget);
        }
    });
})();

document.getElementById('export-btn').addEventListener('click', () => {
    const data = {
        ips: {
            ipv4,
            ipv6
        },
        location: {
            continent: geoData.continent_name,
            country: geoData.country_name,
            region: geoData.region_name,
            city: geoData.city,
            zipCode: geoData.zip,
            latitude: geoData.latitude,
            longitude: geoData.longitude
        },
        isp: {
            organization: geoData.connection ? geoData.connection.org || geoData.connection.isp : 'Unknown',
            timezone: geoData.time_zone ? {
                id: geoData.time_zone.id,
                offset: geoData.time_zone.gmt_offset / 3600,
                currentTime: new Intl.DateTimeFormat('en-US', { timeZone: geoData.time_zone.id, dateStyle: 'full', timeStyle: 'medium' }).format(new Date())
            } : null
        },
        device: (() => {
            const fieldValues = {
                'Screen Resolution': `${screen.width} × ${screen.height}`,
                'Viewport Size': `${window.innerWidth} × ${window.innerHeight}`,
                'Connection Type': navigator.connection ? navigator.connection.effectiveType || 'Unknown' : 'Unknown',
                'Hardware Concurrency': navigator.hardwareConcurrency || 'Unknown',
                'Device Memory': navigator.deviceMemory ? `${navigator.deviceMemory} GB` : 'Unknown',
                'Language': navigator.language || 'Unknown',
                'Platform': navigator.platform || 'Unknown',
                'Screen Color Depth': `${screen.colorDepth} bits`,
                'Max Touch Points': navigator.maxTouchPoints || 'Unknown',
                'Cookies Enabled': navigator.cookieEnabled ? 'Yes' : 'No',
                'Do Not Track': navigator.doNotTrack || 'Not set',
                'Geolocation Available': 'geolocation' in navigator ? 'Yes' : 'No',
                'WebRTC Available': 'RTCPeerConnection' in window ? 'Yes' : 'No',
                'Vibration API': 'vibrate' in navigator ? 'Yes' : 'No',
                'Service Worker': 'serviceWorker' in navigator ? 'Yes' : 'No',
                'WebGL Support': (() => { try { const canvas = document.createElement('canvas'); const gl = canvas.getContext('webgl') || canvas.getContext('experimental-webgl'); return gl ? 'Yes' : 'No'; } catch { return 'No'; } })(),
                'Web Audio API': 'AudioContext' in window || 'webkitAudioContext' in window ? 'Yes' : 'No',
                'Web Speech API': 'speechSynthesis' in window ? 'Yes' : 'No',
                'IndexedDB': 'indexedDB' in window ? 'Yes' : 'No',
                'Web Workers': 'Worker' in window ? 'Yes' : 'No',
                'WebSockets': 'WebSocket' in window ? 'Yes' : 'No',
                'Canvas Support': (() => { const c = document.createElement('canvas'); return c.getContext ? 'Yes' : 'No'; })(),
                'Touch Events': 'ontouchstart' in window ? 'Yes' : 'No',
                'Screen Orientation': screen.orientation ? screen.orientation.type : 'Unknown',
                'Timezone Offset': `${new Date().getTimezoneOffset()} minutes`
            };

            const groups = [
                { title: 'Display', fields: ['Screen Resolution', 'Viewport Size', 'Screen Color Depth', 'Device Pixel Ratio', 'Screen Orientation'] },
                { title: 'Hardware', fields: ['Hardware Concurrency', 'Device Memory', 'Max Touch Points'] },
                { title: 'Network', fields: ['Connection Type'] },
                { title: 'Localization', fields: ['Language', 'Timezone Offset'] },
                { title: 'Capabilities', fields: ['Cookies Enabled', 'Do Not Track', 'Geolocation Available', 'WebRTC Available', 'Vibration API', 'Service Worker', 'WebGL Support', 'Web Audio API', 'Web Speech API', 'IndexedDB', 'Web Workers', 'WebSockets', 'Canvas Support', 'Touch Events'] }
            ];

            const device = {
                browser: {
                    name: bowser.getParser(navigator.userAgent).getBrowser().name,
                    version: bowser.getParser(navigator.userAgent).getBrowser().version,
                    userAgent: navigator.userAgent
                },
                os: {
                    name: bowser.getParser(navigator.userAgent).getOS().name,
                    version: bowser.getParser(navigator.userAgent).getOS().versionName
                },
                platform: {
                    type: bowser.getParser(navigator.userAgent).getPlatform().type
                }
            };

            groups.forEach(group => {
                device[group.title.toLowerCase()] = {};
                group.fields.forEach(key => {
                    device[group.title.toLowerCase()][key] = fieldValues[key];
                });
            });

            device.power = { 'Battery Level': batteryLevel };

            return device;
        })()
    };
    const blob = new Blob([JSON.stringify(data, null, 2)], { type: 'application/json' });
    const url = URL.createObjectURL(blob);
    const a = document.createElement('a');
    a.href = url;
    a.download = 'ip-cow-data.json';
    a.click();
    URL.revokeObjectURL(url);
});

function makeCopyable(element, textGetter = (el) => el.textContent.trim(), options = {}) {
    if (!element || element.dataset.copyableBound === '1') return;
    element.dataset.copyableBound = '1';

    const { lockBackgroundOnHover = false } = options;
    const lockedBackgroundColor = lockBackgroundOnHover
        ? window.getComputedStyle(element).backgroundColor
        : null;

    // Avoid collisions with Bootstrap/other CSS by not using generic class names.
    const tooltipEl = document.createElement('div');
    tooltipEl.className = 'copy-tooltip';
    document.body.appendChild(tooltipEl);

    const setImportantStyles = (styles) => {
        for (const [key, value] of Object.entries(styles)) {
            tooltipEl.style.setProperty(key, value, 'important');
        }
    };

    const applyBaseTooltipStyle = () => {
        setImportantStyles({
            'display': 'none',
            'position': 'fixed',
            'z-index': '2147483647',
            'background-color': '#111827',
            'color': '#ffffff',
            'padding': '6px 10px',
            'border-radius': '6px',
            'font-size': '13px',
            'font-weight': '600',
            'line-height': '1.2',
            'font-family': 'system-ui, -apple-system, Segoe UI, Roboto, Helvetica, Arial, sans-serif',
            'white-space': 'nowrap',
            'pointer-events': 'none',
            'box-shadow': '0 8px 24px rgba(0, 0, 0, 0.35)',
            'opacity': '1',
            'visibility': 'visible',
            'mix-blend-mode': 'normal',
            'text-rendering': 'geometricPrecision',
            '-webkit-font-smoothing': 'antialiased',
            '-webkit-text-fill-color': '#ffffff',
            'text-shadow': '0 1px 1px rgba(0, 0, 0, 0.35)'
        });
    };

    const positionTooltip = (left, top) => {
        // Clamp to viewport with a little padding.
        const padding = 8;
        const rect = tooltipEl.getBoundingClientRect();
        const width = rect.width || 160;
        const height = rect.height || 28;

        let x = left;
        let y = top;
        if (x < padding) x = padding;
        if (x + width > window.innerWidth - padding) x = window.innerWidth - padding - width;
        if (y < padding) y = padding;
        if (y + height > window.innerHeight - padding) y = window.innerHeight - padding - height;

        setImportantStyles({
            'left': `${Math.round(x)}px`,
            'top': `${Math.round(y)}px`
        });
    };

    const showTooltipAtCursor = (event, text) => {
        tooltipEl.textContent = text;
        applyBaseTooltipStyle();
        setImportantStyles({ 'display': 'block' });
        // Measure then position.
        positionTooltip(event.clientX + 12, event.clientY - 34);
    };

    const showTooltipAboveElement = (text) => {
        tooltipEl.textContent = text;
        applyBaseTooltipStyle();
        setImportantStyles({ 'display': 'block' });

        const rect = element.getBoundingClientRect();
        // Measure then position.
        const measured = tooltipEl.getBoundingClientRect();
        const width = measured.width || 160;
        positionTooltip(rect.left + rect.width / 2 - width / 2, rect.top - 42);
    };

    const hideTooltip = () => {
        setImportantStyles({ 'display': 'none' });
    };

    // UX: show it's interactive.
    if (!element.style.cursor) element.style.cursor = 'copy';
    if (!element.hasAttribute('tabindex')) element.setAttribute('tabindex', '0');

    const getPointerLike = (e) => {
        if (!e) return null;
        if (typeof e.clientX === 'number' && typeof e.clientY === 'number') {
            return { clientX: e.clientX, clientY: e.clientY };
        }
        const touch = e.touches && e.touches[0]
            ? e.touches[0]
            : (e.changedTouches && e.changedTouches[0] ? e.changedTouches[0] : null);
        if (touch && typeof touch.clientX === 'number' && typeof touch.clientY === 'number') {
            return { clientX: touch.clientX, clientY: touch.clientY };
        }
        return null;
    };

    const copyTextWithFallback = async (text) => {
        if (navigator.clipboard && typeof navigator.clipboard.writeText === 'function' && window.isSecureContext) {
            await navigator.clipboard.writeText(text);
            return;
        }

        const ta = document.createElement('textarea');
        ta.value = text;
        ta.setAttribute('readonly', '');
        ta.style.position = 'fixed';
        ta.style.left = '-9999px';
        ta.style.top = '0';
        document.body.appendChild(ta);
        ta.select();
        ta.setSelectionRange(0, ta.value.length);
        const ok = document.execCommand('copy');
        document.body.removeChild(ta);
        if (!ok) throw new Error('execCommand(copy) failed');
    };

    let lastPointer = null;
    let isShowingResult = false;

    // Mobile: capture touch/pointer coordinates so "Copied!" can appear at the tap location.
    element.addEventListener('pointerdown', (e) => {
        const p = getPointerLike(e);
        if (p) lastPointer = p;
    }, { passive: true });
    element.addEventListener('touchstart', (e) => {
        const p = getPointerLike(e);
        if (p) lastPointer = p;
    }, { passive: true });

    let mousemoveHandler;
    element.addEventListener('mouseenter', (e) => {
        if (lockBackgroundOnHover && lockedBackgroundColor) {
            element.style.setProperty('background-color', lockedBackgroundColor, 'important');
        }
        lastPointer = getPointerLike(e) || lastPointer;
        showTooltipAtCursor(e, 'click to copy');
        mousemoveHandler = (ev) => {
            lastPointer = getPointerLike(ev) || lastPointer;
            if (isShowingResult) return;
            showTooltipAtCursor(ev, 'click to copy');
        };
        document.addEventListener('mousemove', mousemoveHandler);
    });

    element.addEventListener('mouseleave', () => {
        hideTooltip();
        if (mousemoveHandler) {
            document.removeEventListener('mousemove', mousemoveHandler);
            mousemoveHandler = null;
        }
    });

    const doCopy = async (event) => {
        const text = textGetter(element);
        const pointerEvent = getPointerLike(event) || lastPointer;

        try {
            await copyTextWithFallback(text);
            isShowingResult = true;
            if (pointerEvent) {
                showTooltipAtCursor(pointerEvent, 'Copied!');
            } else {
                showTooltipAboveElement('Copied!');
            }
        } catch (err) {
            console.error('Failed to copy: ', err);
            isShowingResult = true;
            if (pointerEvent) {
                showTooltipAtCursor(pointerEvent, 'Failed to copy');
            } else {
                showTooltipAboveElement('Failed to copy');
            }
        }

        setTimeout(() => {
            hideTooltip();
            isShowingResult = false;
        }, 1500);
    };

    element.addEventListener('click', doCopy);
    element.addEventListener('keydown', (e) => {
        if (e.key === 'Enter' || e.key === ' ') {
            e.preventDefault();
            doCopy(null);
        }
    });
}

async function fetchIPv4() {
    const container = document.getElementById('ipv4-container');
    const ipEl = document.getElementById('ipv4-ip');

    makeCopyable(ipEl, undefined, { lockBackgroundOnHover: true });

    try {
        const response = await fetch(IPV4_ENDPOINT, { signal: AbortSignal.timeout(8000) });
        if (!response.ok) throw new Error();
        const data = await response.json();
        ipv4 = data.ip;
        ipEl.textContent = ipv4;
        container.classList.remove('error');
        container.classList.add('success');
    } catch (err) {
        ipEl.textContent = 'Unable to fetch';
        container.classList.remove('success');
        container.classList.add('error');
    }
}

async function fetchIPv6AndGeo() {
    const container = document.getElementById('ipv6-container');
    const ipEl = document.getElementById('ipv6-ip');

    makeCopyable(ipEl, undefined, { lockBackgroundOnHover: true });

    try {
        const response = await fetch(IPV6_ENDPOINT, { signal: AbortSignal.timeout(8000) });
        if (response.ok) {
            const data = await response.json();
            ipv6 = data.ip;
            ipEl.textContent = ipv6;
            container.classList.remove('error');
            container.classList.add('success');
        } else {
            throw new Error();
        }
    } catch (err) {
        ipv6 = null;
        container.style.display = 'none';
    }

    try {
        // Replace with your actual worker URL after deployment
        // Add cache buster to prevent cached responses
        const geoResponse = await fetch(`https://geo.ipcow.com/?_=${Date.now()}`, { signal: AbortSignal.timeout(10000) });
        if (!geoResponse.ok) throw new Error();
        geoData = await geoResponse.json();
        
        try {
            displayIPDetails(geoData);
        } catch (renderErr) {
            console.error('Error rendering IP details:', renderErr);
            const ipDetails = document.getElementById('ip-details');
            if (ipDetails) {
                 ipDetails.style.display = 'block';
                 ipDetails.innerHTML += '<p style="color:orange; text-align:center;">Partial data error</p>';
            }
        }
        
        initMap(geoData.latitude, geoData.longitude);
    } catch (geoErr) {
        console.error('Geolocation failed:', geoErr);
        // Optional: fallback message
        const ipDetails = document.getElementById('ip-details');
        if (ipDetails) {
            ipDetails.innerHTML = '<p style="color:#999; text-align: center;">ISP data unavailable</p>';
            ipDetails.style.display = 'block';
        }
        const mapEl = document.getElementById('map');
        if (mapEl) {
            mapEl.innerHTML = '<p style="text-align:center; color:#999; padding:60px;">Location data unavailable</p>';
        }
    }

    if (ipv4 && ipv6 && ipv4 === ipv6) {
        document.getElementById('ipv6-container').style.display = 'none';
    }
}

function displayIPDetails(data) {
    const table = document.getElementById('details-table');
    if (!table) return;
    
    // Clear any existing rows to prevent duplication if called multiple times
    // But preserve the table structure if needed? Here we just wipe tbody content.
    table.innerHTML = '';

    const locationFields = {
        continent_name: 'Continent',
        country_name: 'Country',
        region_name: 'Region',
        city: 'City',
        zip: 'ZIP Code',
        latitude: 'Latitude',
        longitude: 'Longitude',
    };

    let hasLocationData = false;
    for (const key in locationFields) {
        const val = data[key];
        // Skip null, undefined, 0, or empty string
        if (val !== undefined && val !== null && val !== 0 && val !== '') {
            const row = table.insertRow();
            row.insertCell(0).textContent = locationFields[key];
            const cell = row.insertCell(1);
            cell.innerHTML = `<span class="ua-value">${val}</span>`;
            hasLocationData = true;
        }
    }
    
    // If no location data was found, show a fallback row
    if (!hasLocationData) {
        const row = table.insertRow();
        const cell = row.insertCell(0);
        cell.colSpan = 2;
        cell.style.textAlign = 'center';
        cell.style.color = '#999';
        cell.textContent = 'Detailed location data unavailable for this IP';
    }

    if (data.connection) {
        const row = table.insertRow();
        row.insertCell(0).textContent = 'ISP / Organization';
        const cell = row.insertCell(1);
        cell.innerHTML = `<span class="ua-value">${data.connection.org || data.connection.isp || 'Unknown'}</span>`;
    }

    if (data.time_zone) {
        const tz = data.time_zone;
        const offsetHours = tz.gmt_offset ? tz.gmt_offset / 3600 : 0;
        const row = table.insertRow();
        row.insertCell(0).textContent = 'Timezone';
        const cell = row.insertCell(1);
        cell.innerHTML = `<span class="ua-value">${tz.id || 'Unknown'} (Offset: GMT${offsetHours >= 0 ? '+' : ''}${offsetHours})</span>`;

        const timeRow = table.insertRow();
        timeRow.insertCell(0).textContent = 'Current Time';
        const timeCell = timeRow.insertCell(1);
        try {
            const currentTime = new Intl.DateTimeFormat('en-US', { timeZone: tz.id, dateStyle: 'full', timeStyle: 'medium' }).format(new Date());
            timeCell.innerHTML = `<span class="ua-value">${currentTime}</span>`;
        } catch (e) {
            timeCell.innerHTML = `<span class="ua-value">Date/Time Unavailable</span>`;
        }
    }

    document.getElementById('ip-details').style.display = 'block';

    // Make all ua-value spans copyable
    document.querySelectorAll('#details-table .ua-value').forEach(span => makeCopyable(span));
}

function initMap(lat, lng) {
    if (!lat && !lng) {
         // If both are 0 or null/undefined, treat as invalid
         document.getElementById('map').innerHTML = '<p style="text-align:center; color:#999; padding:60px;">Location data not available</p>';
         return;
    }
    // Allow 0 if one of them is non-zero (unlikely but possible), but if both are falsy (0,0), it's usually bad data.
    // However, the check `if (!lat || !lng)` fails for coordinate 0.
    // Let's be more specific: fail if they are null/undefined.
    // If they are exactly 0, that's "Null Island", but for IP geolocation it usually means "failed lookup".
    if (lat === 0 && lng === 0) {
        document.getElementById('map').innerHTML = '<p style="text-align:center; color:#999; padding:60px;">Location data not available</p>';
        return;
    }
    if (lat === undefined || lat === null || lng === undefined || lng === null) {
        document.getElementById('map').innerHTML = '<p style="text-align:center; color:#999; padding:60px;">Location data not available</p>';
        return;
    }

    const map = L.map('map', {
        center: [lat, lng],
        zoom: 10
    });

    L.tileLayer('https://{s}.tile.openstreetmap.org/{z}/{x}/{y}.png', {
        attribution: '&copy; OpenStreetMap contributors'
    }).addTo(map);

    L.marker([lat, lng]).addTo(map).openPopup();
}

function parseClientInfo() {
    const explanations = {
        'Name': 'The name of your browser.',
        'Version': 'The version number of your browser.',
        'User-Agent String': 'The full user-agent string sent by your browser to websites.',
        'Type': 'The type of platform your device is running on.',
        'Screen Resolution': 'The width and height of your screen in pixels.',
        'Viewport Size': 'The width and height of the browser window viewport.',
        'Screen Color Depth': 'The number of bits used to represent the color of each pixel.',
        'Device Pixel Ratio': 'The ratio of physical pixels to CSS pixels on your device.',
        'Screen Orientation': 'The current orientation of your screen (portrait or landscape).',
        'Hardware Concurrency': 'The number of logical processors available to run threads.',
        'Device Memory': 'The amount of device memory (RAM) available.',
        'Max Touch Points': 'The maximum number of simultaneous touch points supported.',
        'Connection Type': 'An estimate of connection speed categorized as 2g/3g/4g (not the actual network type like cable or DSL).',
        'Timezone Offset': 'The offset in minutes from UTC for your timezone.',
        'Cookies Enabled': 'Whether cookies are enabled in your browser.',
        'Do Not Track': 'Your browser\'s do-not-track preference.',
        'Geolocation Available': 'Whether geolocation API is available.',
        'WebRTC Available': 'Whether WebRTC (real-time communication) is supported.',
        'Vibration API': 'Whether the device can vibrate.',
        'Service Worker': 'Whether service workers are supported for background tasks.',
        'WebGL Support': 'Whether WebGL is supported for 3D graphics.',
        'Web Audio API': 'Whether the Web Audio API is supported for audio processing.',
        'Web Speech API': 'Whether speech synthesis and recognition are supported.',
        'IndexedDB': 'Whether IndexedDB is supported for client-side storage.',
        'Web Workers': 'Whether web workers are supported for background scripts.',
        'WebSockets': 'Whether WebSocket connections are supported.',
        'Canvas Support': 'Whether HTML5 Canvas is supported for drawing.',
        'Touch Events': 'Whether touch events are supported.',
        'Battery Level': 'The current battery level of your device.'
    };

    const parser = bowser.getParser(navigator.userAgent);
    const browser = parser.getBrowser();
    const os = parser.getOS();
    const platform = parser.getPlatform();

    const table = document.getElementById('ua-table');
    if (!table) return;
    table.innerHTML = '';

    const sections = [
        { title: 'Browser', data: { Name: browser.name, Version: browser.version } },
        { title: 'OS', data: { Name: os.name, Version: os.version || os.versionName } },
        { title: 'Platform', data: { Type: platform.type } }
    ];

    sections.forEach(section => {
        const hasValue = Object.values(section.data).some(v => v);
        if (!hasValue) return;

        const headerRow = table.insertRow();
        const headerCell = headerRow.insertCell(0);
        headerCell.textContent = section.title;
        headerCell.colSpan = 2;
        headerCell.style.fontWeight = 'bold';
        headerCell.style.background = 'var(--table-header-bg)';

        if (section.title === 'Browser') {
            const uaRow = table.insertRow();
            const uaCell = uaRow.insertCell(0);
            uaCell.innerHTML = `User-Agent String <i class="icon-help" title="${explanations['User-Agent String']}"></i>`;
            const uaValueCell = uaRow.insertCell(1);
            uaValueCell.innerHTML = `<span class="ua-value" style="word-break:break-all; display:block;">${navigator.userAgent}</span>`;
            makeCopyable(uaValueCell.querySelector('.ua-value'));
        }

        for (const key in section.data) {
            const value = section.data[key];
            if (value) {
                const row = table.insertRow();
                const cell = row.insertCell(0);
                cell.innerHTML = `${key} <i class="icon-help" title="${explanations[key]}"></i>`;
                const valueCell = row.insertCell(1);
                valueCell.innerHTML = `<span class="ua-value">${value}</span>`;
                makeCopyable(valueCell.querySelector('.ua-value'));
            }
        }
    });

    const fieldValues = {
        'Screen Resolution': `${screen.width} × ${screen.height}`,
        'Viewport Size': `${window.innerWidth} × ${window.innerHeight}`,
        'Connection Type': navigator.connection ? navigator.connection.effectiveType || 'Unknown' : 'Unknown',
        'Hardware Concurrency': navigator.hardwareConcurrency || 'Unknown',
        'Device Memory': navigator.deviceMemory ? `${navigator.deviceMemory} GB` : 'Unknown',
        'Language': navigator.language || 'Unknown',
        'Platform': navigator.platform || 'Unknown',
        'Screen Color Depth': `${screen.colorDepth} bits`,
        'Device Pixel Ratio': window.devicePixelRatio || 'Unknown',
        'Max Touch Points': navigator.maxTouchPoints || 'Unknown',
        'Cookies Enabled': navigator.cookieEnabled ? 'Yes' : 'No',
        'Do Not Track': navigator.doNotTrack || 'Not set',
        'Geolocation Available': 'geolocation' in navigator ? 'Yes' : 'No',
        'WebRTC Available': 'RTCPeerConnection' in window ? 'Yes' : 'No',
        'Vibration API': 'vibrate' in navigator ? 'Yes' : 'No',
        'Service Worker': 'serviceWorker' in navigator ? 'Yes' : 'No',
        'WebGL Support': (() => { try { const canvas = document.createElement('canvas'); const gl = canvas.getContext('webgl') || canvas.getContext('experimental-webgl'); return gl ? 'Yes' : 'No'; } catch { return 'No'; } })(),
        'Web Audio API': 'AudioContext' in window || 'webkitAudioContext' in window ? 'Yes' : 'No',
        'Web Speech API': 'speechSynthesis' in window ? 'Yes' : 'No',
        'IndexedDB': 'indexedDB' in window ? 'Yes' : 'No',
        'Web Workers': 'Worker' in window ? 'Yes' : 'No',
        'WebSockets': 'WebSocket' in window ? 'Yes' : 'No',
        'Canvas Support': (() => { const c = document.createElement('canvas'); return c.getContext ? 'Yes' : 'No'; })(),
        'Touch Events': 'ontouchstart' in window ? 'Yes' : 'No',
        'Screen Orientation': screen.orientation ? screen.orientation.type : 'Unknown',
        'Timezone Offset': `${new Date().getTimezoneOffset()} minutes`
    };

    const groups = [
        { title: 'Display', fields: ['Screen Resolution', 'Viewport Size', 'Screen Color Depth', 'Device Pixel Ratio', 'Screen Orientation'] },
        { title: 'Hardware', fields: ['Hardware Concurrency', 'Device Memory', 'Max Touch Points'] },
        { title: 'Network', fields: ['Connection Type'] },
        { title: 'Localization', fields: ['Language', 'Timezone Offset'] },
        { title: 'Capabilities', fields: ['Cookies Enabled', 'Do Not Track', 'Geolocation Available', 'WebRTC Available', 'Vibration API', 'Service Worker', 'WebGL Support', 'Web Audio API', 'Web Speech API', 'IndexedDB', 'Web Workers', 'WebSockets', 'Canvas Support', 'Touch Events'] }
    ];

    groups.forEach(group => {
        const headerRow = table.insertRow();
        const headerCell = headerRow.insertCell(0);
        headerCell.textContent = group.title;
        headerCell.colSpan = 2;
        headerCell.style.fontWeight = 'bold';
        headerCell.style.background = 'var(--table-header-bg)';

        group.fields.forEach(key => {
            const row = table.insertRow();
            const cell = row.insertCell(0);
            cell.innerHTML = `${key} <i class="icon-help" title="${explanations[key]}"></i>`;
            const valueCell = row.insertCell(1);
            valueCell.innerHTML = `<span class="ua-value">${fieldValues[key]}</span>`;
            makeCopyable(valueCell.querySelector('.ua-value'));
        });
    });

    // Add Power section header
    const powerHeader = table.insertRow();
    const powerCell = powerHeader.insertCell(0);
    powerCell.textContent = 'Power';
    powerCell.colSpan = 2;
    powerCell.style.fontWeight = 'bold';
    powerCell.style.background = 'var(--table-header-bg)';

    // Add battery level asynchronously
    if ('getBattery' in navigator) {
        navigator.getBattery().then(battery => {
            batteryLevel = `${Math.round(battery.level * 100)}%`;
            const row = table.insertRow();
            const cell = row.insertCell(0);
            cell.innerHTML = `Battery Level <i class="icon-help" title="${explanations['Battery Level']}"></i>`;
            const valueCell = row.insertCell(1);
            valueCell.innerHTML = `<span class="ua-value">${batteryLevel}</span>`;
            // Make copyable
            makeCopyable(valueCell.querySelector('.ua-value'));
        }).catch(() => {
            batteryLevel = 'Unavailable';
        });
    } else {
        batteryLevel = 'Not supported';
    }

    // Make all ua-value spans copyable
    document.querySelectorAll('#ua-table .ua-value').forEach(span => makeCopyable(span));
}

// Run everything
Promise.allSettled([fetchIPv4(), fetchIPv6AndGeo()]).finally(parseClientInfo);