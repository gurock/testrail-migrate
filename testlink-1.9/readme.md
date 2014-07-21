TestLink Migration Script for TestLink 1.9 or later
---------------------------------------------------

This script can be used to convert TestLink XML files to TestRail's
XML import file format. This script is specifically built for 
TestLink 1.9 or later. Please note that TestLink 1.9 and later
use separate test step fields, and you need to configure TestRail
this way as well in order to import the converted files correctly:

http://docs.gurock.com/testrail-faq/config-steps

Usage
-----

You can use the script as follows:

    > php testlink2testrail.php <input-file> <output-file>

    <input-file> should be the filename of a valid TestLink XML test
    specification export file you want to convert (created with a
    recent version of TestLink).

    <output-file> specifies the filename of the resulting TestRail
    import/export file.

The script converts a test suite tree of a TestLink test specification
into a single test suite of TestRail. The tree structure is represented
with sections and subsections in TestRail. To import a converted file
into TestRail, create an empty test suite and choose the import button
in the toolbar.

Unlike TestRail, TestLink uses HTML for formatting test case properties.
The script tries to convert these fields into TestRail's text format
(markdown) where possible.

You can learn more about this migration script on its project website:
http://docs.gurock.com/testrail-admin/migration-testlink

Included third-party code:
--------------------------

Markdownify (c) Milian Wolff
Published under the GNU Lesser General Public License:
http://milianw.de/projects/markdownify/index.php

-- 
Copyright Gurock Software GmbH. All rights reserved.

http://www.gurock.com/
