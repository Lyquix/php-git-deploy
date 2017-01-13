<?php
/*
 * php-git-deploy
 * PHP script for automatic code deployment directly from Github or Bitbucket to your server using webhooks
 * Documentation: https://github.com/Lyquix/php-git-deploy
 */
// Check if there is a configuration file
if (file_exists(dirname(__FILE__) . '/deploy-config.php')) {
	require_once dirname(__FILE__) . '/deploy-config.php';
} else {
	die('File deploy-config.php does not exist');
}
$err = array();
if (!defined('ACCESS_TOKEN')) $err[] = 'Access token is not configured';
if (!defined('REMOTE_REPOSITORY')) $err[] = 'Remote repository is not configured';
if (!defined('BRANCH')) $err[] = 'Branch is not configured';
if (!defined('GIT_DIR')) $err[] = 'Git directory is not configured';
if (!defined('TARGET_DIR')) $err[] = 'Target directory is not configured';
if (!defined('TIME_LIMIT')) define('TIME_LIMIT', 60);

// If there's authorization error, set the correct HTTP header.
if (!isset($_GET['t']) || $_GET['t'] !== ACCESS_TOKEN || ACCESS_TOKEN === '') {
	header($_SERVER['SERVER_PROTOCOL'] . ' 403 Forbidden', true, 403);
}
// Prevent caching
header("Cache-Control: no-store, no-cache, must-revalidate, max-age=0");
header("Cache-Control: post-check=0, pre-check=0", false);
header("Pragma: no-cache");
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
<?php
if (!isset($_GET['t']) || $_GET['t'] !== ACCESS_TOKEN) {
	header($_SERVER['SERVER_PROTOCOL'] . ' 403 Forbidden', true, 403);
	die('<h2>Access Denied</h2>');
}
if (count($err) || ACCESS_TOKEN === '' || REMOTE_REPOSITORY === '' || BRANCH === '' || GIT_DIR === '' || TARGET_DIR === '') {
	header($_SERVER['SERVER_PROTOCOL'] . ' 403 Forbidden', true, 403);
	die("<h2>Configuration Error</h2>\n<pre>\n" . implode("\n", $err) . "\n</pre>");
}
?>
<pre>
<?php
// The branch
$branch = '';
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
		echo "\nOnly push and merged pull request events are processed\n\nDone.\n</pre></body></html>";
		exit;
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
		echo "\nOnly push and merged pull request events are processed\n\nDone.\n</pre></body></html>";
		exit;
	}
} 
// Branch from webhook?
if($branch) {
	// Only main branch is allowed for webhook deployments
	if($branch != unserialize(BRANCH)[0]) {
		echo "\nBranch $branch not allowed, stopping execution.\n</pre></body></html>";
		exit;
	}

} else {
	echo "\nNo Bitbucket or Github webhook headers detected. Assumming manual trigger.\n";
	if(isset($_GET['b'])) {
		$branch = $_GET['b'];
		// Check if branch is allowed
		if(!in_array($branch, unserialize(BRANCH))) {
			echo "\nBranch $branch not allowed, stopping execution.\n</pre></body></html>";
			exit;
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
		die(sprintf('<div class="error"><b>%s</b> not available. It needs to be installed on the server for this script to work.</div>', $command));
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
function cmd($command, $print = true) {
	set_time_limit(TIME_LIMIT); // Reset the time limit for each command
	if (file_exists(GIT_DIR) && is_dir(GIT_DIR)) {
		chdir(GIT_DIR); // Ensure that we're in the right directory
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
		printf('<span class="error">Error encountered! Stopping the script to prevent possible data loss.
CHECK THE DATA IN YOUR TARGET DIR!</span>
'
		);
		break;
	}

	return $tmp;
}

// The commits
$commits = array();
// The checkout commit
$checkout = '';
// The current files version
$version = '';
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
	// Checkout branch
	echo "\nCheckout branch $branch\n";
	cmd(sprintf(
		'git --git-dir="%s.git" --work-tree="%s" checkout %s'
		, GIT_DIR
		, GIT_DIR
		, $branch
	));
	echo "\nFetching repository from origin\n";
	cmd(sprintf(
		'git --git-dir="%s.git" --work-tree="%s" fetch --tags origin %s'
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
cmd('git submodule update --init --recursive');
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
// rsync all added and modified files (no deletes, exclude .git directory)
cmd(sprintf(
	'rsync -rltgoDzvO %s %s --exclude=.git'
	, GIT_DIR
	, TARGET_DIR
));
echo "\nDeleting files removed from repository\n";
// Delete files removed in commits
foreach($deleted as $file) unlink($file);
// Update version file to current commit
echo "\nUpdate target directory version file to commit $checkout\n";
cmd(sprintf(
	'echo "%s" > %s'
	, $checkout
	, TARGET_DIR . 'VERSION'
));

?>

Done.
</pre>
</body>
</html>
