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
	$input = $options['i'];
	$output = $options['i'];