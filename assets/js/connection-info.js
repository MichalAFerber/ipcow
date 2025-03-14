console.log('Script loaded');

async function loadInfo() {
  const elements = document.querySelectorAll('#connection-info span, #ip-display');
  elements.forEach(el => el.textContent = 'Loading...');
  const errorMessage = document.getElementById('error-message');

  try {
    // Fetch IPv4 and geo from info.php
    const infoResponse = await fetch('/api/info.php');
    if (!infoResponse.ok) {
      throw new Error(`HTTP error! status: ${infoResponse.status} - ${infoResponse.statusText}`);
    }
    const serverData = await infoResponse.json();
    if (!serverData.success || serverData.error) {
      throw new Error(serverData.error || 'Failed to load server data.');
    }
    const data = serverData.data;

    // Fetch IPv4 from checkip.ipcow.com
    const checkIpV4Response = await fetch('https://checkip.ipcow.com/?ip=v4', {
      cache: 'no-store'
    });
    if (!checkIpV4Response.ok) {
      throw new Error('Failed to fetch IPv4 from checkip.ipcow.com');
    }
    const ipv4FromCheckIp = await checkIpV4Response.text();
    console.log('IPv4 from checkip:', ipv4FromCheckIp);

    // Fetch IPv6 from checkip.ipcow.com?ip=v6
    const checkIpV6Response = await fetch('https://checkip.ipcow.com/?ip=v6', {
      cache: 'no-store'
    });
    if (!checkIpV6Response.ok) {
      throw new Error('Failed to fetch IPv6 from checkip.ipcow.com');
    }
    let ipv6FromCheckIp = await checkIpV6Response.text();
    console.log('IPv6 from checkip:', ipv6FromCheckIp);
    if (ipv6FromCheckIp === 'No IPv6 detected') {
      ipv6FromCheckIp = 'Unavailable';
    }

    // Check query string for IP version
    const urlParams = new URLSearchParams(window.location.search);
    const ipVersion = urlParams.get('ip');
    const showIPv6 = ipVersion === 'v6';

    const setText = (id, value) => {
      const element = document.getElementById(id);
      if (element) element.textContent = value || 'Unknown';
    };

    // Display IP based on query string
    setText('ip-display', showIPv6 ? ipv6FromCheckIp : (ipv4FromCheckIp || data.ipv4));
    setText('hostname', data.hostname);
    setText('isp', data.isp);
    setText('country', data.country);
    setText('region', data.region);
    setText('city', data.city);
    setText('latitude', data.latitude);
    setText('longitude', data.longitude);
    setText('timezone', data.timezone);

    const ua = navigator.userAgent;
    const uaParser = new UAParser(ua);
    setText('user-agent', ua);
    setText('browser', uaParser.getBrowser().name);
    setText('browser-version', uaParser.getBrowser().version);
    setText('os', uaParser.getOS().name);
    setText('os-version', uaParser.getOS().version);
    setText('device', uaParser.getDevice().type || 'desktop');
    setText('device-vendor', uaParser.getDevice().vendor || 'Unknown');
    setText('device-model', uaParser.getDevice().model || 'Unknown');
    setText('cpu-architecture', uaParser.getCPU().architecture || 'Unknown');
    setText('screen-resolution', `${screen.width}x${screen.height}`);
    setText('viewport-size', `${window.innerWidth}x${window.innerHeight}`);
    setText('cookies-enabled', navigator.cookieEnabled ? 'true' : 'false');
    setText('javascript-enabled', 'true');
    setText('language', navigator.language);

    errorMessage.style.display = 'none';
  } catch (error) {
    console.error('Error in loadInfo:', error);
    errorMessage.textContent = `Error: ${error.message}`;
    errorMessage.style.display = 'block';
    elements.forEach(el => el.textContent = 'Error');
  }
}

document.addEventListener('DOMContentLoaded', () => {
  console.log('DOM fully loaded, calling loadInfo');
  loadInfo();
});