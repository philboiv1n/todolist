<?php

namespace TodoApp;

use DateTimeImmutable;
use DateTimeZone;

class Recurrence
{
    /**
     * Recurrence rules are stored as JSON in `todos.repeat_rule`.
     *
     * Supported forms:
     * - {"freq":"daily"}
     * - {"freq":"weekly","byweekday":[1..7]} (Mon=1..Sun=7)
     * - {"freq":"monthly","bymonthday":1..31}
     * - {"freq":"yearly","bymonth":1..12,"bymonthday":1..31}
     */

    public static function buildRuleFromPreset(string $preset, ?string $dueDate): ?string
    {
        $preset = strtolower(trim($preset));
        if ($preset === '' || $preset === 'none') {
            return null;
        }

        $date = self::parseIsoDate($dueDate) ?? new DateTimeImmutable('today');

        $rule = null;
        if ($preset === 'daily') {
            $rule = ['freq' => 'daily'];
        } elseif ($preset === 'weekdays') {
            $rule = ['freq' => 'weekly', 'byweekday' => [1, 2, 3, 4, 5]];
        } elseif ($preset === 'weekly') {
            $weekday = (int)$date->format('N');
            $rule = ['freq' => 'weekly', 'byweekday' => [$weekday]];
        } elseif ($preset === 'monthly') {
            $day = (int)$date->format('j');
            $rule = ['freq' => 'monthly', 'bymonthday' => $day];
        } elseif ($preset === 'yearly') {
            $month = (int)$date->format('n');
            $day = (int)$date->format('j');
            $rule = ['freq' => 'yearly', 'bymonth' => $month, 'bymonthday' => $day];
        }

        if ($rule === null) {
            return null;
        }

        $json = json_encode($rule, JSON_UNESCAPED_SLASHES);
        return is_string($json) ? $json : null;
    }

    public static function describe(?string $ruleJson): ?string
    {
        $rule = self::parseRule($ruleJson);
        if (!$rule) {
            return null;
        }

        if ($rule['freq'] === 'daily') {
            return 'Daily';
        }

        if ($rule['freq'] === 'weekly') {
            $byweekday = $rule['byweekday'] ?? [];
            if ($byweekday === [1, 2, 3, 4, 5]) {
                return 'Weekdays';
            }
            if (count($byweekday) === 1) {
                $names = [1 => 'Mon', 2 => 'Tue', 3 => 'Wed', 4 => 'Thu', 5 => 'Fri', 6 => 'Sat', 7 => 'Sun'];
                $name = $names[$byweekday[0]] ?? 'Weekly';
                return "Weekly ({$name})";
            }
            return 'Weekly';
        }

        if ($rule['freq'] === 'monthly') {
            $day = $rule['bymonthday'] ?? null;
            if (is_int($day) && $day >= 1 && $day <= 31) {
                return "Monthly (day {$day})";
            }
            return 'Monthly';
        }

        if ($rule['freq'] === 'yearly') {
            $month = $rule['bymonth'] ?? null;
            $day = $rule['bymonthday'] ?? null;
            if (is_int($month) && is_int($day) && $month >= 1 && $month <= 12 && $day >= 1 && $day <= 31) {
                $dt = self::safeDate((int)date('Y'), $month, $day);
                $label = $dt ? $dt->format('M j') : "{$month}/{$day}";
                return "Yearly ({$label})";
            }
            return 'Yearly';
        }

        return null;
    }

    /**
     * Compute the next due date (YYYY-MM-DD) for a recurring todo.
     *
     * Scheduling is based on the later of:
     * - the todo's current due date (if it's in the future), and
     * - the completion date (today).
     *
     * This avoids generating an immediately overdue "next" occurrence when a task
     * is completed late, while also keeping the cadence stable when completed early.
     */
    public static function nextDueDate(?string $currentDueDate, ?string $ruleJson, ?DateTimeImmutable $completedAt = null): ?string
    {
        $rule = self::parseRule($ruleJson);
        if (!$rule) {
            return null;
        }

        $completedAt = $completedAt ?? new DateTimeImmutable('today');
        $due = self::parseIsoDate($currentDueDate);
        $anchor = $due && $due > $completedAt ? $due : $completedAt;

        $next = null;
        if ($rule['freq'] === 'daily') {
            $next = $anchor->modify('+1 day');
        } elseif ($rule['freq'] === 'weekly') {
            $byweekday = $rule['byweekday'] ?? [];
            if (empty($byweekday)) {
                $byweekday = [(int)$anchor->format('N')];
            }
            for ($i = 1; $i <= 7; $i++) {
                $candidate = $anchor->modify("+{$i} day");
                if (in_array((int)$candidate->format('N'), $byweekday, true)) {
                    $next = $candidate;
                    break;
                }
            }
        } elseif ($rule['freq'] === 'monthly') {
            $day = (int)($rule['bymonthday'] ?? 0);
            if ($day <= 0) {
                $day = (int)$anchor->format('j');
            }

            $year = (int)$anchor->format('Y');
            $month = (int)$anchor->format('n');
            for ($i = 0; $i < 24; $i++) {
                $candidateYear = $year + intdiv(($month - 1 + $i), 12);
                $candidateMonth = (($month - 1 + $i) % 12) + 1;
                $candidate = self::safeDate($candidateYear, $candidateMonth, $day);
                if ($candidate && $candidate > $anchor) {
                    $next = $candidate;
                    break;
                }
            }
        } elseif ($rule['freq'] === 'yearly') {
            $month = (int)($rule['bymonth'] ?? 0);
            $day = (int)($rule['bymonthday'] ?? 0);
            if ($month <= 0 || $month > 12) {
                $month = (int)$anchor->format('n');
            }
            if ($day <= 0) {
                $day = (int)$anchor->format('j');
            }

            $year = (int)$anchor->format('Y');
            for ($i = 0; $i < 8; $i++) {
                $candidate = self::safeDate($year + $i, $month, $day);
                if ($candidate && $candidate > $anchor) {
                    $next = $candidate;
                    break;
                }
            }
        }

        return $next ? $next->format('Y-m-d') : null;
    }

