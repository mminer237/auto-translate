#!/usr/bin/env php

<?php
	require_once __DIR__.'/vendor/autoload.php';

	use Google\Cloud\Translate\V2\TranslateClient;
	use Symfony\Component\Yaml\Yaml;

	chdir(dirname(__FILE__));

	$api_keys_file = 'api_keys.yaml';
	if (file_exists($api_keys_file))
		$api_keys = Yaml::parseFile($api_keys_file);
	else {
		exit("No API keys file found\n");
	}

	$options = getopt('hi:l:o:rt:');
	if (isset($options['h']))
		exit(file_get_contents('help.txt'));
	if (isset($options['i']))
		$input_path = $options['i'];
	if (isset($options['l']))
		$targets = $options['l'];
	if (isset($options['o']))
		$output_dir = $options['o'];
	$recursive = isset($options['r']);
	if (isset($options['t']))
		$output_type = $options['t'];

	if (!isset($input_path) || !isset($output_dir) || !isset($targets))
		if (count($argv) >= 4) {
			$input_path = $argv[1];
			$output_dir = $argv[2];
			$targets = $argv[3];
			$recursive = true;
		}
		else
			exit("Input, output, and languages not all set\n");

	$targets = explode(',', $targets);
	foreach ($targets as &$target)
		$target = trim($target);
	
	if ($api_keys['googleTranslate'])
		$googleTranslate = new TranslateClient(['key' => $api_keys['googleTranslate']]);
	
	const DEEPL_LIMIT = 500000;
	const GOOGLE_TRANSLATE_LIMIT = 500000;
	const OLD_CHAR_COUNTS_FILE = 'api_usage.yaml';
	$remaining_chars = [
		'deepl' => DEEPL_LIMIT,
		'googleTranslate' => GOOGLE_TRANSLATE_LIMIT
	];
	$today = date('Y-m-d');
	if (file_exists(OLD_CHAR_COUNTS_FILE)) {
		$old_char_counts = Yaml::parseFile(OLD_CHAR_COUNTS_FILE);
		$first_of_month = date('Y-m-d', strtotime('first day of this month'));
		foreach ($old_char_counts as $service => $counts) {
			foreach ($counts as $date => $count) {
				if ($date < $first_of_month) {
					unset($counts[$date]);
				}
				else {
					$remaining_chars[$service] -= $count;
				}
			}
		}
		$char_counts = $old_char_counts;
		if (!isset($char_counts['deepl'][$today]))
			$char_counts['deepl'][$today] = 0;
		if (!isset($char_counts['googleTranslate'][$today]))
			$char_counts['googleTranslate'][$today] = 0;
	}
	else
		$char_counts = [
			'deepl' => [$today => 0],
			'googleTranslate' => [$today => 0]
		];

	function get_type(string $file_path): string {
		if (strlen($file_path) > 5) {
			if (substr($file_path, -5) === '.json') {
				return 'json';
			}
			elseif (
				substr($file_path, -5) === '.yaml' ||
				substr($file_path, -4) === '.yml'
			) {
				return 'yaml';
			}
			elseif (
				substr($file_path, -3) === '.db' ||
				substr($file_path, -7) === '.sqlite' ||
				substr($file_path, -8) === '.sqlite3'
			) {
				exit("Databases are not supported\n");
			}
			else {
				return 'other';
			}
		}
	}

	function process_dir(string $dir): void {

		foreach (scandir($dir) as $file_path) {
			if ($file_path !== '.' && $file_path !== '..') {
				if (is_dir($dir.'/'.$file_path)) {
					process_dir($dir.'/'.$file_path, $file_path);
				}
				else {
					process_file($dir.'/'.$file_path, $file_path);
				}
			}
		}
	}

	function process_file(string $input_path, string $output_file_name): void {
		global $output_dir;
		global $output_type;
		global $targets;

		echo "Processing $input_path...\n";

		$input_type = get_type($input_path);
		$output_type = $output_type ?: $input_type;
			
		switch ($input_type) {
			case 'json':
				$input = json_decode(file_get_contents($input_path), true);
				break;
			case 'yaml':
				$input = Yaml::parseFile($input_path);
				break;
			case 'txt':
			default:
				$input = [file_get_contents($input_path)];
				break;
		}

		$output_path_ext = pathinfo($output_file_name, PATHINFO_EXTENSION);
		if (
			$output_path_ext !== $output_type ||
			($output_path_ext !== 'yml' && $output_type === 'yaml') ||
			$output_type === 'other'
		) {
			$output_file_name = preg_replace(
				'/\.[^\/.]+$/',
				$output_type === 'other' ? '' : '.'.$output_type,
				$output_file_name
			);
		}

		foreach ($targets as $target) {
			$output = $input;
			array_walk_recursive($output, function(&$value, $key) use ($target) {
				global $api_keys;
				global $char_counts;
				global $googleTranslate;
				global $remaining_chars;
				global $today;

				if (is_string($value)) {
					$value_length = mb_strlen($value);
					
					echo "Sending: $value\n";
					
					if ($api_keys['deepl'] ) {
						if ($remaining_chars['deepl'] >= $value_length) {
							$curl_request = curl_init('https://api-free.deepl.com/v2/translate');
							curl_setopt($curl_request, CURLOPT_POST, true);
							curl_setopt($curl_request, CURLOPT_RETURNTRANSFER, true);
							curl_setopt($curl_request, CURLOPT_POSTFIELDS, http_build_query([
								'auth_key' => $api_keys['deepl'],
								'text' => $value,
								'target_lang' => $target
							]));
							curl_setopt($curl_request, CURLOPT_HTTPHEADER, [
								'Content-Type' => 'application/x-www-form-urlencoded'
							]);
							$response = curl_exec($curl_request);
							if($response === false) {
								exit(curl_error($curl_request)."\n");
							}
						}
						else
							echo 'DeepL quota reached';
					}

					if (isset($response) && $response)
						$response = json_decode($response, true);
					if (isset($response) && isset($response['translations'])) {
						echo "Using DeepL...\n";
						$char_counts['deepl'][$today] += $value_length;
						$remaining_chars['deepl'] -= $value_length;
						$value = $response['translations'][0]['text'];
					}
					elseif ($googleTranslate) {
						if ($remaining_chars['googleTranslate'] >= $value_length) {
							echo "Using Google Translate...\n";
							$char_counts['googleTranslate'][$today] += $value_length;
							$remaining_chars['googleTranslate'] -= $value_length;
							$value = $googleTranslate->translate($value, ['target' => $target])['text'];
						}
						else {
							echo "Google Translate quota reached\n";
							exit("No translation available\n");
						}
					}
					else
						exit("No translation available\n");
					echo "Response: $value\n";
				}
			});

			$output_path = "$output_dir/$target/$output_file_name";
			if (!is_dir("$output_dir/$target"))
				mkdir("$output_dir/$target", 0777, true);
			echo "Putting results in $output_dir/$target/$output_file_name...\n";
			switch ($output_type) {
				case 'yaml':
					file_put_contents($output_path, Yaml::dump($output));
					break;
				case 'json':
					file_put_contents($output_path, json_encode($output, JSON_UNESCAPED_UNICODE));
					break;
				case 'txt':
				default:
					file_put_contents($output_path, $output);
					break;
			}
		}
	}

	if (is_dir($input_path))
		process_dir($input_path);
	else
		process_file($input_path, pathinfo($input_path, PATHINFO_BASENAME));
	
	echo "Translating complete\n";
	echo "Characters remaining this month:\n";
	echo "\tDeepL:            {$remaining_chars['deepl']}\n";
	echo "\tGoogle Translate: {$remaining_chars['googleTranslate']}\n";

	file_put_contents(OLD_CHAR_COUNTS_FILE, Yaml::dump($char_counts));