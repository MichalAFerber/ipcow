<?php
require_once __DIR__ . '/utils.php'; // Include utils.php for debugLog()

// Start script
$scriptStartTime = microtime(true);
debugLog("Script started");

// Check for GET parameters
if (!isset($_GET['domain']) || !isset($_GET['h-captcha-response'])) {
    debugLog("Error: Missing domain or hCaptcha response");
    http_response_code(400);
    echo json_encode(['error' => 'Missing domain or hCaptcha response']);
    exit;
}

$domain = htmlspecialchars($_GET['domain']);
$hCaptchaResponse = $_GET['h-captcha-response'];
debugLog("GET parameters received: domain=$domain, hCaptcha response length=" . strlen($hCaptchaResponse));

// Include hCaptcha utility functions
require_once __DIR__ . '/hcaptcha-utils.php';

debugLog("Calling validateHcaptcha");
if (!validateHcaptcha($hCaptchaResponse)) {
    debugLog("Error: hCaptcha validation failed");
    http_response_code(403);
    echo json_encode(['error' => 'hCaptcha validation failed']);
    exit;
}
debugLog("hCaptcha validation result: success");

// Function to get WHOIS server based on TLD
function getWhoisServer($domain) {
    $tld = strtolower(substr(strrchr($domain, '.'), 1));
    $whoisServers = [
        'com' => 'whois.verisign-grs.com',
        'net' => 'whois.verisign-grs.com',
        'edu' => 'whois.verisign-grs.com',
        'org' => 'whois.pir.org',
        'me' => 'whois.nic.me',
        'co' => 'whois.nic.co',
        'io' => 'whois.nic.io',
        'us' => 'whois.nic.us',
        'uk' => 'whois.nic.uk',
        'ca' => 'whois.cira.ca',
        'de' => 'whois.denic.de',
        'fr' => 'whois.nic.fr',
        'nl' => 'whois.domain-registry.nl',
        'au' => 'whois.audns.net.au',
        'nz' => 'whois.srs.net.nz',
        'jp' => 'whois.jprs.jp',
        'kr' => 'whois.kr',
        'br' => 'whois.registro.br',
        'se' => 'whois.iis.se',
        'sg' => 'whois.sgnic.sg',
        'cn' => 'whois.cnnic.cn',
        'ru' => 'whois.tcinet.ru',
        'ua' => 'whois.ua',
        'ch' => 'whois.nic.ch',
        'hk' => 'whois.hkirc.hk',
        'tw' => 'whois.twnic.net.tw',
        'id' => 'whois.pandi.or.id',
        'my' => 'whois.mynic.my',
        'th' => 'whois.thnic.co.th',
        'vn' => 'whois.vnnic.vn',
        'ph' => 'whois.dot.ph',
        'ae' => 'whois.aeda.net.ae',
        'sa' => 'whois.nic.net.sa',
        'il' => 'whois.isoc.org.il',
        'tr' => 'whois.nic.tr',
        'it' => 'whois.nic.it',
        'es' => 'whois.nic.es',
        'pt' => 'whois.dns.pt',
        'pl' => 'whois.dns.pl',
        'cz' => 'whois.nic.cz',
        'at' => 'whois.nic.at',
        'be' => 'whois.dns.be',
        'dk' => 'whois.dk-hostmaster.dk',
        'fi' => 'whois.ficora.fi',
        'no' => 'whois.norid.no',
        'se' => 'whois.iis.se',
        'nl' => 'whois.domain-registry.nl',
        'ru' => 'whois.tcinet.ru',
        'ro' => 'whois.rotld.ro',
        'xyz' => 'whois.nic.xyz',
        'site' => 'whois.nic.site',
        'online' => 'whois.nic.online',
        'store' => 'whois.nic.store',
        'tech' => 'whois.nic.tech',
        'website' => 'whois.nic.website',
        'space' => 'whois.nic.space',
        'press' => 'whois.nic.press',
        'host' => 'whois.nic.host',
        'fun' => 'whois.nic.fun',
        'pw' => 'whois.nic.pw',
        'top' => 'whois.nic.top',
        'club' => 'whois.nic.club',
        'guru' => 'whois.nic.guru',
        'pro' => 'whois.afilias.net',
        'info' => 'whois.afilias.net',
        'mobi' => 'whois.afilias.net',
        'name' => 'whois.nic.name',
        'biz' => 'whois.neulevel.biz',
        'us' => 'whois.nic.us',
        'ws' => 'whois.website.ws',
        'tv' => 'tvwhois.verisign-grs.com',
        'cc' => 'ccwhois.verisign-grs.com',
        'fm' => 'whois.nic.fm',
        'nu' => 'whois.iis.nu',
        'tk' => 'whois.dot.tk',
        'ml' => 'whois.dot.ml',
        'ga' => 'whois.dot.ga',
        'cf' => 'whois.dot.cf',
        'gq' => 'whois.dominio.gq',
        'to' => 'whois.tonic.to',
        'ai' => 'whois.ai',
        'io' => 'whois.nic.io',
        'sh' => 'whois.nic.sh',
        'ac' => 'whois.nic.ac',
        'sc' => 'whois2.afilias-grs.net',
        'bz' => 'whois.belizenic.bz',
        'hn' => 'whois.nic.hn',
        'lc' => 'whois.afilias-grs.info',
        'vc' => 'whois2.afilias-grs.net',
        'mn' => 'whois.nic.mn',
        'pw' => 'whois.nic.pw',
        'tel' => 'whois.nic.tel',
        'xxx' => 'whois.nic.xxx',
        'aero' => 'whois.aero',
        'coop' => 'whois.nic.coop',
        'museum' => 'whois.museum',
        'travel' => 'whois.nic.travel',
        // Add more TLDs as needed
        'default' => 'whois.iana.org' // Fallback for unknown TLDs
    ];
    return $whoisServers[$tld] ?? $whoisServers['default'];
}

