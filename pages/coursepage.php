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
require_once($CFG->dirroot.'/local/extdb/classes/task/extdb.php');
global $CFG, $PAGE, $USER, $OUTPUT, $DB;

// Get info from courselink block as POST form.
if (isset($_POST['crssn'])) {
    if (substr($_POST['crssn'], 0, 3) != 'CRS') {
        $crssn = 'CRS-'.$_POST['crssn'];
    } else {
        $crssn = $_POST['crssn'];
    }
    $crs = $DB->get_record('course', array('shortname' => $crssn), '*', MUST_EXIST);
    $crsid = $crs->id;
    $crsidnum = $crs->idnumber;
    $crsfn = $crs->fullname;
    if (strpos($crssn, '-DOM-')) {  // Must be CRS-DOM.
        $dom = explode('-', $crssn);
        $crsdomain = $dom[2];
    } else {
        echo '<h2 class="warning">No Course id provided</h2>';
        exit;
    }
} else {
    echo '<h2 class="warning">No Course id provided</h2>';
    exit;
}

$userid = $USER->id;
$isstudent = 0;
if (strpos($USER->email, '@connect.glos.ac.uk') !== false ) {
    $isstudent = 1;
}

// Set up new function.
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

$sourcemapheader = get_string('sourcemapheader', 'block_courselink');
$sourcemapdiet = get_string('sourcemapdiet', 'block_courselink');
$sourcemapdietmodules = get_string('sourcemapdietmodules', 'block_courselink');
$sourcecrsgroup = get_string('sourcecrsgroup', 'block_courselink');

