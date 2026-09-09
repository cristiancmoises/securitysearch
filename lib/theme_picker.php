<?php
/** Native appearance form. Paths and names come only from bundled CSS files. */
function securitysearch_theme_catalog(): array {
    $out=['Black'=>null,'Tron'=>null,'Dark'=>null];
    foreach (glob(dirname(__DIR__).'/static/themes/*.css') ?: [] as $file) {
        $name=basename($file,'.css');
        if (preg_match('/\A[A-Za-z0-9][A-Za-z0-9 _-]{0,99}\z/',$name)!==1) { continue; }
        $preview='/static/theme-previews/'.rawurlencode($name).'.webp';
        $out[$name]=is_file(dirname(__DIR__).'/static/theme-previews/'.$name.'.webp') ? $preview : null;
    }
    return $out;
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
        $label=$name==='Black' ? 'Pure black' : $name;
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
        '<p>Saved in this browser. Previews use small, local still images; full wallpapers load only after you select their theme. Pure black loads no wallpaper.</p></form></details>';
}
