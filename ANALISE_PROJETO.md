# Controle de Absenteísmo - Análise do Projeto

## 1. Objetivo do projeto

Este projeto tem como objetivo centralizar o controle e acompanhamento de presença, ausências e ocorrências de funcionários de forma simples, visual e eficiente, permitindo:

- visualizar presença diária de todos os funcionários;
- registrar ocorrências com múltiplos tipos de justificativa (faltas, atrasados, férias, licenças, etc);
- filtrar dados por turno, setor e período;
- gerar relatórios com análise de tendências;
- gerenciar cadastro de funcionários (prontuário, nome, turno, setor);
- registrar observações em cada ocorrência;
- acompanhar KPIs em tempo real (presentes hoje, faltas, taxa de presença);
- suportar operações em lote (adicionar mesma ocorrência para múltiplos funcionários).

## 2. Principais funcionalidades

### Dashboard (index.php)
- Visualização de KPIs em tempo real:
  - Total de funcionários ativos por setor
  - Presentes hoje (total e percentual)
  - Faltas não justificadas
  - Total de ocorrências no mês
  - Funcionários com prazo de férias próximo
  - Funcionários com muitas faltas no mês
- Cards interativos com ícones
- Filtros por setor com atualização automática

### Controle de Presença (controle.php)
- Tabela dinâmica com todos os dias do mês
- Filtros por mês, ano, turno e setor
- Visualização em grid: linhas = funcionários, colunas = dias
- Cada célula mostra a justificativa com código colorido
- Cores padronizadas por tipo de justificativa (presença verde, falta vermelho, etc)
- Duplo-clique para editar célula inline
- Modal para editar ocorrência completa (adicionar observação)
- Operação em lote: aplicar mesma ocorrência para múltiplos funcionários de uma vez
- Filtros salvos em URL (GET) para compartilhamento de links

### Gerenciamento de Funcionários (usuarios.php)
- CRUD completo de usuários
- Campos: prontuário, nome, turno, setor, ativo/inativo
- Cards de resumo com total geral e contagem por turno
- Tabela com busca por nome/prontuário
- Filtros por turno e setor
- Contagem de ocorrências por funcionário
- Ações: editar, deletar, ativar/desativar

### Relatório (relatorio.php)
- Análise histórica de dados
- Filtros por período, turno, setor
- Gráficos e estatísticas
- Exportação de dados

### APIs (pasta api/)
- `save_ocorrencia.php`: salva ocorrência individual ou em lote (POST JSON)
- `delete_ocorrencia.php`: remove ocorrência específica
- `buscar_funcionario.php`: busca funcionários por nome/prontuário (autocomplete)
- `usuarios.php`: CRUD de usuários (POST para criar, PUT para atualizar, DELETE para remover)

## 3. Tecnologias utilizadas

- **Backend**: PHP 8+ (sem framework, arquitetura monolítica)
- **Banco de dados**: MySQL 5.7+ / MariaDB 10.4+
- **Frontend**: HTML5, CSS3, JavaScript Vanilla (ES6+)
- **Acesso ao BD**: PDO com prepared statements
- **UI Framework**: Bootstrap 5.3
- **Ícones**: Bootstrap Icons
- **Autenticação**: Sessão PHP (não implementada no escopo atual)
- **API**: JSON via XMLHttpRequest / Fetch API

## 4. Estrutura do projeto

```
controle_absenteismo/
├── index.php                    # Dashboard principal
├── controle.php                 # Tabela de controle de presença
├── usuarios.php                 # Gerenciamento de funcionários
├── relatorio.php                # Relatórios e análises
├── .env                         # Configurações locais
├── .env.example                 # Exemplo de variáveis de ambiente
├── README.md                    # Documentação original
│
├── config/
│   ├── database.php             # Conexão PDO + função getConnection()
│   └── justificativas.php       # Constants com tipos de ocorrência + cores
│
├── includes/
│   ├── header.php               # HTML header + navbar comum
│   └── footer.php               # HTML footer comum
│
├── api/
│   ├── save_ocorrencia.php      # POST: salva ocorrência (individual/lote)
│   ├── delete_ocorrencia.php    # POST: deleta ocorrência
│   ├── buscar_funcionario.php   # GET: busca para autocomplete
│   └── usuarios.php             # GET/POST/PUT/DELETE: CRUD usuários
│
├── assets/
│   ├── css/
│   │   └── style.css            # Estilos customizados
│   └── js/
│       ├── modules/
│       │   ├── api.js           # Wrapper de chamadas HTTP
│       │   └── toast.js         # Notificações visuais
│       └── pages/
│           ├── controle.js      # Lógica de controle (inline edit, filtros)
│           ├── dashboard.js     # Atualização de KPIs
│           └── usuarios.js      # CRUD de usuários
│
└── [banco de dados MySQL]       # Tabelas: usuarios, ocorrencias
```

## 5. Modelo de dados (Banco de Dados)

