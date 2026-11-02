<?php
// Kleine, zentrale UI-/Druckanpassungen ohne Eingriffe in die bestehende Einsatzlogik.
ob_start(function (string $html): string {
    $html = str_replace('>Bemerkungen</label>', '>Bemerkung/Lage</label>', $html);
    $html = str_replace('<h2>Bemerkungen</h2>', '<h2>Bemerkung/Lage</h2>', $html);

    // Fahrzeug-Symbole nur in Fahrzeugüberschriften entfernen; andere Icons der Anwendung bleiben erhalten.
    $html = preg_replace_callback('/(<h[23][^>]*>)(.*?)(<\/h[23]>)/is', function (array $m): string {
        $heading = str_replace(['🚒', '🚑', '🚓', '🚐', '🚗', '🚙', '🚛', '🚚', '🚘'], '', $m[2]);
        return $m[1].$heading.$m[3];
    }, $html) ?? $html;

    // Die bestehende Fahrzeugausgabe erzeugt bei mehreren Fahrzeugen verschachtelte <ul>/<div>-Strukturen.
    // Im Browser führt das zu einer immer stärkeren Einrückung (und zu unterschiedlichen Aufzählungszeichen).
    // Vor der Anzeige bzw. dem Druck werden die Fahrzeugblöcke deshalb einmal sauber auf derselben Ebene aufgebaut.
    $fixScript = <<<'HTML'
<script id="fwdesk-vehicle-layout-fix">
document.addEventListener('DOMContentLoaded', function () {
    var sections = document.querySelectorAll('.print-section');
    sections.forEach(function (section) {
        var title = section.querySelector(':scope > h2');
        if (!title || title.textContent.trim() !== 'Fahrzeuge und Besatzung') return;

        var headings = Array.prototype.slice.call(section.querySelectorAll('h3'));
        if (!headings.length) return;

        var blocks = [];
        headings.forEach(function (heading) {
            var list = heading.nextElementSibling;
            if (!list || list.tagName.toLowerCase() !== 'ul') return;

            // Fahrzeug-Symbol entfernen, falls es durch Browser/Cache noch vorhanden ist.
            heading.childNodes.forEach(function (node) {
                if (node.nodeType === Node.TEXT_NODE) {
                    node.nodeValue = node.nodeValue.replace(/^\s*[🚒🚑🚓🚐🚗🚙🚛🚚🚘]\s*/, '');
                }
            });

            var block = document.createElement('div');
            block.className = 'fwdesk-vehicle-block';
            block.appendChild(heading);
            block.appendChild(list);
            blocks.push(block);
        });

        if (!blocks.length) return;
        Array.prototype.slice.call(section.children).forEach(function (child) {
            if (child !== title) child.remove();
        });
        blocks.forEach(function (block) { section.appendChild(block); });
    });
});
</script>
HTML;
    $html = str_replace('</body>', $fixScript.'</body>', $html);

    $css = '<style id="fwdesk-print-fix">'
         . '.fwdesk-vehicle-block{margin:10px 0 0!important;padding:0 0 10px!important;border-bottom:1px solid #ddd!important;break-inside:avoid!important;page-break-inside:avoid!important;display:block!important}'
         . '.fwdesk-vehicle-block:last-child{border-bottom:0!important}'
         . '.fwdesk-vehicle-block>h3{margin:0 0 6px!important;padding:0!important;font-size:16px!important;line-height:1.3!important}'
         . '.fwdesk-vehicle-block>ul{margin:0 0 0 22px!important;padding:0 0 0 18px!important;list-style-type:disc!important}'
         . '.fwdesk-vehicle-block>ul>li{margin:0!important;padding:0!important;line-height:1.45!important}'
         . '@media print{\n'
         . '@page{size:A4;margin:12mm 12mm 20mm 12mm}\n'
         . 'html,body{width:auto!important;min-width:0!important;background:#fff!important}\n'
         . 'body{font-size:10.5pt!important;line-height:1.3!important}\n'
         . 'main{width:auto!important;max-width:none!important;margin:0!important;padding:0!important}\n'
         . '.card{margin:0!important;padding:0!important;border:0!important;box-shadow:none!important}\n'
         . '.print-footer{position:relative!important;left:auto!important;right:auto!important;bottom:auto!important;clear:both!important;width:100%!important;height:auto!important;box-sizing:border-box!important;margin:14mm 0 0!important;padding:4mm 0 0!important;border-top:1px solid #ddd!important;break-inside:avoid!important;page-break-inside:avoid!important}\n'
         . '.print-section{margin-top:8mm!important;padding-top:3mm!important;break-inside:auto!important;page-break-inside:auto!important}\n'
         . '.print-section>h2,.print-section>h3{break-after:avoid!important;page-break-after:avoid!important}\n'
         . '.print-section:has(.photo-grid){break-before:page!important;page-break-before:always!important;break-inside:auto!important;page-break-inside:auto!important;margin-top:0!important;padding-top:8mm!important}\n'
         . '.print-section:has(.photo-grid)>h2{break-after:avoid!important;page-break-after:avoid!important}\n'
         . '.fwdesk-vehicle-block{margin:7mm 0 0!important;padding:0 0 4mm!important;border-bottom:1px solid #ddd!important;break-inside:avoid!important;page-break-inside:avoid!important}\n'
         . '.fwdesk-vehicle-block>h3{margin:0 0 2mm!important;padding:0!important;font-size:11pt!important;break-after:avoid!important;page-break-after:avoid!important}\n'
         . '.fwdesk-vehicle-block>ul{margin:0 0 0 5mm!important;padding:0 0 0 6mm!important;break-before:avoid!important;page-break-before:avoid!important;list-style-type:disc!important}\n'
         . '.fwdesk-vehicle-block>ul>li{margin:0!important;padding:0!important}\n'
         . '.print-grid{grid-template-columns:repeat(2,minmax(0,1fr))!important;gap:2mm 8mm!important}\n'
         . '.person-print{break-inside:avoid!important;page-break-inside:avoid!important}\n'
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
    $responsiveCss = <<<'CSS'
<style id="fwdesk-responsive-ui">
@media screen {
    :root {
        --fwdesk-ink: #17212b;
        --fwdesk-muted: #566575;
        --fwdesk-border: #d7dfe7;
        --fwdesk-canvas: #edf2f6;
        --fwdesk-primary: #b4232b;
        --fwdesk-primary-hover: #941b23;
        --fwdesk-focus: #1769aa;
    }

    * { box-sizing: border-box; }
    body {
        min-width: 320px;
        background: var(--fwdesk-canvas);
        color: var(--fwdesk-ink);
        font-family: Inter, "Segoe UI", Roboto, Helvetica, Arial, sans-serif;
        font-size: 16px;
        line-height: 1.55;
        -webkit-font-smoothing: antialiased;
    }
    header {
        padding: 18px clamp(16px, 4vw, 40px);
        background: #17212b;
        box-shadow: 0 3px 14px #17212b26;
    }
    .topbar {
        max-width: 1320px;
        margin: 0 auto;
        white-space: normal;
        flex-wrap: wrap;
    }
    .brand { white-space: normal; }
    .brand strong { font-size: 1.15rem; letter-spacing: .01em; }
    .topnav { flex-wrap: wrap; gap: 8px; overflow: visible; }
    .topnav a {
        min-height: 44px;
        padding: 10px 14px;
        border-radius: 8px;
        font-weight: 650;
        transition: background-color .15s ease;
    }
    .topnav a:hover { background: #ffffff1a; }

    main {
        width: min(100% - 32px, 1200px);
        max-width: 1200px;
        margin: clamp(20px, 4vw, 36px) auto;
        padding: 0;
    }
    .card {
        margin-bottom: 20px;
        padding: clamp(20px, 3vw, 32px);
        border: 1px solid #e0e6ec;
        border-radius: 16px;
        background: #fff;
        box-shadow: 0 8px 26px #17212b0b;
    }
    h1, h2, h3 { color: var(--fwdesk-ink); line-height: 1.25; }
    h1 { margin: 0 0 22px; font-size: clamp(1.65rem, 3.2vw, 2.15rem); letter-spacing: -.025em; }
    h2 { margin: 28px 0 14px; font-size: clamp(1.2rem, 2.4vw, 1.45rem); }
    h3 { font-size: 1.05rem; }
    p { margin: .65em 0; }
    .muted { color: var(--fwdesk-muted); }
    label { margin: 14px 0 6px; font-weight: 650; }
    input, textarea, select {
        min-height: 44px;
        padding: 10px 12px;
        border: 1px solid #bdc9d5;
        border-radius: 9px;
        background: #fff;
        color: var(--fwdesk-ink);
        font: inherit;
        line-height: 1.35;
        transition: border-color .15s ease, box-shadow .15s ease;
    }
    textarea { min-height: 88px; resize: vertical; }
    input::placeholder, textarea::placeholder { color: #647487; opacity: 1; }
    input[type="checkbox"], input[type="radio"] {
        width: 20px;
        min-height: 20px;
        height: 20px;
        margin: 0 8px 0 0;
        padding: 0;
        accent-color: var(--fwdesk-primary);
        vertical-align: middle;
    }
    input[type="file"] { min-height: auto; padding: 9px; }
    input:disabled, textarea:disabled, select:disabled { background: #f0f3f6; }
    :where(a, button, input, textarea, select, [tabindex]):focus-visible {
        outline: 3px solid var(--fwdesk-focus);
        outline-offset: 2px;
    }
    input:focus-visible, textarea:focus-visible, select:focus-visible { border-color: var(--fwdesk-focus); }

    button, .btn {
        min-height: 44px;
        padding: 10px 16px;
        border: 1px solid var(--fwdesk-primary);
        border-radius: 9px;
        background: var(--fwdesk-primary);
        color: #fff;
        font: inherit;
        font-weight: 700;
        line-height: 1.25;
        text-align: center;
        cursor: pointer;
        transition: background-color .15s ease, border-color .15s ease, transform .15s ease;
    }
    button:hover, .btn:hover {
        border-color: var(--fwdesk-primary-hover);
        background: var(--fwdesk-primary-hover);
        color: #fff;
    }
    button:active, .btn:active { transform: translateY(1px); }
    button:disabled { cursor: not-allowed; opacity: .6; }
    button.danger, .btn.danger {
        border-color: #efc1c3;
        background: #fff1f1;
        color: #8d1c24;
    }
    button.danger:hover, .btn.danger:hover { border-color: #d78a8e; background: #ffe2e3; color: #78151c; }
    .actions { align-items: center; gap: 10px; }
    .actions > form { display: flex; align-items: center; gap: 8px; min-width: 0; }
    .actions > form input:not([type="hidden"]) { width: auto; min-width: 0; flex: 1 1 170px; }
    .actions a, .actions button { overflow-wrap: anywhere; }
    hr { margin: 22px 0; border: 0; border-top: 1px solid var(--fwdesk-border); }

    .grid, .master-grid, .person-grid { align-items: start; }
    .master-grid { grid-template-columns: repeat(auto-fit, minmax(min(100%, 20rem), 1fr)); gap: 20px; }
    .time-grid { gap: 14px; }
    .duration-value { min-height: 44px; border-color: var(--fwdesk-border); background: #f4f7fa; }
    .crew, .material-list, .person-block, .master-row {
        border-color: var(--fwdesk-border);
        border-radius: 12px;
        background: #fff;
    }
    .crew { padding: 16px; }
    .crew > .check { display: flex; align-items: center; min-height: 44px; margin: 0; }
    .crew .actions { margin: 8px 0; }
    .crewlist { display: flex; flex-wrap: wrap; gap: 4px 8px; padding-top: 8px; }
    .crewlist[hidden] { display: none !important; }
    .check { display: inline-flex; align-items: center; gap: 4px; min-height: 40px; margin: 4px 10px 4px 0; }
    .person-choice { padding: 4px 8px; border-radius: 8px; }
    .person-choice.disabled { opacity: .55; }
    .person-choice.divera-suggested { background: #fff3cc; }
    .va-option { display: inline-flex; align-items: center; gap: 4px; margin-left: 6px; }
    .va-option input { margin-right: 0; }
    .person-block { padding: 16px; }
    .person-block h3 { margin-top: 0; }
    .master-row { padding: 16px; overflow-wrap: anywhere; }
    .master-row > a { color: #173d61; font-weight: 700; text-decoration-thickness: 1px; text-underline-offset: 3px; }
    .inline { display: grid; grid-template-columns: repeat(2, minmax(0, 1fr)); gap: 14px; }
    .photo-grid { grid-template-columns: repeat(auto-fit, minmax(min(100%, 220px), 1fr)); }
    .photo-item { padding: 10px; border-color: var(--fwdesk-border); background: #f7f9fb; }
    .photo-item img { border-radius: 8px; }
    .divera-modal { padding: 16px; }
    .divera-modal-box { border: 1px solid var(--fwdesk-border); }
    .divera-response-row { min-height: 48px; }
    .divera-response-row input { flex: 0 0 20px; }
    .print-grid { grid-template-columns: repeat(2, minmax(0, 1fr)); gap: 4px 24px; }
    .print-section { border-color: var(--fwdesk-border); }
    .print-footer { color: var(--fwdesk-muted); }

    @media (max-width: 760px) {
        header { padding: 14px 16px; }
        .topbar { display: flex; align-items: flex-start; gap: 10px; }
        .topnav { width: 100%; gap: 6px; }
        .topnav a { flex: 1 1 auto; padding: 9px 10px; text-align: center; }
        main { width: min(100% - 24px, 1200px); margin: 16px auto 24px; }
        .card { padding: 20px; border-radius: 13px; }
        .grid { grid-template-columns: minmax(0, 1fr); }
        .time-grid { grid-template-columns: repeat(2, minmax(0, 1fr)); }
        .time-grid .duration-box { grid-column: 1 / -1; }
        .person-grid, .master-grid { grid-template-columns: minmax(0, 1fr); }
        .print-grid { grid-template-columns: minmax(0, 1fr); gap: 0 16px; }
        .actions { align-items: stretch; }
        .actions > a, .actions > button, .actions > form { flex: 1 1 100%; }
        .actions > form { flex-wrap: wrap; }
        .actions > form button { flex: 1 1 auto; }
        .actions > form input:not([type="hidden"]) { flex-basis: 100%; }
        .divera-modal { align-items: flex-end; padding: 10px; }
        .divera-modal-box { max-height: min(85vh, 720px); padding: 18px; border-radius: 16px 16px 10px 10px; }
        .divera-modal-head { align-items: flex-start; }
    }

    @media (max-width: 420px) {
        body { font-size: 15px; }
        .topnav a { padding-inline: 8px; font-size: .92rem; }
        main { width: calc(100% - 20px); }
        .card { padding: 16px; }
        .time-grid { gap: 10px; }
        .inline { grid-template-columns: minmax(0, 1fr); }
        .actions > a, .actions > button, .actions > form { flex-basis: 100%; }
        .divera-modal-box .actions { flex-direction: column; }
        .divera-modal-box .actions > button { width: 100%; }
    }

    @media (prefers-reduced-motion: reduce) {
        *, *::before, *::after { scroll-behavior: auto !important; transition-duration: .01ms !important; }
    }
}
</style>
CSS;
    return str_replace('</head>', $css.$responsiveCss.'</head>', $html);
});
