/* ===== COMANDAS MOBILE - JAVASCRIPT PRINCIPAL ===== */

// Configurações da API
const API_BASE_URL = `${window.location.origin}/api`;

// Funções utilitárias

/**
 * MUDANÇA 1: Sistema de Toast Notifications
 * Substitui as mensagens estáticas do topo por notificações flutuantes
 */
function mostrarAlerta(mensagem, tipo = 'info') {
    // Criar ou obter container de toasts
    let toastContainer = document.getElementById('toast-container');
    if (!toastContainer) {
        toastContainer = document.createElement('div');
        toastContainer.id = 'toast-container';
        toastContainer.className = 'toast-container';
        document.body.appendChild(toastContainer);
    }
    
    // Criar toast
    const toast = document.createElement('div');
    toast.className = `toast toast-${tipo}`;
    
    const content = document.createElement('div');
    content.className = 'toast-content';
    content.textContent = mensagem;
    
    const closeBtn = document.createElement('button');
    closeBtn.className = 'toast-close';
    closeBtn.innerHTML = '&times;';
    closeBtn.setAttribute('aria-label', 'Fechar');
    closeBtn.onclick = () => removerToast(toast);
    
    toast.appendChild(content);
    toast.appendChild(closeBtn);
    toastContainer.appendChild(toast);
    
    // Comportamento baseado no tipo
    if (tipo === 'success' || tipo === 'info') {
        // Sucesso/Info: desaparecer automaticamente após 3-4 segundos
        setTimeout(() => {
            removerToast(toast);
        }, tipo === 'success' ? 3000 : 4000);
    }
    // Erro: permanece até o usuário fechar manualmente
}

/**
 * Remove um toast com animação
 */
function removerToast(toast) {
    toast.classList.add('toast-removing');
    setTimeout(() => {
        if (toast.parentNode) {
            toast.parentNode.removeChild(toast);
        }
        
        // Remover container se não houver mais toasts
        const container = document.getElementById('toast-container');
        if (container && container.children.length === 0) {
            container.remove();
        }
    }, 300);
}

function formatarMoeda(valor) {
    return `R$ ${parseFloat(valor).toFixed(2).replace('.', ',')}`;
}

function formatarData(data) {
    if (!data) return 'Data não disponível';
    
    // Se a data já é uma string ISO, criar Date diretamente
    let dataObj;
    if (typeof data === 'string') {
        // Se for string ISO, usar diretamente
        if (data.includes('T') || data.includes('Z')) {
            dataObj = new Date(data);
        } else {
            // Se for string de data local, tentar parsear
            dataObj = new Date(data);
        }
    } else {
        dataObj = new Date(data);
    }
    
    // Verificar se a data é válida
    if (isNaN(dataObj.getTime())) {
        return 'Data inválida';
    }
    
    // Formatar no timezone local sem conversão adicional
    // Usar toLocaleString com opções específicas para manter o horário correto
    const opcoes = {
        day: '2-digit',
        month: '2-digit',
        year: 'numeric',
        hour: '2-digit',
        minute: '2-digit',
        second: '2-digit',
        hour12: false,
        timeZone: Intl.DateTimeFormat().resolvedOptions().timeZone
    };
    
    return dataObj.toLocaleString('pt-BR', opcoes);
}

// Funções de API
async function fazerRequisicao(endpoint, opcoes = {}) {
    try {
        const response = await fetch(`${API_BASE_URL}${endpoint}`, {
            headers: {
                'Content-Type': 'application/json',
                ...opcoes.headers
            },
            ...opcoes
        });
        
        // Retornar a response diretamente para que o código chamador possa verificar o status
        return response;
    } catch (error) {
        console.error('Erro na requisição:', error);
        mostrarAlerta(`Erro: ${error.message}`, 'error');
        throw error;
    }
}

// Funções específicas para produtos
async function buscarProdutos(termo) {
    if (termo.length < 2) {
        document.getElementById('resultadosBusca').style.display = 'none';
        return;
    }

    try {
        const response = await fazerRequisicao(`/produtos?busca=${encodeURIComponent(termo)}`);
        const data = await response.json();
        
        const resultadosDiv = document.getElementById('resultadosBusca');
        resultadosDiv.innerHTML = '';

        if (data.produtos && data.produtos.length > 0) {
            data.produtos.forEach(produto => {
                const item = document.createElement('div');
                const codigoExibicao = produto.codigo_barras || produto.codigo_interno;
                item.innerHTML = `
                    <strong>${codigoExibicao}</strong> - ${produto.descricao}<br>
                    <small>${formatarMoeda(produto.preco_unitario)} | ${produto.unidade}</small>
                `;
                item.onclick = () => selecionarProduto(produto);
                resultadosDiv.appendChild(item);
            });
            resultadosDiv.style.display = 'block';
        } else {
            resultadosDiv.innerHTML = '<div style="padding: 15px; color: #666;">Nenhum produto encontrado</div>';
            resultadosDiv.style.display = 'block';
        }
    } catch (error) {
        console.error('Erro ao buscar produtos:', error);
        document.getElementById('resultadosBusca').style.display = 'none';
    }
}

function selecionarProduto(produto) {
    // Apenas preencher o campo e aguardar o usuário digitar quantidade
    const codigoParaUsar = produto.codigo_barras || produto.codigo_interno;
    document.getElementById('buscaProduto').value = codigoParaUsar;
    document.getElementById('quantidadeProduto').value = '1';
    document.getElementById('resultadosBusca').style.display = 'none';
    
    // MUDANÇA 1: Ajustar validação do campo quantidade baseado no tipo de produto
    ajustarCampoQuantidade(produto.prod_balanca || 'N');
    
    // Focar no campo de quantidade para facilitar
    document.getElementById('quantidadeProduto').focus();
    document.getElementById('quantidadeProduto').select();
}

/**
 * MUDANÇA 1: Ajusta o campo quantidade baseado no tipo de produto
 * @param {string} prod_balanca - 'U' para Unitário, 'P' para Pesável, 'N' para Não especificado
 */
function ajustarCampoQuantidade(prod_balanca) {
    const campoQuantidade = document.getElementById('quantidadeProduto');
    
    if (prod_balanca === 'U') {
        // Produto Unitário: apenas números inteiros (step="1")
        campoQuantidade.step = '1';
        campoQuantidade.min = '1';
        // Validar e arredondar se necessário
        const valorAtual = parseFloat(campoQuantidade.value);
        if (valorAtual && !Number.isInteger(valorAtual)) {
            campoQuantidade.value = Math.round(valorAtual).toString();
        }
    } else {
        // Produto Pesável ou outro: permite decimais (step="0.001")
        campoQuantidade.step = '0.001';
        campoQuantidade.min = '0.001';
    }
}

/**
 * MUDANÇA 1: Valida quantidade antes de adicionar produto
 * @param {number} quantidade - Quantidade a validar
 * @param {string} prod_balanca - Tipo de produto ('U' ou 'P')
 * @returns {boolean} - True se válido, False se inválido
 */
