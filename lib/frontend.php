<?php

require_once __DIR__ . "/provider_availability.php";

class frontend{
	// Cache only bundled, unrendered templates. Never store queries, cookies or
	// rendered HTML in the shared cache. File metadata invalidates local edits.
	private static function template_source(string $template): string{
		if(!preg_match('/\A[a-z][a-z0-9_-]*\.html\z/', $template)){
			throw new InvalidArgumentException("Invalid template name");
		}
		$path = dirname(__DIR__) . "/template/" . $template;
		$stat = stat($path);
		if($stat === false){ throw new RuntimeException("Template unavailable"); }
		$key = 'securitysearch-template-v1-' . hash('sha256', $path . ':' . config::VERSION . ':' . $stat['mtime'] . ':' . $stat['size']);
		$shared = function_exists('apcu_enabled') && apcu_enabled();
		if($shared){
			$source = apcu_fetch($key, $found);
			if($found && is_string($source)){ return $source; }
		}
		$data = file_get_contents($path);
		if($data === false){ throw new RuntimeException("Template unavailable"); }
		$source = implode('', array_map('trim', explode("\n", $data)));
		if($shared && strlen($source) <= 131072){ apcu_store($key, $source, 300); }
		return $source;
	}

	public function video_suggestion(string $query): string{
		$url = 'https://invidious.securityops.co/search?q=' . rawurlencode($query);
		return '<aside class="video-suggestion" aria-label="YouTube alternative">' .
			'<strong>Looking for YouTube videos?</strong> ' .
			'<a href="' . htmlspecialchars($url, ENT_QUOTES, 'UTF-8') . '" rel="noreferrer noopener">Search our Invidious instance</a>' .
			'<small>Selecting Invidious sends this search to our instance. Playback depends on YouTube availability.</small></aside>';
	}
	
	public function image_suggestion(array $get): string {
        $query = is_string($get['s'] ?? null) ? $get['s'] : '';
        $direct = 'https://images.securityops.co/search.php?q=' . rawurlencode($query);
        $local = '/images?s=' . rawurlencode($query) . '&scraper=binternet';
        $note = ($get['scraper'] ?? '') === 'binternet' ?
            'Binternet format filtering checks file extensions on each returned page; it may return fewer matches. Safe Search is controlled by that service.' :
            'Choose Pinterest via Binternet to search our image service. Format support varies by provider.';
        return '<aside class="video-suggestion" aria-label="Pinterest search"><a href="'.htmlspecialchars($local,ENT_QUOTES).'">Pinterest via Binternet</a> · <a href="'.htmlspecialchars($direct,ENT_QUOTES).'" rel="noreferrer noopener">Open Binternet</a><small>'. $note .'</small></aside>';
    }

	public function load($template, $replacements = []){

		$replacements["server_name"] = htmlspecialchars(config::SERVER_NAME);
		$replacements["version"] = config::VERSION;
        if (in_array($template, ["home.html", "header.html"], true)) {
            $replacements["search_actions"] = self::template_source("search-actions.html");
        }
		$replacements["video_suggestion"] ??= "";
		$replacements["image_suggestion"] ??= "";
		$replacements["trust_footer"] = '<div class="trust-footer"><p>In Code We Trust.</p><a href="https://git.securityops.co/" rel="noreferrer noopener">Explore the code</a></div>';

		$theme = config::DEFAULT_THEME;
		if(isset($_COOKIE["theme"]) && is_string($_COOKIE["theme"])){

			$requested_theme = $_COOKIE["theme"];
			$valid_theme_name =
				strlen($requested_theme) <= 100 &&
				preg_match('/\A[A-Za-z0-9][A-Za-z0-9 _-]*\z/', $requested_theme) === 1;

			if($requested_theme === "Dark"){

				$theme = "Dark";
			}elseif(
				$valid_theme_name &&
				is_file(dirname(__DIR__) . "/static/themes/" . $requested_theme . ".css")
			){

				$theme = $requested_theme;
			}
		}
		
		if($theme != "Dark"){
			
			$replacements["style"] = '<link rel="stylesheet" href="/static/themes/' . rawurlencode($theme) . '.css?v' . config::VERSION . '">';
		}else{
			
			$replacements["style"] = "";
		}
		
		if(isset($_COOKIE["scraper_ac"])){
			
			$replacements["ac"] = '?ac=' . htmlspecialchars($_COOKIE["scraper_ac"]);
		}else{
			
			$replacements["ac"] = '';
		}
		
		if(
			isset($replacements["timetaken"]) &&
			$replacements["timetaken"] !== null
		){
			
			$replacements["timetaken"] = '<div class="timetaken">Took ' . number_format(microtime(true) - $replacements["timetaken"], 2) . 's</div>';
		}
		
		$html = self::template_source($template);
		
		foreach($replacements as $key => $value){
		
			$html =
				str_replace(
					"{%{$key}%}",
					$value,
					$html
				);
		}
		
		return trim($html);
	}
	
