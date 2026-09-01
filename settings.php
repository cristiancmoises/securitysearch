<?php
include_once __DIR__ . "/lib/security_headers.php";

include "data/config.php";

/*
	Define settings
*/
$settings = [
	[
		"name" => "General",
		"settings" => [
			[
				"description" => "Allow NSFW content",
				"parameter" => "nsfw",
				"options" => [
					[
						"value" => "yes",
						"text" => "Yes"
					],
					[
						"value" => "maybe",
						"text" => "Maybe"
					],
					[
						"value" => "no",
						"text" => "No"
					]
				]
			],
			[
				"description" => "Theme",
				"parameter" => "theme",
				"options" => []
			],
			[
				"description" => "Prevent clicking background elements when image viewer is open",
				"parameter" => "bg_noclick",
				"options" => [
					[
						"value" => "no",
						"text" => "No"
					],
					[
						"value" => "yes",
						"text" => "Yes"
					]
				]
			]
		]
	],
	[
		"name" => "Scrapers to use",
		"settings" => [
			[
				"description" => "Autocomplete<br><i>Picking <span class=\"code-inline\">Auto</span> changes the source dynamically depending of the page's scraper<br><b>Warning:</b> If you edit this field, you will need to re-add the search engine so that the new autocomplete settings are applied!</i>",
				"parameter" => "scraper_ac",
				"options" => [
					[
						"value" => "disabled",
						"text" => "Disabled"
					],
					[
						"value" => "auto",
						"text" => "Auto"
					],
					[
						"value" => "brave",
						"text" => "Brave"
					],
					[
						"value" => "ddg",
						"text" => "DuckDuckGo"
					],
					[
						"value" => "yandex",
						"text" => "Yandex"
					],
					[
						"value" => "google",
						"text" => "Google"
					],
					[
						"value" => "startpage",
						"text" => "Startpage"
					],
					[
						"value" => "kagi",
						"text" => "Kagi"
					],
					[
						"value" => "qwant",
						"text" => "Qwant"
					],
					[
						"value" => "ghostery",
						"text" => "Ghostery"
					],
					[
						"value" => "yep",
						"text" => "Yep"
					],
					[
						"value" => "marginalia",
						"text" => "Marginalia"
					],
					[
						"value" => "yt",
						"text" => "YouTube"
					],
					[
						"value" => "sc",
						"text" => "SoundCloud"
					]
				]
			],
			[
				"description" => "Web<br><i><b>Google</b> is the instance default. Choose <b>Brave</b> for an independent index, or save a preference to make either choice persistent.</i>",
				"parameter" => "scraper_web",
				"options" => [
					[
						"value" => "google",
						"text" => "Google (default)"
					],
					[
						"value" => "brave",
						"text" => "Brave"
					],
					[
						"value" => "ddg",
						"text" => "DuckDuckGo"
					],
					[
						"value" => "yandex",
						"text" => "Yandex"
					],
					[
						"value" => "google_api",
						"text" => "Google API"
					],
					[
						"value" => "google_cse",
						"text" => "Google CSE"
					],
					[
						"value" => "yahoo_japan",
						"text" => "Yahoo! JAPAN",
					],
					[
						"value" => "startpage",
						"text" => "Startpage"
					],
					[
						"value" => "qwant",
						"text" => "Qwant"
					],
					[
						"value" => "ghostery",
						"text" => "Ghostery"
					],
					[
						"value" => "yep",
						"text" => "Yep"
					],
					[
						"value" => "mwmbl",
						"text" => "Mwmbl"
					],
					[
						"value" => "mojeek",
						"text" => "Mojeek"
					],
					[
						"value" => "baidu",
						"text" => "Baidu"
					],
					[
						"value" => "coccoc",
						"text" => "Cốc Cốc"
					],
					[
						"value" => "solofield",
						"text" => "Solofield"
					],
					[
						"value" => "marginalia",
						"text" => "Marginalia"
					],
					[
						"value" => "wiby",
						"text" => "wiby"
					]
				]
			],
			[
				"description" => "Images<br><i><b>Google</b> is the instance default. <b>Brave</b> is available as a fast alternative for every image search.</i>",
				"parameter" => "scraper_images",
				"options" => [
					[
						"value" => "google",
						"text" => "Google (default)"
					],
					[
						"value" => "brave",
						"text" => "Brave"
					],
					[
						"value" => "ddg",
						"text" => "DuckDuckGo"
					],
					[
						"value" => "yandex",
						"text" => "Yandex"
					],
					[
						"value" => "google_cse",
						"text" => "Google CSE"
					],
					[
						"value" => "yahoo_japan",
						"text" => "Yahoo! JAPAN",
					],
					[
						"value" => "startpage",
						"text" => "Startpage"
					],
					[
						"value" => "qwant",
						"text" => "Qwant"
					],
					[
						"value" => "google_api",
						"text" => "Google API"
					],
					[
						"value" => "baidu",
						"text" => "Baidu"
					],
					[
						"value" => "solofield",
						"text" => "Solofield"
					],
					[
						"value" => "pinterest",
						"text" => "Pinterest"
					],
					[
						"value" => "cara",
						"text" => "Cara"
					],
					[
						"value" => "flickr",
						"text" => "Flickr"
					],
					[
						"value" => "pexels",
						"text" => "Pexels"
					],
					[
						"value" => "pixabay",
						"text" => "Pixabay"
					],
					[
						"value" => "unsplash",
						"text" => "Unsplash"
					],
					[
						"value" => "fivehpx",
						"text" => "500px"
					],
					[
						"value" => "vsco",
						"text" => "VSCO"
					],
					[
						"value" => "imgur",
						"text" => "Imgur"
					],
					[
						"value" => "ftm",
						"text" => "FindThatMeme"
					]
				]
			],
			[
				"description" => "Videos",
				"parameter" => "scraper_videos",
				"options" => [
					[
						"value" => "yt",
						"text" => "YouTube"
					],
					[
						"value" => "vimeo",
						"text" => "Vimeo"
					],
					[
						"value" => "sepiasearch",
						"text" => "Sepia Search"
					],
					[
						"value" => "ddg",
						"text" => "DuckDuckGo"
					],
					[
						"value" => "brave",
						"text" => "Brave"
					],
					[
						"value" => "yandex",
						"text" => "Yandex"
					],
					[
						"value" => "google",
						"text" => "Google"
					],
					[
						"value" => "yahoo_japan",
						"text" => "Yahoo! JAPAN",
					],
					[
						"value" => "startpage",
						"text" => "Startpage"
					],
					[
						"value" => "qwant",
						"text" => "Qwant"
					],
					[
						"value" => "baidu",
						"text" => "Baidu"
					],
					[
						"value" => "coccoc",
						"text" => "Cốc Cốc"
					],
					[
						"value" => "solofield",
						"text" => "Solofield"
					]
				]
			],
			[
				"description" => "News",
				"parameter" => "scraper_news",
				"options" => [
					[
						"value" => "ddg",
						"text" => "DuckDuckGo"
					],
					[
						"value" => "brave",
						"text" => "Brave"
					],
					[
						"value" => "google",
						"text" => "Google"
					],
					[
						"value" => "yahoo_japan",
						"text" => "Yahoo! JAPAN",
					],
					[
						"value" => "startpage",
						"text" => "Startpage"
					],
					[
						"value" => "qwant",
						"text" => "Qwant"
					],
					[
						"value" => "mojeek",
						"text" => "Mojeek"
					],
					[
						"value" => "baidu",
						"text" => "Baidu"
					]
				]
			],
			[
				"description" => "Music",
				"parameter" => "scraper_music",
				"options" => [
					[
						"value" => "sc",
						"text" => "SoundCloud"
					],
					[
						"value" => "swisscows",
						"text" => "Swisscows"
					]
					/*[
						"value" => "spotify",
						"text" => "Spotify"
					]*/
				]
			]
		]
	]
];