function validarQuantidade(quantidade, prod_balanca) {
    if (prod_balanca === 'U') {
        // Produto Unitário: deve ser número inteiro
        if (!Number.isInteger(quantidade)) {
            mostrarAlerta('Produto unitário só aceita quantidade inteira (ex: 1, 2, 3). Decimais não são permitidos.', 'error');
            return false;
        }
        if (quantidade < 1) {
            mostrarAlerta('Quantidade deve ser maior que zero.', 'error');
            return false;
        }
    } else {
        // Produto Pesável: permite decimais
        if (quantidade <= 0) {
            mostrarAlerta('Quantidade deve ser maior que zero.', 'error');
            return false;
        }
    }
    return true;
}

/**
 * MUDANÇA 1: Valida quantidade em tempo real durante digitação
 * Usado no evento oninput do campo quantidade
 */
function validarQuantidadeInput() {
    const campoQuantidade = document.getElementById('quantidadeProduto');
    const valorAtual = campoQuantidade.value;
    
    // Tentar buscar produto atual do campo busca para verificar tipo
    const codigo = document.getElementById('buscaProduto').value.trim();
    
    if (!codigo || valorAtual === '') {
        return; // Não validar se não houver produto selecionado ou campo vazio
    }
    
    // Buscar produto para verificar tipo (assíncrono - não bloquear digitação)
    fazerRequisicao(`/produtos?busca=${encodeURIComponent(codigo)}`)
        .then(response => response.json())
        .then(data => {
            if (data.produtos && data.produtos.length > 0) {
                const produto = data.produtos[0];
                const prod_balanca = produto.prod_balanca || 'N';
                
                if (prod_balanca === 'U') {
                    // Produto Unitário: arredondar automaticamente se houver decimal
                    const valorNumerico = parseFloat(valorAtual);
                    if (!isNaN(valorNumerico) && !Number.isInteger(valorNumerico)) {
                        const valorInteiro = Math.round(valorNumerico);
                        campoQuantidade.value = valorInteiro.toString();
                        // Não mostrar alerta aqui para não interromper o fluxo
                    }
                }
            }
        })
        .catch(error => {
            // Ignorar erros de busca durante digitação
            console.debug('Erro ao buscar produto para validação:', error);
        });
}

// Função para processar etiquetas de balança
async function processarEtiquetaBalanca(codigoBarras) {
    // Verificar se é uma etiqueta de balança (13 dígitos começando com 2)
    if (codigoBarras.length === 13 && codigoBarras.startsWith('2')) {
        try {
            console.log('Processando etiqueta de balança:', codigoBarras);
            
            // Chamar API para processar etiqueta de balança
            const response = await fazerRequisicao('/comandas/processar-etiqueta', {
                method: 'POST',
                body: JSON.stringify({ codigo_barras: codigoBarras }),
                headers: { 'Content-Type': 'application/json' }
            });
            
            if (response.ok) {
                const data = await response.json();
                
                // Adicionar produto à comanda com quantidade calculada
                const produtoComanda = {
                    ...data.produto,
                    quantidade: data.quantidade,
                    total_item: data.quantidade * data.produto.preco_unitario
                };
                
                const indexExistente = produtosComanda.findIndex(p => p.codigo_interno === data.produto.codigo_interno);
                if (indexExistente >= 0) {
                    // Se já existe, incrementar quantidade
                    produtosComanda[indexExistente].quantidade += data.quantidade;
                    produtosComanda[indexExistente].total_item = produtosComanda[indexExistente].quantidade * data.produto.preco_unitario;
                } else {
                    // Se não existe, adicionar
                    produtosComanda.push(produtoComanda);
                }
                
                atualizarListaProdutos();
                atualizarTotalComanda();
                
                // MUDANÇA 2: Limpar campos e focar automaticamente no campo de busca
                document.getElementById('buscaProduto').value = '';
                const prod_balanca = data.produto.prod_balanca || 'N';
                document.getElementById('quantidadeProduto').value = prod_balanca === 'U' ? '1' : '1.000';
                document.getElementById('resultadosBusca').style.display = 'none';
                
                // MUDANÇA 2: Retornar foco automaticamente para o campo de busca
                setTimeout(() => {
                    const campoBusca = document.getElementById('buscaProduto');
                    campoBusca.focus();
                    campoBusca.select();
                }, 100);
                
                mostrarAlerta(`Etiqueta processada! ${data.produto.prod_descrpdvs || data.produto.descricao} - ${data.quantidade}${data.produto.unidade}`, 'success');
            } else {
                const errorData = await response.json();
                mostrarAlerta(`Erro: ${errorData.error}`, 'error');
            }
        } catch (error) {
            console.error('Erro ao processar etiqueta de balança:', error);
            mostrarAlerta('Erro ao processar etiqueta de balança', 'error');
        }
    }
}

// Função para detectar entrada rápida (scanner)
let ultimaEntrada = '';
let tempoUltimaEntrada = 0;

function detectarScanner(input, proximoCampo) {
    const agora = Date.now();
    const tempoDecorrido = agora - tempoUltimaEntrada;
    const tamanhoAtual = input.value.length;
    const tamanhoAnterior = ultimaEntrada.length;
    
    // Verificar se é digitação manual normal (lenta) ou scanner (rápida)
    const ehDigitarNormal = tempoDecorrido > 100; // Mais de 100ms entre caracteres
    
    // Se a entrada foi muito rápida (menos de 50ms entre caracteres), provavelmente é um scanner
    if (!ehDigitarNormal && tempoDecorrido < 50 && tamanhoAtual > tamanhoAnterior) {
        // Verificar se é uma etiqueta de balança (13 dígitos começando com 2)
        if (tamanhoAtual === 13 && input.value.startsWith('2')) {
            processarEtiquetaBalanca(input.value);
            return;
        }
        
        // Para códigos normais de scanner, apenas avançar para o próximo campo
        if (tamanhoAtual >= 13) { // Código de barras completado
            setTimeout(() => {
                if (proximoCampo) {
                    proximoCampo.focus();
                }
            }, 100);
        }
    }
    
    ultimaEntrada = input.value;
    tempoUltimaEntrada = agora;
}

// Funções para comandas ativas
function verDetalhesComanda(numeroComanda) {
    // Buscar detalhes da comanda
    buscarDetalhesComanda(numeroComanda).then(detalhes => {
        if (detalhes) {
            mostrarModalDetalhes(detalhes);
        }
    });
}

function editarComanda(numeroComanda) {
    // Redirecionar para nova comanda com número para edição
    window.location.href = `/pages/nova-comanda.html?numero=${numeroComanda}`;
}

async function cancelarComanda(numeroComanda) {
    if (confirm(`Tem certeza que deseja cancelar a comanda ${numeroComanda}?`)) {
        try {
            const response = await fazerRequisicao(`/comandas/${numeroComanda}`, {
                method: 'DELETE'
            });
            
            if (response.ok) {
                mostrarAlerta('Comanda cancelada com sucesso!', 'success');
                carregarComandasAtivas(); // Recarregar a lista
            } else {
                const errorData = await response.json();
                mostrarAlerta(`Erro ao cancelar comanda: ${errorData.error}`, 'error');
            }
        } catch (error) {
            console.error('Erro ao cancelar comanda:', error);
            mostrarAlerta('Erro ao cancelar comanda', 'error');
        }
    }
}

