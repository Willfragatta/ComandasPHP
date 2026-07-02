// ===== PAINEL ADMINISTRATIVO - LÓGICA JAVASCRIPT =====

const API_BASE = window.location.origin;

// Função auxiliar para fazer requisições
async function fazerRequisicaoAdmin(endpoint, options = {}) {
    try {
        const response = await fetch(`${API_BASE}/api${endpoint}`, {
            ...options,
            headers: {
                'Content-Type': 'application/json',
                ...options.headers
            }
        });
        
        if (!response.ok) {
            const error = await response.json();
            throw new Error(error.error || error.erro || 'Erro na requisição');
        }
        
        return await response.json();
    } catch (error) {
        console.error('Erro na requisição:', error);
        throw error;
    }
}

// Função para mostrar toast (reutilizar do main.js se existir)
function mostrarToast(mensagem, tipo = 'success') {
    const toastContainer = document.getElementById('toast-container');
    if (!toastContainer) return;
    
    const toast = document.createElement('div');
    toast.className = `toast toast-${tipo}`;
    toast.innerHTML = `
        <div class="toast-content">${mensagem}</div>
        <button class="toast-close" onclick="this.parentElement.remove()">&times;</button>
    `;
    
    toastContainer.appendChild(toast);
    
    setTimeout(() => {
        toast.classList.add('toast-removing');
        setTimeout(() => toast.remove(), 300);
    }, 3000);
}

// ===== NAVEGAÇÃO ENTRE MÓDULOS =====
function mostrarModulo(nomeModulo) {
    // Esconder todos os módulos
    document.querySelectorAll('.admin-module').forEach(mod => {
        mod.classList.remove('active');
    });
    
    // Remover active de todos os botões
    document.querySelectorAll('.nav-btn').forEach(btn => {
        btn.classList.remove('active');
    });
    
    // Mostrar módulo selecionado
    const modulo = document.getElementById(`modulo-${nomeModulo}`);
    if (modulo) {
        modulo.classList.add('active');
    }
    
    // Ativar botão correspondente
    const btn = document.querySelector(`[data-module="${nomeModulo}"]`);
    if (btn) {
        btn.classList.add('active');
    }
    
    // Carregar dados do módulo
    switch(nomeModulo) {
        case 'dashboard':
            carregarDashboard();
            break;
        case 'usuarios':
            carregarUsuarios();
            break;
        case 'vendedores':
            carregarRankingVendedores();
            break;
        case 'produtos':
            carregarProdutosVendidos();
            break;
        case 'gerenciar-produtos':
            carregarProdutosGerenciar();
            break;
        case 'gamificacao':
            carregarGamificacao();
            break;
    }
}

// ===== MÓDULO: DASHBOARD =====
function atualizarFiltrosDashboard() {
    const periodo = document.getElementById('filtro-periodo-dashboard').value;
    const intervaloDiv = document.getElementById('intervalo-customizado-dashboard');
    const semanaSelect = document.getElementById('filtro-semana-dashboard');
    const mesSelect = document.getElementById('filtro-mes-dashboard');
    
    intervaloDiv.style.display = 'none';
    semanaSelect.style.display = 'none';
    mesSelect.style.display = 'none';
    
    if (periodo === 'customizado') {
        intervaloDiv.style.display = 'flex';
        intervaloDiv.style.alignItems = 'center';
    } else if (periodo === 'semana-anterior') {
        semanaSelect.style.display = 'block';
        popularSemanasDashboard();
    } else if (periodo === 'mes-anterior') {
        mesSelect.style.display = 'block';
        popularMesesDashboard();
    }
    
    carregarDashboard();
}

function popularSemanasDashboard() {
    const select = document.getElementById('filtro-semana-dashboard');
    select.innerHTML = '<option value="0">Esta Semana</option>';
    
    // Adicionar últimas 4 semanas
    for (let i = 1; i <= 4; i++) {
        const data = new Date();
        data.setDate(data.getDate() - (data.getDay() + (i * 7)));
        const inicioSemana = new Date(data);
        inicioSemana.setDate(data.getDate() - data.getDay());
        const fimSemana = new Date(inicioSemana);
        fimSemana.setDate(inicioSemana.getDate() + 6);
        
        const texto = `Semana ${i} (${inicioSemana.toLocaleDateString('pt-BR')} - ${fimSemana.toLocaleDateString('pt-BR')})`;
        select.innerHTML += `<option value="${i}">${texto}</option>`;
    }
}

