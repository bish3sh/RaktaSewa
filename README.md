# 🩸 Raktasewa - Blood Bank Management System

## Introduction

Raktasewa is a modern, full-featured Blood Bank Management System designed to streamline the process of blood donation, inventory management, and emergency response. It connects donors, hospitals, and administrators through a unified digital platform, making blood donation safer, faster, and more efficient.

## Significance

Blood shortages are a persistent global health challenge, often resulting in preventable loss of life. Traditional blood bank systems are fragmented, slow, and lack real-time data sharing. Raktasewa addresses these issues by providing a centralized, digital solution that empowers all stakeholders—donors, hospitals, and administrators—to act quickly and collaboratively, especially during emergencies.

## Objectives

- **Save Lives:** Accelerate the process of matching donors with patients in need.
- **Increase Efficiency:** Automate and digitize blood inventory, requests, and donor management.
- **Enhance Transparency:** Provide real-time data on blood availability and requests.
- **Promote Engagement:** Encourage regular donations through rewards, campaigns, and notifications.
- **Ensure Security:** Protect sensitive data with robust authentication and role-based access.

## Features

- **Multi-role Access:** Separate dashboards for Admin, Hospital, and Donor users.
- **Real-time Blood Inventory:** Live tracking of blood units by type and location.
- **Emergency Requests:** Instant alerts for critical blood needs, filtered by donor compatibility.
- **Donation Scheduling:** Book appointments at nearby hospitals with available stock.
- **Campaign Management:** Organize and join blood drives, track progress, and register participation.
- **Rewards & Recognition:** Earn points, badges, and redeemable rewards for donations.
- **Comprehensive Analytics:** Visual dashboards for trends, statistics, and activity logs.
- **Mobile-Responsive Design:** Fully functional on desktop and mobile devices.
- **Robust Security:** Session validation, input sanitization, and role-based permissions.
- **Graceful Error Handling:** User-friendly messages and fallback mechanisms.

## Problem Statement

Blood banks often struggle with outdated, manual processes that lead to delays, miscommunication, and wasted resources. Donors are not always aware of urgent needs, and hospitals may lack up-to-date information on blood inventory. There is a critical need for a digital platform that bridges these gaps, enabling real-time coordination and efficient management of blood resources.

## Scope & Limitations

### Scope

- Designed for use by hospitals, blood banks, and individual donors.
- Supports core workflows: registration, authentication, inventory management, emergency requests, and campaign participation.
- Modular architecture allows for future enhancements (e.g., mobile app, AI-powered scheduling, multi-language support).

### Limitations

- Requires internet connectivity and a modern web browser.
- Initial deployment assumes a single-country setup; multi-country support is planned for future versions.
- Integration with external hospital systems and IoT devices is not included in the current release.
- Real-time notifications (e.g., via SMS or push) are roadmap features, not present in v1.0.

# 🩸 Raktasewa - Blood Bank Management System

![Raktasewa Banner](https://img.shields.io/badge/Raktasewa-Blood%20Bank%20Management-dc3545?style=for-the-badge&logo=heart&logoColor=white)

**Every Drop Counts, Every Life Matters**

Raktasewa is a comprehensive Blood Bank Management System designed to streamline blood donation processes, connect donors with hospitals, and save lives through efficient blood inventory management.

## 🚀 Quick Deployment

### For New Installation:

1. **Install XAMPP** from [apachefriends.org](https://www.apachefriends.org/)
2. **Copy Raktasewa folder** to `C:\xampp\htdocs\`
3. **Import database**: Use `sql/bloodbank_complete.sql` in phpMyAdmin
4. **Run setup check**: Double-click `setup_check.bat` (Windows) or `./setup_check.sh` (Linux/Mac)
5. **Access system**: Open `http://localhost/Raktasewa`

📖 **Complete guide**: See [DEPLOYMENT_GUIDE.md](DEPLOYMENT_GUIDE.md) for detailed instructions.

## 🌟 Features

### 👥 Three User Roles

- **🛡️ Admin**: Complete system oversight and management
- **🏥 Hospital**: Blood inventory and donor management
- **❤️ Donor**: Donation scheduling and reward tracking

### 🎯 Core Functionality

- ✅ **Real-time Blood Inventory Tracking**
- ✅ **Emergency Blood Request System**
- ✅ **Donation Scheduling & Management**
- ✅ **Comprehensive Rewards Program**
- ✅ **Multi-role Authentication System**
- ✅ **Responsive Mobile-Friendly Design**
- ✅ **Advanced Reporting & Analytics**
- ✅ **Notification System**
- ✅ **Campaign Management**

## 🏗️ Technology Stack

### Frontend

- **HTML5** - Semantic markup
- **CSS3** - Modern styling with Flexbox/Grid
- **JavaScript (ES6+)** - Interactive functionality
- **Font Awesome 6** - Icons
- **SweetAlert2** - Beautiful alerts

### Backend

- **PHP 8.0+** - Server-side logic
- **MySQL 8.0+** - Database management
- **PDO** - Database abstraction layer

### Development Environment

- **XAMPP** - Local development server
- **Apache** - Web server
- **phpMyAdmin** - Database administration

## 📁 Project Structure