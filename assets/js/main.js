async function loadInfo() {
  const response = await fetch('/api/info.php');
  const serverData = await response.json();

  // Helper function to safely set text content
  const setText = (id, value) => {
    const element = document.getElementById(id);
    if (element) element.textContent = value;
  };

  setText('ipv4', serverData.ipv4);
  setText('ipv6', serverData.ipv6);
  setText('hostname', serverData.hostname);
  setText('isp', serverData.isp);
  setText('country', serverData.country);
  setText('region', serverData.region);
  setText('city', serverData.city);
  setText('latitude', serverData.latitude);
  setText('longitude', serverData.longitude);
  setText('timezone', serverData.timezone);

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
}

// Only run if we're on a page that needs this data
if (document.getElementById('connection-info')) {
  window.onload = loadInfo;
}
