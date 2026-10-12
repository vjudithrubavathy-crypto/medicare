<?php
/* Cancel an appointment (POST only). The row is kept; only its status changes. */
require_once __DIR__ . '/../includes/functions.php';
require_once __DIR__ . '/appointment_helpers.php';

require_post_with_csrf('appointments/');
$id = post_int('id');

$error = cancel_appointment($id);
if ($error !== '') {
    set_flash('error', $error);
} else {
    set_flash('success', 'Appointment cancelled. Any open queue token for it was cancelled too.');
}
redirect('appointments/');
