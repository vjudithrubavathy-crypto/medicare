# MediCare – Demonstration Script (about 10 minutes)

**Before the evaluation (5 minutes earlier)**
1. Start Apache and MySQL in the XAMPP Control Panel.
2. phpMyAdmin → Import → `medicare_db.sql` (this resets the data and makes the sample visits "today").
3. Open two tabs: `http://localhost/medicare/` and `http://localhost/phpmyadmin`.

---

### 1. Introduction (1 min) – Dashboard

Say: "MediCare is a hospital front-desk system. The frontend is HTML, CSS and JavaScript, the backend is PHP with PDO, and the data is in a MySQL database with five related tables."

Show: the four cards, the status overview, recent appointments and the live token queue. Point out: "All these numbers come from COUNT and GROUP BY queries."

### 2. Patients – view, first, last, search, insert, update, delete (2 min)

1. Click **Patients**. "Seven records, ordered by primary key."
2. Click **First**, then **Last**. "First and last record use ORDER BY patient_id with LIMIT 1."
3. Search `9800000005` (phone), then `PT-004` (ID). "One search box works for name, ID and phone using LIKE."
4. **Register patient**. "Section 1 is the appointment, section 2 is the patient." Doctor **Dr. Sanjay Patel** (show that the list has speciality and fee, and no inactive doctor), date today, a free time later today, reason `Back pain`. Patient `Lakshmi Iyer`, 45, Female, `9811111111`, B+, any address → **Register Patient & Book Appointment**.
   Say: "One transaction inserted two rows: the patient PT-008 and the appointment AP-008. If either insert fails, both are rolled back. No token was made, because tokens are given on the visit day."
5. **Edit** Lakshmi, change age to 46 → Save. (UPDATE)
6. Point to the faded Delete on PT-001: "This patient has a token, so the record can't be deleted. The foreign keys are ON DELETE RESTRICT, so history is safe." A patient whose only visit hasn't happened yet can be deleted, together with that upcoming appointment.

### 3. Doctors (30 sec)

Show DR-005 is **Inactive**. "Inactive doctors cannot be booked."

### 4. Booking a follow-up visit (2 min)

1. On Patients, click **Book** for Lakshmi. "The doctor from her registration is already selected, but I can change it."
2. Date tomorrow, time 10:00 AM, reason `Follow-up` → **Review booking**. "Nothing is saved yet."
3. **Confirm booking** → AP-009. "Clicking Confirm twice cannot save two bookings; the form has a one-time token."
4. Quick validation: book again with the same doctor and time → clash error. Try yesterday's date → past-date error.

### 5. Token queue (2 min)

1. **Token Queue** → **Generate token** → pick AP-008 (Lakshmi, today) → T-004.
2. "Only scheduled appointments for today without a token are listed. The token table has a UNIQUE key on appointment_id, so duplicates are impossible."
3. T-002 (Ananya, In Consultation) → **Mark completed**. "This also marks her appointment Completed in one transaction."
4. T-004 → **Call patient**, then **Mark completed**.

### 6. Billing (2 min)

1. **Billing → Create bill** → choose **AP-006 Karthik Raman**. "Patient, doctor and fee are filled from the database."
2. Lab `250.50`, Medicine `120`, Discount `70.50`. "Live preview shows ₹1,100.00. PHP calculates it again on the server in paise, so there are no rounding errors."
3. **Save bill & show receipt** → **Print receipt**.
4. Go back to Create bill: "AP-006 is gone from the list. bills.appointment_id is UNIQUE, so one visit gets one bill."

### 7. Proof in the database (30 sec)

phpMyAdmin → medicare_db → **bills** → Browse: show BL-002 with total 1100.00. Then **Dashboard**: numbers have changed.

### If they ask…

- **"How do you stop SQL injection?"** Every query with user input uses PDO prepared statements with `?` placeholders.
- **"What about XSS?"** All output goes through `htmlspecialchars` (the `e()` function).
- **"Why can't I delete a doctor?"** They have appointments. Set them Inactive instead. If they have none, delete works and patients who preferred them become "No preference" (ON DELETE SET NULL).
- **"What normal form is the schema in?"** Each table describes one thing and every non-key column depends only on its key, so the tables are in 3NF with one known exception: `bills.patient_id` can also be found through the appointment (a transitive dependency). It is kept on purpose because the assignment asks for a direct bill–patient link, and PHP always copies it from the appointment so it can't disagree. `bills.consultation_fee` is not a repeat: it is the fee at billing time, kept so old receipts never change.
- **"What if two people click Generate token at the same moment?"** The UNIQUE key on (queue_date, token_number) makes the second insert fail, and the user sees "Please try again."
