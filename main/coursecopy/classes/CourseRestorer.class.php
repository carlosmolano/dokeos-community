<?php // $Id: CourseRestorer.class.php 22200 2009-07-17 19:47:58Z iflorespaz $
/*
==============================================================================
    Dokeos - elearning and course management software

    Copyright (c) 2008 Dokeos SPRL
    Copyright (c) 2003 Ghent University (UGent)
    Copyright (c) 2001 Universite catholique de Louvain (UCL)
    Copyright (c) Bart Mollet (bart.mollet@hogent.be)

    For a full list of contributors, see "credits.txt".
    The full license can be read in "license.txt".

    This program is free software; you can redistribute it and/or
    modify it under the terms of the GNU General Public License
    as published by the Free Software Foundation; either version 2
    of the License, or (at your option) any later version.

    See the GNU General Public License for more details.

    Contact address: Dokeos, 44 rue des palais, B-1030 Brussels, Belgium
    Mail: info@dokeos.com
==============================================================================
*/

require_once 'Course.class.php';
require_once 'Event.class.php';
require_once 'Link.class.php';
require_once 'ToolIntro.class.php';
require_once 'LinkCategory.class.php';
require_once 'ForumCategory.class.php';
require_once 'Forum.class.php';
require_once 'ForumTopic.class.php';
require_once 'ForumPost.class.php';
require_once 'CourseDescription.class.php';
require_once 'Learnpath.class.php';
require_once 'Survey.class.php';
require_once 'SurveyQuestion.class.php';
//require_once 'mkdirr.php';
//require_once 'rmdirr.php';
require_once 'Glossary.class.php';
require_once 'wiki.class.php';
require_once 'Certificate.class.php';
require_once 'Evaluation.class.php';
require_once api_get_path(LIBRARY_PATH).'fileUpload.lib.php';
require_once api_get_path(LIBRARY_PATH).'fileManage.lib.php';
require_once api_get_path(LIBRARY_PATH).'document.lib.php';

define('FILE_SKIP', 1);
define('FILE_RENAME', 2);
define('FILE_OVERWRITE', 3);

/**
* public function to replace old course code in html links
* @param string old course code
* @param string destination course code
* @param string html text in which the links must be updated
**/
function replace_old_course_links($old_course_code, $new_course_code, $html_text) {
    return str_replace('/' . $old_course_code . '/', '/' . $new_course_code . '/', $html_text);
}


/**
 * Class to restore items from a course object to a Dokeos-course
 * @author Bart Mollet <bart.mollet@hogent.be>
 * @package dokeos.backup
 */
class CourseRestorer {

    /**
     * The CourseBuilder object
     */
    public $course;

    /**
     * What to do with files with same name (FILE_SKIP, FILE_RENAME or
     * FILE_OVERWRITE)
     */
    public $file_option;

    /**
     * Destination course code
     * */
    public $destination_course_code;

    /**
     * Create a new CourseRestorer
     */
    public function __construct($course) {
        $this->course = $course;
        $this->file_option = FILE_RENAME;
    }

    /**
     * Set the file-option
     * @param constant $options What to do with files with same name (FILE_SKIP,
     * FILE_RENAME or FILE_OVERWRITE)
     */
    public function set_file_option($option) {
        $this->file_option = $option;
    }

    /**
     * Restore a course.
     * @param string $destination_course_code The code of the Dokeos-course in
     * which the resources should be stored. Default: Current Dokeos-course.
     */
    public function restore($destination_course_code = '', $session_id = 0) {
        $this->destination_course_code = $destination_course_code;

        if ($destination_course_code == '') {
            $course_info = api_get_course_info();
            $this->course->destination_db = $course_info['dbName'];
            $this->course->destination_path = $course_info['path'];
            $this->destination_course_code = api_get_course_id();
        } else {
            $course_info = Database :: get_course_info($destination_course_code);
            $this->course->destination_db = $course_info['database'];
            $this->course->destination_path = $course_info['directory'];
        }
        // platform encoding
        $course_charset = $this->course->encoding;

        if (!empty($session_id)) {
            $this->restore_documents($session_id, $destination_course_code);
            $this->restore_quizzes($session_id);
            $this->restore_evaluations($session_id);
            $this->restore_glossary($session_id);
            $this->restore_learnpaths($session_id);
            $this->restore_links($session_id);
            $this->restore_course_descriptions($session_id);
            $this->restore_wiki();
            $this->restore_certificates($session_id);
		} else {
            $this->restore_links();
            $this->restore_tool_intro();
            $this->restore_events();
            $this->restore_announcements();
            $this->restore_documents();
            $this->restore_scorm_documents();
            $this->restore_course_descriptions();
            $this->restore_quizzes(); // after restore_documents! (for correct import of sound/video)
            $this->restore_evaluations(); 
            $this->restore_learnpaths();
            $this->restore_surveys();
            $this->restore_student_publication();
            $this->restore_glossary();
            $this->restore_wiki();
            $this->restore_forums();
            $this->restore_certificates();
        }


        // item properties table
        $table = Database :: get_course_table(TABLE_ITEM_PROPERTY, $this->course->destination_db);
        $condition_session = "";
        if (!empty($session_id)) {
            $condition_session = " , id_session='" . intval($session_id) . "'";
        }
        // linked-resources table
        $table_linked_resources = Database :: get_course_table(TABLE_LINKED_RESOURCES, $this->course->destination_db);

        foreach ($this->course->resources as $type => $resources) {
            if (is_array($resources)) {
                foreach ($resources as $id => $resource) {
                    // Restore the item properties
                    $item_properties = $resource->item_properties;
                    if (is_array($item_properties)) {
                        foreach ($item_properties as $property) {
                            // First check if there isn't allready a record for this resource
                            $sql = "SELECT * FROM $table WHERE tool = '" . $property['tool'] . "' AND ref = '" . $resource->destination_id . "'";
                            $res = Database::query($sql, __FILE__, __LINE__);
                            if (Database::num_rows($res) == 0) {
                                // The to_group_id and to_user_id are set to default values as users/groups possibly not exist in the target course
                                $sql = "INSERT INTO $table SET
                                        tool = '" . Database::escape_string($property['tool']) . "',
                                        insert_user_id = '" . Database::escape_string($property['insert_user_id']) . "',
                                        insert_date = '" . Database::escape_string($property['insert_date']) . "',
                                        lastedit_date = '" . Database::escape_string($property['lastedit_date']) . "',
                                        ref = '" . Database::escape_string($resource->destination_id) . "',
                                        lastedit_type = '" . Database::escape_string($property['lastedit_type']) . "',
                                        lastedit_user_id = '" . Database::escape_string($property['lastedit_user_id']) . "',
                                        visibility = '" . Database::escape_string($property['visibility']) . "',
                                        start_visible = '" . Database::escape_string($property['start_visible']) . "',
                                        end_visible = '" . Database::escape_string($property['end_visible']) . "',
                                        to_user_id = '" . Database::escape_string($property['to_user_id']) . "',
                                        to_group_id = '0' $condition_session";
                                ;
                                Database::query($sql, __FILE__, __LINE__);
                            }
                        }
                    }
                    // Restore the linked-resources
                    if (isset($resource->linked_resources)) {
                        $linked_resources = $resource->get_linked_resources();
                        if (is_array($linked_resources)) {
                            foreach ($linked_resources as $to_type => $to_ids) {
                                if (is_array($to_ids)) {
                                    foreach ($to_ids as $index => $to_id) {
                                        $to_resource = $this->course->resources[$to_type][$to_id];
                                        $sql = "INSERT INTO " . $table_linked_resources . " SET source_type = '" . $type . "', source_id = '" . $resource->destination_id . "', resource_type='" . $to_type . "', resource_id='" . $to_resource->destination_id . "' ";
                                        Database::query($sql, __FILE__, __LINE__);
                                    }
                                }
                            }
                        }
                    }
                }
            }
        }
    }

    /*
     * Clean course
     */

    public function clean_course() {
        // Clean resource of type link category
        $tbl_resource = Database :: get_course_table(TABLE_LINK_CATEGORY, $this->course->destination_db);
        $sql_resource = "TRUNCATE TABLE $tbl_resource";
        Database::query($sql_resource, __FILE__, __LINE__);

        // Clean resource of type links
        $tbl_resource = Database :: get_course_table(TABLE_LINK, $this->course->destination_db);
        $sql_resource = "TRUNCATE TABLE $tbl_resource";
        Database::query($sql_resource, __FILE__, __LINE__);

        // Clean resource of type announcement
        $tbl_resource = Database :: get_course_table(TABLE_ANNOUNCEMENT, $this->course->destination_db);
        $sql_resource = "TRUNCATE TABLE $tbl_resource";
        Database::query($sql_resource, __FILE__, __LINE__);

        // Clean resource of type glossary
        $tbl_resource = Database :: get_course_table(TABLE_GLOSSARY, $this->course->destination_db);
        $sql_resource = "TRUNCATE TABLE $tbl_resource";
        Database::query($sql_resource, __FILE__, __LINE__);

        // Clean resource of type forum category
        $tbl_resource = Database :: get_course_table(TABLE_FORUM_CATEGORY, $this->course->destination_db);
        $sql_resource = "TRUNCATE TABLE $tbl_resource";
        Database::query($sql_resource, __FILE__, __LINE__);

        // Clean resource of type forum
        $tbl_resource = Database :: get_course_table(TABLE_FORUM, $this->course->destination_db);
        $sql_resource = "TRUNCATE TABLE $tbl_resource";
        Database::query($sql_resource, __FILE__, __LINE__);

        // Clean resource of type forum post
        $tbl_resource = Database :: get_course_table(TABLE_FORUM_POST, $this->course->destination_db);
        $sql_resource = "TRUNCATE TABLE $tbl_resource";
        Database::query($sql_resource, __FILE__, __LINE__);

        // Clean resource of type forum thread
        $tbl_resource = Database :: get_course_table(TABLE_FORUM_THREAD, $this->course->destination_db);
        $sql_resource = "TRUNCATE TABLE $tbl_resource";
        Database::query($sql_resource, __FILE__, __LINE__);
    }

