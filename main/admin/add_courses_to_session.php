<?php
/* For licensing terms, see /dokeos_license.txt */

/**
* @package dokeos.admin
*/

// name of the language file that needs to be included
$language_file = 'admin';

// resetting the course id
$cidReset = true;

// including some necessary dokeos files
require '../inc/global.inc.php';

// including additonal libraries
require_once '../inc/lib/xajax/xajax.inc.php';
require_once api_get_path(LIBRARY_PATH).'sessionmanager.lib.php';
$xajax = new xajax();
//$xajax->debugOn();
$xajax->registerFunction('search_courses');

// setting the section (for the tabs)
$this_section = SECTION_PLATFORM_ADMIN;

// Access restrictions
api_protect_admin_script(true);

// setting breadcrumbs
$interbreadcrumb[] = array('url' => 'index.php', 'name' => get_lang('PlatformAdmin'));
$interbreadcrumb[] = array('url' => 'session_list.php','name' => get_lang('SessionList'));

// Database Table Definitions
$tbl_session_rel_course_rel_user = Database::get_main_table(TABLE_MAIN_SESSION_COURSE_USER);
$tbl_session                     = Database::get_main_table(TABLE_MAIN_SESSION);
$tbl_session_rel_user            = Database::get_main_table(TABLE_MAIN_SESSION_USER);
$tbl_session_rel_course          = Database::get_main_table(TABLE_MAIN_SESSION_COURSE);
$tbl_course                      = Database::get_main_table(TABLE_MAIN_COURSE);

// setting the name of the tool
$tool_name = get_lang('SubscribeCoursesToSession');
$id_session = intval($_GET['id_session']);

$add_type = 'multiple';
if (isset($_GET['add_type']) && $_GET['add_type'] != '') {
    $add_type = Security::remove_XSS($_REQUEST['add_type']);
}

if (!api_is_platform_admin()) {
    $sql = 'SELECT session_admin_id FROM ' . Database::get_main_table(TABLE_MAIN_SESSION) . ' WHERE id=' . $id_session;
    $rs = Database::query($sql,__FILE__,__LINE__);
    if (Database::result($rs, 0, 0) != $_user['user_id']) {
        api_not_allowed(true);
    }
}

