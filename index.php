<?php
require_once __DIR__ . '/session.php';
if (!isset($_SESSION['user_id'])) {
    header('Location: home.php');
    exit;
}
?>
<!DOCTYPE html>
<html lang="en">
<head>
  <meta charset="UTF-8" />
  <meta name="viewport" content="width=device-width, initial-scale=1.0, viewport-fit=cover"/>
  <meta name="theme-color" content="#08101F"/>
  <link rel="icon" type="image/png" href="favicon.png"/>
<title>Parish Youth Ministry – Attendance System</title>
  <link rel="stylesheet" href="style.css"/>
  <link rel="stylesheet" href="mobile.css"/>
  <link href="https://fonts.googleapis.com/css2?family=Inter:wght@300;400;500;600;700;800&family=Playfair+Display:wght@700;800&display=swap" rel="stylesheet"/>
  <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.5.0/css/all.min.css"/>
<style>
.cat-block{border:1.5px solid var(--border);border-radius:var(--radius);padding:16px;margin-bottom:16px;background:var(--surface)}
.cat-head{display:flex;align-items:center;justify-content:space-between;gap:10px;flex-wrap:wrap;margin-bottom:12px}
.cat-head h4{font-family:'Plus Jakarta Sans',sans-serif;font-size:15px;font-weight:800;display:flex;align-items:center;gap:8px}
.cat-actions{display:flex;gap:6px;align-items:center;flex-wrap:wrap}
.cat-note{font-size:12.5px;color:var(--text-soft);background:var(--surface-2);border:1.5px dashed var(--border-mid);border-radius:var(--radius-sm);padding:12px 14px}
.cat-badge{font-size:9.5px;font-weight:800;padding:3px 9px;border-radius:99px;background:var(--gold-pale);color:var(--amber);text-transform:uppercase;letter-spacing:.4px}
.cat-event-row{display:flex;align-items:center;gap:12px;padding:11px 13px;border-radius:var(--radius);background:var(--bg);border:1px solid var(--border);margin-bottom:8px}
.cat-event-row .event-details{flex:1;min-width:0}
.event-tag.category{background:#FEF3C7;color:#92400E}
.nav-section{font-size:9.5px;font-weight:800;letter-spacing:1.6px;text-transform:uppercase;color:rgba(255,255,255,.28);padding:16px 14px 6px}
.nav-section:first-child{padding-top:6px}
</style>
</head>
<body>

<!-- SIDEBAR -->
<aside class="sidebar" id="sidebar">
  <div class="sidebar-logo">
    <div class="logo-icon logo-img"><img src="logo.png" alt="PYM logo"/></div>
    <div class="logo-text">
      <span class="logo-title">PYM</span>
      <span class="logo-sub">Attendance</span>
    </div>
  </div>

  <div class="sidebar-user">
    <div class="user-avatar"><i class="fas fa-user-shield"></i></div>
    <div class="user-info">
      <span class="user-name">Welcome,</span>
      <span class="user-role-name" id="sidebarUserName">—</span>
      <span class="user-badge" id="sidebarUserRole">—</span>
    </div>
  </div>

  <nav class="sidebar-nav">
    <div class="nav-section">Overview</div>
    <a href="#" class="nav-item active" data-page="dashboard">
      <i class="fas fa-th-large"></i><span>Dashboard</span>
    </a>
    <a href="#" class="nav-item" data-page="attendance">
      <i class="fas fa-clipboard-check"></i><span>Attendance</span>
    </a>
    <div class="nav-section">Members</div>
    <a href="#" class="nav-item" data-page="youth">
      <i class="fas fa-users"></i><span>Youth Members</span>
    </a>
    <a href="#" class="nav-item" data-page="records">
      <i class="fas fa-file-excel"></i><span>Attendance Records</span>
    </a>
    <div class="nav-section leader-only">Ministry</div>
    <a href="#" class="nav-item leader-only" data-page="events">
      <i class="fas fa-calendar-alt"></i><span>Events</span>
    </a>
    <a href="#" class="nav-item leader-only" data-page="groups">
      <i class="fas fa-layer-group"></i><span>Groups / Slots</span>
    </a>
    <div class="nav-section secretariat-only">Administration</div>
    <a href="#" class="nav-item secretariat-only" data-page="admin">
      <i class="fas fa-shield-alt"></i><span>Admin Panel</span>
    </a>
    <a href="#" class="nav-item secretariat-only" data-page="users">
      <i class="fas fa-user-cog"></i><span>Users & Roles</span>
    </a>
    <div class="nav-section">Account</div>
    <a href="#" class="nav-item" data-page="settings">
      <i class="fas fa-cog"></i><span>Settings</span>
    </a>
  </nav>

  <div class="sidebar-footer">
    <a href="#" class="logout-btn" id="logoutBtn">
      <i class="fas fa-sign-out-alt"></i><span>Log Out</span>
    </a>
  </div>
</aside>

<!-- MAIN CONTENT -->
<main class="main-content" id="mainContent">

  <!-- TOPBAR -->
  <header class="topbar">
    <div class="topbar-left">
      <button class="hamburger" id="hamburger"><i class="fas fa-bars"></i></button>
      <div class="page-title-wrap">
        <h1 class="page-title">Dashboard</h1>
        <p class="page-subtitle">Secretary & Leaders can mark attendance</p>
      </div>
    </div>
    <div class="topbar-right">
      <div class="search-wrap">
        <i class="fas fa-search"></i>
        <input type="text" placeholder="Search members, events..."/>
      </div>
      <div class="topbar-avatar" title="Logged in user"><i class="fas fa-user-shield"></i></div>
    </div>
  </header>

  <!-- PAGE: DASHBOARD -->
  <div class="page active" id="page-dashboard">
    <section class="stats-grid">
      <div class="stat-card card-blue">
        <div class="stat-info">
          <p class="stat-label">Total Youth</p>
          <h2 class="stat-number" id="statTotalYouth">0</h2>
          <p class="stat-desc">All Registered Youth</p>
        </div>
        <div class="stat-icon"><i class="fas fa-users"></i></div>
      </div>
      <div class="stat-card card-green">
        <div class="stat-info">
          <p class="stat-label">Present Today</p>
          <h2 class="stat-number" id="statPresentToday">0</h2>
          <p class="stat-desc">Across all events</p>
        </div>
        <div class="stat-icon"><i class="fas fa-user-check"></i></div>
      </div>
      <div class="stat-card card-red">
        <div class="stat-info">
          <p class="stat-label">Absent Today</p>
          <h2 class="stat-number" id="statAbsentToday">0</h2>
          <p class="stat-desc">Across all events</p>
        </div>
        <div class="stat-icon"><i class="fas fa-user-times"></i></div>
      </div>
      <div class="stat-card card-gold">
        <div class="stat-info">
          <p class="stat-label">Upcoming Events</p>
          <h2 class="stat-number" id="statUpcomingEvents">0</h2>
          <p class="stat-desc">In the next 7 days</p>
        </div>
        <div class="stat-icon"><i class="fas fa-calendar-star"></i></div>
      </div>
    </section>

    <section class="mid-grid">
      <div class="card services-card">
        <div class="card-header">
          <div>
            <h3>Today's Services</h3>
            <p class="card-sub" id="todayDate">—</p>
          </div>
          <div class="legend">
            <span class="dot green"></span><small>Present</small>
            <span class="dot red"></span><small>Absent</small>
          </div>
        </div>
        <div class="service-list" id="servicesList">
          <p class="empty-msg">No events scheduled for today.</p>
        </div>
      </div>

      <div class="card recent-card">
        <div class="card-header">
          <div>
            <h3>Recent Attendance</h3>
            <p class="card-sub">Latest Check-ins</p>
          </div>
          <a href="#" class="view-all-link" data-page-link="attendance">View all</a>
        </div>
        <div class="recent-list" id="recentList">
          <p class="empty-msg">No attendance records yet.</p>
        </div>
      </div>
    </section>

    <section class="bottom-grid">
      <div class="card chart-card">
        <div class="card-header">
          <div>
            <h3>Weekly Attendance Overview</h3>
            <p class="card-sub">Last 7 days</p>
          </div>
        </div>
        <div class="chart-bars" id="chartBars"></div>
      </div>

      <div class="card events-card">
        <div class="card-header">
          <div>
            <h3>Upcoming Events</h3>
            <p class="card-sub">Next 7 days</p>
          </div>
        </div>
        <div class="event-list" id="dashboardEventList">
          <p class="empty-msg">No upcoming events.</p>
        </div>
      </div>

      <div class="card slot-card">
        <div class="card-header">
          <div>
            <h3>Slot Summary</h3>
            <p class="card-sub">Most recent service per slot</p>
          </div>
        </div>
        <div class="slot-list" id="slotList">
          <p class="empty-msg">No groups/slots created yet.</p>
        </div>
      </div>
    </section>
  </div>
  <!-- END DASHBOARD -->

  <!-- PAGE: YOUTH MEMBERS -->
  <div class="page" id="page-youth">
    <div class="card">
      <div class="card-header">
        <div>
          <h3>Youth Members</h3>
          <p class="card-sub">All registered youth of the parish</p>
        </div>
        <button class="add-btn" id="showYouthFormBtn"><i class="fas fa-plus"></i> Add Member</button>
      </div>
      <form id="youthForm" class="data-form hidden">
        <input type="hidden" name="id"/>
        <div class="form-row">
          <div class="form-field">
            <label>Full Name</label>
            <input type="text" name="full_name" required/>
          </div>
          <div class="form-field">
            <label>Status</label>
            <select name="status">
              <option value="active">Active</option>
              <option value="inactive">Inactive</option>
            </select>
          </div>
        </div>
        <p style="font-size:11px;color:var(--text-light);margin:-6px 0 10px;">Group/Slot will default to <strong>None</strong> and can be assigned later via Admin Panel.</p>
        <div class="form-actions">
          <button type="submit" class="auth-btn small">Save Member</button>
          <button type="button" class="cancel-btn" id="cancelYouthFormBtn">Cancel</button>
        </div>
      </form>
      <div class="table-wrap">
        <table class="data-table">
          <thead><tr><th>Name</th><th>Group/Slot</th><th>Status</th><th>Actions</th></tr></thead>
          <tbody id="youthTableBody"><tr><td colspan="5" class="empty-msg">Loading...</td></tr></tbody>
        </table>
      </div>
    </div>
  </div>

  <!-- PAGE: ATTENDANCE RECORDS (secretariat only) -->
  <div class="page" id="page-records">

    <div class="card rec-card">
      <div class="card-header">
        <div>
          <h3><i class="fas fa-file-excel" style="color:var(--emerald);margin-right:8px"></i>Attendance Records</h3>
          <p class="card-sub">Pick a date range and one or more categories, review the results, then download them as Excel</p>
        </div>
      </div>

      <div class="rec-filters">
        <div class="form-field">
          <label>From</label>
          <input type="date" id="recFrom"/>
        </div>
        <div class="form-field">
          <label>To</label>
          <input type="date" id="recTo"/>
        </div>
        <div class="form-field">
          <label>Status</label>
          <select id="recStatus">
            <option value="">All statuses</option>
            <option value="present">Present only</option>
            <option value="absent">Absent only (includes excused)</option>
            <option value="excused">Excused only</option>
          </select>
        </div>
      </div>

      <div class="rec-quick">
        <button type="button" data-range="month">This month</button>
        <button type="button" data-range="lastmonth">Last month</button>
        <button type="button" data-range="30">Last 30 days</button>
        <button type="button" data-range="90">Last 90 days</button>
        <button type="button" data-range="year">This year</button>
      </div>

      <div class="rec-label">
        <span>Event Categories <button type="button" class="rec-link" id="recSelectAll">Select all</button><button type="button" class="rec-link" id="recClearAll">Clear</button></span>
        <small id="recCatCount">—</small>
      </div>
      <div class="rec-chips" id="recCatChips"><span class="coy-empty">Loading categories...</span></div>

      <div class="rec-label">
        <span>Members
          <button type="button" class="rec-link" id="recMemAll">All members</button>
        </span>
        <small id="recMemCount">All members</small>
      </div>
      <div class="rec-members">
        <div class="rec-mem-top">
          <div class="rec-search"><i class="fas fa-search"></i><input type="text" id="recMemSearch" placeholder="Search a member to pick..." autocomplete="off"/></div>
          <button type="button" class="rec-link" id="recMemClear">Clear selection</button>
        </div>
        <div class="rec-mem-selected" id="recMemSelected"></div>
        <div class="rec-mem-list" id="recMemList"></div>
      </div>

      <div class="rec-actions">
        <button type="button" class="auth-btn small" id="recGenerateBtn"><i class="fas fa-magnifying-glass-chart"></i> Show Records</button>
      </div>
    </div>

    <div class="card hidden" id="recResults">
      <div class="card-header">
        <div>
          <h3>Results</h3>
          <p class="rec-summary-line" id="recSummaryLine" style="margin:4px 0 0">—</p>
        </div>
        <button type="button" class="rec-excel-btn" id="recDownloadBtn"><i class="fas fa-download"></i> Download Excel</button>
      </div>

      <div class="rec-stats">
        <div class="rec-stat blue"><b id="recStatTotal">0</b><span>Records</span></div>
        <div class="rec-stat green"><b id="recStatPresent">0</b><span>Present</span></div>
        <div class="rec-stat red"><b id="recStatAbsent">0</b><span>Absent (incl. excused)</span></div>
        <div class="rec-stat amber"><b id="recStatExcused">0</b><span>Of which excused</span></div>
        <div class="rec-stat gold"><b id="recStatRate">0%</b><span>Attendance rate</span></div>
      </div>

      <div class="rec-toolbar">
        <div class="rec-tabs">
          <button type="button" class="rec-tab active" data-view="records">Records</button>
          <button type="button" class="rec-tab" data-view="members">Member Summary</button>
          <button type="button" class="rec-tab" data-view="events">Event Summary</button>
        </div>
        <div class="rec-tools">
          <div class="rec-search"><i class="fas fa-search"></i><input type="text" id="recSearch" placeholder="Search name, event..." autocomplete="off"/></div>
        </div>
      </div>

      <div class="table-wrap">
        <table class="data-table">
          <thead id="recTableHead"></thead>
          <tbody id="recTableBody"></tbody>
        </table>
      </div>
      <div class="rec-pager" id="recPager"></div>
    </div>

  </div>

  <!-- PAGE: ATTENDANCE -->
  <div class="page" id="page-attendance">

    <!-- SLOT LEADER BANNER -->
    <div id="leaderSlotBanner" class="leader-slot-banner hidden">
      <i class="fas fa-layer-group"></i>
      <span>You are marking attendance for your slot: <strong id="leaderSlotName">—</strong></span>
    </div>

    <!-- CALENDAR PANEL -->
    <div class="card cal-card">
      <div class="card-header">
        <div>
          <h3>Normal Serve — Monthly Attendance Calendar</h3>
          <p class="card-sub" id="calMonthLabel">—</p>
        </div>
        <div class="cal-nav">
          <button class="cal-nav-btn" id="calPrevBtn"><i class="fas fa-chevron-left"></i></button>
          <span class="cal-month-display" id="calMonthDisplay">—</span>
          <button class="cal-nav-btn" id="calNextBtn"><i class="fas fa-chevron-right"></i></button>
        </div>
      </div>

      <div class="cal-grid-header">
        <div>Sun</div><div>Mon</div><div>Tue</div><div>Wed</div>
        <div>Thu</div><div>Fri</div><div class="weekend-col">Sat</div>
      </div>
      <div class="cal-grid" id="calGrid">
        <div class="cal-loading"><i class="fas fa-spinner fa-spin"></i> Loading...</div>
      </div>
    </div>

    <!-- ATTENDANCE MARKING PANEL -->
    <div class="card att-panel hidden" id="attendancePanel">
      <div class="card-header">
        <div>
          <h3 id="attPanelTitle">Mark Attendance</h3>
          <p class="card-sub" id="attPanelSub">—</p>
        </div>
        <div style="display:flex;gap:8px;align-items:center;flex-wrap:wrap;">
          <select id="attendanceEventSelect" style="border:1px solid var(--border);border-radius:8px;padding:8px 12px;font-size:13px;color:var(--text-dark);background:#fff;outline:none;min-width:220px;">
            <option value="">— Select Service Time —</option>
          </select>
          <button class="cal-nav-btn" id="closeAttPanel" title="Close"><i class="fas fa-times"></i></button>
        </div>
      </div>

      <div class="att-quick-actions">
        <button class="att-quick-btn present-all" id="markAllPresentBtn"><i class="fas fa-check-double"></i> Mark All Present</button>
        <button class="att-quick-btn absent-all" id="markAllAbsentBtn"><i class="fas fa-times"></i> Mark All Absent</button>
      </div>

      <div class="table-wrap">
        <table class="data-table">
          <thead><tr><th>Name</th><th>Group/Slot</th><th>Status</th><th>Time In</th></tr></thead>
          <tbody id="attendanceTableBody"><tr><td colspan="4" class="empty-msg">Select a service time above.</td></tr></tbody>
        </table>
      </div>
      <div class="form-actions">
        <button type="button" class="auth-btn small" id="saveAttendanceBtn">
          <i class="fas fa-save"></i> Save Attendance
        </button>
      </div>
    </div>

    <!-- CATEGORY EVENTS (secretariat only) -->
    <div class="card hidden" id="categoryEventsCard" style="margin-bottom:20px;">
      <div class="card-header">
        <div>
          <h3>Event Categories</h3>
          <p class="card-sub">Click an event to mark attendance (Secretariat only)</p>
        </div>
      </div>
      <div id="attendanceCategoriesList"></div>
    </div>

    <div class="card att-panel hidden" id="eventAttPanel">
      <div class="card-header">
        <div>
          <h3 id="evAttTitle">Event Attendance</h3>
          <p class="card-sub" id="evAttSub">—</p>
        </div>
        <button class="cal-nav-btn" id="closeEvAttPanel" title="Close"><i class="fas fa-times"></i></button>
      </div>
      <div class="att-quick-actions">
        <button class="att-quick-btn present-all" id="evMarkAllPresentBtn"><i class="fas fa-check-double"></i> Mark All Present</button>
        <button class="att-quick-btn absent-all" id="evMarkAllAbsentBtn"><i class="fas fa-times"></i> Mark All Absent</button>
      </div>
      <div class="table-wrap">
        <table class="data-table">
          <thead><tr><th>Name</th><th>Group/Slot</th><th>Status</th><th>Time In</th></tr></thead>
          <tbody id="evAttTableBody"><tr><td colspan="4" class="empty-msg">Select an event.</td></tr></tbody>
        </table>
      </div>
      <div class="form-actions">
        <button type="button" class="auth-btn small" id="saveEventAttendanceBtn"><i class="fas fa-save"></i> Save Attendance</button>
      </div>
    </div>

  </div>

  <!-- PAGE: EVENTS (view only for leaders; Secretariat manages in Admin Panel) -->
  <div class="page" id="page-events">
    <div class="card">
      <div class="card-header">
        <div>
          <h3>Events</h3>
          <p class="card-sub">Parish events and scheduled masses</p>
        </div>
        <button class="add-btn secretariat-only hidden" id="showEventFormBtn" style="display:none"><i class="fas fa-plus"></i> Add Event</button>
      </div>
      <form id="eventForm" class="data-form hidden">
        <input type="hidden" name="id"/>
        <div class="form-row">
          <div class="form-field">
            <label>Title</label>
            <input type="text" name="title" required/>
          </div>
          <div class="form-field">
            <label>Type</label>
            <select name="type">
              <option value="mass">Mass</option>
              <option value="youth">Youth Activity</option>
              <option value="bible">Bible Study</option>
              <option value="recollect">Recollection</option>
            </select>
          </div>
        </div>
        <div class="form-row">
          <div class="form-field">
            <label>Date</label>
            <input type="date" name="event_date" required/>
          </div>
          <div class="form-field">
            <label>Time</label>
            <input type="time" name="event_time"/>
          </div>
        </div>
        <div class="form-row">
          <div class="form-field">
            <label>Location</label>
            <input type="text" name="location"/>
          </div>
        </div>
        <div class="form-actions">
          <button type="submit" class="auth-btn small">Save Event</button>
          <button type="button" class="cancel-btn" id="cancelEventFormBtn">Cancel</button>
        </div>
      </form>
      <div class="table-wrap">
        <table class="data-table">
          <thead>
            <tr>
              <th>Title</th><th>Date</th><th>Time</th><th>Location</th><th>Type</th>
              <th class="secretariat-only" id="eventsActionHeader">Actions</th>
            </tr>
          </thead>
          <tbody id="eventsTableBody"><tr><td colspan="6" class="empty-msg">Loading...</td></tr></tbody>
        </table>
      </div>
    </div>
  </div>

  <!-- PAGE: GROUPS / SLOTS (view only for leaders; Secretariat manages in Admin Panel) -->
  <div class="page" id="page-groups">
    <div class="card">
      <div class="card-header">
        <div>
          <h3>Groups / Slots</h3>
          <p class="card-sub">Mass slot groups and their leaders</p>
        </div>
      </div>
      <form id="slotForm" class="data-form hidden">
        <input type="hidden" name="id"/>
        <div class="form-row">
          <div class="form-field">
            <label>Slot Name</label>
            <input type="text" name="name" disabled/>
          </div>
          <div class="form-field">
            <label>Color</label>
            <input type="color" name="color" value="#4F8EF7"/>
          </div>
        </div>
        <div class="form-row">
          <div class="form-field">
            <label>Slot Leaders <small>(max 5)</small></label>
            <div class="leader-checklist" id="slotLeaderSelect"></div>
          </div>
        </div>
        <div class="form-actions">
          <button type="submit" class="auth-btn small">Save Slot</button>
          <button type="button" class="cancel-btn" id="cancelSlotFormBtn">Cancel</button>
        </div>
      </form>
      <div class="table-wrap">
        <table class="data-table">
          <thead>
            <tr>
              <th>Slot</th><th>Leader</th><th>Members</th>
              <th class="secretariat-only" id="slotsActionHeader">Actions</th>
            </tr>
          </thead>
          <tbody id="slotsTableBody"><tr><td colspan="4" class="empty-msg">Loading...</td></tr></tbody>
        </table>
      </div>
    </div>
  </div>

  <!-- PAGE: ADMIN PANEL (secretariat only) -->
  <div class="page" id="page-admin">
    <div class="admin-tabs-wrap">
      <button class="admin-tab active" data-admin-tab="admin-events">
        <i class="fas fa-calendar-alt"></i> Manage Events
      </button>
      <button class="admin-tab" data-admin-tab="admin-slots">
        <i class="fas fa-layer-group"></i> Manage Groups / Slots
      </button>
      <button class="admin-tab" data-admin-tab="admin-categories">
        <i class="fas fa-tags"></i> Event Categories
      </button>
      <button class="admin-tab" data-admin-tab="admin-insert">
        <i class="fas fa-file-import"></i> Insert Members
      </button>
    </div>

    <!-- ADMIN: EVENTS -->
    <div class="admin-section active" id="admin-events">
      <div class="card">
        <div class="card-header">
          <div>
            <h3>Events Management</h3>
            <p class="card-sub">Add, edit, or delete parish events</p>
          </div>
          <button class="add-btn" id="adminShowEventFormBtn"><i class="fas fa-plus"></i> Add Event</button>
        </div>
        <form id="adminEventForm" class="data-form hidden">
          <input type="hidden" name="id"/>
          <div class="form-row">
            <div class="form-field">
              <label>Title</label>
              <input type="text" name="title" required/>
            </div>
            <div class="form-field">
              <label>Type</label>
              <select name="type">
                <option value="mass">Mass</option>
                <option value="youth">Youth Activity</option>
                <option value="bible">Bible Study</option>
                <option value="recollect">Recollection</option>
              </select>
            </div>
          </div>
          <div class="form-row">
            <div class="form-field">
              <label>Date</label>
              <input type="date" name="event_date" required/>
            </div>
            <div class="form-field">
              <label>Time</label>
              <input type="time" name="event_time"/>
            </div>
          </div>
          <div class="form-row">
            <div class="form-field">
              <label>Location</label>
              <input type="text" name="location"/>
            </div>
          </div>
          <div class="form-actions">
            <button type="submit" class="auth-btn small">Save Event</button>
            <button type="button" class="cancel-btn" id="adminCancelEventFormBtn">Cancel</button>
          </div>
        </form>
        <div class="table-wrap">
          <table class="data-table">
            <thead><tr><th>Title</th><th>Date</th><th>Time</th><th>Location</th><th>Category</th><th>Actions</th></tr></thead>
            <tbody id="adminEventsTableBody"><tr><td colspan="6" class="empty-msg">Loading...</td></tr></tbody>
          </table>
        </div>
      </div>
    </div>

    <!-- ADMIN: GROUPS/SLOTS -->
    <div class="admin-section" id="admin-slots">
      <div class="card">
        <div class="card-header">
          <div>
            <h3>Groups / Slots Management</h3>
            <p class="card-sub">Assign leaders and a color to each of the 6 fixed schedule slots</p>
          </div>
        </div>
        <form id="adminSlotForm" class="data-form hidden">
          <input type="hidden" name="id"/>
          <div class="form-row">
            <div class="form-field">
              <label>Slot Name</label>
              <input type="text" name="name" disabled/>
            </div>
            <div class="form-field">
              <label>Color</label>
              <input type="color" name="color" value="#4F8EF7"/>
            </div>
          </div>
          <div class="form-row">
            <div class="form-field">
              <label>Slot Leaders <small>(max 5)</small></label>
              <div class="leader-checklist" id="adminSlotLeaderSelect"></div>
            </div>
          </div>
          <div class="form-actions">
            <button type="submit" class="auth-btn small">Save Slot</button>
            <button type="button" class="cancel-btn" id="adminCancelSlotFormBtn">Cancel</button>
          </div>
        </form>
        <div class="table-wrap">
          <table class="data-table">
            <thead><tr><th>Slot</th><th>Leader</th><th>Members</th><th>Actions</th></tr></thead>
            <tbody id="adminSlotsTableBody"><tr><td colspan="4" class="empty-msg">Loading...</td></tr></tbody>
          </table>
        </div>
      </div>
    </div>

    <!-- ADMIN: EVENT CATEGORIES -->
    <div class="admin-section" id="admin-categories">
      <div class="card">
        <div class="card-header">
          <div>
            <h3>Event Categories</h3>
            <p class="card-sub">Add categories, rename them, and add events under each category. Events appear in Attendance.</p>
          </div>
          <button class="add-btn" id="showCategoryFormBtn"><i class="fas fa-plus"></i> Add Category</button>
        </div>
        <form id="categoryForm" class="data-form hidden">
          <input type="hidden" name="id"/>
          <div class="form-row">
            <div class="form-field">
              <label>Category Name</label>
              <input type="text" name="name" required/>
            </div>
          </div>
          <div class="form-actions">
            <button type="submit" class="auth-btn small">Save Category</button>
            <button type="button" class="cancel-btn" id="cancelCategoryFormBtn">Cancel</button>
          </div>
        </form>
        <form id="categoryEventForm" class="data-form hidden">
          <input type="hidden" name="id"/>
          <input type="hidden" name="category_id"/>
          <p class="report-subtitle" id="categoryEventFormTitle" style="margin-top:0">Add Event</p>
          <div class="form-row">
            <div class="form-field">
              <label>Event Title</label>
              <input type="text" name="title" required/>
            </div>
            <div class="form-field">
              <label>Location</label>
              <input type="text" name="location"/>
            </div>
          </div>
          <div class="form-row">
            <div class="form-field">
              <label>Date</label>
              <input type="date" name="event_date" required/>
            </div>
            <div class="form-field">
              <label>Time</label>
              <input type="time" name="event_time"/>
            </div>
          </div>
          <div class="form-actions">
            <button type="submit" class="auth-btn small">Save Event</button>
            <button type="button" class="cancel-btn" id="cancelCategoryEventFormBtn">Cancel</button>
          </div>
        </form>
        <div id="categoriesList"><p class="empty-msg">Loading...</p></div>
      </div>
    </div>

    <!-- ADMIN: INSERT MEMBERS -->
    <div class="admin-section" id="admin-insert">
    <div class="card">
      <div class="card-header">
        <div>
          <h3><i class="fas fa-file-import" style="color:var(--gold);margin-right:8px;"></i>Insert Members from Excel</h3>
          <p class="card-sub">Paste copied cells from Excel — Column 1: Full Name &nbsp;|&nbsp; Column 2: Status (active/inactive, optional)</p>
        </div>
      </div>

      <div id="insertMessage" class="insert-msg"></div>

      <div class="insert-step">
        <div class="insert-step-label"><span class="insert-step-num">1</span> Paste your Excel data below</div>
        <p class="insert-hint">Copy cells from Excel (2 columns: <strong>Full Name</strong>, <strong>Status</strong>) and paste. No headers needed. Group/Slot will be set to <em>None</em> by default.</p>
        <div class="insert-example">
          <span class="insert-example-label">Example:</span>
          <table class="insert-example-table">
            <tr><td>Juan Dela Cruz</td><td>active</td></tr>
            <tr><td>Maria Santos</td><td>active</td></tr>
            <tr><td>Pedro Reyes</td><td>inactive</td></tr>
          </table>
        </div>
        <textarea id="insertExcelText" class="insert-textarea" placeholder="Paste your Excel data here...
Example:
Juan Dela Cruz&#9;active
Maria Santos&#9;active
Pedro Reyes&#9;inactive"></textarea>
        <div class="form-actions" style="margin-top:12px;">
          <button class="auth-btn small" id="insertPreviewBtn"><i class="fas fa-eye"></i> Preview Data</button>
          <button class="cancel-btn" id="insertClearBtn"><i class="fas fa-times"></i> Clear</button>
        </div>
      </div>

      <div id="insertPreviewSection" class="insert-step hidden" style="margin-top:24px;">
        <div class="insert-step-label"><span class="insert-step-num">2</span> Review before importing</div>
        <div class="insert-count-badge" id="insertCountBadge">0 members ready</div>
        <div class="table-wrap" style="margin-top:12px;">
          <table class="data-table">
            <thead><tr><th>#</th><th>Full Name</th><th>Status</th><th>Group/Slot</th></tr></thead>
            <tbody id="insertPreviewBody"></tbody>
          </table>
        </div>
        <div class="form-actions" style="margin-top:16px;">
          <button class="auth-btn small" id="insertSaveBtn"><i class="fas fa-file-import"></i> Import Members</button>
        </div>
      </div>
    </div>
    </div>

  </div>
  <!-- END ADMIN PANEL -->

  <!-- PAGE: USERS & ROLES -->
  <div class="page" id="page-users">
    <div class="card hidden" id="pendingUsersSection" style="margin-bottom:20px;">
      <div class="card-header">
        <div>
          <h3>Pending Account Approvals</h3>
          <p class="card-sub">New registrations awaiting Secretariat confirmation</p>
        </div>
      </div>
      <div class="table-wrap">
        <table class="data-table">
          <thead><tr><th>Full Name</th><th>Username</th><th>Requested Role</th><th>Registered</th><th>Actions</th></tr></thead>
          <tbody id="pendingUsersBody"><tr><td colspan="5" class="empty-msg">Loading...</td></tr></tbody>
        </table>
      </div>
    </div>
    <div class="card">
      <div class="card-header">
        <div>
          <h3>Users & Roles</h3>
          <p class="card-sub">Manage system users and permissions</p>
        </div>
        <button class="add-btn" id="showUserFormBtn"><i class="fas fa-plus"></i> Add User</button>
      </div>
      <form id="userForm" class="data-form hidden">
        <input type="hidden" name="id"/>
        <div class="form-row">
          <div class="form-field">
            <label>Full Name</label>
            <input type="text" name="full_name" required/>
          </div>
          <div class="form-field">
            <label>Username</label>
            <input type="text" name="username" required/>
          </div>
        </div>
        <div class="form-row">
          <div class="form-field">
            <label>Password <small>(leave blank to keep current)</small></label>
            <input type="password" name="password"/>
          </div>
          <div class="form-field">
            <label>Role</label>
            <select name="role">
              <option value="leader">Slot Leader</option>
              <option value="secretariat">Secretariat</option>
            </select>
          </div>
        </div>
        <div class="form-row">
          <div class="form-field">
            <label>Status</label>
            <select name="status">
              <option value="approved">Approved</option>
              <option value="pending">Pending</option>
              <option value="rejected">Rejected</option>
            </select>
          </div>
        </div>
        <div class="form-actions">
          <button type="submit" class="auth-btn small">Save User</button>
          <button type="button" class="cancel-btn" id="cancelUserFormBtn">Cancel</button>
        </div>
      </form>
      <div class="table-wrap">
        <table class="data-table">
          <thead><tr><th>Full Name</th><th>Username</th><th>Role</th><th>Status</th><th>Created</th><th>Actions</th></tr></thead>
          <tbody id="usersTableBody"><tr><td colspan="6" class="empty-msg">Loading...</td></tr></tbody>
        </table>
      </div>
    </div>
  </div>

  <!-- PAGE: SETTINGS -->
  <div class="page" id="page-settings">
    <div class="card">
      <div class="card-header">
        <div>
          <h3>Account Settings</h3>
          <p class="card-sub">Your current session</p>
        </div>
      </div>
      <div class="settings-info">
        <p><strong>Full Name:</strong> <span id="settingsFullName">—</span></p>
        <p><strong>Username:</strong> <span id="settingsUsername">—</span></p>
        <p><strong>Role:</strong> <span id="settingsRole">—</span></p>
      </div>
      <div class="form-actions">
        <button type="button" class="cancel-btn" id="settingsLogoutBtn">
          <i class="fas fa-sign-out-alt"></i> Log Out
        </button>
      </div>
    </div>
  </div>

</main>

<nav class="bottom-nav" aria-label="Main">
  <button type="button" class="bn-item" data-page="dashboard"><i class="fas fa-th-large"></i><span>Home</span></button>
  <button type="button" class="bn-item" data-page="attendance"><i class="fas fa-clipboard-check"></i><span>Attendance</span></button>
  <button type="button" class="bn-item" data-page="youth"><i class="fas fa-users"></i><span>Youth</span></button>
  <button type="button" class="bn-item" data-page="records"><i class="fas fa-file-excel"></i><span>Records</span></button>
  <button type="button" class="bn-item bn-more"><i class="fas fa-bars"></i><span>More</span></button>
</nav>
<div class="overlay" id="overlay"></div>
<script src="https://cdnjs.cloudflare.com/ajax/libs/xlsx/0.18.5/xlsx.full.min.js"></script>
<script src="app.js"></script>
<script src="coy.js"></script>
<script src="export.js?v=3"></script>
<script src="records.js"></script>
<script src="excused.js"></script>
<script src="mobile.js"></script>
<script src="activity.js"></script>
<script src="extras.js"></script>
<script src="servers.js"></script>
</body>
</html>
