<?php

require('midi_class_v178/classes/midi.class.php');
require('PreciseBaseConvert/PreciseBaseConverter.php');

use FourthPlanetDev\MathUtils;

class midimaker extends module {

	protected $midi;

	protected $math_constants = array();

	protected static $scale_steps = array(
		'major' => array(1,1,.5,1,1,1,.5),
		'natural minor' => array(1,.5,1,1,.5,1,1),
		'harmonic minor' => array(1,.5,1,1,.5,1.5,.5),
		'melodic minor' => array(1,.5,1,1,1,1,.5),
		'chromatic' =>array(.5,.5,.5,.5,.5,.5,.5,.5,.5,.5,.5,.5),
		'pentatonic' => array(1,1,1.5,1,1.5)
	);

	protected $note_idx = array();
	public $last_file_made = "";

	public function __construct() {
		$this->midi = new Midi();
		$this->get_math_constants();
		$this->note_idx = array_flip($this->midi->getNoteList());
	}

	protected function get_math_constants() {
		$files =array_diff(scandir(__DIR__.'/math_constants'),array('.','..'));
		foreach($files as $filename) {
			$const_name = strstr($filename, '.', true);
			$const_val = file_get_contents(__DIR__ . '/math_constants/' . $filename);
			$this->math_constants[$const_name] = $const_val;
		}
	}

	public function make_scales($start) {
		if (!is_numeric($start)) {
			$start = $this->note_idx[$start];
		}

		$this->midi->open();

		$this->midi->setBpm(60);

		$ch = 1;
		$ts=240;

		$track_number = $this->midi->newTrack()-1;
		$this->midi->addMsg($track_number, "0 PrCh ch=$ch p=1");
		foreach(self::$scale_steps as $steps) {
			$note = $start;
			foreach($steps as $increment) {
				$this->midi->addMsg($track_number, "0 On ch=$ch n=$note v=127",1);
#				$ts+=240;
				$this->midi->addMsg($track_number, "$ts Off ch=$ch n=$note v=127",1);
				$note += ($increment*2);
			}
			$this->midi->addMsg($track_number, "0 On ch=$ch n=$note v=127",1);
			#$ts+=240;
			$this->midi->addMsg($track_number, "$ts Off ch=$ch n=$note v=127",1);
		}

		$file = __DIR__ . '/files/scales'.$start.'.midi';
		$this->midi->saveMidFile($file);
		$this->last_file_made = $file;
	}

	public function constantMidi($options) {

		$allowed_notes = isset($options['allowed_notes']) ? $options['allowed_notes'] : array($this->note_idx['C4']);
		$constant = !empty($options['constant']) ? $options['constant'] : 'pi';
		$durations = isset($options['durations']) ? $options['durations'] : array(.25);
		$rhythm = !empty($options['rhythm_constant']) ? $options['rhythm_constant'] : 'pi';

		$file = __DIR__ . "/files/constant-$constant-".implode('-',$allowed_notes).'-'.(new MathUtils\PreciseBaseConvert(array_sum($durations)))->toBase(2).'-'.$rhythm.".midi";
		$num_allowed_notes = count($allowed_notes);
		$this->midi->open(4);	// 4 ticks to a quarter note
		$this->midi->setBpm($options['bpm']);
		$ch = 1;
		$ts = 2;	// length in ticks (4 = quarter note)
		$track_number = $this->midi->newTrack()-1;
		$this->midi->addMsg($track_number, "0 PrCh ch=$ch p=1");

		// convert our math constants to a more malleable format...
		$constant = new MathUtils\PreciseBaseConvert($this->math_constants[$constant]);
		$rhythm = new MathUtils\PreciseBaseConvert($this->math_constants[$rhythm]);

		$constant->scale = $rhythm->scale = $options['num_notes'];

		$base = count($allowed_notes);
		if ($base > 1) {
			$pattern = str_replace('.','',$constant->toBase($base));
			$pattern_length = strlen($pattern);
		} else {
			$base = 10;
			$pattern_length = $constant->scale;
			$pattern = str_repeat(0, $constant->scale);
		}


		$rhythm_base = count($durations);
		if ($rhythm_base > 1) {
			$rhythm_pattern = str_replace('.','',$rhythm->toBase($rhythm_base));
		} else {
			$rhythm_base = 10;
			$rhythm_pattern = str_repeat(0, $pattern_length);
		}

		$noteCode = new MathUtils\PreciseBaseConvert(0);
		$noteCode->scale = 0;


		for($idx = 0; $idx < $pattern_length; $idx++) {
			$noteCode->debug = true;
			$noteCode->setNumber($pattern[$idx],$base);
			$note = $allowed_notes[$noteCode->toBase(10)];

			$noteCode->setNumber($rhythm_pattern[$idx],$rhythm_base);
			$note_duration = 16 * $durations[$noteCode->toBase(10)];

			$this->midi->addMsg($track_number, "0 On ch=$ch n=$note v=127",1);
			$this->midi->addMsg($track_number, "$note_duration Off ch=$ch n=$note v=127",1);
		}

		$this->midi->saveMidFile($file);
		$this->last_file_made = $file;
	}

