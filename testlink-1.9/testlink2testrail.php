#!/usr/bin/php
<?php

/**
 * This is a simple PHP command line script for migrating TestLink XML
 * export files to TestRail's import/export files.
 *
 * Copyright Gurock Software GmbH. All rights reserved.
 * http://www.gurock.com/
 *
 **********************************************************************
 *
 * Learn more about this migration script on its project website:
 *
 * http://docs.gurock.com/testrail-admin/migration-testlink
 */

error_reporting(E_ALL);

require_once(dirname(__FILE__) . '/lib/markdownify/parsehtml.php');
require_once(dirname(__FILE__) . '/lib/markdownify/markdownify.php');

$section_count = 0;
$case_count = 0;

/*********************************************************************/
function print_error_and_exit($error)
{
	print "$error\n";
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
	$usage = sprintf("Usage: %s <input-file> <output-file>\n",
		$argv[0]) .
"Copyright 2010-2013 Gurock Software GmbH. All rights reserved.

<input-file> should be the filename of a valid TestLink XML test
specification export file you want to convert (created with a
recent version of TestLink).

<output-file> specifies the filename of the resulting TestRail
import/export file.";

	print_error_and_exit($error . $usage);
}

/*********************************************************************/
function read_input($filename)
{
	libxml_use_internal_errors(true);
	$dom = simplexml_load_file($filename);
		
	if (!$dom || !is_object($dom))
	{
		$errors = libxml_get_errors();
		$error = '';
		
		foreach ($errors as $e)
		{
			$error .= "$e->message\n";			
		}
		
		print_error_and_exit($error);
	}
				
	return $dom;
}

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
function write_output($handle, $dom)
{
	fprintf($handle, "<?xml version=\"1.0\" encoding=\"UTF-8\"?>\n");
	write_sections($handle, $dom);
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
function write_case($handle, $case)
{	
	if (!isset($case['name']))
	{
		return; // Cases without title/name are ignored
	}
	
	global $case_count;
	$case_count++;

	$title = $case['name'];
	
	xml_write_opening_tag($handle, 'case');
	xml_write_tag($handle, 'title', $title);
	
	xml_write_opening_tag($handle, 'custom');
	if (isset($case->preconditions) && 
		strlen(trim($case->preconditions)) > 0)
	{	
		xml_write_html_tag($handle, 'preconds', $case->preconditions);
	}

	if (isset($case->summary) && strlen(trim($case->summary)) > 0)
	{	
		xml_write_html_tag($handle, 'summary', $case->summary);
	}

	if (isset($case->steps->step))
	{
		xml_write_opening_tag($handle, 'steps_separated');
		foreach ($case->steps->step as $step)
		{
			xml_write_opening_tag($handle, 'step');
			if (isset($step->expectedresults) && 
				(string) $step->expectedresults)
			{
				$content = html_to_markdown(
					(string) trim($step->actions));
				$expected = html_to_markdown(
					(string) trim($step->expectedresults));
				xml_write_tag($handle, 'content', $content);
				xml_write_tag($handle, 'expected', $expected);
			}
			else
			{		
				$content = html_to_markdown((string) trim($step->actions));
				xml_write_tag($handle, 'content', $content);
			}
			
			xml_write_closing_tag($handle, 'step');
		}
		xml_write_closing_tag($handle, 'steps_separated');
	}

	xml_write_closing_tag($handle, 'custom');
	
	xml_write_closing_tag($handle, 'case');
}

/*********************************************************************/
function write_section($handle, $section)
{
	if (!isset($section['name']))
	{
		return; // Sections without name are ignored
	}
	
	global $section_count;
	$section_count++;

	xml_write_opening_tag($handle, 'section');
	xml_write_tag($handle, 'name', $section['name']);
	
	if (isset($section->testsuite))
	{
		write_sections($handle, $section->testsuite);
	}
	
	if (isset($section->testcase))
	{
		write_cases($handle, $section->testcase);
	}
	
	xml_write_closing_tag($handle, 'section');
}

/*********************************************************************/
function html_to_markdown($html)
{
	$html = preg_replace('/&gt;/', '!!!!gt!!!!', $html);
	$html = preg_replace('/&lt;/', '!!!!lt!!!!', $html);

    $md = new Markdownify();
    $markdown = strip_tags($md->parseString($html));

	$markdown = preg_replace('/!!!!gt!!!!/', '>', $markdown);
	$markdown = preg_replace('/!!!!lt!!!!/', '<', $markdown);
	return $markdown;
}

/*********************************************************************/
if (count($argv) != 3)
{
	print_usage_and_exit();
}

$in = $argv[1];
$out = $argv[2];

if (!file_exists($in))
{
	print_usage_and_exit("File $in not found");
}

$dom = read_input($in);
$handle = fopen($out, 'w');
	
if (!$handle)
{
	print_error_and_exit('Could not create output file');
}

write_output($handle, $dom);
fclose($handle);

print("Successfully converted $section_count sections and $case_count cases\n");

?>
