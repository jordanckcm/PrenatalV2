<?php
/**
 * Slot Helper Functions
 * Generates and manages time slots for the clinic
 */

/**
 * I-generate ang time slots base sa start, end, ug interval (minutes)
 */
function generateTimeSlots($startTime, $endTime, $intervalMinutes = 30) {
    $slots = [];
    $start = strtotime($startTime);
    $end = strtotime($endTime);
    $interval = $intervalMinutes * 60;

    for ($time = $start; $time < $end; $time += $interval) {
        $slots[] = date('H:i:s', $time);
    }

    return $slots;
}

/**
 * I-kuha ang weekly schedule sa clinic
 */
function getClinicWeeklySchedule($db) {
    try {
        $stmt = $db->query("SELECT * FROM schedules ORDER BY FIELD(day_of_week, 'Monday','Tuesday','Wednesday','Thursday','Friday','Saturday','Sunday')");
        $rows = $stmt->fetchAll();

        $result = [];
        foreach ($rows as $row) {
            $result[$row['day_of_week']] = $row;
        }
        return $result;
    } catch (Exception $e) {
        return [];
    }
}

/**
 * I-check kung naa ba ang slot_overrides table, kung wala, i-create
 */
function ensureSlotOverridesTable($db) {
    try {
        $db->exec("
            CREATE TABLE IF NOT EXISTS slot_overrides (
                id INT AUTO_INCREMENT PRIMARY KEY,
                override_date DATE NOT NULL,
                start_time TIME NOT NULL,
                end_time TIME NOT NULL,
                max_patients INT NOT NULL DEFAULT 2,
                is_blocked TINYINT(1) NOT NULL DEFAULT 0,
                reason VARCHAR(255) DEFAULT NULL,
                created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
                UNIQUE KEY unique_slot (override_date, start_time)
            )
        ");
    } catch (Exception $e) {
        // Silent fail
    }
}

/**
 * I-kuha ang availability sa usa ka adlaw (base sa schedule + overrides + appointments)
 */
function getSlotsForDate($db, $date) {
    $dayOfWeek = date('l', strtotime($date));

    // Kuhaon ang schedule sa maong adlaw
    $stmt = $db->prepare("SELECT * FROM schedules WHERE day_of_week = ? AND is_active = 1");
    $stmt->execute([$dayOfWeek]);
    $schedule = $stmt->fetch();

    if (!$schedule) {
        return []; // Sirado ang clinic
    }

    $interval = 30; // Default 30 minutes
    $slots = generateTimeSlots($schedule['start_time'], $schedule['end_time'], $interval);

    // Kuhaon ang overrides (blocked slots)
    $stmt = $db->prepare("SELECT * FROM slot_overrides WHERE override_date = ?");
    $stmt->execute([$date]);
    $overrides = $stmt->fetchAll();

    $overrideMap = [];
    foreach ($overrides as $o) {
        $overrideMap[$o['start_time']] = $o;
    }

    // Kuhaon ang existing appointments
    $stmt = $db->prepare("
        SELECT appointment_time, COUNT(*) as booked 
        FROM appointments 
        WHERE appointment_date = ? AND status IN ('pending','confirmed')
        GROUP BY appointment_time
    ");
    $stmt->execute([$date]);
    $bookedMap = [];
    while ($row = $stmt->fetch()) {
        $bookedMap[$row['appointment_time']] = (int)$row['booked'];
    }

    // Build ang final slot list
    $result = [];
    foreach ($slots as $slot) {
        $capacity = $schedule['max_patients_per_slot'];
        $isBlocked = false;

        if (isset($overrideMap[$slot])) {
            $o = $overrideMap[$slot];
            if ($o['is_blocked']) {
                $isBlocked = true;
            } else {
                $capacity = $o['max_patients'];
            }
        }

        $booked = $bookedMap[$slot] ?? 0;
        $available = max(0, $capacity - $booked);

        $result[] = [
            'time' => $slot,
            'time_formatted' => date('g:i A', strtotime($slot)),
            'capacity' => $capacity,
            'booked' => $booked,
            'available' => $available,
            'is_blocked' => $isBlocked,
            'is_full' => $available <= 0
        ];
    }

    return $result;
}

/**
 * I-check kung available ba ang specific slot
 */
function isSlotAvailable($db, $date, $time) {
    $slots = getSlotsForDate($db, $date);
    foreach ($slots as $slot) {
        if ($slot['time'] === $time) {
            return !$slot['is_blocked'] && $slot['available'] > 0;
        }
    }
    return false;
}