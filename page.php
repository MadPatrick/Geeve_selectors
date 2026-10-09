<?php
$articles=[['A-1','Slang eerste','K1'],['B-2','Slang tweede','K2'],['A-3','Slang derde','Z9']];
?><!doctype html><meta charset=utf-8><link rel=stylesheet href="/shared/style.css"><link rel=stylesheet href="/klantartikel/assets/style.css">
<main class="page-shell page-shell--wide"><section class="panel">
<form id="kaForm" data-lookup="/lookup.php"><div id="kaMessage" class="ka-message ka-message--error" hidden></div>
<small id="kaCount"></small><button type=submit id=kaExport>x</button>
<table class="ka-table" id="kaTable"><thead><tr class="ka-filter-row"><th><input type="search" id="kaFilterItem" class="ka-input"></th><th></th><th><input type="search" id="kaFilterCode" class="ka-input"></th><th></th></tr><tr><th>a</th><th>b</th><th>c</th><th></th></tr></thead><tbody>
<?php foreach($articles as $a): ?><tr class="is-found"><td><input name="artikel[]" value="<?=$a[0]?>" class="ka-input ka-item"></td><td class="ka-desc"><?=$a[1]?></td><td><input name="klantartikel[]" value="<?=$a[2]?>" class="ka-input"></td><td><button type=button class="ka-remove">&times;</button></td></tr><?php endforeach; ?>
</tbody></table><button type=button id=kaAdd class="ka-add">+</button></form></section></main>
<script src="/klantartikel/assets/klantartikel.js"></script>
