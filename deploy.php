<?php
/*
 * php-git-deploy
 * PHP script for automatic code deployment directly from Github or Bitbucket to your server using webhooks
 * Documentation: https://github.com/Lyquix/php-git-deploy
 */

// Measure execution time
$time = -microtime(true);

// Autoloader for plugin classes
spl_autoload_register(function($className){
	$namespace=str_replace("\\","/",__NAMESPACE__);
	$className=str_replace("\\","/",$className);
	$class=(!defined("PLUGINS_FOLDER") ? "plugins/" : PLUGINS_FOLDER).(empty($namespace)?"":$namespace."/")."{$className}.class.php";
	include_once($class);
});

/* Functions */

// Output buffering handler
function obHandler($buffer) {
	global $output;
	$output .= $buffer;
	return $buffer;
}

// Render error page
function errorPage($msg) {
	header($_SERVER['SERVER_PROTOCOL'] . ' 403 Forbidden', true, 403);
	echo "<html>\n<body>\n"
		 . $msg . "\n"
		 . "</body>\n</html>\n"
		 . "<!--\n~~~~~~~~~~~~~ Prevent browser friendly error page ~~~~~~~~~~~~~~\n"
		 . str_repeat(str_repeat("~", 64) . "\n", 8)
		 . "-->\n";
}

// Command to execute at the end of the script
function endScript($msg = "",$display = false) {
	// Remove lock file
	unlink(__DIR__ . '/deploy.lock');

	// Flush buffer and prepare output for log and email
	ob_end_flush();
	global $output;

	// Remove <head>, <script>, and <style> tags, including content
	$output = preg_replace('/<head[\s\w\W\n]+<\/head>/m', '', $output);
	$output = preg_replace('/<script[\s\w\W\n]+<\/script>/m', '', $output);
	$output = preg_replace('/<style[\s\w\W\n]+<\/style>/m', '', $output);

	// Add heading and strip tags
	$output = str_repeat("~", 80) . "\n"
			  . '[' . date('c') . '] - ' . $_SERVER['REMOTE_ADDR'] . " - b=" . $_GET['b'] . ' c=' . $_GET['c'] . "\n"
			  . strip_tags($output);

	// Decode HTML entities
	$output = html_entity_decode($output);

	// Collapse multiple blank lines into one
	$output = preg_replace('/^\n+/m', "\n", $output);

	// Save to log file
	if(defined('LOG_FILE') && LOG_FILE !== '') error_log($output, 3, LOG_FILE);

	// Send email notification
	if(defined('EMAIL_NOTIFICATIONS') && EMAIL_NOTIFICATIONS !== '') error_log($output, 1, EMAIL_NOTIFICATIONS);

	// Send error callback
	if(!empty($msg) && defined('CALLBACK_CLASSES') && !empty(CALLBACK_CLASSES)){
		foreach (CALLBACK_CLASSES as $class) {
			if(is_callable(array($class,"errorWebhook"))){
				$callback = $class::errorWebhook($msg);
				// prevent outputting after errorPage()
				if($display === true){
					if($callback === true){
						echo "\n[Plugin: ".$class."] webhook successfully sent\n";
					}else{
						echo "ERR! Plugin '".$class."' returned: ".$callback;
					}
				}
			}
		}
	}

	// prevent outputting same error message from errorPage()
	if($display === true){
		die($msg);
	}else{
		die();
	}
}

/* Begin Script Execution */

// Prevent caching
header("Cache-Control: no-store, no-cache, must-revalidate, max-age=0");
header("Cache-Control: post-check=0, pre-check=0", false);
header("Pragma: no-cache");

// Start output buffering
ob_start('obHandler');
$output = '';

// Check if there is a configuration file
if (file_exists(__DIR__ . '/deploy-config.php')) {
	require_once __DIR__ . '/deploy-config.php';
} else {
	$msg = 'File deploy-config.php does not exist';
	errorPage('<h2>'. $msg . '</h2>');
	endScript($msg);
}

// Check configuration errors
$err = array();
if (!defined('ACCESS_TOKEN') || ACCESS_TOKEN === '') $err[] = 'Access token is not configured';
if (!defined('REMOTE_REPOSITORY') || REMOTE_REPOSITORY === '') $err[] = 'Remote repository is not configured';
if (!defined('BRANCH') || BRANCH === '') $err[] = 'Branch is not configured';
if (!defined('GIT_DIR') || GIT_DIR === '') $err[] = 'Git directory is not configured';
if (!defined('TARGET_DIR') || TARGET_DIR === '') $err[] = 'Target directory is not configured';
if (!defined('TIME_LIMIT')) define('TIME_LIMIT', 60);
if (!defined('EXCLUDE_FILES')) define('EXCLUDE_FILES', serialize(array('.git')));
if (!defined('RSYNC_FLAGS')) define('RSYNC_FLAGS', '-rltgoDzvO');

