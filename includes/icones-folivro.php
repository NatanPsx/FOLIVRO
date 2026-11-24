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

            // 4. Logo Principal do Site (Frame 25: círculo em degradê vermelho-escuro com página aberta + F)
            'emblema-f' => [
                'titulo' => 'Logotipo Oficial FOLIVRO',
                'uso' => 'Logotipo principal do site, login, cadastro e identidade da plataforma',
                'svg' => '
                    <defs>
                        <linearGradient id="folivroGradLogo25" x1="0.5" y1="0" x2="0.5" y2="1">
                            <stop offset="0%" stop-color="#9e1108"/>
                            <stop offset="48%" stop-color="#3b0502"/>
                            <stop offset="100%" stop-color="#0d0202"/>
                        </linearGradient>
                    </defs>
                    <circle cx="50" cy="50" r="46" fill="url(#folivroGradLogo25)" stroke="var(--icone-circulo-borda, transparent)" stroke-width="2.5"/>
                    <path d="M23 32 Q37 26 48 39 V73 Q37 61 23 63 Z" fill="var(--icone-papel, #ffffff)"/>
                    <path d="M53 33 H77 V44 H63 V50 H74 V60 H63 V74 L58 69 L53 74 Z" fill="var(--icone-papel, #ffffff)"/>
                ',
            ],

            // 5. Obras / Biblioteca de Livros em Pé (dois livros em pé com marcador de página)
            'livro-indice-f' => [
                'titulo' => 'Obras e Biblioteca Vertical',
                'uso' => 'Minhas obras, estante de obras e lista de capítulos',
                'svg' => '
                    <circle cx="50" cy="50" r="46" fill="var(--icone-circulo-fundo, #1f0605)" stroke="var(--icone-circulo-borda, transparent)" stroke-width="2.5"/>
                    <polygon points="27,31 54,23 58,69 31,74" fill="var(--icone-sombra-papel, #a8a29e)"/>
                    <polygon points="27,31 33,33 36,74 31,74" fill="#78716c"/>
                    <polygon points="41,37 68,28 70,72 43,78" fill="var(--icone-papel, #ffffff)"/>
                    <polygon points="41,37 46,39 47,78 43,78" fill="#d6d3d1"/>
                    <path d="M51 34 L58 31.5 V49 L54.5 46 L51 50 Z" fill="var(--icone-circulo-fundo, #1f0605)"/>
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

            // 8. Metas (círculo com alvo concêntrico e flecha apontando para o centro)
            'folha-metas-f' => [
                'titulo' => 'Metas e Estatísticas de Leitura',
                'uso' => 'Metas anuais, estatísticas e indicadores de desempenho',
                'svg' => '
                    <circle cx="50" cy="50" r="46" fill="var(--icone-circulo-fundo, #1f0605)" stroke="var(--icone-circulo-borda, transparent)" stroke-width="2.5"/>
                    <circle cx="50" cy="50" r="32" fill="var(--icone-papel, #ffffff)"/>
                    <circle cx="50" cy="50" r="21" fill="var(--icone-sombra-papel, #b8b3b0)"/>
                    <circle cx="50" cy="50" r="10.5" fill="var(--icone-papel, #ffffff)"/>
                    <line x1="27" y1="73" x2="50" y2="50" stroke="var(--icone-circulo-fundo, #1f0605)" stroke-width="6" stroke-linecap="round"/>
                    <polyline points="37,50 50,50 50,63" fill="none" stroke="var(--icone-circulo-fundo, #1f0605)" stroke-width="6" stroke-linecap="round" stroke-linejoin="round"/>
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

            // 12. Loja (círculo escuro com sacola branca de compras e marcador de página)
            'livro-lombada' => [
                'titulo' => 'Loja de Obras Independentes',
                'uso' => 'Loja autoral, vitrine de obras e exemplares digitais',
                'svg' => '
                    <circle cx="50" cy="50" r="46" fill="var(--icone-circulo-fundo, #1f0605)" stroke="var(--icone-circulo-borda, transparent)" stroke-width="2.5"/>
                    <path d="M36 40 C36 22, 64 22, 64 40" fill="none" stroke="var(--icone-papel, #ffffff)" stroke-width="3.2" stroke-linecap="round"/>
                    <path d="M31 39 H69 Q71.5 39 72 41.8 L76.5 72.5 Q77 76 73.2 76 H26.8 Q23 76 23.5 72.5 L28 41.8 Q28.5 39 31 39 Z" fill="var(--icone-papel, #ffffff)"/>
                    <path d="M49 39 H61 V56 L55 51 L49 56 Z" fill="var(--icone-circulo-fundo, #1f0605)"/>
                ',
            ],

            // 13. Escrever (círculo com folha pautada inclinada e lápis escuro)
            'notebook-livros' => [
                'titulo' => 'Estúdio de Escrita Digital',
                'uso' => 'Escrever livro, estúdio do autor e publicação digital',
                'svg' => '
                    <circle cx="50" cy="50" r="46" fill="var(--icone-circulo-fundo, #1f0605)" stroke="var(--icone-circulo-borda, transparent)" stroke-width="2.5"/>
                    <polygon points="25,28 65,20 75,70 35,78" fill="var(--icone-papel, #ffffff)"/>
                    <g transform="rotate(-11 50 50)" stroke="var(--icone-sombra-papel, #b8b3b0)" stroke-width="3.2" stroke-linecap="round">
                        <line x1="34" y1="36" x2="62" y2="36"/>
                        <line x1="34" y1="45" x2="60" y2="45"/>
                        <line x1="34" y1="54" x2="54" y2="54"/>
                        <line x1="34" y1="63" x2="48" y2="63"/>
                    </g>
                    <g transform="rotate(42 64 52)">
                        <rect x="59" y="27" width="10" height="36" rx="2" fill="var(--icone-circulo-fundo, #1f0605)"/>
                        <polygon points="59,63 69,63 64,74" fill="var(--icone-circulo-fundo, #1f0605)"/>
                    </g>
                ',
            ],

            // 14. Biblioteca (círculo com pilha de três livros horizontais)
            'pasta-biblioteca' => [
                'titulo' => 'Minha Biblioteca Digital',
                'uso' => 'Biblioteca pessoal, estante e coleções salvas',
                'svg' => '
                    <circle cx="50" cy="50" r="46" fill="var(--icone-circulo-fundo, #1f0605)" stroke="var(--icone-circulo-borda, transparent)" stroke-width="2.5"/>
                    <rect x="24" y="28" width="44" height="12" rx="2.5" fill="var(--icone-papel, #ffffff)"/>
                    <polygon points="68,28 80,24 80,36 68,40" fill="var(--icone-sombra-papel, #b8b3b0)"/>
                    <rect x="24" y="44" width="46" height="12" rx="2.5" fill="var(--icone-papel, #ffffff)"/>
                    <polygon points="70,44 82,40 82,52 70,56" fill="var(--icone-sombra-papel, #b8b3b0)"/>
                    <rect x="24" y="60" width="44" height="12" rx="2.5" fill="var(--icone-papel, #ffffff)"/>
                    <polygon points="68,60 80,56 80,68 68,72" fill="var(--icone-sombra-papel, #b8b3b0)"/>
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

            // 16. Novo Ícone de Chat (círculo com página dupla inclinada e ondas de diálogo/voz)
            'chat-folivro' => [
                'titulo' => 'Chat Literário FOLIVRO',
                'uso' => 'Menu Chat, conversas e mensagens literárias',
                'svg' => '
                    <circle cx="50" cy="50" r="46" fill="var(--icone-circulo-fundo, #230907)" stroke="var(--icone-circulo-borda, transparent)" stroke-width="2.5"/>
                    <polygon points="26,27 65,18 73,67 34,72" fill="var(--icone-sombra-papel, #b8b3b0)"/>
                    <path d="M24 27 Q43 32 67 22 L78 72 Q54 78 35 77 Z" fill="var(--icone-papel, #ffffff)"/>
                    <rect x="28" y="42" width="9" height="19" rx="4.5" fill="var(--icone-circulo-fundo, #230907)"/>
                    <rect x="41" y="34" width="9.5" height="34" rx="4.7" fill="var(--icone-circulo-fundo, #230907)"/>
                    <rect x="54.5" y="44" width="9" height="16" rx="4.5" fill="var(--icone-circulo-fundo, #230907)"/>
                    <rect x="66.5" y="49" width="9.5" height="6.5" rx="3.2" fill="var(--icone-circulo-fundo, #230907)"/>
                ',
            ],

            // 17. Novo Ícone de Início (círculo com telhado geométrico em V e casa estilizada)
            'inicio-folivro' => [
                'titulo' => 'Início FOLIVRO',
                'uso' => 'Menu Início e página principal',
                'svg' => '
                    <circle cx="50" cy="50" r="46" fill="var(--icone-circulo-fundo, #230907)" stroke="var(--icone-circulo-borda, transparent)" stroke-width="2.5"/>
                    <polygon points="44,18 50,22 25,68 18,64" fill="var(--icone-papel, #ffffff)"/>
                    <polygon points="56,18 50,22 75,68 82,64" fill="var(--icone-papel, #ffffff)"/>
                    <polygon points="50,29 36,56 33,74 41,74 45,57 49,74 53,74 57,57 62,74 75,74" fill="var(--icone-papel, #ffffff)"/>
                ',
            ],

            // 18. Perfil (círculo escuro com rosto fofo estilizado e arco de ombros brancos)
            'perfil-folivro' => [
                'titulo' => 'Perfil do Leitor FOLIVRO',
                'uso' => 'Menu Perfil, avatar padrão e configurações de conta',
                'svg' => '
                    <circle cx="50" cy="50" r="46" fill="var(--icone-papel, #ffffff)" stroke="var(--icone-circulo-borda, transparent)" stroke-width="2.5"/>
                    <path d="M18 82 A46 46 0 1 1 82 82 C76 62, 64 58, 50 58 C36 58, 24 62, 18 82 Z" fill="var(--icone-circulo-fundo, #1f0605)"/>
                    <ellipse cx="50" cy="76" rx="20" ry="13" fill="#f2f1f0"/>
                    <circle cx="50" cy="40" r="19" fill="#d9d7d5"/>
                    <circle cx="50" cy="40" r="17" fill="var(--icone-papel, #ffffff)"/>
                    <ellipse cx="38.5" cy="42.5" rx="7.2" ry="8.8" fill="#d6d4d2"/>
                    <ellipse cx="61.5" cy="42.5" rx="7.2" ry="8.8" fill="#d6d4d2"/>
                    <circle cx="38" cy="44" r="3.6" fill="#524d4a"/>
                    <circle cx="39.2" cy="42.8" r="1.4" fill="#ffffff"/>
                    <circle cx="62" cy="44" r="3.6" fill="#524d4a"/>
                    <circle cx="63.2" cy="42.8" r="1.4" fill="#ffffff"/>
                    <ellipse cx="50" cy="46.5" rx="3.4" ry="2.4" fill="#c7c4c1"/>
                    <path d="M47.8 47.4 Q50 48.8 52.2 47.4" fill="none" stroke="#8a8581" stroke-width="1.3" stroke-linecap="round"/>
                    <line x1="43.5" y1="51.2" x2="56.5" y2="51.2" stroke="#9e9995" stroke-width="1.4" stroke-linecap="round"/>
                ',
            ],

            // 19. Buscar (círculo com lupa limpa branca)
            'pesquisar-folivro' => [
                'titulo' => 'Pesquisar no Catálogo FOLIVRO',
                'uso' => 'Menu Buscar e pesquisa de obras',
                'svg' => '
                    <circle cx="50" cy="50" r="46" fill="var(--icone-circulo-fundo, #1f0605)" stroke="var(--icone-circulo-borda, transparent)" stroke-width="2.5"/>
                    <circle cx="55" cy="45" r="20" fill="none" stroke="var(--icone-papel, #ffffff)" stroke-width="7"/>
                    <line x1="40" y1="60" x2="21" y2="79" stroke="var(--icone-papel, #ffffff)" stroke-width="7.5" stroke-linecap="round"/>
                ',
            ],

            // 20. Configurações (círculo com engrenagem cinza e anel central branco)
            'configuracoes-folivro' => [
                'titulo' => 'Configurações do FOLIVRO',
                'uso' => 'Botão flutuante de configurações e preferências da conta',
                'svg' => '
                    <circle cx="50" cy="50" r="46" fill="var(--icone-circulo-fundo, #1f0605)" stroke="var(--icone-circulo-borda, transparent)" stroke-width="2.5"/>
                    <g fill="var(--icone-sombra-papel, #9e9a97)">
                        <rect x="44" y="16" width="12" height="68" rx="3.5"/>
                        <rect x="44" y="16" width="12" height="68" rx="3.5" transform="rotate(45 50 50)"/>
                        <rect x="44" y="16" width="12" height="68" rx="3.5" transform="rotate(90 50 50)"/>
                        <rect x="44" y="16" width="12" height="68" rx="3.5" transform="rotate(135 50 50)"/>
                        <circle cx="50" cy="50" r="26"/>
                    </g>
                    <circle cx="50" cy="50" r="18" fill="var(--icone-papel, #ffffff)"/>
                    <circle cx="50" cy="50" r="10.5" fill="var(--icone-circulo-fundo, #1f0605)"/>
                ',
            ],

            // 21. Conquista: Primeiro Passo (pilha de livros com número 1 ao centro)
            'conquista-primeiro-passo' => [
                'titulo' => 'Conquista Primeiro Passo',
                'uso' => 'Conquista de leitura: adicione 1+ livro na biblioteca',
                'svg' => '
                    <circle cx="50" cy="50" r="46" fill="var(--icone-circulo-fundo, #1f0605)" stroke="var(--icone-circulo-borda, transparent)" stroke-width="2.5"/>
                    <rect x="24" y="28" width="44" height="12" rx="2.5" fill="var(--icone-papel, #ffffff)"/>
                    <polygon points="68,28 80,24 80,36 68,40" fill="var(--icone-sombra-papel, #b8b3b0)"/>
                    <rect x="24" y="44" width="46" height="12" rx="2.5" fill="var(--icone-papel, #ffffff)"/>
                    <polygon points="70,44 82,40 82,52 70,56" fill="var(--icone-sombra-papel, #b8b3b0)"/>
                    <rect x="24" y="60" width="44" height="12" rx="2.5" fill="var(--icone-papel, #ffffff)"/>
                    <polygon points="68,60 80,56 80,68 68,72" fill="var(--icone-sombra-papel, #b8b3b0)"/>
                    <text x="48" y="66" text-anchor="middle" fill="var(--icone-circulo-fundo, #1f0605)" font-family="Georgia, serif" font-size="48" font-weight="900">1</text>
                ',
            ],

            // 22. Conquista: Leitor Finalizador (alvo claro com 1° ao centro)
            'conquista-finalizador' => [
                'titulo' => 'Conquista Leitor Finalizador',
                'uso' => 'Conquista de leitura: conclua pelo menos 1 livro',
                'svg' => '
                    <circle cx="50" cy="50" r="46" fill="var(--icone-circulo-fundo, #1f0605)" stroke="var(--icone-circulo-borda, transparent)" stroke-width="2.5"/>
                    <circle cx="50" cy="50" r="32" fill="var(--icone-papel, #ffffff)"/>
                    <circle cx="50" cy="50" r="21" fill="#d6d3d1"/>
                    <text x="51" y="62" text-anchor="middle" fill="var(--icone-circulo-fundo, #1f0605)" font-family="Arial, sans-serif" font-size="36" font-weight="900">1°</text>
                ',
            ],

            // 23. Conquista: Maratonista (+500) (círculo vinho com 500 e livro aberto)
            'conquista-maratonista' => [
                'titulo' => 'Conquista Maratonista (+500)',
                'uso' => 'Conquista de leitura: leia 500 páginas registradas',
                'svg' => '
                    <defs>
                        <linearGradient id="folivroGradMarat" x1="0.5" y1="0" x2="0.5" y2="1">
                            <stop offset="0%" stop-color="#6e0704"/>
                            <stop offset="50%" stop-color="#9e0e08"/>
                            <stop offset="100%" stop-color="#450302"/>
                        </linearGradient>
                    </defs>
                    <circle cx="50" cy="50" r="46" fill="url(#folivroGradMarat)" stroke="var(--icone-circulo-borda, transparent)" stroke-width="2.5"/>
                    <text x="50" y="34" text-anchor="middle" fill="#ffffff" font-family="Arial, sans-serif" font-size="22" font-weight="800" letter-spacing="1">500</text>
                    <path d="M16 44 Q33 38 49 50 V82 Q34 68 16 69 Z" fill="#ffffff"/>
                    <path d="M51 50 Q67 38 84 44 V53 L68 58 L84 62 V69 Q66 68 51 82 Z" fill="#ffffff"/>
                ',
            ],

            // 24. Conquista: Crítico Literário (círculo vermelho com folha, chama e lápis)
            'conquista-critico' => [
                'titulo' => 'Conquista Crítico Literário',
                'uso' => 'Conquista de leitura: escreva 1+ resenha na comunidade',
                'svg' => '
                    <defs>
                        <linearGradient id="folivroGradCritico" x1="0" y1="0" x2="1" y2="1">
                            <stop offset="0%" stop-color="#e3190e"/>
                            <stop offset="100%" stop-color="#8c0603"/>
                        </linearGradient>
                    </defs>
                    <circle cx="50" cy="50" r="46" fill="url(#folivroGradCritico)" stroke="var(--icone-circulo-borda, transparent)" stroke-width="2.5"/>
                    <polygon points="25,28 65,20 74,69 34,77" fill="#ffffff"/>
                    <g transform="rotate(-11 50 50)" stroke="#c7c2be" stroke-width="3" stroke-linecap="round">
                        <line x1="33" y1="37" x2="60" y2="37"/>
                        <line x1="33" y1="46" x2="56" y2="46"/>
                        <line x1="33" y1="55" x2="48" y2="55"/>
                    </g>
                    <path d="M55 68 C45 64 44 51 52 43 C51 49 55 51 56 46 C59 38 64 36 65 31 C71 40 73 53 67 62 C64 67 59 69 55 68 Z" fill="#e6200e"/>
                    <path d="M57 66 C52 63 52 55 57 50 C59 54 63 52 63 58 C63 63 60 66 57 66 Z" fill="#ff7a1a"/>
                    <g transform="rotate(42 66 49)">
                        <rect x="62" y="25" width="9" height="35" rx="2" fill="#1a0504"/>
                        <polygon points="62,60 71,60 66.5,70" fill="#1a0504"/>
                    </g>
                ',
            ],

            // 25. Conquista: Colecionador (círculo vermelho vibrante com página aberta e número 5)
            'conquista-colecionador' => [
                'titulo' => 'Conquista Colecionador',
                'uso' => 'Conquista de leitura: reúna 5+ títulos na estante',
                'svg' => '
                    <defs>
                        <radialGradient id="folivroGradColec" cx="40%" cy="30%" r="70%">
                            <stop offset="0%" stop-color="#ff3300"/>
                            <stop offset="100%" stop-color="#c40000"/>
                        </radialGradient>
                    </defs>
                    <circle cx="50" cy="50" r="46" fill="url(#folivroGradColec)" stroke="var(--icone-circulo-borda, transparent)" stroke-width="2.5"/>
                    <path d="M18 37 Q34 31 46 43 V74 Q34 62 18 64 Z" fill="#ffffff"/>
                    <text x="67" y="68" text-anchor="middle" fill="#ffffff" stroke="#3b0502" stroke-width="2" paint-order="stroke" font-family="Georgia, serif" font-size="44" font-weight="900">5</text>
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