async function buscarDetalhesComanda(numeroComanda) {
    try {
        console.log(`Buscando detalhes da comanda: ${numeroComanda}`);
        const response = await fazerRequisicao(`/comandas/${numeroComanda}`);
        const data = await response.json();
        console.log('Detalhes encontrados:', data);
        return data;
    } catch (error) {
        console.error('Erro ao buscar detalhes da comanda:', error);
        mostrarAlerta('Erro ao carregar detalhes da comanda', 'error');
        return null;
    }
}

function mostrarModalDetalhes(detalhes) {
    console.log('Mostrando modal com detalhes:', detalhes);
    
    const modal = document.createElement('div');
    modal.style.cssText = `
        position: fixed; top: 0; left: 0; width: 100%; height: 100%; 
        background: rgba(0,0,0,0.5); z-index: 1000; display: flex; 
        align-items: center; justify-content: center;
    `;
    
    // Verificar se os dados vêm do TXT ou do banco
    const comanda = detalhes.comanda || detalhes;
    const itens = detalhes.itens || detalhes.produtos || [];
    
    modal.innerHTML = `
        <div style="background: white; border-radius: 15px; padding: 30px; max-width: 600px; width: 90%; max-height: 80vh; overflow-y: auto;">
            <div style="display: flex; justify-content: space-between; align-items: center; margin-bottom: 20px;">
                <h3 style="color: #2ECC71; margin: 0;">Detalhes da Comanda ${comanda.numero_comanda}</h3>
                <button onclick="this.closest('.modal').remove()" style="background: none; border: none; font-size: 24px; cursor: pointer;">&times;</button>
            </div>
            
            <div style="margin-bottom: 20px;">
                <strong>Cliente:</strong> ${comanda.cliente_codigo || '113727'}<br>
                <strong>Status:</strong> ${comanda.status || 'ATIVA'}<br>
                <strong>Criada em:</strong> ${formatarData(comanda.data_criacao)}<br>
                ${comanda.usuario_codigo && comanda.usuario_nome ? `<strong>Atendido por:</strong> ${comanda.usuario_codigo} - ${comanda.usuario_nome}` : ''}
            </div>
            
            <h4 style="color: #2ECC71; margin-bottom: 15px;">Produtos:</h4>
            <div style="overflow-x: auto;">
                <table style="width: 100%; border-collapse: collapse;">
                    <thead>
                        <tr style="background: #E8F8F5;">
                            <th style="padding: 10px; border: 1px solid #D5DBDB; text-align: left;">Código</th>
                            <th style="padding: 10px; border: 1px solid #D5DBDB; text-align: left;">Descrição</th>
                            <th style="padding: 10px; border: 1px solid #D5DBDB; text-align: center;">Quant</th>
                            <th style="padding: 10px; border: 1px solid #D5DBDB; text-align: right;">Valor Unt</th>
                            <th style="padding: 10px; border: 1px solid #D5DBDB; text-align: right;">Total</th>
                        </tr>
                    </thead>
                    <tbody>
                        ${itens.length > 0 ? itens.map(item => `
                            <tr>
                                <td style="padding: 10px; border: 1px solid #D5DBDB;">${item.produto_codigo || item.codigo_interno || 'N/A'}</td>
                                <td style="padding: 10px; border: 1px solid #D5DBDB;">${item.produto_descrpdvs || item.prod_descrpdvs || item.produto_descricao || item.descricao || 'N/A'}</td>
                                <td style="padding: 10px; border: 1px solid #D5DBDB; text-align: center;">${item.quantidade || 0}</td>
                                <td style="padding: 10px; border: 1px solid #D5DBDB; text-align: right;">${formatarMoeda(item.preco_unitario || 0)}</td>
                                <td style="padding: 10px; border: 1px solid #D5DBDB; text-align: right;"><strong>${formatarMoeda(item.total_item || 0)}</strong></td>
                            </tr>
                        `).join('') : '<tr><td colspan="5" style="text-align: center; padding: 20px;">Nenhum produto encontrado</td></tr>'}
                    </tbody>
                </table>
            </div>
            
            <div style="margin-top: 20px; padding: 15px; background: #F8F9FA; border-radius: 10px; text-align: center;">
                <strong style="color: #2ECC71; font-size: 18px;">Total: ${formatarMoeda(itens.length > 0 ? itens.reduce((sum, item) => {
                    const valor = typeof item.total_item === 'string' 
                        ? parseFloat(item.total_item.replace(/[^\d,.-]/g, '').replace(',', '.')) 
                        : parseFloat(item.total_item) || 0;
                    return sum + (isNaN(valor) ? 0 : valor);
                }, 0) : (typeof comanda.total_valor === 'string' 
                    ? parseFloat(comanda.total_valor.replace(/[^\d,.-]/g, '').replace(',', '.')) 
                    : parseFloat(comanda.total_valor)) || 0)}</strong>
            </div>
            
            <div style="text-align: center; margin-top: 20px;">
                <button class="btn btn-secondary" onclick="this.closest('.modal').remove()">Fechar</button>
            </div>
        </div>
    `;
    
    modal.className = 'modal';
    document.body.appendChild(modal);
}

// Funções específicas para comandas
async function carregarEstatisticas() {
    try {
        // CORREÇÃO: fazerRequisicao retorna Response, precisa converter para JSON
        const [comandasResponse, produtosResponse] = await Promise.all([
            fazerRequisicao('/comandas/rede'),
            fazerRequisicao('/produtos/estatisticas')
        ]);

        // Verificar se as respostas foram bem-sucedidas
        if (!comandasResponse.ok) {
            console.error('Erro ao buscar comandas:', comandasResponse.status);
            throw new Error('Erro ao buscar comandas');
        }

        if (!produtosResponse.ok) {
            console.error('Erro ao buscar produtos:', produtosResponse.status);
            throw new Error('Erro ao buscar produtos');
        }

        // Converter respostas para JSON
        const comandasData = await comandasResponse.json();
        const produtosData = await produtosResponse.json();

        // Atualizar estatísticas de comandas
        if (document.getElementById('totalComandas')) {
            const totalComandas = comandasData.total || (comandasData.comandas ? comandasData.comandas.length : 0);
            document.getElementById('totalComandas').textContent = totalComandas;
            console.log('Total de comandas ativas:', totalComandas);
        }

        // Atualizar estatísticas de produtos
        if (document.getElementById('totalProdutos')) {
            const totalProdutos = produtosData.total_produtos || 0;
            document.getElementById('totalProdutos').textContent = totalProdutos;
            console.log('Total de produtos cadastrados:', totalProdutos);
        }

        console.log('Estatísticas carregadas:', { 
            comandas: comandasData.total || comandasData.comandas?.length || 0, 
            produtos: produtosData.total_produtos || 0 
        });
    } catch (error) {
        console.error('Erro ao carregar estatísticas:', error);
        // Definir valores padrão em caso de erro
        if (document.getElementById('totalComandas')) {
            document.getElementById('totalComandas').textContent = '0';
        }
        if (document.getElementById('totalProdutos')) {
            document.getElementById('totalProdutos').textContent = '0';
        }
    }
}

