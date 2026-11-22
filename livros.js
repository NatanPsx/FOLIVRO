(() => {
    const API_URL = 'api/livros.php';

    function mostrarMensagem(container, mensagem, tipo = '') {
        container.replaceChildren();
        const aviso = document.createElement('p');
        aviso.className = `estado-vazio ${tipo}`.trim();
        aviso.textContent = mensagem;
        container.appendChild(aviso);
    }

    const EYE_OFF_SVG = `<svg class="icone-olho-bloqueado-svg" viewBox="0 0 24 24" width="32" height="32" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round" aria-hidden="true"><path d="M17.94 17.94A10.07 10.07 0 0 1 12 20c-7 0-11-8-11-8a18.45 18.45 0 0 1 5.06-5.94"/><path d="M9.9 4.24A9.12 9.12 0 0 1 12 4c7 0 11 8 11 8a18.5 18.5 0 0 1-2.16 3.19"/><path d="M14.12 14.12a3 3 0 1 1-4.24-4.24"/><line x1="1" y1="1" x2="23" y2="23"/></svg>`;

    function criarCapa(book) {
        const bloqueado = Boolean(book.bloqueadoIdade);
        const classNorm = book.classificacao || 'livre';
        const siglaIdade = book.classificacaoSigla || (classNorm === 'livre' ? 'L' : `+${classNorm}`);
        const labelIdade = book.classificacaoLabel || 'Classificação Livre';

        const ehFolivro = book.source === 'folivro';
        const urlDestino = ehFolivro && book.detailUrl ? book.detailUrl : `livro.php?work=${encodeURIComponent(book.workId)}`;

        const wrap = document.createElement(bloqueado ? 'div' : 'a');
        wrap.className = bloqueado
            ? 'livro-capa capa-bloqueada-18'
            : 'livro-capa link-livro-capa';
        if (!bloqueado) {
            wrap.href = urlDestino;
            wrap.title = ehFolivro ? `Abrir ${book.title} no FOLIVRO` : `Ver críticas e informações de ${book.title}`;
        }

        const fallbackTexto = bloqueado ? 'Obra +18' : book.title;
        if (book.coverUrl) {
            const image = document.createElement('img');
            image.src = book.coverUrl;
            image.alt = bloqueado ? 'Capa bloqueada por classificação indicativa +18' : `Capa de ${book.title}`;
            image.loading = 'lazy';
            image.onerror = () => {
                const fallback = document.createElement('div');
                fallback.className = 'capa-fallback';
                fallback.textContent = fallbackTexto;
                image.replaceWith(fallback);
            };
            wrap.appendChild(image);
        } else {
            const fallback = document.createElement('div');
            fallback.className = 'capa-fallback';
            fallback.textContent = fallbackTexto;
            wrap.appendChild(fallback);
        }

        if (bloqueado) {
            const overlay = document.createElement('div');
            overlay.className = 'capa-bloqueio-idade-overlay';
            overlay.setAttribute('role', 'img');
            overlay.setAttribute('aria-label', 'Conteúdo bloqueado: exclusivo para maiores de 18 anos');
            overlay.innerHTML = `
                <span class="icone-olho-bloqueado-circulo">${EYE_OFF_SVG}</span>
                <strong class="titulo-bloqueio-capa">Classificação +18</strong>
                <span class="motivo-bloqueio-capa">Bloqueado: exclusivo para maiores de 18 anos</span>
            `;
            wrap.appendChild(overlay);
        }

        const seloCapa = document.createElement('span');
        seloCapa.className = `selo-classificacao-idade selo-classificacao-capa selo-idade-${classNorm}`;
        seloCapa.title = labelIdade;
        seloCapa.textContent = siglaIdade;
        wrap.appendChild(seloCapa);

        return wrap;
    }

    function criarCard(book) {
        const bloqueado = Boolean(book.bloqueadoIdade);
        const ehFolivro = book.source === 'folivro';
        const urlDestino = ehFolivro && book.detailUrl ? book.detailUrl : `livro.php?work=${encodeURIComponent(book.workId)}`;
        const classNorm = book.classificacao || 'livre';
        const siglaIdade = book.classificacaoSigla || (classNorm === 'livre' ? 'L' : `+${classNorm}`);
        const labelIdade = book.classificacaoLabel || 'Classificação Livre';

        const card = document.createElement('article');
        card.className = bloqueado ? 'livro-card livro-card-bloqueado-idade' : 'livro-card';
        card.appendChild(criarCapa(book));

        const details = document.createElement('div');
        details.className = 'livro-info';

        const badgesRow = document.createElement('div');
        badgesRow.style.display = 'flex';
        badgesRow.style.gap = '6px';
        badgesRow.style.flexWrap = 'wrap';
        badgesRow.style.alignItems = 'center';

        const origemBadge = document.createElement('span');
        origemBadge.className = ehFolivro ? 'selo-status selo-lido selo-origem-folivro' : 'selo-status selo-origem-openlibrary';
        origemBadge.textContent = book.sourceLabel || (ehFolivro ? 'Publicado no FOLIVRO' : 'Open Library');
        badgesRow.appendChild(origemBadge);

        if (book.ratingAvg && book.ratingsCount > 0) {
            const badge = document.createElement('span');
            badge.className = 'selo-status selo-avaliacao-destaque';
            badge.title = `Avaliação média: ${book.ratingAvg.toFixed(1).replace('.', ',')} de 5 estrelas (${book.ratingsCount} ${book.ratingsCount === 1 ? 'avaliação computada' : 'avaliações computadas'})`;
            badge.innerHTML = `<img src="img/icones/star.png" alt="" class="icone-btn-png icone-estrela-badge"> <strong>${book.ratingAvg.toFixed(1).replace('.', ',')}</strong> <span class="badge-votos-qtd">(${book.ratingsCount} ${book.ratingsCount === 1 ? 'voto' : 'votos'})</span>`;
            badgesRow.appendChild(badge);
        }

        if (ehFolivro && book.priceFormatted) {
            const precoBadge = document.createElement('span');
            precoBadge.className = 'selo-status selo-comprado';
            precoBadge.textContent = book.priceFormatted;
            badgesRow.appendChild(precoBadge);
        }

        const seloIdade = document.createElement('span');
        seloIdade.className = `selo-classificacao-idade selo-idade-${classNorm}`;
        seloIdade.title = labelIdade;
        seloIdade.textContent = siglaIdade;
        badgesRow.appendChild(seloIdade);

        details.appendChild(badgesRow);

        const title = document.createElement('h3');
        if (bloqueado) {
            const hiddenTitle = document.createElement('span');
            hiddenTitle.className = 'titulo-oculto-idade';
            hiddenTitle.title = 'Título oculto por restrição de faixa etária (+18)';
            hiddenTitle.textContent = 'Título oculto (+18)';
            title.appendChild(hiddenTitle);
        } else {
            const titleLink = document.createElement('a');
            titleLink.href = urlDestino;
            titleLink.className = 'link-titulo-livro';
            titleLink.textContent = book.title;
            titleLink.title = ehFolivro ? `Abrir ${book.title} no FOLIVRO` : `Ver críticas e detalhes de ${book.title}`;
            title.appendChild(titleLink);
        }
        details.appendChild(title);

        if (!bloqueado && book.originalTitle && book.originalTitle !== book.title) {
            const orig = document.createElement('p');
            orig.style.fontSize = '12px';
            orig.style.color = 'var(--texto-secundario)';
            orig.style.marginTop = '-4px';
            orig.style.marginBottom = '6px';
            orig.textContent = `Original: ${book.originalTitle}`;
            details.appendChild(orig);
        }

        const author = document.createElement('p');
        author.textContent = book.authors.length ? book.authors.join(', ') : 'Autoria não informada';
        details.appendChild(author);

        if (book.year) {
            const year = document.createElement('p');
            year.className = 'livro-ano';
            year.textContent = `Publicado em ${book.year}`;
            details.appendChild(year);
        }

        if (bloqueado) {
            const motivoCard = document.createElement('p');
            motivoCard.className = 'texto-motivo-bloqueio-card';
            motivoCard.innerHTML = 'Obra oculta: sua conta possui menos de 18 anos e este livro tem classificação <strong>+18</strong>.';
            details.appendChild(motivoCard);

            const blockedBtn = document.createElement('button');
            blockedBtn.type = 'button';
            blockedBtn.className = 'botao-adicionar-livro botao-bloqueado-idade';
            blockedBtn.disabled = true;
            blockedBtn.title = 'Conteúdo exclusivo para maiores de 18 anos';
            blockedBtn.textContent = 'Bloqueado (+18)';
            details.appendChild(blockedBtn);
        } else if (ehFolivro) {
            const linkDetalhes = document.createElement('a');
            linkDetalhes.href = urlDestino;
            linkDetalhes.className = 'link-ver-detalhes';
            linkDetalhes.innerHTML = 'Ver ficha da obra &rarr;';
            details.appendChild(linkDetalhes);

            const openButton = document.createElement('a');
            openButton.href = urlDestino;
            openButton.className = 'botao-adicionar-livro';
            openButton.style.textAlign = 'center';
            openButton.style.textDecoration = 'none';
            openButton.textContent = book.priceCentavos === 0 ? 'Ler / Obter grátis' : 'Ver obra na loja';
            details.appendChild(openButton);
        } else {
            const linkDetalhes = document.createElement('a');
            linkDetalhes.href = urlDestino;
            linkDetalhes.className = 'link-ver-detalhes';
            linkDetalhes.innerHTML = 'Ver críticas & ficha completa &rarr;';
            details.appendChild(linkDetalhes);

            const addButton = document.createElement('button');
            addButton.type = 'button';
            addButton.className = 'botao-adicionar-livro';
            addButton.textContent = 'Salvar na biblioteca';
            addButton.addEventListener('click', async () => {
                addButton.disabled = true;
                addButton.textContent = 'Adicionando…';
                try {
                    const token = document.querySelector('meta[name="csrf-token"]')?.content || '';
                    const response = await fetch('api/biblioteca.php', {
                        method: 'POST',
                        headers: { 'Content-Type': 'application/json', 'X-CSRF-Token': token, Accept: 'application/json' },
                        body: JSON.stringify(book)
                    });
                    const data = await response.json();
                    if (!response.ok) throw new Error(data.error || 'Não foi possível adicionar o livro.');
                    window.location.href = data.readerUrl;
                } catch (error) {
                    addButton.disabled = false;
                    addButton.textContent = error.message || 'Tentar novamente';
                }
            });
            details.appendChild(addButton);

            if (book.workUrl) {
                const link = document.createElement('a');
                link.href = book.workUrl;
                link.target = '_blank';
                link.rel = 'noopener noreferrer';
                link.textContent = 'Ver na Open Library';
                details.appendChild(link);
            }
        }
        card.appendChild(details);
        return card;
    }

    const cacheBuscaEmMemoria = new Map();

    async function buscarLivros(query, container, limit = 18, offset = 0, sort = 'readinglog', append = false) {
        const queryLimpa = (query || '').trim();
        const cacheKey = `${queryLimpa.toLowerCase()}_${sort}_${limit}_${offset}`;
        const containerMais = document.querySelector('#container-mais-pesquisa');

        const renderizar = (data) => {
            if (!Array.isArray(data.items) || data.items.length === 0) {
                if (!append) {
                    mostrarMensagem(container, 'Nenhum livro encontrado. Tente outro título, autor ou categoria.');
                }
                if (containerMais) containerMais.style.display = 'none';
                return 0;
            }
            const cards = data.items.map(criarCard);
            if (append) {
                cards.forEach((c) => container.appendChild(c));
            } else {
                container.replaceChildren(...cards);
            }
            if (containerMais) {
                containerMais.style.display = data.hasMore ? 'flex' : 'none';
            }
            return data.items.length;
        };

        if (cacheBuscaEmMemoria.has(cacheKey)) {
            return renderizar(cacheBuscaEmMemoria.get(cacheKey));
        }

        if (!append) {
            mostrarMensagem(container, 'Buscando livros no catálogo aberto…', 'estado-carregando');
        }
        try {
            const response = await fetch(`${API_URL}?q=${encodeURIComponent(queryLimpa)}&sort=${encodeURIComponent(sort)}&limit=${limit}&offset=${offset}`, {
                headers: { Accept: 'application/json' }
            });
            const data = await response.json();
            if (!response.ok) {
                throw new Error(data.error || 'Não foi possível consultar o catálogo.');
            }
            cacheBuscaEmMemoria.set(cacheKey, data);
            return renderizar(data);
        } catch (error) {
            if (!append) {
                mostrarMensagem(container, 'Não foi possível carregar o catálogo agora. Confira a conexão e tente novamente.', 'estado-erro');
            }
            if (containerMais) containerMais.style.display = 'none';
            return 0;
        }
    }

    const searchForm = document.querySelector('[data-busca-livros]');
    if (searchForm) {
        const input = searchForm.querySelector('input[name="busca"]');
        const results = document.querySelector('#resultados-livros');
        const heading = document.querySelector('.titulo-resultados');
        const btnLimpar = document.querySelector('#btn-limpar-pesquisa');
        const iconeBtnBusca = document.querySelector('#icone-btn-busca');
        const selectOrdem = document.querySelector('#ordenar-pesquisa');
        const btnMais = document.querySelector('#btn-mais-pesquisa');
        const containerMais = document.querySelector('#container-mais-pesquisa');
        const params = new URLSearchParams(window.location.search);
        const initialQuery = (params.get('categoria') || params.get('busca') || '').trim();
        let termoAtivo = '';
        let offsetPesquisa = 0;
        let debounceTimer = null;

        async function executarBusca(termoOriginal, rotuloExibicao = null) {
            const query = (termoOriginal || '').trim();
            termoAtivo = query;
            offsetPesquisa = 0;
            document.querySelectorAll('.lista-categorias a[data-categoria]').forEach((el) => {
                el.classList.toggle('ativo', (el.dataset.categoria || '').toLowerCase() === query.toLowerCase());
            });
            if (!query) {
                const url = new URL(window.location.href);
                url.searchParams.delete('busca');
                url.searchParams.delete('categoria');
                window.history.replaceState({}, '', url);
                if (heading) heading.textContent = 'Resultados da pesquisa';
                if (results) mostrarMensagem(results, 'Digite um livro, autor ou escolha uma categoria abaixo para explorar o catálogo.');
                if (btnLimpar) btnLimpar.style.display = 'none';
                if (containerMais) containerMais.style.display = 'none';
                return;
            }

            if (btnLimpar) {
                btnLimpar.style.display = 'inline-flex';
            }

            const url = new URL(window.location.href);
            url.searchParams.delete('categoria');
            url.searchParams.set('busca', query);
            window.history.replaceState({}, '', url);

            if (heading) {
                heading.textContent = `Resultados para: “${rotuloExibicao || query}”`;
            }

            if (iconeBtnBusca) {
                iconeBtnBusca.src = 'img/icones/hourglass.png';
                iconeBtnBusca.classList.add('icone-girando');
            }

            const sort = selectOrdem ? selectOrdem.value : 'readinglog';
            try {
                const qtd = await buscarLivros(query, results, 18, 0, sort, false);
                offsetPesquisa = qtd;
            } finally {
                if (iconeBtnBusca) {
                    iconeBtnBusca.src = 'img/icones/search.png';
                    iconeBtnBusca.classList.remove('icone-girando');
                }
            }
        }

        searchForm.addEventListener('submit', (event) => {
            event.preventDefault();
            clearTimeout(debounceTimer);
            const query = input.value.trim();
            if (!query) {
                input.focus();
                executarBusca('');
                return;
            }
            executarBusca(query);
        });

        // Busca em tempo real com debounce de 450ms
        input.addEventListener('input', () => {
            const val = input.value;
            const queryLimpa = val.trim();

            if (btnLimpar) {
                btnLimpar.style.display = val.length > 0 ? 'inline-flex' : 'none';
            }

            clearTimeout(debounceTimer);

            if (queryLimpa === '') {
                executarBusca('');
                return;
            }

            // Dispara busca automática após 3 caracteres e pausa de 450ms
            if (queryLimpa.length >= 3) {
                debounceTimer = setTimeout(() => {
                    executarBusca(queryLimpa);
                }, 450);
            }
        });

        // Botão para limpar a pesquisa instantaneamente
        if (btnLimpar) {
            btnLimpar.addEventListener('click', () => {
                clearTimeout(debounceTimer);
                input.value = '';
                btnLimpar.style.display = 'none';
                input.focus();
                executarBusca('');
            });
        }

        // Dicas rápidas (chips) clicáveis
        document.querySelectorAll('.chip-sugestao').forEach((chip) => {
            chip.addEventListener('click', () => {
                clearTimeout(debounceTimer);
                const termo = chip.dataset.termo || chip.textContent.trim();
                input.value = termo;
                if (btnLimpar) btnLimpar.style.display = 'inline-flex';
                input.focus();
                executarBusca(termo);
            });
        });

        if (selectOrdem) {
            selectOrdem.addEventListener('change', () => {
                if (termoAtivo) {
                    executarBusca(termoAtivo);
                }
            });
        }

        if (btnMais) {
            btnMais.addEventListener('click', async () => {
                if (!termoAtivo) return;
                btnMais.disabled = true;
                const sort = selectOrdem ? selectOrdem.value : 'readinglog';
                try {
                    const qtd = await buscarLivros(termoAtivo, results, 12, offsetPesquisa, sort, true);
                    offsetPesquisa += qtd;
                } finally {
                    btnMais.disabled = false;
                }
            });
        }

        document.querySelectorAll('.lista-categorias a[data-categoria]').forEach((linkCat) => {
            linkCat.addEventListener('click', (ev) => {
                ev.preventDefault();
                clearTimeout(debounceTimer);
                const cat = linkCat.dataset.categoria || '';
                const rotulo = linkCat.dataset.rotulo || linkCat.textContent.trim();
                input.value = rotulo;
                if (btnLimpar) btnLimpar.style.display = 'inline-flex';
                executarBusca(cat, rotulo);
                window.scrollTo({ top: 0, behavior: 'smooth' });
            });
        });

        if (initialQuery) {
            const linkMatch = document.querySelector(`.lista-categorias a[data-categoria="${CSS.escape(initialQuery)}"]`);
            const rotuloInit = linkMatch ? (linkMatch.dataset.rotulo || initialQuery) : initialQuery;
            input.value = rotuloInit;
            if (btnLimpar) btnLimpar.style.display = 'inline-flex';
            executarBusca(initialQuery, rotuloInit);
        }
    }

    // Gerenciamento da Grade de Livros em Destaque na Home com botão 'Ver mais...'
    const featured = document.querySelector('#catalogo-destaque');
    const containerVerMais = document.querySelector('#container-ver-mais-catalogo');
    const btnVerMais = document.querySelector('#btn-ver-mais-catalogo');

    if (featured) {
        let offsetAtual = 0;
        let carregando = false;

        async function carregarCatalogoInicial() {
            mostrarMensagem(featured, 'Carregando os livros mais populares e aclamados…', 'estado-carregando');
            if (containerVerMais) {
                containerVerMais.style.display = 'none';
            }
            try {
                const response = await fetch(`${API_URL}?destaques=1&limit=12&offset=0&v=2`, {
                    headers: { Accept: 'application/json' },
                    cache: 'no-store'
                });
                const data = await response.json();
                if (!response.ok) {
                    throw new Error(data.error || 'Não foi possível carregar os livros.');
                }
                if (!Array.isArray(data.items) || data.items.length === 0) {
                    mostrarMensagem(featured, 'Nenhum livro disponível no momento.');
                    return;
                }

                featured.replaceChildren(...data.items.map(criarCard));
                offsetAtual = data.items.length;

                // Exibe o botão 'Ver mais...' centralizado logo após os 12 livros
                if (containerVerMais && btnVerMais) {
                    containerVerMais.style.display = 'flex';
                    if (data.items.length < 12 || data.hasMore === false) {
                        btnVerMais.disabled = true;
                        btnVerMais.textContent = 'Fim do catálogo';
                    } else {
                        btnVerMais.disabled = false;
                        btnVerMais.innerHTML = '<img src="img/icones/expand_more.png" alt="" class="icone-ver-mais-png"> Ver mais…';
                    }
                }
            } catch (err) {
                mostrarMensagem(featured, 'Não foi possível carregar o catálogo. Verifique sua conexão e tente novamente.', 'estado-erro');
            }
        }

        async function carregarMaisLivros() {
            if (carregando || !btnVerMais) return;
            carregando = true;
            btnVerMais.disabled = true;
            btnVerMais.innerHTML = '<img src="img/icones/hourglass.png" alt="" class="icone-ver-mais-png icone-girando"> Carregando mais 6 livros…';

            try {
                const response = await fetch(`${API_URL}?destaques=1&limit=6&offset=${offsetAtual}`, {
                    headers: { Accept: 'application/json' }
                });
                const data = await response.json();
                if (!response.ok) {
                    throw new Error(data.error || 'Não foi possível carregar mais livros.');
                }

                const novosItens = Array.isArray(data.items) ? data.items : [];
                if (novosItens.length > 0) {
                    novosItens.forEach(book => {
                        featured.appendChild(criarCard(book));
                    });
                    offsetAtual += novosItens.length;

                    // O botão permanece centralizado abaixo da nova fileira
                    if (novosItens.length < 6 || data.hasMore === false) {
                        btnVerMais.disabled = true;
                        btnVerMais.textContent = 'Todos os livros foram carregados';
                    } else {
                        btnVerMais.disabled = false;
                        btnVerMais.innerHTML = '<img src="img/icones/expand_more.png" alt="" class="icone-ver-mais-png"> Ver mais…';
                    }
                } else {
                    btnVerMais.disabled = true;
                    btnVerMais.textContent = 'Todos os livros foram carregados';
                }
            } catch (err) {
                btnVerMais.disabled = false;
                btnVerMais.innerHTML = '<img src="img/icones/hourglass.png" alt="" class="icone-ver-mais-png"> Erro ao carregar mais. Tentar novamente';
            } finally {
                carregando = false;
            }
        }

        if (btnVerMais) {
            btnVerMais.addEventListener('click', carregarMaisLivros);
        }

        carregarCatalogoInicial();
    }
})();