function search_courses($needle, $type, $selected)
{
	global $tbl_course, $tbl_session_rel_course, $id_session;

	$xajax_response = new XajaxResponse();
	$return = '';
	if (!empty($needle) && !empty($type) && !empty($selected)) {
            // xajax send utf8 datas... datas in db can be non-utf8 datas
            $charset = api_get_setting('platform_charset');
            $needle = api_convert_encoding($needle, $charset, 'utf-8');

            $cond_course_code = '';
            if (!empty($id_session)) {
            $id_session = Database::escape_string($id_session);
                // check course_code from session_rel_course table
                $sql = 'SELECT course_code FROM '.$tbl_session_rel_course.' WHERE id_session ="'.(int)$id_session.'"';
                $res = Database::query($sql,__FILE__,__LINE__);
                $course_codes = '';
                if (Database::num_rows($res) > 0) {
                    while ($row = Database::fetch_row($res)) {
                        $course_codes .= '\'' . $row[0] . '\',';
                    }
                    $course_codes = substr($course_codes, 0, (strlen($course_codes) - 1));

                    $cond_course_code = ' AND course.code NOT IN(' . $course_codes . ') ';
                }
            }

            if ($type == 'single') {
                // search users where username or firstname or lastname begins likes $needle
                $sql = 'SELECT course.code, course.visual_code, course.title, session_rel_course.id_session
                        FROM ' . $tbl_course . ' course
                        LEFT JOIN ' . $tbl_session_rel_course . ' session_rel_course
                            ON course.code = session_rel_course.course_code
                            AND session_rel_course.id_session = ' . intval($id_session) . '
                        WHERE course.visual_code LIKE "' . $needle . '%"
                        OR course.title LIKE "' . $needle . '%"';
            } else {
                $sql = 'SELECT course.code, course.visual_code, course.title
                        FROM ' . $tbl_course . ' course
                        WHERE course.visual_code LIKE "' . $needle . '%" ' . $cond_course_code . ' AND course.code NOT IN(' . $selected . ') ORDER BY course.code;';
            }

            global $_configuration;
            if ($_configuration['multiple_access_urls'] == true) {
                $tbl_course_rel_access_url = Database::get_main_table(TABLE_MAIN_ACCESS_URL_REL_COURSE);
                $access_url_id = api_get_current_access_url_id();
                if ($access_url_id != -1) {
                    if ($type == 'singlee') {
                        $sql = 'SELECT course.code, course.visual_code, course.title, session_rel_course.id_session
                                FROM '.$tbl_course.' course
                                LEFT JOIN '.$tbl_session_rel_course.' session_rel_course
                                    ON course.code = session_rel_course.course_code
                                    AND session_rel_course.id_session = '.intval($id_session).'
                                INNER JOIN '.$tbl_course_rel_access_url.' url_course ON (url_course.course_code=course.code)
                                WHERE access_url_id = '.$access_url_id.' AND (course.visual_code LIKE "'.$needle.'%"
                                OR course.title LIKE "'.$needle.'%" )';
                    } else {
                        $sql = 'SELECT course.code, course.visual_code, course.title
                                FROM '.$tbl_course.' course, '.$tbl_course_rel_access_url.' url_course
                                WHERE url_course.course_code=course.code AND access_url_id = '.$access_url_id.'
                                AND course.visual_code LIKE "'.$needle.'%" '.$cond_course_code . ' AND course.code NOT IN(' . $selected . ') ORDER BY course.code;';
                    }
                }
            }

            $rs = Database::query($sql, __FILE__, __LINE__);
            $course_list = array();
            if ($type == 'single') {
                while($course = Database :: fetch_array($rs)) {
                    $course_list[] = $course['code'];
                    $course_title = str_replace("'", "\'", $course_title);
                    $return .= '<a href="javascript: void(0);" onclick="javascript: add_course_to_session(\'' . $course['code'] . '\',\'' . $course_title . ' (' . $course['visual_code'] . ')' . '\')">' . $course['title'] . ' (' . $course['visual_code'] . ')</a><br />';
                }
                //$xajax_response->addAssign('ajax_list_courses_single', 'innerHTML', api_utf8_encode($return));
                $xajax_response->addAssign('ajax_list_courses_single', 'innerHTML', 'Ed');
            } else {
                $return .= '<select id="origin" name="NoSessionCoursesList[]" multiple="multiple" size="20" style="width:340px;">';
                while($course = Database :: fetch_array($rs)) {
                    $course_list[] = $course['code'];
                    $course_title = str_replace("'", "\'", $course_title);
                    $return .= '<option value="' . $course['code'] . '" title="' . htmlspecialchars($course['title'] . ' (' . $course['visual_code'] . ')', ENT_QUOTES) . '">' . $course['title'] . ' (' . $course['visual_code'] . ')</option>';
                }
                $return .= '</select>';

                $xajax_response->addAssign('ajax_list_courses_multiple', 'innerHTML', api_utf8_encode($return));
            }
	}
	$_SESSION['course_list'] = $course_list;
	return $xajax_response;
}
$xajax->processRequests();

$htmlHeadXtra[] = $xajax->getJavascript('../inc/lib/xajax/');
    $htmlHeadXtra[] = '<script type="text/javascript">
    $(document).ready( function() {
    $( "#course_to_demo" ).keyup(function() {
        ptype="simple";
        val =$(this).val();
        $.ajax({
            url: "'.api_get_path(WEB_PATH).'main/admin/ajax.php?ptype="+ptype+"&val="+val,
            success: function(data){
                   $("#ajax_list_courses_demo").html(data);
                }
        });
    });
    });
    </script>';
