<?php

/**
 * Copyright 2010 Gurock Software GmbH. All rights reserved.
 * http://www.gurock.com - contact@gurock.com
 */

function custom_filter($csv)
{
	// Skip the first line
	unset($csv[0]);

	// Iterate rows and build cases
	foreach ($csv as $row)
	{
		// Create a new case and assign the various properties
		$case = array();
		$case['title'] = $row[0];
		$case['type'] = $row[1];
		$case['priority'] = $row[2];
		
		// Custom fields, such as Preconditions, Steps and
		// Expected Results
		$custom = array();
		$custom['preconds'] = $row[3];
		$custom['steps'] = $row[4];
		$custom['expected'] = $row[5];
		$case['custom'] = $custom;
		
		// The export script will automatically create the section
		// hierarchy for us. All we have to do is to specify the
		// sections for a test case in this format:
		//
		// "Section 1 > Section 2 > Section 3"
		$case['section'] = $row[6];

		// Add the cases to the list
		$cases[] = $case;
	}
	
	// Return all cases
	return $cases;
}
 
?>
