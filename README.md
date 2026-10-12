# MediCare Hospital Management System

A DBMS project: a hospital front-desk website built with HTML, CSS, JavaScript, PHP and MySQL. It runs on your own laptop with XAMPP. No internet is needed after installation.

It has six sections: Dashboard, Patients, Doctors, Appointments, Token Queue and Billing. Every number and every record you see comes from the MySQL database `medicare_db`.

Other documents in this folder:

- `docs/TESTING.md` – test checklist with expected results
- `docs/DEMO_SCRIPT.md` – a 10-minute script to follow during your evaluation
- `docs/MediCare_DBMS_Report.docx` – the project report (under 10 pages)
- `docs/ER_Diagram.png` and `docs/Relational_Schema.png` – diagrams
- `docs/screenshots/` – screenshots of every page

---

## Part 1. Install and run (from zero)

You do not need to type anything in a terminal. Everything is done by clicking.

### Step 1. Download and install XAMPP

1. Open https://www.apachefriends.org/download.html
2. Under **XAMPP for Windows**, download the newest 8.x version (64-bit). The file name looks like `xampp-windows-x64-8.x.x-installer.exe`.
3. Double-click the downloaded file. If Windows asks "Do you want to allow this app to make changes?", click **Yes**.
4. You may see a warning about "User Account Control (UAC)". Click **OK**. It only matters if you install inside `C:\Program Files`, which we will not do.
5. On the "Select Components" screen, keep the defaults. Apache, MySQL, PHP and phpMyAdmin must stay ticked.
6. Install folder: keep **`C:\xampp`**.
7. Click **Next** until the installation finishes. Leave "Start the Control Panel now" ticked and click **Finish**.

PHP comes inside XAMPP, so you do not need to install PHP separately.

### Step 2. Start Apache and MySQL

1. Open the **XAMPP Control Panel** (Start menu → XAMPP → XAMPP Control Panel).
2. Click **Start** next to **Apache**.
3. Click **Start** next to **MySQL**.
4. Both names should turn **green**. If Windows Firewall asks, click **Allow access**.

Keep the Control Panel open while you use the website.

### Step 3. If Apache or MySQL does not start

| Problem | What to do |
|---|---|
| Apache stays red, log says port 80 or 443 is "in use" | Another program (Skype, IIS, VMware) is using the port. Close that program and try again. Or click **Config** next to Apache → `httpd.conf`, change `Listen 80` to `Listen 8080`, save, and start Apache again. Then use `http://localhost:8080/medicare/` everywhere in this guide. |
| MySQL stays red, log says port 3306 is in use | Another MySQL is already installed. Press Windows + R, type `services.msc`, find "MySQL" or "MySQL80", right-click → **Stop**. Then start MySQL in XAMPP again. |
| You cannot stop the other MySQL | In XAMPP click **Config** next to MySQL → `my.ini`. Change both `port=3306` lines to `port=3307`. Save, start MySQL. Then open `config/database.php` and change `DB_PORT` to `'3307'`. |
| MySQL says "shutdown unexpectedly" | Click **Logs** to read the reason. The most common cause is the port problem above. If it is not, close XAMPP, make a copy of `C:\xampp\mysql\data` as a backup, then search online for "XAMPP MySQL shutdown unexpectedly" for your exact log message. |
| "Missing MSVCR110.dll" or similar | Install the Microsoft Visual C++ Redistributable (x64) from Microsoft's website, then restart XAMPP. |

### Step 4. Put the project folder in the right place

1. Right-click the ZIP file → **Extract All…**
2. As the destination, choose **`C:\xampp\htdocs`** and click **Extract**.
3. Check that this file exists: **`C:\xampp\htdocs\medicare\index.php`**

Be careful not to end up with `C:\xampp\htdocs\medicare\medicare\index.php` (a folder inside a folder). If that happens, move the inner `medicare` folder up one level.

The final layout is:

```
C:\xampp\htdocs\medicare\
    index.php                 ← Dashboard
    test_connection.php       ← connection test
    config\database.php       ← database settings
    includes\                 ← shared header, sidebar, footer, helper functions
    patients\  doctors\  appointments\  queue\  billing\
    assets\css\style.css      ← all styles
    assets\js\script.js       ← small browser helpers
    database\medicare_db.sql  ← tables + sample data
    docs\                     ← report, diagrams, test cases, demo script
```

### Step 5. Create the database in phpMyAdmin