$htmlHeadXtra[] = '
<script type="text/javascript">
// Function used for validate what the dive have content
function xajax_search_courses_validate(){
    // Remove the class
    $("#ajax_list_courses_single").removeClass("ajax_list_courses_single");
    //If the div have content
    if($("#ajax_list_courses_single").html()!=""){
        // Add the class to div
        $("#ajax_list_courses_single").addClass("ajax_list_courses_single");
    }
}
    function add_course_to_session (code, content) {
        document.getElementById("course_to_demo").value = "";
        document.getElementById("ajax_list_courses_demo").innerHTML = "";

        destination = document.getElementById("destination");

        for (i=0;i<destination.length;i++) {
            if(destination.options[i].text == content) {
                return false;
            }
        }

        destination.options[destination.length] = new Option(content,code);

        destination.selectedIndex = -1;
        sortOptions(destination.options);
    }
    function remove_item(origin) {
        for(var i = 0 ; i<origin.options.length ; i++) {
            if(origin.options[i].selected) {
                origin.options[i]=null;
                i = i-1;
            }
        }
    }
</script>';


$formSent = 0;
$errorMsg = $firstLetterCourse = $firstLetterSession = '';
$CourseList = $SessionList = array();
$courses = $sessions = array();
$noPHP_SELF = true;