function popularMesesDashboard() {
    const select = document.getElementById('filtro-mes-dashboard');
    select.innerHTML = '<option value="0">Este Mês</option>';
    
    // Adicionar últimos 6 meses
    for (let i = 1; i <= 6; i++) {
        const data = new Date();
        data.setMonth(data.getMonth() - i);
        const nomeMes = data.toLocaleDateString('pt-BR', { month: 'long', year: 'numeric' });
        select.innerHTML += `<option value="${i}">${nomeMes.charAt(0).toUpperCase() + nomeMes.slice(1)}</option>`;
    }
}

function aplicarIntervaloDashboard() {
    const dataInicio = document.getElementById('data-inicio-dashboard').value;
    const dataFim = document.getElementById('data-fim-dashboard').value;
    
    if (!dataInicio || !dataFim) {
        mostrarToast('Selecione as duas datas para o intervalo', 'error');
        return;
    }
    
    if (new Date(dataInicio) > new Date(dataFim)) {
        mostrarToast('A data de início deve ser anterior à data de fim', 'error');
        return;
    }
    
    carregarDashboard();
}

async function carregarDashboard() {
    const periodo = document.getElementById('filtro-periodo-dashboard').value;
    let url = '/admin/estatisticas';
    
    // Adicionar parâmetros conforme o período selecionado
    if (periodo === 'customizado') {
        const dataInicio = document.getElementById('data-inicio-dashboard').value;
        const dataFim = document.getElementById('data-fim-dashboard').value;
        if (dataInicio && dataFim) {
            url += `?periodo=customizado&data_inicio=${dataInicio}&data_fim=${dataFim}`;
        } else {
            mostrarToast('Selecione as datas do intervalo', 'error');
            return;
        }
    } else if (periodo === 'semana-anterior') {
        const semana = parseInt(document.getElementById('filtro-semana-dashboard').value);
        url += `?periodo=semana&offset=${semana}`;
    } else if (periodo === 'mes-anterior') {
        const mes = parseInt(document.getElementById('filtro-mes-dashboard').value);
        url += `?periodo=mes&offset=${mes}`;
    } else {
        url += `?periodo=${periodo}`;
    }
    
    try {
        const data = await fazerRequisicaoAdmin(url);
        
        // Atualizar labels e valores do período selecionado
        const periodoSelecionado = data.periodo || {};
        const labelVendas = document.getElementById('label-vendas');
        const labelComandas = document.getElementById('label-comandas');
        const labelCanceladas = document.getElementById('label-canceladas');
        
        // Atualizar labels conforme período
        if (periodo === 'hoje') {
            labelVendas.textContent = 'Vendas Hoje';
            labelComandas.textContent = 'Comandas Hoje';
            labelCanceladas.textContent = 'Comandas Canceladas (Hoje)';
        } else if (periodo === 'semana' || periodo === 'semana-anterior') {
            labelVendas.textContent = 'Vendas da Semana';
            labelComandas.textContent = 'Comandas da Semana';
            labelCanceladas.textContent = 'Comandas Canceladas (Semana)';
        } else if (periodo === 'mes' || periodo === 'mes-anterior') {
            labelVendas.textContent = 'Vendas do Mês';
            labelComandas.textContent = 'Comandas do Mês';
            labelCanceladas.textContent = 'Comandas Canceladas (Mês)';
        } else {
            labelVendas.textContent = 'Vendas do Período';
            labelComandas.textContent = 'Comandas do Período';
            labelCanceladas.textContent = 'Comandas Canceladas (Período)';
        }
        
        // Atualizar métricas do período selecionado
        document.getElementById('vendas-periodo').textContent = formatarMoeda(periodoSelecionado.total_valor || 0);
        document.getElementById('comandas-periodo').textContent = periodoSelecionado.total_comandas || 0;
        document.getElementById('comandas-canceladas').textContent = periodoSelecionado.comandas_canceladas || 0;
        
        const ticketMedio = periodoSelecionado.total_comandas > 0 
            ? (periodoSelecionado.total_valor / periodoSelecionado.total_comandas) 
            : 0;
        document.getElementById('ticket-medio').textContent = formatarMoeda(ticketMedio);
        
        // Atualizar comparação rápida (sempre mostra hoje, semana e mês atuais)
        document.getElementById('vendas-hoje').textContent = formatarMoeda(data.hoje?.total_valor || 0);
        document.getElementById('vendas-semana').textContent = formatarMoeda(data.semana?.total_valor || 0);
        document.getElementById('vendas-mes').textContent = formatarMoeda(data.mes?.total_valor || 0);
        
        document.getElementById('atendentes-ativos').textContent = data.atendentes_ativos || 0;
    } catch (error) {
        console.error('Erro ao carregar dashboard:', error);
        mostrarToast('Erro ao carregar estatísticas do dashboard', 'error');
    }
}

