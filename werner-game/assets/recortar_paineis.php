<?php
/**
 * Recorta uma folha de sprites em quadros soltos, com fundo transparente.
 *
 * Versão em PHP do que os extract_*.py faziam — o PC do Rodrigo não tem Python
 * nem Pillow, e GD dá conta: as folhas do Gemini seguem sempre o mesmo padrão
 * (painéis de fundo azul-claro liso sobre um fundo azul-médio).
 *
 *   php recortar_paineis.php <folha.jpg> <prefixo> [altura-alvo]
 *
 * Como funciona:
 * 1. Painel = região conexa da cor clara dos painéis. O vão entre painéis é de
 *    outra cor, então os painéis saem separados sozinhos.
 * 2. Dentro de cada painel, o fundo vira transparente por preenchimento a
 *    partir das BORDAS — não por cor solta. Isso preserva qualquer parte clara
 *    do personagem (camisa, crachá) que por acaso tenha cor parecida.
 * 3. Recorta na caixa do que sobrou e, se pedido, redimensiona por altura.
 *
 * Painéis muito pequenos (os retratos do topo) são ignorados por padrão.
 */

$folha   = $argv[1] ?? null;
$prefixo = $argv[2] ?? null;
$alturaAlvo = isset($argv[3]) ? (int) $argv[3] : 0;
if (!$folha || !$prefixo) { fwrite(STDERR, "uso: php recortar_paineis.php <folha.jpg> <prefixo> [altura]\n"); exit(1); }

$im = imagecreatefromjpeg($folha);
$W = imagesx($im); $H = imagesy($im);

$px = function ($x, $y) use ($im) { $c = imagecolorat($im, $x, $y); return [($c >> 16) & 255, ($c >> 8) & 255, $c & 255]; };
$perto = function ($a, $b, $tol) { return abs($a[0]-$b[0]) <= $tol && abs($a[1]-$b[1]) <= $tol && abs($a[2]-$b[2]) <= $tol; };

// Cor do painel = a mais frequente da folha (o miolo claro domina a área).
$cont = [];
for ($y = 0; $y < $H; $y += 2) for ($x = 0; $x < $W; $x += 2) {
    [$r,$g,$b] = $px($x,$y);
    $k = (($r>>3)<<3).",".(($g>>3)<<3).",".(($b>>3)<<3);
    $cont[$k] = ($cont[$k] ?? 0) + 1;
}
arsort($cont);
$corPainel = array_map('intval', explode(',', array_key_first($cont)));

// 1. máscara do painel (tolerância alta: JPEG borra, e há grade fraca por cima)
$M = [];
for ($y = 0; $y < $H; $y++) for ($x = 0; $x < $W; $x++) {
    $M[$y][$x] = $perto($px($x,$y), $corPainel, 26) ? 1 : 0;
}

// 2. componentes conexos -> retângulos de painel
$vis = []; $paineis = [];
for ($y = 0; $y < $H; $y++) for ($x = 0; $x < $W; $x++) {
    if (!$M[$y][$x] || isset($vis[$y][$x])) continue;
    $fila = [[$x,$y]]; $vis[$y][$x] = 1; $n = 0;
    $x0=$x; $x1=$x; $y0=$y; $y1=$y;
    while ($fila) {
        [$cx,$cy] = array_pop($fila); $n++;
        if ($cx<$x0)$x0=$cx; if ($cx>$x1)$x1=$cx; if ($cy<$y0)$y0=$cy; if ($cy>$y1)$y1=$cy;
        foreach ([[1,0],[-1,0],[0,1],[0,-1]] as [$dx,$dy]) {
            $nx=$cx+$dx; $ny=$cy+$dy;
            if ($nx<0||$ny<0||$nx>=$W||$ny>=$H) continue;
            if (!$M[$ny][$nx] || isset($vis[$ny][$nx])) continue;
            $vis[$ny][$nx] = 1; $fila[] = [$nx,$ny];
        }
    }
    $lw = $x1-$x0+1; $lh = $y1-$y0+1;
    if ($lw < $W*0.07 || $lh < $H*0.12) continue;   // descarta retratos e sobras
    $paineis[] = ['x'=>$x0,'y'=>$y0,'w'=>$lw,'h'=>$lh,'n'=>$n];
}
// ordem de leitura: linha por linha, da esquerda pra direita
usort($paineis, function ($a, $b) {
    $linha = intdiv($a['y'], 120) <=> intdiv($b['y'], 120);
    return $linha ?: $a['x'] <=> $b['x'];
});

