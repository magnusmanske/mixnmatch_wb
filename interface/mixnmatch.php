<?php

require_once ( 'wikidata.php' ) ;

class MixnMatch {
	public $config ;
	public $wil_local ;
	public $wil_wd ;
	public $wd_sparql_api = 'https://query.wikidata.org/sparql' ;
	public $sparql_label_service = ' SERVICE wikibase:label { bd:serviceParam wikibase:language "[AUTO_LANGUAGE],en". } ' ;
	private $cookiejar ; # For doPostRequest
	private $browser_agent = "Mozilla/5.0 (Macintosh; Intel Mac OS X 10.10; rv:57.0) Gecko/20100101 Firefox/57.0" ;
	private $bad_instance_of = [
		'Q13406463' , # Wikimedia list article
		'Q4167836' , # Wikimedia category
		'Q4663903' , # Wikipedia portal
		'Q4167410' , # Wikimedia disambiguation page
		'Q11266439' , # Wikimedia template
	] ;
	private $subclass_list_cache = [] ;


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

	public function syncCatalogWithWikidata ( $catalog ) { # INCOMPLETE
		# Get from MnM: ext=>Mnm, ext=>WD
		$j = $this->loadCatalogMappingFromMnM ( $catalog ) ;
		$ext2mnm = [] ;
		$ext2mnm_wdq = [] ;
		foreach ( $j->results->bindings AS $b ) {
			$ext2mnm[$b->extid->value] = preg_replace('|^.*/|','',$b->q->value) ;
			if ( isset($b->wdq) ) $ext2mnm_wdq[$b->extid->value] = preg_replace('|^.*/|','',$b->wdq->value) ;
		}

		# Get from WD: ext=>WD
		$j = $this->loadCatalogMappingFromWD ( $catalog ) ;
		$ext2wd = [] ;
		foreach ( $j->results->bindings AS $b ) {
			$ext2wd[$b->extid->value] = preg_replace('|^.*/|','',$b->q->value) ;
		}

		$wd_prop = $this->getWikidataPropertyForCatalog ( $catalog ) ;
		$qs_mnm = [] ;
		foreach ( $ext2wd AS $ext_id => $wdq ) {
			if ( !isset($ext2mnm[$ext_id]) ) { # Not in MnM
				# TODO
				print "No MnM for {$ext_id}\n" ;
				continue ;
			}
			if ( !isset($ext2mnm_wdq[$ext_id]) ) { # Not in MnM => WD

				$qs = $ext2mnm[$ext_id] . "\t{$this->config->props->manual}\t\"{$wdq}\"" ;
				$qs .= "\t{$this->config->props->by_user}\t\"|Wikidata importer\"" ;
				$qs .= "\t{$this->config->props->matched_on}\t+2018-10-10T00:00:00Z/11" ; # TODO current date
				$qs_mnm[] = $qs ;
				continue ;
			}
			if ( $ext2mnm_wdq[$ext_id] != $wdq ) { # Mismatch
				# TODO
			}
		#	print "Already in MnM: {$ext_id} => {$wdq}\n" ;
		}

		print implode ( "\n" , $qs_mnm ) ;
	}

	public function searchWikidata ( $query , $max_results = 50 ) {
		$url = "https://www.wikidata.org/w/api.php?action=query&format=json&srnamespace=0&srlimit={$max_results}&list=search&srsearch=" . urlencode($query) ;
		$j = json_decode ( file_get_contents ( $url ) ) ;
		return $j->query->search ;
	}

	private function autoLimitWikibaseCache() {
		$max_items = 500 ;
		if ( $this->wil_local->countItems() > $max_items ) {
			$this->wil_local = new WikidataItemList ;
			$this->wil_local->wikidata_api_url = $this->config->mwapi ;
		}
		if ( $this->wil_wd->countItems() > $max_items ) {
			$this->wil_wd = new WikidataItemList ;
		}
	}

