# Comandas Mobile — PHP (Laravel)

Port PHP do sistema **ComandasErpInfo** (Node/TypeScript) para uso escolar, conectado ao mesmo PostgreSQL e pasta de rede Conexo.

## Requisitos

- PHP 8.0+ com extensões `pdo_pgsql`, `pgsql`, `mbstring`, `openssl`
- Composer
- PostgreSQL (`metabase_prod`)
- Acesso à pasta `PASTA_REDE` (arquivos `VD*.TXT`)

## API

- `POST/GET/PUT/DELETE /api/comandas/*`
- `GET/PUT /api/produtos/*`
- `GET /api/usuarios/*`
- `GET/POST/PUT /api/admin/*`
- `GET /health`, `GET /info`

