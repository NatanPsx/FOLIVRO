<?php
declare(strict_types=1);

/**
 * Componente Global de Configurações do FOLIVRO
 * Inclui:
 * 1. Botão circular com as 3 barrinhas no canto superior DIREITO da página.
 * 2. Modal com abas:
 *    - Aba 1: Configurações de Perfil (dados, atalhos e edição rápida).
 *    - Aba 2: Temas de Cores para o Site (comutador claro/escuro e paletas completas).
 *    - Aba 3: Moderação (EXCLUSIVA para admin — único ponto de entrada para o painel de moderação).
 */

if (!function_exists('folivro_start_session')) {
    require_once __DIR__ . '/../config/auth.php';
}
folivro_start_session();

$cfgUserId = (int) ($_SESSION['id_usuario'] ?? 0);
$cfgNivelUsuario = (string) ($_SESSION['nivel_usuario'] ?? 'leitor');
$cfgIsAdmin = ($cfgNivelUsuario === 'admin');

// Recupera dados do perfil atual
$cfgPdo = function_exists('folivro_database') ? folivro_database() : null;
$cfgUser = null;
if ($cfgPdo && $cfgUserId > 0) {
    try {
        $stmtCfgUser = $cfgPdo->prepare(
            "SELECT nome_usuario, email_usuario, data_nascimento_usuario, foto_usuario, nivel_usuario, biografia_usuario, data_cadastro_usuario 
               FROM usuario 
              WHERE id_usuario = ? 
              LIMIT 1"
        );
        $stmtCfgUser->execute([$cfgUserId]);
        $cfgUser = $stmtCfgUser->fetch(PDO::FETCH_ASSOC);
    } catch (Throwable $e) {
        $cfgUser = null;
    }
}

$cfgNome = (string) ($cfgUser['nome_usuario'] ?? ($_SESSION['nome_usuario'] ?? 'Meu Perfil'));
$cfgEmail = (string) ($cfgUser['email_usuario'] ?? '');
$cfgDataNascimento = (string) ($cfgUser['data_nascimento_usuario'] ?? '');
$cfgIdade = function_exists('folivro_calcular_idade') ? folivro_calcular_idade($cfgDataNascimento) : null;
$cfgFoto = (string) ($cfgUser['foto_usuario'] ?? '');
$cfgBio = (string) ($cfgUser['biografia_usuario'] ?? '');
$cfgDataCadastro = !empty($cfgUser['data_cadastro_usuario']) ? date('d/m/Y', strtotime((string) $cfgUser['data_cadastro_usuario'])) : '';
$cfgCsrf = function_exists('folivro_csrf_token') ? folivro_csrf_token() : '';

// Dados de moderação: EXCLUSIVAMENTE para administradores (segurança estrita no servidor)
$cfgModStats = [
    'denuncias' => 0,
    'obras' => 0,
    'chat' => 0,
    'recentes' => []
];
$cfgPagamentoSimuladoAtivo = true;
$cfgAprovacaoAutomaticaAtiva = false;