// ===== MÓDULO: USUÁRIOS =====
let todosUsuarios = [];

async function carregarUsuarios() {
    try {
        const data = await fazerRequisicaoAdmin('/admin/usuarios');
        todosUsuarios = data.usuarios || [];
        renderizarUsuarios(todosUsuarios);
    } catch (error) {
        console.error('Erro ao carregar usuários:', error);
        mostrarToast('Erro ao carregar usuários', 'error');
        document.getElementById('tabela-usuarios').innerHTML = 
            '<tr><td colspan="6" class="loading">Erro ao carregar usuários</td></tr>';
    }
}

function renderizarUsuarios(usuarios) {
    const tbody = document.getElementById('tabela-usuarios');
    
    if (usuarios.length === 0) {
        tbody.innerHTML = '<tr><td colspan="6" style="text-align: center; padding: 40px; color: #999;">Nenhum usuário encontrado</td></tr>';
        return;
    }
    
    tbody.innerHTML = usuarios.map(usuario => {
        const dataCriacao = usuario.data_criacao 
            ? new Date(usuario.data_criacao).toLocaleDateString('pt-BR')
            : '-';
        
        return `
            <tr>
                <td>${usuario.id || '-'}</td>
                <td><strong>${usuario.codigo}</strong></td>
                <td>${usuario.nome}</td>
                <td>
                    <span class="status-badge ${usuario.ativo ? 'ativo' : 'inativo'}">
                        ${usuario.ativo ? 'Ativo' : 'Inativo'}
                    </span>
                </td>
                <td>${dataCriacao}</td>
                <td>
                    <button class="btn btn-small btn-primary" onclick="editarUsuario(${usuario.id})">
                        ✏️ Editar
                    </button>
                    ${usuario.ativo 
                        ? `<button class="btn btn-small btn-danger" onclick="desativarUsuario(${usuario.id})">Desativar</button>`
                        : `<button class="btn btn-small btn-primary" onclick="ativarUsuario(${usuario.id})">Ativar</button>`
                    }
                </td>
            </tr>
        `;
    }).join('');
}

function filtrarUsuarios() {
    const busca = document.getElementById('busca-usuario').value.toLowerCase();
    const status = document.getElementById('filtro-status-usuario').value;
    
    let filtrados = todosUsuarios;
    
    // Filtro por busca
    if (busca) {
        filtrados = filtrados.filter(u => 
            u.nome.toLowerCase().includes(busca) || 
            u.codigo.toLowerCase().includes(busca)
        );
    }
    
    // Filtro por status
    if (status === 'ativo') {
        filtrados = filtrados.filter(u => u.ativo);
    } else if (status === 'inativo') {
        filtrados = filtrados.filter(u => !u.ativo);
    }
    
    renderizarUsuarios(filtrados);
}

// Modal de Usuário
function abrirModalUsuario(usuario = null) {
    const modal = document.getElementById('modal-usuario');
    const form = document.getElementById('form-usuario');
    const titulo = document.getElementById('modal-usuario-titulo');
    
    if (usuario) {
        titulo.textContent = 'Editar Usuário';
        document.getElementById('usuario-id').value = usuario.id;
        document.getElementById('usuario-nome').value = usuario.nome;
        document.getElementById('usuario-codigo').value = usuario.codigo;
        document.getElementById('usuario-ativo').value = usuario.ativo ? 'true' : 'false';
    } else {
        titulo.textContent = 'Novo Usuário';
        form.reset();
        document.getElementById('usuario-id').value = '';
    }
    
    modal.classList.add('active');
}

function fecharModalUsuario() {
    document.getElementById('modal-usuario').classList.remove('active');
    document.getElementById('form-usuario').reset();
}