### Tabela: usuarios
```sql
- id (INT, PRIMARY KEY)
- prontuario (VARCHAR, UNIQUE)
- nome (VARCHAR)
- turno (VARCHAR) -- 'Comercial', 'Segundo Turno', 'Terceiro Turno'
- setor (VARCHAR) -- ex: 'Produção', 'Administrativo', 'Vendas'
- ativo (TINYINT) -- 0 = inativo, 1 = ativo
- criado_em (TIMESTAMP)
- atualizado_em (TIMESTAMP)
```

### Tabela: ocorrencias
```sql
- id (INT, PRIMARY KEY)
- usuario_id (INT, FOREIGN KEY -> usuarios.id)
- data (DATE)
- justificativa (VARCHAR) -- códigos: 'P', 'F', 'FJ', 'AT', 'LME', 'FE', 'FER', 'HE', 'BH', etc
- observacao (TEXT)
- criado_em (TIMESTAMP)
- atualizado_em (TIMESTAMP)
```

### Justificativas (constantes em justificativas.php)
```
'P'   => 'Presença' (verde)
'F'   => 'Falta Não Justificada' (vermelho)
'FJ'  => 'Falta Justificada' (amarelo)
'AT'  => 'Atraso' (marrom)
'LME' => 'Licença Médica' (azul claro)
'FE'  => 'Férias' (roxo)
'FER' => 'Feriado' (ciano)
'FG'  => 'Folga' (cinza)
'HE'  => 'Hora Extra' (laranja)
'BH'  => 'Banco de Horas' (azul escuro)
'COM' => 'Compensação' (teal)
'SU'  => 'Suspensão' (verde claro)
'LMA' => 'Licença Maternidade' (marrom escuro)
'CH'  => 'Crachá' (ciano escuro)
'SD'  => 'Saída' (pêssego)
```

Cada justificativa possui:
- `label`: nome legível
- `bg`: cor de fundo da célula
- `text`: cor do texto
- `bold`: se texto deve ser negritado
- `group`: classificação ('ausencia' ou 'trabalho')

## 6. Requisitos

- **PHP**: 8.0 ou superior
- **Banco de dados**: MySQL 5.7+ ou MariaDB 10.4+
- **Servidor**: XAMPP (ou Apache + PHP + MySQL)
- **Extensões PHP**: pdo_mysql, fileinfo, session
- **Navegador**: Chrome, Firefox, Safari, Edge (compatível com ES6)
- **Charset**: utf8mb4 (suporte a acentuação)

## 7. Configuração do ambiente

### 7.1 Estrutura de diretórios
1. Clone/copie o projeto em `c:/xampp/htdocs/controle_absenteismo`
2. Verifique permissões de leitura/escrita nos diretórios

### 7.2 Arquivo .env
Crie `.env` na raiz do projeto com base em `.env.example`:
```env
DB_HOST=
DB_PORT=
DB_USERNAME=root
DB_PASSWORD=
DB_NAME=
```

### 7.3 Banco de dados
- O projeto cria o banco automaticamente ao conectar se não existir
- Requer que o banco `controle_absenteismo` esteja configurado no MySQL
- A estrutura das tabelas deve ser criada manualmente (SQL fornecido no projeto ou em script separado)

## 8. Como executar

1. **Inicie Apache e MySQL** no XAMPP
2. **Acesse no navegador**:
   - Dashboard: `http://localhost/controle_absenteismo/`
   - Controle: `http://localhost/controle_absenteismo/controle.php`
   - Funcionários: `http://localhost/controle_absenteismo/usuarios.php`
3. **Crie funcionários** via página de Funcionários
4. **Registre ocorrências** na tabela de Controle ou diretamente no card do Dashboard

## 9. Fluxo de uso recomendado

### Cenário 1: Primeira execução
1. Acessar página de Funcionários
2. Criar novo funcionário (prontuário, nome, turno, setor)
3. Repetir para todos os funcionários

### Cenário 2: Registro de presença diária
1. Acessar Dashboard para verificar KPIs
2. Ir para Controle de Presença
3. Localizar data e funcionário
4. Duplo-clique na célula para marcar presença rápida, ou
5. Clique direito / botão para abrir modal e adicionar observação
6. Se múltiplos funcionários com mesma ocorrência, usar operação em lote

### Cenário 3: Análise mensal
1. Aplicar filtros (turno, setor, período)
2. Consultar Relatório para gráficos e estatísticas
3. Exportar dados se necessário

## 10. Regras de negócio implementadas

- **Validação de data**: formato YYYY-MM-DD obrigatório
- **Códigos de justificativa**: apenas códigos pré-definidos em JUSTIFICATIVAS são aceitos
- **Prontuário único**: não é permitido duplicar prontuário
- **Filtros cascata**: setor e turno são filtrados por dados reais no BD
- **Operação em lote**: uma ocorrência pode ser aplicada a múltiplos funcionários simultaneamente
- **Edição inline**: duplo-clique em célula permite edição rápida
- **Observações**: suportam texto livre para contexto adicional
- **Funcionários ativos**: apenas usuários com ativo=1 aparecem em listas
- **Cores por tipo**: cada justificativa tem cor padronizada para rápida identificação visual

