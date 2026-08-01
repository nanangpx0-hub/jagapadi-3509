/**
 * Storytelling Dashboard JavaScript Module
 * 
 * Handles data storytelling dashboard interactions, chart rendering,
 * and AJAX communication for production causality analysis.
 * 
 * @version 1.0.0
 * @author JAGAPADI System - Data Storytelling Module
 */

const StorytellingDashboard = (function() {
    'use strict';
    
    // Configuration
    let config = {
        baseUrl: '',
        csrfToken: '',
        currentUser: null
    };
    
    // State management
    const state = {
        correlationChart: null,
        currentAnalysis: null,
        isAnalyzing: false
    };
    
    // DOM elements
    const elements = {
        filterBulan: null,
        filterTahun: null,
        filterKecamatan: null,
        btnAnalyze: null,
        btnReset: null,
        btnSaveAnalysis: null,
        btnPreview: null,
        
        // KPI Cards
        kpiLuasPanen: null,
        kpiCurahHujan: null,
        kpiLaporanHama: null,
        kpiSkorRisiko: null,
        
        // Analysis Result Panel
        analysisResult: null,
        defaultState: null,
        faktorPenyebab: null,
        scoreCuaca: null,
        scoreHama: null,
        scoreTotal: null,
        narasiOtomatis: null,
        narasiFinal: null,
        existingWarning: null,
        
        // Chart
        correlationChart: null,
        
        // Loading
        loadingOverlay: null
    };
    
    /**
     * Initialize the dashboard
     */
    function init(options) {
        config = { ...config, ...options };
        
        // Cache DOM elements
        cacheElements();
        
        // Bind event listeners
        bindEvents();
        
        // Initialize chart
        initChart();
        
        console.log('[StorytellingDashboard] Initialized');
    }
    
    /**
     * Cache DOM elements for better performance
     */
    function cacheElements() {
        elements.filterBulan = document.getElementById('filter-bulan');
        elements.filterTahun = document.getElementById('filter-tahun');
        elements.filterKecamatan = document.getElementById('filter-kecamatan');
        elements.btnAnalyze = document.getElementById('btn-analyze');
        elements.btnReset = document.getElementById('btn-reset');
        elements.btnSaveAnalysis = document.getElementById('btn-save-analysis');
        elements.btnPreview = document.getElementById('btn-preview');
        
        // KPI Cards
        elements.kpiLuasPanen = document.getElementById('kpi-luas-panen');
        elements.kpiCurahHujan = document.getElementById('kpi-curah-hujan');
        elements.kpiLaporanHama = document.getElementById('kpi-laporan-hama');
        elements.kpiSkorRisiko = document.getElementById('kpi-skor-risiko');
        
        // Analysis Result Panel
        elements.analysisResult = document.getElementById('analysis-result');
        elements.defaultState = document.getElementById('default-state');
        elements.faktorPenyebab = document.getElementById('faktor-penyebab');
        elements.scoreCuaca = document.getElementById('score-cuaca');
        elements.scoreHama = document.getElementById('score-hama');
        elements.scoreTotal = document.getElementById('score-total');
        elements.narasiOtomatis = document.getElementById('narasi-otomatis');
        elements.narasiFinal = document.getElementById('narasi-final');
        elements.existingWarning = document.getElementById('existing-warning');
        
        // Loading
        elements.loadingOverlay = document.getElementById('loading-overlay');
    }
    
    /**
     * Bind event listeners
     */
    function bindEvents() {
        // Analyze button
        elements.btnAnalyze.addEventListener('click', handleAnalyze);
        
        // Reset button
        elements.btnReset.addEventListener('click', handleReset);
        
        // Save analysis button
        elements.btnSaveAnalysis.addEventListener('click', handleSaveAnalysis);
        
        // Preview button
        elements.btnPreview.addEventListener('click', handlePreview);
        
        // Auto-copy narasi otomatis to narasi final when analysis is generated
        elements.narasiOtomatis.addEventListener('input', function() {
            if (!elements.narasiFinal.value.trim()) {
                elements.narasiFinal.value = this.value;
            }
        });
        
        // Filter change handlers for real-time chart updates
        elements.filterBulan.addEventListener('change', handleFilterChange);
        elements.filterTahun.addEventListener('change', handleFilterChange);
        elements.filterKecamatan.addEventListener('change', handleFilterChange);
    }
    
    /**
     * Initialize correlation chart
     */
    function initChart() {
        const ctx = document.getElementById('correlationChart');
        if (!ctx) return;
        
        state.correlationChart = new Chart(ctx, {
            type: 'bar',
            data: {
                labels: [],
                datasets: [
                    {
                        label: 'Luas Panen (Ha)',
                        type: 'bar',
                        data: [],
                        backgroundColor: 'rgba(54, 162, 235, 0.6)',
                        borderColor: 'rgba(54, 162, 235, 1)',
                        borderWidth: 1,
                        yAxisID: 'y'
                    },
                    {
                        label: 'Curah Hujan (mm)',
                        type: 'line',
                        data: [],
                        backgroundColor: 'rgba(255, 99, 132, 0.2)',
                        borderColor: 'rgba(255, 99, 132, 1)',
                        borderWidth: 2,
                        fill: false,
                        yAxisID: 'y1',
                        tension: 0.4
                    },
                    {
                        label: 'Laporan Hama',
                        type: 'line',
                        data: [],
                        backgroundColor: 'rgba(255, 206, 86, 0.2)',
                        borderColor: 'rgba(255, 206, 86, 1)',
                        borderWidth: 2,
                        fill: false,
                        yAxisID: 'y2',
                        tension: 0.4
                    }
                ]
            },
            options: {
                responsive: true,
                maintainAspectRatio: false,
                interaction: {
                    mode: 'index',
                    intersect: false,
                },
                plugins: {
                    title: {
                        display: true,
                        text: 'Korelasi Luas Panen dengan Lagging Indicators (6 Bulan Terakhir)'
                    },
                    legend: {
                        position: 'top',
                    },
                    tooltip: {
                        callbacks: {
                            afterLabel: function(context) {
                                if (context.datasetIndex === 0) {
                                    return 'Sumbu Y: Kiri (Ha)';
                                } else if (context.datasetIndex === 1) {
                                    return 'Sumbu Y: Kanan (mm) - Data Bulan Sebelumnya';
                                } else {
                                    return 'Sumbu Y: Kanan (Laporan) - Data Bulan Sebelumnya';
                                }
                            }
                        }
                    }
                },
                scales: {
                    x: {
                        display: true,
                        title: {
                            display: true,
                            text: 'Periode'
                        }
                    },
                    y: {
                        type: 'linear',
                        display: true,
                        position: 'left',
                        title: {
                            display: true,
                            text: 'Luas Panen (Ha)'
                        },
                        grid: {
                            drawOnChartArea: false,
                        },
                    },
                    y1: {
                        type: 'linear',
                        display: true,
                        position: 'right',
                        title: {
                            display: true,
                            text: 'Curah Hujan (mm)'
                        },
                        grid: {
                            drawOnChartArea: false,
                        },
                    },
                    y2: {
                        type: 'linear',
                        display: false, // Hide third axis to avoid clutter
                        position: 'right',
                    }
                }
            }
        });
    }
    
    /**
     * Handle analyze button click
     */
    async function handleAnalyze() {
        if (state.isAnalyzing) return;
        
        // Validate inputs
        const bulan = elements.filterBulan.value;
        const tahun = elements.filterTahun.value;
        const wilayahId = elements.filterKecamatan.value;
        
        if (!wilayahId) {
            showAlert('Pilih kecamatan terlebih dahulu', 'warning');
            return;
        }
        
        try {
            state.isAnalyzing = true;
            showLoading(true);
            
            // Disable analyze button
            elements.btnAnalyze.disabled = true;
            elements.btnAnalyze.innerHTML = '<i class="fas fa-spinner fa-spin"></i> Menganalisis...';
            
            // Make AJAX request
            const response = await fetch(`${config.baseUrl}/storytelling/generateAnalysis`, {
                method: 'POST',
                headers: {
                    'Content-Type': 'application/x-www-form-urlencoded',
                    'X-CSRF-Token': config.csrfToken
                },
                body: new URLSearchParams({
                    bulan: bulan,
                    tahun: tahun,
                    wilayah_id: wilayahId
                })
            });
            
            const result = await response.json();
            
            if (result.success) {
                state.currentAnalysis = result;
                displayAnalysisResult(result);
                updateChart(result.chart_data);
                showAlert('Analisis berhasil dibuat!', 'success');
            } else {
                throw new Error(result.error || 'Gagal melakukan analisis');
            }
            
        } catch (error) {
            console.error('Analysis error:', error);
            showAlert(error.message, 'danger');
        } finally {
            state.isAnalyzing = false;
            showLoading(false);
            
            // Re-enable analyze button
            elements.btnAnalyze.disabled = false;
            elements.btnAnalyze.innerHTML = '<i class="fas fa-magic"></i> Analisa Sekarang';
        }
    }
    
    /**
     * Display analysis result in the story panel
     */
    function displayAnalysisResult(result) {
        // Hide default state, show analysis result
        elements.defaultState.style.display = 'none';
        elements.analysisResult.style.display = 'block';
        
        // Update KPI cards with animation
        updateKPICards(result);
        
        // Update analysis panel
        elements.faktorPenyebab.value = result.faktor_penyebab_utama;
        elements.narasiOtomatis.value = result.narasi_otomatis;
        
        // Copy to final narasi if empty
        if (!elements.narasiFinal.value.trim()) {
            elements.narasiFinal.value = result.narasi_otomatis;
        }
        
        // Update risk scores
        updateRiskScores(result.skor_risiko);
        
        // Show existing analysis warning if applicable
        if (result.has_existing) {
            elements.existingWarning.style.display = 'block';
        } else {
            elements.existingWarning.style.display = 'none';
        }
    }
    
    /**
     * Update KPI cards with animation
     */
    function updateKPICards(result) {
        const produksi = result.produksi_data;
        const curahHujan = result.lagging_indicators.curah_hujan;
        const hama = result.lagging_indicators.hama;
        const skor = result.skor_risiko;
        
        // Animate counter updates
        animateCounter(elements.kpiLuasPanen, produksi.total_luas_panen, 2);
        animateCounter(elements.kpiCurahHujan, curahHujan.avg_curah_hujan, 1);
        animateCounter(elements.kpiLaporanHama, hama.total_laporan_hama, 0);
        animateCounter(elements.kpiSkorRisiko, skor.skor_risiko_total, 0);
    }
    
    /**
     * Update risk score badges
     */
    function updateRiskScores(skorRisiko) {
        elements.scoreCuaca.textContent = `Cuaca: ${skorRisiko.skor_risiko_cuaca}`;
        elements.scoreHama.textContent = `Hama: ${skorRisiko.skor_risiko_hama}`;
        elements.scoreTotal.textContent = `Total: ${skorRisiko.skor_risiko_total}`;
        
        // Update risk level classes
        updateRiskClass(elements.scoreCuaca, skorRisiko.skor_risiko_cuaca);
        updateRiskClass(elements.scoreHama, skorRisiko.skor_risiko_hama);
        updateRiskClass(elements.scoreTotal, skorRisiko.skor_risiko_total);
    }
    
    /**
     * Update risk level CSS class
     */
    function updateRiskClass(element, score) {
        element.classList.remove('risk-low', 'risk-medium', 'risk-high');
        
        if (score > 70) {
            element.classList.add('risk-high');
        } else if (score > 40) {
            element.classList.add('risk-medium');
        } else {
            element.classList.add('risk-low');
        }
    }
    
    /**
     * Animate counter with easing
     */
    function animateCounter(element, targetValue, decimals = 0) {
        const startValue = parseFloat(element.textContent.replace(/[^\d.-]/g, '')) || 0;
        const duration = 1500; // 1.5 seconds
        const startTime = performance.now();
        
        function updateCounter(currentTime) {
            const elapsed = currentTime - startTime;
            const progress = Math.min(elapsed / duration, 1);
            
            // Easing function (ease-out cubic)
            const easedProgress = 1 - Math.pow(1 - progress, 3);
            
            const currentValue = startValue + (targetValue - startValue) * easedProgress;
            element.textContent = formatNumber(currentValue, decimals);
            
            if (progress < 1) {
                requestAnimationFrame(updateCounter);
            }
        }
        
        requestAnimationFrame(updateCounter);
    }
    
    /**
     * Update chart with new data
     */
    function updateChart(chartData) {
        if (!state.correlationChart || !chartData) return;
        
        state.correlationChart.data.labels = chartData.labels;
        state.correlationChart.data.datasets[0].data = chartData.datasets[0].data;
        state.correlationChart.data.datasets[1].data = chartData.datasets[1].data;
        state.correlationChart.data.datasets[2].data = chartData.datasets[2].data;
        
        state.correlationChart.update('active');
    }
    
    /**
     * Handle save analysis
     */
    async function handleSaveAnalysis() {
        if (!state.currentAnalysis) {
            showAlert('Tidak ada analisis untuk disimpan', 'warning');
            return;
        }
        
        try {
            // Prepare data for saving
            const saveData = {
                ...state.currentAnalysis,
                narasi_final: elements.narasiFinal.value.trim(),
                faktor_penyebab_override: elements.faktorPenyebab.value
            };
            
            const response = await fetch(`${config.baseUrl}/storytelling/store`, {
                method: 'POST',
                headers: {
                    'Content-Type': 'application/json',
                    'X-CSRF-Token': config.csrfToken
                },
                body: JSON.stringify(saveData)
            });
            
            const result = await response.json();
            
            if (result.success) {
                showAlert(result.message, 'success');
                
                // Update UI state
                elements.existingWarning.style.display = 'none';
                
                // Refresh recent analyses table
                refreshRecentAnalyses();
                
            } else {
                throw new Error(result.error || 'Gagal menyimpan analisis');
            }
            
        } catch (error) {
            console.error('Save error:', error);
            showAlert(error.message, 'danger');
        }
    }
    
    /**
     * Handle preview functionality
     */
    function handlePreview() {
        if (!state.currentAnalysis) {
            showAlert('Tidak ada analisis untuk di-preview', 'warning');
            return;
        }
        
        const previewData = {
            periode: `${getMonthName(state.currentAnalysis.periode.bulan)} ${state.currentAnalysis.periode.tahun}`,
            kecamatan: elements.filterKecamatan.options[elements.filterKecamatan.selectedIndex].text,
            faktor_penyebab: elements.faktorPenyebab.value,
            narasi_final: elements.narasiFinal.value.trim() || elements.narasiOtomatis.value,
            skor_risiko: state.currentAnalysis.skor_risiko
        };
        
        showPreviewModal(previewData);
    }
    
    /**
     * Handle reset functionality
     */
    function handleReset() {
        // Reset form
        elements.filterKecamatan.value = '';
        elements.narasiFinal.value = '';
        
        // Reset state
        state.currentAnalysis = null;
        
        // Reset UI
        elements.analysisResult.style.display = 'none';
        elements.defaultState.style.display = 'block';
        elements.existingWarning.style.display = 'none';
        
        // Reset KPI cards
        elements.kpiLuasPanen.textContent = '-';
        elements.kpiCurahHujan.textContent = '-';
        elements.kpiLaporanHama.textContent = '-';
        elements.kpiSkorRisiko.textContent = '-';
        
        // Reset chart
        if (state.correlationChart) {
            state.correlationChart.data.labels = [];
            state.correlationChart.data.datasets.forEach(dataset => {
                dataset.data = [];
            });
            state.correlationChart.update();
        }
    }
    
    /**
     * Handle filter change for real-time updates
     */
    function handleFilterChange() {
        // Only update chart if all filters are selected
        const bulan = elements.filterBulan.value;
        const tahun = elements.filterTahun.value;
        const wilayahId = elements.filterKecamatan.value;
        
        if (bulan && tahun && wilayahId) {
            updateChartData(bulan, tahun, wilayahId);
        }
    }
    
    /**
     * Update chart data based on filters
     */
    async function updateChartData(bulan, tahun, wilayahId) {
        try {
            const response = await fetch(`${config.baseUrl}/storytelling/getChartData?bulan=${bulan}&tahun=${tahun}&wilayah_id=${wilayahId}&months=6`);
            const result = await response.json();
            
            if (result.success) {
                updateChart(result.data);
            }
        } catch (error) {
            console.error('Chart update error:', error);
        }
    }
    
    /**
     * Utility functions
     */
    
    function showLoading(show) {
        elements.loadingOverlay.style.display = show ? 'flex' : 'none';
    }
    
    function showAlert(message, type = 'info') {
        // Create alert element
        const alertDiv = document.createElement('div');
        alertDiv.className = `alert alert-${type} alert-dismissible fade show position-fixed`;
        alertDiv.style.cssText = 'top: 20px; right: 20px; z-index: 10000; min-width: 300px;';
        alertDiv.innerHTML = `
            ${message}
            <button type="button" class="btn-close" data-bs-dismiss="alert"></button>
        `;
        
        document.body.appendChild(alertDiv);
        
        // Auto remove after 5 seconds
        setTimeout(() => {
            if (alertDiv.parentNode) {
                alertDiv.remove();
            }
        }, 5000);
    }
    
    function formatNumber(value, decimals = 0) {
        return new Intl.NumberFormat('id-ID', {
            minimumFractionDigits: decimals,
            maximumFractionDigits: decimals
        }).format(value);
    }
    
    function getMonthName(month) {
        const months = [
            'Januari', 'Februari', 'Maret', 'April', 'Mei', 'Juni',
            'Juli', 'Agustus', 'September', 'Oktober', 'November', 'Desember'
        ];
        return months[month - 1] || 'Unknown';
    }
    
    function showPreviewModal(data) {
        // Create modal HTML
        const modalHtml = `
            <div class="modal fade" id="previewModal" tabindex="-1">
                <div class="modal-dialog modal-lg">
                    <div class="modal-content">
                        <div class="modal-header">
                            <h5 class="modal-title">Preview Publikasi Analisis</h5>
                            <button type="button" class="btn-close" data-bs-dismiss="modal"></button>
                        </div>
                        <div class="modal-body">
                            <div class="card">
                                <div class="card-header bg-primary text-white">
                                    <h6 class="mb-0">Analisis Produksi Padi - ${data.periode}</h6>
                                </div>
                                <div class="card-body">
                                    <div class="row mb-3">
                                        <div class="col-md-6">
                                            <strong>Wilayah:</strong> ${data.kecamatan}
                                        </div>
                                        <div class="col-md-6">
                                            <strong>Faktor Penyebab:</strong> 
                                            <span class="badge bg-secondary">${data.faktor_penyebab}</span>
                                        </div>
                                    </div>
                                    <div class="row mb-3">
                                        <div class="col-md-12">
                                            <strong>Skor Risiko:</strong>
                                            <span class="badge bg-info">Cuaca: ${data.skor_risiko.skor_risiko_cuaca}</span>
                                            <span class="badge bg-warning">Hama: ${data.skor_risiko.skor_risiko_hama}</span>
                                            <span class="badge bg-danger">Total: ${data.skor_risiko.skor_risiko_total}</span>
                                        </div>
                                    </div>
                                    <div class="mb-3">
                                        <strong>Narasi Resmi:</strong>
                                        <div class="border p-3 mt-2 bg-light">
                                            ${data.narasi_final}
                                        </div>
                                    </div>
                                </div>
                            </div>
                        </div>
                        <div class="modal-footer">
                            <button type="button" class="btn btn-secondary" data-bs-dismiss="modal">Tutup</button>
                            <button type="button" class="btn btn-primary" onclick="window.print()">
                                <i class="fas fa-print"></i> Print
                            </button>
                        </div>
                    </div>
                </div>
            </div>
        `;
        
        // Remove existing modal if any
        const existingModal = document.getElementById('previewModal');
        if (existingModal) {
            existingModal.remove();
        }
        
        // Add modal to DOM
        document.body.insertAdjacentHTML('beforeend', modalHtml);
        
        // Show modal
        const modal = new bootstrap.Modal(document.getElementById('previewModal'));
        modal.show();
        
        // Clean up when modal is hidden
        document.getElementById('previewModal').addEventListener('hidden.bs.modal', function() {
            this.remove();
        });
    }
    
    function refreshRecentAnalyses() {
        // Reload the page to refresh recent analyses table
        // In a more sophisticated implementation, this could be done via AJAX
        setTimeout(() => {
            window.location.reload();
        }, 1000);
    }
    
    // Public API
    return {
        init: init,
        analyze: handleAnalyze,
        reset: handleReset,
        save: handleSaveAnalysis,
        preview: handlePreview
    };
    
})();

// Global functions for table actions
function viewAnalysis(id) {
    // Implementation for viewing existing analysis
    console.log('View analysis:', id);
    // This could open a modal or navigate to a detail page
}