    /**
     * Restore documents
     */
    public function restore_documents($session_id = 0, $destination_course_code = '') {
        if ($this->course->has_resources(RESOURCE_DOCUMENT)) {
            $table = Database :: get_course_table(TABLE_DOCUMENT, $this->course->destination_db);
            $resources = $this->course->resources;
            $destination_course['dbName'] = $this->course->destination_db;
            /* echo '<pre>'; echo $this->course->backup_path; echo '<br>'; */
            foreach ($resources[RESOURCE_DOCUMENT] as $id => $document) {
                $path = api_get_path(SYS_COURSE_PATH) . $this->course->destination_path . '/';
                $perm = api_get_setting('permissions_for_new_directories');
                $perm = octdec(!empty($perm) ? $perm : 0770);
                $dirs = explode('/', dirname($document->path));
                if (count($dirs) == 1) {
                    if ($this->file_type == FOLDER) {
                        $new = substr($document->path, 8);
                        $created_dir = create_unexisting_directory($destination_course, api_get_user_id(), 0, 0, $path . 'document', $new, basename($new));
                    }
                } else {
                    $my_temp = '';
                    for ($i = 1; $i <= count($dirs); $i++) {
                        $my_temp .= $dirs[$i];
                        if (!is_dir($path . 'document/' . $my_temp)) {
                            $sql = "SELECT id FROM " . $table . " WHERE path='/" . Database::escape_string($my_temp) . "'";
                            //echo '<br>';
                            $res = Database::query($sql, __FILE__, __LINE__);
                            $num_result = Database::num_rows($res);
                            if ($num_result == 0) {
                                $created_dir = create_unexisting_directory($destination_course, api_get_user_id(), 0, 0, $path . 'document', '/' . $my_temp, basename($my_temp));
                            }
                        }
                        $my_temp .= '/';
                    }
                }

                if ($document->file_type == DOCUMENT) {
                    if (file_exists($path . $document->path)) {
                        switch ($this->file_option) {
                            case FILE_OVERWRITE :
                                $this->course->backup_path . '/' . $document->path;
                                //copy($this->course->backup_path.'/'.$document->path, $path.$document->path);
                                $origin_path = $this->course->backup_path . '/' . $document->path;
                                if (file_exists($origin_path)) {
                                    copy($origin_path, $path . $document->path);
                                }

                                $file_info = pathinfo($path . $document->path);
                                if (in_array($file_info['extension'], array('html', 'htm'))) {
                                    $content = file_get_contents($path . $document->path);
                                    $content = DocumentManager::replace_urls_inside_content_html_from_copy_course($content, $this->course->code, $this->course->destination_path);
                                    $result = file_put_contents($path . $document->path, $content);
                                }

                                $sql = "SELECT id FROM " . $table . " WHERE path='/" . substr($document->path, 9) . "'";
                                $res = Database::query($sql, __FILE__, __LINE__);
                                $obj = Database::fetch_object($res);
                                $this->course->resources[RESOURCE_DOCUMENT][$id]->destination_id = $obj->id;
                                $sql = "UPDATE " . $table . " SET comment = '" . Database::escape_string($document->comment) . "', title='" . Database::escape_string($document->title) . "', size='" . $document->size . ", display_order= '" . $document->display_order . "', readonly= '" . $document->readonly . "', is_template= '" . $document->is_template . "'' WHERE id = '" . $obj->id . "'";
                                Database::query($sql, __FILE__, __LINE__);
                                break;
                            case FILE_SKIP :
                                $sql = "SELECT id FROM " . $table . " WHERE path='/" . Database::escape_string(substr($document->path, 9)) . "'";
                                $res = Database::query($sql, __FILE__, __LINE__);
                                $obj = Database::fetch_object($res);
                                $this->course->resources[RESOURCE_DOCUMENT][$id]->destination_id = $obj->id;
                                break;
                            case FILE_RENAME :
                                $i = 1;
                                $ext = explode('.', basename($document->path));
                                if (count($ext) > 1) {
                                    $ext = array_pop($ext);
                                    $file_name_no_ext = substr($document->path, 0, - (strlen($ext) + 1));
                                    $ext = '.' . $ext;
                                } else {
                                    $ext = '';
                                    $file_name_no_ext = $document->path;
                                }
                                $new_file_name = $file_name_no_ext . '_' . $i . $ext;
                                $file_exists = file_exists($path . $new_file_name);
                                while ($file_exists) {
                                    $i++;
                                    $new_file_name = $file_name_no_ext . '_' . $i . $ext;
                                    $file_exists = file_exists($path . $new_file_name);
                                }


                                if (!empty($session_id)) {

                                    $document_path = explode('/', $document->path, 3);
                                    $course_path = $path;        // "/var/www/wiener/courses/"
                                    $orig_base_folder = $document_path[1];
                                    $orig_base_path = $course_path . $document_path[0] . '/' . $document_path[1];

                                    if (is_dir($orig_base_path)) {

                                        $new_base_foldername = $orig_base_folder; // e.g: "carpeta1"
                                        $new_base_path = $orig_base_path;   // e.g: "/var/www/wiener/courses/CURSO4/document/carpeta1"

                                        if ($_SESSION['orig_base_foldername'] != $new_base_foldername) {
                                            unset($_SESSION['new_base_foldername']);
                                            unset($_SESSION['orig_base_foldername']);
                                            unset($_SESSION['new_base_path']);
                                        }

                                        $folder_exists = file_exists($new_base_path);
                                        if ($folder_exists) {
                                            $_SESSION['orig_base_foldername'] = $new_base_foldername;   // e.g: carpeta1 in session
                                            $x = '';
                                            while ($folder_exists) {
                                                $x = $x + 1;
                                                $new_base_foldername = $document_path[1] . '_' . $x;
                                                $new_base_path = $orig_base_path . '_' . $x;
                                                if ($_SESSION['new_base_foldername'] == $new_base_foldername)
                                                    break;
                                                $folder_exists = file_exists($new_base_path);
                                            }
                                            $_SESSION['new_base_foldername'] = $new_base_foldername;
                                            $_SESSION['new_base_path'] = $new_base_path;
                                        }

                                        if (isset($_SESSION['new_base_foldername']) && isset($_SESSION['new_base_path'])) {
                                            $new_base_foldername = $_SESSION['new_base_foldername'];
                                            $new_base_path = $_SESSION['new_base_path'];
                                        }

                                        $dest_document_path = $new_base_path . '/' . $document_path[2];  // e.g: "/var/www/wiener/courses/CURSO4/document/carpeta1_1/subcarpeta1/collaborative.png"
                                        $basedir_dest_path = dirname($dest_document_path);    // e.g: "/var/www/wiener/courses/CURSO4/document/carpeta1_1/subcarpeta1"
                                        $dest_filename = basename($dest_document_path);      // e.g: "collaborative.png"
                                        $base_path_document = $course_path . $document_path[0];   // e.g: "/var/www/wiener/courses/CURSO4/document"
                                        $course_info = api_get_course_info($destination_course_code);
                                        $path_title = '/' . $new_base_foldername . '/' . $document_path[2];

                                        copy_folder_course_session($basedir_dest_path, $base_path_document, $session_id, $course_info, $document);


                                        //copy($course_path.$document->path, $dest_document_path);
                                        if (file_exists($course_path . $document->path)) {
                                            copy($course_path . $document->path, $dest_document_path);
                                        }

                                        //Replace old course code with the new destination code see BT#1985
                                        if (file_exists($dest_document_path)) {
                                            $file_info = pathinfo($dest_document_path);
                                            if (in_array($file_info['extension'], array('html', 'htm'))) {
                                                $content = file_get_contents($dest_document_path);
                                                $content = DocumentManager::replace_urls_inside_content_html_from_copy_course($content, $this->course->code, $this->course->destination_path);
                                                $result = file_put_contents($dest_document_path, $content);
                                            }
                                        }

                                        $sql = "INSERT INTO $table SET path = '$path_title', comment = '" . Database::escape_string($document->comment) . "', title = '" . Database::escape_string(basename($path_title)) . "' , filetype='" . $document->file_type . "', size= '" . $document->size . "', display_order= '" . $document->display_order . "', readonly= '" . $document->readonly . "', is_template= '" . $document->is_template . "', session_id = '$session_id'";

                                        Database::query($sql, __FILE__, __LINE__);
                                        $this->course->resources[RESOURCE_DOCUMENT][$id]->destination_id = Database::insert_id();
                                    } else {
                                        //copy($path.$document->path, $path.$new_file_name);

                                        if (file_exists($path . $document->path)) {
                                            copy($path . $document->path, $path . $new_file_name);
                                        }

                                        //Replace old course code with the new destination code see BT#1985
                                        if (file_exists($path . $new_file_name)) {
                                            $file_info = pathinfo($path . $new_file_name);
                                            if (in_array($file_info['extension'], array('html', 'htm'))) {
                                                $content = file_get_contents($path . $new_file_name);
                                                $content = DocumentManager::replace_urls_inside_content_html_from_copy_course($content, $this->course->code, $this->course->destination_path);
                                                $result = file_put_contents($path . $new_file_name, $content);
                                            }
                                        }

                                        $sql = "INSERT INTO " . $table . " SET path = '/" . Database::escape_string(substr($new_file_name, 9)) . "', comment = '" . Database::escape_string($document->comment) . "', title = '" . Database::escape_string($document->title) . "' , filetype='" . $document->file_type . "', size= '" . $document->size . "', display_order= '" . $document->display_order . "', readonly= '" . $document->readonly . "', is_template= '" . $document->is_template . "', session_id = '$session_id'";
                                        Database::query($sql, __FILE__, __LINE__);
                                        $this->course->resources[RESOURCE_DOCUMENT][$id]->destination_id = Database::insert_id();
                                    }
                                } else {

                                    copy($this->course->backup_path . '/' . $document->path, $path . $new_file_name);

                                    //Replace old course code with the new destination code see BT#1985
                                    if (file_exists($path . $new_file_name)) {
                                        $file_info = pathinfo($path . $new_file_name);
                                        if (in_array($file_info['extension'], array('html', 'htm'))) {
                                            $content = file_get_contents($path . $new_file_name);
                                            $content = DocumentManager::replace_urls_inside_content_html_from_copy_course($content, $this->course->code, $this->course->destination_path);
                                            $result = file_put_contents($path . $new_file_name, $content);
                                        }
                                    }

                                    $sql = "INSERT INTO " . $table . " SET path = '/" . Database::escape_string(substr($new_file_name, 9)) . "', comment = '" . Database::escape_string($document->comment) . "', title = '" . Database::escape_string($document->title) . "' , filetype='" . $document->file_type . "', size= '" . $document->size . "', display_order= '" . $document->display_order . "', readonly= '" . $document->readonly . "', is_template= '" . $document->is_template . "'";
                                    Database::query($sql, __FILE__, __LINE__);
                                    $this->course->resources[RESOURCE_DOCUMENT][$id]->destination_id = Database::insert_id();
                                }
                                break;
                        } // end switch
                    } // end if file exists
                    else {
                        //make sure the source file actually exists
                        //echo $this->course->backup_path.'/'.$document->path;
                        if (is_file($this->course->backup_path . '/' . $document->path) && is_readable($this->course->backup_path . '/' . $document->path) && is_dir(dirname($path . $document->path)) && is_writeable(dirname($path . $document->path))) {
                            copy($this->course->backup_path . '/' . $document->path, $path . $document->path);

                            //Replace old course code with the new destination code see BT#1985
                            if (file_exists($path . $document->path)) {
                                $file_info = pathinfo($path . $document->path);
                                if (in_array($file_info['extension'], array('html', 'htm'))) {
                                    $content = file_get_contents($path . $document->path);
                                    $content = DocumentManager::replace_urls_inside_content_html_from_copy_course($content, $this->course->code, $this->course->destination_path);
                                    $result = file_put_contents($path . $document->path, $content);
                                }
                            }

                            $sql = "INSERT INTO " . $table . " SET path = '/" . substr($document->path, 9) . "', comment = '" . Database::escape_string($document->comment) . "', title = '" . Database::escape_string($document->title) . "' , filetype='" . $document->file_type . "', size= '" . $document->size . "', display_order= '" . $document->display_order . "', readonly= '" . $document->readonly . "', is_template= '" . $document->is_template . "'";
                            Database::query($sql, __FILE__, __LINE__);
                            $this->course->resources[RESOURCE_DOCUMENT][$id]->destination_id = Database::insert_id();
                        } else {
                            if (is_file($this->course->backup_path . '/' . $document->path) && is_readable($this->course->backup_path . '/' . $document->path)) {
                                error_log('Course copy generated an ignoreable error while trying to copy ' . $this->course->backup_path . '/' . $document->path . ': file not found');
                            }
                            if (!is_dir(dirname($path . $document->path))) {
                                error_log('Course copy generated an ignoreable error while trying to copy to ' . dirname($path . $document->path) . ': directory not found');
                            }
                            if (!is_writeable(dirname($path . $document->path))) {
                                error_log('Course copy generated an ignoreable error while trying to copy to ' . dirname($path . $document->path) . ': directory not writeable');
                            }
                        }
                    } // end file doesn't exist
                } else {
                    /* $sql = "SELECT id FROM ".$table." WHERE path = '/".Database::escape_string(substr($document->path, 9))."'";
                      $res = Database::query($sql,__FILE__,__LINE__);
                      if( Database::num_rows($res)> 0)
                      {
                      $obj = Database::fetch_object($res);
                      $this->course->resources[RESOURCE_DOCUMENT][$id]->destination_id = $obj->id;
                      }
                      else
                      {
                      $sql = "INSERT INTO ".$table." SET path = '/".Database::escape_string(substr($document->path, 9))."', comment = '".Database::escape_string($document->comment)."', title = '".Database::escape_string($document->title)."' , filetype='".$document->file_type."', size= '".$document->size."', display_order= '".$document->display_order."', readonly= '".$document->readonly."', is_template= '".$document->is_template."'";
                      Database::query($sql, __FILE__, __LINE__);
                      $this->course->resources[RESOURCE_DOCUMENT][$id]->destination_id = Database::insert_id();
                      } */
                } // end folder
            } // end for each
            // Delete sessions for the copy the new folder in session
            unset($_SESSION['new_base_foldername']);
            unset($_SESSION['orig_base_foldername']);
            unset($_SESSION['new_base_path']);
        }
    }