	public function removeAutoMatches ( $catalog ) {
		$query = "select distinct ?q { ?q wdt:{$this->config->props->catalog} wd:{$catalog} ; wdt:{$this->config->props->auto} [] }" ;
		$sparql_results = $this->getSPARQL ( $query ) ;
		foreach ( $sparql_results->results->bindings AS $b ) {
			$this->autoLimitWikibaseCache() ;
			$q = preg_replace ( '/^.+\//' , '' , $b->q->value ) ;
			$i = $this->wil_local->loadItem ( $q ) ;
			if ( !isset($i) ) continue ;

			$data = [ 'claims' => [] ] ;
			$claims = $i->getClaims($this->config->props->auto) ;
			foreach ( $claims AS $claim ) {
				$data['claims'][] = [ 'id'=>$claim->id , 'remove'=>'' ] ;
			}
			$result = $this->doEditEntity ( $q , $data , 'Cleaning up auto-matching' ) ;
		}
	}

	# Gets all P279* for $q from Wikidata
	public function getSubclassList ( $q ) {
		if ( isset($this->subclass_list_cache[$q]) ) return $this->subclass_list_cache[$q] ;
		$query = "SELECT DISTINCT ?q { ?q wdt:P279* wd:{$q} }" ;
		$list = [] ;
		$j = $this->getSPARQL ( $query , $this->wd_sparql_api ) ;
		foreach ( $j->results->bindings AS $b ) $list[] = preg_replace ( '|^.*/|' , '' , $b->q->value ) ;
		$this->subclass_list_cache[$q] = $list ;
		return $list ;
	}

	public function getWikidataSearchString ( $s ) {
		$s = preg_replace ( '/\(.*?\)/' , ' ' , $s ) ;
		$s = preg_replace ( '/\s+/' , ' ' , $s ) ;
		return trim($s) ;
	}

	public function addAutoMatches ( $catalog , $stringent_typing = true ) {
		$query = "SELECT DISTINCT ?q ?qLabel (group_concat(?type;SEPARATOR='|') AS ?types) {" ;
		$query .= " ?q wdt:{$this->config->props->catalog} wd:{$catalog}" ;
		$query .= " MINUS { ?q wdt:{$this->config->props->manual} [] }" ;
		$query .= " MINUS { ?q wdt:{$this->config->props->auto} [] }" ;
		$query .= " MINUS { ?q wdt:{$this->config->props->na} [] }" ;
		$query .= " OPTIONAL { ?q wdt:{$this->config->props->type_q} ?type } " ;
		$query .= $this->sparql_label_service ;
		$query .= "} GROUP BY ?q ?qLabel" ;
		
		$sparql_results = $this->getSPARQL ( $query ) ;
		foreach ( $sparql_results->results->bindings AS $b ) {
			$this->autoLimitWikibaseCache() ;
			$q = preg_replace ( '/^.+\//' , '' , $b->q->value ) ;
			$label = $b->qLabel->value ;
			$label = $this->getWikidataSearchString ( $label ) ;

			$search_results = $this->searchWikidata ( $label , $stringent_typing?50:10 ) ;
			if ( count($search_results) == 0 ) continue ;

			$to_load = [] ;
			foreach ( $search_results AS $result ) $to_load[] = $result->title ;
			$this->wil_wd->loadItems ( $to_load ) ;

			# For stringent typing:
			# If the entry has one or more types, get all the type subclasses from Wikidata
			$stringent_subclasses = [] ;
			if ( $stringent_typing and isset($b->types) ) {
				$types = explode ( '|' , $b->types->value ) ;
				foreach ( $types AS $type ) {
					$stringent_subclasses = array_merge ( $stringent_subclasses , $this->getSubclassList ( $type ) ) ;
				}
				$stringent_subclasses = array_unique ( $stringent_subclasses ) ;
			}

			# Check all search results
			$had_that_target_q = [] ;
			$data = [ 'claims' => [] ] ;
			foreach ( $search_results AS $result ) {
				$target_q = $result->title ;
				if ( isset($had_that_target_q[$target_q]) ) continue ;
				$had_that_target_q[$target_q] = true ;

				$i = $this->wil_wd->getItem ( $target_q ) ;
				if ( !isset($i) ) continue ; # Item didn't load from Wikidata

				$claim_instances = [] ;
				foreach ( $i->getClaims('P31') as $claim ) $claim_instances[] = $i->getTarget ( $claim ) ;

				# Check for stringent type:
				# Check if the search result is an instance of one of these subclass items
				# But only if we have entry types AND at least one P31 (if not, icnlude it anyway, apparently noone has worked on the item...)
				if ( $stringent_typing and isset($b->types) and count($claim_instances) > 0 ) {
					if ( count ( array_intersect ( $stringent_subclasses , $claim_instances ) ) == 0 ) continue ; # Skip this result if not a _good_ P31
				}

				# Check for bad instance_of
				if ( count ( array_intersect ( $this->bad_instance_of , $claim_instances ) ) > 0 ) continue ; # Skin this result if _bad_ P31

				$data['claims'][] = $this->getNewClaimString($this->config->props->auto,$target_q) ;
				if ( count($data['claims']) >= 10 ) break ; # Don't add more than 10 candidates
			}

			if ( count($data['claims']) == 0 ) continue ;
			$result = $this->doEditEntity ( $q , $data , 'Auto-matching' ) ;
		}
	}



