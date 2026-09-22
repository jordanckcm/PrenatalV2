<?php
/**
 * Staff Duty Schedule & Availability Helper
 * Web-Based Prenatal Health Center Booking Appointment and Record Management System
 */

require_once __DIR__ . '/../config/config.php';

/**
 * Ensures the staff_duty_schedules table exists.
 */
function ensureStaffDutyTable($db) {
    static $checked = false;
    if ($checked) return;

    $sql = "CREATE TABLE IF NOT EXISTS `staff_duty_schedules` (
        `id` INT AUTO_INCREMENT PRIMARY KEY,
        `staff_id` INT NOT NULL,
        `day_of_week` ENUM('Monday', 'Tuesday', 'Wednesday', 'Thursday', 'Friday', 'Saturday', 'Sunday') NOT NULL,
        `start_time` TIME NOT NULL DEFAULT '08:00:00',
        `end_time` TIME NOT NULL DEFAULT '16:00:00',
        `is_duty` TINYINT(1) NOT NULL DEFAULT 1,
        `notes` VARCHAR(255) DEFAULT NULL,
        UNIQUE KEY `idx_staff_day` (`staff_id`, `day_of_week`),
        FOREIGN KEY (`staff_id`) REFERENCES `users`(`id`) ON DELETE CASCADE
    ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;";

    try {
        $db->exec($sql);
        $checked = true;
    } catch (Exception $e) {
        // Table already exists
    }
}

/**
 * Initializes default weekly schedule for a staff member if not already set.
 */
function initializeStaffDutySchedule($db, $staffId) {
    ensureStaffDutyTable($db);

    $days = [
        'Monday'    => ['is_duty' => 1, 'start' => '08:00:00', 'end' => '16:00:00'],
        'Tuesday'   => ['is_duty' => 1, 'start' => '08:00:00', 'end' => '16:00:00'],
        'Wednesday' => ['is_duty' => 1, 'start' => '08:00:00', 'end' => '16:00:00'],
        'Thursday'  => ['is_duty' => 1, 'start' => '08:00:00', 'end' => '16:00:00'],
        'Friday'    => ['is_duty' => 1, 'start' => '08:00:00', 'end' => '16:00:00'],
        'Saturday'  => ['is_duty' => 1, 'start' => '09:00:00', 'end' => '13:00:00'],
        'Sunday'    => ['is_duty' => 0, 'start' => '08:00:00', 'end' => '12:00:00']
    ];

    $check = $db->prepare("SELECT COUNT(*) FROM staff_duty_schedules WHERE staff_id = ?");
    $check->execute([$staffId]);
    if ((int)$check->fetchColumn() === 0) {
        $insert = $db->prepare("INSERT INTO staff_duty_schedules (staff_id, day_of_week, start_time, end_time, is_duty) VALUES (?, ?, ?, ?, ?) ON DUPLICATE KEY UPDATE is_duty = VALUES(is_duty)");
        foreach ($days as $day => $info) {
            $insert->execute([$staffId, $day, $info['start'], $info['end'], $info['is_duty']]);
        }
    }
}

/**
 * Retrieves the full weekly duty schedule for a staff member.
 */
function getStaffWeeklyDutySchedule($db, $staffId) {
    ensureStaffDutyTable($db);
    initializeStaffDutySchedule($db, $staffId);

    $stmt = $db->prepare("SELECT * FROM staff_duty_schedules WHERE staff_id = ? ORDER BY FIELD(day_of_week, 'Monday', 'Tuesday', 'Wednesday', 'Thursday', 'Friday', 'Saturday', 'Sunday')");
    $stmt->execute([$staffId]);
    return $stmt->fetchAll(PDO::FETCH_ASSOC);
}

/**
 * Checks staff duty status, working hours, and vacancy for a specific date and time.
 */
