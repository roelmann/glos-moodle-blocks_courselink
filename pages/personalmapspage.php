<?php
// This file is part of The Bootstrap 3 Moodle theme
//
// Moodle is free software: you can redistribute it and/or modify
// it under the terms of the GNU General Public License as published by
// the Free Software Foundation, either version 3 of the License, or
// (at your option) any later version.
//
// Moodle is distributed in the hope that it will be useful,
// but WITHOUT ANY WARRANTY; without even the implied warranty of
// MERCHANTABILITY or FITNESS FOR A PARTICULAR PURPOSE.  See the
// GNU General Public License for more details.
//
// You should have received a copy of the GNU General Public License
// along with Moodle.  If not, see <http://www.gnu.org/licenses/>.
/**
 * Course block page.
 *
 * @package    page_coursepage
 * @author     2019 Richard Oelmann
 * @copyright  2019 R. Oelmann

 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */
// Ref: http://docs.moodle.org/dev/Page_API.

require_once('../../../config.php');
require_login();
global $CFG, $PAGE, $USER, $OUTPUT, $DB;

// Get user details and check user is student.
$isstudent = 0;
if (strpos($USER->email, '@connect.glos.ac.uk') !== false ) {
    $isstudent = 1;
    $username = strtolower($USER->idnumber); // Ensure 'S' or 's' used.

    if (strlen($USER->institution) > 1) { // If user has course identified.
        // Fetch Student institution code and explode to find Course Domain and links.
        $dom = explode('~', $USER->institution);
        $crsdomain = 'CRS-' . $dom[3];
        $cd = explode('-', $dom[3]);
        $crsdom = $cd[1];

        $course = $DB->get_record('course', array('idnumber' => $crsdomain), '*', MUST_EXIST);
        $pagelink = new moodle_url ('/blocks/courselink/pages/coursepage.php');
    }
} else {
    echo '<h2 class="alert-warning">Personalised Course maps are only available for active enrolled students.</h2>';
    exit;
}

// Get dependency external plugin to enable reading external database.
require_once($CFG->dirroot.'/local/extdb/classes/task/extdb.php');

// Set up new function to read external database and extDb configuration.
$externaldb = new \local_extdb\extdb();
$name = $externaldb->get_name();

$externaldbtype = $externaldb->get_config('dbtype');
$externaldbhost = $externaldb->get_config('dbhost');
$externaldbname = $externaldb->get_config('dbname');
$externaldbencoding = $externaldb->get_config('dbencoding');
$externaldbsetupsql = $externaldb->get_config('dbsetupsql');
$externaldbsybasequoting = $externaldb->get_config('dbsybasequoting');
$externaldbdebugdb = $externaldb->get_config('dbdebugdb');
$externaldbuser = $externaldb->get_config('dbuser');
$externaldbpassword = $externaldb->get_config('dbpass');

// Get language strings for database tables used in this plugin page.
$sourceuserenrolments = get_string('sourceuserenrolments', 'block_courselink');
$sourcecrsgroup = get_string('sourcecrsgroup', 'block_courselink');
$mapmodguide = get_string('mapmodguide', 'block_courselink');

// Database connection and setup checks.
// Check connection and label Db/Table in cron output for debugging if required.
if (!$externaldbtype) {
    echo 'Database not defined.<br>';
    return 0;
}
// Check remote sourcecrsgroup.
if (!$sourcecrsgroup) {
    echo 'sourcecrsgroup Table not defined.<br>';
    return 0;
}
// Check remote MapsModuleGuide table.
if (!$mapmodguide) {
    echo 'mapmodguide Table not defined.<br>';
    return 0;
}

// Report connection error if occurs.
if (!$extdb = $externaldb->db_init(
    $externaldbtype,
    $externaldbhost,
    $externaldbuser,
    $externaldbpassword,
    $externaldbname)) {
        echo 'Error while communicating with external database <br>';
        return 1;
}

// Get Domain details and parse into domain() array.
$domain = array();
$domsql = " SELECT DISTINCT
                DomainCode,
                DomainName
            FROM " . $sourcecrsgroup . "
            WHERE DomainCode = '" . $crsdom ."';";
if ($rs = $extdb->Execute($domsql)) {
    if (!$rs->EOF) {
        while ($dom = $rs->FetchRow()) {
            $dom = array_change_key_case($dom, CASE_LOWER);
            $dom = $externaldb->db_decode($dom);
            $domain[] = $dom;
        }
    }
    $rs->Close();
} else {
    // Report error if required.
    $extdb->Close();
    echo 'Error reading data from the external '.$sourcecrsgroup.' table<br>';
    return 4;
}

// Get all modules taken by student, from enrolments table and parse into modulestaken array.
$modulestaken = array();
$modsql = " SELECT
                course,
                Academic_year as Acyear,
                semester,
                run
            FROM " . $sourceuserenrolments . "
            WHERE LOWER(username) = '" . $username . "'
            AND role = 'student';";