	// METHODS FOR EDITING THE MIX'N'MATCH WIKI

	// Takes a URL and an array with POST parameters
	public function doPostRequest ( $url , $params = [] , $optional_headers = null ) {
#		if ( !isset($this->cookiejar) ) $this->cookiejar = tmpfile() ;
		$ch = curl_init();
		curl_setopt($ch, CURLOPT_COOKIESESSION, true );
		curl_setopt($ch, CURLOPT_COOKIEJAR, $this->cookiejar);
		curl_setopt($ch, CURLOPT_COOKIEFILE, $this->cookiejar);
		curl_setopt($ch, CURLOPT_URL, $url);
		curl_setopt($ch, CURLOPT_RETURNTRANSFER, 1);
		curl_setopt($ch, CURLOPT_POST, true);
		curl_setopt($ch, CURLOPT_POSTFIELDS, $params);
		curl_setopt($ch, CURLOPT_USERAGENT, $this->browser_agent);
		if ($optional_headers !== null) curl_setopt($ch, CURLOPT_HTTPHEADER, $optional_headers);
		$output = curl_exec($ch);
		$info = curl_getinfo($ch);

		curl_close($ch);
		return $output ;
	}

	public function getEditToken () {
		$result = json_decode ( file_get_contents ( "{$this->config->mwapi}?action=query&meta=tokens&type=csrf&format=json" ) ) ;
		return $result->query->tokens->csrftoken ;
	}

	public function doEditEntity ( $q , $data , $summary = '' ) {
		$params = [
			'action' => 'wbeditentity',
			'id' => $q ,
			'summary' => $summary ,
			'token' => $this->getEditToken() ,
			'data' => json_encode($data) ,
			'format' => 'json'
		] ;
		$result = json_decode ( $this->doPostRequest ( $this->config->mwapi , $params ) ) ;
		return $result ;
	}

	public function getNewClaimString ( $prop , $string ) {
		return $this->getNewClaimFromSnak ( $this->getNewSnakString ( $prop , $string ) ) ;
	}

	public function getNewClaimItem ( $prop , $target_q ) {
		return $this->getNewClaimFromSnak ( $this->getNewSnakItem ( $prop , $target_q ) ) ;
	}

	public function getNewClaimDate ( $prop , $time , $precision = 9 ) {
		return $this->getNewClaimFromSnak ( $this->getNewSnakDate ( $prop , $time , $precision ) ) ;
	}

	public function getNewClaimFromSnak ( $snak ) {
		return [ 'type'=>'statement' , 'rank'=>'normal' , 'mainsnak'=>$snak ] ;
	}

	public function getNewSnakFromDatavalue ( $prop , $dv ) {
		return [ 'snaktype'=>'value' , 'property'=>$prop , 'datavalue'=>$dv ] ;
	}

	public function getNewSnakDate ( $prop , $time , $precision = 9 ) {
		return $this->getNewSnakFromDatavalue ( $prop , [ 'type'=>'time' , 'value'=>['time'=>$time,'precision'=>$precision,'calendarmodel'=>'http://www.wikidata.org/entity/Q1985727'] ] ) ;
	}

	public function getNewSnakString ( $prop , $string ) {
		return $this->getNewSnakFromDatavalue ( $prop , [ 'type'=>'string' , 'value'=>$string ] ) ;
	}

	public function getNewSnakItem ( $prop , $target_q ) {
		return $this->getNewSnakFromDatavalue ( $prop , [ 'type'=>'wikibase-entityid' , 'value'=>['entity-type'=>'item','id'=>$target_q,'numeric-id'=>preg_replace('/\D/','',$target_q)] ] ) ;
	}

	
} ;

?>