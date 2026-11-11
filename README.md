# CareSlot - Healthcare Appointment Management System

PHP 8 + MySQL/MariaDB, runs locally on XAMPP. No Composer, no internet needed.

## Setup (XAMPP, Windows)

1. Install XAMPP (PHP 8.0 or newer) and open the **XAMPP Control Panel**.
2. Click **Start** next to **Apache** and **MySQL** (both turn green).
3. Unzip this project so the folder is exactly `C:\xampp\htdocs\healthcare-appointment\` (the file `index.php` must sit directly inside it).
4. Open http://localhost/phpmyadmin
5. Click the **Import** tab, choose `database.sql` from the project folder, click **Import** at the bottom. This creates the database `healthcare_appointment` with all tables and sample data.
6. Open `config/database.php`. The defaults (user `root`, empty password) already match a fresh XAMPP. Change them only if you set a MySQL password.
7. Open http://localhost/healthcare-appointment/
8. Sign in with a demo account below.

## Demo accounts

| Role    | Email                 | Password     |
|---------|-----------------------|--------------|
| Admin   | admin@careslot.local  | Admin@123    |
| Patient | patient@example.com   | Patient@123  |

You can also register new patients from the Register page. Admin sign-in page: http://localhost/healthcare-appointment/admin/

## Troubleshooting

- **Page not found:** the folder name or location is wrong. It must be `htdocs\healthcare-appointment`.
- **Database connection error:** MySQL is not started, or `database.sql` was not imported, or the password in `config/database.php` is wrong.
- **Apache will not start (port 80 busy):** close Skype/IIS, or change Apache's port in XAMPP and use `http://localhost:8080/healthcare-appointment/`.
- **Other folder name:** edit `BASE_URL` in `includes/functions.php`.

## How double booking is prevented

1. The booking request is validated on the server (slot must be inside working hours, not on a day off, not in the past).
2. Booking runs in a **transaction** that first locks the doctor row (`SELECT ... FOR UPDATE`), so simultaneous requests for one doctor are handled one at a time.
3. `appointments.slot_key` (`doctor|date|time`) has a **UNIQUE index**. Cancelled appointments set it to NULL, which frees the slot. Even if code failed, MySQL rejects the second row and the patient sees a clear "already booked" message.

## Recommendation rules (bonus)

When a specialization is selected, doctors with real free slots in the next 14 days are ranked by a visible score: sooner next slot (max 50), experience (max 30), number of open slots (max 20), lower fee (max 10). The reasons are shown on every card.

## Structure

`index.php` booking dashboard | `my-appointments.php` | `login/register/logout.php` | `ajax/` JSON endpoints | `admin/` dashboard, doctors, availability, patients, appointments | `includes/` shared PHP | `assets/` CSS and JS | `database.sql`

## Known limitations

- Doctor photos are optional uploads (admin > Doctors). Seed doctors show coloured initials because no photos can be bundled.
- Appointment emails/SMS are not sent.
- Changing a doctor's schedule does not move existing appointments.
