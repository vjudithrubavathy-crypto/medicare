<?php
/*
 * Patient directory.
 * Shows: all records, search, first record, last record, total count.
 */
require_once __DIR__ . '/../includes/functions.php';

$pdo = db();
$q = get('q');
$view = get('view');              // "first" or "last" (from the First / Last buttons)

$totalRecords = (int) $pdo->query('SELECT COUNT(*) FROM patients')->fetchColumn();

// --- First or last record, using the primary key as the stable order ---
$record = null;
if ($view === 'first' || $view === 'last') {
    $order = $view === 'first' ? 'ASC' : 'DESC';
    $record = $pdo->query(
        "SELECT p.*, d.full_name AS doctor_name
         FROM patients p LEFT JOIN doctors d ON d.doctor_id = p.preferred_doctor_id
         ORDER BY p.patient_id $order LIMIT 1"
    )->fetch();
}

// --- All records, or only the ones that match the search ---
// history_count = completed or cancelled visits + queue tokens + bills. Patients with history are never deleted.
$sql = "SELECT p.*, d.full_name AS doctor_name,
               (SELECT COUNT(*) FROM appointments a WHERE a.patient_id = p.patient_id) AS appt_count,
               ((SELECT COUNT(*) FROM appointments a WHERE a.patient_id = p.patient_id AND a.status IN ('Completed', 'Cancelled'))
                + (SELECT COUNT(*) FROM token_queue t JOIN appointments a ON a.appointment_id = t.appointment_id WHERE a.patient_id = p.patient_id)
                + (SELECT COUNT(*) FROM bills b WHERE b.patient_id = p.patient_id)) AS history_count
        FROM patients p
        LEFT JOIN doctors d ON d.doctor_id = p.preferred_doctor_id";
$params = [];
if ($q !== '') {
    $sql .= ' WHERE p.full_name LIKE ? OR p.patient_code LIKE ? OR p.phone LIKE ?';
    $like = '%' . $q . '%';
    $params = [$like, $like, $like];
}
$sql .= ' ORDER BY p.patient_id ASC';
$stmt = $pdo->prepare($sql);
$stmt->execute($params);
$patients = $stmt->fetchAll();

$pageTitle = 'Patients';
$breadcrumb = 'Patient directory';
$activeNav = 'patients';
require __DIR__ . '/../includes/header.php';
?>

<div class="page-head">
    <div>
        <h2>Patient directory</h2>
        <p>Registering a patient also books their first appointment. Use Book for later visits.</p>
    </div>
    <div class="page-actions">
        <form class="search-form" method="get" action="<?= e(url('patients/')) ?>">
            <div class="search-box">
                <?= icon('search') ?>
                <input type="search" name="q" value="<?= e($q) ?>" placeholder="Search name, ID or phone…" aria-label="Search patients">
            </div>
            <button class="btn btn-outline" type="submit">Search</button>
        </form>
        <a class="btn btn-primary" href="<?= e(url('patients/create.php')) ?>"><?= icon('plus') ?> Register patient</a>
    </div>
</div>

<?php if ($view === 'first' || $view === 'last'): ?>
    <section class="card record-card">
        <div class="card-head">
            <h3><?= $view === 'first' ? 'First record' : 'Last record' ?></h3>
            <a class="btn btn-sm btn-outline" href="<?= e(url('patients/')) ?>">Close</a>
        </div>
        <?php if (!$record): ?>
            <div class="empty-state">There are no patient records yet, so there is no <?= e($view) ?> record to show.</div>
        <?php else: ?>
            <div class="record-grid">
                <div><div class="label">Patient ID</div><div class="value"><?= e($record['patient_code']) ?></div></div>
                <div><div class="label">Full name</div><div class="value"><?= e($record['full_name']) ?></div></div>
                <div><div class="label">Age / Gender</div><div class="value"><?= (int) $record['age'] ?> / <?= e($record['gender']) ?></div></div>
                <div><div class="label">Phone</div><div class="value"><?= e($record['phone']) ?></div></div>
                <div><div class="label">Blood group</div><div class="value"><?= e($record['blood_group']) ?></div></div>
                <div><div class="label">Preferred doctor</div><div class="value"><?= e($record['doctor_name'] ?? 'None') ?></div></div>
                <div><div class="label">Registered on</div><div class="value"><?= e(fmt_date($record['created_at'])) ?></div></div>
                <div><div class="label">Address</div><div class="value"><?= e($record['address']) ?></div></div>
            </div>
        <?php endif; ?>
    </section>