    private static function parseIsoDate(?string $isoDate): ?DateTimeImmutable
    {
        if (!is_string($isoDate)) {
            return null;
        }
        $isoDate = trim($isoDate);
        if ($isoDate === '') {
            return null;
        }

        $tz = new DateTimeZone(date_default_timezone_get());
        $dt = DateTimeImmutable::createFromFormat('!Y-m-d', $isoDate, $tz);
        if (!$dt) {
            return null;
        }

        $errors = DateTimeImmutable::getLastErrors();
        if ($errors && ($errors['warning_count'] > 0 || $errors['error_count'] > 0)) {
            return null;
        }

        return $dt;
    }

    /**
     * Parse and normalize a recurrence rule JSON payload.
     *
     * @return ?array{freq:string, byweekday?:array<int,int>, bymonth?:int, bymonthday?:int}
     */
    private static function parseRule(?string $ruleJson): ?array
    {
        if (!is_string($ruleJson)) {
            return null;
        }
        $ruleJson = trim($ruleJson);
        if ($ruleJson === '') {
            return null;
        }

        $data = json_decode($ruleJson, true);
        if (!is_array($data)) {
            return null;
        }

        $freq = $data['freq'] ?? null;
        if (!is_string($freq)) {
            return null;
        }
        $freq = strtolower(trim($freq));

        if ($freq === 'daily') {
            return ['freq' => 'daily'];
        }

        if ($freq === 'weekly') {
            $byweekday = $data['byweekday'] ?? [];
            if (!is_array($byweekday)) {
                $byweekday = [];
            }
            $normalized = [];
            foreach ($byweekday as $v) {
                if (is_int($v)) {
                    $n = $v;
                } elseif (is_string($v) && ctype_digit($v)) {
                    $n = (int)$v;
                } else {
                    continue;
                }
                if ($n < 1 || $n > 7) {
                    continue;
                }
                $normalized[$n] = $n;
            }
            $normalized = array_values($normalized);
            sort($normalized);

            return ['freq' => 'weekly', 'byweekday' => $normalized];
        }

        if ($freq === 'monthly') {
            $bymonthday = $data['bymonthday'] ?? null;
            if (is_string($bymonthday) && ctype_digit($bymonthday)) {
                $bymonthday = (int)$bymonthday;
            }
            if (!is_int($bymonthday) || $bymonthday < 1 || $bymonthday > 31) {
                return null;
            }
            return ['freq' => 'monthly', 'bymonthday' => $bymonthday];
        }

        if ($freq === 'yearly') {
            $bymonth = $data['bymonth'] ?? null;
            if (is_string($bymonth) && ctype_digit($bymonth)) {
                $bymonth = (int)$bymonth;
            }
            $bymonthday = $data['bymonthday'] ?? null;
            if (is_string($bymonthday) && ctype_digit($bymonthday)) {
                $bymonthday = (int)$bymonthday;
            }

            if (!is_int($bymonth) || $bymonth < 1 || $bymonth > 12) {
                return null;
            }
            if (!is_int($bymonthday) || $bymonthday < 1 || $bymonthday > 31) {
                return null;
            }

            return ['freq' => 'yearly', 'bymonth' => $bymonth, 'bymonthday' => $bymonthday];
        }

        return null;
    }

    private static function safeDate(int $year, int $month, int $day): ?DateTimeImmutable
    {
        if ($month < 1 || $month > 12) {
            return null;
        }

        $firstOfMonth = DateTimeImmutable::createFromFormat(
            '!Y-m-d',
            sprintf('%04d-%02d-01', $year, $month),
            new DateTimeZone(date_default_timezone_get())
        );
        if (!$firstOfMonth) {
            return null;
        }
        $daysInMonth = (int)$firstOfMonth->format('t');
        $day = max(1, min($day, $daysInMonth));

        return $firstOfMonth->setDate($year, $month, $day);
    }
}