async function salvarUsuario(event) {
    event.preventDefault();
    
    const id = document.getElementById('usuario-id').value;
    const nome = document.getElementById('usuario-nome').value.trim();
    const codigo = document.getElementById('usuario-codigo').value.trim();
    const ativo = document.getElementById('usuario-ativo').value === 'true';
    
    if (!nome || !codigo) {
        mostrarToast('Preencha todos os campos obrigatórios', 'error');
        return;
    }
    
    try {
        if (id) {
            // Editar
            await fazerRequisicaoAdmin(`/admin/usuarios/${id}`, {
                method: 'PUT',
                body: JSON.stringify({ nome, codigo, ativo })
            });
            mostrarToast('Usuário atualizado com sucesso!', 'success');
        } else {
            // Criar
            await fazerRequisicaoAdmin('/admin/usuarios', {
                method: 'POST',
                body: JSON.stringify({ nome, codigo, ativo })
            });
            mostrarToast('Usuário criado com sucesso!', 'success');
        }
        
        fecharModalUsuario();
        carregarUsuarios();
    } catch (error) {
        mostrarToast(error.message || 'Erro ao salvar usuário', 'error');
    }
}

async function editarUsuario(id) {
    const usuario = todosUsuarios.find(u => u.id === id);
    if (usuario) {
        abrirModalUsuario(usuario);
    }
}

async function desativarUsuario(id) {
    if (!confirm('Tem certeza que deseja desativar este usuário?')) {
        return;
    }
    
    try {
        await fazerRequisicaoAdmin(`/admin/usuarios/${id}/desativar`, {
            method: 'PUT'
        });
        mostrarToast('Usuário desativado com sucesso!', 'success');
        carregarUsuarios();
    } catch (error) {
        mostrarToast(error.message || 'Erro ao desativar usuário', 'error');
    }
}

async function ativarUsuario(id) {
    try {
        await fazerRequisicaoAdmin(`/admin/usuarios/${id}/ativar`, {
            method: 'PUT'
        });
        mostrarToast('Usuário ativado com sucesso!', 'success');
        carregarUsuarios();
    } catch (error) {
        mostrarToast(error.message || 'Erro ao ativar usuário', 'error');
    }
}

// ===== MÓDULO: VENDEDORES =====
function atualizarFiltrosPeriodoVendedores() {
    const periodo = document.getElementById('filtro-periodo-vendedores').value;
    const intervaloDiv = document.getElementById('intervalo-customizado-vendedores');
    
    if (periodo === 'customizado') {
        intervaloDiv.style.display = 'flex';
        intervaloDiv.style.alignItems = 'center';
    } else {
        intervaloDiv.style.display = 'none';
        carregarRankingVendedores();
    }
}

function aplicarIntervaloVendedores() {
    const dataInicio = document.getElementById('data-inicio-vendedores').value;
    const dataFim = document.getElementById('data-fim-vendedores').value;
    
    if (!dataInicio || !dataFim) {
        mostrarToast('Selecione as duas datas para o intervalo', 'error');
        return;
    }
    
    if (new Date(dataInicio) > new Date(dataFim)) {
        mostrarToast('A data de início deve ser anterior à data de fim', 'error');
        return;
    }
    
    carregarRankingVendedores();
}

async function carregarRankingVendedores() {
    const periodo = document.getElementById('filtro-periodo-vendedores').value;
    const tipo = document.getElementById('filtro-tipo-vendedores').value;
    let url = `/admin/vendedores/ranking?periodo=${periodo}&tipo=${tipo}`;
    
    // Se for intervalo customizado, adicionar datas
    if (periodo === 'customizado') {
        const dataInicio = document.getElementById('data-inicio-vendedores').value;
        const dataFim = document.getElementById('data-fim-vendedores').value;
        if (dataInicio && dataFim) {
            url += `&data_inicio=${dataInicio}&data_fim=${dataFim}`;
        } else {
            mostrarToast('Selecione as datas do intervalo', 'error');
            return;
        }
    }
    
    try {
        const data = await fazerRequisicaoAdmin(url);
        renderizarRankingVendedores(data.ranking || []);
    } catch (error) {
        console.error('Erro ao carregar ranking:', error);
        mostrarToast('Erro ao carregar ranking de vendedores', 'error');
        document.getElementById('ranking-vendedores').innerHTML = 
            '<div class="loading">Erro ao carregar ranking</div>';
    }
}

