# SIGA-CLOUD — Fase 1

Fundação white-label, multi-tenancy e autenticação. Criada de raiz porque a pasta disponibilizada estava vazia. Não foram alterados projectos existentes ou servidores de produção.

## Ambiente e dependências

- Laravel 12.73.0, PHP ^8.2 (validado com PHP 8.3.30).
- Blade, Tailwind CSS 4, Alpine.js 3, Vite 7.
- Autenticação de sessão nativa do Laravel; Breeze não foi necessário.
- Composer e npm com versões exactas das dependências nos respectivos lockfiles.
- SQLite usado apenas na execução local e nos testes. As migrations usam Schema Builder e índices compatíveis com MySQL 8/PostgreSQL; a execução nesses motores ainda precisa de validação.

## Instalação de desenvolvimento

Na pasta extraída `siga-cloud`, com PHP 8.2+, Composer e Node.js 20.19+ ou 22.12+:

```sh
composer install
```

Copiar `.env.example` para `.env` (no Windows: `copy .env.example .env`; em Linux: `cp .env.example .env`).

Para MySQL 8, criar primeiro uma base de dados vazia chamada `sigacloud` e configurar no `.env`:

```dotenv
DB_CONNECTION=mysql
DB_HOST=127.0.0.1
DB_PORT=3306
DB_DATABASE=sigacloud
DB_USERNAME=root
DB_PASSWORD=
APP_URL=http://sigacloud.test:8000
APP_CENTRAL_DOMAIN=sigacloud.test
SESSION_DOMAIN=null
```

Ajustar o utilizador e a palavra-passe à instalação local. Para PostgreSQL, usar `DB_CONNECTION=pgsql`, porta 5432 e as credenciais dessa base de dados.

Para reproduzir a validação com SQLite, manter `DB_CONNECTION=sqlite`, criar o ficheiro vazio `database/database.sqlite` e não definir `DB_DATABASE` (ou indicar o caminho absoluto desse ficheiro).

```sh
php artisan key:generate
php artisan migrate:fresh --seed
npm ci
npm run build
php artisan serve --host=127.0.0.1 --port=8000
```

`migrate:fresh` apaga as tabelas da base de dados configurada: usar apenas numa base de desenvolvimento descartável.

Adicionar ao ficheiro hosts do computador:

```text
127.0.0.1 sigacloud.test demo.sigacloud.test
```

Windows: `C:\Windows\System32\drivers\etc\hosts`. Linux: `/etc/hosts`.

Acessos locais:

- Central: http://sigacloud.test:8000/login
- Instituição demo: http://demo.sigacloud.test:8000/login

O endereço localhost sem o domínio configurado devolve 404 intencionalmente. Estes nomes são locais, não representam uma publicação na Internet.

## Credenciais de demonstração

Palavra-passe de todas as contas: `password`.

| Perfil | E-mail | Código | Telemóvel |
| --- | --- | --- | --- |
| super_admin | admin@sigacloud.co.mz | — | — |
| direcao | direcao@demo.sigacloud.co.mz | DIRECAO001 | +258840000001 |
| registo_academico | registo_academico@demo.sigacloud.co.mz | REGISTO_ACADEMICO001 | +258840000002 |
| tesouraria | tesouraria@demo.sigacloud.co.mz | TESOURARIA001 | +258840000003 |
| docente | docente@demo.sigacloud.co.mz | DOC001 | +258840000004 |
| estudante | estudante@demo.sigacloud.co.mz | 20260001 | +258840000005 |
| encarregado | encarregado@demo.sigacloud.co.mz | ENCARREGADO001 | +258840000006 |

O administrador entra exclusivamente no domínio central. Os restantes entram no domínio da demo. E-mail, código e telemóvel funcionam no mesmo campo; o telemóvel é comparado no formato guardado, incluindo `+258`.

A demo chama-se Instituto Superior Politécnico Exemplo, tem estado `trial`, regime semestral, escala 20, dispensa 14 e aprovação 10. Cores: azul `#122D50` e dourado `#D4AF37`. Sem logótipo carregado, mostra a inicial da instituição; não foi inventado um logótipo oficial.

## Arquitectura implementada