if ($_POST['formSent']) {
    $formSent = $_POST['formSent'];
    $firstLetterCourse = $_POST['firstLetterCourse'];
    $firstLetterSession = $_POST['firstLetterSession'];
    $CourseList = $_POST['SessionCoursesList'];
    if (!is_array($CourseList)) {
        $CourseList = array();
    }
    $nbr_courses = 0;

    $id_coach = Database::query("SELECT id_coach FROM $tbl_session WHERE id = $id_session");
    $id_coach = Database::fetch_array($id_coach);
    $id_coach = $id_coach[0];

    $rs = Database::query("SELECT course_code FROM $tbl_session_rel_course WHERE id_session = $id_session");
    $existingCourses = Database::store_result($rs);

    $sql="SELECT id_user
          FROM $tbl_session_rel_user
          WHERE id_session = $id_session";
    $result=Database::query($sql,__FILE__,__LINE__);
    $UserList=Database::store_result($result);


    foreach ($CourseList as $enreg_course) {
        $enreg_course = Database::escape_string($enreg_course);
        $exists = false;
        foreach ($existingCourses as $existingCourse) {
            if($enreg_course == $existingCourse['course_code']) {               
                $exists = true;
            }
        }
        if (!$exists) {            
            $sql_insert_rel_course= "INSERT INTO $tbl_session_rel_course (id_session, course_code) VALUES ('$id_session', '$enreg_course')";
            Database::query($sql_insert_rel_course ,__FILE__,__LINE__);
            //We add in the existing courses table the current course, to not try to add another time the current course
            $existingCourses[]=array('course_code'=>$enreg_course);
            $nbr_users = 0;
            foreach ($UserList as $enreg_user) {
                $enreg_user = Database::escape_string($enreg_user['id_user']);
                $sql_insert = "INSERT IGNORE INTO $tbl_session_rel_course_rel_user (id_session, course_code, id_user) VALUES ('$id_session', '$enreg_course', '$enreg_user')";
                Database::query($sql_insert, __FILE__, __LINE__);
                if (Database::affected_rows()) {
                    $nbr_users++;
                }
            }
            if ($nbr_users == 0) {
                $sql_insert = "INSERT IGNORE INTO $tbl_session_rel_course_rel_user (id_session, course_code, id_user) VALUES ('$id_session', '$enreg_course', '$id_coach')";
                Database::query($sql_insert, __FILE__, __LINE__);
                if(Database::affected_rows()) {
                    $nbr_users++;
                }
            }
            $tbl_courses = Database::get_main_table(TABLE_MAIN_COURSE);
            $rs_course = Database::query("SELECT code, db_name, directory FROM $tbl_courses WHERE code = '$enreg_course' ");            
            if (Database::num_rows($rs_course) > 0) {
                 while ($row_course = Database::fetch_object($rs_course)) {                                          
                      $tool_table = Database::get_course_table(TABLE_TOOL_LIST,$row_course->db_name);
                      $rs_tool= Database::query("SELECT * FROM $tool_table  WHERE session_id = 0");
                      $tools = array();
                      if (Database::num_rows($rs_tool) > 0) {
                          Database::query("DELETE FROM $tool_table WHERE session_id = $id_session");
                          while ($row_tool = Database::fetch_object($rs_tool)) {
                              $sql = "INSERT INTO $tool_table (
                                `name`, 
                                `link`,
                                `image`, 
                                `visibility`, 
                                `admin`, 
                                `address`, 
                                `added_tool`, 
                                `target`, 
                                `category`, 
                                `session_id`,
                                `popup`
                            ) VALUES (
                                '" . $row_tool->name . "', 
                                '" . $row_tool->link . "', 
                                '" . $row_tool->image . "', 
                                '1', 
                                '" . $row_tool->admin . "', 
                                '" . $row_tool->address . "', 
                                '" . $row_tool->added_tool . "', 
                                '" . $row_tool->target . "', 
                                '" . $row_tool->category . "', 
                                '" . $id_session . "' ,
                                '" . $row_tool->popup . "' 
                            );";
                            Database::query($sql);                              
                          }
                      }                      
                 }
            }            
            Database::query("UPDATE $tbl_session_rel_course SET nbr_users = $nbr_users WHERE id_session = '$id_session' AND course_code = '$enreg_course'",__FILE__,__LINE__);
        }
    }
    foreach ($existingCourses as $existingCourse) {
        if (!in_array($existingCourse['course_code'], $CourseList)) {
            Database::query("DELETE FROM $tbl_session_rel_course WHERE course_code = '" . $existingCourse['course_code'] . "' AND id_session = $id_session");            
            $tbl_courses = Database::get_main_table(TABLE_MAIN_COURSE);
            $enreg_course = $existingCourse['course_code'];
            $rs_course = Database::query("SELECT code, db_name, directory FROM $tbl_courses WHERE code = '$enreg_course' ");
            
            if (Database::num_rows($rs_course) > 0) {    
                 while ($row_course = Database::fetch_object($rs_course)) {                                          
                      $tool_table = Database::get_course_table(TABLE_TOOL_LIST,$row_course->db_name);
                      Database::query("DELETE FROM $tool_table WHERE session_id =$id_session ");
                 }
            }
            Database::query("DELETE FROM $tbl_session_rel_course_rel_user WHERE course_code = '" . $existingCourse['course_code'] . "' AND id_session = $id_session");
        }
    }
    $nbr_courses=count($CourseList);
    Database::query("UPDATE $tbl_session SET nbr_courses = $nbr_courses WHERE id = '$id_session'",__FILE__,__LINE__);
    if (isset($_GET['add'])) {
        //header('Location: add_users_to_session.php?id_session='.$id_session.'&amp;add=true');
        //else
        //header('Location: resume_session.php?id_session='.$id_session);
        //add_users_to_session.php?page=resume_session.php&amp;id_session=3
        header('Location: add_users_to_session.php?page=resume_session.php&id_session='.$id_session);
        exit();
    }
    header('Location: '.$_GET['page'].'?id_session='.$id_session);
    exit();
}

// display the dokeos header
Display::display_header($tool_name);

// display the tool title
// api_display_tool_title($tool_name);

if ($add_type == 'multiple') {
    $link_add_type_unique = '<a href="'.api_get_self().'?id_session='.$id_session.'&amp;add='.Security::remove_XSS($_GET['add']).'&amp;add_type=unique">'.Display::return_icon('pixel.gif',get_lang('SessionAddTypeUnique'), array('class' => 'toolactionplaceholdericon toolactionsingle')).get_lang('SessionAddTypeUnique').'</a>';
    //$link_add_type_multiple = Display::return_icon('pixel.gif',get_lang('SessionAddTypeMultiple'), array('class' => 'toolactionplaceholdericon toolactionmultiple')).get_lang('SessionAddTypeMultiple').' ';
} else {
    //$link_add_type_unique = Display::return_icon('pixel.gif',get_lang('SessionAddTypeUnique'), array('class' => 'toolactionplaceholdericon toolactionsingle')).get_lang('SessionAddTypeUnique').'&nbsp;&nbsp;&nbsp;';
    $link_add_type_multiple = '<a href="'.api_get_self().'?id_session='.$id_session.'&amp;add='.Security::remove_XSS($_GET['add']).'&amp;add_type=multiple">'.Display::return_icon('pixel.gif',get_lang('SessionAddTypeMultiple'), array('class' => 'toolactionplaceholdericon toolactionmultiple')).get_lang('SessionAddTypeMultiple').'</a>';
}