function renderizarRankingVendedores(ranking) {
    const container = document.getElementById('ranking-vendedores');
    
    if (ranking.length === 0) {
        container.innerHTML = '<div class="loading">Nenhum vendedor encontrado para o período selecionado</div>';
        return;
    }
    
    const medalhas = ['🥇', '🥈', '🥉'];
    
    container.innerHTML = ranking.map((vendedor, index) => {
        const medalha = index < 3 ? medalhas[index] : '';
        const posicao = index + 1;
        
        return `
            <div class="ranking-item">
                <div class="ranking-posicao">
                    ${medalha || posicao}
                </div>
                <div class="ranking-info">
                    <div class="ranking-nome">${vendedor.usuario_nome || 'Sem nome'} (${vendedor.usuario_codigo || 'Sem código'})</div>
                    <div class="ranking-detalhes">
                        <span>📊 ${vendedor.total_comandas} comandas</span>
                        <span>💰 Ticket médio: ${formatarMoeda(vendedor.ticket_medio || 0)}</span>
                    </div>
                </div>
                <div class="ranking-valor">
                    ${formatarMoeda(vendedor.total_valor || 0)}
                </div>
            </div>
        `;
    }).join('');
}

// ===== MÓDULO: PRODUTOS =====
function atualizarFiltrosPeriodoProdutos() {
    const periodo = document.getElementById('filtro-periodo-produtos').value;
    const intervaloDiv = document.getElementById('intervalo-customizado-produtos');
    
    if (periodo === 'customizado') {
        intervaloDiv.style.display = 'flex';
        intervaloDiv.style.alignItems = 'center';
    } else {
        intervaloDiv.style.display = 'none';
        carregarProdutosVendidos();
    }
}

function aplicarIntervaloProdutos() {
    const dataInicio = document.getElementById('data-inicio-produtos').value;
    const dataFim = document.getElementById('data-fim-produtos').value;
    
    if (!dataInicio || !dataFim) {
        mostrarToast('Selecione as duas datas para o intervalo', 'error');
        return;
    }
    
    if (new Date(dataInicio) > new Date(dataFim)) {
        mostrarToast('A data de início deve ser anterior à data de fim', 'error');
        return;
    }
    
    carregarProdutosVendidos();
}

async function carregarProdutosVendidos() {
    const periodo = document.getElementById('filtro-periodo-produtos').value;
    const ordem = document.getElementById('filtro-ordem-produtos').value;
    let url = `/admin/produtos/ranking?periodo=${periodo}&ordem=${ordem}`;
    
    // Se for intervalo customizado, adicionar datas
    if (periodo === 'customizado') {
        const dataInicio = document.getElementById('data-inicio-produtos').value;
        const dataFim = document.getElementById('data-fim-produtos').value;
        if (dataInicio && dataFim) {
            url += `&data_inicio=${dataInicio}&data_fim=${dataFim}`;
        } else {
            mostrarToast('Selecione as datas do intervalo', 'error');
            return;
        }
    }
    
    try {
        const data = await fazerRequisicaoAdmin(url);
        renderizarProdutosVendidos(data.produtos || []);
    } catch (error) {
        console.error('Erro ao carregar produtos:', error);
        mostrarToast('Erro ao carregar produtos mais vendidos', 'error');
        document.getElementById('tabela-produtos').innerHTML = 
            '<tr><td colspan="5" class="loading">Erro ao carregar produtos</td></tr>';
    }
}

function renderizarProdutosVendidos(produtos) {
    const tbody = document.getElementById('tabela-produtos');
    
    if (produtos.length === 0) {
        tbody.innerHTML = '<tr><td colspan="5" style="text-align: center; padding: 40px; color: #999;">Nenhum produto encontrado para o período selecionado</td></tr>';
        return;
    }
    
    tbody.innerHTML = produtos.map((produto, index) => {
        const posicao = index + 1;
        const quantidadeFormatada = produto.unidade === 'KG' 
            ? `${parseFloat(produto.quantidade_total).toFixed(3)} kg`
            : `${parseInt(produto.quantidade_total)} ${produto.unidade || 'UN'}`;
        
        return `
            <tr>
                <td><strong>#${posicao}</strong></td>
                <td>${produto.descricao || produto.produto_descricao || '-'}</td>
                <td>${quantidadeFormatada}</td>
                <td><strong>${formatarMoeda(produto.valor_total || 0)}</strong></td>
                <td>${produto.unidade || 'UN'}</td>
            </tr>
        `;
    }).join('');
}

