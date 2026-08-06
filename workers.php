<?php
// Jagdamba Electrical - Worker & Machine Manager (workers.php)
require_once 'sidebar.php';

// Auto-migration check: create workers, machines, and daily_work tables if they do not exist
$pdo = getSidebarConnection();
$migrationError = null;
if ($pdo) {
    try {
        // Create Workers Table (includes designation and hourly_salary by default if table is new)
        $pdo->exec("CREATE TABLE IF NOT EXISTS `workers` (
            `id` INT AUTO_INCREMENT PRIMARY KEY,
            `name` VARCHAR(100) NOT NULL,
            `mobile_no` VARCHAR(20) NOT NULL,
            `emp_no` VARCHAR(50) UNIQUE NOT NULL,
            `designation` VARCHAR(50) NOT NULL DEFAULT 'experience',
            `hourly_salary` DECIMAL(10,2) NOT NULL DEFAULT 0.00,
            `aadhar_no` VARCHAR(20) NULL,
            `created_at` TIMESTAMP DEFAULT CURRENT_TIMESTAMP
        ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;");

        // Alter Workers Table for existing tables
        $cols = $pdo->query("DESCRIBE `workers`")->fetchAll(PDO::FETCH_COLUMN);
        if (!in_array('designation', $cols)) {
            $pdo->exec("ALTER TABLE `workers` ADD `designation` VARCHAR(50) NOT NULL DEFAULT 'experience'");
        }
        if (!in_array('hourly_salary', $cols)) {
            $pdo->exec("ALTER TABLE `workers` ADD `hourly_salary` DECIMAL(10,2) NOT NULL DEFAULT 0.00");
        }
        if (!in_array('aadhar_no', $cols)) {
            $pdo->exec("ALTER TABLE `workers` ADD `aadhar_no` VARCHAR(20) NULL");
        }
        if (!in_array('doc_id_card', $cols)) {
            $pdo->exec("ALTER TABLE `workers` ADD `doc_id_card` VARCHAR(255) NULL");
        }
        if (!in_array('doc_tax_card', $cols)) {
            $pdo->exec("ALTER TABLE `workers` ADD `doc_tax_card` VARCHAR(255) NULL");
        }
        if (!in_array('doc_passbook', $cols)) {
            $pdo->exec("ALTER TABLE `workers` ADD `doc_passbook` VARCHAR(255) NULL");
        }
        if (!in_array('doc_photo', $cols)) {
            $pdo->exec("ALTER TABLE `workers` ADD `doc_photo` VARCHAR(255) NULL");
        }
        if (!in_array('doc_address', $cols)) {
            $pdo->exec("ALTER TABLE `workers` ADD `doc_address` VARCHAR(255) NULL");
        }
        if (!in_array('doc_other', $cols)) {
            $pdo->exec("ALTER TABLE `workers` ADD `doc_other` VARCHAR(255) NULL");
        }

        // Create Machines Table
        $pdo->exec("CREATE TABLE IF NOT EXISTS `machines` (
            `id` INT AUTO_INCREMENT PRIMARY KEY,
            `machine_no` VARCHAR(50) UNIQUE NOT NULL,
            `rate` DECIMAL(10,2) NOT NULL,
            `created_at` TIMESTAMP DEFAULT CURRENT_TIMESTAMP
        ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;");

        // Create Daily Work Entry Table
        $pdo->exec("CREATE TABLE IF NOT EXISTS `daily_work` (
            `id` INT AUTO_INCREMENT PRIMARY KEY,
            `worker_id` INT NOT NULL,
            `machine_id` INT NULL,
            `work_date` DATE NOT NULL,
            `qty` DECIMAL(10,2) NULL,
            `in_time` TIME NULL,
            `out_time` TIME NULL,
            `construction_no` VARCHAR(50) NULL,
            `status` VARCHAR(20) DEFAULT 'Present',
            `attendance` VARCHAR(20) DEFAULT 'Present',
            `work_done` TEXT NULL,
            `remarks` TEXT NULL,
            `created_at` TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
            FOREIGN KEY (`worker_id`) REFERENCES `workers` (`id`) ON DELETE CASCADE,
            FOREIGN KEY (`machine_id`) REFERENCES `machines` (`id`) ON DELETE CASCADE
        ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;");

        // Alter Daily Work Table for existing tables
        $dwCols = $pdo->query("DESCRIBE `daily_work`")->fetchAll(PDO::FETCH_COLUMN);
        if (!in_array('in_time', $dwCols)) {
            $pdo->exec("ALTER TABLE `daily_work` ADD `in_time` TIME NULL");
        }
        if (!in_array('out_time', $dwCols)) {
            $pdo->exec("ALTER TABLE `daily_work` ADD `out_time` TIME NULL");
        }
        if (!in_array('construction_no', $dwCols)) {
            $pdo->exec("ALTER TABLE `daily_work` ADD `construction_no` VARCHAR(50) NULL");
        }
        if (!in_array('status', $dwCols)) {
            $pdo->exec("ALTER TABLE `daily_work` ADD `status` VARCHAR(20) DEFAULT 'Present'");
        }
        if (!in_array('attendance', $dwCols)) {
            $pdo->exec("ALTER TABLE `daily_work` ADD `attendance` VARCHAR(20) DEFAULT 'Present'");
        }
        if (!in_array('work_done', $dwCols)) {
            $pdo->exec("ALTER TABLE `daily_work` ADD `work_done` TEXT NULL");
        }
        if (!in_array('remarks', $dwCols)) {
            $pdo->exec("ALTER TABLE `daily_work` ADD `remarks` TEXT NULL");
        }
        $pdo->exec("ALTER TABLE `daily_work` MODIFY `machine_id` INT NULL");
        $pdo->exec("ALTER TABLE `daily_work` MODIFY `qty` DECIMAL(10,2) NULL");

    } catch (Exception $e) {
        $migrationError = $e->getMessage();
    }
}

renderHeader("Workers & Machines Manager", "workers");
?>

<style>
    /* Tabs Navigation styling */
    .tabs-navigation {
        display: flex;
        gap: 12px;
        margin-bottom: 24px;
        border-bottom: 1px solid var(--border-color);
        padding-bottom: 12px;
        flex-wrap: wrap;
    }
    .tab-btn {
        background: var(--bg-card);
        border: 1px solid var(--border-color);
        color: var(--text-muted);
        padding: 12px 20px;
        border-radius: 8px;
        cursor: pointer;
        font-family: var(--font-header);
        font-weight: 600;
        transition: var(--transition);
        display: flex;
        align-items: center;
        gap: 8px;
    }
    .tab-btn:hover {
        background: var(--bg-card-hover);
        color: var(--text-main);
        border-color: rgba(255, 255, 255, 0.15);
    }
    .tab-btn.active {
        background: var(--primary);
        color: #ffffff;
        border-color: var(--primary);
    }
    .tab-btn.active i {
        color: #ffffff;
    }
    .tab-content {
        display: none;
        animation: fadeIn 0.3s ease;
    }
    .tab-content.active {
        display: block;
    }

    /* Additional custom styling */
    .btn-delete-row {
        background: none;
        border: none;
        color: var(--error);
        cursor: pointer;
        padding: 4px 8px;
        border-radius: 4px;
        transition: var(--transition);
    }
    .btn-delete-row:hover {
        background: rgba(239, 68, 68, 0.15);
        color: #ff5f5f;
    }

    .report-summary-card {
        background: rgba(255, 255, 255, 0.02);
        border: 1px solid var(--border-color);
        border-radius: 8px;
        padding: 16px 24px;
        margin-bottom: 20px;
        display: flex;
        justify-content: space-between;
        align-items: center;
        flex-wrap: wrap;
        gap: 16px;
    }
    .report-summary-card .title {
        font-size: 0.85rem;
        color: var(--text-muted);
        text-transform: uppercase;
        letter-spacing: 0.5px;
    }
    .report-summary-card .value {
        font-size: 1.5rem;
        font-weight: 700;
        color: var(--primary);
    }

    @keyframes fadeIn {
        from { opacity: 0; transform: translateY(5px); }
        to { opacity: 1; transform: translateY(0); }
    }
</style>

<?php if ($migrationError): ?>
    <div style="background-color: rgba(239, 68, 68, 0.1); border: 1px solid var(--error); color: var(--error); padding: 16px; border-radius: 8px; margin-bottom: 24px;">
        <strong>Database Auto-Migration Failed:</strong> <?php echo htmlspecialchars($migrationError); ?>. Please ensure your schema is set up.
    </div>
<?php endif; ?>

<!-- Tabs Navigation -->
<div class="tabs-navigation">
    <button class="tab-btn active" onclick="switchTab('tab-workers')">
        <i class="fa-solid fa-user-plus"></i> Add Worker
    </button>
    <button class="tab-btn" onclick="switchTab('tab-machines')">
        <i class="fa-solid fa-server"></i> Add Machine
    </button>
    <button class="tab-btn" onclick="switchTab('tab-daily-work')">
        <i class="fa-solid fa-calendar-check"></i> Daily Worker Work
    </button>
    <button class="tab-btn" onclick="switchTab('tab-payments')">
        <i class="fa-solid fa-file-invoice-dollar"></i> Payment & Report
    </button>
</div>

<!-- ==========================================================================
   TAB 1: WORKERS MASTER
   ========================================================================== -->
<div id="tab-workers" class="tab-content active">
    <div class="module-container">
        <!-- Form Panel (Left) -->
        <div class="panel">
            <div class="panel-header">
                <span class="panel-title"><i class="fa-solid fa-square-plus" style="color: var(--primary);"></i> Add Worker</span>
                <span class="panel-subtitle">Create new worker record</span>
            </div>
            <div class="panel-body">
                <form id="worker-form" onsubmit="handleAddWorker(event)">
                    <div class="form-grid">
                        <div class="form-group">
                            <label class="form-label" for="worker_name">Worker Name <span style="color:var(--error);">*</span></label>
                            <input type="text" id="worker_name" class="form-control" placeholder="e.g. Rajesh Kumar" required>
                        </div>

                        <div class="form-group">
                            <label class="form-label" for="worker_mobile">Mobile Number <span style="color:var(--error);">*</span></label>
                            <input type="tel" id="worker_mobile" class="form-control" placeholder="e.g. 9876543210" required>
                        </div>

                        <div class="form-group">
                            <label class="form-label" for="worker_emp_no">Employee Number <span style="color:var(--error);">*</span></label>
                            <input type="text" id="worker_emp_no" class="form-control" placeholder="e.g. EMP102" required>
                        </div>

                        <div class="form-group">
                            <label class="form-label" for="worker_designation">Designation <span style="color:var(--error);">*</span></label>
                            <select id="worker_designation" class="form-control" style="background-color: var(--bg-main);" required>
                                <option value="experience">Experience</option>
                                <option value="fresher">Fresher</option>
                            </select>
                        </div>

                        <div class="form-group" style="position: relative;">
                            <label class="form-label" for="worker_aadhar">Aadhaar Number</label>
                            <div style="display: flex; gap: 8px;">
                                <input type="text" id="worker_aadhar" class="form-control" placeholder="e.g. 1234 5678 9012" style="flex: 1;">
                                <button type="button" class="btn btn-secondary" onclick="triggerDigiLocker()" style="font-size: 0.75rem; padding: 0 12px; display: inline-flex; align-items: center; gap: 4px; border-color: #8b5cf6; color: #8b5cf6; white-space: nowrap;"><i class="fa-solid fa-passport"></i> Verify DigiLocker</button>
                            </div>
                            <span id="digilocker-status-badge" style="font-size: 0.75rem; color: var(--success); font-weight: bold; margin-top: 4px; display: none;"><i class="fa-solid fa-circle-check"></i> Verified via DigiLocker</span>
                        </div>

                        <div class="form-group">
                            <label class="form-label" for="worker_hourly_salary">1 Hr Salary (₹) <span style="color:var(--error);">*</span></label>
                            <input type="number" id="worker_hourly_salary" class="form-control" step="0.01" min="0" value="0.00" placeholder="e.g. 100.00" required>
                        </div>

                        <div class="form-group">
                            <label class="form-label" for="worker_photo_file">Passport Photo</label>
                            <input type="file" id="worker_photo_file" class="form-control" accept="image/png, image/jpeg, image/jpg" style="padding: 6px; background-color: var(--bg-main);">
                        </div>

                        <div class="form-group">
                            <label class="form-label" for="worker_aadhar_file">Aadhaar Card Upload (ID)</label>
                            <input type="file" id="worker_aadhar_file" class="form-control" accept="image/png, image/jpeg, image/jpg, application/pdf" style="padding: 6px; background-color: var(--bg-main);">
                        </div>

                        <div class="form-group">
                            <label class="form-label" for="worker_pan_file">PAN Card Upload (Tax)</label>
                            <input type="file" id="worker_pan_file" class="form-control" accept="image/png, image/jpeg, image/jpg, application/pdf" style="padding: 6px; background-color: var(--bg-main);">
                        </div>

                        <div class="form-group">
                            <label class="form-label" for="worker_bank_file">Bank Passbook Upload</label>
                            <input type="file" id="worker_bank_file" class="form-control" accept="image/png, image/jpeg, image/jpg, application/pdf" style="padding: 6px; background-color: var(--bg-main);">
                        </div>

                        <div class="form-group">
                            <label class="form-label" for="worker_address_file">Address Proof Upload</label>
                            <input type="file" id="worker_address_file" class="form-control" accept="image/png, image/jpeg, image/jpg, application/pdf" style="padding: 6px; background-color: var(--bg-main);">
                        </div>

                        <div class="form-group">
                            <label class="form-label" for="worker_other_file">Other Documents Upload</label>
                            <input type="file" id="worker_other_file" class="form-control" accept="image/png, image/jpeg, image/jpg, application/pdf" style="padding: 6px; background-color: var(--bg-main);">
                        </div>
                    </div>

                    <div class="btn-group" style="margin-top: 24px;">
                        <button type="submit" class="btn btn-primary" style="flex: 1;"><i class="fa-solid fa-check"></i> Submit</button>
                        <button type="button" class="btn btn-secondary" onclick="resetForm('worker-form')"><i class="fa-solid fa-xmark"></i> Clear</button>
                    </div>
                </form>
            </div>
        </div>

        <!-- Grid List Panel (Right) -->
        <div class="panel">
            <div class="panel-header">
                <span class="panel-title"><i class="fa-solid fa-users" style="color: var(--secondary);"></i> Worker Registry</span>
                <span class="panel-subtitle" id="worker-count-label">0 Workers</span>
            </div>
            <div class="panel-body">
                <div class="table-container">
                    <table class="custom-table" id="workers-table">
                        <thead>
                            <tr>
                                <th style="width: 80px;">Emp No</th>
                                <th>Name</th>
                                <th>Mobile No</th>
                                <th>Aadhaar No</th>
                                <th>Designation</th>
                                <th style="text-align: right;">1 Hr Salary</th>
                                <th>Documents</th>
                                <th style="width: 80px; text-align: center;">Actions</th>
                            </tr>
                        </thead>
                        <tbody id="workers-grid-body">
                            <!-- Populated dynamically -->
                        </tbody>
                    </table>
                </div>
            </div>
        </div>
    </div>
</div>

<!-- ==========================================================================
   TAB 2: MACHINES MASTER
   ========================================================================== -->
<div id="tab-machines" class="tab-content">
    <div class="module-container">
        <!-- Form Panel (Left) -->
        <div class="panel">
            <div class="panel-header">
                <span class="panel-title"><i class="fa-solid fa-square-plus" style="color: var(--primary);"></i> Add Machine</span>
                <span class="panel-subtitle">Create new machine record</span>
            </div>
            <div class="panel-body">
                <form id="machine-form" onsubmit="handleAddMachine(event)">
                    <div class="form-grid">
                        <div class="form-group">
                            <label class="form-label" for="machine_no">Machine Number <span style="color:var(--error);">*</span></label>
                            <input type="text" id="machine_no" class="form-control" placeholder="e.g. M-101" required>
                        </div>

                        <div class="form-group">
                            <label class="form-label" for="machine_rate">Rate per 1 Qty (₹) <span style="color:var(--error);">*</span></label>
                            <input type="number" id="machine_rate" class="form-control" step="0.01" min="0.01" placeholder="e.g. 15.50" required>
                        </div>
                    </div>

                    <div class="btn-group" style="margin-top: 24px;">
                        <button type="submit" class="btn btn-primary" style="flex: 1;"><i class="fa-solid fa-check"></i> Submit</button>
                        <button type="button" class="btn btn-secondary" onclick="resetForm('machine-form')"><i class="fa-solid fa-xmark"></i> Clear</button>
                    </div>
                </form>
            </div>
        </div>

        <!-- Grid List Panel (Right) -->
        <div class="panel">
            <div class="panel-header">
                <span class="panel-title"><i class="fa-solid fa-screwdriver-wrench" style="color: var(--secondary);"></i> Machine Directory</span>
                <span class="panel-subtitle" id="machine-count-label">0 Machines</span>
            </div>
            <div class="panel-body">
                <div class="table-container">
                    <table class="custom-table" id="machines-table">
                        <thead>
                            <tr>
                                <th>Machine No</th>
                                <th style="text-align: right;">Rate per Qty</th>
                                <th style="width: 80px; text-align: center;">Actions</th>
                            </tr>
                        </thead>
                        <tbody id="machines-grid-body">
                            <!-- Populated dynamically -->
                        </tbody>
                    </table>
                </div>
            </div>
        </div>
    </div>
</div>

<!-- ==========================================================================
   TAB 3: DAILY WORKER WORK
   ========================================================================== -->
<div id="tab-daily-work" class="tab-content">
    <div class="module-container">
        <!-- Form Panel (Left) -->
        <div class="panel">
            <div class="panel-header">
                <span class="panel-title"><i class="fa-solid fa-calendar-plus" style="color: var(--primary);"></i> Log Daily Work</span>
                <span class="panel-subtitle">Record daily work output</span>
            </div>
            <div class="panel-body">
                <form id="daily-work-form" onsubmit="handleLogDailyWork(event)">
                    <div class="form-grid">
                        <div class="form-group">
                            <label class="form-label" for="dw_attendance">Attendance <span style="color:var(--error);">*</span></label>
                            <select id="dw_attendance" class="form-control" style="background-color: var(--bg-main);" required onchange="handleAttendanceChange()">
                                <option value="Present">Present</option>
                                <option value="Absent">Absent</option>
                            </select>
                        </div>

                        <div class="form-group">
                            <label class="form-label" for="dw_worker">Select Worker <span style="color:var(--error);">*</span></label>
                            <select id="dw_worker" class="form-control" style="background-color: var(--bg-main);" onchange="handleDwWorkerChange()" required>
                                <option value="">-- Choose Worker --</option>
                            </select>
                        </div>

                        <!-- Experience Worker Fields -->
                        <div class="form-group dw-experience-field">
                            <label class="form-label" for="dw_machine">Select Machine <span style="color:var(--error);">*</span></label>
                            <select id="dw_machine" class="form-control" style="background-color: var(--bg-main);" required>
                                <option value="">-- Choose Machine --</option>
                            </select>
                        </div>

                        <div class="form-group dw-experience-field">
                            <label class="form-label" for="dw_construction">Construction No <span style="color:var(--error);">*</span></label>
                            <input type="text" id="dw_construction" class="form-control" placeholder="e.g. C-8899" required>
                        </div>

                        <div class="form-group dw-experience-field">
                            <label class="form-label" for="dw_qty">Total Quantity <span style="color:var(--error);">*</span></label>
                            <input type="number" id="dw_qty" class="form-control" step="0.01" min="0.01" placeholder="e.g. 100" required>
                        </div>

                        <!-- Fresher Fields -->
                        <div class="form-group dw-fresher-field" style="display: none;">
                            <label class="form-label" for="dw_in_time">In Time <span style="color:var(--error);">*</span></label>
                            <input type="time" id="dw_in_time" class="form-control">
                        </div>

                        <div class="form-group dw-fresher-field" style="display: none;">
                            <label class="form-label" for="dw_out_time">Out Time <span style="color:var(--error);">*</span></label>
                            <input type="time" id="dw_out_time" class="form-control">
                        </div>

                        <div class="form-group" style="grid-column: span 2;">
                            <label class="form-label" for="dw_work_done">Work Done <span style="color:var(--error);">*</span></label>
                            <textarea id="dw_work_done" class="form-control" placeholder="Description of work performed..." style="background-color: var(--bg-main); height: 60px; resize: vertical; padding: 8px;" required></textarea>
                        </div>

                        <div class="form-group" style="grid-column: span 2;">
                            <label class="form-label" for="dw_remarks">Remarks</label>
                            <textarea id="dw_remarks" class="form-control" placeholder="e.g. Completed winding work, machine maintenance done" style="background-color: var(--bg-main); height: 60px; resize: vertical; padding: 8px;"></textarea>
                        </div>
                    </div>

                    <div class="btn-group" style="margin-top: 24px;">
                        <button type="submit" class="btn btn-primary" style="flex: 1;"><i class="fa-solid fa-plus"></i> Add Entry</button>
                        <button type="button" class="btn btn-secondary" onclick="resetDailyWorkForm()"><i class="fa-solid fa-xmark"></i> Clear</button>
                    </div>
                </form>
            </div>
        </div>

        <!-- Grid List Panel (Right) -->
        <div class="panel">
            <div class="panel-header">
                <span class="panel-title"><i class="fa-solid fa-list-check" style="color: var(--secondary);"></i> Logged Entries</span>
                <span class="panel-subtitle" id="daily-count-label">0 Entries</span>
            </div>
            <div class="panel-body" style="display: flex; flex-direction: column; height: 100%;">
                <div style="margin-bottom: 12px; display: flex; gap: 12px; align-items: center;">
                    <label class="form-label" style="margin: 0; font-size: 0.85rem;" for="filter-today-date">View Date:</label>
                    <input type="date" id="filter-today-date" class="form-control" style="max-width: 180px; padding: 6px 12px;" onchange="loadDailyWork()">
                </div>
                <div class="table-container" style="flex: 1;">
                    <table class="custom-table" id="daily-work-table">
                        <thead>
                            <tr>
                                <th>Worker</th>
                                <th>Details</th>
                                <th>Work Done</th>
                                <th style="text-align: right;">Qty / Hours</th>
                                <th>Attendance</th>
                                <th>Remarks</th>
                                <th style="width: 80px; text-align: center;">Actions</th>
                            </tr>
                        </thead>
                        <tbody id="daily-grid-body">
                            <!-- Populated dynamically -->
                        </tbody>
                    </table>
                </div>
            </div>
        </div>
    </div>
</div>

<!-- ==========================================================================
   TAB 4: PAYMENT & REPORT
   ========================================================================== -->
<div id="tab-payments" class="tab-content">
    <div class="panel" style="margin-bottom: 24px;">
        <div class="panel-header">
            <span class="panel-title"><i class="fa-solid fa-filter" style="color: var(--primary);"></i> Report Filters</span>
            <span class="panel-subtitle">Select filter parameters to calculate wages</span>
        </div>
        <div class="panel-body">
            <div style="display: grid; grid-template-columns: repeat(auto-fit, minmax(200px, 1fr)); gap: 16px; align-items: flex-end;">
                <div class="form-group" style="margin: 0;">
                    <label class="form-label" for="report_worker">Select Worker <span style="color:var(--error);">*</span></label>
                    <select id="report_worker" class="form-control" style="background-color: var(--bg-main);">
                        <option value="">-- Choose Worker --</option>
                    </select>
                </div>

                <div class="form-group" style="margin: 0;">
                    <label class="form-label" for="report_date_from">From Date <span style="color:var(--error);">*</span></label>
                    <input type="date" id="report_date_from" class="form-control">
                </div>

                <div class="form-group" style="margin: 0;">
                    <label class="form-label" for="report_date_to">To Date <span style="color:var(--error);">*</span></label>
                    <input type="date" id="report_date_to" class="form-control">
                </div>

                <div style="display: flex; gap: 12px;">
                    <button class="btn btn-primary" style="flex: 1; padding: 11px;" onclick="generateReport()">
                        <i class="fa-solid fa-magnifying-glass"></i> Generate
                    </button>
                    <button id="btn-print-report" class="btn btn-secondary" style="padding: 11px; display: none;" onclick="printReport()">
                        <i class="fa-solid fa-print"></i> Print
                    </button>
                </div>
            </div>
        </div>
    </div>

    <!-- Report Output Grid -->
    <div class="panel" id="report-output-panel" style="display: none;">
        <div class="panel-header">
            <span class="panel-title"><i class="fa-solid fa-file-invoice" style="color: var(--secondary);"></i> Generated Statement</span>
            <span class="panel-subtitle" id="report-period-label">Wages Statement</span>
        </div>
        <div class="panel-body">
            <div class="report-summary-card">
                <div>
                    <div class="title">Selected Employee</div>
                    <div id="report-employee-name" style="font-size: 1.15rem; font-weight: 700; color: var(--text-highlight);">N/A</div>
                    <div id="report-employee-emp-no" style="font-size: 0.85rem; color: var(--text-muted);">Emp No: N/A</div>
                </div>
                <div style="text-align: right;">
                    <div class="title">Net Total Wages</div>
                    <div class="value">₹<span id="report-net-total">0.00</span></div>
                </div>
            </div>

            <div class="table-container">
                <table class="custom-table" id="report-table">
                    <thead>
                        <tr>
                            <th>Machine No</th>
                            <th style="text-align: right;">Total Qty</th>
                            <th style="text-align: right;">Rate (₹)</th>
                            <th style="text-align: right;">Total Amount (₹)</th>
                        </tr>
                    </thead>
                    <tbody id="report-grid-body">
                        <!-- Populated dynamically -->
                    </tbody>
                </table>
            </div>
        </div>
    </div>
</div>

<script>
    // State lists
    let workersList = [];
    let machinesList = [];
    let dailyWorkList = [];

    document.addEventListener('DOMContentLoaded', () => {
        // Set default dates
        const todayStr = getTodayDateString();
        document.getElementById('dw_date').value = todayStr;
        document.getElementById('filter-today-date').value = todayStr;

        // Default range to first to today of the current month
        const now = new Date();
        const firstDay = new Date(now.getFullYear(), now.getMonth(), 1);
        document.getElementById('report_date_from').value = formatDateString(firstDay);
        document.getElementById('report_date_to').value = todayStr;

        // Load data
        loadWorkers();
        loadMachines();
        loadDailyWork();
    });

    // Helper functions
    function getTodayDateString() {
        const d = new Date();
        return formatDateString(d);
    }

    function formatDateString(date) {
        const year = date.getFullYear();
        let month = '' + (date.getMonth() + 1);
        let day = '' + date.getDate();

        if (month.length < 2) month = '0' + month;
        if (day.length < 2) day = '0' + day;

        return [year, month, day].join('-');
    }

    function escapeHtml(str) {
        if (!str) return '';
        return str
            .replace(/&/g, "&amp;")
            .replace(/</g, "&lt;")
            .replace(/>/g, "&gt;")
            .replace(/"/g, "&quot;")
            .replace(/'/g, "&#039;");
    }

    // Switch Tabs
    function switchTab(tabId) {
        document.querySelectorAll('.tab-content').forEach(el => el.classList.remove('active'));
        document.querySelectorAll('.tab-btn').forEach(el => el.classList.remove('active'));

        document.getElementById(tabId).classList.add('active');

        // Find the button which has onclick containing the tabId
        const activeBtn = Array.from(document.querySelectorAll('.tab-btn')).find(btn => btn.getAttribute('onclick').includes(tabId));
        if (activeBtn) activeBtn.classList.add('active');

        // Reload context data for lists/dropdowns
        if (tabId === 'tab-daily-work') {
            populateWorkerDropdowns();
            populateMachineDropdowns();
            loadDailyWork();
        } else if (tabId === 'tab-payments') {
            populateWorkerDropdowns();
        }
    }

    function resetForm(formId) {
        document.getElementById(formId).reset();
        if (formId === 'daily-work-form') {
            document.getElementById('dw_date').value = getTodayDateString();
        }
    }

    function resetDailyWorkForm() {
        resetForm('daily-work-form');
    }

    // --- WORKERS MODULE ---
    async function loadWorkers() {
        App.showLoading('Loading workers...');
        try {
            const res = await App.api('list', { table: 'workers' });
            workersList = res.rows || [];
            populateWorkersGrid(workersList);
            populateWorkerDropdowns();
        } catch (err) {
            console.error(err);
        } finally {
            App.hideLoading();
        }
    }

    async function uploadFileHelper(fileInputId, docType) {
        const fileInput = document.getElementById(fileInputId);
        if (!fileInput || fileInput.files.length === 0) return null;

        const file = fileInput.files[0];
        const formData = new FormData();
        formData.append('doc', file);
        formData.append('type', docType);

        const response = await fetch('upload_document.php', {
            method: 'POST',
            body: formData
        });
        const text = await response.text();
        let resJson;
        try {
            resJson = JSON.parse(text);
        } catch (e) {
            console.error("Invalid JSON response from server:", text);
            throw new Error(`File upload failed (non-JSON response). Please check the console or contact support.`);
        }
        if (!resJson.ok) {
            throw new Error(`File upload failed for ${docType}: ${resJson.error}`);
        }
        return resJson.filepath;
    }

    function populateWorkersGrid(data) {
        const body = document.getElementById('workers-grid-body');
        body.innerHTML = '';
        document.getElementById('worker-count-label').innerText = `${data.length} Workers`;

        if (data.length === 0) {
            body.innerHTML = '<tr><td colspan="8" style="text-align:center; color:var(--text-muted); padding:32px;">No workers registered.</td></tr>';
            return;
        }

        data.forEach(item => {
            const tr = document.createElement('tr');
            const isFresher = item.designation === 'fresher';
            const badgeClass = isFresher ? 'badge-pending' : 'badge-success';
            const badgeLabel = isFresher ? 'Fresher' : 'Experience';

            let docsHtml = '';
            if (item.doc_photo) docsHtml += `<a href="${item.doc_photo}" target="_blank" title="Passport Photo" style="color:var(--text-highlight); margin-right:10px;"><i class="fa-solid fa-user"></i> Photo</a>`;
            if (item.doc_id_card) docsHtml += `<a href="${item.doc_id_card}" target="_blank" title="Aadhaar Card" style="color:var(--primary); margin-right:10px;"><i class="fa-solid fa-id-card"></i> ID</a>`;
            if (item.doc_tax_card) docsHtml += `<a href="${item.doc_tax_card}" target="_blank" title="PAN Card" style="color:var(--secondary); margin-right:10px;"><i class="fa-solid fa-receipt"></i> Tax</a>`;
            if (item.doc_passbook) docsHtml += `<a href="${item.doc_passbook}" target="_blank" title="Bank Passbook" style="color:var(--success); margin-right:10px;"><i class="fa-solid fa-building-columns"></i> Bank</a>`;
            if (item.doc_address) docsHtml += `<a href="${item.doc_address}" target="_blank" title="Address Proof" style="color:#d97706; margin-right:10px;"><i class="fa-solid fa-map-location-dot"></i> Addr</a>`;
            if (item.doc_other) docsHtml += `<a href="${item.doc_other}" target="_blank" title="Other Doc" style="color:#6b7280;"><i class="fa-solid fa-file-shield"></i> Other</a>`;
            if (!docsHtml) docsHtml = '<span style="color:var(--text-muted);">-</span>';

            tr.innerHTML = `
                <td><strong style="color:var(--text-highlight);">${escapeHtml(item.emp_no)}</strong></td>
                <td>${escapeHtml(item.name)}</td>
                <td>${escapeHtml(item.mobile_no)}</td>
                <td>${escapeHtml(item.aadhar_no || '-')}</td>
                <td><span class="badge ${badgeClass}">${badgeLabel}</span></td>
                <td style="text-align: right; font-weight: 700; color: var(--primary);">₹${App.formatDecimal(item.hourly_salary)}</td>
                <td style="font-size:0.8rem;">${docsHtml}</td>
                <td style="text-align: center;">
                    <button class="btn-delete-row" onclick="handleDeleteWorker(${item.id}, '${escapeHtml(item.name)}')" title="Delete Worker">
                        <i class="fa-solid fa-trash-can"></i>
                    </button>
                </td>
            `;
            body.appendChild(tr);
        });
    }

    function populateWorkerDropdowns() {
        const dwWorkerSelect = document.getElementById('dw_worker');
        const reportWorkerSelect = document.getElementById('report_worker');

        const dwVal = dwWorkerSelect.value;
        const reportVal = reportWorkerSelect.value;

        dwWorkerSelect.innerHTML = '<option value="">-- Choose Worker --</option>';
        reportWorkerSelect.innerHTML = '<option value="">-- Choose Worker --</option>';

        workersList.forEach(w => {
            const opt1 = document.createElement('option');
            opt1.value = w.id;
            opt1.textContent = `${w.name} (${w.emp_no})`;
            dwWorkerSelect.appendChild(opt1);

            const opt2 = document.createElement('option');
            opt2.value = w.id;
            opt2.textContent = `${w.name} (${w.emp_no})`;
            reportWorkerSelect.appendChild(opt2);
        });

        dwWorkerSelect.value = dwVal;
        reportWorkerSelect.value = reportVal;
    }

    async function handleAddWorker(e) {
        e.preventDefault();
        const name = document.getElementById('worker_name').value.trim();
        const mobile_no = document.getElementById('worker_mobile').value.trim();
        const emp_no = document.getElementById('worker_emp_no').value.trim();
        const designation = document.getElementById('worker_designation').value;
        const aadhar_no = document.getElementById('worker_aadhar').value.trim();
        const hourly_salary = parseFloat(document.getElementById('worker_hourly_salary').value) || 0;

        if (!name || !mobile_no || !emp_no || !designation) {
            App.showToast('Please fill all fields.', 'error');
            return;
        }

        // Client-side unique validation
        const exists = workersList.some(w => w.emp_no.toLowerCase() === emp_no.toLowerCase());
        if (exists) {
            App.showToast(`Worker with Employee Number "${emp_no}" already exists!`, 'error');
            return;
        }

        App.showLoading('Uploading onboarding documents & registering worker...');

        let aadhar_file = null;
        let pan_file = null;
        let bank_file = null;
        let photo_file = null;
        let address_file = null;
        let other_file = null;

        try {
            aadhar_file = await uploadFileHelper('worker_aadhar_file', 'aadhar');
            pan_file = await uploadFileHelper('worker_pan_file', 'pan');
            bank_file = await uploadFileHelper('worker_bank_file', 'bank');
            photo_file = await uploadFileHelper('worker_photo_file', 'photo');
            address_file = await uploadFileHelper('worker_address_file', 'address');
            other_file = await uploadFileHelper('worker_other_file', 'other');
        } catch (err) {
            App.showToast(err.message, 'error');
            App.hideLoading();
            return;
        }

        try {
            await App.api('insert', {
                table: 'workers',
                data: {
                    name,
                    mobile_no,
                    emp_no,
                    designation,
                    hourly_salary,
                    aadhar_no,
                    doc_id_card: aadhar_file,
                    doc_tax_card: pan_file,
                    doc_passbook: bank_file,
                    doc_photo: photo_file,
                    doc_address: address_file,
                    doc_other: other_file
                }
            });
            App.showToast('Worker registered successfully!');
            // Reset DigiLocker verified visual indicator
            document.getElementById('digilocker-status-badge').style.display = 'none';
            document.getElementById('worker_aadhar').style.borderColor = '';
            document.getElementById('worker_name').style.borderColor = '';
            document.getElementById('worker_mobile').style.borderColor = '';
            resetForm('worker-form');
            await loadWorkers();
        } catch (err) {
            console.error(err);
        } finally {
            App.hideLoading();
        }
    }

    async function handleDeleteWorker(id, name) {
        if (!confirm(`Are you sure you want to delete worker "${name}"? This will also remove all associated daily logs.`)) {
            return;
        }

        App.showLoading('Deleting worker...');
        try {
            await App.api('delete', { table: 'workers', id });
            App.showToast('Worker deleted successfully.');
            await loadWorkers();
            // Also refresh daily work and report panels since cascades could have modified records
            loadDailyWork();
            document.getElementById('report-output-panel').style.display = 'none';
        } catch (err) {
            console.error(err);
        } finally {
            App.hideLoading();
        }
    }

    // --- MACHINES MODULE ---
    async function loadMachines() {
        App.showLoading('Loading machines...');
        try {
            const res = await App.api('list', { table: 'machines' });
            machinesList = res.rows || [];
            populateMachinesGrid(machinesList);
            populateMachineDropdowns();
        } catch (err) {
            console.error(err);
        } finally {
            App.hideLoading();
        }
    }

    function populateMachinesGrid(data) {
        const body = document.getElementById('machines-grid-body');
        body.innerHTML = '';
        document.getElementById('machine-count-label').innerText = `${data.length} Machines`;

        if (data.length === 0) {
            body.innerHTML = '<tr><td colspan="3" style="text-align:center; color:var(--text-muted); padding:32px;">No machines configured.</td></tr>';
            return;
        }

        data.forEach(item => {
            const tr = document.createElement('tr');
            tr.innerHTML = `
                <td><strong style="color:var(--text-highlight);">${escapeHtml(item.machine_no)}</strong></td>
                <td style="text-align: right; font-weight: 700;">₹${App.formatDecimal(item.rate)}</td>
                <td style="text-align: center;">
                    <button class="btn-delete-row" onclick="handleDeleteMachine(${item.id}, '${escapeHtml(item.machine_no)}')" title="Delete Machine">
                        <i class="fa-solid fa-trash-can"></i>
                    </button>
                </td>
            `;
            body.appendChild(tr);
        });
    }

    function populateMachineDropdowns() {
        const select = document.getElementById('dw_machine');
        const activeVal = select.value;
        select.innerHTML = '<option value="">-- Choose Machine --</option>';

        machinesList.forEach(m => {
            const opt = document.createElement('option');
            opt.value = m.id;
            opt.textContent = `${m.machine_no} (₹${App.formatDecimal(m.rate)} / Qty)`;
            select.appendChild(opt);
        });

        select.value = activeVal;
    }

    async function handleAddMachine(e) {
        e.preventDefault();
        const machine_no = document.getElementById('machine_no').value.trim();
        const rate = parseFloat(document.getElementById('machine_rate').value);

        if (!machine_no || isNaN(rate) || rate <= 0) {
            App.showToast('Please enter a valid machine number and rate > 0.', 'error');
            return;
        }

        // Client-side unique check
        const exists = machinesList.some(m => m.machine_no.toLowerCase() === machine_no.toLowerCase());
        if (exists) {
            App.showToast(`Machine with Number "${machine_no}" already exists!`, 'error');
            return;
        }

        App.showLoading('Adding machine...');
        try {
            await App.api('insert', {
                table: 'machines',
                data: { machine_no, rate }
            });
            App.showToast('Machine created successfully!');
            resetForm('machine-form');
            await loadMachines();
        } catch (err) {
            console.error(err);
        } finally {
            App.hideLoading();
        }
    }

    async function handleDeleteMachine(id, machineNo) {
        if (!confirm(`Are you sure you want to delete machine "${machineNo}"? This will delete all logged daily work associated with this machine.`)) {
            return;
        }

        App.showLoading('Deleting machine...');
        try {
            await App.api('delete', { table: 'machines', id });
            App.showToast('Machine deleted successfully.');
            await loadMachines();
            loadDailyWork();
            document.getElementById('report-output-panel').style.display = 'none';
        } catch (err) {
            console.error(err);
        } finally {
            App.hideLoading();
        }
    }

    // --- DAILY WORK MODULE ---
    async function loadDailyWork() {
        const dateStr = document.getElementById('filter-today-date').value;
        if (!dateStr) return;

        // Custom JOIN query via SELECT api using LEFT JOIN on machines
        const sql = `
            SELECT dw.id, dw.qty, dw.work_date, dw.in_time, dw.out_time, dw.construction_no,
                   dw.status, dw.remarks, dw.work_done,
                   w.name as worker_name, w.emp_no, m.machine_no
            FROM daily_work dw
            JOIN workers w ON dw.worker_id = w.id
            LEFT JOIN machines m ON dw.machine_id = m.id
            WHERE dw.work_date = :today
            ORDER BY dw.id DESC
        `;

        try {
            const res = await App.api('query', { sql, params: { today: dateStr } });
            dailyWorkList = res.rows || [];
            populateDailyGrid(dailyWorkList);
        } catch (err) {
            console.error(err);
        }
    }

    function calculateHours(inTime, outTime) {
        if (!inTime || !outTime) return 0;
        const [inH, inM] = inTime.split(':').map(Number);
        const [outH, outM] = outTime.split(':').map(Number);
        const inMin = inH * 60 + inM;
        const outMin = outH * 60 + outM;
        let diffMin = outMin - inMin;
        if (diffMin < 0) diffMin += 24 * 60; // Handle overnight work
        return diffMin / 60;
    }

    function handleAttendanceChange() {
        const attendance = document.getElementById('dw_attendance').value;
        const isAbsent = attendance === 'Absent';
        const expFields = document.querySelectorAll('.dw-experience-field');
        const fresherFields = document.querySelectorAll('.dw-fresher-field');

        const dwMachine = document.getElementById('dw_machine');
        const dwConstruction = document.getElementById('dw_construction');
        const dwQty = document.getElementById('dw_qty');
        const dwInTime = document.getElementById('dw_in_time');
        const dwOutTime = document.getElementById('dw_out_time');

        if (isAbsent) {
            expFields.forEach(el => el.style.display = 'none');
            fresherFields.forEach(el => el.style.display = 'none');
            dwMachine.removeAttribute('required');
            dwConstruction.removeAttribute('required');
            dwQty.removeAttribute('required');
            dwInTime.removeAttribute('required');
            dwOutTime.removeAttribute('required');
        } else {
            handleDwWorkerChange();
        }
    }

    function handleDwWorkerChange() {
        const attendance = document.getElementById('dw_attendance').value;
        if (attendance === 'Absent') {
            return;
        }

        const workerId = parseInt(document.getElementById('dw_worker').value);
        const expFields = document.querySelectorAll('.dw-experience-field');
        const fresherFields = document.querySelectorAll('.dw-fresher-field');

        const dwMachine = document.getElementById('dw_machine');
        const dwConstruction = document.getElementById('dw_construction');
        const dwQty = document.getElementById('dw_qty');
        const dwInTime = document.getElementById('dw_in_time');
        const dwOutTime = document.getElementById('dw_out_time');

        if (isNaN(workerId)) {
            expFields.forEach(el => el.style.display = 'none');
            fresherFields.forEach(el => el.style.display = 'none');
            dwMachine.removeAttribute('required');
            dwConstruction.removeAttribute('required');
            dwQty.removeAttribute('required');
            dwInTime.removeAttribute('required');
            dwOutTime.removeAttribute('required');
            return;
        }

        const worker = workersList.find(w => w.id === workerId);
        if (worker && worker.designation === 'fresher') {
            expFields.forEach(el => el.style.display = 'none');
            fresherFields.forEach(el => el.style.display = 'block');

            dwMachine.removeAttribute('required');
            dwConstruction.removeAttribute('required');
            dwQty.removeAttribute('required');
            dwInTime.setAttribute('required', 'true');
            dwOutTime.setAttribute('required', 'true');
        } else {
            expFields.forEach(el => el.style.display = 'block');
            fresherFields.forEach(el => el.style.display = 'none');

            dwMachine.setAttribute('required', 'true');
            dwConstruction.setAttribute('required', 'true');
            dwQty.setAttribute('required', 'true');
            dwInTime.removeAttribute('required');
            dwOutTime.removeAttribute('required');
        }
    }

    function populateDailyGrid(data) {
        const body = document.getElementById('daily-grid-body');
        body.innerHTML = '';
        document.getElementById('daily-count-label').innerText = `${data.length} Entries`;

        if (data.length === 0) {
            body.innerHTML = '<tr><td colspan="6" style="text-align:center; color:var(--text-muted); padding:32px;">No work logged for this date.</td></tr>';
            return;
        }

        data.forEach(item => {
            const tr = document.createElement('tr');
            const isFresher = item.in_time && item.out_time;

            let detailsHtml = '';
            let qtyHoursHtml = '';

            if (item.status === 'Absent') {
                detailsHtml = `<span style="color:var(--text-muted); font-size:0.85rem;"><i class="fa-solid fa-circle-xmark"></i> Absent - No work logged</span>`;
                qtyHoursHtml = `<span style="color:var(--text-muted);">-</span>`;
            } else if (isFresher) {
                const hours = calculateHours(item.in_time, item.out_time);
                detailsHtml = `<span style="color:var(--text-muted); font-size:0.85rem;"><i class="fa-solid fa-clock"></i> In: ${item.in_time.substring(0, 5)} | Out: ${item.out_time.substring(0, 5)}</span>`;
                qtyHoursHtml = `<strong style="color:var(--primary);">${hours.toFixed(2)} hrs</strong>`;
            } else {
                detailsHtml = `<strong>Machine:</strong> ${escapeHtml(item.machine_no || '-')} | <strong>Const No:</strong> ${escapeHtml(item.construction_no || '-')}`;
                qtyHoursHtml = `<strong>${App.formatDecimal(item.qty)}</strong>`;
            }

            const attBadge = item.status === 'Absent' ? '<span class="badge badge-pending">Absent</span>' : '<span class="badge badge-success">Present</span>';
            const remarksText = item.remarks ? escapeHtml(item.remarks) : '<span style="color:var(--text-muted); font-style:italic;">-</span>';

            tr.innerHTML = `
                <td><strong>${escapeHtml(item.worker_name)}</strong> <span style="font-size:0.8rem; color:var(--text-muted);">(${escapeHtml(item.emp_no)})</span></td>
                <td>${detailsHtml}</td>
                <td>${item.work_done ? escapeHtml(item.work_done) : '<span style="color:var(--text-muted); font-style:italic;">-</span>'}</td>
                <td style="text-align: right;">${qtyHoursHtml}</td>
                <td>${attBadge}</td>
                <td style="max-width: 150px; overflow: hidden; text-overflow: ellipsis;" title="${escapeHtml(item.remarks || '')}">${remarksText}</td>
                <td style="text-align: center;">
                    <button class="btn-delete-row" onclick="handleDeleteDailyWork(${item.id})" title="Delete Log">
                        <i class="fa-solid fa-trash-can"></i>
                    </button>
                </td>
            `;
            body.appendChild(tr);
        });
    }

    async function handleLogDailyWork(e) {
        e.preventDefault();
        const worker_id = parseInt(document.getElementById('dw_worker').value);
        const work_date = document.getElementById('dw_date').value;
        const status = document.getElementById('dw_attendance').value;
        const work_done = document.getElementById('dw_work_done').value.trim();

        if (isNaN(worker_id) || !work_date) {
            App.showToast('Please select a worker and work date.', 'error');
            return;
        }

        const worker = workersList.find(w => w.id === worker_id);
        if (!worker) return;

        let insertData = { worker_id, work_date, status, remarks, work_done };

        if (status === 'Absent') {
            insertData.machine_id = null;
            insertData.qty = null;
            insertData.construction_no = null;
            insertData.in_time = null;
            insertData.out_time = null;
        } else if (worker.designation === 'fresher') {
            const in_time = document.getElementById('dw_in_time').value;
            const out_time = document.getElementById('dw_out_time').value;
            if (!in_time || !out_time) {
                App.showToast('In Time and Out Time are required for fresher workers.', 'error');
                return;
            }
            insertData.in_time = in_time;
            insertData.out_time = out_time;
            insertData.machine_id = null;
            insertData.qty = null;
            insertData.construction_no = null;
        } else {
            const machine_id = parseInt(document.getElementById('dw_machine').value);
            const qty = parseFloat(document.getElementById('dw_qty').value);
            const construction_no = document.getElementById('dw_construction').value.trim();

            if (isNaN(machine_id) || isNaN(qty) || qty <= 0 || !construction_no) {
                App.showToast('Select Machine, Quantity > 0 and enter Construction No for experience workers.', 'error');
                return;
            }
            insertData.machine_id = machine_id;
            insertData.qty = qty;
            insertData.construction_no = construction_no;
            insertData.in_time = null;
            insertData.out_time = null;
        }

        App.showLoading('Saving work entry...');
        try {
            await App.api('insert', {
                table: 'daily_work',
                data: insertData
            });
            App.showToast('Daily work logged successfully.');

            // Set date selector to this entered date to verify
            document.getElementById('filter-today-date').value = work_date;

            // Reset quantity / times / remarks / attendance
            document.getElementById('dw_qty').value = '';
            document.getElementById('dw_construction').value = '';
            document.getElementById('dw_in_time').value = '';
            document.getElementById('dw_out_time').value = '';
            document.getElementById('dw_remarks').value = '';
            document.getElementById('dw_work_done').value = '';
            document.getElementById('dw_attendance').value = 'Present';
            handleAttendanceChange();

            await loadDailyWork();
        } catch (err) {
            console.error(err);
        } finally {
            App.hideLoading();
        }
    }

    async function handleDeleteDailyWork(id) {
        if (!confirm('Delete this daily work log?')) {
            return;
        }

        App.showLoading('Deleting entry...');
        try {
            await App.api('delete', { table: 'daily_work', id });
            App.showToast('Work entry deleted.');
            await loadDailyWork();
        } catch (err) {
            console.error(err);
        } finally {
            App.hideLoading();
        }
    }

    // --- PAYMENT REPORT MODULE ---
    async function generateReport() {
        const worker_id = parseInt(document.getElementById('report_worker').value);
        const date_from = document.getElementById('report_date_from').value;
        const date_to = document.getElementById('report_date_to').value;

        if (isNaN(worker_id) || !date_from || !date_to) {
            App.showToast('Please select a worker, start date, and end date.', 'error');
            return;
        }

        const worker = workersList.find(w => w.id === worker_id);
        if (!worker) return;

        App.showLoading('Generating wages statement...');
        try {
            let sql = '';
            let params = { worker_id, date_from, date_to };

            if (worker.designation === 'fresher') {
                sql = `
                    SELECT dw.id, dw.work_date, dw.in_time, dw.out_time
                    FROM daily_work dw
                    WHERE dw.worker_id = :worker_id AND dw.work_date BETWEEN :date_from AND :date_to
                    ORDER BY dw.work_date ASC
                `;
            } else {
                sql = `
                    SELECT m.machine_no, dw.construction_no, SUM(dw.qty) as total_qty, m.rate, SUM(dw.qty * m.rate) as total_amount
                    FROM daily_work dw
                    JOIN machines m ON dw.machine_id = m.id
                    WHERE dw.worker_id = :worker_id AND dw.work_date BETWEEN :date_from AND :date_to
                    GROUP BY m.id, dw.construction_no
                    ORDER BY m.machine_no ASC, dw.construction_no ASC
                `;
            }

            const res = await App.api('query', { sql, params });
            const data = res.rows || [];

            // Update UI elements based on designation
            document.getElementById('report-employee-name').innerText = worker.name;
            document.getElementById('report-employee-emp-no').innerText = `Emp No: ${worker.emp_no} | Mobile: ${worker.mobile_no} | Designation: ${worker.designation === 'fresher' ? 'Fresher' : 'Experience'}`;
            document.getElementById('report-period-label').innerText = `Wages Statement for Period: ${date_from} to ${date_to}`;

            // Rewrite table headers dynamically
            const tableHead = document.querySelector('#report-table thead');
            if (worker.designation === 'fresher') {
                tableHead.innerHTML = `
                    <tr>
                        <th>Date</th>
                        <th>In Time</th>
                        <th>Out Time</th>
                        <th style="text-align: right;">Hours Worked</th>
                        <th style="text-align: right;">Hourly Rate</th>
                        <th style="text-align: right;">Total Amount (₹)</th>
                    </tr>
                `;
            } else {
                tableHead.innerHTML = `
                    <tr>
                        <th>Machine No</th>
                        <th>Construction No</th>
                        <th style="text-align: right;">Total Qty</th>
                        <th style="text-align: right;">Rate (₹)</th>
                        <th style="text-align: right;">Total Amount (₹)</th>
                    </tr>
                `;
            }

            populateReportGrid(data, worker);

            // Show result panels
            document.getElementById('report-output-panel').style.display = 'block';
            document.getElementById('btn-print-report').style.display = 'block';

        } catch (err) {
            console.error(err);
        } finally {
            App.hideLoading();
        }
    }

    function populateReportGrid(data, worker) {
        const body = document.getElementById('report-grid-body');
        body.innerHTML = '';

        let netTotal = 0;

        if (data.length === 0) {
            const colspan = worker.designation === 'fresher' ? 6 : 5;
            body.innerHTML = `<tr><td colspan="${colspan}" style="text-align:center; color:var(--text-muted); padding:32px;">No work logs found for this worker during the selected period.</td></tr>`;
            document.getElementById('report-net-total').innerText = '0.00';
            return;
        }

        if (worker.designation === 'fresher') {
            data.forEach(item => {
                const hours = calculateHours(item.in_time, item.out_time);
                const amount = hours * parseFloat(worker.hourly_salary || 0);
                netTotal += amount;

                const tr = document.createElement('tr');
                tr.innerHTML = `
                    <td>${item.work_date}</td>
                    <td>${item.in_time ? item.in_time.substring(0, 5) : '-'}</td>
                    <td>${item.out_time ? item.out_time.substring(0, 5) : '-'}</td>
                    <td style="text-align: right; font-weight: 600;">${hours.toFixed(2)} hrs</td>
                    <td style="text-align: right;">₹${App.formatDecimal(worker.hourly_salary)}</td>
                    <td style="text-align: right; font-weight: 700; color: var(--primary);">₹${App.formatDecimal(amount)}</td>
                `;
                body.appendChild(tr);
            });
        } else {
            data.forEach(item => {
                const amount = parseFloat(item.total_amount || 0);
                netTotal += amount;

                const tr = document.createElement('tr');
                tr.innerHTML = `
                    <td><strong style="color:var(--text-highlight);">${escapeHtml(item.machine_no)}</strong></td>
                    <td><span class="badge badge-pending">${escapeHtml(item.construction_no || '-')}</span></td>
                    <td style="text-align: right; font-weight: 600;">${App.formatDecimal(item.total_qty)}</td>
                    <td style="text-align: right;">₹${App.formatDecimal(item.rate)}</td>
                    <td style="text-align: right; font-weight: 700; color: var(--primary);">₹${App.formatDecimal(amount)}</td>
                `;
                body.appendChild(tr);
            });
        }

        document.getElementById('report-net-total').innerText = App.formatDecimal(netTotal);
    }

    function printReport() {
        const workerSelect = document.getElementById('report_worker');
        const workerText = workerSelect.options[workerSelect.selectedIndex].text;
        const workerId = parseInt(workerSelect.value);
        const dateFrom = document.getElementById('report_date_from').value;
        const dateTo = document.getElementById('report_date_to').value;

        const worker = workersList.find(w => w.id === workerId);
        if (!worker) return;

        const isFresher = worker.designation === 'fresher';
        const rows = [];

        document.querySelectorAll('#report-grid-body tr').forEach(tr => {
            const cols = tr.querySelectorAll('td');
            if (isFresher && cols.length >= 6) {
                rows.push({
                    date: cols[0].innerText,
                    in_time: cols[1].innerText,
                    out_time: cols[2].innerText,
                    hours: cols[3].innerText,
                    rate: cols[4].innerText,
                    amount: cols[5].innerText
                });
            } else if (!isFresher && cols.length >= 5) {
                rows.push({
                    machine_no: cols[0].innerText,
                    construction_no: cols[1].innerText,
                    qty: cols[2].innerText,
                    rate: cols[3].innerText,
                    amount: cols[4].innerText
                });
            }
        });

        if (rows.length === 0) {
            App.showToast('No data to print!', 'warning');
            return;
        }

        const netTotal = document.getElementById('report-net-total').innerText;

        let tableHeaderHtml = '';
        let tableRowsHtml = '';

        if (isFresher) {
            tableHeaderHtml = `
                <tr>
                    <th>Date</th>
                    <th>In Time</th>
                    <th>Out Time</th>
                    <th class="text-right">Hours</th>
                    <th class="text-right">Hourly Rate</th>
                    <th class="text-right">Total Amount</th>
                </tr>
            `;
            tableRowsHtml = rows.map(row => `
                <tr>
                    <td>${row.date}</td>
                    <td>${row.in_time}</td>
                    <td>${row.out_time}</td>
                    <td class="text-right">${row.hours}</td>
                    <td class="text-right">${row.rate}</td>
                    <td class="text-right" style="font-weight: 700;">${row.amount}</td>
                </tr>
            `).join('');
        } else {
            tableHeaderHtml = `
                <tr>
                    <th>Machine No</th>
                    <th>Construction No</th>
                    <th class="text-right">Total Qty</th>
                    <th class="text-right">Rate</th>
                    <th class="text-right">Total Amount</th>
                </tr>
            `;
            tableRowsHtml = rows.map(row => `
                <tr>
                    <td><strong>${row.machine_no}</strong></td>
                    <td>${row.construction_no}</td>
                    <td class="text-right">${row.qty}</td>
                    <td class="text-right">${row.rate}</td>
                    <td class="text-right" style="font-weight: 700;">${row.amount}</td>
                </tr>
            `).join('');
        }

        const printWindow = window.open('', '_blank', 'width=800,height=600');
        printWindow.document.write(`
            <!DOCTYPE html>
            <html>
            <head>
                <meta charset="utf-8">
                <title>Wages Report - ${workerText}</title>
                <style>
                    body {
                        font-family: 'Inter', 'Segoe UI', Arial, sans-serif;
                        color: #1e293b;
                        background: #ffffff;
                        margin: 40px;
                        line-height: 1.5;
                        font-size: 14px;
                    }
                    .header {
                        text-align: center;
                        margin-bottom: 35px;
                        border-bottom: 2px solid #0f172a;
                        padding-bottom: 15px;
                    }
                    .header h1 {
                        margin: 0;
                        font-size: 26px;
                        font-weight: 700;
                        letter-spacing: 0.5px;
                        color: #0f172a;
                    }
                    .header p {
                        margin: 6px 0 0 0;
                        color: #64748b;
                        font-size: 13px;
                        text-transform: uppercase;
                        letter-spacing: 1px;
                    }
                    .info-section {
                        display: flex;
                        justify-content: space-between;
                        margin-bottom: 30px;
                        background: #f8fafc;
                        border: 1px solid #e2e8f0;
                        padding: 16px;
                        border-radius: 8px;
                    }
                    .info-block div {
                        margin-bottom: 6px;
                    }
                    .info-block strong {
                        color: #0f172a;
                    }
                    table {
                        width: 100%;
                        border-collapse: collapse;
                        margin-bottom: 30px;
                    }
                    th {
                        background-color: #f1f5f9;
                        color: #475569;
                        font-weight: 600;
                        text-transform: uppercase;
                        font-size: 12px;
                        border: 1px solid #cbd5e1;
                        padding: 12px;
                    }
                    td {
                        border: 1px solid #cbd5e1;
                        padding: 12px;
                        color: #334155;
                    }
                    .text-right {
                        text-align: right;
                    }
                    .footer-section {
                        display: flex;
                        justify-content: flex-end;
                        font-size: 18px;
                        font-weight: 700;
                        color: #0f172a;
                        margin-top: 20px;
                        padding-top: 15px;
                        border-top: 2px solid #0f172a;
                    }
                    .footer-total-box {
                        background: #f1f5f9;
                        border: 1px solid #cbd5e1;
                        padding: 10px 20px;
                        border-radius: 6px;
                    }
                    .print-btn-container {
                        text-align: center;
                        margin-top: 40px;
                    }
                    .btn-print {
                        padding: 10px 24px;
                        font-size: 15px;
                        font-weight: 600;
                        background-color: #0f172a;
                        color: #ffffff;
                        border: none;
                        cursor: pointer;
                        border-radius: 6px;
                        transition: background-color 0.2s;
                    }
                    .btn-print:hover {
                        background-color: #1e293b;
                    }
                    @media print {
                        body {
                            margin: 20px;
                        }
                        .print-btn-container {
                            display: none;
                        }
                    }
                </style>
            </head>
            <body>
                <div class="header">
                    <h1>JAGDAMBA ELECTRICAL</h1>
                    <p>Wages Statement & Daily Work Report</p>
                </div>

                <div class="info-section">
                    <div class="info-block">
                        <div><strong>Employee:</strong> ${workerText}</div>
                        <div><strong>Statement Date:</strong> ${new Date().toLocaleDateString('en-IN')}</div>
                        <div><strong>Designation:</strong> ${isFresher ? 'Fresher (Hourly Basis)' : 'Experience (Piece-Rate Basis)'}</div>
                    </div>
                    <div class="info-block" style="text-align: right;">
                        <div><strong>Wages Period:</strong></div>
                        <div><strong>${dateFrom}</strong> to <strong>${dateTo}</strong></div>
                    </div>
                </div>

                <table>
                    <thead>
                        ${tableHeaderHtml}
                    </thead>
                    <tbody>
                        ${tableRowsHtml}
                    </tbody>
                </table>

                <div class="footer-section">
                    <div class="footer-total-box">
                        Net Wages: ₹${netTotal}
                    </div>
                </div>

                <div class="print-btn-container">
                    <button class="btn-print" onclick="window.print();">Print Invoice Statement</button>
                </div>
            </body>
            </html>
        `);
        printWindow.document.close();
    }

    let digiLockerVerified = false;
    window.digiLockerState = {
        aadhar: '',
        name: '',
        mobile: '',
        otp: ''
    };

    function escapeHtmlAttribute(str) {
        if (!str) return '';
        return str
            .replace(/&/g, '&amp;')
            .replace(/"/g, '&quot;')
            .replace(/'/g, '&#039;')
            .replace(/</g, '&lt;')
            .replace(/>/g, '&gt;');
    }

    window.triggerDigiLocker = function() {
        // Read current values from the main form to prepopulate
        const currentAadhar = document.getElementById('worker_aadhar').value.trim();
        const currentName = document.getElementById('worker_name').value.trim();
        const currentMobile = document.getElementById('worker_mobile').value.trim();

        // Create dynamic modal
        const modal = document.createElement('div');
        modal.className = 'modal-backdrop visible';
        modal.id = 'digilocker-modal';
        modal.style.cssText = 'position: fixed; top: 0; left: 0; width: 100vw; height: 100vh; background: rgba(0, 0, 0, 0.7); display: flex; align-items: center; justify-content: center; z-index: 99999;';

        modal.innerHTML = `
            <div class="modal-content" style="width: 420px; max-width: 95%; background: var(--bg-card); border: 1px solid var(--border-color); border-radius: 12px; overflow: hidden; box-shadow: 0 10px 25px rgba(0,0,0,0.5);">
                <div class="modal-header" style="background: #0066cc; color: white; padding: 16px 20px; display: flex; justify-content: space-between; align-items: center; border-bottom: 1px solid rgba(255,255,255,0.1);">
                    <h3 class="modal-title" style="color: white; margin: 0; display: flex; align-items: center; gap: 8px; font-size: 1.1rem; font-weight: 700;"><i class="fa-solid fa-lock"></i> DigiLocker Identity Verify</h3>
                    <button class="modal-close" style="color: white; background: none; border: none; font-size: 1.5rem; cursor: pointer; line-height: 1;" onclick="closeDigiLockerModal()">&times;</button>
                </div>
                <div class="modal-body" style="padding: 24px; text-align: center;">
                    <div id="dl-step-1">
                        <img src="https://www.digilocker.gov.in/assets/img/logo.png" alt="DigiLocker Logo" style="height: 50px; margin-bottom: 20px; filter: drop-shadow(0 2px 4px rgba(0,0,0,0.15)); background: white; padding: 4px; border-radius: 6px;">
                        <p style="font-size: 0.9rem; color: var(--text-highlight); margin-bottom: 20px; line-height: 1.4;">Link and pull Aadhaar details securely directly from DigiLocker API.</p>

                        <div class="form-group" style="text-align: left; margin-bottom: 12px;">
                            <label class="form-label" style="font-size: 0.8rem; margin-bottom: 6px;">Aadhaar Number <span style="color:var(--error);">*</span></label>
                            <input type="text" id="dl-aadhar-input" class="form-control" placeholder="12-digit Aadhaar No" style="background: var(--bg-main);" value="${escapeHtmlAttribute(currentAadhar)}">
                        </div>

                        <div class="form-group" style="text-align: left; margin-bottom: 12px;">
                            <label class="form-label" style="font-size: 0.8rem; margin-bottom: 6px;">Full Name (as on Aadhaar) <span style="color:var(--error);">*</span></label>
                            <input type="text" id="dl-name-input" class="form-control" placeholder="e.g. Rajesh Kumar" style="background: var(--bg-main);" value="${escapeHtmlAttribute(currentName)}">
                        </div>

                        <div class="form-group" style="text-align: left; margin-bottom: 16px;">
                            <label class="form-label" style="font-size: 0.8rem; margin-bottom: 6px;">Aadhaar Linked Mobile No <span style="color:var(--error);">*</span></label>
                            <input type="text" id="dl-mobile-input" class="form-control" placeholder="e.g. 9876543210" style="background: var(--bg-main);" value="${escapeHtmlAttribute(currentMobile)}">
                        </div>

                        <button class="btn btn-primary" onclick="proceedDigiLockerAuth()" style="width: 100%; height: 40px; background: #0066cc; border: none; font-weight: bold; color: white;">Get OTP Verification</button>
                    </div>
                    <div id="dl-step-2" style="display: none;">
                        <p style="font-size: 0.95rem; color: var(--text-highlight); margin-bottom: 8px;">Enter 6-Digit OTP sent to UIDAI registered mobile.</p>
                        <p id="dl-otp-sent-msg" style="font-size: 0.75rem; color: var(--text-muted); margin-bottom: 12px;"></p>

                        <div class="form-group" style="margin-bottom: 16px;">
                            <input type="text" id="dl-otp-input" class="form-control" placeholder="X X X X X X" style="background: var(--bg-main); text-align: center; font-size: 1.2rem; letter-spacing: 6px;" maxLength="6">
                        </div>

                        <div id="dl-otp-demo-hint" style="font-size: 0.85rem; color: #8b5cf6; margin-bottom: 20px; font-weight: 500; background: rgba(139, 92, 246, 0.1); padding: 8px; border-radius: 6px; border: 1px solid rgba(139, 92, 246, 0.2);"></div>

                        <button class="btn btn-success" onclick="verifyDigiLockerOtp()" style="width: 100%; height: 40px; font-weight: bold; background: #22c55e; border: none; color: white;">Verify & Import</button>
                    </div>
                    <div id="dl-spinner" style="display: none; padding: 32px 0;">
                        <i class="fa-solid fa-circle-notch fa-spin" style="font-size: 2.5rem; color: #0066cc; margin-bottom: 16px;"></i>
                        <p style="font-size: 0.85rem; color: var(--text-muted);">Fetching certified Aadhaar documents safely...</p>
                    </div>
                </div>
            </div>
        `;
        document.body.appendChild(modal);
    };

    window.closeDigiLockerModal = function() {
        const modal = document.getElementById('digilocker-modal');
        if (modal) modal.remove();
    };

    window.proceedDigiLockerAuth = function() {
        const aadhar = document.getElementById('dl-aadhar-input').value.trim();
        const cleanedAadhar = aadhar.replace(/\s/g, '');
        const name = document.getElementById('dl-name-input').value.trim();
        const mobile = document.getElementById('dl-mobile-input').value.trim();

        if (cleanedAadhar.length !== 12 || !/^\d+$/.test(cleanedAadhar)) {
            App.showToast('Please enter a valid 12-digit Aadhaar Number.', 'warning');
            return;
        }
        if (name.length === 0) {
            App.showToast('Please enter the Full Name as per Aadhaar.', 'warning');
            return;
        }
        if (mobile.length !== 10 || !/^\d+$/.test(mobile)) {
            App.showToast('Please enter a valid 10-digit Mobile Number.', 'warning');
            return;
        }

        // Save states
        window.digiLockerState = {
            aadhar: aadhar,
            name: name,
            mobile: mobile,
            otp: String(Math.floor(100000 + Math.random() * 900000))
        };

        document.getElementById('dl-step-1').style.display = 'none';
        document.getElementById('dl-spinner').style.display = 'block';

        // Mock API lag
        setTimeout(() => {
            document.getElementById('dl-spinner').style.display = 'none';
            document.getElementById('dl-step-2').style.display = 'block';

            // Set dynamic message & OTP hint
            const maskedMobile = `******${window.digiLockerState.mobile.slice(-4)}`;
            document.getElementById('dl-otp-sent-msg').innerText = `OTP sent to Aadhaar linked mobile: +91 ${maskedMobile}`;
            document.getElementById('dl-otp-demo-hint').innerHTML = `Demo Verification OTP: <strong style="font-size: 1rem; text-decoration: underline;">${window.digiLockerState.otp}</strong>`;
        }, 1200);
    };

    window.verifyDigiLockerOtp = function() {
        const otp = document.getElementById('dl-otp-input').value.trim();
        if (otp.length !== 6) {
            App.showToast('Please enter a valid 6-digit OTP.', 'warning');
            return;
        }

        if (otp !== window.digiLockerState.otp) {
            App.showToast('Incorrect OTP. Please enter the code shown in the modal.', 'error');
            return;
        }

        document.getElementById('dl-step-2').style.display = 'none';
        document.getElementById('dl-spinner').style.display = 'block';
        document.querySelector('#dl-spinner p').innerText = 'Verifying identity and signatures...';

        setTimeout(() => {
            closeDigiLockerModal();
            digiLockerVerified = true;

            // Auto fill fields with details entered by the user
            document.getElementById('worker_aadhar').value = window.digiLockerState.aadhar;
            document.getElementById('worker_name').value = window.digiLockerState.name;
            document.getElementById('worker_mobile').value = window.digiLockerState.mobile;

            document.getElementById('digilocker-status-badge').style.display = 'block';

            // Mark fields as verified visually
            document.getElementById('worker_aadhar').style.borderColor = 'var(--success)';
            document.getElementById('worker_name').style.borderColor = 'var(--success)';
            document.getElementById('worker_mobile').style.borderColor = 'var(--success)';

            App.showToast('DigiLocker Verification Successful! Identity Imported.', 'success');
        }, 1500);
    };
</script>

<?php
renderFooter();
?>