	public function loadheader(array $get, array $filters, string $page, string $notice=""){
		// Search pages contain a visitor's query and selected preferences.
		if(!headers_sent()){ header('Cache-Control: private, no-store'); }
		$page_styles = [
			"web" => "web-results.css",
			"images" => "image-results.css"
		];
		$page_style = "";
		if(isset($page_styles[$page])){

			$page_style = '<link rel="stylesheet" href="/static/' . $page_styles[$page] . '?v' . config::VERSION . '">';
		}
		
		echo
			$this->load("header.html", [
				"title" => trim(htmlspecialchars($get["s"]) . " ({$page})"),
				"description" => ucfirst($page) . ' search results for &quot;' . htmlspecialchars($get["s"]) . '&quot;',
				"index" => "no",
				"search" => htmlspecialchars($get["s"]),
				"tabs" => $this->generatehtmltabs($page, $get["s"]),
				"filters" => $this->generatehtmlfilters($filters, $get),
				"page_style" => $page_style,
				"provider_notice" => $notice==='' ? '' : '<p class="provider-notice" role="status">'.htmlspecialchars($notice,ENT_QUOTES | ENT_SUBSTITUTE,'UTF-8').'</p>',
				"provider_label" => htmlspecialchars($filters['scraper']['option'][$get['scraper']] ?? 'Selected provider')
			]);
		
		$headers_raw = getallheaders();
		$header_keys = [];
		$user_agent = "";
		$bad_header = false;
		
		// block bots that present X-Forwarded-For, Via, etc
		foreach($headers_raw as $headerkey => $headervalue){
			
			$headerkey = strtolower($headerkey);
			if($headerkey == "user-agent"){
				
				$user_agent = $headervalue;
				continue;
			}
			
			// check header key
			if(in_array($headerkey, config::FILTERED_HEADER_KEYS)){
				
				$bad_header = true;
				break;
			}
		}
		
		// SSL check
		$bad_ssl = false;
		if(
			isset($_SERVER["https"]) &&
			$_SERVER["https"] == "on" &&
			isset($_SERVER["SSL_CIPHER"]) &&
			in_array($_SERVER["SSL_CIPHER"], config::FILTERED_HEADER_KEYS)
		){
			
			$bad_ssl = true;
		}
		
		if(
			$bad_header === true ||
			$bad_ssl === true ||
			$user_agent == "" ||
			// user agent check
			preg_match(
				config::HEADER_REGEX,
				$user_agent
			)
		){
			
			// bot detected !!
			apcu_inc("captcha_gen");
			
			$this->drawerror(
				"Request blocked",
				'Your browser, IP or IP range has been blocked from this 4get instance. If this is an error, please <a href="/about">contact the administrator</a>.'
			);
			die();
		}
	}
	
	public function drawerror($title, $error, $timetaken = null, $showtime = true){
		
		if($timetaken === null){
			
			$timetaken = microtime(true);
		}
		
		echo
			$this->load("search.html", [
				"timetaken" => $showtime ? $timetaken : null,
				"class" => " error-view",
				"right-left" => "",
				"right-right" => "",
				"left" =>
					'<div class="infobox">' .
						'<h1>' . htmlspecialchars($title) . '</h1>' .
						$error .
					'</div>'
			]);
		die();
	}
	
    public function provider_recovery(string $error, array $get, string $target): array {
        $target = in_array($target, ['web','images','videos','news','music'], true) ? $target : 'web';
        $current = is_string($get['scraper'] ?? null) ? $get['scraper'] : '';
        // A deliberate new search; never replay a continuation or restart a timer.
        foreach (['npt','frame','flow_action','flow_start','seconds','destination','append'] as $key) { unset($get[$key]); }
        $limited = str_starts_with($current, 'google') && preg_match('/rate.limit|cooldown|too many|429/i', $error);
        $title = $limited ? 'Google is temporarily unavailable' : 'Search paused';
        $message = $limited ? "Google is limiting requests from this instance. Try another provider, or wait at least 30 seconds before retrying." : 'This provider could not complete your search. Choose another provider or retry in a moment.';
        $alternatives = match ($target) {
            'images' => ['brave'=>'Try Brave', 'binternet'=>'Search Pinterest'],
            'web','news' => ['brave'=>'Try Brave', 'ddg'=>'Try DuckDuckGo'],
            'videos' => ['invidious'=>'Search YouTube', 'brave'=>'Try Brave'],
            default => []
        };
        $actions = '';
        foreach ($alternatives as $provider=>$label) {
            if ($provider === $current) { continue; }
            // Only shared image controls travel between providers. Numeric and
            // provider-specific filters can mean something different elsewhere.
            $alternate = array_intersect_key($get, array_flip(['s','view','quality','nsfw']));
            if (in_array($get['format'] ?? '', ['gif','webp','png','jpg','jpeg','avif','apng','svg'], true)) { $alternate['format']=$get['format']; }
            $alternate['scraper']=$provider;
            $actions .= '<a href="'.htmlspecialchars('/'.$target.'?'.$this->buildquery($alternate,false), ENT_QUOTES).'">'.$label.'</a>';
        }
        $actions .= '<a href="'.htmlspecialchars('/'.$target.'?'.$this->buildquery($get,false), ENT_QUOTES).'">Retry search</a><a href="/settings">Provider settings</a>';
        return [$title, '<p class="recovery-lead">'.$message.'</p><div class="error-actions">'.$actions.'</div>'.
            '<p class="error-note">Your search text and display choices are retained. Provider-specific filters may reset. No background retry is running.</p>'.
            '<details class="provider-details"><summary>Provider details</summary><p>'.htmlspecialchars($error, ENT_QUOTES | ENT_SUBSTITUTE, 'UTF-8').'</p></details>'];
    }

    public function drawscrapererror($error, $get, $target, $timetaken = null){
        if (!headers_sent()) {
            http_response_code(503);
            header('Cache-Control: private, no-store');
            header('Retry-After: 30');
            header_remove('Refresh');
        }
        [$title, $body] = $this->provider_recovery((string)$error, $get, $target);
        $this->drawerror($title, $body, $timetaken, false);
    }

