'use strict';
function fitBatchResults() {
    document.querySelectorAll('.batch-print .print-sheet').forEach(function (sheet) {
        var content = sheet.querySelector('.print-content');
        content.style.zoom = '1';
        if (window.getComputedStyle(sheet.parentElement).display !== 'grid') { return; }
        var style = window.getComputedStyle(sheet);
        var available = sheet.clientHeight - parseFloat(style.paddingTop) - parseFloat(style.paddingBottom);
        content.style.zoom = Math.min(1, available / content.getBoundingClientRect().height);
    });
}

window.addEventListener('load', fitBatchResults);
window.addEventListener('resize', fitBatchResults);
window.addEventListener('beforeprint', fitBatchResults);
window.addEventListener('afterprint', fitBatchResults);
document.getElementById('print-results').addEventListener('click', function () { window.print(); });
