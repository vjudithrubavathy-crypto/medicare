<?php
/* Edit an existing patient. */
require_once __DIR__ . '/../includes/functions.php';
require_once __DIR__ . '/patient_helpers.php';

$pdo = db();
$id = is_post() ? post_int('id') : get_int('id');

$stmt = $pdo->prepare('SELECT * FROM patients WHERE patient_id = ?');
$stmt->execute([$id]);
$patient = $stmt->fetch();
if (!$patient) {
    set_flash('error', 'Patient not found.');
    redirect('patients/');
}

$data = [
    'full_name'           => $patient['full_name'],
    'age'                 => (string) $patient['age'],
    'gender'              => $patient['gender'],
    'phone'               => $patient['phone'],
    'blood_group'         => $patient['blood_group'],
    'address'             => $patient['address'],
    'preferred_doctor_id' => (string) ($patient['preferred_doctor_id'] ?? ''),
];
$errors = [];

if (is_post()) {
    require_post_with_csrf('patients/edit.php?id=' . $id);
    $data = patient_input();
    $errors = validate_patient($data, $id, $patient['preferred_doctor_id']);

    if (!$errors) {
        try {
            $stmt = $pdo->prepare(
                'UPDATE patients
                 SET full_name = ?, age = ?, gender = ?, phone = ?, blood_group = ?, address = ?, preferred_doctor_id = ?
                 WHERE patient_id = ?'
            );
            $stmt->execute([
                $data['full_name'],
                (int) $data['age'],
                $data['gender'],
                $data['phone'],
                $data['blood_group'],
                $data['address'],
                $data['preferred_doctor_id'] === '' ? null : (int) $data['preferred_doctor_id'],
                $id,
            ]);
            set_flash('success', 'Patient ' . $patient['patient_code'] . ' updated.');
            redirect('patients/');
        } catch (PDOException $e) {
            $errors[] = db_error_message($e, 'The changes could not be saved. Please try again.');
        }
    }
}

$doctors = doctor_options($patient['preferred_doctor_id']);
$pageTitle = 'Edit patient';
$breadcrumb = 'Patients / ' . $patient['patient_code'];
$activeNav = 'patients';
require __DIR__ . '/../includes/header.php';
?>
<section class="card" style="max-width:900px">
    <div class="card-head">
        <div>
            <h3><?= e($patient['full_name']) ?> <span class="count">(<?= e($patient['patient_code']) ?>)</span></h3>
            <p class="card-sub">Change the details and click Save changes.</p>
        </div>
    </div>
    <?php render_errors($errors); ?>
    <?php render_patient_form($data, $doctors, 'Save changes', $id); ?>
</section>
<?php require __DIR__ . '/../includes/footer.php'; ?>