    /**
     * Restore scorm documents
     * TODO @TODO check that the restore public function with renaming doesn't break the scorm structure!
     */
    public function restore_scorm_documents() {
        if ($this->course->has_resources(RESOURCE_SCORM)) {
            $resources = $this->course->resources;

            foreach ($resources[RESOURCE_SCORM] as $id => $document) {
                $path = api_get_path(SYS_COURSE_PATH) . $this->course->destination_path . '/';

                $perm = api_get_setting('permissions_for_new_directories');
                $perm = octdec(!empty($perm) ? $perm : '0770');
                @mkdir(dirname($path . $document->path), $perm, true);

                if (file_exists($path . $document->path)) {
                    switch ($this->file_option) {
                        case FILE_OVERWRITE :
                            rmdirr($path . $document->path);

                            copyDirTo($this->course->backup_path . '/' . $document->path, $path . dirname($document->path), false);

                            break;
                        case FILE_SKIP :
                            break;
                        case FILE_RENAME :
                            $i = 1;

                            $ext = explode('.', basename($document->path));

                            if (count($ext) > 1) {
                                $ext = array_pop($ext);
                                $file_name_no_ext = substr($document->path, 0, - (strlen($ext) + 1));
                                $ext = '.' . $ext;
                            } else {
                                $ext = '';
                                $file_name_no_ext = $document->path;
                            }

                            $new_file_name = $file_name_no_ext . '_' . $i . $ext;
                            $file_exists = file_exists($path . $new_file_name);

                            while ($file_exists) {
                                $i++;
                                $new_file_name = $file_name_no_ext . '_' . $i . $ext;
                                $file_exists = file_exists($path . $new_file_name);
                            }

                            rename($this->course->backup_path . '/' . $document->path, $this->course->backup_path . '/' . $new_file_name);

                            copyDirTo($this->course->backup_path . '/' . $new_file_name, $path . dirname($new_file_name), false);

                            rename($this->course->backup_path . '/' . $new_file_name, $this->course->backup_path . '/' . $document->path);

                            break;
                    } // end switch
                } // end if file exists
                else {
                    copyDirTo($this->course->backup_path . '/' . $document->path, $path . dirname($document->path), false);
                }
            } // end for each
        }
    }

    /**
     * Restore forums
     */
    public function restore_forums() {
        if ($this->course->has_resources(RESOURCE_FORUM)) {
            $table_forum = Database :: get_course_table(TABLE_FORUM, $this->course->destination_db);
            $table_topic = Database :: get_course_table(TABLE_FORUM_THREAD, $this->course->destination_db);
            $table_post = Database :: get_course_table(TABLE_FORUM_POST, $this->course->destination_db);
            $resources = $this->course->resources;
            foreach ($resources[RESOURCE_FORUM] as $id => $forum) {
                $cat_id = $this->restore_forum_category($forum->category_id);
                $sql = "INSERT INTO " . $table_forum .
                        " SET forum_title = '" . Database::escape_string($forum->title) .
                        "', forum_comment = '" . Database::escape_string($forum->description) .
                        "', forum_category = " . (int) Database::escape_string($cat_id) .
                        ", forum_last_post = " . (int) Database::escape_string($forum->last_post) .
                        ", forum_threads = " . (int) Database::escape_string($forum->topics) .
                        ", forum_posts = " . (int) Database::escape_string($forum->posts) .
                        ", allow_anonymous = " . (int) Database::escape_string($forum->allow_anonymous) .
                        ", allow_edit = " . (int) Database::escape_string($forum->allow_edit) .
                        ", approval_direct_post = '" . Database::escape_string($forum->approval_direct_post) .
                        "', allow_attachments = " . (int) Database::escape_string($forum->allow_attachments) .
                        ", allow_new_threads = " . (int) Database::escape_string($forum->allow_new_topics) .
                        ", default_view = '" . Database::escape_string($forum->default_view) .
                        "', forum_of_group = '" . Database::escape_string($forum->of_group) .
                        "', forum_group_public_private = '" . Database::escape_string($forum->group_public_private) .
                        "', forum_order = " . (int) Database::escape_string($forum->order) .
                        ", locked = " . (int) Database::escape_string($forum->locked) .
                        ", session_id = " . (int) Database::escape_string($forum->session_id) .
                        ", forum_image = '" . Database::escape_string($forum->image) . "'";
                Database::query($sql, __FILE__, __LINE__);
                $new_id = Database::insert_id();
                $this->course->resources[RESOURCE_FORUM][$id]->destination_id = $new_id;

                $forum_topics = 0;
                if (is_array($this->course->resources[RESOURCE_FORUMTOPIC])) {
                    foreach ($this->course->resources[RESOURCE_FORUMTOPIC] as $topic_id => $topic) {
                        if ($topic->forum_id == $id) {
                            $this->restore_topic($topic_id, $new_id);
                            $forum_topics++;
                        }
                    }
                }
                if ($forum_topics > 0) {
                    $last_post = $this->course->resources[RESOURCE_FORUMPOST][$forum->last_post];
                    $sql = "UPDATE " . $table_forum . " SET forum_threads = " . $forum_topics . ", forum_last_post = " . (int) $last_post->destination_id . " WHERE forum_id = " . (int) $new_id;
                    Database::query($sql, __FILE__, __LINE__);
                }
            }
        }
    }

    /**
     * Restore forum-categories
     */
    public function restore_forum_category($id) {
        $forum_cat_table = Database :: get_course_table(TABLE_FORUM_CATEGORY, $this->course->destination_db);
        $resources = $this->course->resources;
        $forum_cat = $resources[RESOURCE_FORUMCATEGORY][$id];
        $sql = 'SELECT MAX(cat_order) FROM ' . $forum_cat_table;
        $rs = Database::query($sql, __FILE__, __LINE__);
        $max_cat_order = (int) Database::result($rs, 0, 0);
        $sql = "INSERT INTO " . $forum_cat_table .
                " SET cat_title = '" . Database::escape_string($forum_cat->title) .
                "', cat_comment = '" . Database::escape_string($forum_cat->description) .
                "', cat_order = " . (int) Database::escape_string($max_cat_order + 1) .
                ", locked = " . (int) Database::escape_string($forum_cat->locked) .
                ", session_id = " . (int) Database::escape_string($forum_cat->session_id);
        Database::query($sql, __FILE__, __LINE__);
        $new_id = Database::insert_id();
        $this->course->resources[RESOURCE_FORUMCATEGORY][$id]->destination_id = $new_id;
        return $new_id;
    }

