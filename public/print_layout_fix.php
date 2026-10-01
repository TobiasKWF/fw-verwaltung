<?php
// Kleine, zentrale UI-/Druckanpassungen ohne Eingriffe in die bestehende Einsatzlogik.
ob_start(function (string $html): string {
    $html = str_replace('>Bemerkungen</label>', '>Bemerkung/Lage</label>', $html);
    $html = str_replace('<h2>Bemerkungen</h2>', '<h2>Bemerkung/Lage</h2>', $html);

    $css = '<style id="fwdesk-print-fix">@media print{\n'
         . '@page{size:A4;margin:12mm 12mm 16mm 12mm}\n'
         . 'html,body{width:auto!important;min-width:0!important;background:#fff!important}\n'
         . 'body{font-size:10.5pt!important;line-height:1.3!important}\n'
         . 'main{width:auto!important;max-width:none!important;margin:0!important;padding:0!important}\n'
         . '.card{margin:0!important;padding:0!important;border:0!important;box-shadow:none!important}\n'
         . '.print-footer{position:static!important;left:auto!important;right:auto!important;bottom:auto!important;width:100%!important;margin-top:12mm!important;padding:4mm 0 0!important;border-top:1px solid #ddd!important}\n'
         . '.print-section{margin-top:10mm!important;padding-top:4mm!important;break-inside:auto!important}\n'
         . '.print-section>h2,.print-section>h3{break-after:avoid!important}\n'
         . '.print-grid{grid-template-columns:repeat(2,minmax(0,1fr))!important;gap:2mm 8mm!important}\n'
         . '.person-print{break-inside:avoid!important}\n'
         . '.photo-grid{grid-template-columns:repeat(2,minmax(0,1fr))!important;gap:5mm!important}\n'
         . '.photo-item{break-inside:avoid!important;page-break-inside:avoid!important;padding:2mm!important}\n'
         . '.photo-item img{width:100%!important;max-width:100%!important;max-height:95mm!important;object-fit:contain!important}\n'
         . '.photo-item form,.actions{display:none!important}\n'
         . 'p,li{overflow-wrap:anywhere!important}\n'
         . 'ul{margin-top:2mm!important}\n'
         . 'h1{font-size:18pt!important;margin:0 0 5mm!important}\n'
         . 'h2{font-size:13pt!important}\n'
         . 'h3{font-size:11pt!important}\n'
         . '}</style>';
    return str_replace('</head>', $css.'</head>', $html);
});
