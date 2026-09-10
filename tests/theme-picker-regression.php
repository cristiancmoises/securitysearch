<?php
require 'data/config.php';require 'lib/frontend.php';
$n=0;function check($v,$label){global $n;$n++;if(!$v)throw new RuntimeException($label);}
$f=new frontend();$_COOKIE=[];$html=$f->load('home.html');
check(config::DEFAULT_THEME==='Black' && str_contains($html,'data-home-style="black"') && !str_contains($html,'/static/themes/Black.css'),'Default pure-black theme');
check(str_contains($html,'Choose appearance') && str_contains($html,'method="post" action="/"'),'Native form is present');
check(!str_contains($html,'<script') && !str_contains($html,'{%theme_picker%}'),'No script or leftover placeholder');
foreach (securitysearch_theme_catalog() as $name=>$preview) {
 check(securitysearch_theme_choice($name)===$name,'Available theme is selectable');
 $_COOKIE=['theme'=>$name];$selected=$f->load('home.html');
 check(str_contains($selected,'value="'.htmlspecialchars($name,ENT_QUOTES).'" checked'),'Saved selection is shown');
 if($preview!==null){check(is_file(rawurldecode(ltrim($preview,'/'))),'Preview exists');check(filesize(rawurldecode(ltrim($preview,'/')))<16384,'Small preview');}
}
$_COOKIE=['theme'=>'SecOps'];$secops=$f->load('home.html');
check(!str_contains(file_get_contents('static/themes/SecOps.css'),'/static/wallpapers/secops.webp'),'Palette stylesheet does not prefetch the alternate animation');
check(str_contains($secops,operator_themes::available('SecOps') ? 'SecOps-operator.css' : 'SecOps-motion.css'),'Select exactly the available motion asset family');
check(!(str_contains($secops,'SecOps-operator.css') && str_contains($secops,'SecOps-motion.css')),'Never request both public and private motion styles');
foreach (['../data/config','<script>',"Tron\0",['Black'],'unknown'] as $bad) check(securitysearch_theme_choice($bad)===null,'Reject unlisted input');
check(!preg_match('/url\(/',file_get_contents('static/themes/Black.css')),'Black has no wallpaper fetch');
foreach (['Dark','Wine','The Birthday Massacre'] as $name) {
 check(!isset(securitysearch_theme_catalog()[$name]),'Removed theme not offered');
 $_COOKIE=['theme'=>$name];check(securitysearch_selected_theme()==='Black','Legacy preference safely migrates');
}
$_COOKIE=['theme'=>'Custom'];$custom=$f->load('home.html');
check(str_contains($custom,'local-background.js'),'Custom explicitly enables optional local script');
check(strpos($custom,'id="background-file"')<strpos($custom,'class="appearance-form"'),'File control outside preference form');
echo "PASS: $n theme selection, whitelist, native form and preview-size assertions.\n";
