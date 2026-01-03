const IPV4_ENDPOINT = 'https://api4.ipify.org?format=json';
const IPV6_ENDPOINT = 'https://api64.ipify.org?format=json';

let ipv4 = null;
let ipv6 = null;
let geoData = {};
let batteryLevel = 'Loading...';

// Dark mode logic
const toggle = document.getElementById('themeToggle');
const html = document.documentElement;

function setTheme(theme) {
    html.setAttribute('data-theme', theme);
    localStorage.setItem('ipcow-theme', theme);
}

const savedTheme = localStorage.getItem('ipcow-theme');
if (savedTheme) {
    setTheme(savedTheme);
} else if (window.matchMedia('(prefers-color-scheme: dark)').matches) {
    setTheme('dark');
}

toggle.addEventListener('click', () => {
    const current = html.getAttribute('data-theme') || 'light';
    setTheme(current === 'dark' ? 'light' : 'dark');
});

// Handle info icon clicks for tooltip display
document.addEventListener('click', (e) => {
    if (e.target.classList.contains('info-icon')) {
        const title = e.target.getAttribute('title');
        if (title) {
            let tooltip = document.querySelector('.custom-tooltip');
            if (!tooltip) {
                tooltip = document.createElement('div');
                tooltip.className = 'custom-tooltip';
                document.body.appendChild(tooltip);
            }
            tooltip.textContent = title;
            const rect = e.target.getBoundingClientRect();
            tooltip.style.left = (rect.left + rect.width / 2 - 50) + 'px'; // center above
            tooltip.style.top = (rect.top - 35) + 'px';
            tooltip.style.display = 'block';
            // Hide after 3 seconds
            setTimeout(() => {
                tooltip.style.display = 'none';
            }, 3000);
        }
    }
});

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

function copyIP(element) {
    const ipText = element.firstChild.textContent.trim();

    if (ipText) {
        const tooltip = element.nextElementSibling;
        if (tooltip) {
            tooltip.textContent = 'Copying...';
            tooltip.style.opacity = '1';

            navigator.clipboard.writeText(ipText).then(() => {
                tooltip.textContent = 'Copied!';
                setTimeout(() => {
                    tooltip.style.opacity = '0';
                }, 1500);
            }).catch(err => {
                console.error('Secure context/Clipboard error:', err);
                tooltip.textContent = 'Failed to copy';
                setTimeout(() => {
                    tooltip.style.opacity = '0';
                }, 1500);
            });
        }
    }
}

async function fetchIPv4() {
    const container = document.getElementById('ipv4-container');
    const ipEl = document.getElementById('ipv4-ip');

    try {
        const response = await fetch(IPV4_ENDPOINT, { signal: AbortSignal.timeout(8000) });
        if (!response.ok) throw new Error();
        const data = await response.json();
        ipv4 = data.ip;
        ipEl.textContent = ipv4;
        container.classList.remove('loading', 'error');
        container.classList.add('success');
    } catch (err) {
        ipEl.textContent = 'Unable to fetch';
        container.classList.remove('loading', 'success');
        container.classList.add('error');
    }

    ipEl.onclick = () => copyIP(ipEl);
}

async function fetchIPv6AndGeo() {
    const container = document.getElementById('ipv6-container');
    const ipEl = document.getElementById('ipv6-ip');

    try {
        const response = await fetch(IPV6_ENDPOINT, { signal: AbortSignal.timeout(8000) });
        if (response.ok) {
            const data = await response.json();
            ipv6 = data.ip;
            ipEl.textContent = ipv6;
            container.classList.remove('loading', 'error');
            container.classList.add('success');
        } else {
            throw new Error();
        }
    } catch (err) {
        ipv6 = null;
        container.style.display = 'none';
    }

    ipEl.onclick = () => copyIP(ipEl);

    try {
        // Replace with your actual worker URL after deployment
        const geoResponse = await fetch('https://ipcow-geo-proxy.techguywithabeard.workers.dev', { signal: AbortSignal.timeout(10000) });
        if (!geoResponse.ok) throw new Error();
        geoData = await geoResponse.json();
        displayIPDetails(geoData);
        initMap(geoData.latitude, geoData.longitude);
    } catch (geoErr) {
        console.error('Geolocation failed:', geoErr);
        // Optional: fallback message
        document.getElementById('ip-details').innerHTML = '<p style="color:#999;">Location data unavailable</p>';
    }

    if (ipv4 && ipv6 && ipv4 === ipv6) {
        document.getElementById('ipv6-container').style.display = 'none';
    }
}

function displayIPDetails(data) {
    const table = document.getElementById('details-table');
    if (!table) return;
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

    for (const key in locationFields) {
        if (data[key] !== undefined && data[key] !== null) {
            const row = table.insertRow();
            row.insertCell(0).textContent = locationFields[key];
            const cell = row.insertCell(1);
            cell.innerHTML = `<span class="ua-value">${data[key]}</span>`;
        }
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
        const currentTime = new Intl.DateTimeFormat('en-US', { timeZone: tz.id, dateStyle: 'full', timeStyle: 'medium' }).format(new Date());
        timeCell.innerHTML = `<span class="ua-value">${currentTime}</span>`;
    }

    document.getElementById('ip-details').style.display = 'block';
}

function initMap(lat, lng) {
    if (!lat || !lng) {
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
            uaCell.innerHTML = `User-Agent String <span class="info-icon" title="${explanations['User-Agent String']}"><i class="fa fa-question-circle"></i></span>`;
            const uaValueCell = uaRow.insertCell(1);
            uaValueCell.innerHTML = `<span class="ua-value" style="word-break:break-all; display:block;">${navigator.userAgent}</span>`;
        }

        for (const key in section.data) {
            const value = section.data[key];
            if (value) {
                const row = table.insertRow();
                const cell = row.insertCell(0);
                cell.innerHTML = `${key} <span class="info-icon" title="${explanations[key]}"><i class="fa fa-question-circle"></i></span>`;
                const valueCell = row.insertCell(1);
                valueCell.innerHTML = `<span class="ua-value">${value}</span>`;
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
            cell.innerHTML = `${key} <span class="info-icon" title="${explanations[key]}"><i class="fa fa-question-circle"></i></span>`;
            const valueCell = row.insertCell(1);
            valueCell.innerHTML = `<span class="ua-value">${fieldValues[key]}</span>`;
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
            cell.innerHTML = `Battery Level <span class="info-icon" title="${explanations['Battery Level']}"><i class="fa fa-question-circle"></i></span>`;
            const valueCell = row.insertCell(1);
            valueCell.innerHTML = `<span class="ua-value">${batteryLevel}</span>`;
        }).catch(() => {
            batteryLevel = 'Unavailable';
        });
    } else {
        batteryLevel = 'Not supported';
    }
}

// Run everything
Promise.allSettled([fetchIPv4(), fetchIPv6AndGeo()]).finally(parseClientInfo);