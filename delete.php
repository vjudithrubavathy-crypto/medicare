<?php
/*
 * Delete a patient (POST only).
 *
 * Safe delete rule: a patient is NOT deleted if they have any visit history,
 * which means a completed or cancelled appointment, a queue token or a bill.
 * Deleting them would break visit and billing records.
 *
 * Every new patient now has an appointment from registration. If that visit
 * has not happened yet (still Scheduled, no token, no bill), the patient can
 * be deleted, for example after a wrong registration. Those upcoming
 * appointments are deleted first, then the patient, in one transaction.
 * (The foreign key appointments.patient_id is ON DELETE RESTRICT, so MySQL
 * would refuse to delete the patient while any appointment row remains.)
 */
require_once __DIR__ . '/../includes/functions.php';

require_post_with_csrf('patients/');
$id = post_int('id');
$pdo = db();

$stmt = $pdo->prepare('SELECT patient_code, full_name FROM patients WHERE patient_id = ?');
$stmt->execute([$id]);
$patient = $stmt->fetch();
if (!$patient) {
    set_flash('error', 'Patient not found. It may already be deleted.');
    redirect('patients/');
}

$stmt = $pdo->prepare(
    "SELECT (SELECT COUNT(*) FROM appointments WHERE patient_id = ? AND status IN ('Completed', 'Cancelled'))
          + (SELECT COUNT(*) FROM token_queue t JOIN appointments a ON a.appointment_id = t.appointment_id WHERE a.patient_id = ?)
          + (SELECT COUNT(*) FROM bills WHERE patient_id = ?)"
);
$stmt->execute([$id, $id, $id]);
if ((int) $stmt->fetchColumn() > 0) {
    set_flash('error', $patient['full_name'] . ' has a completed or cancelled visit, a queue token or a bill, so the record cannot be deleted.');
    redirect('patients/');
}

try {
    $pdo->beginTransaction();
    // Only Scheduled appointments without a token or bill can be left at this point.
    $stmt = $pdo->prepare("DELETE FROM appointments WHERE patient_id = ? AND status = 'Scheduled'");
    $stmt->execute([$id]);
    $removedAppointments = $stmt->rowCount();
    $stmt = $pdo->prepare('DELETE FROM patients WHERE patient_id = ?');
    $stmt->execute([$id]);
    $pdo->commit();

    $message = 'Patient ' . $patient['full_name'] . ' (' . $patient['patient_code'] . ') deleted.';
    if ($removedAppointments > 0) {
        $message .= ' ' . $removedAppointments . ' upcoming appointment(s) were deleted with the record.';
    }
    set_flash('success', $message);
} catch (PDOException $e) {
    if ($pdo->inTransaction()) {
        $pdo->rollBack();
    }
    set_flash('error', db_error_message($e, 'This patient is linked to other records and cannot be deleted.'));
}
redirect('patients/');
