<?php
/**
 * Componente reutilizável: Modal "Apoie o criador" com QR Code Pix oficial (BR Code EMV) — FOLIVRO
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

/* Card de apoio no final da leitura da obra */
.card-apoio-fim-leitura {
    display: flex;
    align-items: center;
    justify-content: space-between;
    gap: 16px;
    flex-wrap: wrap;
    margin: 22px 0 8px;
    padding: 18px 22px;
    border-radius: 16px;
    border: 1px solid var(--borda-suave, #e2d6cb);
    border-left: 4px solid var(--cor-dourado-foco, #d4943a);
    background: var(--fundo-superficie, #ffffff);
    box-shadow: var(--sombra-card, 0 4px 14px rgba(0, 0, 0, 0.08));
}
.card-apoio-fim-leitura h3 {
    margin: 0 0 4px;
    font-family: var(--fonte-literaria, Georgia, serif);
    font-size: 17px;
    color: var(--texto-principal, #231b18);
}
.card-apoio-fim-leitura p {
    margin: 0;
    font-size: 13.5px;
    color: var(--texto-secundario, #54463f);
    max-width: 56ch;
}

/* Modal */
.modal-apoie-fundo {
    position: fixed;
    inset: 0;
    z-index: 21000;
    display: none;
    align-items: center;
    justify-content: center;
    padding: 16px;
    background: rgba(15, 10, 8, 0.74);
    backdrop-filter: blur(6px);
}
.modal-apoie-fundo.aberto {
    display: flex;
}
.modal-apoie-caixa {
    width: min(94vw, 510px);
    max-height: min(92vh, 760px);
    background: var(--fundo-superficie, #ffffff);
    color: var(--texto-principal, #231b18);
    border: 1px solid var(--borda-forte, #c9b7a8);
    border-radius: 18px;
    box-shadow: 0 18px 46px rgba(0, 0, 0, 0.45);
    display: flex;
    flex-direction: column;
    overflow: hidden;
    animation: folivroModalSurgir 0.2s cubic-bezier(0.16, 1, 0.3, 1);
}
.modal-apoie-topo {
    display: flex;
    align-items: center;
    justify-content: space-between;
    gap: 12px;
    padding: 16px 22px;
    border-bottom: 1px solid var(--borda-suave, #e2d6cb);
    background: var(--fundo-destaque, #fcf9f5);
    flex-shrink: 0;
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
    padding: 18px 22px 22px;
    display: flex;
    flex-direction: column;
    gap: 15px;
    overflow-y: auto;
}
.modal-apoie-msg {
    margin: 0;
    padding: 11px 14px;
    border-radius: 10px;
    background: var(--fundo-superficie-suave, #faf6f2);
    border-left: 3px solid var(--cor-dourado-foco, #d4943a);
    font-style: italic;
    color: var(--texto-secundario, #54463f);
    font-size: 13.5px;
    line-height: 1.45;
}

/* Valores sugeridos Pix */
.bloco-pix-interativo {
    display: flex;
    flex-direction: column;
    gap: 14px;
    padding: 16px;
    border-radius: 14px;
    border: 1px solid var(--borda-suave, #e2d6cb);
    background: var(--fundo-superficie-suave, #faf6f2);
}
.topo-pix-interativo {
    display: flex;
    align-items: center;
    justify-content: space-between;
    gap: 8px;
    flex-wrap: wrap;
}
.topo-pix-interativo strong {
    font-size: 13.5px;
    color: var(--texto-principal, #231b18);
}
.selo-valor-pix-ativo {
    font-size: 12px;
    font-weight: 700;
    padding: 3px 10px;
    border-radius: 999px;
    background: color-mix(in srgb, var(--cor-dourado-foco, #d4943a) 22%, transparent);
    color: var(--texto-principal, #231b18);
    border: 1px solid color-mix(in srgb, var(--cor-dourado-foco, #d4943a) 45%, transparent);
}
.grade-valores-pix {
    display: grid;
    grid-template-columns: repeat(4, minmax(0, 1fr));
    gap: 7px;
}
.btn-valor-pix {
    appearance: none;
    padding: 8px 6px;
    border-radius: 10px;
    border: 1.5px solid var(--borda-forte, #c9b7a8);
    background: var(--fundo-superficie, #ffffff);
    color: var(--texto-principal, #231b18);
    font-family: var(--fonte-interface, system-ui, sans-serif);
    font-size: 12.5px;
    font-weight: 700;
    cursor: pointer;
    text-align: center;
    transition: border-color 0.15s ease, background 0.15s ease, color 0.15s ease, transform 0.12s ease;
}
.btn-valor-pix:hover {
    border-color: var(--cor-vermelho-acao, #6b130d);
    transform: translateY(-1px);
}
.btn-valor-pix.ativo {
    background: var(--cor-vermelho-acao, #6b130d);
    border-color: var(--cor-vermelho-acao, #6b130d);
    color: #ffffff;
}
.linha-valor-custom-pix {
    display: flex;
    align-items: center;
    gap: 8px;
}
.linha-valor-custom-pix label {
    font-size: 12.5px;
    font-weight: 600;
    color: var(--texto-secundario, #54463f);
    white-space: nowrap;
}
.input-valor-custom-pix {
    width: 118px;
    padding: 6px 10px;
    border-radius: 8px;
    border: 1px solid var(--borda-forte, #c9b7a8);
    background: var(--fundo-superficie, #ffffff);
    color: var(--texto-principal, #231b18);
    font-size: 13px;
    font-weight: 600;
}

/* QR Code */
.area-qrcode-pix {
    display: flex;
    flex-direction: column;
    align-items: center;
    gap: 8px;
    padding: 12px;
    border-radius: 12px;
    background: var(--fundo-superficie, #ffffff);
    border: 1px dashed var(--borda-forte, #c9b7a8);
}
.caixa-qrcode-branca {
    width: 184px;
    height: 184px;
    padding: 10px;
    border-radius: 12px;
    background: #ffffff;
    display: flex;
    align-items: center;
    justify-content: center;
    box-shadow: 0 2px 10px rgba(0, 0, 0, 0.12);
}
.caixa-qrcode-branca canvas,
.caixa-qrcode-branca img {
    width: 164px !important;
    height: 164px !important;
    display: block;
}
.legenda-qrcode-pix {
    font-size: 12px;
    color: var(--texto-secundario, #54463f);
    text-align: center;
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
    font-size: 12px;
}
.btn-copiar-pix {
    padding: 9px 14px;
    border-radius: 8px;
    border: 0;
    background: var(--cor-vermelho-acao, #6b130d);
    color: #ffffff;
    font-size: 12.5px;
    font-weight: 700;
    cursor: pointer;
    white-space: nowrap;
    transition: opacity 0.16s ease, transform 0.14s ease;
}
.btn-copiar-pix:hover {
    opacity: 0.92;
    transform: translateY(-1px);
}
.btn-copiar-pix.secundario {
    background: transparent;
    color: var(--texto-principal, #231b18);
    border: 1px solid var(--borda-forte, #c9b7a8);
}
.btn-copiar-pix.secundario:hover {
    border-color: var(--cor-vermelho-acao, #6b130d);
    color: var(--cor-vermelho-acao, #6b130d);
}
.detalhes-chave-direta {
    font-size: 12px;
    color: var(--texto-secundario, #54463f);
}
.detalhes-chave-direta summary {
    cursor: pointer;
    font-weight: 600;
    margin-bottom: 6px;
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
    font-size: 11.5px;
    line-height: 1.45;
    color: var(--texto-apoio, #6e5d54);
}
@media (max-width: 480px) {
    .grade-valores-pix {
        grid-template-columns: repeat(2, minmax(0, 1fr));
    }
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
                <p style="margin:0; font-size:13.5px; color:var(--texto-secundario);">
                    Gostou da leitura? Apoie o trabalho independente de <strong><?= folivro_escape($apoioAutorNome) ?></strong> diretamente pelos canais abaixo.
                </p>
            <?php endif; ?>

            <?php if ($apoioAutorPix !== ''): ?>
                <div class="bloco-pix-interativo" id="blocoPixInterativo"
                     data-pix-chave="<?= folivro_escape($apoioAutorPix) ?>"
                     data-pix-nome="<?= folivro_escape($apoioAutorNome) ?>">
                    <div class="topo-pix-interativo">
                        <strong>Escolha um valor de apoio via Pix</strong>
                        <span class="selo-valor-pix-ativo" id="seloValorPixAtivo">Valor livre</span>
                    </div>

                    <div class="grade-valores-pix" role="group" aria-label="Valores sugeridos para apoio via Pix">
                        <button type="button" class="btn-valor-pix ativo" data-valor-pix="0">Valor livre</button>
                        <button type="button" class="btn-valor-pix" data-valor-pix="5">☕ R$ 5</button>
                        <button type="button" class="btn-valor-pix" data-valor-pix="15">📖 R$ 15</button>
                        <button type="button" class="btn-valor-pix" data-valor-pix="30">✨ R$ 30</button>
                    </div>

                    <div class="linha-valor-custom-pix">
                        <label for="inputValorCustomPix">Outro valor (R$):</label>
                        <input type="number" id="inputValorCustomPix" class="input-valor-custom-pix" min="0" max="10000" step="0.50" placeholder="Ex.: 10,00" aria-label="Informar outro valor em reais">
                    </div>

                    <div class="area-qrcode-pix">
                        <div class="caixa-qrcode-branca" id="qrCodePixContainer" aria-label="QR Code Pix para apoio ao autor"></div>
                        <span class="legenda-qrcode-pix" id="legendaQrPix">Aponte a câmera no app do seu banco para apoiar</span>
                    </div>

                    <div>
                        <div class="linha-chave-pix">
                            <input type="text" class="campo-chave-pix-leitura" id="campoBrCodePixApoio" value="" readonly aria-label="Código Pix Copia e Cola">
                            <button type="button" class="btn-copiar-pix" id="btnCopiarBrCodePixApoio">Copiar Pix</button>
                        </div>
                        <small id="feedbackCopiarPixApoio" style="font-size:12px; color:#1f6f3b; display:none; margin-top:5px;" role="status">Código Pix Copia e Cola copiado!</small>
                    </div>

                    <details class="detalhes-chave-direta">
                        <summary>Prefere copiar apenas a chave Pix?</summary>
                        <div class="linha-chave-pix">
                            <input type="text" class="campo-chave-pix-leitura" id="campoChavePixApoio" value="<?= folivro_escape($apoioAutorPix) ?>" readonly aria-label="Chave Pix do autor">
                            <button type="button" class="btn-copiar-pix secundario" id="btnCopiarChavePixApoio">Copiar chave</button>
                        </div>
                    </details>
                </div>
            <?php endif; ?>

            <?php if ($apoioAutorLink !== ''): ?>
                <a href="<?= folivro_escape($apoioAutorLink) ?>" target="_blank" rel="noopener noreferrer" class="btn-abrir-link-apoio">
                    <span>Abrir página de apoio externa ↗</span>
                </a>
            <?php endif; ?>

            <p class="aviso-apoio-externo">
                O apoio é 100% voluntário e vai diretamente para o(a) autor(a) fora do FOLIVRO. A plataforma não processa pagamentos, não armazena dados bancários e não cobra taxas.
            </p>
        </div>
    </div>
</div>

<script>
(() => {
    const modal = document.getElementById('modalApoieCriador');
    const btnFechar = document.getElementById('fecharModalApoieCriador');
    const blocoPix = document.getElementById('blocoPixInterativo');
    if (!modal) return;

    // --- Gerador oficial de Pix Estático (BR Code EMV / CRC16-CCITT) ---
    function normalizarTextoPix(str, maxLen) {
        return String(str || '')
            .normalize('NFD')
            .replace(/[\u0300-\u036f]/g, '')
            .replace(/[^A-Za-z0-9 ]/g, ' ')
            .replace(/\s+/g, ' ')
            .trim()
            .toUpperCase()
            .slice(0, maxLen) || 'AUTOR FOLIVRO';
    }

    function tlv(id, valor) {
        const v = String(valor);
        const len = String(new TextEncoder().encode(v).length).padStart(2, '0');
        return `${id}${len}${v}`;
    }

    function crc16Ccitt(payload) {
        let crc = 0xFFFF;
        const bytes = new TextEncoder().encode(payload);
        for (let i = 0; i < bytes.length; i++) {
            crc ^= (bytes[i] << 8);
            for (let j = 0; j < 8; j++) {
                crc = (crc & 0x8000) ? ((crc << 1) ^ 0x1021) : (crc << 1);
                crc &= 0xFFFF;
            }
        }
        return crc.toString(16).toUpperCase().padStart(4, '0');
    }

    function gerarBrCodePix(chave, nomeRecebedor, valorNum) {
        const gui = tlv('00', 'br.gov.bcb.pix');
        const key = tlv('01', chave.trim());
        const merchantAccount = tlv('26', gui + key);
        const nomeLimpo = normalizarTextoPix(nomeRecebedor, 25);

        let payload = '';
        payload += tlv('00', '01');
        payload += merchantAccount;
        payload += tlv('52', '0000');
        payload += tlv('53', '986');
        if (valorNum > 0) {
            payload += tlv('54', valorNum.toFixed(2));
        }
        payload += tlv('58', 'BR');
        payload += tlv('59', nomeLimpo);
        payload += tlv('60', 'BRASIL');
        payload += tlv('62', tlv('05', 'FOLIVRO'));
        payload += '6304';
        return payload + crc16Ccitt(payload);
    }

    let qrLibCarregando = null;
    function garantirQrLib() {
        if (window.QRCode) return Promise.resolve(true);
        if (qrLibCarregando) return qrLibCarregando;
        qrLibCarregando = new Promise((resolve) => {
            const s = document.createElement('script');
            s.src = 'https://cdnjs.cloudflare.com/ajax/libs/qrcodejs/1.0.0/qrcode.min.js';
            s.onload = () => resolve(Boolean(window.QRCode));
            s.onerror = () => resolve(false);
            document.head.appendChild(s);
        });
        return qrLibCarregando;
    }

    let valorAtualPix = 0;

    async function atualizarPixApoio(valor) {
        if (!blocoPix) return;
        valorAtualPix = Math.max(0, Math.min(10000, Number(valor) || 0));
        const chave = blocoPix.getAttribute('data-pix-chave') || '';
        const nome = blocoPix.getAttribute('data-pix-nome') || 'Autor FOLIVRO';
        const brCode = gerarBrCodePix(chave, nome, valorAtualPix);

        const campoBr = document.getElementById('campoBrCodePixApoio');
        const seloValor = document.getElementById('seloValorPixAtivo');
        const legendaQr = document.getElementById('legendaQrPix');
        const qrBox = document.getElementById('qrCodePixContainer');

        if (campoBr) campoBr.value = brCode;
        if (seloValor) {
            seloValor.textContent = valorAtualPix > 0
                ? `R$ ${valorAtualPix.toFixed(2).replace('.', ',')}`
                : 'Valor livre';
        }
        if (legendaQr) {
            legendaQr.textContent = valorAtualPix > 0
                ? `QR Code pronto para R$ ${valorAtualPix.toFixed(2).replace('.', ',')} · Escaneie no app do banco`
                : 'Escaneie no app do seu banco e escolha o valor que desejar';
        }

        if (qrBox) {
            qrBox.innerHTML = '';
            const temLib = await garantirQrLib();
            if (temLib && window.QRCode) {
                new window.QRCode(qrBox, {
                    text: brCode,
                    width: 164,
                    height: 164,
                    colorDark: '#1b1412',
                    colorLight: '#ffffff',
                    correctLevel: window.QRCode.CorrectLevel.M
                });
            } else {
                const img = document.createElement('img');
                img.alt = 'QR Code Pix para apoio ao autor';
                img.src = `https://api.qrserver.com/v1/create-qr-code/?size=164x164&margin=2&data=${encodeURIComponent(brCode)}`;
                qrBox.appendChild(img);
            }
        }
    }

    if (blocoPix) {
        const botoesValor = blocoPix.querySelectorAll('.btn-valor-pix');
        const inputCustom = document.getElementById('inputValorCustomPix');

        botoesValor.forEach((btn) => {
            btn.addEventListener('click', () => {
                botoesValor.forEach((b) => b.classList.remove('ativo'));
                btn.classList.add('ativo');
                if (inputCustom) inputCustom.value = '';
                atualizarPixApoio(Number(btn.getAttribute('data-valor-pix')) || 0);
            });
        });

        if (inputCustom) {
            let debounceCustom = null;
            inputCustom.addEventListener('input', () => {
                botoesValor.forEach((b) => b.classList.remove('ativo'));
                clearTimeout(debounceCustom);
                debounceCustom = setTimeout(() => {
                    const v = parseFloat(inputCustom.value.replace(',', '.'));
                    atualizarPixApoio(Number.isFinite(v) && v > 0 ? v : 0);
                }, 220);
            });
        }
    }

    async function copiarTexto(texto, btn, textoOrig, feedbackEl, msgFeedback) {
        try {
            if (navigator.clipboard && navigator.clipboard.writeText) {
                await navigator.clipboard.writeText(texto);
            } else {
                const tmp = document.createElement('textarea');
                tmp.value = texto;
                document.body.appendChild(tmp);
                tmp.select();
                document.execCommand('copy');
                document.body.removeChild(tmp);
            }
            btn.textContent = 'Copiado ✓';
            if (feedbackEl) {
                feedbackEl.textContent = msgFeedback;
                feedbackEl.style.display = 'block';
            }
            setTimeout(() => { btn.textContent = textoOrig; }, 2400);
        } catch (_) {}
    }

    const btnCopiarBr = document.getElementById('btnCopiarBrCodePixApoio');
    const campoBr = document.getElementById('campoBrCodePixApoio');
    const feedbackPix = document.getElementById('feedbackCopiarPixApoio');
    if (btnCopiarBr && campoBr) {
        btnCopiarBr.addEventListener('click', () => {
            copiarTexto(campoBr.value, btnCopiarBr, 'Copiar Pix', feedbackPix, 'Código Pix Copia e Cola copiado!');
        });
    }

    const btnCopiarChave = document.getElementById('btnCopiarChavePixApoio');
    const campoChave = document.getElementById('campoChavePixApoio');
    if (btnCopiarChave && campoChave) {
        btnCopiarChave.addEventListener('click', () => {
            copiarTexto(campoChave.value, btnCopiarChave, 'Copiar chave', feedbackPix, 'Chave Pix copiada!');
        });
    }

    function abrirApoio() {
        modal.hidden = false;
        modal.classList.add('aberto');
        document.body.style.overflow = 'hidden';
        if (blocoPix) {
            atualizarPixApoio(valorAtualPix);
        }
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
})();
</script>
