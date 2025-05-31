# HR Employee Lifecycle Management Application

A comprehensive HR application built with Core PHP, HTML, and Tailwind CSS for managing the complete employee lifecycle from recruitment to onboarding.

## Features

Based on the Product Requirements Document (PRD), this application includes:

### Phase 1 (Currently Implemented)
- ✅ **User Authentication & Authorization**
  - Role-based access control (Admin, HR Recruiter, Hiring Manager, Interviewer, Employee)
  - Secure login with session management
  - User permissions and dashboard

- ✅ **Dashboard & Analytics**
  - Real-time statistics and metrics
  - Hiring pipeline visualization
  - Recent activity tracking
  - Quick actions panel

- ✅ **Candidate Management**
  - Resume parsing and AI-based screening
  - Candidate database with search and filtering
  - Status tracking (New, Shortlisted, Interviewing, Offered, Hired, Rejected)
  - Bulk upload functionality

### Phase 2 (In Development)
- 🔄 **Interview Management**
  - Automated interview scheduling
  - Calendar integration
  - Interview feedback and evaluation
  - Bias detection in evaluations

### Phase 3 (Planned)
- 📋 **Offer & Approval Workflow**
  - Customizable offer templates
  - Electronic signatures integration
  - Multi-level approval workflow
  - Audit trail

- 📄 **Pre-Onboarding & Documentation**
  - Digital document collection
  - eSignatures for compliance
  - Task automation and checklists

- 👥 **Employee Onboarding**
  - Personalized onboarding portal
  - Buddy system and mentorship
  - Progress tracking and surveys

- 📊 **Advanced Reporting & Analytics**
  - Recruitment metrics
  - Employee onboarding insights
  - Custom report generation

## Technology Stack

- **Backend**: Core PHP 7.4+
- **Database**: MySQL 8.0+
- **Frontend**: HTML5, Tailwind CSS 3.x
- **Icons**: Font Awesome 6.0
- **Charts**: Chart.js
- **Server**: Apache (XAMPP recommended for development)

## Requirements

- PHP 7.4 or higher
- MySQL 8.0 or higher
- Apache Web Server
- mod_rewrite enabled
- PDO extension enabled

## Installation & Setup

### 1. Prerequisites
- Install XAMPP (recommended) or LAMP/WAMP stack
- Ensure PHP and MySQL are running

### 2. Clone/Download Project
```bash
# If using Git
git clone <repository-url> C:/xampp/htdocs/hrops

# Or download and extract to C:/xampp/htdocs/hrops
```

### 3. Database Setup
1. Open phpMyAdmin (http://localhost/phpmyadmin)
2. Create a new database named `hrops_db`
3. Import the database schema:
   ```sql
   # Run the SQL file: database/schema.sql
   ```
   Or manually execute the schema file located at `database/schema.sql`

### 4. Configuration
1. Update database credentials in `config/config.php` if needed:
   ```php
   define('DB_HOST', 'localhost');
   define('DB_NAME', 'hrops_db');
   define('DB_USER', 'root');
   define('DB_PASS', '');
   ```

2. Update the base URL in `config/config.php`:
   ```php
   define('BASE_URL', 'http://localhost/hrops/');
   ```

### 5. File Permissions
Create uploads directory and set proper permissions:
```bash
mkdir uploads
chmod 755 uploads
```

### 6. Access the Application
- Open your browser and navigate to: `http://localhost/hrops/`
- Default login credentials:
  - **Username**: admin
  - **Password**: admin123

## Directory Structure

```
hrops/
├── config/
│   ├── config.php          # Main configuration
│   └── database.php        # Database connection class
├── database/
│   └── schema.sql          # Database schema
├── includes/
│   ├── auth.php           # Authentication middleware
│   ├── functions.php      # Utility functions
│   ├── header.php         # Header component
│   └── sidebar.php        # Sidebar navigation
├── candidates/
│   ├── list.php           # Candidates listing
│   ├── add.php            # Add new candidate
│   ├── edit.php           # Edit candidate
│   └── view.php           # View candidate details
├── jobs/
│   └── [job management files]
├── interviews/
│   └── [interview management files]
├── offers/
│   └── [offer management files]
├── employees/
│   └── [employee management files]
├── reports/
│   └── [reporting files]
├── uploads/               # File uploads directory
├── index.php              # Main entry point
├── login.php              # Login page
├── dashboard.php          # Main dashboard
├── logout.php             # Logout handler
└── README.md              # This file
```

## Key Features Implemented

### Authentication System
- Secure password hashing using PHP password_hash()
- Session management with timeout
- Role-based access control
- Password reset functionality (planned)

### Dashboard
- Real-time statistics
- Interactive charts using Chart.js
- Recent activity feed
- Quick action buttons

### Candidate Management
- Complete CRUD operations
- Advanced search and filtering
- Status tracking and updates
- File upload for resumes
- AI scoring system (basic implementation)

### Database Design
- Normalized database structure
- Foreign key relationships
- Audit trails for all actions
- Optimized queries with indexing

## Security Features

- SQL injection prevention using prepared statements
- XSS protection with input sanitization
- CSRF protection (planned)
- Session hijacking prevention
- File upload security
- Role-based access control

## Performance Optimizations

- Pagination for large datasets
- Lazy loading of images
- Optimized database queries
- CDN usage for external libraries
- Proper indexing on database tables

## Browser Support

- Chrome 90+
- Firefox 88+
- Safari 14+
- Edge 90+

## Development Guidelines

### Code Standards
- Follow PSR-4 autoloading standards
- Use meaningful variable and function names
- Comment complex logic
- Maintain consistent indentation

### Database Conventions
- Use snake_case for table and column names
- Include created_at and updated_at timestamps
- Use proper foreign key constraints
- Index frequently queried columns

### Security Best Practices
- Always use prepared statements
- Validate and sanitize all user inputs
- Implement proper error handling
- Log security-related events

## Troubleshooting

### Common Issues

1. **Database Connection Error**
   - Check MySQL service is running
   - Verify database credentials in config.php
   - Ensure database exists

2. **Permission Denied**
   - Check file permissions for uploads directory
   - Verify Apache has write access

3. **Blank Page/PHP Errors**
   - Enable PHP error reporting in php.ini
   - Check Apache error logs
   - Verify PHP version compatibility

4. **Styling Issues**
   - Check internet connection for CDN resources
   - Verify Tailwind CSS is loading properly

## Contributing

1. Fork the repository
2. Create a feature branch
3. Make your changes
4. Test thoroughly
5. Submit a pull request

## License

This project is licensed under the MIT License - see the LICENSE file for details.

## Support

For support and questions:
- Check the documentation
- Review the troubleshooting section
- Contact the development team

## Roadmap

### Upcoming Features
- Email notifications
- Calendar integration
- Document management system
- Advanced reporting
- Mobile responsiveness improvements
- API development for integrations

---

**Version**: 1.0.0  
**Last Updated**: December 2024 