# HR Operations Platform (HROPS)
**Enterprise-Grade Human Resources Lifecycle Management System**

## 🏢 Project Overview

**HROPS** is a comprehensive, enterprise-grade Human Resources Operations Platform built with Core PHP, MySQL, and modern web technologies. The system manages the complete employee lifecycle from recruitment to performance management, featuring advanced organizational structure management, dual interface capabilities, and enterprise-level scalability.

### 🎯 Mission Statement
Provide a unified HR platform that seamlessly handles recruitment, onboarding, performance management, organizational structure, and employee self-service while maintaining enterprise-grade security, audit trails, and scalability.

---

## 🚀 Core Features & Capabilities

### 📋 **Recruitment Management**
- **Candidate Lifecycle:** Application → Screening → Interview → Offer → Hire
- **Job Posting Management:** Create, edit, and manage job openings
- **Interview Scheduling:** Multi-round interviews with feedback collection
- **Candidate Communication:** Automated emails and reminders
- **Analytics Dashboard:** Recruitment metrics and pipeline tracking

### 👥 **Employee Onboarding**
- **Structured Onboarding Process:** Task-based workflow management
- **Document Collection:** Digital document submission and verification
- **Training Assignment:** Module-based training with progress tracking
- **Buddy System:** Mentor assignment and guidance
- **Progress Monitoring:** Real-time onboarding status tracking

### ⭐ **Performance Management**
- **Goal Setting & Tracking:** Individual and team performance goals
- **Performance Reviews:** Structured review cycles and evaluations
- **360° Feedback:** Multi-source feedback collection and analysis
- **Development Plans:** Personalized growth and development tracking
- **Performance Improvement Plans:** Structured improvement workflows

### 🏗️ **Enterprise Organizational Management**
- **6-Level Hierarchy:** Executive → Director → Manager → Team Lead → Senior IC → IC
- **36 Organizational Positions:** Predefined roles with clear reporting structures
- **Role Transition Workflows:** Promotion, transfer, and organizational change management
- **Team Management:** Cross-functional and project-based team structures
- **Historical Tracking:** Complete audit trail of all organizational changes

### 🏢 **Department Management**
- **5 Active Departments:** Administration, Engineering, Marketing, Sales, Finance
- **Budget Management:** Department-wise budget allocation and tracking
- **Department Goals:** Strategic objective setting and monitoring
- **Head Assignments:** Formal department leadership structure

### 🔄 **Dual Interface System**
- **HR Management Mode:** Complete administrative and management capabilities
- **Employee Portal Mode:** Self-service features for all users including HR staff
- **Seamless Switching:** Context-aware interface transitions
- **Role-Based Access:** Intelligent permission management

---

## 🏗️ System Architecture

### 📊 **Database Structure**

#### **Core HR Tables**
- `users` - User authentication and basic information
- `employees` - Employee records and HR data
- `candidates` - Recruitment candidate management
- `interviews` - Interview scheduling and feedback
- `offers` - Job offers and acceptance tracking

#### **Organizational Structure**
- `departments` - Formal department management with budgets
- `organizational_positions` - 36-position enterprise hierarchy
- `employee_position_assignments` - Historical position tracking
- `role_transitions` - Promotion/transfer workflows
- `teams` - Cross-functional team management
- `current_reporting_relationships` - Manager-subordinate relationships

#### **Performance & Development**
- `performance_goals` - Individual and team objectives
- `performance_reviews` - Review cycles and evaluations
- `feedback_360_requests` - Multi-source feedback management
- `development_plans` - Employee growth tracking

#### **Training & Onboarding**
- `training_modules` - Learning content management
- `onboarding_checklists` - Structured onboarding workflows
- `employee_training` - Training assignment and progress

### 🎨 **Technology Stack**
- **Backend:** Core PHP 8.x with PDO
- **Database:** MySQL 8.x with InnoDB engine
- **Frontend:** HTML5, Tailwind CSS 3.x, JavaScript ES6+
- **Charts:** Chart.js for analytics visualization
- **Icons:** FontAwesome 6.x
- **Server:** Apache 2.4 (XAMPP development environment)

