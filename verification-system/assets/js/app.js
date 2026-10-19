/* Verification System – Single Page Frontend Application Engine */

(function () {
	'use strict';

	const App = {
		config: window.VS_CONFIG || {},
		state: {
			route: 'login',
			user: null,
			dashboardData: null,
			branches: [],
			employees: [],
			reportsData: null,
			auditLogs: [],
		},

		init: function () {
			this.state.user = this.config.user;
			this.state.route = this.config.is_logged_in ? 'dashboard' : 'login';
			this.render();
		},

		render: function () {
			const loader = document.getElementById('vs-app-loader');
			const viewport = document.getElementById('vs-app-viewport');

			if (loader) loader.classList.add('hidden');
			if (!viewport) return;

			viewport.classList.remove('hidden');

			if (!this.config.is_logged_in) {
				viewport.innerHTML = this.templateLogin();
				this.bindLoginEvents();
				return;
			}

			let navHtml = this.templateNavbar();
			let mainHtml = '';

			switch (this.state.route) {
				case 'dashboard':
					mainHtml = this.templateDashboard();
					break;
				case 'employees':
					mainHtml = this.templateEmployees();
					break;
				case 'branches':
					mainHtml = this.templateBranches();
					break;
				case 'reports':
					mainHtml = this.templateReports();
					break;
				case 'audit':
					mainHtml = this.templateAuditLogs();
					break;
				default:
					mainHtml = this.templateDashboard();
			}

			viewport.innerHTML = navHtml + '<div class="vs-container">' + mainHtml + '</div>';
			this.bindAppEvents();

			// Initial data load for route
			if (this.state.route === 'dashboard' && !this.state.dashboardData) {
				this.fetchDashboard();
			} else if (this.state.route === 'employees' && this.state.employees.length === 0) {
				this.fetchEmployees();
			} else if (this.state.route === 'branches' && this.state.branches.length === 0) {
				this.fetchBranches();
			} else if (this.state.route === 'reports' && !this.state.reportsData) {
				this.fetchReports();
			} else if (this.state.route === 'audit' && this.state.auditLogs.length === 0) {
				this.fetchAuditLogs();
			}
		},

		templateNavbar: function () {
			const role = this.state.user ? this.state.user.role : '';
			const isManager = (role === 'vs_system_admin' || role === 'vs_general_manager' || role === 'vs_branch_manager');

			return `
				<div class="vs-navbar">
					<div class="vs-brand">
						<div class="vs-brand-logo">VS</div>
						<span>Verification System</span>
					</div>
					<div class="vs-nav-links">
						<button class="vs-nav-btn ${this.state.route === 'dashboard' ? 'active' : ''}" data-route="dashboard">Dashboard</button>
						${isManager ? `<button class="vs-nav-btn ${this.state.route === 'employees' ? 'active' : ''}" data-route="employees">Employees</button>` : ''}
						${isManager ? `<button class="vs-nav-btn ${this.state.route === 'branches' ? 'active' : ''}" data-route="branches">Branches</button>` : ''}
						<button class="vs-nav-btn ${this.state.route === 'reports' ? 'active' : ''}" data-route="reports">Reports</button>
						${isManager ? `<button class="vs-nav-btn ${this.state.route === 'audit' ? 'active' : ''}" data-route="audit">Audit Logs</button>` : ''}
					</div>
					<div class="vs-user-meta">
						<div class="vs-avatar">${this.state.user && this.state.user.name ? this.state.user.name.charAt(0).toUpperCase() : 'U'}</div>
						<button id="vs-logout-btn" class="vs-btn vs-btn-secondary" style="padding: 6px 12px; font-size: 12px;">Sign Out</button>
					</div>
				</div>
			`;
		},

		templateLogin: function () {
			return `
				<div class="vs-container" style="max-width: 420px; padding-top: 60px;">
					<div class="vs-card" style="text-align: center;">
						<div class="vs-brand-logo" style="margin: 0 auto 16px auto; width: 48px; height: 48px; font-size: 20px;">VS</div>
						<h2 style="margin: 0 0 6px 0; font-size: 20px;">Employee Sign In</h2>
						<p style="color: var(--vs-muted); font-size: 13px; margin: 0 0 24px 0;">Smart Attendance & Workforce Management</p>

						<div id="vs-login-alert"></div>

						<form id="vs-login-form">
							<div class="vs-form-group">
								<label class="vs-label">Mobile Phone Number</label>
								<div class="vs-phone-group">
									<div class="vs-phone-prefix">🇦🇪 +971</div>
									<input type="text" id="vs-login-phone" class="vs-input" placeholder="50 123 4567" required />
								</div>
							</div>

							<div class="vs-form-group">
								<label class="vs-label">Password</label>
								<input type="password" id="vs-login-password" class="vs-input" placeholder="••••••••" required />
							</div>

							<button type="submit" class="vs-btn vs-btn-primary vs-btn-block">Sign In</button>
						</form>
					</div>
				</div>
			`;
		},

		templateDashboard: function () {
			const data = this.state.dashboardData;
			if (!data) {
				return '<div class="vs-loader-screen"><div class="vs-spinner"></div><p>Fetching dashboard status...</p></div>';
			}

			const emp = data.employee || {};
			const att = data.attendance || {};
			const session = att.session || {};

			let actionButton = '';
			if (att.can_check_in) {
				actionButton = '<button id="vs-check-in-btn" class="vs-btn vs-btn-primary vs-btn-block" style="padding: 16px; font-size: 16px;">Check In Now</button>';
			} else if (att.can_check_out) {
				actionButton = '<button id="vs-check-out-btn" class="vs-btn vs-btn-secondary vs-btn-block" style="padding: 16px; font-size: 16px;">Check Out Now</button>';
			} else {
				actionButton = '<div class="vs-alert vs-alert-success" style="text-align: center; justify-content: center;">Today\'s Attendance Session Completed</div>';
			}

			return `
				<div class="vs-grid">
					<div class="vs-action-card">
						<span class="vs-badge vs-badge-${att.state ? att.state.toLowerCase() : 'none'}">${att.state ? att.state.replace('_', ' ') : 'STANDBY'}</span>
						<div class="vs-time-display" id="vs-live-clock">--:--:--</div>
						<div class="vs-date-display">${new Date().toLocaleDateString('en-US', { weekday: 'long', year: 'numeric', month: 'long', day: 'numeric' })}</div>

						<div id="vs-attendance-alert" style="width: 100%;"></div>

						<div style="width: 100%; margin-top: 12px;">
							${actionButton}
						</div>
					</div>

					<div class="vs-card">
						<div class="vs-card-header">
							<h3 class="vs-card-title">Employee Profile</h3>
							<span class="vs-badge">${emp.app_role ? emp.app_role.replace('vs_', '').replace('_', ' ') : 'Employee'}</span>
						</div>
						<table class="vs-table">
							<tr><td><strong>Name:</strong></td><td>${emp.full_name || 'N/A'}</td></tr>
							<tr><td><strong>Employee ID:</strong></td><td>${emp.employee_code || 'N/A'}</td></tr>
							<tr><td><strong>Branch:</strong></td><td>${emp.branch_name || 'Unassigned'}</td></tr>
							<tr><td><strong>Schedule:</strong></td><td>${data.schedule ? data.schedule.start_time + ' - ' + data.schedule.end_time : 'Standard'}</td></tr>
							<tr><td><strong>Check In:</strong></td><td>${session.check_in_time ? session.check_in_time : '-'}</td></tr>
							<tr><td><strong>Check Out:</strong></td><td>${session.check_out_time ? session.check_out_time : '-'}</td></tr>
						</table>
					</div>
				</div>

				<div class="vs-card">
					<div class="vs-card-header">
						<h3 class="vs-card-title">Recent Personal Attendance History</h3>
					</div>
					<div class="vs-table-wrapper">
						<table class="vs-table">
							<thead>
								<tr>
									<th>Date</th>
									<th>Check In</th>
									<th>Check Out</th>
									<th>Status</th>
									<th>Worked (Hrs)</th>
								</tr>
							</thead>
							<tbody>
								${(data.history || []).map(row => `
									<tr>
										<td>${row.date}</td>
										<td>${row.check_in_time ? row.check_in_time.substring(11, 16) : '-'}</td>
										<td>${row.check_out_time ? row.check_out_time.substring(11, 16) : '-'}</td>
										<td><span class="vs-badge vs-badge-${row.status}">${row.status}</span></td>
										<td>${(row.total_worked_minutes / 60).toFixed(2)}</td>
									</tr>
								`).join('') || '<tr><td colspan="5" style="text-align:center;">No recent records found.</td></tr>'}
							</tbody>
						</table>
					</div>
				</div>
			`;
		},

		templateEmployees: function () {
			return `
				<div class="vs-card">
					<div class="vs-card-header">
						<h3 class="vs-card-title">Employee Management</h3>
						<div style="display: flex; gap: 8px;">
							<button id="vs-add-emp-btn" class="vs-btn vs-btn-primary">+ Add Employee</button>
							<button id="vs-export-emp-btn" class="vs-btn vs-btn-secondary">Export CSV</button>
						</div>
					</div>
					<div id="vs-emp-alert"></div>
					<div class="vs-table-wrapper">
						<table class="vs-table">
							<thead>
								<tr>
									<th>ID</th>
									<th>Full Name</th>
									<th>Phone Number</th>
									<th>Branch</th>
									<th>Role</th>
									<th>Status</th>
								</tr>
							</thead>
							<tbody>
								${this.state.employees.map(emp => `
									<tr>
										<td><strong>${emp.employee_code}</strong></td>
										<td>${emp.full_name}</td>
										<td>${emp.phone_number}</td>
										<td>${emp.branch_name || 'N/A'}</td>
										<td>${emp.app_role}</td>
										<td><span class="vs-badge">${emp.status}</span></td>
									</tr>
								`).join('') || '<tr><td colspan="6" style="text-align:center;">No employees found.</td></tr>'}
							</tbody>
						</table>
					</div>
				</div>
			`;
		},

		templateBranches: function () {
			return `
				<div class="vs-card">
					<div class="vs-card-header">
						<h3 class="vs-card-title">Branch Management</h3>
						<button id="vs-add-branch-btn" class="vs-btn vs-btn-primary">+ Add Branch</button>
					</div>
					<div id="vs-branch-alert"></div>
					<div class="vs-table-wrapper">
						<table class="vs-table">
							<thead>
								<tr>
									<th>Code</th>
									<th>Name</th>
									<th>Phone</th>
									<th>Email</th>
									<th>Status</th>
								</tr>
							</thead>
							<tbody>
								${this.state.branches.map(b => `
									<tr>
										<td><strong>${b.branch_code}</strong></td>
										<td>${b.name}</td>
										<td>${b.contact_phone || '-'}</td>
										<td>${b.contact_email || '-'}</td>
										<td><span class="vs-badge">${b.is_active == 1 ? 'ACTIVE' : 'INACTIVE'}</span></td>
									</tr>
								`).join('') || '<tr><td colspan="5" style="text-align:center;">No branches found.</td></tr>'}
							</tbody>
						</table>
					</div>
				</div>
			`;
		},

		templateReports: function () {
			const data = this.state.reportsData || { metrics: {}, records: [] };
			const m = data.metrics || {};

			return `
				<div class="vs-card">
					<div class="vs-card-header">
						<h3 class="vs-card-title">Attendance Reports & Analytics</h3>
						<button id="vs-export-report-btn" class="vs-btn vs-btn-secondary">Export Report CSV</button>
					</div>

					<div class="vs-grid" style="margin-bottom: 20px;">
						<div class="vs-card" style="padding: 16px; margin: 0;">
							<div style="font-size: 11px; text-transform: uppercase; color: var(--vs-muted);">Total Sessions</div>
							<div style="font-size: 24px; font-weight: 700; color: var(--vs-primary);">${m.total_sessions || 0}</div>
						</div>
						<div class="vs-card" style="padding: 16px; margin: 0;">
							<div style="font-size: 11px; text-transform: uppercase; color: var(--vs-muted);">Late Arrivals</div>
							<div style="font-size: 24px; font-weight: 700; color: var(--vs-primary);">${m.total_late || 0}</div>
						</div>
						<div class="vs-card" style="padding: 16px; margin: 0;">
							<div style="font-size: 11px; text-transform: uppercase; color: var(--vs-muted);">Total Worked Hours</div>
							<div style="font-size: 24px; font-weight: 700; color: var(--vs-primary);">${m.total_worked_hours || 0}h</div>
						</div>
					</div>

					<div class="vs-table-wrapper">
						<table class="vs-table">
							<thead>
								<tr>
									<th>Date</th>
									<th>Employee</th>
									<th>Branch</th>
									<th>Check In</th>
									<th>Check Out</th>
									<th>Status</th>
									<th>Worked (Hrs)</th>
								</tr>
							</thead>
							<tbody>
								${(data.records || []).map(r => `
									<tr>
										<td>${r.date}</td>
										<td><strong>${r.full_name}</strong> (${r.employee_code})</td>
										<td>${r.branch_name || 'N/A'}</td>
										<td>${r.check_in_time ? r.check_in_time.substring(11, 16) : '-'}</td>
										<td>${r.check_out_time ? r.check_out_time.substring(11, 16) : '-'}</td>
										<td><span class="vs-badge">${r.status}</span></td>
										<td>${(r.total_worked_minutes / 60).toFixed(2)}</td>
									</tr>
								`).join('') || '<tr><td colspan="7" style="text-align:center;">No records found.</td></tr>'}
							</tbody>
						</table>
					</div>
				</div>
			`;
		},

		templateAuditLogs: function () {
			return `
				<div class="vs-card">
					<div class="vs-card-header">
						<h3 class="vs-card-title">Security & Operational Audit Trail</h3>
					</div>
					<div class="vs-table-wrapper">
						<table class="vs-table">
							<thead>
								<tr>
									<th>Timestamp</th>
									<th>Actor</th>
									<th>Role</th>
									<th>Action</th>
									<th>Target</th>
									<th>IP Address</th>
								</tr>
							</thead>
							<tbody>
								${this.state.auditLogs.map(log => `
									<tr>
										<td>${log.created_at}</td>
										<td><strong>${log.actor_name}</strong></td>
										<td>${log.actor_role}</td>
										<td><span class="vs-badge">${log.action}</span></td>
										<td>${log.target_type} #${log.target_id}</td>
										<td>${log.ip_address}</td>
									</tr>
								`).join('') || '<tr><td colspan="6" style="text-align:center;">No audit records found.</td></tr>'}
							</tbody>
						</table>
					</div>
				</div>
			`;
		},

		bindLoginEvents: function () {
			const form = document.getElementById('vs-login-form');
			if (!form) return;

			form.addEventListener('submit', (e) => {
				e.preventDefault();
				const phone = document.getElementById('vs-login-phone').value;
				const password = document.getElementById('vs-login-password').value;

				this.request('/auth/login', 'POST', { phone: phone, password: password })
					.then(res => {
						if (res.success) {
							window.location.reload();
						} else {
							this.showAlert('vs-login-alert', res.message, 'error');
						}
					})
					.catch(err => {
						this.showAlert('vs-login-alert', err.message || 'Login request failed.', 'error');
					});
			});
		},

		bindAppEvents: function () {
			// Nav buttons
			document.querySelectorAll('.vs-nav-btn').forEach(btn => {
				btn.addEventListener('click', (e) => {
					this.state.route = e.target.getAttribute('data-route');
					this.render();
				});
			});

			// Logout button
			const logoutBtn = document.getElementById('vs-logout-btn');
			if (logoutBtn) {
				logoutBtn.addEventListener('click', () => {
					this.request('/auth/logout', 'POST')
						.then(() => window.location.reload());
				});
			}

			// Check in / Check out
			const checkInBtn = document.getElementById('vs-check-in-btn');
			if (checkInBtn) {
				checkInBtn.addEventListener('click', () => this.handleAttendanceAction('check-in'));
			}

			const checkOutBtn = document.getElementById('vs-check-out-btn');
			if (checkOutBtn) {
				checkOutBtn.addEventListener('click', () => this.handleAttendanceAction('check-out'));
			}

			// Clock timer
			this.startLiveClock();
		},

		startLiveClock: function () {
			const clock = document.getElementById('vs-live-clock');
			if (!clock) return;
			const update = () => {
				const now = new Date();
				clock.textContent = now.toLocaleTimeString('en-US', { hour12: false });
			};
			update();
			setInterval(update, 1000);
		},

		handleAttendanceAction: function (action) {
			const payload = {
				ssid: 'Workplace_WiFi',
				timestamp: Math.floor(Date.now() / 1000)
			};

			this.request('/attendance/' + action, 'POST', payload)
				.then(res => {
					if (res.success) {
						this.showAlert('vs-attendance-alert', res.message, 'success');
						this.fetchDashboard();
					} else {
						this.showAlert('vs-attendance-alert', res.message, 'error');
					}
				})
				.catch(err => {
					this.showAlert('vs-attendance-alert', err.message || 'Verification request failed.', 'error');
				});
		},

		fetchDashboard: function () {
			this.request('/dashboard', 'GET')
				.then(res => {
					if (res.success) {
						this.state.dashboardData = res;
						this.render();
					}
				});
		},

		fetchEmployees: function () {
			this.request('/employees', 'GET')
				.then(res => {
					if (res.success) {
						this.state.employees = res.employees;
						this.render();
					}
				});
		},

		fetchBranches: function () {
			this.request('/branches', 'GET')
				.then(res => {
					if (res.success) {
						this.state.branches = res.branches;
						this.render();
					}
				});
		},

		fetchReports: function () {
			this.request('/reports/attendance', 'GET')
				.then(res => {
					if (res.success) {
						this.state.reportsData = res.data;
						this.render();
					}
				});
		},

		fetchAuditLogs: function () {
			this.request('/audit-logs', 'GET')
				.then(res => {
					if (res.success) {
						this.state.auditLogs = res.logs;
						this.render();
					}
				});
		},

		request: function (endpoint, method, data) {
			const url = this.config.api_root + endpoint;
			const headers = {
				'X-WP-Nonce': this.config.nonce,
				'Content-Type': 'application/json'
			};

			const opts = { method: method, headers: headers };
			if (data) opts.body = JSON.stringify(data);

			return fetch(url, opts).then(res => res.json());
		},

		showAlert: function (targetId, msg, type) {
			const el = document.getElementById(targetId);
			if (el) {
				el.innerHTML = `<div class="vs-alert vs-alert-${type}">${msg}</div>`;
			}
		}
	};

	document.addEventListener('DOMContentLoaded', () => App.init());
})();
