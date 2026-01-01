//console.log('Script loaded');
async function loadInfo() {
  const elements = document.querySelectorAll('#connection-info span, #ipv4-display, #ipv6-display');
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
    console.log('IPv4:', data.ipv4);

    // Fetch IPv6 from checkipv6.ipcow.com
    const checkIpV6Response = await fetch('https://checkipv6.ipcow.com/', {
      cache: 'no-store'
    });
    if (!checkIpV6Response.ok) {
      throw new Error('Failed to fetch IPv6 from checkipv6.ipcow.com');
    }
    let ipv6 = await checkIpV6Response.text();
    console.log('IPv6:', ipv6);
    if (ipv6 === 'No IPv6 detected') {
      ipv6 = 'Unavailable';
    }

    const setText = (id, value) => {
      const element = document.getElementById(id);
      if (element) element.textContent = value || 'Unknown';
    };

    // Display both IPs
    setText('ipv4-display', data.ipv4);
    setText('ipv6-display', ipv6);
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

  } catch (error) {
    console.error('Error in loadInfo:', error);
    errorMessage.textContent = `Error: ${error.message}`;
    errorMessage.style.display = 'block';
    elements.forEach(el => el.textContent = 'Error');
  }
}

document.addEventListener('DOMContentLoaded', () => {
  //console.log('DOM fully loaded, calling loadInfo');
  loadInfo();
});