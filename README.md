## E-Services Laravel Application

Containerized Laravel + Node (Vite) + MariaDB stack with queue and Reverb (websocket) services.

---

### 1. Prerequisites
- Docker (Compose v2)


### 2. Create Application `.env`
```bash
cp .env.example .env   # if example exists, else create manually
```
Core values (must align with docker services):
```ini
DB_CONNECTION=mysql
DB_HOST=your_host
DB_PORT=your_port
DB_DATABASE=your_database
DB_USERNAME=root
DB_PASSWORD=your_password
...
COMPOSE_PROJECT_NAME=your_stack_preferred_name
```

### 3. Custom VirtualHost
```bash
cp docker/apache/vhost.conf.example docker/apache/vhost.conf
```
Edit `docker/apache/vhost.conf` (ignored by Git).

### 4. Review `docker/docker-compose.example.yml` and create your own
```bash
cp docker/docker-compose.example.yml docker/docker-compose.yml
```
Services:
- `database` (MariaDB)
- `application` (Apache + PHP)
- `queue-worker` (artisan queue:work)
- `reverb-server` (websocket)

Common adjustments:
- Ports: `8000:80`, `8080:8080`
- Volumes if you change `APP_WORKDIR`
- Queue worker tries / timeout

### 5. Build & Start
```bash
docker compose up -d --build
```

### 6. Import Database (if you have a backup)
```bash
# Example: dump.sql in project root
type dump.sql | docker exec -i dgu-db mariadb -u root -p%DB_PASSWORD% e-services-2
```
(Use `cat` instead of `type` on Linux/macOS.)
- Restart the Containers

### 7. Access the App
- Web: http://localhost:host_port