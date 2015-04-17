<?php // External login module : LDAP
/**
 *
 * This file is included in main/inc/local.inc.php at user login if the user have 'external_ldap' in 
 * his auth_source field insted of platform
 *
 * Variables that can be used : 
 *    - $login : string containing the username posted by the user
 *    - $password : string containing the password posted by the user
 *    - $uData : associative array with those keys : 
 *           -username
 *           -password
 *           -auth_source
 *           -active
 *           -expiration_date
 *  
 * If login succeeds, we have 2 choices : 
 *    1.  - set $loginFailed to false, 
 *        - set $_SESSION['_user']['user_id'] with the dokeos user_id 
 *        - set $uidReset to true
 *        - upgrade user info in dokeos database if needeed
 *        - let the script local.inc.php continue
 *
 *    2.  - set $_SESSION['_user']['user_id'] with the dokeos user_id 
 *        - set $_SESSION['_user']['uidReset'] to true
 *        - upgrade user info in dokeos database if needeed
 *        - redirect to any page and let local.inc.php do the magic
 * 
 * If login fails we have to redirect to index.php with the right message
 * Possible messages are : 
 *  - index.php?loginFailed=1&error=access_url_inactive
 *  - index.php?loginFailed=1&error=account_expired
 *  - index.php?loginFailed=1&error=account_inactive
 *  - index.php?loginFailed=1&error=user_password_incorrect 
 *  - index.php?loginFailed=1&error=unrecognize_sso_origin');
 *
 **/
require_once dirname(__FILE__).'/ldap.conf.php';
require_once dirname(__FILE__).'/functions.inc.php';

$ldap_user = ldap_authenticate($login,$password);
if ($ldap_user !== false) {
  $dokeos_user = ldap_get_dokeos_user($ldap_user);
  //userid is not on the ldap, we have to use $uData variable from local.inc.php
  $dokeos_user['user_id'] = $uData['user_id'];
  $dokeos_user['auth_source'] = LDAP_AUTH_SOURCE;
  //Update user info
  if(isset($ldap_config['update_userinfo']) && $ldap_config['update_userinfo'])
  {
    external_update_user($dokeos_user);
  }

  $loginFailed = false;
  $_user['user_id'] = $dokeos_user['user_id'];
  $_user['uidReset'] = true;  
  api_session_register('_user');
  $uidReset=true;
  event_login();

} else {
  $loginFailed = true;
  $uidReset = false;
  unset($_user['user_id']);
  header('Location:'.api_get_path(WEB_ROOT).'?loginFailed=1&error=user_password_incorrect');
  exit;
}
?>