## 11. Segurança e boas práticas

- ✅ **Prepared statements**: todas as queries usam PDO para evitar SQL injection
- ✅ **Escape de saída**: `htmlspecialchars()` em valores exibidos
- ✅ **Validação de entrada**: data, código de justificativa, IDs numéricos validados
- ✅ **Charset UTF-8**: suporte a caracteres acentuados
- ⚠️ **Autenticação**: não implementada no escopo atual (recomenda-se adicionar em produção)
- ⚠️ **CSRF**: não implementado explicitamente (recomenda-se tokens em operações de escrita)
- ⚠️ **Autorização**: não há controle de permissões por perfil de usuário

## 12. Fluxo de dados

```
Cliente (Browser)
    ↓ (GET/POST/DELETE)
    ↓ (JSON request)
    ↓
API Endpoints (api/*.php)
    ↓ (validação)
    ↓ (prepared statements)
    ↓
PDO / MySQL
    ↓ (query result)
    ↓
JSON Response
    ↓
JavaScript (pages/*.js)
    ↓ (update DOM / toast)
    ↓
User Interface
```

### Exemplo: Salvar ocorrência
```
1. Usuário duplo-clica célula em controle.js
2. controle.js captura (usuario_id, data, justificativa)
3. Faz POST para api/save_ocorrencia.php (JSON)
4. save_ocorrencia.php valida e insere em BD via PDO
5. Retorna JSON {success: true, id: 123}
6. controle.js atualiza UI com nova cor/texto
7. toast.js exibe notificação "Salvo com sucesso"
```

## 13. Possíveis melhorias futuras

- 📋 **Histórico de alterações**: rastreabilidade de mudanças por usuário e data
- 🔐 **Autenticação e autorização**: login de usuários + perfis de acesso
- 📊 **Dashboard melhorado**: gráficos de tendência, heatmap de faltas, previsões
- 📤 **Exportação**: relatórios em PDF/Excel com filtros e formatação
- 📱 **Responsividade**: layout mobile-first para usar em tablets/smartphones
- ⚙️ **Configurações**: permitir cadastro de novos turnos, setores, tipos de justificativa
- 🔔 **Notificações**: alertas automáticos para faltas frequentes ou prazo de férias
- 🔗 **Integração com RH**: sincronização com sistemas externos (folha, ERP)
- 🌙 **Modo escuro**: tema visual alternativo
- 🇧🇷 **i18n**: suporte a múltiplos idiomas

## 14. Solução de problemas rápida

### Erro: "Falha na conexão com o banco de dados"
- Verifique se MySQL está rodando (XAMPP Control Panel)
- Confirme credenciais em `.env` (host, porta, usuário, senha)
- Teste conexão manual: `mysql -h 127.0.0.1 -u root`

### Erro: "Tabela 'usuarios' não encontrada"
- Banco ainda não foi criado
- Execute script SQL para criar tabelas (schema.sql ou import manual)

### Caracteres com acento estranhos
- Confirme charset UTF-8MB4 na conexão (verificar em database.php)
- Verifique collation das tabelas: `SHOW CREATE TABLE usuarios`

### Operação em lote não funciona
- Verifique se `usuario_ids` é um array JSON válido
- Confirme que todos os IDs são numéricos > 0

### Ocorrência não aparece após salvar
- Abra DevTools (F12) para ver erro na rede
- Verifique console.js para erros JavaScript
- Verifique se código de justificativa existe em justificativas.php

## 15. Estrutura de arquivos críticos

### config/database.php
- Define `getConnection()` que retorna singleton PDO
- Carrega variáveis de `.env`
- Trata exceções de conexão

### config/justificativas.php
- Define array JUSTIFICATIVAS com cores e labels
- Função `cellStyle()` para gerar inline styles

### assets/js/modules/api.js
- Wrapper para fetch/XMLHttpRequest
- Centraliza comunicação HTTP com APIs

### assets/js/modules/toast.js
- Sistema de notificações visuais
- Tipos: success, error, info, warning

### assets/css/style.css
- Estilos customizados da aplicação
- Cores por tipo de justificativa
- Classes de utilitários (KPI cards, badges, etc)

## 16. Recomendações finais

Se este projeto for usado em **produção**:

1. ✅ Adicionar **autenticação** (login de usuários)
2. ✅ Implementar **CSRF tokens** em todas as operações de escrita
3. ✅ Adicionar **logging** de auditoria (quem fez o quê e quando)
4. ✅ Configurar **backup automatizado** do banco de dados
5. ✅ Usar **HTTPS** em servidor web
6. ✅ Revisar **permissões de arquivo** (640, 750 para diretórios sensíveis)
7. ✅ Implementar **rate limiting** nas APIs
8. ✅ Adicionar **validação de input** mais rigorosa
9. ✅ Usar **variáveis de ambiente** para dados sensíveis
10. ✅ Documentar **procedimentos de backup e disaster recovery**

---

**Documento gerado em**: 2026-05-12  
**Versão do projeto**: 1.0  
**Última atualização**: 2026-05-12
