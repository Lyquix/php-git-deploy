<?php
/*
 * php-git-deploy configuration file
 * PHP script for automatic code deployment directly from Github or Bitbucket to your server using webhooks
 * Documentation: https://github.com/Lyquix/php-git-deploy
 */

/*
 * REMOTE_REPOSITORY:
 * Address of the remote Git repo. For private repos use the SSH address 
 * Examples: 
 * https://github.com/username/reponame.git
 * git@bitbucket.org:username/reponame.git
 * 
 */
define('REMOTE_REPOSITORY', '');

/* 
 * BRANCH:
 * Array of branch names allowed to deploy 
 * First name in array is considered the default branch and only one allowed for automatic deployments
 */
define('BRANCH', serialize(array(
	'branch',
	'mybranch',
	'yourbranch'
)));

/*
 * ACCESS_TOKEN:
 * Secret code/password used to authorize execution of this script 
 * Script will not execute if left blank
 * You must add this token to the deployment URL as the value of parameter t
 * Example: http://domain.com/deploy.php?t=ACCESS_TOKEN
 */
define('ACCESS_TOKEN', '');

/* GIT_DIR: Directory where the repo will be cloned */
define('GIT_DIR', '/srv/www/domain.com/git/');

/* TARGET_DIR: Directory where the production files are located */
define('TARGET_DIR', '/srv/www/domain.com/public_html/');

/* TIME_LIMIT: Time limit for each command */
define('TIME_LIMIT', 60);