echo "folha $folha: ", $W, "x", $H, " | cor do painel rgb(", implode(',', $corPainel), ") | ", count($paineis), " painéis\n";

// 3. recorta cada painel, tira o fundo pelas bordas e salva
$feitos = [];
foreach ($paineis as $i => $p) {
    $cv = imagecreatetruecolor($p['w'], $p['h']);
    imagealphablending($cv, false); imagesavealpha($cv, true);
    imagecopy($cv, $im, 0, 0, $p['x'], $p['y'], $p['w'], $p['h']);

    // preenchimento a partir das bordas: só o fundo que ENCOSTA na borda cai
    $transp = imagecolorallocatealpha($cv, 0, 0, 0, 127);
    $fundo = []; $fila = [];
    $ehFundo = function ($x, $y) use ($cv, $perto, $corPainel) {
        $c = imagecolorat($cv, $x, $y);
        $r = [($c>>16)&255, ($c>>8)&255, $c&255];
        // fundo do painel OU a grade (um tom mais escura) OU o vão entre painéis
        return $perto($r, $corPainel, 30)
            || ($r[2] > $r[0] + 18 && $r[2] > 150 && $r[0] < 190);
    };
    for ($x = 0; $x < $p['w']; $x++) { foreach ([0, $p['h']-1] as $y) if ($ehFundo($x,$y) && !isset($fundo[$y][$x])) { $fundo[$y][$x]=1; $fila[]=[$x,$y]; } }
    for ($y = 0; $y < $p['h']; $y++) { foreach ([0, $p['w']-1] as $x) if ($ehFundo($x,$y) && !isset($fundo[$y][$x])) { $fundo[$y][$x]=1; $fila[]=[$x,$y]; } }
    while ($fila) {
        [$cx,$cy] = array_pop($fila);
        foreach ([[1,0],[-1,0],[0,1],[0,-1]] as [$dx,$dy]) {
            $nx=$cx+$dx; $ny=$cy+$dy;
            if ($nx<0||$ny<0||$nx>=$p['w']||$ny>=$p['h']) continue;
            if (isset($fundo[$ny][$nx]) || !$ehFundo($nx,$ny)) continue;
            $fundo[$ny][$nx]=1; $fila[]=[$nx,$ny];
        }
    }
    foreach ($fundo as $y => $linha) foreach ($linha as $x => $v) imagesetpixel($cv, $x, $y, $transp);

    // caixa do que sobrou
    $mnx=1e9;$mny=1e9;$mxx=-1;$mxy=-1;
    for ($y=0;$y<$p['h'];$y++) for ($x=0;$x<$p['w'];$x++) {
        if ((imagecolorat($cv,$x,$y) >> 24) & 0x7F) continue;
        if ($x<$mnx)$mnx=$x; if ($x>$mxx)$mxx=$x; if ($y<$mny)$mny=$y; if ($y>$mxy)$mxy=$y;
    }
    if ($mxx < 0) continue;
    $cw=$mxx-$mnx+1; $ch=$mxy-$mny+1;
    if ($cw < 20 || $ch < 40) continue;

    $alvo = $alturaAlvo ?: $ch;
    $esc = $alvo / $ch;
    $fim = imagecreatetruecolor((int)round($cw*$esc), $alvo);
    imagealphablending($fim, false); imagesavealpha($fim, true);
    imagefilledrectangle($fim, 0, 0, imagesx($fim), imagesy($fim), imagecolorallocatealpha($fim,0,0,0,127));
    imagealphablending($fim, true);
    imagecopyresampled($fim, $cv, 0, 0, $mnx, $mny, imagesx($fim), $alvo, $cw, $ch);
    imagealphablending($fim, false);

    $nome = sprintf('%s%02d.png', $prefixo, $i);
    imagepng($fim, $nome);
    $feitos[] = [$nome, imagesx($fim), imagesy($fim), $p['x'], $p['y']];
    printf("  %-18s %3dx%-3d  (painel em %d,%d %dx%d)\n", $nome, imagesx($fim), imagesy($fim), $p['x'], $p['y'], $p['w'], $p['h']);
}
echo count($feitos), " quadros salvos\n";
