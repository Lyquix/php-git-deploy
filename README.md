# php-git-deploy
_PHP script that automatically deploys code from git repositories (Github, BitBucket, or other) to a target directory_

## Overview

This script allows you to deploy code from your git repository to your server without any additional 3rd party services. We developed this script to deploy code for websites. Make sure you understand how this script works to determine if it is appropriate for your project.

Both GitHub and BitBucket offer _Webhooks_ that can be used to automatically execute this script when a commit is pushed to the the repository, and automate the deployment of your code to from the repository to your server.

This script creates a local repository in your server (git directory), in a directory different from the server production files (target directory, e.g. the document root in the case of a web server). There are two reasons for separating the local git directory from the target directory: First, in most cases, production directories contain files that are not part of the repository and perfoming git operations directly on production files may cause problems. Second, in the case of websites making the local git repo also your production directory would expose the `.git` directory to the public.

### Typical Operation

Each time there is a commit pushed to the repository or a pull request merged, the GitHub/BitBucket Webhooks trigger this script. This script checks the HTTP headers and payload sent by the Webhook and it ignores any events other than push and pull request merge, as well as any events on branches other than one for which this script is configured. 

The script fetches the repository and then checks out the most recent commit in the configured default branch. Following, it uses `rsync` to add and modify any files in the target directory that differ from the git directory. The script uses the information from the commit to identify any files that have been removed from the repository, and deletes them from the target directory.

Any other files that exist in the target directory that are not tracked in the repo are not affected. This normally includes media files, configuration files, etc.

At the end of each execution, the script writes a `VERSION` file in the target directory that contains the hash value of the latest commit that has been deployed. This is used in subsequent executions of the script to determine what is the last commit that was deployed and what files need to be deleted. If no version file is found the script assumes that there have been no previous deployments.

### Manual Use

You can also trigger the script manually. If no GET parameters are passed in the URL (other than the access token), the script deploys the most recent commit for the default branch. However, it is possible to specify a different branch as long as it is included in the configuration in the list of allowed branches, as well as a different commit. This functionality can be used when you need to set your target directory to a specific branch/commit.

## Requirements

* Medium to high technical skill level
* Access to your server command line
* Access to the user that runs PHP scripts (e.g. www-data)
* Access to your server production directory
* `git` and `rsync` must be installed
* `exec` must be allowed in PHP scripts
* Access to the repository

NOTE: this script was developed and tested using:

 * git 2.9 and later
 * rsync 3.1.2
 * Ubuntu 14.04 and 16.04

We recommend using up-to-date versions of git and rsync. If you are using a different distribution of Linux you should be able to easily adapt the server setup instructions.

## Server Setup

If you are using a private repository start here. If you are using a public repository you can jump ahead to the point indicated below.

* Log in as the PHP user (e.g. www-data): 
```
% su www-data
```
* Create the directory for storing SSH keys: 
```
% mkdir ~/.ssh
```
NOTE: if you get an error, it could be that the home directory is not owned by the www-data user. Check that the home directory for www-data is owned by www-data, and if not, correct its ownership. 

* Change directory: 
```
% cd ~/.ssh
```
* Create deployment keys for GitHub/Bitbucket: 
```
% ssh-keygen -t rsa
```
* When prompted use file name `github_rsa` or `bitbucket_rsa` depending on what service you use. When prompted for a passphrase, leave it blank.
* Create a new file `config` that will tie specific servers with keys. If you are using BitBucket, insert the following lines into the `config` files:
```
Host bitbucket.org
    IdentityFile ~/.ssh/bitbucket_rsa
```
or if you are using GitHub:
```
Host github.com
    IdentityFile ~/.ssh/github_rsa
```
* You must connect to the repository for the first time to verify the SSH keys. In the following commands replace BRANCH with the name of the branch, and REPOSITORY with the SSH address of your repository in Github or BitBucket (e.g. git@bitbucket.org:username/reponame.git). When prompted answer yes:
```
% mkdir ~/git
% cd ~/git
% git clone --depth=1 --branch BRANCH REPOSITORY
% rm -rf ~/git
```

NOTE: you can ignore the permission denied error message you will see at this point

If you are using a public repository you can start here.

