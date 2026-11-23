<?php
declare(strict_types=1);

/**
 * Sistema de Ícones Personalizados — FOLIVRO
 *
 * Contém os 15 ícones autorais da identidade visual do FOLIVRO em SVG vetorial,
 * integrados dinamicamente ao sistema de temas via variáveis CSS:
 *   --icone-circulo-fundo   (fundo circular escuro do tema ativo)
 *   --icone-circulo-borda   (borda sutil de contraste em fundos escuros)
 *   --icone-primaria        (cor principal do tema ativo)
 *   --icone-acento          (cor de destaque/ação do tema ativo)
 *   --icone-acento-claro    (tom vibrante de destaque do tema ativo)
 *   --icone-traco-escuro    (traço escuro principal do tema ativo)
 *   --icone-papel           (branco das páginas internas)
 *   --icone-sombra-papel    (cinza das camadas secundárias de página)
 */

if (!function_exists('folivro_icones_definicoes')) {
    /**
     * Retorna o mapa com os 15 ícones personalizados (conteúdo interno de viewBox="0 0 100 100").
     * @return array<string, array{titulo:string, uso:string, svg:string}>
     */
    function folivro_icones_definicoes(): array
    {
        return [
            // 1. (Linha 1, Col 1) Etiqueta / Tag literária com letra F
            'etiqueta-f' => [
                'titulo' => 'Selo Editorial FOLIVRO',
                'uso' => 'Tags de obra original, selos, vendas e categorias',
                'svg' => '
                    <path d="M23 26 L33 13 Q50 7 67 13 L77 26 V90 H23 Z" fill="var(--icone-traco-escuro, #230907)"/>
                    <rect x="29" y="26" width="42" height="58" fill="var(--icone-papel, #ffffff)"/>
                    <path d="M35 31 H65 V42 H49 V49 H62 V59 H49 V79 H35 Z" fill="var(--icone-circulo-fundo, #230907)"/>
                ',
            ],

            // 2. (Linha 1, Col 2) Livro fechado em linhas com F na capa e base de páginas
            'livro-fechado-f' => [
                'titulo' => 'Livro Fechado FOLIVRO',
                'uso' => 'Ficha do livro, cadastro, login e obras fechadas',
                'svg' => '
                    <rect x="22" y="13" width="56" height="60" fill="var(--icone-papel, #ffffff)" stroke="var(--icone-traco-escuro, #230907)" stroke-width="4.8"/>
                    <path d="M31 22 H70 M31 20 V63 M31 44 H70" fill="none" stroke="var(--icone-traco-escuro, #230907)" stroke-width="5" stroke-linecap="square"/>
                    <rect x="22" y="79" width="56" height="12" fill="var(--icone-papel, #ffffff)" stroke="var(--icone-traco-escuro, #230907)" stroke-width="4.5"/>
                ',
            ],

            // 3. (Linha 1, Col 3) Círculo com página inclinada e marcador triangular apontando para cima
            'pagina-marcador' => [
                'titulo' => 'Página com Marcador de Progresso',
                'uso' => 'Perfil do leitor, progresso de leitura, continuar lendo e marcação de página',
                'svg' => '
                    <circle cx="50" cy="50" r="46" fill="var(--icone-circulo-fundo, #230907)" stroke="var(--icone-circulo-borda, transparent)" stroke-width="2.5"/>
                    <polygon points="27,27 66,18 74,67 34,72" fill="var(--icone-sombra-papel, #b8b3b0)"/>
                    <polygon points="25,27 67,22 77,72 35,77" fill="var(--icone-papel, #ffffff)"/>
                    <polygon points="41,76 53,44 66,73 53,67" fill="var(--icone-circulo-fundo, #230907)"/>
                ',
            ],

            // 4. (Linha 2, Col 1) Círculo com letra F em formato de fita marcadora de página
            'emblema-f' => [
                'titulo' => 'Emblema FOLIVRO',
                'uso' => 'Logotipo principal, favoritos e identidade da plataforma',
                'svg' => '
                    <circle cx="50" cy="50" r="46" fill="var(--icone-circulo-fundo, #230907)" stroke="var(--icone-circulo-borda, transparent)" stroke-width="2.5"/>
                    <path d="M35 23 H66 V37 H50 V44 H65 V58 H50 V79 L42.5 71.5 L35 79 Z" fill="var(--icone-papel, #ffffff)"/>
                ',
            ],

            // 5. (Linha 2, Col 2) Círculo com página aberta à esquerda e 4 barras formando F à direita
            'livro-indice-f' => [
                'titulo' => 'Índice de Capítulos e Obras',
                'uso' => 'Minhas obras, lista de capítulos e sumário',
                'svg' => '
                    <circle cx="50" cy="50" r="46" fill="var(--icone-circulo-fundo, #230907)" stroke="var(--icone-circulo-borda, transparent)" stroke-width="2.5"/>
                    <path d="M20 30 Q34 24 46 37 V73 Q34 61 20 63 Z" fill="var(--icone-papel, #ffffff)"/>
                    <polygon points="40,67 46,37 49,77" fill="var(--icone-sombra-papel, #b8b3b0)"/>
                    <rect x="50" y="36" width="10" height="8.5" rx="4" fill="var(--icone-sombra-papel, #b8b3b0)"/>
                    <rect x="54" y="36" width="26" height="8.5" rx="4.2" fill="var(--icone-papel, #ffffff)"/>
                    <rect x="50" y="47.5" width="9" height="8" rx="4" fill="var(--icone-sombra-papel, #b8b3b0)"/>
                    <rect x="54" y="47.5" width="12" height="8" rx="4" fill="var(--icone-papel, #ffffff)"/>
                    <rect x="50" y="58" width="10" height="8.5" rx="4" fill="var(--icone-sombra-papel, #b8b3b0)"/>
                    <rect x="54" y="58" width="19" height="8.5" rx="4.2" fill="var(--icone-papel, #ffffff)"/>
                    <rect x="50" y="69" width="9" height="8" rx="4" fill="var(--icone-sombra-papel, #b8b3b0)"/>
                    <rect x="54" y="69" width="12" height="8" rx="4" fill="var(--icone-papel, #ffffff)"/>
                ',
            ],

            // 6. (Linha 2, Col 3) Círculo com livro abrindo em perfil lateral
            'livro-abrindo' => [
                'titulo' => 'Livro Abrindo para Leitura',
                'uso' => 'Iniciar leitura e leitor de capítulos',
                'svg' => '
                    <circle cx="50" cy="50" r="46" fill="var(--icone-circulo-fundo, #230907)" stroke="var(--icone-circulo-borda, transparent)" stroke-width="2.5"/>
                    <path d="M19 30 L45 64 L48 61 L23 27 Z" fill="var(--icone-papel, #ffffff)"/>
                    <path d="M26 25 Q39 35 49 58 L46 60 Q36 38 23 28 Z" fill="var(--icone-sombra-papel, #b8b3b0)"/>
                    <path d="M49 59 Q63 50 80 57 L81 61 Q63 55 49 62 Z" fill="var(--icone-papel, #ffffff)"/>
                    <rect x="49" y="64" width="32" height="4.8" rx="1.6" fill="var(--icone-papel, #ffffff)"/>
                ',
            ],

            // 7. (Linha 3, Col 1) Círculo com folha inclinada e 4 barras escuras formando F
            'folha-lista-f' => [
                'titulo' => 'Folha de Resenhas, Chat e Anotações',
                'uso' => 'Chat literário, resenhas, críticas, avaliações e ficha técnica',
                'svg' => '
                    <circle cx="50" cy="50" r="46" fill="var(--icone-circulo-fundo, #230907)" stroke="var(--icone-circulo-borda, transparent)" stroke-width="2.5"/>
                    <polygon points="28,27 66,18 73,66 34,71" fill="var(--icone-sombra-papel, #b8b3b0)"/>
                    <polygon points="26,27 68,22 77,72 35,77" fill="var(--icone-papel, #ffffff)"/>
                    <g transform="rotate(-6 50 50)">
                        <rect x="35" y="30" width="27" height="8.2" rx="4" fill="var(--icone-circulo-fundo, #230907)"/>
                        <rect x="35" y="41" width="13.5" height="7.8" rx="3.8" fill="var(--icone-circulo-fundo, #230907)"/>
                        <rect x="35" y="51.5" width="24" height="8.2" rx="4" fill="var(--icone-circulo-fundo, #230907)"/>
                        <rect x="35" y="62" width="13.5" height="7.8" rx="3.8" fill="var(--icone-circulo-fundo, #230907)"/>
                    </g>
                ',
            ],

            // 8. (Linha 3, Col 2) Círculo com folha inclinada e 4 barras em degradê do tema formando F
            'folha-metas-f' => [
                'titulo' => 'Folha de Metas e Estatísticas',
                'uso' => 'Metas anuais, estatísticas e indicadores de desempenho',
                'svg' => '
                    <circle cx="50" cy="50" r="46" fill="var(--icone-circulo-fundo, #230907)" stroke="var(--icone-circulo-borda, transparent)" stroke-width="2.5"/>
                    <polygon points="28,27 66,18 73,66 34,71" fill="var(--icone-sombra-papel, #b8b3b0)"/>
                    <polygon points="26,27 68,22 77,72 35,77" fill="var(--icone-papel, #ffffff)"/>
                    <g transform="rotate(-6 50 50)">
                        <rect x="35" y="30" width="27" height="8.2" rx="4" fill="var(--icone-circulo-fundo, #230907)"/>
                        <rect x="35" y="41" width="13.5" height="7.8" rx="3.8" fill="var(--icone-primaria, #5c0000)"/>
                        <rect x="35" y="51.5" width="24" height="8.2" rx="4" fill="var(--icone-acento, #9e1b12)"/>
                        <rect x="35" y="62" width="13.5" height="7.8" rx="3.8" fill="var(--icone-acento-claro, #d92316)"/>
                    </g>
                ',
            ],

            // 9. (Linha 3, Col 3) Círculo com livro aberto frontal e marcador flutuante
            'livro-aberto-seta' => [
                'titulo' => 'Leitura Imersiva e Modo Foco',
                'uso' => 'Modo foco, modo silencioso e leitura ativa',
                'svg' => '
                    <circle cx="50" cy="50" r="46" fill="var(--icone-circulo-fundo, #230907)" stroke="var(--icone-circulo-borda, transparent)" stroke-width="2.5"/>
                    <polygon points="35,33 45,20 48,37 42,33" fill="var(--icone-papel, #ffffff)"/>
                    <path d="M15 58 Q32 50 48 58 L48 53.5 Q32 44.5 17 53 Z" fill="var(--icone-papel, #ffffff)"/>
                    <path d="M52 58 Q68 50 85 58 L83 53 Q68 44.5 52 53.5 Z" fill="var(--icone-papel, #ffffff)"/>
                    <rect x="15" y="62" width="33" height="4.8" rx="1.6" fill="var(--icone-papel, #ffffff)"/>
                    <rect x="52" y="62" width="33" height="4.8" rx="1.6" fill="var(--icone-papel, #ffffff)"/>
                ',
            ],

            // 10. (Linha 4, Col 1) Círculo com páginas abertas em movimento formando F
            'folhas-voando-f' => [
                'titulo' => 'Explorar e Buscar Obras',
                'uso' => 'Busca no catálogo, exploração de categorias e livros populares',
                'svg' => '
                    <circle cx="50" cy="50" r="46" fill="var(--icone-circulo-fundo, #230907)" stroke="var(--icone-circulo-borda, transparent)" stroke-width="2.5"/>
                    <path d="M9 47 Q24 41 37 52 L45 81 Q31 67 13 68 Z" fill="var(--icone-papel, #ffffff)"/>
                    <path d="M47 80 L41 50 L54 27 L73 25 L71 36 L58 38 L57 44 L73 48 L70 59 L55 58 Z" fill="var(--icone-sombra-papel, #b8b3b0)"/>
                    <path d="M45 78 L38 48 L51 25 L71 23 L68 33 L54 35 L52 42 L70 46 L68 56 L52 55 Z" fill="var(--icone-papel, #ffffff)"/>
                ',
            ],

            // 11. (Linha 4, Col 2) Círculo com livro aberto simétrico e recorte F na página direita
            'livro-aberto-f' => [
                'titulo' => 'Início e Catálogo FOLIVRO',
                'uso' => 'Página inicial, catálogo aberto e leitura na plataforma',
                'svg' => '
                    <circle cx="50" cy="50" r="46" fill="var(--icone-circulo-fundo, #230907)" stroke="var(--icone-circulo-borda, transparent)" stroke-width="2.5"/>
                    <path d="M17 32 Q33 26 48 40 V79 Q35 61 17 61 Z" fill="var(--icone-papel, #ffffff)"/>
                    <polygon points="62,48 82,43 82,48" fill="var(--icone-sombra-papel, #b8b3b0)"/>
                    <path d="M51 40 Q66 26 82 32 V43 L62 48 L82 51 V61 Q64 61 51 79 Z" fill="var(--icone-papel, #ffffff)"/>
                ',
            ],

            // 12. (Linha 4, Col 3) Círculo com livro inclinado, lombada destacada e base
            'livro-lombada' => [
                'titulo' => 'Loja de Obras Independentes',
                'uso' => 'Loja autoral, vitrine de obras e exemplares digitais',
                'svg' => '
                    <circle cx="50" cy="50" r="46" fill="var(--icone-circulo-fundo, #230907)" stroke="var(--icone-circulo-borda, transparent)" stroke-width="2.5"/>
                    <polygon points="21,27 25.5,26 38,71 33.5,72" fill="var(--icone-papel, #ffffff)"/>
                    <polygon points="32,27 66,18 74,66 42,70" fill="var(--icone-sombra-papel, #b8b3b0)"/>
                    <polygon points="29,27 68,22 77,69 41,70" fill="var(--icone-papel, #ffffff)"/>
                    <rect x="41" y="73" width="36" height="4.8" rx="1.6" fill="var(--icone-papel, #ffffff)"/>
                ',
            ],

            // 13. (Linha 5, Col 1) Notebook aberto sobre pilha de dois livros
            'notebook-livros' => [
                'titulo' => 'Estúdio de Escrita Digital',
                'uso' => 'Escrever livro, estúdio do autor e publicação digital',
                'svg' => '
                    <polygon points="18,12 22,12 31,52 27,52" fill="var(--icone-traco-escuro, #230907)"/>
                    <polygon points="21.5,14 24.5,14 32,50 29,50" fill="var(--icone-sombra-papel, #b8b3b0)"/>
                    <rect x="32" y="49" width="36" height="3.2" rx="1" fill="var(--icone-sombra-papel, #b8b3b0)"/>
                    <rect x="28" y="52" width="40" height="4.2" rx="1" fill="var(--icone-traco-escuro, #230907)"/>
                    <path d="M29 56.5 H78 V73.5 H29 V69.8 H72 V60.2 H29 Z" fill="var(--icone-primaria, #5c0000)"/>
                    <rect x="30" y="60.2" width="42" height="9.6" fill="var(--icone-papel, #ffffff)"/>
                    <line x1="33" y1="63.5" x2="55" y2="63.5" stroke="var(--icone-sombra-papel, #b8b3b0)" stroke-width="1.8"/>
                    <line x1="33" y1="66.8" x2="65" y2="66.8" stroke="var(--icone-sombra-papel, #b8b3b0)" stroke-width="1.8"/>
                    <path d="M24 74.5 H76 V92 H24 V88.2 H70 V78.2 H24 Z" fill="var(--icone-traco-escuro, #230907)"/>
                    <rect x="25" y="78.2" width="45" height="10" fill="#d9d5d2"/>
                    <line x1="28" y1="81.8" x2="51" y2="81.8" stroke="#9e9a97" stroke-width="1.8"/>
                    <line x1="28" y1="85.2" x2="61" y2="85.2" stroke="#9e9a97" stroke-width="1.8"/>
                ',
            ],

            // 14. (Linha 5, Col 2) Círculo com pasta/estante arredondada e recorte de marcador
            'pasta-biblioteca' => [
                'titulo' => 'Minha Biblioteca Digital',
                'uso' => 'Biblioteca pessoal, estante e coleções salvas',
                'svg' => '
                    <circle cx="50" cy="50" r="46" fill="var(--icone-circulo-fundo, #230907)" stroke="var(--icone-circulo-borda, transparent)" stroke-width="2.5"/>
                    <rect x="32" y="35" width="44" height="36" rx="6" fill="var(--icone-sombra-papel, #b8b3b0)"/>
                    <path d="M24 35 Q24 28 31 28 H44 Q49 28 51 33 H53 V47 L60 41 L67 47 V33 Q73 33 73 39 V65 Q73 71 67 71 H30 Q24 71 24 65 Z" fill="var(--icone-papel, #ffffff)"/>
                ',
            ],

            // 15. (Linha 5, Col 3) Círculo com degradê do tema e 4 barras brancas formando F
            'circulo-gradiente-f' => [
                'titulo' => 'Símbolo Institucional FOLIVRO',
                'uso' => 'Botão de configurações, avatar institucional e destaque da marca',
                'svg' => '
                    <defs>
                        <linearGradient id="folivroGradIcon15" x1="0" y1="0" x2="0.8" y2="1">
                            <stop offset="0%" stop-color="var(--icone-acento-claro, #b81d13)"/>
                            <stop offset="48%" stop-color="var(--icone-primaria, #5c0000)"/>
                            <stop offset="100%" stop-color="var(--icone-circulo-fundo, #190504)"/>
                        </linearGradient>
                    </defs>
                    <circle cx="50" cy="50" r="46" fill="url(#folivroGradIcon15)" stroke="var(--icone-circulo-borda, transparent)" stroke-width="2.5"/>
                    <rect x="35" y="26" width="27" height="8.8" rx="4.4" fill="var(--icone-papel, #ffffff)"/>
                    <rect x="35" y="38" width="13.5" height="8.2" rx="4.1" fill="var(--icone-papel, #ffffff)"/>
                    <rect x="35" y="49.5" width="30" height="8.8" rx="4.4" fill="var(--icone-papel, #ffffff)"/>
                    <rect x="35" y="61.5" width="13.5" height="8.8" rx="4.4" fill="var(--icone-papel, #ffffff)"/>
                ',
            ],
        ];
    }
}

