async function loadInfo() {
  const response = await fetch('/api/info.php');
  const serverData = await response.json();
  document.getElementById('ipv4').textContent = serverData.ipv4;
  document.getElementById('ipv6').textContent = serverData.ipv6;
  document.getElementById('hostname').textContent = serverData.hostname;
  document.getElementById('isp').textContent = serverData.isp;
  document.getElementById('country').textContent = serverData.country;
  document.getElementById('region').textContent = serverData.region;
  document.getElementById('city').textContent = serverData.city;
  document.getElementById('latitude').textContent = serverData.latitude;
  document.getElementById('longitude').textContent = serverData.longitude;
  document.getElementById('timezone').textContent = serverData.timezone;

  const ua = navigator.userAgent;
  const uaParser = new UAParser(ua);
  document.getElementById('user-agent').textContent = ua;
  document.getElementById('browser').textContent = uaParser.getBrowser().name || 'Unknown';
  document.getElementById('browser-version').textContent = uaParser.getBrowser().version || 'Unknown';
  document.getElementById('os').textContent = uaParser.getOS().name || 'Unknown';
  document.getElementById('os-version').textContent = uaParser.getOS().version || 'Unknown';
  document.getElementById('device').textContent = uaParser.getDevice().type || 'desktop';
  document.getElementById('screen-resolution').textContent = `${screen.width}x${screen.height}`;
  document.getElementById('viewport-size').textContent = `${window.innerWidth}x${window.innerHeight}`;
  document.getElementById('cookies-enabled').textContent = navigator.cookieEnabled ? 'true' : 'false';
  document.getElementById('javascript-enabled').textContent = 'true';
  document.getElementById('language').textContent = navigator.language || 'Unknown';
}
window.onload = loadInfo;