/*
	Set theme collection
*/
$themes = glob("static/themes/*");

$settings[0]["settings"][1]["options"][] = [
	"value" => "SecOps",
	"text" => "SecOps"
];

foreach($themes as $theme){
	
	$theme = explode(".", basename($theme))[0];
	
	$settings[0]["settings"][1]["options"][] = [
		"value" => $theme,
		"text" => $theme
	];
}

/*
	Set cookies
*/
if($_POST){

	$loop = &$_POST;
}elseif(count($_GET) !== 0){
	
	// redirect user to front page
	$loop = &$_GET;
	header("Location: /");
	
}else{
	// refresh cookie dates
	$loop = &$_COOKIE;
}

foreach($loop as $key => $value){
	
	if($key == "theme"){
		
		if($value == config::DEFAULT_THEME){
			
			unset($_COOKIE[$key]);
			
			setcookie(
				"theme",
				"",
				[
					"expires" => -1, // removes cookie
					"samesite" => "Lax",
					"path" => "/"
				]
			);
			continue;
		}
	}else{
		
		foreach($settings as $title){
			
			foreach($title["settings"] as $list){
				
				if(
					$list["parameter"] == $key &&
					$list["options"][0]["value"] == $value
				){
					
					unset($_COOKIE[$key]);
					
					setcookie(
						$key,
						"",
						[
							"expires" => -1, // removes cookie
							"samesite" => "Lax",
							"path" => "/"
						]
					);
					
					continue 3;
				}
			}
		}
	}
	
	if(!is_string($value)){
		
		continue;
	}
	
	$key = trim($key);
	$value = trim($value);
	
	$_COOKIE[$key] = $value;
	
	setcookie(
		$key,
		$value,
		[
			"expires" => strtotime("+400 days"), // maximal cookie ttl in chrome
			"samesite" => "Lax",
			"path" => "/"
		]
	);
}