    /**
     * Restore a forum-topic
     */
    public function restore_topic($id, $forum_id) {
        $table = Database :: get_course_table(TABLE_FORUM_THREAD, $this->course->destination_db);
        $resources = $this->course->resources;
        $topic = $resources[RESOURCE_FORUMTOPIC][$id];

        $sql = "INSERT INTO " . $table .
                " SET thread_title = '" . Database::escape_string($topic->title) .
                "', forum_id = " . (int) Database::escape_string($forum_id) .
                ", thread_date = '" . Database::escape_string($topic->time) .
                "', thread_poster_id = " . (int) Database::escape_string($topic->topic_poster_id) .
                ", thread_poster_name = '" . Database::escape_string($topic->topic_poster_name) .
                "', thread_views = " . (int) Database::escape_string($topic->views) .
                ", thread_sticky = " . (int) Database::escape_string($topic->sticky) .
                ", locked = " . (int) Database::escape_string($topic->locked) .
                ", thread_close_date = '" . Database::escape_string($topic->time_closed) .
                "', thread_weight = " . (float) Database::escape_string($topic->weight) .
                ", thread_title_qualify = '" . Database::escape_string($topic->title_qualify) .
                "', thread_qualify_max = " . (float) Database::escape_string($topic->qualify_max);
        Database::query($sql, __FILE__, __LINE__);
        $new_id = Database::insert_id();
        $this->course->resources[RESOURCE_FORUMTOPIC][$id]->destination_id = $new_id;

        $topic_replies = -1;
        foreach ($this->course->resources[RESOURCE_FORUMPOST] as $post_id => $post) {
            if ($post->topic_id == $id) {
                $topic_replies++;
                $this->restore_post($post_id, $new_id, $forum_id);
            }
        }
        $last_post = $this->course->resources[RESOURCE_FORUMPOST][$topic->last_post];
        if (is_object($last_post)) {
            $sql = "UPDATE " . $table . " SET thread_last_post = " . (int) $last_post->destination_id;
            Database::query($sql, __FILE__, __LINE__);
        }
        if ($topic_replies >= 0) {
            $sql = "UPDATE " . $table . " SET thread_replies = " . $topic_replies;
            Database::query($sql, __FILE__, __LINE__);
        }
        return $new_id;
    }

    /**
     * Restore a forum-post
     * @TODO Restore tree-structure of posts. For example: attachments to posts.
     */
    public function restore_post($id, $topic_id, $forum_id) {
        $table_post = Database :: get_course_table(TABLE_FORUM_POST, $this->course->destination_db);
        $resources = $this->course->resources;
        $post = $resources[RESOURCE_FORUMPOST][$id];
        $sql = "INSERT INTO " . $table_post .
                " SET post_title = '" . Database::escape_string($post->title) .
                "', post_text = '" . Database::escape_string($post->text) .
                "', thread_id = " . (int) Database::escape_string($topic_id) .
                ", post_date = '" . Database::escape_string($post->post_time) .
                "', forum_id = " . (int) Database::escape_string($forum_id) .
                ", poster_id = " . (int) Database::escape_string($post->poster_id) .
                ", poster_name = '" . Database::escape_string($post->poster_name) .
                "', post_notification = " . (int) Database::escape_string($post->topic_notify) .
                ", post_parent_id = " . (int) Database::escape_string($post->parent_post_id) .
                ", visible = " . (int) Database::escape_string($post->visible);
        Database::query($sql, __FILE__, __LINE__);
        $new_id = Database::insert_id();
        $this->course->resources[RESOURCE_FORUMPOST][$id]->destination_id = $new_id;
        return $new_id;
    }

    /**
     * Restore links
     */
    public function restore_links($session_id = 0) {
        // If exist the resource of this type then
        if ($this->course->has_resources(RESOURCE_LINK)) {
            $link_table = Database :: get_course_table(TABLE_LINK, $this->course->destination_db);
            $resources = $this->course->resources;
            // Loop for each item of this resource
            foreach ($resources[RESOURCE_LINK] as $id => $link) {
                $cat_id = $this->restore_link_category($link->category_id, $session_id);
                $sql = "SELECT MAX(display_order) FROM  $link_table WHERE category_id='" . Database::escape_string($cat_id) . "'";
                $result = Database::query($sql, __FILE__, __LINE__);
                list($max_order) = Database::fetch_array($result);

                // If exist the condition of session
                $condition_session = "";
                if (!empty($session_id)) {
                    $condition_session = " , session_id = '$session_id' ";
                }
                // Insert the values into the table of this resource
                $sql = "INSERT INTO " . $link_table . " SET url = '" . Database::escape_string($link->url) . "', title = '" . Database::escape_string($link->title) . "', description = '" . Database::escape_string($link->description) . "', category_id='" . $cat_id . "', display_order='" . ($max_order + 1) . "', on_homepage = '" . $link->on_homepage . "', target = '" . $link->target . "' $condition_session";
                Database::query($sql, __FILE__, __LINE__);
                $this->course->resources[RESOURCE_LINK][$id]->destination_id = Database::insert_id();
            }
        }
    }

    /**
     * Restore tool intro
     */
    public function restore_tool_intro() {
        if ($this->course->has_resources(RESOURCE_TOOL_INTRO)) {
            $tool_intro_table = Database :: get_course_table(TABLE_TOOL_INTRO, $this->course->destination_db);
            $resources = $this->course->resources;
            foreach ($resources[RESOURCE_TOOL_INTRO] as $id => $tool_intro) {
                $sql = "DELETE FROM " . $tool_intro_table . " WHERE id='" . Database::escape_string($tool_intro->id) . "'";
                Database::query($sql, __FILE__, __LINE__);

                $tool_intro->intro_text = DocumentManager::replace_urls_inside_content_html_from_copy_course($tool_intro->intro_text, $this->course->code, $this->course->destination_path);

                $sql = "INSERT INTO " . $tool_intro_table . " SET id='" . Database::escape_string($tool_intro->id) . "', intro_text = '" . Database::escape_string($tool_intro->intro_text) . "'";
                Database::query($sql, __FILE__, __LINE__);

                $this->course->resources[RESOURCE_TOOL_INTRO][$id]->destination_id = Database::insert_id();
            }
        }
    }

    /**
     * Restore a link-category
     */
    public function restore_link_category($id, $session_id = 0) {
        $condition_session = "";
        if (!empty($session_id)) {
            $condition_session = " , session_id = '$session_id' ";
        }

        if ($id == 0) {
            return 0;
        }
        $link_cat_table = Database :: get_course_table(TABLE_LINK_CATEGORY, $this->course->destination_db);
        $resources = $this->course->resources;
        $link_cat = $resources[RESOURCE_LINKCATEGORY][$id];
        if (is_object($link_cat) && !$link_cat->is_restored()) {
            $sql = "SELECT MAX(display_order) FROM  $link_cat_table";
            $result = Database::query($sql, __FILE__, __LINE__);
            list($orderMax) = Database::fetch_array($result, 'NUM');
            $display_order = $orderMax + 1;
            $sql = "INSERT INTO " . $link_cat_table . " SET category_title = '" . Database::escape_string($link_cat->category_title) . "', description='" . Database::escape_string($link_cat->description) . "', display_order='" . $display_order . "' $condition_session ";
            Database::query($sql, __FILE__, __LINE__);
            $new_id = Database::insert_id();
            $this->course->resources[RESOURCE_LINKCATEGORY][$id]->destination_id = $new_id;
            return $new_id;
        }
        return $this->course->resources[RESOURCE_LINKCATEGORY][$id]->destination_id;
    }

    /**
     * Restore events
     */
    public function restore_events() {
        if ($this->course->has_resources(RESOURCE_EVENT)) {
            $table = Database :: get_course_table(TABLE_AGENDA, $this->course->destination_db);
            $resources = $this->course->resources;
            foreach ($resources[RESOURCE_EVENT] as $id => $event) {
                // check resources inside html from fckeditor tool and copy correct urls into recipient course
                $event->content = DocumentManager::replace_urls_inside_content_html_from_copy_course($event->content, $this->course->code, $this->course->destination_path);

                $sql = "INSERT INTO " . $table . " SET title = '" . Database::escape_string($event->title) . "', content = '" . Database::escape_string($event->content) . "', start_date = '" . $event->start_date . "', end_date = '" . $event->end_date . "', parent_event_id = '" . $event->parent_event_id . "'";
                Database::query($sql, __FILE__, __LINE__);
                $this->course->resources[RESOURCE_EVENT][$id]->destination_id = Database::insert_id();
            }
        }
    }

    /**
     * Restore course-description
     */
    public function restore_course_descriptions($session_id = 0) {
        if ($this->course->has_resources(RESOURCE_COURSEDESCRIPTION)) {
            $table = Database :: get_course_table(TABLE_COURSE_DESCRIPTION, $this->course->destination_db);
            $resources = $this->course->resources;
            foreach ($resources[RESOURCE_COURSEDESCRIPTION] as $id => $cd) {
                if (isset($_POST['destination_course'])) {
                    $course_destination = Security::remove_XSS($_POST['destination_course']);
                    $course_destination = api_get_course_info($course_destination);
                    $course_destination = $course_destination['path'];
                } else {
                    $course_destination = $this->course->destination_path;
                }

                $course_info = api_get_course_info(api_get_course_id());
                $search = '../courses/' . $course_info['path'] . '/document';
                $replace_search_by = '../courses/' . $course_destination . '/document';
                $description_content = str_replace($search, $replace_search_by, $cd->content);

                $condition_session = "";
                if (!empty($session_id)) {
                    $session_id = intval($session_id);
                    $condition_session = " , session_id = '$session_id' ";
                }

                // check resources inside html from fckeditor tool and copy correct urls into recipient course
                $description_content = DocumentManager::replace_urls_inside_content_html_from_copy_course($cd->content, $this->course->code, $this->course->destination_path);

                $sql = "INSERT INTO " . $table . " SET description_type = '" . Database::escape_string($cd->description_type) . "',title = '" . Database::escape_string($cd->title) . "', content = '" . Database::escape_string($description_content) . "' $condition_session";
                Database::query($sql, __FILE__, __LINE__);
                $this->course->resources[RESOURCE_COURSEDESCRIPTION][$id]->destination_id = Database::insert_id();
            }
        }
    }

    /**
     * Restore announcements
     */
    public function restore_announcements() {
        if ($this->course->has_resources(RESOURCE_ANNOUNCEMENT)) {
            $table = Database :: get_course_table(TABLE_ANNOUNCEMENT, $this->course->destination_db);
            $resources = $this->course->resources;
            foreach ($resources[RESOURCE_ANNOUNCEMENT] as $id => $announcement) {
                // check resources inside html from fckeditor tool and copy correct urls into recipient course
                $announcement->content = DocumentManager::replace_urls_inside_content_html_from_copy_course($announcement->content, $this->course->code, $this->course->destination_path);

                $sql = "SELECT MAX(display_order) FROM " . $table;
                $result = Database::query($sql, __FILE__, __LINE__);
                $display_order = (int) Database::result($result, 0) + 1;

                $sql = "INSERT INTO " . $table . " " .
                        "SET title = '" . Database::escape_string($announcement->title) . "'," .
                        "content = '" . Database::escape_string($announcement->content) . "', " .
                        "end_date = '" . $announcement->end_date . "', " .
                        "display_order = '" . $display_order . "', " .
                        "email_sent = '" . $announcement->email_sent . "'";
                Database::query($sql, __FILE__, __LINE__);
                $this->course->resources[RESOURCE_ANNOUNCEMENT][$id]->destination_id = Database::insert_id();
            }
        }
    }


