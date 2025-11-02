# ChatVia

<p align="center">
  <img src="public/images/logo.svg" alt="ChatVia Logo" width="120" height="120">
</p>

<p align="center">
  <strong>Create AI agents powered by your documents</strong>
</p>

<p align="center">
  <a href="https://laravel.com"><img src="https://img.shields.io/badge/Laravel-12.x-red.svg" alt="Laravel Version"></a>
  <a href="https://www.php.net"><img src="https://img.shields.io/badge/PHP-8.4+-blue.svg" alt="PHP Version"></a>
  <a href="https://vuejs.org"><img src="https://img.shields.io/badge/Vue.js-3.x-green.svg" alt="Vue.js Version"></a>
  <a href="https://inertiajs.com"><img src="https://img.shields.io/badge/Inertia.js-2.x-purple.svg" alt="Inertia.js Version"></a>
  <a href="https://tailwindcss.com"><img src="https://img.shields.io/badge/Tailwind_CSS-4.x-cyan.svg" alt="Tailwind CSS Version"></a>
</p>

---

## 🚀 Overview

ChatVia is a sophisticated RAG (Retrieval-Augmented Generation) application that enables users to create AI agents by uploading documents and leveraging advanced AI technology. The platform processes documents, creates intelligent embeddings, and provides contextual responses through chat interfaces powered by multiple AI providers.

### ✨ Key Features

- **🤖 AI Agent Creation**: Create widget or knowledge-based AI agents
- **📄 Document Ingestion**: Support for multiple file formats with intelligent parsing
- **🔍 Advanced RAG**: Sophisticated retrieval-augmented generation with vector embeddings
- **💬 Real-time Chat**: Streaming chat interface with SSE (Server-Sent Events)
- **🎨 Customizable Appearance**: Brand your agents with custom themes and styling
- **📊 Analytics & Usage Tracking**: Comprehensive usage statistics and monitoring
- **🔐 Multi-tenancy**: Support for teams and member management
- **💳 Billing Integration**: Stripe integration with subscription management
- **🔌 API Access**: RESTful API for programmatic integration
- **📱 Responsive Design**: Mobile-first responsive interface

## 🛠 Tech Stack

### Backend
- **Framework**: Laravel 12.x with PHP 8.4+
- **Database**: PostgreSQL with pgvector extension
- **Queue System**: Laravel Horizon with Redis
- **Storage**: AWS S3 compatible storage
- **Authentication**: Laravel Sanctum
- **Payments**: Laravel Cashier (Stripe)
- **Admin Panel**: Filament 3.x

### Frontend
- **Framework**: Vue.js 3 with TypeScript
- **Bridge**: Inertia.js 2.x
- **Styling**: Tailwind CSS 4.x with shadcn/ui components
- **Build Tool**: Vite
- **State Management**: Pinia (via composables)
- **Icons**: Heroicons & Lucide

### AI & Machine Learning
- **LLM Infrastructure**: OpenAI, Cohere, Mistral AI, Google AI, Groq
- **Document Parsing**: LlamaParse, Azure Document Intelligence
- **Vector Database**: pgvector (PostgreSQL extension)
- **Text Processing**: Advanced chunking and embedding strategies

### DevOps & Deployment
- **Server**: Laravel Octane (high-performance)
- **Monitoring**: Laravel Telescope, Laravel Pulse
- **Error Tracking**: Sentry
- **Email**: Resend
- **Search**: Elasticsearch (optional)

## 📋 Requirements

- **PHP**: 8.4 or higher
- **Node.js**: 18.x or higher
- **PostgreSQL**: 14+ with pgvector extension
- **Redis**: 6.x or higher
- **Composer**: 2.x
- **NPM/Yarn**: Latest stable

### Required Extensions
- `ext-fileinfo`
- `ext-pgsql`
- `ext-redis`
- `ext-gd` or `ext-imagick`

## 🚀 Installation

### 1. Clone the Repository
```bash
git clone https://github.com/yourusername/chatvia.git
cd chatvia
```

