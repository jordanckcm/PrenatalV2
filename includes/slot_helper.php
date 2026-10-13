<?php
/**
 * Slot Helper Functions
 */

// Marker stored in slot_overrides.reason: a regular (weekly-schedule) slot the admin moved or deleted
// for one date. Without it the original slot is regenerated from the weekly schedule and the
// edit appears to "go back" (e.g. to 8:00 AM).
const SLOT_REMOVED_MARKER = '__removed__';

// Accepts 8:5, 08:05 or 08:05:00 and returns HH:MM:SS (or null when invalid)
function normalizeTimeValue($t) {
    $t = trim((string)$t);
    if (!preg_match('/^(\d{1,2}):(\d{2})(?::(\d{2}))?$/', $t, $m)) return null;
    $h = (int)$m[1]; $i = (int)$m[2]; $sec = isset($m[3]) ? (int)$m[3] : 0;
    if ($h > 23 || $i > 59 || $sec > 59) return null;
    return sprintf('%02d:%02d:%02d', $h, $i, $sec);
}

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
        $stmt = $db->query("SELECT * FROM schedules ORDER BY FIELD(day_of_week, 'Monday','Tuesday','Wednesday','Thursday','Friday','Saturday','Sunday'), (staff_id IS NULL) ASC, id ASC");
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

    $stmt = $db->prepare("SELECT * FROM schedules WHERE day_of_week = ? AND is_active = 1 ORDER BY (staff_id IS NULL) DESC, id DESC LIMIT 1");
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
                if ($o['is_blocked'] && ($o['reason'] ?? '') === SLOT_REMOVED_MARKER) {
                    continue; // moved or deleted by the admin for this date
                }
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

    // Slots whose start time has already passed today can no longer be booked
    $isToday = ($date === date('Y-m-d'));
    $nowTime = date('H:i:s');

    $result = [];
    foreach ($slots as $slot) {
        $booked = isset($bookedMap[$slot['time']]) ? $bookedMap[$slot['time']] : 0;
        $available = max(0, $slot['capacity'] - $booked);
        $isPast = ($date < date('Y-m-d')) || ($isToday && $slot['time'] <= $nowTime);

        $result[] = [
            'time' => $slot['time'],
            'time_formatted' => $slot['time_formatted'],
            'capacity' => (int)$slot['capacity'],
            'booked' => (int)$booked,
            'available' => (int)$available,
            'is_blocked' => (bool)$slot['is_blocked'],
            'is_full' => $available <= 0,
            'is_past' => $isPast,
        ];
    }

    return $result;
}

function isSlotAvailable($db, $date, $time) {
    $slots = getSlotsForDate($db, $date);
    foreach ($slots as $slot) {
        if ($slot['time'] === $time) {
            return !$slot['is_blocked'] && !$slot['is_past'] && $slot['available'] > 0;
        }
    }
    return false;
}