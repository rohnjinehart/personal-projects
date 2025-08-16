<?php
// Require session management file and ensure user is logged in
require_once 'session.php';
requireLogin();

require_once 'authFunctions.php';

$isEmployee = isEmployee();
$isSupervisor = isSupervisor();
$isAdmin = isAdmin();

?>
<!DOCTYPE html>
<html lang="en">
<head>
    <!-- Basic meta tags for character set and responsive viewport -->
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Report Map</title>
    
    <!-- External CSS dependencies -->
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/css/bootstrap.min.css" rel="stylesheet">
    <link rel="stylesheet" href="https://unpkg.com/leaflet@1.9.4/dist/leaflet.css" />  <!-- Leaflet map CSS with specific version -->
    <link rel="stylesheet" href="https://unpkg.com/leaflet.markercluster@1.4.1/dist/MarkerCluster.css" />  <!-- Marker clustering -->
    <link rel="stylesheet" href="https://unpkg.com/leaflet.markercluster@1.4.1/dist/MarkerCluster.Default.css" />
    <link rel="stylesheet" href="https://cdn.jsdelivr.net/npm/bootstrap-icons@1.10.0/font/bootstrap-icons.css">  <!-- Bootstrap icons -->
    
    <!-- Custom CSS styles -->
    <style>
        /* Map container styling */
        #map-container {
            scroll-margin-top: 20px;
            margin-bottom: 30px;
            position: relative;
        }
        
        /* Main map element styling - IMPORTANT added */
        #map { 
            height: 500px !important;
            width: 100% !important;
            border-radius: 8px;
            box-shadow: 0 4px 8px rgba(0,0,0,0.1);
        }
        
        /* Loading state for map */
        #map.loading {
            opacity: 0.7;
            pointer-events: none;
        }
        
        /* Debug console styling */
        #debug-console {
            position: fixed;
            bottom: 0;
            left: 0;
            right: 0;
            background: #f8f9fa;
            border-top: 1px solid #ddd;
            padding: 10px;
            max-height: 200px;
            overflow-y: auto;
            display: none;
            z-index: 2000;
        }
        
        /* Rest of your existing styles... */
        .form-container {
            background-color: #f8f9fa;
            padding: 20px;
            border-radius: 8px;
            box-shadow: 0 2px 4px rgba(0,0,0,0.05);
            margin-bottom: 30px;
        }
        
        #success-message {
            display: none;
            position: fixed;
            top: 20px;
            left: 50%;
            transform: translateX(-50%);
            background-color: #4CAF50;
            color: white;
            padding: 15px 25px;
            border-radius: 5px;
            z-index: 1000;
            box-shadow: 0 4px 8px rgba(0,0,0,0.1);
        }
        
        .page-header {
            margin-bottom: 30px;
            padding-bottom: 15px;
            border-bottom: 1px solid #eee;
        }
        
        .form-label {
            font-weight: 500;
        }
        
        .btn-primary {
            background-color: #0d6efd;
            border-color: #0d6efd;
        }
        
        .btn-outline-primary {
            color: #0d6efd;
            border-color: #0d6efd;
        }
        
        .btn-outline-primary:hover {
            background-color: #0d6efd;
            color: white;
        }
        
        .button-container {
            display: flex;
            gap: 10px;
        }
        
        .button-container button {
            flex: 1;
        }
        
        .highlighted-marker {
            filter: drop-shadow(0 0 8px yellow) !important;
            transform: scale(1.2);
            transition: all 0.3s ease;
            z-index: 1000 !important;
        }
        
        .coordinates-display {
            background-color: #f8f9fa;
            padding: 10px;
            border-radius: 4px;
            margin-top: 10px;
            font-family: monospace;
        }
        
        .user-menu {
            position: absolute;
            top: 20px;
            right: 20px;
            z-index: 1000;
        }
        
        .user-menu-btn {
            background: none;
            border: none;
            font-size: 1.5rem;
            color: #6c757d;
            padding: 5px 10px;
            border-radius: 50%;
            transition: all 0.2s ease;
        }
        
        .user-menu-btn:hover {
            background-color: rgba(108, 117, 125, 0.1);
        }

        #geolocate-btn {
            position: absolute;
            top: 10px;
            right: 10px;
            z-index: 1000;
            padding: 8px 12px;
            border-radius: 4px;
            cursor: pointer;
        }

        #geolocate-btn.loading .bi {
            animation: spin 1s linear infinite;
        }

        #geolocate-btn.active {
            background-color: #28a745;
            border-color: #28a745;
        }

        @keyframes spin {
            from { transform: rotate(0deg); }
            to { transform: rotate(360deg); }
        }

        .user-location-marker {
            background: none;
            border: none;
        }

        .pulse-dot {
            width: 20px;
            height: 20px;
            border-radius: 50%;
            background-color: #0078A8;
            box-shadow: 0 0 0 0 rgba(0, 120, 168, 1);
            transform: scale(1);
            animation: pulse 2s infinite;
        }

        @keyframes pulse {
            0% {
                transform: scale(0.95);
                box-shadow: 0 0 0 0 rgba(0, 120, 168, 0.7);
            }
            70% {
                transform: scale(1.3);
                box-shadow: 0 0 0 10px rgba(0, 120, 168, 0);
            }
            100% {
                transform: scale(0.95);
                box-shadow: 0 0 0 0 rgba(0, 120, 168, 0);
            }
        }

        .image-preview-container {
            display: flex;
            flex-wrap: wrap;
            gap: 10px;
            margin-top: 10px;
        }

        .image-preview {
            position: relative;
            width: 100px;
            height: 100px;
            border: 1px solid #ddd;
            border-radius: 4px;
            overflow: hidden;
        }

        .image-preview img {
            width: 100%;
            height: 100%;
            object-fit: cover;
        }

        .remove-image {
            position: absolute;
            top: 2px;
            right: 2px;
            background: rgba(0,0,0,0.5);
            color: white;
            border: none;
            border-radius: 50%;
            width: 20px;
            height: 20px;
            display: flex;
            align-items: center;
            justify-content: center;
            cursor: pointer;
        }
    </style>
