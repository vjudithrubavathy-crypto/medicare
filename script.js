/*
 * MediCare - small helpers for the browser.
 * JavaScript only makes the pages nicer to use.
 * All real checks and calculations are done again in PHP on the server.
 */
document.addEventListener('DOMContentLoaded', function () {

    /* ---------- Mobile menu ---------- */
    var sidebar = document.getElementById('sidebar');
    var backdrop = document.getElementById('sidebarBackdrop');
    var toggle = document.getElementById('menuToggle');
    if (toggle && sidebar && backdrop) {
        toggle.addEventListener('click', function () {
            sidebar.classList.add('open');
            backdrop.classList.add('show');
        });
        backdrop.addEventListener('click', function () {
            sidebar.classList.remove('open');
            backdrop.classList.remove('show');
        });
    }

    /* ---------- Close buttons on messages ---------- */
    document.querySelectorAll('.alert-close').forEach(function (btn) {
        btn.addEventListener('click', function () {
            btn.parentElement.remove();
        });
    });

    /* ---------- "Are you sure?" before delete / cancel ---------- */
    document.querySelectorAll('form[data-confirm]').forEach(function (form) {
        form.addEventListener('submit', function (event) {
            if (!window.confirm(form.getAttribute('data-confirm'))) {
                event.preventDefault();
            }
        });
    });

    /* ---------- Stop double-clicks on important forms ---------- */
    document.querySelectorAll('form[data-once-submit]').forEach(function (form) {
        form.addEventListener('submit', function (event) {
            // A disabled button is not sent with the form, so copy its name/value first.
            var clicked = event.submitter;
            if (clicked && clicked.name) {
                var keep = document.createElement('input');
                keep.type = 'hidden';
                keep.name = clicked.name;
                keep.value = clicked.value;
                form.appendChild(keep);
            }
            form.querySelectorAll('button[type="submit"]').forEach(function (btn) {
                btn.disabled = true;
                btn.textContent = 'Saving…';
            });
        });
    });

    /* ---------- Dropdowns that save as soon as they change ---------- */
    document.querySelectorAll('select[data-autosubmit]').forEach(function (select) {
        select.addEventListener('change', function () {
            var form = select.form;
            var message = form.getAttribute('data-confirm');
            if (!message && select.value === 'Cancelled') {
                message = 'Cancel this appointment? The record is kept, but it cannot get a token or a bill.';
            }
            if (message && !window.confirm(message)) {
                select.value = select.getAttribute('data-original');
                return;
            }
            form.submit();
        });
    });
    // The small "Save" buttons are only needed when JavaScript is off.
    document.querySelectorAll('.js-hide').forEach(function (el) {
        el.style.display = 'none';
    });

    /* ---------- Appointment form: filter patients, preselect doctor ---------- */
    var patientSelect = document.getElementById('patient_id');
    var patientFilter = document.getElementById('patientFilter');
    var doctorSelect = document.getElementById('doctor_id');

    if (patientSelect && patientFilter) {
        patientFilter.addEventListener('input', function () {
            var term = patientFilter.value.toLowerCase();
            Array.prototype.forEach.call(patientSelect.options, function (opt) {
                if (!opt.value) { return; }
                var match = opt.text.toLowerCase().indexOf(term) !== -1;
                opt.hidden = !match;
                opt.disabled = !match;
            });
        });
    }

    if (patientSelect && doctorSelect && patientSelect.hasAttribute('data-preselect-doctor')) {
        patientSelect.addEventListener('change', function () {
            var opt = patientSelect.options[patientSelect.selectedIndex];
            var note = document.getElementById('preferredNote');
            var preferred = opt ? opt.getAttribute('data-preferred') : '';
            var found = false;
            if (preferred) {
                Array.prototype.forEach.call(doctorSelect.options, function (d) {
                    if (d.value === preferred) { found = true; }
                });
            }
            if (found) {
                doctorSelect.value = preferred;
                if (note) { note.textContent = 'Preferred doctor selected. You can change it.'; }
            } else if (note) {
                note.textContent = preferred
                    ? 'This patient\'s preferred doctor is not active now. Please choose a doctor.'
                    : 'No preferred doctor saved. Please choose a doctor.';
            }
        });
    }

    /* ---------- Billing form: live preview of the total ---------- */
    var billForm = document.getElementById('billForm');
    if (billForm) {
        var apptSelect = document.getElementById('appointment_id');
        var patientBox = document.getElementById('patient_display');
        var doctorBox = document.getElementById('doctor_display');
        var feeBox = document.getElementById('consultation_fee');
        var fields = ['lab_charges', 'medicine_charges', 'discount'].map(function (id) {
            return document.getElementById(id);
        });

        // Convert "120.50" to 12050 paise. Returns NaN if the text is not money.
        var toPaise = function (text) {
            text = String(text).trim();
            if (text === '') { return 0; }
            if (!/^\d{1,7}(\.\d{1,2})?$/.test(text)) { return NaN; }
            var parts = text.split('.');
            var paise = parts[1] ? parseInt((parts[1] + '0').slice(0, 2), 10) : 0;
            return parseInt(parts[0], 10) * 100 + paise;
        };
        var show = function (paise) {
            return '₹' + (paise / 100).toLocaleString('en-IN', { minimumFractionDigits: 2, maximumFractionDigits: 2 });
        };

        var update = function () {
            var opt = apptSelect.options[apptSelect.selectedIndex];
            var fee = opt && opt.value ? opt.getAttribute('data-fee') : '0';
            patientBox.value = opt && opt.value ? opt.getAttribute('data-patient') : '';
            doctorBox.value = opt && opt.value ? opt.getAttribute('data-doctor') : '';
            feeBox.value = opt && opt.value ? fee : '';

            var feeP = toPaise(fee || '0');
            var lab = toPaise(fields[0].value);
            var med = toPaise(fields[1].value);
            var disc = toPaise(fields[2].value);
            var msg = document.getElementById('billMessage');

            if (isNaN(lab) || isNaN(med) || isNaN(disc)) {
                msg.textContent = 'Amounts must be numbers like 250 or 250.50 (no minus sign).';
                return;
            }
            var subtotal = feeP + lab + med;
            document.getElementById('sumSubtotal').textContent = show(subtotal);
            document.getElementById('sumDiscount').textContent = '− ' + show(disc);
            if (disc > subtotal) {
                msg.textContent = 'Discount cannot be more than the subtotal.';
                document.getElementById('sumTotal').textContent = '—';
            } else {
                msg.textContent = '';
                document.getElementById('sumTotal').textContent = show(subtotal - disc);
            }
        };

        apptSelect.addEventListener('change', update);
        fields.forEach(function (f) { f.addEventListener('input', update); });
        update();
    }

    /* ---------- Scroll to a newly booked appointment ---------- */
    var newRow = document.querySelector('tr.highlight');
    if (newRow && window.location.search.indexOf('highlight=') !== -1) {
        newRow.scrollIntoView({ block: 'center' });
    }

    /* ---------- Receipt print button ---------- */
    var printBtn = document.getElementById('printBtn');
    if (printBtn) {
        printBtn.addEventListener('click', function () { window.print(); });
    }
});
