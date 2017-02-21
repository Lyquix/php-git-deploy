<?php
/*
 * php-git-deploy configuration file
 * PHP script for automatic code deployment directly from Github or Bitbucket to your server using webhooks
 * Documentation: https://github.com/Lyquix/php-git-deploy
 */

/* DISABLED: Set to true to prevent the execution of this script. cript only when needed */
define('DISABLED', false);

/* IP_ALLOW: 
 * Array of IP addresses and ranges in CIDR notation that are allowed to execute
 * the script. Supports IPv4 and IPv6. Leave array empty to allow all IPs.
 * GitHub IP ranges are 192.30.252.0/22 and 2620:112:3000::/44
 * (https://help.github.com/articles/github-s-ip-addresses/)
 * BitBucket IP ranges are 104.192.143.192/28 and 2401:1d80:1010::/64 
 * (https://confluence.atlassian.com/bitbucket/what-are-the-bitbucket-cloud-ip-addresses-i-should-use-to-configure-my-corporate-firewall-343343385.html)
 *
 */
define('IP_ALLOW', serialize(array(
)));

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

/* LOG_FILE: Full path of log file. Leave blank to disable logging */
define('LOG_FILE', '/srv/www/domain.com/logs/deploy.log');

/* EMAIL_NOTIFICATIONS: Email address where notifications are sent. Leave blank to disable email notifications */
define('EMAIL_NOTIFICATIONS', '');

/* TIME_LIMIT: Time limit for each command */
define('TIME_LIMIT', 60);
