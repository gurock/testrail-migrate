#!/usr/bin/php
<?php

/**
 * This is a simple PHP command line script for migrating CSV/Excel
 * files to TestRail's import/export files.
 *
 * Copyright 2010 Gurock Software GmbH. All rights reserved.
 * http://www.gurock.com - contact@gurock.com
 *
 **********************************************************************
 *
 * Learn more about this migration script on its project website:
 *
 * http://code.gurock.com/p/testrail-migrate/
 *
 */

error_reporting(E_ALL);

require_once(dirname(__FILE__) . '/lib/csv/Dialect.php');
require_once(dirname(__FILE__) . '/lib/csv/Reader/Abstract.php');
require_once(dirname(__FILE__) . '/lib/csv/Reader.php');

define('MODE_EXPORT', 1);
define('MODE_CSV', 2);
define('MODE_CASES', 3);
define('MODE_TREE', 4);

$section_count = 0;
$case_count = 0;

/*********************************************************************/
function xml_escape($str)
{
	return str_replace(
		array(
			'&',
			'"',
			'\'',
			'<',
			'>'
		),
		array(
			'&amp;',
			'&quot;',
			'&apos;',
			'&lt;',
			'&gt;'
		),
		$str);
}

/*********************************************************************/
function xml_write_tag($handle, $tag, $value)
{
	fprintf($handle, "<%s>%s</%s>\n", $tag, xml_escape($value), $tag);
}

/*********************************************************************/
function xml_write_html_tag($handle, $tag, $value)
{
	xml_write_tag($handle, $tag, html_to_markdown($value));
}

/*********************************************************************/
function xml_write_opening_tag($handle, $tag)
{
	fprintf($handle, "<%s>\n", $tag);
}

/*********************************************************************/
function xml_write_closing_tag($handle, $tag)
{
	fprintf($handle, "</%s>\n", $tag);
}

/*********************************************************************/
function write_output($handle, $sections)
{
	fprintf($handle, "<?xml version=\"1.0\" encoding=\"UTF-8\"?>\n");
	write_sections($handle, $sections);
}

/*********************************************************************/
function write_sections($handle, $sections)
{
	xml_write_opening_tag($handle, 'sections');
	
	foreach ($sections as $section)
	{
		write_section($handle, $section);		
	}
	
	xml_write_closing_tag($handle, 'sections');
}

/*********************************************************************/
function write_cases($handle, $cases)
{
	xml_write_opening_tag($handle, 'cases');

	foreach ($cases as $case)
	{
		write_case($handle, $case);
	}
	
	xml_write_closing_tag($handle, 'cases');
}

/*********************************************************************/
function write_case_tags($handle, $tags, $force_tag = false)
{
	foreach ($tags as $name => $value)
	{
		if (is_array($value))
		{
			if (preg_match('/collection\:(.+)/', $name, $matches))
			{
				write_case_tags($handle, $value, $matches[1]);
			}
			else
			{
				xml_write_opening_tag($handle, $force_tag ? 
					$force_tag : $name);
				write_case_tags($handle, $value);
				xml_write_closing_tag($handle, $force_tag ? 
					$force_tag : $name);
			}
		}
		else
		{
			xml_write_tag($handle, $name, $value);
		}
	}
}

/*********************************************************************/
function write_case($handle, $case)
{	
	if (!(isset($case['title']) || $case['title']))
	{
		return; // Cases without title/name are ignored
	}
	
	global $case_count;
	$case_count++;

	xml_write_opening_tag($handle, 'case');
	unset($case['section']);
	write_case_tags($handle, $case);
	xml_write_closing_tag($handle, 'case');
}

/*********************************************************************/
function write_section($handle, $section)
{
	if (!$section->name)
	{
		return; // Sections without name are ignored
	}
	
	global $section_count;
	$section_count++;

	xml_write_opening_tag($handle, 'section');
	xml_write_tag($handle, 'name', $section->name);
	
	if ($section->sections)
	{
		write_sections($handle, $section->sections);
	}
			
	if ($section->cases)
	{
		write_cases($handle, $section->cases);
	}
	
	xml_write_closing_tag($handle, 'section');
}

/*********************************************************************/
function print_line($line)
{
	print "$line\n";
}

/*********************************************************************/
function print_error_and_exit($error)
{
	print("$error\n");
	exit(1);
}