function getStaffAvailabilityForDate($db, $staffId, $dateStr, $timeStr = null, $excludeAppointmentId = 0) {
    ensureStaffDutyTable($db);
    initializeStaffDutySchedule($db, $staffId);

    $dayOfWeek = date('l', strtotime($dateStr));

    // 1. Fetch Staff User Info
    $uStmt = $db->prepare("SELECT id, full_name, email, phone FROM users WHERE id = ?");
    $uStmt->execute([$staffId]);
    $staff = $uStmt->fetch(PDO::FETCH_ASSOC);

    if (!$staff) {
        return null;
    }

    // 2. Fetch Duty Schedule for this day of week
    $dStmt = $db->prepare("SELECT * FROM staff_duty_schedules WHERE staff_id = ? AND day_of_week = ? LIMIT 1");
    $dStmt->execute([$staffId, $dayOfWeek]);
    $duty = $dStmt->fetch(PDO::FETCH_ASSOC);

    $isOnDuty = $duty ? (bool)$duty['is_duty'] : false;
    $startTime = $duty ? $duty['start_time'] : '08:00:00';
    $endTime = $duty ? $duty['end_time'] : '16:00:00';

    // 3. Count existing confirmed appointments assigned to this staff on that date
    $countSql = "SELECT COUNT(*) FROM appointments WHERE healthcare_worker_id = ? AND appointment_date = ? AND status IN ('pending', 'confirmed') AND id != ?";
    $cStmt = $db->prepare($countSql);
    $cStmt->execute([$staffId, $dateStr, $excludeAppointmentId]);
    $assignedAppointmentsCount = (int)$cStmt->fetchColumn();

    // 4. Check time slot conflict
    $hasSlotConflict = false;
    $conflictAppointmentCode = '';
    if (!empty($timeStr)) {
        $confSql = "SELECT appointment_code FROM appointments WHERE healthcare_worker_id = ? AND appointment_date = ? AND appointment_time = ? AND status IN ('pending', 'confirmed') AND id != ? LIMIT 1";
        $confStmt = $db->prepare($confSql);
        $confStmt->execute([$staffId, $dateStr, $timeStr, $excludeAppointmentId]);
        $conflictAppt = $confStmt->fetch(PDO::FETCH_ASSOC);
        if ($conflictAppt) {
            $hasSlotConflict = true;
            $conflictAppointmentCode = $conflictAppt['appointment_code'];
        }
    }

    // 5. Time within shift range check
    $isWithinShift = true;
    if (!empty($timeStr) && $isOnDuty) {
        $slotTs = strtotime($timeStr);
        $startTs = strtotime($startTime);
        $endTs = strtotime($endTime);
        if ($slotTs < $startTs || $slotTs >= $endTs) {
            $isWithinShift = false;
        }
    }

    // Determine Vacancy
    $isVacant = ($isOnDuty && !$hasSlotConflict && $isWithinShift);

    // Formatted label
    $shiftFormatted = date('g:i A', strtotime($startTime)) . ' - ' . date('g:i A', strtotime($endTime));

    return [
        'staff_id' => (int)$staffId,
        'full_name' => $staff['full_name'],
        'phone' => $staff['phone'],
        'date' => $dateStr,
        'day_of_week' => $dayOfWeek,
        'is_on_duty' => $isOnDuty,
        'start_time' => $startTime,
        'end_time' => $endTime,
        'shift_formatted' => $shiftFormatted,
        'is_within_shift' => $isWithinShift,
        'assigned_count' => $assignedAppointmentsCount,
        'has_slot_conflict' => $hasSlotConflict,
        'conflict_code' => $conflictAppointmentCode,
        'is_vacant' => $isVacant,
        'status_text' => $isOnDuty ? ($isVacant ? "On Duty & Vacant ({$assignedAppointmentsCount} booked)" : ($hasSlotConflict ? "Conflict at " . date('g:i A', strtotime($timeStr)) : "Outside Shift Hours")) : "Off Duty on {$dayOfWeek}"
    ];
}
