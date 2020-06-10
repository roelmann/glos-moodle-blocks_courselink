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

// Get info from courselink block as POST form.
if (isset($_GET['crssn']) && isset($_GET['rtc'])) {
    $crs = $DB->get_record('course', array('shortname' => $_GET['crssn']), '*', MUST_EXIST);
    $crsid = $crs->id;
    $crssn = $_GET['crssn'];
    $crsidnum = $crs->idnumber;
    $crsfn = $crs->fullname;
    if (strpos($crssn, '-DOM-')) {  // Must be CRS-DOM.
        $dom = explode('-', $crssn);
        $crsdomain = $dom[2];
    } else {
        echo '<h2 class="warning">' . get_string('nocourseid', 'block_courselink') . '</h2>';
        exit;
    }
    $routecode = $_GET['rtc'];
} else {
    echo '<h2 class="warning">' . get_string('nocourseid', 'block_courselink') . '</h2>';
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
$sourcemapdiet = get_string('sourcemapdiet', 'block_courselink');
$sourcemapdietmodules = get_string('sourcemapdietmodules', 'block_courselink');
$sourcecrsgroup = get_string('sourcecrsgroup', 'block_courselink');
$sourcecrsdocs = get_string('sourcecrsdocs', 'block_courselink');

$mapmodguide = get_string('mapmodguide', 'block_courselink');

// Database connection and setup checks.
// Check connection and label Db/Table in cron output for debugging if required.
if (!$externaldbtype) {
    echo 'Database not defined.<br>';
    return 0;
}
// Check remote sourcemapdiet.
if (!$sourcemapdiet) {
    echo 'Validated details Table not defined.<br>';
    return 0;
}
// Check remote sourcemapdietmodules.
if (!$sourcemapdietmodules) {
    echo 'sourcemapdietmodules Table not defined.<br>';
    return 0;
}
// Check remote sourcecrsgroup.
if (!$sourcecrsgroup) {
    echo 'sourcecrsgroup Table not defined.<br>';
    return 0;
}
// Check remote sourcecrsdocs.
if (!$sourcecrsdocs) {
    echo 'sourcecrsdocs Table not defined.<br>';
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
$domsql = " SELECT DISTINCT
                DomainCode,
                DomainName
            FROM " . $sourcecrsgroup . "
            WHERE DomainCode = '" . $crsdomain ."';";
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
    <h5 class="my-1"><?php echo get_string('subjtochange', 'block_courselink'); ?></h5>
</div>

<?php
// Get academic years available for map.
$ay = array();
$aysql = "  SELECT DISTINCT
                DietYear
            FROM ".$sourcemapdiet."
            WHERE RouteCode = '".$routecode."';";
if ($rs = $extdb->Execute($aysql)) {
    if (!$rs->EOF) {
        while ($acyr = $rs->FetchRow()) {
            $acyr = array_change_key_case($acyr, CASE_LOWER);
            $acyr = $externaldb->db_decode($acyr);
            $ay[] = $acyr;
        }
    }
    $rs->Close();
} else {
    // Report error if required.
    $extdb->Close();
    echo 'Error reading data from the external '.$sourcecrsgroup.' table<br>';
    return 4;
}

echo '<div id="accordianmap">'; // Begin accordian - top level, academic years.
$aycount = 0;
foreach ($ay as $ayk => $ayv) {
    $year = str_replace('/', '', $ay[$aycount]["dietyear"]); // Needs / removing to use for IDs and Classes.
    // Create year header as exapnd/collapse button for content.
    echo '<h4 id="acyear'.$year.'" class="w-100 bg-info text-white p-1">';
    echo '<button class="btn btn-info w-75 text-left collapsed" type="button"
        data-toggle="collapse" data-target="#collapse'.$year.'"
        aria-expanded="false" aria-controls="collapse'.$year.'">';
    echo $ay[$aycount]["dietyear"];
    echo'</button>';
    echo '</h4>';

    // Get diet levels for displayed academic year.
    $dl = array();
    $dlsql = "  SELECT DISTINCT
                    DietLevel
                FROM ".$sourcemapdiet."
                WHERE RouteCode = '".$routecode."'
                    AND DietYear = '".$ay[$aycount]["dietyear"]."';";
    if ($rs = $extdb->Execute($dlsql)) {
        if (!$rs->EOF) {
            while ($dtlv = $rs->FetchRow()) {
                $dtlv = array_change_key_case($dtlv, CASE_LOWER);
                $dtlv = $externaldb->db_decode($dtlv);
                $dl[] = $dtlv;
            }
        }
        $rs->Close();
    } else {
        // Report error if required.
        $extdb->Close();
            echo 'Error reading data from the external '.$sourcecrsgroup.' table<br>';
        return 4;
    }

    // Set 2nd level of accordian content for Levels (parent = accordianmap).
    echo '<div id="collapse'.$year.'" class="collapse" data-parent="#accordianmap">';
    $dlcount = 0;
    foreach ($dl as $dlk => $dlv) {
        $level = 'y'.$year.'l'.$dl[$dlcount]["dietlevel"];
        $leveldisp = $dl[$dlcount]["dietlevel"];
        // Create level header as exapnd/collapse button for content.
        echo '<h5 id="level'.$level.'" class="bg-secondary text-white m-y-0 p-1">';
        echo '<button class="btn btn-secondary collapsed w-75 text-left"
            type="button" data-toggle="collapse" data-target="#collapse'.$level.'"
            aria-expanded="false" aria-controls="collapse'.$level.'">';
        echo 'Level '.$leveldisp;
        echo'</button>';
        echo '</h5>';

        // Get Requirements sections for each level.
        $sequ = array();
        $seqsql = " SELECT DISTINCT
                        PDMSequence,
                        PDMDescription
                    FROM ".$sourcemapdiet."
                    WHERE RouteCode = '".$routecode."'
                        AND DietYear = '".$ay[$aycount]["dietyear"]."'
                        AND DietLevel = '".$dl[$dlcount]["dietlevel"]."';";
        if ($rs = $extdb->Execute($seqsql)) {
            if (!$rs->EOF) {
                while ($seq = $rs->FetchRow()) {
                    $seq = array_change_key_case($seq, CASE_LOWER);
                    $seq = $externaldb->db_decode($seq);
                    $sequ[] = $seq;
                }
            }
            $rs->Close();
        } else {
            // Report error if required.
            $extdb->Close();
                echo 'Error reading data from the external '.$sourcecrsgroup.' table<br>';
            return 4;
        }

        // Set 3rd level of accordian content for Requirements (parent = levels).
        echo '<div id="collapse'.$level.'" class="collapse" data-parent="#collapse'.$year.'">';
        $seqcount = 0;
        foreach ($sequ as $seqk => $seqv) {
            // Set button for displaying requirement levels.
            echo '<h5 class="bg-dark text-white m-t-0 p-1">';
                echo '<button class="btn btn-dark p-y-0 m-y-0">';
                    echo $sequ[$seqcount]["pdmdescription"];
                echo '</button>';
                echo '<span class="float-right p-r-2">CATS</span>';
            echo '</h5>';

            // Get modules from each requirement.
            $dmods = array();
            $dmodssql = "SELECT
                            dm.CollectionElement as ModuleCode,
                            dm.ModuleName,
                            dm.ModuleCredits
                        FROM ".$sourcemapdietmodules." dm
                            JOIN ".$sourcemapdiet." cmd ON cmd.PDMCode = dm.CollectionCode
                        WHERE cmd.RouteCode = '".$routecode."'
                            AND cmd.DietYear = '".$ay[$aycount]["dietyear"]."'
                            AND cmd.DietLevel = '".$dl[$dlcount]["dietlevel"]."'
                            AND cmd.PDMSequence = '".$sequ[$seqcount]["pdmsequence"]."';";

            if ($rs = $extdb->Execute($dmodssql)) {
                if (!$rs->EOF) {
                    while ($mod = $rs->FetchRow()) {
                        $mod = array_change_key_case($mod, CASE_LOWER);
                        $mod = $externaldb->db_decode($mod);
                        $dmods[] = $mod;
                    }
                }
                $rs->Close();
            } else {
                // Report error if required.
                $extdb->Close();
                    echo 'Error reading data from the external '.$sourcecrsgroup.' table<br>';
                return 4;
            }

            // Content collapsible for module validated details.
            $modcount = 0;
            foreach ($dmods as $modk => $modv) {
                $objectid = $dmods[$modcount]['modulecode']."~".$ay[$aycount]["dietyear"];
                $modcode = $dmods[$modcount]['modulecode'];
                $modclass = $level.$modcode;

                $valmodguide = $validated = array();
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
                    $mgdisplay = array();
                    foreach ($validated as $k => $v) {
                        $mgdisplay[$validated[$k]['field']] = $validated[$k]['value'];
                    }
                }

                // Create module title as button to expand/collapse module validated content.
                echo '<h6 id="module'.$modclass.'" class="bg-primary text-white m-y-0">';
                echo '<button class="btn btn-primary collapsed w-100 text-left"
                    type="button" data-toggle="collapse" data-target="#collapse'.$modclass.'"
                    aria-expanded="false" aria-controls="collapse'.$modclass.'">';
                echo $dmods[$modcount]['modulecode'].": ".$dmods[$modcount]['modulename'].
                    "<span class='float-right p-r-1'>".$dmods[$modcount]['modulecredits']."</span>";
                echo'</button>';
                echo '</h6>';

                // Display collapsible full content of validated details for module.
                echo '<div id="collapse'.$modclass.'" class="collapse" data-parent="#collapse'.$level.'">';
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
                            echo '<div class="col-3 bg-success"><h5 class="text-white m-t-1">Indicative Syllabus</h5></div>';
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
                            echo '<div class="col-3 bg-success"><h5 class="text-white m-t-1">
                                Assessment</h5></div>';
                            echo '<div class="col-9"><p class="m-t-1">'.$mgdisplay['ASSESS'].
                                '</p></div>';
                        echo '</div>';
                        echo '<div class="row">';
                            echo '<div class="col-3 bg-success"><h5 class="text-white m-t-1">
                                Special Assessment Requirements</h5></div>';
                            echo '<div class="col-9"><p class="m-t-1">'.$mgdisplay['SPECASSESS'].
                                '</p></div>';
                        echo '</div>';
                        echo '<div class="row">';
                            echo '<div class="col-3 bg-success"><h5 class="text-white m-t-1">
                                Indicative Resources</h5></div>';
                            echo '<div class="col-9"><p class="m-t-1">'.$mgdisplay['INDRES'].
                                '</p></div>';
                        echo '</div>';

                    echo '</div>';
                echo '</div>'; // End collapse$modclass.
                $modcount++;
            }

            $seqcount++;
        }

        $dlcount++;
        echo '</div>'; // End collapse$level.
    }

    $aycount++;
    echo '</div>'; // End collapse$year.
}
echo '</div>'; // End accordian.

// Get Course Documents.
$crsdocs = array();
$cdsql = "SELECT * FROM " . $sourcecrsdocs . " WHERE route_code = '" . $routecode . "' ORDER BY doc_type DESC;";
if ($rs = $extdb->Execute($cdsql)) {
    if (!$rs->EOF) {
        while ($crsd = $rs->FetchRow()) {
            $crsd = array_change_key_case($crsd, CASE_LOWER);
            $crsd = $externaldb->db_decode($crsd);
            $crsdocs[] = $crsd;
        }
    }
    $rs->Close();
} else {
    // Report error if required.
    $extdb->Close();
        echo 'Error reading data from the external '.$sourcecrsdocs.' table<br>';
    return 4;
}

// Display link buttons to course documents.
$x = $progspec = $crsstrat = 0;
echo '<div class="coursedocs container m-t-2">';
    echo '<div class="row">';

foreach ($crsdocs as $k => $v) {
    $fai = $dt = '';

    if ($crsdocs[$x]['doc_type'] === 'Programme Specification') {
        $fai = 'fa fa-3x fa-id-card';
        $progspec = 1;
    } else if ($crsdocs[$x]['doc_type'] === 'Course History') {
        $crsdocs[$x]['doc_type'] = 'Course Assessment Strategy';
        $fai = 'fa fa-3x fa-line-chart';
        $crsstrat = 1;
    }

    echo '<div class="col text-center">';
        echo '<a href="' . $crsdocs[$x]['doc_link'] . '" title = "' . $crsdocs[$x]['doc_name'] . '">';
            echo '<p><i class="' . $fai . '" aria-hidden="true"></i><br>';
                echo $crsdocs[$x]['doc_type'] . '<br>';
                echo $crsdocs[$x]['doc_name'];
            echo '</p>';
        echo '</a>';
    echo '</div>';
    $x++;
}
if ($progspec == 0) {
    echo '<div class="col text-center">';
        echo '<p><i class="fa fa-3x fa-id-card" aria-hidden="true"></i><br>';
            echo 'There are currently no<br>Programme Specification documents<br>uploaded for this Course Map.';
        echo '</p>';
    echo '</div>';
}
if ($crsstrat == 0) {
    echo '<div class="col text-center">';
        echo '<p><i class="fa fa-3x fa-line-chart" aria-hidden="true"></i><br>';
            echo 'There are currently no<br>Course Assessment Strategy documents<br>uploaded for this Course Map.';
        echo '</p>';
    echo '</div>';
}

    echo '</div>';
echo '</div>';

echo $OUTPUT->box_end();
echo $OUTPUT->footer();