/*********************************************************************/
function print_usage_and_exit($error = false)
{
	if ($error)	
	{
		$error .= "\n";
	}
	else 
	{
		$error = '';
	}
	
	global $argv;
	$usage = sprintf("Usage: %s <filter-script> <input-file> <output-file> [mode] [delimiter]\n",
		$argv[0]) .
"Copyright 2010 Gurock Software GmbH. All rights reserved.

<filter-script> a PHP script to extract the CSV data for conversion.
See the project website for more details.

<input-file> should be the filename of a CSV file with test cases
you want to convert (for example, an exported Excel file).

<output-file> specifies the filename of the resulting TestRail
import/export file.

[mode] An optional mode. The following modes are available:

  --export  The default behavior; exports the data to the XML file.
  --csv     For debugging: prints the CSV data as seen by the script
  --cases   For debugging: prints the cases after the filter script
            was called
  --tree    For debugging: prints the section/case tree after analyzing
            the cases and sections
			
[delimiter] Allows you to override the default comma delimiter.";

	print_error_and_exit($error . $usage);
}

/*********************************************************************/
function read_input($filename, $delimiter)
{
	$dialect = new Csv_Dialect();
	$dialect->delimiter = $delimiter;
	try
	{
		$reader = new Csv_Reader($filename, $dialect);
	}
	catch (Exception $e)
	{
		print_error_and_exit('Could not open the specified input file');
	}
	$rows = $reader->toArray();
	return $rows;
}

/*********************************************************************/
function filter_csv_input($csv)
{
	$result = custom_filter($csv);
	if (!$result)
	{
		print_error_and_exit("The 'custom_filter' function didn't " .
			"return any data");
	}
	
	return $result;
}

/*********************************************************************/
function build_tree($cases)
{
	$top_sections = array();
	$count = 0;
	foreach($cases as $case)
	{
		// Find the sections for this case
		if (isset($case['section']) && $case['section'])
		{
			$section_names = preg_split('/\>/', $case['section']);
			$section_names = array_map('trim', $section_names);
			if (count($section_names) == 0)
			{
				print_error_and_exit("Case $count has no sections defined.");
			}
		}
		else
		{
			$section_names = array('Unnamed');
		}
		
		// Find/build the sections and attach the case
		$current_sections =& $top_sections;
		foreach ($section_names as $name)
		{
			$key = strtolower($name);
			if (!isset($current_sections[$key]))
			{
				$current_sections[$key] = new stdClass();
				$current_sections[$key]->sections = array();
				$current_sections[$key]->cases = array();
				$current_sections[$key]->name = $name;
			}
			$current_cases =& $current_sections[$key]->cases;
			$current_sections =& $current_sections[$key]->sections;
		}
		
		$current_cases[] = $case;
		$count++;
	}
	
	return $top_sections;
}

/*********************************************************************/
if (count($argv) < 4)
{
	print_usage_and_exit();
}


$filter = $argv[1];
$in = $argv[2];
$out = $argv[3];

$mode = isset($argv[4]) ? strtolower($argv[4]) : '--export';
switch ($mode)
{
	case '--export':
		$mode = MODE_EXPORT;
		break;
	case '--csv':
		$mode = MODE_CSV;
		break;
	case '--cases':
		$mode = MODE_CASES;
		break;
	case '--tree':
		$mode = MODE_TREE;
		break;
	default:
		print_usage_and_exit('Unknow mode specified');
}

// Process the input file
if (!file_exists($in))
{
	print_usage_and_exit("File $in not found");
}
$csv = read_input($in, isset($argv[5]) ? $argv[5] : ',');
if ($mode == MODE_CSV)
{
	print_r($csv);
	exit(1);
}

// Filter the CSV data for conversion
if (!@include($filter))
{
	print_usage_and_exit("Could not include filter script $in");
}

if (!function_exists('custom_filter'))
{
	print_error_and_exit("Filter function 'custom_filter' not " . 
		"defined in filter script");
}

$cases = filter_csv_input($csv);
if ($mode == MODE_CASES)
{
	print_r($cases);
	exit(1);
}

// Generate a section tree
$sections = build_tree($cases);
if ($mode == MODE_TREE)
{
	print_r($sections);
	exit(1);
}

// Write the output
$handle = fopen($out, 'w');
if (!$handle)
{
	print_error_and_exit('Could not create output file');
}
write_output($handle, $sections);
fclose($handle);

print("Successfully converted $section_count sections and $case_count cases\n");

?>