// If there is a configuration error
if (count($err)) {
	errorPage("<h2>Configuration Error</h2>\n<pre>\n" . implode("\n", $err) . "\n</pre>");
	endScript("Configuration Error:\n\n" . implode("\n", $err));
}

// Check if lock file exists
if (file_exists(__DIR__ . '/deploy.lock')) {
	$msg = 'File deploy.lock detected, another process already running';
	errorPage('<h2>' . $msg . '</h2>');
	endScript($msg);
}

// Create lock file
$fh = fopen(__DIR__ . '/deploy.lock', 'w');
fclose($fh);

// Check if IP is allowed
if(defined('IP_ALLOW') && count(unserialize(IP_ALLOW))) {
	$allow = false;
	foreach(unserialize(IP_ALLOW) as $ip_allow) {
		if(strpos($ip_allow, '/') === false) {
			// Single IP
			if(inet_pton($_SERVER['REMOTE_ADDR']) == inet_pton($ip_allow)) {
				$allow = true;
				break;
			}
		}
		else {
			// IP range
			list($subnet, $bits) = explode('/', $ip_allow);
			// Convert subnet to binary string of $bits length
			$subnet = unpack('H*', inet_pton($subnet));
			foreach($subnet as $i => $h) $subnet[$i] = base_convert($h, 16, 2);
			$subnet = substr(implode('', $subnet), 0, $bits);
			// Convert remote IP to binary string of $bits length
			$ip = unpack('H*', inet_pton($_SERVER['REMOTE_ADDR']));
			foreach($ip as $i => $h) $ip[$i] = base_convert($h, 16, 2);
			$ip = substr(implode('', $ip), 0, $bits);
			if($subnet == $ip) {
				$allow = true;
				break;
			}
		}
	}
	if(!$allow) {
		$msg = 'Access Denied';
		errorPage('<h2>' . $msg . '</h2>');
		endScript($msg);
	}
}

