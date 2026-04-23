/**
 * pages/dashboard.js
 * Inicializa os gráficos Chart.js do Dashboard.
 * Dados via <script id="app-data" type="application/json"> em index.php
 */
document.addEventListener('DOMContentLoaded', function () {
    const raw = document.getElementById('app-data');
    if (!raw) return;

    const data = JSON.parse(raw.textContent);

    // ---- Bar: ocorrências por justificativa ---------------
    const canvasJust = document.getElementById('chartJust');
    if (canvasJust && data.justData.length > 0) {
        // Ordenar do maior para o menor
        const order = data.justData
            .map((v, i) => i)
            .sort((a, b) => data.justData[b] - data.justData[a]);
        const sortedLabels = order.map(i => data.justLabels[i]);
        const sortedData   = order.map(i => data.justData[i]);
        const sortedColors = order.map(i => data.justColors[i]);
        const sortedKeys   = order.map(i => data.justKeys[i]);

        new Chart(canvasJust, {
            type: 'bar',
            data: {
                labels: sortedLabels,
                datasets: [{
                    data: sortedData,
                    backgroundColor: sortedColors,
                    borderRadius: 4,
                    borderSkipped: false,
                }],
            },
            options: {
                indexAxis: 'y',
                responsive: true,
                maintainAspectRatio: false,
                onClick: (evt, elements) => {
                    if (!elements.length) return;
                    const idx     = elements[0].index;
                    const key     = sortedKeys[idx];
                    const label   = sortedLabels[idx];
                    const details = data.justDetails[key] || [];

                    document.getElementById('donutDetailTitle').textContent = label;
                    const headerEl = document.getElementById('donutDetailHeader');
                    headerEl.style.background = sortedColors[idx];
                    headerEl.style.color      = '#fff';
                    const closeBtn = headerEl.querySelector('.btn-close');
                    if (closeBtn) closeBtn.classList.add('btn-close-white');

                    const tbody = document.getElementById('donutDetailBody');
                    tbody.innerHTML = details.length
                        ? details.map(d =>
                            `<tr>
                                <td>${d.data}</td>
                                <td class="fw-medium">${d.prontuario}</td>
                                <td>${d.nome}</td>
                            </tr>`
                          ).join('')
                        : '<tr><td colspan="3" class="text-center text-muted py-3">Sem registros</td></tr>';

                    new bootstrap.Modal(document.getElementById('donutDetailModal')).show();
                },
                onHover: (evt, elements) => {
                    evt.native.target.style.cursor = elements.length ? 'pointer' : 'default';
                },
                plugins: {
                    legend: { display: false },
                    tooltip: {
                        callbacks: {
                            label: ctx => ` ${ctx.parsed.x} ocorrência${ctx.parsed.x !== 1 ? 's' : ''}`,
                        },
                    },
                },
                scales: {
                    x: {
                        beginAtZero: true,
                        ticks: { precision: 0, font: { size: 11 } },
                        grid: { color: 'rgba(0,0,0,0.06)' },
                    },
                    y: {
                        ticks: { font: { size: 11 } },
                        grid: { display: false },
                    },
                },
            },
        });
    }

    // ---- Line: % presença por dia útil ---------------------
    const canvasPresenca = document.getElementById('chartPresenca');
    if (canvasPresenca) {
        new Chart(canvasPresenca, {
            type: 'line',
            data: {
                labels: data.dias,
                datasets: [{
                    label: '% Presença',
                    data: data.presenca,
                    borderColor: '#1565C0',
                    backgroundColor: 'rgba(21,101,192,0.1)',
                    fill: true,
                    tension: 0.35,
                    pointRadius: 3,
                    pointBackgroundColor: '#1565C0',
                    pointBorderColor: '#fff',
                    pointBorderWidth: 1.5,
                }],
            },
            options: {
                responsive: true,
                maintainAspectRatio: false,
                scales: {
                    y: {
                        min: 80,
                        max: 100,
                        ticks: { callback: v => v + '%', font: { size: 11 } },
                        grid: { color: '#eee' },
                    },
                    x: {
                        ticks: { font: { size: 10 }, maxRotation: 45 },
                        grid: { display: false },
                    },
                },
                plugins: {
                    legend: { display: false },
                    tooltip: {
                        callbacks: { label: ctx => ' ' + ctx.parsed.y + '%' },
                    },
                },
            },
        });
    }
});
