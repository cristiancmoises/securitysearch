<?php
/** Shared by the full native suite and the extension-independent news contract check. */
function assert_external_navigation(frontend $frontend): void {
    $check = static function (bool $ok, string $message): void {
        if (!$ok) throw new RuntimeException($message);
    };
    foreach (['header.html', 'home.html'] as $template) {
        $html = $frontend->load($template);
        foreach (['Img'=>'img', 'Vids'=>'invidious', 'Chat'=>'chat', 'News'=>'news',
                  'Wiki'=>'wiki', 'Zupt'=>'zupt-web'] as $label=>$host) {
            $check(preg_match('#href="https://'.preg_quote($host, '#').'\.securityops\.co/"[^>]*>'.preg_quote($label, '#').'</a>#', $html) === 1,
                'Correct external navigation: '.$template.' '.$label);
        }
        // v0.9.23 intentionally uses the selected external primary, not libre.
        $url = service_pool::primary().'/';
        $check(preg_match('#href="'.preg_quote($url, '#').'"[^>]*>Reddit</a>#', $html) === 1,
            'Reddit navigation must use the effective primary: '.$template.' '.$url);
        $check(!str_contains($html, 'libre.securityops.co'), 'Retired Redlib instance must not be linked: '.$template);
        $check(!str_contains($html, '{%redlib_'), 'Redlib template placeholders must be resolved: '.$template);
    }
}
