# 🔧 Configuração de Conexões DynamoDB

Este package suporta múltiplas conexões DynamoDB (AWS e Local).

## 📋 Como Funciona

Este package usa `config/database-dynamodb.php` (ou `config/dynamodb.php` para compatibilidade) para definir as conexões DynamoDB. As conexões são automaticamente mescladas com `config/database.php` pelo ServiceProvider, então você **não precisa** modificar `config/database.php` manualmente!

## ⚙️ Configuração

### 1. Publicar o arquivo de configuração

```bash
php artisan vendor:publish --provider="AutomoveisConfiaveis\LaravelDynamoDb\DynamoDbServiceProvider" --tag="dynamodb-config"
```

Isso cria o arquivo `config/database-dynamodb.php` com as conexões padrão.

> **Nota:** O package suporta tanto `database-dynamodb.php` (novo) quanto `dynamodb.php` (legado) para compatibilidade com código existente.

### 2. Configurar `config/database-dynamodb.php`

O arquivo já vem com duas conexões pré-configuradas (`aws` e `local`). Você pode editar conforme necessário:

```php
'connections' => [
    'aws' => [
        'driver' => 'dynamodb',
        'database' => env('DYNAMODB_TABLE', 'default'),
        'table' => env('DYNAMODB_TABLE', 'default'),
        'prefix' => '',
        'region' => env('AWS_DEFAULT_REGION', 'us-east-1'),
        'key' => env('AWS_ACCESS_KEY_ID'),
        'secret' => env('AWS_SECRET_ACCESS_KEY'),
    ],

    'local' => [
        'driver' => 'dynamodb',
        'database' => env('DYNAMODB_TABLE', 'default'),
        'table' => env('DYNAMODB_TABLE', 'default'),
        'prefix' => '',
        'region' => env('DYNAMODB_REGION', 'us-east-1'),
        'endpoint' => env('DYNAMODB_ENDPOINT', 'http://localhost:8000'),
        'key' => env('DYNAMODB_ACCESS_KEY_ID', 'AKIAIOSFODNN7EXAMPLE'),
        'secret' => env('DYNAMODB_SECRET_ACCESS_KEY', 'wJalrXUtnFEMI/K7MDENG/bPxRfiCYEXAMPLEKEY'),
    ],
],
```

### 2. Configurar `.env`

Para **AWS DynamoDB**:
```env
AWS_ACCESS_KEY_ID=your-access-key
AWS_SECRET_ACCESS_KEY=your-secret-key
AWS_DEFAULT_REGION=us-east-1
```

Para **DynamoDB Local**:
```env
DYNAMODB_ENDPOINT=http://localhost:8000
DYNAMODB_REGION=us-east-1
DYNAMODB_ACCESS_KEY_ID=AKIAIOSFODNN7EXAMPLE
DYNAMODB_SECRET_ACCESS_KEY=wJalrXUtnFEMI/K7MDENG/bPxRfiCYEXAMPLEKEY
```

### 3. Usar as Conexões

As conexões definidas em `config/dynamodb.php` são automaticamente disponibilizadas e podem ser usadas normalmente:

#### Em Models:

```php
use AutomoveisConfiaveis\LaravelDynamoDb\Database\DynamoDb\Eloquent\Model;

class Cliente extends Model
{
    protected $connection = 'local'; // ou 'aws'
    protected $table = 'clientes';
    // ...
}
```

#### No Código:

```php
// Usar conexão específica
$connection = DB::connection('local');
$clientes = Cliente::on('local')->get();
```

#### Definir Conexão Padrão:

No `.env`:
```env
DB_CONNECTION=local
```

## ✅ Resumo

- ✅ **Não precisa modificar `config/database.php`**
- ✅ Define conexões apenas em `config/database-dynamodb.php` (ou `dynamodb.php` para compatibilidade)
- ✅ As conexões são automaticamente mescladas pelo ServiceProvider
- ✅ O Connector detecta automaticamente se é Local (tem endpoint) ou AWS
- ✅ Você pode ter quantas conexões precisar
- ✅ Suporta código legado com `dynamodb.php`
- ✅ Muito mais simples e organizado!