### 2. Install Dependencies
```bash
# Install PHP dependencies
composer install

# Install Node.js dependencies
npm install
```

### 3. Environment Configuration
```bash
# Copy environment file
cp .env.example .env

# Generate application key
php artisan key:generate
```

### 4. Configure Environment Variables
Edit your `.env` file with the following essential configurations:

```env
# Application
APP_NAME="ChatVia"
APP_ENV=local
APP_DEBUG=true
APP_URL=http://localhost

# Database
DB_CONNECTION=pgsql
DB_HOST=127.0.0.1
DB_PORT=5432
DB_DATABASE=chatvia
DB_USERNAME=your_username
DB_PASSWORD=your_password

# Redis
REDIS_HOST=127.0.0.1
REDIS_PASSWORD=null
REDIS_PORT=6379

# AI Infrastructure (configure as needed)
OPENAI_API_KEY=your_openai_key
COHERE_API_KEY=your_cohere_key
GROQ_API_KEY=your_groq_key

# Storage (AWS S3 or compatible)
AWS_ACCESS_KEY_ID=your_access_key
AWS_SECRET_ACCESS_KEY=your_secret_key
AWS_DEFAULT_REGION=us-east-1
AWS_BUCKET=your_bucket_name

# Stripe (for billing)
STRIPE_KEY=your_stripe_key
STRIPE_SECRET=your_stripe_secret
```

### 5. Database Setup
```bash
# Run migrations
php artisan migrate

# Seed the database (optional)
php artisan db:seed
```

### 6. Storage Setup
```bash
# Create storage link
php artisan storage:link

# Create required directories
mkdir -p storage/app/public/uploads
```

### 7. Build Assets
```bash
# Development build
npm run dev

# Production build
npm run build
```

### 8. Start Development Server
```bash
# Using the convenient dev script
composer run dev

# Or manually start services
php artisan serve
php artisan queue:work
npm run dev
```

## 📁 Project Structure

```
chatvia/
├── app/
│   ├── Actions/              # Domain actions
│   ├── Console/Commands/     # Artisan commands
│   ├── Data/                 # Data transfer objects
│   ├── Enums/                # Application enums
│   ├── Filament/             # Admin panel resources
│   ├── Http/
│   │   ├── Controllers/      # HTTP controllers
│   │   ├── Middleware/       # Custom middleware
│   │   └── Requests/         # Form requests
│   ├── Jobs/                 # Queue jobs
│   ├── Models/               # Eloquent models
│   ├── Policies/             # Authorization policies
│   ├── Infrastructure/            # Service providers
│   ├── Services/             # Business logic services
│   └── Support/              # Helper classes
├── database/
│   ├── factories/            # Model factories
│   ├── migrations/           # Database migrations
│   └── seeders/              # Database seeders
├── resources/
│   ├── css/                  # Stylesheets
│   ├── js/
│   │   ├── components/       # Vue components
│   │   ├── composables/      # Vue composables
│   │   ├── layouts/          # Page layouts
│   │   ├── pages/            # Inertia pages
│   │   └── types/            # TypeScript definitions
│   └── views/                # Blade templates
├── routes/                   # Route definitions
├── tests/                    # Test files
└── public/                   # Public assets
```

## 🔧 Configuration

### AI Infrastructure
Configure your preferred AI providers in the `.env` file:

```env
# OpenAI
OPENAI_API_KEY=sk-...
OPENAI_ORGANIZATION=org-...

# Cohere
COHERE_API_KEY=...

# Groq
GROQ_API_KEY=gsk_...

# Google AI
GOOGLE_AI_API_KEY=...
```

### Document Processing
ChatVia supports various document formats:

- **Text Documents**: PDF, DOC, DOCX, TXT, RTF, HTML
- **Spreadsheets**: XLS, XLSX, CSV, ODS
- **Presentations**: PPT, PPTX, ODP
- **Images**: JPG, PNG, GIF, BMP, SVG, TIFF, WEBP
- **Audio**: MP3, WAV, MP4, M4A, WEBM (up to 20MB)
- **Video**: MP4, AVI, MOV, WMV

