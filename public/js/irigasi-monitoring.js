/**
 * Irigasi Monitoring JavaScript Module
 * Real-time monitoring for irrigation system
 * @version 1.0.0
 * @author JAGAPADI System
 */

const IrigasiMonitoring = (function () {
    'use strict';

    // Configuration
    let config = {
        baseUrl: '',
        apiUrl: '',
        refreshInterval: 30000,
        irigasiData: []
    };

    // State
    const state = {
        map: null,
        markers: [],
        sensorChart: null,
        activityChart: null,
        refreshTimer: null,
        isLightMode: false,
        isRefreshing: false
    };

    // =========================================================================
    // Initialization
    // =========================================================================

    function init(options) {
        config = { ...config, ...options };

        // Initialize components
        initMap();
        initCharts();
        bindEvents();

        // Load initial data
        loadDashboardData();
        loadWeatherData();

        // Start auto-refresh
        startAutoRefresh();

        console.log('[IrigasiMonitoring] Initialized');
    }

    // =========================================================================
    // Map Functions
    // =========================================================================

    function initMap() {
        // Default center: Jember, East Java
        const defaultCenter = [-8.1845, 113.6681];

        state.map = L.map('monitoringMap').setView(defaultCenter, 10);

        // Add tile layer
        L.tileLayer('https://{s}.tile.openstreetmap.org/{z}/{x}/{y}.png', {
            attribution: '&copy; OpenStreetMap contributors'
        }).addTo(state.map);

        // Load markers
        loadMapMarkers();
    }

    function loadMapMarkers() {
        // Clear existing markers
        state.markers.forEach(marker => state.map.removeLayer(marker));
        state.markers = [];

        // Add markers for each irigasi
        if (config.irigasiData && config.irigasiData.length > 0) {
            config.irigasiData.forEach(irigasi => {
                if (irigasi.koordinat_lat && irigasi.koordinat_lng) {
                    const marker = createMarker(irigasi);
                    state.markers.push(marker);
                }
            });

            // Fit bounds if we have markers
            if (state.markers.length > 0) {
                const group = L.featureGroup(state.markers);
                state.map.fitBounds(group.getBounds().pad(0.1));
            }
        }
    }

    function createMarker(irigasi) {
        const statusClass = getStatusClass(irigasi.status_kondisi || irigasi.status_perbaikan);
        const iconHtml = `<div class="marker-icon ${statusClass}"><i class="fas fa-water"></i></div>`;

        const icon = L.divIcon({
            html: iconHtml,
            className: 'custom-marker',
            iconSize: [30, 30],
            iconAnchor: [15, 15]
        });

        const marker = L.marker([irigasi.koordinat_lat, irigasi.koordinat_lng], { icon })
            .addTo(state.map);

        // Add popup
        const popupContent = `
            <div class="irigasi-popup">
                <h6>${irigasi.nama_saluran || irigasi.nama_irigasi || 'Irigasi'}</h6>
                <div class="popup-info"><strong>Status:</strong> ${irigasi.status_kondisi || '-'}</div>
                <div class="popup-info"><strong>Jenis:</strong> ${irigasi.jenis_saluran || irigasi.jenis_irigasi || '-'}</div>
                <div class="popup-info"><strong>Luas:</strong> ${formatNumber(irigasi.luas_layanan)} Ha</div>
                <div class="popup-info"><strong>Debit:</strong> ${formatNumber(irigasi.debit_air)} L/s</div>
                <div class="mt-2">
                    <a href="${config.baseUrl}irigasi/detail/${irigasi.id}" class="btn btn-sm btn-primary">
                        <i class="fas fa-eye"></i> Detail
                    </a>
                </div>
            </div>
        `;

        marker.bindPopup(popupContent);

        return marker;
    }

    function getStatusClass(status) {
        const statusMap = {
            'Baik': 'status-baik',
            'Normal': 'status-baik',
            'Selesai Diperbaiki': 'status-baik',
            'Rusak Ringan': 'status-rusak-ringan',
            'Dalam Perbaikan': 'status-rusak-ringan',
            'Rusak Berat': 'status-rusak-berat',
            'Belum Ditangani': 'status-rusak-berat'
        };
        return statusMap[status] || 'status-baik';
    }

    // =========================================================================
    // Charts Functions
    // =========================================================================

    function initCharts() {
        // Sensor Trend Chart
        const sensorCtx = document.getElementById('sensorTrendChart');
        if (sensorCtx) {
            state.sensorChart = new Chart(sensorCtx, {
                type: 'line',
                data: {
                    labels: [],
                    datasets: [{
                        label: 'Kelembaban Tanah (%)',
                        data: [],
                        borderColor: '#667eea',
                        backgroundColor: 'rgba(102, 126, 234, 0.1)',
                        fill: true,
                        tension: 0.4
                    }]
                },
                options: {
                    responsive: true,
                    maintainAspectRatio: false,
                    plugins: {
                        legend: {
                            display: false
                        }
                    },
                    scales: {
                        y: {
                            beginAtZero: true,
                            max: 100
                        }
                    }
                }
            });
        }

        // Activity Chart
        const activityCtx = document.getElementById('irrigationActivityChart');
        if (activityCtx) {
            state.activityChart = new Chart(activityCtx, {
                type: 'bar',
                data: {
                    labels: [],
                    datasets: [{
                        label: 'Pengairan',
                        data: [],
                        backgroundColor: '#28a745'
                    }]
                },
                options: {
                    responsive: true,
                    maintainAspectRatio: false,
                    plugins: {
                        legend: {
                            display: false
                        }
                    },
                    scales: {
                        y: {
                            beginAtZero: true,
                            ticks: {
                                stepSize: 1
                            }
                        }
                    }
                }
            });
        }
    }

    function updateSensorChart(data, sensorType) {
        if (!state.sensorChart || !data) return;

        const labels = data.map(d => formatDate(d.date));
        const values = data.map(d => parseFloat(d.avg_value) || 0);

        const sensorLabels = {
            'soil_moisture': 'Kelembaban Tanah (%)',
            'water_ph': 'pH Air',
            'water_flow': 'Debit Air (L/s)',
            'temperature': 'Suhu (┬░C)'
        };

        state.sensorChart.data.labels = labels;
        state.sensorChart.data.datasets[0].label = sensorLabels[sensorType] || sensorType;
        state.sensorChart.data.datasets[0].data = values;
        state.sensorChart.update();
    }

    function updateActivityChart(data) {
        if (!state.activityChart || !data) return;

        // Group by date
        const grouped = {};
        data.forEach(d => {
            if (!grouped[d.date]) {
                grouped[d.date] = 0;
            }
            grouped[d.date] += parseInt(d.count) || 0;
        });

        const labels = Object.keys(grouped).map(d => formatDate(d));
        const values = Object.values(grouped);

        state.activityChart.data.labels = labels;
        state.activityChart.data.datasets[0].data = values;
        state.activityChart.update();
    }

    // =========================================================================
    // Data Loading Functions
    // =========================================================================

    function loadDashboardData() {
        if (state.isRefreshing) return;
        state.isRefreshing = true;

        fetch(`${config.apiUrl}dashboard-summary`)
            .then(response => response.json())
            .then(result => {
                if (result.success && result.data) {
                    updateKPIs(result.data);
                    updateActivityList(result.data.recent_activities);
                }
            })
            .catch(error => {
                console.error('[IrigasiMonitoring] Error loading dashboard:', error);
            })
            .finally(() => {
                state.isRefreshing = false;
                updateLastRefresh();
            });

        // Load analytics for charts
        loadAnalytics();

        // Load alerts
        loadAlerts();
    }

    function loadAnalytics() {
        const days = document.getElementById('timeRangeSelect')?.value || 30;
        const sensorType = document.getElementById('sensorTypeSelect')?.value || 'soil_moisture';

        // For now, load first irigasi analytics
        if (config.irigasiData && config.irigasiData.length > 0) {
            const firstId = config.irigasiData[0].id;

            fetch(`${config.apiUrl}${firstId}/analytics?days=${days}`)
                .then(response => response.json())
                .then(result => {
                    if (result.success && result.data) {
                        const sensorData = result.data.sensor_trends.filter(
                            d => d.sensor_type === sensorType
                        );
                        updateSensorChart(sensorData, sensorType);
                        updateActivityChart(result.data.irrigation_history);
                    }
                })
                .catch(error => {
                    console.error('[IrigasiMonitoring] Error loading analytics:', error);
                });
        }
    }

    function loadWeatherData() {
        if (config.irigasiData && config.irigasiData.length > 0) {
            const firstId = config.irigasiData[0].id;

            fetch(`${config.apiUrl}${firstId}/weather`)
                .then(response => response.json())
                .then(result => {
                    if (result.success && result.data) {
                        updateWeatherWidget(result.data);
                    }
                })
                .catch(error => {
                    console.error('[IrigasiMonitoring] Error loading weather:', error);
                    // Show fallback
                    document.getElementById('weatherDesc').textContent = 'Data tidak tersedia';
                });
        }
    }

    function loadAlerts() {
        // Load all alerts from dashboard summary
        const alertList = document.getElementById('alertList');
        if (!alertList) return;

        // Simulate loading - in production would use dedicated endpoint
        setTimeout(() => {
            alertList.innerHTML = `
                <div class="text-center text-muted py-3">
                    <i class="fas fa-check-circle text-success fa-2x mb-2"></i>
                    <p class="mb-0">Tidak ada alert aktif</p>
                </div>
            `;
        }, 500);
    }

    // =========================================================================
    // UI Update Functions
    // =========================================================================

    function updateKPIs(data) {
        const overview = data.overview || {};

        animateNumber('kpiTotalIrigasi', overview.total || 0);

        // Calculate active sensors from sensor_status
        let activeSensors = 0;
        if (data.sensor_status) {
            const active = data.sensor_status.find(s => s.status === 'active');
            activeSensors = active ? parseInt(active.count) : 0;
        }
        animateNumber('kpiActiveSensors', activeSensors);

        animateNumber('kpiTodayOps', data.today_operations || 0);
        animateNumber('kpiAlerts', data.active_alerts || 0);
    }

    function animateNumber(elementId, value) {
        const element = document.getElementById(elementId);
        if (!element) return;

        const current = parseInt(element.textContent) || 0;
        const diff = value - current;
        const duration = 500;
        const steps = 20;
        const stepValue = diff / steps;
        const stepDuration = duration / steps;

        let step = 0;
        const timer = setInterval(() => {
            step++;
            element.textContent = Math.round(current + stepValue * step);
            if (step >= steps) {
                clearInterval(timer);
                element.textContent = value;
            }
        }, stepDuration);
    }

    function updateWeatherWidget(data) {
        const current = data.current || {};
        const forecast = data.forecast?.daily || [];
        const adjustment = data.irrigation_adjustment || {};

        // Current weather
        document.getElementById('weatherTemp').textContent =
            current.temperature_max ? `${current.temperature_max}┬░C` : '--┬░C';
        document.getElementById('weatherDesc').textContent =
            current.description || 'Tidak diketahui';
        document.getElementById('weatherRain').textContent =
            `${current.precipitation || 0} mm`;

        // Weather icon
        const iconEl = document.getElementById('weatherIcon');
        iconEl.className = 'weather-icon';
        if (current.precipitation > 5) {
            iconEl.classList.add('rainy');
            iconEl.innerHTML = '<i class="fas fa-cloud-rain"></i>';
        } else if (current.category === 'cloudy') {
            iconEl.classList.add('cloudy');
            iconEl.innerHTML = '<i class="fas fa-cloud"></i>';
        } else {
            iconEl.innerHTML = '<i class="fas fa-sun"></i>';
        }

        // Recommendation
        document.getElementById('irrigationRecommendation').textContent =
            adjustment.recommendation || 'Pengairan normal';

        const multiplierBadge = document.getElementById('irrigationMultiplier');
        multiplierBadge.textContent = `Faktor: ${(adjustment.multiplier || 1).toFixed(1)}`;
        multiplierBadge.className = 'multiplier-badge';
        if (adjustment.multiplier < 0.8) {
            multiplierBadge.classList.add('reduce');
        } else if (adjustment.multiplier > 1.1) {
            multiplierBadge.classList.add('increase');
        }

        // Forecast days
        const forecastContainer = document.getElementById('forecastDays');
        if (forecastContainer && forecast.length > 0) {
            forecastContainer.innerHTML = forecast.slice(0, 7).map(day => {
                const dayName = new Date(day.date).toLocaleDateString('id-ID', { weekday: 'short' });
                const icon = day.precipitation_sum > 5 ? 'fa-cloud-rain' :
                    day.precipitation_sum > 0 ? 'fa-cloud-sun' : 'fa-sun';
                return `
                    <div class="forecast-day">
                        <div class="day-name">${dayName}</div>
                        <div class="day-icon"><i class="fas ${icon}"></i></div>
                        <div class="day-temp">${day.temperature_max || '--'}┬░</div>
                        <div class="day-rain">${day.precipitation_sum || 0} mm</div>
                    </div>
                `;
            }).join('');
        }
    }

    function updateActivityList(activities) {
        const activityList = document.getElementById('activityList');
        if (!activityList || !activities) return;

        if (activities.length === 0) {
            activityList.innerHTML = `
                <div class="text-center text-muted py-3">
                    <p class="mb-0">Belum ada aktivitas</p>
                </div>
            `;
            return;
        }

        activityList.innerHTML = activities.map(activity => `
            <div class="activity-item">
                <div class="activity-icon">
                    <i class="fas fa-clipboard-check"></i>
                </div>
                <div class="activity-content">
                    <div class="activity-title">${activity.nama_saluran || 'Laporan'}</div>
                    <div class="activity-meta">
                        <span class="badge badge-${getStatusBadgeClass(activity.status)}">${activity.status}</span>
                        <span class="ml-2">${formatDate(activity.tanggal)}</span>
                    </div>
                </div>
            </div>
        `).join('');
    }

    function updateLastRefresh() {
        const lastUpdate = document.getElementById('lastUpdate');
        if (lastUpdate) {
            lastUpdate.textContent = new Date().toLocaleTimeString('id-ID');
        }
    }

    // =========================================================================
    // Event Handlers
    // =========================================================================

    function bindEvents() {
        // Refresh button
        document.getElementById('btnRefresh')?.addEventListener('click', function () {
            this.querySelector('i').classList.add('fa-spin');
            loadDashboardData();
            loadWeatherData();
            setTimeout(() => {
                this.querySelector('i').classList.remove('fa-spin');
            }, 1000);
        });

        // Light mode toggle
        document.getElementById('btnLightMode')?.addEventListener('click', function () {
            toggleLightMode();
            this.classList.toggle('active');
        });

        // Sensor type selector
        document.getElementById('sensorTypeSelect')?.addEventListener('change', function () {
            loadAnalytics();
        });

        // Time range selector
        document.getElementById('timeRangeSelect')?.addEventListener('change', function () {
            loadAnalytics();
        });

        // Map layer buttons
        document.querySelectorAll('.map-controls button').forEach(btn => {
            btn.addEventListener('click', function () {
                document.querySelectorAll('.map-controls button').forEach(b => b.classList.remove('active'));
                this.classList.add('active');
                // Future: change map layer based on data-layer attribute
            });
        });
    }

    // =========================================================================
    // Auto Refresh
    // =========================================================================

    function startAutoRefresh() {
        if (state.refreshTimer) {
            clearInterval(state.refreshTimer);
        }

        state.refreshTimer = setInterval(() => {
            if (!state.isLightMode) {
                loadDashboardData();
            }
        }, config.refreshInterval);

        document.getElementById('refreshStatus').textContent = 'Aktif';
        document.getElementById('refreshStatus').className = 'text-success';
    }

    function stopAutoRefresh() {
        if (state.refreshTimer) {
            clearInterval(state.refreshTimer);
            state.refreshTimer = null;
        }

        document.getElementById('refreshStatus').textContent = 'Nonaktif';
        document.getElementById('refreshStatus').className = 'text-danger';
    }

    // =========================================================================
    // Light Mode
    // =========================================================================

    function toggleLightMode() {
        state.isLightMode = !state.isLightMode;
        document.body.classList.toggle('light-mode', state.isLightMode);

        if (state.isLightMode) {
            stopAutoRefresh();
        } else {
            startAutoRefresh();
        }

        // Resize map
        setTimeout(() => {
            if (state.map) {
                state.map.invalidateSize();
            }
        }, 100);
    }

    // =========================================================================
    // Utility Functions
    // =========================================================================

    function formatDate(dateStr) {
        if (!dateStr) return '-';
        const date = new Date(dateStr);
        return date.toLocaleDateString('id-ID', { day: 'numeric', month: 'short' });
    }

    function formatNumber(num) {
        if (num === null || num === undefined) return '-';
        return parseFloat(num).toLocaleString('id-ID', { maximumFractionDigits: 2 });
    }

    function getStatusBadgeClass(status) {
        const map = {
            'Diverifikasi': 'success',
            'Submitted': 'primary',
            'Draf': 'secondary',
            'Ditolak': 'danger'
        };
        return map[status] || 'secondary';
    }

    // =========================================================================
    // Public API
    // =========================================================================

    return {
        init,
        refresh: loadDashboardData,
        toggleLightMode,
        loadWeatherData
    };

})();

// Export for global access
window.IrigasiMonitoring = IrigasiMonitoring;
