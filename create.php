<?php
/*
 * Register a new patient AND book their first appointment in one step.
 *
 * Order on the form: 1. Appointment details  2. Patient details.
 * Both parts are checked on the server. If everything is valid, the
 * patient row and the appointment row are saved together in ONE
 * transaction: if either INSERT fails, both are rolled back.
 *
 * No token is created here. Tokens are still given from the Token Queue
 * page, on the day of the visit.
 */
require_once __DIR__ . '/../includes/functions.php';
require_once __DIR__ . '/patient_helpers.php';
require_once __DIR__ . '/../appointments/appointment_helpers.php';

$pdo = db();
$doctors = doctor_choices();   // active doctors with specialization and fee

$patient = [
    'full_name' => '', 'age' => '', 'gender' => '', 'phone' => '',
    'blood_group' => '', 'address' => '', 'preferred_doctor_id' => '',
];
$visit = [
    'patient_id' => '', 'doctor_id' => '', 'appointment_date' => today(),
    'appointment_time' => '', 'reason' => '', 'status' => 'Scheduled',
];
$visitErrors = [];
$patientErrors = [];
$saveError = '';

if (is_post()) {
    require_post_with_csrf('patients/create.php');

    // Same form sent twice (double-click, Back button, refresh): do not save again.
    if (!use_once_token()) {
        set_flash('info', 'This registration was already submitted, so it was not saved a second time. Check the list below.');
        redirect('appointments/');
    }

    // 1. Read both parts of the form
    $patient = patient_input();
    $patient['preferred_doctor_id'] = '';          // not on this form
    $visit = [
        'patient_id'       => '',                  // the patient does not exist yet
        'doctor_id'        => post('doctor_id'),
        'appointment_date' => post('appointment_date'),
        'appointment_time' => post('appointment_time'),
        'reason'           => post('reason'),
        'status'           => 'Scheduled',         // a new booking always starts as Scheduled
    ];

    // 2. Check both parts on the server (same rules as the booking page)
    $visitErrors = validate_appointment($visit, 0, 0, true);
    $patientErrors = validate_patient($patient);

    // 3. Save both rows together
    if (!$visitErrors && !$patientErrors) {
        try {
            $pdo->beginTransaction();

            // 3a. Patient. The booked doctor is also saved as the preferred doctor,
            //     so the Book button pre-selects the same doctor for follow-up visits.
            $stmt = $pdo->prepare(
                'INSERT INTO patients (patient_code, full_name, age, gender, phone, blood_group, address, preferred_doctor_id)
                 VALUES (?, ?, ?, ?, ?, ?, ?, ?)'
            );
            $stmt->execute([
                temp_code(),
                $patient['full_name'],
                (int) $patient['age'],
                $patient['gender'],
                $patient['phone'],
                $patient['blood_group'],
                $patient['address'],
                (int) $visit['doctor_id'],
            ]);
            $patientId = (int) $pdo->lastInsertId();
            $patientCode = assign_code('patients', 'patient_id', 'patient_code', 'PT', $patientId);

            // 3b. Appointment for the new patient
            $stmt = $pdo->prepare(
                'INSERT INTO appointments (appointment_code, patient_id, doctor_id, appointment_date, appointment_time, reason, status)
                 VALUES (?, ?, ?, ?, ?, ?, ?)'
            );
            $stmt->execute([
                temp_code(),
                $patientId,
                (int) $visit['doctor_id'],
                $visit['appointment_date'],
                $visit['appointment_time'],
                $visit['reason'],
                'Scheduled',
            ]);
            $appointmentId = (int) $pdo->lastInsertId();
            $appointmentCode = assign_code('appointments', 'appointment_id', 'appointment_code', 'AP', $appointmentId);

            $pdo->commit();

            $stmt = $pdo->prepare('SELECT full_name FROM doctors WHERE doctor_id = ?');
            $stmt->execute([(int) $visit['doctor_id']]);
            $doctorName = $stmt->fetchColumn();

            set_flash('success',
                'Patient ' . $patient['full_name'] . ' registered with Patient ID ' . $patientCode
                . '. Appointment ' . $appointmentCode . ' booked with ' . $doctorName
                . ' on ' . fmt_datetime($visit['appointment_date'], $visit['appointment_time']) . '.'
                . ' Generate the queue token from Token Queue on the visit day.'
            );
            redirect('appointments/?highlight=' . $appointmentId);
        } catch (PDOException $e) {
            if ($pdo->inTransaction()) {
                $pdo->rollBack();   // neither the patient nor the appointment is kept
            }
            $saveError = db_error_message($e, 'Nothing was saved because of a database error. Please check the details and try again.');
        }
    }
}

$hasErrors = $visitErrors || $patientErrors || $saveError !== '';

$pageTitle = 'Register patient';
$breadcrumb = 'Patients / New';
$activeNav = 'patients';
require __DIR__ . '/../includes/header.php';
?>
<form method="post" novalidate data-once-submit style="max-width:900px">
    <?= csrf_field() ?>
    <?= once_field() ?>

    <?php if ($hasErrors): ?>
        <div class="alert alert-error" role="alert">
            <span><?= $saveError !== '' ? e($saveError) : 'Please fix the problems shown below. Nothing has been saved yet.' ?></span>
        </div>
    <?php endif; ?>

    <section class="card form-section section-visit">
        <div class="form-section-head">
            <span class="step-number">1</span>
            <div>
                <h3>Appointment details</h3>
                <p>Choose the doctor and the visit time first. Only active doctors are listed.</p>
            </div>
        </div>
        <div class="form-section-body">
        <?php if (!$doctors): ?>
            <div class="alert alert-info">There is no active doctor, so no appointment can be booked.
                <a href="<?= e(url('doctors/create.php')) ?>">Add a doctor first →</a></div>
        <?php endif; ?>
        <?php render_errors($visitErrors); ?>
        <div class="form-grid">
            <?php render_visit_fields($visit, $doctors); ?>
        </div>
        </div>
    </section>

    <section class="card form-section section-patient">
        <div class="form-section-head">
            <span class="step-number">2</span>
            <div>
                <h3>Patient details</h3>
                <p>The Patient ID (for example PT-008) and the Appointment ID (for example AP-008) are created automatically.</p>
            </div>
        </div>
        <div class="form-section-body">
        <?php render_errors($patientErrors); ?>
        <div class="form-grid">
            <?php render_patient_fields($patient); ?>
        </div>
        <div class="form-actions">
            <button type="submit" class="btn btn-primary" <?= $doctors ? '' : 'disabled' ?>>Register Patient &amp; Book Appointment</button>
            <a class="btn btn-outline" href="<?= e(url('patients/')) ?>">Cancel</a>
        </div>
        </div>
    </section>
    <div class="info-panel">No queue token is created now. On the day of the visit, generate the token from the Token Queue page.</div>
</form>
<?php require __DIR__ . '/../includes/footer.php'; ?>