// Function to perform WHOIS lookup
function performWhoisLookup($domain, $server, &$whoisTime) {
    $port = 43;
    $fp = @fsockopen($server, $port, $errno, $errstr, 10);
    if (!$fp) {
        debugLog("Error: WHOIS connection failed to $server - $errstr ($errno)");
        return false;
    }

    fputs($fp, "$domain\r\n");
    $whoisData = '';
    $startTime = microtime(true);
    while (!feof($fp)) {
        $whoisData .= fgets($fp, 128);
    }
    fclose($fp);
    $whoisTime = (microtime(true) - $startTime) * 1000;

    debugLog("WHOIS time for $server: " . number_format($whoisTime, 2) . " ms");
    debugLog("WHOIS data received from $server: " . substr($whoisData, 0, 100) . "...");
    return $whoisData;
}

// Perform initial WHOIS lookup
$whoisServer = getWhoisServer($domain);
debugLog("Initial WHOIS server: $whoisServer");
$whoisData = performWhoisLookup($domain, $whoisServer, $whoisTime);

if ($whoisData === false) {
    http_response_code(500);
    echo json_encode(['error' => "WHOIS lookup failed for server: $whoisServer"]);
    exit;
}

// Check for referral to registrar's WHOIS server
if (preg_match('/Registrar WHOIS Server: (.+)/i', $whoisData, $match)) {
    $registrarWhois = trim($match[1]);
    if ($registrarWhois && $registrarWhois !== $whoisServer) {
        debugLog("Referral detected, querying: $registrarWhois");
        $referralData = performWhoisLookup($domain, $registrarWhois, $referralTime);
        if ($referralData !== false) {
            $whoisData = $referralData; // Use referral data if successful
            $whoisTime = $referralTime; // Update time to reflect referral lookup
        } else {
            debugLog("Referral lookup failed, sticking with initial data");
        }
    }
}

// Calculate total time
$totalTime = (microtime(true) - $scriptStartTime) * 1000;
debugLog("Total time: " . number_format($totalTime, 2) . " ms");

// Return response
header('Content-Type: application/json');
echo json_encode([
    'success' => true,
    'domain' => $domain,
    'whois' => trim($whoisData),
    'whois_time_ms' => $whoisTime,
    'total_time_ms' => $totalTime
]);
?>