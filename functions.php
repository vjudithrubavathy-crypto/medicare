<?php
/*
 * Small helper functions used by every page.
 * Every page starts with:  require_once __DIR__ . '/../includes/functions.php';
 */
require_once __DIR__ . '/../config/database.php';

if (session_status() === PHP_SESSION_NONE) {
    session_start();
}

/* ------------------------------------------------------------------
 * URLs
 * Works out the web address of the project folder automatically,
 * for example "/medicare". So links keep working even if you rename
 * the folder inside htdocs.
 * ------------------------------------------------------------------ */
function base_url()
{
    static $base = null;
    if ($base !== null) {
        return $base;
    }
    $root   = str_replace('\\', '/', realpath(dirname(__DIR__)));
    $script = str_replace('\\', '/', realpath($_SERVER['SCRIPT_FILENAME']));
    $rel    = ltrim(substr($script, strlen($root)), '/');   // e.g. "patients/index.php"
    $depth  = substr_count($rel, '/');                       // 1 for pages inside a module folder

    $url = str_replace('\\', '/', dirname($_SERVER['SCRIPT_NAME']));
    for ($i = 0; $i < $depth; $i++) {
        $url = str_replace('\\', '/', dirname($url));
    }
    $base = rtrim($url, '/.');
    return $base;
}

function url($path = '')
{
    return base_url() . '/' . ltrim($path, '/');
}

function redirect($path)
{
    header('Location: ' . url($path));
    exit;
}

/* ------------------------------------------------------------------
 * Output escaping (stops HTML/JavaScript injection, XSS)
 * ------------------------------------------------------------------ */
function e($value)
{
    return htmlspecialchars((string) $value, ENT_QUOTES, 'UTF-8');
}

/* ------------------------------------------------------------------
 * Flash messages: shown once on the next page, then removed.
 * ------------------------------------------------------------------ */
function set_flash($type, $message)
{
    $_SESSION['flash'][] = ['type' => $type, 'message' => $message];
}

function get_flashes()
{
    $list = $_SESSION['flash'] ?? [];
    unset($_SESSION['flash']);
    return $list;
}

/* ------------------------------------------------------------------
 * CSRF protection for every form that changes data.
 * ------------------------------------------------------------------ */
function csrf_token()
{
    if (empty($_SESSION['csrf_token'])) {
        $_SESSION['csrf_token'] = bin2hex(random_bytes(32));
    }
    return $_SESSION['csrf_token'];
}

function csrf_field()
{
    return '<input type="hidden" name="csrf_token" value="' . e(csrf_token()) . '">';
}

/* Stops the request if the form did not come from our own page. */
function require_post_with_csrf($backPath)
{
    if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
        redirect($backPath);
    }
    $sent = $_POST['csrf_token'] ?? '';
    if (!is_string($sent) || !hash_equals(csrf_token(), $sent)) {
        set_flash('error', 'Your session expired. Please try again.');
        redirect($backPath);
    }
}

/*
 * One-time form token: stops the same form from being saved twice
 * (double-click, browser Back + submit again, or re-sending the page).
 * Each form gets a fresh random token. The first submit uses it up;
 * a second submit with the same token is refused.
 */
function once_field()
{
    $token = bin2hex(random_bytes(16));
    $_SESSION['once_tokens'][$token] = time();
    // keep only the 20 newest tokens so the session stays small
    $_SESSION['once_tokens'] = array_slice($_SESSION['once_tokens'], -20, null, true);
    return '<input type="hidden" name="form_once" value="' . e($token) . '">';
}

/* Returns true the first time a token is used, false after that. */
function use_once_token()
{
    $token = $_POST['form_once'] ?? '';
    if (!is_string($token) || !isset($_SESSION['once_tokens'][$token])) {
        return false;
    }
    unset($_SESSION['once_tokens'][$token]);
    return true;
}

function is_post()
{
    return $_SERVER['REQUEST_METHOD'] === 'POST';
}

/* ------------------------------------------------------------------
 * Reading input safely
 * ------------------------------------------------------------------ */
function post($key, $default = '')
{
    $v = $_POST[$key] ?? $default;
    return is_string($v) ? trim($v) : $default;
}

function get($key, $default = '')
{
    $v = $_GET[$key] ?? $default;
    return is_string($v) ? trim($v) : $default;
}

function get_int($key)
{
    $v = $_GET[$key] ?? '';
    return (is_string($v) && ctype_digit($v)) ? (int) $v : 0;
}

function post_int($key)
{
    $v = $_POST[$key] ?? '';
    return (is_string($v) && ctype_digit($v)) ? (int) $v : 0;
}

/* ------------------------------------------------------------------
 * Validation helpers
 * ------------------------------------------------------------------ */
function valid_date($date)
{
    $d = DateTime::createFromFormat('Y-m-d', $date);
    return $d && $d->format('Y-m-d') === $date;
}

/* Accepts "09:30" or "09:30:00" and returns "09:30:00", or false. */
function normalise_time($time)
{
    foreach (['H:i', 'H:i:s'] as $format) {
        $t = DateTime::createFromFormat($format, $time);
        if ($t && $t->format($format) === $time) {
            return $t->format('H:i:s');
        }
    }
    return false;
}

/*
 * Money is checked as text and converted to paise (whole numbers),
 * so 0.1 + 0.2 problems with decimals can never happen.
 * Returns the amount in paise, or false if the text is not valid money.
 */
function money_to_paise($text)
{
    $text = trim((string) $text);
    if ($text === '') {
        return 0;
    }
    if (!preg_match('/^\d{1,7}(\.\d{1,2})?$/', $text)) {
        return false;
    }
    $parts = explode('.', $text);
    $rupees = (int) $parts[0];
    $paise = isset($parts[1]) ? (int) str_pad($parts[1], 2, '0') : 0;
    return $rupees * 100 + $paise;
}

