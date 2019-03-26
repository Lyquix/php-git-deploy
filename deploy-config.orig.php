<?php
/*
 * php-git-deploy configuration file
 * PHP script for automatic code deployment directly from Github or Bitbucket to your server using webhooks
 * Documentation: https://github.com/Lyquix/php-git-deploy
 */
 
/*
 * For array options, you can use any of the following formats:
 * define('OPTION', ['value1', 'value2', 'value3']); // works only in PHP 7.0+
 * define('OPTION', 'value1, value2, value3');
 * define('OPTION', serialize(array('value1', 'value2', 'value3'))); // for older PHP versions that disallow to use arrays as const value
 * define('OPTION', ''); // for empty arrays
 *
 */

/* DISABLED: Set to true to prevent the execution of this script.
 * Useful to control activation of this script only when needed.
 *
 */
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
define('IP_ALLOW', '');

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
define('BRANCH', 'branch, mybranch, yourbranch');

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

/* EXCLUDE_FILES:
 * Array of files excluded from rsync (they will appear in GIT_DIR, but not in TARGET_DIR)
 * By default, only .git directory is excluded.
 * It's recommended to leave '.git' excluded and add something more if needed.
 * Example: define('EXCLUDE_FILES', ['.git', '.gitignore', '*.less', '*.scss']);
 *
 */
define('EXCLUDE_FILES', '.git');

/* RSYNC_FLAGS:
 * Custom flags to run rsync with
 * Default: '-rltgoDzvO'
 *  -r recursive
 *  -l recreate symlinks at destination
 *  -t tranfer modification time
 *  -g preserve group ownership
 *  -o preserve owner
 *  -D transfer special files
 *  -z compress files during transfer
 *  -v verbose
 *  -O omit directories when preserving modification times
 * Do not change them if not necessary
 * Example: '-rltDzvO' (don't changes owner:group of copied files,
 * useful for vhosts than require separate group for document_root to be accessible by webserver)
 */
define('RSYNC_FLAGS', '-rltgoDzvO');

/* COMMANDS_BEFORE_RSYNC:
 * Run commands before running rsync. Default: empty array
 * This commands will be run under GIT_DIR after checkout from remote repository
 * Useful for running build tasks
 * Example: define('COMMANDS_BEFORE_RSYNC', ['composer install']);
 */
define('COMMANDS_BEFORE_RSYNC', '');

/* COMMANDS_AFTER_RSYNC:
 * Run commands after running rsync. Default: empty array
 * This commands will be run under TARGET_DIR after copying files from GIT_DIR
 * Useful for doing some cleanups
 * Example: define('COMMANDS_AFTER_RSYNC', ['rm cache/*.php -f']);
 */
define('COMMANDS_AFTER_RSYNC', '');

/* CLEANUP_WORK_TREE:
 * Clean GIT_DIR from leftovers after custom commands
 * Set to true if you wish to clean up GIT_DIR after running all custom commands
 * Useful if your custom commands create intermediate files you want not to keep between deployments
 * However, intermediate files would not be cleaned up from TARGET_DIR
 */
define('CLEANUP_WORK_TREE', false);

/* CALLBACK_CLASSES:
 * Classnames containing callback functions to
 * be triggered at the end of the script on success or failure.
 * Useful to connect to your preferred notification system.
 * Note:
 * 	- Classes must implement Plugin Interface
 * 	- Class names have to match their file-name (case-sensitive), e.g. "Discord.class.php" has class "Discord"
 * 	- The array keys contain only the class name, e.g. array("Discord") 
 */
define('CALLBACK_CLASSES', '');

/* PLUGINS_FOLDER:
 * Folder containing all webhook plugins/classes, default: 'plugins/'
 */
define('PLUGINS_FOLDER','plugins/');