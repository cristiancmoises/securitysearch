<?php
include_once __DIR__ . "/lib/security_headers.php";

/*
	Initialize random shit
*/
include "data/config.php";
include "lib/frontend.php";
$frontend = new frontend();

[$scraper, $filters] = $frontend->getscraperfilters("images");
$get = $frontend->parsegetfilters($_GET, $filters);

/*
	Captcha
*/
include "lib/bot_protection.php";
new bot_protection($frontend, $get, $filters, "images", true);

$payload = [
	"timetaken" => microtime(true),
	"images" => "",
	"nextpage" => ""
];

try{
	$results = $scraper->image($get);

}catch(Exception $error){

	$frontend->drawscrapererror($error->getMessage(), $get, "images", $payload["timetaken"]);
}

/* ============================================================
   PAGE-SCOPED STYLES
   Additive only. We do NOT override layout / sizing on the
   existing .image-wrapper / .image / .thumb selectors that
   4get's base CSS relies on for the masonry grid.
   ============================================================ */
$payload["images"] = <<<CSS
<style>
/* IMAGE CARD POLISH ---------------------------------------- */
.image-wrapper{
    transition: transform .18s ease, filter .18s ease;
}
.image-wrapper:hover{
    transform: translateY(-2px);
    z-index: 2;
}
.image-wrapper .image .thumb{
    position: relative;
    overflow: hidden;
}
.image-wrapper .image .thumb img{
    transition: filter .2s ease, transform .25s ease;
    display: block;
}
.image-wrapper:hover .image .thumb img{
    filter: brightness(1.08) saturate(1.05);
    transform: scale(1.02);
}
.image-wrapper .image .thumb .duration{
    backdrop-filter: blur(6px) saturate(140%);
    -webkit-backdrop-filter: blur(6px) saturate(140%);
    background: rgba(0,0,0,0.55) !important;
    border: 1px solid rgba(255,255,255,0.08);
    font-variant-numeric: tabular-nums;
    letter-spacing: .02em;
}
.image-wrapper .image .title{
    opacity: .7;
    transition: opacity .15s;
}
.image-wrapper:hover .image .title{ opacity: 1; }

