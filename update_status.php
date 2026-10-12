<?php
/*
 * Change an appointment's status from the dropdown on the list (POST only).
 *   Scheduled -> Completed : also completes its open queue token
 *   Completed -> Scheduled : only if it has no bill and no token yet
 *   Any -> Cancelled       : same rules as the Cancel button
 * Cancelled and billed appointments cannot be changed.
 */
require_once __DIR__ . '/../includes/functions.php';
require_once __DIR__ . '/appointment_helpers.php';

require_post_with_csrf('appointments/');
$id = post_int('id');
$newStatus = post('status');
$pdo = db();

$appt = appointment_state($id);
if (!$appt) {
    set_flash('error', 'Appointment not found.');
    redirect('appointments/');
}
$code = $appt['appointment_code'];

if (!in_array($newStatus, APPOINTMENT_STATUSES, true)) {
    set_flash('error', 'Unknown status.');
    redirect('appointments/');
}
if ($newStatus === $appt['status']) {
    redirect('appointments/');
}
if ($appt['status'] === 'Cancelled') {
    set_flash('error', $code . ' is cancelled. Book a new appointment instead.');
    redirect('appointments/');
}
if ($appt['bill_id']) {
    set_flash('error', $code . ' is already billed, so its status is locked.');
    redirect('appointments/');
}

if ($newStatus === 'Cancelled') {
    $error = cancel_appointment($id);
    set_flash($error === '' ? 'success' : 'error', $error === '' ? $code . ' cancelled.' : $error);
    redirect('appointments/');
}

try {
    if ($newStatus === 'Completed') {
        $pdo->beginTransaction();
        $stmt = $pdo->prepare("UPDATE appointments SET status = 'Completed' WHERE appointment_id = ?");
        $stmt->execute([$id]);
        $stmt = $pdo->prepare(
            "UPDATE token_queue SET status = 'Completed'
             WHERE appointment_id = ? AND status IN ('Waiting', 'In Consultation')"
        );
        $stmt->execute([$id]);
        $pdo->commit();
        set_flash('success', $code . ' marked as Completed. It is now ready for billing.');
    } else { // back to Scheduled
        if ($appt['token_id'] && $appt['token_status'] !== 'Cancelled') {
            set_flash('error', $code . ' already went through the queue, so it cannot go back to Scheduled.');
            redirect('appointments/');
        }
        $stmt = $pdo->prepare("UPDATE appointments SET status = 'Scheduled' WHERE appointment_id = ?");
        $stmt->execute([$id]);
        set_flash('success', $code . ' set back to Scheduled.');
    }
} catch (PDOException $e) {
    if ($pdo->inTransaction()) {
        $pdo->rollBack();
    }
    set_flash('error', db_error_message($e, 'The status could not be changed. Please try again.'));
}
redirect('appointments/');
