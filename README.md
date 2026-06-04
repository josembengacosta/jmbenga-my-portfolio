# 🎯 JMbenga Portfolio

Portfólio profissional (Full Stack) do **José Mbenga** com **PHP + MySQL**, **PWA offline** e **Painel Admin**.

## ✅ Domínio / Publicação
Este projeto está configurado para ser publicado no teu ambiente: **`jmbenga.free.dev.app`** (via DNS/SSL do teu provider).

> ⚠️ O ficheiro `includes/config.php` **NÃO deve** ir para o GitHub.

## 🚀 Stack
- **Frontend:** HTML5, CSS3, JavaScript
- **Backend:** PHP 8.2
- **Base de dados:** MySQL / MariaDB
- **Servidor:** Apache (com mod_rewrite)

## 📁 Estrutura do Projeto
```
portfolio/
├── includes/       ← núcleo PHP (config, db, helpers) [NÃO versionar config.php]
├── api/            ← endpoints JSON
├── assets/         ← css, js, imagens
├── status/         ← páginas de erro
├── database/       ← schema SQL
└── jm-panel/       ← painel admin
    ├── auth/
    ├── include/
    └── pages/
```

## 🔐 Segredos (GitHub seguro)
O `.gitignore` foi reforçado para impedir commitar:
- `includes/config.php` e `includes/config.*.php`
- ficheiros de config/segredos em `includes/` e `api/`

> **Importante:** se já enviaste `includes/config.php` para o GitHub antes, o `.gitignore` sozinho não remove os segredos do histórico. Nesse caso é necessário **rotacionar/revogar** as chaves e remover do histórico.

## 🛠️ Instalação (servidor)
1. Clonar o repositório
2. Copiar `includes/config.example.php` → `includes/config.php` e preencher credenciais
3. Importar o esquema: `database/schema.sql`
4. Configurar o `DocumentRoot`/VirtualHost e garantir que o `BASE_URL` está correto
5. Garantir `mod_rewrite` activo no Apache
6. (Opcional) Executar o seed do admin uma única vez: `database/seed_admin.php`

## 👨‍💻 Painel Admin
Aceder em:
- `https://jmbenga.free.dev.app/jm-panel/` (ajustar conforme o teu setup)

## 📄 Licença
MIT — José Mbenga © 2026

