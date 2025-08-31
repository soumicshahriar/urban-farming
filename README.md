# Urban Farming Management System

A comprehensive web-based system for managing urban farming operations with IoT monitoring, drone services, AI recommendations, and a Green Points reward system.

## 🌱 Features

### User Authentication & Role Management
- **Farmer**: Manage farms, request drone services, monitor IoT sensors, trade in marketplace
- **Planner**: Approve farm requests, manage drone assignments, optimize resources
- **Admin**: Global system monitoring, user management, system oversight

### Farm Management
- Create and manage farm requests
- IoT device integration and monitoring
- Real-time sensor data visualization
- Farm approval workflow

### Drone Services
- Request drone services (survey, pest control, monitoring)
- AI-powered recommendations for optimal drone usage
- Real-time drone status tracking
- Automated mission completion

### IoT Monitoring
- Real-time sensor data (soil moisture, temperature, humidity, light, water flow)
- Device control and automation
- Historical data analysis
- Alert system for critical conditions

### Seed Marketplace
- Buy and sell seeds with eco-friendly options
- Green Points rewards for sustainable practices
- Search and filter functionality
- Transaction history

### Green Points System
- Earn points for eco-friendly practices
- AI recommendation following rewards
- Leaderboard and achievements
- Points redemption system

### AI Recommendations
- Smart farming suggestions
- Optimal timing recommendations
- Resource optimization
- Predictive analytics

## 🚀 Installation

### Prerequisites
- PHP 7.4 or higher
- MySQL 5.7 or higher
- Apache/Nginx web server
- XAMPP/WAMP/MAMP (for local development)

### Setup Instructions

1. **Clone the repository**
   ```bash
   git clone <repository-url>
   cd urban-farming
   ```

2. **Database Setup**
   - Create a MySQL database named `urban_farming`
   - Import the database schema from `database/schema.sql`
   - Update database credentials in `config/database.php`

3. **Web Server Configuration**
   - Place the project in your web server's document root
   - Ensure PHP has PDO and MySQL extensions enabled
   - Set appropriate file permissions

4. **Access the Application**
   - Open your browser and navigate to the project URL
   - Register new accounts or use the default admin account:
     - Username: `admin`
     - Password: `password`

## 📁 Project Structure

```
urban-farming/
├── config/
│   └── database.php          # Database configuration
├── database/
│   └── schema.sql           # Database schema
├── includes/
│   ├── header.php           # Common header
│   └── footer.php           # Common footer
├── index.php                # Landing page
├── login.php                # User authentication
├── register.php             # User registration
├── logout.php               # Session cleanup
├── farmer_dashboard.php     # Farmer dashboard
├── planner_dashboard.php    # Planner dashboard
├── admin_dashboard.php      # Admin dashboard
├── create_farm.php          # Farm creation
├── farm_requests.php        # Farm management
├── request_drone.php        # Drone request form
├── iot_monitoring.php       # IoT monitoring dashboard
├── marketplace.php          # Seed marketplace
└── README.md               # This file
```

## 🔧 Configuration

### Database Configuration
Edit `config/database.php`:
```php
private $host = "localhost";
private $db_name = "urban_farming";
private $username = "your_username";
private $password = "your_password";
```

### Environment Variables
For production deployment, consider using environment variables for sensitive data.

## 🎯 User Workflows

### Farmer Workflow
1. Register as a farmer
2. Create farm requests with IoT device specifications
3. Wait for planner approval
4. Request drone services for approved farms
5. Monitor IoT sensors in real-time
6. Trade seeds in the marketplace
7. Earn Green Points for eco-friendly practices

### Planner Workflow
1. Review pending farm requests
2. Approve/reject farms based on criteria
3. Manage drone assignments
4. Monitor system efficiency
5. Earn Green Points for optimal resource allocation

### Admin Workflow
1. Monitor overall system health
2. Manage user accounts and permissions
3. View system logs and analytics
4. Oversee Green Points distribution
5. Ensure system compliance and security

## 🌟 Green Points System

### Earning Points
- Following AI recommendations: +5 points
- Using eco-friendly seeds: +3 points
- Efficient drone usage: +2 points
- Optimal timing: +3 points
- IoT optimization: +2 points

### Benefits
- Priority access to services
- Recognition badges
- Marketplace discounts
- Leaderboard ranking

## 🔒 Security Features

- Password hashing with bcrypt
- Session management
- SQL injection prevention
- XSS protection
- Role-based access control
- Input validation and sanitization

## 📊 System Requirements

### Minimum Requirements
- PHP 7.4+
- MySQL 5.7+
- 512MB RAM
- 1GB storage

### Recommended Requirements
- PHP 8.0+
- MySQL 8.0+
- 2GB RAM
- 5GB storage
- SSL certificate

## 🚀 Deployment

### Local Development
1. Use XAMPP/WAMP/MAMP
2. Import database schema
3. Configure database connection
4. Access via localhost

### Production Deployment
1. Set up a web server (Apache/Nginx)
2. Configure SSL certificate
3. Set up MySQL database
4. Configure environment variables
5. Set proper file permissions
6. Enable error logging

## 🐛 Troubleshooting

### Common Issues

1. **Database Connection Error**
   - Check database credentials
   - Ensure MySQL service is running
   - Verify database exists

2. **Permission Denied**
   - Set proper file permissions (755 for directories, 644 for files)
   - Ensure web server has read/write access

3. **Session Issues**
   - Check PHP session configuration
   - Verify session storage permissions

4. **IoT Data Not Updating**
   - Check device connectivity
   - Verify sensor configurations
   - Review error logs

## 📈 Future Enhancements

- Mobile application
- Advanced AI analytics
- Weather integration
- Blockchain for Green Points
- API for third-party integrations
- Advanced reporting and analytics
- Multi-language support
- Push notifications

## 🤝 Contributing

1. Fork the repository
2. Create a feature branch
3. Make your changes
4. Test thoroughly
5. Submit a pull request

## 📄 License

This project is licensed under the MIT License - see the LICENSE file for details.

## 📞 Support

For support and questions:
- Create an issue in the repository
- Contact the development team
- Check the documentation

## 🎉 Acknowledgments

- Bootstrap for UI components
- Chart.js for data visualization
- Font Awesome for icons
- PHP PDO for database operations

---

**Urban Farming Management System** - Revolutionizing urban agriculture with technology and sustainability.
