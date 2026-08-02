<?php
include_once __DIR__ . "/lib/security_headers.php";
/*
	Initialize random shit
*/
include "data/config.php";

include "lib/frontend.php";
$frontend = new frontend();

[$scraper, $filters] = $frontend->getscraperfilters("web");

$get = $frontend->parsegetfilters($_GET, $filters);

/*
	Captcha
*/
include "lib/bot_protection.php";
new bot_protection($frontend, $get, $filters, "web", true);

$payload = [
	"timetaken" => microtime(true),
	"class" => "",
	"right-left" => "",
	"right-right" => "",
	"left" => ""
];

try{
	$results = $scraper->web($get);

}catch(Exception $error){

	$frontend->drawscrapererror($error->getMessage(), $get, "web", $payload["timetaken"]);
}

/* ============================================================
   PAGE-SCOPED STYLES
   Additive — does not override 4get's existing result layout
   (.result, .answer, .answer-wrapper sizing) or break the
   spoiler-checkbox mechanism in the sidebar.
   ============================================================ */
$page_style = <<<CSS
<style>
/* SPELLING / INFOBOX POLISH ------------------------------- */
.left .infobox{
    background: rgba(255,255,255,0.025);
    border: 1px solid rgba(255,255,255,0.07);
    border-top-color: rgba(255,255,255,0.13);
    border-left: 2px solid var(--8ec07c, currentColor);
    border-radius: 6px;
    padding: .75rem .95rem;
    margin-bottom: 1rem;
    line-height: 1.6;
}
@supports (backdrop-filter: blur(1px)){
    .left .infobox{
        background: rgba(14,14,18,0.4);
        backdrop-filter: blur(14px) saturate(140%);
        -webkit-backdrop-filter: blur(14px) saturate(140%);
    }
}
.left .infobox b{
    color: var(--bdae93, currentColor);
    font-weight: 600;
}
.left .infobox a{
    border-bottom: 1px solid rgba(255,255,255,0.2);
    transition: color .15s, border-color .15s;
}
.left .infobox a:hover{
    color: var(--8ec07c, #5ce28a);
    border-bottom-color: var(--8ec07c, #5ce28a);
}

/* EMPTY STATE --------------------------------------------- */
.web-empty{
    max-width: 560px;
    padding: 1.5rem 1.5rem 1.4rem;
    margin: 1.5rem 0;
    background: rgba(255,255,255,0.025);
    border: 1px solid rgba(255,255,255,0.08);
    border-top-color: rgba(255,255,255,0.14);
    border-radius: 8px;
    position: relative;
    overflow: hidden;
}
@supports (backdrop-filter: blur(1px)){
    .web-empty{
        background: rgba(14,14,18,0.4);
        backdrop-filter: blur(14px) saturate(140%);
        -webkit-backdrop-filter: blur(14px) saturate(140%);
    }
}
.web-empty::before{
    content: "";
    position: absolute;
    inset: 0 0 auto 0;
    height: 2px;
    background: linear-gradient(90deg, var(--8ec07c, #5ce28a), var(--bdae93, #00eaff));
    opacity: .6;
}
.web-empty .we-head{
    display: flex;
    align-items: center;
    gap: .55rem;
    margin-bottom: .55rem;
}
.web-empty .we-icon{
    width: 18px; height: 18px;
    color: var(--8ec07c, currentColor);
    flex-shrink: 0;
    opacity: .85;
}
.web-empty h1{
    font-size: 1rem;
    font-weight: 600;
    margin: 0;
    letter-spacing: -.01em;
}
.web-empty p{
    margin: 0 0 .9rem;
    font-size: .86rem;
    line-height: 1.55;
    opacity: .78;
}
.web-empty p b{ color: var(--bdae93, currentColor); font-weight: 600; opacity: 1; }
.web-empty .we-tips{
    text-align: left;
    list-style: none;
    padding: .7rem .85rem;
    margin: 0;
    background: rgba(0,0,0,0.25);
    border: 1px solid rgba(255,255,255,0.04);
    border-radius: 4px;
    font-size: .82rem;
    line-height: 1.7;
}
.web-empty .we-tips li{ padding-left: 1.1rem; position: relative; }
.web-empty .we-tips li::before{
    content: "›";
    position: absolute; left: 0;
    color: var(--8ec07c, #5ce28a);
    font-weight: 600;
}
.web-empty .we-tips a{
    color: inherit;
    border-bottom: 1px solid rgba(255,255,255,0.2);
    transition: color .15s, border-color .15s;
}
.web-empty .we-tips a:hover{
    color: var(--8ec07c, #5ce28a);
    border-bottom-color: var(--8ec07c, #5ce28a);
}

/* RESULT HOVER POLISH ------------------------------------- */
/* Subtle, additive — doesn't touch positioning */
.left .result{
    padding: .6rem .55rem;
    margin: 0 -.55rem;
    border-radius: 6px;
    transition: background .15s ease;
}
.left .result:hover{
    background: rgba(255,255,255,0.02);
}
.left .result .title a{
    transition: color .15s;
}
.left .result:hover .title a{
    color: var(--bdae93, currentColor);
}

/* RELATED SEARCHES ---------------------------------------- */
.left h3{
    font-size: .68rem;
    font-weight: 600;
    text-transform: uppercase;
    letter-spacing: .22em;
    opacity: .55;
    margin: 1.8rem 0 .8rem;
    padding-bottom: .4rem;
    border-bottom: 1px solid rgba(255,255,255,0.06);
}
.left table.related{
    border-collapse: separate;
    border-spacing: .4rem;
    margin: 0 -.4rem;
    width: calc(100% + .8rem);
}
.left table.related td{
    padding: 0;
    width: 50%;
}
.left table.related td:empty{ padding: 0; }
.left table.related a{
    display: block;
    padding: .5rem .75rem;
    background: rgba(255,255,255,0.025);
    border: 1px solid rgba(255,255,255,0.07);
    border-radius: 4px;
    font-size: .85rem;
    transition: background .15s, border-color .15s, color .15s, transform .15s;
}
.left table.related a::before{
    content: "↗  ";
    opacity: .5;
    font-size: .8em;
}
.left table.related a:hover{
    color: var(--8ec07c, #5ce28a);
    background: rgba(92,226,138,.06);
    border-color: rgba(92,226,138,.3);
    transform: translateX(2px);
}

/* NEXT PAGE ----------------------------------------------- */
.left .nextpage{
    display: inline-flex !important;
    align-items: center;
    gap: .55rem;
    padding: .65rem 1.3rem;
    margin: 2rem 0 1rem;
    font-size: .82rem;
    font-weight: 600;
    letter-spacing: .12em;
    text-transform: uppercase;
    color: inherit;
    background: rgba(255,255,255,0.04);
    border: 1px solid rgba(255,255,255,0.12);
    border-top-color: rgba(255,255,255,0.2);
    border-radius: 999px;
    text-decoration: none !important;
    transition: background .15s, border-color .15s, transform .15s, color .15s;
}
@supports (backdrop-filter: blur(1px)){
    .left .nextpage{
        background: rgba(14,14,18,0.45);
        backdrop-filter: blur(14px) saturate(140%);
        -webkit-backdrop-filter: blur(14px) saturate(140%);
    }
}
.left .nextpage:hover{
    color: var(--8ec07c, #5ce28a);
    border-color: rgba(92,226,138,.4);
    background: rgba(92,226,138,.06);
    transform: translateY(-1px);
}

/* RIGHT SIDEBAR (Images / Videos / News / Answers) -------- */
.answer-wrapper{
    margin-bottom: 1rem;
    border-radius: 8px;
    overflow: hidden;
    background: rgba(255,255,255,0.025);
    border: 1px solid rgba(255,255,255,0.07);
    border-top-color: rgba(255,255,255,0.13);
    transition: border-color .15s;
}
@supports (backdrop-filter: blur(1px)){
    .answer-wrapper{
        background: rgba(14,14,18,0.4);
        backdrop-filter: blur(14px) saturate(140%);
        -webkit-backdrop-filter: blur(14px) saturate(140%);
    }
}
.answer-wrapper:hover{ border-color: rgba(255,255,255,0.14); }

.answer-wrapper .answer{
    padding: .8rem .9rem .55rem;
}
.answer-wrapper .answer-title{
    margin: 0 0 .65rem;
    padding-bottom: .5rem;
    border-bottom: 1px solid rgba(255,255,255,0.05);
}
.answer-wrapper .answer-title h1,
.answer-wrapper .answer-title h2{
    font-size: .72rem;
    font-weight: 600;
    text-transform: uppercase;
    letter-spacing: .2em;
    margin: 0;
    opacity: .65;
    transition: opacity .15s, color .15s;
}
.answer-wrapper .answer-title a{ color: inherit; }
.answer-wrapper .answer-title a:hover h1,
.answer-wrapper .answer-title a:hover h2{
    opacity: 1;
    color: var(--8ec07c, currentColor);
}
.answer-wrapper .answer-title a:hover h1::after,
.answer-wrapper .answer-title a:hover h2::after{
    content: " →";
    font-size: .8em;
    margin-left: .25em;
}

/* Image strip inside sidebar */
.answer-wrapper .images{
    display: grid;
    grid-template-columns: repeat(auto-fill, minmax(72px, 1fr));
    gap: 3px;
}
.answer-wrapper .images a.image{
    position: relative;
    aspect-ratio: 1 / 1;
    overflow: hidden;
    border-radius: 4px;
    transition: transform .15s;
}
.answer-wrapper .images a.image img{
    width: 100%; height: 100%;
    object-fit: cover;
    transition: filter .2s, transform .2s;
}
.answer-wrapper .images a.image:hover{ transform: scale(1.05); z-index: 2; }
.answer-wrapper .images a.image:hover img{ filter: brightness(1.1); }
.answer-wrapper .images .duration{
    position: absolute;
    bottom: 3px; right: 3px;
    padding: 1px 4px;
    background: rgba(0,0,0,.65) !important;
    border: 1px solid rgba(255,255,255,0.06);
    font-size: .58rem;
    border-radius: 3px;
    font-variant-numeric: tabular-nums;
}

/* Wiki-style answer card */
.answer-wrapper .wiki-head{ padding: 0; }
.answer-wrapper .wiki-head .photo{
    float: right;
    margin: 0 0 .6rem .8rem;
    border-radius: 4px;
    overflow: hidden;
    max-width: 140px;
}
.answer-wrapper .wiki-head .photo img{
    display: block;
    width: 100%;
    height: auto;
}
.answer-wrapper .description{
    font-size: .86rem;
    line-height: 1.55;
}
.answer-wrapper .description h2{
    font-size: .9rem;
    font-weight: 600;
    margin: 1rem 0 .4rem;
    opacity: .85;
}
.answer-wrapper .description .quote{
    border-left: 2px solid var(--8ec07c, currentColor);
    padding: .35rem .7rem;
    margin: .55rem 0;
    background: rgba(255,255,255,0.025);
    font-style: italic;
    opacity: .85;
    border-radius: 0 4px 4px 0;
}
.answer-wrapper .description .code,
.answer-wrapper .description .code-inline{
    background: rgba(0,0,0,0.35);
    border: 1px solid rgba(255,255,255,0.06);
    border-radius: 4px;
    font-family: ui-monospace, "JetBrains Mono", Menlo, Consolas, monospace;
    font-size: .78rem;
}
.answer-wrapper .description .code{
    padding: .55rem .7rem;
    margin: .55rem 0;
    overflow-x: auto;
}
.answer-wrapper .description .code-inline{
    display: inline-block;
    padding: .03rem .35rem;
}
.answer-wrapper table{
    width: 100%;
    margin-top: .8rem;
    font-size: .8rem;
    border-collapse: collapse;
}
.answer-wrapper table tr td{
    padding: .4rem .55rem;
    border-bottom: 1px solid rgba(255,255,255,0.05);
}
.answer-wrapper table tr td:first-child{
    color: inherit;
    opacity: .65;
    width: 40%;
    font-size: .76rem;
    letter-spacing: .02em;
}
.answer-wrapper table tr:last-child td{ border-bottom: none; }

.answer-wrapper .socials{
    display: flex;
    flex-wrap: wrap;
    gap: .35rem;
    margin-top: .8rem;
    padding-top: .65rem;
    border-top: 1px solid rgba(255,255,255,0.05);
}
.answer-wrapper .socials a{
    display: inline-flex;
    align-items: center;
    gap: .4rem;
    padding: .35rem .65rem;
    background: rgba(255,255,255,0.04);
    border: 1px solid rgba(255,255,255,0.08);
    border-radius: 999px;
    font-size: .72rem;
    transition: border-color .15s, background .15s;
}
.answer-wrapper .socials a:hover{
    border-color: rgba(255,255,255,0.2);
    background: rgba(255,255,255,0.08);
}
.answer-wrapper .socials .center{
    display: inline-flex;
    align-items: center;
    gap: .4rem;
}
.answer-wrapper .socials img{
    width: 14px; height: 14px;
    border-radius: 2px;
}

/* Spoiler button */
.spoiler-button{
    display: block;
    text-align: center;
    padding: .35rem;
    background: rgba(0,0,0,0.2);
    border-top: 1px solid rgba(255,255,255,0.04);
    font-size: .65rem;
    text-transform: uppercase;
    letter-spacing: .2em;
    opacity: .5;
    cursor: pointer;
    transition: opacity .15s, background .15s;
}
.spoiler-button:hover{
    opacity: .9;
    background: rgba(255,255,255,0.04);
}

/* Reduced motion --------------------------------------- */
@media (prefers-reduced-motion: reduce){
    .left .result,
    .left .nextpage,
    .left table.related a,
    .answer-wrapper,
    .answer-wrapper .images a.image,
    .answer-wrapper .images a.image img{
        transition: none;
    }
    .left .nextpage:hover,
    .answer-wrapper .images a.image:hover,
    .left table.related a:hover{
        transform: none;
    }
}
</style>
CSS;

/*
	Prepend Oracle output, if applicable
*/
include("oracles/encoder.php");
include("oracles/calc.php");
include("oracles/time.php");
include("oracles/numerics.php");
$oracles = [new calculator(), new encoder(), new time(), new numerics()];
$fortune = "";
foreach ($oracles as $oracle) {
	if ($oracle->check_query($_GET["s"])) {
		$resp = $oracle->generate_response($_GET["s"]);
		if ($resp != "") {
			$fortune .= "<div class=\"infobox\">";
			foreach ($resp as $title => $r) {
				if ($title) {
					$fortune .= "<h3>".htmlspecialchars($title)."</h3><div class=\"code\">".htmlspecialchars($r)."</div>";
				}
				else {
					$fortune .= "<i>".$r."</i><br>";
				}
			}
			$fortune .= "<small>Answer provided by oracle: ".$oracle->info["name"]."</small></div>";
		}
		break;
	}
}
$payload["left"] = $page_style . $fortune;

$answerlen = 0;

/*
	Spelling checker
*/
if($results["spelling"]["type"] != "no_correction"){

	switch($results["spelling"]["type"]){

		case "including":
			$type = "Including results for";
			break;

		case "not_many":
			$type = "Not many results contain";
			break;
	}

	$payload["left"] .=
		'<div class="infobox">' .
			$type . ' <b>' . htmlspecialchars($results["spelling"]["using"]) . '</b>.<br>' .
			'Did you mean <a href="?s=' .
			urlencode($results["spelling"]["correction"]) .
			'&' .
			$frontend->buildquery($get, true) .
			'&spellcheck=no">' .
			htmlspecialchars($results["spelling"]["correction"]) .
			'</a>?' .
		'</div>';
}

/*
	Populate links
*/
if(count($results["web"]) === 0){

	$query = htmlspecialchars($_GET["s"] ?? "");

	$payload["left"] .=
		'<div class="web-empty">' .
			'<div class="we-head">' .
				'<svg class="we-icon" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round" aria-hidden="true">' .
					'<circle cx="11" cy="11" r="8"/>' .
					'<path d="m21 21-4.3-4.3"/>' .
					'<line x1="8" y1="11" x2="14" y2="11"/>' .
				'</svg>' .
				'<h1>No results found</h1>' .
			'</div>' .
			'<p>The scraper came back empty for <b>' . $query . '</b>.</p>' .
			'<ul class="we-tips">' .
				'<li>Try a different web scraper in <a href="/settings">Settings</a></li>' .
				'<li>Use fewer or simpler keywords</li>' .
				'<li>Check filters &mdash; NSFW filter may be hiding results</li>' .
				'<li>Some scrapers rate-limit; wait briefly and retry</li>' .
			'</ul>' .
		'</div>';
}

foreach($results["web"] as $site){

	$n = null;

	if($site["date"] !== null){

		$date = date("jS M y @ g:ia", $site["date"]);
	}else{

		$date = null;
	}

	$payload["left"] .= $frontend->drawtextresult($site, $date, $n, $get["s"]);
}

$right = [];

/*
	Generate images
*/
if(count($results["image"]) !== 0){

	$answerlen++;
	$right["image"] =
		'<div class="answer-wrapper">' .
			'<input id="answer' . $answerlen . '" class="spoiler" type="checkbox">' .
			'<div class="answer">' .
				'<div class="answer-title">' .
					'<a class="answer-title" href="/images?s=' . urlencode($get["s"]) . '"><h2>Images</h2></a>' .
				'</div>' .
				'<div class="images">';

	foreach($results["image"] as $image){

		$right["image"] .=
			'<a class="image" href="' . htmlspecialchars($image["url"]) . '" rel="noreferrer nofollow" title="' . htmlspecialchars($image["title"]) . '" data-json="' . htmlspecialchars(json_encode($image["source"])) . '" tabindex="-1">' .
				'<img src="' . $frontend->htmlimage($image["source"][count($image["source"]) - 1]["url"], "square") . '" alt="thumb" loading="lazy" decoding="async">';

		if(
			$image["source"][0]["width"] !== null &&
			$image["source"][0]["height"] !== null
		){

			$right["image"] .= '<div class="duration">' . $image["source"][0]["width"] . 'x' . $image["source"][0]["height"] . '</div>';
		}

		$right["image"] .= '</a>';
	}

	$right["image"] .=
		'</div></div>' .
		'<label class="spoiler-button" for="answer' . $answerlen . '"></label></div>';
}

/*
	Generate videos
*/
if(count($results["video"]) !== 0){

	$answerlen++;
	$right["video"] =
		'<div class="answer-wrapper">' .
			'<input id="answer' . $answerlen . '" class="spoiler" type="checkbox">' .
			'<div class="answer">' .
				'<div class="answer-title">' .
					'<a class="answer-title" href="/videos?s=' . urlencode($get["s"]) . '"><h2>Videos</h2></a>' .
				'</div>';

	foreach($results["video"] as $video){

		if($video["views"] !== null){

			$greentext = number_format($video["views"]) . " views";
		}else{

			$greentext = null;
		}

		if($video["date"] !== null){

			if($greentext !== null){

				$greentext .= " • ";
			}

			$greentext .= date("jS M y @ g:ia", $video["date"]);
		}

		if($video["duration"] !== null){

			if($video["duration"] == "_LIVE"){

				$duration = 'LIVE';
			}else{

				$duration = $frontend->s_to_timestamp($video["duration"]);
			}
		}else{

			$duration = null;
		}

		$right["video"] .= $frontend->drawtextresult($video, $greentext, $duration, $get["s"], false);
	}

	$right["video"] .=
		'</div>' .
		'<label class="spoiler-button" for="answer' . $answerlen . '"></label></div>';
}

/*
	Generate news
*/
if(count($results["news"]) !== 0){

	$answerlen++;
	$right["news"] =
		'<div class="answer-wrapper">' .
			'<input id="answer' . $answerlen . '" class="spoiler" type="checkbox">' .
			'<div class="answer">' .
				'<div class="answer-title">' .
					'<a class="answer-title" href="/news?s=' . urlencode($get["s"]) . '"><h2>News</h2></a>' .
				'</div>';

	foreach($results["news"] as $news){

		if($news["date"] !== null){

			$greentext = date("jS M y @ g:ia", $news["date"]);
		}else{

			$greentext = null;
		}

		$right["news"] .= $frontend->drawtextresult($news, $greentext, null, $get["s"], false);
	}

	$right["news"] .=
		'</div>' .
		'<label class="spoiler-button" for="answer' . $answerlen . '"></label></div>';
}

/*
	Generate answers
*/
if(count($results["answer"]) !== 0){

	$right["answer"] = "";

	foreach($results["answer"] as $answer){

		$answerlen++;
		$right["answer"] .=
			'<div class="answer-wrapper">' .
				'<input id="answer' . $answerlen . '" class="spoiler" type="checkbox">' .
				'<div class="answer"><div class="wiki-head">';

		if(!empty($answer["title"])){

			$right["answer"] .=
			'<div class="answer-title">';

			if(!empty($answer["url"])){

				$right["answer"] .= '<a class="answer-title" href="' . htmlspecialchars($answer["url"]) . '" rel="noreferrer nofollow">';
			}

			$right["answer"] .= '<h1>' . htmlspecialchars($answer["title"]) . '</h1>';

			if(!empty($answer["url"])){

				$right["answer"] .= '</a>';
			}


			$right["answer"] .= '</div>';
		}

		if(!empty($answer["url"])){

			$right["answer"] .=
				$frontend->drawlink($answer["url"]);
		}

		$right["answer"] .= '<div class="description">';

		if(!empty($answer["thumb"])){

			$right["answer"] .=
				'<a href="' . htmlspecialchars($answer["thumb"]) . '" rel="noreferrer nofollow" class="photo">' .
					'<img src="' . $frontend->htmlimage($answer["thumb"], "cover") . '" alt="thumb" class="openimg" loading="lazy">' .
				'</a>';
		}

		foreach($answer["description"] as $description){

			switch($description["type"]){

				case "text":
					$right["answer"] .= $frontend->highlighttext($get["s"], $description["value"]);
					break;

				case "title":
					$right["answer"] .=
						'<h2>' .
							htmlspecialchars($description["value"]) .
						'</h2>';
					break;

				case "italic":
					$right["answer"] .=
						'<i>' .
							$frontend->highlighttext($get["s"], $description["value"]) .
						'</i>';
					break;

				case "quote":
					$right["answer"] .=
						'<div class="quote">' .
							$frontend->highlighttext($get["s"], $description["value"]) .
						'</div>';
					break;

				case "code":
					$right["answer"] .=
						'<div class="code" tabindex="-1">' .
							$frontend->highlightcode($description["value"], true) .
						'</div>';
					break;

				case "inline_code":
					$right["answer"] .=
						'<div class="code-inline">' .
							htmlspecialchars($description["value"]) .
						'</div>';
					break;

				case "link":
					$right["answer"] .=
						'<a href="' . htmlspecialchars($description["url"]) . '" rel="noreferrer nofollow" class="underline" tabindex="-1">' . htmlspecialchars($description["value"]) . '</a>';
					break;

				case "image":
					$right["answer"] .=
						'<a href="' . htmlspecialchars($description["url"]) . '" rel="noreferrer nofollow" tabindex="-1"><img src="' . $frontend->htmlimage($description["url"], "thumb") . '" alt="image" class="fullimg openimg" loading="lazy"></a>';
					break;

				case "audio":
					$right["answer"] .=
						'<audio src="/audio/linear?s=' . urlencode($description["url"]) . '" controls><a href="/audio/linear?s=' . urlencode($description["url"]) . '">Listen to the pronunciation audio</a></audio>';
					break;
			}
		}

		$right["answer"] .= '</div>';

		if(count($answer["table"]) !== 0){

			$right["answer"] .= '<table>';

			foreach($answer["table"] as $info => $value){

				$right["answer"] .=
					'<tr>' .
						'<td>' . $info . '</td>' .
						'<td>' . $value . '</td>' .
					'</tr>';
			}

			$right["answer"] .= '</table>';
		}

		if(count($answer["sublink"]) !== 0){

			$right["answer"] .= '<div class="socials">';
			$icons = glob("static/icon/*");

			foreach($answer["sublink"] as $website => $url){

				$flag = false;
				$icon = str_replace(" ", "", strtolower($website));

				foreach($icons as $path){

					if(pathinfo($path, PATHINFO_FILENAME) == $icon){

						$flag = true;
						break;
					}
				}

				if($flag === false){

					$icon = "website";
				}

				$right["answer"] .=
					'<a href="' . htmlspecialchars($url) . '" rel="noreferrer nofollow" tabindex="-1">' .
						'<div class="center">' .
							'<img src="/static/icon/' . $icon . '.png" alt="icon">' .
							'<div class="title">' . $website . '</div>' .
						'</div>' .
					'</a>';
			}

			$right["answer"] .= '</div>';
		}

		$right["answer"] .=
			'</div></div>' .
			'<label class="spoiler-button" for="answer' . $answerlen . '"></label></div>';
	}
}

/*
	Add right containers
*/
if(isset($right["answer"])){

	if(count($right) >= 2){

		$payload["right-right"] = $right["answer"];
		unset($right["answer"]);
	}
}

$c = 0;
foreach($right as $snippet){

	if($c % 2 === 0){

		$payload["right-left"] .= $snippet;
	}else{

		$payload["right-right"] .= $snippet;
	}

	$c++;
}

if($c !== 0){

	$payload["class"] = " has-answer";
}

/*
	Generate related searches
*/
$c = count($results["related"]);

if($c !== 0){
	$payload["left"] .= '<h3>Related searches</h3><table class="related">';

	$opentr = false;

	for($i=0; $i<$c; $i++){

		if(($i % 2) === 0){

			$opentr = true;
			$payload["left"] .= '<tr>';
		}else{

			$opentr = false;
		}

		$payload["left"] .=
			'<td>' .
				'<a href="/web?s=' .
					urlencode($results["related"][$i]) . "&" .
					$frontend->buildquery($get, true) .
					'">' .
					htmlspecialchars($results["related"][$i]) .
				'</a>';

		$payload["left"] .= '</td>';

		if($opentr === false){

			$payload["left"] .= '</tr>';
		}
	}

	if($opentr === true){

		$payload["left"] .= '<td></td></tr>';
	}

	$payload["left"] .= '</table>';
}

/*
	Load next page
*/
if($results["npt"] !== null){

	$payload["left"] .=
		'<a href="' . $frontend->htmlnextpage($get, $results["npt"], "web") . '" class="nextpage">Next page &gt;</a>';
}

echo $frontend->load("search.html", $payload);
