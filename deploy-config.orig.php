<?php
/*
 * Configuration file for automatic Git deployment
 */

/* 
 * Address of the remote Git repo. For private repos use the SSH address 
 * Examples: 
 * https://github.com/username/reponame.git
 * git@bitbucket.org:username/reponame.git
 * 
 */
define('REMOTE_REPOSITORY', '');

/* Name of the branches allowed to deploy */
define('BRANCH', serialize(array(
	'branch',
	'mybranch',
	'yourbranch'
)));

/*
 * Access token to authorize execution of this script 
 * Script will not execute if left blank
 * You must add this token to the deployment URL as the value of parameter t
 * Example: http://domain.com/deploy.php?t=AccessToken
 */
define('ACCESS_TOKEN', '');

/* Directory where the repo will be cloned */
define('GIT_DIR', '/srv/www/domain.com/git/');

/* Directory where the production files are located */
define('TARGET_DIR', '/srv/www/domain.com/public_html/');

/* Time limit for each command */
define('TIME_LIMIT', 60);
