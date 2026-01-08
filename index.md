---
layout: default
title: "What is my IP Address?"
extra_scripts:
  - "https://unpkg.com/leaflet@1.9.4/dist/leaflet.js"
  - "/assets/js/ipcow.js"
---

<div class="section">
    <div id="ipv4-container" class="loading">
        <div class="ip-display" id="ipv4-ip" onclick="copyIP(this)">Loading IPv4...</div>
        <div class="tooltip">Copied!</div>
        <div class="spinner"></div>
    </div>
    <div id="ipv6-container" class="loading" style="margin-top:20px;">
        <div class="ip-display" id="ipv6-ip" onclick="copyIP(this)">Loading IPv6...</div>
        <div class="tooltip">Copied!</div>
        <div class="spinner"></div>
    </div>
</div>

<div class="section">
    <h3>Your ISP Information</h3>
    <div id="ip-details" style="margin-top:30px; display:none;">
        <table id="details-table"></table>
    </div>
</div>

<div class="section">
    <h3>Your Approximate Location</h3>
    <div id="map"></div>
    <p style="font-size:0.6em; color:#777; margin-top:8px;">Location is approximate based on IP geolocation (not
        GPS).</p>
</div>

<div class="section">
    <h3>Your Device & Browser</h3>
    <div id="ua-container">
        <table id="ua-table"></table>
    </div>
</div>

<div style="text-align: center; margin-top: 20px;">
    <button id="export-btn" style="background: var(--progress-success); color: white; border: none; border-radius: 6px; cursor: pointer; font-size: 1em;">Export Data (JSON Format)</button>
</div>