	public function drawtextresult($site, $greentext = null, $duration = null, $keywords = "", $tabindex = true, $customhtml = null){
		
		$payload =
			'<div class="text-result">';
		
		// add favicon, link and archive links
		$payload .= $this->drawlink($site["url"]);
		
		/*
			Draw title + description + filetype
		*/
		$payload .=
			'<a href="' . htmlspecialchars($site["url"]) . '" class="hover" rel="noreferrer nofollow"';
			
		if($tabindex === false){
			
			$payload .= ' tabindex="-1"';
		}
			
		$payload .= '>';
			
			if($site["thumb"]["url"] !== null){
				
				$payload .=
					'<div class="thumb-wrap';
				
				switch($site["thumb"]["ratio"]){
					
					case "16:9":
						$size = "landscape";
						break;
					
					case "9:16":
						$payload .= " portrait";
						$size = "portrait";
						break;
					
					case "1:1":
						$payload .= " square";
						$size = "square";
						break;
				}
				
				$payload .=
					'">' .
						'<img class="thumb" src="' . $this->htmlimage($site["thumb"]["url"], $size) . '" alt="thumb" loading="lazy" decoding="async" fetchpriority="low">';
				
				if($duration !== null){
					
					$payload .=
						'<div class="duration">' .
							htmlspecialchars($duration) .
						'</div>';
				}
				
				$payload .=
					'</div>';
			}
			
		$payload .=
			'<div class="title">';
		
		if(
			isset($site["type"]) &&
			$site["type"] != "web"
		){
			
			$payload .= '<div class="type">' . strtoupper($site["type"]) . '</div>';
		}
		
		$payload .=
			$this->highlighttext($keywords, $site["title"]) .
		'</div>';
		
		if($greentext !== null){
			
			$payload .=
				'<div class="greentext">' .
					htmlspecialchars($greentext) .
				'</div>';
		}
		
		if($site["description"] !== null){
			
			$payload .=
				'<div class="description">' .
					$this->highlighttext($keywords, $site["description"]) .
				'</div>';
		}
		
		$payload .= $customhtml;
		
		$payload .= '</a>';
		
		/*
			Sublinks
		*/
		if(
			isset($site["sublink"]) &&
			!empty($site["sublink"])
		){
			
			usort($site["sublink"], function($a, $b){
				
				return strlen($a["description"]) > strlen($b["description"]);
			});
			
			$payload .=
				'<div class="sublinks">' .
					'<table>';
			
			$opentr = false;
			for($i=0; $i<count($site["sublink"]); $i++){
				
				if(($i % 2) === 0){
					
					$opentr = true;
					$payload .= '<tr>';
				}else{
					
					$opentr = false;
				}
				
				$payload .=
					'<td>' .
						'<a href="' . htmlspecialchars($site["sublink"][$i]["url"]) . '" rel="noreferrer nofollow">' .
							'<div class="title">' .
								htmlspecialchars($site["sublink"][$i]["title"]) .
							'</div>';
				
				if(!empty($site["sublink"][$i]["date"])){
					
					$payload .=
						'<div class="greentext">' .
							date("jS M y @ g:ia", $site["sublink"][$i]["date"]) .
						'</div>';
				}
				
				if(!empty($site["sublink"][$i]["description"])){
					
					$payload .=
						'<div class="description">' .
							$this->highlighttext($keywords, $site["sublink"][$i]["description"]) .
						'</div>';
				}
				
				$payload .= '</a></td>';
				
				if($opentr === false){
					
					$payload .= '</tr>';
				}
			}
			
			if($opentr === true){
				
				$payload .= '<td></td></tr>';
			}
			
			$payload .= '</table></div>';
		}
		
		if(
			isset($site["table"]) &&
			!empty($site["table"])
		){
			
			$payload .= '<table class="info-table">';
			
			foreach($site["table"] as $title => $value){
				
				$payload .=
					'<tr>' .
						'<td>' . htmlspecialchars($title) . '</td>' .
						'<td>' . htmlspecialchars($value) . '</td>' .
					'</tr>';
			}
			
			$payload .= '</table>';
		}
		
		return $payload . '</div>';
	}
	
	public function highlighttext($keywords, $text){
		if(trim((string)$keywords)===''){ return htmlspecialchars((string)$text,ENT_QUOTES | ENT_SUBSTITUTE,'UTF-8'); }
		
		$text = htmlspecialchars($text);
		
		$keywords = explode(" ", $keywords);
		$regex = [];
		
		foreach($keywords as $word){
			
			$regex[] = "\b" . preg_quote($word, "/") . "\b";
		}
		
		$regex = "/" . implode("|", $regex) . "/i";
		
		return
			preg_replace(
				$regex,
				'<b>${0}</b>',
				$text
			);
	}
	
	function highlightcode($text){
		
		// https://www.php.net/highlight_string
		ini_set("highlight.comment", "c-comment");
		ini_set("highlight.default", "c-default");
		ini_set("highlight.html", "c-default");
		ini_set("highlight.keyword", "c-keyword");
		ini_set("highlight.string", "c-string");
		
		$text =
			trim(
				preg_replace(
					'/<code [^>]+>/',
					"",
					str_replace(
						[
							"<br />",
							"&nbsp;",
							"<pre>",
							"</pre>",
							"</code>"
						],
						[
							"\n",
							" ",
							"",
							"",
							""
						],
						explode(
							"&lt;?php",
							highlight_string("<?php " . $text, true),
							2
						)[1]
					)
				)
			);
		
		// replace colors
		$classes = ["c-comment", "c-default", "c-keyword", "c-string"];
		
		foreach($classes as $class){
			
			$text = str_replace('<span style="color: ' . $class . '">', '<span class="' . $class . '">', $text);
		}
		
		return $text;
	}
	