// Variável global para armazenar todas as comandas (para filtro)
let todasComandasAtivas = [];

async function carregarComandasAtivas() {
    const listaDiv = document.getElementById('listaComandas');
    if (!listaDiv) return;
    
    listaDiv.innerHTML = '<div class="loading"><div class="spinner"></div><p>Carregando comandas...</p></div>';

    try {
        const response = await fazerRequisicao('/comandas/rede');
        const data = await response.json();
        todasComandasAtivas = data.comandas || [];

        // Atualizar estatísticas
        if (document.getElementById('totalComandas')) {
            document.getElementById('totalComandas').textContent = todasComandasAtivas.length;
        }

        // Renderizar todas as comandas (sem filtro inicial)
        renderizarComandas(todasComandasAtivas);

    } catch (error) {
        console.error('Erro ao carregar comandas:', error);
        listaDiv.innerHTML = '<div class="alert alert-error">Erro ao carregar comandas</div>';
    }
}

function renderizarComandas(comandas) {
    const listaDiv = document.getElementById('listaComandas');
    if (!listaDiv) return;

    if (comandas.length === 0) {
        listaDiv.innerHTML = '<div style="text-align: center; padding: 40px; color: #7F8C8D;">Nenhuma comanda encontrada</div>';
        return;
    }

    listaDiv.innerHTML = comandas.map(comanda => `
        <div class="comanda-item">
            <div class="comanda-header">
                <div class="comanda-numero">Comanda ${comanda.numero_comanda || 'N/A'}</div>
                <div class="comanda-total">${formatarMoeda(comanda.total_valor || 0)}</div>
            </div>
            <div class="comanda-info">
                <strong>Criada em:</strong> ${formatarData(comanda.data_criacao)}<br>
                <strong>Status:</strong> ${comanda.status}<br>
                <strong>Cliente:</strong> ${comanda.cliente_codigo || '113727'}<br>
                ${comanda.usuario_codigo && comanda.usuario_nome ? `<strong>Atendido por:</strong> ${comanda.usuario_codigo} - ${comanda.usuario_nome}<br>` : ''}
            </div>
            <div class="comanda-actions">
                <button class="btn btn-small" onclick="verDetalhesComanda('${comanda.numero_comanda}')">👁️ Ver Detalhes</button>
                <button class="btn btn-secondary btn-small" onclick="editarComanda('${comanda.numero_comanda}')">✏️ Editar</button>
                <button class="btn btn-danger btn-small" onclick="cancelarComanda('${comanda.numero_comanda}')">❌ Cancelar</button>
            </div>
        </div>
    `).join('');
}

function filtrarComandas() {
    const buscaInput = document.getElementById('buscaComanda');
    if (!buscaInput) return;

    const termoBusca = buscaInput.value.trim().toLowerCase();
    
    if (!termoBusca) {
        // Se não há termo de busca, mostrar todas
        renderizarComandas(todasComandasAtivas);
        return;
    }

    // Filtrar comandas pelo número
    const comandasFiltradas = todasComandasAtivas.filter(comanda => {
        const numeroComanda = (comanda.numero_comanda || '').toLowerCase();
        // Permitir busca parcial (ex: "100" encontra "000100")
        return numeroComanda.includes(termoBusca) || numeroComanda.replace(/^0+/, '').includes(termoBusca);
    });

    renderizarComandas(comandasFiltradas);
}

function limparBusca() {
    const buscaInput = document.getElementById('buscaComanda');
    if (buscaInput) {
        buscaInput.value = '';
    }
    renderizarComandas(todasComandasAtivas);
}

// Variável global para armazenar o atendente selecionado
let atendenteSelecionado = null;

// Funções de seleção de atendente
async function mostrarModalSelecaoAtendente() {
    // Criar modal
    const modal = document.createElement('div');
    modal.id = 'modalSelecaoAtendente';
    modal.style.cssText = `
        position: fixed; top: 0; left: 0; width: 100%; height: 100%; 
        background: rgba(0,0,0,0.5); z-index: 1000; display: flex; 
        align-items: center; justify-content: center;
    `;
    
    modal.innerHTML = `
        <div style="background: white; border-radius: 15px; padding: 30px; max-width: 500px; width: 90%; box-shadow: 0 8px 25px rgba(0,0,0,0.3);">
            <div style="display: flex; justify-content: space-between; align-items: center; margin-bottom: 20px;">
                <h3 style="color: #2ECC71; margin: 0;">Quem está atendendo?</h3>
                <button onclick="fecharModalSelecaoAtendente()" style="background: none; border: none; font-size: 24px; cursor: pointer; color: #7F8C8D;">&times;</button>
            </div>
            
            <div class="form-group" style="margin-bottom: 20px;">
                <label for="buscaAtendente">Digite o código ou nome do atendente:</label>
                <input 
                    type="text" 
                    id="buscaAtendente" 
                    placeholder="Ex: 1 ou Maria" 
                    style="width: 100%; padding: 12px; border: 2px solid #E8F8F5; border-radius: 8px; font-size: 16px; box-sizing: border-box;"
                    autofocus
                    oninput="buscarAtendentes(this.value)"
                    onkeyup="if(event.key==='Enter') confirmarAtendente()"
                >
            </div>
            
            <div id="resultadosAtendentes" style="max-height: 300px; overflow-y: auto; margin-bottom: 20px; border: 1px solid #E8F8F5; border-radius: 8px; display: none;"></div>
            
            <div style="text-align: right;">
                <button class="btn btn-secondary" onclick="fecharModalSelecaoAtendente()" style="margin-right: 10px;">Cancelar</button>
                <button class="btn btn-primary" onclick="confirmarAtendente()">Confirmar</button>
            </div>
        </div>
    `;
    
    document.body.appendChild(modal);
    
    // Focar no campo de busca
    setTimeout(() => {
        const campoBusca = document.getElementById('buscaAtendente');
        if (campoBusca) {
            campoBusca.focus();
        }
    }, 100);
    
    // Carregar todos os atendentes ao abrir
    buscarAtendentes('');
}

async function buscarAtendentes(termo) {
    const resultadosDiv = document.getElementById('resultadosAtendentes');
    if (!resultadosDiv) return;
    
    try {
        const response = await fazerRequisicao(`/usuarios?busca=${encodeURIComponent(termo)}`);
        const data = await response.json();
        
        if (data.usuarios && data.usuarios.length > 0) {
            resultadosDiv.style.display = 'block';
            resultadosDiv.innerHTML = data.usuarios.map(usuario => `
                <div 
                    class="resultado-atendente" 
                    onclick="selecionarAtendente(${usuario.id}, '${usuario.nome.replace(/'/g, "\\'")}', '${usuario.codigo}', this)"
                    style="padding: 12px; cursor: pointer; border-bottom: 1px solid #E8F8F5; transition: background 0.2s;"
                    onmouseover="this.style.background='#E8F8F5'"
                    onmouseout="this.style.background='white'"
                >
                    <strong>${usuario.codigo} - ${usuario.nome}</strong>
                </div>
            `).join('');
        } else {
            resultadosDiv.style.display = termo.trim().length > 0 ? 'block' : 'none';
            resultadosDiv.innerHTML = termo.trim().length > 0 
                ? '<div style="padding: 20px; text-align: center; color: #7F8C8D;">Nenhum atendente encontrado</div>'
                : '';
        }
    } catch (error) {
        console.error('Erro ao buscar atendentes:', error);
        resultadosDiv.style.display = 'block';
        resultadosDiv.innerHTML = '<div style="padding: 20px; text-align: center; color: #E74C3C;">Erro ao buscar atendentes</div>';
    }
}