	public function restore_certificates() {
		
        if ($this->course->has_resources(RESOURCE_CERTIFICATE)) {
			
            $table = Database :: get_course_table(TABLE_CERTIFICATE, $this->course->destination_db);
            $resources = $this->course->resources;
            foreach ($resources[RESOURCE_CERTIFICATE] as $id => $certificate) {

                $sql = "INSERT INTO " . $table . " " .
                        "SET name = '" . Database::escape_string($certificate->name) . "'," .
                        "content = '" . Database::escape_string($certificate->content) . "'";   

                Database::query($sql, __FILE__, __LINE__);
                $this->course->resources[RESOURCE_CERTIFICATE][$id]->destination_id = Database::insert_id();
            }
        }
    }


	public function restore_evaluations($session_id = 0) {
		
        if ($this->course->has_resources(RESOURCE_EVALUATION)) {
			
            $table_exam = Database :: get_course_table(TABLE_EXAM, $this->course->destination_db);
            $resources = $this->course->resources;
            foreach ($resources[RESOURCE_EVALUATION] as $id => $evaluation) {
                if ($id != -1) {
                    $condition_session = "";
                    if (!empty($session_id)) {
                        $session_id = intval($session_id);
                        $condition_session = ", session_id = '$session_id'";
                    }


                    // Normal tests are stored in the database.
                    $sql = "INSERT INTO " . $table_exam . " SET ".
							"   exam_name = '" . Database::escape_string($evaluation->exam_name) .
                            "', quiz_id = '" . Database::escape_string($evaluation->quiz_id) .
                            "', modality = '" . Database::escape_string($evaluation->modality) .
                            "', min_score = '" . $evaluation->min_score .
                            "', start_date = '" . $evaluation->start_date .
                            "', end_date = '" . $evaluation->end_date .
                            "', invitation_email_sentdate = '" . $evaluation->invitation_email_sentdate .
                            "',  feedback_email_sentdate = '" . $evaluation->feedback_email_sentdate .
                            "', invitation_email = '" . $evaluation->invitation_email .
                            "',  feedback_email = '" . $evaluation->feedback_email .
                            "', feedback_email_fail = '" . $evaluation->feedback_email_fail .
                            "', certif_id = '" . $evaluation->certif_id .
                            "',  picture_name = '" . $evaluation->picture_name .
                            "'" . $condition_session ;
                       
                    Database::query($sql, __FILE__, __LINE__);
                    $new_id = Database::insert_id();

                    
                } 
                
            }
        }
    }



    /**
     * Restore Quiz
     */
    public function restore_quizzes($session_id = 0) {

        if ($this->course->has_resources(RESOURCE_QUIZ)) {
            $table_qui = Database :: get_course_table(TABLE_QUIZ_TEST, $this->course->destination_db);
            $table_rel = Database :: get_course_table(TABLE_QUIZ_TEST_QUESTION, $this->course->destination_db);
            $table_doc = Database :: get_course_table(TABLE_DOCUMENT, $this->course->destination_db);
            $tbl_quiz_scenario = Database :: get_course_table(TABLE_QUIZ_SCENARIO, $this->course->destination_db);
            $resources = $this->course->resources;
            foreach ($resources[RESOURCE_QUIZ] as $id => $quiz) {
                $doc = '';
                if (strlen($quiz->sound) > 0) {
                    if ($this->course->resources[RESOURCE_DOCUMENT][$quiz->sound]->is_restored()) {
                        $sql = "SELECT path FROM " . $table_doc . " WHERE id = " . $resources[RESOURCE_DOCUMENT][$quiz->sound]->destination_id;
                        $doc = Database::query($sql, __FILE__, __LINE__);
                        $doc = Database::fetch_object($doc);
                        $doc = str_replace('/audio/', '', $doc->path);
                    }
                }
                if ($id != -1) {

                    $condition_session = "";
                    if (!empty($session_id)) {
                        $session_id = intval($session_id);
                        $condition_session = ", session_id = '$session_id'";
                    }

                    // check resources inside html from fckeditor tool and copy correct urls into recipient course
                    $quiz->description = DocumentManager::replace_urls_inside_content_html_from_copy_course($quiz->description, $this->course->code, $this->course->destination_path);


                    // Normal tests are stored in the database.
                    $sql = "INSERT INTO " . $table_qui .
                            " SET title = '" . Database::escape_string($quiz->title) .
                            "', description = '" . Database::escape_string($quiz->description) .
                            "', sound = '" . Database::escape_string($doc) .
                            "', type = '" . $quiz->type_db .
                            "', random = '" . $quiz->random .
                            "', active = '" . $quiz->active .
                            "', results_disabled = " . (int) $quiz->results_disabled .
                            ", access_condition = '" . $quiz->access_condition .
                            "', max_attempt = " . (int) $quiz->max_attempt .
                            ", start_time = '" . $quiz->start_time .
                            "', end_time = '" . $quiz->end_time .
                            "', feedback_type = " . (int) $quiz->feedback_type .
                            ", expired_time = " . (int) $quiz->expired_time .
                            ", position = " . (int) $quiz->position .
                            $condition_session .
                            ", certif_template = " . (int) $quiz->certif_template .
                            ", certif_min_score = '" . $quiz->certif_min_score .
                            "', score_pass = " . (int) $quiz->score_pass .
                            ", quiz_type = " . $quiz->quiz_type;
                    Database::query($sql, __FILE__, __LINE__);
                    $new_id = Database::insert_id();

                    $scenario_types = array(1, 2);
                    foreach ($scenario_types as $scenario) {
                        $rsScenario = Database::query("INSERT INTO $tbl_quiz_scenario (exercice_id, scenario_type,
				    title, description, sound, type, random, active, results_disabled, access_condition,
				    max_attempt, start_time, end_time, feedback_type, expired_time, session_id, 
                                    certif_template, certif_min_score, score_pass, quiz_type) VALUES('" . $new_id . "','" . $scenario . "',
				    '" . Database::escape_string($quiz->title) . "','" . Database::escape_string($quiz->description) . "','" . Database::escape_string($doc) . "',
				    '" . $quiz->type_db . "','" . $quiz->random . "','" . $quiz->active . "',
				    '" . (int) $quiz->results_disabled . "','" . $quiz->access_condition . "','" . (int) $quiz->max_attempt . "','" . $quiz->start_time . "',
				    '" . $quiz->end_time . "','" . (int) $quiz->feedback_type . "','" . (int) $quiz->expired_time . "','" . $session_id . "',
                                    '" . (int) $quiz->certif_template . "','" . $quiz->certif_min_score . "','" . (int) $quiz->score_pass . "','" . $quiz->quiz_type . "')", __FILE__, __LINE__);
                    }
                } else {
                    // $id = -1 identifies the fictionary test for collecting orphan questions. We do not store it in the database.
                    $new_id = -1;
                }
                $this->course->resources[RESOURCE_QUIZ][$id]->destination_id = $new_id;
                foreach ($quiz->question_ids as $index => $question_id) {
                    if (is_array($question_id)) {
                        $question_order = $question_id['question_order'];
                        $question_id = $question_id['question_id'];
                    } else {
                        $question_order = $index;
                        $question_id = $question_id;
                    }
                    $qid = $this->restore_quiz_question($question_id);
                    $sql = "INSERT IGNORE INTO " . $table_rel . " SET question_order = " . $question_order . ",question_id = " . $qid . ", exercice_id = " . $new_id;
                    Database::query($sql, __FILE__, __LINE__);
                }
            }
        }
    }

    /**
     * Restore quiz-questions
     */
    public function restore_quiz_question($id) {
        $old_course_code = $this->course->code;
        if (isset($this->destination_course_code)) {
            $new_course_code = $this->destination_course_code;
        } else {
            $new_course_code = $old_course_code;
        }

        $resources = $this->course->resources;
        $question = $resources[RESOURCE_QUIZQUESTION][$id];

        $new_id = 0;

        if (is_object($question)) {
            if ($question->is_restored()) {
                return $question->destination_id;
            }
            $table_que = Database :: get_course_table(TABLE_QUIZ_QUESTION, $this->course->destination_db);
            $table_ans = Database :: get_course_table(TABLE_QUIZ_ANSWER, $this->course->destination_db);

            // check resources inside html from fckeditor tool and copy correct urls into recipient course
            $question->description = DocumentManager::replace_urls_inside_content_html_from_copy_course($question->description, $this->course->code, $this->course->destination_path);

            $sql = "INSERT INTO " . $table_que
                    . " SET question = '" . Database::escape_string(replace_old_course_links($old_course_code, $new_course_code, $question->question))
                    . "', description = '" . Database::escape_string(replace_old_course_links($old_course_code, $new_course_code, $question->description))
                    . "', ponderation = '" . Database::escape_string($question->ponderation)
                    . "', position = '" . Database::escape_string($question->position)
                    . "', type='" . Database::escape_string($question->type_db)
                    . "', picture='" . Database::escape_string($question->picture)
                    . "', level='" . Database::escape_string($question->level)
                    . "', category='" . Database::escape_string($question->category)
                    . "', media_position='" . Database::escape_string($question->media_position) . "'";
            Database::query($sql, __FILE__, __LINE__);
            $new_id = Database::insert_id();
            foreach ($question->answers as $index => $answer) {
                // check resources inside html from fckeditor tool and copy correct urls into recipient course
                $answer['answer'] = DocumentManager::replace_urls_inside_content_html_from_copy_course($answer['answer'], $this->course->code, $this->course->destination_path);
                $answer['comment'] = DocumentManager::replace_urls_inside_content_html_from_copy_course($answer['comment'], $this->course->code, $this->course->destination_path);

                $sql = "INSERT INTO " . $table_ans . " SET id= '" . ($index + 1)
                        . "',question_id = '" . $new_id
                        . "', answer = '" . Database::escape_string($answer['answer'])
                        . "', correct = '" . $answer['correct']
                        . "', comment = '" . Database::escape_string($answer['comment'])
                        . "', ponderation='" . $answer['ponderation']
                        . "', position = '" . $answer['position']
                        . "', hotspot_coordinates = '" . $answer['hotspot_coordinates']
                        . "', hotspot_type = '" . $answer['hotspot_type']
                        . "', destination = '" . $answer['destination'] . "'";
                Database::query($sql, __FILE__, __LINE__);
            }
            $this->course->resources[RESOURCE_QUIZQUESTION][$id]->destination_id = $new_id;
        }

        return $new_id;
    }