	public function fibonacci($allowed_notes,$num_notes,$f1=1,$f2=1) {
		if ($f1 > $f2) {
			$f3 = $f1;
			$f1 = $f2;
			$f2 = $f3;
		}
		$fibStack = array($f1,$f2);
		while (count($fibStack) < $num_notes) {
			$fibStack[] = $f3 = bcadd($f1,$f2,0);
			$f1 = $f2;
			$f2 = $f3;
		}

		$num_allowed_notes = count($allowed_notes);

		$this->midi->open(4);	// 4 ticks per beat
		$this->midi->setBpm(60);	// 60 beats per minute

		$ch = 1;
		$ts=2;		// length in ticks
		$track_number = $this->midi->newTrack()-1;
		$this->midi->addMsg($track_number, "0 PrCh ch=$ch p=21");
		while ($fib = array_shift($fibStack)) {
			$note = $allowed_notes[$fib==0?$fib:bcmod($fib,$num_allowed_notes)];
			if (!isset($last_note) || $note == $last_note)
				$len = 2;
			else if ($note < $last_note)
				$len = 3;
			else
				$len = 1;

			$this->midi->addMsg($track_number, "0 On ch=$ch n=$note v=127",1);
			$this->midi->addMsg($track_number, "$len Off ch=$ch n=$note v=127",1);


			$num_notes--;
			$last_note = $note;
		}

		$file = __DIR__ . '/files/fibonacci.midi';
		$this->midi->saveMidFile($file);
		$this->last_file_made = $file;
	}

	public function get_note_list() {return array_flip($this->note_idx);}


	public static function install() {}
	public static function required_tables() { return array();}
	public static function required_rights() {
		return array(
			'MidiMaker' => array(
				'MidiMaker' => array(
					'Use MidiMaker' => array(
						'description' => 'Allows user to use the midi maker - for good or for awesome.',
						'default_groups' => array('Admin')
					)
				)
			)
		);
	}

	public static function post($args,$request) {
		// all we really care about are the allowed_notes and math constant...
		$_SESSION[__CLASS__]['midi_data'] = utilities::make_html_safe($request);
	}

	// given key and scale, determine all allowed notes from C0 to G10
	public function get_allowed_notes($key,$scale) {
		$midimaker = new midimaker();
		$allowed_notes = array();
		$note = $this->note_idx[str_replace('#','s',$key)."0"];
		$allowed_notes[$note] = $key."0";
		$all_notes = $this->get_note_list();
		while ($note < $this->note_idx['G10']) {
			$step = current(self::$scale_steps[$scale]);
			$note += ($step*2);
			if ($note < $this->note_idx['G10'])
			{
				$allowed_notes[$note] = str_replace('s','#',$all_notes[$note]);
				$step = next(self::$scale_steps[$scale]);
				if (!$step) reset(self::$scale_steps[$scale]);
			}
		}
		// still need to map numbers to Notes (e.g., 0=C0, 12=C1, etc.)
		return $allowed_notes;
	}

	public static function ajax($args,$request) {
		$midimaker = new midimaker();
		if (empty($args)) {
			return $midimaker->get_allowed_notes($request['key'], $request['scale']);
		}
	}

	public static function menu() {return array();}