include "lib/frontend.php";
$frontend = new frontend();

echo
	$frontend->load(
		"header_nofilters.html",
		[
			"title" => "Settings",
			"class" => ""
		]
	);

/* ============================================================
   PAGE-SCOPED STYLES — namespaced so they don't leak.
   Covers both the settings form (.settings-page) and the
   bookmark card (.bookmark-card) on the right rail.
   ============================================================ */
$page_style = <<<CSS
<style>
/* SETTINGS FORM ============================================ */
.settings-page{
    max-width: 980px;
    margin: 0 auto;
    padding: clamp(.8rem, 2vw, 1.6rem) clamp(.6rem, 2vw, 1.2rem) 5rem;
    font-size: .92rem;
}
.settings-page .sp-head{
    display: flex;
    align-items: center;
    justify-content: space-between;
    gap: 1rem;
    margin-bottom: 1.2rem;
    padding-bottom: .9rem;
    border-bottom: 1px solid rgba(255,255,255,0.06);
    flex-wrap: wrap;
}
.settings-page .sp-title{
    font-size: clamp(1.15rem, 1rem + .5vw, 1.4rem);
    font-weight: 600;
    margin: 0;
    letter-spacing: -.01em;
    display: inline-flex;
    align-items: center;
    gap: .55rem;
}
.settings-page .sp-title::before{
    content: "";
    width: 8px; height: 8px; border-radius: 50%;
    background: var(--bdae93, currentColor);
    opacity: .8;
}
.settings-page .sp-back{
    font-size: .82rem;
    padding: .4rem .8rem;
    border: 1px solid rgba(255,255,255,0.1);
    border-radius: 4px;
    color: inherit;
    opacity: .85;
    transition: opacity .15s, border-color .15s;
}
.settings-page .sp-back:hover{ opacity: 1; border-color: rgba(255,255,255,0.25); }

.settings-page .sp-blurb{
    background: rgba(255,255,255,0.025);
    border: 1px solid rgba(255,255,255,0.06);
    border-left: 2px solid var(--8ec07c, currentColor);
    border-radius: 4px;
    padding: .75rem .95rem;
    font-size: .85rem;
    line-height: 1.6;
    color: inherit;
    opacity: .85;
    margin-bottom: 1rem;
}
.settings-page .sp-blurb .code-inline{
    display: inline-block;
    padding: .05rem .35rem;
    margin: 0 .1rem;
    background: rgba(255,255,255,0.06);
    border-radius: 3px;
    font-family: ui-monospace, "JetBrains Mono", Menlo, monospace;
    font-size: .78rem;
}