// the form header
$session_info = SessionManager::fetch($id_session);
echo '<div class="actions">';
/*echo '<a href="'.api_get_path(WEB_CODE_PATH).'admin/catalogue_management.php">' . Display::return_icon('pixel.gif',get_lang('Catalogue'), array('class' => 'toolactionplaceholdericon toolactioncatalogue')) . get_lang('Catalogue') . '</a>';
echo '<a href="'.api_get_path(WEB_CODE_PATH).'admin/topic_list.php">' . Display :: return_icon('pixel.gif', get_lang('Topics'),array('class' => 'toolactionplaceholdericon toolactiontopic')) . get_lang('Topics') . '</a>';
echo '<a href="'.api_get_path(WEB_CODE_PATH).'admin/programme_list.php">' . Display :: return_icon('pixel.gif', get_lang('Programmes'),array('class' => 'toolactionplaceholdericon toolactionprogramme')) . get_lang('Programmes') . '</a>';*/
echo '<a href="'.api_get_path(WEB_CODE_PATH).'admin/session_list.php">' . Display :: return_icon('pixel.gif', get_lang('SessionList'),array('class' => 'toolactionplaceholdericon toolactionsession')) . get_lang('Sessions') . '</a>';
echo $link_add_type_unique . $link_add_type_multiple;
echo '</div>';

// Start content page
echo '<div id="content">';

echo '<div class="row"><div class="form_header">' . $tool_name . ' (' . $session_info['name'] . ')</div></div><br />';



/*$sql = 'SELECT COUNT(1) FROM '.$tbl_course;
$rs = Database::query($sql, __FILE__, __LINE__);
$count_courses = Database::result($rs, 0, 0);*/

$ajax_search = $add_type == 'unique' ? true : false;
$nosessionCourses = $sessionCourses = array();
if ($ajax_search) {
    $sql="SELECT code, title, visual_code, id_session
          FROM $tbl_course course
          INNER JOIN $tbl_session_rel_course session_rel_course
              ON course.code = session_rel_course.course_code
            AND session_rel_course.id_session = ".intval($id_session)."
          ORDER BY ".(sizeof($courses)?"(code IN(".implode(',',$courses).")) DESC,":"")." title";

    global $_configuration;
    if ($_configuration['multiple_access_urls']==true) {
        $tbl_course_rel_access_url= Database::get_main_table(TABLE_MAIN_ACCESS_URL_REL_COURSE);
        $access_url_id = api_get_current_access_url_id();
        if ($access_url_id != -1){
            $sql="SELECT code, title, visual_code, id_session
            FROM $tbl_course course
            INNER JOIN $tbl_session_rel_course session_rel_course
                ON course.code = session_rel_course.course_code
                AND session_rel_course.id_session = ".intval($id_session)."
                INNER JOIN $tbl_course_rel_access_url url_course ON (url_course.course_code=course.code)
                WHERE access_url_id = $access_url_id
            ORDER BY ".(sizeof($courses)?"(code IN(".implode(',',$courses).")) DESC,":"")." title";
        }
    }

    $result=Database::query($sql,__FILE__,__LINE__);
    $Courses=Database::store_result($result);

    foreach($Courses as $course) {
        $sessionCourses[$course['code']] = $course ;
    }
} else {
    $sql="SELECT code, title, visual_code, id_session
          FROM $tbl_course course
          LEFT JOIN $tbl_session_rel_course session_rel_course
              ON course.code = session_rel_course.course_code
              AND session_rel_course.id_session = ".intval($id_session)."
          ORDER BY ".(sizeof($courses)?"(code IN(".implode(',',$courses).")) DESC,":"")." title";

    global $_configuration;
    if ($_configuration['multiple_access_urls']==true) {
        $tbl_course_rel_access_url= Database::get_main_table(TABLE_MAIN_ACCESS_URL_REL_COURSE);
        $access_url_id = api_get_current_access_url_id();
        if ($access_url_id != -1){
            $sql="SELECT code, title, visual_code, id_session
                  FROM $tbl_course course
                  LEFT JOIN $tbl_session_rel_course session_rel_course
                      ON course.code = session_rel_course.course_code
                      AND session_rel_course.id_session = ".intval($id_session)."
                  INNER JOIN $tbl_course_rel_access_url url_course ON (url_course.course_code=course.code)
                  WHERE access_url_id = $access_url_id
                  ORDER BY ".(sizeof($courses)?"(code IN(".implode(',',$courses).")) DESC,":"")." title";
        }
    }

    $result=Database::query($sql,__FILE__,__LINE__);
    $Courses=Database::store_result($result);
    foreach($Courses as $course) {
        if ($course['id_session'] == $id_session) {
            $sessionCourses[$course['code']] = $course ;
        } else {
            $nosessionCourses[$course['code']] = $course ;
        }
    }
}
unset($Courses);
?>

