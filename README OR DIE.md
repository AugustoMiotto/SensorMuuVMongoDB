# 🔥 README OR DIE - Guia Definitivo de Instalação e Execução

> **LEIA COM ATENÇÃO:** Este guia contém tudo o que você precisa para configurar o ambiente do zero, ligar o hardware do ESP32, rodar o backend Laravel com MongoDB e colocar o dashboard 3D no ar sem erros.

---

## 📋 1. Pré-Requisitos de Sistema

Antes de começar, certifique-se de ter instalado no seu computador:

1. **PHP 8.3 ou superior** (com a extensão oficial `mongodb` instalada e habilitada).
   - Verifique com:
     ```bash
     php -v
     php -m | findstr mongodb
     ```
   - *Se o comando não encontrar o `mongodb`:* Baixe a DLL correspondente à sua versão do PHP no [PECL MongoDB](https://pecl.php.net/package/mongodb), coloque na pasta `ext/` do PHP e adicione a linha `extension=mongodb` no seu `php.ini`.
2. **Composer (v2.x)**:
   ```bash
   composer -V
   ```
3. **Node.js (v18+) e NPM**:
   ```bash
   node -v
   npm -v
   ```
4. **Arduino IDE (v2.x)** (para gravar o código no ESP32).
5. **Cluster no MongoDB Atlas** (ou MongoDB Community Server local).

---

## 💻 2. Preparação do Backend (Laravel)

Abra o terminal na pasta raiz do projeto (`cow-backend`) e execute os passos abaixo em ordem:

### Passo 1: Instalar dependências PHP
```bash
composer install
```

### Passo 2: Instalar dependências JavaScript (Three.js, ApexCharts, Tailwind, Echo)
```bash
npm install
```

### Passo 3: Configurar o arquivo `.env`
Copie o modelo de variáveis de ambiente:
```bash
copy .env.example .env
```

Gere a chave da aplicação:
```bash
php artisan key:generate
```

Abra o arquivo `.env` gerado e configure a conexão com o seu MongoDB Atlas:
```env
DB_CONNECTION=mongodb
MONGODB_URI="mongodb+srv://<SEU_USUARIO>:<SUA_SENHA>@<SEU_CLUSTER>.mongodb.net/cow_tracking?retryWrites=true&w=majority"
MONGODB_DATABASE=cow_tracking

# Configurações do WebSocket (Reverb)
BROADCAST_CONNECTION=reverb
REVERB_APP_ID=307916
REVERB_APP_KEY=xtds2nzenmmqchar54wz
REVERB_APP_SECRET=hluyeupwrpu9hj62publ
REVERB_HOST="localhost"
REVERB_PORT=8080
REVERB_SCHEME=http

# Token de autenticação esperado da coleira ESP32
SENSOR_API_TOKEN=H3rsrkcZD8gzh0yWrXKYe3SBt8RwfsynuAWC2vi5
```

> ⚠️ **Atenção no MongoDB Atlas (Network Access):**
> 1. Acesse o [MongoDB Cloud](https://cloud.mongodb.com/).
> 2. No menu esquerdo, vá em **Security > Network Access**.
> 3. Clique em **Add IP Address** e adicione `0.0.0.0/0` (*Allow Access from Anywhere*) para evitar bloqueios de IP dinâmico durante os testes.

---

## ⚡ 3. Preparação do Hardware e Firmware (ESP32)

### 3.1 Esquema de Ligação dos Pinos
| Componente | Pino do Componente | Pino do ESP32 | Observações |
| :--- | :--- | :--- | :--- |
| **MPU6050** | VCC | 3.3V ou 5V | Alimentação |
| **MPU6050** | GND | GND | Terra comum |
| **MPU6050** | SDA | **GPIO 21** | Barramento I2C |
| **MPU6050** | SCL | **GPIO 22** | Barramento I2C |
| **DS18B20** | VCC | 3.3V | Alimentação do sensor de temperatura |
| **DS18B20** | GND | GND | Terra comum |
| **DS18B20** | Data | **GPIO 4** | Requer resistor pull-up de 4.7kΩ entre VCC e Data |

### 3.2 Bibliotecas no Arduino IDE
No Arduino IDE, vá em **Sketch > Incluir Biblioteca > Gerenciar Bibliotecas** e instale:
1. `Adafruit MPU6050`
2. `Adafruit Unified Sensor`
3. `Adafruit BusIO`
4. `OneWire`
5. `DallasTemperature`
6. `ArduinoJson` (versão 7.x)
7. `arduinoFFT` (por Enrique Condes)

### 3.3 Configuração do Código `.ino`
Abra o arquivo [`coleirasensormpu6050/coleirasensormpu6050.ino`](coleirasensormpu6050/coleirasensormpu6050.ino):

1. **Wi-Fi**: Coloque o SSID e senha da sua rede:
   ```cpp
   const char* ssid = "NOME_DO_SEU_WIFI";
   const char* password = "SENHA_DO_WIFI";
   ```
2. **IP da sua Máquina**: O ESP32 precisa saber o endereço IP do seu computador na rede local (descubra rodando `ipconfig` no terminal):
   ```cpp
   // Exemplo: se o seu IP local for 192.168.1.105:
   const char* serverURL    = "http://192.168.1.105:8000/api/sensors/data";
   const char* serverURL_RT = "http://192.168.1.105:8000/api/sensors/realtime";
   ```
   > 🚨 **NUNCA use `localhost` nem `127.0.0.1` no ESP32!** O ESP32 é um dispositivo externo e não consegue resolver `localhost`.
3. **Token**: Garanta que o `apiToken` seja o mesmo configurado no `.env`:
   ```cpp
   const char* apiToken = "H3rsrkcZD8gzh0yWrXKYe3SBt8RwfsynuAWC2vi5";
   ```

Grave o código no ESP32 e abra o Monitor Serial em **115200 baud** para acompanhar as mensagens de inicialização.

---

## 🚀 4. Executando a Aplicação (Os 3 Terminais)

Para a aplicação funcionar com telemetria completa, WebSockets e 3D, você precisa de **3 terminais abertos simultaneamente**:

### 💻 Terminal 1: Servidor Web Laravel
Rode escutando em todas as interfaces para o ESP32 conseguir conectar:
```bash
php artisan serve --host=0.0.0.0 --port=8000
```

### 📡 Terminal 2: Servidor de WebSockets (Laravel Reverb)
Responsável por entregar a inclinação do pescoço (Pitch/Roll) em tempo real para o simulador 3D:
```bash
php artisan reverb:start --debug
```

### 🎨 Terminal 3: Bundler dos Assets Frontend (Vite)
```bash
npm run dev
```

---

## 🌐 5. Acessando o Dashboard

Abra no navegador:
👉 [**http://localhost:8000**](http://localhost:8000)

Você verá:
- Os cards com Temperatura, Passos, Ruminação e Inclinação (Pitch).
- O **Simulador 3D** com a vaca articulando a cabeça e o pescoço de acordo com os movimentos da coleira.
- Os gráficos de histórico sendo plotados a cada novo pacote de dados salvo.

---

## ☠️ 6. Soluções de Erros Fatais ("Or Die")

### ❌ Erro: `Class "MongoDB\Driver\Manager" not found`
- **Causa:** O PHP da sua máquina não está com a extensão do MongoDB habilitada.
- **Solução:** No seu `php.ini`, certifique-se de descomentar ou adicionar `extension=mongodb` e reiniciar o terminal.

### ❌ Erro: `socket timeout calling hello on ...:27017` / `ConnectionTimeoutException`
- **Causa 1:** O seu IP não foi liberado no MongoDB Atlas. Adicione `0.0.0.0/0` em **Security > Network Access**.
- **Causa 2:** Você está conectado no Wi-Fi de uma universidade, escola ou empresa que bloqueia a porta de saída `27017`.
- **Solução:** Roteie o 4G/5G do seu celular para o computador ou utilize uma VPN (como o [Cloudflare WARP 1.1.1.1](https://1.1.1.1/)) para encapsular o tráfego pela porta 443.

### ❌ Erro: ESP32 mostra `HTTP Response code: -1` ou não conecta
- **Causa 1:** O ESP32 e o computador estão em redes Wi-Fi diferentes.
- **Causa 2:** O Firewall do Windows bloqueou as conexões de entrada na porta `8000`.
- **Solução:** Abra o *Firewall do Windows com Segurança Avançada* > *Regras de Entrada* > *Nova Regra* > liberar a porta TCP `8000`.

### ❌ Erro: O modelo 3D da vaca não aparece
- **Causa:** O arquivo do modelo 3D não está presente na pasta pública.
- **Solução:** Verifique se o arquivo `vaca_dashboard.glb` está localizado em `public/models/vaca_dashboard.glb`.

