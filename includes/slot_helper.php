<?php
/**
 * Slot Helper Functions
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

function getClinicWeeklySchedule($db) {
    try {
        $stmt = $db->query("SELECT * FROM schedules ORDER BY FIELD(day_of_week, 'Monday','Tuesday','Wednesday','Thursday','Friday','Saturday','Sunday')");
        return $stmt->fetchAll();
    } catch (Exception $e) {
        return [];
    }
}

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
    }
}

function getSlotsForDate($db, $date) {
    $dayOfWeek = date('l', strtotime($date));

    $stmt = $db->prepare("SELECT * FROM schedules WHERE day_of_week = ? AND is_active = 1");
    $stmt->execute([$dayOfWeek]);
    $schedule = $stmt->fetch();

    $stmt = $db->prepare("SELECT * FROM slot_overrides WHERE override_date = ?");
    $stmt->execute([$date]);
    $overrides = $stmt->fetchAll();

    $overrideMap = [];
    foreach ($overrides as $o) {
        $overrideMap[$o['start_time']] = $o;
    }

    $slots = [];

    if ($schedule) {
        $interval = 30;
        $regularSlots = generateTimeSlots($schedule['start_time'], $schedule['end_time'], $interval);
        foreach ($regularSlots as $slot) {
            $capacity = (int)$schedule['max_patients_per_slot'];
            $isBlocked = false;

            if (isset($overrideMap[$slot])) {
                $o = $overrideMap[$slot];
                if ($o['is_blocked']) {
                    $isBlocked = true;
                } else {
                    $capacity = (int)$o['max_patients'];
                }
            }

            $slots[$slot] = [
                'time' => $slot,
                'time_formatted' => date('g:i A', strtotime($slot)),
                'capacity' => $capacity,
                'is_blocked' => $isBlocked,
            ];
        }
    }

    foreach ($overrides as $o) {
        if ($o['is_blocked']) continue;
        $slotTime = $o['start_time'];
        if (!isset($slots[$slotTime])) {
            $slots[$slotTime] = [
                'time' => $slotTime,
                'time_formatted' => date('g:i A', strtotime($slotTime)),
                'capacity' => (int)$o['max_patients'],
                'is_blocked' => false,
            ];
        }
    }

    ksort($slots);

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

    $result = [];
    foreach ($slots as $slot) {
        $booked = isset($bookedMap[$slot['time']]) ? $bookedMap[$slot['time']] : 0;
        $available = max(0, $slot['capacity'] - $booked);

        $result[] = [
            'time' => $slot['time'],
            'time_formatted' => $slot['time_formatted'],
            'capacity' => (int)$slot['capacity'],
            'booked' => (int)$booked,
            'available' => (int)$available,
            'is_blocked' => (bool)$slot['is_blocked'],
            'is_full' => $available <= 0,
        ];
    }

    return $result;
}

function isSlotAvailable($db, $date, $time) {
    $slots = getSlotsForDate($db, $date);
    foreach ($slots as $slot) {
        if ($slot['time'] === $time) {
            return !$slot['is_blocked'] && $slot['available'] > 0;
        }
    }
    return false;
}