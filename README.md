# Framee

**Framee** is a modern photo kiosk application built with Laravel 12 and Vue.js that allows users to take photos, select decorative frames, and process payments through an intuitive touch-screen interface.

## 🚀 Features

### Core Functionality
- **Photo Capture**: Multi-photo capture with live camera feed
- **Frame Selection**: Choose from a variety of decorative borders and frames
- **Payment Processing**: Integrated payment gateway (Midtrans, Xendit) with QR code generation
- **Session Management**: Unique session codes for tracking photo sessions
- **Real-time Preview**: Live preview of photos with applied frames
- **Download & Print**: QR code-based photo delivery system

### Technical Features
- **Responsive Kiosk Interface**: Touch-optimized UI for kiosk environments
- **Image Processing**: Server-side image composition with borders using Intervention Image
- **Queue System**: Background processing for image composition tasks
- **Role-based Permissions**: Admin panel with Spatie Laravel Permission
- **RESTful API**: Clean API structure for frontend-backend communication
- **Session Persistence**: Database-driven session management with expiration

## 🛠️ Tech Stack

### Backend
- **Laravel 12** with PHP 8.2+
- **MySQL** database
- **Laravel Sanctum** for API authentication
- **Spatie Laravel Permission** for role management
- **Intervention Image** for image processing
- **Simple QRCode** for QR code generation
- **Queue workers** for background jobs

### Frontend
- **Vue.js 3** with Composition API
- **Inertia.js** for SPA experience
- **TailwindCSS v4** for styling
- **Pinia** for state management
- **Vite** for asset bundling

### Development Tools
- **Pest** for testing
- **Laravel Pint** for code formatting
- **Laravel Pail** for real-time logs
- **Concurrently** for running multiple dev processes

## 📋 Requirements

- PHP 8.2 or higher
- Composer
- Node.js 18+ and npm
- MySQL 5.7+ or 8.0+
- Camera access for photo capture
- Modern web browser with WebRTC support

## 🚀 Installation

### 1. Clone the Repository
```bash
git clone <repository-url>
cd photobox
```

### 2. Install Dependencies
```bash
# Install PHP dependencies
composer install

# Install Node.js dependencies
npm install
```

### 3. Environment Setup
```bash
# Copy environment file
cp .env.example .env

# Generate application key
php artisan key:generate
```

### 4. Database Configuration
Update your `.env` file with database credentials:
```env
DB_CONNECTION=mysql
DB_HOST=127.0.0.1
DB_PORT=3306
DB_DATABASE=photobox
DB_USERNAME=your_username
DB_PASSWORD=your_password
```

### 5. Database Migration & Seeding
```bash
# Run migrations
php artisan migrate

# Seed the database with sample data
php artisan db:seed
```

### 6. Storage Setup
```bash
# Create storage link
php artisan storage:link

# Set proper permissions
chmod -R 755 storage bootstrap/cache
```

### 7. Payment Gateway Configuration (Optional)
Configure payment gateways in your `.env`:
```env
# For Midtrans
MIDTRANS_SERVER_KEY=your_server_key
MIDTRANS_CLIENT_KEY=your_client_key
MIDTRANS_IS_PRODUCTION=false

# For Xendit
XENDIT_SECRET_KEY=your_secret_key
XENDIT_PUBLIC_KEY=your_public_key
```

## 🏃‍♂️ Running the Application

### Development Mode
The project includes a convenient development script that runs all necessary services:
```bash
composer dev
```