if (!function_exists('folivro_icone')) {
    /**
     * Renderiza um ícone personalizado do FOLIVRO em SVG inline responsivo e adaptável ao tema.
     *
     * @param string $nome        Identificador do ícone (ex.: 'emblema-f', 'pasta-biblioteca', 'folha-metas-f')
     * @param string $classeExtra Classes CSS adicionais (ex.: 'folivro-icon-sm', 'folivro-icon-lg')
     * @param string $ariaLabel   Texto acessível opcional (se vazio, o ícone é marcado com aria-hidden="true")
     */
    function folivro_icone(string $nome, string $classeExtra = '', string $ariaLabel = ''): string
    {
        $defs = folivro_icones_definicoes();
        if (!isset($defs[$nome])) {
            return '';
        }
        $classes = trim('folivro-icon folivro-icon-' . $nome . ($classeExtra !== '' ? ' ' . $classeExtra : ''));
        $clsAttr = htmlspecialchars($classes, ENT_QUOTES, 'UTF-8');
        if ($ariaLabel !== '') {
            $lblAttr = htmlspecialchars($ariaLabel, ENT_QUOTES, 'UTF-8');
            $a11y = 'role="img" aria-label="' . $lblAttr . '"';
        } else {
            $a11y = 'aria-hidden="true" focusable="false"';
        }
        return '<svg class="' . $clsAttr . '" viewBox="0 0 100 100" xmlns="http://www.w3.org/2000/svg" ' . $a11y . '>'
            . trim($defs[$nome]['svg'])
            . '</svg>';
    }
}
