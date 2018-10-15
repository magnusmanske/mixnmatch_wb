#!/usr/bin/php
<?php

require_once ( 'interface/mixnmatch.php' ) ;
require_once ( 'scraper.php' ) ;

if ( !isset($argv[1]) ) die ( "Needs command\n" ) ;
$command = $argv[1] ;

$mnm = new MixnMatch ( 'interface/config.json' ) ;

function getParamQ ( $pos = 2 ) {
	global $argv ;
	if ( !isset($argv[$pos]) or !preg_match ( '/^Q\d+$/' , $argv[$pos] ) ) die ( "Usage: {$argv[0]} {$argv[1]} Qxxx\n" ) ;
	return $argv[$pos] ;
}

if ( $command == 'auto' ) {

	$mnm->addAutoMatches ( getParamQ() ) ;

} else if ( $command == 'deauto' ) {

	$mnm->removeAutoMatches ( getParamQ() ) ;

} else if ( $command == 'sync_wd' ) {

	$mnm->syncCatalogWithWikidata ( getParamQ() ) ;

} else if ( $command == 'create_catalog' ) {

# TODO check params
	$params = [] ;
	$params['label'] = $argv[2] ;
	$params['url'] = $argv[3] ;
	if ( isset($argv[4]) ) $params['wd_prop'] = $argv[4] ;

	$s = new Scraper () ;
	$q = $s->getorCreateCatalogItem ( $params ) ;
	if ( !isset($q) ) die ( "Could not get a catalog ID for ".json_encode($p)."\n") ;
	print "{$q}\n" ;

} else if ( $command == 'scrape' ) {

	$s = new Scraper ( getParamQ() ) ;
	$s->ensureDirectoryStructureForCatalog () ;
	$s->updateURLs () ;
	$s->cachePages () ;
	$p = $s->getCatalogSpecificIncludePath() ;
	if ( file_exists($p) ) include_once ( $p ) ;
	$s->processPages () ;
	$mnm->syncCatalogWithWikidata ( $s->catalog_q ) ;
	$mnm->addAutoMatches ( $s->catalog_q ) ;

} else {

	die ( "Unknown command '{$command}'\n" ) ;

}


?>