function paise_to_decimal($paise)
{
    return sprintf('%d.%02d', intdiv($paise, 100), $paise % 100);
}

function today()
{
    return date('Y-m-d');
}

/* ------------------------------------------------------------------
 * Display formatting
 * ------------------------------------------------------------------ */
function money($amount)
{
    return '₹' . number_format((float) $amount, 2);
}

function fmt_date($date)
{
    return $date ? date('d M Y', strtotime($date)) : '';
}

function fmt_time($time)
{
    return $time ? date('h:i A', strtotime($time)) : '';
}

function fmt_datetime($date, $time)
{
    return date('d M Y, h:i A', strtotime($date . ' ' . $time));
}

function token_label($number)
{
    return 'T-' . str_pad((string) $number, 3, '0', STR_PAD_LEFT);
}

function initials($name)
{
    $name = preg_replace('/^Dr\.?\s+/i', '', trim($name));
    $words = preg_split('/\s+/', $name);
    $out = '';
    foreach (array_slice($words, 0, 2) as $w) {
        $out .= mb_strtoupper(mb_substr($w, 0, 1));
    }
    return $out;
}

/* Coloured pill for a status word. */
function badge($status, $label = null)
{
    $class = strtolower(str_replace(' ', '-', $status));
    return '<span class="badge badge-' . e($class) . '">' . e($label ?? $status) . '</span>';
}

/* After INSERT: turns new id 8 into code "PT-008" and saves it. */
function assign_code($table, $idColumn, $codeColumn, $prefix, $id)
{
    // Table and column names come from our own code, never from the user.
    $code = $prefix . '-' . str_pad((string) $id, 3, '0', STR_PAD_LEFT);
    $stmt = db()->prepare("UPDATE $table SET $codeColumn = ? WHERE $idColumn = ?");
    $stmt->execute([$code, $id]);
    return $code;
}

/* A temporary unique code used for the moment between INSERT and assign_code(). */
function temp_code()
{
    return 'TMP' . substr(bin2hex(random_bytes(4)), 0, 7);
}

/* Show a friendly message for database errors, log the real one. */
function db_error_message(PDOException $e, $friendly)
{
    error_log('MediCare DB error: ' . $e->getMessage());
    return $friendly;
}

/* Shows a list of validation errors. */
function render_errors(array $errors)
{
    if (!$errors) {
        return;
    }
    echo '<ul class="error-list">';
    foreach ($errors as $err) {
        echo '<li>' . e($err) . '</li>';
    }
    echo '</ul>';
}

/* ------------------------------------------------------------------
 * Simple inline SVG icons (no internet needed).
 * ------------------------------------------------------------------ */
function icon($name)
{
    $paths = [
        'dashboard' => '<rect x="3" y="3" width="7" height="7" rx="1.5"/><rect x="14" y="3" width="7" height="7" rx="1.5"/><rect x="3" y="14" width="7" height="7" rx="1.5"/><rect x="14" y="14" width="7" height="7" rx="1.5"/>',
        'patients'  => '<circle cx="12" cy="8" r="4"/><path d="M4 21c0-4.4 3.6-7 8-7s8 2.6 8 7"/>',
        'doctors'   => '<path d="M6 3v6a5 5 0 0 0 10 0V3"/><path d="M11 14v2a5 5 0 0 0 10 0v-1"/><circle cx="21" cy="12" r="2"/>',
        'calendar'  => '<rect x="3" y="5" width="18" height="16" rx="2"/><path d="M3 10h18M8 3v4M16 3v4"/>',
        'queue'     => '<path d="M8 6h13M8 12h13M8 18h13"/><circle cx="4" cy="6" r="1.2"/><circle cx="4" cy="12" r="1.2"/><circle cx="4" cy="18" r="1.2"/>',
        'billing'   => '<path d="M6 2h12v20l-3-2-3 2-3-2-3 2z"/><path d="M9 7h6M9 11h6M9 15h4"/>',
        'plus'      => '<path d="M12 5v14M5 12h14"/>',
        'search'    => '<circle cx="11" cy="11" r="7"/><path d="M20 20l-3.5-3.5"/>',
        'clock'     => '<circle cx="12" cy="12" r="9"/><path d="M12 7v5l3 2"/>',
        'check'     => '<path d="M5 12l5 5 9-10"/>',
        'database'  => '<ellipse cx="12" cy="5" rx="8" ry="3"/><path d="M4 5v14c0 1.7 3.6 3 8 3s8-1.3 8-3V5M4 12c0 1.7 3.6 3 8 3s8-1.3 8-3"/>',
        'print'     => '<path d="M6 9V3h12v6"/><rect x="3" y="9" width="18" height="8" rx="2"/><path d="M6 14h12v7H6z"/>',
    ];
    $inner = $paths[$name] ?? '';
    return '<svg class="icon" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" '
         . 'stroke-linecap="round" stroke-linejoin="round" aria-hidden="true">' . $inner . '</svg>';
}

/* ------------------------------------------------------------------
 * Fixed lists used in forms and checked on the server.
 * ------------------------------------------------------------------ */
const GENDERS = ['Male', 'Female', 'Other'];
const BLOOD_GROUPS = ['A+', 'A-', 'B+', 'B-', 'AB+', 'AB-', 'O+', 'O-'];
const APPOINTMENT_STATUSES = ['Scheduled', 'Completed', 'Cancelled'];
const TOKEN_STATUSES = ['Waiting', 'In Consultation', 'Completed', 'Cancelled'];
