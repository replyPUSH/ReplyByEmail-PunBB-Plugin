<?php
if(!defined('FORUM')){
    define('FORUM_ROOT', '../../');
    require FORUM_ROOT.'include/essentials.php';
}

if (!defined('FORUM_EMAIL_FUNCTIONS_LOADED')){
    require FORUM_ROOT.'include/email.php';
}

require_once(FORUM_ROOT.'/extensions/reply_by_email/class.replybyemail.php');

reply_by_email::handler()->notify()->replypush_controller();
