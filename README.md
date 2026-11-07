# 🎯 JMbenga Portfolio

> 🌐 **Acede ao projecto online:** [jmbenga.freedev.app](https://jmbenga.freedev.app/)

Portefólio profissional Full Stack desenvolvido para apresentar os meus projectos, competências e gerir conteúdos em tempo real através de um painel de administração integrado.

---

## ✨ Funcionalidades Principais

- **Painel Administrativo:** gestão de projectos, mensagens, testemunhos, visitantes e configurações.
- **Suporte PWA:** aplicação instalável em dispositivos móveis e computadores, com funcionalidades de utilização offline.
- **URLs Amigáveis:** navegação limpa gerida pelo Apache `mod_rewrite`.
- **Segurança Aplicacional:** protecções contra SQL Injection, CSRF e XSS, controlo de sessões e autenticação administrativa.
- **Gestão Dinâmica:** conteúdos e configurações administráveis através do painel.
- **Interface Responsiva:** experiência adaptada a diferentes dimensões de ecrã.

---

## 🛠️ Tecnologias e Stack

- **Backend:** PHP 8.2+
- **Base de Dados:** MySQL / MariaDB
- **Frontend:** HTML5, CSS3 e JavaScript (Vanilla)
- **Framework CSS:** Bootstrap
- **Servidor Web:** Apache
- **Arquitectura:** PHP modular, PDO e Progressive Web App (PWA).

---

## 📁 Estrutura do Projecto

```text
portfolio/
├── api/                ← Endpoints e serviços JSON
├── assets/              ← CSS, JavaScript, imagens e ícones
├── database/            ← Scripts e recursos relacionados com a BD
├── includes/            ← Configuração, ligação à BD e funções centrais
├── jm-panel/            ← Painel administrativo
├── services/            ← Páginas de serviços
├── status/              ← Páginas de estado e indisponibilidade
├── manifest.json        ← Manifesto PWA
├── manifest.php         ← Manifesto dinâmico
└── sw.js                ← Service Worker
```

---

## 🔐 Segurança do Repositório

O repositório segue boas práticas de protecção de informação e configuração por ambiente.

- O ficheiro `includes/config.php` gere a configuração global e carrega as variáveis de ambiente.
- As credenciais e chaves privadas devem ser mantidas em `.env` ou `.env.production`, nunca directamente no código.
- O ficheiro `env.example` disponibiliza um modelo de configuração sem credenciais reais.
- Os ficheiros `.env`, `.env.production`, dependências, caches e backups locais são excluídos através do `.gitignore`.
- As sessões públicas e administrativas utilizam contextos separados.
- As operações administrativas utilizam autenticação, autorização e protecção CSRF.

---

## 💻 Instalação Local

Para testar o projecto num ambiente local com XAMPP ou Laragon:

1. **Clonar o repositório:**

   ```bash
   git clone https://github.com/josembengacosta/jmbenga-my-portfolio.git
   ```

2. **Configurar o ambiente:**

   Copia `env.example` para `.env` e preenche as variáveis com as configurações do teu ambiente local.

3. **Configurar a base de dados:**

   Cria uma base de dados MySQL/MariaDB e importa o esquema SQL actualizado correspondente à versão do projecto. Confirma a versão do script antes de executar a importação.

4. **Configurar o acesso administrativo:**

   O script `database/seed_admin.php` encontra-se desactivado. O provisionamento do primeiro administrador deve seguir o procedimento seguro de criação de contas do projecto.

5. **Executar o projecto:**

   Inicia Apache e MySQL no XAMPP ou Laragon e acede ao endereço local configurado em `BASE_URL`.

---

## 📄 Licença

Este projecto está sob a licença MIT. Consulta o ficheiro [LICENSE](LICENSE) para mais detalhes.

---

Desenvolvido por **José Mbenga** © 2026.