.settings-page .sp-cookies{ margin-bottom: 1.4rem; }
.settings-page .sp-cookies-label{
    font-size: .7rem;
    text-transform: uppercase;
    letter-spacing: .18em;
    opacity: .55;
    margin-bottom: .4rem;
}
.settings-page .sp-cookies .code{ margin: 0; word-break: break-all; font-size: .78rem; }
.settings-page .sp-cookies-empty{ font-size: .82rem; opacity: .55; font-style: italic; }

.settings-page .sp-group{
    margin-bottom: 1rem;
    background: rgba(255,255,255,0.025);
    border: 1px solid rgba(255,255,255,0.07);
    border-top-color: rgba(255,255,255,0.12);
    border-radius: 8px;
    overflow: hidden;
}
@supports (backdrop-filter: blur(1px)){
    .settings-page .sp-group{
        background: rgba(14,14,18,0.4);
        backdrop-filter: blur(14px) saturate(140%);
        -webkit-backdrop-filter: blur(14px) saturate(140%);
    }
}
.settings-page .sp-group-head{
    padding: .65rem .9rem;
    font-size: .68rem;
    font-weight: 600;
    text-transform: uppercase;
    letter-spacing: .22em;
    color: inherit;
    opacity: .65;
    border-bottom: 1px solid rgba(255,255,255,0.05);
    background: rgba(0,0,0,0.15);
}

.settings-page .sp-row{
    display: grid;
    grid-template-columns: 1fr minmax(180px, 280px);
    gap: 1rem;
    align-items: center;
    padding: .75rem .9rem;
    border-bottom: 1px solid rgba(255,255,255,0.04);
}
.settings-page .sp-row:last-child{ border-bottom: none; }
.settings-page .sp-row:hover{ background: rgba(255,255,255,0.02); }
.settings-page .sp-row-label{ font-size: .88rem; line-height: 1.45; color: inherit; }
.settings-page .sp-row-label i{
    display: block; margin-top: .3rem; font-style: normal;
    font-size: .76rem; opacity: .6; line-height: 1.5;
}
.settings-page .sp-row-label .code-inline{
    display: inline-block;
    padding: .02rem .3rem; margin: 0 .1rem;
    background: rgba(255,255,255,0.06);
    border-radius: 3px;
    font-family: ui-monospace, "JetBrains Mono", Menlo, monospace;
    font-size: .74rem;
}
.settings-page .sp-row-label b{ font-weight: 600; opacity: .9; }

.settings-page .sp-select{ position: relative; display: block; width: 100%; }
.settings-page .sp-select select{
    appearance: none;
    -webkit-appearance: none;
    -moz-appearance: none;
    width: 100%;
    padding: .55rem 2.2rem .55rem .85rem;
    font: inherit;
    font-size: .85rem;
    color: inherit;
    background: rgba(0,0,0,0.35);
    border: 1px solid rgba(255,255,255,0.1);
    border-radius: 4px;
    cursor: pointer;
    transition: border-color .15s, background .15s;
}
.settings-page .sp-select select:hover{
    border-color: rgba(255,255,255,0.2);
    background: rgba(0,0,0,0.5);
}
.settings-page .sp-select select:focus{ outline: 0; border-color: var(--8ec07c, currentColor); }
.settings-page .sp-select::after{
    content: "";
    position: absolute; right: .85rem; top: 50%;
    width: 8px; height: 8px;
    border-right: 1.5px solid currentColor;
    border-bottom: 1.5px solid currentColor;
    transform: translateY(-70%) rotate(45deg);
    pointer-events: none;
    opacity: .55;
}