<form name="formulaire" method="post" action="<?php echo api_get_self(); ?>?page=<?php echo Security::remove_XSS($_GET['page']) ?>&id_session=<?php echo $id_session; ?><?php if(!empty($_GET['add'])) echo '&add=true' ; ?>" style="margin:0px;" <?php if($ajax_search){echo ' onsubmit="valide();"';}?>>
    <input type="hidden" name="formSent" value="1" />
<?php
if (!empty($errorMsg)) {
    Display::display_normal_message($errorMsg); //main API
}
?>
    <table border="0" cellpadding="5" cellspacing="0" width="100%" align="center">
        <tr>
            <td width="45%" align="center"><b><?php echo get_lang('CourseListInPlatform') ?> :</b></td>
            <td width="10%">&nbsp;</td>
            <td align="center" width="45%"><b><?php echo get_lang('CourseListInSession') ?> :</b></td>
        </tr>
<?php if($add_type == 'multiple') { ?>
        <tr>
            <td width="45%" align="center">
<?php echo get_lang('FirstLetterCourse'); ?> :
                <select name="firstLetterCourse" onchange = "xajax_search_courses(this.value,'multiple', getSelected())">
                   <option value="%">--</option>
                   <?php
                   echo Display :: get_alphabet_options();
                   echo Display :: get_numeric_options(0,9,'');
                   ?>
                </select>
            </td>
            <td>&nbsp;</td>
        </tr>
<?php } ?>
        <tr>
            <td width="45%" align="center">
<?php
if (!($add_type == 'multiple')) {
?>
                <!--
                <input type="text" id="course_to_add" onkeyup="xajax_search_courses(this.value,'single');xajax_search_courses_validate();" />
                <div id="ajax_list_courses_single"></div>
                -->
                <input type="text" id="course_to_demo" />
                <div id="ajax_list_courses_demo"></div>
<?php
} else {
?>
                <div id="ajax_list_courses_multiple">
                    <!--[if IE 7]>
                    <div class="selectt-ajax_list_courses_multiple" style="width:320px;"> 
                    <![endif]-->   
                    <select id="origin" name="NoSessionCoursesList[]" multiple="multiple" size="20" style="width:320px;"> <?php
    foreach ($nosessionCourses as $enreg) {
        
?>
                        <option value="<?php echo $enreg['code']; ?>" <?php echo 'title="' . htmlspecialchars($enreg['title'] . ' (' . $enreg['visual_code'] . ')', ENT_QUOTES) . '"'; if (in_array($enreg['code'],$CourseList)) echo 'selected="selected"'; ?>><?php echo $enreg['title'] . ' (' . $enreg['visual_code'] . ')'; ?></option>
<?php
    }
?>
                    </select>
                    <!--[if IE 7]>
                    </div>
                    <![endif]-->
                </div>
<?php
}
unset($nosessionCourses);
?>
            </td>
            <td width="10%" valign="middle" align="center">
