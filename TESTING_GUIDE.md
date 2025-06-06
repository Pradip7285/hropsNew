# 🧪 Interview Management System - Testing Guide

## 🚀 Getting Started

### Prerequisites
1. ✅ XAMPP is running (Apache & MySQL)
2. ✅ Database is set up and accessible
3. ✅ You can access: http://localhost/hrops

### Quick Setup
1. **Set up test data**: Visit `http://localhost/hrops/test_setup.php`
2. **Login**: Use admin credentials (username: `admin`, password: `admin123`)
3. **Start testing**: Follow the scenarios below

---

## 📋 Testing Scenarios

### 1. 🏠 Dashboard Testing

**URL**: `http://localhost/hrops/dashboard.php`

**What to test**:
- [ ] Dashboard loads successfully
- [ ] Interview statistics are displayed
- [ ] Quick action buttons work
- [ ] Charts render properly
- [ ] Recent activity shows interview events

**Expected Results**:
- Statistics show: 2 interviews today, 8 total interviews
- Pipeline chart displays candidate distribution
- Quick actions navigate to correct pages

---

### 2. 📋 Interview List Management

**URL**: `http://localhost/hrops/interviews/list.php`

**Test Cases**:

#### 2.1 Basic List View
- [ ] All test interviews are displayed
- [ ] Status badges show correct colors
- [ ] Pagination works (if more than 20 interviews)
- [ ] Interview details are accurate

#### 2.2 Search & Filtering
- [ ] **Search test**: Search for "John" → Should find John Smith
- [ ] **Status filter**: Filter by "Scheduled" → Should show upcoming interviews
- [ ] **Date filter**: Select "Today" → Should show 2 interviews
- [ ] **Interviewer filter**: Select your name → Should show assigned interviews
- [ ] **Clear filters**: Reset all filters → Should show all interviews

#### 2.3 Bulk Actions
- [ ] Select multiple interviews using checkboxes
- [ ] Try bulk status updates
- [ ] Verify changes are applied correctly

**Expected Results**:
- List shows 8 interviews total
- Filters work correctly and reset properly
- Status changes are saved and reflected immediately

---

### 3. 📅 Today's Interviews

**URL**: `http://localhost/hrops/interviews/today.php`

**Test Cases**:
- [ ] Shows 2 interviews scheduled for today
- [ ] One interview should be in ~2 hours (John Smith)
- [ ] One interview should be in ~4 hours (Sarah Johnson)
- [ ] Status updates work in real-time
- [ ] Emergency contact info is accessible

**Expected Results**:
- Real-time countdown to interview times
- Quick status change buttons work
- Professional layout with priority indicators

---

### 4. ➕ Interview Scheduling

**URL**: `http://localhost/hrops/interviews/schedule.php`

**Test Cases**:

#### 4.1 Basic Scheduling
- [ ] **Select candidate**: Choose an existing candidate
- [ ] **Choose job**: Select appropriate job posting
- [ ] **Set date/time**: Pick a future date/time
- [ ] **Select interviewer**: Choose interviewer (admin)
- [ ] **Set details**: Add location, duration, meeting link

#### 4.2 Conflict Detection
- [ ] Try to schedule at the same time as existing interview
- [ ] System should warn about conflicts
- [ ] Suggest alternative time slots

#### 4.3 Advanced Features
- [ ] **Auto-generated meeting link**: Check if Zoom/Teams link is created
- [ ] **Smart suggestions**: See if system suggests optimal times
- [ ] **Email notifications**: Verify notifications are logged (check console/logs)

**Expected Results**:
- Form validation works properly
- Conflicts are detected and alternatives suggested
- Interview is saved and appears in list
- Notifications are triggered

---

### 5. 📝 Interview Feedback

**URL**: `http://localhost/hrops/interviews/feedback.php?interview_id=X`

**Test Cases**:

#### 5.1 Access Completed Interview
- [ ] Go to interview list and find a "Completed" interview
- [ ] Click "Feedback" button
- [ ] Form should load with interview details

#### 5.2 Feedback Form Testing
- [ ] **Rating system**: Test all rating categories (1-5 stars)
- [ ] **Text areas**: Fill in strengths, weaknesses, notes
- [ ] **Recommendation**: Select hire/no-hire recommendation
- [ ] **Bias detection**: Try typing words like "aggressive" or "emotional"

#### 5.3 Draft Saving
- [ ] Fill partial form
- [ ] Click "Save Draft" button
- [ ] Refresh page → Draft should be loaded automatically
- [ ] Check browser localStorage for backup

#### 5.4 Auto-save Feature
- [ ] Start filling form
- [ ] Wait 30 seconds
- [ ] Check console for auto-save messages

**Expected Results**:
- All ratings and text are saved properly
- Bias detection triggers warnings for problematic language
- Draft saves successfully and reloads on page refresh
- Final submission creates feedback record

---

### 6. 📆 Calendar View

**URL**: `http://localhost/hrops/interviews/calendar.php`

**Test Cases**:

#### 6.1 Calendar Navigation
- [ ] **Day view**: Switch to daily view
- [ ] **Week view**: Switch to weekly view  
- [ ] **Month view**: Switch to monthly view
- [ ] **Navigation**: Use prev/next arrows to navigate dates

#### 6.2 Interview Display
- [ ] Interviews show up on correct dates
- [ ] Color coding works (scheduled=blue, completed=green, etc.)
- [ ] Click on interview shows modal with details

#### 6.3 Interactive Features
- [ ] **Modal details**: Click interview → Modal opens with full details
- [ ] **Quick actions**: Edit/Delete buttons work in modal
- [ ] **Status updates**: Change status from calendar view

