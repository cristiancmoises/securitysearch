<?php
require_once __DIR__.'/tranco.php';
/** Onion address already published in this project's service directory.
 * A link advertises an address, not a successful reachability test. */
function securitysearch_footer(): string {
    $onion='http://secopsi47k4idy5pw3wrm2zkmn3d5ghaad3s7ulnrze3h53sbzzg5gad.onion/';
    $row=securitysearch_tranco::cached();
    $rank='Tranco · unavailable';$date='';
    if ($row!==null) {
        $rank=$row['rank']===null ? 'Tranco · not in returned lists' : 'Tranco #'.number_format($row['rank']);
        $date=$row['rank']===null ? 'Checked '.gmdate('Y-m-d',$row['fetched_at']) : 'List '.$row['date'];
    }
    $e=static fn($s)=>htmlspecialchars($s,ENT_QUOTES|ENT_SUBSTITUTE,'UTF-8');
    return '<div class="trust-footer" role="group" aria-label="Site information">'.
        '<a class="footer-onion" href="'.$onion.'" rel="noreferrer noopener" title="Open our onion address in Tor Browser">Onion · Tor</a>'.
        '<p class="footer-motto">In Code We Trust.</p>'.
        '<a class="footer-rank" href="https://tranco-list.eu/" rel="noreferrer noopener" title="Domain popularity list; not a search-quality rating">'.$e($rank).($date!=='' ? '<small>'.$e($date).'</small>' : '').'</a></div>';
}
