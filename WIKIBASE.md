# Mix'n'match VM

## Setup VM
* Setup on horizon `eqiad1-r`
* Connect: `ssh magnus@mixnmatch.mix-n-match.eqiad.wmflabs`
* Create proxy to 8181 (wiki), 8282 (wdqs), and 9191 (QS)

## Installation
* Installed via https://github.com/wmde/wikibase-docker/blob/master/setup.sh
* Add myself to group docker: `sudo gpasswd -a magnus docker` _doesn't seem to work_
* Check via: `sudo docker-compose images`
* Start/stop as in https://github.com/wmde/wikibase-docker/blob/master/README-compose.md
* Start with `sudo docker-compose up --no-build -d`

## Configuration
* Add php for shell: `sudo apt-get install php-cli php-curl`
* Add `- ./interface:/var/www/html/interface` to `services/wikibase/volumes` in `docker-compose.yml`
* URLs: http://mixnmatch.wmflabs.org http://mixnmatch-query.wmflabs.org http://mixnmatch-qs.wmflabs.org
* Open new shell on wikibase: `sudo docker exec -it wikibase-docker_wikibase_1 bash`

### MediaWiki
* Install nano:`apt-get update ; apt-get install nano`
* Change MediaWiki Admin password: `php changePassword.php --user=Admin --password=NEWPASSWORD`
* Restrict user account creation and anonymous editing, via https://www.mediawiki.org/wiki/Manual:Preventing_access#Restrict_account_creation
```
$wgGroupPermissions['*']['edit'] = false;
$wgGroupPermissions['*']['createaccount'] = false;
$wgEnableEmail=true;
$wgWBRepoSettings['formatterUrlProperty'] = 'P8'; # Create and link a formatter URL property
```
* Also add that (in similar fashion) to `LocalSettings.php.wikibase-bundle.template` in the Docker image
* Change sidebar: https://www.mediawiki.org/wiki/Manual:Interface/Sidebar

THIS SEEMS BETTER (anon wikibase edits allowed, everything else down):
```
#$wgGroupPermissions['*']['edit'] = false;
$wgGroupPermissions['*']['createaccount'] = false;

$wgNamespaceProtection[NS_MAIN]     = $wgNamespaceProtection[NS_USER]  =
$wgNamespaceProtection[NS_PROJECT]  = $wgNamespaceProtection[NS_IMAGE] =
$wgNamespaceProtection[NS_TEMPLATE] = $wgNamespaceProtection[NS_HELP]  =
$wgNamespaceProtection[NS_CATEGORY] = array( 'editroot' );

$wgNamespaceProtection[1]     = $wgNamespaceProtection[NS_USER_TALK]  =
$wgNamespaceProtection[NS_PROJECT_TALK]  = $wgNamespaceProtection[NS_IMAGE_TALK] =
$wgNamespaceProtection[NS_TEMPLATE_TALK] = $wgNamespaceProtection[NS_HELP_TALK]  =
$wgNamespaceProtection[NS_CATEGORY_TALK] = array( 'editroot' );

$wgNamespaceProtection[120] = [ 'ns120_edit' ];
$wgGroupPermissions['*']['ns120_edit'] = true;
```

### QuickStatements
Setup QS as described here (especially the email hack!): https://github.com/wmde/wikibase-docker/blob/master/quickstatements/README.md
You will get
`You have been assigned a consumer token of ID1 and a secret token of ID2. Please record these for future reference.`
Edit nano docker-compose.yml outside the docker image, then restart

## Docker container issues
* QuickStatements does not display commands; public_html/config.js needs to use https for api URL
* QuickStatements conatiner PHP does not appear to have INTL module, no Normalizer class or normalizer_normalize function
* SPARQL engine links items to http://wikibase.svc/entity/ instead of actual URL
* Cirrus search is broken