    /**
     * Restore surveys
     */
    public function restore_surveys() {
        if ($this->course->has_resources(RESOURCE_SURVEY)) {
            $table_sur = Database :: get_course_table(TABLE_SURVEY, $this->course->destination_db);
            $table_que = Database :: get_course_table(TABLE_SURVEY_QUESTION, $this->course->destination_db);
            $table_ans = Database :: get_course_table(TABLE_SURVEY_QUESTION_OPTION, $this->course->destination_db);
            $resources = $this->course->resources;
            foreach ($resources[RESOURCE_SURVEY] as $id => $survey) {

                $sql_check = 'SELECT survey_id FROM ' . $table_sur . '
								WHERE code = "' . Database::escape_string($survey->code) . '"
								AND lang="' . Database::escape_string($survey->lang) . '"
								';

                $result_check = Database::query($sql_check, __FILE__, __LINE__);

                // check resources inside html from fckeditor tool and copy correct urls into recipient course
                $survey->title = DocumentManager::replace_urls_inside_content_html_from_copy_course($survey->title, $this->course->code, $this->course->destination_path);
                $survey->subtitle = DocumentManager::replace_urls_inside_content_html_from_copy_course($survey->subtitle, $this->course->code, $this->course->destination_path);
                $survey->intro = DocumentManager::replace_urls_inside_content_html_from_copy_course($survey->intro, $this->course->code, $this->course->destination_path);
                $survey->surveythanks = DocumentManager::replace_urls_inside_content_html_from_copy_course($survey->surveythanks, $this->course->code, $this->course->destination_path);

                $doc = '';
                $sql = "INSERT INTO " . $table_sur . " " .
                        "SET code = ':survey_code', " .
                        "title = '" . Database::escape_string($survey->title) . "', " .
                        "subtitle = '" . Database::escape_string($survey->subtitle) . "', " .
                        "author = '" . Database::escape_string($survey->author) . "', " .
                        "lang = '" . Database::escape_string($survey->lang) . "', " .
                        "avail_from = '" . Database::escape_string($survey->avail_from) . "', " .
                        "avail_till = '" . Database::escape_string($survey->avail_till) . "', " .
                        "is_shared = '" . Database::escape_string($survey->is_shared) . "', " .
                        "template = '" . Database::escape_string($survey->template) . "', " .
                        "intro = '" . Database::escape_string($survey->intro) . "', " .
                        "surveythanks = '" . Database::escape_string($survey->surveythanks) . "', " .
                        "creation_date = '" . Database::escape_string($survey->creation_date) . "', " .
                        "invited = '0', " .
                        "answered = '0', " .
                        "invite_mail = '" . Database::escape_string($survey->invite_mail) . "', " .
                        "reminder_mail = '" . Database::escape_string($survey->reminder_mail) . "', " .
                        "mail_subject = '" . Database::escape_string($survey->mail_subject) . "', " .
                        "anonymous = '" . Database::escape_string($survey->anonymous) . "', " .
                        "question_per_page = '" . Database::escape_string($survey->question_per_page) . "', " .
                        "access_condition = '" . Database::escape_string($survey->access_condition) . "', " .
                        "shuffle = '" . Database::escape_string($survey->shuffle) . "', " .
                        "one_question_per_page = '" . Database::escape_string($survey->one_question_per_page) . "', " .
                        "survey_version = '" . Database::escape_string($survey->survey_version) . "', " .
                        "parent_id = '" . Database::escape_string($survey->parent_id) . "', " .
                        "survey_type = '" . Database::escape_string($survey->survey_type) . "', " .
                        "show_form_profile = '" . Database::escape_string($survey->show_form_profile) . "', " .
                        "form_fields = '" . Database::escape_string($survey->form_fields) . "', " .
                        "session_id = '0'";

                //An existing survey exists with the same code and the same language
                if (Database::num_rows($result_check) == 1) {

                    switch ($this->file_option) {

                        case FILE_SKIP:
                            //Do nothing
                            break;

                        case FILE_RENAME:

                            $survey_code = Database::escape_string($survey->code) . '_';
                            $i = 1;
                            $temp_survey_code = $survey_code . $i;
                            while (!$this->is_survey_code_available($temp_survey_code)) {
                                $temp_survey_code = $survey_code . ++$i;
                            }
                            $survey_code = $temp_survey_code;

                            //Insert the new source survey
                            Database::query(str_replace(":survey_code", $survey_code, $sql), __FILE__, __LINE__);

                            $new_id = Database::insert_id();
                            $this->course->resources[RESOURCE_SURVEY][$id]->destination_id = $new_id;
                            foreach ($survey->question_ids as $index => $question_id) {
                                $qid = $this->restore_survey_question($question_id);
                                $sql = "UPDATE " . $table_que . " " .
                                        "SET survey_id = " . $new_id . " WHERE " .
                                        "question_id = " . $qid . "";
                                Database::query($sql, __FILE__, __LINE__);
                                $sql = "UPDATE " . $table_ans . " " .
                                        "SET survey_id = " . $new_id . " WHERE " .
                                        "question_id = " . $qid . "";
                                Database::query($sql, __FILE__, __LINE__);
                            }

                            break;

                        case FILE_OVERWRITE:

                            // Delete the existing survey with the same code and language and import the one of the source course
                            // getting the information of the survey (used for when the survey is shared)
                            require_once(api_get_path(SYS_CODE_PATH) . 'survey/survey.lib.php');

                            $sql_select_existing_survey = "SELECT * FROM $table_sur WHERE survey_id='" . Database::escape_string(Database::result($result_check, 0, 0)) . "'";
                            $result = Database::query($sql_select_existing_survey, __FILE__, __LINE__);
                            $survey_data = Database::fetch_array($result, 'ASSOC');

                            // if the survey is shared => also delete the shared content
                            if (is_numeric($survey_data['survey_share'])) {
                                survey_manager::delete_survey($survey_data['survey_share'], true, $this->course->destination_db);
                            }
                            $return = survey_manager :: delete_survey($survey_data['survey_id'], false, $this->course->destination_db);

                            //Insert the new source survey
                            Database::query(str_replace(":survey_code", Database::escape_string($survey->code), $sql), __FILE__, __LINE__);

                            $new_id = Database::insert_id();
                            $this->course->resources[RESOURCE_SURVEY][$id]->destination_id = $new_id;
                            foreach ($survey->question_ids as $index => $question_id) {
                                $qid = $this->restore_survey_question($question_id);
                                $sql = "UPDATE " . $table_que . " " .
                                        "SET survey_id = " . $new_id . " WHERE " .
                                        "question_id = " . $qid . "";
                                Database::query($sql, __FILE__, __LINE__);
                                $sql = "UPDATE " . $table_ans . " " .
                                        "SET survey_id = " . $new_id . " WHERE " .
                                        "question_id = " . $qid . "";
                                Database::query($sql, __FILE__, __LINE__);
                            }

                            break;

                        default:
                            break;
                    }
                }
                //No existing survey with the same language and the same code, we just copy the survey
                else {
                    Database::query(str_replace(":survey_code", Database::escape_string($survey->code), $sql), __FILE__, __LINE__);
                    $new_id = Database::insert_id();
                    $this->course->resources[RESOURCE_SURVEY][$id]->destination_id = $new_id;
                    foreach ($survey->question_ids as $index => $question_id) {
                        $qid = $this->restore_survey_question($question_id);
                        $sql = "UPDATE " . $table_que . " " .
                                "SET survey_id = " . $new_id . " WHERE " .
                                "question_id = " . $qid . "";
                        Database::query($sql, __FILE__, __LINE__);
                        $sql = "UPDATE " . $table_ans . " " .
                                "SET survey_id = " . $new_id . " WHERE " .
                                "question_id = " . $qid . "";
                        Database::query($sql, __FILE__, __LINE__);
                    }
                }
            }
        }
    }

    /**
     * Check availability of a survey code
     */
    public function is_survey_code_available($survey_code) {
        $table_sur = Database :: get_course_table(TABLE_SURVEY, $this->course->destination_db);
        $sql = "SELECT * FROM $table_sur WHERE code='" . Database::escape_string($survey_code) . "'";
        $result = Database::query($sql, __FILE__, __LINE__);
        return Database::num_rows($result) == 0;
    }

    /**
     * Restore survey-questions
     */
    public function restore_survey_question($id) {
        $resources = $this->course->resources;
        $question = $resources[RESOURCE_SURVEYQUESTION][$id];

        $new_id = 0;

        if (is_object($question)) {
            if ($question->is_restored()) {
                return $question->destination_id;
            }
            $table_que = Database :: get_course_table(TABLE_SURVEY_QUESTION, $this->course->destination_db);
            $table_ans = Database :: get_course_table(TABLE_SURVEY_QUESTION_OPTION, $this->course->destination_db);

            $sql = "INSERT INTO " . $table_que . " " .
                    "SET survey_id = '" . Database::escape_string($question->survey_id) . "', " .
                    "survey_question = '" . Database::escape_string($question->survey_question) . "', " .
                    "survey_question_comment = '" . Database::escape_string($question->survey_question_comment) . "', " .
                    "type = '" . Database::escape_string($question->survey_question_type) . "', " .
                    "display = '" . Database::escape_string($question->display) . "', " .
                    "sort = '" . Database::escape_string($question->sort) . "', " .
                    "shared_question_id = '" . Database::escape_string($question->shared_question_id) . "', " .
                    "max_value = '" . Database::escape_string($question->max_value) . "', " .
                    "survey_group_pri = '" . Database::escape_string($question->survey_group_pri) . "', " .
                    "survey_group_sec1 = '" . Database::escape_string($question->survey_group_sec1) . "', " .
                    "survey_group_sec2 = '" . Database::escape_string($question->survey_group_sec2) . "'";
            Database::query($sql, __FILE__, __LINE__);

            $new_id = Database::insert_id();
            foreach ($question->answers as $index => $answer) {
                $sql = "INSERT INTO $table_ans SET " .
                        "question_id = '" . Database::escape_string($new_id) . "', " .
                        "survey_id = '" . Database::escape_string($question->survey_id) . "', " .
                        "option_text = '" . Database::escape_string($answer['option_text']) . "', " .
                        "sort = '" . Database::escape_string($answer['sort']) . "', " .
                        "value = '" . Database::escape_string($answer['value']) . "'";

                Database::query($sql, __FILE__, __LINE__);
            }
            $this->course->resources[RESOURCE_SURVEYQUESTION][$id]->destination_id = $new_id;
        }

        return $new_id;
    }

