/* ============================================================
   NHIS Pregnancy Exemption Registration System – Twifo Praso
   Main JavaScript
   ============================================================ */

document.addEventListener('DOMContentLoaded', function () {

    // ------------------------------------------------------------------
    // Sidebar Toggle (desktop collapse + mobile open)
    // ------------------------------------------------------------------
    const sidebar         = document.getElementById('nhis-sidebar');
    const overlay         = document.getElementById('sidebarOverlay');
    const mobileToggle    = document.getElementById('sidebarToggleMobile');

    if (mobileToggle && sidebar) {
        mobileToggle.addEventListener('click', () => {
            sidebar.classList.toggle('open');
            overlay.classList.toggle('active');
        });
    }
    if (overlay) {
        overlay.addEventListener('click', () => {
            sidebar.classList.remove('open');
            overlay.classList.remove('active');
        });
    }

    // ------------------------------------------------------------------
    // Live Clock in topbar
    // ------------------------------------------------------------------
    const clockEl = document.getElementById('topDateTime');
    function updateClock() {
        if (!clockEl) return;
        const now = new Date();
        const opts = { weekday:'short', year:'numeric', month:'short', day:'numeric',
                       hour:'2-digit', minute:'2-digit' };
        clockEl.textContent = now.toLocaleDateString('en-GH', opts);
    }
    updateClock();
    setInterval(updateClock, 60000);

    // ------------------------------------------------------------------
    // Auto-dismiss flash alerts after 6 seconds
    // ------------------------------------------------------------------
    document.querySelectorAll('.alert.alert-dismissible').forEach(function (el) {
        setTimeout(() => {
            const bsAlert = bootstrap.Alert.getOrCreateInstance(el);
            if (bsAlert) bsAlert.close();
        }, 6000);
    });

    // ------------------------------------------------------------------
    // Confirm delete modals
    // ------------------------------------------------------------------
    document.querySelectorAll('[data-confirm]').forEach(function (el) {
        el.addEventListener('click', function (e) {
            e.preventDefault();
            const msg  = el.getAttribute('data-confirm') || 'Are you sure?';
            const href = el.getAttribute('href') || el.getAttribute('data-href');
            showConfirmModal(msg, function () {
                if (href) window.location.href = href;
            });
        });
    });

    // ------------------------------------------------------------------
    // Confirm Modal (reusable)
    // ------------------------------------------------------------------
    window.showConfirmModal = function (message, onConfirm, title) {
        let modal = document.getElementById('globalConfirmModal');
        if (!modal) {
            modal = document.createElement('div');
            modal.id = 'globalConfirmModal';
            modal.className = 'modal fade';
            modal.setAttribute('tabindex', '-1');
            modal.innerHTML = `
              <div class="modal-dialog modal-dialog-centered">
                <div class="modal-content">
                  <div class="modal-header border-0 pb-0">
                    <h5 class="modal-title fw-600" id="gcmTitle">Confirm Action</h5>
                    <button type="button" class="btn-close" data-bs-dismiss="modal"></button>
                  </div>
                  <div class="modal-body pt-2" id="gcmBody"></div>
                  <div class="modal-footer border-0 pt-0">
                    <button type="button" class="btn btn-secondary btn-sm" data-bs-dismiss="modal">Cancel</button>
                    <button type="button" class="btn btn-danger btn-sm" id="gcmConfirmBtn">Confirm</button>
                  </div>
                </div>
              </div>`;
            document.body.appendChild(modal);
        }
        document.getElementById('gcmTitle').textContent = title || 'Confirm Action';
        document.getElementById('gcmBody').textContent  = message;

        const bsModal   = new bootstrap.Modal(modal);
        const confirmBtn = document.getElementById('gcmConfirmBtn');
        const newBtn    = confirmBtn.cloneNode(true);
        confirmBtn.parentNode.replaceChild(newBtn, confirmBtn);
        newBtn.addEventListener('click', function () {
            bsModal.hide();
            if (typeof onConfirm === 'function') onConfirm();
        });
        bsModal.show();
    };

    // ------------------------------------------------------------------
    // Phone number formatter (Ghana: 0XXXXXXXXX)
    // ------------------------------------------------------------------
    document.querySelectorAll('input[data-phone]').forEach(function (inp) {
        inp.addEventListener('input', function () {
            let v = this.value.replace(/\D/g, '');
            if (v.length > 10) v = v.slice(0, 10);
            this.value = v;
        });
    });

    // ------------------------------------------------------------------
    // NHIS membership number duplicate check (AJAX)
    // ------------------------------------------------------------------
    const nhisInput   = document.getElementById('nhis_membership_number');
    const dupWarning  = document.getElementById('duplicateWarning');
    const editId      = document.getElementById('edit_record_id');

    if (nhisInput && dupWarning) {
        let debounceTimer;
        nhisInput.addEventListener('input', function () {
            clearTimeout(debounceTimer);
            const val = this.value.trim();
            if (val.length < 5) { dupWarning.classList.add('d-none'); return; }
            debounceTimer = setTimeout(function () {
                const id = editId ? editId.value : '';
                fetch(`${BASE_URL}/pregnancy/check_duplicate.php?nhis=${encodeURIComponent(val)}&exclude_id=${id}`)
                    .then(r => r.json())
                    .then(data => {
                        if (data.found) {
                            dupWarning.classList.remove('d-none');
                            const link = document.getElementById('dupRecordLink');
                            if (link) link.href = `${BASE_URL}/pregnancy/view.php?id=${data.id}`;
                            const name = document.getElementById('dupName');
                            if (name) name.textContent = data.full_name;
                        } else {
                            dupWarning.classList.add('d-none');
                        }
                    })
                    .catch(() => {});
            }, 500);
        });
    }

    // ------------------------------------------------------------------
    // EDD auto-calculation from LMP / gestational age
    // ------------------------------------------------------------------
    const lmpInput  = document.getElementById('lmp_date');
    const eddInput  = document.getElementById('expected_delivery_date');
    const gaInput   = document.getElementById('gestational_age_weeks');

    if (lmpInput && eddInput) {
        lmpInput.addEventListener('change', function () {
            if (!this.value) return;
            const lmp = new Date(this.value);
            lmp.setDate(lmp.getDate() + 280); // Naegele's rule: LMP + 280 days
            eddInput.value = lmp.toISOString().slice(0, 10);
            // Also calc gestational age
            if (gaInput) {
                const today = new Date();
                const lmpDate = new Date(this.value);
                const diffDays = Math.floor((today - lmpDate) / (1000 * 60 * 60 * 24));
                const weeks = Math.floor(diffDays / 7);
                if (weeks >= 0 && weeks <= 42) gaInput.value = weeks;
            }
        });
    }

    // ------------------------------------------------------------------
    // Form validation feedback
    // ------------------------------------------------------------------
    document.querySelectorAll('form.needs-validation').forEach(function (form) {
        form.addEventListener('submit', function (event) {
            if (!form.checkValidity()) {
                event.preventDefault();
                event.stopPropagation();
                // Scroll to first invalid field
                const first = form.querySelector(':invalid');
                if (first) {
                    first.scrollIntoView({ behavior: 'smooth', block: 'center' });
                    first.focus();
                }
            }
            form.classList.add('was-validated');
        }, false);
    });

    // ------------------------------------------------------------------
    // Print page
    // ------------------------------------------------------------------
    document.querySelectorAll('[data-print]').forEach(function (btn) {
        btn.addEventListener('click', function () {
            window.print();
        });
    });

    // ------------------------------------------------------------------
    // Tooltip initialization
    // ------------------------------------------------------------------
    document.querySelectorAll('[data-bs-toggle="tooltip"]').forEach(function (el) {
        new bootstrap.Tooltip(el, { trigger: 'hover' });
    });

    // ------------------------------------------------------------------
    // Select-all checkbox for tables
    // ------------------------------------------------------------------
    const selectAll = document.getElementById('selectAll');
    if (selectAll) {
        selectAll.addEventListener('change', function () {
            document.querySelectorAll('.row-check').forEach(cb => cb.checked = this.checked);
        });
    }

});

// ------------------------------------------------------------------
// Chart.js default color palette (NHIS theme)
// ------------------------------------------------------------------
const NHIS_COLORS = [
    '#1a7a4c','#2196d3','#d97706','#6d28d9','#be123c',
    '#0f766e','#b45309','#1a6fa8','#4a5568','#15803d',
    '#0369a1','#9333ea'
];

function nhisChart(ctx, type, labels, datasets, options) {
    return new Chart(ctx, {
        type: type,
        data: { labels: labels, datasets: datasets },
        options: Object.assign({
            responsive: true,
            maintainAspectRatio: false,
            plugins: {
                legend: {
                    labels: { font: { family: 'Inter', size: 11 }, padding: 12 }
                },
                tooltip: {
                    backgroundColor: '#1a2535',
                    titleFont: { family: 'Inter', size: 12 },
                    bodyFont:  { family: 'Inter', size: 11 },
                }
            }
        }, options || {})
    });
}

// BASE_URL passed from PHP if needed
var BASE_URL = BASE_URL || window.location.origin + '/NHIS';
