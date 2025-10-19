<?php
// Shared Admin Sidebar
$current = basename($_SERVER['PHP_SELF']);
?>
<div class="sidebar" id="sidebar">
	<div class="p-3" style="background:#3a2322;">
		<div style="display:flex;align-items:center;">
			<span style="display:inline-block; margin-right:12px;">
				<i class="fas fa-shield-alt" style="font-size:1.5em;color:#fff;"></i>
			</span>
			<span style="color:#fff; font-weight:700; font-size:1.em; line-height:1.1; display:flex; flex-direction:column;">
				<span style="margin-bottom:-2px;">Admin</span>
				<span>Panel</span>
			</span>
		</div>
	</div>
	<nav class="nav flex-column p-2">
		<a class="nav-link <?php echo $current === 'dashboard.php' ? 'active' : ''; ?>" href="dashboard.php">
			<i class="fas fa-tachometer-alt me-2"></i> Dashboard
		</a>
		<a class="nav-link <?php echo $current === 'residents.php' ? 'active' : ''; ?>" href="residents.php">
			<i class="fas fa-users me-2"></i> Manage Residents
		</a>
		<a class="nav-link <?php echo $current === 'pending_residents.php' ? 'active' : ''; ?>" href="pending_residents.php">
			<i class="fas fa-user-clock me-2"></i> Pending Approvals
		</a>
		<a class="nav-link <?php echo $current === 'announcements.php' ? 'active' : ''; ?>" href="announcements.php">
			<i class="fas fa-bullhorn me-2"></i> Announcements
		</a>
		<a class="nav-link <?php echo $current === 'concerns.php' ? 'active' : ''; ?>" href="concerns.php">
			<i class="fas fa-exclamation-triangle me-2"></i> Concerns
		</a>
		<a class="nav-link <?php echo $current === 'visitors.php' ? 'active' : ''; ?>" href="visitors.php">
			<i class="fas fa-address-book me-2"></i> Visitors
		</a>
		<a class="nav-link <?php echo $current === 'reports.php' ? 'active' : ''; ?>" href="reports.php">
			<i class="fas fa-chart-bar me-2"></i> Reports
		</a>
		<a class="nav-link mt-auto" href="../logout.php">
			<i class="fas fa-sign-out-alt me-2"></i> Logout
		</a>
	</nav>
</div>


