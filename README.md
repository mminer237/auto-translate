# auto-translate
A simple PHP script to automatically translate data files into multiple languages using free DeepL or Google Translate APIs

## Supported Data
Supports translating `.yaml` and `.json` files, as well as any plain text files.

## API Keys
Supports using either DeepL or Google Translate basic translation, although it prefers DeepL if available.

To get a free API key:

* [Sign up for a free API key with DeepL](https://www.deepl.com/pro/change-plan#developer)
* [Start a project on Google Cloud Platform and generate a basic "API Key"](https://console.cloud.google.com/apis/credentials)

This counts characters and should avoid exceeding the free limits, but this is not guaranteed and a bug could cause you to exceed Google's free limit.

It can be easily modified to use DeepL's paid plan.

## Usage
```
Usage:
	script.php <input-file|input-dir> <output-dir> <languages>
	script.php -h
	script.php [-r] -i <input-file|input-dir> -o <output-dir> -l <languages> [-t <output-file-type>]
	script.php -k <language-key> [-r] -i <input-file|input-dir> [-o <output-file>] -l <languages>

	-h                       	Show this help
	-i <input-file|input-dir>	Specify input file
	-k                       	Translate inside file based on specified language code keys
	-l <languages>           	Specify output languages
	-o <output-dir>          	Specify output directory
	-r                       	Recursively translate directory
	-t <output-file-type>    	Specify output file type
```

## Copyright
Copyright © 2021 Matthew Miner

Released under the MIT License