.settings-page .sp-actions{
    position: sticky;
    bottom: .8rem;
    margin-top: 1.5rem;
    padding: .7rem .9rem;
    display: flex;
    justify-content: space-between;
    align-items: center;
    gap: 1rem;
    background: rgba(10,10,14,0.7);
    border: 1px solid rgba(255,255,255,0.08);
    border-top-color: rgba(255,255,255,0.16);
    border-radius: 999px;
    box-shadow: 0 6px 30px rgba(0,0,0,0.25);
    flex-wrap: wrap;
}
@supports (backdrop-filter: blur(1px)){
    .settings-page .sp-actions{
        backdrop-filter: blur(18px) saturate(160%);
        -webkit-backdrop-filter: blur(18px) saturate(160%);
    }
}
.settings-page .sp-actions input[type=submit]{
    appearance: none; -webkit-appearance: none;
    padding: .55rem 1.2rem;
    font: inherit; font-size: .8rem; font-weight: 600;
    letter-spacing: .12em; text-transform: uppercase;
    color: #0a0a0c;
    background: var(--8ec07c, #5ce28a);
    border: 0; border-radius: 999px;
    cursor: pointer;
    transition: filter .15s, transform .15s;
}
.settings-page .sp-actions input[type=submit]:hover{ filter: brightness(1.1); transform: translateY(-1px); }
.settings-page .sp-actions input[type=submit]:active{ transform: translateY(0); }
.settings-page .sp-actions .sp-cancel{ font-size: .8rem; opacity: .7; transition: opacity .15s; }
.settings-page .sp-actions .sp-cancel:hover{ opacity: 1; }

@media (max-width: 640px){
    .settings-page .sp-row{ grid-template-columns: 1fr; gap: .5rem; }
    .settings-page .sp-actions{ border-radius: 8px; }
}

/* BOOKMARK CARD ============================================ */
.bookmark-card{
    background: rgba(255,255,255,0.025);
    border: 1px solid rgba(255,255,255,0.07);
    border-top-color: rgba(255,255,255,0.14);
    border-radius: 8px;
    padding: 1rem;
    position: relative;
    overflow: hidden;
}
@supports (backdrop-filter: blur(1px)){
    .bookmark-card{
        background: rgba(14,14,18,0.4);
        backdrop-filter: blur(14px) saturate(140%);
        -webkit-backdrop-filter: blur(14px) saturate(140%);
    }
}
.bookmark-card::before{
    /* subtle accent stripe at top */
    content: "";
    position: absolute;
    inset: 0 0 auto 0;
    height: 2px;
    background: linear-gradient(90deg, var(--8ec07c, #5ce28a), var(--bdae93, #00eaff));
    opacity: .65;
}

.bookmark-card .bm-title{
    display: inline-flex;
    align-items: center;
    gap: .5rem;
    margin: 0 0 .55rem;
    font-size: .92rem;
    font-weight: 600;
    color: inherit;
    letter-spacing: -.005em;
}
.bookmark-card .bm-title svg{
    width: 14px; height: 14px;
    color: var(--8ec07c, #5ce28a);
    flex-shrink: 0;
}

.bookmark-card .bm-desc{
    margin: 0 0 .9rem;
    font-size: .82rem;
    line-height: 1.55;
    opacity: .75;
}

/* URL preview block */
.bookmark-card .bm-url-wrap{
    margin-bottom: .9rem;
}
.bookmark-card .bm-url-label{
    font-size: .58rem;
    text-transform: uppercase;
    letter-spacing: .22em;
    opacity: .5;
    margin-bottom: .3rem;
}
.bookmark-card .bm-url{
    display: block;
    padding: .55rem .7rem;
    background: rgba(0,0,0,0.35);
    border: 1px solid rgba(255,255,255,0.06);
    border-radius: 4px;
    font-family: ui-monospace, "JetBrains Mono", Menlo, Consolas, monospace;
    font-size: .72rem;
    line-height: 1.5;
    word-break: break-all;
    color: var(--bdae93, currentColor);
    opacity: .85;
    max-height: 5.4em;
    overflow-y: auto;
}
.bookmark-card .bm-url::-webkit-scrollbar{ width: 4px; }
.bookmark-card .bm-url::-webkit-scrollbar-thumb{ background: rgba(255,255,255,0.15); border-radius: 2px; }

/* Action button — designed to be draggable to the bookmarks bar */
.bookmark-card .bm-btn{
    display: flex;
    align-items: center;
    justify-content: center;
    gap: .5rem;
    width: 100%;
    padding: .7rem .9rem;
    font-size: .8rem;
    font-weight: 600;
    letter-spacing: .1em;
    text-transform: uppercase;
    color: #0a0a0c !important;
    background: var(--8ec07c, #5ce28a);
    border: 0;
    border-radius: 4px;
    cursor: grab;
    text-decoration: none !important;
    transition: filter .15s, transform .15s;
    -webkit-user-drag: element;
    -khtml-user-drag: element;
    -moz-user-drag: element;
    -o-user-drag: element;
    user-drag: element;
}
.bookmark-card .bm-btn:hover{ filter: brightness(1.1); transform: translateY(-1px); }
.bookmark-card .bm-btn:active{ cursor: grabbing; transform: translateY(0); }
.bookmark-card .bm-btn svg{
    width: 14px; height: 14px;
    fill: currentColor;
    flex-shrink: 0;
}

.bookmark-card .bm-tip{
    margin: .8rem 0 0;
    padding: .55rem .7rem;
    background: rgba(255,255,255,0.025);
    border-radius: 4px;
    font-size: .72rem;
    line-height: 1.55;
    color: inherit;
    opacity: .7;
    display: flex;
    align-items: flex-start;
    gap: .55rem;
}
.bookmark-card .bm-tip-icon{
    flex-shrink: 0;
    width: 14px; height: 14px;
    color: var(--8ec07c, currentColor);
    margin-top: 1px;
}

/* Empty state */
.bookmark-card.is-empty{
    text-align: center;
    padding: 1.3rem 1rem;
}
.bookmark-card.is-empty::before{
    background: linear-gradient(90deg, rgba(255,255,255,0.12), rgba(255,255,255,0.04));
}
.bookmark-card.is-empty .bm-empty-icon{
    width: 28px; height: 28px;
    margin: 0 auto .55rem;
    opacity: .35;
}
.bookmark-card.is-empty .bm-empty-text{
    font-size: .82rem;
    line-height: 1.55;
    opacity: .65;
    margin: 0;
}
</style>
CSS;

$left = $page_style . '<div class="settings-page">';

$left .=
	'<div class="sp-head">' .
		'<h1 class="sp-title">Settings</h1>' .
		'<a href="../" class="sp-back">← Back to search</a>' .
	'</div>';

$left .=
	'<div class="sp-blurb">' .
		'Clicking <span class="code-inline">Update settings</span> stores a plaintext ' .
		'<span class="code-inline">key=value</span> cookie in your browser. ' .
		'Selecting a default value removes that parameter from your cookies.' .
	'</div>';

/* COOKIE INSPECTOR */
$c = count($_COOKIE);
$code = "";

$left .= '<div class="sp-cookies">';
if($c !== 0){
	
	$left .= '<div class="sp-cookies-label">Current cookie</div>';
	$left .= '<div class="code">';
	
	$ca = 0;
	foreach($_COOKIE as $key => $value){
		
		$code .= $key . "=" . $value;
		
		$ca++;
		if($ca !== $c){
			
			$code .= "; ";
		}
	}
	
	$left .= $frontend->highlightcode($code);
	$left .= '</div>';
}else{
	
	$left .= '<div class="sp-cookies-empty">No cookies are currently set.</div>';
}
$left .= '</div>';

/* FORM */
$left .= '<form method="post" autocomplete="off">';

foreach($settings as $title){
	
	$left .= '<section class="sp-group">';
	$left .= '<div class="sp-group-head">' . $title["name"] . '</div>';
	
	foreach($title["settings"] as $setting){
		
		$left .= '<div class="sp-row">';
		$left .=   '<div class="sp-row-label">' . $setting["description"] . '</div>';
		$left .=   '<label class="sp-select">';
		$left .=     '<select name="' . $setting["parameter"] . '">';
		
		if($setting["parameter"] == "theme"){
			
			if(!isset($_COOKIE["theme"])){
				
				$_COOKIE["theme"] = config::DEFAULT_THEME;
			}
		}
		
		foreach($setting["options"] as $option){
			
			$left .= '<option value="' . $option["value"] . '"';
			
			if(
				isset($_COOKIE[$setting["parameter"]]) &&
				$_COOKIE[$setting["parameter"]] == $option["value"]
			){
				$left .= ' selected';
			}
			
			$left .= '>' . $option["text"] . '</option>';
		}
		
		$left .=     '</select>';
		$left .=   '</label>';
		$left .= '</div>';
	}
	
	$left .= '</section>';
}

$left .=
	'<div class="sp-actions">' .
		'<a href="../" class="sp-cancel">Cancel</a>' .
		'<input type="submit" value="Update settings">' .
	'</div>';

$left .= '</form>';
$left .= '</div>'; // /.settings-page

if(count($_GET) === 0){
	
	$code = [];
	foreach($_COOKIE as $key => $value){
		
		$code[] = rawurlencode($key) . "=" . rawurlencode($value);
	}
	
	$code = implode("&", $code);
	
	if($code != ""){
		
		$code = "?" . $code;
	}
	
	/* ====================================================
	   BOOKMARK CARD — replaces the old infobox
	   ==================================================== */
	$bookmark_href = htmlspecialchars("settings" . $code, ENT_QUOTES);
	$bookmark_url_display = htmlspecialchars("/settings" . $code, ENT_QUOTES);
	
	if($code !== ""){
		// Cookies are set → show full bookmark card
		$right_left =
			'<aside class="bookmark-card">' .
				'<h2 class="bm-title">' .
					'<svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2.2" stroke-linecap="round" stroke-linejoin="round" aria-hidden="true">' .
						'<path d="M19 21l-7-5-7 5V5a2 2 0 0 1 2-2h10a2 2 0 0 1 2 2z"/>' .
					'</svg>' .
					'Preference Link' .
				'</h2>' .
				'<p class="bm-desc">' .
					'Save this link so your preferences return instantly — useful when private mode, ' .
					'auto-cleanup, or browser updates wipe your cookies.' .
				'</p>' .
				
				'<div class="bm-url-wrap">' .
					'<div class="bm-url-label">Link target</div>' .
					'<code class="bm-url">' . $bookmark_url_display . '</code>' .
				'</div>' .
				
				'<a href="' . $bookmark_href . '" class="bm-btn" ' .
				   'title="Drag to bookmarks bar, or right-click → Add to bookmarks">' .
					'<svg viewBox="0 0 24 24" fill="currentColor" aria-hidden="true">' .
						'<path d="M17 3H7a2 2 0 0 0-2 2v16l7-3 7 3V5a2 2 0 0 0-2-2z"/>' .
					'</svg>' .
					'<span>Bookmark this link</span>' .
				'</a>' .
				
				'<p class="bm-tip">' .
					'<svg class="bm-tip-icon" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round" aria-hidden="true">' .
						'<circle cx="12" cy="12" r="10"/>' .
						'<line x1="12" y1="16" x2="12" y2="12"/>' .
						'<line x1="12" y1="8" x2="12.01" y2="8"/>' .
					'</svg>' .
					'<span>Drag the button to your bookmarks bar, or right-click → <em>Add to bookmarks</em>. Following the link re-applies your cookies and sends you to the front page.</span>' .
				'</p>' .
			'</aside>';
	}else{
		// No cookies → show empty state with guidance
		$right_left =
			'<aside class="bookmark-card is-empty">' .
				'<svg class="bm-empty-icon" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round" aria-hidden="true">' .
					'<path d="M19 21l-7-5-7 5V5a2 2 0 0 1 2-2h10a2 2 0 0 1 2 2z"/>' .
				'</svg>' .
				'<p class="bm-empty-text">' .
					'<b>Preference Link unavailable.</b><br>' .
					'Pick at least one non-default setting above and save, then return here to grab a portable link to your config.' .
				'</p>' .
			'</aside>';
	}
	
	echo
		$frontend->load(
			"search.html",
			[
				"timetaken" => null,
				"class" => "",
				"right-left" => $right_left,
				"right-right" => "",
				"left" => $left
			]
		);
}
