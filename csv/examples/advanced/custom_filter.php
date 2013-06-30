<?php

/**
 * Copyright 2010 Gurock Software GmbH. All rights reserved.
 * http://www.gurock.com - contact@gurock.com
 */

function custom_filter($csv)
{
	// Skip the first line
	unset($csv[0]);

	$current_id = false;
	$case = array();
	
	// Iterate rows and build cases
	foreach ($csv as $row)
	{
		// Are we starting with a new case or do we have data
		// for the previous case? Look up the ID and compare with
		// previous one
		if ($current_id != $row[0])
		{
			// Looks like we have a new ID; do we have a previous case
			// that has to be added to the case list?
			if ($case)
			{
				$cases[] = $case;
				$case = array();
			}
		}
		$current_id = $row[0];
	
		// Is this the first time we see that case?
		if (!isset($case['custom']))
		{
			// Create a new case and assign the various properties
			$case['title'] = $row[1];
			$case['type'] = $row[2];

			// The priority field needs a conversion to TestRail numbers
			switch ($row[3])
			{
				case 'Normal':
					$case['priority'] = 3;
					break;
				case 'Low':
					$case['priority'] = 1;
					break;
				case 'High':
					$case['priority'] = 5;
					break;
				default:
					$case['priority'] = 3;
					break;
			}
			
			// The export script will automatically create the section
			// hierarchy for us. All we have to do is to specify the
			// sections for a test case in this format:
			//
			// "Section 1 > Section 2 > Section 3"
			$case['section'] = $row[7];
			
			// Custom fields, such as Preconditions, Steps and
			// Expected Results
			$custom = array();
			$custom['preconds'] = trim($row[4]);
			$custom['steps'] = trim($row[5]);
			$custom['expected'] = trim($row[6]);
			$case['custom'] = $custom;
		}
		else
		{
			// Update text fields with additional lines
			$case['custom']['preconds'] .= "\n" . trim($row[4]);
			$case['custom']['steps'] .= "\n" . trim($row[5]);
			$case['custom']['expected'] .= "\n" . trim($row[6]);
		}	
	}
	
	if ($case)
	{
		$cases[] = $case;
	}	
	
	// Return all cases
	return $cases;
}
 
?>
