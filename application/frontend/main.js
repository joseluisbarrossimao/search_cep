const API_URL = 'http://localhost:8000/api';
const cepInput = document.getElementById('cep');
const btn = document.getElementById('buscarBtn');
const msg = document.getElementById('mensagem');
const logEl = document.getElementById('log');
const enderecoInput = document.getElementById('endereco');
const bairroInput   = document.getElementById('bairro');
const cidadeInput   = document.getElementById('cidade');
const estadoInput   = document.getElementById('estado');
let logs = [];
function carregarLogs() {
  try {
    const salvo = localStorage.getItem('log_cep');
    if (salvo) {
      logs = JSON.parse(salvo);
    }
  } catch (e) {
    logs = [];
  }
  renderizarLog();
}

function salvarLogs() {
  try {
    localStorage.setItem('log_cep', JSON.stringify(logs));
  } catch (e) {
    // se der erro para salvar, ignoramos
  }
}

function adicionarLog(tipo, mensagem) {
  const item = {
    tipo,
    mensagem,
    data: new Date().toLocaleString()
  };
  logs.unshift(item); // último erro em cima
  if (logs.length > 100) logs.pop(); // limita tamanho
  salvarLogs();
  renderizarLog();
}

function renderizarLog() {
  if (!logs.length) {
    logEl.textContent = 'Nenhum log ainda.';
    return;
  }
  logEl.textContent = logs
    .map(l => `[${l.data}] [${l.tipo.toUpperCase()}] ${l.mensagem}`)
    .join('\n');
}

function limparCamposEndereco() {
  enderecoInput.value = '';
  bairroInput.value = '';
  cidadeInput.value = '';
  estadoInput.value = '';
}

function preencherCamposEndereco(dados) {
  // Ajuste as chaves de acordo com o retorno do seu backend.
  enderecoInput.value = dados.endereco || dados.logradouro || '';
  bairroInput.value   = dados.bairro   || '';
  cidadeInput.value   = dados.cidade   || dados.localidade || '';
  estadoInput.value   = dados.estado   || dados.uf || '';
}

async function buscarCep() {
  const cep = (cepInput.value || '').replace(/\D/g, '');

  msg.textContent = '';
  msg.className = 'mensagem';
  if (!cep || cep.length !== 8) {
    msg.textContent = 'erro de cep';
    msg.classList.add('erro');
    adicionarLog('erro', 'CEP inválido informado: ' + cepInput.value);
    limparCamposEndereco();
    return;
  }
  btn.disabled = true;
  btn.textContent = 'Buscando...';
  try {
    const resp = await fetch(API_URL, {
      method: 'POST',
      headers: {
        'Content-Type': 'application/json'
      },
      body: JSON.stringify({cep: cep})
    });
    if (!resp.ok) {
      msg.textContent = 'erro de cep';
      msg.classList.add('erro');
      adicionarLog('erro', 'HTTP ' + resp.status + ' ao consultar CEP ' + cep);
      limparCamposEndereco();
      return;
    }
    const data = await resp.json();
    if (data.erro || data.sucesso === false) {
      msg.textContent = 'erro de cep';
      msg.classList.add('erro');
      const detalhe = data.mensagem || JSON.stringify(data);
      adicionarLog('erro', 'Backend retornou erro para CEP ' + cep + ': ' + detalhe);
      limparCamposEndereco();
      return;
    }
    preencherCamposEndereco(data);
    msg.textContent = 'Endereço encontrado com sucesso.';
    msg.classList.add('sucesso');
    adicionarLog('sucesso', 'CEP ' + cep + ' buscado com sucesso.');
  } catch (e) {
    msg.textContent = 'erro de cep';
    msg.classList.add('erro');
    adicionarLog('erro', 'Exceção ao consultar CEP ' + cep + ': ' + (e.message || e));
    limparCamposEndereco();
  } finally {
    btn.disabled = false;
    btn.textContent = 'Buscar';
  }
}
btn.addEventListener('click', buscarCep);
cepInput.addEventListener('keyup', function (ev) {
  if (ev.key === 'Enter') {
    buscarCep();
  }
});
carregarLogs();
