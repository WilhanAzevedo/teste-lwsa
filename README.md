# API de Controle de Estoque e Vendas

API REST desenvolvida com Laravel para gerenciamento simplificado de estoque e vendas para um sistema ERP.

## 📋 Requisitos

- Docker
- Docker Compose
- Git

## 🚀 Tecnologias

- **Laravel 12** - Framework PHP
- **PHP 8.2+** - Linguagem de programação
- **PostgreSQL 17** - Banco de dados
- **Redis 7** - Cache e gerenciamento de filas
- **Supervisor** - Gerenciamento de processos (queue worker)
- **PHPUnit** - Testes unitários

## 🏗️ Arquitetura

O projeto utiliza as seguintes práticas e padrões:

- **Repository Pattern** - Abstração da camada de dados
- **Service Layer** - Lógica de negócio centralizada
- **Dependency Injection** - Inversão de controle via interfaces
- **Events & Listeners** - Processamento assíncrono
- **Form Requests** - Validação de entrada
- **API Resources** - Formatação de resposta
- **Queue Workers** - Processamento em background

## 📦 Instalação

### 1. Clone o repositório

```bash
git clone <repository-url>
cd teste-lwsa
```

### 2. Configure as variáveis de ambiente

```bash
cp .env.example .env
```

### 3. Suba os containers Docker

```bash
docker-compose up -d
```

Isso irá criar 4 containers:
- `teste-lwsa-app` - Aplicação Laravel (porta 8080)
- `teste-lwsa-queue-worker` - Worker para processar filas
- `teste-lwsa-db` - PostgreSQL (porta 5432)
- `teste-lwsa-redis` - Redis (porta 6379)

### 4. Instale as dependências

```bash
docker exec -it teste-lwsa-app composer install
```

### 5. Gere a chave da aplicação

```bash
docker exec -it teste-lwsa-app php artisan key:generate
```

### 6. Execute as migrations

```bash
docker exec -it teste-lwsa-app php artisan migrate
```

### 7. Popule o banco de dados (opcional)

```bash
docker exec -it teste-lwsa-app php artisan db:seed --class=ProductSeeder
```

Isso irá criar 5 produtos de exemplo com seus respectivos inventários.

## 🧪 Testes

Execute os testes unitários:

```bash
docker exec -it teste-lwsa-app php artisan test
```

Ou

```bash
docker exec -it teste-lwsa-app /vendor/bin/phpunit
```


## 📚 Endpoints da API

### Base URL
```
http://localhost:8080
```

### Registrar Entrada de Estoque
```http
POST /api/inventory
Content-Type: application/json

{
  "product_id": 1,
  "quantity": 50,
  "cost_price": 100.00
}
```

### Consultar Estoque
```http
GET /api/inventory?page=1&per_page=10
```

### Registrar Venda
```http
POST /api/sales
Content-Type: application/json

{
  "items": [
    {
      "product_id": 1,
      "quantity": 2
    }
  ]
}
```

### Consultar Venda
```http
GET /api/sales/{id}
```

## ⚙️ Funcionalidades

### Sistema de Eventos

Quando uma venda é criada:
1. O evento `SaleCreated` é disparado
2. O listener `UpdateInventory` processa a atualização assíncrona via fila
3. O estoque de cada produto é debitado automaticamente
4. Se houver falha (estoque insuficiente), a venda é cancelada automaticamente

### Gerenciamento de Cache

- Listagens de inventário e detalhes de venda são armazenados em cache
- Cache é invalidado automaticamente ao adicionar/debitar estoque ou criar uma venda
- Utiliza Redis com tags para gerenciamento eficiente



