# 🎯 JMbenga Portfolio

> 🌐 **Acede ao projeto online:** [jmbenga.freedev.app]((https://jmbenga.freedev.app/))

Portfólio profissional Full Stack desenvolvido para apresentar os meus projetos, competências e gerir conteúdos em tempo real através de um painel de administração integrado.

---

## ✨ Funcionalidades Principais (O que avaliar)

- **Acesso ao Painel Admin:** Disponível em `https://dev.app/jm-panel/` (Controlo total de projetos e mensagens).
- **Suporte PWA:** O site é instalável no mobile e desktop, funcionando em modo offline.
- **URLs Amigáveis:** Navegação limpa gerida pelo Apache `mod_rewrite`.
- **Segurança Nativa:** Proteção integrada contra SQL Injection, CSRF e XSS.

---

## 🛠️ Tecnologias & Stack

- **Backend:** PHP 8.2 (Arquitetura modular)
- **Base de Dados:** MySQL / MariaDB
- **Frontend:** HTML5, CSS3, JavaScript (Vanilla)
- **Servidor:** Apache

---

## 📁 Estrutura do Projeto

```text
portfolio/
├── includes/       ← Núcleo PHP (config, db, helpers) [config.php protegido]
├── api/            ← Endpoints JSON para consumo dinâmico
├── assets/         ← Recursos estáticos (CSS, JS, Imagens)
├── database/       ← Scripts de criação e população do banco de dados
└── jm-panel/       ← Painel de Administração (Auth e Gestão de Conteúdo)
```

---

## 🔐 Segurança do Repositório (GitHub Seguro)

O ficheiro `.gitignore` está configurado para garantir boas práticas de segurança em ambiente de equipa:
- O ficheiro real `includes/config.php` (com as senhas do banco de dados) está estritamente bloqueado e não é enviado para o GitHub.
- Para consulta de estrutura, existe o modelo `includes/config.example.php`.

---

## 💻 Instalação Local (Apenas para Avaliação Técnica)

Se fores um Tech Lead ou Recrutador e desejares testar o projeto no teu ambiente local (XAMPP/Laragon):

1. **Clona o projeto:** `git clone https://github.com`
2. **Configura o Ambiente:** Na pasta `includes/`, duplica o `config.example.php` para `config.php` e insere as tuas credenciais locais.
3. **Base de Dados:** Cria a base de dados e importa o ficheiro `database/schema.sql`.
4. **Cria o Admin:** Executa o script `php database/seed_admin.php` no terminal para gerar o acesso inicial ao painel.

---

## 📄 Licença

Este projeto está sob a licença MIT. Veja o ficheiro [LICENSE](LICENSE) para mais detalhes.

Desenvolvido por **José Mbenga** © 2026
