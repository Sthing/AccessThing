<?php

if ($argc < 2) {
	echo "Usage: {$argv[0]} <filename> [track-no] [threshold] [p_min] [p_max]\n";
	die();
}
$file = $argv[1];
$track = isset($argv[2]) ? intval($argv[2]) : 0;
$threshold = isset($argv[3]) ? intval($argv[3]) : 0;
$p_min = isset($argv[4]) ? intval($argv[4]) : 0;
$p_max = isset($argv[5]) ? intval($argv[5]) : 0;

if ( ! is_readable($file)) {
	echo "Cannot read $file\n";
	die();
}

// Read all tracks into an array
$content = file_get_contents($file);
$lines = explode("\n", $content);
$values = array();
$i = 0;
foreach ($lines as $line) {
	$i++;
	$fields = explode(',', $line);
	if (count($fields) != 5) {
		echo "Skipping line $i\n";
		continue;
	}
	$values[1][] = intval($fields[0]);
	$values[2][] = intval($fields[1]);
	$values[3][] = intval($fields[2]);
	$values[4][] = intval($fields[3]);
}
$count = count($values[1]);
echo "Loaded $count rows\n";

// Find extremes:
echo "Extremes:\n";
$min = $max = array();
foreach ($values as $trk => $trk_values) {
	$min[$trk] = min($trk_values);
	$max[$trk] = max($trk_values);
	echo "  Track $trk: {$min[$trk]} - {$max[$trk]}\n";
}

if ($track < 1 OR $track > 4) {
	echo "Specify track-no (1-4) to continue.\n";
	die();
}
if ($threshold < 1) {
	echo "Specify threshold to continue.\n";
	die();
}

// Find times between transitions
$bits = array();
$periods = array();
$t_last=0; // Time of last transition
$last_bit = 0;
foreach ($values[$track] as $t => $value) {
	$bit = $value >= $threshold ? 1 : 0;
	$bits[] = $bit;
	// Detect transisition and record time since last transition
	if ($bit != $last_bit) {
		if ($t > 0) { // Ignore transition on first sample
			$period = $t - $t_last;
			$periods[$period] = isset($periods[$period]) ? $periods[$period] + 1 : 1;
			$t_last = $t;
		}
		$last_bit = $bit;
	}
}
echo implode($bits), "\n";
echo "Distribution of times between transisitions:\n";
ksort($periods);
print_r($periods);
if ($p_min <= 0 || $p_max <= 0) {
	echo "Specify minimum and maximum periods to include in calculation of average.\n";
	die();
}
$sum = $n = 0;
foreach ($periods as $period => $cnt) {
	if ($period > $p_max) {
		break;
	}
	if ($period >= $p_min) {
		$sum += $period * $cnt;
		$n += $cnt;
	}
}
if ($n == 0) {
	echo "No data found in given interval.\n";
	die();
}
$t_period = $sum / $n;
echo "Calculated average period: $t_period\n";

// Find first transition
// echo implode($bits); echo "\n";
$last_bit = $bits[0];
foreach ($bits as $t => $bit) {
	if ($bit != $last_bit) {
		break;
	}
	//echo " ";
}
	$t_start = $t_next = $t;
if (0) {
	while ($t <= $count) {
		if ($t >= $t_next) {
			$t_next += $t_period;
			echo '|';
		}
		else {
			echo ' ';
		}
		$t++;
	}
	echo "\n";
}
// Select the value in the middle of each period.
$samples = array();
$t = $t_start - $t_period / 2;
$last_t = -1;
while ($t < $count) {
	$t_int = intval(round($t));
	if ($t_int >= 0 && $t_int < $count) {
		$samples[] = $bits[$t_int];
		//echo str_repeat(' ', $t_int - $last_t - 1);
		//echo $bits[$t_int];
		$last_t = $t_int;
	}
	$t += $t_period;
}
//echo "\n";
$no_of_samples = count($samples);
echo implode($samples), "\n";

// Look for manchester encoding. There must be on or two transitions for each clock period. 
echo "Manchester encodings:\n";
$manchester = array('normal' => array(), 'biphase' => array());
for ($offset = 0; $offset <= 1; $offset++) {
	$manchester['normal'][$offset] = array();
	$manchester['biphase'][$offset] = array();
	echo "Offset $offset:\n";
	for ($n = $offset; $n < $no_of_samples; $n += 2) {
		if ( ! array_key_exists($n+1, $samples)) {
			break;
		}
		if ($samples[$n] == $samples[$n+1]) {
			echo "Missing transistion at n=$n\n";
			break;
		}
		$manchester['normal'][$offset][] = $samples[$n+1] ? 0 : 1; // Invert
		if ( ! array_key_exists($n+2, $samples)) {
			break;
		}
		$manchester['biphase'][$offset][] = $samples[$n+1] == $samples[$n+2] ? 0 : 1; // Invert
	}
}

// Look for EM4100 64bit manchester data
foreach ($manchester as $mode => $offset_bits) {
	foreach ($offset_bits as $offset => $bits) {
		$bit_string = implode($bits);
		printf("%-7s %d: %s\n", $mode, $offset, $bit_string);
		$manufacturer = $data = $parities_for_data = 0;
		// Header: 9 1-bits
		if (($start = strpos($bit_string, '111111111')) === false) {
			continue;
		}
		echo str_repeat(' ', 11 + $start), '<-START->';
		// Bit 64: Stopbit, 0
		$stop = $start + 63;
		if ( ! isset($bits[$stop])) {
			echo "Not enough bits to find stop-bit\n";
			continue;
		}
		if ($bits[$stop] != 0) {
			echo "The 64th bit is not 0 --------------------------------^\n";
			continue;
		}
		// Next: 2+8 rows of 4 bits and an even parity bit
		// The first 2 rows are the customer id (or version number/manufacturer code), the last 8 rows the data.
		for ($row = 0; $row < 10; $row++) {
			$n = $start + 9 + $row * 5;
			$digit = ($bits[$n] << 3) + ($bits[$n+1] << 2) + ($bits[$n+2] << 1) + $bits[$n+3];
			printf("   %X", $digit);
			$parity = ($bits[$n] + $bits[$n+1] + $bits[$n+2] + $bits[$n+3]) % 2;
			if ($parity != $bits[$n+4]) {
				echo "^Wrong parity\n";
				continue 2;
			} 
			echo " ";
			if ($row <= 1) {
				$manufacturer += $digit << (4 * (1 - $row));
			}
			else {
				$data += $digit << (4 * (9 - $row));
				$parities_for_data += $parity << (9 - $row);
			}
		}
		// The 4 column parity bits
		$n = $start + 9 + (10 * 5);
		for ($column = 0; $column < 4; $column++) {
			$bit_sum = 0;
			for ($row = 0; $row < 10; $row++) {
				$bit_sum += $bits[$start + 9 + $row * 5 + $column];
			}
			$parity = $bit_sum % 2;
			if ($parity != $bits[$n + $column]) {
				echo "^Wrong parity\n";
				continue 2;
			} 
			echo " ";
		}
		echo "^Stop\n";
		printf("JSXLXL-format: %02X %010d\n", $manufacturer, $data);
		
	}
}

