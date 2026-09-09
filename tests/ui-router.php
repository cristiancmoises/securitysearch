<?php
// Offline browser fixture only. Never a production endpoint or live provider test.
if(PHP_SAPI !== 'cli-server'){
    http_response_code(404);
    exit;
}
chdir(dirname(__DIR__));
$path = parse_url($_SERVER['REQUEST_URI'], PHP_URL_PATH);
if($path === '/'){
    require 'index.php';
    return true;
}
if(preg_match('#\A/(?:static/|banner/|favicon\.)#', $path)){
    return false;
}
if(!in_array($path, ['/fixture-images','/fixture-videos'], true)){
    http_response_code(404);
    return true;
}
require 'data/config.php';
require 'lib/frontend.php';
$frontend = new frontend();
if($path === '/fixture-videos'){
    $_GET['scraper'] = 'brave';
    [$provider, $filters] = $frontend->getscraperfilters('videos');
    $get = $frontend->parsegetfilters(['s'=>'GNU Guix & privacy'], $filters);
    $frontend->loadheader($get, $filters, 'videos');
    echo $frontend->load('search.html', ['timetaken'=>null,'class'=>'','right-left'=>'','right-right'=>'','left'=>'<p>Offline video fixture: suggestion remains available independently of provider results.</p>']);
    return true;
}
$_GET['scraper'] = 'google';
[$provider, $filters] = $frontend->getscraperfilters('images');
$get = $frontend->parsegetfilters(['s'=>'Offline layout fixture', 'view'=>$_GET['view'] ?? 'grid'], $filters);
$view = $get['view'];
$frontend->loadheader($get, $filters, 'images');
$pictures = ['static/misc/arte.jpg', 'static/misc/valerie.jpg', 'static/misc/tbm.jpg', 'banner/securitysearch.webp'];
$cards = '';
for($index = 0; $index < 16; $index++){
    $file = $pictures[$index % count($pictures)];
    [$width, $height] = getimagesize($file);
    $label = 'Fixture '.($index + 1).' — a deliberately long descriptive image title that wraps without obscuring its source';
    $cards .= '<div class="image-wrapper"><div class="image"><a class="thumb" href="#fixture-'.($index + 1).'">'.
        '<img src="/'.$file.'" width="'.$width.'" height="'.$height.'" alt="'.htmlspecialchars($label).'" loading="'.($index < 4 ? 'eager' : 'lazy').'" decoding="async">'.
        '<span class="duration">'.$width.'x'.$height.'</span></a>'.
        '<a href="#source-'.($index + 1).'"><div class="title">source-'.($index + 1).'.example.invalid</div><div class="description">'.htmlspecialchars($label).'</div></a></div></div>';
}
echo $frontend->load('images.html', [
    'images'=>$cards,
    'image_classes'=>' class="images-view-'.htmlspecialchars($view).' images-quality-preview"',
    'image_view_help'=>'<p class="image-view-note">Offline, bundled-image fixture. No provider or remote image request.</p>',
    'image_script'=>'',
    'timetaken'=>microtime(true),
    'nextpage'=>'<a class="nextpage img" href="/fixture-images?view='.htmlspecialchars($view).'">Next page</a>',
]);
return true;
