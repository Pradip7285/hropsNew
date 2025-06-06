# 🎯 Employee Portal - 3-Tier Role-Based System

## 📋 Overview

The HROPS system now has a **dedicated employee portal** with role-based access control, providing employees with a streamlined, focused interface for their daily tasks.

## 🏗️ System Architecture

### **3-Tier Role Hierarchy:**

```
┌─────────────────────────┐
│       ADMIN TIER        │
│   • Admin               │
│   • Full System Access │
└─────────────────────────┘
            │
┌─────────────────────────┐
│    MANAGEMENT TIER      │
│   • Sr. HR              │
│   • HR Head             │
│   • Executives          │
│   • Managers            │
└─────────────────────────┘
            │
┌─────────────────────────┐
│     EMPLOYEE TIER       │
│   • Employees           │
│   • Dedicated Portal    │
└─────────────────────────┘
```

## 🚀 Employee Portal Features

### **📊 Employee Dashboard** (`/employee/dashboard.php`)
- **Personal workspace** with employee-focused metrics
- **Quick stats**: Pending tasks, completed training, documents, goals
- **Action cards** for core employee functions
- **Onboarding progress** tracking
- **Recent activity** feed

### **✅ Task Management** (`/employee/tasks.php`)
- **View and track** assigned onboarding and daily tasks
- **Task completion** with feedback submission
- **Priority-based** task organization
- **Status tracking**: Pending, In Progress, Under Review, Completed
- **Manager approval** workflow

### **🎓 Training & Development** (`/employee/training.php`)
- **Mandatory training** completion tracking
- **Optional professional development** modules
- **Progress monitoring** with completion percentages
- **Interactive training content**
- **Certification tracking**

### **📄 Document Management** (`/employee/documents.php`)
- **Upload personal documents** (ID, resume, certificates)
- **Document status tracking** (Pending Review, Approved, Needs Update)
- **Required documents checklist**
- **Secure file upload** with validation
- **HR approval workflow**

### **📚 Company Policies** (`/employee/policies.php`)
- **View and acknowledge** company policies
- **Mandatory vs. optional** policy classification
- **Policy acknowledgment tracking**
- **Full-text policy reading** with modal display
- **Compliance monitoring**

## 🔐 Role-Based Access Control

### **Employee Access:**
✅ **Allowed:**
- Employee dashboard
- Personal tasks
- Training modules
- Document upload/management
- Policy reading
- Personal goals (performance module)
- Personal reviews

❌ **Restricted:**
- HR management features
- Candidate management
- Interview scheduling (for others)
- System administration
- User management

### **Manager/HR Access:**
✅ **Full employee access PLUS:**
- Interview management
- Candidate evaluation
- Employee task approval
- Training assignment
- Document review
- Goal setting for team members

### **Admin Access:**
✅ **Complete system access:**
- All employee and manager features
- System configuration
- User management
- Advanced analytics
- Database administration

## 🎯 Future Extensions (Planned)

### **Attendance Module:**
- Daily check-in/check-out
- Work hours tracking
- Overtime calculation
- Attendance reports

### **Leave Management:**
- Leave application submission
- Leave balance tracking
- Manager approval workflow
- Leave calendar integration

### **Payroll Integration:**
- Salary slip downloads
- Tax documents
- Payroll processing status
- Benefits tracking

### **Performance Appraisal:**
- 360-degree feedback
- Performance reviews
- Goal setting and tracking
- Development planning

## 📁 File Structure

```
employee/
├── dashboard.php          # Main employee dashboard
├── tasks.php             # Task management
├── training.php          # Training modules
├── documents.php         # Document management
├── policies.php          # Company policies
├── profile.php           # Employee profile (planned)
├── attendance.php        # Attendance tracking (planned)
├── leave.php            # Leave management (planned)
└── payroll.php          # Payroll access (planned)
```

## 🔄 Workflow Examples

### **1. New Employee Onboarding:**
1. Employee logs in → Redirected to employee portal
2. Complete profile setup task
3. Upload required documents
4. Complete mandatory training
5. Acknowledge company policies
6. Manager reviews and approves tasks

### **2. Document Update Process:**
1. Employee uploads document
2. Status: "Pending Review"
3. HR reviews document
4. Status changes to "Approved" or "Needs Update"
5. Employee receives notification

### **3. Training Completion:**
1. Employee starts mandatory training
2. Progress tracked automatically
3. Completion recorded
4. Certificate generated (future)
5. Updates employee skill profile

## 🛠️ Technical Implementation

### **Security Features:**
- Role-based authentication
- Session management
- File upload validation
- SQL injection prevention
- XSS protection

### **Database Structure:**
- `employees` table links to `users`
- `onboarding_tasks` for task management
- `employee_training` for training progress
- `employee_documents` for file management
- `policy_acknowledgments` for compliance

### **UI/UX Design:**
- Tailwind CSS for responsive design
- FontAwesome icons
- Mobile-friendly interface
- Intuitive navigation
- Progress indicators

## 🚀 Getting Started

### **For Employees:**
1. Login with employee credentials
2. Complete onboarding tasks
3. Upload required documents
4. Complete mandatory training
5. Acknowledge policies

### **For Managers:**
1. Access employee management features
2. Review and approve employee tasks
3. Monitor team training progress
4. Provide feedback and guidance

### **For Admins:**
1. Configure system settings
2. Manage user roles
3. Monitor system usage
4. Generate reports

## 📈 Benefits

### **For Employees:**
- **Simplified interface** focused on their needs
- **Clear task prioritization** and tracking
- **Self-service capabilities** for common tasks
- **Progress visibility** on onboarding and development

### **For Managers:**
- **Team oversight** with approval workflows
- **Efficient task assignment** and monitoring
- **Streamlined employee development** tracking

### **For HR:**
- **Centralized employee data** management
- **Automated compliance tracking**
- **Reduced administrative overhead**
- **Better employee engagement** metrics

## 🔧 Configuration

### **Role Assignment:**
```php
// In includes/auth.php
$role_hierarchy = [
    'admin' => 5,
    'hr_recruiter' => 4,
    'hiring_manager' => 3,
    'interviewer' => 2,
    'employee' => 1
];
```

### **Access Control:**
```php
// Redirect employees to portal
if ($_SESSION['role'] == 'employee') {
    header('Location: ' . BASE_URL . '/employee/dashboard.php');
    exit();
}
```

This employee portal system provides a **professional, scalable foundation** for employee self-service while maintaining proper access controls and workflow management. The modular design ensures easy extension for future payroll, attendance, and appraisal features. 