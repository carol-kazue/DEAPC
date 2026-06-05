// Utilitários de validação partilhados entre páginas

function marcaErro(campo, mensagem) {
    campo.style.borderColor = '#e53e3e';
    campo.style.backgroundColor = '#fff5f5';
    let aviso = campo.parentElement.querySelector('.aviso-val');
    if (!aviso) {
        aviso = document.createElement('span');
        aviso.className = 'aviso-val';
        aviso.style.cssText = 'display:block;color:#c53030;font-size:0.78rem;margin-top:3px;';
        campo.parentElement.appendChild(aviso);
    }
    aviso.textContent = mensagem;
}

function limpaErro(campo) {
    campo.style.borderColor = '';
    campo.style.backgroundColor = '';
    const aviso = campo.parentElement.querySelector('.aviso-val');
    if (aviso) aviso.textContent = '';
}

function emailValido(valor) {
    return /^[^\s@]+@[^\s@]+\.[^\s@]+$/.test(valor.trim());
}

function telefoneValido(valor) {
    return valor.trim() === '' || /^[0-9+\s\-]{9,15}$/.test(valor.trim());
}

function nifValido(valor) {
    return valor.trim() === '' || /^[0-9]{9}$/.test(valor.trim());
}