1. Make sure Apache and MySQL are green.
2. Open **http://localhost/phpmyadmin** in Chrome or Edge.
3. Click the **Import** tab at the top.
4. Click **Choose File** and pick `C:\xampp\htdocs\medicare\database\medicare_db.sql`.
5. Scroll down and click **Import** (in older versions the button is called **Go**).
6. You should see a green message saying the import finished. On the left, a database called **medicare_db** appears with 5 tables: `appointments`, `bills`, `doctors`, `patients`, `token_queue`.

You do not need to create the database first. The SQL file does it for you. You can import the file again at any time to reset everything to the sample data.

Note: the sample appointments use "today's date" at the moment you import. If you import on Monday and demonstrate on Friday, import the file again on Friday morning so the sample queue shows today.

### Step 6. Install VS Code (to read or change the code)

1. Download VS Code from https://code.visualstudio.com and install it with the default options.
2. Open VS Code → **File → Open Folder…** → choose `C:\xampp\htdocs\medicare`.
3. If VS Code asks "Do you trust the authors?", click **Yes**.

VS Code is only for reading and editing the code. The website runs through XAMPP.

### Step 7. Database settings

Open `config/database.php`. The default XAMPP settings are already filled in:

```php
define('DB_HOST', '127.0.0.1');
define('DB_PORT', '3306');
define('DB_NAME', 'medicare_db');
define('DB_USER', 'root');
define('DB_PASS', '');        // XAMPP has no MySQL password by default
```

Change something only if your setup is different (for example a MySQL password, or port 3307).

### Step 8. Test the connection

Open **http://localhost/medicare/test_connection.php**

Success looks like this:

```
✔ Connected to MySQL
Database: medicare_db · Server version: 10.4.32-MariaDB (your number may differ)
doctors       5 rows
patients      7 rows
appointments  7 rows
token_queue   3 rows
bills         1 rows
```

If you see "Cannot connect to the database" instead, the page lists what to check (MySQL started, database imported, settings correct). If a table says "missing", import the SQL file again (Step 5).

### Step 9. Open the website

Open **http://localhost/medicare/**

You should see "Good morning/afternoon/evening, Admin" with 7 patients, 5 doctors, 7 appointments and 1 patient waiting.

### Step 10. Test everything

Follow `docs/TESTING.md`. It walks through every feature with the expected result.

---

## Part 2. How the project works

### Technology

- **Frontend:** HTML5, CSS3 (one file: `assets/css/style.css`) and plain JavaScript (`assets/js/script.js`)
- **Backend:** PHP 8 with **PDO** and prepared statements everywhere
- **Database:** MySQL / MariaDB (comes with XAMPP), InnoDB tables, utf8mb4

There are no frameworks and no outside libraries. Fonts and icons are built in, so it works offline.

### The workflow

```
Register patient + book first visit  →  Generate token  →  Consultation  →  Bill  →  Receipt
   (patients + appointments)             (token_queue)      (status)        (bills)
```

A new patient is always registered together with their first appointment, in one form and one transaction. Later visits for the same patient are booked with the **Book** button. Booking never creates a token or a bill: the token is given on the visit day from the Token Queue page.

### Module by module

**Dashboard (`index.php`)**
Counts patients, doctors, appointments and today's waiting tokens with `COUNT(*)` queries. Shows appointment and token status counts with `GROUP BY status`, the 5 latest appointments, and today's live queue. Nothing is hard-coded.

**Patients (`patients/`)**
- `index.php` – list, search (name, Patient ID or phone), First record, Last record, total count
- `create.php` – **Register Patient & Book Appointment**. Section 1 is the appointment (active doctor with speciality and fee, date, time, reason). Section 2 is the patient (name, age, gender, phone, blood group, address). Both sections are checked with the same rules as normal booking. Then the patient row and the appointment row are inserted in **one transaction**: if either insert fails, both are rolled back and nothing is saved. The Patient ID (PT-008 …) and Appointment ID (AP-008 …) are made automatically, shown in the success message, and the new appointment is highlighted on the Appointments page. The booked doctor is also saved as the patient's preferred doctor, so **Book** pre-selects the same doctor for follow-up visits.
- `edit.php` – change details (the preferred doctor can be changed here)
- `delete.php` – deletes a patient only if none of their visits has happened yet: no completed or cancelled appointment, no token, no bill. Their upcoming (Scheduled) appointments are deleted together with them in one transaction. This is for correcting a wrong registration.
- `patient_helpers.php` – the shared form and the server-side checks

**Doctors (`doctors/`)**
Same pattern: list, search, first/last, add, edit, delete. A doctor can be set **Inactive**. Inactive doctors do not appear in the booking form. A doctor with appointments cannot be deleted; set them inactive instead.

