<?php
/**
 * Componente reutilizável: Modal "Apoie o criador" — FOLIVRO
 *
 * Variáveis esperadas antes de incluir:
 *   $apoioAutorNome (string)
 *   $apoioAutorLink (string)
 *   $apoioAutorPix  (string)
 *   $apoioAutorMsg  (string)
 */
$apoioAutorNome = trim((string) ($apoioAutorNome ?? 'Autor(a)'));
$apoioAutorLink = trim((string) ($apoioAutorLink ?? ''));
$apoioAutorPix  = trim((string) ($apoioAutorPix ?? ''));
$apoioAutorMsg  = trim((string) ($apoioAutorMsg ?? ''));
$temApoioCriador = ($apoioAutorLink !== '' || $apoioAutorPix !== '');

if (!$temApoioCriador) {
    return;
}
?>
<style id="folivro-apoie-criador-estilos">
.btn-apoie-criador {
    display: inline-flex;
    align-items: center;
    justify-content: center;
    gap: 7px;
    padding: 8px 16px;
    border-radius: 999px;
    border: 1.5px solid var(--cor-dourado-foco, #d4943a);
    background: linear-gradient(135deg, rgba(212, 148, 58, 0.16), rgba(107, 19, 13, 0.12));
    color: var(--texto-principal, #231b18);
    font-family: var(--fonte-interface, sans-serif);
    font-size: 13.5px;
    font-weight: 700;
    cursor: pointer;
    transition: transform 0.16s ease, box-shadow 0.16s ease, background 0.16s ease, border-color 0.16s ease;
    text-decoration: none;
    white-space: nowrap;
}
.btn-apoie-criador:hover {
    transform: translateY(-1px);
    background: linear-gradient(135deg, rgba(212, 148, 58, 0.28), rgba(107, 19, 13, 0.2));
    box-shadow: 0 4px 14px rgba(212, 148, 58, 0.25);
}
.btn-apoie-criador:focus-visible {
    outline: 3px solid var(--cor-dourado-foco, #e7ae5c);
    outline-offset: 2px;
}
.btn-apoie-criador svg {
    color: #d9463e;
    flex-shrink: 0;
}

.modal-apoie-fundo {
    position: fixed;
    inset: 0;
    z-index: 21000;
    display: none;
    align-items: center;
    justify-content: center;
    padding: 16px;
    background: rgba(15, 10, 8, 0.72);
    backdrop-filter: blur(5px);
}
.modal-apoie-fundo.aberto {
    display: flex;
}
.modal-apoie-caixa {
    width: min(94vw, 480px);
    background: var(--fundo-superficie, #ffffff);
    color: var(--texto-principal, #231b18);
    border: 1px solid var(--borda-forte, #c9b7a8);
    border-radius: 18px;
    box-shadow: 0 18px 46px rgba(0, 0, 0, 0.42);
    overflow: hidden;
    animation: folivroModalSurgir 0.2s cubic-bezier(0.16, 1, 0.3, 1);
}
.modal-apoie-topo {
    display: flex;
    align-items: center;
    justify-content: space-between;
    gap: 12px;
    padding: 18px 22px;
    border-bottom: 1px solid var(--borda-suave, #e2d6cb);
    background: var(--fundo-destaque, #fcf9f5);
}
.modal-apoie-topo h2 {
    margin: 0;
    font-family: var(--fonte-literaria, Georgia, serif);
    font-size: 19px;
    display: flex;
    align-items: center;
    gap: 8px;
}
.modal-apoie-fechar {
    background: transparent;
    border: 0;
    color: var(--texto-apoio, #6e5d54);
    cursor: pointer;
    padding: 6px;
    border-radius: 8px;
    display: inline-flex;
    align-items: center;
    justify-content: center;
}
.modal-apoie-fechar:hover {
    background: var(--borda-suave, #e2d6cb);
    color: var(--texto-principal, #231b18);
}
.modal-apoie-corpo {
    padding: 20px 22px 22px;
    display: flex;
    flex-direction: column;
    gap: 16px;
}
.modal-apoie-msg {
    margin: 0;
    padding: 12px 14px;
    border-radius: 10px;
    background: var(--fundo-superficie-suave, #faf6f2);
    border-left: 3px solid var(--cor-dourado-foco, #d4943a);
    font-style: italic;
    color: var(--texto-secundario, #54463f);
    font-size: 14px;
}
.bloco-pix-apoio {
    display: flex;
    flex-direction: column;
    gap: 8px;
    padding: 14px;
    border-radius: 12px;
    border: 1px solid var(--borda-suave, #e2d6cb);
    background: var(--fundo-superficie-suave, #faf6f2);
}
.bloco-pix-apoio strong {
    font-size: 13px;
    color: var(--texto-principal, #231b18);
}
.linha-chave-pix {
    display: flex;
    align-items: center;
    gap: 8px;
}
.campo-chave-pix-leitura {
    flex: 1 1 auto;
    min-width: 0;
    padding: 9px 12px;
    border-radius: 8px;
    border: 1px solid var(--borda-forte, #c9b7a8);
    background: var(--fundo-superficie, #ffffff);
    color: var(--texto-principal, #231b18);
    font-family: ui-monospace, SFMono-Regular, Menlo, Consolas, monospace;
    font-size: 13px;
}
.btn-copiar-pix {
    padding: 9px 14px;
    border-radius: 8px;
    border: 0;
    background: var(--cor-vermelho-acao, #6b130d);
    color: #ffffff;
    font-size: 13px;
    font-weight: 700;
    cursor: pointer;
    white-space: nowrap;
    transition: opacity 0.16s ease, transform 0.14s ease;
}
.btn-copiar-pix:hover {
    opacity: 0.92;
    transform: translateY(-1px);
}
.btn-abrir-link-apoio {
    display: inline-flex;
    align-items: center;
    justify-content: center;
    gap: 8px;
    width: 100%;
    padding: 11px 16px;
    border-radius: 10px;
    background: var(--cor-vermelho-acao, #6b130d);
    color: #ffffff !important;
    font-weight: 700;
    font-size: 14px;
    text-decoration: none;
    box-sizing: border-box;
    transition: opacity 0.16s ease, transform 0.14s ease;
}
.btn-abrir-link-apoio:hover {
    opacity: 0.92;
    transform: translateY(-1px);
}
.aviso-apoio-externo {
    margin: 0;
    font-size: 12px;
    line-height: 1.45;
    color: var(--texto-apoio, #6e5d54);
}
</style>

<div class="modal-apoie-fundo" id="modalApoieCriador" role="dialog" aria-modal="true" aria-labelledby="tituloModalApoieCriador" hidden>
    <div class="modal-apoie-caixa">
        <header class="modal-apoie-topo">
            <h2 id="tituloModalApoieCriador">
                <svg viewBox="0 0 24 24" width="20" height="20" fill="currentColor" style="color:#d9463e;" aria-hidden="true">
                    <path d="M12 21.35l-1.45-1.32C5.4 15.36 2 12.28 2 8.5 2 5.42 4.42 3 7.5 3c1.74 0 3.41.81 4.5 2.09C13.09 3.81 14.76 3 16.5 3 19.58 3 22 5.42 22 8.5c0 3.78-3.4 6.86-8.55 11.54L12 21.35z"/>
                </svg>
                <span>Apoie <?= folivro_escape($apoioAutorNome) ?></span>
            </h2>
            <button type="button" class="modal-apoie-fechar" id="fecharModalApoieCriador" aria-label="Fechar janela de apoio ao criador">
                <svg viewBox="0 0 24 24" width="20" height="20" fill="none" stroke="currentColor" stroke-width="2.2" stroke-linecap="round" stroke-linejoin="round">
                    <line x1="18" y1="6" x2="6" y2="18"></line>
                    <line x1="6" y1="6" x2="18" y2="18"></line>
                </svg>
            </button>
        </header>

        <div class="modal-apoie-corpo">
            <?php if ($apoioAutorMsg !== ''): ?>
                <p class="modal-apoie-msg">“<?= folivro_escape($apoioAutorMsg) ?>”</p>
            <?php else: ?>
                <p style="margin:0; font-size:14px; color:var(--texto-secundario);">
                    Gostou da leitura? Você pode apoiar o trabalho independente de <strong><?= folivro_escape($apoioAutorNome) ?></strong> diretamente pelos canais abaixo.
                </p>
            <?php endif; ?>

            <?php if ($apoioAutorPix !== ''): ?>
                <div class="bloco-pix-apoio">
                    <strong>Chave Pix do(a) autor(a)</strong>
                    <div class="linha-chave-pix">
                        <input type="text" class="campo-chave-pix-leitura" id="campoChavePixApoio" value="<?= folivro_escape($apoioAutorPix) ?>" readonly aria-label="Chave Pix do autor">
                        <button type="button" class="btn-copiar-pix" id="btnCopiarChavePixApoio">Copiar Pix</button>
                    </div>
                    <small id="feedbackCopiarPixApoio" style="font-size:12px; color:#1f6f3b; display:none;" role="status">Chave Pix copiada para a área de transferência!</small>
                </div>
            <?php endif; ?>

            <?php if ($apoioAutorLink !== ''): ?>
                <a href="<?= folivro_escape($apoioAutorLink) ?>" target="_blank" rel="noopener noreferrer" class="btn-abrir-link-apoio">
                    <span>Abrir página de apoio externa ↗</span>
                </a>
            <?php endif; ?>

            <p class="aviso-apoio-externo">
                O apoio é 100% voluntário e ocorre diretamente fora do FOLIVRO. A plataforma não processa pagamentos, não armazena dados bancários e não cobra taxas sobre contribuições aos autores.
            </p>
        </div>
    </div>
</div>

<script>
(() => {
    const modal = document.getElementById('modalApoieCriador');
    const btnFechar = document.getElementById('fecharModalApoieCriador');
    const btnCopiar = document.getElementById('btnCopiarChavePixApoio');
    const inputPix = document.getElementById('campoChavePixApoio');
    const feedbackPix = document.getElementById('feedbackCopiarPixApoio');
    if (!modal) return;

    function abrirApoio() {
        modal.hidden = false;
        modal.classList.add('aberto');
        document.body.style.overflow = 'hidden';
        if (btnFechar) btnFechar.focus();
    }
    function fecharApoio() {
        modal.classList.remove('aberto');
        modal.hidden = true;
        document.body.style.overflow = '';
    }

    document.querySelectorAll('.js-abrir-apoie-criador').forEach((btn) => {
        btn.addEventListener('click', abrirApoio);
    });
    if (btnFechar) btnFechar.addEventListener('click', fecharApoio);
    modal.addEventListener('click', (ev) => {
        if (ev.target === modal) fecharApoio();
    });
    document.addEventListener('keydown', (ev) => {
        if (ev.key === 'Escape' && modal.classList.contains('aberto')) {
            fecharApoio();
        }
    });

    if (btnCopiar && inputPix) {
        btnCopiar.addEventListener('click', async () => {
            try {
                if (navigator.clipboard && navigator.clipboard.writeText) {
                    await navigator.clipboard.writeText(inputPix.value);
                } else {
                    inputPix.select();
                    document.execCommand('copy');
                }
                btnCopiar.textContent = 'Copiado ✓';
                if (feedbackPix) feedbackPix.style.display = 'block';
                setTimeout(() => {
                    btnCopiar.textContent = 'Copiar Pix';
                }, 2500);
            } catch (_) {
                inputPix.select();
            }
        });
    }
})();
</script>