    /**
     * Restore learnpaths
     */
    public function restore_learnpaths($session_id = 0) {
        if ($this->course->has_resources(RESOURCE_LEARNPATH)) {
            $table_main = Database :: get_course_table(TABLE_LP_MAIN, $this->course->destination_db);
            $table_item = Database :: get_course_table(TABLE_LP_ITEM, $this->course->destination_db);
            $table_tool = Database::get_course_table(TABLE_TOOL_LIST, $this->course->destination_db);

            // check nb tools
            $sql = 'SELECT MAX(id) FROM ' . $table_tool;
            $rs = Database::query($sql, __FILE__, __LINE__);
            $max_tool_id = Database::result($rs, 0, 0);

            $resources = $this->course->resources;

            foreach ($resources[RESOURCE_LEARNPATH] as $id => $lp) {

                $session_id = intval($session_id);

                $learning_path = '/upload/learning_path/images/' . $lp->preview_image;
                $path = api_get_path(SYS_COURSE_PATH) . $this->course->destination_path . '/';
                if (is_file($this->course->backup_path . $learning_path) && is_readable($this->course->backup_path . '/' . $learning_path) && is_dir(dirname($path . $learning_path)) && is_writeable(dirname($path . $learning_path))) {
                    copy($this->course->backup_path . $learning_path, $path . $learning_path);
                }

                $sql = "INSERT INTO $table_main SET " .
                        "lp_type = '" . $lp->lp_type . "', " .
                        "name = '" . Database::escape_string($lp->name) . "', " .
                        "ref = '" . Database::escape_string($lp->ref) . "', " .
                        "description = '" . Database::escape_string($lp->description) . "', " .
                        "path = '" . Database::escape_string($lp->path) . "', " .
                        "force_commit = '" . Database::escape_string($lp->force_commit) . "', " .
                        "default_view_mod = '" . Database::escape_string($lp->default_view_mod) . "', " .
                        "default_encoding = '" . Database::escape_string($lp->default_encoding) . "', " .
                        "display_order = '" . Database::escape_string($lp->display_order) . "', " .
                        "content_maker = '" . Database::escape_string($lp->content_maker) . "', " .
                        "content_local = '" . Database::escape_string($lp->content_local) . "', " .
                        "content_license = '" . Database::escape_string($lp->content_license) . "', " .
                        "prevent_reinit = '" . Database::escape_string($lp->prevent_reinit) . "', " .
                        "js_lib = '" . Database::escape_string($lp->js_lib) . "', " .
                        "debug = '" . Database::escape_string($lp->debug) . "', " .
                        "theme = '" . Database::escape_string($lp->theme) . "', " .
                        "preview_image = '" . Database::escape_string($lp->preview_image) . "', " .
                        "author = '" . Database::escape_string($lp->author) . "', " .
                        "lp_interface = '" . Database::escape_string($lp->lp_interface) . "', " .
                        "session_id = '" . $session_id . "', " .
                        "certif_template = '" . Database::escape_string($lp->certif_template) . "', " .
                        "certif_min_score = '" . Database::escape_string($lp->certif_min_score) . "', " .
                        "certif_min_progress = '" . Database::escape_string($lp->certif_min_progress) . "'";
                Database::query($sql, __FILE__, __LINE__);

                $new_lp_id = Database::insert_id();

                if ($lp->visibility) {
                    $tool_id = $max_tool_id + intval($lp->tool_id);
                    $sql = "INSERT INTO $table_tool SET id=$tool_id, name='" . Database::escape_string($lp->name) . "', link='newscorm/lp_controller.php?action=view&amp;lp_id=$new_lp_id', image='scormbuilder.gif', visibility='1', admin='0', address='squaregrey.gif'";
                    Database::query($sql, __FILE__, __LINE__);
                }

                $new_item_ids = array();
                $parent_item_ids = array();
                $previous_item_ids = array();
                $next_item_ids = array();
                $old_prerequisite = array();
                $old_refs = array();
                $prerequisite_ids = array();

                foreach ($lp->get_items() as $index => $item) {

                    // we set the ref code here and then we update in a for loop
                    $ref = $item['ref'];

                    //Dealing with path the same way as ref as some data has been put into path when it's a
                    //local resource
                    $path = Database::escape_string($item['path']);
                    if (strval(intval($path)) === $path) {
                        if (!empty($session_id)) {
                            $path = intval($path);
                        } else {
                            $path = $this->get_new_id($item['item_type'], $path);
                        }
                    }

                    $sql = "INSERT INTO $table_item SET " .
                            "lp_id = '" . Database::escape_string($new_lp_id) . "', " .
                            "item_type = '" . Database::escape_string($item['item_type']) . "', " .
                            "ref = '" . Database::escape_string($ref) . "', " .
                            "title = '" . Database::escape_string($item['title']) . "', " .
                            "description = '" . Database::escape_string($item['description']) . "', " .
                            "path = '" . Database::escape_string($path) . "', " .
                            "min_score = '" . Database::escape_string($item['min_score']) . "', " .
                            "max_score = '" . Database::escape_string($item['max_score']) . "', " .
                            "mastery_score = '" . Database::escape_string($item['mastery_score']) . "', " .
                            "parent_item_id = '" . Database::escape_string($item['parent_item_id']) . "', " .
                            "previous_item_id = '" . Database::escape_string($item['previous_item_id']) . "', " .
                            "next_item_id = '" . Database::escape_string($item['next_item_id']) . "', " .
                            "display_order = '" . Database::escape_string($item['display_order']) . "', " .
                            "prerequisite = '" . Database::escape_string($item['prerequisite']) . "', " .
                            "parameters = '" . Database::escape_string($item['parameters']) . "', " .
                            "max_time_allowed = '" . Database::escape_string($item['max_time_allowed']) . "', " .
                            "terms = '" . Database::escape_string($item['terms']) . "', " .
                            "search_did = '" . Database::escape_string($item['search_did']) . "', " .
                            "audio = '" . Database::escape_string($item['audio']) . "'";

                    Database::query($sql, __FILE__, __LINE__);
                    $new_item_id = Database::insert_id();
                    //save a link between old and new item IDs
                    $new_item_ids[$item['id']] = $new_item_id;
                    //save a reference of items that need a parent_item_id refresh
                    $parent_item_ids[$new_item_id] = $item['parent_item_id'];
                    //save a reference of items that need a previous_item_id refresh
                    $previous_item_ids[$new_item_id] = $item['previous_item_id'];
                    //save a reference of items that need a next_item_id refresh
                    $next_item_ids[$new_item_id] = $item['next_item_id'];

                    if (!empty($item['prerequisite'])) {
                        if ($lp->lp_type == '2') {
                            // if is an sco
                            $old_prerequisite[$new_item_id] = $item['prerequisite'];
                        } else {
                            $old_prerequisite[$new_item_id] = $new_item_ids[$item['prerequisite']];
                        }
                    }

                    if (!empty($ref)) {
                        if ($lp->lp_type == '2') {
                            // if is an sco
                            $old_refs[$new_item_id] = $ref;
                        } else {
                            $old_refs[$new_item_id] = $new_item_ids[$ref];
                        }
                    }

                    $prerequisite_ids[$new_item_id] = $item['prerequisite'];
                }

                // updating prerequisites
                foreach ($old_prerequisite as $key => $my_old_prerequisite) {
                    if ($my_old_prerequisite != '') {
                        $sql = "UPDATE " . $table_item . " SET prerequisite = '" . $my_old_prerequisite . "' WHERE id = '" . $key . "'  ";
                        Database::query($sql, __FILE__, __LINE__);
                    }
                }

                //updating refs
                foreach ($old_refs as $key => $my_old_ref) {
                    if ($my_old_ref != '') {
                        $sql = "UPDATE " . $table_item . " SET ref = '" . $my_old_ref . "' WHERE id = '" . $key . "'  ";
                        Database::query($sql, __FILE__, __LINE__);
                    }
                }

                foreach ($parent_item_ids as $new_item_id => $parent_item_old_id) {
                    $parent_new_id = 0;
                    if ($parent_item_old_id != 0) {
                        $parent_new_id = $new_item_ids[$parent_item_old_id];
                    }
                    $sql = "UPDATE " . $table_item . " SET parent_item_id = '" . $parent_new_id . "' WHERE id = '" . $new_item_id . "'";
                    Database::query($sql, __FILE__, __LINE__);
                }
                foreach ($previous_item_ids as $new_item_id => $previous_item_old_id) {
                    $previous_new_id = 0;
                    if ($previous_item_old_id != 0) {
                        $previous_new_id = $new_item_ids[$previous_item_old_id];
                    }
                    $sql = "UPDATE " . $table_item . " SET previous_item_id = '" . $previous_new_id . "' WHERE id = '" . $new_item_id . "'";
                    Database::query($sql, __FILE__, __LINE__);
                }

                foreach ($next_item_ids as $new_item_id => $next_item_old_id) {
                    $next_new_id = 0;
                    if ($next_item_old_id != 0) {
                        $next_new_id = $new_item_ids[$next_item_old_id];
                    }
                    $sql = "UPDATE " . $table_item . " SET next_item_id = '" . $next_new_id . "' WHERE id = '" . $new_item_id . "'";
                    Database::query($sql, __FILE__, __LINE__);
                }

                foreach ($prerequisite_ids as $new_item_id => $prerequisite_old_id) {
                    $prerequisite_new_id = 0;
                    if ($prerequisite_old_id != 0) {
                        $prerequisite_new_id = $new_item_ids[$prerequisite_old_id];
                    }
                    $sql = "UPDATE " . $table_item . " SET prerequisite = '" . $prerequisite_new_id . "' WHERE id = '" . $new_item_id . "'";
                    Database::query($sql, __FILE__, __LINE__);
                }

                $this->course->resources[RESOURCE_LEARNPATH][$id]->destination_id = $new_lp_id;
            }
        }
    }

