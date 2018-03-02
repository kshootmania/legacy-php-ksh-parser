# KSMChart-php
K-Shoot MANIA chart (.ksh) parser written in PHP(>=5.6).

# How to use

```PHP
require_once 'ksm_chart.php';

// Load your chart as string
$str = file_get_contents('D:\kshoot\songs\[OiivsU] over199 2ndstyle\1_boss1\prestogambit\EX.ksh');

// Create KSMChart object
$chart = new KSMChart($str);

// You can get option values from $chart->options array
echo "Title: {$chart->options['title']}\n";
echo "Artist: {$chart->options['artist']}\n";
echo "Level: {$chart->options['level']}\n";
echo "Difficulty: {$chart->options['difficulty']}\n";

// You can also get the number of combos by note types (bt/fx/laser)
echo "BT Combo: $chart->bt_combo\n";
echo "FX Combo: $chart->fx_combo\n";
echo "Laser Combo: $chart->laser_combo\n";

// or $chart->total_combo for the sum of all types
echo "Total Combo: $chart->total_combo\n";

// Use $chart->notes for BT/FX or $chart->lasers for Laser to access information for each note
echo "BT/FX Notes:\n";
foreach($chart->notes as $note) {
  echo "Time Position: ${note[KSMChart::POS]}\n";
  echo "Lane No.: ${note[KSMChart::LANE]}\n";
  echo "Length: ${note[KSMChart::LENGTH]}\n";
  echo "--------\n";
}
echo "\n";  

echo "Lasers:\n";
foreach($chart->lasers as $laser) {
  echo "Time Position: ${laser[KSMChart::POS]}\n";
  echo "Lane No.: ${laser[KSMChart::LANE]}\n";
  echo "Length: ${laser[KSMChart::LENGTH]}\n";
  echo "X Position: ${laser[KSMChart::LASER_POS_START]} -> ${laser[KSMChart::LASER_POS_END]}\n";
  echo "--------\n";
}

```
