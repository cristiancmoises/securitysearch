<?php
require_once __DIR__."/operator_themes.php";
/** Native appearance form. Paths and names come only from bundled CSS files. */
function securitysearch_theme_catalog(): array {
    static $catalog=null;
    if ($catalog!==null) return $catalog;
    $out=[];
    $names=['Black','Tron','SecOps','Custom','Ajattix','Art','Art1','Art2','Art3',
        'Arte','Cat','Cat2','Gentoo','Kawaii','Lain','SecurityOps','Stop','Valerie'];
    foreach ($names as $name) {
        if (!is_file(dirname(__DIR__).'/static/themes/'.$name.'.css')) continue;
        $preview='/static/theme-previews/'.rawurlencode($name).'.webp';
        $out[$name]=is_file(dirname(__DIR__).'/static/theme-previews/'.$name.'.webp') ? $preview : null;
    }
    return $catalog=array_replace($out,operator_themes::catalog());
}

function securitysearch_theme_choice($value): ?string {
    return is_string($value) && array_key_exists($value,securitysearch_theme_catalog()) ? $value : null;
}

function securitysearch_theme_post(): void {
    header('Cache-Control: private, no-store');
    if (($_SERVER['REQUEST_METHOD'] ?? 'GET')!=='POST') { return; }
    if (($_SERVER['HTTP_SEC_FETCH_SITE'] ?? '')==='cross-site') {
        http_response_code(403); echo 'Use the appearance form on this site.'; exit;
    }
    $choice=securitysearch_theme_choice($_POST['theme'] ?? null);
    if (($_POST['appearance'] ?? null)!=='1' || $choice===null) {
        http_response_code(400); echo 'Choose an available theme.'; exit;
    }
    setcookie('theme',$choice,[
        'expires'=>time()+34560000,'path'=>'/','samesite'=>'Lax','httponly'=>true,
        'secure'=>(isset($_SERVER['HTTPS']) && $_SERVER['HTTPS']!=='off') || ($_SERVER['HTTP_X_FORWARDED_PROTO'] ?? '')==='https'
    ]);
    header('Location: /',true,303); exit;
}

function securitysearch_theme_picker(string $selected): string {
    $escape=static fn($s)=>htmlspecialchars($s,ENT_QUOTES|ENT_SUBSTITUTE,'UTF-8');
    $html='<details class="appearance-picker" id="appearance"><summary>Choose appearance <span>Black or your image themes</span></summary>' .
        '<form method="post" action="/" class="appearance-form"><input type="hidden" name="appearance" value="1">' .
        '<fieldset><legend>Background theme</legend><div class="appearance-grid">';
    foreach (securitysearch_theme_catalog() as $name=>$preview) {
        $label=['Black'=>'Pure black','Custom'=>'My picture','Stop'=>'Stop · palette','Lain'=>'Lain · palette'][$name] ?? $name;
        if ($name==='Lain' && operator_themes::available('Lain')) $label='Lain';
        $html.='<label class="appearance-choice"><input type="radio" name="theme" value="'.$escape($name).'"'.($name===$selected ? ' checked' : '').'>';
        if ($preview!==null) {
            $html.='<img src="'.$escape($preview).'?v'.config::VERSION.'" width="240" height="135" alt="" loading="lazy" decoding="async">';
        } else {
            $swatch=in_array($name,['Black','Tron'],true) ? strtolower($name) : 'plain';
            $html.='<span class="appearance-swatch appearance-'.$swatch.'" aria-hidden="true"></span>';
        }
        $html.='<span class="appearance-label">'.$escape($label).'</span></label>';
    }
    return $html.'</div></fieldset><div class="appearance-actions"><button type="submit">Save appearance</button><a href="/settings">All settings</a></div>' .
        '<p>Saved in this browser. Previews use small, local still images; full wallpapers load only after you select their theme. Pure black loads no wallpaper.</p></form>'.($selected==='Custom' ? securitysearch_background_controls() : '<p class="local-picture-hint">Select My picture and save to choose a file locally. Optional JavaScript; no image upload.</p>').'</details>';
}

/** File input is deliberately outside every form and has no name attribute. */
function securitysearch_background_controls(): string {
    return '<section class="local-background-controls" aria-labelledby="local-background-title">'.
        '<h2 id="local-background-title">Your picture, only in your browser</h2>'.
        '<label for="background-file">JPEG, PNG or WebP (up to 8 MiB)</label> '.
        '<input id="background-file" type="file" accept="image/jpeg,image/png,image/webp">'.
        '<label><input id="background-remember" type="checkbox"> Remember on this device (local storage)</label>'.
        '<button id="background-remove" type="button">Remove my picture</button>'.
        '<p id="background-status" role="status" aria-live="polite">Nothing is uploaded. By default the picture stays only in this tab session.</p>'.
        '<noscript><p>Local picture selection needs optional JavaScript. Search and bundled themes work without it.</p></noscript></section>';
}

function securitysearch_selected_theme(): string {
    $value=$_COOKIE['theme'] ?? (defined('config::DEFAULT_THEME') ? config::DEFAULT_THEME : 'Black');
    if ($value==='gentoo') $value='Gentoo';
    return securitysearch_theme_choice($value) ?? 'Black';
}
