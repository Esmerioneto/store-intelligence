/**
 * Esmerio Neto
 *
 * NOTICE OF LICENSE
 *
 * DISCLAIMER
 *
 * Do not edit or add to this file if you wish to upgrade Magento to newer
 * versions in the future.
 *
 * @category Egsn
 * @package Egsn_StoreIntelligence
 *
 * @copyright Copyright (c) 2026 Esmerio Neto.
 *
 * @author Esmerio Neto <esmerioneto@gmail.com>
 */
define(['chartjs', 'mage/translate'], function (Chart, $t) {
    'use strict';

    return function (config, element) {
        initChart(element);
        initRunButton(element);
    };

    function initChart(root) {
        var chartData = root.querySelector('#si-chart-data');
        if (!chartData) return;

        var labels = JSON.parse(chartData.getAttribute('data-labels') || '[]');
        var scores = JSON.parse(chartData.getAttribute('data-scores') || '[]');
        if (!scores.length) return;

        var ctx = root.querySelector('#scoreChart');
        if (!ctx) return;

        new Chart(ctx, {
            type: 'line',
            data: {
                labels: labels,
                datasets: [{
                    label: 'Score',
                    data: scores,
                    borderColor: '#1979c3',
                    backgroundColor: 'rgba(25,121,195,.1)',
                    pointBackgroundColor: '#1979c3',
                    tension: 0.3,
                    fill: true
                }]
            },
            options: {
                responsive: true,
                maintainAspectRatio: false,
                scales: {
                    y: { min: 0, max: 100, ticks: { stepSize: 20 } }
                },
                plugins: { legend: { display: false } }
            }
        });
    }

    function initRunButton(root) {
        var btn = root.querySelector('#si-run-btn');
        var msg = root.querySelector('#si-msg');
        if (!btn) return;

        var url       = root.getAttribute('data-run-url');
        var statusUrl = root.getAttribute('data-status-url');
        var formKey   = root.getAttribute('data-form-key');

        function showMsg(text, cssClass) {
            msg.textContent   = text;
            msg.className     = 'si-msg ' + cssClass;
            msg.style.display = 'block';
        }

        function fetchStatus() {
            return fetch(statusUrl, {headers: {'X-Requested-With': 'XMLHttpRequest'}})
                .then(function (r) { return r.json(); });
        }

        /**
         * Acompanha a fila: quando surge uma análise mais nova que a baseline e ela
         * conclui (ou falha), recarrega a página para atualizar score e cards.
         */
        function pollStatus(baselineId) {
            var interval = 5000,
                maxMs    = 15 * 60 * 1000,
                elapsed  = 0;

            var timer = setInterval(function () {
                elapsed += interval;
                if (elapsed >= maxMs) {
                    clearInterval(timer);
                    btn.disabled = false;
                    btn.innerHTML = '&#9654; ' + $t('Analyze Now');
                    showMsg($t('Tracking timed out. Reload the page to see the result.'), 'error');
                    return;
                }
                fetchStatus().then(function (data) {
                    if (data.id === null || data.id === baselineId) {
                        return; // ainda na fila
                    }
                    if (data.status === 'completed' || data.status === 'failed') {
                        clearInterval(timer);
                        showMsg($t('Analysis completed. Refreshing…'), 'success');
                        window.location.reload();
                    } else {
                        showMsg($t('Analysis running…'), 'success');
                    }
                }).catch(function () { /* tenta de novo no próximo tick */ });
            }, interval);
        }

        btn.addEventListener('click', function () {
            btn.disabled      = true;
            btn.textContent   = $t('Please wait…');
            msg.style.display = 'none';
            msg.className     = 'si-msg';

            fetchStatus().catch(function () { return {id: null}; }).then(function (baseline) {
                return fetch(url, {
                    method: 'POST',
                    headers: {
                        'Content-Type': 'application/x-www-form-urlencoded',
                        'X-Requested-With': 'XMLHttpRequest'
                    },
                    body: 'form_key=' + encodeURIComponent(formKey)
                })
                .then(function (r) {
                    if (!(r.headers.get('content-type') || '').includes('application/json')) {
                        window.location.reload();
                        throw new Error($t('Session expired. Reloading…'));
                    }
                    return r.json();
                })
                .then(function (data) {
                    showMsg(data.message || (data.success ? $t('Success!') : $t('Unknown error.')),
                        data.success ? 'success' : 'error');
                    if (data.success) {
                        showMsg($t('Analysis queued. Waiting for processing…'), 'success');
                        pollStatus(baseline.id);
                    } else {
                        btn.disabled  = false;
                        btn.innerHTML = '&#9654; ' + $t('Analyze Now');
                    }
                });
            })
            .catch(function (e) {
                showMsg($t('Request error:') + ' ' + e.message, 'error');
                btn.disabled  = false;
                btn.innerHTML = '&#9654; ' + $t('Analyze Now');
            });
        });
    }
});
