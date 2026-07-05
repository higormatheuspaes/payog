#!/bin/bash
# Setup inicial do servidor Oracle Cloud (Ubuntu 22.04 / 24.04 ARM)
# Execute como root: sudo bash server-setup.sh
set -e

echo "═══════════════════════════════════════"
echo "  Setup Payog — Oracle Cloud"
echo "═══════════════════════════════════════"

# ── 1. Atualiza sistema ────────────────────────────────────────────────────
echo "▶ Atualizando sistema..."
apt-get update -qq && apt-get upgrade -y -qq

# ── 2. Instala Docker ──────────────────────────────────────────────────────
echo "▶ Instalando Docker..."
curl -fsSL https://get.docker.com | sh
systemctl enable docker
systemctl start docker

# Adiciona o usuário ubuntu ao grupo docker (sem precisar de sudo)
usermod -aG docker ubuntu

# ── 3. Instala Git ─────────────────────────────────────────────────────────
echo "▶ Instalando Git..."
apt-get install -y -qq git

# ── 4. Abre portas no firewall (Oracle Cloud bloqueia por padrão) ──────────
echo "▶ Abrindo portas 80 e 443..."
apt-get install -y -qq iptables-persistent

iptables -I INPUT 6 -m state --state NEW -p tcp --dport 80  -j ACCEPT
iptables -I INPUT 6 -m state --state NEW -p tcp --dport 443 -j ACCEPT
# HTTP/3 (UDP 443)
iptables -I INPUT 6 -m state --state NEW -p udp --dport 443 -j ACCEPT

netfilter-persistent save

# ── 5. Clona o repositório ─────────────────────────────────────────────────
echo "▶ Clonando repositório..."
mkdir -p /opt/payog
cd /opt/payog

# Substitua pela URL do seu repositório
REPO_URL="${REPO_URL:-https://github.com/SEU_USUARIO/hmpay.git}"
git clone "$REPO_URL" . || git pull origin main

chown -R ubuntu:ubuntu /opt/payog

# ── 6. Cria .env de produção ───────────────────────────────────────────────
echo ""
echo "═══════════════════════════════════════"
echo "  ⚠️  AÇÃO MANUAL NECESSÁRIA"
echo "═══════════════════════════════════════"
echo ""
echo "Crie o arquivo de ambiente de produção:"
echo ""
echo "  nano /opt/payog/src/.env"
echo ""
echo "Use como base: /opt/payog/src/.env.example"
echo "Variáveis obrigatórias:"
echo "  APP_KEY           → gere com: php artisan key:generate --show"
echo "  APP_URL           → https://seudominio.com"
echo "  DB_PASSWORD       → senha forte"
echo "  DB_ROOT_PASSWORD  → senha forte diferente"
echo "  REDIS_PASSWORD    → senha forte"
echo "  ABACATEPAY_API_KEY"
echo "  ABACATEPAY_WEBHOOK_SECRET"
echo "  TWILIO_SID / TWILIO_TOKEN / TWILIO_WHATSAPP_FROM"
echo "  MAIL_*            → credenciais SMTP de produção"
echo "  CADDY_EMAIL       → e-mail para certificado Let's Encrypt"
echo "  APP_DOMAIN        → seudominio.com (sem https://)"
echo ""
echo "Depois de criar o .env, execute:"
echo ""
echo "  cd /opt/payog"
echo "  docker compose -f docker-compose.prod.yml up -d"
echo "  docker compose -f docker-compose.prod.yml exec app php artisan key:generate"
echo "  docker compose -f docker-compose.prod.yml exec app php artisan migrate --force"
echo "  docker compose -f docker-compose.prod.yml exec app php artisan optimize"
echo "  docker compose -f docker-compose.prod.yml exec app php artisan storage:link"
echo ""
echo "✅ Servidor pronto!"
