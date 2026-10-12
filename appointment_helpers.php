<?php
/* Shared code for the appointment pages. */

// Outpatient (OPD) hours. Appointments outside these times are rejected.
const OPD_START = '07:00:00';
const OPD_END   = '22:00:00';

function appointment_input()
{
    return [
        'patient_id'       => post('patient_id'),
        'doctor_id'        => post('doctor_id'),
        'appointment_date' => post('appointment_date'),
        'appointment_time' => post('appointment_time'),
        'reason'           => post('reason'),
        'status'           => post('status', 'Scheduled'),
    ];
}

/*
 * Server-side checks for booking and rescheduling.
 * $appointmentId: 0 when booking, the id when editing.
 * $currentDoctorId: lets an edit keep a doctor who became inactive later.
 * $newPatient: true when the patient is being registered in the same form
 *              (patients/create.php), so there is no patient_id yet.
 */
function validate_appointment(array &$d, $appointmentId = 0, $currentDoctorId = 0, $newPatient = false)
{
    $pdo = db();
    $errors = [];

    // Patient must exist (skipped when the patient is being registered now)
    if (!$newPatient) {
        $patient = false;
        if (ctype_digit($d['patient_id'])) {
            $stmt = $pdo->prepare('SELECT patient_id FROM patients WHERE patient_id = ?');
            $stmt->execute([(int) $d['patient_id']]);
            $patient = $stmt->fetch();
        }
        if (!$patient) {
            $errors[] = 'Please choose a registered patient.';
        }
    }

    // Doctor must exist and be active
    $doctor = false;
    if (ctype_digit($d['doctor_id'])) {
        $stmt = $pdo->prepare('SELECT doctor_id, is_active FROM doctors WHERE doctor_id = ?');
        $stmt->execute([(int) $d['doctor_id']]);
        $doctor = $stmt->fetch();
    }
    if (!$doctor) {
        $errors[] = 'Please choose a doctor.';
    } elseif ((int) $doctor['is_active'] !== 1 && (int) $doctor['doctor_id'] !== (int) $currentDoctorId) {
        $errors[] = 'This doctor is not active. Please choose an active doctor.';
    }

    // Date: real calendar date, not in the past
    if (!valid_date($d['appointment_date'])) {
        $errors[] = 'Please enter a valid appointment date.';
    } elseif ($d['appointment_date'] < today()) {
        $errors[] = 'The appointment date cannot be in the past.';
    }

    // Time: real time inside OPD hours
    $time = normalise_time($d['appointment_time']);
    if ($time === false) {
        $errors[] = 'Please enter a valid appointment time.';
    } elseif ($time < OPD_START || $time > OPD_END) {
        $errors[] = 'Appointments are only between ' . fmt_time(OPD_START) . ' and ' . fmt_time(OPD_END) . '.';
    } else {
        $d['appointment_time'] = $time;   // store as HH:MM:SS
    }

    $reasonLen = mb_strlen($d['reason']);
    if ($reasonLen < 3 || $reasonLen > 255) {
        $errors[] = 'Reason for visit is required (3–255 characters).';
    }

    if ($appointmentId === 0 && !in_array($d['status'], ['Scheduled', 'Completed'], true)) {
        $errors[] = 'A new appointment can only be Scheduled or Completed.';
    }

    // Duplicate / clashing bookings (cancelled visits are ignored)
    if (!$errors) {
        $stmt = $pdo->prepare(
            "SELECT appointment_code FROM appointments
             WHERE doctor_id = ? AND appointment_date = ? AND appointment_time = ?
               AND status <> 'Cancelled' AND appointment_id <> ?"
        );
        $stmt->execute([(int) $d['doctor_id'], $d['appointment_date'], $d['appointment_time'], $appointmentId]);
        if ($code = $stmt->fetchColumn()) {
            $errors[] = 'This doctor already has appointment ' . $code . ' at that date and time. Please pick another time.';
        }

        // A new patient has no appointments yet, so this check is only for existing patients.
        if (!$newPatient) {
            $stmt = $pdo->prepare(
                "SELECT appointment_code FROM appointments
                 WHERE patient_id = ? AND appointment_date = ?
                   AND (doctor_id = ? OR appointment_time = ?)
                   AND status <> 'Cancelled' AND appointment_id <> ?"
            );
            $stmt->execute([(int) $d['patient_id'], $d['appointment_date'], (int) $d['doctor_id'], $d['appointment_time'], $appointmentId]);
            if ($code = $stmt->fetchColumn()) {
                $errors[] = 'This patient already has appointment ' . $code . ' on that day with this doctor or at the same time.';
            }
        }
    }
    return $errors;
}

