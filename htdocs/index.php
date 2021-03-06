<?php

require_once( "../lib/arc2/ARC2.php" );
require_once( "../lib/Graphite/Graphite.php" );




date_default_timezone_set( "Europe/London" );

if(substr($_SERVER['HTTP_HOST'],0,4)=='www.'){
	header("HTTP/1.1 301 Moved Permanently"); 
	header("Location: http".( (isset($_SERVER['HTTPS']) && $_SERVER['HTTPS'] == 'on') ? 's' : '' )."://".substr($_SERVER['HTTP_HOST'],4)."/"); 
	exit();
}

try {
    $f3=require('lib/base.php');
} catch (Exception $e) {
    echo 'Caught exception: ',  $e->getMessage(), "\n";
}

#if ((float)strstr(PCRE_VERSION,' ',TRUE)<7.9)
#	trigger_error('Outdated PCRE library version');

if (function_exists('apache_get_modules') &&
	!in_array('mod_rewrite',apache_get_modules()))
	trigger_error('Apache rewrite_module is disabled');


require_once( "../etc/eq_config.php" );
require_once( "{$eq_config->pwd}/dataacukEquipment.php" );
$eq = new dataacukEquipment($eq_config);


header("X-Host: {$eq_config->name}"); 

$f3->eq = $eq;


$f3->set('SERVER_NAME', $eq_config->name);	
$f3->set('DEBUG',3);
$f3->set('AUTOLOAD',"app/");
$f3->set('UI','ui/');

$note = "";
if( file_exists( "ui/note.html" ) )
{
	$note = Template::instance()->render( "note.html" );
}
$f3->set( "note", $note );


$f3->route('GET /faq', 'faq->page' );
/*$f3->route('GET /faq',
	function() use($f3) {
                $f3=Base::instance();

		$f3->set('html_title', "Frequently Asked Questions" );
		$f3->set('content','faq.html');
		print Template::instance()->render( "page-template.html" );
	}
);

*/
$f3->route('GET /logos',
	function() use($f3) {
		$f3=Base::instance();
		$f3->set('html_title', "Our Logos" );
		$f3->set('content','logos.html');
		print Template::instance()->render( "page-template.html" );
	}
);

$f3->route('GET /troubleshooting',
	function() use($f3) {
                $f3=Base::instance();

		$f3->set('html_title', "Troubleshooting" );
		$f3->set('content','troubleshooting.html');
		print Template::instance()->render( "page-template.html" );
	}
);

$f3->route('GET /uniquip',
	function() use($f3) {
                $f3=Base::instance();

		$f3->set('html_title', "UNIQUIP Data Publishing Specification" );
		$f3->set('content','uniquip.html');
		print Template::instance()->render( "page-template.html" );
	}
);

$f3->route('GET /opd',
    function() use($f3) {
        $f3->reroute('http://opd.data.ac.uk/checker');
    }
);

$f3->route('GET /poster',
    function() use($f3) {
        $f3->reroute('/posters');
    }
);
$f3->route('GET /posters',
    function() use($f3) {
        $f3->reroute('/guides');
    }
);


$f3->route('GET /guides',
	function() use($f3) {
                $f3=Base::instance();

		$f3->set('html_title', "Guides and Posters" );
		$f3->set('content','poster.html');
		print Template::instance()->render( "page-template.html" );
	}
);

$f3->route('GET /info',
	function() use($f3) {
		
		$f3=Base::instance();
		print Template::instance()->render( "useful_info.html" );
	}
);


$f3->route('GET /api/search', 'api->search' );
$f3->route('GET /api/inst', 'api->inst' );



$f3->route('GET /', 'home->page' );
$f3->route('GET /status', 'status->page' );

$f3->route('GET /reports', 'reports->index');
$f3->route('GET /reports/crawlhistory', 'reports->crawlhistory');
$f3->route('GET /reports/search', 'reports->search');
$f3->route('GET /reports/contacts', 'reports->contacts');
$f3->route('GET /reports/joined', 'reports->joined');

