<?php
/** Fail explicitly before fixtures if a mandatory native dependency is missing. */
$missing=[];
foreach (['curl','dom','xml','mbstring','apcu','sodium','fileinfo','imagick'] as $extension) if (!extension_loaded($extension)) $missing[]=$extension;
if ($missing) {fwrite(STDERR,'NATIVE_RUNTIME_MISSING: '.implode(', ',$missing).PHP_EOL);exit(2);}
echo "Native PHP audit extensions present. No external requests performed.\n";
