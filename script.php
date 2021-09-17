#!/usr/bin/env php

<?php
	require_once __DIR__.'/vendor/autoload.php';

	use Symfony\Component\Yaml\Yaml;

	chdir(dirname(__FILE__));

	$api_keys_file = 'api_keys.yaml';
	if (file_exists($api_keys_file))
		$api_keys = Yaml::parseFile($api_keys_file);
	else {
		exit("No API keys file found");
	}

	$options = getopt('hi:o:');
	if (isset($options['h']))
		exit(file_get_contents('help.txt'));
	$input_path = $options['i'];
	$output_path = $options['o'];
	if (!isset($input_path) && !isset($output_path) && count($argv) >= 4) {
		$input_path = $argv[2];
		$output_path = $argv[3];
	}
	else
		exit('Input and output not set');

	foreach (['input', 'output'] as $direction) {
		if (strlen(${$direction.'_path'}) > 5) {
			if (substr(${$direction.'_path'}, -5) === '.json') {
				${$direction.'_type'} = 'json';
				if ($direction === 'input')
					$input = json_decode(file_get_contents(${$direction.'_path'}));
			}
			elseif (
				substr(${$direction.'_path'}, -5) === '.yaml' ||
				substr(${$direction.'_path'}, -4) === '.yml'
			) {
				${$direction.'_type'} = 'json';
				if ($direction === 'input')
					$input = Yaml::parseFile(${$direction.'_path'});
			}
			elseif (
				substr(${$direction.'_path'}, -3) === '.db' ||
				substr(${$direction.'_path'}, -7) === '.sqlite' ||
				substr(${$direction.'_path'}, -8) === '.sqlite3'
			) {
				exit("Databases are not supported");
			}
			else {
				if ($direction === 'input') {
					$input_type = 'txt';
					$input = file_get_contents($input_path);
				}
				else {
					$output_type = $input_type;
				}
				
			}
		}
	}