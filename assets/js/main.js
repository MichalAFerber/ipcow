console.log('Script loaded');
async function loadInfo() {
  const elements = document.querySelectorAll('#connection-info span');
  elements.forEach(el => el.textContent = 'Loading...');
  console.log('Starting loadInfo');
  try {
    const response = await fetch('/api/info.php');
    console.log('Fetch response:', response);
    if (!response.ok) {
      throw new Error(`HTTP error! status: ${response.status} - ${response.statusText}`);
    }
    const serverData = await response.json();
    console.log('Server data:', serverData);

    const setText = (id, value) => {
      const element = document.getElementById(id);
      if (element) element.textContent = value || 'Unknown';
    };

    setText('ipv4', serverData.ipv4 || 'Not available');
    setText('ipv6', serverData.ipv6 || 'Not available');
    setText('hostname', serverData.hostname || 'Not available');
    setText('isp', serverData.isp || 'Not available');
    setText('country', serverData.country || 'Not available');
    setText('region', serverData.region || 'Not available');
    setText('city', serverData.city || 'Not available');
    setText('latitude', serverData.latitude || 'Not available');
    setText('longitude', serverData.longitude || 'Not available');
    setText('timezone', serverData.timezone || 'Not available');

    const ua = navigator.userAgent;
    const uaParser = new UAParser(ua);
    setText('user-agent', ua);
    setText('browser', uaParser.getBrowser().name || 'Unknown');
    setText('browser-version', uaParser.getBrowser().version || 'Unknown');
    setText('os', uaParser.getOS().name || 'Unknown');
    setText('os-version', uaParser.getOS().version || 'Unknown');
    setText('device', uaParser.getDevice().type || 'desktop');
    setText('screen-resolution', `${screen.width}x${screen.height}`);
    setText('viewport-size', `${window.innerWidth}x${window.innerHeight}`);
    setText('cookies-enabled', navigator.cookieEnabled ? 'true' : 'false');
    setText('javascript-enabled', 'true');
    setText('language', navigator.language || 'Unknown');
    console.log('LoadInfo completed');
  } catch (error) {
    console.error('Error in loadInfo:', error);
    document.getElementById('error-message').style.display = 'block';
    elements.forEach(el => el.textContent = 'Error loading data');
  }
}

// Use DOMContentLoaded to ensure the DOM is ready
document.addEventListener('DOMContentLoaded', () => {
  console.log('DOM fully loaded, checking connection-info');
  if (document.getElementById('connection-info')) {
    console.log('Connection info section found, calling loadInfo');
    loadInfo();
  } else {
    console.warn('No connection-info section found');
  }
});