// If there's authorization error
if (!isset($_GET['t']) || $_GET['t'] !== ACCESS_TOKEN || DISABLED === true) {
	$msg = 'Access Denied';
	errorPage('<h2>' . $msg . '</h2>');
	endScript($msg);
}
?>
<!DOCTYPE html>
<html lang="en">
<head>
<meta charset="utf-8">
<meta name="robots" content="noindex">
<title>PHP Git deploy script</title>
<style>
body { padding: 0 1em; background: #222; color: #fff; }
h2, .error { color: #c33; }
.prompt { color: #6be234; }
.command { color: #729fcf; }
.output { color: #999; }
</style>
</head>
<body>
<pre>
<?php
// The branch
$branch = '';

if (!function_exists('getallheaders')) {
	function getallheaders() {
		$headers = [];
		if (isset($_SERVER['CONTENT_TYPE']))
			$headers['Content-Type'] = $_SERVER['CONTENT_TYPE'];
		if (isset($_SERVER['CONTENT_LENGTH']))
			$headers['Content-Length'] = $_SERVER['CONTENT_LENGTH'];
		foreach ($_SERVER as $name => $value) {
			if (substr($name, 0, 5) == 'HTTP_') {
				$name = substr($name, 5);
				$name = str_replace('_', ' ', $name);
				$name = ucwords(strtolower($name));
				$name = str_replace(' ', '-', $name);
				$name = str_replace('X-Github', 'X-GitHub', $name);
				$headers[$name] = $value;
			}
		}
		return $headers;
	}
}

// Process request headers
$headers = getallheaders();
if(isset($headers['X-Event-Key'])) {
	// Bitbucket webhook
	echo "\nBitbucket headers detected\n";
	// Get payload
	$payload = json_decode(file_get_contents('php://input'));
	// Accept only push and pull request merge events
	if($headers['X-Event-Key'] == 'repo:push') {
		// Check branch
		$branch = $payload->push->changes[0]->new->name;
	} else if($headers['X-Event-Key'] == 'pullrequest:fulfilled') {
		// Check branch
		$branch = $payload->pullrequest->destination->branch->name;
	} else {
		$msg = 'Only push and merged pull request events are processed';
		echo "\n" . $msg . "\n\nDone.\n</pre></body></html>";
		endScript($msg);
	}
} else if(isset($headers['X-GitHub-Event'])) {
	// Github webhook
	echo "\nGithub headers detected\n";
	// Get payload
	if($headers['Content-Type'] == 'application/json') $payload = json_decode(file_get_contents('php://input'));
	else $payload = json_decode($_POST['payload']);
	// Accept only push and pull request merge events
	if($headers['X-GitHub-Event'] == 'push') {
		// Check branch
		$branch = explode('/', $payload->ref)[2];
	} else if($headers['X-GitHub-Event'] == 'pull_request' && $payload->action == 'closed' && $payload->pull_request->merged == true) {
		// Check branch
		$branch = $payload->pull_request->head->ref;
	} else {
		$msg = 'Only push and merged pull request events are processed';
		echo "\n" . $msg . "\n\nDone.\n</pre></body></html>";
		endScript($msg);
	}
}

// Branch from webhook?
if($branch) {
	// Only main branch is allowed for webhook deployments
	if($branch != unserialize(BRANCH)[0]) {
		$msg = 'Branch ' . $branch . ' not allowed, stopping execution.';
		echo "\n" . $msg . "\n</pre></body></html>";
		endScript($msg);
	}

} else {
	echo "\nNo Bitbucket or Github webhook headers detected. Assumming manual trigger.\n";
	if(isset($_GET['b'])) {
		$branch = $_GET['b'];
		// Check if branch is allowed
		if(!in_array($branch, unserialize(BRANCH))) {
			$msg = 'Branch ' . $branch . ' not allowed, stopping execution.';
			echo "\n" . $msg . "\n</pre></body></html>";
			endScript($msg);
			}
	} else {
		$branch = unserialize(BRANCH)[0];
		echo "No branch specified, assuming default branch $branch\n";
	}
}
?>
Checking the environment ...
Running as <strong><?php echo trim(shell_exec('whoami')); ?></strong>.
<?php
// Check if the required programs are available
$requiredBinaries = array('git', 'rsync');
foreach ($requiredBinaries as $command) {
	$path = trim(shell_exec('which '.$command));
	if ($path == '') {
		header($_SERVER['SERVER_PROTOCOL'] . ' 500 Internal Server Error', true, 500);
		$msg = $command . ' not available. It needs to be installed on the server for this script to work.';
		echo $msg;
		endScript($msg);
	} else {
		$version = explode("\n", shell_exec($command.' --version'));
		printf('<b>%s</b> : %s'."\n"
			, $path
			, $version[0]
		);
	}
}
?>

Environment OK.

Deploying : <?php echo REMOTE_REPOSITORY; ?> (<?php echo $branch; ?>)
to        : <?php echo TARGET_DIR; ?>

<?php
// Runs shell commands in Git directory, outputs command and result
function cmd($command, $print = true, $dir = GIT_DIR) {
	set_time_limit(TIME_LIMIT); // Reset the time limit for each command
	if (file_exists($dir) && is_dir($dir)) {
		chdir($dir); // Ensure that we're in the right directory
	}
	$tmp = array();
	exec($command.' 2>&1', $tmp, $return_code); // Execute the command
	// Output the result
	if($print) {
		printf('
<span class="prompt">$</span> <span class="command">%s</span>
<span class="output">%s</span>
'
			, htmlentities(trim($command))
			, htmlentities(trim(implode("\n", $tmp)))
		);
	}
	// Error handling and cleanup
	if($print && $return_code !== 0) {
		header($_SERVER['SERVER_PROTOCOL'] . ' 500 Internal Server Error', true, 500);
		$msg = "Error encountered! Stopping the script to prevent possible data loss.\nCHECK THE DATA IN YOUR TARGET DIR!";
		echo '<span class="error">' . $msg . '</span>';
		endScript($msg);
	}

	return $tmp;
}

// The commits
$commits = array();

// The checkout commit
$checkout = '';

// The current files version
$version = '';

// Check if there is a git directory
if (!is_dir(GIT_DIR)) {
	// Clone the repository into the GIT_DIR
	echo "\nGit directory not found, cloning repository\n";
	cmd(sprintf(
		'git clone --branch %s %s %s'
		, $branch
		, REMOTE_REPOSITORY
		, GIT_DIR
	));
	// Checkout branch
	echo "\nCheckout branch $branch\n";
	cmd(sprintf(
		'git --git-dir="%s.git" --work-tree="%s" checkout %s'
		, GIT_DIR
		, GIT_DIR
		, $branch
	));
} else {
	// GIT_DIR exists and hopefully already contains the correct remote origin
	// so we'll fetch the changes
	echo "\nFetching repository from origin\n";
	cmd(sprintf(
		'git --git-dir="%s.git" --work-tree="%s" fetch --tags origin %s'
		, GIT_DIR
		, GIT_DIR
		, $branch
	));
	// Checkout branch
	echo "\nCheckout branch $branch\n";
	cmd(sprintf(
		'git --git-dir="%s.git" --work-tree="%s" checkout %s'
		, GIT_DIR
		, GIT_DIR
		, $branch
	));
}

// Get list of all commits
$commits = cmd(sprintf(
	'git --no-pager --git-dir="%s.git" log --pretty=format:"%%h" origin/%s'
	, GIT_DIR
	, $branch)
, false);

// Set checkout commit
if(in_array($_GET['c'], $commits)) {
	$checkout = $_GET['c'];
} else {
	$checkout = reset($commits);
	echo "\nPassed commit hash is blank or doesn't match existing commits. Assuming most recent commit in branch: $checkout\n";
}

// Checkout specific commit
echo "\nReset branch to commit $checkout in git directory\n";
cmd(sprintf(
	'git --git-dir="%s.git" --work-tree="%s" reset --hard %s'
	, GIT_DIR
	, GIT_DIR
	, $checkout
));

// Update the submodules
echo "\nUpdating git submodules in git directory\n";
cmd(sprintf(
	'git --git-dir="%s.git" --work-tree="%s" submodule update --init --recursive'
	, GIT_DIR
	, GIT_DIR
));

// Get current version or assume oldest commit
if(file_exists(TARGET_DIR . 'VERSION')) {
	$version = trim(file_get_contents(TARGET_DIR . 'VERSION'));
	if(!in_array($version, $commits)) {
		$version = end($commits);
		echo "WARNING: version file commit hash doesn't match existing commits, assuming oldest commit $version\n";
	} else echo "Current target directory version is $version\n";
}
else {
	$version = end($commits);
	echo "No version file found, assuming current version is oldest commit\n";
}

// Get list of added, modified and deleted files
echo "\nGet list of files added, modified and deleted from $version to $checkout\n";
$files = cmd(sprintf(
	'git --no-pager --git-dir="%s.git" diff --name-status %s %s'
	, GIT_DIR
	, $version
	, $checkout
));

// Count files that were added or modified. Add removed files to array.
$added = $modified = 0;
$deleted = array();
foreach($files as $file) {
	if(preg_match('/^([ADM])\s*(.*)/', $file, $matches)) {
		switch($matches[1]) {
			case 'A':
				$added++;
				break;
			case 'M':
				$modified++;
				break;
			case 'D':
				$deleted[] = TARGET_DIR . $matches[2];
				break;
		}
	}
}
printf(
	"\nDeploying %d files:\n  Added      %d\n  Modified   %d\n  Deleted    %d\n"
	, count($files)
	, $added
	, $modified
	, count($deleted)
);
echo "\nNOTE: repository files that have been modfied or removed in target directory will be resynced with repository even if not listed in commits\n";

// Run before rsync commands
if(defined('COMMANDS_BEFORE_RSYNC') && count(unserialize(COMMANDS_BEFORE_RSYNC))) {
	echo "\nRunning before rsync commands\n";
	foreach(unserialize(COMMANDS_BEFORE_RSYNC) as $command) {
		cmd($command);
	}
}

// Build exclusion list
$exclude = unserialize(EXCLUDE_FILES);
array_unshift($exclude, '');

// rsync all added and modified files (by default: no deletes, exclude .git directory)
cmd(sprintf(
	'rsync %s %s %s %s'
	, RSYNC_FLAGS
	, GIT_DIR
	, TARGET_DIR
	, implode(' --exclude=', $exclude)
));
echo "\nDeleting files removed from repository\n";

// Delete files removed in commits
foreach($deleted as $file) unlink($file);

// Run after rsync commands
if(defined('COMMANDS_AFTER_RSYNC') && count(unserialize(COMMANDS_AFTER_RSYNC))) {
	echo "\nRunning after rsync commands\n";
	foreach(unserialize(COMMANDS_AFTER_RSYNC) as $command) {
		cmd($command, true, TARGET_DIR);
	}
}

// Cleanup work tree from build results, etc
if(defined('CLEANUP_WORK_TREE') && !empty(CLEANUP_WORK_TREE)){
	echo "\nCleanup work tree\n";
	cmd(sprintf(
		'git --git-dir="%s.git" --work-tree="%s" clean -dfx'
		, GIT_DIR
		, GIT_DIR
	));
}

// Update version file to current commit
echo "\nUpdate target directory version file to commit $checkout\n";
cmd(sprintf(
	'echo "%s" > %s'
	, $checkout
	, TARGET_DIR . 'VERSION'
));

// Send success callback
if(defined('CALLBACK_CLASSES') && !empty(CALLBACK_CLASSES)){
	foreach (CALLBACK_CLASSES as $class) {
		if(is_callable(array($class,"successWebhook"))){
			$callback = $class::successWebhook(array(
				'remote' => REMOTE_REPOSITORY,
				'branch' => $branch,
				'targetDirectory' => TARGET_DIR,
				'commit' => $checkout,
				'execTime' => $time + microtime(true)
			));

			if($callback === true){
				echo "\n[Plugin: ".$class."] webhook successfully sent\n";
			}else{
				echo "ERR! Plugin '".$class."' returned: ".$callback;
			}
		}
	}
}
?>

Done in <?php echo $time + microtime(true); ?>sec
</pre>
</body>
</html>
<?php
endScript("",true);