<?php

declare(strict_types=1);

namespace Sukarix\Utils;

use Carbon\Carbon;

/**
 * Time and Date Helper Class.
 */
class Time
{
    public const UNIX_BASE = '0001-01-01 00:00:00';

    /**
     * Format a database-specific date/time string.
     *
     * @param null|\DateTime|int|string $unixTime (optional) the unix time (null = now)
     * @param null|string               $dbms     (optional) the database software the timestamp is for
     *
     * @return bool|string date in format of database driver
     *
     * @throws \Exception
     */
    public static function db($unixTime = null, $dbms = null)
    {
        // Initialize DateTime or Carbon instance from given time or current time if unset
        if (class_exists('Carbon\Carbon')) {
            if ($unixTime instanceof Carbon) {
                $dateTime = $unixTime;
            } elseif (\is_string($unixTime)) {
                $dateTime = new Carbon($unixTime);
            } elseif ($unixTime instanceof \DateTime) {
                $dateTime = Carbon::instance($unixTime);
            } else {
                $dateTime = Carbon::now();
            }

            // Ensure the datetime is not empty and set to now if it is
            if (!$dateTime || $dateTime->getTimestamp() <= 0) {
                $dateTime = Carbon::now();
            }
        } else {
            // Fallback to DateTime if Carbon is not available
            if ($unixTime instanceof \DateTime) {
                $dateTime = $unixTime;
            } elseif (\is_string($unixTime)) {
                $dateTime = new \DateTime($unixTime);
            } else {
                $dateTime = new \DateTime();
            }
        }

        // Format date/time according to database driver
        $dbms = empty($dbms) ? \Base::instance()->get('db.driver') : $dbms;

        switch ($dbms) {
            case 'pgsql':
                // Formatting for PostgreSQL with microseconds
                return $dateTime->format('Y-m-d H:i:s.u'); // PostgreSQL handles timezone

            case 'mysql':
            default:
                // Formatting for MySQL or default handling without specifying timezone
                return $dateTime->format('Y-m-d H:i:s');
        }
    }

    /**
     * Utility to convert timestamp into a http header date/time.
     *
     * @param null|int $unixtime time php time value
     * @param string   $zone     timezone, default GMT
     *
     * @return string
     */
    public static function http($unixtime = null, $zone = 'GMT')
    {
        // use current time if bad time value or unset
        $unixtime = (int) $unixtime;
        if ($unixtime <= 0) {
            $unixtime = time();
        }

        // if it's not a 3 letter timezone set it to GMT
        if (3 !== mb_strlen($zone)) {
            $zone = 'GMT';
        } else {
            $zone = mb_strtoupper($zone);
        }

        return gmdate('D, d M Y H:i:s', $unixtime) . ' ' . $zone;
    }

    /**
     * Format the provided date and time.
     *
     * @param null|string $dateTime
     *
     * @return array
     */
    public static function formattedTime($dateTime = null)
    {
        $formatTime = ' G:i';
        $timestamp  = $dateTime ? strtotime($dateTime) : time();
        $dateMonth  = lcfirst(date('F', $timestamp));
        $dateYear   = lcfirst(date('j, Y', $timestamp));
        $time       = date($formatTime, $timestamp);

        return [$dateMonth, $dateYear, $time];
    }
}