<?php endif; ?>

<section class="card">
    <div class="card-head">
        <h3>
            <?= $q !== '' ? 'Search results' : 'All patients' ?>
            <span class="count">(<?= count($patients) ?><?= $q !== '' ? ' of ' . $totalRecords : '' ?> records)</span>
        </h3>
        <div class="actions">
            <?php if ($q !== ''): ?>
                <a class="btn btn-outline" href="<?= e(url('patients/')) ?>">Clear search</a>
            <?php endif; ?>
            <a class="btn btn-outline" href="<?= e(url('patients/?view=first')) ?>">|← First</a>
            <a class="btn btn-outline" href="<?= e(url('patients/?view=last')) ?>">Last →|</a>
        </div>
    </div>

    <div class="table-wrap">
        <table>
            <thead>
                <tr><th>Patient</th><th>Age / Gender</th><th>Phone</th><th>Blood group</th><th>Preferred doctor</th><th>Actions</th></tr>
            </thead>
            <tbody>
            <?php if (!$patients): ?>
                <tr><td colspan="6" class="empty-state">
                    <?= $q !== '' ? 'No patient matches "' . e($q) . '".' : 'No patients registered yet. Click "Register patient" to start.' ?>
                </td></tr>
            <?php endif; ?>
            <?php foreach ($patients as $p): ?>
                <tr class="<?= $record && (int) $record['patient_id'] === (int) $p['patient_id'] ? 'highlight' : '' ?>">
                    <td>
                        <div class="cell-person">
                            <span class="avatar"><?= e(initials($p['full_name'])) ?></span>
                            <div><div class="name"><?= e($p['full_name']) ?></div><div class="code"><?= e($p['patient_code']) ?></div></div>
                        </div>
                    </td>
                    <td><?= (int) $p['age'] ?> / <?= e($p['gender']) ?></td>
                    <td><?= e($p['phone']) ?></td>
                    <td><?= e($p['blood_group']) ?></td>
                    <td><?= $p['doctor_name'] ? e($p['doctor_name']) : '<span class="muted">None</span>' ?></td>
                    <td>
                        <div class="actions">
                            <a class="btn btn-sm btn-soft" href="<?= e(url('appointments/create.php?patient_id=' . (int) $p['patient_id'])) ?>">Book</a>
                            <a class="btn btn-sm btn-outline" href="<?= e(url('patients/edit.php?id=' . (int) $p['patient_id'])) ?>">Edit</a>
                            <?php if ((int) $p['history_count'] > 0): ?>
                                <span class="btn btn-sm btn-danger disabled" title="This patient has a completed or cancelled visit, a queue token or a bill, so the record is kept for history.">Delete</span>
                            <?php else: ?>
                                <form class="inline-form" method="post" action="<?= e(url('patients/delete.php')) ?>"
                                      data-confirm="Delete patient <?= e($p['full_name']) ?> (<?= e($p['patient_code']) ?>)?<?= (int) $p['appt_count'] > 0 ? ' Their ' . (int) $p['appt_count'] . ' upcoming appointment(s) will also be deleted.' : '' ?> This cannot be undone.">
                                    <?= csrf_field() ?>
                                    <input type="hidden" name="id" value="<?= (int) $p['patient_id'] ?>">
                                    <button class="btn btn-sm btn-danger" type="submit">Delete</button>
                                </form>
                            <?php endif; ?>
                        </div>
                    </td>
                </tr>
            <?php endforeach; ?>
            </tbody>
        </table>
    </div>
    <p class="muted" style="margin:14px 0 0">Total patient records in the database: <b><?= $totalRecords ?></b>.
        A patient with a completed or cancelled visit, a token or a bill cannot be deleted, so visit and bill history stays correct.</p>
</section>

<?php require __DIR__ . '/../includes/footer.php'; ?>