This command starts:
- Laravel development server (http://localhost:8000)
- Queue worker for background jobs
- Real-time log viewer (Laravel Pail)
- Vite development server for hot reloading

### Individual Commands
If you prefer to run services separately:
```bash
# Laravel server
php artisan serve

# Frontend development
npm run dev

# Queue worker
php artisan queue:work

# Real-time logs
php artisan pail
```

### Production Build
```bash
# Build frontend assets
npm run build

# Optimize Laravel
php artisan config:cache
php artisan route:cache
php artisan view:cache
```

## 🧪 Testing

Run the test suite using Pest:
```bash
# Run all tests
composer test
# or
php artisan test

# Run specific test
php artisan test --filter="test_name"

# Run tests with coverage
php artisan test --coverage
```

## 📝 Code Quality

The project uses Laravel Pint for code formatting:
```bash
# Format code
vendor/bin/pint

# Check formatting without fixing
vendor/bin/pint --test
```

## 🏗️ Project Structure

```
photobox/
├── app/
│   ├── Console/Commands/     # Artisan commands
│   ├── Http/
│   │   ├── Controllers/      # API and web controllers
│   │   ├── Middleware/       # Custom middleware
│   │   └── Requests/         # Form request validation
│   ├── Jobs/                 # Queue jobs (image processing)
│   ├── Models/               # Eloquent models
│   └── Services/             # Business logic services
├── database/
│   ├── migrations/           # Database schema
│   └── seeders/              # Sample data
├── resources/
│   ├── js/
│   │   ├── Components/       # Vue.js components
│   │   ├── Layouts/          # Page layouts
│   │   ├── Pages/            # Inertia.js pages
│   │   └── stores/           # Pinia stores
│   └── css/                  # Stylesheets
├── routes/
│   ├── web.php               # Web routes
│   ├── api.php               # API routes
│   └── auth.php              # Authentication routes
└── tests/                    # Pest tests
```

## 🔧 Configuration

### Camera Settings
Configure camera preferences in the frontend:
```javascript
// resources/js/Components/Camera/CameraView.vue
const cameraConstraints = {
  video: {
    width: { ideal: 1920 },
    height: { ideal: 1080 },
    facingMode: 'user'
  }
}
```

### Session Management
Configure session timeout in your model:
```php
// app/Models/PhotoSession.php
public const SESSION_TIMEOUT_MINUTES = 30;
```

### Image Processing
Customize image processing settings:
```php
// config/services.php
'image_processing' => [
    'max_width' => 1920,
    'max_height' => 1080,
    'quality' => 90,
    'format' => 'jpg'
]
```

## 📚 API Documentation

### Key Endpoints

#### Session Management
```http
POST /api/v1/sessions          # Create new session
GET /api/v1/sessions/{code}    # Get session by code
PATCH /api/v1/sessions/{id}    # Update session status
```

#### Photo Operations
```http
POST /api/v1/photos           # Upload photo
GET /api/v1/photos/{id}       # Get photo details
POST /api/v1/photos/compose   # Compose photo with border
```

#### Payment Processing
```http
POST /api/v1/payments         # Create payment
GET /api/v1/payments/{id}     # Get payment status
POST /api/v1/payments/webhook # Payment webhook
```

## 🔒 Security Features

- **CSRF Protection**: Built-in Laravel CSRF protection
- **Input Validation**: Comprehensive request validation
- **File Upload Security**: Secure file handling with type validation
- **Session Security**: Secure session management with expiration
- **Payment Security**: PCI-compliant payment processing

## 🚀 Deployment

### Environment Setup
1. Set `APP_ENV=production` in `.env`
2. Configure production database credentials
3. Set up proper file permissions
4. Configure web server (Nginx/Apache)
5. Set up SSL certificates
6. Configure payment gateway production keys

### Performance Optimization
```bash
# Cache configuration
php artisan config:cache
php artisan route:cache
php artisan view:cache

# Optimize autoloader
composer install --optimize-autoloader --no-dev

# Queue workers
php artisan queue:restart
```

## 🤝 Contributing

1. Fork the repository
2. Create a feature branch (`git checkout -b feature/amazing-feature`)
3. Commit your changes (`git commit -m 'Add amazing feature'`)
4. Run tests (`composer test`)
5. Push to the branch (`git push origin feature/amazing-feature`)
6. Open a Pull Request

### Development Guidelines
- Follow PSR-12 coding standards
- Write tests for new features
- Use conventional commit messages
- Update documentation for API changes

## 🐛 Troubleshooting

### Common Issues

**Camera not working:**
- Ensure HTTPS is enabled (required for camera access)
- Check browser permissions for camera access
- Verify WebRTC support in the browser

**Queue jobs not processing:**
- Start the queue worker: `php artisan queue:work`
- Check queue configuration in `.env`
- Monitor failed jobs: `php artisan queue:failed`

**Image composition fails:**
- Verify GD/Imagick extension is installed
- Check file permissions in storage directory
- Monitor Laravel logs for specific errors

**Payment webhook issues:**
- Verify webhook URL is accessible
- Check payment gateway configuration
- Review webhook logs in payment provider dashboard

## 📄 License

This project is open-sourced software licensed under the [MIT license](https://opensource.org/licenses/MIT).

## 🙏 Acknowledgments

- Laravel framework and community
- Vue.js ecosystem
- TailwindCSS for beautiful styling
- Intervention Image for image processing
- All the amazing open-source contributors

---

**Built with ❤️ for creating memorable photo experiences**
