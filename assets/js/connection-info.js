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

    setText('ipv4', data.ipv4);
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
    setText('screen-resolution', `${screen.width}x${screen.height}`);
    setText('viewport-size', `${window.innerWidth}x${window.innerHeight}`);
    setText('cookies-enabled', navigator.cookieEnabled ? 'true' : 'false');
    setText('javascript-enabled', 'true');
    setText('language', navigator.language);
    setText('engine', uaParser.getEngine().name);
    setText('device-vendor', uaParser.getDevice().vendor || 'Unknown');
    setText('device-model', uaParser.getDevice().model || 'Unknown');
    setText('cpu-architecture', uaParser.getCPU().architecture || 'Unknown');
    
    // IP toggle functionality
    const toggleButton = document.getElementById('toggle-ip');
    const ipDisplay = document.getElementById('ipv4');
    let showingIPv6 = false;
    if (data.ipv6 !== 'Not available') {
      toggleButton.addEventListener('click', () => {
        showingIPv6 = !showingIPv6;
        ipDisplay.textContent = showingIPv6 ? data.ipv6 : data.ipv4;
        toggleButton.textContent = showingIPv6 ? 'Show IPv4' : 'Show IPv6';
      });
    } else {
      toggleButton.style.display = 'none';
    }

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