<?php

function catalogSpecificParser ( $config , $block , &$res ) {
	$res['label'] = ucwords ( strtolower ( $res['label'] ) ) ;
}

?>