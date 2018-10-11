<?php

require_once ( 'wikidata.php' ) ;

class MixnMatch {
	public $config ;
	public $wil_local ;
	public $wil_wd ;
	public $wd_sparql_api = 'https://query.wikidata.org/sparql' ;

	function __construct ( $config_json_url = './config.json' ) {
		$this->config = json_decode ( file_get_contents ( $config_json_url ) ) ;
		$this->wil_wd = new WikidataItemList ;
		$this->wil_local = new WikidataItemList ;
		$this->wil_local->wikidata_api_url = $this->config->mwapi ;
	}

	public function getSPARQL ( $query , $base_url = '' ) {
		if ( $base_url == '' ) $base_url = $this->config->sparql_url ;
		$url = "{$base_url}?format=json&query=" . urlencode($query) ;
		return json_decode(file_get_contents($url)) ;
	}

	public function loadCatalogMappingFromMnM ( $catalog ) {
		return $this->getSPARQL ( "SELECT DISTINCT ?q ?wdq ?extid { ?q wdt:{$this->config->props->catalog} wd:{$catalog} ; wdt:{$this->config->props->ext_id} ?extid OPTIONAL { ?q wdt:{$this->config->props->manual} ?wdq } }" ) ;
	}

	public function getWikidataPropertyForCatalog ( $catalog ) {
		$this->wil_local->loadItem ( $catalog ) ;
		$i = $this->wil_local->getItem ( $catalog ) ;
		if ( !isset($i) ) die ( "Can't find item for catalog {$catalog}\n" ) ;
		if ( !$i->hasClaims($this->config->props->wd_prop) ) die ( "Catalog {$catalog} has no Wikidata property set\n" ) ;
		$wdprop = $i->getFirstString ( $this->config->props->wd_prop ) ;
		return $wdprop ;
	}

	public function loadCatalogMappingFromWD ( $catalog ) {
		$wdprop = $this->getWikidataPropertyForCatalog ( $catalog ) ;
		$sparql = "SELECT ?q ?extid { ?q wdt:{$wdprop} ?extid }" ;
		return $this->getSPARQL ( $sparql , $this->wd_sparql_api ) ;
	}
	
} ;

?>