	public function drawlink($link){
		
		/*
			Add favicon
		*/
		$host = parse_url($link);
		
		// special case for when we're not drawing a full url
		if(!isset($host["host"])){
			
			$payload =
				'<div class="url">' .
					'<button class="favicon" tabindex="-1">' .
						'<img src="/favicon?s=404" alt="xx" loading="lazy" decoding="async" fetchpriority="low">' .
					'</button>';
		}else{
			
			$esc =
				explode(
					".",
					$host["host"],
					2
				);
			
			if(
				count($esc) === 2 &&
				$esc[0] == "www"
			){
				
				$esc = $esc[1];
			}else{
				
				$esc = $esc[0];
			}
			
			$esc = substr($esc, 0, 2);
			
			$urlencode = urlencode($link);
			
			$payload =
				'<div class="url">' .
					'<button class="favicon" tabindex="-1">' .
						'<img src="/favicon?s=' . htmlspecialchars($host["scheme"] . "://" . $host["host"]) . '" alt="' . htmlspecialchars($esc) . '" loading="lazy" decoding="async" fetchpriority="low">' .
						//'<img src="/404.php" alt="' . htmlspecialchars($esc) . '">' .
					'</button>' .
					'<div class="favicon-dropdown">';
			
			/*
				Add archive links
			*/
			if(
				$host["host"] == "boards.4chan.org" ||
				$host["host"] == "boards.4channel.org"
			){
				
				$archives = [];
				$path = explode("/", $host["path"]);
				$count = count($path);
				// /pol/thread/417568063/post-shitty-memes-if-you-want-to
				
				if($count !== 0){
					
					$isboard = true;
					
					switch($path[1]){
						
						case "con":
							break;
						
						case "q":
							$archives[] = "desuarchive.org";
							break;
							
						case "qa":
							$archives[] = "desuarchive.org";
							break;
							
						case "qb":
							$archives[] = "arch.b4k.co";
							break;
							
						case "trash":
							$archives[] = "desuarchive.org";
							break;
						
						case "a":
							$archives[] = "desuarchive.org";
							break;
						
						case "c":
							$archives[] = "desuarchive.org";
							break;
						
						case "w":
							break;
						
						case "m":
							$archives[] = "desuarchive.org";
							break;
						
						case "cgl":
							$archives[] = "desuarchive.org";
							$archives[] = "warosu.org";
							break;
						
						case "f":
							$archives[] = "archive.4plebs.org";
							break;
						
						case "n":
							break;
						
						case "jp":
							$archives[] = "warosu.org";
							break;
						
						case "vt":
							$archives[] = "warosu.org";
							break;
						
						case "v":
							$archives[] = "arch.b4k.co";
							break;
						
						case "vg":
							$archives[] = "arch.b4k.co";
							break;
						
						case "vm":
							$archives[] = "arch.b4k.co";
							break;
						
						case "vmg":
							$archives[] = "arch.b4k.co";
							break;
						
						case "vp":
							$archives[] = "arch.b4k.co";
							break;
						
						case "vr":
							$archives[] = "desuarchive.org";
							$archives[] = "warosu.org";
							break;
						
						case "vrpg":
							$archives[] = "arch.b4k.co";
							break;
						
						case "vst":
							$archives[] = "arch.b4k.co";
							break;
						
						case "co":
							$archives[] = "desuarchive.org";
							break;
						
						case "g":
							$archives[] = "desuarchive.org";
							$archives[] = "arch.b4k.co";
							break;
						
						case "tv":
							$archives[] = "archive.4plebs.org";
							break;
						
						case "k":
							$archives[] = "desuarchive.org";
							break;
						
						case "o":
							$archives[] = "archive.4plebs.org";
							break;
						
						case "an":
							$archives[] = "desuarchive.org";
							break;
						
						case "tg":
							$archives[] = "desuarchive.org";
							$archives[] = "archive.4plebs.org";
							break;
						
						case "sp":
							$archives[] = "archive.4plebs.org";
							break;
						
						case "xs":
							$archives[] = "eientei.xyz";
							break;
						
						case "pw":
							break;
						
						case "sci":
							$archives[] = "warosu.org";
							$archives[] = "eientei.xyz";
							break;
						
						case "his":
							$archives[] = "desuarchive.org";
							break;
						
						case "int":
							$archives[] = "desuarchive.org";
							break;
						
						case "out":
							break;
						
						case "toy":
							break;
						
						case "i":
							$archives[] = "archiveofsins.com";
							$archives[] = "eientei.xyz";
							break;
						
						case "po":
							break;
						
						case "p":
							break;
						
						case "ck":
							$archives[] = "warosu.org";
							break;
						
						case "ic":
							$archives[] = "warosu.org";
							break;
						
						case "wg":
							break;
						
						case "lit":
							$archives[] = "warosu.org";
							break;
						
						case "mu":
							$archives[] = "desuarchive.org";
							break;
						
						case "fa":
							$archives[] = "warosu.org";
							break;
						
						case "3":
							$archives[] = "warosu.org";
							$archives[] = "eientei.xyz";
							break;
						
						case "gd":
							break;
						
						case "diy":
							$archives[] = "warosu.org";
							break;
						
						case "wsg":
							$archives[] = "desuarchive.org";
							break;
						
						case "qst":
							break;
						
						case "biz":
							$archives[] = "warosu.org";
							break;
						
						case "trv":
							$archives[] = "archive.4plebs.org";
							break;
						
						case "fit":
							$archives[] = "desuarchive.org";
							break;
						
						case "x":
							$archives[] = "archive.4plebs.org";
							break;
						
						case "adv":
							$archives[] = "archive.4plebs.org";
							break;
						
						case "lgbt":
							$archives[] = "archiveofsins.com";
							break;
						
						case "mlp":
							$archives[] = "desuarchive.org";
							$archives[] = "arch.b4k.co";
							break;
						
						case "news":
							break;
						
						case "wsr":
							break;
						
						case "vip":
							break;
						
						case "b":
							$archives[] = "thebarchive.com";
							break;
						
						case "r9k":
							$archives[] = "desuarchive.org";
							break;
						
						case "pol":
							$archives[] = "archive.4plebs.org";
							break;
						
						case "bant":
							$archives[] = "thebarchive.com";
							break;
						
						case "soc":
							$archives[] = "archiveofsins.com";
							break;
						
						case "s4s":
							$archives[] = "archive.4plebs.org";
							break;
						
						case "s":
							$archives[] = "archiveofsins.com";
							break;
						
						case "hc":
							$archives[] = "archiveofsins.com";
							break;
						
						case "hm":
							$archives[] = "archiveofsins.com";
							break;
						
						case "h":
							$archives[] = "archiveofsins.com";
							break;
						
						case "e":
							break;
						
						case "u":
							$archives[] = "archiveofsins.com";
							break;
						
						case "d":
							$archives[] = "desuarchive.org";
							break;
						
						case "t":
							$archives[] = "archiveofsins.com";
							break;
						
						case "hr":
							$archives[] = "archive.4plebs.org";
							break;
						
						case "gif":
							break;
						
						case "aco":
							$archives[] = "desuarchive.org";
							break;
						
						case "r":
							$archives[] = "archiveofsins.com";
							break;
						
						default:
							$isboard = false;
							break;
					}
					
					if($isboard === true){
						
						$archives[] = "archived.moe";
					}
					
					$trail = "";
					
					if(
						isset($path[2]) &&
						isset($path[3]) &&
						$path[2] == "thread"
					){
						
						$trail .= "/" . $path[1] . "/thread/" . $path[3];
					}elseif($isboard){
						
						$trail = "/" . $path[1] . "/";
					}
					
					for($i=0; $i<count($archives); $i++){
						
						$payload .=
							'<a href="https://' . $archives[$i] . $trail . '" class="list" target="_BLANK">' .
								'<img src="/favicon?s=https://' . $archives[$i] . '" alt="' . $archives[$i][0] . $archives[$i][1] . '" loading="lazy" decoding="async" fetchpriority="low">' .
								$archives[$i] .
							'</a>';
					}
				}
			}
			
			$payload .=
					'<a href="https://web.archive.org/web/' . $urlencode . '" class="list" target="_BLANK"><img src="/favicon?s=https://archive.org" alt="ar" loading="lazy" decoding="async" fetchpriority="low">Archive.org</a>' .
					'<a href="https://archive.ph/newest/' . htmlspecialchars($link) . '" class="list" target="_BLANK"><img src="/favicon?s=https://archive.is" alt="ar" loading="lazy" decoding="async" fetchpriority="low">Archive.is</a>' .
					'<a href="https://ghostarchive.org/search?term=' . $urlencode . '" class="list" target="_BLANK"><img src="/favicon?s=https://ghostarchive.org" alt="gh" loading="lazy" decoding="async" fetchpriority="low">Ghostarchive</a>' .
					'<a href="https://arquivo.pt/wayback/' . htmlspecialchars($link) . '" class="list" target="_BLANK"><img src="/favicon?s=https://arquivo.pt" alt="ar" loading="lazy" decoding="async" fetchpriority="low">Arquivo.pt</a>' .
					'<a href="https://www.bing.com/search?q=url%3A' . $urlencode . '" class="list" target="_BLANK"><img src="/favicon?s=https://bing.com" alt="bi" loading="lazy" decoding="async" fetchpriority="low">Bing cache</a>' .
					'<a href="https://megalodon.jp/?url=' . $urlencode . '" class="list" target="_BLANK"><img src="/favicon?s=https://megalodon.jp" alt="me" loading="lazy" decoding="async" fetchpriority="low">Megalodon</a>' .
				'</div>';
		}
		
		/*
			Draw link
		*/
		$parts = explode("/", $link);
		$clickurl = "";
		
		// remove trailing /
		$c = count($parts) - 1;
		if($parts[$c] == ""){
			
			$parts[$c - 1] = $parts[$c - 1] . "/";
			unset($parts[$c]);
		}
		
		// merge https://site together
		if(isset($host["host"])){
			$parts = [
				$parts[0] . $parts[1] . '//' . $parts[2],
				...array_slice($parts, 3, count($parts) - 1)
			];
		}
		
		$c = count($parts);
		for($i=0; $i<$c; $i++){
			
			if($i !== 0){ $clickurl .= "/"; }
			
			$clickurl .= $parts[$i];
			
			if($i === $c - 1){
				
				$parts[$i] = rtrim($parts[$i], "/");
			}
			
			$payload .=
				'<a class="part" href="' . htmlspecialchars($clickurl) . '" rel="noreferrer nofollow" tabindex="-1">' .
					htmlspecialchars(urldecode($parts[$i])) .
				'</a>';
			
			if($i !== $c - 1){
				
				$payload .= '<span class="separator"></span>';
			}
		}
		
		return $payload . '</div>';
	}
	