function selecionarAtendente(id, nome, codigo, elemento) {
    atendenteSelecionado = { id, nome, codigo };
    
    // Atualizar campo de busca com o selecionado
    const campoBusca = document.getElementById('buscaAtendente');
    if (campoBusca) {
        campoBusca.value = `${codigo} - ${nome}`;
    }
    
    // Destacar o item selecionado
    const resultados = document.querySelectorAll('.resultado-atendente');
    resultados.forEach(item => {
        item.style.background = 'white';
    });
    if (elemento) {
        elemento.style.background = '#D5F4E6';
    }
    
    // Confirmar automaticamente após seleção
    setTimeout(() => {
        confirmarAtendente();
    }, 300);
}

async function confirmarAtendente() {
    const campoBusca = document.getElementById('buscaAtendente');
    if (!campoBusca) return;
    
    const termo = campoBusca.value.trim();
    
    // Se já tem atendente selecionado, usar ele
    if (atendenteSelecionado) {
        // Salvar no sessionStorage para usar na página nova-comanda
        sessionStorage.setItem('atendenteSelecionado', JSON.stringify(atendenteSelecionado));
        fecharModalSelecaoAtendente();
        abrirNovaComanda();
        return;
    }
    
    // Se não tem selecionado, buscar pelo termo digitado
    if (!termo) {
        mostrarAlerta('Digite o código ou nome do atendente', 'error');
        return;
    }
    
    try {
        const response = await fazerRequisicao(`/usuarios?busca=${encodeURIComponent(termo)}`);
        const data = await response.json();
        
        if (data.usuarios && data.usuarios.length > 0) {
            // Pegar o primeiro resultado (mais relevante)
            const usuario = data.usuarios[0];
            atendenteSelecionado = {
                id: usuario.id,
                nome: usuario.nome,
                codigo: usuario.codigo
            };
            
            // Salvar no sessionStorage
            sessionStorage.setItem('atendenteSelecionado', JSON.stringify(atendenteSelecionado));
            fecharModalSelecaoAtendente();
            abrirNovaComanda();
        } else {
            mostrarAlerta('Atendente não encontrado. Digite o código ou nome corretamente.', 'error');
        }
    } catch (error) {
        console.error('Erro ao confirmar atendente:', error);
        mostrarAlerta('Erro ao buscar atendente', 'error');
    }
}

function fecharModalSelecaoAtendente() {
    const modal = document.getElementById('modalSelecaoAtendente');
    if (modal) {
        modal.remove();
    }
    atendenteSelecionado = null;
}

// Funções de navegação
function abrirNovaComanda() {
    window.location.href = 'pages/nova-comanda.html';
}

function abrirComandasAtivas() {
    window.location.href = 'pages/comandas-ativas.html';
}

function voltarAoInicio() {
    window.location.href = '/';
}

// Funções específicas para nova comanda
let produtosComanda = [];

async function adicionarProduto() {
    const codigo = document.getElementById('buscaProduto').value.trim();
    let quantidade = parseFloat(document.getElementById('quantidadeProduto').value) || 1;

    if (!codigo) {
        mostrarAlerta('Digite o código do produto', 'error');
        return;
    }

    try {
        // Buscar produto usando a API de busca
        const response = await fazerRequisicao(`/produtos?busca=${encodeURIComponent(codigo)}`);
        const data = await response.json();
        
        if (data.produtos && data.produtos.length > 0) {
            const produto = data.produtos[0]; // Pega o primeiro resultado
            const prod_balanca = produto.prod_balanca || 'N';
            
            // MUDANÇA 1: Validar quantidade baseado no tipo de produto
            if (prod_balanca === 'U') {
                // Produto Unitário: garantir que seja inteiro
                quantidade = Math.round(quantidade);
                if (quantidade < 1) {
                    quantidade = 1;
                }
            }
            
            if (!validarQuantidade(quantidade, prod_balanca)) {
                return; // A validação já mostra o erro
            }
            
            // CORREÇÃO: Validar preço unitário antes de adicionar
            const precoUnitario = parseFloat(produto.preco_unitario);
            if (isNaN(precoUnitario) || precoUnitario <= 0) {
                mostrarAlerta(`Erro: O produto "${produto.descricao}" possui preço inválido (R$ ${produto.preco_unitario || '0,00'}). Não é possível adicionar produtos sem preço definido.`, 'error');
                return;
            }
            
            // CORREÇÃO: Usar descrição curta (prod_descrpdvs) na tabela, descrição longa (descricao) apenas na busca
            const produtoComanda = {
                codigo_interno: produto.codigo_interno,
                codigo_barras: produto.codigo_barras,
                descricao: produto.descricao, // Descrição longa (para referência, não exibir)
                prod_descrpdvs: produto.prod_descrpdvs || produto.descricao, // Descrição curta (usar nas tabelas)
                produto_descrpdvs: produto.prod_descrpdvs || produto.descricao, // Alias para compatibilidade
                prod_balanca: prod_balanca,
                produto_balanca: prod_balanca, // Alias para compatibilidade
                quantidade: quantidade,
                preco_unitario: precoUnitario,
                total_item: quantidade * precoUnitario,
                unidade: produto.unidade || 'UN',
                tributacao_codigo: produto.tributacao_codigo || '123'
            };

            const indexExistente = produtosComanda.findIndex(p => p.codigo_interno === produto.codigo_interno);
            if (indexExistente >= 0) {
                produtosComanda[indexExistente].quantidade += quantidade;
                produtosComanda[indexExistente].total_item = produtosComanda[indexExistente].quantidade * precoUnitario;
            } else {
                produtosComanda.push(produtoComanda);
            }

            atualizarListaProdutos();
            atualizarTotalComanda();
            
            // MUDANÇA 2: Limpar campos e focar automaticamente no campo de busca
            document.getElementById('buscaProduto').value = '';
            document.getElementById('quantidadeProduto').value = prod_balanca === 'U' ? '1' : '1.000';
            document.getElementById('resultadosBusca').style.display = 'none';
            
            // MUDANÇA 2: Retornar foco automaticamente para o campo de busca
            setTimeout(() => {
                const campoBusca = document.getElementById('buscaProduto');
                campoBusca.focus();
                campoBusca.select(); // Selecionar texto anterior para digitação imediata
            }, 100);
            
            mostrarAlerta('Produto adicionado com sucesso!', 'success');
        } else {
            mostrarAlerta('Produto não encontrado', 'error');
        }
    } catch (error) {
        console.error('Erro ao buscar produto:', error);
        mostrarAlerta('Erro ao buscar produto', 'error');
    }
}