/* Patients for the booking dropdown, with their preferred doctor. */
function patient_choices()
{
    return db()->query(
        'SELECT patient_id, patient_code, full_name, phone, preferred_doctor_id FROM patients ORDER BY full_name'
    )->fetchAll();
}

/* Active doctors (plus one extra doctor when editing an old booking). */
function doctor_choices($includeDoctorId = 0)
{
    $stmt = db()->prepare(
        'SELECT doctor_id, doctor_code, full_name, specialization, consultation_fee, is_active
         FROM doctors WHERE is_active = 1 OR doctor_id = ? ORDER BY full_name'
    );
    $stmt->execute([(int) $includeDoctorId]);
    return $stmt->fetchAll();
}

/* Facts that decide what can still be done with one appointment. */
function appointment_state($appointmentId)
{
    $stmt = db()->prepare(
        'SELECT a.*, t.token_id, t.status AS token_status, b.bill_id
         FROM appointments a
         LEFT JOIN token_queue t ON t.appointment_id = a.appointment_id
         LEFT JOIN bills b ON b.appointment_id = a.appointment_id
         WHERE a.appointment_id = ?'
    );
    $stmt->execute([$appointmentId]);
    return $stmt->fetch();
}

function token_is_active($tokenStatus)
{
    return in_array($tokenStatus, ['Waiting', 'In Consultation'], true);
}

/*
 * Cancels an appointment without deleting it.
 * An open token for it is cancelled in the same transaction.
 * Returns an error message, or '' on success.
 */
function cancel_appointment($appointmentId)
{
    $pdo = db();
    $appt = appointment_state($appointmentId);
    if (!$appt) {
        return 'Appointment not found.';
    }
    if ($appt['status'] !== 'Scheduled') {
        return 'Only scheduled appointments can be cancelled. ' . $appt['appointment_code'] . ' is ' . $appt['status'] . '.';
    }
    if ($appt['bill_id']) {
        return 'Appointment ' . $appt['appointment_code'] . ' already has a bill and cannot be cancelled.';
    }
    try {
        $pdo->beginTransaction();
        $stmt = $pdo->prepare("UPDATE appointments SET status = 'Cancelled' WHERE appointment_id = ?");
        $stmt->execute([$appointmentId]);
        $stmt = $pdo->prepare(
            "UPDATE token_queue SET status = 'Cancelled'
             WHERE appointment_id = ? AND status IN ('Waiting', 'In Consultation')"
        );
        $stmt->execute([$appointmentId]);
        $pdo->commit();
    } catch (PDOException $e) {
        if ($pdo->inTransaction()) {
            $pdo->rollBack();
        }
        return db_error_message($e, 'The appointment could not be cancelled. Please try again.');
    }
    return '';
}

