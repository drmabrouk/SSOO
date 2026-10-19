/* Verification System – Single Page Frontend Application Engine with Floating Left Sidebar */

(function () {
	'use strict';

	const App = {
		config: window.VS_CONFIG || {},
		state: {
			route: 'dashboard',
			user: null,
			dashboardData: null,
			branches: [],
			employees: [],
			reportsData: null,
			auditLogs: [],
			wifiConfig: null,
			selectedBranchId: 0,
		},

		init: function () {
			this.state.user = this.config.user;

			// Handle hash-based SPA routing
			const initialHash = window.location.hash.replace('#', '');
			if (initialHash && ['dashboard', 'employees', 'branches', 'reports', 'wifi-settings', 'audit'].includes(initialHash)) {
				this.state.route = initialHash;
			} else {
				this.state.route = this.config.is_logged_in ? 'dashboard' : 'login';
			}

			window.addEventListener('hashchange', () => {
				const hash = window.location.hash.replace('#', '');
				if (hash && hash !== this.state.route) {
					this.state.route = hash;
					this.render();
				}
			});

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

			let sidebarHtml = this.templateSidebar();
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
				case 'wifi-settings':
					mainHtml = this.templateWifiSettings();
					break;
				case 'audit':
					mainHtml = this.templateAuditLogs();
					break;
				default:
					mainHtml = this.templateDashboard();
			}

			viewport.innerHTML = `
				<button class="vs-mobile-toggle" id="vs-mobile-menu-btn">☰ Menu</button>
				${sidebarHtml}
				<div class="vs-main-content">${mainHtml}</div>
			`;

			this.bindAppEvents();

			// Route data prefetching
			if (this.state.route === 'dashboard' && !this.state.dashboardData) {
				this.fetchDashboard();
			} else if (this.state.route === 'employees' && this.state.employees.length === 0) {
				this.fetchEmployees();
			} else if (this.state.route === 'branches' && this.state.branches.length === 0) {
				this.fetchBranches();
			} else if (this.state.route === 'reports' && !this.state.reportsData) {
				this.fetchReports();
			} else if (this.state.route === 'wifi-settings' && !this.state.wifiConfig) {
				this.fetchBranches().then(() => {
					if (this.state.branches.length > 0) {
						this.state.selectedBranchId = this.state.branches[0].id;
						this.fetchWifiConfig(this.state.selectedBranchId);
					}
				});
			} else if (this.state.route === 'audit' && this.state.auditLogs.length === 0) {
				this.fetchAuditLogs();
			}
		},

		templateSidebar: function () {
			const role = this.state.user ? this.state.user.role : '';
			const isManager = (role === 'vs_system_admin' || role === 'vs_general_manager' || role === 'vs_branch_manager');
			const isAdmin = (role === 'vs_system_admin' || role === 'vs_general_manager');

			return `
				<div class="vs-sidebar" id="vs-app-sidebar">
					<div>
						<div class="vs-sidebar-brand">
							<div class="vs-sidebar-logo">VS</div>
							<div class="vs-sidebar-title">Verification System</div>
						</div>
						<div class="vs-sidebar-menu">
							<button class="vs-menu-item ${this.state.route === 'dashboard' ? 'active' : ''}" data-route="dashboard">
								<svg viewBox="0 0 24 24"><rect x="3" y="3" width="7" height="7"/><rect x="14" y="3" width="7" height="7"/><rect x="14" y="14" width="7" height="7"/><rect x="3" y="14" width="7" height="7"/></svg>
								Dashboard
							</button>
							${isManager ? `
								<button class="vs-menu-item ${this.state.route === 'employees' ? 'active' : ''}" data-route="employees">
									<svg viewBox="0 0 24 24"><path d="M17 21v-2a4 4 0 0 0-4-4H5a4 4 0 0 0-4 4v2"/><circle cx="9" cy="7" r="4"/><path d="M23 21v-2a4 4 0 0 0-3-3.87"/><path d="M16 3.13a4 4 0 0 1 0 7.75"/></svg>
									Employees
								</button>
							` : ''}
							${isManager ? `
								<button class="vs-menu-item ${this.state.route === 'branches' ? 'active' : ''}" data-route="branches">
									<svg viewBox="0 0 24 24"><path d="M3 9l9-7 9 7v11a2 2 0 0 1-2 2H5a2 2 0 0 1-2-2z"/><polyline points="9 22 9 12 15 12 15 22"/></svg>
									Branches
								</button>
							` : ''}
							<button class="vs-menu-item ${this.state.route === 'reports' ? 'active' : ''}" data-route="reports">
								<svg viewBox="0 0 24 24"><line x1="18" y1="20" x2="18" y2="10"/><line x1="12" y1="20" x2="12" y2="4"/><line x1="6" y1="20" x2="6" y2="14"/></svg>
								Reports
							</button>
							${isAdmin ? `
								<button class="vs-menu-item ${this.state.route === 'wifi-settings' ? 'active' : ''}" data-route="wifi-settings">
									<svg viewBox="0 0 24 24"><path d="M5 12.55a11 11 0 0 1 14.08 0"/><path d="M1.42 9a16 16 0 0 1 21.16 0"/><path d="M8.53 16.11a6 6 0 0 1 6.95 0"/><line x1="12" y1="20" x2="12.01" y2="20"/></svg>
									Wi-Fi Settings
								</button>
							` : ''}
							${isManager ? `
								<button class="vs-menu-item ${this.state.route === 'audit' ? 'active' : ''}" data-route="audit">
									<svg viewBox="0 0 24 24"><path d="M12 22s8-4 8-10V5l-8-3-8 3v7c0 6 8 10 8 10z"/></svg>
									Audit Logs
								</button>
							` : ''}
						</div>
					</div>
					<div class="vs-sidebar-footer">
						<div class="vs-user-card">
							<div class="vs-avatar">${this.state.user && this.state.user.name ? this.state.user.name.charAt(0).toUpperCase() : 'U'}</div>
							<div class="vs-user-info">
								<div class="vs-user-name">${this.state.user ? this.state.user.name : 'User'}</div>
								<div class="vs-user-role">${this.state.user ? this.state.user.role.replace('vs_', '').replace('_', ' ') : ''}</div>
							</div>
						</div>
						<button id="vs-logout-btn" class="vs-btn vs-btn-secondary vs-btn-block">Sign Out</button>
					</div>
				</div>
			`;
		},

		templateLogin: function () {
			return `
				<div class="vs-container" style="max-width: 420px; padding-top: 80px;">
					<div class="vs-card" style="text-align: center;">
						<div class="vs-sidebar-logo" style="margin: 0 auto 16px auto; width: 48px; height: 48px; font-size: 20px;">VS</div>
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
							<button id="vs-add-emp-btn" class="vs-btn vs-btn-primary">+ Create Account</button>
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
									<th>Actions</th>
								</tr>
							</thead>
							<tbody>
								${this.state.employees.map(emp => `
									<tr>
										<td><strong>${emp.employee_code}</strong></td>
										<td>${emp.full_name}</td>
										<td>${emp.phone_number}</td>
										<td>${emp.branch_name || 'N/A'}</td>
										<td>${emp.app_role ? emp.app_role.replace('vs_', '').replace('_', ' ') : 'Employee'}</td>
										<td><span class="vs-badge vs-badge-${emp.status}">${emp.status}</span></td>
										<td>
											<button class="vs-btn vs-btn-secondary vs-reset-pass-btn" data-id="${emp.id}" style="padding: 4px 8px; font-size: 11px;">Reset Password</button>
										</td>
									</tr>
								`).join('') || '<tr><td colspan="7" style="text-align:center;">No accounts found.</td></tr>'}
							</tbody>
						</table>
					</div>
				</div>

				<div id="vs-modal-container"></div>
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

		templateWifiSettings: function () {
			const cfg = this.state.wifiConfig || {};
			return `
				<div class="vs-card">
					<div class="vs-card-header">
						<h3 class="vs-card-title">Branch Wi-Fi Settings & Integration Diagnostics</h3>
					</div>

					<div class="vs-form-group">
						<label class="vs-label">Select Target Branch</label>
						<select id="vs-wifi-branch-select" class="vs-select">
							${this.state.branches.map(b => `
								<option value="${b.id}" ${b.id == this.state.selectedBranchId ? 'selected' : ''}>${b.name} (${b.branch_code})</option>
							`).join('')}
						</select>
					</div>

					<div id="vs-wifi-alert"></div>

					<form id="vs-wifi-form">
						<div class="vs-grid">
							<div class="vs-form-group">
								<label class="vs-label">Configuration Name</label>
								<input type="text" id="vs-wifi-name" class="vs-input" value="${cfg.config_name || 'Main Office Wi-Fi'}" required />
							</div>
							<div class="vs-form-group">
								<label class="vs-label">Integration Type</label>
								<select id="vs-wifi-type" class="vs-select">
									<option value="hmac_token" ${cfg.auth_type === 'hmac_token' ? 'selected' : ''}>HMAC Signed Token Gateway</option>
									<option value="meraki" ${cfg.auth_type === 'meraki' ? 'selected' : ''}>Cisco Meraki Enterprise Controller</option>
								</select>
							</div>
						</div>

						<div class="vs-form-group">
							<label class="vs-label">Approved Network SSID</label>
							<input type="text" id="vs-wifi-ssid" class="vs-input" value="${cfg.ssid || ''}" placeholder="e.g. Workplace_Secure_WiFi" required />
						</div>

						<div class="vs-form-group">
							<label class="vs-label">Approved Access Point BSSIDs (One per line)</label>
							<textarea id="vs-wifi-bssids" class="vs-textarea" rows="3" placeholder="00:11:22:33:44:55">${cfg.allowed_bssids || ''}</textarea>
						</div>

						<div class="vs-form-group">
							<label class="vs-label">Shared Secret Key / HMAC Secret</label>
							<input type="password" id="vs-wifi-secret" class="vs-input" value="${cfg.shared_secret || ''}" placeholder="••••••••" />
						</div>

						<div class="vs-form-group">
							<label class="vs-label">Controller API Endpoint (Cisco Meraki)</label>
							<input type="text" id="vs-wifi-endpoint" class="vs-input" value="${cfg.api_endpoint || ''}" placeholder="https://api.meraki.com/api/v1" />
						</div>

						<div class="vs-form-group">
							<label class="vs-label">Controller API Key (Cisco Meraki)</label>
							<input type="password" id="vs-wifi-key" class="vs-input" value="${cfg.api_key || ''}" placeholder="••••••••" />
						</div>

						<div style="display: flex; gap: 12px; margin-top: 20px;">
							<button type="submit" class="vs-btn vs-btn-primary">Save Wi-Fi Configuration</button>
							<button type="button" id="vs-test-wifi-btn" class="vs-btn vs-btn-secondary">Test Controller Connection</button>
						</div>
					</form>
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
			// Mobile Drawer Menu toggle
			const mobileBtn = document.getElementById('vs-mobile-menu-btn');
			const sidebar = document.getElementById('vs-app-sidebar');
			if (mobileBtn && sidebar) {
				mobileBtn.addEventListener('click', () => sidebar.classList.toggle('open'));
			}

			// Sidebar menu buttons
			document.querySelectorAll('.vs-menu-item').forEach(btn => {
				btn.addEventListener('click', (e) => {
					const target = e.currentTarget.getAttribute('data-route');
					this.state.route = target;
					window.location.hash = target;
					this.render();
				});
			});

			// Logout button
			const logoutBtn = document.getElementById('vs-logout-btn');
			if (logoutBtn) {
				logoutBtn.addEventListener('click', () => {
					this.request('/auth/logout', 'POST')
						.then(() => {
							this.config.is_logged_in = false;
							this.state.user = null;
							window.location.hash = '';
							window.location.reload();
						});
				});
			}

			// Attendance Check-In / Check-Out
			const checkInBtn = document.getElementById('vs-check-in-btn');
			if (checkInBtn) {
				checkInBtn.addEventListener('click', () => this.runAttendanceSequence('check-in'));
			}

			const checkOutBtn = document.getElementById('vs-check-out-btn');
			if (checkOutBtn) {
				checkOutBtn.addEventListener('click', () => this.runAttendanceSequence('check-out'));
			}

			// Add Employee Modal
			const addEmpBtn = document.getElementById('vs-add-emp-btn');
			if (addEmpBtn) {
				addEmpBtn.addEventListener('click', () => this.openAddEmployeeModal());
			}

			// Reset Password Buttons
			document.querySelectorAll('.vs-reset-pass-btn').forEach(btn => {
				btn.addEventListener('click', (e) => {
					const empId = e.currentTarget.getAttribute('data-id');
					const newPass = prompt("Enter new password (min 6 chars):");
					if (newPass) {
						this.request(`/employees/${empId}/reset-password`, 'POST', { password: newPass })
							.then(res => {
								alert(res.message);
							});
					}
				});
			});

			// Wi-Fi Config Form & Test
			const wifiBranchSelect = document.getElementById('vs-wifi-branch-select');
			if (wifiBranchSelect) {
				wifiBranchSelect.addEventListener('change', (e) => {
					this.state.selectedBranchId = e.target.value;
					this.fetchWifiConfig(this.state.selectedBranchId);
				});
			}

			const wifiForm = document.getElementById('vs-wifi-form');
			if (wifiForm) {
				wifiForm.addEventListener('submit', (e) => {
					e.preventDefault();
					const payload = {
						branch_id: this.state.selectedBranchId,
						config_name: document.getElementById('vs-wifi-name').value,
						auth_type: document.getElementById('vs-wifi-type').value,
						ssid: document.getElementById('vs-wifi-ssid').value,
						allowed_bssids: document.getElementById('vs-wifi-bssids').value,
						shared_secret: document.getElementById('vs-wifi-secret').value,
						api_endpoint: document.getElementById('vs-wifi-endpoint').value,
						api_key: document.getElementById('vs-wifi-key').value,
						is_active: 1
					};

					this.request('/wifi/config', 'POST', payload)
						.then(res => {
							this.showAlert('vs-wifi-alert', res.message, res.success ? 'success' : 'error');
						});
				});
			}

			const testWifiBtn = document.getElementById('vs-test-wifi-btn');
			if (testWifiBtn) {
				testWifiBtn.addEventListener('click', () => {
					this.request('/wifi/test', 'POST', { branch_id: this.state.selectedBranchId })
						.then(res => {
							this.showAlert('vs-wifi-alert', res.message, res.success ? 'success' : 'error');
						});
				});
			}

			this.startLiveClock();
		},

		runAttendanceSequence: function (action) {
			const overlay = document.createElement('div');
			overlay.className = 'vs-progress-overlay';
			overlay.innerHTML = `
				<div class="vs-progress-box">
					<h3 style="margin: 0 0 8px 0; font-size: 18px;">Attendance Verification</h3>
					<p style="color: var(--vs-muted); font-size: 13px; margin: 0;">Please stay on this screen while we verify your workplace connectivity.</p>

					<div class="vs-progress-steps">
						<div class="vs-step-item active" id="vs-step-1"><div class="vs-step-icon"></div> Checking Employee Account...</div>
						<div class="vs-step-item" id="vs-step-2"><div class="vs-step-icon"></div> Identifying Assigned Branch...</div>
						<div class="vs-step-item" id="vs-step-3"><div class="vs-step-icon"></div> Connecting to Workplace Verification Service...</div>
						<div class="vs-step-item" id="vs-step-4"><div class="vs-step-icon"></div> Verifying Authorized Network Session...</div>
						<div class="vs-step-item" id="vs-step-5"><div class="vs-step-icon"></div> Validating Attendance Information...</div>
					</div>
				</div>
			`;
			document.body.appendChild(overlay);

			const setStep = (num, status) => {
				const el = document.getElementById('vs-step-' + num);
				if (el) {
					el.className = 'vs-step-item ' + status;
				}
			};

			setTimeout(() => { setStep(1, 'completed'); setStep(2, 'active'); }, 500);
			setTimeout(() => { setStep(2, 'completed'); setStep(3, 'active'); }, 1000);
			setTimeout(() => { setStep(3, 'completed'); setStep(4, 'active'); }, 1500);

			setTimeout(() => {
				setStep(4, 'completed');
				setStep(5, 'active');

				const payload = {
					ssid: 'Workplace_WiFi',
					timestamp: Math.floor(Date.now() / 1000)
				};

				this.request('/attendance/' + action, 'POST', payload)
					.then(res => {
						document.body.removeChild(overlay);
						if (res.success) {
							this.showAlert('vs-attendance-alert', res.message, 'success');
							this.fetchDashboard();
						} else {
							this.showAlert('vs-attendance-alert', res.message, 'error');
						}
					})
					.catch(err => {
						document.body.removeChild(overlay);
						this.showAlert('vs-attendance-alert', err.message || 'Verification request failed.', 'error');
					});
			}, 2000);
		},

		openAddEmployeeModal: function () {
			const container = document.getElementById('vs-modal-container');
			if (!container) return;

			container.innerHTML = `
				<div class="vs-progress-overlay">
					<div class="vs-progress-box" style="text-align: left; max-width: 500px;">
						<h3 style="margin: 0 0 16px 0; font-size: 18px;">Create Employee Account</h3>
						<form id="vs-create-emp-form">
							<div class="vs-form-group">
								<label class="vs-label">Full Name</label>
								<input type="text" id="vs-emp-name" class="vs-input" required />
							</div>
							<div class="vs-form-group">
								<label class="vs-label">Employee ID</label>
								<input type="text" id="vs-emp-code" class="vs-input" placeholder="EMP-1001" required />
							</div>
							<div class="vs-form-group">
								<label class="vs-label">Mobile Number</label>
								<div class="vs-phone-group">
									<div class="vs-phone-prefix">🇦🇪 +971</div>
									<input type="text" id="vs-emp-phone" class="vs-input" placeholder="50 123 4567" required />
								</div>
							</div>
							<div class="vs-form-group">
								<label class="vs-label">Assigned Branch</label>
								<select id="vs-emp-branch" class="vs-select">
									${this.state.branches.map(b => `<option value="${b.id}">${b.name}</option>`).join('')}
								</select>
							</div>
							<div class="vs-form-group">
								<label class="vs-label">Assigned Role</label>
								<select id="vs-emp-role" class="vs-select">
									<option value="vs_employee">Employee</option>
									<option value="vs_branch_manager">Branch Manager</option>
								</select>
							</div>
							<div style="display: flex; gap: 8px; justify-content: flex-end; margin-top: 20px;">
								<button type="button" class="vs-btn vs-btn-secondary" id="vs-close-modal">Cancel</button>
								<button type="submit" class="vs-btn vs-btn-primary">Create Account</button>
							</div>
						</form>
					</div>
				</div>
			`;

			document.getElementById('vs-close-modal').addEventListener('click', () => { container.innerHTML = ''; });
			document.getElementById('vs-create-emp-form').addEventListener('submit', (e) => {
				e.preventDefault();
				const payload = {
					full_name: document.getElementById('vs-emp-name').value,
					employee_code: document.getElementById('vs-emp-code').value,
					phone_number: document.getElementById('vs-emp-phone').value,
					branch_id: document.getElementById('vs-emp-branch').value,
					app_role: document.getElementById('vs-emp-role').value
				};

				this.request('/employees', 'POST', payload)
					.then(res => {
						container.innerHTML = '';
						if (res.success) {
							this.fetchEmployees();
						} else {
							alert(res.message);
						}
					});
			});
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

		fetchDashboard: function () {
			return this.request('/dashboard', 'GET').then(res => {
				if (res.success) {
					this.state.dashboardData = res;
					this.render();
				}
			});
		},

		fetchEmployees: function () {
			return this.request('/employees', 'GET').then(res => {
				if (res.success) {
					this.state.employees = res.employees;
					this.render();
				}
			});
		},

		fetchBranches: function () {
			return this.request('/branches', 'GET').then(res => {
				if (res.success) {
					this.state.branches = res.branches;
					this.render();
				}
			});
		},

		fetchWifiConfig: function (branchId) {
			return this.request(`/wifi/config?branch_id=${branchId}`, 'GET').then(res => {
				if (res.success) {
					this.state.wifiConfig = res.config;
					this.render();
				}
			});
		},

		fetchReports: function () {
			return this.request('/reports/attendance', 'GET').then(res => {
				if (res.success) {
					this.state.reportsData = res.data;
					this.render();
				}
			});
		},

		fetchAuditLogs: function () {
			return this.request('/audit-logs', 'GET').then(res => {
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
