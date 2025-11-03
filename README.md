# CleanIndex Portal - WordPress Plugin
## Installation & Setup Guide

### 📋 **Table of Contents**
1. [Overview](#overview)
2. [Requirements](#requirements)
3. [Installation](#installation)
4. [Configuration](#configuration)
5. [User Roles](#user-roles)
6. [Features](#features)
7. [File Structure](#file-structure)
8. [Troubleshooting](#troubleshooting)

---

## 🎯 **Overview**

CleanIndex Portal is a complete WordPress plugin for managing ESG (Environmental, Social, and Governance) certifications. It includes:

- **Multi-step registration** with document uploads
- **CSRD/ESRS compliance questionnaire** (5 steps, 30+ questions)
- **Role-based dashboards** (Admin, Manager, Organization)
- **Approval workflow** with email notifications
- **Evidence file management** (PDF/DOC/DOCX)
- **Glassmorphism UI** with brand colors

---

## ⚙️ **Requirements**

- **WordPress**: 5.8 or higher
- **PHP**: 7.4 or higher
- **MySQL**: 5.7 or higher
- **Server**: Apache or Nginx
- **PHP Extensions**: `mysqli`, `mbstring`, `fileinfo`
- **Upload Limits**: Minimum 10MB per file

---

## 📦 **Installation**

### Step 1: Download the Plugin

Download and extract the `cleanindex-portal` folder.

### Step 2: Upload to WordPress

```bash
/wp-content/plugins/cleanindex-portal/
```

Upload the entire folder to your WordPress plugins directory.

### Step 3: Activate the Plugin

1. Go to **WordPress Admin → Plugins**
2. Find "CleanIndex Portal"
3. Click **Activate**

The plugin will automatically:
- Create two database tables (`wp_company_registrations`, `wp_company_assessments`)
- Add custom user roles (`Manager`, `Organization Admin`)
- Create upload directories in `/wp-content/uploads/cleanindex/`

---

## 🔧 **Configuration**

### Email Settings

For email notifications to work, configure SMTP in WordPress:

1. Install **WP Mail SMTP** plugin (recommended)
2. Configure your email provider (Gmail, SendGrid, etc.)
3. Test email delivery

### Permalinks

1. Go to **Settings → Permalinks**
2. Choose **Post name** or **Custom Structure**
3. Click **Save Changes**

This ensures clean URLs like `/cleanindex/register` work properly.

### File Upload Limits

If you need to increase upload limits, add to `wp-config.php`:

```php
@ini_set('upload_max_filesize', '10M');
@ini_set('post_max_size', '10M');
```

---

## 👥 **User Roles**

### Administrator
- Full access to all features
- Can approve/reject registrations
- Manage submissions and assessments

### Manager
- Review pending registrations
- Request more information
- Recommend approvals

### Organization Admin
- Register organization
- Complete ESG assessment
- Upload evidence documents
- View own dashboard

---

## ✨ **Features**

### Registration System
- **URL**: `/cleanindex/register`
- Multi-field form with validation
- Support for 3 document uploads (PDF/DOC/DOCX, max 10MB each)
- Automatic status tracking

### Assessment (CSRD/ESRS Compliant)
- **URL**: `/cleanindex/assessment`
- 5-step questionnaire:
  1. General Requirements & Materiality Analysis
  2. Company Profile & Governance
  3. Strategy & Risk Management
  4. Environment (E1-E5)
  5. Social & Metrics (S1-S4)
- Evidence upload per question
- Auto-save progress
- Visual progress indicator

### Dashboards

**Manager Dashboard**: `/cleanindex/manager`
- View all pending registrations
- Filter by industry, country, date
- Download submitted documents
- Recommend approval or request more info

**Admin Dashboard**: `/cleanindex/admin-portal`
- Statistics cards (Total, Pending, Approved, Rejected)
- Full submission details
- Approve/Reject actions
- Send approval/rejection emails

**Organization Dashboard**: `/cleanindex/dashboard`
- View registration status
- Access assessment
- Track progress
- View uploaded documents

### Email Notifications

Automated emails sent for:
- ✅ **Approval**: "Your registration has been approved"
- ❌ **Rejection**: "Additional information required"
- 📧 **Info Request**: "Action required from manager"

---

## 📂 **File Structure**

```
cleanindex-portal/
│
├── cleanindex-portal.php          # Main plugin file
├── README.md                       # This file
│
├── includes/                       # Core functionality
│   ├── db.php                     # Database functions
│   ├── roles.php                  # User roles & capabilities
│   ├── auth.php                   # Authentication
│   ├── email.php                  # Email notifications
│   ├── upload-handler.php         # File upload handling
│   └── helpers.php                # Helper functions
│
├── pages/                          # Frontend pages
│   ├── register.php               # Registration page
│   ├── login.php                  # Login page
│   ├── user-dashboard.php         # Organization dashboard
│   ├── assessment.php             # CSRD/ESRS questionnaire
│   ├── manager-dashboard.php      # Manager dashboard
│   └── admin-dashboard.php        # Admin dashboard
│
├── css/
│   └── style.css                  # Glassmorphism styles
│
├── js/
│   └── script.js                  # Frontend JavaScript
│
└── mailer/
    └── email-templates/           # HTML email templates
        ├── approval.html
        ├── rejection.html
        └── info-request.html
```

---

## 🎨 **Design System**

### Colors
- **Primary**: `#4CAF50` (Green)
- **Secondary**: `#EB5E28` (Orange)
- **Accent**: `#03A9F4` (Blue)
- **Black**: `#000000`
- **White**: `#FAFAFA`

### Typography
- **Headers**: Raleway
- **Subheadings**: Open Sans
- **Body**: Inter

### UI Style
- Glassmorphism cards with frosted backgrounds
- Rounded corners (12-16px)
- Subtle shadows and hover effects
- Fully responsive design

---

## 🔑 **Key URLs**

| Page | URL | Access |
|------|-----|--------|
| Registration | `/cleanindex/register` | Public |
| Login | `/cleanindex/login` | Public |
| Org Dashboard | `/cleanindex/dashboard` | Organization Admin |
| Assessment | `/cleanindex/assessment` | Approved Orgs |
| Manager Portal | `/cleanindex/manager` | Manager |
| Admin Portal | `/cleanindex/admin-portal` | Administrator |

---

## 🛠️ **Troubleshooting**

### Issue: 404 Error on Plugin Pages

**Solution**: 
1. Go to **Settings → Permalinks**
2. Click **Save Changes** (no changes needed)
3. This flushes rewrite rules

### Issue: File Upload Fails

**Solution**:
1. Check PHP upload limits in `php.ini`
2. Ensure `/wp-content/uploads/cleanindex/` is writable
3. Verify file types (PDF, DOC, DOCX only)

### Issue: Emails Not Sending

**Solution**:
1. Install and configure **WP Mail SMTP** plugin
2. Test email configuration
3. Check spam folder

### Issue: Database Tables Not Created

**Solution**:
1. Deactivate the plugin
2. Re-activate it
3. Check database for `wp_company_registrations` table

### Issue: Permission Denied Errors

**Solution**:
1. Check folder permissions: `755` for directories
2. Check file permissions: `644` for files
3. Ensure web server can write to upload directory

---

## 📊 **Database Schema**

### Table: `wp_company_registrations`

| Column | Type | Description |
|--------|------|-------------|
| id | INT | Primary key |
| company_name | VARCHAR(255) | Organization name |
| employee_name | VARCHAR(255) | Contact person |
| org_type | VARCHAR(100) | Company/Municipality/NGO |
| industry | VARCHAR(255) | Business sector |
| country | VARCHAR(100) | Location |
| working_desc | TEXT | Company description |
| num_employees | INT | Employee count |
| culture | VARCHAR(255) | Company culture |
| email | VARCHAR(255) | Login email |
| password | VARCHAR(255) | Hashed password |
| status | ENUM | pending/approved/rejected |
| manager_notes | TEXT | Manager comments |
| supporting_files | LONGTEXT | JSON file list |
| created_at | DATETIME | Registration date |

### Table: `wp_company_assessments`

| Column | Type | Description |
|--------|------|-------------|
| id | INT | Primary key |
| user_id | INT | Foreign key to registrations |
| assessment_json | LONGTEXT | All questionnaire answers |
| progress | INT | Current step (1-5) |
| submitted_at | DATETIME | Submission date |
| updated_at | DATETIME | Last update |

---

## 📧 **Support**

For issues or questions:
- **Email**: support@cleanindex.com
- **Documentation**: docs.cleanindex.com
- **GitHub**: github.com/cleanindex/portal

---

## 📝 **License**

Proprietary - © 2025 CleanIndex / Brnd Guru

---

## 🚀 **Next Steps**

1. ✅ Activate the plugin
2. ✅ Configure SMTP for emails
3. ✅ Create a Manager user account
4. ✅ Test registration flow
5. ✅ Customize email templates
6. ✅ Add your logo and branding

---

**Version**: 1.0  
**Last Updated**: October 31, 2025  
**Author**: Brnd Guru / Shivanshu