if ($cfgIsAdmin && $cfgPdo) {
    $cfgPagamentoSimuladoAtivo = folivro_sistema_config_obter($cfgPdo, 'payment_mode', strtolower((string) (getenv('PAYMENT_MODE') ?: 'simulated'))) !== 'real';
    $cfgAprovacaoAutomaticaAtiva = folivro_aprovacao_automatica_obras_ativa($cfgPdo);
    try {
        $stmtStats = $cfgPdo->query("
            SELECT
                (SELECT COUNT(*) FROM denuncias WHERE status_denuncias IN ('aberta', 'em_analise')) AS total_denuncias,
                (SELECT COUNT(*) FROM autor_dos_livros WHERE revisao_publicacao = 'pendente') AS total_obras,
                (SELECT COUNT(*) FROM denuncias WHERE tipo_denuncias = 'mensagem_chat' AND status_denuncias IN ('aberta', 'em_analise')) AS total_chat
        ");
        $rowStats = $stmtStats ? $stmtStats->fetch(PDO::FETCH_ASSOC) : null;
        if ($rowStats) {
            $cfgModStats['denuncias'] = (int) ($rowStats['total_denuncias'] ?? 0);
            $cfgModStats['obras'] = (int) ($rowStats['total_obras'] ?? 0);
            $cfgModStats['chat'] = (int) ($rowStats['total_chat'] ?? 0);
        }

        $stmtRecent = $cfgPdo->query("
            SELECT id_denuncias, tipo_denuncias, motivo_denuncia, prioridade_denuncia, data_denuncia
              FROM denuncias
             WHERE status_denuncias IN ('aberta', 'em_analise')
             ORDER BY FIELD(prioridade_denuncia, 'alta', 'media', 'baixa'), data_denuncia DESC
             LIMIT 4
        ");
        $cfgModStats['recentes'] = $stmtRecent ? ($stmtRecent->fetchAll(PDO::FETCH_ASSOC) ?: []) : [];
    } catch (Throwable $e) {
        // Falha graciosa
    }
}
$cfgTotalPendencias = $cfgModStats['denuncias'] + $cfgModStats['obras'];
?>

<!-- Estilos Completos e Autocontidos do Botão e Modal de Configurações -->
<style id="folivro-config-estilos-completos">
/* Botão fixo no canto superior direito */
.folivro-topo-fixo-acoes {
    position: fixed !important;
    top: 18px !important;
    right: 24px !important;
    left: auto !important;
    bottom: auto !important;
    z-index: 9999 !important;
    display: flex !important;
    align-items: center !important;
    pointer-events: none !important;
}

.botao-configuracoes-topo {
    pointer-events: auto !important;
    display: inline-flex !important;
    align-items: center !important;
    justify-content: center !important;
    width: 44px !important;
    height: 44px !important;
    padding: 0 !important;
    border-radius: 50% !important;
    background: var(--fundo-superficie, #ffffff) !important;
    color: var(--texto-principal, #231b18) !important;
    border: 2px solid var(--borda-suave, #e2d6cb) !important;
    box-shadow: 0 4px 14px rgba(0, 0, 0, 0.16) !important;
    cursor: pointer !important;
    transition: transform 0.2s ease, background 0.2s ease, border-color 0.2s ease, color 0.2s ease, box-shadow 0.2s ease !important;
    position: relative !important;
}

.botao-configuracoes-topo:hover {
    transform: scale(1.08) !important;
    background: var(--fundo-superficie-suave, #faf6f2) !important;
    border-color: var(--cor-vermelho-acao, #6b130d) !important;
    color: var(--cor-vermelho-acao, #6b130d) !important;
    box-shadow: 0 6px 20px rgba(0, 0, 0, 0.22) !important;
}

.botao-configuracoes-topo:focus-visible {
    outline: 3px solid var(--cor-dourado-foco, #e7ae5c) !important;
    outline-offset: 2px !important;
}

.icone-barras-config {
    width: 32px !important;
    height: 32px !important;
    display: block !important;
    stroke: currentColor !important;
}

.ponto-notificacao-admin {
    position: absolute !important;
    top: -3px !important;
    right: -3px !important;
    display: inline-flex !important;
    align-items: center !important;
    justify-content: center !important;
    min-width: 18px !important;
    height: 18px !important;
    padding: 0 5px !important;
    font-size: 10.5px !important;
    font-weight: 700 !important;
    color: #ffffff !important;
    background: #c5221f !important;
    border-radius: 999px !important;
    border: 2px solid var(--fundo-superficie, #ffffff) !important;
    line-height: 1 !important;
}

/* Modal de Configurações (Overlay) */
.modal-configuracoes-fundo {
    position: fixed !important;
    inset: 0 !important;
    z-index: 20000 !important;
    display: none; /* Controlado via inline style e JS */
    align-items: center !important;
    justify-content: center !important;
    background: rgba(18, 12, 10, 0.72) !important;
    backdrop-filter: blur(5px) !important;
    padding: 16px !important;
}

.modal-configuracoes-caixa {
    width: min(94vw, 680px) !important;
    max-height: min(90vh, 760px) !important;
    background: var(--fundo-superficie, #ffffff) !important;
    color: var(--texto-principal, #231b18) !important;
    border: 1px solid var(--borda-forte, #c9b7a8) !important;
    border-radius: 16px !important;
    box-shadow: 0 14px 40px rgba(0, 0, 0, 0.4) !important;
    display: flex !important;
    flex-direction: column !important;
    overflow: hidden !important;
    animation: folivroModalSurgir 0.22s cubic-bezier(0.16, 1, 0.3, 1) !important;
}

@keyframes folivroModalSurgir {
    from { opacity: 0; transform: scale(0.96) translateY(12px); }
    to { opacity: 1; transform: scale(1) translateY(0); }
}

.modal-configuracoes-cabecalho {
    display: flex !important;
    align-items: center !important;
    justify-content: space-between !important;
    padding: 18px 24px !important;
    border-bottom: 1px solid var(--borda-suave, #e2d6cb) !important;
    background: var(--fundo-destaque, #fcf9f5) !important;
}

.modal-config-titulo-bloco {
    display: flex !important;
    align-items: center !important;
    gap: 14px !important;
}

.icone-modal-emblema {
    width: 40px !important;
    height: 40px !important;
    border-radius: 50% !important;
    background: var(--cor-vermelho-acao, #6b130d) !important;
    color: #ffffff !important;
    display: flex !important;
    align-items: center !important;
    justify-content: center !important;
    box-shadow: 0 2px 8px rgba(107, 19, 13, 0.25) !important;
}

.modal-config-titulo-bloco h2 {
    margin: 0 !important;
    font-family: var(--fonte-literaria, Georgia, serif) !important;
    font-size: 20px !important;
    font-weight: 700 !important;
    line-height: 1.2 !important;
    color: var(--texto-principal, #231b18) !important;
}

.modal-config-sub {
    margin: 2px 0 0 !important;
    font-size: 13px !important;
    color: var(--texto-secundario, #54463f) !important;
}

.botao-fechar-config {
    background: transparent !important;
    border: 0 !important;
    color: var(--texto-apoio, #6e5d54) !important;
    cursor: pointer !important;
    padding: 8px !important;
    border-radius: 8px !important;
    display: flex !important;
    align-items: center !important;
    justify-content: center !important;
    transition: background 0.15s ease, color 0.15s ease !important;
}

.botao-fechar-config:hover {
    background: var(--borda-suave, #e2d6cb) !important;
    color: var(--texto-principal, #231b18) !important;
}

.abas-config-nav {
    display: flex !important;
    gap: 4px !important;
    padding: 8px 20px 0 !important;
    border-bottom: 1px solid var(--borda-suave, #e2d6cb) !important;
    background: var(--fundo-superficie-suave, #faf6f2) !important;
    overflow-x: auto !important;
}

.aba-config-btn {
    display: inline-flex !important;
    align-items: center !important;
    gap: 8px !important;
    padding: 10px 18px !important;
    font-family: var(--fonte-interface, sans-serif) !important;
    font-size: 13.5px !important;
    font-weight: 600 !important;
    color: var(--texto-secundario, #54463f) !important;
    background: transparent !important;
    border: 0 !important;
    border-bottom: 2px solid transparent !important;
    border-radius: 6px 6px 0 0 !important;
    cursor: pointer !important;
    transition: all 0.18s ease !important;
    white-space: nowrap !important;
}

.aba-config-btn:hover {
    color: var(--texto-principal, #231b18) !important;
    background: rgba(0, 0, 0, 0.03) !important;
}

.aba-config-btn.ativa {
    color: var(--cor-vermelho-acao, #6b130d) !important;
    border-bottom-color: var(--cor-vermelho-acao, #6b130d) !important;
    background: var(--fundo-superficie, #ffffff) !important;
}

.aba-config-btn.aba-config-admin {
    margin-left: auto !important;
}

.selo-badge-admin {
    font-size: 10px !important;
    font-weight: 700 !important;
    text-transform: uppercase !important;
    letter-spacing: 0.5px !important;
    padding: 2px 6px !important;
    border-radius: 4px !important;
    background: #ffecb3 !important;
    color: #795548 !important;
}

.modal-configuracoes-corpo {
    flex: 1 !important;
    overflow-y: auto !important;
    padding: 24px !important;
}

.painel-config-item {
    display: none !important;
}

.painel-config-item.ativo {
    display: block !important;
}

/* Card Resumo do Perfil */
.cartao-usuario-resumo-modal {
    display: flex !important;
    align-items: center !important;
    gap: 18px !important;
    padding: 16px 20px !important;
    background: var(--fundo-destaque, #fcf9f5) !important;
    border: 1px solid var(--borda-suave, #e2d6cb) !important;
    border-radius: 12px !important;
    margin-bottom: 20px !important;
}

.avatar-usuario-modal {
    width: 68px !important;
    height: 68px !important;
    max-width: 68px !important;
    max-height: 68px !important;
    flex: 0 0 68px !important;
    border-radius: 50% !important;
    overflow: hidden !important;
    box-shadow: 0 2px 8px rgba(92, 0, 0, 0.18) !important;
}

.avatar-usuario-modal img,
.avatar-usuario-modal svg {
    width: 100% !important;
    height: 100% !important;
    max-width: 68px !important;
    max-height: 68px !important;
    object-fit: cover !important;
    display: block !important;
}

.dados-usuario-modal {
    flex: 1 !important;
    min-width: 0 !important;
}

.nome-linha-modal {
    display: flex !important;
    align-items: center !important;
    gap: 10px !important;
    flex-wrap: wrap !important;
    margin-bottom: 4px !important;
}

.nome-linha-modal h3 {
    margin: 0 !important;
    font-family: var(--fonte-literaria, Georgia, serif) !important;
    font-size: 18px !important;
    font-weight: 700 !important;
    color: var(--texto-principal, #231b18) !important;
}

.selo-nivel-usuario {
    font-size: 11px !important;
    font-weight: 700 !important;
    padding: 2px 8px !important;
    border-radius: 999px !important;
    text-transform: uppercase !important;
    letter-spacing: 0.4px !important;
}

.selo-nivel-usuario.selo-admin {
    background: #e6f4ea !important;
    color: #137333 !important;
    border: 1px solid #ceead6 !important;
}

.selo-nivel-usuario.selo-autor {
    background: #fef7e0 !important;
    color: #b06000 !important;
    border: 1px solid #feefc3 !important;
}

.selo-nivel-usuario.selo-leitor {
    background: var(--fundo-superficie-suave, #faf6f2) !important;
    color: var(--texto-apoio, #6e5d54) !important;
    border: 1px solid var(--borda-suave, #e2d6cb) !important;
}

.email-usuario-modal {
    margin: 0 0 2px !important;
    font-size: 13.5px !important;
    color: var(--texto-secundario, #54463f) !important;
    overflow-wrap: anywhere !important;
}

.membro-usuario-modal {
    margin: 0 !important;
    font-size: 12px !important;
    color: var(--texto-apoio, #6e5d54) !important;
}

.secao-config-bloco {
    margin-bottom: 22px !important;
}

.secao-config-bloco h4 {
    margin: 0 0 10px !important;
    font-family: var(--fonte-literaria, Georgia, serif) !important;
    font-size: 15px !important;
    font-weight: 700 !important;
    color: var(--texto-principal, #231b18) !important;
}

.texto-apoio-config {
    margin: 0 0 14px !important;
    font-size: 13px !important;
    color: var(--texto-secundario, #54463f) !important;
}

.grade-atalhos-perfil-modal {
    display: grid !important;
    grid-template-columns: repeat(auto-fit, minmax(180px, 1fr)) !important;
    gap: 10px !important;
}

.atalho-perfil-btn {
    display: flex !important;
    align-items: center !important;
    gap: 8px !important;
    padding: 10px 14px !important;
    background: var(--fundo-superficie-suave, #faf6f2) !important;
    color: var(--texto-principal, #231b18) !important;
    border: 1px solid var(--borda-suave, #e2d6cb) !important;
    border-radius: 8px !important;
    font-size: 13px !important;
    font-weight: 600 !important;
    text-decoration: none !important;
    transition: background 0.15s ease, border-color 0.15s ease, transform 0.15s ease !important;
}

.atalho-perfil-btn:hover {
    background: var(--fundo-destaque, #fcf9f5) !important;
    border-color: var(--borda-forte, #c9b7a8) !important;
    transform: translateY(-1px) !important;
}

.atalho-perfil-btn.destaque-admin {
    background: rgba(107, 19, 13, 0.08) !important;
    border-color: var(--cor-vermelho-acao, #6b130d) !important;
    color: var(--cor-vermelho-acao, #6b130d) !important;
}

.form-config-rapido {
    display: flex !important;
    flex-direction: column !important;
    gap: 12px !important;
    padding: 16px !important;
    background: var(--fundo-destaque, #fcf9f5) !important;
    border: 1px solid var(--borda-suave, #e2d6cb) !important;
    border-radius: 10px !important;
}

.campo-config-grupo {
    display: flex !important;
    flex-direction: column !important;
    gap: 5px !important;
}

.campo-config-grupo label {
    font-size: 12.5px !important;
    font-weight: 600 !important;
    color: var(--texto-secundario, #54463f) !important;
}

.campo-config-grupo input,
.campo-config-grupo textarea {
    padding: 9px 12px !important;
    font-family: inherit !important;
    font-size: 13.5px !important;
    color: var(--texto-principal, #231b18) !important;
    background: var(--fundo-superficie, #ffffff) !important;
    border: 1px solid var(--borda-forte, #c9b7a8) !important;
    border-radius: 6px !important;
}

.acoes-salvar-perfil-linha {
    display: flex !important;
    align-items: center !important;
    justify-content: space-between !important;
    gap: 12px !important;
    margin-top: 6px !important;
    flex-wrap: wrap !important;
}

.botao-salvar-perfil-modal {
    padding: 8px 16px !important;
    font-family: var(--fonte-interface, sans-serif) !important;
    font-size: 13.5px !important;
    font-weight: 600 !important;
    color: #ffffff !important;
    background: var(--cor-vermelho-acao, #6b130d) !important;
    border: 0 !important;
    border-radius: 6px !important;
    cursor: pointer !important;
}

.rodape-sessao-config {
    margin-top: 18px !important;
    padding-top: 16px !important;
    border-top: 1px solid var(--borda-suave, #e2d6cb) !important;
    display: flex !important;
    justify-content: flex-end !important;
}

.botao-sair-modal {
    padding: 8px 16px !important;
    font-size: 13px !important;
    font-weight: 600 !important;
    color: #c5221f !important;
    background: transparent !important;
    border: 1px solid rgba(197, 34, 31, 0.35) !important;
    border-radius: 6px !important;
    cursor: pointer !important;
}

/* Temas de Cores */
.seletor-modo-rapido {
    display: grid !important;
    grid-template-columns: 1fr 1fr !important;
    gap: 12px !important;
    margin-bottom: 22px !important;
}

.btn-modo-rapido {
    display: flex !important;
    align-items: center !important;
    justify-content: center !important;
    gap: 10px !important;
    padding: 12px 18px !important;
    background: var(--fundo-superficie-suave, #faf6f2) !important;
    color: var(--texto-principal, #231b18) !important;
    border: 2px solid var(--borda-suave, #e2d6cb) !important;
    border-radius: 10px !important;
    font-family: var(--fonte-interface, sans-serif) !important;
    font-size: 14px !important;
    font-weight: 600 !important;
    cursor: pointer !important;
}

.btn-modo-rapido.ativo {
    border-color: var(--cor-vermelho-acao, #6b130d) !important;
    background: var(--fundo-destaque, #fcf9f5) !important;
    color: var(--cor-vermelho-acao, #6b130d) !important;
}

.grade-temas-cores {
    display: grid !important;
    grid-template-columns: repeat(auto-fit, minmax(260px, 1fr)) !important;
    gap: 12px !important;
}

.card-opcao-tema {
    display: flex !important;
    align-items: flex-start !important;
    gap: 12px !important;
    padding: 14px 16px !important;
    background: var(--fundo-destaque, #fcf9f5) !important;
    color: var(--texto-principal, #231b18) !important;
    border: 2px solid var(--borda-suave, #e2d6cb) !important;
    border-radius: 12px !important;
    cursor: pointer !important;
    text-align: left !important;
    position: relative !important;
}

.card-opcao-tema.ativo {
    border-color: var(--cor-vermelho-acao, #6b130d) !important;
    background: var(--fundo-superficie, #ffffff) !important;
}

.amostras-cores-tema {
    display: flex !important;
    flex-direction: column !important;
    gap: 4px !important;
    padding-top: 2px !important;
}

.ponto-cor-amostra {
    width: 14px !important;
    height: 14px !important;
    border-radius: 50% !important;
    display: block !important;
    box-shadow: 0 1px 3px rgba(0, 0, 0, 0.25) !important;
    border: 1px solid rgba(255, 255, 255, 0.2) !important;
}

.info-opcao-tema strong {
    display: block !important;
    font-size: 14px !important;
    margin-bottom: 2px !important;
    color: var(--texto-principal, #231b18) !important;
}

.info-opcao-tema span {
    display: block !important;
    font-size: 12px !important;
    color: var(--texto-secundario, #54463f) !important;
    line-height: 1.35 !important;
}

.indicador-tema-ativo {
    display: none;
    font-size: 14px !important;
    font-weight: 700 !important;
    color: var(--cor-vermelho-acao, #6b130d) !important;
}

.indicador-tema-ativo img,
.icone-check-png {
    width: 18px !important;
    height: 18px !important;
    object-fit: contain !important;
    display: block !important;
    filter: invert(15%) sepia(85%) saturate(3000%) hue-rotate(345deg) !important;
}

.card-opcao-tema.ativo .indicador-tema-ativo {
    display: block !important;
}

/* Moderação (Admin) */
.aviso-admin-moderacao {
    display: flex !important;
    align-items: flex-start !important;
    gap: 14px !important;
    padding: 14px 18px !important;
    background: rgba(107, 19, 13, 0.07) !important;
    border: 1px solid rgba(107, 19, 13, 0.25) !important;
    border-radius: 10px !important;
    margin-bottom: 20px !important;
}

.icone-alerta-admin {
    color: var(--cor-vermelho-acao, #6b130d) !important;
    flex-shrink: 0 !important;
    margin-top: 2px !important;
}

.aviso-admin-moderacao strong {
    display: block !important;
    font-size: 14px !important;
    color: var(--cor-vermelho-acao, #6b130d) !important;
    margin-bottom: 3px !important;
}

.aviso-admin-moderacao p {
    margin: 0 !important;
    font-size: 12.5px !important;
    color: var(--texto-secundario, #54463f) !important;
    line-height: 1.45 !important;
}

.grade-kpis-mod-modal {
    display: grid !important;
    grid-template-columns: repeat(3, 1fr) !important;
    gap: 12px !important;
    margin-bottom: 6px !important;
}

.card-kpi-mod-modal {
    padding: 14px !important;
    background: var(--fundo-destaque, #fcf9f5) !important;
    border: 1px solid var(--borda-suave, #e2d6cb) !important;
    border-radius: 10px !important;
    text-align: center !important;
}

.numero-kpi-mod {
    display: block !important;
    font-family: var(--fonte-literaria, Georgia, serif) !important;
    font-size: 26px !important;
    font-weight: 700 !important;
    color: var(--texto-principal, #231b18) !important;
    line-height: 1.1 !important;
    margin-bottom: 4px !important;
}

.numero-kpi-mod.kpi-alerta {
    color: #c5221f !important;
}

.rotulo-kpi-mod {
    display: block !important;
    font-size: 11.5px !important;
    color: var(--texto-secundario, #54463f) !important;
    font-weight: 600 !important;
    line-height: 1.25 !important;
}

.lista-controles-admin {
    display: flex !important;
    flex-direction: column !important;
    gap: 10px !important;
}

.controle-admin-card {
    display: flex !important;
    align-items: center !important;
    justify-content: space-between !important;
    gap: 14px !important;
    padding: 12px 14px !important;
    background: var(--fundo-destaque, #fcf9f5) !important;
    border: 1px solid var(--borda-suave, #e2d6cb) !important;
    border-radius: 10px !important;
}

.controle-admin-info strong {
    display: block !important;
    font-size: 14px !important;
    color: var(--texto-principal, #231b18) !important;
    margin-bottom: 2px !important;
}

.controle-admin-info small {
    display: block !important;
    font-size: 12px !important;
    color: var(--texto-secundario, #54463f) !important;
    line-height: 1.35 !important;
}

.btn-toggle-admin {
    flex-shrink: 0 !important;
    padding: 8px 14px !important;
    border-radius: 8px !important;
    font-size: 12.5px !important;
    font-weight: 700 !important;
    cursor: pointer !important;
    border: 1px solid var(--borda-forte, #c9b7a8) !important;
    transition: all 0.15s ease !important;
}

.btn-toggle-admin.ativo {
    background: #2f5222 !important;
    color: #ffffff !important;
    border-color: #2f5222 !important;
}

.btn-toggle-admin.inativo {
    background: var(--fundo-superficie-suave, #faf6f2) !important;
    color: var(--texto-principal, #231b18) !important;
}

.lista-denuncias-rapidas {
    display: flex !important;
    flex-direction: column !important;
    gap: 8px !important;
}

.item-denuncia-rapida {
    display: flex !important;
    align-items: center !important;
    justify-content: space-between !important;
    gap: 12px !important;
    padding: 10px 14px !important;
    background: var(--fundo-destaque, #fcf9f5) !important;
    border: 1px solid var(--borda-suave, #e2d6cb) !important;
    border-radius: 8px !important;
}

.item-denuncia-info {
    flex: 1 !important;
    min-width: 0 !important;
}

.motivo-denuncia-rapida {
    display: block !important;
    font-size: 13px !important;
    color: var(--texto-principal, #231b18) !important;
    white-space: nowrap !important;
    overflow: hidden !important;
    text-overflow: ellipsis !important;
}

.tipo-denuncia-rapida {
    display: block !important;
    font-size: 11px !important;
    color: var(--texto-apoio, #6e5d54) !important;
    margin-top: 1px !important;
}

.selo-prioridade-mod {
    display: inline-block !important;
    font-size: 10px !important;
    font-weight: 700 !important;
    text-transform: uppercase !important;
    padding: 2px 6px !important;
    border-radius: 4px !important;
    margin-bottom: 3px !important;
}

.selo-prioridade-mod.prio-alta {
    background: #fce8e6 !important;
    color: #c5221f !important;
}

.selo-prioridade-mod.prio-media {
    background: #fef7e0 !important;
    color: #b06000 !important;
}

.selo-prioridade-mod.prio-baixa {
    background: #e8f0fe !important;
    color: #1a73e8 !important;
}

.botao-tratar-denuncia {
    font-size: 12px !important;
    font-weight: 600 !important;
    color: var(--cor-vermelho-acao, #6b130d) !important;
    text-decoration: none !important;
    padding: 4px 8px !important;
    border-radius: 4px !important;
    background: var(--fundo-superficie, #ffffff) !important;
    border: 1px solid var(--borda-suave, #e2d6cb) !important;
    white-space: nowrap !important;
}

.botao-tratar-denuncia:hover {
    background: var(--cor-vermelho-acao, #6b130d) !important;
    color: #ffffff !important;
    border-color: var(--cor-vermelho-acao, #6b130d) !important;
}

@media (max-width: 768px) {
    .folivro-topo-fixo-acoes {
        top: 12px !important;
        right: 14px !important;
    }
    .botao-configuracoes-topo {
        width: 40px !important;
        height: 40px !important;
    }
    .icone-barras-config {
        width: 28px !important;
        height: 28px !important;
    }
    .modal-configuracoes-caixa {
        width: 96vw !important;
        max-height: 94vh !important;
        border-radius: 12px !important;
    }
    .modal-configuracoes-corpo {
        padding: 16px !important;
    }
    .grade-kpis-mod-modal {
        grid-template-columns: 1fr !important;
    }
    .grade-temas-cores {
        grid-template-columns: 1fr !important;
    }
    .seletor-modo-rapido {
        grid-template-columns: 1fr !important;
    }
}
</style>

<!-- Botão de Configurações no Canto Superior Direito (Símbolo das 3 Barrinhas no Círculo) -->
<div class="folivro-topo-fixo-acoes" aria-label="Ações de configuração e preferências">
    <button type="button" 
            class="botao-configuracoes-topo" 
            id="abrirModalConfiguracoes" 
            title="Configurações (Perfil, Temas<?= $cfgIsAdmin ? ', Moderação' : '' ?>)" 
            aria-label="Abrir configurações de perfil, temas e moderação" 
            aria-haspopup="dialog" 
            aria-expanded="false" 
            aria-controls="modalConfiguracoes">
        <!-- Ícone idêntico ao modelo: círculo com 3 barras horizontais -->
        <svg class="icone-barras-config" viewBox="0 0 100 100" fill="none" stroke="currentColor" stroke-linecap="round" stroke-linejoin="round">
            <circle cx="50" cy="50" r="44" stroke-width="7" />
            <line x1="26" y1="36" x2="74" y2="36" stroke-width="8" />
            <line x1="26" y1="50" x2="74" y2="50" stroke-width="8" />
            <line x1="26" y1="64" x2="74" y2="64" stroke-width="8" />
        </svg>
        <?php if ($cfgIsAdmin && $cfgTotalPendencias > 0): ?>
            <span class="ponto-notificacao-admin" title="<?= $cfgTotalPendencias ?> pendência(s) na moderação">
                <?= $cfgTotalPendencias > 99 ? '99+' : $cfgTotalPendencias ?>
            </span>
        <?php endif; ?>
    </button>
</div>

<!-- Janela Modal de Configurações (OBRIGATORIAMENTE INICIA OCULTA VIA INLINE STYLE) -->
<div class="modal-configuracoes-fundo" id="modalConfiguracoes" style="display: none !important;" role="dialog" aria-modal="true" aria-labelledby="tituloModalConfiguracoes">
    <div class="modal-configuracoes-caixa">
        <!-- Cabeçalho do Modal -->
        <header class="modal-configuracoes-cabecalho">
            <div class="modal-config-titulo-bloco">
                <div class="icone-modal-emblema" aria-hidden="true">
                    <svg viewBox="0 0 100 100" width="22" height="22" fill="none" stroke="currentColor" stroke-linecap="round" stroke-linejoin="round">
                        <circle cx="50" cy="50" r="44" stroke-width="7" />
                        <line x1="26" y1="36" x2="74" y2="36" stroke-width="8" />
                        <line x1="26" y1="50" x2="74" y2="50" stroke-width="8" />
                        <line x1="26" y1="64" x2="74" y2="64" stroke-width="8" />
                    </svg>
                </div>
                <div>
                    <h2 id="tituloModalConfiguracoes">Configurações</h2>
                    <p class="modal-config-sub">Perfil, temas visuais do site<?= $cfgIsAdmin ? ' e moderação administrativa' : '' ?></p>
                </div>
            </div>
            <button type="button" class="botao-fechar-config" id="fecharModalConfiguracoes" aria-label="Fechar janela de configurações">
                <svg viewBox="0 0 24 24" width="20" height="20" fill="none" stroke="currentColor" stroke-width="2.2" stroke-linecap="round" stroke-linejoin="round">
                    <line x1="18" y1="6" x2="6" y2="18"></line>
                    <line x1="6" y1="6" x2="18" y2="18"></line>
                </svg>
            </button>
        </header>

        <!-- Navegação por Abas -->
        <nav class="abas-config-nav" role="tablist" aria-label="Abas de configuração">
            <button type="button" role="tab" class="aba-config-btn ativa" id="abaBtnPerfil" aria-selected="true" aria-controls="painelConfigPerfil">
                <svg viewBox="0 0 24 24" width="16" height="16" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round">
                    <path d="M20 21v-2a4 4 0 0 0-4-4H8a4 4 0 0 0-4 4v2"></path>
                    <circle cx="12" cy="7" r="4"></circle>
                </svg>
                <span>Perfil</span>
            </button>
            <button type="button" role="tab" class="aba-config-btn" id="abaBtnTemas" aria-selected="false" aria-controls="painelConfigTemas">
                <svg viewBox="0 0 24 24" width="16" height="16" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round">
                    <circle cx="13.5" cy="6.5" r=".5" fill="currentColor"></circle>
                    <circle cx="17.5" cy="10.5" r=".5" fill="currentColor"></circle>
                    <circle cx="8.5" cy="7.5" r=".5" fill="currentColor"></circle>
                    <circle cx="6.5" cy="12.5" r=".5" fill="currentColor"></circle>
                    <path d="M12 2C6.5 2 2 6.5 2 12s4.5 10 10 10c.926 0 1.648-.746 1.648-1.688 0-.437-.18-.835-.437-1.125-.29-.289-.438-.652-.438-1.125a1.64 1.64 0 0 1 1.668-1.668h1.996c3.051 0 5.563-2.512 5.563-5.563C22 6.5 17.5 2 12 2z"></path>
                </svg>
                <span>Temas de Cores</span>
            </button>
            <?php if ($cfgIsAdmin): ?>
            <!-- ABA DE MODERAÇÃO: RENDERIZADA EXCLUSIVAMENTE PARA ADMINISTRADORES (ÚNICO PONTO DE ACESSO) -->
            <button type="button" role="tab" class="aba-config-btn aba-config-admin" id="abaBtnModeracao" aria-selected="false" aria-controls="painelConfigModeracao">
                <svg viewBox="0 0 24 24" width="16" height="16" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round">
                    <path d="M12 22s8-4 8-10V5l-8-3-8 3v7c0 6 8 10 8 10z"></path>
                    <path d="m9 12 2 2 4-4"></path>
                </svg>
                <span>Moderação</span>
                <span class="selo-badge-admin">Admin</span>
                <?php if ($cfgTotalPendencias > 0): ?>
                    <span class="contador-pendencias-pill" title="<?= $cfgTotalPendencias ?> pendência(s)"><?= $cfgTotalPendencias ?></span>
                <?php endif; ?>
            </button>
            <?php endif; ?>
        </nav>

        <!-- Corpo dos Painéis -->
        <div class="modal-configuracoes-corpo">
            
            <!-- PAINEL 1: CONFIGURAÇÕES DE PERFIL -->
            <div role="tabpanel" id="painelConfigPerfil" class="painel-config-item ativo" aria-labelledby="abaBtnPerfil">
                <div class="cartao-usuario-resumo-modal">
                    <div class="avatar-usuario-modal" aria-hidden="true">
                        <?php if (!empty($cfgFoto)): ?>
                            <img src="<?= folivro_escape($cfgFoto) ?>" alt="Foto de perfil">
                        <?php else: ?>
                            <svg viewBox="0 0 100 100">
                                <circle cx="50" cy="50" r="50" fill="#555" />
                                <circle cx="50" cy="35" r="19" fill="#ccc" />
                                <path d="M15 88 Q18 57 50 57 Q82 57 85 88 Q68 100 50 100 Q32 100 15 88" fill="#ccc" />
                            </svg>
                        <?php endif; ?>
                    </div>
                    <div class="dados-usuario-modal">
                        <div class="nome-linha-modal">
                            <h3><?= folivro_escape($cfgNome) ?></h3>
                            <?php if ($cfgIsAdmin): ?>
                                <span class="selo-nivel-usuario selo-admin">Administrador</span>
                            <?php elseif ($cfgNivelUsuario === 'autor'): ?>
                                <span class="selo-nivel-usuario selo-autor">Autor</span>
                            <?php else: ?>
                                <span class="selo-nivel-usuario selo-leitor">Leitor</span>
                            <?php endif; ?>
                        </div>
                        <?php if ($cfgEmail !== ''): ?>
                            <p class="email-usuario-modal"><?= folivro_escape($cfgEmail) ?></p>
                        <?php endif; ?>
                        <?php if ($cfgDataCadastro !== ''): ?>
                            <p class="membro-usuario-modal">Membro do FOLIVRO desde <?= folivro_escape($cfgDataCadastro) ?></p>
                        <?php endif; ?>
                    </div>
                </div>

                <div class="secao-config-bloco">
                    <h4>Atalhos Rápidos de Perfil</h4>
                    <div class="grade-atalhos-perfil-modal">
                        <a href="usuario.php" class="atalho-perfil-btn">
                            <svg viewBox="0 0 24 24" width="16" height="16" fill="none" stroke="currentColor" stroke-width="2"><path d="M20 21v-2a4 4 0 0 0-4-4H8a4 4 0 0 0-4 4v2"></path><circle cx="12" cy="7" r="4"></circle></svg>
                            <span>Meu Perfil Completo</span>
                        </a>
                        <a href="minha-biblioteca.php" class="atalho-perfil-btn">
                            <svg viewBox="0 0 24 24" width="16" height="16" fill="none" stroke="currentColor" stroke-width="2"><path d="M4 19.5A2.5 2.5 0 0 1 6.5 17H20"></path><path d="M6.5 2H20v20H6.5A2.5 2.5 0 0 1 4 19.5v-15A2.5 2.5 0 0 1 6.5 2z"></path></svg>
                            <span>Minha Biblioteca</span>
                        </a>
                        <?php if ($cfgNivelUsuario === 'autor' || $cfgIsAdmin): ?>
                            <a href="biblioteca-autor.php" class="atalho-perfil-btn">
                                <svg viewBox="0 0 24 24" width="16" height="16" fill="none" stroke="currentColor" stroke-width="2"><path d="M12 20h9"></path><path d="M16.5 3.5a2.12 2.12 0 0 1 3 3L7 19l-4 1 1-4Z"></path></svg>
                                <span>Painel do Autor</span>
                            </a>
                        <?php endif; ?>
                        <a href="estatisticas.php" class="atalho-perfil-btn">
                            <svg viewBox="0 0 24 24" width="16" height="16" fill="none" stroke="currentColor" stroke-width="2"><circle cx="12" cy="12" r="10"></circle><polyline points="12 6 12 12 16 14"></polyline></svg>
                            <span>Metas & Estatísticas</span>
                        </a>
                        <a href="termos.php" class="atalho-perfil-btn">
                            <svg viewBox="0 0 24 24" width="16" height="16" fill="none" stroke="currentColor" stroke-width="2"><path d="M14 2H6a2 2 0 0 0-2 2v16a2 2 0 0 0 2 2h12a2 2 0 0 0 2-2V8z"></path><polyline points="14 2 14 8 20 8"></polyline><line x1="16" y1="13" x2="8" y2="13"></line><line x1="16" y1="17" x2="8" y2="17"></line><polyline points="10 9 9 9 8 9"></polyline></svg>
                            <span>Termos & Privacidade</span>
                        </a>
                    </div>
                </div>

                <div class="secao-config-bloco">
                    <h4>Edição Rápida de Perfil</h4>
                    <form method="post" action="usuario.php" class="form-config-rapido">
                        <input type="hidden" name="csrf_token" value="<?= folivro_escape($cfgCsrf) ?>">
                        <input type="hidden" name="acao" value="editar_perfil">
                        
                        <div class="campo-config-grupo">
                            <label for="cfg_nome_usuario">Nome de exibição</label>
                            <input type="text" id="cfg_nome_usuario" name="nome_usuario" value="<?= folivro_escape($cfgNome) ?>" maxlength="120" required>
                        </div>

                        <div class="campo-config-grupo">
                            <label for="cfg_data_nascimento_usuario">Data de nascimento (classificação indicativa<?= $cfgIdade !== null ? ' · ' . $cfgIdade . ' anos' : '' ?>)</label>
                            <input type="date" id="cfg_data_nascimento_usuario" name="data_nascimento_usuario" value="<?= folivro_escape($cfgDataNascimento) ?>" min="1905-01-01" max="<?= date('Y-m-d') ?>">
                        </div>
                        
                        <div class="campo-config-grupo">
                            <label for="cfg_biografia_usuario">Biografia breve (opcional)</label>
                            <textarea id="cfg_biografia_usuario" name="biografia_usuario" rows="2" maxlength="1000"><?= folivro_escape($cfgBio) ?></textarea>
                        </div>
                        
                        <div class="acoes-salvar-perfil-linha">
                            <button type="submit" class="botao-salvar-perfil-modal">Salvar alterações</button>
                            <a href="usuario.php" class="link-perfil-detalhes">Editar foto e senha completa →</a>
                        </div>
                    </form>
                </div>

                <div class="rodape-sessao-config">
                    <form method="post" action="logout.php">
                        <input type="hidden" name="csrf_token" value="<?= folivro_escape($cfgCsrf) ?>">
                        <button type="submit" class="botao-sair-modal">Desconectar da conta</button>
                    </form>
                </div>
            </div>

            <!-- PAINEL 2: TEMAS DE CORES PARA O SITE -->
            <div role="tabpanel" id="painelConfigTemas" class="painel-config-item" aria-labelledby="abaBtnTemas" hidden>
                
                <div class="secao-config-bloco">
                    <h4>Modo de Iluminação Rápido</h4>
                    <p class="texto-apoio-config">Escolha entre contraste claro ou escuro com um único clique:</p>
                    <div class="seletor-modo-rapido">
                        <button type="button" class="btn-modo-rapido" id="btnModoClaro" data-modo="claro">
                            <svg viewBox="0 0 24 24" width="20" height="20" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round">
                                <circle cx="12" cy="12" r="5"></circle>
                                <line x1="12" y1="1" x2="12" y2="3"></line>
                                <line x1="12" y1="21" x2="12" y2="23"></line>
                                <line x1="4.22" y1="4.22" x2="5.64" y2="5.64"></line>
                                <line x1="18.36" y1="18.36" x2="19.78" y2="19.78"></line>
                                <line x1="1" y1="12" x2="3" y2="12"></line>
                                <line x1="21" y1="12" x2="23" y2="12"></line>
                                <line x1="4.22" y1="19.78" x2="5.64" y2="18.36"></line>
                                <line x1="18.36" y1="5.64" x2="19.78" y2="4.22"></line>
                            </svg>
                            <span>Modo Claro</span>
                        </button>
                        <button type="button" class="btn-modo-rapido" id="btnModoEscuro" data-modo="escuro">
                            <svg viewBox="0 0 24 24" width="20" height="20" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round">
                                <path d="M21 12.79A9 9 0 1 1 11.21 3 7 7 0 0 0 21 12.79z"></path>
                            </svg>
                            <span>Modo Escuro</span>
                        </button>
                    </div>
                </div>

                <div class="secao-config-bloco">
                    <h4>Paletas de Cores Literárias</h4>
                    <p class="texto-apoio-config">Selecione uma atmosfera visual personalizada. A escolha é aplicada imediatamente e preservada em todas as páginas do FOLIVRO:</p>
                    
                    <div class="grade-temas-cores">
                        <!-- 1. Padrão Vinho / Marsala -->
                        <button type="button" class="card-opcao-tema" data-tema-id="padrao" data-modo-padrao="claro">
                            <div class="amostras-cores-tema">
                                <span class="ponto-cor-amostra" style="background:#5c0000" title="Vinho / Marsala"></span>
                                <span class="ponto-cor-amostra" style="background:#f3eee8" title="Marfim aquecido"></span>
                                <span class="ponto-cor-amostra" style="background:#8a542c" title="Bronze literário"></span>
                            </div>
                            <div class="info-opcao-tema">
                                <strong>Vinho & Marfim</strong>
                                <span>A identidade clássica e acolhedora do FOLIVRO.</span>
                            </div>
                            <span class="indicador-tema-ativo" aria-hidden="true"><img src="img/icones/check.png" alt="" class="icone-check-png"></span>
                        </button>

                        <!-- 2. Noturno Café -->
                        <button type="button" class="card-opcao-tema" data-tema-id="escuro" data-modo-padrao="escuro">
                            <div class="amostras-cores-tema">
                                <span class="ponto-cor-amostra" style="background:#1b1716" title="Café profundo"></span>
                                <span class="ponto-cor-amostra" style="background:#282220" title="Superfície grafite"></span>
                                <span class="ponto-cor-amostra" style="background:#f3c879" title="Âmbar suave"></span>
                            </div>
                            <div class="info-opcao-tema">
                                <strong>Café Noturno</strong>
                                <span>Fundo escuro aveludado e confortável para leituras à noite.</span>
                            </div>
                            <span class="indicador-tema-ativo" aria-hidden="true"><img src="img/icones/check.png" alt="" class="icone-check-png"></span>
                        </button>

                        <!-- 3. Sépia Papel Antigo -->
                        <button type="button" class="card-opcao-tema" data-tema-id="sepia" data-modo-padrao="claro">
                            <div class="amostras-cores-tema">
                                <span class="ponto-cor-amostra" style="background:#f5eedf" title="Pergaminho"></span>
                                <span class="ponto-cor-amostra" style="background:#873d1e" title="Terracota"></span>
                                <span class="ponto-cor-amostra" style="background:#5b4632" title="Sépia amadeirado"></span>
                            </div>
                            <div class="info-opcao-tema">
                                <strong>Sépia & Pergaminho</strong>
                                <span>Tons quentes de livro físico que reduzem o cansaço dos olhos.</span>
                            </div>
                            <span class="indicador-tema-ativo" aria-hidden="true"><img src="img/icones/check.png" alt="" class="icone-check-png"></span>
                        </button>

                        <!-- 4. Floresta & Sálvia -->
                        <button type="button" class="card-opcao-tema" data-tema-id="floresta" data-modo-padrao="claro">
                            <div class="amostras-cores-tema">
                                <span class="ponto-cor-amostra" style="background:#1e4d28" title="Verde Floresta"></span>
                                <span class="ponto-cor-amostra" style="background:#eef3ec" title="Sálvia claro"></span>
                                <span class="ponto-cor-amostra" style="background:#526f55" title="Musgo suave"></span>
                            </div>
                            <div class="info-opcao-tema">
                                <strong>Floresta & Sálvia</strong>
                                <span>Tons botânicos e terrosos para uma leitura tranquila e fresca.</span>
                            </div>
                            <span class="indicador-tema-ativo" aria-hidden="true"><img src="img/icones/check.png" alt="" class="icone-check-png"></span>
                        </button>

                        <!-- 5. Oceano Meia-Noite -->
                        <button type="button" class="card-opcao-tema" data-tema-id="meianoite" data-modo-padrao="escuro">
                            <div class="amostras-cores-tema">
                                <span class="ponto-cor-amostra" style="background:#0c131f" title="Azul abissal"></span>
                                <span class="ponto-cor-amostra" style="background:#162030" title="Ardósia noturna"></span>
                                <span class="ponto-cor-amostra" style="background:#3b82f6" title="Azul celeste"></span>
                            </div>
                            <div class="info-opcao-tema">
                                <strong>Oceano Meia-Noite</strong>
                                <span>Contraste dark moderno em tons de ardósia e azul safira.</span>
                            </div>
                            <span class="indicador-tema-ativo" aria-hidden="true"><img src="img/icones/check.png" alt="" class="icone-check-png"></span>
                        </button>

                        <!-- 6. Ametista & Lavanda -->
                        <button type="button" class="card-opcao-tema" data-tema-id="ametista" data-modo-padrao="escuro">
                            <div class="amostras-cores-tema">
                                <span class="ponto-cor-amostra" style="background:#16121f" title="Noite violeta"></span>
                                <span class="ponto-cor-amostra" style="background:#221c30" title="Ametista escuro"></span>
                                <span class="ponto-cor-amostra" style="background:#a855f7" title="Lavanda brilhante"></span>
                            </div>
                            <div class="info-opcao-tema">
                                <strong>Ametista & Lavanda</strong>
                                <span>Estética noturna refinada em tons aveludados de roxo literário.</span>
                            </div>
                            <span class="indicador-tema-ativo" aria-hidden="true"><img src="img/icones/check.png" alt="" class="icone-check-png"></span>
                        </button>
                    </div>
                </div>

            </div>

            <?php if ($cfgIsAdmin): ?>
            <!-- PAINEL 3: MODERAÇÃO (RENDERIZADO EXCLUSIVAMENTE PARA ADMINISTRADORES - ÚNICO PONTO DE ACESSO) -->
            <div role="tabpanel" id="painelConfigModeracao" class="painel-config-item" aria-labelledby="abaBtnModeracao" hidden>
                
                <div class="aviso-admin-moderacao">
                    <div class="icone-alerta-admin" aria-hidden="true">
                        <svg viewBox="0 0 24 24" width="22" height="22" fill="none" stroke="currentColor" stroke-width="2">
                            <path d="M12 22s8-4 8-10V5l-8-3-8 3v7c0 6 8 10 8 10z"></path>
                            <path d="m9 12 2 2 4-4"></path>
                        </svg>
                    </div>
                    <div>
                        <strong>Painel Administrativo de Moderação Ativa</strong>
                        <p>Acesso exclusivo para verificação de direitos autorais (<em>Notice and Takedown</em>), revisão editorial de publicação e segurança no chat.</p>
                    </div>
                </div>

                <div class="secao-config-bloco">
                    <h4>Fila de Pendências em Tempo Real</h4>
                    <div class="grade-kpis-mod-modal">
                        <div class="card-kpi-mod-modal">
                            <strong class="numero-kpi-mod <?= $cfgModStats['denuncias'] > 0 ? 'kpi-alerta' : '' ?>"><?= $cfgModStats['denuncias'] ?></strong>
                            <span class="rotulo-kpi-mod">Denúncias Pendentes</span>
                        </div>
                        <div class="card-kpi-mod-modal">
                            <strong class="numero-kpi-mod <?= $cfgModStats['obras'] > 0 ? 'kpi-alerta' : '' ?>"><?= $cfgModStats['obras'] ?></strong>
                            <span class="rotulo-kpi-mod">Obras para Revisão</span>
                        </div>
                        <div class="card-kpi-mod-modal">
                            <strong class="numero-kpi-mod"><?= $cfgModStats['chat'] ?></strong>
                            <span class="rotulo-kpi-mod">Mensagens de Chat</span>
                        </div>
                    </div>
                </div>

                <div class="secao-config-bloco">
                    <h4>Acesso ao Painel de Moderação</h4>
                    <div class="grade-atalhos-perfil-modal">
                        <a href="moderacao.php" class="atalho-perfil-btn destaque-admin">
                            <svg viewBox="0 0 24 24" width="16" height="16" fill="none" stroke="currentColor" stroke-width="2"><path d="M12 22s8-4 8-10V5l-8-3-8 3v7c0 6 8 10 8 10z"></path><path d="m9 12 2 2 4-4"></path></svg>
                            <span>Abrir Painel Geral de Moderação</span>
                        </a>
                        <a href="moderacao.php#titulo-fila-revisao-publicacao" class="atalho-perfil-btn">
                            <svg viewBox="0 0 24 24" width="16" height="16" fill="none" stroke="currentColor" stroke-width="2"><path d="M14 2H6a2 2 0 0 0-2 2v16a2 2 0 0 0 2 2h12a2 2 0 0 0 2-2V8z"></path><polyline points="14 2 14 8 20 8"></polyline><line x1="16" y1="13" x2="8" y2="13"></line><line x1="16" y1="17" x2="8" y2="17"></line></svg>
                            <span>Fila de Revisão de Obras</span>
                        </a>
                    </div>
                </div>

                <div class="secao-config-bloco">
                    <h4>Controles da Plataforma (Loja &amp; Publicação)</h4>
                    <div class="lista-controles-admin">
                        <?php if (defined('FOLIVRO_PAGAMENTOS_ATIVOS') && FOLIVRO_PAGAMENTOS_ATIVOS): ?>
                        <div class="controle-admin-card">
                            <div class="controle-admin-info">
                                <strong>Pagamento Simulado (Loja)</strong>
                                <small id="descPagamentoSimulado">
                                    <?= $cfgPagamentoSimuladoAtivo
                                        ? 'Ativo: compras na loja usam o gateway simulado (sem dinheiro real).'
                                        : 'Desativado: loja operando em modo real (Mercado Pago).' ?>
                                </small>
                            </div>
                            <button type="button"
                                    id="btnTogglePagamentoSimulado"
                                    class="btn-toggle-admin <?= $cfgPagamentoSimuladoAtivo ? 'ativo' : 'inativo' ?>"
                                    data-ativo="<?= $cfgPagamentoSimuladoAtivo ? '1' : '0' ?>">
                                <?= $cfgPagamentoSimuladoAtivo ? 'Ativado (Desativar)' : 'Desativado (Ativar)' ?>
                            </button>
                        </div>
                        <?php endif; ?>

                        <div class="controle-admin-card">
                            <div class="controle-admin-info">
                                <strong>Aprovação Automática de Obras Postadas</strong>
                                <small id="descAprovacaoAutomatica">
                                    <?= $cfgAprovacaoAutomaticaAtiva
                                        ? 'Ativada: obras seguras são aprovadas e publicadas automaticamente.'
                                        : 'Desativada: obras novas passam por revisão humana ou critérios de confiança.' ?>
                                </small>
                            </div>
                            <button type="button"
                                    id="btnToggleAprovacaoAuto"
                                    class="btn-toggle-admin <?= $cfgAprovacaoAutomaticaAtiva ? 'ativo' : 'inativo' ?>"
                                    data-ativo="<?= $cfgAprovacaoAutomaticaAtiva ? '1' : '0' ?>">
                                <?= $cfgAprovacaoAutomaticaAtiva ? 'Ativada (Desativar)' : 'Desativada (Ativar)' ?>
                            </button>
                        </div>
                    </div>
                    <p id="feedbackAdminConfigModal" class="estado-vazio" style="display:none; margin-top:10px; padding:10px; font-size:13px;" role="status"></p>
                </div>

                <?php if (!empty($cfgModStats['recentes'])): ?>
                <div class="secao-config-bloco">
                    <h4>Denúncias Recentes em Aberto</h4>
                    <div class="lista-denuncias-rapidas">
                        <?php foreach ($cfgModStats['recentes'] as $rec): ?>
                            <div class="item-denuncia-rapida">
                                <div class="item-denuncia-info">
                                    <span class="selo-prioridade-mod prio-<?= folivro_escape((string) $rec['prioridade_denuncia']) ?>">
                                        <?= folivro_escape(folivro_status_label((string) $rec['prioridade_denuncia'])) ?>
                                    </span>
                                    <strong class="motivo-denuncia-rapida"><?= folivro_escape((string) $rec['motivo_denuncia']) ?></strong>
                                    <small class="tipo-denuncia-rapida"><?= folivro_escape(folivro_status_label((string) $rec['tipo_denuncias'])) ?> · <?= date('d/m H:i', strtotime((string) $rec['data_denuncia'])) ?></small>
                                </div>
                                <a href="moderacao.php" class="botao-tratar-denuncia">Tratar no painel →</a>
                            </div>
                        <?php endforeach; ?>
                    </div>
                </div>
                <?php else: ?>
                <div class="secao-config-bloco">
                    <p class="estado-vazio estado-sucesso" style="margin:0;padding:12px;font-size:14px;display:flex;align-items:center;gap:8px;">
                        <img src="img/icones/check.png" alt="" class="icone-check-png" style="display:inline-block !important; flex-shrink:0;">
                        <span>Nenhuma denúncia aberta pendente na fila neste momento.</span>
                    </p>
                </div>
                <?php endif; ?>

            </div>
            <?php endif; ?>

        </div>
    </div>
</div>

<script>
/**
 * Gerenciador Unificado de Temas e Modal de Configurações — FOLIVRO
 */
(function () {
    const TEMAS_DISPONIVEIS = ['padrao', 'escuro', 'sepia', 'floresta', 'meianoite', 'ametista'];
    
    function obterTemaSalvo() {
        return localStorage.getItem('folivro_tema_cor') || (localStorage.getItem('folivroTema') === 'escuro' ? 'escuro' : 'padrao');
    }

    function obterModoSalvo() {
        const modo = localStorage.getItem('folivroTema') || localStorage.getItem('folivro_tema');
        if (modo) return modo;
        const tema = obterTemaSalvo();
        return (tema === 'escuro' || tema === 'meianoite' || tema === 'ametista') ? 'escuro' : 'claro';
    }

    function aplicarTema(temaId, modo) {
        if (!TEMAS_DISPONIVEIS.includes(temaId)) {
            temaId = 'padrao';
        }
        TEMAS_DISPONIVEIS.forEach(function (t) {
            document.body.classList.remove('tema-' + t);
        });
        if (temaId !== 'padrao') {
            document.body.classList.add('tema-' + temaId);
        }

        const isEscuro = (modo === 'escuro' || temaId === 'escuro' || temaId === 'meianoite' || temaId === 'ametista');
        document.body.classList.toggle('tema-escuro', isEscuro);

        localStorage.setItem('folivro_tema_cor', temaId);
        const modoStr = isEscuro ? 'escuro' : 'claro';
        localStorage.setItem('folivroTema', modoStr);
        localStorage.setItem('folivro_tema', modoStr);

        atualizarInterfaceTemas(temaId, modoStr);
    }

    function atualizarInterfaceTemas(temaId, modo) {
        document.querySelectorAll('.card-opcao-tema').forEach(function (btn) {
            const btnTema = btn.getAttribute('data-tema-id');
            const ativo = (btnTema === temaId);
            btn.classList.toggle('ativo', ativo);
            btn.setAttribute('aria-pressed', ativo ? 'true' : 'false');
        });

        const btnClaro = document.getElementById('btnModoClaro');
        const btnEscuro = document.getElementById('btnModoEscuro');
        if (btnClaro && btnEscuro) {
            btnClaro.classList.toggle('ativo', modo === 'claro');
            btnEscuro.classList.toggle('ativo', modo === 'escuro');
        }
    }

    // Aplicação inicial imediata
    aplicarTema(obterTemaSalvo(), obterModoSalvo());

    document.addEventListener('DOMContentLoaded', function () {
        const modal = document.getElementById('modalConfiguracoes');
        const abrirBtn = document.getElementById('abrirModalConfiguracoes');
        const fecharBtn = document.getElementById('fecharModalConfiguracoes');

        if (!modal || !abrirBtn) return;

        function abrirModal() {
            modal.style.setProperty('display', 'flex', 'important');
            abrirBtn.setAttribute('aria-expanded', 'true');
            document.body.style.overflow = 'hidden';
            if (fecharBtn) fecharBtn.focus();
        }

        function fecharModal() {
            modal.style.setProperty('display', 'none', 'important');
            abrirBtn.setAttribute('aria-expanded', 'false');
            document.body.style.overflow = '';
            abrirBtn.focus();
        }

        abrirBtn.addEventListener('click', abrirModal);
        if (fecharBtn) fecharBtn.addEventListener('click', fecharModal);

        modal.addEventListener('click', function (ev) {
            if (ev.target === modal) {
                fecharModal();
            }
        });

        document.addEventListener('keydown', function (ev) {
            if (ev.key === 'Escape' && modal.style.display !== 'none') {
                fecharModal();
            }
        });

        const abas = modal.querySelectorAll('.aba-config-btn');
        const paineis = modal.querySelectorAll('.painel-config-item');

        abas.forEach(function (aba) {
            aba.addEventListener('click', function () {
                const painelId = this.getAttribute('aria-controls');
                
                abas.forEach(function (b) {
                    b.classList.remove('ativa');
                    b.setAttribute('aria-selected', 'false');
                });
                paineis.forEach(function (p) {
                    p.classList.remove('ativo');
                    p.style.setProperty('display', 'none', 'important');
                });

                this.classList.add('ativa');
                this.setAttribute('aria-selected', 'true');

                const painelAlvo = document.getElementById(painelId);
                if (painelAlvo) {
                    painelAlvo.classList.add('ativo');
                    painelAlvo.style.setProperty('display', 'block', 'important');
                }
            });
        });

        document.querySelectorAll('.card-opcao-tema').forEach(function (btn) {
            btn.addEventListener('click', function () {
                const temaEscolhido = this.getAttribute('data-tema-id') || 'padrao';
                const modoPadrao = this.getAttribute('data-modo-padrao') || 'claro';
                aplicarTema(temaEscolhido, modoPadrao);
            });
        });

        const btnClaro = document.getElementById('btnModoClaro');
        if (btnClaro) {
            btnClaro.addEventListener('click', function () {
                const temaAtual = obterTemaSalvo();
                const novoTema = (temaAtual === 'escuro' || temaAtual === 'meianoite' || temaAtual === 'ametista') ? 'padrao' : temaAtual;
                aplicarTema(novoTema, 'claro');
            });
        }

        const btnEscuro = document.getElementById('btnModoEscuro');
        if (btnEscuro) {
            btnEscuro.addEventListener('click', function () {
                const temaAtual = obterTemaSalvo();
                const novoTema = (temaAtual === 'padrao' || temaAtual === 'sepia' || temaAtual === 'floresta') ? 'escuro' : temaAtual;
                aplicarTema(novoTema, 'escuro');
            });
        }

        atualizarInterfaceTemas(obterTemaSalvo(), obterModoSalvo());

        const btnPagSim = document.getElementById('btnTogglePagamentoSimulado');
        const btnAprovAuto = document.getElementById('btnToggleAprovacaoAuto');
        const feedbackAdmin = document.getElementById('feedbackAdminConfigModal');

        async function enviarAdminConfig(payload) {
            if (feedbackAdmin) {
                feedbackAdmin.style.display = 'none';
                feedbackAdmin.className = 'estado-vazio';
            }
            const resp = await fetch('api/admin-config.php', {
                method: 'POST',
                headers: {
                    'Content-Type': 'application/json',
                    'X-CSRF-Token': <?= json_encode($cfgCsrf) ?>
                },
                body: JSON.stringify(payload)
            });
            const data = await resp.json();
            if (!resp.ok || !data.ok) {
                throw new Error(data.error || 'Não foi possível atualizar a configuração.');
            }
            if (feedbackAdmin) {
                feedbackAdmin.textContent = data.mensagem || 'Configuração atualizada.';
                feedbackAdmin.className = 'estado-vazio estado-sucesso';
                feedbackAdmin.style.display = 'block';
            }
            return data;
        }

        if (btnPagSim) {
            btnPagSim.addEventListener('click', async function () {
                const proximo = this.getAttribute('data-ativo') !== '1';
                this.disabled = true;
                try {
                    const res = await enviarAdminConfig({ acao: 'alternar_pagamento', simulado_ativo: proximo });
                    const ativo = !!res.simulado_ativo;
                    this.setAttribute('data-ativo', ativo ? '1' : '0');
                    this.className = 'btn-toggle-admin ' + (ativo ? 'ativo' : 'inativo');
                    this.textContent = ativo ? 'Ativado (Desativar)' : 'Desativado (Ativar)';
                    const desc = document.getElementById('descPagamentoSimulado');
                    if (desc) {
                        desc.textContent = ativo
                            ? 'Ativo: compras na loja usam o gateway simulado (sem dinheiro real).'
                            : 'Desativado: loja operando em modo real (Mercado Pago).';
                    }
                } catch (err) {
                    if (feedbackAdmin) {
                        feedbackAdmin.textContent = err.message;
                        feedbackAdmin.className = 'estado-vazio estado-erro';
                        feedbackAdmin.style.display = 'block';
                    }
                } finally {
                    this.disabled = false;
                }
            });
        }

        if (btnAprovAuto) {
            btnAprovAuto.addEventListener('click', async function () {
                const proximo = this.getAttribute('data-ativo') !== '1';
                this.disabled = true;
                try {
                    const res = await enviarAdminConfig({ acao: 'alternar_aprovacao_automatica', aprovacao_automatica: proximo });
                    const ativo = !!res.aprovacao_automatica;
                    this.setAttribute('data-ativo', ativo ? '1' : '0');
                    this.className = 'btn-toggle-admin ' + (ativo ? 'ativo' : 'inativo');
                    this.textContent = ativo ? 'Ativada (Desativar)' : 'Desativada (Ativar)';
                    const desc = document.getElementById('descAprovacaoAutomatica');
                    if (desc) {
                        desc.textContent = ativo
                            ? 'Ativada: obras seguras são aprovadas e publicadas automaticamente.'
                            : 'Desativada: obras novas passam por revisão humana ou critérios de confiança.';
                    }
                } catch (err) {
                    if (feedbackAdmin) {
                        feedbackAdmin.textContent = err.message;
                        feedbackAdmin.className = 'estado-vazio estado-erro';
                        feedbackAdmin.style.display = 'block';
                    }
                } finally {
                    this.disabled = false;
                }
            });
        }
    });
})();
</script>