### 🔐 **Security & Access Control**
- **Role-Based Access Control (RBAC):** 5-tier permission system
- **Session Management:** Secure PHP session handling
- **Password Security:** BCrypt hashing with strong policies
- **SQL Injection Protection:** Prepared statements throughout
- **XSS Prevention:** Input sanitization and output encoding

---

## 👤 User Roles & Permissions

### **Role Hierarchy (Level 1-5)**
| Role | Level | Capabilities |
|------|-------|-------------|
| **admin** | 5 | Full system access, organizational management, all HR functions |
| **hr_recruiter** | 4 | Complete recruitment lifecycle, employee management, analytics |
| **hiring_manager** | 3 | Interview management, candidate evaluation, team oversight |
| **interviewer** | 2 | Interview participation, candidate feedback, limited access |
| **employee** | 1 | Employee portal access, self-service features, personal data |

### **Access Control Matrix**
- **Recruitment:** Admin, HR Recruiter, Hiring Manager
- **Onboarding:** Admin, HR Recruiter
- **Performance:** Admin, HR Recruiter, Hiring Manager
- **Organization:** Admin, HR Recruiter, Hiring Manager
- **Employee Portal:** All users (with role-appropriate features)
- **Dual Interface:** HR roles can switch between management and employee modes

---

## 📁 Directory Structure

```
hrops/
├── config/
│   ├── config.php              # Main configuration
│   ├── database.php            # Database connection
│   └── auth.php               # Authentication logic
├── includes/
│   ├── functions.php          # Core utility functions
│   ├── auth.php              # Session management
│   ├── header.php            # Common header
│   ├── footer.php            # Common footer
│   ├── dual_interface.php    # Interface switching logic
│   └── employee_navbar.php   # Employee portal navigation
├── candidates/               # Recruitment management
├── interviews/              # Interview scheduling
├── offers/                  # Offer management
├── employees/               # Employee management
├── onboarding/             # Onboarding workflows
├── performance/            # Performance management
├── training/               # Training modules
├── departments/            # Department management
├── organization/           # Enterprise organizational management
├── employee/               # Employee portal
├── analytics/              # Reporting and analytics
└── assets/                 # Static assets (CSS, JS, images)
```

---

## 🎯 Current Implementation Status

### ✅ **Fully Implemented**
- ✅ **User Authentication & Session Management**
- ✅ **Complete Recruitment Lifecycle** (Candidates → Offers → Hire)
- ✅ **Interview Scheduling & Management**
- ✅ **Employee Onboarding Workflows**
- ✅ **Performance Goal Setting & Tracking**
- ✅ **360° Feedback System**
- ✅ **Training Module Management**
- ✅ **Dual Interface System** (HR ↔ Employee switching)
- ✅ **Department Management** with budgets and goals
- ✅ **Enterprise Organizational Structure** (36 positions, 6 levels)
- ✅ **Role Transition Workflows**
- ✅ **Team Management System**
- ✅ **Analytics & Reporting Dashboards**
- ✅ **Audit Trails & Historical Tracking**

### 🔄 **Ready for Extension**
- 🔄 **Payroll Integration** - Database structure ready
- 🔄 **Attendance Tracking** - Framework in place
- 🔄 **Benefits Management** - Core infrastructure available
- 🔄 **Document Management** - Basic system implemented
- 🔄 **Advanced Reporting** - Base analytics implemented

---

## 📊 System Statistics

### **Database Metrics**
- **Total Tables:** 38+ tables with complete relational integrity
- **Organizational Positions:** 36 predefined positions across 6 levels
- **Departments:** 5 active departments with $3.5M total budget allocation
- **User Roles:** 5-tier permission system
- **Management Positions:** 24 management roles with defined authorities

### **Feature Coverage**
- **Recruitment:** 100% - Complete lifecycle management
- **Onboarding:** 100% - Structured workflow system
- **Performance:** 100% - Goals, reviews, 360° feedback
- **Organization:** 100% - Enterprise-grade structure management
- **Employee Portal:** 100% - Full self-service capabilities
- **Analytics:** 85% - Core metrics with expansion capability

---

## 🛠️ Installation & Setup

### **Prerequisites**
- PHP 8.0+ with extensions: PDO, MySQL, mbstring, openssl
- MySQL 8.0+ or MariaDB 10.5+
- Apache 2.4+ with mod_rewrite
- Web browser with JavaScript enabled

