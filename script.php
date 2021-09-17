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
		exit("No API keys file found");
	}

	$options = getopt('hi:o:rt:');
	if (isset($options['h']))
		exit(file_get_contents('help.txt'));
	$input_path = $options['i'];
	$output_dir = $options['o'];
	$recursive = isset($options['r']);
	$output_type = $options['t'];
	if ((!isset($input_path) || !isset($output_dir)) && count($argv) >= 4) {
		$input_path = $argv[2];
		$output_dir = $argv[3];
		$recursive = true;
	}
	else
		exit('Input and output not set');

	
	$googleTranslate = new TranslateClient(['key' => $api_keys['googleTranslate']]);

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
				exit("Databases are not supported");
			}
			else {
				return 'other';
			}
		}
	}

	function process_dir(string $dir): void {
		global $output_dir;
		global $targets;
		global $googleTranslate;

		foreach (scandir($dir) as $file_path) {
			if ($file_path !== '.' && $file_path !== '..') {
				if (is_dir($dir.'/'.$file_path)) {
					process_dir($dir.'/'.$file_path, $output_dir);
				}
				else {
					process_file($dir.'/'.$file_path, $output_dir);
				}
			}
		}
	}

	function process_file(string $input_path): void {
		global $output_dir;

		echo "Processing $input_path...\n";

		$input_type = get_type($input_path);
			
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

		$output = $input;
		array_walk_recursive($output, function(&$value, $key) {
			global $targets;
			global $api_keys;
			global $googleTranslate;

			if (is_string($value)) {
				foreach ($targets as $target) {
					echo "Sending: $value";
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
					echo "DeepL Response:\n";
					print_r($response);

					if ($response)
						$value = json_decode($response, true)['translations']['text'];
					else
						$value = $googleTranslate->translate($value, ['target' => $target])['text'];
					echo "Value: $value";
				}
			}
		});
	}

	if (is_dir($input_path))
		process_dir($input_path);
	else
		process_file($input_path);