<?php

/**
 * Copyright 2013 Gurock Software GmbH. All rights reserved.
 * http://www.gurock.com - contact@gurock.com
 */

function custom_filter($csv)
{
	// Iterate rows and build cases
	$count = count($csv);
	$pos = 1;

	while ($pos < $count)
	{
		$row = $csv[$pos];

		// Skip this row if there is no title
		if (!trim($row[1]))
		{
			$pos++;
			continue;
		}

		$step_index = 1;

		// Assign case attributes
		$case = array();
		$case['title'] = $row[0];
		$case['type'] = $row[1];

		// The priority field needs a conversion to TestRail numbers
		switch ($row[2])
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
		$case['section'] = $row[6];

		$custom = array();
		$custom['preconds'] = $row[3];
		$custom['steps_separated'] = 
			array('collection:step' => array()
		);

		// Build steps array
		while ($pos < $count)
		{
			$row = $csv[$pos];

			if ($row[4] || $row[5])
			{
				$custom['steps_separated']['collection:step'][] = array(
					'index' => $step_index,
					'content' => $row[4],
					'expected' => $row[5]
				);
				$step_index++;
			}

			// If the next row contains a test case break steps loop
			if ($pos + 1 < $count)
			{
				if (trim($csv[$pos + 1][0]))
				{
					break;
				}
			}
			
			$pos++;
		}

		// Add new case to case list
		$case['custom'] = $custom;
		$cases[] = $case;

		$pos++;
	}

	// Return all cases
	return $cases;
}

?>
