import { initShell } from '../shell.js';
import { api, ApiError } from '../api.js';

// Placeholder centroid — replace with Barangay New Bulatukan's actual
// coordinates once available (see docs/01-requirements-analysis.md §13.3).
const DEFAULT_CENTER = [7.0, 125.5];
const DEFAULT_ZOOM = 14;

const CONDITION_COLORS = { hypertension: '#dc2626', diabetes: '#2563eb', both: '#7c3aed' };

const session = await initShell('gis-map', 'GIS Mapping');
if (session) init(session);

async function init({ content }) {
  let puroks = [];
  try {
    puroks = (await api.get('/puroks')).items;
  } catch {
    // filter dropdown degrades to empty if the lookup fails
  }

  content.innerHTML = `
    <div class="toolbar">
      <div class="filters">
        <select id="f-purok"><option value="">All Puroks</option>${puroks.map((p) => `<option value="${p.id}">${p.name}</option>`).join('')}</select>
        <select id="f-condition">
          <option value="">All Conditions</option>
          <option value="hypertension">Hypertension</option>
          <option value="diabetes">Diabetes</option>
          <option value="both">Both</option>
        </select>
        <select id="f-status">
          <option value="">All Statuses</option>
          <option value="active">Active</option>
          <option value="pending">Pending</option>
          <option value="inactive">Inactive</option>
        </select>
      </div>
    </div>
    <div class="card" style="margin-bottom:12px; display:flex; gap:16px; align-items:center; font-size:13px;">
      <strong>Legend:</strong>
      <span><span style="display:inline-block;width:10px;height:10px;border-radius:50%;background:${CONDITION_COLORS.hypertension};margin-right:4px;"></span>Hypertension</span>
      <span><span style="display:inline-block;width:10px;height:10px;border-radius:50%;background:${CONDITION_COLORS.diabetes};margin-right:4px;"></span>Diabetes</span>
      <span><span style="display:inline-block;width:10px;height:10px;border-radius:50%;background:${CONDITION_COLORS.both};margin-right:4px;"></span>Both</span>
    </div>
    <div id="map-status"></div>
    <div id="map"></div>
  `;

  const map = L.map('map').setView(DEFAULT_CENTER, DEFAULT_ZOOM);
  L.tileLayer('https://{s}.tile.openstreetmap.org/{z}/{x}/{y}.png', {
    attribution: '&copy; OpenStreetMap contributors',
    maxZoom: 19,
  }).addTo(map);

  let markers = L.layerGroup().addTo(map);
  const filters = { purok_id: '', medical_condition: '', distribution_status: '' };

  async function reload() {
    const statusBox = content.querySelector('#map-status');
    try {
      const { points } = await api.get('/map/beneficiaries', filters);
      markers.clearLayers();

      const plottable = points.filter((p) => p.latitude !== null && p.longitude !== null);
      const missing = points.length - plottable.length;

      if (points.length === 0) {
        statusBox.innerHTML = '<div class="empty-state">No beneficiaries match the selected filters.</div>';
      } else {
        statusBox.innerHTML = missing > 0
          ? `<p style="color:var(--color-text-muted);font-size:13px;">${missing} of ${points.length} matching beneficiaries have no coordinates on file and are not shown on the map.</p>`
          : '';
      }

      plottable.forEach((p) => {
        const marker = L.circleMarker([p.latitude, p.longitude], {
          radius: 8,
          color: CONDITION_COLORS[p.medical_condition] || '#6b7280',
          fillColor: CONDITION_COLORS[p.medical_condition] || '#6b7280',
          fillOpacity: 0.85,
        });
        marker.bindPopup(`
          <strong>${p.full_name}</strong><br>
          Condition: ${p.medical_condition}<br>
          Assigned Medicine: ${p.assigned_medicine_name ?? '—'}<br>
          Status: ${p.distribution_status}<br>
          Purok: ${p.purok_name}
        `);
        markers.addLayer(marker);
      });

      if (plottable.length > 0) {
        map.fitBounds(L.featureGroup(markers.getLayers()).getBounds().pad(0.2));
      }
    } catch (err) {
      statusBox.innerHTML = `<div class="error-state">${err instanceof ApiError ? err.message : 'Failed to load map data.'}</div>`;
    }
  }

  content.querySelector('#f-purok').addEventListener('change', (e) => { filters.purok_id = e.target.value; reload(); });
  content.querySelector('#f-condition').addEventListener('change', (e) => { filters.medical_condition = e.target.value; reload(); });
  content.querySelector('#f-status').addEventListener('change', (e) => { filters.distribution_status = e.target.value; reload(); });

  await reload();
}