if ($rs = $extdb->Execute($modsql)) {
    if (!$rs->EOF) {
        while ($mod = $rs->FetchRow()) {
            $mod = array_change_key_case($mod, CASE_LOWER);
            $mod = $externaldb->db_decode($mod);
            $modulestaken[] = $mod;
        }
    }
    $rs->Close();
} else {
    // Report error if required.
    $extdb->Close();
    echo 'Error reading data from the external '.$sourcecrsgroup.' table<br>';
    return 4;
}

// Set up page requirements.
$PAGE->set_context(context_system::instance());
$thispageurl = new moodle_url('/blocks/courselink/pages/coursepage.php');
$PAGE->set_url($thispageurl, $thispageurl->params());
$PAGE->set_docs_path('');
$PAGE->set_pagelayout('base');
$PAGE->set_title($domain[0]['domainname'] . ' Course Maps');
$PAGE->set_heading($domain[0]['domainname'] . ' Course Maps');
$USER->editing = $edit = 0;
$PAGE->navbar->ignore_active();
$PAGE->navbar->add($PAGE->title, $thispageurl);

// Output.
echo $OUTPUT->header();
echo $OUTPUT->box_start();
?>

<!-- Page content heading -->
<h4 class="my-0"><?php echo $domain[0]['domainname']; ?></h4>
<div class="jumbotron bg-info m-b-2 p-y-1">
    <h5 class="my-1"><?php echo get_string('perscmheader', 'block_courselink'); ?></h5>
</div>
<!-- Past and Current modules -->
<h5><?php echo get_string('pcmods', 'block_courselink'); ?></h5>

<?php
// Get academic years of past/current modules for this student.
$acyrs = array(); $ac = array(); $x = 0;
foreach ($modulestaken as $k => $v) {
    $ac[] = $modulestaken[$x]['acyear'];
    $x++;
}
$acyrs = array_unique($ac);

echo '<div id="accordianmap">'; // Start accordian map parent.
    $aycount = 0;
