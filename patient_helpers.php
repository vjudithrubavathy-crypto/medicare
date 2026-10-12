<?php
/*
 * Shared code for patients/create.php and patients/edit.php:
 * reading the form, checking it, and drawing it.
 *
 * Registration (create.php) always books an appointment in the same form,
 * so it does not show the "Preferred doctor" field. The Edit page still
 * shows it for existing patients.
 */

/* Reads the patient form fields from $_POST. */
function patient_input()
{
    return [
        'full_name'           => post('full_name'),
        'age'                 => post('age'),
        'gender'              => post('gender'),
        'phone'               => post('phone'),
        'blood_group'         => post('blood_group'),
        'address'             => post('address'),
        'preferred_doctor_id' => post('preferred_doctor_id'),   // only sent by the Edit form
    ];
}

/*
 * Checks the form on the server. Returns a list of error messages.
 * $patientId is the patient being edited (0 for a new patient).
 * $currentDoctorId lets an edit keep a preferred doctor who is now inactive.
 */
function validate_patient(array $d, $patientId = 0, $currentDoctorId = null)
{
    $errors = [];

    if (!preg_match("/^[\\p{L} .'-]{2,100}$/u", $d['full_name'])) {
        $errors[] = 'Full name is required (2–100 letters; spaces, dot, apostrophe and hyphen are allowed).';
    }
    if (!ctype_digit($d['age']) || (int) $d['age'] > 120) {
        $errors[] = 'Age must be a whole number from 0 to 120.';
    }
    if (!in_array($d['gender'], GENDERS, true)) {
        $errors[] = 'Please choose a gender.';
    }
    if (!preg_match('/^[6-9][0-9]{9}$/', $d['phone'])) {
        $errors[] = 'Phone must be a 10-digit mobile number starting with 6, 7, 8 or 9.';
    }
    if (!in_array($d['blood_group'], BLOOD_GROUPS, true)) {
        $errors[] = 'Please choose a blood group.';
    }
    $addrLen = mb_strlen($d['address']);
    if ($addrLen < 5 || $addrLen > 255) {
        $errors[] = 'Address is required (5–255 characters).';
    }

    // Preferred doctor is optional. If given, it must be a real, active doctor.
    if ($d['preferred_doctor_id'] !== '') {
        if (!ctype_digit($d['preferred_doctor_id'])) {
            $errors[] = 'Please choose a valid preferred doctor.';
        } else {
            $docId = (int) $d['preferred_doctor_id'];
            $stmt = db()->prepare('SELECT is_active FROM doctors WHERE doctor_id = ?');
            $stmt->execute([$docId]);
            $active = $stmt->fetchColumn();
            if ($active === false) {
                $errors[] = 'The chosen preferred doctor does not exist.';
            } elseif ((int) $active !== 1 && $docId !== (int) $currentDoctorId) {
                $errors[] = 'The chosen preferred doctor is not active.';
            }
        }
    }

    // Duplicate check: same name and same phone number.
    if (!$errors) {
        $stmt = db()->prepare(
            'SELECT patient_code FROM patients WHERE LOWER(full_name) = LOWER(?) AND phone = ? AND patient_id <> ?'
        );
        $stmt->execute([$d['full_name'], $d['phone'], $patientId]);
        $existing = $stmt->fetchColumn();
        if ($existing !== false) {
            $message = 'A patient with this name and phone number already exists (' . $existing . ').';
            if ($patientId === 0) {
                $message .= ' To book another visit for them, use the Book button on the Patients page.';
            }
            $errors[] = $message;
        }
    }
    return $errors;
}

/* Doctors shown in the "preferred doctor" dropdown. */
function doctor_options($includeDoctorId = null)
{
    $stmt = db()->prepare(
        'SELECT doctor_id, doctor_code, full_name, specialization, is_active
         FROM doctors WHERE is_active = 1 OR doctor_id = ? ORDER BY full_name'
    );
    $stmt->execute([(int) $includeDoctorId]);
    return $stmt->fetchAll();
}

/*
 * Draws the patient detail fields (name, age, gender, phone, blood group,
 * address). Call it inside a <div class="form-grid">.
 * $showPreferred: true on the Edit page only.
 */
function render_patient_fields(array $d, array $doctors = [], $showPreferred = false)
{
    ?>
            <div class="field full">
                <label for="full_name">Full name <span class="req">*</span></label>
                <input class="input" id="full_name" name="full_name" maxlength="100" required value="<?= e($d['full_name']) ?>">
            </div>
            <div class="field">
                <label for="age">Age <span class="req">*</span></label>
                <input class="input" id="age" name="age" type="number" min="0" max="120" required value="<?= e($d['age']) ?>">
            </div>
            <div class="field">
                <label for="gender">Gender <span class="req">*</span></label>
                <select class="input" id="gender" name="gender" required>
                    <option value="">Select gender</option>
                    <?php foreach (GENDERS as $g): ?>
                        <option value="<?= e($g) ?>" <?= $d['gender'] === $g ? 'selected' : '' ?>><?= e($g) ?></option>
                    <?php endforeach; ?>
                </select>
            </div>
            <div class="field">
                <label for="phone">Phone number <span class="req">*</span></label>
                <input class="input" id="phone" name="phone" inputmode="numeric" maxlength="10" pattern="[6-9][0-9]{9}" required value="<?= e($d['phone']) ?>">
                <div class="hint">10-digit mobile number</div>
            </div>
            <div class="field">
                <label for="blood_group">Blood group <span class="req">*</span></label>
                <select class="input" id="blood_group" name="blood_group" required>
                    <option value="">Select blood group</option>
                    <?php foreach (BLOOD_GROUPS as $b): ?>
                        <option value="<?= e($b) ?>" <?= $d['blood_group'] === $b ? 'selected' : '' ?>><?= e($b) ?></option>
                    <?php endforeach; ?>
                </select>
            </div>
            <div class="field full">
                <label for="address">Address <span class="req">*</span></label>
                <textarea class="input" id="address" name="address" maxlength="255" required><?= e($d['address']) ?></textarea>
            </div>
            <?php if ($showPreferred): ?>
            <div class="field full">
                <label for="preferred_doctor_id">Preferred doctor</label>
                <select class="input" id="preferred_doctor_id" name="preferred_doctor_id">
                    <option value="">No preference</option>
                    <?php foreach ($doctors as $doc): ?>
                        <option value="<?= (int) $doc['doctor_id'] ?>" <?= (string) $d['preferred_doctor_id'] === (string) $doc['doctor_id'] ? 'selected' : '' ?>>
                            <?= e($doc['full_name'] . ' — ' . $doc['specialization'] . ($doc['is_active'] ? '' : ' (inactive)')) ?>
                        </option>
                    <?php endforeach; ?>
                </select>
                <div class="hint">Used to pre-select the doctor when you click Book for this patient.</div>
            </div>
            <?php endif; ?>
    <?php
}

/* Draws the Edit patient form (patient fields + preferred doctor). */
function render_patient_form(array $d, array $doctors, $submitLabel, $patientId = 0)
{
    ?>
    <form method="post" novalidate>
        <?= csrf_field() ?>
        <?php if ($patientId): ?><input type="hidden" name="id" value="<?= (int) $patientId ?>"><?php endif; ?>
        <div class="form-grid">
            <?php render_patient_fields($d, $doctors, true); ?>
        </div>
        <div class="form-actions">
            <button type="submit" class="btn btn-primary"><?= e($submitLabel) ?></button>
            <a class="btn btn-outline" href="<?= e(url('patients/')) ?>">Cancel</a>
        </div>
    </form>
    <?php
}