/* Draws the patient/doctor/date/time/reason fields. */
function render_appointment_fields(array $d, array $patients, array $doctors, $patientLocked = false, $showStatus = true)
{
    ?>
    <div class="form-grid">
        <?php if (!$patientLocked): ?>
            <div class="field full">
                <label for="patientFilter">Find patient</label>
                <input class="input" id="patientFilter" type="search" placeholder="Type a name, ID or phone to filter the list…">
            </div>
        <?php endif; ?>
        <div class="field full">
            <label for="patient_id">Patient <span class="req">*</span></label>
            <?php if ($patientLocked): ?>
                <?php foreach ($patients as $p): if ((string) $p['patient_id'] === (string) $d['patient_id']): ?>
                    <input class="input" readonly value="<?= e($p['full_name'] . ' · ' . $p['patient_code']) ?>">
                <?php endif; endforeach; ?>
                <input type="hidden" name="patient_id" value="<?= e($d['patient_id']) ?>">
            <?php else: ?>
                <select class="input" id="patient_id" name="patient_id" required data-preselect-doctor>
                    <option value="">Select a registered patient</option>
                    <?php foreach ($patients as $p): ?>
                        <option value="<?= (int) $p['patient_id'] ?>"
                                data-preferred="<?= e((string) ($p['preferred_doctor_id'] ?? '')) ?>"
                                <?= (string) $d['patient_id'] === (string) $p['patient_id'] ? 'selected' : '' ?>>
                            <?= e($p['full_name'] . ' · ' . $p['patient_code'] . ' · ' . $p['phone']) ?>
                        </option>
                    <?php endforeach; ?>
                </select>
                <div class="hint" id="preferredNote">If the patient has a preferred doctor, that doctor is selected for you.</div>
            <?php endif; ?>
        </div>
        <?php render_visit_fields($d, $doctors); ?>
        <?php if ($showStatus): ?>
            <div class="field">
                <label for="status">Appointment status</label>
                <select class="input" id="status" name="status">
                    <?php foreach (['Scheduled', 'Completed'] as $s): ?>
                        <option value="<?= e($s) ?>" <?= $d['status'] === $s ? 'selected' : '' ?>><?= e($s) ?></option>
                    <?php endforeach; ?>
                </select>
                <div class="hint">Use "Completed" only for a walk-in visit that is already over.</div>
            </div>
        <?php endif; ?>
    </div>
    <?php
}

/*
 * Doctor, date, time and reason fields.
 * Used by the booking form above and by the patient registration form
 * (patients/create.php), so both follow the same rules and look the same.
 * Call it inside a <div class="form-grid">.
 */
function render_visit_fields(array $d, array $doctors)
{
    ?>
        <div class="field full">
            <label for="doctor_id">Doctor <span class="req">*</span></label>
            <select class="input" id="doctor_id" name="doctor_id" required>
                <option value="">Select a doctor</option>
                <?php foreach ($doctors as $doc): ?>
                    <option value="<?= (int) $doc['doctor_id'] ?>" <?= (string) $d['doctor_id'] === (string) $doc['doctor_id'] ? 'selected' : '' ?>>
                        <?= e($doc['full_name'] . ' — ' . $doc['specialization'] . ' (' . money($doc['consultation_fee']) . ')' . ($doc['is_active'] ? '' : ' [inactive]')) ?>
                    </option>
                <?php endforeach; ?>
            </select>
        </div>
        <div class="field">
            <label for="appointment_date">Date <span class="req">*</span></label>
            <input class="input" id="appointment_date" name="appointment_date" type="date" min="<?= e(today()) ?>" required value="<?= e($d['appointment_date']) ?>">
        </div>
        <div class="field">
            <label for="appointment_time">Time <span class="req">*</span></label>
            <input class="input" id="appointment_time" name="appointment_time" type="time" min="07:00" max="22:00" required value="<?= e(substr($d['appointment_time'], 0, 5)) ?>">
            <div class="hint">OPD hours: 7:00 AM – 10:00 PM</div>
        </div>
        <div class="field full">
            <label for="reason">Reason for visit <span class="req">*</span></label>
            <input class="input" id="reason" name="reason" maxlength="255" required value="<?= e($d['reason']) ?>" placeholder="e.g. Fever and cough">
        </div>
    <?php
}