function removerProduto(index) {
    // CORREÇÃO: Marcar como removido mas NÃO remover do array (precisa do ID para enviar ao backend)
    if (produtosComanda[index].id) {
        produtosComanda[index].removido = true;
        console.log(`Item marcado como removido: ID ${produtosComanda[index].id}`);
    } else {
        // Se não tem ID (é novo), pode remover do array diretamente
        produtosComanda.splice(index, 1);
    }
    
    // Atualizar visualização (itens removidos serão filtrados)
    atualizarListaProdutos();
    atualizarTotalComanda();
}

function editarQuantidade(index, novaQuantidade) {
    // CORREÇÃO: Verificar se o item não foi removido antes de editar
    if (!produtosComanda[index] || produtosComanda[index].removido) {
        console.warn('Tentativa de editar item removido ou inexistente, ignorando...');
        atualizarListaProdutos();
        return;
    }
    
    const quantidade = parseFloat(novaQuantidade);
    if (quantidade > 0) {
        produtosComanda[index].quantidade = quantidade;
        produtosComanda[index].total_item = quantidade * parseFloat(produtosComanda[index].preco_unitario);
        
        // Se o produto tem ID (é existente), marcar como modificado
        if (produtosComanda[index].id) {
            produtosComanda[index].modificado = true;
        }
        
        atualizarListaProdutos();
        atualizarTotalComanda();
    } else {
        mostrarAlerta('Quantidade deve ser maior que zero', 'error');
        // Restaurar valor anterior
        atualizarListaProdutos();
    }
}

function atualizarListaProdutos() {
    const corpoTabela = document.getElementById('corpoTabelaProdutos');
    if (!corpoTabela) return;
    
    // CORREÇÃO: Filtrar itens removidos da visualização, mas mantê-los no array para envio ao backend
    const itensVisiveis = produtosComanda.filter((p) => !p.removido);
    
    if (itensVisiveis.length === 0) {
        corpoTabela.innerHTML = `
            <tr>
                <td colspan="4" style="text-align: center; padding: 40px; color: #7F8C8D;">
                    Nenhum produto adicionado ainda
                </td>
            </tr>
        `;
        return;
    }
    
    corpoTabela.innerHTML = itensVisiveis.map((produto, idx) => {
        // Encontrar o índice real no array original
        const indexReal = produtosComanda.indexOf(produto);
        return `
        <tr style="border-bottom: 1px solid #E8F8F5;">
            <td style="padding: 12px; border: 1px solid #D5DBDB;">
                <div style="font-weight: bold; margin-bottom: 4px;">${produto.produto_descrpdvs || produto.prod_descrpdvs || produto.descricao}</div>
                <div style="font-size: 12px; color: #7F8C8D; margin-bottom: 2px;">Código: ${produto.codigo_interno}</div>
                <div style="font-size: 12px; color: #2ECC71; font-weight: bold;">R$ ${formatarMoeda(produto.preco_unitario)}</div>
                ${produto.produto_balanca && produto.produto_balanca !== 'N' ? 
                    `<div style="font-size: 10px; color: #E67E22; font-weight: bold;">${produto.produto_balanca === 'P' ? 'PESÁVEL' : 'UNITÁRIO'}</div>` : ''}
            </td>
            <td style="padding: 12px; border: 1px solid #D5DBDB; text-align: center;">
                <input type="number" 
                       value="${produto.quantidade}" 
                       min="0.01" 
                       step="0.01" 
                       style="width: 80px; padding: 5px; border: 1px solid #D5DBDB; border-radius: 5px; text-align: center;"
                       onchange="editarQuantidade(${indexReal}, this.value)">
            </td>
            <td style="padding: 12px; border: 1px solid #D5DBDB; text-align: right;">
                <strong>${formatarMoeda(produto.total_item)}</strong>
            </td>
            <td style="padding: 12px; border: 1px solid #D5DBDB; text-align: center;">
                <button class="btn btn-danger btn-small" onclick="removerProduto(${indexReal})" style="padding: 5px 10px; font-size: 12px;">🗑️</button>
            </td>
        </tr>
    `;
    }).join('');
}

function atualizarTotalComanda() {
    // CORREÇÃO: Calcular totais apenas dos itens não removidos
    const itensAtivos = produtosComanda.filter(p => !p.removido);
    const totalValor = itensAtivos.reduce((total, produto) => total + parseFloat(produto.total_item), 0);
    const totalItens = itensAtivos.reduce((total, produto) => total + parseFloat(produto.quantidade), 0);
    const totalPeso = itensAtivos.reduce((total, produto) => {
        return total + (produto.unidade === 'KG' ? parseFloat(produto.quantidade) : 0);
    }, 0);
    
    if (document.getElementById('totalValor')) {
        document.getElementById('totalValor').textContent = formatarMoeda(totalValor);
    }
    
    if (document.getElementById('totalItens')) {
        document.getElementById('totalItens').textContent = totalItens.toFixed(3).replace('.', ',');
    }
    
    if (document.getElementById('totalPeso')) {
        document.getElementById('totalPeso').textContent = `${totalPeso.toFixed(3).replace('.', ',')} Kg`;
    }
}

// Detectar se está no modo edição ou criação
function detectarModoEdicao() {
    const urlParams = new URLSearchParams(window.location.search);
    const numeroComanda = urlParams.get('numero');
    
    if (numeroComanda) {
        return { modo: 'edicao', numero: numeroComanda };
    }
    return { modo: 'criacao' };
}

// Carregar produtos da comanda para edição
async function carregarProdutosDaComanda(numero) {
    try {
        console.log(`Carregando produtos da comanda ${numero}...`);
        const response = await fazerRequisicao(`/comandas/${numero}`);
        const data = await response.json();
        
        if (!response.ok) {
            mostrarAlerta(`Erro ao carregar comanda: ${data.error}`, 'error');
            return false;
        }
        
        const itens = data.itens || [];
        
        // CORREÇÃO: Ao carregar produtos para edição, usar descrição curta (produto_descrpdvs)
        produtosComanda = itens.map(item => ({
            id: item.id,
            codigo_interno: item.produto_codigo,
            codigo_barras: item.produto_barras,
            descricao: item.produto_descricao, // Descrição longa (mantida para referência, não exibir)
            prod_descrpdvs: item.produto_descrpdvs || item.produto_descricao, // Descrição curta (usar nas tabelas)
            produto_descrpdvs: item.produto_descrpdvs || item.produto_descricao, // Alias para compatibilidade
            produto_balanca: item.produto_balanca || 'N',
            prod_balanca: item.produto_balanca || 'N', // Alias para compatibilidade
            quantidade: parseFloat(item.quantidade),
            preco_unitario: parseFloat(item.preco_unitario),
            total_item: parseFloat(item.total_item),
            unidade: item.unidade || 'UN',
            tributacao_codigo: item.tributacao_codigo || '123',
            modificado: false
        }));
        
        atualizarListaProdutos();
        atualizarTotalComanda();
        
        return true;
    } catch (error) {
        console.error('Erro ao carregar produtos da comanda:', error);
        mostrarAlerta('Erro ao carregar produtos da comanda', 'error');
        return false;
    }
}

