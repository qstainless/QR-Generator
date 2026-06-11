const form = document.getElementById('qr-form');
const activeTabField = document.getElementById('active_tab');
const tabs = Array.from(document.querySelectorAll('.tab'));
const panels = Array.from(document.querySelectorAll('.tab-panel'));
const sizeInput = document.getElementById('size');
const logoWidthInput = document.getElementById('logo_width');
const logoXInput = document.getElementById('logo_x');
const logoYInput = document.getElementById('logo_y');
const analysisMessage = document.getElementById('analysis-message');
const layoutStatus = document.getElementById('layout-status');
const layoutConstraints = document.getElementById('layout-constraints');
const logoStatus = document.getElementById('logo-status');
const metricVersion = document.getElementById('metric-version');
const metricModules = document.getElementById('metric-modules');
const metricPpm = document.getElementById('metric-ppm');
const metricLogoMax = document.getElementById('metric-logo-max');

let debounceTimer = null;
let currentController = null;

function setActiveTab(tabName) {
    activeTabField.value = tabName;

    tabs.forEach((tab) => {
        tab.classList.toggle('active', tab.dataset.tab === tabName);
    });

    panels.forEach((panel) => {
        panel.classList.toggle('active', panel.dataset.panel === tabName);
    });
}

tabs.forEach((tab) => {
    tab.addEventListener('click', () => setActiveTab(tab.dataset.tab));
});

function scheduleAnalysis() {
    window.clearTimeout(debounceTimer);
    debounceTimer = window.setTimeout(runAnalysis, 350);
}

function clampLogoPositionFields() {
    if (!logoXInput || !logoYInput) {
        return;
    }

    if (logoXInput.value === '' || logoYInput.value === '') {
        return;
    }

    const qrSize = Number(sizeInput.value);
    const logoSize = Number(logoWidthInput.value);
    const moduleCount = Number(metricModules.textContent);

    if (!Number.isFinite(qrSize) || !Number.isFinite(logoSize) || !Number.isFinite(moduleCount) || moduleCount <= 0) {
        return;
    }

    const maxOrigin = Math.max(0, qrSize - logoSize);
    const exclusion = Math.ceil((7 * (qrSize / moduleCount)) + 10);
    const bottomOverlapStart = qrSize - exclusion - logoSize;
    const safeMiddleStart = Math.min(maxOrigin, Math.max(0, exclusion));
    const safeMiddleEnd = Math.max(safeMiddleStart, Math.min(maxOrigin, bottomOverlapStart));

    let x = Math.min(Math.max(Number(logoXInput.value), 0), maxOrigin);
    let y = Math.min(Math.max(Number(logoYInput.value), 0), maxOrigin);

    if (y < exclusion) {
        const minX = exclusion;
        const maxX = qrSize - exclusion - logoSize;

        if (minX > maxX) {
            y = safeMiddleStart;
            x = Math.min(Math.max(x, 0), maxOrigin);
        } else {
            x = Math.min(Math.max(x, minX), maxX);
        }
    } else if (y > bottomOverlapStart) {
        y = Math.max(safeMiddleEnd, Math.min(y, maxOrigin));
        x = Math.min(Math.max(x, exclusion), maxOrigin);
    }

    logoXInput.value = String(Math.round(x));
    logoYInput.value = String(Math.round(y));
}

async function runAnalysis() {
    if (currentController) {
        currentController.abort();
    }

    currentController = new AbortController();
    const formData = new FormData(form);

    analysisMessage.textContent = 'Rechecking payload, layout, and logo limits...';
    analysisMessage.className = 'status';

    try {
        const response = await fetch('?analyze=1', {
            method: 'POST',
            body: formData,
            signal: currentController.signal,
        });

        const result = await response.json();
        if (!result.ready) {
            analysisMessage.textContent = result.message || 'Analysis could not run yet.';
            analysisMessage.className = 'status error';
            return;
        }

        analysisMessage.textContent = result.message;
        analysisMessage.className = 'status ok';

        metricVersion.textContent = result.layout.version ?? '—';
        metricModules.textContent = result.layout.module_count ?? '—';
        metricPpm.textContent = result.layout.pixels_per_module ?? '—';
        metricLogoMax.textContent = result.logo.maximum_width === null ? '—' : `${result.logo.maximum_width} px`;

        layoutStatus.textContent = result.layout.message;
        layoutStatus.className = result.layout.current_valid ? 'status ok' : 'status error';

        const minimumSize = result.layout.minimum_size;
        layoutConstraints.textContent = minimumSize === null
            ? 'The payload is still too dense for the current constraints.'
            : `Minimum size: ${minimumSize} px. ${result.layout.margin_note ?? ''}`;

        if (typeof minimumSize === 'number') {
            sizeInput.min = String(minimumSize);
            if (Number(sizeInput.value) < minimumSize) {
                sizeInput.value = String(minimumSize);
            }
        }

        logoStatus.textContent = result.logo.message;
        logoStatus.className = result.logo.current_valid ? 'status ok' : 'status';

        if (typeof result.logo.maximum_width === 'number') {
            logoWidthInput.max = String(result.logo.maximum_width);
            if (Number(logoWidthInput.value) > result.logo.maximum_width) {
                logoWidthInput.value = String(result.logo.maximum_width);
            }
        }

        clampLogoPositionFields();
    } catch (error) {
        if (error.name === 'AbortError') {
            return;
        }

        analysisMessage.textContent = 'Analysis request failed.';
        analysisMessage.className = 'status error';
    }
}

form.addEventListener('input', scheduleAnalysis);
form.addEventListener('change', scheduleAnalysis);
logoXInput?.addEventListener('input', clampLogoPositionFields);
logoYInput?.addEventListener('input', clampLogoPositionFields);
logoWidthInput?.addEventListener('input', clampLogoPositionFields);
sizeInput?.addEventListener('input', clampLogoPositionFields);
runAnalysis();
