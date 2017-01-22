# php-git-deploy
_PHP script that automatically deploys code from git repositories (Github, BitBucket, or other) to a target directory_

## Overview

This script allows you to deploy code from your git repository to your server without any additional services. We developed this script to deploy code for websites. Make sure you understand how this script works to determine if it is appropriate for your environment.

Both GitHub and BitBucket offer _Webhooks_ that can be used to automatically execute this script when a commit is pushed to the the repository, and automate the deployment of your code to from the repository to your server.

The script creates a local repository in your server (git directory) it a directory different from the server production files (target directory, e.g. the document root in the case of a web server). There are two reasons for separating the local git directory from the target directory: in most cases, production directories contain files that are not part of the repository, and in the case of websites placing the local git repo in a production directory would expose the `.git` directory to the public.  

In normal use, each time there is a commit pushed to the repository or a pull request merged, GitHub/BitBucket triggers this script. The script checks the headers and payload sent by the Webhook and ignores events other than push and pull request merge, as well as any events on branches other than one for which the script is configured.

The script fetches the repository, and then checks out the most recent commit in the configured branch. Following, it uses `rsync` to add and modify any files in the target directory that differ from the git directory. The script uses the information from the commit to identify any files that have been removed from the repository, and deletes them from the target directory.

Any other files that exist in the target directory but are not tracked in the repo are not affected. This normally include media files, configuration files, etc.

At the end each execution, the script writes a version file in the target directory that contains the hash value of the latest commit that has been deployed. This is used in subsequent executions of the script to determine what is the last commit that was deployed and what files need to be deleted. If no version file is found the script assumes that there have been no previous deployments.

You can also trigger the script manually. If no GET parameters are passed URL (other than the access token), the script deploys the most recent commit for the default branch configured for the script. However, it is possible to specify a different branch as long as it is included in the configuration in the list of allowed branches, as well as a different commit. This functionality can be used when you need to set your target directory to a specific branch/commit.

## Requirements

* Medium to high technical skill level
* Access to your server command line
* Access to the user that runs PHP scripts (e.g. www-data)
* Access to your server production directory
* `git` and `rsync` must be installed
* `exec` must be allowed in PHP scripts
* Access to the repository

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
NOTE: if you get an error, it could be that the home directory is not owned by the www-data user.

* Change directory: 
```
% cd ~/.ssh
```
* Create deployment keys for GitHub/Bitbucket: 
```
% ssh-keygen -t rsa
```
* When prompted use file name `github_rsa` or `bitbucket_rsa` depending on what service you use. When prompted for a passphrase, leave it blank.
* Create a new file `config` that will tie specific servers with keys. If you are using BitBucket, add the following:
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

* Download `deploy.php` and `deploy-config.orig.php` to your webserver, accessible via a public URL
* Rename `deploy-config.orig.php` to `deploy-config.php` and edit its configuration:
  * __REMOTE_REPOSITORY__: for public repositories you can use the HTTPS address (e.g. https://github.com/username/reponame.git), and for private repositories you will need to use the SSH address (e.g. git@bitbucket.org:username/reponame.git)
  * __BRANCH__: this is the array of branches allowed to deploy with this script. The first branch is the only one that will be allowed for webhook triggers from Github/BitBucket. The others will be allowed on manual triggers.
  * __ACCESS_TOKEN__: a secret string that must be configured to ensure some reasonable level of security and prevent abuse. More on security below.
  * __GIT_DIR__: the full path of the directory where the Git repository will be cloned. This should be different than the production directory, and should not be accessible publicly. Include the trailing slash.
  * __TARGET_DIR__: the full path of the directory of your production files. Include the trailing slash.
  * __TIME_LIMIT__: maximum time allowed for each command, in seconds. 60 should be fine unless your deployments are massive. Adjust if necessary.

## Repository Setup

If you are using a private repository you need to copy the public key to your repository:

* In the directory `~/.ssh` you will find a `.pub` file. That's your public key. 
* Copy its contents
* In both BitBucket and GitHub go to Settings > Deploy Keys (or Deployment Keys) > Add New
* Enter a name for the key and paste the content of the `.pub` file.

For both private and public repositories you need to configure the Webhooks. You will need as many Webhooks as environments you want to deploy to (development, production, etc.):

* In both BitBucket and GitHub go to Settings > Webhooks > Add Webhook
* Use a descriptive name, for example: _Deploy development_ or _Deploy master_
* On URL (or Payload URL) enter the URL of the deploy script with your secret access token, for example: https://domain.com/deploy.php?t=ACCESS_TOKEN
* If your development environment is protected with HTTP password, just add the username and password to the webhook URL as follows:
```
https://username:password@domain.com/deploy.php?t=ACCESS_TOKEN
```
* Choose the specific events that would trigger this webhook:
  * BitBucket: Push
  * GitHub: Push, Pull Request

## Deploy Different Branches to Different Environments

If you have multiple branches that you need to deploy to different environments, just copy the script and its configuration, and add its own webhook. For example, you can have a deploy script in your development server and in your production server, with different configurations and their own webhooks. This will allow you to deploy different branches to different environments.

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

* Treat the ACCESS_TOKEN the same way you would treat a password: choose a long and hard-to-guess string, and keep it secret.
* Ideally, the deploy script is accessible through an SSL connection (HTTPS), to minimize the risk of the ACCESS_TOKEN being intercepted.
* The script doesn't include any sanitation rules for the parameters that are read from the request because all of them pass through validation from configuration settings. If the values cannot be validated the script stops. Let me know if there is anything else that can be done to make this script more secure. 

## Acknowledgements & References

[simple-php-git-deploy (Marko Marković)](https://github.com/markomarkovic/simple-php-git-deploy)

[Automated git deployments from Bitbucket (Jonathan Nicol)](http://jonathannicol.com/blog/2013/11/19/automated-git-deployments-from-bitbucket/)

[Automatic deployment for bitbucket.org web-based projects (Igor Ll)](https://bitbucket.org/lilliputten/automatic-bitbucket-deploy)

[How do I check for valid Git branch names?](http://stackoverflow.com/a/12093994)

[BitBucket Webhook Event Payloads](https://confluence.atlassian.com/bitbucket/event-payloads-740262817.html)

[GitHub Webhook Event Review](https://developer.github.com/webhooks/)

[GitHub Webhook Event Payloads](https://developer.github.com/v3/activity/events/types/)
