<?php
require_once __DIR__ . '/../includes/auth.php';
require_once __DIR__ . '/../models/SeniorCitizen.php';

$user = requireRole(['ipho']);

$purokOptions = getDistinctPurokZones($conn);

$pageTitle = 'GIS Map';
$activeMenu = 'map';
include __DIR__ . '/../views/partials/header.php';
?>
<div class="page-header"><div><h1>Beneficiary Map</h1><div class="page-subtitle">Barangay New Bulatukan &middot; colored by latest distribution status</div></div></div>

<div class="mt-card p-3 mb-3">
    <div class="row g-2 align-items-end">
        <div class="col-md-4">
            <label class="form-label small">Filter by Purok/Zone</label>
            <select id="filter-purok" class="form-select form-select-sm">
                <option value="">All</option>
                <?php foreach ($purokOptions as $pz): ?>
                    <option value="<?= h($pz) ?>"><?= h($pz) ?></option>
                <?php endforeach; ?>
            </select>
        </div>
        <div class="col-md-4">
            <label class="form-label small">Filter by Medicine</label>
            <input type="text" id="filter-medicine" class="form-control form-control-sm" placeholder="e.g. Metformin">
        </div>
        <div class="col-md-4 d-flex gap-3 small pt-2">
            <span><span class="badge rounded-pill" style="background:#2c8f5c">&nbsp;</span> Dispensed</span>
            <span><span class="badge rounded-pill" style="background:#2a5cd6">&nbsp;</span> Scheduled</span>
            <span><span class="badge rounded-pill" style="background:#c53c30">&nbsp;</span> Not dispensed / Missed</span>
        </div>
    </div>
</div>

<div class="mt-card p-0">
    <div id="map" style="height: 560px; border-radius: 8px;"></div>
</div>

<link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/leaflet/1.9.4/leaflet.min.css">
<script src="https://cdnjs.cloudflare.com/ajax/libs/leaflet/1.9.4/leaflet.min.js"></script>
<script>
const map = L.map('map').setView([<?= BARANGAY_LAT ?>, <?= BARANGAY_LNG ?>], 14);
L.tileLayer('https://{s}.tile.openstreetmap.org/{z}/{x}/{y}.png', { attribution: '&copy; OpenStreetMap contributors' }).addTo(map);

const colorMap = { green: '#2c8f5c', blue: '#2a5cd6', red: '#c53c30', gray: '#8a8f8d' };
let allFeatures = [];
let markers = [];

function renderMarkers() {
    markers.forEach(m => map.removeLayer(m));
    markers = [];
    const purokFilter = document.getElementById('filter-purok').value.toLowerCase();
    const medicineFilter = document.getElementById('filter-medicine').value.toLowerCase();

    allFeatures.forEach(f => {
        if (purokFilter && f.purok_zone.toLowerCase() !== purokFilter) return;
        if (medicineFilter && !(f.medicine || '').toLowerCase().includes(medicineFilter)) return;

        const marker = L.circleMarker([f.latitude, f.longitude], {
            radius: 8, fillColor: colorMap[f.marker_color] || colorMap.gray, color: '#fff', weight: 2, fillOpacity: 0.9,
        }).addTo(map);
        marker.bindPopup('<strong>' + f.name + '</strong><br>Purok/Zone: ' + f.purok_zone + '<br>Contact: ' + (f.contact_number || 'Not on file') + '<br>Medicine: ' + (f.medicine || '-') + '<br>Status: ' + (f.distribution_status || 'No distribution yet'));
        markers.push(marker);
    });
}

fetch('/bhw/map-data.php')
    .then(res => res.json())
    .then(data => { allFeatures = data; renderMarkers(); });

document.getElementById('filter-purok').addEventListener('change', renderMarkers);
document.getElementById('filter-medicine').addEventListener('input', renderMarkers);
</script>

<?php include __DIR__ . '/../views/partials/footer.php'; ?>