</head>
<body>
    <!-- User dropdown menu -->
    <div class="user-menu">
        <div class="dropdown">
            <button class="user-menu-btn dropdown-toggle" type="button" id="userMenu" data-bs-toggle="dropdown" aria-expanded="false">
                <i class="bi bi-gear-fill"></i>
            </button>
            <ul class="dropdown-menu dropdown-menu-end" aria-labelledby="userMenu">
                <li><a class="dropdown-item" href="profile.php"><i class="bi bi-person-circle me-2"></i>Profile</a></li>
                <li><hr class="dropdown-divider"></li>
                <li><a class="dropdown-item" href="logout.php"><i class="bi bi-box-arrow-right me-2"></i>Logout</a></li>
            </ul>
        </div>
    </div>

    <!-- Main content container -->
    <div class="container py-4">
        <!-- Page header section -->
        <div class="page-header text-center">
            <h1 class="display-4">GridFix</h1>
            <p class="lead">Submit and view reports in your area</p>
        </div>

        <!-- Success message (initially hidden) -->
        <div id="success-message">Your report has been submitted successfully!</div>

        <!-- Main content row -->
        <div class="row">
            <!-- Left column - Report form -->
            <div class="col-lg-6">
                <div class="form-container">
                    <form id="report-form" action="Save_Reports.php" method="POST" enctype="multipart/form-data" onsubmit="return handleFormSubmit(event)">
                        <!-- Report title field -->
                        <div class="mb-3">
                            <label for="title" class="form-label">Title</label>
                            <input type="text" class="form-control" name="title" id="title" maxlength="100" required>
                        </div>
                        
                        <!-- Report description field -->
                        <div class="mb-3">
                            <label for="description" class="form-label">Description</label>
                            <textarea class="form-control" name="description" id="description" rows="3"></textarea>
                        </div>
                        
                        <!-- Address field -->
                        <div class="mb-3">
                            <label for="address" class="form-label">Address</label>
                            <input type="text" class="form-control" name="address" id="address" required>
                        </div>

                        <!-- Photo upload field -->
                        <div class="mb-3">
                            <label for="photos" class="form-label">Upload Photos (Max 5MB each)</label>
                            <input type="file" class="form-control" name="photos[]" id="photos" multiple accept="image/*">
                            <div class="form-text">You can upload multiple photos (JPEG, PNG, GIF)</div>
                            <div id="image-preview-container" class="image-preview-container"></div>
                        </div>
                        
                        <!-- Hidden fields for coordinates -->
                        <input type="hidden" name="latitude" id="latitude" value="30.6280">
                        <input type="hidden" name="longitude" id="longitude" value="-96.3344">
                        
                        <!-- Coordinates display -->
                        <div class="coordinates-display">
                            <strong>Location Coordinates:</strong>
                            <div id="coordinates-text">Lat: 30.6280, Lng: -96.3344 (default)</div>
                        </div>

                        <!-- Form buttons - show different buttons based on role -->
                        <div class="d-grid gap-2 d-md-flex mt-3">
                            <?php if ($isEmployee || $isSupervisor || $isAdmin): ?>
                                <button type="submit" class="btn btn-primary me-md-2" id="submit-button">
                                    <i class="bi bi-save"></i> Save Report
                                </button>
                            <?php endif; ?>
                            
                            <button type="button" class="btn btn-outline-primary" onclick="window.location.href='View_reports.php'">
                                <i class="bi bi-eye"></i> View Reports
                            </button>
                        </div>
                    </form>
                </div>
            </div>
            
            <!-- Right column - Instructions -->
            <div class="col-lg-6">
                <div class="alert alert-info">
                    <h5 class="alert-heading">How to use this map</h5>
                    <p>Click on the map to select a custom location for your report. The coordinates will update automatically.</p>
                    <p>Click the <i class="bi bi-geo-alt"></i> button on the map to find your current location and see nearby reports.</p>
                    <p>You can upload multiple photos (up to 5MB each) to document the issue.</p>
                </div>
            </div>
        </div>

        <!-- Map section -->
        <div class="row">
            <div class="col-12">
                <h4 class="mb-3">Report Locations</h4>
                <div id="map-container">
                    <div id="map"></div>
                    <div id="geolocate-btn" class="btn btn-primary">
                        <i class="bi bi-geo-alt"></i> Find Me
                    </div>
                </div>
            </div>
        </div>
    </div>

    <!-- Debug console (toggle with Ctrl+Shift+D) -->
    <div id="debug-console"></div>

    <!-- JavaScript dependencies -->
    <script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/js/bootstrap.bundle.min.js"></script>
    <script src="https://unpkg.com/leaflet@1.9.4/dist/leaflet.js"></script>
    <script src="https://unpkg.com/leaflet.markercluster@1.4.1/dist/leaflet.markercluster.js"></script>
    
    <!-- Main application JavaScript -->
    <script>
        // Debugging functions
        function debugLog(message) {
            const debugConsole = document.getElementById('debug-console');
            if (debugConsole) {
                const entry = document.createElement('div');
                entry.textContent = `[${new Date().toLocaleTimeString()}] ${message}`;
                debugConsole.appendChild(entry);
                debugConsole.scrollTop = debugConsole.scrollHeight;
            }
            console.log(message);
        }

        // Toggle debug console with Ctrl+Shift+D
        document.addEventListener('keydown', function(e) {
            if (e.ctrlKey && e.shiftKey && e.key === 'D') {
                const debugConsole = document.getElementById('debug-console');
                debugConsole.style.display = debugConsole.style.display === 'none' ? 'block' : 'none';
            }
        });

        // Initialize debug console
        document.getElementById('debug-console').style.display = 'none';
        debugLog('Application starting...');

        // Define map boundaries for College Station area
        const collegeStationBounds = L.latLngBounds(
            L.latLng(30.55, -96.45),
            L.latLng(30.70, -96.20)
        );

        // Initialize the map with error handling
        let map;
        try {
            debugLog('Initializing map...');
            map = L.map('map', {
                center: [30.6280, -96.3344],
                zoom: 12,
                maxBounds: collegeStationBounds,
                maxBoundsViscosity: 1.0,
                minZoom: 12
            });
            debugLog('Map initialized successfully');

            // Add OpenStreetMap base layer
            L.tileLayer('https://{s}.tile.openstreetmap.org/{z}/{x}/{y}.png', {
                attribution: '© OpenStreetMap contributors'
            }).addTo(map);
            debugLog('Tile layer added to map');
        } catch (e) {
            debugLog('Map initialization failed: ' + e.message);
            alert('Failed to initialize map. Please check console for details.');
            throw e;
        }

        // Variables for map markers
        let marker = null;
        const markers = L.markerClusterGroup();
        let markersLoaded = false;
        let userLocationMarker = null;
        let geolocationWatchId = null;
        let isGeolocationActive = false;
        const geolocationRadius = 0.5; // 0.5 mile radius

        // Rate limiting variables for geocoding
        let lastGeocodeTime = 0;
        const geocodeRateLimit = 1000; // 1 second in milliseconds
        let geocodeQueue = [];
        let isProcessingQueue = false;

        // Update the coordinate display text
        function updateCoordinateDisplay(lat, lng, isDefault = false) {
            const coordinatesText = document.getElementById('coordinates-text');
            coordinatesText.textContent = `Lat: ${lat.toFixed(4)}, Lng: ${lng.toFixed(4)}${isDefault ? ' (default)' : ''}`;
            debugLog(`Updated coordinates display: ${lat}, ${lng}`);
        }

        // Function to reverse geocode coordinates to address with rate limiting
        async function getAddressFromCoordinates(lat, lng) {
            debugLog(`Starting geocode for coordinates: ${lat}, ${lng}`);
            const now = Date.now();
            const timeSinceLastCall = now - lastGeocodeTime;
            
            // If we're calling too soon, queue the request
            if (timeSinceLastCall < geocodeRateLimit) {
                debugLog(`Geocode rate limited, adding to queue (${geocodeQueue.length} items in queue)`);
                return new Promise((resolve) => {
                    geocodeQueue.push({ lat, lng, resolve });
                    if (!isProcessingQueue) {
                        processGeocodeQueue();
                    }
                });
            }
            
            // Otherwise make the request immediately
            lastGeocodeTime = now;
            return await fetchAddress(lat, lng);
        }

        // Process the geocode queue with proper rate limiting
        async function processGeocodeQueue() {
            if (geocodeQueue.length === 0 || isProcessingQueue) return;
            
            isProcessingQueue = true;
            const { lat, lng, resolve } = geocodeQueue.shift();
            
            // Wait until we can make the next request
            const now = Date.now();
            const timeSinceLastCall = now - lastGeocodeTime;
            const delayNeeded = Math.max(0, geocodeRateLimit - timeSinceLastCall);
            
            await new Promise(resolve => setTimeout(resolve, delayNeeded));
            
            lastGeocodeTime = Date.now();
            const address = await fetchAddress(lat, lng);
            resolve(address);
            
            isProcessingQueue = false;
            
            // Process next item in queue if any
            if (geocodeQueue.length > 0) {
                setTimeout(processGeocodeQueue, 0);
            }
        }

        // Actual function to fetch address from Nominatim
        async function fetchAddress(lat, lng) {
            try {
                debugLog(`Fetching address from Nominatim for ${lat}, ${lng}`);
                const response = await fetch(`https://nominatim.openstreetmap.org/reverse?format=json&lat=${lat}&lon=${lng}&zoom=18&addressdetails=1`);
                
                if (!response.ok) {
                    throw new Error(`HTTP error! status: ${response.status}`);
                }
                
                const data = await response.json();
                
                if (data.error) {
                    console.error('Geocoding error:', data.error);
                    debugLog(`Geocoding error: ${data.error}`);
                    return "Address not found";
                }
                
                // Construct address from available components
                const address = [];
                if (data.address.road) address.push(data.address.road);
                if (data.address.house_number) address.push(data.address.house_number);
                if (address.length === 0 && data.address.suburb) address.push(data.address.suburb);
                if (address.length === 0 && data.address.city) address.push(data.address.city);
                
                const result = address.length > 0 ? address.join(' ') : "Address not found";
                debugLog(`Geocode result: ${result}`);
                return result;
            } catch (error) {
                console.error('Error fetching address:', error);
                debugLog(`Geocode failed: ${error.message}`);
                return "Address lookup failed";
            }
        }

        // Handle map clicks to place markers and get address
        map.on('click', async function(e) {
            const { lat, lng } = e.latlng;
            debugLog(`Map clicked at ${lat}, ${lng}`);

            // Update hidden form fields
            document.getElementById('latitude').value = lat;
            document.getElementById('longitude').value = lng;

            // Update coordinate display
            updateCoordinateDisplay(lat, lng);

            // Remove existing marker if present
            if (marker) {
                map.removeLayer(marker);
            }

            // Add new marker at clicked location
            marker = L.marker([lat, lng]).addTo(map);
            debugLog(`Added marker at ${lat}, ${lng}`);
            
            // Show loading message while we fetch the address
            const loadingMessage = "Loading address...";
            marker.bindPopup(`<b>Selected Location</b><br>Lat: ${lat.toFixed(4)}<br>Lng: ${lng.toFixed(4)}<br>${loadingMessage}`)
                  .openPopup();
            
            // Get address and update the form field and popup
            const address = await getAddressFromCoordinates(lat, lng);
            document.getElementById('address').value = address;
            
            // Update marker popup with the address
            marker.setPopupContent(`<b>Selected Location</b><br>Lat: ${lat.toFixed(4)}<br>Lng: ${lng.toFixed(4)}<br>Address: ${address}`);
        });

        // Initialize coordinate display with default values
        updateCoordinateDisplay(30.6280, -96.3344, true);

        // Load reports from server and display as clustered markers
        function reloadMarkers() {
            return new Promise((resolve, reject) => {
                debugLog('Loading markers...');
                // Show loading state
                document.getElementById('map').classList.add('loading');
                
                // First remove all existing markers from the map
                if (map.hasLayer(markers)) {
                    map.removeLayer(markers);
                }
                markers.clearLayers();
                
                fetch('Get_Reports.php')
                    .then(response => {
                        if (!response.ok) {
                            throw new Error(`HTTP error! status: ${response.status}`);
                        }
                        return response.json();
                    })
                    .then(data => {
                        debugLog(`Received ${data.length} reports from server`);
                        // Filter to show only active reports
                        data = data.filter(report => {
                            if (!report || !report.status) {
                                debugLog('Skipping invalid report (missing data)');
                                return false;
                            }
                            const shouldShow = report.status.toLowerCase() === 'pending' || 
                                           report.status.toLowerCase() === 'accepted';
                            if (!shouldShow) {
                                debugLog(`Skipping report ${report.id} with status ${report.status}`);
                            }
                            return shouldShow;
                        });
                        
                        debugLog(`Displaying ${data.length} active reports`);
                        data.forEach(report => {
                            const reportMarker = L.marker([report.latitude, report.longitude], {
                                reportId: report.id,
                                title: report.title
                            });

                            let popupContent = `
                                <div class="report-popup">
                                    <h5>${report.title}</h5>
                                    <p>${report.description}</p>
                                    <p>Status: ${report.status}</p>
                            `;

                            // Add photos to popup if they exist
                            if (report.photos && report.photos.length > 0) {
                                popupContent += `<div class="report-photos" style="margin: 10px 0;">`;
                                report.photos.forEach(photo => {
                                    popupContent += `<img src="${photo.file_path}" style="max-width: 100%; margin-bottom: 5px;">`;
                                });
                                popupContent += `</div>`;
                            }

                            if (<?php echo $isEmployee ? 'true' : 'false'; ?> && report.status.toLowerCase() === 'pending') {
                                popupContent += `
                                    <button class="btn btn-sm btn-success" onclick="performReportAction('accept', ${report.id})">
                                        Accept Report
                                    </button>
                                `;
                            }

                            if ((<?php echo $isSupervisor || $isAdmin ? 'true' : 'false'; ?>) && report.status.toLowerCase() === 'accepted') {
                                popupContent += `
                                    <button class="btn btn-sm btn-primary" onclick="performReportAction('complete', ${report.id})">
                                        Mark as Complete
                                    </button>
                                `;
                            }

                            popupContent += `</div>`;

                            reportMarker.bindPopup(popupContent);
                            markers.addLayer(reportMarker);
                        });

                        map.addLayer(markers);
                        markersLoaded = true;
                        debugLog('Markers added to map');
                        resolve();
                    })
                    .catch(error => {
                        debugLog('Error loading reports: ' + error.message);
                        reject(error);
                    })
                    .finally(() => {
                        // Hide loading state
                        document.getElementById('map').classList.remove('loading');
                    });
            });
        }

        // Modified version of reloadMarkers that accepts geolocation parameters
        function reloadMarkersWithGeolocation(lat, lng) {
            return new Promise((resolve, reject) => {
                debugLog(`Loading markers with geolocation filter (${lat}, ${lng})`);
                document.getElementById('map').classList.add('loading');
                
                if (map.hasLayer(markers)) {
                    map.removeLayer(markers);
                }
                markers.clearLayers();
                
                fetch(`Get_Reports.php?userLat=${lat}&userLng=${lng}&radius=${geolocationRadius}`)
                    .then(response => {
                        if (!response.ok) {
                            throw new Error(`HTTP error! status: ${response.status}`);
                        }
                        return response.json();
                    })
                    .then(data => {
                        debugLog(`Received ${data.length} geofiltered reports`);
                        data = data.filter(report => {
                            if (!report || !report.status) {
                                debugLog('Skipping invalid report (missing data)');
                                return false;
                            }
                            const shouldShow = report.status.toLowerCase() === 'pending' || 
                                           report.status.toLowerCase() === 'accepted';
                            if (!shouldShow) {
                                debugLog(`Skipping report ${report.id} with status ${report.status}`);
                            }
                            return shouldShow;
                        });
                        
                        debugLog(`Displaying ${data.length} active geofiltered reports`);
                        data.forEach(report => {
                            const reportMarker = L.marker([report.latitude, report.longitude], {
                                reportId: report.id,
                                title: report.title
                            });

                            let popupContent = `
                                <div class="report-popup">
                                    <h5>${report.title}</h5>
                                    <p>${report.description}</p>
                                    <p>Status: ${report.status}</p>
                            `;

                            if (report.photos && report.photos.length > 0) {
                                popupContent += `<div class="report-photos" style="margin: 10px 0;">`;
                                report.photos.forEach(photo => {
                                    popupContent += `<img src="${photo.file_path}" style="max-width: 100%; margin-bottom: 5px;">`;
                                });
                                popupContent += `</div>`;
                            }

                            if (<?php echo $isEmployee ? 'true' : 'false'; ?> && report.status.toLowerCase() === 'pending') {
                                popupContent += `
                                    <button class="btn btn-sm btn-success" onclick="performReportAction('accept', ${report.id})">
                                        Accept Report
                                    </button>
                                `;
                            }

                            if ((<?php echo $isSupervisor || $isAdmin ? 'true' : 'false'; ?>) && report.status.toLowerCase() === 'accepted') {
                                popupContent += `
                                    <button class="btn btn-sm btn-primary" onclick="performReportAction('complete', ${report.id})">
                                        Mark as Complete
                                    </button>
                                `;
                            }

                            popupContent += `</div>`;

                            reportMarker.bindPopup(popupContent);
                            markers.addLayer(reportMarker);
                        });

                        map.addLayer(markers);
                        markersLoaded = true;
                        debugLog('Geofiltered markers added to map');
                        resolve();
                    })
                    .catch(error => {
                        debugLog('Error loading geofiltered reports: ' + error.message);
                        reject(error);
                    })
                    .finally(() => {
                        document.getElementById('map').classList.remove('loading');
                    });
            });
        }

        // Toggle geolocation functionality
        function toggleGeolocation() {
            debugLog('Toggling geolocation');
            const geolocateBtn = document.getElementById('geolocate-btn');
            
            if (isGeolocationActive) {
                // Turn off geolocation
                debugLog('Turning off geolocation');
                if (geolocationWatchId !== null) {
                    navigator.geolocation.clearWatch(geolocationWatchId);
                    geolocationWatchId = null;
                }
                
                if (userLocationMarker) {
                    map.removeLayer(userLocationMarker);
                    userLocationMarker = null;
                }
                
                // Remove any radius circle
                map.eachLayer(layer => {
                    if (layer.options && layer.options.radiusCircle) {
                        map.removeLayer(layer);
                    }
                });
                
                geolocateBtn.classList.remove('active', 'loading');
                isGeolocationActive = false;
                
                // Reload all markers without geolocation filter
                reloadMarkers();
            } else {
                // Turn on geolocation
                debugLog('Turning on geolocation');
                geolocateBtn.classList.add('loading');
                
                if (navigator.geolocation) {
                    geolocationWatchId = navigator.geolocation.watchPosition(
                        (position) => {
                            const { latitude, longitude } = position.coords;
                            debugLog(`Geolocation position: ${latitude}, ${longitude}`);
                            
                            // Update button state
                            geolocateBtn.classList.remove('loading');
                            geolocateBtn.classList.add('active');
                            isGeolocationActive = true;
                            
                            // Remove previous user location marker if exists
                            if (userLocationMarker) {
                                map.removeLayer(userLocationMarker);
                            }
                            
                            // Add new user location marker
                            userLocationMarker = L.marker([latitude, longitude], {
                                icon: L.divIcon({
                                    className: 'user-location-marker',
                                    html: '<div class="pulse-dot"></div>',
                                    iconSize: [20, 20]
                                })
                            }).addTo(map);
                            
                            // Add radius circle
                            map.eachLayer(layer => {
                                if (layer.options && layer.options.radiusCircle) {
                                    map.removeLayer(layer);
                                }
                            });
                            
                            L.circle([latitude, longitude], {
                                radius: geolocationRadius * 1609.34, // Convert miles to meters
                                color: '#0078A8',
                                fillColor: '#0078A8',
                                fillOpacity: 0.2,
                                radiusCircle: true
                            }).addTo(map);
                            
                            // Zoom to user location with the radius visible
                            map.fitBounds([
                                [latitude - geolocationRadius * 0.015, longitude - geolocationRadius * 0.015],
                                [latitude + geolocationRadius * 0.015, longitude + geolocationRadius * 0.015]
                            ]);
                            
                            // Reload markers with geolocation filter
                            reloadMarkersWithGeolocation(latitude, longitude);
                        },
                        (error) => {
                            debugLog('Geolocation error: ' + error.message);
                            console.error('Geolocation error:', error);
                            geolocateBtn.classList.remove('loading');
                            alert('Unable to get your location. Please ensure location services are enabled.');
                        },
                        {
                            enableHighAccuracy: true,
                            maximumAge: 30000,
                            timeout: 10000
                        }
                    );
                } else {
                    debugLog('Geolocation not supported by browser');
                    geolocateBtn.classList.remove('loading');
                    alert('Geolocation is not supported by your browser.');
                }
            }
        }

        // Highlight a specific marker by report ID
        function highlightMarkerById(reportId) {
            debugLog(`Attempting to highlight marker for report ${reportId}`);
            let attempts = 0;
            const maxAttempts = 5;
            
            const tryHighlight = () => {
                attempts++;
                let found = false;
                
                markers.eachLayer(layer => {
                    if (found) return;
                    
                    if (layer.options && layer.options.reportId == reportId) {
                        found = true;
                        map.setView(layer.getLatLng(), 18);
                        
                        if (layer.__parent) {
                            layer.__parent.zoomToBounds();
                        }
                        
                        const icon = layer.getElement();
                        if (icon) {
                            icon.classList.add('highlighted-marker');
                            setTimeout(() => {
                                layer.openPopup();
                            }, 500);
                            debugLog(`Highlighted marker for report ${reportId}`);
                        }
                    }
                });
                
                if (!found && attempts < maxAttempts) {
                    debugLog(`Marker not found yet (attempt ${attempts}), trying again...`);
                    setTimeout(tryHighlight, 500);
                } else if (!found) {
                    debugLog(`Failed to find marker for report ${reportId} after ${maxAttempts} attempts`);
                }
            };
            
            tryHighlight();
        }

        // Check for report to highlight from localStorage or URL
        function checkForReportLocation() {
            debugLog('Checking for report to highlight');
            const storedReport = localStorage.getItem('reportToView');
            if (storedReport) {
                const reportData = JSON.parse(storedReport);
                localStorage.removeItem('reportToView');
                debugLog(`Found stored report to highlight: ${reportData.reportId}`);
                
                // Only reload markers if they haven't been loaded yet
                if (!markersLoaded) {
                    reloadMarkers().then(() => {
                        highlightMarkerById(reportData.reportId);
                    }).catch(error => {
                        debugLog('Error loading markers: ' + error.message);
                        console.error('Error loading markers:', error);
                    });
                } else {
                    highlightMarkerById(reportData.reportId);
                }
            }
            
            // Check URL parameters for report to highlight
            const urlParams = new URLSearchParams(window.location.search);
            const reportId = urlParams.get('highlight_report');
            const lat = urlParams.get('lat');
            const lng = urlParams.get('lng');
            
            // Check localStorage for saved location
            const storedLocation = localStorage.getItem('reportLocation');
            let locationData;
            
            if (reportId && lat && lng) {
                locationData = {
                    latitude: parseFloat(lat),
                    longitude: parseFloat(lng),
                    reportId: reportId
                };
                debugLog(`Found URL parameters for report location: ${lat}, ${lng}, report ${reportId}`);
            } else if (storedLocation) {
                locationData = JSON.parse(storedLocation);
                debugLog(`Found stored report location in localStorage`);
            }
            
            if (locationData) {
                const { latitude, longitude, reportId } = locationData;
                
                document.getElementById('map-container').scrollIntoView({ behavior: 'smooth' });
                
                map.setView([latitude, longitude], 18, {
                    animate: true,
                    duration: 1
                });

                const highlightMarker = (marker) => {
                    document.querySelectorAll('.highlighted-marker').forEach(el => {
                        el.classList.remove('highlighted-marker');
                    });
                    
                    const icon = marker.getElement();
                    if (icon) {
                        icon.classList.add('highlighted-marker');
                        icon.style.zIndex = '1000';
                        icon.style.filter = 'drop-shadow(0 0 8px yellow)';
                        debugLog(`Highlighted marker for report ${reportId}`);
                    }
                    
                    marker.openPopup();
                };

                const findMarkerById = () => {
                    let foundMarker = null;
                    
                    markers.eachLayer(layer => {
                        if (foundMarker) return;
                        
                        if (layer.options && layer.options.reportId == reportId) {
                            foundMarker = layer;
                        }
                    });
                    
                    if (foundMarker) {
                        if (foundMarker.__parent) {
                            map.setView(foundMarker.getLatLng(), 18);
                            setTimeout(() => {
                                highlightMarker(foundMarker);
                            }, 300);
                        } else {
                            highlightMarker(foundMarker);
                        }
                    } else {
                        debugLog(`Marker not found yet, trying again...`);
                        setTimeout(findMarkerById, 200);
                    }
                };

                setTimeout(findMarkerById, 500);
                
                localStorage.removeItem('reportLocation');
                
                if (window.history.replaceState) {
                    const cleanUrl = window.location.pathname;
                    window.history.replaceState({}, document.title, cleanUrl);
                }
            }
        }

        // Handle form submission
        function handleFormSubmit(event) {
            event.preventDefault();
            debugLog('Handling form submission');
            
            const form = event.target;
            const formData = new FormData(form);
            const submitButton = document.getElementById('submit-button');
            const fileInput = document.getElementById('photos');
            const files = fileInput.files;
            
            // Validate file sizes
            for (let i = 0; i < files.length; i++) {
                if (files[i].size > 5 * 1024 * 1024) { // 5MB limit
                    debugLog(`File too large: ${files[i].name} (${files[i].size} bytes)`);
                    alert(`File "${files[i].name}" is too large. Maximum size is 5MB.`);
                    return;
                }
            }
            
            submitButton.disabled = true;
            submitButton.innerHTML = '<span class="spinner-border spinner-border-sm" role="status" aria-hidden="true"></span> Submitting...';
            
            fetch(form.action, {
                method: 'POST',
                body: formData
            })
            .then(response => {
                if (!response.ok) {
                    throw new Error('Network response was not ok');
                }
                return response.json();
            })
            .then(data => {
                debugLog('Form submission response:', data);
                
                if (data.success) {
                    const successMessage = document.getElementById('success-message');
                    successMessage.textContent = data.message || 'Report submitted successfully!';
                    successMessage.style.display = 'block';
                    debugLog('Report submitted successfully');
                    
                    setTimeout(() => {
                        successMessage.style.display = 'none';
                    }, 5000);
                    
                    form.reset();
                    document.getElementById('latitude').value = '30.6280';
                    document.getElementById('longitude').value = '-96.3344';
                    updateCoordinateDisplay(30.6280, -96.3344, true);
                    
                    if (marker) {
                        map.removeLayer(marker);
                        marker = null;
                    }
                    
                    // Clear image previews
                    document.getElementById('image-preview-container').innerHTML = '';
                    
                    // Reload markers after successful submission
                    reloadMarkers();
                    
                    // Highlight the new marker if reportId is provided
                    if (data.reportId) {
                        highlightMarkerById(data.reportId);
                    }
                } else {
                    throw new Error(data.message || 'Submission failed');
                }
            })
            .catch(error => {
                debugLog('Form submission error: ' + error.message);
                console.error('Submission error:', error);
                alert(`Error: ${error.message}`);
            })
            .finally(() => {
                submitButton.disabled = false;
                submitButton.innerHTML = '<i class="bi bi-save"></i> Save Report';
            });
        }

        // Handle report actions
        function performReportAction(action, reportId, assigneeId = null) {
            debugLog(`Performing report action: ${action} on report ${reportId}`);
            // Prevent any default behavior that might cause page navigation
            if (event) {
                event.preventDefault();
                event.stopPropagation();
            }
            
            fetch('ReportActions.php', {
                method: 'POST',
                headers: {
                    'Content-Type': 'application/json',
                },
                body: JSON.stringify({
                    action: action,
                    reportId: reportId,
                    assigneeId: assigneeId
                })
            })
            .then(response => {
                if (!response.ok) {
                    throw new Error('Network response was not ok');
                }
                return response.json();
            })
            .then(data => {
                debugLog(`Report action response:`, data);
                // Always refresh the page regardless of success/failure
                window.location.reload();
            })
            .catch(error => {
                debugLog('Report action error: ' + error.message);
                // Still refresh even if there's an error
                window.location.reload();
            });
            
            return false;
        }

        // Handle image previews for file uploads
        function handleImagePreview(event) {
            debugLog('Handling image preview');
            const container = document.getElementById('image-preview-container');
            container.innerHTML = '';
            
            const files = event.target.files;
            debugLog(`Selected ${files.length} files for preview`);
            
            for (let i = 0; i < files.length; i++) {
                const file = files[i];
                
                if (!file.type.match('image.*')) {
                    debugLog(`Skipping non-image file: ${file.name}`);
                    continue;
                }
                
                const reader = new FileReader();
                
                reader.onload = (function(file) {
                    return function(e) {
                        const previewDiv = document.createElement('div');
                        previewDiv.className = 'image-preview';
                        
                        const img = document.createElement('img');
                        img.src = e.target.result;
                        img.title = file.name;
                        
                        const removeBtn = document.createElement('button');
                        removeBtn.className = 'remove-image';
                        removeBtn.innerHTML = '&times;';
                        removeBtn.onclick = function() {
                            previewDiv.remove();
                            // Remove the file from the input
                            const newFiles = Array.from(files).filter(f => f !== file);
                            const dataTransfer = new DataTransfer();
                            newFiles.forEach(f => dataTransfer.items.add(f));
                            event.target.files = dataTransfer.files;
                            debugLog(`Removed image: ${file.name}`);
                        };
                        
                        previewDiv.appendChild(img);
                        previewDiv.appendChild(removeBtn);
                        container.appendChild(previewDiv);
                        debugLog(`Added preview for image: ${file.name}`);
                    };
                })(file);
                
                reader.readAsDataURL(file);
            }
        }

        // Initialize when DOM is loaded
        document.addEventListener('DOMContentLoaded', function() {
            debugLog('DOM fully loaded and parsed');
            
            // Load markers first, then check for any reports to highlight
            reloadMarkers().then(() => {
                checkForReportLocation();
            }).catch(error => {
                debugLog('Error loading markers: ' + error.message);
                console.error('Error loading markers:', error);
            });
            
            // Add event listener for geolocation button
            document.getElementById('geolocate-btn').addEventListener('click', toggleGeolocation);
            
            // Add event listener for file input changes
            document.getElementById('photos').addEventListener('change', handleImagePreview);
            
            // Debug: Check if Leaflet is loaded
            debugLog('Leaflet available: ' + (typeof L !== 'undefined'));
            debugLog('Map container dimensions: ' + document.getElementById('map').offsetWidth + 'x' + document.getElementById('map').offsetHeight);
        });
        const titleInput = document.getElementById('title');
const titleCounter = document.createElement('small');
titleCounter.className = 'form-text text-muted';
titleCounter.textContent = '100 characters remaining';
titleInput.insertAdjacentElement('afterend', titleCounter);

titleInput.addEventListener('input', function() {
    const remaining = 100 - this.value.length;
    titleCounter.textContent = `${remaining} characters remaining`;
    
    if (remaining < 0) {
        this.value = this.value.substring(0, 100);
        titleCounter.textContent = '0 characters remaining';
    }
});

    </script>
    <!--script src="darkMode.js"></script-->
</body>
</html>