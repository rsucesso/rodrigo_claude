# Werner: Fúria Verde — memória do projeto

Jogo beat 'em up (estilo Final Fight / Streets of Rage) feito de presente para os **60 anos do
Werner Grau** (advogado ambientalista, sócio do Pinheiro Neto, comediante de stand-up, joga rugby,
mantém um abrigo com centenas de cães). Presente do Rodrigo. Roda no celular, na horizontal.

## Onde está cada coisa

- **Jogo inteiro**: `werner-game/index.html` — arquivo único (~1,4 MB), sprites embutidos em base64.
  Abre direto no navegador, sem build.
- **Folhas de sprite originais** (geradas pelo Rodrigo no Gemini) e scripts de recorte:
  `werner-game/assets/` (`*.jpg`, `extract_*.py`, `build_sprites.py`).
- **Branch de trabalho**: `claude/werner-grau-streets-of-rage-game-17qoms` (repo `rsucesso/rodrigo_claude`;
  não há `main`, por isso não há PR).
- **Prévia online (privada)**: https://claude.ai/artifact/GzAPpLLVqdyAvLhS3SMEc1 — republicar com o
  Artifact tool a partir de uma cópia do `index.html` sem `<!doctype>/<html>/<head>/<body>`.
- **Produção**: https://intellivent.com.br/wg/ — o arquivo também está no repo `rsucesso/intellivent`,
  branch `claude/werner-game-wg`, em `app/wg/index.html`.

## Deploy (Hostinger)

Feito **por SSH a partir do PC do Rodrigo** (chaves em `G:\Meu Drive\CLAUDE\Projetos\secrets`).
Sessões na nuvem não têm a chave nem acesso de rede ao domínio. Na pasta do intellivent:

```bash
git fetch origin && git checkout origin/claude/werner-game-wg -- app/wg/index.html
bash "/g/Meu Drive/CLAUDE/Projetos/scripts/intellivent-deploy.sh" wg/index.html
```

Se o script reclamar do `php -l` por ser `.html`, subir pelo Gerenciador de Arquivos em
`domains/intellivent.com.br/public_html/wg/`. **Não usar FTP** (o host recusa com 450).
Status em 2026-10-05: **ainda não confirmado no ar** — conferir se /wg/ abre.

Ao atualizar o jogo: copiar `werner-game/index.html` para `app/wg/index.html` do intellivent,
commitar na branch e repetir o deploy.

## Conteúdo atual do jogo

- **Só o Werner é jogável** (o Rodrigo pediu para NÃO ter segundo personagem).
- Werner: sprites recortados da folha (terno azul com colete, sem paletó; careca; referência
  visual Bruce Willis). Combo soco-soco-chute, voadora (pulo+soco), especial **LEI** (Lei 9.605).
- Fases: 1 **Rua dos Vira-Latas** (de dia) → chefão Punk Pugilista; 2 **Clube de Stand-up** →
  Werner Falso e Agente Supremo; 3 **Campo de Rugby** → Ex-Jogador de Rugby e **Barão da Motosserra**
  (vilão final, roubou o bolo).
- Capangas: Agente Infiltrado (terno preto e terno azul). Mulheres a proteger (10 variações) e
  cães a soltar (4 raças: vira-lata preto, pit bull, dálmata, vira-lata caramelo) — todos sprites.
- Final: festa no "Abrigo do Werner", bolo de 60, "Parabéns pra Você" em chiptune, dedicatória
  editável no topo do script (`DEDICATORIA`, `ASSINATURA`, `ABRIGO_BASE`).
- Humor: frases jurídicas reais ao derrotar vilões (Art. 32 e 2º da Lei 9.605, Art. 299, etc.).

## Pendências / próximos passos

1. Confirmar o deploy em intellivent.com.br/wg/.
2. **Cenários**: hoje são desenhados por código, abaixo do nível dos sprites. O Rodrigo vai gerar
   no Gemini: panorâmicas 3:1, emenda horizontal perfeita, chão plano no terço de baixo (linha do
   chão a 65% da altura), sem personagens. Integrar como fundo repetido com parallax.
3. Opcional: folha de itens (pão de queijo, coxinha, bolo, livro da lei) e árvores em sprite;
   chapéus de festa dos cães na tela final às vezes ficam deslocados.

## Como integrar uma folha de sprites nova

Padrão que funciona: painéis com fundo liso (azul-claro ou magenta), um personagem por painel,
mesma escala, olhando para a direita, sem texto nem efeitos dentro do painel.
1. Detectar painéis (`auto.py`/`extract_sheets.py`: flood-fill do fundo + componentes conexos).
2. Conferir os quadros num mosaico (montage) antes de usar.
3. Adicionar ao `build_sprites.py` (prefixo, escala-alvo em px de altura, âncora `ax`), rodar,
   e trocar o bloco `const SPRITE_DATA={...}` / `SPRITE_META` no `index.html`.
4. Inimigo com sprite: entrada em `DEF` com `sprite:'prefixo_'` e `anim` (ANIM_AG/GYM/RUG/PUNK).
5. Testar com Playwright (Chromium em `/opt/pw-browsers`), modo celular 844×390: jogar as 3 fases
   até a tela final (`window.__G` tem `skipTo`, `jump`, `killAll`) e checar erros no console.

## Como o Rodrigo trabalha

Mensagens curtas em rajada, pelo celular, em português. Manda folhas de sprite durante o trabalho.
Quer o arquivo `index.html` reenviado a cada versão. Perguntar nas bifurcações visíveis ao Werner;
não assumir o óbvio.
