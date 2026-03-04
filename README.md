# Kwekwe Polytechnic Intelligent Chatbot

A comprehensive AI-powered chatbot system for Kwekwe Polytechnic in Zimbabwe, providing accurate information about courses, fees, admissions, and institutional services using Retrieval-Augmented Generation (RAG).

## 🏗️ Architecture

```
┌─────────────────┐     ┌──────────────────┐     ┌─────────────────┐
│   React Web     │────▶│   FastAPI        │────▶│   Vector DB     │
│   Widget        │     │   Python Backend │     │   (ChromaDB)    │
└─────────────────┘     │   (RAG Engine)   │     └─────────────────┘
                        │                   │            │
┌─────────────────┐     │   - LlamaIndex    │     ┌─────▼─────────┐
│   WhatsApp      │────▶│   - LangChain     │────▶│   LLM API      │
│   Integration   │     │   - Async/Await   │     │   (OpenAI/Local│
└─────────────────┘     └──────────────────┘     └─────────────────┘
```

## 🚀 Features

### Core Functionality
- **RAG-powered responses** grounded in verified institutional data
- **Multi-channel support**: Web widget and WhatsApp Business Platform
- **Session management** for multi-turn conversations
- **Live data integration** with existing PHP systems
- **Multi-currency support** (USD and ZiG)

### Technical Features
- **Self-hosted vector database** (ChromaDB) for data sovereignty
- **Async/await architecture** for high concurrency
- **Rate limiting and security** middleware
- **Responsive design** with institutional branding
- **Docker deployment** ready

## 📋 Prerequisites

- Python 3.11+
- Node.js 18+
- Redis (for session management)
- Docker & Docker Compose (optional)

## 🛠️ Installation

### Backend Setup

1. **Clone the repository**
```bash
git clone <repository-url>
cd kwekwe-chatbot
```

2. **Create virtual environment**
```bash
python -m venv venv
source venv/bin/activate  # On Windows: venv\Scripts\activate
```

3. **Install dependencies**
```bash
pip install -r requirements.txt
```

4. **Configure environment**
```bash
cp .env.example .env
# Edit .env with your configuration
```

5. **Start the backend**
```bash
python main.py
```

### Frontend Setup

1. **Navigate to frontend directory**
```bash
cd frontend
```

2. **Install dependencies**
```bash
npm install
```

3. **Start development server**
```bash
npm run dev
```

### Docker Deployment

1. **Build and start all services**
```bash
cd docker
docker-compose up -d
```

## ⚙️ Configuration

### Environment Variables

Key environment variables in `.env`:

```env
# API Configuration
DEBUG=true
HOST=0.0.0.0
PORT=8000

# LLM Configuration
OPENAI_API_KEY=your-openai-api-key
OPENAI_MODEL=gpt-3.5-turbo

# WhatsApp Business API
WHATSAPP_PHONE_NUMBER_ID=your-phone-number-id
WHATSAPP_ACCESS_TOKEN=your-whatsapp-access-token
WHATSAPP_WEBHOOK_VERIFY_TOKEN=your-webhook-verify-token

# Vector Database
CHROMA_DB_PATH=./data/vector_store
EMBEDDING_MODEL=all-MiniLM-L6-v2

# Redis
REDIS_URL=redis://localhost:6379
```

## 📚 Knowledge Base

The system handles information about:

### Academic Programs
- **Engineering Division**: Automotive, Electrical, Mechanical
  - Requirements: Math, English, Science at 'O' Level
  - Department heads: Mr. Gunda (Engineering), Mr. Mutiza (Automotive), Mr. Sibanda (Electrical), Mr. Mundandi (Mechanical)

- **Commerce Division**: Management, Business
  - Requirements: English only (Management), Math & English (Business)
  - Department heads: Mr. T. Sambama (Management), Mr. A. Vuma (Business)

### Administrative Data
- Fee structures (USD and ZiG currencies)
- Payment methods: Paynow, Ecocash, OneMoney, Bank transfers
- HEXCO results and examination information
- ICT Unit services (Mrs. R. Mahachi)

## 🔌 API Endpoints

### Chat API
- `POST /api/v1/chat/query` - Send chat message
- `GET /api/v1/chat/session/{session_id}` - Get session info
- `GET /api/v1/chat/health` - Health check

### WhatsApp Webhooks
- `GET /api/v1/webhooks/whatsapp/verify` - Verify webhook
- `POST /api/v1/webhooks/whatsapp/message` - Receive messages

## 🎨 Frontend Integration

### Embed Script
Add this to any HTML page to embed the chatbot:

```html
<script src="https://your-domain.com/embed.js" 
        data-api-url="https://your-api.com"
        data-position="bottom-right">
</script>
```

### React Component
```jsx
import { ChatWidget } from './components/ChatWidget'

<ChatWidget 
  isOpen={isOpen}
  onToggle={() => setIsOpen(!isOpen)}
  position="bottom-right"
/>
```

## 🧪 Testing

Run the test suite:

```bash
# Backend tests
pytest tests/

# Frontend tests
cd frontend && npm test
```

## 🔒 Security Features

- **Rate limiting** to prevent abuse
- **Input validation** and sanitization
- **Session management** with Redis
- **HTTPS support** with Nginx
- **CORS configuration** for cross-origin requests
- **Audit logging** for compliance

## 📊 Monitoring

### Health Checks
- Backend: `GET /api/v1/chat/health`
- Vector store statistics
- Session management metrics

### Logging
- Application logs: `./logs/app.log`
- Audit logs: `./logs/app_audit.log`
- Structured logging with timestamps

## 🚀 Deployment

### Production Deployment

1. **Environment setup**
```bash
export DEBUG=false
export LOG_LEVEL=INFO
```

2. **Docker deployment**
```bash
docker-compose -f docker/docker-compose.yml up -d
```

3. **SSL Configuration**
- Update `docker/nginx.conf` with SSL certificates
- Configure HTTPS redirects

### Scaling Considerations

- **Horizontal scaling**: Multiple backend instances behind load balancer
- **Database scaling**: Redis cluster for session management
- **CDN**: Frontend assets served via CDN
- **Monitoring**: Prometheus + Grafana for metrics

## 🤝 Contributing

1. Fork the repository
2. Create a feature branch
3. Make your changes
4. Add tests for new functionality
5. Submit a pull request

## 📄 License

This project is licensed under the MIT License - see the LICENSE file for details.

## 📞 Support

For support and questions:
- **Technical Issues**: Create an issue in the repository
- **Institutional Questions**: Contact Kwekwe Polytechnic ICT Unit
- **WhatsApp Support**: Available through the chatbot

## 🗺️ Roadmap

- [ ] Advanced analytics dashboard
- [ ] Multi-language support (Shona, Ndebele)
- [ ] Voice integration
- [ ] Mobile app development
- [ ] Advanced AI features (sentiment analysis, proactive assistance)

---

**Built with ❤️ for Kwekwe Polytechnic**