### Vector Database
Ensure PostgreSQL has the pgvector extension installed:

```sql
CREATE EXTENSION IF NOT EXISTS vector;
```

## 🎯 Usage

### Creating an Agent

1. **Navigate to Agents**: Go to the agents section in your dashboard
2. **Choose Type**: Select between widget or knowledge-based agents
3. **Upload Documents**: For knowledge-based agents, upload your files
4. **Configure Settings**: Set system instructions, appearance, and capabilities
5. **Deploy**: Your agent will be available at `your-subdomain.chatvia.ai`

### Chat Interface

- **Real-time Streaming**: Responses stream in real-time using SSE
- **Context Awareness**: Agents remember conversation history
- **Source Citations**: Document-based agents show source references
- **Multi-language Support**: Automatic language detection and support

### API Usage

```bash
# Example API call
curl -X POST https://api.chatvia.ai/v1/chat \
  -H "Authorization: Bearer YOUR_API_KEY" \
  -H "Content-Type: application/json" \
  -d '{
    "agent_id": "your-agent-id",
    "message": "Hello, how can you help me?",
    "stream": true
  }'
```

## 🧪 Testing

```bash
# Run all tests
php artisan test

# Run specific test suite
php artisan test --testsuite=Feature

# Run with coverage
php artisan test --coverage
```

## 🚀 Deployment

### Production Setup

1. **Environment**: Set `APP_ENV=production` and `APP_DEBUG=false`
2. **Optimize**: Run optimization commands
   ```bash
   php artisan config:cache
   php artisan route:cache
   php artisan view:cache
   php artisan filament:optimize
   ```
3. **Assets**: Build production assets
   ```bash
   npm run build
   ```
4. **Queue Workers**: Set up supervisor for queue workers
5. **Scheduler**: Add Laravel scheduler to cron
   ```bash
   * * * * * cd /path-to-your-project && php artisan schedule:run >> /dev/null 2>&1
   ```

### Docker Support
```bash
# Build and run with Docker
docker-compose up -d
```

## 📊 Monitoring & Analytics

- **Application Monitoring**: Laravel Telescope for debugging
- **Performance Monitoring**: Laravel Pulse for metrics
- **Error Tracking**: Sentry integration
- **Usage Analytics**: Built-in usage tracking and reporting

## 🔒 Security

- **Authentication**: Multi-factor authentication support
- **Authorization**: Role-based access control
- **Data Protection**: Encryption at rest and in transit
- **API Security**: Rate limiting and API key management
- **File Upload Security**: Comprehensive file validation and scanning

## 🤝 Contributing

We welcome contributions! Please see our [Contributing Guide](CONTRIBUTING.md) for details.

### Development Workflow

1. Fork the repository
2. Create a feature branch
3. Make your changes
4. Add tests for new functionality
5. Ensure all tests pass
6. Submit a pull request

### Code Standards

- **PHP**: Follow PSR-12 coding standards
- **JavaScript/TypeScript**: Use ESLint and Prettier
- **Vue.js**: Follow Vue.js style guide
- **Commits**: Use conventional commit messages

## 📝 License

This project is licensed under the [MIT License](LICENSE).

## 🆘 Support

- **Documentation**: [docs.chatvia.ai](https://docs.chatvia.ai)
- **Community**: [Discord](https://discord.gg/chatvia)
- **Issues**: [GitHub Issues](https://github.com/yourusername/chatvia/issues)
- **Email**: support@chatvia.ai

## 🙏 Acknowledgments

- **Laravel Team**: For the amazing framework
- **Vue.js Team**: For the reactive frontend framework
- **Inertia.js**: For seamless SPA experience
- **Filament**: For the beautiful admin panel
- **Tailwind CSS**: For the utility-first CSS framework
- **All Contributors**: Thank you for making ChatVia better!

---

<p align="center">
  Made with ❤️ by the ChatVia Team
</p>
