<?php
/*
 +-------------------------------------------------------------------------+
 | Copyright (C) 2004-2026 The Cacti Group                                 |
 |                                                                         |
 | This program is free software; you can redistribute it and/or           |
 | modify it under the terms of the GNU General Public License             |
 | as published by the Free Software Foundation; either version 2          |
 | of the License, or (at your option) any later version.                  |
 +-------------------------------------------------------------------------+
 | Cacti: The Complete RRDtool-based Graphing Solution                     |
 +-------------------------------------------------------------------------+
 | http://www.cacti.net/                                                   |
 +-------------------------------------------------------------------------+
*/

/*
 * Before any partition DDL, the layout read from information_schema must
 * be one MacTrack built: RANGE on UNIX_TIMESTAMP(scan_date), valid names,
 * strictly increasing bounds, dMaxValue last. Anything else stops DDL.
 */

beforeAll(function () {
	mactrack_test_load(dirname(__DIR__, 2) . '/lib/mactrack_partitions.php');
});

/**
 * Builds information_schema.PARTITIONS rows as the server returns them.
 *
 * @param array<int, array{0: string, 1: string, 2?: string}> $parts [name, description, rows].
 * @param string $expression PARTITION_EXPRESSION.
 * @param string $method PARTITION_METHOD.
 * @return array<int, array<string, string|null>>
 */
function mt_layout_rows(array $parts, string $expression = 'unix_timestamp(`scan_date`)', string $method = 'RANGE'): array {
	$rows = [];

	foreach (array_values($parts) as $i => $p) {
		$rows[] = [
			'PARTITION_NAME'             => $p[0],
			'PARTITION_ORDINAL_POSITION' => (string) ($i + 1),
			'PARTITION_METHOD'           => $method,
			'PARTITION_EXPRESSION'       => $expression,
			'PARTITION_DESCRIPTION'      => $p[1],
			'TABLE_ROWS'                 => $p[2] ?? '0',
		];
	}

	return $rows;
}

it('reports an unpartitioned table as none', function () {
	expect(mactrack_partition_layout([])['state'])->toBe('none');

	$plain = [['PARTITION_NAME' => null, 'PARTITION_ORDINAL_POSITION' => null, 'PARTITION_METHOD' => null,
		'PARTITION_EXPRESSION' => null, 'PARTITION_DESCRIPTION' => null, 'TABLE_ROWS' => '120']];
	expect(mactrack_partition_layout($plain))->toBe(['state' => 'none', 'reason' => '', 'partitions' => []]);
});

it('recognises the legacy daily layout from mactrack_convert.php', function () {
	$layout = mactrack_partition_layout(mt_layout_rows([['d20261111', '740297'], ['dMaxValue', 'MAXVALUE']], 'to_days(`scan_date`)'));

	expect($layout['state'])->toBe('legacy');
	expect($layout['partitions'])->toHaveCount(2);
});

it('accepts a layout MacTrack built and reports each partition', function () {
	$layout = mactrack_partition_layout(mt_layout_rows([
		['s20261112061507', '1794464108', '20000'],
		['s20261112063007', '1794465008', '19980'],
		['dMaxValue', 'MAXVALUE', '0'],
	]));

	expect($layout)->toBe(['state' => 'range', 'reason' => '', 'partitions' => [
		['name' => 's20261112061507', 'bound' => 1794464108, 'rows' => 20000],
		['name' => 's20261112063007', 'bound' => 1794465008, 'rows' => 19980],
		['name' => 'dMaxValue', 'bound' => null, 'rows' => 0],
	]]);
});

it('accepts a layout holding only dMaxValue', function () {
	expect(mactrack_partition_layout(mt_layout_rows([['dMaxValue', 'MAXVALUE']]))['state'])->toBe('range');
});

it('ignores case, spaces and backticks in the expression', function () {
	expect(mactrack_partition_layout(mt_layout_rows([['dMaxValue', 'MAXVALUE']], 'UNIX_TIMESTAMP( scan_date )'))['state'])->toBe('range');
});

it('sorts rows by ordinal position before checking them', function () {
	$rows = mt_layout_rows([['h2026111206', '1794466800'], ['h2026111207', '1794470400'], ['dMaxValue', 'MAXVALUE']]);
	$layout = mactrack_partition_layout(array_reverse($rows));

	expect($layout['state'])->toBe('range');
	expect(array_column($layout['partitions'], 'name'))->toBe(['h2026111206', 'h2026111207', 'dMaxValue']);
});

it('treats a null row count as zero', function () {
	$rows = mt_layout_rows([['dMaxValue', 'MAXVALUE']]);
	$rows[0]['TABLE_ROWS'] = null;

	expect(mactrack_partition_layout($rows)['partitions'][0]['rows'])->toBe(0);
});

it('reports the first rule a layout breaks', function (array $rows, string $reason) {
	$layout = mactrack_partition_layout($rows);

	expect($layout['state'])->toBe('invalid');
	expect($layout['reason'])->toContain($reason);
})->with([
	'another method'         => [mt_layout_rows([['p0', '10']], 'device_id', 'LIST'), 'method'],
	'another expression'     => [mt_layout_rows([['dMaxValue', 'MAXVALUE']], 'device_id'), 'expression'],
	'no dMaxValue'           => [mt_layout_rows([['d20261112', '1794528000']]), 'dMaxValue'],
	'dMaxValue not last'     => [mt_layout_rows([['dMaxValue', 'MAXVALUE'], ['d20261112', '1794528000']]), 'dMaxValue'],
	'bounds not increasing'  => [mt_layout_rows([['d20261113', '1794614400'], ['d20261112', '1794528000'], ['dMaxValue', 'MAXVALUE']]), 'increase'],
	'equal bounds'           => [mt_layout_rows([['d20261112', '1794528000'], ['d20261113', '1794528000'], ['dMaxValue', 'MAXVALUE']]), 'increase'],
	'non-numeric bound'      => [mt_layout_rows([['d20261112', 'abc'], ['dMaxValue', 'MAXVALUE']]), 'bound'],
	'invalid name'           => [mt_layout_rows([['p_old', '1794528000'], ['dMaxValue', 'MAXVALUE']]), 'name'],
	'MAXVALUE not dMaxValue' => [mt_layout_rows([['d20261112', '1794528000'], ['pmax', 'MAXVALUE']]), 'dMaxValue'],
]);