	public function getscraperfilters($page){
        // Native submit buttons use a separate name from the Scraper select.
        // Destination changes always begin a new search with compatible filters.
        $destination = $_GET['destination'] ?? null;
        if (($page === 'images' && in_array($destination, ['images','binternet'], true)) || ($page === 'videos' && $destination === 'invidious')) {
            unset($_GET['npt']);
            $_GET = array_intersect_key($_GET, array_flip(['s','view','quality','nsfw','format']));
            if ($destination === 'images') {
                unset($_GET['scraper']);
            } else { $_GET['scraper'] = $destination; }
        }

		
		$default_scraper = null;
		if($page === "web"){
			$default_scraper = config::DEFAULT_SCRAPER_WEB;
		}elseif($page === "images"){
			$default_scraper = config::DEFAULT_SCRAPER_IMAGES;
		}elseif($page === "news"){
			$default_scraper = config::DEFAULT_SCRAPER_NEWS;
		}

		$get_scraper =
			isset($_COOKIE["scraper_$page"])
				? $_COOKIE["scraper_$page"]
				: $default_scraper;
		
		if(
			isset($_GET["scraper"]) &&
			is_string($_GET["scraper"])
		){
			
			$get_scraper = $_GET["scraper"];
		}else{
			
			if(
				isset($_GET["npt"]) &&
				is_string($_GET["npt"])
			){
				
				$get_scraper = explode(".", $_GET["npt"], 2)[0];
				
				$get_scraper =
					preg_replace(
						'/[0-9]+$/',
						"",
						$get_scraper
					);
			}
		}
		
		// add search field
		$filters =
			[
				"s" => [
					"option" => "_SEARCH"
				]
			];
		
		// define default scrapers
		switch($page){
			
			case "web":
				$filters["scraper"] = [
					"display" => "Scraper",
					"option" => [
						"google" => "Google",
						"brave" => "Brave",
						"google_api" => "Google API",
						"ddg" => "DuckDuckGo",
						"yandex" => "Yandex",
						"google_cse" => "Google CSE",
						"yahoo_japan" => "Yahoo! JAPAN",
						"startpage" => "Startpage",
						"qwant" => "Qwant",
						"ghostery" => "Ghostery",
						"yep" => "Yep",
						"mwmbl" => "Mwmbl",
						"mojeek" => "Mojeek",
						"baidu" => "Baidu",
						"coccoc" => "Cốc Cốc",
						"solofield" => "Solofield",
						"marginalia" => "Marginalia",
						"wiby" => "wiby"
					]
				];
				break;
			
			case "images":
				$filters["scraper"] = [
					"display" => "Scraper",
					"option" => [
						"google" => "Google",
						"brave" => "Brave",
						"google_cse" => "Google CSE",
						"google_api" => "Google API",
						"yandex" => "Yandex",
						"ddg" => "DuckDuckGo",
						"yahoo_japan" => "Yahoo! JAPAN",
						"startpage" => "Startpage",
						"qwant" => "Qwant",
						"baidu" => "Baidu",
						"solofield" => "Solofield",
						"binternet" => "Pinterest via Binternet",
						"pinterest" => "Pinterest (direct)",
						"cara" => "Cara",
						"flickr" => "Flickr",
						"fivehpx" => "500px",
						"vsco" => "VSCO",
						"imgur" => "Imgur",
						"pexels" => "Pexels",
						"unsplash" => "Unsplash",
						"pixabay" => "Pixabay",
						"ftm" => "FindThatMeme",
						//"sankakucomplex" => "SankakuComplex"
					]
				];
				break;
			
			case "videos":
				$filters["scraper"] = [
					"display" => "Scraper",
					"option" => [
						"invidious" => "YouTube via Invidious",
						"yt" => "YouTube (direct)",
						"archiveorg" => "Archive.org",
						"vimeo" => "Vimeo",
						//"odysee" => "Odysee",
						"sepiasearch" => "Sepia Search",
						//"fb" => "Facebook videos",
						"ddg" => "DuckDuckGo",
						"brave" => "Brave",
						"yandex" => "Yandex",
						"yahoo_japan" => "Yahoo! JAPAN",
						"startpage" => "Startpage",
						"qwant" => "Qwant",
						"baidu" => "Baidu",
						"coccoc" => "Cốc Cốc",
						"solofield" => "Solofield"
					]
				];
				break;
			
			case "news":
				$filters["scraper"] = [
					"display" => "Scraper",
					"option" => [
						"reddit" => "Reddit via Redlib",
						"ddg" => "DuckDuckGo",
						"brave" => "Brave",
						"yahoo_japan" => "Yahoo! JAPAN",
						"startpage" => "Startpage",
						"qwant" => "Qwant",
						"yep" => "Yep",
						"mojeek" => "Mojeek",
						"baidu" => "Baidu"
					]
				];
				break;
			
			case "music":
				$filters["scraper"] = [
					"display" => "Scraper",
					"option" => [
						"sc" => "SoundCloud",
						"swisscows" => "Swisscows (SoundCloud)"
						//"spotify" => "Spotify"
					]
				];
				break;
		}

		if(
			isset($filters["scraper"]["option"]["google_api"]) &&
			!securitysearch_google_api_available()
		){

			if($get_scraper === "google_api"){
				// Respect an explicit or saved choice: never send its query to CSE
				// merely because this instance has no API key configured.
				$filters["scraper"]["option"]["google_api"] = "Google API — not configured";
			}else{
				unset($filters["scraper"]["option"]["google_api"]);
			}
		}

		if($page === "images"){

			$filters["view"] = [
				"display" => "View",
				"option" => [
					"grid" => "Grid",
					"compact" => "Compact grid",
					"gallery" => "Gallery — uncropped",
					"feed" => "Large feed",
					"list" => "List — titles & sources",
					"filmstrip" => "Filmstrip — side by side"
				]
			];
			$filters["quality"] = [
				"display" => "Quality",
				"option" => [
					"preview" => "Fast preview",
					"high" => "High quality — up to 1280 px",
					"original" => "Original"
				]
			];
		}
		
		// get scraper name from user input, or default out to preferred scraper
		$scraper_out = null;
		$first = true;
		
		foreach($filters["scraper"]["option"] as $scraper_name => $scraper_pretty){
			
			if($first === true){
				
				$first = $scraper_name;
			}
			
			if($scraper_name == $get_scraper){
				
				$scraper_out = $scraper_name;
			}
		}
		
		if($scraper_out === null){
			
			$scraper_out = $first;
		}
		
		include_once "scraper/$scraper_out.php";
		$lib = new $scraper_out();
		
		// set scraper on $_GET
		$_GET["scraper"] = $scraper_out;
		
		// set nsfw on $_GET
		if(!isset($_GET["nsfw"])){

			if(isset($_COOKIE["nsfw"]) && is_string($_COOKIE["nsfw"])){

				$_GET["nsfw"] = $_COOKIE["nsfw"];
			}else{

				$_GET["nsfw"] = config::DEFAULT_NSFW;
			}
		}
		
		return
			[
				$lib,
				array_merge_recursive(
					$filters,
					$lib->getfilters($page)
				)
			];
	}
	