foreach ($acyrs as $acyr) { // Loop each academic year.
    $ay = str_replace('/', '', $acyr); // Where used in classes and IDs needs / removed.
    // Create year header as accordian exapnd button.
    echo '<h4 id="acyear' . $ay . '" class="w-100 bg-info text-white p-1">';
        echo '<button class="btn btn-info w-75 text-left collapsed"
            type="button" data-toggle="collapse" data-target="#collapse' . $ay .
            '" aria-expanded="false" aria-controls="collapse' . $ay . '">';
            echo $acyr;
        echo'</button>';
    echo '</h4>';

    // Create year content as collapsable.
    echo '<div id="collapse'.$ay.'" class="collapse" data-parent="#accordianmap">'; // Also accordian for year content - modules.
    $modcount = 0;
    foreach ($modulestaken as $mtk => $mtv) { // Loop through each module.
        if ($modulestaken[$modcount]['acyear'] == $acyr) { // only pick out the modules in that year.
            // Extract details from course code.
            $mc = explode('_', $modulestaken[$modcount]['course']);
            $modcode = $mc[0];
            $objectid = $modcode . "~" . $acyr;
            $modclass = $modcode.$ay;

            // Get all validated details for the module and parse into validated() array.
            $validated = array();
            $mgsql = "SELECT field, fieldname, value FROM ".$mapmodguide." WHERE objectid = '".$objectid."';";
            if ($rs = $extdb->Execute($mgsql)) {
                if (!$rs->EOF) {
                    while ($val = $rs->FetchRow()) {
                        $val = array_change_key_case($val, CASE_LOWER);
                        $val = $externaldb->db_decode($val);
                        $validated[] = $val;
                    }
                }
                $rs->Close();
            } else {
                // Report error if required.
                $extdb->Close();
                echo 'Error reading data from the external course tables<br>';
                return 4;
            }

            if (count($validated) <= 0) {
                $mgoutput = '<h4>' . get_string('nomodguide', 'block_courselink') . '</h4>';
            } else {
                // Parse validated array into mgdisplay() array as easier to work with in each loop.
                $mgdisplay = array();
                foreach ($validated as $k => $v) {
                    $mgdisplay[$validated[$k]['field']] = $validated[$k]['value'];
                }
            }

            // Create module title as button to expand/collapse module validated content.
            echo '<h6 id="module'.$modclass.'" class="bg-primary text-white m-y-0">';
            echo '<button class="btn btn-primary collapsed w-100 text-left"
                type="button" data-toggle="collapse" data-target="#collapse'.
                $modclass.'" aria-expanded="false" aria-controls="collapse'.$modclass.'">';
            echo $modcode.": ".$mgdisplay['TITLE'].
                "<span class='float-right p-r-1'>".$mgdisplay['CREDIT']."</span>";
                echo'</button>';
            echo '</h6>';

            // Display collapsible full content of validated details for module.
            echo '<div id="collapse' . $modclass . '" class="collapse" data-parent="#collapse'.$ay.'">';
                echo '<div class="modtable container-fluid">';

                    echo '<div class="row">';
                        echo '<div class="col-3 bg-success"><h5 class="text-white m-t-1">Module Code</h5></div>';
                        echo '<div class="col-9"><p class="m-t-1">'.$modcode.'</p></div>';
                    echo '</div>';
                    echo '<div class="row">';
                        echo '<div class="col-3 bg-success"><h5 class="text-white m-t-1">Module Title</h5></div>';
                        echo '<div class="col-9"><p class="m-t-1">'.$mgdisplay['TITLE'].'</p></div>';
                    echo '</div>';
                    echo '<div class="row">';
                        echo '<div class="col-3 bg-success"><h5 class="text-white m-t-1">School</h5></div>';
                        echo '<div class="col-9"><p class="m-t-1">'.$mgdisplay['SCHOOL'].'</p></div>';
                    echo '</div>';
                    echo '<div class="row">';
                        echo '<div class="col-3 bg-success"><h5 class="text-white m-t-1">Level</h5></div>';
                        echo '<div class="col-3"><p class="m-t-1">'.$mgdisplay['LEVEL'].'</p></div>';
                        echo '<div class="col-3 bg-success"><h5 class="text-white m-t-1">CAT Points</h5></div>';
                        echo '<div class="col-3"><p class="m-t-1">'.$mgdisplay['CREDIT'].'</p></div>';
                    echo '</div>';
                    echo '<div class="row">';
                        echo '<div class="col-3 bg-success"><h5 class="text-white m-t-1">Pre-Requisites</h5></div>';
                        echo '<div class="col-9"><p class="m-t-1">'.$mgdisplay['PREREQ'].'</p></div>';
                    echo '</div>';
                    echo '<div class="row">';
                        echo '<div class="col-3 bg-success"><h5 class="text-white m-t-1">Co-Requisites</h5></div>';
                        echo '<div class="col-9"><p class="m-t-1">'.$mgdisplay['COREQ'].'</p></div>';
                    echo '</div>';
                    echo '<div class="row">';
                        echo '<div class="col-3 bg-success"><h5 class="text-white m-t-1">Restrictions</h5></div>';
                        echo '<div class="col-9"><p class="m-t-1">'.$mgdisplay['RESTRICT'].'</p></div>';
                    echo '</div>';

                    echo '<div class="row">';
                        echo '<div class="col-3 bg-success"><h5 class="text-white m-t-1">Brief Description</h5></div>';
                        echo '<div class="col-9"><p class="m-t-1">'.$mgdisplay['DESC'].'</p></div>';
                    echo '</div>';
                    echo '<div class="row">';
                        echo '<div class="col-3 bg-success"><h5 class="text-white m-t-1">indicative Syllabus</h5></div>';
                        echo '<div class="col-9"><p class="m-t-1">'.$mgdisplay['INDSYLL'].'</p></div>';
                    echo '</div>';
                    echo '<div class="row">';
                        echo '<div class="col-3 bg-success"><h5 class="text-white m-t-1">Learning Outcomes</h5></div>';
                        echo '<div class="col-9"><p class="m-t-1">'.$mgdisplay['OUTCOME'].'</p></div>';
                    echo '</div>';
                    echo '<div class="row">';
                        echo '<div class="col-3 bg-success"><h5 class="text-white m-t-1">Activities</h5></div>';
                        echo '<div class="col-9"><p class="m-t-1">'.$mgdisplay['LEARNTEACH'].'</p></div>';
                    echo '</div>';
                    echo '<div class="row">';
                        echo '<div class="col-3 bg-success"><h5 class="text-white m-t-1">Assessment</h5></div>';
                        echo '<div class="col-9"><p class="m-t-1">'.$mgdisplay['ASSESS'].'</p></div>';
                    echo '</div>';
                    echo '<div class="row">';
                        echo '<div class="col-3 bg-success"><h5 class="text-white m-t-1">
                            Special Assessment Requirements</h5></div>';
                        echo '<div class="col-9"><p class="m-t-1">'.$mgdisplay['SPECASSESS'].'</p></div>';
                    echo '</div>';
                    echo '<div class="row">';
                        echo '<div class="col-3 bg-success"><h5 class="text-white m-t-1">Indicative Resources</h5></div>';
                        echo '<div class="col-9"><p class="m-t-1">'.$mgdisplay['INDRES'].'</p></div>';
                    echo '</div>';

                echo '</div>';
            echo '</div>'; // End collapse$modclass.

        }
            $modcount++;
    }
    echo '</div>'; // End year accordian.
}
echo '</div>'; // End accordian.

// Create link back to full course map page.
// Currently not possible to link to future course maps as the data does not exist to
// accurately tie an individual student to their course map.
echo '<h5>Full course maps can be seen from the link below.</h5>';
if (strlen($USER->institution) > 1) { // If user has course identified.
    echo '<h5 class = "w-100 bg-info text-white p-3" style="text-align:center;">';
    echo '<form id="courselink" action="'.$pagelink.'" method="post" style="margin:0 0 2px 0">';
    echo '<input type="hidden" name="crssn" value="'.$course->shortname.'">';
    echo '<input class="mx-auto" type="submit" value="Course Map" class="courselinksubmit p-2" >';
    echo '</form>';
    echo '</h5>';
}

// Close page display.
echo $OUTPUT->box_end();
echo $OUTPUT->footer();