async function salvarComanda() {
    const modo = detectarModoEdicao();
    const numeroComandaInput = document.getElementById('numeroComanda');
    
    // Garantir que o número está formatado com 6 dígitos
    if (numeroComandaInput) {
        let valor = numeroComandaInput.value.replace(/\D/g, '');
        if (valor) {
            valor = valor.padStart(6, '0');
            if (valor.length > 6) {
                valor = valor.substring(0, 6);
            }
            numeroComandaInput.value = valor;
        }
    }
    
    const numeroComanda = numeroComandaInput.value.trim();
    
    if (!numeroComanda) {
        mostrarAlerta('Digite o número da comanda', 'error');
        return;
    }

    if (produtosComanda.length === 0) {
        mostrarAlerta('Adicione pelo menos um produto', 'error');
        return;
    }

    // Se estiver editando, chamar função de edição
    if (modo.modo === 'edicao') {
        await salvarEdicaoComanda(numeroComanda);
        return;
    }

    try {
        console.log('Iniciando salvamento da comanda:', numeroComanda);
        
        // Buscar dados do atendente do sessionStorage
        let dadosAtendente = null;
        try {
            const atendenteStr = sessionStorage.getItem('atendenteSelecionado');
            if (atendenteStr) {
                dadosAtendente = JSON.parse(atendenteStr);
            }
        } catch (e) {
            console.error('Erro ao ler atendente do sessionStorage:', e);
        }
        
        // Criar comanda
        const comandaData = {
            numero_comanda: numeroComanda,
            cliente_codigo: '113727'
        };
        
        // Adicionar dados do atendente se disponíveis
        if (dadosAtendente) {
            comandaData.usuario_id = dadosAtendente.id;
            comandaData.usuario_nome = dadosAtendente.nome;
            comandaData.usuario_codigo = dadosAtendente.codigo;
        }
        
        const comandaResponse = await fazerRequisicao('/comandas', {
            method: 'POST',
            body: JSON.stringify(comandaData)
        });
        
        if (!comandaResponse.ok) {
            const errorData = await comandaResponse.json();
            console.error('Erro ao criar comanda:', errorData);
            mostrarAlerta(`Erro ao criar comanda: ${errorData.error}`, 'error');
            return;
        }
        
        console.log('Comanda criada com sucesso');

        // Adicionar produtos
        for (const produto of produtosComanda) {
            console.log('Adicionando produto:', produto);
            console.log('Código interno:', produto.codigo_interno);
            console.log('Código de barras:', produto.codigo_barras);
            console.log('Quantidade:', produto.quantidade);
            console.log('Preço unitário:', produto.preco_unitario);
            
            // CORREÇÃO: Validar preço antes de enviar
            const precoUnitario = parseFloat(produto.preco_unitario);
            if (isNaN(precoUnitario) || precoUnitario <= 0) {
                mostrarAlerta(`Erro: O produto "${produto.descricao}" possui preço inválido (R$ ${produto.preco_unitario || '0,00'}). Remova este item da lista antes de salvar.`, 'error');
                return;
            }
            
            const itemResponse = await fazerRequisicao(`/comandas/${numeroComanda}/itens`, {
                method: 'POST',
                body: JSON.stringify({
                    produto_codigo: produto.codigo_interno,
                    quantidade: produto.quantidade,
                    preco_unitario: precoUnitario
                })
            });
            
            if (!itemResponse.ok) {
                const errorData = await itemResponse.json();
                console.error('Erro ao adicionar item:', errorData);
                mostrarAlerta(`Erro ao adicionar item ${produto.descricao}: ${errorData.error}`, 'error');
                return;
            }
            
            console.log('Produto adicionado com sucesso');
        }

        // Salvar arquivo TXT
        console.log('Gerando arquivo TXT...');
        const txtResponse = await fazerRequisicao(`/comandas/${numeroComanda}/salvar-txt`, {
            method: 'POST'
        });
        
        if (!txtResponse.ok) {
            const errorData = await txtResponse.json();
            console.error('Erro ao gerar TXT:', errorData);
            mostrarAlerta(`Erro ao gerar arquivo TXT: ${errorData.error}`, 'error');
            return;
        }
        
        console.log('Arquivo TXT gerado com sucesso');

        mostrarAlerta('Comanda salva e arquivo TXT gerado com sucesso!', 'success');
        
        // Limpar formulário
        produtosComanda = [];
        atualizarListaProdutos();
        atualizarTotalComanda();
        document.getElementById('numeroComanda').value = '';
        
        // Voltar ao início após 2 segundos
        setTimeout(() => {
            window.location.href = '/';
        }, 2000);

    } catch (error) {
        console.error('Erro ao salvar comanda:', error);
        mostrarAlerta('Erro ao salvar comanda', 'error');
    }
}

// Função para salvar edição de comanda
async function salvarEdicaoComanda(numeroComanda) {
    console.log(`Salvando edição da comanda ${numeroComanda}...`);
    
    try {
        // Identificar itens novos, modificados e removidos
        const itensModificados = produtosComanda.filter(p => p.id && p.modificado).map(p => ({
            id: p.id,
            quantidade: parseFloat(p.quantidade),
            preco_unitario: parseFloat(p.preco_unitario),
            total_item: parseFloat(p.total_item)
        }));
        
        const itensNovos = produtosComanda.filter(p => !p.id).map(p => {
            // CORREÇÃO: Validar preço antes de adicionar à lista de novos itens
            const precoUnitario = parseFloat(p.preco_unitario);
            if (isNaN(precoUnitario) || precoUnitario <= 0) {
                throw new Error(`O produto "${p.descricao}" possui preço inválido. Remova este item antes de salvar.`);
            }
            return {
                codigo_interno: p.codigo_interno,
                codigo_barras: p.codigo_barras,
                descricao: p.descricao, // Descrição longa (mantida para referência)
                prod_descrpdvs: p.prod_descrpdvs || p.produto_descrpdvs || p.descricao, // Descrição curta
                prod_balanca: p.prod_balanca || p.produto_balanca || 'N',
                quantidade: parseFloat(p.quantidade),
                preco_unitario: precoUnitario,
                total_item: parseFloat(p.total_item),
                tributacao_codigo: p.tributacao_codigo
            };
        });
        
        // CORREÇÃO: Buscar IDs dos itens removidos (que foram marcados mas não removidos do array)
        const idsRemovidos = produtosComanda
            .filter(p => p.removido && p.id)
            .map(p => p.id)
            .filter(id => id !== undefined && id !== null);
        
        console.log(`Itens modificados: ${itensModificados.length}`);
        console.log(`Itens novos: ${itensNovos.length}`);
        console.log(`Itens removidos: ${idsRemovidos.length}`, idsRemovidos);
        
        // Enviar para o backend
        const response = await fazerRequisicao(`/comandas/${numeroComanda}`, {
            method: 'PUT',
            body: JSON.stringify({
                itens: itensModificados,
                itensNovos: itensNovos,
                itensRemovidos: idsRemovidos
            }),
            headers: { 'Content-Type': 'application/json' }
        });
        
        if (!response.ok) {
            const errorData = await response.json();
            console.error('Erro ao salvar edição:', errorData);
            mostrarAlerta(`Erro ao salvar edição: ${errorData.error}`, 'error');
            return;
        }
        
        mostrarAlerta('Comanda atualizada com sucesso!', 'success');
        
        // Redirecionar para lista de comandas ativas após 2 segundos
        setTimeout(() => {
            window.location.href = '/pages/comandas-ativas.html';
        }, 2000);
        
    } catch (error) {
        console.error('Erro ao salvar edição:', error);
        // CORREÇÃO: Mostrar mensagem específica para erro de preço inválido
        if (error.message && error.message.includes('preço inválido')) {
            mostrarAlerta(error.message, 'error');
        } else {
            mostrarAlerta('Erro ao salvar edição da comanda', 'error');
        }
    }
}