// Database connection and setup checks.
// Check connection and label Db/Table in cron output for debugging if required.
if (!$externaldbtype) {
    echo 'Database not defined.<br>';
    return 0;
}
// Check remote sourcemapheader.
if (!$sourcemapheader) {
    echo 'Validated details Table not defined.<br>';
    return 0;
}
// Check remote sourcemapdiet.
if (!$sourcemapdiet) {
    echo 'Validated details Table not defined.<br>';
    return 0;
}
// Check remote sourcemapdietmodules.
if (!$sourcemapdietmodules) {
    echo 'Validated details Table not defined.<br>';
    return 0;
}
// Check remote sourcecrsgroup.
if (!$sourcecrsgroup) {
    echo 'Validated details Table not defined.<br>';
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
// Get Domain details.

$domsql = ' SELECT DISTINCT
                DomainCode,
                DomainName,
                DomainSubjectCommunityName,
                DomainSchoolName,
                DomainCourseType,
                DomainCourseTypeDescription,
                SeniorTutor,
                CourseLeadPRS as CourseLead,
                SubjectCommunityLeadPRS as SubCommLead,
                HeadOfSchoolPRS as HoS,
                DomainRegisteringInstitution as Institution,
                DomainSubjectCommunityCampusName as Campus
            FROM ' . $sourcecrsgroup . '
            WHERE DomainCode = "' . $crsdomain . '";';
$domain = array();
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

if (count($domain) == 0) {
    $PAGE->set_context(context_system::instance());
    $thispageurl = new moodle_url('/blocks/courselink/pages/coursepage.php');
    $PAGE->set_url($thispageurl, $thispageurl->params());
    $PAGE->set_docs_path('');
    $PAGE->set_pagelayout('base');
    $PAGE->set_title('No Record Found');
    $PAGE->set_heading('No Record Found');

    // No edit.
    $USER->editing = $edit = 0;
    $PAGE->navbar->ignore_active();
    $PAGE->navbar->add($PAGE->title, $thispageurl);

    // Output.
    echo $OUTPUT->header();
    echo $OUTPUT->box_start();

    echo '<h4>No record could be found for this course domain.
        <br>Please contact Academic Adminstration for further assistance.</h4>';

    echo $OUTPUT->box_end();
    echo $OUTPUT->footer();

} else {

    $crsseniortutor = $DB->get_record('user', array('idnumber' => 's'.$domain[0]['seniortutor']));
    $crslead = $DB->get_record('user', array('idnumber' => 's'.$domain[0]['courselead']));
    $crssclead = $DB->get_record('user', array('idnumber' => 's'.$domain[0]['subcommlead']));
    $crshos = $DB->get_record('user', array('idnumber' => 's'.$domain[0]['hos']));

    // Get routes for Domain.
    $sql = "SELECT DomainCode, DomainName, rou_code, rou_name FROM " . $sourcecrsgroup . " WHERE DomainCode = '" . $crsdomain ."';";
    $routes = array();
    if ($rs = $extdb->Execute($sql)) {
        if (!$rs->EOF) {
            while ($crsgroup = $rs->FetchRow()) {
                $crsgroup = array_change_key_case($crsgroup, CASE_LOWER);
                $crsgroup = $externaldb->db_decode($crsgroup);
                $routes[] = $crsgroup;
            }
        }
        $rs->Close();
    } else {
        // Report error if required.
        $extdb->Close();
        echo 'Error reading data from the external '.$sourcecrsgroup.' table<br>';
        return 4;
    }

    $PAGE->set_context(context_system::instance());
    $thispageurl = new moodle_url('/blocks/courselink/pages/coursepage.php');
    $PAGE->set_url($thispageurl, $thispageurl->params());
    $PAGE->set_docs_path('');
    $PAGE->set_pagelayout('base');
    $PAGE->set_title($domain[0]['domainname'] . ' Course Maps');
    $PAGE->set_heading($domain[0]['domainname'] . ' Course Maps');

    // No edit.
    $USER->editing = $edit = 0;
    $PAGE->navbar->ignore_active();
    $PAGE->navbar->add($PAGE->title, $thispageurl);

    // Output.
    echo $OUTPUT->header();
    echo $OUTPUT->box_start();
    ?>
    <div class="jumbotron bg-info m-b-2 p-y-1">
        <h4 class="m-y-1">
            Please note that course maps are indicative of the structure of a course and may be subject to change.
        </h4>
    </div>

    <div class="coursetable container-fluid">
        <div class="row">
            <div class="col-3 bg-info"><h5 class="m-t-1 text-white">School</h5></div>
            <div class="col-3"><p class="m-t-1"><?php echo $domain[0]['domainschoolname']; ?></div>
            <div class="col-3 bg-info"><h5 class="m-t-1 text-white">Head of School</h5></div>
            <div class="col-3"><p class="m-t-1"><?php echo $crshos->firstname.' '.$crshos->lastname; ?></div>
        </div>
        <div class="row">
            <div class="col-3 bg-info"><h5 class="m-t-1 text-white">Subject Community</h5></div>
            <div class="col-3"><p class="m-t-1"><?php echo $domain[0]['domainsubjectcommunityname']; ?></div>
            <div class="col-3 bg-info"><h5 class="m-t-1 text-white">S.C.Lead</h5></div>
            <div class="col-3"><p class="m-t-1"><?php echo $crssclead->firstname.' '.$crssclead->lastname; ?></div>
        </div>
        <div class="row">
            <div class="col-3 bg-info"><h5 class="m-t-1 text-white">Course Lead</h5></div>
            <div class="col-3"><p class="m-t-1"><?php echo $crslead->firstname.' '.$crslead->lastname; ?></div>
            <div class="col-3 bg-info"><h5 class="m-t-1 text-white">Senior Tutor</h5></div>
            <div class="col-3"><p class="m-t-1"><?php echo $crsseniortutor->firstname.' '.$crsseniortutor->lastname; ?></div>
        </div>


    </div>

    <h4>Available Course maps</h4>

    <div class="mapbuttons w-75 float-left">
    <?php
    // Display Route options.
    $mapspagelink = new moodle_url ('/blocks/courselink/pages/mapspage.php');
    $personalmaplink = new moodle_url ('/blocks/courselink/pages/personalmapspage.php');

    $x = 0;
    foreach ($routes as $k => $v) {
        $rtcode = $routes[$x]['rou_code'];
        echo '<a href = "' . $mapspagelink .'?crssn='.$crssn.'&rtc='.$rtcode.'" class = "btn btn-success text-center w-100">';
        echo $routes[$x]['rou_name'];
        echo '</a>';
        echo '<br><br>';
        $x++;
    }
    if ($isstudent) {
        echo '<a href = "' . $personalmaplink . '" class = "btn btn-default text-center w-100">';
        echo 'My Personal Course Map';
        echo '</a>';
        echo '<br><br>';
    }
    ?>
    </div>
    <div class="mapbuttonicon float-right w-25 text-center ">
        <a href="https://mrgsurvey.bournemouth.ac.uk/surveys/s.asp?k=153503528975" class="display-4">
            <span class="fa-stack fa-lg">
                <i class="fa fa-square fa-stack-2x"></i>
                <i class="fa fa-commenting fa-stack-1x fa-inverse"></i>
            </span>
        </a>
        <h4>#Talk2Simon</h4>
    </div>

    <?php
    echo $OUTPUT->box_end();
    echo $OUTPUT->footer();
}