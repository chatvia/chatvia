# ChatVia Docker Setup

PostgreSQL 18 with vector search extensions for ChatVia development.

## 🚀 Quick Start

```bash
cd docker

# Start PostgreSQL
docker-compose up -d

# View logs
docker-compose logs -f

# Stop
docker-compose down

# Stop and remove all data (fresh start)
docker-compose down -v
```

## 📊 Default Credentials

- **Database**: `chatvia`
- **User**: `chatvia`
- **Password**: `password`
- **Port**: `5432`

## 🔌 Laravel Connection

Update your `.env` file:

```env
DB_CONNECTION=pgsql
DB_HOST=localhost
DB_PORT=5432
DB_DATABASE=chatvia
DB_USERNAME=chatvia
DB_PASSWORD=password
```

## ✅ Verify Installation

```bash
# Check if container is running
docker ps

# Check extensions
docker exec -it chatvia-postgres psql -U chatvia -d chatvia -c "\dx"

# Test connection
docker exec -it chatvia-postgres psql -U chatvia -d chatvia -c "SELECT version();"
```

## 📦 Installed Extensions

- ✅ **pgvector** (0.8.1) - Vector similarity search
- ✅ **VectorChord** (1.0.0) - High-performance vector index
- ✅ **pg_tokenizer** (0.1.1) - Text tokenization
- ✅ **VectorChord-bm25** (0.3.0) - BM25 text search
- ✅ **pg_trgm** - Trigram matching
- ✅ **btree_gin**, **btree_gist** - Advanced indexing
- ✅ **pg_stat_statements** - Query statistics

## 🗄️ Create Additional Databases

```bash
# For Laravel Pulse
docker exec -it chatvia-postgres psql -U chatvia -d chatvia -c "CREATE DATABASE pulse;"

# For Laravel Telescope
docker exec -it chatvia-postgres psql -U chatvia -d chatvia -c "CREATE DATABASE telescope;"
```

## 🔧 Troubleshooting

### Rebuild Container

```bash
docker-compose down
docker-compose build --no-cache
docker-compose up -d
```

### Access PostgreSQL Shell

```bash
docker exec -it chatvia-postgres psql -U chatvia -d chatvia
```

### View Logs

```bash
docker-compose logs -f postgres
```

### Complete Reset

```bash
# Remove everything and start fresh
docker-compose down -v
docker-compose up -d
```

## 📂 Files

- `Dockerfile` - PostgreSQL 18 image with extensions
- `docker-compose.yml` - Service orchestration
- `postgresql.conf` - Optimized PostgreSQL settings
- `init-extensions.sql` - Auto-install extensions on first run
- `.dockerignore` - Exclude unnecessary files from build

## 🔐 Security Note

⚠️ **Default credentials are for development only!**

For production deployment, change the password and use proper secrets management.

---

That's it! Your PostgreSQL with vector search is ready to use. 🎉