/* EMPTY STATE --------------------------------------------- */
.images-empty{
    max-width: 540px;
    margin: 2.5rem auto;
    padding: 1.5rem 1.5rem 1.4rem;
    background: rgba(255,255,255,0.025);
    border: 1px solid rgba(255,255,255,0.08);
    border-top-color: rgba(255,255,255,0.14);
    border-radius: 8px;
    text-align: center;
    position: relative;
    overflow: hidden;
}
@supports (backdrop-filter: blur(1px)){
    .images-empty{
        background: rgba(14,14,18,0.4);
        backdrop-filter: blur(14px) saturate(140%);
        -webkit-backdrop-filter: blur(14px) saturate(140%);
    }
}
.images-empty::before{
    content: "";
    position: absolute;
    inset: 0 0 auto 0;
    height: 2px;
    background: linear-gradient(90deg, var(--8ec07c, #5ce28a), var(--bdae93, #00eaff));
    opacity: .6;
}
.images-empty .ie-icon{
    width: 36px; height: 36px;
    margin: 0 auto .7rem;
    color: var(--8ec07c, currentColor);
    opacity: .65;
}
.images-empty h2{
    font-size: 1rem;
    font-weight: 600;
    margin: 0 0 .5rem;
    letter-spacing: -.01em;
}
.images-empty p{
    margin: 0 0 1rem;
    font-size: .85rem;
    line-height: 1.55;
    opacity: .75;
}
.images-empty p b{
    color: var(--bdae93, currentColor);
    font-weight: 600;
    opacity: 1;
}
.images-empty .ie-tips{
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
.images-empty .ie-tips li{
    padding-left: 1.1rem;
    position: relative;
}
.images-empty .ie-tips li::before{
    content: "›";
    position: absolute;
    left: 0;
    color: var(--8ec07c, #5ce28a);
    font-weight: 600;
}
.images-empty .ie-tips a{
    color: inherit;
    border-bottom: 1px solid rgba(255,255,255,0.2);
    transition: border-color .15s, color .15s;
}
.images-empty .ie-tips a:hover{
    color: var(--8ec07c, #5ce28a);
    border-bottom-color: var(--8ec07c, #5ce28a);
}
.images-empty .ie-actions{
    margin-top: 1.1rem;
    display: flex;
    justify-content: center;
    gap: .5rem;
    flex-wrap: wrap;
}
.images-empty .ie-btn{
    display: inline-block;
    padding: .45rem .95rem;
    font-size: .76rem;
    font-weight: 500;
    letter-spacing: .12em;
    text-transform: uppercase;
    color: inherit;
    background: rgba(255,255,255,0.04);
    border: 1px solid rgba(255,255,255,0.1);
    border-radius: 999px;
    transition: border-color .15s, background .15s, color .15s;
}
.images-empty .ie-btn:hover{
    border-color: rgba(255,255,255,0.25);
    background: rgba(255,255,255,0.08);
}
.images-empty .ie-btn.primary{
    color: #0a0a0c;
    background: var(--8ec07c, #5ce28a);
    border-color: transparent;
}
.images-empty .ie-btn.primary:hover{
    filter: brightness(1.1);
}

/* NEXT PAGE ----------------------------------------------- */
.nextpage.img{
    display: inline-flex !important;
    align-items: center;
    gap: .55rem;
    padding: .65rem 1.3rem;
    margin: 1.5rem auto;
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
    .nextpage.img{
        background: rgba(14,14,18,0.45);
        backdrop-filter: blur(14px) saturate(140%);
        -webkit-backdrop-filter: blur(14px) saturate(140%);
    }
}
.nextpage.img:hover{
    color: var(--8ec07c, #5ce28a);
    border-color: rgba(92,226,138,.4);
    background: rgba(92,226,138,.06);
    transform: translateY(-1px);
}

/* RESPECT REDUCED MOTION ---------------------------------- */
@media (prefers-reduced-motion: reduce){
    .image-wrapper,
    .image-wrapper .image .thumb img,
    .nextpage.img{ transition: none; }
    .image-wrapper:hover,
    .nextpage.img:hover{ transform: none; }
    .image-wrapper:hover .image .thumb img{ transform: none; }
}
</style>
CSS;

/* ============================================================
   EMPTY STATE
   ============================================================ */
if(count($results["image"]) === 0){

	$query = htmlspecialchars($get["s"] ?? "");

	$payload["images"] .=
		'<div class="images-empty">' .
			'<svg class="ie-icon" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round" aria-hidden="true">' .
				'<circle cx="11" cy="11" r="8"/>' .
				'<path d="m21 21-4.3-4.3"/>' .
				'<line x1="8" y1="11" x2="14" y2="11"/>' .
			'</svg>' .
			'<h2>No images found</h2>' .
			'<p>The scraper came back empty for <b>' . $query . '</b>.</p>' .
			'<ul class="ie-tips">' .
				'<li>Try a different image scraper in <a href="/settings">Settings</a></li>' .
				'<li>Use fewer or broader keywords</li>' .
				'<li>Check your NSFW filter — it may be hiding results</li>' .
				'<li>Some scrapers rate-limit briefly; wait a moment and retry</li>' .
			'</ul>' .
			'<div class="ie-actions">' .
				'<a href="/" class="ie-btn">← Home</a>' .
				'<a href="/settings" class="ie-btn primary">Open settings</a>' .
			'</div>' .
		'</div>';
}

/* ============================================================
   RESULTS — unchanged structure (4get lightbox JS depends on
   the .image-wrapper[data-json] attribute and class chain)
   ============================================================ */
foreach($results["image"] as $image){

	$host = parse_url($image["url"], PHP_URL_HOST) ?? "source";

	$payload["images"] .=
		'<div class="image-wrapper" title="' . htmlspecialchars($image["title"]) .'" data-json="' . htmlspecialchars(json_encode($image["source"])) . '">' .
			'<div class="image">' .
				'<a href="' . htmlspecialchars($image["source"][0]["url"]) . '" rel="noreferrer nofollow" class="thumb">' .
					'<img src="' . $frontend->htmlimage($image["source"][count($image["source"]) - 1]["url"], "thumb") . '" alt="thumbnail" loading="lazy" decoding="async">';

				if($image["source"][0]["width"] !== null){
					$payload["images"] .= '<div class="duration">' . $image["source"][0]["width"] . 'x' . $image["source"][0]["height"] . '</div>';
				}

			$payload["images"] .=
				'</a>' .
				'<a href="' . htmlspecialchars($image["url"]) . '" rel="noreferrer nofollow">' .
					'<div class="title">' . htmlspecialchars($host) . '</div>' .
					'<div class="description">' . $frontend->highlighttext($get["s"], $image["title"]) . '</div>' .
				'</a>' .
			'</div>' .
		'</div>';
}

/* ============================================================
   PAGINATION
   ============================================================ */
if($results["npt"] !== null){

	$payload["nextpage"] =
		'<a href="' . $frontend->htmlnextpage($get, $results["npt"], "images") . '" class="nextpage img">Next page &gt;</a>';
}

echo $frontend->load("images.html", $payload);