### **Quick Start (XAMPP)**
1. **Clone/Download** project to `C:\xampp\htdocs\hrops`
2. **Start XAMPP** - Apache and MySQL services
3. **Database Setup:**
   ```sql
   CREATE DATABASE hrops_db CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci;
   ```
4. **Import Database** - Run SQL migrations (auto-generated during setup)
5. **Access Application:** `http://localhost/hrops`
6. **Default Admin Login:**
   - Username: `admin`
   - Password: `admin123`

### **Configuration**
- **Database:** Edit `config/database.php` for database credentials
- **Application:** Modify `config/config.php` for application settings
- **Security:** Update default passwords and session configuration

---

## 👥 Current User Base

### **Active Users**
| Username | Role | Department | Employee ID | Status |
|----------|------|------------|-------------|---------|
| admin | admin | Administration | HR001 | ✅ Active |
| sarah.wilson | hiring_manager | Engineering | EMP005 | ✅ Active |
| john.doe | employee | Engineering | EMP002 | ✅ Active |
| jane.smith | employee | Marketing | EMP003 | ✅ Active |
| mike.johnson | employee | Sales | EMP004 | ✅ Active |
| david.brown | employee | Finance | EMP006 | ✅ Active |

### **Manager Assignments**
- **System Administrator** → Manages: Jane Smith, Mike Johnson, Sarah Wilson, David Brown
- **Sarah Wilson** → Manages: John Doe (Engineering Team)

---

## 🔮 Future Roadmap

### **Phase 1: Payroll Integration** (Next Priority)
- Salary management and processing
- Tax calculations and deductions
- Payslip generation and distribution
- Integration with existing employee records

### **Phase 2: Advanced Analytics** 
- Predictive HR analytics
- Advanced reporting dashboards
- Data visualization enhancements
- Business intelligence integration

### **Phase 3: Mobile Application**
- Native mobile app for employee self-service
- Push notifications for HR processes
- Offline capability for critical functions
- Mobile-first onboarding experience

### **Phase 4: API & Integrations**
- RESTful API for external integrations
- SSO (Single Sign-On) implementation
- Third-party HR tool integrations
- Webhook support for real-time updates

---

## 🎉 Key Achievements

### **Enterprise-Grade Capabilities**
✅ **Scalable Architecture** - Supports complex organizational structures
✅ **Dual Interface System** - Unique HR/Employee mode switching
✅ **Complete Audit Trails** - Full historical tracking for compliance
✅ **Role Transition Workflows** - Enterprise-level promotion/transfer management
✅ **Cross-Functional Teams** - Matrix organization support
✅ **Performance Management** - 360° feedback and development planning

### **Technical Excellence**
✅ **Security First** - RBAC, session management, SQL injection prevention
✅ **Modern UI/UX** - Responsive design with Tailwind CSS
✅ **Database Optimization** - Normalized structure with proper indexing
✅ **Code Quality** - Clean, maintainable PHP with separation of concerns
✅ **Documentation** - Comprehensive system documentation

---

## 📞 Support & Maintenance

### **System Monitoring**
- Regular database backups recommended
- Session cleanup for optimal performance
- Log monitoring for security and errors
- Regular updates for security patches

### **Maintenance Schedule**
- **Daily:** Automated backups, log rotation
- **Weekly:** Performance monitoring, security updates
- **Monthly:** Database optimization, analytics review
- **Quarterly:** Feature updates, security audits

---

## 🏆 Project Status: **PRODUCTION READY**

**HROPS** is a fully functional, enterprise-grade HR management system ready for production deployment. The system successfully handles the complete employee lifecycle with advanced organizational management capabilities, making it suitable for organizations of any size.

**Key Differentiators:**
- ✨ **Dual Interface Innovation** - Unique HR/Employee mode switching
- 🏗️ **Enterprise Organizational Management** - 36-position hierarchy system
- 🔄 **Workflow-Driven Processes** - Automated approval chains
- 📊 **Complete Analytics Suite** - Data-driven HR insights
- 🛡️ **Security & Compliance** - Enterprise-grade audit trails

---

**Built with ❤️ for modern HR operations**
*Last Updated: January 2025*