	public function parsegetfilters($parameters, $whitelist){
		
		$sanitized = [];
		
		// add npt token
		if(
			isset($parameters["npt"]) &&
			is_string($parameters["npt"])
		){
			
			$sanitized["npt"] = $parameters["npt"];
		}else{
			
			$sanitized["npt"] = false;
		}
		
		// we're iterating over $whitelist, so
		// you can't polluate $sanitized with useless
		// parameters
		foreach($whitelist as $parameter => $value){
			
			if(isset($parameters[$parameter])){
				
				if(!is_string($parameters[$parameter])){
					
					$sanitized[$parameter] = is_array($value["option"]) ? array_key_first($value["option"]) : ($value["option"] === "_SEARCH" ? "" : false);
					continue;
				}
				
				// parameter is already set, use that value
				$sanitized[$parameter] = $parameters[$parameter];
			}else{
				
				// parameter is not set, add it
				if(is_string($value["option"])){
					
					// special field: set default value manually
					switch($value["option"]){
						
						case "_DATE":
							// no date set
							$sanitized[$parameter] = false;
							break;
						
						case "_SEARCH":
							// no search set
							$sanitized[$parameter] = "";
							break;
					}
					
				}else{
					
					// set a default value
					$sanitized[$parameter] = array_keys($value["option"])[0];
				}
			}
			
			// sanitize input
			if(is_array($value["option"])){
				if(
					!in_array(
						$sanitized[$parameter],
						$keys = array_keys($value["option"])
					)
				){
					
					$sanitized[$parameter] = $keys[0];
				}
			}else{
				
				// sanitize search & string
				switch($value["option"]){
					
					case "_DATE":
						if($sanitized[$parameter] !== false){
							
							$sanitized[$parameter] = strtotime($sanitized[$parameter]);
							if($sanitized[$parameter] <= 0){
								
								$sanitized[$parameter] = false;
							}
						}
						break;
					
					case "_SEARCH":
						// get search string
						$sanitized["s"] = mb_strcut(trim($sanitized[$parameter]), 0, 500, "UTF-8");
				}
			}
		}
		
		// invert dates if needed
		if(
			isset($sanitized["older"]) &&
			isset($sanitized["newer"]) &&
			$sanitized["newer"] !== false &&
			$sanitized["older"] !== false &&
			$sanitized["newer"] > $sanitized["older"]
		){
			
			// invert
			[
				$sanitized["older"],
				$sanitized["newer"]
			] = [
				$sanitized["newer"],
				$sanitized["older"]
			];
		}
		
		return $sanitized;
	}

