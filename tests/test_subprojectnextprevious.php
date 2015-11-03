<?php
//
// After including cdash_test_case.php, subsequent require_once calls are
// relative to the top of the CDash source tree
//
require_once(dirname(__FILE__).'/cdash_test_case.php');
require_once('cdash/common.php');
require_once('cdash/pdo.php');

class SubProjectNextPreviousTestCase extends KWWebTestCase
{
    public function __construct()
    {
        parent::__construct();
    }

    public function testSubProjectNextPrevious()
    {
        // Submit our testing data.  This sets up three days of data for the
        // Didasko subproject.
        //
        // Build_3.xml creates a build of Mesquite.  The purpose of this is
        // to keep the 'Current' link honest.  This test will fail if the
        // underlying functionality ignores the subproject & finds the
        // most recently submitted build instead.
        $filesToSubmit =
            array("Build_1.xml", "Configure_1.xml", "Notes_1.xml", "Test_1.xml",
                    "Build_2.xml", "Configure_2.xml", "Notes_2.xml", "Test_2.xml",
                    "Build_3.xml");
        $dir  = dirname(__FILE__)."/data/SubProjectNextPrevious";
        foreach ($filesToSubmit as $file) {
            if (!$this->submission('Trilinos', "$dir/$file")) {
                $this->fail("Failed to submit $file");
                return 1;
            }
        }

        // Get the ids for the three subsequent builds of Didasko.
        $result = pdo_query("
                SELECT b.id FROM build AS b
                LEFT JOIN subproject2build AS sp2b ON sp2b.buildid=b.id
                LEFT JOIN subproject AS sp ON sp.id = sp2b.subprojectid
                WHERE sp.name = 'Didasko'
                ORDER BY b.starttime");

        $num_rows = pdo_num_rows($result);
        if ($num_rows != 3) {
            $this->fail("Expected 3 rows, found $num_rows");
            return 1;
        }

        $buildids = array();
        while ($row = pdo_fetch_array($result)) {
            $buildids[] = $row['id'];
        }
        $first_buildid = $buildids[0];
        $second_buildid = $buildids[1];
        $third_buildid = $buildids[2];

        // Verify the relevant pages have the correct links for
        // Previous, Next, and Current.
        $success = true;
        $error_msg = "";

        $old_style_pages = array("buildSummary", "viewConfigure", "viewUpdate");
        $new_style_pages = array("viewBuildError", "viewNotes");

        foreach ($old_style_pages as $page) {
            $this->get($this->url . "/$page.php?buildid=" . $first_buildid);
            $content = $this->getBrowser()->getContent();
            if ($content == false) {
                $error_msg = "Error retrieving content from $page.php";
                $success = false;
                break;
            }

            // Verify 'Next' from build #1 points to build #2
            $pattern = "#<a href=\"[a-zA-Z.]+\?buildid=$second_buildid\">\s*Next\s*</a>#";
            if (preg_match($pattern, $content) !== 1) {
                $error_msg = "Expected 'Next' link not found on $page for $first_buildid";
                $success = false;
                break;
            }

            // Verify 'Current' from build #1 points to build #3
            $pattern = "#<a href=\"[a-zA-Z.]+\?buildid=$third_buildid\">\s*Current\s*</a>#";
            if (preg_match($pattern, $content) !== 1) {
                $error_msg = "Expected 'Current' link not found on $page for $first_buildid";
                $success = false;
                break;
            }

            $this->get($this->url . "/$page.php?buildid=" . $second_buildid);
            $content = $this->getBrowser()->getContent();
            if ($content == false) {
                $error_msg = "Error retrieving content from $page.php";
                $success = false;
                break;
            }

            // Verify 'Previous' from build #2 points to build #1
            $pattern = "#<a href=\"[a-zA-Z.]+\?buildid=$first_buildid\">\s*Previous\s*</a>#";
            if (preg_match($pattern, $content) !== 1) {
                $error_msg = "Expected 'Previous' link not found on $page for $second_buildid";
                $success = false;
                break;
            }

            // Verify 'Next' from build #2 points to build #3
            $pattern = "#<a href=\"[a-zA-Z.]+\?buildid=$third_buildid\">\s*Next\s*</a>#";
            if (preg_match($pattern, $content) !== 1) {
                $error_msg = "Expected 'Next' link not found on $page for $second_buildid";
                $success = false;
                break;
            }

            // Verify 'Current' from build #2 points to build #3
            $pattern = "#<a href=\"[a-zA-Z.]+\?buildid=$third_buildid\">\s*Current\s*</a>#";
            if (preg_match($pattern, $content) !== 1) {
                $error_msg = "Expected 'Current' link not found on $page for $second_buildid";
                $success = false;
                break;
            }

            $this->get($this->url . "/$page.php?buildid=" . $third_buildid);
            $content = $this->getBrowser()->getContent();
            if ($content == false) {
                $error_msg = "Error retrieving content from $page.php";
                $success = false;
                break;
            }

            // Verify 'Previous' from build #3 points to build #2
            $pattern = "#<a href=\"[a-zA-Z.]+\?buildid=$second_buildid\">\s*Previous\s*</a>#";
            if (preg_match($pattern, $content) !== 1) {
                $error_msg = "Expected 'Previous' link not found on $page for $third_buildid";
                $success = false;
                break;
            }
        }

        foreach ($new_style_pages as $page) {
            $this->get($this->url . "/api/v1/$page.php?buildid=" . $first_buildid);
            $content = $this->getBrowser()->getContent();
            $jsonobj = json_decode($content, true);

            // Verify 'Next' from build #1 points to build #2
            if (strpos($jsonobj['menu']['next'], "buildid=$second_buildid") === false) {
                $error_msg = "Expected 'Next' link not found on $page for $first_buildid";
                $success = false;
                break;
            }

            // Verify 'Current' from build #1 points to build #3
            if (strpos($jsonobj['menu']['current'], "buildid=$third_buildid") === false) {
                $error_msg = "Expected 'Current' link not found on $page for $first_buildid";
                $success = false;
                break;
            }

            $this->get($this->url . "/api/v1/$page.php?buildid=" . $second_buildid);
            $content = $this->getBrowser()->getContent();
            $jsonobj = json_decode($content, true);

            // Verify 'Previous' from build #2 points to build #1
            if (strpos($jsonobj['menu']['previous'], "buildid=$first_buildid") === false) {
                $error_msg = "Expected 'Previous' link not found on $page for $second_buildid";
                $success = false;
                break;
            }

            // Verify 'Next' from build #2 points to build #3
            if (strpos($jsonobj['menu']['next'], "buildid=$third_buildid") === false) {
                $error_msg = "Expected 'Next' link not found on $page for $second_buildid";
                $success = false;
                break;
            }

            // Verify 'Current' from build #2 points to build #3
            if (strpos($jsonobj['menu']['current'], "buildid=$third_buildid") === false) {
                $error_msg = "Expected 'Current' link not found on $page for $second_buildid";
                $success = false;
                break;
            }

            $this->get($this->url . "/api/v1/$page.php?buildid=" . $third_buildid);
            $content = $this->getBrowser()->getContent();
            $jsonobj = json_decode($content, true);

            // Verify 'Previous' from build #3 points to build #2
            if (strpos($jsonobj['menu']['previous'], "buildid=$second_buildid") === false) {
                $error_msg = "Expected 'Previous' link not found on $page for $third_buildid";
                $success = false;
                break;
            }
        }

        // Delete the builds that we created during this test.
        $result = pdo_single_row_query(
            "SELECT parentid FROM build WHERE id=$second_buildid");
        remove_build($result['parentid']);
        $result = pdo_single_row_query(
            "SELECT parentid FROM build WHERE id=$third_buildid");
        remove_build($result['parentid']);

        if (!$success) {
            $this->fail($error_msg);
            return 1;
        }

        $this->pass('Tests passed');
        return 0;
    }
}