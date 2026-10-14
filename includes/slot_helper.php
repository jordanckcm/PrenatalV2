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

/* ----------------------------------------------------------------------------
   Live clinic load (used by api/clinic_load.php, wizard.js, clinic_board.js)
   ---------------------------------------------------------------------------- */

// Returns a valid Y-m-d string (falls back to today in clinic time)
function normalizeYmd($value) {
    $value = trim((string)$value);
    $d = DateTime::createFromFormat('Y-m-d', $value);
    if ($d && $d->format('Y-m-d') === $value) return $value;
    return date('Y-m-d');
}

// Maps a remaining/capacity pair to the status words the front-end understands
function clinicLoadStatus($remaining, $capacity) {
    if ($capacity <= 0 || $remaining <= 0) return 'full';
    $ratio = $remaining / $capacity;
    if ($ratio <= 0.25) return 'almost_full';
    if ($ratio <= 0.6)  return 'busy';
    return 'open';
}

// Public counts-only snapshot of one day: hours, per-service seats left, next open slot
function getClinicLoadForDate($db, $date) {
    $date = normalizeYmd($date);
    $slots = getSlotsForDate($db, $date);
    $bookable = array_values(array_filter($slots, function ($s) {
        return !$s['is_blocked'] && !$s['is_past'];
    }));
    $usable = array_values(array_filter($slots, function ($s) { return !$s['is_blocked']; }));

    $out = [
        'date'      => $date,
        'day_label' => date('l, M j', strtotime($date)),
        'is_today'  => ($date === date('Y-m-d')),
        'is_closed' => empty($usable),
        'status'    => 'closed',
        'hours'     => null,
        'next_slot' => null,
        'next_open' => null,
        'services'  => [],
    ];

    if (empty($usable)) {
        // Find the next day (within 14 days) that has slots
        for ($i = 1; $i <= 14; $i++) {
            $next = date('Y-m-d', strtotime("$date +$i day"));
            $nextSlots = array_filter(getSlotsForDate($db, $next), function ($s) { return !$s['is_blocked']; });
            if ($nextSlots) {
                $nextSlots = array_values($nextSlots);
                $out['next_open'] = [
                    'date'      => $next,
                    'day_label' => date('l, M j', strtotime($next)),
                    'hours'     => $nextSlots[0]['time_formatted'] . ' - ' . date('g:i A', strtotime(end($nextSlots)['time']) + 1800),
                ];
                break;
            }
        }
    } else {
        $out['hours'] = [
            'open'  => $usable[0]['time_formatted'],
            'close' => date('g:i A', strtotime(end($usable)['time']) + 1800),
        ];
    }

    // Overall seats across all slots
    $totalCap = 0; $totalLeft = 0;
    foreach ($usable as $s) { $totalCap += $s['capacity']; $totalLeft += $s['available']; }
    $bookableLeft = 0;
    foreach ($bookable as $s) { $bookableLeft += $s['available']; }

    if (!$out['is_closed']) {
        if ($bookableLeft > 0)                 $out['status'] = clinicLoadStatus($bookableLeft, $totalCap);
        elseif ($date === date('Y-m-d') && $totalLeft > 0) $out['status'] = 'ended';
        else                                   $out['status'] = 'full';
    }

    // Per-service daily seats (services.max_daily_slots minus open bookings that day)
    try {
        $svcRows = $db->query("SELECT id, service_name, max_daily_slots FROM services WHERE is_active = 1 ORDER BY id")->fetchAll();
        $cnt = $db->prepare("SELECT service_id, COUNT(*) FROM appointments WHERE appointment_date = ? AND status IN ('pending','confirmed') GROUP BY service_id");
        $cnt->execute([$date]);
        $booked = $cnt->fetchAll(PDO::FETCH_KEY_PAIR);

        foreach ($svcRows as $svc) {
            $cap = (int)$svc['max_daily_slots'];
            $remaining = max(0, $cap - (int)($booked[$svc['id']] ?? 0));
            // Bookable seats are limited by the clinic's own remaining slot capacity too
            $bookableRemaining = min($remaining, $bookableLeft);
            if ($out['is_closed'])            $st = 'closed';
            elseif ($remaining <= 0)          $st = 'full';
            elseif ($bookableLeft <= 0)       $st = 'ended';
            else                              $st = clinicLoadStatus($bookableRemaining, $cap);

            $out['services'][] = [
                'id'                 => (int)$svc['id'],
                'name'               => $svc['service_name'],
                'capacity'           => $cap,
                'remaining'          => $remaining,
                'bookable_remaining' => $bookableRemaining,
                'status'             => $st,
            ];
        }
    } catch (Exception $e) {
        // leave services empty
    }

    if (!empty($bookable)) {
        foreach ($bookable as $s) {
            if ($s['available'] > 0) {
                $out['next_slot'] = [
                    'time'           => $s['time'],
                    'time_formatted' => $s['time_formatted'],
                    'service_name'   => !empty($out['services']) ? $out['services'][0]['name'] : 'Clinic visit',
                ];
                break;
            }
        }
    }

    return $out;
}

// N consecutive days of availability for one service, starting at $startDate
function getClinicDayStrip($db, $serviceId, $startDate, $days = 14) {
    $startDate = normalizeYmd($startDate);
    $days = max(1, min(31, (int)$days));
    $strip = [];
    for ($i = 0; $i < $days; $i++) {
        $d = date('Y-m-d', strtotime("$startDate +$i day"));
        $load = getClinicLoadForDate($db, $d);
        $svc = null;
        foreach ($load['services'] as $s) { if ($s['id'] === (int)$serviceId) { $svc = $s; break; } }
        $cap = $svc ? $svc['capacity'] : 0;
        $rem = $svc ? $svc['remaining'] : 0;
        $strip[] = [
            'date'        => $d,
            'weekday'     => date('D', strtotime($d)),
            'day'         => (int)date('j', strtotime($d)),
            'is_today'    => ($d === date('Y-m-d')),
            'status'      => $svc ? $svc['status'] : $load['status'],
            'remaining'   => $rem,
            'capacity'    => $cap,
            'utilization' => $cap > 0 ? (int)round((($cap - $rem) / $cap) * 100) : 100,
        ];
    }
    return $strip;
}