// ===== MÓDULO: GAMIFICAÇÃO =====
function atualizarFiltrosGamificacao() {
    const tipo = document.getElementById('filtro-tipo-gamificacao').value;
    const periodoCustomizado = document.getElementById('periodo-customizado-gamificacao');
    const semanaSelect = document.getElementById('filtro-semana-gamificacao');
    const mesSelect = document.getElementById('filtro-mes-gamificacao');
    
    periodoCustomizado.style.display = 'none';
    semanaSelect.style.display = 'none';
    mesSelect.style.display = 'none';
    
    if (tipo === 'customizado') {
        periodoCustomizado.style.display = 'flex';
        periodoCustomizado.style.alignItems = 'center';
    } else if (tipo === 'semana') {
        semanaSelect.style.display = 'block';
        popularSemanas();
    } else if (tipo === 'mes') {
        mesSelect.style.display = 'block';
        popularMeses();
    }
    
    carregarGamificacao();
}

function popularSemanas() {
    const select = document.getElementById('filtro-semana-gamificacao');
    select.innerHTML = '<option value="0">Esta Semana</option>';
    
    // Adicionar últimas 4 semanas
    for (let i = 1; i <= 4; i++) {
        const data = new Date();
        data.setDate(data.getDate() - (data.getDay() + (i * 7)));
        const inicioSemana = new Date(data);
        inicioSemana.setDate(data.getDate() - data.getDay());
        const fimSemana = new Date(inicioSemana);
        fimSemana.setDate(inicioSemana.getDate() + 6);
        
        const texto = `Semana ${i} (${inicioSemana.toLocaleDateString('pt-BR')} - ${fimSemana.toLocaleDateString('pt-BR')})`;
        select.innerHTML += `<option value="${i}">${texto}</option>`;
    }
}

function popularMeses() {
    const select = document.getElementById('filtro-mes-gamificacao');
    select.innerHTML = '<option value="0">Este Mês</option>';
    
    // Adicionar últimos 6 meses
    for (let i = 1; i <= 6; i++) {
        const data = new Date();
        data.setMonth(data.getMonth() - i);
        const nomeMes = data.toLocaleDateString('pt-BR', { month: 'long', year: 'numeric' });
        select.innerHTML += `<option value="${i}">${nomeMes.charAt(0).toUpperCase() + nomeMes.slice(1)}</option>`;
    }
}

function aplicarPeriodoGamificacao() {
    const dataInicio = document.getElementById('data-inicio-gamificacao').value;
    const dataFim = document.getElementById('data-fim-gamificacao').value;
    
    if (!dataInicio || !dataFim) {
        mostrarToast('Selecione as duas datas para o período', 'error');
        return;
    }
    
    if (new Date(dataInicio) > new Date(dataFim)) {
        mostrarToast('A data de início deve ser anterior à data de fim', 'error');
        return;
    }
    
    carregarGamificacao();
}

async function carregarGamificacao() {
    const tipo = document.getElementById('filtro-tipo-gamificacao').value;
    let url = `/admin/gamificacao?tipo=${tipo}`;
    
    // Atualizar títulos
    const tituloBadges = document.getElementById('titulo-badges');
    const tituloHallFama = document.getElementById('titulo-hall-fama');
    
    if (tipo === 'customizado') {
        const dataInicio = document.getElementById('data-inicio-gamificacao').value;
        const dataFim = document.getElementById('data-fim-gamificacao').value;
        if (dataInicio && dataFim) {
            url += `&data_inicio=${dataInicio}&data_fim=${dataFim}`;
            tituloBadges.textContent = `🏆 Badges do Período`;
            tituloHallFama.textContent = `👑 Hall da Fama - Período Selecionado`;
        } else {
            mostrarToast('Selecione as datas do período', 'error');
            return;
        }
    } else if (tipo === 'semana') {
        const semana = parseInt(document.getElementById('filtro-semana-gamificacao').value);
        url += `&semana=${semana}`;
        tituloBadges.textContent = semana === 0 ? '🏆 Atendentes da Semana' : `🏆 Badges da Semana ${semana}`;
        tituloHallFama.textContent = semana === 0 ? '👑 Hall da Fama - Esta Semana' : `👑 Hall da Fama - Semana ${semana}`;
    } else if (tipo === 'mes') {
        const mes = parseInt(document.getElementById('filtro-mes-gamificacao').value);
        url += `&mes=${mes}`;
        tituloBadges.textContent = mes === 0 ? '🏆 Atendentes do Mês' : `🏆 Badges do Mês ${mes}`;
        tituloHallFama.textContent = mes === 0 ? '👑 Hall da Fama - Este Mês' : `👑 Hall da Fama - Mês ${mes}`;
    } else {
        tituloBadges.textContent = '🏆 Atendentes do Dia';
        tituloHallFama.textContent = '👑 Hall da Fama - Este Mês';
    }
    
    try {
        const data = await fazerRequisicaoAdmin(url);
        renderizarBadges(data.badges || []);
        renderizarHallFama(data.hall_fama || []);
    } catch (error) {
        console.error('Erro ao carregar gamificação:', error);
        mostrarToast('Erro ao carregar gamificação', 'error');
    }
}