<?php
if ($ajax_search) {
?>
                <button class="button-blue" type="button" onclick="remove_item(document.getElementById('destination'))"><img src="<?php echo api_get_path(WEB_CSS_PATH).api_get_setting('stylesheets')?>/images/action/arrow-white-left.png"/></button>
<?php
} else {
?>
                <button class="button-blue" type="button" onclick="moveItem(document.getElementById('origin'), document.getElementById('destination'))" onclick="moveItem(document.getElementById('origin'), document.getElementById('destination'))"><img src="<?php echo api_get_path(WEB_CSS_PATH).api_get_setting('stylesheets')?>/images/action/arrow-white-right.png"/></button>
                <br /><br />
                <button class="button-blue" type="button" onclick="moveItem(document.getElementById('destination'), document.getElementById('origin'))" onclick="moveItem(document.getElementById('destination'), document.getElementById('origin'))"><img src="<?php echo api_get_path(WEB_CSS_PATH).api_get_setting('stylesheets')?>/images/action/arrow-white-left.png"/></button>
<?php
}
?>  
            </td>
            <td width="45%" align="center">
                <select id='destination' name="SessionCoursesList[]" multiple="multiple" size="20" style="width:320px;">
<?php
foreach ($sessionCourses as $enreg) {
?>
                    <option value="<?php echo $enreg['code']; ?>" title="<?php echo htmlspecialchars($enreg['title'].' ('.$enreg['visual_code'].')',ENT_QUOTES); ?>"><?php echo $enreg['title'].' ('.$enreg['visual_code'].')'; ?></option>
<?php
}
unset($sessionCourses);
?>
                </select>
            </td>
        </tr>
        <tr>
            <td colspan="3" align="right">
                <br/>
	<?php
	if(isset($_GET['add']))
	{
		echo '<button style="float:none;" class="save" type="button" value="" onclick="valide()" >'.get_lang('NextStep').'</button>';
	}
	else
	{
		echo '<button style="float:none;" class="save" type="button" value="" onclick="valide()" >'.get_lang('SubscribeCoursesToSession').'</button>';
	}
	?>
            </td>
        </tr>
    </table>
</form>
<script type="text/javascript">
    function moveItem(origin , destination) {
        for(var i = 0 ; i<origin.options.length ; i++) {
            if(origin.options[i].selected) {
                destination.options[destination.length] = new Option(origin.options[i].text,origin.options[i].value);
                origin.options[i]=null;
                i = i-1;
            }
        }
        destination.selectedIndex = -1;
        sortOptions(destination.options);
    }

    function sortOptions(options) {
        newOptions = new Array();

        for (i = 0 ; i<options.length ; i++) {
            newOptions[i] = options[i];
        }

        newOptions = newOptions.sort(mysort);
        options.length = 0;

        for(i = 0 ; i < newOptions.length ; i++){
            options[i] = newOptions[i];
        }
    }

    function mysort(a, b){
        if(a.text.toLowerCase() > b.text.toLowerCase()){
            return 1;
        }
        if(a.text.toLowerCase() < b.text.toLowerCase()){
            return -1;
        }
        return 0;
    }

    function valide(){
        var options = document.getElementById('destination').options;
        for (i = 0 ; i<options.length ; i++)
            options[i].selected = true;

        document.forms.formulaire.submit();
    }
    
    function getSelected() {
        var ret = new Array();
        var options = $('select#destination option');
        for (i = 0 ; i<options.length ; i++) {
            ret[i] = options[i].value;
        }
        return "'" + ret.join("', '") + "'";
    }
</script>
<?php
// End content page
echo '</div>';
/*
==============================================================================
		FOOTER
==============================================================================
*/
Display::display_footer();
