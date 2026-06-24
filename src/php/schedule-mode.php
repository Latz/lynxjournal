<?php
/**
 * LynxJournal schedule mode enum.
 *
 * @package LynxJournal
 * @since   1.0.0
 */

declare(strict_types=1);

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

enum LynxJournal_ScheduleMode: string {
	case Daily   = 'daily';
	case Weekly  = 'weekly';
	case Monthly = 'monthly';
	case Count   = 'count';
	case Age     = 'age';
	case Manual  = 'manual';

	/** Returns the modes that use a calendar-based recurrence config. */
	public static function time_based(): array {
		return array( self::Daily, self::Weekly, self::Monthly );
	}

	/** Returns the modes that use a threshold trigger instead of a schedule. */
	public static function trigger_based(): array {
		return array( self::Count, self::Age );
	}
}
