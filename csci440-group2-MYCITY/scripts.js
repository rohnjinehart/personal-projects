// Define the geographic boundaries for College Station, TX
// Format: [Southwest corner, Northeast corner]
const collegeStationBounds = [
    [30.55, -96.45], // Southwest corner (lat, lng)
    [30.70, -96.20]  // Northeast corner (lat, lng)
];

// Initialize the Leaflet map with configuration options
const map = L.map('map', {
    center: [30.6280, -96.3344], // Default center point (Texas A&M University)
    zoom: 12,                     // Initial zoom level (street level)
    maxBounds: collegeStationBounds, // Restrict map to College Station area
    maxBoundsViscosity: 1.0,      // Strict boundary enforcement (1.0 = no elasticity)
    minZoom: 12                   // Prevent zooming out beyond street level
});

// Add the OpenStreetMap base tile layer to our map
L.tileLayer('https://{s}.tile.openstreetmap.org/{z}/{x}/{y}.png', {
    attribution: '© OpenStreetMap contributors'  // Required attribution
}).addTo(map);

/**
 * Adds a marker to the map at specified coordinates
 * @param {number} lat - Latitude coordinate
 * @param {number} lng - Longitude coordinate
 * @param {string} title - Popup text for the marker
 */
function addMarker(lat, lng, title) {
    // Create marker and add to map
    L.marker([lat, lng]).addTo(map)
        .bindPopup(title)    // Attach popup with title
        .openPopup();       // Open popup by default
}

// Fetch reports data from server and display markers
fetch('Get_Reports.php')
    .then(response => response.json())  // Parse JSON response
    .then(data => {
        // Loop through reports and add markers
        data.forEach(report => {
            addMarker(report.latitude, report.longitude, report.title);
        });
    })
    .catch(error => {
        console.error('Error loading reports:', error);
    });