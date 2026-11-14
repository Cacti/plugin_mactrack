<?php
/*
 +-------------------------------------------------------------------------+
 | Copyright (C) 2004-2026 The Cacti Group                                 |
 |                                                                         |
 | This program is free software; you can redistribute it and/or           |
 | modify it under the terms of the GNU General Public License             |
 | as published by the Free Software Foundation; either version 2          |
 | of the License, or (at your option) any later version.                  |
 |                                                                         |
 | This program is distributed in the hope that it will be useful,         |
 | but WITHOUT ANY WARRANTY; without even the implied warranty of          |
 | MERCHANTABILITY or FITNESS FOR A PARTICULAR PURPOSE.  See the           |
 | GNU General Public License for more details.                            |
 +-------------------------------------------------------------------------+
 | Cacti: The Complete RRDtool-based Graphing Solution                     |
 +-------------------------------------------------------------------------+
 | This code is designed, written, and maintained by the Cacti Group. See  |
 | about.php and/or the AUTHORS file for specific developer information.   |
 +-------------------------------------------------------------------------+
 | http://www.cacti.net/                                                   |
 +-------------------------------------------------------------------------+
*/

require_once dirname(__DIR__) . '/Support/load_function.php';

/*
 * The MacWatch schedule is the minutes between mails; the column must
 * hold a day (1440), and each mail records when it went out.
 */
it('offers the schedules in minutes', function () {
	$src = file_get_contents(dirname(__DIR__, 2) . '/setup.php');

	expect($src)->toContain("720  => __('Every %d Hours', 12, 'mactrack'),");
	expect($src)->toContain("1440 => __('Every Day', 'mactrack')]");
	expect($src)->not->toContain("1800 => __('Every %d Hours', 12, 'mactrack'),");
});

it('stores a day in the schedule column and widens old installs', function () {
	$src = file_get_contents(dirname(__DIR__, 2) . '/includes/database.php');

	expect($src)->toContain("['name' => 'notify_schedule', 'unsigned' => true, 'type' => 'smallint(5)'");
	expect($src)->toContain("MODIFY COLUMN notify_schedule smallint(5) unsigned NOT NULL default '1'");
	expect($src)->toContain('UPDATE mac_track_macwatch SET notify_schedule = 1440 WHERE notify_schedule = 255');
});

it('mails new entries, then follows the schedule in minutes', function (array $record, bool $mail) {
	mactrack_test_load_function('poller_mactrack.php', 'mactrack_macwatch_should_mail');

	$now = strtotime('2026-11-12 12:00:00');

	expect(mactrack_macwatch_should_mail($record + ['date_last_notif' => '2026-11-12 11:30:00'], $now))->toBe($mail);
})->with([
	'not yet discovered, mail once'      => [['discovered' => '0', 'notify_schedule' => '1'], true],
	'discovered, mail once'              => [['discovered' => '1', 'notify_schedule' => '1'], false],
	'every scan'                         => [['discovered' => '1', 'notify_schedule' => '2'], true],
	'hourly, 30 minutes after last mail' => [['discovered' => '1', 'notify_schedule' => '60'], false],
	'half-hourly, at the interval'       => [['discovered' => '1', 'notify_schedule' => '30'], true],
	'daily, not yet discovered'          => [['discovered' => '0', 'notify_schedule' => '1440'], false],
]);

it('mails an entry that was never mailed whatever its schedule', function () {
	mactrack_test_load_function('poller_mactrack.php', 'mactrack_macwatch_should_mail');

	expect(mactrack_macwatch_should_mail(['discovered' => '1', 'notify_schedule' => '1440', 'date_last_notif' => '0000-00-00 00:00:00'], time()))->toBeTrue();
});

it('records each mail', function () {
	$src = preg_replace('/\s+/', ' ', file_get_contents(dirname(__DIR__, 2) . '/poller_mactrack.php'));

	expect($src)->toContain("if (mactrack_macwatch_should_mail(\$record, time())) { mactrack_mail(\$record['email_addresses'], \$from, \$fromname, \$subject, \$message); db_execute_prepared('UPDATE mac_track_macwatch SET date_last_notif = NOW() WHERE mac_id = ?', [\$record['mac_id']]);");
});