**Appointments (`appointments/`)**
- `create.php` – booking in two steps: fill the form → **Review booking** → **Confirm booking**. Nothing is saved before Confirm. Choosing a patient selects their preferred doctor automatically (if that doctor is active). You can still change it.
- `index.php` – list, search, filter by status and date, change status from the dropdown
- `edit.php` – reschedule (doctor, date, time, reason)
- `cancel.php` – cancels without deleting. The row stays for history.
- `update_status.php` – handles the status dropdown
- `appointment_helpers.php` – checks and the shared form

**Token Queue (`queue/`)**
- `generate.php` – gives the next token number for today (T-001, T-002 …) to a scheduled appointment of today
- `index.php` – today's queue in token order, with Waiting / In Consultation / Completed counts, a date picker for older days, and Hide/Show completed
- `update_status.php` – Call patient, Mark completed, Back to waiting, Cancel, Re-open

**Billing (`billing/`)**
- `create.php` – pick a completed visit; patient, doctor and fee fill in automatically; type lab charges, medicine charges and discount; the total is previewed live and **calculated again in PHP** before saving
- `index.php` – all bills, search by Bill ID, patient or appointment, totals
- `receipt.php` – a clean printable receipt (Print button or Ctrl + P)

**Shared files**
- `config/database.php` – one place for connection settings; shows a friendly page if MySQL is down
- `includes/functions.php` – escaping (`e()`), CSRF tokens, flash messages, money helpers, date formatting, icons
- `includes/header.php`, `sidebar.php`, `footer.php` – the same layout on every page

### Rules the system follows

**Registration and double submits**
- Every field in both sections is required and checked on the server.
- Each form carries a one-time token. If the same form is sent twice (double-click, Back + resubmit), the second copy is refused with "already submitted". The submit button also turns into "Saving…" after the first click.
- The same one-time token protects the **Confirm booking** step on the Book page.
- Old patients without any appointment (if you have any) are kept as they are.

**Appointments**
- The date cannot be in the past. The time must be between 7:00 AM and 10:00 PM.
- A doctor cannot have two active appointments at the same date and time.
- A patient cannot have two active appointments on the same day with the same doctor, or at the same time.
- Only active doctors can be booked.
- Only Scheduled appointments can be edited or cancelled. Edit is also blocked while the patient is in the queue.
- Cancelled is final. Billed appointments are locked.

**Tokens**
- Only a **Scheduled** appointment **dated today** can get a token.
- One appointment has at most one token row (UNIQUE key). A cancelled token can be re-opened, but a second token is never created.
- Numbers restart every day (UNIQUE on `queue_date + token_number`).
- Moves: Waiting → In Consultation → Completed. Waiting or In Consultation can be cancelled. Completed is final.
- A doctor sees one patient at a time.
- Marking a token Completed also marks its appointment Completed (in one transaction).
- Cancelling an appointment also cancels its open token.

**Billing**
- A visit can be billed only when its appointment is **Completed** (through the queue, or from the status dropdown).
- One bill per appointment (UNIQUE `appointment_id`).
- All amounts must be 0 or more, with at most 2 decimals. The discount cannot be more than the subtotal.
- Subtotal = Consultation fee + Laboratory charges + Medicine charges
- Final total = Subtotal − Discount
- The fee is copied into the bill, so an old receipt never changes when a doctor's fee changes later.
- Money is calculated in paise (whole numbers) in PHP, so there are no rounding errors. Example: 800 + 250.50 + 120 − 70.50 = **₹1,100.00**.

### Security points (simple versions of real practice)

- **Prepared statements** with `?` placeholders for every query that uses user input (stops SQL injection).
- **Output escaping** with `htmlspecialchars` through the `e()` helper (stops XSS).
- **CSRF token** in every form that changes data. Changes only happen through **POST**.
- **Post/Redirect/Get**: after saving, the page redirects, so pressing F5 does not save twice.
- **Server-side validation** for every field. JavaScript checks are only for convenience.
- **Transactions** where two tables change together (for example completing a token and its appointment).
- Database errors are written to the PHP error log. The user sees a plain message, never SQL or passwords.

There is no login screen. The project assumes one front-desk computer used by the admin. A login page is listed as a future improvement.

---

## Part 3. Database design

### Tables and creation order

The SQL file creates tables parents-first, so every foreign key points to a table that already exists. There are no circular references.

1. **doctors** – no foreign keys
2. **patients** – `preferred_doctor_id` → doctors (nullable)
3. **appointments** – `patient_id` → patients, `doctor_id` → doctors
4. **token_queue** – `appointment_id` → appointments
5. **bills** – `appointment_id` → appointments, `patient_id` → patients

### Relationships