$f3->route('GET /compliance/podium', 'compliance->podium' );
$f3->route('GET /compliance', 'compliance->page' );
$f3->route('GET /search/advanced', 'search->advanced' );
$f3->route('GET /search', 'search->fragment' );
$f3->route('GET /data/search', 'search->data' );
$f3->route('GET	/org/@type/@id/@dataset', 'org->page' );
$f3->route('GET	/org/@type/@id.logo', 'logo->getLogo' );
$f3->route('GET	/item/@id', 'item->page' );
$f3->route('GET /item/@id.fragment', 'item->fragment' );
$f3->route('GET /item/@id/image.jpg', 'logo->getItemImage' );


$f3->route('GET	/newsletters', 'newsletters->index' );
$f3->route('GET	/newsletters/@issue', 'newsletters->issue' );
$f3->route('GET	/newsletters/@issue/@article', 'newsletters->issue' );

$f3->route('GET /org/ukprn-@id',
    function() {
        $f3=Base::instance();
		$id = $f3->get('PARAMS.id');
		$type = (substr($id,0,1)=='X') ? 'other' : 'ukprn';
		$f3->reroute("/org/$type/$id");
    }
);

$f3->route('GET /data/org/ukprn-@id',
    function() {
        $f3=Base::instance();
		$id = $f3->get('PARAMS.id');
		$type = (substr($id,0,1)=='X') ? 'other' : 'ukprn';
		$f3->reroute("/org/$type/$id");
    }
);
$f3->route('GET	/demo/slideshow', function() {

    $f3=Base::instance();
	$status = json_decode( file_get_contents( 'data/status-v2.json' ), true );
	$f3->set('status', $status );
//$status
	print Template::instance()->render( "slideshow.html" );
	exit();
	}
);


$f3->route('GET	/guides/@guide', function() {

    $f3=Base::instance();
	$guide = $f3->get('PARAMS.guide');
	$guides = array(
		"how-to-contribute" => array("How to Contriubte","turnjs",16, "/resources/booklets/how-to-contribute/how-to-contribute.pdf"),
		"opd" => array("The OPD","turnjs",16, "/resources/booklets/opd/opd.pdf")
	);
	
	if(!isset($guides[$guide])){
		$f3->error(404);
	}
	$tg = $guides[$guide];
	$f3->set('html_title', $tg[0]);
	
	switch($tg[1]){
		case "turnjs":

			$f3->set('key',$guide);
			$f3->set('noofpages',$tg[2]);
			$f3->set('download',$tg[3]);
			$f3->set('content','guide-turnjs.html');
			break;
		case "issuu":
		$f3->set('issuu',$tg[2]);
		$f3->set('download',$tg[3]);
		$f3->set('content','guide-issuu.html');
		break;
	}
	print Template::instance()->render( "page-template.html" );
	exit();
	}
);

$f3->set('ONERROR',function() use($f3) {
 	$f3=Base::instance();

	$error = $f3->get('ERROR');
	$error_title = constant('Base::HTTP_'.$error['code']);
	
	
   	$f3->set('html_title', "{$error['code']} {$error_title}" );
	$f3->set('content','content.html');
	
	$c[] = "<h2>{$error_title}</h2>";
	
	switch($error['code']){
		case "404":
			$c[] = "<p>The requested URL {$_SERVER['REDIRECT_URL']} was not found on this server.</p>";
		break;
	}
	
	if($f3->get('DEBUG')>0){
		$c[] = "<hr/>";
		$c[] = "<p>{$error['text']}</p>";
		foreach ($error['trace'] as $frame) {
			$line='';
			if (isset($frame['file']) && 
				(empty($frame['class']) || $frame['class']!='Magic') &&
				(empty($frame['function']) || !preg_match('/^(?:(?:trigger|user)_error|__call|call_user_func)/',$frame['function']))
				) {
				
				$addr=$f3->fixslashes($frame['file']).':'.$frame['line'];
				if (isset($frame['class']))
					$line.=$frame['class'].$frame['type'];
				if (isset($frame['function'])) {
					$line.=$frame['function'];
					if (!preg_match('/{.+}/',$frame['function'])) {
						$line.='(';
						if (!empty($frame['args']))
							$line.=$f3->csv($frame['args']);
						$line.=')';
					}
				}
				$str=$addr.' '.$line;
				$c[] = '&bull; '.nl2br($f3->encode($str)).'<br />';
			}
		}
	}
	
	$f3->set('html_content',join("",$c));

	print Template::instance()->render( "page-template.html" );
	exit();
});


$f3->run();
exit;