	public function s_to_timestamp($seconds){
		
		if(is_string($seconds)){
			
			return "LIVE";
		}
		
		return ($seconds >= 60) ? ltrim(gmdate("H:i:s", $seconds), ":0") : gmdate("0:s", $seconds);
	}
	
	public function generatehtmltabs($page, $query){
		
		$html = null;
		
		foreach(["web", "images", "videos", "news", "music"] as $type){
			
			$html .= '<a href="/' . $type . '?s=' . urlencode($query);
			
			if(!empty($params)){
				
				$html .= $params;
			}
			
			$html .= '" class="tab';
			
			if($type == $page){
				
				$html .= ' selected';
			}
			
			$html .= '">' . ucfirst($type) . '</a>';
		}
		
		return $html;
	}
	
	public function generatehtmlfilters($filters, $params){
		
		$html = null;
		
		foreach($filters as $filter_name => $filter_values){
			
			if(!isset($filter_values["display"])){
				
				continue;
			}
			
			$output = true;
			$tmp =
				'<div class="filter">' .
					'<div class="title">' . htmlspecialchars($filter_values["display"]) . '</div>';
			
			if(is_array($filter_values["option"])){
				
				$tmp .= '<select name="' . $filter_name . '">';
				
				foreach($filter_values["option"] as $option_name => $option_title){
					
					$tmp .= '<option value="' . $option_name . '"';
					
					if($params[$filter_name] == $option_name){
						
						$tmp .= ' selected';
					}
					
					$tmp .= '>' . htmlspecialchars($option_title) . '</option>';
				}
				
				$tmp .= '</select>';
			}else{
				
				switch($filter_values["option"]){
					
					case "_DATE":
						$tmp .= '<input type="date" name="' . $filter_name . '"';
						
						if($params[$filter_name] !== false){
							
							$tmp .= ' value="' . date("Y-m-d", $params[$filter_name]) . '"';
						}
						
						$tmp .= '>';
						break;
					
					default:
						$output = false;
						break;
				}
			}
			
			$tmp .= '</div>';
			
			if($output === true){
				
				$html .= $tmp;
			}
		}
		
		return $html;
	}
	