* Download `deploy.php` script and `deploy-config.orig.php` sample configuration file to your webserver, and place them in a directory accessible via a public URL (typically your target directory)
* Rename `deploy-config.orig.php` to `deploy-config.php` and edit its configuration, as follows:
  * __DISABLED__: (optional) change to `true` to disable the script and prevent its execution. This feature provides an extra level of security.
  * __IP_ALLOW__: (optional) array of single IP addresses or subnets (in CIDR format, e.g. 203.2.54.0/24) that are allowed to execute the script. If not defined, or array is empty, any IP address is allowed. Supports IPv4 and IPv6.
  * __REMOTE_REPOSITORY__: for public repositories you can use the HTTPS address (e.g. https://github.com/username/reponame.git), and for private repositories you will need to use the SSH address (e.g. git@bitbucket.org:username/reponame.git). You can get these addresses by browsing the repository page on GitHub or BitBucket.
  * __BRANCH__: this is the array of branches allowed to deploy with this script. The first element in the array is considered the default branch and it is the only branch that will be allowed for webhook triggers from Github/BitBucket, or when no branch is specified in the GET parameters. The other branches are allowed only on manual triggers.
  * __ACCESS_TOKEN__: a secret string that must be configured to provide protection against abuse. More on security below.
  * __GIT_DIR__: the full path of the directory where the Git repository will be cloned. This should be different than the target directory, it should not be your within your website document root, and should not be accessible publicly. Include the trailing slash. (e.g. /srv/www/domain.com/git/)
  * __TARGET_DIR__: the full path of the directory of your target directory. This is typically the website document root. Include the trailing slash. (e.g. /srv/www/domain.com/public_html/)
  * __LOG_FILE__: (optional) the full path of file to log all script output
  * __EMAIL_NOTIFICATIONS__: (optional) email address to which a copy of the script output will be sent
  * __TIME_LIMIT__: maximum time allowed for each command, in seconds. 60 should be fine unless your deployments are massive. Adjust if necessary.
  * __EXCLUDE_FILES__: (optional) array of files or filename patterns which won't be copied to `TARGET_DIR` . By default it's `.git`.
  * __RSYNC_FLAGS__: (optional) override rsync flags. By default, it's `-rltgoDzvO` .
  * __COMMANDS_BEFORE_RSYNC__: (optional) array of commands executed between pulling remote repository and copying files to target directory. These commands are executed under `GIT_DIR` directory.
  * __COMMANDS_AFTER_RSYNC__: (optional) array of commands executed after copying files to target directory. These commands are executed under `TARGET_DIR` directory.

NOTE: do not include/track the files `deploy-config.php` and `VERSION` in your repository.

## Repository Setup

If you are using a private repository you need to copy the public key to your repository:

* In the directory `~/.ssh` you will find a `.pub` file. That's your public key. 
* Copy its contents
* In both BitBucket and GitHub go to Settings > Deploy Keys (or Deployment Keys) > Add New
* Enter a name for the key and paste the content of the `.pub` file.

For both private and public repositories you need to configure the Webhooks. You will need as many Webhooks as environments you want to deploy to (development, staging, production, etc.):

* In both BitBucket and GitHub go to Settings > Webhooks > Add Webhook
* Use a descriptive name, for example: _Deploy Development Branch_ or _Deploy Master Branch_
* On URL (or Payload URL) enter the URL of the deploy script with your secret access token, for example: https://domain.com/deploy.php?t=ACCESS_TOKEN
* If your development environment is protected with HTTP password, just add the username and password to the webhook URL as follows:
```
https://username:password@domain.com/deploy.php?t=ACCESS_TOKEN
```
* Choose the specific events that would trigger this webhook:
  * For BitBucket: Push
  * For GitHub: Push, Pull Request

## Deploy Different Branches to Different Environments

If you have multiple branches that you need to deploy to different environments, just copy the script and configuration files, and add its own webhook. For example, you can have a deploy script in your development server and in your production server, with different configurations and their own webhooks. This will allow you to deploy different branches to different environments.

## Manual Triggering

In addition to automatic deployment triggered by webhooks, you can use this script manually to force the deployment of a specific branch and/or commit.

You can open in your browser the same URL you configured in webhooks. This will deploy the default branch to the most recent commit:
```
https://domain.com/deploy.php?t=ACCESS_TOKEN
```
Note that you must have the secret access token to execute the script, otherwise you will get an access denied error.

To deploy a specific branch, use the `b` parameter, for example:
```
https://domain.com/deploy.php?t=ACCESS_TOKEN&b=BRANCH
```

To deploy a specific commit, use the `c` parameter with the short hash, for example:
```
https://domain.com/deploy.php?t=ACCESS_TOKEN&c=COMMIT
```

You can use the `b` and `c` parameters simultaneously to specify a branch and commit, for example:
```
https://domain.com/deploy.php?t=ACCESS_TOKEN&b=BRANCH&c=COMMIT
```

## Security Considerations

You must keep in mind that this script can be dangerous if misused or abused. We recommend the following security considerations, specially for your production environment:

* Treat the access token the same way you would treat a password: choose a long and hard-to-guess string, and keep it secret.
* Make sure the deploy script is accessible through an SSL-protected connection (HTTPS), this will protect the the access token from being intercepted.
* For your production environment, configure only one branch (e.g. master). Even if a 3rd party can get ahold of your access token, they will not be able to change branches, and it is unlikely they can guess a specific commit hash.
* Change the DISABLED parameter to `true` to turn off the script completely. This provides added security if you only deploy code ocassionally.
* Configure IP_ALLOW to the list of IP addresses or subnets that are allowed to run the script for additional security.
* The script doesn't include any sanitation for the parameters that are read from the request. This is because the access token and branch names must match the values in the configuration file, and the commit hash must match a commit from the repo in the specified branch. If the values cannot be validated the script stops.
* The script creates a lock file that is used to ensure that only one instance of the script is running at a given time. This prevents multiple git, rsync, and delete operations from being executed in parallel, and helps protect againts a DoS-type of abuse. 
* The script usage of the latest version deployed to determine what files to delete, and the use of rsync, help protect against performance and load issues that could be caused by rapid firing of the script.

## Troubleshooting

 * `File deploy-config.php does not exist`: make sure you have renamed the file deploy-config.orig.php to deploy-config.php.
 * `Access token is not configured`: the access token cannot be left blank in the configuation file.
 * `Remote repository is not configured`: the remote repository address (either HTTPS or SSH) cannot be left blank in the configuration file.
 * `Branch is not configured`: you must add at least one branch to the branches array in the configuration file.
 * `Git directory is not configured`: the git directory cannot be left blank in the configuration file.
 * `Target directory is not configured`: the target directory cannot be left blank in the configuration file.
 * `File deploy.lock detected, another process already running`: if the script execution is interrupted, the deploy.lock file is not automatically deleted. If you are sure no other deployments are in progress, you can delete this file to allow for the script to run.
 * `Access Denied`: either no access token was provided in the URL, or the wrong token was provided.
 * `Branch BRANCH not allowed, stopping execution`: you need to add the branch you are trying to deploy to the branch array in the configuration file.
 * If the script works correctly when triggered manually but the automatic webhooks don't seem to be doing anything check the webhook requests log in BitBucket/GitHub to find out if they are having any problem reaching your server and script. Remember that the script URL has to be accessible from the public Internet, and if you have an HTTP password, you need to include it in the webhook URL.

## Acknowledgements & References

[simple-php-git-deploy (Marko Marković)](https://github.com/markomarkovic/simple-php-git-deploy)

[Automated git deployments from Bitbucket (Jonathan Nicol)](http://jonathannicol.com/blog/2013/11/19/automated-git-deployments-from-bitbucket/)

[Automatic deployment for bitbucket.org web-based projects (Igor Ll)](https://bitbucket.org/lilliputten/automatic-bitbucket-deploy)

[How do I check for valid Git branch names?](http://stackoverflow.com/a/12093994)

[BitBucket Webhook Event Payloads](https://confluence.atlassian.com/bitbucket/event-payloads-740262817.html)

[GitHub Webhook Event Review](https://developer.github.com/webhooks/)

[GitHub Webhook Event Payloads](https://developer.github.com/v3/activity/events/types/)