// Inicialização
document.addEventListener('DOMContentLoaded', function() {
    // Carregar estatísticas na página principal
    if (document.getElementById('totalComandas')) {
        carregarEstatisticas();
    }
    
    // Carregar comandas ativas se estivermos na página correta
    if (document.getElementById('listaComandas')) {
        carregarComandasAtivas();
    }
    
    // Configurar busca de produtos
    const buscaProduto = document.getElementById('buscaProduto');
    if (buscaProduto) {
        buscaProduto.addEventListener('input', function() {
            buscarProdutos(this.value);
        });
        
        // Configurar detector de scanner
        buscaProduto.addEventListener('input', function() {
            detectarScanner(this, document.getElementById('quantidadeProduto'));
        });
        
        // Validar se o número da comanda foi preenchido antes de permitir foco
        buscaProduto.addEventListener('mousedown', function(e) {
            const numeroComanda = document.getElementById('numeroComanda');
            if (numeroComanda) {
                const valor = numeroComanda.value.replace(/\D/g, '');
                if (!valor || valor.length === 0) {
                    e.preventDefault();
                    alert('Por favor, digite ou escaneie o número da comanda antes de buscar produtos.');
                    numeroComanda.focus();
                    return false;
                }
            }
        });
        
        // Validar ao receber foco (Tab, etc)
        buscaProduto.addEventListener('focus', function(e) {
            const numeroComanda = document.getElementById('numeroComanda');
            if (numeroComanda) {
                const valor = numeroComanda.value.replace(/\D/g, '');
                if (!valor || valor.length === 0) {
                    buscaProduto.blur();
                    alert('Por favor, digite ou escaneie o número da comanda antes de buscar produtos.');
                    numeroComanda.focus();
                }
            }
        });
    }
    
    // Configurar número da comanda para scanner
    const numeroComanda = document.getElementById('numeroComanda');
    if (numeroComanda) {
        // Garantir foco automático no campo ao carregar a página
        setTimeout(function() {
            numeroComanda.focus();
        }, 100);
        // Função para formatar número com zeros à esquerda (6 dígitos)
        function formatarNumeroComanda(input) {
            // Remove caracteres não numéricos
            let valor = input.value.replace(/\D/g, '');
            
            // Limita a 6 dígitos
            if (valor.length > 6) {
                valor = valor.substring(0, 6);
            }
            
            // Se houver valor, preenche com zeros à esquerda até 6 dígitos
            if (valor) {
                valor = valor.padStart(6, '0');
                input.value = valor;
            } else {
                input.value = '';
            }
        }
        
        // Permitir apenas números ao digitar (sem formatar ainda)
        numeroComanda.addEventListener('input', function(e) {
            // Remove caracteres não numéricos
            let valor = this.value.replace(/\D/g, '');
            
            // Limita a 6 dígitos
            if (valor.length > 6) {
                valor = valor.substring(0, 6);
            }
            
            // Atualiza o valor sem formatação de zeros ainda
            this.value = valor;
            
            // Detectar scanner
            detectarScanner(this, document.getElementById('buscaProduto'));
        });
        
        // Bloquear teclas não numéricas
        numeroComanda.addEventListener('keypress', function(e) {
            // Permitir apenas números (0-9) e algumas teclas especiais (Backspace, Delete, Tab, Enter, etc)
            const charCode = e.which ? e.which : e.keyCode;
            if (charCode > 31 && (charCode < 48 || charCode > 57)) {
                e.preventDefault();
            }
        });
        
        // Tratar colar (paste) - garantir que apenas números sejam aceitos
        numeroComanda.addEventListener('paste', function(e) {
            e.preventDefault();
            // Obter texto colado
            const textoColado = (e.clipboardData || window.clipboardData).getData('text');
            // Remover caracteres não numéricos
            let valor = textoColado.replace(/\D/g, '');
            // Limitar a 6 dígitos
            if (valor.length > 6) {
                valor = valor.substring(0, 6);
            }
            // Atualizar valor
            this.value = valor;
        });
        
        // Formatar ao sair do campo (blur) - adiciona zeros à esquerda
        numeroComanda.addEventListener('blur', function() {
            formatarNumeroComanda(this);
        });
        
        // Navegar para campo de código de barras ao pressionar Enter e formatar
        numeroComanda.addEventListener('keyup', function(e) {
            if (e.key === 'Enter') {
                e.preventDefault();
                // Formatar antes de sair
                formatarNumeroComanda(this);
                
                // Validar se o número da comanda foi preenchido
                const valor = this.value.replace(/\D/g, '');
                if (!valor || valor.length === 0) {
                    alert('Por favor, digite ou escaneie o número da comanda antes de continuar.');
                    this.focus();
                    return;
                }
                
                // Focar no próximo campo apenas se o número foi preenchido
                const buscaProduto = document.getElementById('buscaProduto');
                if (buscaProduto) {
                    buscaProduto.focus();
                }
            }
        });
    }
    
    // Configurar tecla Enter para adicionar produto
    const quantidadeProduto = document.getElementById('quantidadeProduto');
    if (quantidadeProduto) {
        quantidadeProduto.addEventListener('keyup', function(e) {
            if (e.key === 'Enter') {
                adicionarProduto();
            }
        });
    }
    
    // Detectar modo edição e carregar produtos
    if (numeroComanda) {
        const modo = detectarModoEdicao();
        
        if (modo.modo === 'edicao') {
            console.log(`Modo edição detectado para comanda ${modo.numero}`);
            
            // Preencher número da comanda (readonly)
            numeroComanda.value = modo.numero;
            numeroComanda.readOnly = true;
            numeroComanda.style.background = '#f0f0f0';
            
            // Carregar produtos da comanda
            carregarProdutosDaComanda(modo.numero);
            
            // Adicionar botão "Cancelar" ao lado de "Salvar"
            const botaoSalvar = document.querySelector('button[onclick="salvarComanda()"]');
            if (botaoSalvar) {
                const botaoCancelar = document.createElement('button');
                botaoCancelar.className = 'btn btn-danger';
                botaoCancelar.textContent = '❌ Cancelar Edição';
                botaoCancelar.onclick = function() {
                    if (confirm('Tem certeza que deseja cancelar a edição?')) {
                        window.location.href = '/pages/comandas-ativas.html';
                    }
                };
                botaoSalvar.parentElement.appendChild(botaoCancelar);
            }
        }
    }
});
