<?php

function catalogSpecificParser ( $config , $block , &$res ) {
	$res['label'] = ucwords ( strtolower ( $res['label'] ) ) ;
	$res['desc'] = ucwords ( strtolower ( $res['desc'] ) ) ;
	$indicators = [
		'monument' => 'Q4989906' ,
		'crematorium' => 'Q157570' ,
		'park' => 'Q22698' ,
		'gardens' => 'Q1107656' ,
		'church' => 'Q16970'
	] ;
	foreach ( $indicators AS $k => $v ) {
		if ( preg_match ( '/\b'.$k.'\b/i' , $res['label']) ) {
			$res['type'] = $v ;
			break ;
		}
	}
}

?>