| Relationship | Type | Foreign key | On delete |
|---|---|---|---|
| Doctor is preferred by patients | 1 : N (optional) | patients.preferred_doctor_id | SET NULL |
| Patient books appointments | 1 : N | appointments.patient_id | RESTRICT |
| Doctor attends appointments | 1 : N | appointments.doctor_id | RESTRICT |
| Appointment gets a token | 1 : 0..1 | token_queue.appointment_id (UNIQUE) | RESTRICT |
| Appointment is billed | 1 : 0..1 | bills.appointment_id (UNIQUE) | RESTRICT |
| Patient pays bills | 1 : N | bills.patient_id | RESTRICT |

RESTRICT means history can never be broken: MySQL itself refuses to delete a patient or doctor who has appointments, or an appointment that has a token or bill.

### Constraints used

- Primary keys: `AUTO_INCREMENT` integer ids
- UNIQUE: all readable codes (`PT-…`, `DR-…`, `AP-…`, `BL-…`), patient name + phone, one token per appointment, one token number per day, one bill per appointment
- CHECK: age 0–120, fee ≥ 0, bill amounts ≥ 0, discount ≤ subtotal, total = subtotal − discount (enforced by MariaDB 10.2+ and MySQL 8.0.16+, which XAMPP has)
- ENUM: gender, blood group, appointment status, token status
- Indexes on columns used for search and joins (phone, doctor + date + time, appointment date, token status)

### Known limits of this schema

- MySQL has no "partial unique index", so "only one *active* token per appointment" is done as "only one token row per appointment". A cancelled token is re-opened instead of adding a new row. The downside: the database keeps only the latest token for a visit, not the full history of cancelled ones.
- `bills.patient_id` repeats information that can also be found through the appointment. It is kept because the assignment asks for every bill to link to the patient. The PHP code always copies it from the appointment, so it cannot disagree.
- Readable codes come from the auto-increment id. Deleted ids are not reused, so there can be gaps (PT-007, then PT-009). That is normal.

### Where each DBMS operation is

| Operation | Example SQL | File |
|---|---|---|
| View all records | `SELECT p.*, d.full_name … FROM patients p LEFT JOIN doctors d … ORDER BY p.patient_id` | `patients/index.php` |
| First record | `SELECT … FROM patients ORDER BY patient_id ASC LIMIT 1` | `patients/index.php?view=first` |
| Last record | `SELECT … FROM patients ORDER BY patient_id DESC LIMIT 1` | `patients/index.php?view=last` |
| Search | `… WHERE p.full_name LIKE ? OR p.patient_code LIKE ? OR p.phone LIKE ?` | `patients/index.php`, `doctors/index.php`, `appointments/index.php`, `billing/index.php` |
| Insert | `INSERT INTO patients (…) VALUES (?, ?, ?, …)` | `patients/create.php`, `doctors/create.php`, `appointments/create.php`, `queue/generate.php`, `billing/create.php` |
| Update | `UPDATE patients SET full_name = ?, … WHERE patient_id = ?` | `patients/edit.php`, `doctors/edit.php`, `appointments/edit.php`, status pages |
| Delete | `DELETE FROM patients WHERE patient_id = ?` | `patients/delete.php`, `doctors/delete.php` |
| Count / group | `SELECT status, COUNT(*) FROM appointments GROUP BY status` | `index.php` |
| Processing | Bill total in PHP, then `INSERT INTO bills …` in a transaction | `billing/create.php` |
| Join report | bills ⋈ appointments ⋈ patients ⋈ doctors ⋈ token_queue | `billing/receipt.php` |

The First and Last buttons use the primary key as the order, because it never changes. If there are no rows, the page says "There are no patient records yet" instead of showing an empty card.

---

## Part 4. Sample data

All names, phone numbers and addresses are made up.

- 5 doctors (DR-005 Dr. Farhan Siddiqui is **inactive**, to show that inactive doctors are hidden from booking)
- 7 patients, each with at least one appointment (PT-004 and PT-007 have no preferred doctor saved)
- 7 appointments: 5 today, 1 tomorrow (AP-003), 1 yesterday and cancelled (AP-007)
- 3 tokens today: T-001 Completed, T-002 In Consultation, T-003 Waiting
- 1 bill: BL-001 for AP-002 → 500 + 200 + 150 − 50 = ₹800.00

Ready-made demo situations:
- **AP-005** (Priya Nair, today, no token) → generate a token
- **AP-006** (Karthik Raman, completed, not billed) → create a bill
- **AP-003** (tomorrow) → shows "tokens are given only on the day of the visit"
- **AP-007** (cancelled) → cannot get a token or a bill
