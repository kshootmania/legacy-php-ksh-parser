<?php

class KSMChart {

    // 配列の添字
    const POS = 0;
    const VALUE = 1;
    const LANE = 1;
    const NUMERATOR = 1;
    const LENGTH = 2;
    const DENOMINATOR = 2;
    const LASER_POS_START = 3;
    const LASER_POS_END = 4;

    // 1小節あたりのpos値
    const POS_UNIT = 10000;

    // コンボ数半減の境界BPM
    const TEMPO_HALF_COMBO = 260;

    // ノーツ
    public $notes;
    public $lasers;

    // テンポ/拍子変更
    public $tempos;
    public $time_signatures;

    // 設定値
    public $options;
    
    // コンボ数
    public $bt_combo;
    public $fx_combo;
    public $laser_combo;
    public $total_combo;

    /**
     * コンストラクタ (kshファイルの内容をパース)
     *
     * @param string kshファイルの内容
     */
    function __construct($ksh) {

        // BOMがあれば除去, なければ全体をShift-JISからUTF-8へ変換
        $bom = chr(0xEF).chr(0xBB).chr(0xBF);
        if(substr($ksh, 0, 3) === $bom){
            $ksh = substr($ksh, 3);
        }else{
            $ksh = mb_convert_encoding($ksh, 'UTF-8', 'SJIS');
        }

        // 改行コードをLFへ
        $ksh = preg_replace("/\r\n|\r/", "\n", $ksh);

        // レーザーの横位置変換用テーブル
        $laser_pos_table = array_flip(str_split('0123456789ABCDEFGHIJKLMNOPQRSTUVWXYZabcdefghijklmno'));

        // 現在の小節開始地点でのpos値
        $measure_head_pos = 0;

        // 拍子
        $beat_n = 4;
        $beat_d = 4;

        // 小節毎に区切る
        $measure_strs = explode("\n--\n", $ksh);

        // 最終行のロングノーツを挿入するためにダミー小節を末尾へ挿入
        $measure_strs[] = '0000|00|--';

        // 各小節の分割行数を数える
        $line_counts = array();
        foreach($measure_strs as $measure_idx => $measure_str) {
            $chart_line_counts[$measure_idx] = 0;
            foreach(explode("\n", $measure_str) as $line) {
                if((substr($line, 0, 1) !== '#') && (substr($line, 0, 2) !== '//') && (substr($line, 4, 1) === '|') && (strpos('=', $line) === false)) {
                    $chart_line_counts[$measure_idx]++;
                }
            }
            if($chart_line_counts[$measure_idx] === 0){
                $chart_line_counts[$measure_idx] = 1;
            }
        }

        // ノーツの列挙
        $prepared_notes = array();
        $lane_combos = array_fill(0, 8, 0);
        foreach($measure_strs as $measure_idx => $measure_str) {
            $chart_line_count = 0;

            foreach(explode("\n", $measure_str) as $line) {
                // 行ごとのpos値
                $line_pos = $measure_head_pos + intval(intval(self::POS_UNIT * $chart_line_count * $beat_n / $beat_d) / $chart_line_counts[$measure_idx]);

                $parts = explode('=', $line, 2);
                if(count($parts) === 2){
                    // 初回登場の設定値を保管
                    if(!isset($this->options[$parts[0]])){
                        $this->options[$parts[0]] = $parts[1];
                    }

                    // 設定行の取得
                    switch($parts[0]) {
                        case 't':
                            if(strpos('-', $parts[1]) === false){
                                $tempo = floatval($parts[1]);
                                $this->tempos[] = array(
                                    self::POS => $line_pos,
                                    self::VALUE => $tempo,
                                );
                            }
                            break;
                        case 'beat':
                            $values = explode('/', $parts[1]);
                            if(count($values) === 2){
                                $beat_n = $values[0];
                                $beat_d = $values[1];
                                $this->time_signatures[] = array(
                                    self::POS => $line_pos,
                                    self::NUMERATOR => $beat_n,
                                    self::DENOMINATOR => $beat_d,
                                );
                            }
                            break;
                    }
                }else if((substr($line, 0, 1) !== '#') && (substr($line, 0, 2) !== '//') && (substr($line, 7, 1) === '|')){
                    $chars = str_split($line);

                    // BT/FXレーン
                    for($i = 0; $i < 6; $i++) {
                        if($i < 4){
                            // BTレーン
                            $c = $chars[$i];
                            $chip_char = '1';
                        }else{
                            // FXレーン
                            $c = $chars[$i + 1];
                            $chip_char = '2';
                        }

                        if($c === '0'){
                            // ノーツなし
                            if(isset($prepared_notes[$i])){
                                $prepared_notes[$i][self::LENGTH] = intval($prepared_notes[$i][self::LENGTH]);
                                $this->notes[] = $prepared_notes[$i];

                                // ノーツ始点のテンポでの判定間隔を決定
                                if($prepared_notes_tempo[$i] >= self::TEMPO_HALF_COMBO){
                                    $min_length = intval(self::POS_UNIT * 3 / 8);
                                    $interval = intval(self::POS_UNIT / 8);
                                }else{
                                    $min_length = intval(self::POS_UNIT * 3 / 16);
                                    $interval = intval(self::POS_UNIT / 16);
                                }

                                // コンボ数の計算
                                if($prepared_notes[$i][self::LENGTH] <= $min_length){
                                    $lane_combos[$i]++;
                                }else{
                                    $start = (intval(($prepared_notes[$i][self::POS] + $interval - 1) / $interval) + 1) * $interval;
                                    $end = $prepared_notes[$i][self::POS] + $prepared_notes[$i][self::LENGTH] - $interval;
                                    $lane_combos[$i] += intval(($end - $start + $interval - 1) / $interval);
                                }

                                $prepared_notes[$i] = null;
                                $prepared_notes_tempo[$i] = null;
                            }
                        }else if($c === $chip_char){
                            // チップノーツ
                            $this->notes[] = array(
                                self::POS => $line_pos,
                                self::LANE => $i,
                                self::LENGTH => 0,
                            );
                            $lane_combos[$i]++;
                        }else{
                            // ロングノーツ
                            if(isset($prepared_notes[$i])){
                                // ロングノーツの長さを伸ばす(float)
                                $prepared_notes[$i][self::LENGTH] += self::POS_UNIT * $beat_n / $beat_d / $chart_line_counts[$measure_idx];
                            }else{
                                // ロングノーツを新しく準備
                                $prepared_notes[$i] = array(
                                    self::POS => $line_pos,
                                    self::LANE => $i,
                                    self::LENGTH => self::POS_UNIT * $beat_n / $beat_d / $chart_line_counts[$measure_idx] /* 準備段階では長さはfloat */,
                                );
                                $prepared_notes_tempo[$i] = $tempo;
                            }
                        }
                    }

                    // レーザーレーン
                    for($i = 0; $i < 2; $i++) {
                        $c = $chars[$i + 8];

                        if($c === ':'){
                            // レーザーの長さを伸ばす(float)
                            if(isset($prepared_lasers[$i])){
                                $prepared_lasers[$i][self::LENGTH] += self::POS_UNIT * $beat_n / $beat_d / $chart_line_counts[$measure_idx];
                            }
                        }else if(isset($laser_pos_table[$c])){
                            // 準備中のレーザーの挿入
                            if(isset($prepared_lasers[$i])){
                                $prepared_lasers[$i][self::LENGTH] = intval($prepared_lasers[$i][self::LENGTH]);
                                $prepared_lasers[$i][self::LASER_POS_END] = $laser_pos_table[$c];
                                $this->lasers[] = $prepared_lasers[$i];

                                // ノーツ始点のテンポでの判定間隔を決定
                                if($prepared_lasers_tempo[$i] >= self::TEMPO_HALF_COMBO){
                                    $interval = intval(self::POS_UNIT / 8);
                                }else{
                                    $interval = intval(self::POS_UNIT / 16);
                                }

                                // コンボ数の計算
                                if($prepared_lasers[$i][self::LENGTH] <= intval(self::POS_UNIT / 32) && ($prepared_lasers[$i][self::LASER_POS_START] !== $prepared_lasers[$i][self::LASER_POS_END])){
                                    $lane_combos[$i + 6]++;  // 直角レーザー
                                }else{
                                    $start = intval(($prepared_lasers[$i][self::POS] + $interval - 1) / $interval) * $interval;
                                    $end = $prepared_lasers[$i][self::POS] + $prepared_lasers[$i][self::LENGTH];
                                    $lane_combos[$i + 6] += intval(($end - $start + $interval - 1) / $interval);
                                }
                            }

                            // レーザーを新しく準備
                            $prepared_lasers[$i] = array(
                                self::POS => $line_pos,
                                self::LANE => $i,
                                self::LENGTH => self::POS_UNIT * $beat_n / $beat_d / $chart_line_counts[$measure_idx] /* 準備段階では長さはfloat */,
                                self::LASER_POS_START => $laser_pos_table[$c],
                                self::LASER_POS_END => null,
                            );
                            $prepared_lasers_tempo[$i] = $tempo;
                        }else{
                            $prepared_lasers[$i] = null;
                            $prepared_lasers_tempo[$i] = null;
                        }
                    }
                    $chart_line_count++;
                }
            }

            // 次の小節のpos値
            $measure_head_pos += intval(self::POS_UNIT * $beat_n / $beat_d);
        }

        // コンボ数を計算
        $this->bt_combo = $lane_combos[0] + $lane_combos[1] + $lane_combos[2] + $lane_combos[3];
        $this->fx_combo = $lane_combos[4] + $lane_combos[5];
        $this->laser_combo = $lane_combos[6] + $lane_combos[7];
        $this->total_combo = $this->bt_combo + $this->fx_combo + $this->laser_combo;
    }

}
