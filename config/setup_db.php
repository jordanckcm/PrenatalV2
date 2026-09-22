<?php
/**
 * One-Click Database Installer & Setup Script
 * Web-Based Prenatal Health Center Booking Appointment and Record Management System
 */

require_once __DIR__ . '/config.php';

$message = '';
$success = false;

if ($_SERVER['REQUEST_METHOD'] === 'POST' || isset($_GET['auto'])) {
    try {

        // Use the database connection already configured in database.php
        $pdo = getDB();

        // Locate database.sql
        $sqlFile = __DIR__ . '/../database.sql';

        if (!file_exists($sqlFile)) {
            throw new Exception("database.sql file missing in root directory.");
        }

        // Read SQL file
        $sqlContent = file_get_contents($sqlFile);

        if ($sqlContent === false) {
            throw new Exception("Unable to read database.sql.");
        }

        // Execute SQL statements
        $pdo->exec($sqlContent);

        $success = true;

        $message = "Database tables and sample data initialized successfully!";

        // Record setup in audit log
        logAudit(
            'SYSTEM_SETUP',
            'Database initialized via setup_db.php'
        );

    } catch (Exception $e) {

        $success = false;

        $message = "Error initializing database: " . $e->getMessage();
    }
}
?>

<!DOCTYPE html>
<html lang="en">

<head>

    <meta charset="UTF-8">

    <meta name="viewport"
          content="width=device-width, initial-scale=1.0">

    <title>
        Database Setup - Prenatal Health System
    </title>

    <link rel="stylesheet"
          href="../assets/css/style.css">

    <link rel="stylesheet"
          href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.4.0/css/all.min.css">

</head>

<body class="auth-bg">

    <div class="auth-container">

        <div class="auth-card glassmorphism">

            <div class="auth-header">

                <i class="fa-solid fa-baby-carriage brand-icon"></i>

                <h2>
                    Prenatal Health System
                </h2>

                <p>
                    Database Installer & Initialization
                </p>

            </div>


            <?php if ($message): ?>

                <div class="alert <?php echo $success ? 'alert-success' : 'alert-danger'; ?>">

                    <i class="fa-solid <?php echo $success ? 'fa-circle-check' : 'fa-circle-exclamation'; ?>"></i>

                    <div>
                        <?php echo htmlspecialchars($message); ?>
                    </div>

                </div>

            <?php endif; ?>


            <?php if ($success): ?>

                <div class="setup-demo-accounts">

                    <h4>
                        <i class="fa-solid fa-users"></i>
                        Default Pre-seeded Accounts
                    </h4>

                    <p>
                        All demo accounts use password:
                        <strong>password123</strong>
                    </p>

                    <ul class="demo-list">

                        <li>
                            <strong>System Admin:</strong>
                            <code>admin</code>
                        </li>

                        <li>
                            <strong>Doctor:</strong>
                            <code>doctor1</code>
                        </li>

                        <li>
                            <strong>Nurse:</strong>
                            <code>nurse1</code>
                        </li>

                        <li>
                            <strong>Patient 1:</strong>
                            <code>patient1</code>
                        </li>

                        <li>
                            <strong>Patient 2:</strong>
                            <code>patient2</code>
                        </li>

                    </ul>

                </div>


                <div class="form-group mt-4">

                    <a href="../login.php"
                       class="btn btn-primary btn-block btn-lg">

                        <i class="fa-solid fa-right-to-bracket"></i>

                        Proceed to Login

                    </a>


                    <a href="../index.php"
                       class="btn btn-outline btn-block mt-2">

                        <i class="fa-solid fa-house"></i>

                        Go to Landing Page

                    </a>

                </div>


            <?php else: ?>

                <p class="text-center text-muted mb-4">

                    Click the button below to initialize the
                    MaternalCare database tables and sample data

                </p>


                <form method="POST" action="">

                    <button type="submit"
                            class="btn btn-primary btn-block btn-lg">

                        <i class="fa-solid fa-database"></i>

                        Initialize Database Now

                    </button>

                </form>

            <?php endif; ?>

        </div>

    </div>

</body>

</html>
