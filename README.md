# MauticEvolutionBundle

Plugin oficial para integração entre Mautic e Evolution API, permitindo o envio de mensagens WhatsApp através de campanhas automatizadas.

## 📋 Índice

1. [Estado Atual do Plugin](#estado-atual-do-plugin)
2. [Requisitos do Sistema](#requisitos-do-sistema)
3. [Instalação e Configuração](#instalação-e-configuração)
4. [Exemplos Práticos de Utilização](#exemplos-práticos-de-utilização)
5. [Funcionalidades Implementadas](#funcionalidades-implementadas)
6. [Configurações Avançadas](#configurações-avançadas)
7. [Solução de Problemas](#solução-de-problemas)
8. [Estrutura do Plugin](#estrutura-do-plugin)
9. [Contribuição](#contribuição)

## 🚀 Estado Atual do Plugin

### Versão Atual
- **Versão**: 1.0.0
- **Status**: Estável
- **Última Atualização**: 2024

### Compatibilidade
- **Mautic**: >= 6.0
- **PHP**: >= 8.1
- **Evolution API**: >= 1.5.0
- **Symfony**: >= 6.0

### Funcionalidades Implementadas ✅

- ✅ **Envio de Mensagens de Texto**: Mensagens simples via WhatsApp
- ✅ **Envio de Mídia**: Imagens, documentos e outros arquivos
- ✅ **Templates Dinâmicos**: Sistema completo de templates com variáveis
- ✅ **Integração com Campanhas**: Actions nativas no Campaign Builder
- ✅ **Webhooks**: Recebimento de status de entrega e leitura
- ✅ **Sistema de Logs**: Auditoria completa de mensagens enviadas
- ✅ **Interface Administrativa**: Gerenciamento via painel do Mautic
- ✅ **Processamento de Status**: Atualização automática de status de mensagens

### Funcionalidades em Desenvolvimento 🚧

- 🚧 **Mensagens de Áudio**: Envio de mensagens de voz
- 🚧 **Botões Interativos**: Suporte a botões e menus
- 🚧 **Relatórios Avançados**: Dashboard com métricas detalhadas

## 🔧 Requisitos do Sistema

### Requisitos Obrigatórios

#### Servidor
- **PHP**: 8.1 ou superior
- **Composer**: Para gerenciamento de dependências
- **Extensões PHP**:
  - `curl`
  - `json`
  - `mbstring`
  - `openssl`

#### Mautic
- **Versão**: 6.0 ou superior
- **Permissões**: Acesso de administrador para configuração
- **Banco de Dados**: MySQL/MariaDB com suporte a UTF8MB4

#### Evolution API
- **Versão**: 1.5.0 ou superior
- **Instância WhatsApp**: Configurada e conectada
- **API Key**: Válida e ativa
- **Webhook Endpoint**: Acessível publicamente

### Dependências PHP

```json
{
    "php": ">=8.1",
    "guzzlehttp/guzzle": "^7.0",
    "symfony/http-foundation": "^6.0",
    "doctrine/orm": "^2.14"
}
```

## 📦 Instalação e Configuração

### Passo 1: Instalação do Plugin

#### Opção A: Via Composer (Recomendado)
```bash
cd /caminho/para/mautic
composer require mautic/evolution-bundle
```

#### Opção B: Instalação Manual
```bash
# Clone o repositório na pasta de plugins
cd plugins/
git clone https://github.com/mautic/MauticEvolutionBundle.git
```

### Passo 2: Ativação no Mautic

```bash
# Limpar cache do Mautic
php bin/console cache:clear

# Instalar assets do plugin
php bin/console mautic:assets:generate

# Atualizar schema do banco de dados
php bin/console doctrine:schema:update --force
```

### Passo 3: Configuração da Integração

1. **Acesse o Painel Administrativo**:
   - Vá para `Configurações` → `Plugins`
   - Encontre "Mautic Evolution Plugin"
   - Clique em "Configurar"

2. **Configure os Parâmetros Básicos**:

| Campo | Descrição | Obrigatório |
|-------|-----------|-------------|
| **URL da Evolution API** | URL base da sua instância Evolution API | ✅ |
| **API Key** | Chave de autenticação da Evolution API | ✅ |
| **Timeout** | Tempo limite para requisições (segundos) | ❌ |
| **Habilitar Webhooks** | Receber atualizações de status | ❌ |
| **Modo Debug** | Logs detalhados para desenvolvimento | ❌ |

3. **Exemplo de Configuração**:
```php
// Configuração via interface ou arquivo local.php
$config = [
    'evolution_api_url' => 'https://sua-evolution-api.com',
    'evolution_api_key' => 'sua-api-key-aqui',
    'evolution_timeout' => 30,
    'evolution_webhook_enabled' => true,
    'evolution_debug_mode' => false
];
```

### Passo 4: Configuração de Webhooks

1. **Configure o Webhook na Evolution API**:
```bash
curl -X POST "https://sua-evolution-api.com/external-webhook/create" \
  -H "Content-Type: application/json" \
  -H "apikey: sua-api-key" \
  -d '{
    "name": "Update event send",
    "url": "http://seu.mautic/webhook/evolution/receive",
    "enabled": true,
    "events": ["messages.update"],
    "description": "Webhook para testar evento MESSAGES_UPDATE"
  }'
```

2. **Teste a Conectividade**:
```bash
# Verificar health check
curl https://seu-mautic.com/webhook/evolution/health

# Resposta esperada:
# {"status": "ok", "timestamp": "2024-01-01T12:00:00Z"}
```

## 💡 Exemplos Práticos de Utilização

### 1. Envio de Mensagem Simples em Campanha

1. **Criar Nova Campanha**:
   - Vá para `Campanhas` → `Nova Campanha`
   - Configure os critérios de entrada

2. **Adicionar Action Evolution**:
   - No Campaign Builder, clique em "+"
   - Selecione "Actions" → "Enviar Mensagem WhatsApp"
   - Configure a mensagem:

```
Olá {contactfield=firstname}!

Temos uma oferta especial para você:
🎯 {contactfield=custom_offer}

Válida até: {contactfield=offer_expiry}

Responda QUERO para mais informações!
```

### 2. Envio de Template Personalizado

1. **Criar Template**:
   - Vá para `Evolution` → `Templates`
   - Clique em "Novo Template"

2. **Configurar Template**:
```json
{
  "name": "Boas-vindas",
  "content": "Bem-vindo(a) {contactfield=firstname}!\n\nSua conta foi criada com sucesso.\nID: {contactfield=id}\nE-mail: {contactfield=email}",
  "variables": ["firstname", "id", "email"]
}
```

3. **Usar em Campanha**:
   - Action: "Enviar Template WhatsApp"
   - Selecionar template criado
   - Configurar campo do telefone (padrão: `mobile`)

### 3. Envio de Mídia com Caption

```php
// Exemplo via API Service
$evolutionApi = $this->get('mautic.evolution.api.service');

$result = $evolutionApi->sendMediaMessage(
    '+5511999999999',
    'https://exemplo.com/imagem.jpg',
    'Confira nossa nova promoção! 🎉',
    $contact,
    $event
);
```

### 4. Configuração de Webhook Personalizado

```php
// No seu EventListener personalizado
public function onWebhookReceived(WebhookEvent $event): void
{
    $data = $event->getData();
    
    if ($data['event'] === 'MESSAGES_UPDATE') {
        $messageId = $data['data']['key']['id'];
        $status = $data['data']['status'];
        
        // Processar atualização de status
        $this->updateMessageStatus($messageId, $status);
    }
}
```

## 🔧 Funcionalidades Implementadas

### Sistema de Mensagens

#### Tipos de Mensagem Suportados
- **Texto Simples**: Mensagens de texto com suporte a emojis
- **Mídia**: Imagens, documentos, áudios e vídeos
- **Templates**: Mensagens padronizadas com variáveis dinâmicas

#### Variáveis Disponíveis
Todas as variáveis de contato do Mautic podem ser utilizadas:
- `{contactfield=firstname}` - Nome
- `{contactfield=lastname}` - Sobrenome
- `{contactfield=email}` - E-mail
- `{contactfield=mobile}` - Telefone
- `{contactfield=company}` - Empresa
- Campos personalizados: `{contactfield=nome_do_campo}`

### Sistema de Templates

#### Gerenciamento de Templates
- **CRUD Completo**: Criar, editar, visualizar e excluir templates
- **Preview**: Visualização prévia com dados de exemplo
- **Clonagem**: Duplicar templates existentes
- **Ativação/Desativação**: Controle de status dos templates

#### Estrutura de Template
```json
{
  "id": 1,
  "name": "Nome do Template",
  "content": "Conteúdo com {contactfield=variavel}",
  "isPublished": true,
  "dateAdded": "2024-01-01T12:00:00Z",
  "dateModified": "2024-01-01T12:00:00Z"
}
```

### Sistema de Webhooks

#### Eventos Suportados
- `MESSAGES_UPSERT`: Nova mensagem recebida
- `MESSAGES_UPDATE`: Atualização de status de mensagem
- `SEND_MESSAGE`: Confirmação de envio

#### Status de Mensagem
- `PENDING`: Aguardando envio
- `SENT`: Enviada
- `DELIVERY_ACK`: Entregue
- `READ`: Lida
- `FAILED`: Falha no envio

### Integração com Campanhas

#### Actions Disponíveis
1. **Enviar Mensagem WhatsApp**
   - Mensagem de texto personalizada
   - Suporte a variáveis de contato
   - Configuração de campo de telefone

2. **Enviar Template WhatsApp**
   - Seleção de template pré-configurado
   - Substituição automática de variáveis
   - Validação de campos obrigatórios

#### Configurações de Action
```php
// Configuração de envio de mensagem
[
    'message' => 'Sua mensagem aqui com {contactfield=firstname}',
    'phone_field' => 'mobile', // Campo que contém o telefone
    'media_url' => '', // URL da mídia (opcional)
    'media_caption' => '' // Legenda da mídia (opcional)
]
```

## ⚙️ Configurações Avançadas

### Configuração de Timeout
```php
// config/local.php
return [
    'parameters' => [
        'evolution_timeout' => 60, // 60 segundos
        'evolution_retry_attempts' => 3,
        'evolution_retry_delay' => 5 // 5 segundos entre tentativas
    ]
];
```

### Configuração de Logs
```php
// Habilitar logs detalhados
'evolution_debug_mode' => true,
'evolution_log_level' => 'debug', // debug, info, warning, error
'evolution_log_file' => 'var/logs/evolution.log'
```

### Configuração de Webhook Personalizado
```php
// EventListener personalizado
class CustomWebhookListener implements EventSubscriberInterface
{
    public static function getSubscribedEvents(): array
    {
        return [
            'mautic.evolution.webhook.received' => 'onWebhookReceived'
        ];
    }
    
    public function onWebhookReceived(WebhookEvent $event): void
    {
        // Sua lógica personalizada aqui
    }
}
```

## 🔍 Solução de Problemas

### Problemas Comuns

#### 1. Erro de Conexão com Evolution API
**Sintoma**: `Connection timeout` ou `Connection refused`

**Soluções**:
```bash
# Verificar conectividade
curl -I https://sua-evolution-api.com

# Testar API Key
curl -H "apikey: sua-key" https://sua-evolution-api.com/instance/connectionState/sua-instancia

# Verificar logs do Mautic
tail -f var/logs/mautic_prod.log | grep evolution
```

#### 2. Mensagens Não Enviadas
**Sintoma**: Status permanece como `PENDING`

**Verificações**:
1. **Instância WhatsApp Conectada**:
```bash
curl -H "apikey: sua-key" \
  https://sua-evolution-api.com/instance/connectionState/sua-instancia
```

2. **Formato do Número**:
```php
// Formato correto: +5511999999999
// Formato incorreto: 11999999999, (11) 99999-9999
```

3. **Logs de Erro**:
```bash
# Verificar logs específicos do Evolution
grep -i "evolution" var/logs/mautic_prod.log

# Verificar logs de campanha
grep -i "campaign" var/logs/mautic_prod.log
```

#### 3. Webhooks Não Funcionando
**Sintoma**: Status de mensagens não atualizam

**Verificações**:
1. **URL do Webhook Acessível**:
```bash
curl -X POST https://seu-mautic.com/webhook/evolution/receive \
  -H "Content-Type: application/json" \
  -d '{"test": true}'
```

2. **Configuração na Evolution API**:
```bash
curl -H "apikey: sua-key" \
  https://sua-evolution-api.com/webhook/find/sua-instancia
```

3. **Logs de Webhook**:
```bash
tail -f var/logs/mautic_prod.log | grep webhook
```

#### 4. Erro de Banco de Dados
**Sintoma**: `Table 'evolution_messages' doesn't exist`

**Solução**:
```bash
# Atualizar schema do banco
php bin/console doctrine:schema:update --force

# Verificar se as tabelas foram criadas
php bin/console doctrine:schema:validate
```

#### 5. Problemas de Permissão
**Sintoma**: `Access denied` ou `Permission denied`

**Verificações**:
1. **Permissões de Arquivo**:
```bash
# Definir permissões corretas
chmod -R 755 plugins/MauticEvolutionBundle/
chown -R www-data:www-data plugins/MauticEvolutionBundle/
```

2. **Permissões do Usuário no Mautic**:
   - Verificar se o usuário tem permissão para gerenciar plugins
   - Verificar permissões de campanha

### Comandos de Diagnóstico

#### Verificar Status do Plugin
```bash
# Listar plugins instalados
php bin/console mautic:plugins:list

# Verificar status específico
php bin/console mautic:plugins:install --plugin=MauticEvolutionBundle
```

#### Testar Conectividade
```bash
# Script de teste de conectividade
php bin/console mautic:evolution:test-connection
```

#### Limpar Cache
```bash
# Limpar todos os caches
php bin/console cache:clear

# Limpar cache específico do plugin
php bin/console cache:clear --env=prod
```

### Logs Importantes

#### Localização dos Logs
- **Logs Gerais**: `var/logs/mautic_prod.log`
- **Logs de Desenvolvimento**: `var/logs/mautic_dev.log`
- **Logs de Campanha**: Filtrar por `campaign` nos logs gerais

#### Exemplos de Logs

**Envio Bem-sucedido**:
```
[2024-01-01 12:00:00] mautic.INFO: Evolution message sent successfully 
{"messageId": "ABC123", "contact": 123, "phone": "+5511999999999"}
```

**Erro de Envio**:
```
[2024-01-01 12:00:00] mautic.ERROR: Evolution API error 
{"error": "Instance not connected", "phone": "+5511999999999"}
```

**Webhook Recebido**:
```
[2024-01-01 12:00:00] mautic.INFO: Evolution webhook received 
{"event": "MESSAGES_UPDATE", "messageId": "ABC123", "status": "READ"}
```

## 📁 Estrutura do Plugin

```
MauticEvolutionBundle/
├── Assets/                     # Recursos estáticos
│   ├── css/evolution.css      # Estilos do plugin
│   ├── js/evolution.js        # Scripts JavaScript
│   └── images/                # Imagens e ícones
├── Config/                     # Configurações
│   ├── config.php             # Configuração principal
│   └── services.php           # Definição de serviços
├── Controller/                 # Controladores
│   ├── TemplateController.php # Gerenciamento de templates
│   └── WebhookController.php  # Recebimento de webhooks
├── Entity/                     # Entidades do banco
│   ├── EvolutionMessage.php   # Entidade de mensagens
│   ├── EvolutionMessageRepository.php
│   ├── EvolutionTemplate.php  # Entidade de templates
│   └── EvolutionTemplateRepository.php
├── EventListener/              # Event Listeners
│   ├── CampaignSubscriber.php # Integração com campanhas
│   ├── LeadSubscriber.php     # Eventos de contatos
│   └── PluginSubscriber.php   # Eventos do plugin
├── Form/                       # Formulários
│   └── Type/                  # Tipos de formulário
│       ├── EvolutionConfigType.php
│       ├── SendMessageActionType.php
│       └── SendTemplateActionType.php
├── Integration/                # Integração principal
│   └── MauticEvolutionIntegration.php
├── Model/                      # Modelos de negócio
│   ├── MessageModel.php       # Modelo de mensagens
│   └── TemplateModel.php      # Modelo de templates
├── Service/                    # Serviços
│   ├── EvolutionApiService.php # Comunicação com API
│   ├── MessageService.php     # Processamento de mensagens
│   └── WebhookService.php     # Processamento de webhooks
├── Resources/                  # Recursos de visualização
│   ├── translations/          # Traduções
│   └── views/                 # Templates Twig
├── composer.json              # Dependências
├── MauticEvolutionBundle.php  # Classe principal
└── README.md                  # Esta documentação
```

## 🤝 Contribuição

### Como Contribuir

1. **Fork do Repositório**
2. **Criar Branch de Feature**:
```bash
git checkout -b feature/nova-funcionalidade
```

3. **Implementar Mudanças**
4. **Executar Testes**:
```bash
php bin/console mautic:test:evolution
```

5. **Commit e Push**:
```bash
git commit -m "feat: adiciona nova funcionalidade"
git push origin feature/nova-funcionalidade
```

6. **Criar Pull Request**

### Padrões de Código

- **PSR-12**: Padrão de codificação PHP
- **Symfony Best Practices**: Convenções do Symfony
- **Mautic Coding Standards**: Padrões específicos do Mautic

### Testes

```bash
# Executar todos os testes
php bin/console test

# Testes específicos do plugin
php bin/console test plugins/MauticEvolutionBundle/Tests/
```

---

## 📞 Suporte

- **Documentação**: [Wiki do Projeto](https://github.com/mautic/MauticEvolutionBundle/wiki)
- **Issues**: [GitHub Issues](https://github.com/mautic/MauticEvolutionBundle/issues)
- **Discussões**: [GitHub Discussions](https://github.com/mautic/MauticEvolutionBundle/discussions)
- **E-mail**: support@mautic.org

---

**Desenvolvido com ❤️ pela equipe Soluções Digitais