    /**
     * restore works
     */
    public function restore_student_publication() {
        $my_course_id = api_get_course_id();
        $my_course_info = api_get_course_info($my_course_id); //student_publication_assignment

        $my_tbl_db_spa_origin = Database :: get_course_table(TABLE_STUDENT_PUBLICATION_ASSIGNMENT, $my_course_info['dbName']);
        $my_tbl_db_spa_destination = Database :: get_course_table(TABLE_STUDENT_PUBLICATION_ASSIGNMENT, $this->course->destination_db);

        $my_tbl_db_origin = Database :: get_course_table(TABLE_STUDENT_PUBLICATION, $my_course_info['dbName']);
        $my_tbl_db_destination = Database :: get_course_table(TABLE_STUDENT_PUBLICATION, $this->course->destination_db);

        $my_tbl_db_item_property_origin = Database :: get_course_table(TABLE_ITEM_PROPERTY, $my_course_info['dbName']);
        $my_tbl_db_item_property_destination = Database :: get_course_table(TABLE_ITEM_PROPERTY, $this->course->destination_db);

        //query in student publication

        $query_sql_fin_sp = 'INSERT IGNORE INTO ' . $my_tbl_db_destination . '' .
        '(id,url,title,description,author,active,accepted,post_group_id,sent_date,' .
        'filetype,has_properties,view_properties,qualification,date_of_qualification,' .
        'parent_id,qualificator_id,session_id) ';

        $query_sql_ini_sp = 'SELECT id,url,title,description,author,active,accepted,post_group_id,' .
        'sent_date,filetype,has_properties,view_properties,qualification,date_of_qualification,' .
        'parent_id,qualificator_id,session_id FROM ' . $my_tbl_db_origin . ' WHERE filetype="folder" ';
        //var_dump($query_sql_ini_sp);
        $destination = '../../courses/' . $this->course->destination_path . '/work/';
        $course_info = api_get_course_info(api_get_course_id());
        $origin = '../../courses/' . $course_info['path'] . '/work/';

        self::allow_create_all_directory($origin, $destination, false);

        //query in item property

        $query_sql_fin_ip = 'INSERT IGNORE INTO ' . $my_tbl_db_item_property_destination . '' .
        '(tool,insert_user_id,insert_date,lastedit_date,ref,lastedit_type,lastedit_user_id,to_group_id,' .
        'to_user_id,visibility,start_visible,end_visible) ';

        $query_sql_ini_ip = 'SELECT tool,insert_user_id,insert_date,lastedit_date,ref,lastedit_type,' .
        'lastedit_user_id,to_group_id,to_user_id,visibility,start_visible,
        end_visible FROM ' . $my_tbl_db_item_property_origin . ' ip INNER JOIN ' . $my_tbl_db_origin . ' sp' .
        ' ON ip.ref=sp.id WHERE tool="work" ';


        $query_sql_fin_sa = 'INSERT IGNORE INTO ' . $my_tbl_db_spa_destination . '' .
        '(id,expires_on,ends_on,add_to_calendar,enable_qualification,publication_id) ';

        $query_sql_ini_sa = 'SELECT sa.id,sa.expires_on,sa.ends_on,sa.add_to_calendar,sa.enable_qualification,sa.publication_id FROM ' . $my_tbl_db_spa_origin . ' sa INNER JOIN ' . $my_tbl_db_origin . ' sp
        ON sa.publication_id=sp.id WHERE filetype="folder" ';

        $query_sql_sp = $query_sql_fin_sp . $query_sql_ini_sp;
        $query_sql_ip = $query_sql_fin_ip . $query_sql_ini_ip;
        $query_sql_sa = $query_sql_fin_sa . $query_sql_ini_sa;

        Database::query($query_sql_sp, __FILE__, __LINE__);
        Database::query($query_sql_ip, __FILE__, __LINE__);
        Database::query($query_sql_sa, __FILE__, __LINE__);
    }

    /**
     * copy all directory and sub directory
     * @param string The path origin
     * @param string The path destination
     * @param boolean Option Overwrite
     * @return void()
     */
    public function allow_create_all_directory($source, $dest, $overwrite = false) {
        if (!is_dir($dest)) {
            mkdir($dest);
        }

        if ($handle = opendir($source)) {        // if the folder exploration is sucsessful, continue
            while (false !== ($file = readdir($handle))) { // as long as storing the next file to $file is successful, continue
                if ($file != '.' && $file != '..') {
                    $path = $source . '/' . $file;
                    if (is_file($path)) {
                        /* if (!is_file($dest . '/' . $file) || $overwrite)
                          if (!@copy($path, $dest . '/' . $file)) {
                          echo '<font color="red">File ('.$path.') '.get_lang('NotHavePermission').'</font>';
                          } */
                    } elseif (is_dir($path)) {
                        if (!is_dir($dest . '/' . $file))
                            mkdir($dest . '/' . $file);
                        self:: allow_create_all_directory($path, $dest . '/' . $file, $overwrite);
                    }
                }
            }
            closedir($handle);
        }
    }

    /**
     * Gets the new ID of one specific tool item from the tool name and the old ID
     * @param	string	Tool name
     * @param	integer	Old ID
     * @return	integer	New ID
     */
    public function get_new_id($tool, $ref) {
        //transform $tool into one backup/restore constant
        if ($tool == 'hotpotatoes') {
            $tool = 'document';
        }
        if (!empty($this->course->resources[$tool][$ref]->destination_id)) {
            return $this->course->resources[$tool][$ref]->destination_id;
        }
        return '';
    }

    /**
     * Restore glossary
     */
    public function restore_glossary($session_id = 0) {
        if ($this->course->has_resources(RESOURCE_GLOSSARY)) {
            $table_glossary = Database :: get_course_table(TABLE_GLOSSARY, $this->course->destination_db);
            $t_item_propery = Database :: get_course_table(TABLE_ITEM_PROPERTY, $this->course->destination_db);
            $resources = $this->course->resources;
            foreach ($resources[RESOURCE_GLOSSARY] as $id => $glossary) {

                $condition_session = "";
                if (!empty($session_id)) {
                    $session_id = intval($session_id);
                    $condition_session = ", session_id = '$session_id'";
                }

                // check resources inside html from fckeditor tool and copy correct urls into recipient course
                $glossary->description = DocumentManager::replace_urls_inside_content_html_from_copy_course($glossary->description, $this->course->code, $this->course->destination_path);

                $sql = "SELECT MAX(display_order) FROM " . $table_glossary;
                $result = Database::query($sql, __FILE__, __LINE__);
                $display_order = (int) Database::result($result, 0) + 1;

                $sql = "INSERT INTO " . $table_glossary . " SET  name = '" . Database::escape_string($glossary->name) . "', description = '" . Database::escape_string($glossary->description) . "', display_order='" . $display_order . "'" . $condition_session;
                Database::query($sql, __FILE__, __LINE__);
                $this->course->resources[RESOURCE_GLOSSARY][$id]->destination_id = Database::insert_id();
            }
        }
    }

    public function restore_wiki() {
        if ($this->course->has_resources(RESOURCE_WIKI)) {
            // wiki table of the target course
            $table_wiki = Database :: get_course_table('wiki', $this->course->destination_db);
            $table_wiki_conf = Database :: get_course_table('wiki_conf', $this->course->destination_db);

            // storing all the resources that have to be copied in an array
            $resources = $this->course->resources;

            foreach ($resources[RESOURCE_WIKI] as $id => $wiki) {

                // check resources inside html from fckeditor tool and copy correct urls into recipient course
                $wiki->content = DocumentManager::replace_urls_inside_content_html_from_copy_course($wiki->content, $this->course->code, $this->course->destination_path);

                // the sql statement to insert the groups from the old course to the new course
                $sql = "INSERT INTO $table_wiki (page_id, reflink, title, content, user_id, group_id, dtime, addlock, editlock, 
                                    visibility, addlock_disc, visibility_disc, ratinglock_disc, assignment, comment, progress, score, version, is_editing, 
                                    time_edit, hits, linksto, tag, user_ip)
							VALUES (
							'" . Database::escape_string($wiki->page_id) . "',
							'" . Database::escape_string($wiki->reflink) . "',
							'" . Database::escape_string($wiki->title) . "',
							'" . Database::escape_string($wiki->content) . "',
							'" . Database::escape_string($wiki->user_id) . "', " .
                                                        (empty($wiki->group_id) ? '0' : Database::escape_string($wiki->group_id)) . ",
							'" . Database::escape_string($wiki->dtime) . "',
							'" . Database::escape_string($wiki->addlock) . "',
							'" . Database::escape_string($wiki->editlock) . "',
							'" . Database::escape_string($wiki->visibility) . "',
							'" . Database::escape_string($wiki->addlock_disc) . "',
							'" . Database::escape_string($wiki->visibility_disc) . "',
							'" . Database::escape_string($wiki->ratinglock_disc) . "',
							'" . Database::escape_string($wiki->assignment) . "',
							'" . Database::escape_string($wiki->comment) . "',
							'" . Database::escape_string($wiki->progress) . "',
							'" . Database::escape_string($wiki->score) . "',
							'" . Database::escape_string($wiki->version) . "',
							'" . Database::escape_string($wiki->is_editing) . "',
							'" . Database::escape_string($wiki->time_edit) . "',
							'" . Database::escape_string($wiki->hits) . "',
							'" . Database::escape_string($wiki->linksto) . "',
							'" . Database::escape_string($wiki->tag) . "',
							'" . Database::escape_string($wiki->user_ip) . "')";
                $result = Database::query($sql, __FILE__, __LINE__);
                $new_id = Database::insert_id();
                $wiki->page_id = $new_id;
                $sql = "UPDATE $table_wiki SET page_id = '" . $wiki->page_id . "' WHERE id = '" . $new_id . "'";
                Database::query($sql, __FILE__, __LINE__);
                $this->course->resources[RESOURCE_WIKI][$id]->destination_id = $new_id;

                // we also add an entry in wiki_conf
                $sql = "INSERT INTO $table_wiki_conf 
						(page_id, task, feedback1, feedback2, feedback3, fprogress1, fprogress2, fprogress3, max_size, max_text, max_version, startdate_assig, enddate_assig, delayedsubmit) 
						VALUES
						('" . Database::escape_string($wiki->page_id) . "', '', '', '', '', '', '', '', NULL, 0, 0, '0000-00-00 00:00:00', '0000-00-00 00:00:00', 0)";
                $result = Database::query($sql, __FILE__, __LINE__);
            }
        }
    }

}
