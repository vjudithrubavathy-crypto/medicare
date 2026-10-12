<?php
/* Printable receipt for one bill. Use the Print button or Ctrl + P. */
require_once __DIR__ . '/../includes/functions.php';

$id = get_int('id');
$stmt = db()->prepare(
    'SELECT b.*, a.appointment_code, a.appointment_date, a.appointment_time, a.reason,
            p.full_name AS patient_name, p.patient_code, p.age, p.gender, p.phone,
            d.full_name AS doctor_name, d.specialization,
            t.token_number
     FROM bills b
     JOIN appointments a ON a.appointment_id = b.appointment_id
     JOIN patients p ON p.patient_id = b.patient_id
     JOIN doctors d  ON d.doctor_id  = a.doctor_id
     LEFT JOIN token_queue t ON t.appointment_id = a.appointment_id
     WHERE b.bill_id = ?'
);
$stmt->execute([$id]);
$bill = $stmt->fetch();

if (!$bill) {
    set_flash('error', 'Bill not found.');
    redirect('billing/');
}
$subtotal = (float) $bill['consultation_fee'] + (float) $bill['lab_charges'] + (float) $bill['medicine_charges'];
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <title>Receipt <?= e($bill['bill_code']) ?> · MediCare</title>
    <link rel="stylesheet" href="<?= e(url('assets/css/style.css')) ?>">
</head>
<body class="receipt-page">
    <div class="receipt-toolbar no-print">
        <a class="btn btn-outline" href="<?= e(url('billing/')) ?>">← Back to bills</a>
        <button class="btn btn-primary" type="button" id="printBtn"><?= icon('print') ?> Print receipt</button>
    </div>

    <div class="no-print" style="max-width:760px;margin:12px auto 0">
        <?php foreach (get_flashes() as $flash): ?>
            <div class="alert alert-<?= e($flash['type']) ?>"><span><?= e($flash['message']) ?></span></div>
        <?php endforeach; ?>
    </div>

    <div class="receipt">
        <div class="receipt-head">
            <div class="receipt-brand">
                <span class="brand-logo">+</span>
                <div>
                    <h1>MediCare Hospital</h1>
                    <p>Outpatient Department · Chennai</p>
                </div>
            </div>
            <div class="receipt-title">
                <h2>RECEIPT</h2>
                <div class="code-text"><?= e($bill['bill_code']) ?></div>
                <div class="muted"><?= e(date('d M Y, h:i A', strtotime($bill['created_at']))) ?></div>
            </div>
        </div>

        <div class="receipt-meta">
            <div><span>Patient</span> <b><?= e($bill['patient_name']) ?></b></div>
            <div><span>Patient ID</span> <b><?= e($bill['patient_code']) ?></b></div>
            <div><span>Age / Gender</span> <?= (int) $bill['age'] ?> / <?= e($bill['gender']) ?></div>
            <div><span>Phone</span> <?= e($bill['phone']) ?></div>
            <div><span>Doctor</span> <?= e($bill['doctor_name']) ?></div>
            <div><span>Department</span> <?= e($bill['specialization']) ?></div>
            <div><span>Appointment</span> <?= e($bill['appointment_code']) ?></div>
            <div><span>Visit date</span> <?= e(fmt_datetime($bill['appointment_date'], $bill['appointment_time'])) ?></div>
            <?php if ($bill['token_number'] !== null): ?>
                <div><span>Token</span> <?= e(token_label($bill['token_number'])) ?></div>
            <?php endif; ?>
            <div><span>Reason</span> <?= e($bill['reason']) ?></div>
        </div>

        <table>
            <thead><tr><th>Description</th><th class="text-right">Amount</th></tr></thead>
            <tbody>
                <tr><td>Consultation fee</td><td class="text-right"><?= e(money($bill['consultation_fee'])) ?></td></tr>
                <tr><td>Laboratory charges</td><td class="text-right"><?= e(money($bill['lab_charges'])) ?></td></tr>
                <tr><td>Medicine charges</td><td class="text-right"><?= e(money($bill['medicine_charges'])) ?></td></tr>
                <tr><td><b>Subtotal</b></td><td class="text-right"><b><?= e(money($subtotal)) ?></b></td></tr>
                <tr><td>Discount</td><td class="text-right">− <?= e(money($bill['discount'])) ?></td></tr>
                <tr class="total-row"><td>Total amount</td><td class="text-right"><?= e(money($bill['total_amount'])) ?></td></tr>
            </tbody>
        </table>

        <?php if ($bill['notes']): ?>
            <p style="margin-top:18px"><b>Notes:</b> <?= e($bill['notes']) ?></p>
        <?php endif; ?>

        <div class="receipt-foot">
            This is a computer-generated receipt from the MediCare Hospital Management System. Get well soon!
        </div>
    </div>
    <script src="<?= e(url('assets/js/script.js')) ?>"></script>
</body>
</html>