	public function buildquery($gets, $ommit = false){
		
		$out = [];
		foreach($gets as $key => $value){
			
			if(
				$value == null ||
				$value == false ||
				$key == "npt" ||
				$key == "extendedsearch" ||
				$value == "any" ||
				$value == "all" ||
				$key == "spellcheck" ||
				(
					$ommit === true &&
					$key == "s"
				)
			){
				
				continue;
			}
			
			if(
				$key == "older" ||
				$key == "newer"
			){
				
				$value = date("Y-m-d", (int)$value);
			}
			
			$out[$key] = $value;
		}
		
		return http_build_query($out);
	}
	
	public function animatedimageformat($image){

		if(!is_string($image) || $image === ""){

			return null;
		}

		$decoded = rawurldecode($image);
		// Remote image results must pass through the same-origin proxy so the
		// payload size, MIME type, and frame count can be validated. htmlimage()
		// intentionally returns data URLs unchanged, so they are not candidates.
		if(stripos($decoded, "data:") === 0){

			return null;
		}

		$format = $this->animationformathint($decoded);
		if($format !== null){

			return $format;
		}

		// GitHub Camo can hide the original URL as a hexadecimal path segment.
		// Decode only its documented, tightly bounded URL shape and retain the
		// Camo address as the eventual proxy target. This is only a candidate
		// hint; /proxy still verifies MIME, size, and multiple frames.
		$parts = parse_url($decoded);
		if(
			is_array($parts) &&
			isset($parts["host"], $parts["path"]) &&
			strtolower($parts["host"]) === "camo.githubusercontent.com" &&
			preg_match('#\A/[a-f0-9]{64}/([a-f0-9]{2,8192})\z#i', $parts["path"], $match) === 1 &&
			strlen($match[1]) % 2 === 0
		){

			$embedded = hex2bin($match[1]);
			$embedded_parts = is_string($embedded) ? parse_url($embedded) : false;
			if(
				is_array($embedded_parts) &&
				isset($embedded_parts["scheme"], $embedded_parts["host"]) &&
				in_array(strtolower($embedded_parts["scheme"]), ["http", "https"], true) &&
				$embedded_parts["host"] !== "" &&
				!isset($embedded_parts["user"]) &&
				!isset($embedded_parts["pass"])
			){

				return $this->animationformathint($embedded);
			}
		}

		return null;
	}

	private function animationformathint($decoded){

		$path = parse_url($decoded, PHP_URL_PATH);
		if(is_string($path)){

			$extension = strtolower(pathinfo($path, PATHINFO_EXTENSION));
			if(in_array($extension, ["gif", "webp", "apng"], true)){

				return strtoupper($extension);
			}

			// APNG is commonly served with the ordinary .png extension. Only
			// promote explicit filename hints; the proxy still verifies acTL and
			// frame count, so a false-positive name safely falls back to its poster.
			if(
				$extension === "png" &&
				preg_match('/(?:^|[-_.])(?:apng|animated[-_.]?png)(?:[-_.]|$)/i', basename($path))
			){

				return "APNG";
			}
		}

		// Some CDNs put an encoded source URL or an explicit format in the
		// query string. Limit recognition to animation-capable raster formats.
		if(
			preg_match('/\.(gif|webp|apng)(?:$|[?&#])/i', $decoded, $match) ||
			preg_match('/(?:^|[?&])(?:format|fmt|fm|ext|type|mime)=(?:image\/)?(gif|webp|apng)(?:$|[&#])/i', $decoded, $match)
		){

			return strtoupper($match[1]);
		}

		return null;
	}

	public function htmlimage($image, $format){
		
		if(
			preg_match(
				'/^data:/',
				$image
			)
		){
			
			return htmlspecialchars($image);
		}
		
		//return "https://4get.ca/proxy?i=" . urlencode($image) . "&s=" . $format;
		return "/proxy?i=" . urlencode($image) . "&s=" . $format;
	}
	
	public function htmlnextpage($gets, $npt, $page){
		
		$query = $this->buildquery($gets);
		
		return $page . "?" . $query . "&npt=" . $npt;
	}
}