function renderizarBadges(badges) {
    const container = document.getElementById('badges-dia');
    
    if (badges.length === 0) {
        container.innerHTML = '<div class="loading">Nenhum badge disponível hoje</div>';
        return;
    }
    
    const badgeConfig = {
        'campeao_dia': { icon: '🏆', nome: 'Campeão do Dia', cor: '#FFD700' },
        'estrela_semana': { icon: '⭐', nome: 'Estrela da Semana', cor: '#FFA500' },
        'rei_mes': { icon: '👑', nome: 'Coroa do Mês', cor: '#9370DB' },
        'crescimento': { icon: '🚀', nome: 'Maior Crescimento', cor: '#00CED1' },
        'alta_performance': { icon: '💎', nome: 'Alta Performance', cor: '#4169E1' }
    };
    
    container.innerHTML = badges.map(badge => {
        const config = badgeConfig[badge.tipo] || { icon: '🏅', nome: badge.tipo, cor: '#2ECC71' };
        
        return `
            <div class="badge-card" style="background: linear-gradient(135deg, ${config.cor} 0%, ${config.cor}dd 100%);">
                <div class="badge-icon">${config.icon}</div>
                <div class="badge-nome">${config.nome}</div>
                <div class="badge-valor">${badge.nome || 'N/A'} - ${formatarMoeda(badge.valor || 0)}</div>
            </div>
        `;
    }).join('');
}

function renderizarHallFama(hallFama) {
    const container = document.getElementById('hall-fama');
    
    if (hallFama.length === 0) {
        container.innerHTML = '<div class="loading">Nenhum vencedor ainda este mês</div>';
        return;
    }
    
    container.innerHTML = hallFama.map((vencedor, index) => {
        const medalhas = ['🥇', '🥈', '🥉'];
        const medalha = index < 3 ? medalhas[index] : '🏅';
        
        return `
            <div class="badge-card">
                <div class="badge-icon">${medalha}</div>
                <div class="badge-nome">${vencedor.usuario_nome || 'Sem nome'}</div>
                <div class="badge-valor">${formatarMoeda(vencedor.total_valor || 0)}</div>
            </div>
        `;
    }).join('');
}

// ===== MÓDULO: GERENCIAR PRODUTOS =====
let produtosLista = [];
let produtosFiltrados = [];

async function carregarProdutosGerenciar() {
    try {
        const data = await fazerRequisicaoAdmin('/produtos?limite=1000&todos=true');
        produtosLista = data.produtos || [];
        produtosFiltrados = produtosLista;
        renderizarProdutosGerenciar();
    } catch (error) {
        console.error('Erro ao carregar produtos:', error);
        mostrarToast('Erro ao carregar produtos', 'error');
        document.getElementById('tabela-gerenciar-produtos').innerHTML = 
            '<tr><td colspan="7" class="loading">Erro ao carregar produtos</td></tr>';
    }
}

function filtrarProdutos() {
    const busca = document.getElementById('busca-produto').value.toLowerCase();
    const status = document.getElementById('filtro-status-produto').value;
    
    produtosFiltrados = produtosLista.filter(produto => {
        const matchBusca = !busca || 
            produto.codigo_interno?.toLowerCase().includes(busca) ||
            produto.codigo_barras?.toLowerCase().includes(busca) ||
            produto.descricao?.toLowerCase().includes(busca);
        
        const matchStatus = status === 'todos' ||
            (status === 'ativo' && produto.ativo) ||
            (status === 'inativo' && !produto.ativo);
        
        return matchBusca && matchStatus;
    });
    
    renderizarProdutosGerenciar();
}

