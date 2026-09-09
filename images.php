<?php
// Only this route permits the small, same-origin image pagination enhancement.
define('SECURITYSEARCH_IMAGE_ENHANCEMENT', true);
include_once __DIR__ . '/lib/security_headers.php';
ob_start();
include 'data/config.php';
include 'lib/frontend.php';
require 'lib/image_results.php';
require 'lib/search_execution.php';
$frontend=new frontend();
$started=microtime(true);
$obsolete=isset($_GET['frame']);
$append=($_GET['append'] ?? null)==='1';
if ($obsolete) { $_GET=['s'=>'']; }
[$scraper,$filters]=$frontend->getscraperfilters('images');
$get=$frontend->parsegetfilters($_GET,$filters);
include 'lib/bot_protection.php';
new bot_protection($frontend,$get,$filters,'images',true);
header('Cache-Control: private, no-store');
if ($obsolete) {
    http_response_code(410);
    $frontend->drawerror('This page link has expired','<p>Start a new image search to continue scrolling.</p><a href="/images">Search images</a>',null,false);
}
try {
    [$raw,$notice]=$get['s']==='' && empty($get['npt']) ? [['image'=>[],'npt'=>null],''] : search_execution::run($frontend,$scraper,$get,$filters,'images',$append);
    $results=image_results::bounded($raw);
    if ($notice!=='') { ob_clean();$frontend->loadheader($get,$filters,'images',$notice); }
} catch (Exception $error) {
    $frontend->drawscrapererror($error->getMessage(),$get,'images',$started);
}
$next_url=!empty($results['npt']) ? '/'.$frontend->htmlnextpage($get,$results['npt'],'images') : null;
if ($append) {
    // Same bot/provider checks as HTML. Return data, never executable markup.
    ob_clean();
    header('Content-Type: application/json; charset=UTF-8');
    echo json_encode(['version'=>1,'provider'=>$get['scraper'],'items'=>image_results::items($frontend,$get,$results),'next'=>$next_url,'omitted'=>$results['omitted']], JSON_INVALID_UTF8_SUBSTITUTE | JSON_HEX_TAG | JSON_HEX_AMP);
    exit;
}
[$cards,$rendered]=image_results::render($frontend,$get,$results);
$escape=static fn($v)=>htmlspecialchars((string)$v,ENT_QUOTES | ENT_SUBSTITUTE,'UTF-8');
$next=$next_url===null ? '' : '<a class="nextpage img" href="'.$escape($next_url).'">Next page &gt;</a>';
$view=in_array($get['view'] ?? '',['grid','compact','gallery','feed','list','filmstrip'],true) ? $get['view'] : 'grid';
$quality=in_array($get['quality'] ?? '',['high','original'],true) ? $get['quality'] : 'preview';
$help=$view==='filmstrip' ? 'Scroll sideways to explore images.' : 'Choose a view and quality in the filters, then press Search.';
if (($results['omitted'] ?? 0)>0) { $help.=' Showing the first 24 images from this provider page ('.(int)$results['omitted'].' additional results omitted).'; }
if ($get['scraper']==='binternet') { $help.=' Pinterest format filtering checks file extensions on each returned page; Safe Search is controlled by Binternet.'; }
if ($get['scraper']==='brave') { $help.=' Brave format filtering checks result URLs and metadata on this page; this provider does not offer more image pages.'; }
$enhance=$rendered>0 && $next_url!==null && ($_COOKIE['image_infinite'] ?? 'yes')!=='no';
echo $frontend->load('images.html',[
    'timetaken'=>$started,'images'=>$cards,'nextpage'=>$next,
    'image_script'=>($rendered>0 && ($_COOKIE['image_motion'] ?? 'yes')!=='no' ? '<script defer src="/static/images-motion.js?v'.config::VERSION.'"></script>' : '').
        ($enhance ? '<script defer src="/static/images-infinite.js?v'.config::VERSION.'"></script>' : ''),
    'image_view_help'=>'<p class="image-view-note">'.$escape($help).'</p>',
    'image_classes'=>' data-provider="'.$escape($get['scraper']).'" class="images-view-'.$view.' images-quality-'.$quality.'"'
]);