**Expected Results**:
- Calendar renders properly in all views
- Interviews appear on correct dates with proper colors
- Modal interactions work smoothly
- Real-time updates reflect immediately

---

### 7. 🔔 Reminder System

**URL**: `http://localhost/hrops/interviews/reminders.php`

**Test Cases**:

#### 7.1 Upcoming Interviews
- [ ] Shows interviews in next 48 hours
- [ ] Prioritizes by urgency (color coding)
- [ ] Displays accurate time remaining

#### 7.2 Individual Reminders
- [ ] Click "Remind Candidate" button
- [ ] Add custom message (optional)
- [ ] Send reminder
- [ ] Check confirmation message

#### 7.3 Bulk Reminders
- [ ] Select multiple interviews
- [ ] Choose reminder type (candidate/interviewer)
- [ ] Send bulk reminders
- [ ] Verify success count

**Expected Results**:
- Upcoming interviews listed by priority
- Individual reminders process successfully
- Bulk actions work for multiple selections
- Email notifications are logged (check console)

---

### 8. ❓ Question Bank

**URL**: `http://localhost/hrops/interviews/questions.php`

**Test Cases**:

#### 8.1 Question Management
- [ ] View existing sample questions
- [ ] Filter by category/type/difficulty
- [ ] Add new custom question
- [ ] Edit existing question

#### 8.2 Template Creation
- [ ] Create interview template
- [ ] Select multiple questions for template
- [ ] Save and test template usage

**Expected Results**:
- Question bank displays 3 sample questions
- Filtering works correctly
- New questions save properly
- Templates can be created and reused

---

### 9. 📊 Analytics & Reports

**URL**: `http://localhost/hrops/interviews/reports.php`

**Test Cases**:

#### 9.1 Statistics Dashboard
- [ ] Overall interview statistics load
- [ ] Success rate calculations are correct
- [ ] Charts render properly (if using Chart.js)

#### 9.2 Interviewer Performance
- [ ] View interviewer metrics
- [ ] Check completion rates
- [ ] Review feedback quality scores

#### 9.3 Time-based Analysis
- [ ] Daily volume charts
- [ ] Time slot popularity
- [ ] Department-wise breakdown

**Expected Results**:
- All statistics calculate correctly
- Charts display meaningful data
- Performance metrics are accurate
- Time-based analysis shows trends

---

### 10. ✏️ Interview Editing

**URL**: Access via "Edit" button in interview list

**Test Cases**:

#### 10.1 Basic Editing
- [ ] Open existing interview for editing
- [ ] Change date/time
- [ ] Update location/meeting link
- [ ] Modify notes/details

#### 10.2 Rescheduling
- [ ] Change interview time
- [ ] Check for new conflicts
- [ ] Save changes
- [ ] Verify notifications are sent

**Expected Results**:
- All fields are editable
- Validation prevents conflicts
- Changes save successfully
- Audit trail is maintained

---

## 🚨 Error Testing

### Test Error Scenarios

#### 11.1 Invalid Data
- [ ] Try scheduling interview in the past
- [ ] Submit feedback without required ratings
- [ ] Create interview with invalid email

#### 11.2 Permission Testing
- [ ] Test access control (if implemented)
- [ ] Try accessing admin functions as regular user

#### 11.3 Data Integrity
- [ ] Delete candidate with existing interviews
- [ ] Test foreign key constraints
- [ ] Verify data consistency

---

## 📱 Mobile Responsiveness Testing

### Test on Different Screen Sizes

#### 12.1 Mobile View (< 768px)
- [ ] Dashboard layout adapts properly
- [ ] Interview lists are scrollable
- [ ] Forms are usable on mobile
- [ ] Calendar view works on small screens

#### 12.2 Tablet View (768px - 1024px)
- [ ] Sidebar collapses appropriately
- [ ] Tables remain readable
- [ ] Charts scale properly

---

## 🔧 Performance Testing

### Test System Performance

#### 13.1 Load Testing
- [ ] Create 50+ interviews and test list performance
- [ ] Test calendar with many events
- [ ] Check search performance with large datasets

#### 13.2 Browser Testing
- [ ] Test in Chrome
- [ ] Test in Firefox
- [ ] Test in Edge
- [ ] Check for JavaScript errors in console

---

## ✅ Testing Checklist

Use this checklist to track your testing progress:

### Core Functionality
- [ ] Dashboard loads and displays correctly
- [ ] Interview list shows all interviews
- [ ] Scheduling works with conflict detection
- [ ] Feedback system saves drafts and submissions
- [ ] Calendar displays interviews properly
- [ ] Reminders can be sent
- [ ] Questions bank is functional
- [ ] Reports generate correctly

### Advanced Features
- [ ] Bias detection works in feedback
- [ ] Auto-save functionality operates
- [ ] Email notifications are triggered
- [ ] AJAX operations work smoothly
- [ ] Mobile responsiveness is good
- [ ] Performance is acceptable

### Data Integrity
- [ ] All CRUD operations work
- [ ] Foreign key relationships maintained
- [ ] Validation prevents bad data
- [ ] Error handling is graceful

---

## 🐛 Bug Reporting

When you find issues, note:

1. **What were you doing?** (steps to reproduce)
2. **What did you expect?** (expected behavior)
3. **What actually happened?** (actual behavior)
4. **Browser/device info** (Chrome, mobile, etc.)
5. **Error messages** (from console or screen)

---

## 🎯 Next Steps

After testing Phase 2:
1. ✅ Verify all interview management features work
2. 🐛 Report any bugs found
3. 📊 Review system performance
4. 🚀 Prepare for Phase 3 (Offer Management & Employee Onboarding)

---

**Happy Testing! 🧪** 