console.log('Script loaded');

async function loadInfo() {
  const elements = document.querySelectorAll('#connection-info span, #ipv4');
  elements.forEach(el => el.textContent = 'Loading...');
  const errorMessage = document.getElementById('error-message');

  try {
    const response = await fetch('/api/info.php');
    if (!response.ok) {
      throw new Error(`HTTP error! status: ${response.status} - ${response.statusText}`);
    }
    const serverData = await response.json();
    console.log('Server data:', serverData);

    if (!serverData.success || serverData.error) {
      throw new Error(serverData.error || 'Failed to load server data.');
    }

    const data = serverData.data;
    const setText = (id, value) => {
      const element = document.getElementById(id);
      if (element) element.textContent = value || 'Unknown';
    };

    // Check query string for IP version
    const urlParams = new URLSearchParams(window.location.search);
    const ipVersion = urlParams.get('ip');
    const showIPv6 = ipVersion === 'v6' && data.ipv6 !== 'Not available';
    setText('ipv4', showIPv6 ? data.ipv6 : data.ipv4);

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