- `IdentifyTenant` corre globalmente antes da sessão/autenticação. Resolve domínio exacto ou um único nível de subdomínio do domínio central. Hosts desconhecidos recebem 404; instituições suspensas recebem 403 com página própria. Contexto e dados partilhados com as views são limpos em `finally`.
- `TenantManager` é singleton; `run()` permite contexto explícito e restabelece o contexto anterior mesmo em caso de erro. Jobs futuros devem usá-lo explicitamente e transportar o ID da instituição.
- `TenantScope` limita leituras ao tenant activo e falha sem contexto. O super administrador activo pode consultar globalmente apenas no contexto central.
- `TenantUserProvider` aplica contexto também ao recuperar utilizadores da sessão. Evita recursão entre o scope Eloquent e o provider de autenticação.
- `BelongsToTenant` preenche `tenant_id`, rejeita criação com tenant alheio e impede transferências de registos por actualização do Model. `tenant_id`, `role` e `is_active` não são mass assignable pelo Model User.
- Índices únicos por tenant para e-mail, código e telefone. A coluna gerada `identity_scope` protege também a unicidade das contas centrais, pois índices SQL com NULL, sozinhos, não o fazem.
- Identificações ambíguas entre campos são rejeitadas. Login com mensagens genéricas, limitação de tentativas, CSRF, regeneração de sessão e rehash de palavras-passe. Logout invalida sessão e renova CSRF.
- Gates por perfil e middleware de acesso validam perfil, instituição e conta activa. Cada perfil tem a sua rota inicial; os painéis não apresentam dados académicos fictícios.
- `TenantManager::store()` guarda uploads no disco privado `local`, em `tenants/{id}/...`. O logótipo é servido por rota vinculada ao host, sem tornar uploads privados públicos. Ainda não existe formulário de upload nesta fase.
- As views usam CSS compilado localmente e variáveis de cor validadas no formato hexadecimal de seis dígitos.

### Contrato para os módulos seguintes

Todas as tabelas de negócio devem ter `tenant_id` indexado e os Models devem usar `BelongsToTenant`. Usar Eloquent com Policies e Form Requests. Operações directas com `DB::table`, SQL, `insert`, `upsert`, remoção deliberada de scopes ou actualizações em massa não executam os eventos de Model; devem ser encapsuladas em serviços com filtros explícitos e nunca receber um `tenant_id` vindo do formulário. Métodos de escrita em massa não devem alterar `tenant_id`.

Os índices e os casts de perfil não substituem autorização. O painel central ainda é apenas o dashboard inicial; CRUD de instituições, planos e gestão de utilizadores não fazem parte da Fase 1. Não foram implementados módulos académicos ou financeiros, recuperação de palavra-passe, nem emissão de documentos nesta fase.

## Validação executada em 24/11/2026

```sh
php artisan migrate:fresh --seed
php artisan test
php vendor/bin/pint
npm run build
php artisan route:cache
php artisan view:cache
php artisan optimize:clear
```

Resultados: migrations e seeding aprovados; 22 testes, 115 verificações aprovadas; formatação aplicada; build Vite aprovado; caches de rotas e views criadas e posteriormente limpas com sucesso.

Os testes cobrem resolução de host e domínio personalizado, branding, instituições suspensas, todos os perfis, códigos e telefones, acesso central, reutilização de sessão entre contextos, inactividade, rate limiting, consultas sem contexto, criação e transferências indevidas, índices únicos, identidades ambíguas, isolamento de ficheiros, traversal, recuperação de identidade pelo provider, execução global do super administrador e idempotência dos seeders.

Limitações da validação: apenas SQLite foi executado. Chromium foi impedido de arrancar pelas restrições de socket do ambiente; a revisão visual real em computador e telemóvel está pendente. O layout responsivo foi implementado, mas não é apresentado como visualmente validado.

## Publicação futura

Configurar o DocumentRoot em `public/`, DNS/hosts virtuais para o domínio central e tenants, certificados TLS, `APP_DEBUG=false`, cookies seguros em HTTPS, `SESSION_DOMAIN=null` e permissões de escrita em `storage` e `bootstrap/cache`. Os assets compilados estão incluídos para facilitar cPanel. Não disponibilizar ficheiros `.env`, bases de dados ou pastas privadas pela Web.

Em produção, usar `php artisan migrate --force`; não usar o seeder de demonstração (ele recusa executar em produção). O provisionamento seguro da conta administrativa de produção deve ser tratado antes da publicação. Nenhum servidor ou domínio público foi configurado neste trabalho.

## Ficheiros

Consultar `FICHEIROS_FASE_1.md` para o inventário completo dos ficheiros entregues, incluindo a estrutura base criada de raiz.

A Fase 2 não foi iniciada.
