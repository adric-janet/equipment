#!/usr/bin/php
<?php

# This script uses the list of learning providers to auto discover any opds
# This takes the list of files to download from a configuration file, which is
# generated from the data at hub.data.ac.uk

if(in_array('--dryrun',$argv)){
	$dryrun = true;
}else{
	$dryrun = false;	
}

require_once( "../etc/eq_config.php" );


require_once( "{$eq_config->pwd}/lib/arc2/ARC2.php" );
require_once( "{$eq_config->pwd}/lib/Graphite/Graphite.php" );

require_once( "{$eq_config->pwd}/lib/OPDLib/OrgProfileDocument.php" );



require_once( "{$eq_config->pwd}/equipment.php" );


$eq = new equipment($eq_config);
$eq->launch_db();
$eq->db->dryrun = $dryrun;

$opds = $eq->db->fetch_many('autoOPDs', array('opd_ena' => 1), array());

foreach($opds as $opd){
	
	$orgin = array();
	$orginraw = array();
	
	echo "Loading OPD: {$opd['opd_url']}\n";
	
	if($opd['opd_cache'] != 'text/turtle'){
		$topd = @new OrgProfileDocument( $opd['opd_url'] , 'url');
	}else{
		$topd = @new OrgProfileDocument( $opd['opd_cache'] , 'string');
	}
	
	$graph = $topd->graph;
	$orgin['org_uri'] = $primaryTopic = (string)$topd->org;
	

	$sameas = $graph->resource( $primaryTopic )->all( "http://www.w3.org/2002/07/owl#sameAs" );	
	$uris = array();
	foreach($sameas as $same){
		$uri = parse_url($same);
		$uri['uri'] = (string)$same;
		$uris[$uri['host']] = $uri;
	}
	

	if(isset($eq->config->id->overides[(string)$primaryTopic])){
		$id = explode("/",$eq->config->id->overides[(string)$primaryTopic]);
		$orgin['org_idscheme'] = $id[0];
		$orgin['org_id'] = $id[1];
	}elseif(isset($uris['id.learning-provider.data.ac.uk'])){
		$orgin['org_idscheme'] = 'ukprn';
		$path = explode("/",$uris['id.learning-provider.data.ac.uk']['path']);
		$orgin['org_id'] = $path[2];
	}else{
		$orgin['org_idscheme'] = 'other';
		$orgin['org_id'] = 'X?';
	}

	$orgin['org_name'] = $graph->resource( $primaryTopic )->getString('foaf:name');
	if(!strlen($orgin['org_name']))
		$orgin['org_name'] = $graph->resource( $primaryTopic )->getString('skos:prefLabel');
	
	$orgin['org_sort'] = $eq->misc_order_txt($orgin['org_name']);
	
	
	$orgin['org_url'] = $graph->resource( $primaryTopic )->getString('foaf:homepage');
	$orgin['org_logo'] = $graph->resource( $primaryTopic )->getString('foaf:logo');
	
	$linfo = $eq->misc_curl_getinfo($orgin['org_logo']);
	if($linfo['http_code']!=200){
		$orgin['org_logo'] = "";
	}


	

	$orginraw['org_lastseen'] = 'NOW()';
	
	
	$datas = array();
	foreach(array('equipment'=>'http://purl.org/openorg/theme/equipment','facilities'=>'http://purl.org/openorg/theme/facilities') as $gpsn =>$gpsk){
		if(isset($graph->t['op'][$gpsk]) && is_array($graph->t['op'][$gpsk])){
			foreach($graph->t['op'][$gpsk] as $gp){
				foreach($gp as $g){
					$durl = (string)$g;
					if(!isset($datas[$durl])) {
						$datas[$durl] = array(
							"conformsTo"=>$graph->resource( $g )->getString('dcterms:conformsTo'),
							"license"=>$graph->resource( $g )->getString('dcterms:license'),
							"corrections"=>$graph->resource( $g )->getString('oo:corrections'),
							"type"=>array($gpsn)
						);
					}else{
						$datas[$durl]['type'][] = $gpsn;
					}
				}
			}
		}
	}
	
	$loc = $eq->location_extract($orgin['org_uri'], $graph);
	$orgin['org_location'] = $loc['loc_uri'];
	$orgin['org_ena'] = 1;
	
	
	if(count($datas)==0){
		echo "\tNo datasets found\n";
		continue;
	}
	
	$res = $eq->db->fetch_one('orgs', array('org_uri' => $orgin['org_uri']), array(), "`org_uri`");
	if(isset($res['org_uri'])){
		$eq->db->update('orgs', $orgin, $orginraw, array('org_uri' => $orgin['org_uri']));
	}else{
		$orginraw['org_firstseen'] = 'NOW()';
		$eq->db->insert('orgs', $orgin, $orginraw);
	}
	

	echo "\tFound ".count($datas)." datasets\n";
	
	foreach($datas as $dk => $dv){
		$din = array();
		$dinraw = array();
		
		$din['data_uri'] = $dk;
		$din['data_org'] = $res['org_uri'];
		$din['data_conforms'] = $dv['conformsTo'];
		$din['data_license'] = $dv['license'];
		$din['data_corrections'] = $dv['corrections'];
		$din['data_type'] = join(",",$dv['type']);
		$din['data_ena'] = 1;
		$din['data_hash'] = md5($dk);
		$dinraw['data_lastseen'] = 'NOW()';
		
		
		$res = $eq->db->fetch_one('datasets', array('data_uri' => $dk), array(), "`data_uri`");
	
		if(isset($res['data_uri'])){
			$eq->db->update('datasets',  $din, $dinraw, array('data_uri' => $dk));
		}else{
			$dinraw['data_firstseen'] = 'NOW()';
			$eq->db->insert('datasets', $din, $dinraw);
		}
		
	}
	
	
	
	
//	$opdg = 
}

	