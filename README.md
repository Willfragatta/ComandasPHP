# Comandas Mobile — PHP (Laravel)

Port PHP do sistema **ComandasErpInfo** (Node/TypeScript) para uso escolar, conectado ao mesmo PostgreSQL e pasta de rede Conexo.

## Requisitos

- PHP 8.0+ com extensões `pdo_pgsql`, `pgsql`, `mbstring`, `openssl`
- Composer
- PostgreSQL (`metabase_prod`)
- Acesso à pasta `PASTA_REDE` (arquivos `VD*.TXT`)

## Instalação

```powershell
cd C:\Users\William Fragatta\Documents\CamandasPHP
C:\xampp\php\php.exe composer.phar install
copy .env.example .env
C:\xampp\php\php.exe artisan key:generate
```

Configure `.env` com credenciais do banco e `PASTA_REDE`.

## Executar

```powershell
# API + frontend
C:\xampp\php\php.exe artisan serve --host=0.0.0.0 --port=8000

# Monitor pasta rede (terminal separado)
C:\xampp\php\php.exe artisan comandas:monitor-pasta-rede

# Scheduler (Task Scheduler Windows — a cada minuto)
C:\xampp\php\php.exe artisan schedule:run
```

Acesse: http://localhost:8000

## API

Mesmas rotas do Node, prefixo `/api`:

- `POST/GET/PUT/DELETE /api/comandas/*`
- `GET/PUT /api/produtos/*`
- `GET /api/usuarios/*`
- `GET/POST/PUT /api/admin/*`
- `GET /health`, `GET /info`

## Importante

- **Não rode Node e PHP ao mesmo tempo** na mesma `PASTA_REDE`.
- O projeto original em `ComandasErpInfo` não é alterado — apenas lido como referência.
- Laravel 8 foi usado por compatibilidade com PHP 8.0 (XAMPP).
# ComandasPHP
