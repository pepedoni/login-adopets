# Two-Step Login PHP - Yii2

## Descrição

API de login com as seguintes funcionalidades

- Registro
- Confirmação de e-mail
- Login
- Login em duas etapas
- Reset de senha
- Logout
- Informações do úsuario

Considerações:

- Números de telefone devem estar no formato [DDI][DDD][TELEFONE]
- Collection POSTMAN: https://www.getpostman.com/collections/16e659a049941f3770d1

## Instalação 

- Rodar um banco mysql na porta 3306 e criar a base adopets
- git clone https://github.com/pepedoni/login-adopets.git
- cd login-adopets
- composer install
- ./init
- ./yii migrate
- php yii serve --docroot="backend/web/" --port=8080

Endpoint: http://localhost:8080/api/

## Rotas
### POST /register

- username  => string(255) Nome do usuário 
- password  => string(255) Senha do usuário  - Minimo 6 caracteres
- email     => string(255) E-mail do usuário - Deve ser um e-mail válido 
- two_steps => boolean     Informa se o usuário utiliza autenticação em dois passos
- phone     => string(20)  Telefone do usuário para autenticação em dois passos ( Formato [DDI][DDD][TELEFONE]) null

#### Retorna os dados do usuario cadastrado

### POST /authorize

- username  => string(255) Nome do usuário ou E-mail do usuario
- password  => string(255) Senha do usuário

### POST /accesstoken

- authorization_code => string(4) Código de autorização 
- confirmation_code  => string(4) Código de confirmação de identidade ( Somente quando usuario utiliza autenticação em dois passos )

### POST /requestresetpassword
- username  => string(255) Nome do usuário ou E-mail do usuario

### GET /resetpassword/{id}?key={token}

### GET /site/confirm/{id}?key={token}

### Rotas autenticadas ( Necessitam de setar o header X-Access-Token com o access token)

### GET /logout 

### GET /me 

