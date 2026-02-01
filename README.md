# Sintezy SDK PHP

SDK oficial para integração com a plataforma Sintezy.

## Instalação

```bash
composer require sintezy/sdk
```

## Uso Rápido

```php
<?php

require_once 'vendor/autoload.php';

use Sintezy\SintezySDK;

// 1. Inicializar a SDK
$sdk = new SintezySDK([
    'clientId' => 'seu-client-id',
    'clientSecret' => 'seu-client-secret',
]);

// 2. Criar uma consulta (autenticação é automática)
$appointment = $sdk->createAppointment([
    'userEmail' => 'medico@clinica.com',
    'userName' => 'Dr. João Silva',
    'layout' => [
        'fields' => [
            ['name' => 'Queixa Principal', 'content' => 'inserir aqui a queixa principal', 'position' => 0],
            ['name' => 'História da Doença Atual', 'content' => 'inserir aqui a história', 'position' => 1],
            ['name' => 'Exame Físico', 'content' => 'inserir aqui os exames', 'position' => 2],
            ['name' => 'Diagnóstico', 'content' => 'inserir aqui o diagnóstico', 'position' => 3],
            ['name' => 'Conduta', 'content' => 'inserir aqui a conduta', 'position' => 4],
        ]
    ]
]);

// 3. Abrir portal para gravação
echo "Portal URL: " . $appointment->portalUrl . "\n";
// O médico grava a consulta no portal

// 4. Após finalizar, buscar o documento principal
$documento = $sdk->getDocument($appointment->secureId, 'document');

// 5. Gerar outros documentos
$receita = $sdk->generateDocument($appointment->secureId, 'prescription');
$atestado = $sdk->generateDocument($appointment->secureId, 'certificate');
```

## Métodos Disponíveis

### Autenticação

| Método | Descrição |
|--------|-----------|
| `authenticate()` | Autentica usando Client Credentials (OAuth 2.0). Chamado automaticamente. |
| `isAuthenticated()` | Verifica se há um token válido |

### Consultas (Appointments)

| Método | Descrição |
|--------|-----------|
| `createAppointment($params)` | Cria uma nova consulta e retorna a URL do portal |
| `getAppointment($secureId)` | Busca uma consulta pelo ID |
| `deleteAppointment($secureId)` | Exclui uma consulta (soft delete) |

### Documentos

| Método | Descrição |
|--------|-----------|
| `generateDocument($secureId, $type)` | Gera um documento a partir de uma consulta finalizada |
| `getDocument($secureId, $type)` | Busca um documento específico |
| `listDocuments($secureId)` | Lista todos os documentos disponíveis |

## Tipos de Documento

| Tipo | Descrição |
|------|-----------|
| `document` | Prontuário/Documento principal (gerado automaticamente ao finalizar) |
| `anamnese_summary` | Resumo de anamnese |
| `clinic_summary` | Resumo clínico |
| `referral` | Encaminhamento |
| `exames_call` | Solicitação de exames |
| `prescription` | Receita médica |
| `certificate` | Atestado médico |
| `inss_report` | Laudo INSS |

## Requisitos

- PHP 8.0 ou superior
- Extensão cURL
- Extensão JSON

## Licença

MIT
