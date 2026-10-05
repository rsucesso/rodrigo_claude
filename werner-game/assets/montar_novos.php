<?php
/**
 * Pega os quadros recortados em assets/novos/ e entrega o que o index.html
 * precisa: SPRITE_DATA (PNG em base64) e SPRITE_META (w, h, ax, s) com os
 * nomes que ANIM_AG espera.
 *
 * Decisões que importam:
 * - Todos os quadros de um personagem são escalados pelo MESMO fator, tirado da
 *   altura do stance (240px, igual aos sprites que já existem). Escalar quadro a
 *   quadro para a mesma altura deformaria o personagem (o caído é deitado).
 * - `ax` é o ponto de contato com o chão, não o centro da imagem: calculado como
 *   o centro horizontal dos pixels opacos das últimas linhas. Os sprites atuais
 *   fazem isso (ag_walk1 tem ax=98.6 numa imagem de 136 de largura, porque o
 *   personagem está inclinado).
 * - PNG é convertido para PALETA. As folhas vêm de JPEG, cheias de ruído de
 *   compressão; em truecolor cada quadro ficaria 3 a 4 vezes maior, e o jogo é
 *   um arquivo só que carrega inteiro no celular.
 */

$dir = __DIR__ . '/novos';
$saida = $dir . '/prontos';
@mkdir($saida);

// de que painel sai cada papel, por personagem
$mapa = [
    'tc_' => ['stance'=>0,'walk1'=>1,'walk2'=>2,'walk3'=>3,'victory'=>4,'attack'=>5,'hurt'=>6,'hurt2'=>7,'down'=>8],
    // repórter não tem 2ª pose de dor na folha: o ANIM_RP aponta hurt2 pro
    // mesmo quadro, em vez de guardar a imagem duas vezes no arquivo.
    'rp_' => ['stance'=>0,'walk1'=>1,'walk2'=>2,'walk3'=>3,'victory'=>5,'attack'=>6,'hurt'=>7,'down'=>8],
    'rpc_'=> ['stance'=>0,'walk1'=>1,'walk2'=>2,'walk3'=>3,'victory'=>5,'attack'=>6,'hurt'=>7,'down'=>8],
];
// Cada quadro vai para uma altura FIXA, não para um fator comum: é o que os
// sprites atuais fazem (todos os ag_ em pé têm exatamente 240 de altura). O
// desenho do stance vem maior na folha, então usar o fator dele encolheria o
// personagem ao andar.
// 180 em vez dos 240 dos sprites antigos: na tela o capanga tem ~93px de
// altura, então 240 era o dobro do necessário e custava KB à toa. A escala de
// desenho sobe junto, pro tamanho final na tela ficar idêntico ao do agente.
const ALTURA_EM_PE  = 180;
const ALTURA_CAIDO  = 77;
const ESCALA_TELA   = 0.5187; // 0.389 * 240/180
const ESCALA_CAIDO  = 0.44;   // 0.33 * 240/180

$data = []; $meta = []; $bytes = 0;

