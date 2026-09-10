<?php
/** Only bundled styles are inlined. No CSS, URL, theme or HTML from a visitor. */
final class home_styles {
    private const FILES = ['base'=>'home-base.css','controls'=>'home-controls.css','black'=>'home-black.css'];
    public static function inline(string $name): string {
        if (!isset(self::FILES[$name])) throw new InvalidArgumentException('Unknown homepage stylesheet.');
        $path=dirname(__DIR__).'/static/'.self::FILES[$name];
        if (!is_file($path) || is_link($path)) return '';
        $stat=stat($path);
        if ($stat===false || $stat['size']<1 || $stat['size']>24576) return '';
        $key='securitysearch-home-css-v1-'.hash('sha256',$path.':'.config::VERSION.':'.$stat['mtime'].':'.$stat['size']);
        $shared=function_exists('apcu_enabled') && apcu_enabled();
        $css=$shared ? apcu_fetch($key) : false;
        if (!is_string($css)) {
            $css=file_get_contents($path);
            if (!is_string($css) || str_contains(strtolower($css),'</style') || stripos($css,'@import')!==false) return '';
            if ($shared) apcu_store($key,$css,300);
        }
        return '<style data-home-style="'.$name.'">'.$css.'</style>';
    }
}
