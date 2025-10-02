## E-Services Laravel Application

Containerized Laravel + Node (Vite) + MariaDB stack with queue and Reverb (websocket) services.

---

### 1. Prerequisites
- Docker (Compose v2)
- Git
- Bash (for bootstrap script)

### 2. Clone the Repository
```bash
git clone https://github.com/ktsouvalis/e-services-uop.git dgu-services
cd dgu-services
```

### 3. Create Application `.env`
```bash
cp .env.example .env   # if example exists, else create manually
```
Core values (must align with docker services):
```ini
DB_CONNECTION=mysql
DB_HOST=dgu-db
DB_PORT=3306
DB_DATABASE=e-services-2
DB_USERNAME=root
DB_PASSWORD=your_password
```

### 4. Create Docker-Specific Env File `docker/.env`
Used only for Docker/Compose variable interpolation (not read by Laravel internally).
```ini
DB_PASSWORD=your_password
```

### 5. Custom VirtualHost
```bash
cp docker/apache/vhost.conf.example docker/apache/vhost.conf
```
Edit `docker/apache/vhost.conf` (ignored by Git).

### 6. Review `docker/docker-compose.yml`
Services:
- `database` (MariaDB)
- `application` (Apache + PHP)
- `queue-worker` (artisan queue:work)
- `reverb-server` (websocket)
- `assets` (one-off Vite build)

Common adjustments:
- Ports: `8000:80`, `8080:8080`
- Volumes if you change `APP_WORKDIR`
- Queue worker tries / timeout

### 7. Build & Start
```bash
docker compose -f docker/docker-compose.yml up -d --build
```

### 8. (First-Time Optional) Run Bootstrap
```bash
docker exec -it dgu-app bash -lc 'bash docker/scripts/artisan_bootstrap.sh'
```
What it does:
- Clears & rebuilds caches (config/route/view/event)


Re-run if caches are stale or after env changes.

### 9. Import Database (if database volume is brand new and starting from a dump)
```bash
# Example: dump.sql in project root
type dump.sql | docker exec -i dgu-db mariadb -u root -p%DB_PASSWORD% e-services-2
```
(Use `cat` instead of `type` on Linux/macOS.)

### 10. Access the App
- Web: http://localhost:8000

### 11. Rebuild Frontend Assets
On demand:
```bash
docker compose -f docker/docker-compose.yml run --rm assets
```

### 12. Update Dependencies
Composer:
```bash
docker exec -it dgu-app bash -lc 'composer install --no-dev --optimize-autoloader'
```
Frontend (clean + build):
```bash
docker compose -f docker/docker-compose.yml run --rm assets npm ci
docker compose -f docker/docker-compose.yml run --rm assets npm run build
```
