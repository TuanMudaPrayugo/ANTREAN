# ANTREAN

Aplikasi Laravel untuk manajemen antrian dan layanan.

---

## 🚀 Requirements
- PHP >= 8.1
- Composer
- Node.js & NPM
- MySQL/MariaDB
- Git
- [Ollama](https://ollama.ai) (untuk AI lokal, opsional)

---

## ⚙️ Installation

1. Clone repository:
   ```bash
   git clone https://github.com/TuanMudaPrayugo/ANTREAN.git
   cd ANTREAN

2. Install dependencies:
    composer install
    npm install
    
3. Copy .env.example ke .env:
    cp .env.example .env
    lalu sesuaikan isi .env.

4. Generate key Laravel:
    php artisan key:generate

5. Migrasi database:
    php artisan migrate

Jalankan server:
    php artisan serve

Compile asset frontend:
npm run dev

🗄️ Setup Database MySQL
1. Buat database baru:
    sql

    CREATE DATABASE antrean CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci;

2. Edit .env:
    DB_CONNECTION=mysql
    DB_HOST=127.0.0.1
    DB_PORT=3306
    DB_DATABASE=antrean
    DB_USERNAME=root
    DB_PASSWORD=

3. Migrasi:
    php artisan migrate


🤖 AI & NLP Integration (Opsional)
1. Install Ollama
Jalankan perintah berikut untuk install Ollama di Windows:
    winget install Ollama.Ollama

2. Pull model Qwen2
    ollama pull qwen2:1.5b-instruct

3. Menjalankan Ollama
    ollama run qwen2:1.5b-instruct

4. Test API Ollama
    curl http://127.0.0.1:11434/api/tags

5. Install NLP Bahasa Indonesia (Sastrawi)
    composer require sastrawi/sastrawi

6. Konfigurasi .env
Tambahkan di file .env:

# LLM lokal (Ollama)
LLM_ENDPOINT=http://127.0.0.1:11434/api/chat
LLM_MODEL=qwen2:1.5b-instruct
USE_LLM=true

Dengan ini aplikasi Laravel kamu bisa menghubungkan fitur Chat AI ke Ollama lokal.
Sastrawi bisa dipakai untuk stemming/analisis teks bahasa Indonesia sebelum/atau setelah diproses oleh LLM.