	public static function view() {
		global $local;
		$output = array(
			'html' => '<h2>Math Midi Maker</h2>',
			'script' => array(
				"{$local}script/jquery.min.js",
				utilities::get_public_location(__DIR__ . '/scripts/midimaker.js' )
			),
			'css' => 'label {display: block;}'
		);

		$maker = new midimaker();

		if (isset($_SESSION[__CLASS__]['midi_data'])) {
			$maker->constantMidi($_SESSION[__CLASS__]['midi_data']);
			$selected_key = $_SESSION[__CLASS__]['midi_data']['key'];
			$selected_scale = $_SESSION[__CLASS__]['midi_data']['scale'];
			$allowed_notes = isset($_SESSION[__CLASS__]['midi_data']['allowed_notes']) ? $_SESSION[__CLASS__]['midi_data']['allowed_notes'] : array();
			$constant = $_SESSION[__CLASS__]['midi_data']['constant'];
			$bpm = $_SESSION[__CLASS__]['midi_data']['bpm'];
			$durations = isset($_SESSION[__CLASS__]['midi_data']['durations']) ? $_SESSION[__CLASS__]['midi_data']['durations'] : array();
			$rhythm = $_SESSION[__CLASS__]['midi_data']['rhythm_constant'];
			$num_notes = $_SESSION[__CLASS__]['midi_data']['num_notes'];
		} else {
			$selected_key = $selected_scale = $constant = $rhythm = "";
			$allowed_notes = $durations = array();
			$bpm = 60;
			$num_notes = 100;
		}


		$keys = array('A','A#','B','C','C#','D','D#','E','F','F#','G','G#');
		$scales = array_keys($maker::$scale_steps);

		$key_options = $scale_options = $constant_options = '<option value="">Select...</option>';
		$midi_options = "";

		foreach($keys as $key) {
			$selected = $key == $selected_key ? 'selected="selected"' : '';
			$key_options .= <<<OPTION
	<option value="$key" $selected>$key</option>
OPTION;
		}

		foreach($scales as $scale) {
			$selected = $scale == $selected_scale ? 'selected="selected"' : '';
			$scale_options .= <<<OPTION
	<option value="$scale" $selected>$scale</option>
OPTION;
		}

		foreach($maker->math_constants as $name => $value) {
			$value = substr($value,0,5) . '...';
			$selected = $name == $constant ? 'selected="selected"' : '';
			$constant_options .= <<<OPTION
	<option value="$name" $selected>$name ($value)</option>
OPTION;
		}


		if (!empty($selected_key) && !empty($selected_scale)) {
			$midi_notes_display = '';
			$all_notes = $maker->get_allowed_notes($selected_key,$selected_scale);
			foreach($all_notes as $idx => $note) {
				$selected = in_array($idx,$allowed_notes) ? 'selected="selected"' : '';
				$midi_options .= <<<OPTION
	<option value="$idx" $selected>$note</option>
OPTION;
			}
		} else {
			$midi_notes_display = 'display:none;';
		}

		$duration_options = "";
		$num_durations = 0;
		for($i=4;$i>=0;$i--) {
			$fraction = pow(2,$i);

			switch($i) {
				case 0:
					$note = "Whole";
					break;
				case 1:
					$note = "Half";
					break;
				default:
					$note = "1/" . $fraction;
			}
			$fraction_decimal = 1/$fraction;
			$selected = in_array($fraction_decimal,$durations) ? 'selected="selected"' : '';
			$duration_options .= <<<OPTION
	<option value="$fraction_decimal" $selected>$note</option>
OPTION;
			$num_durations++;
		}

		$output['html'] .= <<<HTML
	<p>Make music with Math! Using the form below:</p>
	<form id="midi_form" method="post">
		<label>Key: <select name="key" id="midi_key">$key_options</select></label>
		<label>Scale: <select name="scale" id="midi_scale">$scale_options</select></label>
		<label>BPM: <input name="bpm" value="$bpm" /></label>
		<label>Rhythm Constant: <select name="rhythm_constant">$constant_options</select></label>
		<label># Notes: <input name="num_notes" value="$num_notes" /></label>

		<label id="midi_notes_label" style="$midi_notes_display">Select Notes to include:<br /><select name="allowed_notes[]" id="midi_allowed_notes" multiple="multiple" size=20>$midi_options</select></label>
		<label>Math Constant: <select name="constant" id="midi_math_constant">$constant_options</select></label>
		<label>Note Durations: <select name="durations[]" multiple="multiple" size="$num_durations">$duration_options</select></label>

				<input type="submit" value="Make MIDI" />
	</form>
HTML;

		if (!empty($maker->last_file_made)) {
			$output['html'] .= '<embed src="'.utilities::get_public_location($maker->last_file_made).'" />';
			unset($_SESSION[__CLASS__]);
		}

		return $output;
	}
}

