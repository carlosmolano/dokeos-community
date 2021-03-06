<?php
/*
==============================================================================
	Dokeos - elearning and course management software

	Copyright (c) 2004 Dokeos S.A.
	Copyright (c) 2003 Ghent University (UGent)
	Copyright (c) 2001 Universite catholique de Louvain (UCL)
	Copyright (c) Olivier Brouckaert

	For a full list of contributors, see "credits.txt".
	The full license can be read in "license.txt".

	This program is free software; you can redistribute it and/or
	modify it under the terms of the GNU General Public License
	as published by the Free Software Foundation; either version 2
	of the License, or (at your option) any later version.

	See the GNU General Public License for more details.

	Contact: Dokeos, 181 rue Royale, B-1000 Brussels, Belgium, info@dokeos.com
==============================================================================
*/
/**
==============================================================================
*	Hidden frame that saves the content of the rich text editor each 3 seconds
*
*	@author Olivier Brouckaert
*	@package dokeos.online
==============================================================================
*/

define('FRAME','hidden2');

// name of the language file that needs to be included
$language_file='chat';

include('../inc/global.inc.php');
$this_section=SECTION_COURSES;

$tbl_user = Database::get_main_table(TABLE_MAIN_USER);

$query="SELECT username FROM $tbl_user WHERE user_id='".$_user['user_id']."'";
$result=Database::query($query,__FILE__,__LINE__);

list($pseudoUser)=Database::fetch_row($result);

$isAllowed=(empty($pseudoUser) || !$_cid)?false:true;
$isMaster=$is_courseAdmin?true:false;

if(!$isAllowed || !$isMaster)
{
	exit();
}

$documentPath=api_get_path(SYS_COURSE_PATH).$_course['path'].'/document/';
$onlinePath=$documentPath.'online_files/';

$editor=trim(stripslashes($_POST['editor']));

if(!empty($editor))
{
	$fp=fopen($onlinePath.'htmlarea.html','w');

	fputs($fp,$editor);

	fclose($fp);
}

include('header_frame.inc.php');
?>

<?php
include('footer_frame.inc.php');
?>