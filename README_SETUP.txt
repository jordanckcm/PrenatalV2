MaternalCare Prenatal Health System - setup

1. Extract this zip INTO C:\xampp\htdocs\ (so you get C:\xampp\htdocs\prenatal\). Choose "Replace files" if asked.
   Do NOT delete your old prenatal folder first: five of your pages are not inside this zip
   (admin/dashboard.php, admin/appointments.php, admin/schedules.php, patient/dashboard.php, patient/notifications.php).
2. Start Apache + MySQL in the XAMPP Control Panel.
3. Fresh database: open http://localhost/prenatal/config/setup_db.php and click "Initialize Database Now"
   (or import database.sql in phpMyAdmin). An existing database is upgraded automatically.
4. Open http://localhost/prenatal/login.php  (demo password for all demo accounts: password123)
5. When done: delete fix_passwords.php and config/setup_db.php.
