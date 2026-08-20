import fs from 'fs';
import path from 'path';
import { fileURLToPath } from 'url';

const __filename = fileURLToPath(import.meta.url);
const __dirname = path.dirname(__filename);

const chromePaths = [
    'C:\\Program Files (x86)\\Microsoft\\Edge\\Application\\msedge.exe',
    'C:\\Program Files\\Google\\Chrome\\Application\\chrome.exe'
];

let executablePath = chromePaths.find(p => fs.existsSync(p));

if (!executablePath) {
    console.error('Error: Neither Chrome nor Edge executable was found.');
    process.exit(1);
}

const fileMap = {
    1: "01_arsitektur_sistem_keseluruhan.png",
    2: "02_alur_autentikasi_dan_otorisasi_rbac.png",
    3: "03_siklus_hidup_laporan_hama_opt.png",
    4: "04_pipeline_data_scraping.png",
    5: "05_alur_import_data_excel_dan_ksa.png",
    6: "06_dashboard_dan_visualisasi_data.png",
    7: "07_sistem_irigasi_dan_rule_engine.png",
    8: "08_feedback_dan_masukan_pengguna.png",
    9: "09_integrasi_api_eksternal.png",
    10: "10_erd_entity_relationship_diagram.png"
};

async function exportFlowcharts() {
    console.log(`Starting Puppeteer export with executable: ${executablePath}`);
    const puppeteer = (await import('puppeteer-core')).default;

    const browser = await puppeteer.launch({
        executablePath,
        headless: "new",
        args: ['--no-sandbox', '--disable-setuid-sandbox', '--force-device-scale-factor=2']
    });

    const page = await browser.newPage();
    await page.setViewport({
        width: 3840,
        height: 2400,
        deviceScaleFactor: 2
    });

    const htmlPath = `file:///${path.resolve(__dirname, 'visual_flowcharts_jagapadi.html').replace(/\\/g, '/')}`;
    console.log(`Loading page: ${htmlPath}`);
    await page.goto(htmlPath, { waitUntil: 'networkidle0' });

    // Wait for Mermaid initialization
    await page.waitForFunction(() => typeof window.mermaid !== 'undefined');

    const outputDir = __dirname;

    for (let id = 1; id <= 10; id++) {
        const filename = fileMap[id];
        const outputPath = path.join(outputDir, filename);

        console.log(`[${id}/10] Rendering Diagram ${id}...`);
        
        // Trigger tab switch in the page
        await page.evaluate((tabId) => {
            window.switchTab(tabId);
            window.setViewMode('explorer');
        }, id);

        // Delay for SVG rendering
        await new Promise(r => setTimeout(r, 1500));

        // Inject high-contrast styling for crisp export
        await page.evaluate(() => {
            const viewer = document.getElementById('mermaidViewer');
            if (viewer) {
                viewer.style.padding = '50px';
                viewer.style.backgroundColor = '#0b0f19';
                viewer.style.borderRadius = '24px';
                viewer.style.boxShadow = '0 25px 50px -12px rgba(0, 0, 0, 0.7)';
                
                const svg = viewer.querySelector('svg');
                if (svg) {
                    svg.style.margin = 'auto';
                    svg.style.backgroundColor = 'transparent';
                }
            }
        });

        const element = await page.$('#mermaidViewer');
        if (element) {
            await element.screenshot({
                path: outputPath,
                omitBackground: false
            });

            const stats = fs.statSync(outputPath);
            console.log(`✅ Exported: ${filename} (${(stats.size / 1024).toFixed(1)} KB)`);
        } else {
            console.error(`❌ Failed to find #mermaidViewer for diagram ${id}`);
        }
    }

    await browser.close();
    console.log("🎉 All 10 flowcharts successfully exported to PNG format!");
}

exportFlowcharts().catch(err => {
    console.error("Export Error:", err);
    process.exit(1);
});
