# KSMChart-php
K-Shoot MANIA chart (.ksh) parser written in PHP.

# How to use

```PHP
require_once 'ksm_chart.php';

// Load your chart as string
$str = file_get_contents('example_ex.ksh');

// Create KSMChart object
$chart = new KSMChart($str);

// You can get option values from $chart->options array
var_dump($chart->options['title']);
var_dump($chart->options['artist']);
var_dump($chart->options['level']);
var_dump($chart->options['difficulty']);

// You can also get the number of combos by note types (bt/fx/laser)
var_dump($chart->bt_combo);
var_dump($chart->fx_combo);
var_dump($chart->laser_combo);

// or $chart->total_combo for the sum of all types
var_dump($chart->total_combo);

```