function renderizarProdutosGerenciar() {
    const tbody = document.getElementById('tabela-gerenciar-produtos');
    
    if (produtosFiltrados.length === 0) {
        tbody.innerHTML = '<tr><td colspan="7" style="text-align: center; padding: 40px; color: #999;">Nenhum produto encontrado</td></tr>';
        return;
    }
    
    tbody.innerHTML = produtosFiltrados.map(produto => {
        return `
            <tr>
                <td>${produto.codigo_interno || '-'}</td>
                <td>${produto.codigo_barras || '-'}</td>
                <td>${produto.descricao || '-'}</td>
                <td>${formatarMoeda(produto.preco_unitario || 0)}</td>
                <td>${produto.unidade || 'UN'}</td>
                <td>
                    <span class="badge ${produto.ativo ? 'badge-success' : 'badge-danger'}">
                        ${produto.ativo ? 'Ativo' : 'Inativo'}
                    </span>
                </td>
                <td>
                    <button class="btn btn-small btn-primary" onclick="editarProduto('${produto.codigo_interno}')">Editar</button>
                </td>
            </tr>
        `;
    }).join('');
}

function abrirModalProduto(codigo = null) {
    const modal = document.getElementById('modal-produto');
    const titulo = document.getElementById('titulo-modal-produto');
    const form = document.getElementById('form-produto');
    
    form.reset();
    document.getElementById('produto-id').value = '';
    
    if (codigo) {
        titulo.textContent = 'Editar Produto';
        const produto = produtosLista.find(p => p.codigo_interno === codigo);
        if (produto) {
            document.getElementById('produto-codigo').value = produto.codigo_interno || '';
            document.getElementById('produto-codigo').disabled = true;
            document.getElementById('produto-barras').value = produto.codigo_barras || '';
            document.getElementById('produto-descricao').value = produto.descricao || '';
            document.getElementById('produto-preco').value = produto.preco_unitario || 0;
            document.getElementById('produto-unidade').value = produto.unidade || 'UN';
            document.getElementById('produto-tributacao').value = produto.tributacao_codigo || '123';
            document.getElementById('produto-ativo').checked = produto.ativo !== false;
        }
    } else {
        titulo.textContent = 'Novo Produto';
        document.getElementById('produto-codigo').disabled = false;
    }
    
    modal.style.display = 'block';
}

function fecharModalProduto() {
    document.getElementById('modal-produto').style.display = 'none';
    document.getElementById('form-produto').reset();
}

async function salvarProduto(event) {
    event.preventDefault();
    
    const codigo = document.getElementById('produto-codigo').value;
    const barras = document.getElementById('produto-barras').value;
    const descricao = document.getElementById('produto-descricao').value;
    const preco = parseFloat(document.getElementById('produto-preco').value);
    const unidade = document.getElementById('produto-unidade').value;
    const tributacao = document.getElementById('produto-tributacao').value;
    const ativo = document.getElementById('produto-ativo').checked;
    
    try {
        // Verificar se produto existe
        const produtoExistente = produtosLista.find(p => p.codigo_interno === codigo);
        
        if (produtoExistente) {
            // Atualizar produto existente
            await fazerRequisicaoAdmin(`/produtos/${codigo}`, {
                method: 'PUT',
                body: JSON.stringify({
                    descricao,
                    codigo_barras: barras || null,
                    preco_unitario: preco,
                    unidade,
                    tributacao_codigo: tributacao,
                    ativo
                })
            });
            
            mostrarToast('Produto atualizado com sucesso!', 'success');
        } else {
            // Criar novo produto - precisamos criar endpoint no backend
            mostrarToast('Funcionalidade de criar produto ainda não implementada no backend', 'error');
            return;
        }
        
        fecharModalProduto();
        carregarProdutosGerenciar();
    } catch (error) {
        console.error('Erro ao salvar produto:', error);
        mostrarToast(error.message || 'Erro ao salvar produto', 'error');
    }
}

function editarProduto(codigo) {
    abrirModalProduto(codigo);
}

// ===== FUNÇÕES AUXILIARES =====
function formatarMoeda(valor) {
    return new Intl.NumberFormat('pt-BR', {
        style: 'currency',
        currency: 'BRL'
    }).format(valor || 0);
}

// Inicialização quando a página carregar
document.addEventListener('DOMContentLoaded', () => {
    // Carregar dashboard por padrão
    carregarDashboard();
    
    // Atualizar dashboard a cada 30 segundos
    setInterval(() => {
        if (document.getElementById('modulo-dashboard').classList.contains('active')) {
            carregarDashboard();
        }
    }, 30000);
});