foreach ($mapa as $prefixo => $papeis) {
    $jaFeito = [];
    foreach ($papeis as $papel => $n) {
        $origem = sprintf('%s/%s%02d.png', $dir, $prefixo, $n);
        $im = imagecreatefrompng($origem);
        $alvoH = ($papel === 'down') ? ALTURA_CAIDO : ALTURA_EM_PE;
        $fator = $alvoH / imagesy($im);
        $w = (int) round(imagesx($im) * $fator);
        $h = $alvoH;

        $cv = imagecreatetruecolor($w, $h);
        imagealphablending($cv, false); imagesavealpha($cv, true);
        imagefilledrectangle($cv, 0, 0, $w, $h, imagecolorallocatealpha($cv, 0, 0, 0, 127));
        imagealphablending($cv, true);
        imagecopyresampled($cv, $im, 0, 0, 0, 0, $w, $h, imagesx($im), imagesy($im));
        imagealphablending($cv, false);

        // ponto de contato: centro horizontal das últimas linhas com pixel opaco
        $ax = $w / 2; $achou = false;
        for ($y = $h - 1; $y >= max(0, $h - 12) && !$achou; $y--) {
            $xs = [];
            for ($x = 0; $x < $w; $x++) if (((imagecolorat($cv, $x, $y) >> 24) & 0x7F) < 60) $xs[] = $x;
            if (count($xs) >= 3) { $ax = array_sum($xs) / count($xs); $achou = true; }
        }

        // PNG de PALETA com transparência, igual aos sprites que já existem —
        // truecolor com alfa deixaria os 27 quadros em 780 KB de base64, e o
        // jogo inteiro tem que caber num arquivo só que o celular baixa.
        //
        // imagetruecolortopalette() sozinha DESCARTA o alfa: o fundo volta
        // opaco e o sprite sai com um bloco azul atrás. Então guarda a máscara
        // antes, converte, e repinta o fundo num índice reservado marcado como
        // transparente.
        // Limpa rótulo ANTES de virar paleta: depois da conversão o
        // imagecolorat devolve o ÍNDICE da cor, não o ARGB, e qualquer teste de
        // transparência passa a mentir (foi o que me enganou na 1ª tentativa).
        $tirados = limparRotulos($cv, $w, $h);

        $mascara = [];
        for ($y = 0; $y < $h; $y++) for ($x = 0; $x < $w; $x++)
            $mascara[$y][$x] = ((imagecolorat($cv, $x, $y) >> 24) & 0x7F) > 64;

        imagetruecolortopalette($cv, false, 96);
        $vazio = imagecolorallocate($cv, 255, 0, 255);   // magenta: não existe na arte
        imagecolortransparent($cv, $vazio);
        for ($y = 0; $y < $h; $y++) for ($x = 0; $x < $w; $x++)
            if ($mascara[$y][$x]) imagesetpixel($cv, $x, $y, $vazio);

        $nome = $prefixo . $papel;
        $arq = "$saida/$nome.png";
        imagepng($cv, $arq, 9);
        $png = file_get_contents($arq);
        $bytes += strlen($png);

        $data[$nome] = base64_encode($png);
        $meta[$nome] = ['w'=>$w, 'h'=>$h, 'ax'=>round($ax, 1), 's'=>($papel==='down'?ESCALA_CAIDO:ESCALA_TELA)];
        printf("  %-14s %3dx%-3d ax %5.1f  %6d bytes%s\n", $nome, $w, $h, $ax, strlen($png),
            $tirados ? "  (tirei {$tirados}px de rótulo)" : '');
        $jaFeito[$n] = $papel;
    }
}

file_put_contents("$saida/data.json", json_encode($data));
file_put_contents("$saida/meta.json", json_encode($meta, JSON_PRETTY_PRINT));
printf("\n%d quadros | %.0f KB em PNG | %.0f KB em base64\n", count($data), $bytes/1024, $bytes*4/3/1024);

/**
 * Tira rótulo da folha que caiu dentro do painel ("block", "knocked down").
 * Regra: componente solto, pequeno e claro demais pra ser parte do personagem.
 * O taco do agente encosta na mão (mesmo componente) e a estrelinha do caído é
 * amarela, não branca — os dois sobrevivem.
 */
function limparRotulos($cv, $w, $h) {
    $op = function ($x, $y) use ($cv) { return ((imagecolorat($cv, $x, $y) >> 24) & 0x7F) <= 64; };
    $vis = []; $comps = [];
    for ($y = 0; $y < $h; $y++) for ($x = 0; $x < $w; $x++) {
        if (!$op($x, $y) || isset($vis[$y][$x])) continue;
        $fila = [[$x, $y]]; $vis[$y][$x] = 1; $px = []; $soma = 0;
        while ($fila) {
            [$cx, $cy] = array_pop($fila); $px[] = [$cx, $cy];
            $c = imagecolorat($cv, $cx, $cy);
            $soma += ((($c >> 16) & 255) + (($c >> 8) & 255) + ($c & 255)) / 3;
            for ($dy = -1; $dy <= 1; $dy++) for ($dx = -1; $dx <= 1; $dx++) {
                $nx = $cx + $dx; $ny = $cy + $dy;
                if ($nx < 0 || $ny < 0 || $nx >= $w || $ny >= $h) continue;
                if (!$op($nx, $ny) || isset($vis[$ny][$nx])) continue;
                $vis[$ny][$nx] = 1; $fila[] = [$nx, $ny];
            }
        }
        $ys = array_column($px, 1);
        $comps[] = ['px' => $px, 'n' => count($px), 'y0' => min($ys), 'y1' => max($ys)];
    }
    if (count($comps) < 2) return 0;
    usort($comps, fn($a, $b) => $b['n'] <=> $a['n']);
    $maior = $comps[0]['n'];
    $transp = imagecolortransparent($cv);
    $tirados = 0;
    $topoDoCorpo = $comps[0]['y0'];
    foreach (array_slice($comps, 1) as $c) {
        if ($c['n'] > $maior * 0.05) continue;        // grande demais: é parte da arte
        // Só cai o que está INTEIRAMENTE acima do personagem — é onde a folha
        // escreve o nome da pose. A estrelinha do caído fica na altura do corpo
        // e sobrevive; o taco do agente nem é componente separado, encosta na mão.
        if ($c['y1'] >= $topoDoCorpo) continue;
        foreach ($c['px'] as [$x, $y]) imagesetpixel($cv, $x, $y, $transp);
        $tirados += $c['n'];
    }
    return $tirados;
}
