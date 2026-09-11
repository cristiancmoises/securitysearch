<?php
require_once __DIR__."/theme_picker.php";
require_once __DIR__."/home_styles.php";
require_once __DIR__."/site_metadata.php";
require_once __DIR__."/service_pool.php";
require_once __DIR__."/view_resources.php";

/** Shared page shell; homepage need not load the search-result renderer. */
class page_renderer {
	// Cache only bundled, unrendered templates. Never store queries, cookies or
	// rendered HTML in the shared cache. File metadata invalidates local edits.
	private static function template_source(string $template): string{
        static $request_cache=[];
		if(!preg_match('/\A[a-z][a-z0-9_-]*\.html\z/', $template)){
			throw new InvalidArgumentException("Invalid template name");
		}
		if(isset($request_cache[$template]))return $request_cache[$template];
        $bundled=view_resources::template($template);
        if($bundled!==null)return $request_cache[$template]=$bundled;
        $path = dirname(__DIR__) . "/template/" . $template;
		$stat = stat($path);
		if($stat === false){ throw new RuntimeException("Template unavailable"); }
		$key = 'securitysearch-template-v1-' . hash('sha256', $path . ':' . config::VERSION . ':' . $stat['mtime'] . ':' . $stat['size']);
		$shared = function_exists('apcu_enabled') && apcu_enabled();
		if($shared){
			$source = apcu_fetch($key, $found);
			if($found && is_string($source)){ return $request_cache[$template]=$source; }
		}
		$data = file_get_contents($path);
		if($data === false){ throw new RuntimeException("Template unavailable"); }
		$source = implode('', array_map('trim', explode("\n", $data)));
		if($shared && strlen($source) <= 131072){ apcu_store($key, $source, 300); }
		return $request_cache[$template]=$source;
	}

	public function load($template, $replacements = []){

        $html = self::template_source($template);
        $needs=static fn(string $key):bool=>str_contains($html,'{%'.$key.'%}');

		$replacements["server_name"] = htmlspecialchars(config::SERVER_NAME);
		$replacements["version"] = config::VERSION;
        if (in_array($template, ["home.html", "header.html"], true)) {
            $replacements["search_actions"] = self::template_source("search-actions.html");
        }
		$replacements["video_suggestion"] ??= "";
		$replacements["image_suggestion"] ??= "";
        if($needs("trust_footer")) $replacements["trust_footer"] = securitysearch_footer();
        $replacements["redlib_attribution"] = htmlspecialchars(redlib_attribution::NOTICE,ENT_QUOTES|ENT_SUBSTITUTE,"UTF-8");
        $replacements["redlib_origin"] = htmlspecialchars(service_pool::primary(),ENT_QUOTES|ENT_SUBSTITUTE,"UTF-8");
        $replacements["redlib_host"] = htmlspecialchars(parse_url(service_pool::primary(),PHP_URL_HOST),ENT_QUOTES|ENT_SUBSTITUTE,"UTF-8");
        if($needs("style") || $needs("theme_picker") || $needs("home_base_style")) {
        $theme = securitysearch_selected_theme();
        if ($template === 'home.html') $replacements['theme_picker'] = securitysearch_theme_picker($theme);
        $replacements["style"] = '<link rel="stylesheet" href="/static/themes/' . rawurlencode($theme) . '.css?v' . config::VERSION . '">' .
            '<link rel="stylesheet" href="/static/experience.css?v' . config::VERSION . '">';
        if ($template === 'home.html') {
            $replacements['home_base_style']=home_styles::inline('base') ?: '<link rel="stylesheet" href="/static/style.css?v'.config::VERSION.'">';
            $theme_style=$theme==='Black' ? home_styles::inline('black') : '';
            $replacements['style']=($theme_style ?: '<link rel="stylesheet" href="/static/themes/'.rawurlencode($theme).'.css?v'.config::VERSION.'">').
                (home_styles::inline('controls') ?: '<link rel="stylesheet" href="/static/experience.css?v'.config::VERSION.'">');
        }
        // A script is emitted only for the explicitly selected browser-local picture.
        if ($theme === 'SecOps' && !operator_themes::available('SecOps')) $replacements["style"] .= '<link rel="stylesheet" href="/static/themes/SecOps-motion.css?v' . config::VERSION . '">';
        if (operator_themes::available($theme)) $replacements["style"] .= '<link rel="stylesheet" href="/static/themes/' . rawurlencode($theme) . '-operator.css?v' . config::VERSION . '">';
        if ($theme === 'Custom') $replacements["style"] .= '<script defer src="/static/local-background.js?v' . config::VERSION . '"></script>';
        }

		if(isset($_COOKIE["scraper_ac"]) && is_string($_COOKIE["scraper_ac"]) && strlen($_COOKIE["scraper_ac"])<=64){

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


        // One pass: substituted values are never interpreted as template instructions.
        $map=[];
        foreach($replacements as $key=>$value) {
            if(!is_string($value) && !is_numeric($value) && $value!==null)throw new InvalidArgumentException('Invalid template value.');
            $map['{%'.$key.'%}']=(string)$value;
        }
        $html=strtr($html,$map);

		return trim($html);
	}

}
